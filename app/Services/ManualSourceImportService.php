<?php

namespace App\Services;

use App\Data\SourceImportContext;
use App\Exceptions\InvalidSourceWorkbook;
use App\Models\ManualSourceImportPreview;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionIssue;
use App\Models\User;
use App\Support\ManualSourceImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManualSourceImportService
{
    public function __construct(
        private readonly XlsxSourceReader $reader,
        private readonly ManualSourceWorkbookContract $contract,
        private readonly ManualSourceImportAnalysisService $analysis,
        private readonly ManualSourceImportFingerprintService $fingerprints,
        private readonly SourceProjectionImportService $importer,
    ) {}

    public function preview(User $actor, UploadedFile $file): ManualSourceImportPreview
    {
        $this->ensureOfficeStaff($actor);
        $disk = (string) config('manual_source_import.storage_disk', 'local');
        $directory = trim((string) config('manual_source_import.storage_directory'), '/');
        $path = $directory.'/'.Str::uuid().'.xlsx';
        $originalFilename = $this->safeOriginalFilename($file->getClientOriginalName());

        if (! Storage::disk($disk)->putFileAs($directory, $file, basename($path))) {
            throw new \RuntimeException('The workbook could not be stored for preview.');
        }

        try {
            $absolutePath = Storage::disk($disk)->path($path);
            $sha256 = hash_file('sha256', $absolutePath);
            $workbook = $this->reader->read($absolutePath);
            $analysis = $this->analysis->analyse(ManualSourceImport::SOURCE_NAMESPACE, $workbook);
            $maximumRows = (int) config('manual_source_import.max_rows', 5000);
            if (count($workbook->rows) > $maximumRows) {
                throw new InvalidSourceWorkbook([[
                    'code' => 'ROW_LIMIT_EXCEEDED',
                    'message' => "The workbook exceeds the {$maximumRows}-row import limit.",
                    'blocking' => true,
                ]]);
            }

            return ManualSourceImportPreview::query()->create([
                'initiated_by_user_id' => $actor->id,
                'source_namespace' => ManualSourceImport::SOURCE_NAMESPACE,
                'original_filename' => $originalFilename,
                'content_sha256' => $sha256,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'workbook_contract_fingerprint' => $this->contract->fingerprint(),
                'source_fingerprint' => $analysis->sourceFingerprint,
                'status' => ManualSourceImport::PREVIEW_STATUS_READY,
                'metadata' => [
                    'original_filename' => $originalFilename,
                    'sha256' => $sha256,
                    'row_count' => count($workbook->rows),
                    'namespace' => ManualSourceImport::SOURCE_NAMESPACE,
                    'worksheet' => $workbook->worksheet,
                    'blank_row_count' => $workbook->blankRowCount,
                    'expires_at' => now()->addMinutes((int) config('manual_source_import.preview_ttl_minutes', 30))->toIso8601String(),
                ],
                'summary' => $analysis->summary,
                'rows' => $analysis->rows,
                'blocking_error_count' => $analysis->blockingErrorCount,
                'expires_at' => now()->addMinutes((int) config('manual_source_import.preview_ttl_minutes', 30)),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function commit(User $actor, ManualSourceImportPreview $preview, string $confirmedSha256): SourceImportRun
    {
        $this->ensureOwnedPreview($actor, $preview);
        $disk = Storage::disk($preview->storage_disk);
        $importStarted = false;
        $representedSiteKeys = [];

        try {
            $run = DB::transaction(function () use ($actor, $preview, $confirmedSha256, $disk, &$importStarted, &$representedSiteKeys): SourceImportRun {
                $lockedPreview = ManualSourceImportPreview::query()->lockForUpdate()->findOrFail($preview->id);
                $this->ensureOwnedPreview($actor, $lockedPreview);

                if ($lockedPreview->status === ManualSourceImport::PREVIEW_STATUS_COMMITTED && $lockedPreview->source_import_run_id !== null) {
                    return $lockedPreview->importRun()->firstOrFail();
                }

                if ($lockedPreview->status !== ManualSourceImport::PREVIEW_STATUS_READY) {
                    throw new \DomainException('This preview is no longer available for commit.');
                }

                if ($lockedPreview->isExpired()) {
                    throw new \DomainException('This preview has expired. Upload the workbook again.');
                }

                if (! hash_equals($lockedPreview->content_sha256, mb_strtolower(trim($confirmedSha256)))) {
                    throw new \DomainException('The confirmed workbook fingerprint does not match this preview.');
                }

                if (! hash_equals($lockedPreview->workbook_contract_fingerprint, $this->contract->fingerprint())) {
                    throw new \DomainException('The workbook contract changed after preview. Upload the workbook again.');
                }

                if (! $disk->exists($lockedPreview->storage_path)) {
                    throw new \DomainException('The temporary workbook is no longer available. Upload it again.');
                }

                $absolutePath = $disk->path($lockedPreview->storage_path);
                if (! hash_equals($lockedPreview->content_sha256, hash_file('sha256', $absolutePath))) {
                    throw new \DomainException('The temporary workbook fingerprint changed after preview.');
                }

                $workbook = $this->reader->read($absolutePath);
                $analysis = $this->analysis->analyse($lockedPreview->source_namespace, $workbook);
                if ($analysis->blockingErrorCount > 0) {
                    throw new InvalidSourceWorkbook($this->blockingErrors($analysis->rows));
                }

                $currentFingerprint = $this->fingerprints->for($lockedPreview->source_namespace, $analysis->representedSiteKeys);
                if (! hash_equals($lockedPreview->source_fingerprint, $analysis->sourceFingerprint)
                    || ! hash_equals($lockedPreview->source_fingerprint, $currentFingerprint)) {
                    throw new \DomainException('The relevant source projections or site bindings changed after preview. Upload and preview the workbook again.');
                }

                $representedSiteKeys = $analysis->representedSiteKeys;
                $importStarted = true;
                $run = $this->importer->import(
                    $lockedPreview->source_namespace,
                    $analysis->records,
                    $lockedPreview->content_sha256,
                    new SourceImportContext(
                        $actor->id,
                        $lockedPreview->original_filename,
                        $lockedPreview->content_sha256,
                        $analysis->representedSiteKeys,
                    ),
                );

                $lockedPreview->update([
                    'status' => ManualSourceImport::PREVIEW_STATUS_COMMITTED,
                    'summary' => $analysis->summary,
                    'rows' => $analysis->rows,
                    'blocking_error_count' => 0,
                    'committed_at' => now(),
                    'source_import_run_id' => $run->id,
                ]);

                return $run;
            });
        } catch (\Throwable $exception) {
            if ($importStarted) {
                $failedRun = SourceImportRun::query()->create([
                    'source_name' => $preview->source_namespace,
                    'source_version' => $preview->content_sha256,
                    'initiated_by_user_id' => $actor->id,
                    'original_filename' => $preview->original_filename,
                    'content_sha256' => $preview->content_sha256,
                    'source_scope' => ['represented_site_keys' => $representedSiteKeys],
                    'status' => 'failed',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'safe_error_summary' => 'Manual source import did not complete. See application logs.',
                ]);
                $preview->update([
                    'status' => ManualSourceImport::PREVIEW_STATUS_FAILED,
                    'source_import_run_id' => $failedRun->id,
                ]);
                $disk->delete($preview->storage_path);
            }

            throw $exception;
        }

        $disk->delete($preview->storage_path);

        return $run;
    }

    /** @return array<string, mixed> */
    public function previewView(User $actor, ManualSourceImportPreview $preview): array
    {
        $this->ensureOwnedPreview($actor, $preview);
        if ($preview->status === ManualSourceImport::PREVIEW_STATUS_READY && $preview->isExpired()) {
            $this->expire($preview);
        }

        return [
            'preview_uuid' => $preview->uuid,
            'status' => $preview->status,
            'metadata' => $preview->metadata,
            'summary' => $preview->summary,
            'blocking_error_count' => $preview->blocking_error_count,
            'can_commit' => $preview->status === ManualSourceImport::PREVIEW_STATUS_READY && ! $preview->isExpired() && $preview->blocking_error_count === 0,
            'rows' => $preview->rows,
            'result_uuid' => $preview->importRun?->uuid,
        ];
    }

    /** @return array<string, mixed> */
    public function resultView(User $actor, SourceImportRun $run): array
    {
        $this->ensureOfficeStaff($actor);
        $preview = ManualSourceImportPreview::query()->where('source_import_run_id', $run->id)->first();

        return [
            'result_uuid' => $run->uuid,
            'namespace' => $run->source_name,
            'original_filename' => $run->original_filename,
            'sha256' => $run->content_sha256,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'counts' => [
                'seen' => $run->records_seen,
                'applied' => $run->records_applied,
                'created' => $run->records_created,
                'updated' => $run->records_updated,
                'unchanged' => $run->records_unchanged,
                'missing' => $run->records_missing,
                'rejected' => $run->records_rejected,
                'reconciliation' => $run->reconciliation_issue_count,
            ],
            'scope' => $run->source_scope,
            'rows' => $preview?->rows ?? [],
            'reconciliation' => SourceProjectionIssue::query()
                ->where('source_import_run_id', $run->id)
                ->orderBy('id')
                ->get()
                ->map(fn (SourceProjectionIssue $issue): array => $this->issueView($issue))
                ->all(),
        ];
    }

    public function pruneExpired(): int
    {
        $count = 0;
        ManualSourceImportPreview::query()
            ->where('status', ManualSourceImport::PREVIEW_STATUS_READY)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($previews) use (&$count): void {
                foreach ($previews as $preview) {
                    $this->expire($preview);
                    $count++;
                }
            });

        return $count;
    }

    private function ensureOwnedPreview(User $actor, ManualSourceImportPreview $preview): void
    {
        $this->ensureOfficeStaff($actor);
        if ((int) $preview->initiated_by_user_id !== (int) $actor->id) {
            abort(404);
        }
    }

    private function ensureOfficeStaff(User $actor): void
    {
        if (! $actor->hasCompletePortalProfile() || ! $actor->isFensterOfficeStaff() || $actor->is_preview_user) {
            abort(403);
        }
    }

    private function expire(ManualSourceImportPreview $preview): void
    {
        Storage::disk($preview->storage_disk)->delete($preview->storage_path);
        $preview->update(['status' => ManualSourceImport::PREVIEW_STATUS_EXPIRED]);
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = trim(str_replace(["\0", '/', '\\'], '', basename($filename)));

        return mb_substr($filename === '' ? 'source-import.xlsx' : $filename, 0, 255);
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<array{code: string, message: string, blocking: bool}>
     */
    private function blockingErrors(array $rows): array
    {
        return collect($rows)
            ->flatMap(fn (array $row) => $row['errors'])
            ->where('blocking', true)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function issueView(SourceProjectionIssue $issue): array
    {
        $messages = [
            'unknown_call_type' => 'An unknown Call Type was rejected.',
            'association_changed' => 'A permanent Call No. identity conflicted with its existing projection.',
            'completion_date_missing' => 'Completion was reported without a Completed Date.',
            'duplicate_call_number' => 'A duplicate Call No. was rejected.',
            'missing_source_record' => 'A known Call No. was absent and retained from its last known source state.',
            'unsafe_completion_reversal' => 'A completion reversal requires reconciliation because a newer active request exists.',
            'invalid_source_record' => 'An invalid source row was rejected.',
        ];
        $blocking = in_array($issue->issue_type->value, ['unknown_call_type', 'association_changed', 'duplicate_call_number', 'invalid_source_record'], true);

        return [
            'issue_uuid' => $issue->uuid,
            'severity' => $blocking ? 'error' : 'warning',
            'message' => $messages[$issue->issue_type->value],
            'source_row_number' => $issue->context['source_row_number'] ?? null,
            'call_number' => $issue->source_call_number,
            'source_site_key' => $issue->context['source_site_key'] ?? null,
            'plot_reference' => $issue->context['plot_reference'] ?? null,
            'blocking' => $blocking,
            'resolved' => $issue->resolved_at !== null,
        ];
    }
}
