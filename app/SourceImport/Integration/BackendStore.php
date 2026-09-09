<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;

final class BackendStore
{
    public const MAX_ROWS = 500;

    public const IDENTITY = ['application' => 'customerapp.wald-import-backend.v2', 'projection' => 'customerapp.wald-source-projection.v2',
        'hashing' => 'customerapp.wald-transient-evidence-digest.v2', 'max_rows' => self::MAX_ROWS];

    public function run(KnowledgeScope $scope, string $uuid, bool $lock = false): object
    {
        $q = DB::table('wald_import_runs')->where($scope->columns())->where('uuid', $uuid);

        return ($lock ? $q->lockForUpdate() : $q)->firstOrFail();
    }

    public function stage(object $run): array
    {
        $stage = DB::table('wald_import_stages')->where('id', $run->stage_id)->where('run_id', $run->id)->firstOrFail();
        $manifest = $this->payload($stage, 'manifest', 'manifest_hash');
        $records = DB::table('wald_staged_rows')->where('stage_id', $stage->id)->orderBy('ordinal')->limit(self::MAX_ROWS + 1)->get();
        if ($records->count() !== (int) $stage->row_count || $records->count() > self::MAX_ROWS) {
            throw new ImportConflict('staging_integrity_error');
        }
        $rows = $records->map(fn ($row) => $this->payload($row))->all();
        if (Canonical::evidenceHash(array_column($rows, 'canonical')) !== $stage->canonical_hash) {
            throw new ImportConflict('staging_content_changed');
        }

        return [$stage, $manifest, $rows];
    }

    public function payload(object $record, string $field = 'payload', string $hash = 'payload_hash'): array
    {
        $data = json_decode($record->$field, true, flags: JSON_THROW_ON_ERROR);
        if (! hash_equals($record->$hash, Canonical::hash($data))) {
            throw new ImportConflict('immutable_payload_integrity');
        }

        return $data;
    }

    public function state(object $run, string $state, array $changes = []): void
    {
        DB::table('wald_import_runs')->where('id', $run->id)->update([...$changes, 'state' => $state, 'epoch' => $changes['epoch'] ?? $run->epoch + 1, 'updated_at' => now('UTC')]);
    }
}
