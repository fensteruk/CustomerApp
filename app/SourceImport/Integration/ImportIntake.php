<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportIntake
{
    public function upload(User $actor, KnowledgeScope $scope, UploadedFile $file, ExportOrder $order, string $confirmation, string $command, string $coverage = 'PARTIAL_FILTERED_EXPORT', ?string $predecessor = null, ?string $reason = null): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'upload');
        if ($confirmation !== ExportOrder::CONFIRMATION || ! in_array($coverage, ['PARTIAL_FILTERED_EXPORT', 'SITE_COMPLETE_SNAPSHOT', 'GLOBAL_COMPLETE_SNAPSHOT'], true)) {
            throw new ImportConflict('export_confirmation_required');
        }
        if ($predecessor !== null && (trim($reason ?? '') === '' || mb_strlen($reason) > 2000)) {
            throw new ImportConflict('replacement_reason_required');
        }
        $storage = new PrivateWorkbookStorage;
        $artifact = $storage->store($file);
        try {
            return (new ImportStore)->run($actor, $scope, 'upload', $command, [$artifact['workbook_hash'], $order->key(), $confirmation, $coverage, $predecessor, $reason], function (User $fresh) use ($scope, $artifact, $order, $confirmation, $coverage, $predecessor, $reason): array {
                $streamKey = Canonical::hash([$scope->namespace, $scope->family]);
                DB::table('wald_import_streams')->insertOrIgnore(['identity_hash' => $streamKey, 'source_namespace' => $scope->namespace, 'workbook_family' => $scope->family]);
                $stream = DB::table('wald_import_streams')->where('identity_hash', $streamKey)->lockForUpdate()->firstOrFail();
                $prior = $predecessor ? (new BackendStore)->run($scope, $predecessor, true) : null;
                if ($prior && ($prior->state !== 'COMMITTED' || $prior->export_order !== $order->key() || (int) $prior->stream_id !== (int) $stream->id)) {
                    throw new ImportConflict('invalid_replacement_predecessor');
                }
                $uuid = (string) Str::uuid();
                DB::table('wald_import_runs')->insert([...$scope->columns(), ...$artifact, 'uuid' => $uuid, 'stream_id' => $stream->id,
                    'uploader_id' => $fresh->id, 'uploader_name' => $fresh->name, 'export_date' => $order->date, 'export_slot' => $order->slot,
                    'export_order' => $order->key(), 'confirmation' => $confirmation, 'provenance' => ExportOrder::PROVENANCE, 'coverage' => $coverage,
                    'predecessor_id' => $prior?->id, 'replacement_reason' => $reason, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                if ($prior) {
                    // Invalidate earlier previews immediately; neither source facts nor accepted history is changed.
                    DB::table('wald_import_streams')->where('id', $stream->id)->update(['epoch' => $stream->epoch + 1]);
                }
                $result = ['run' => $uuid, 'state' => 'UPLOADED', 'workbook_hash' => $artifact['workbook_hash']];

                return [$result, [], [...$result, 'predecessor' => $predecessor, 'reason' => $reason]];
            });
        } finally {
            $storage->discardUnregistered($artifact['storage_key']);
        }
    }

    public function status(User $actor, KnowledgeScope $scope, string $uuid): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'audit');
        $run = (new BackendStore)->run($scope, $uuid);

        return ['run' => $run->uuid, 'state' => $run->state, 'generation' => (int) $run->generation, 'epoch' => (int) $run->epoch,
            'failure_code' => $run->failure_code, 'export_date' => $run->export_date, 'export_slot' => $run->export_slot];
    }
}
