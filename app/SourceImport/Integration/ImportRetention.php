<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;

final class ImportRetention
{
    public function hold(User $actor, KnowledgeScope $scope, string $uuid, bool $held, string $reason, string $command): array
    {
        if (trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw new ImportConflict('hold_reason_required');
        }

        return (new ImportStore)->run($actor, $scope, 'audit', $command, [$uuid, $held, $reason], function () use ($scope, $uuid, $held, $reason): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            DB::table('wald_import_runs')->where('id', $run->id)->update(['on_hold' => $held]);

            return [['run' => $uuid, 'on_hold' => $held], ['on_hold' => (bool) $run->on_hold], ['on_hold' => $held, 'reason' => $reason]];
        });
    }

    public function metadata(User $actor, KnowledgeScope $scope, string $uuid): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'audit');
        $run = (new BackendStore)->run($scope, $uuid);
        $hasLiveDependency = ! in_array($run->state, ['FAILED', 'COMMITTED', 'SUPERSEDED'], true);

        return ['run' => $uuid, 'on_hold' => (bool) $run->on_hold, 'active_dependency' => $hasLiveDependency,
            'workbook_retain_until' => $run->workbook_retain_until,
            'age_eligible' => ! $run->on_hold && ! $hasLiveDependency && $run->workbook_retain_until !== null && now('UTC')->greaterThanOrEqualTo($run->workbook_retain_until),
            'bulky_staging_days' => 7, 'preview_valid_hours' => 24, 'preview_payload_days' => 7, 'minimal_committed_audit_years' => 6,
            'automatic_disposal_enabled' => false];
    }
}
