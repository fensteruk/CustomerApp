<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Authenticated, immutable command receipts. No workbook parsing inside this transaction. */
final class ImportStore
{
    public function run(User $actor, KnowledgeScope $scope, string $ability, string $command, array $payload, callable $write): array
    {
        if (! Str::isUuid($command)) {
            throw new \InvalidArgumentException('invalid_import_command');
        }
        $hash = Canonical::hash([$scope->columns(), $ability, $payload]);

        return DB::transaction(function () use ($actor, $scope, $ability, $command, $hash, $write): array {
            $fresh = (new ImportPolicy)->authorize($actor, $scope, $ability, true);
            $prior = DB::table('wald_import_commands')->where('actor_id', $fresh->id)->where('command_uuid', $command)->first();
            if ($prior) {
                if ($prior->command_hash !== $hash || $prior->ability !== $ability) {
                    throw new ImportConflict('import_command_conflict');
                }

                return json_decode(Canonical::json(json_decode($prior->result, true, flags: JSON_THROW_ON_ERROR)), true, flags: JSON_THROW_ON_ERROR);
            }
            [$result, $before, $after] = $write($fresh);
            $result = json_decode(Canonical::json($result), true, flags: JSON_THROW_ON_ERROR);
            (new ImportPolicy)->authorize($fresh, $scope, $ability, true);
            DB::table('wald_import_commands')->insert(['actor_id' => $fresh->id, 'actor_name' => $fresh->name,
                'actor_role' => 'fenster_office_staff', 'command_uuid' => $command, 'command_hash' => $hash,
                'ability' => $ability, 'scope_hash' => Canonical::hash($scope->columns()), 'scope' => Canonical::json($scope->columns()),
                'subject_uuid' => $result['binding'] ?? $result['run'] ?? null,
                'result' => Canonical::json($result), 'before_state' => Canonical::json($before), 'after_state' => Canonical::json($after),
                'created_at' => now('UTC'), 'retain_until' => now('UTC')->addYears(6)]);

            return $result;
        }, DB::getDriverName() === 'mysql' ? 3 : 1); // Only recognised MySQL concurrency errors are retried.
    }
}
