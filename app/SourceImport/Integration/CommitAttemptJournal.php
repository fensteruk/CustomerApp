<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Durable intent + one immutable terminal outcome per actor/command. Not a business receipt. */
final class CommitAttemptJournal
{
    public function execute(User $actor, KnowledgeScope $scope, string $command, string $hash, array $payload, callable $business): array
    {
        // Framework test transactions are isolation only. Runtime callers must use this as an entry boundary.
        if (DB::transactionLevel() !== 0 && ! app()->runningUnitTests()) {
            throw new ImportConflict('commit_requires_top_level_boundary');
        }
        $attempt = $this->register($actor, $scope, $command, $hash, $payload);
        try {
            $answer = DB::transaction(function () use ($actor, $scope, $attempt, $business): array {
                $denial = null;
                try {
                    (new ImportPolicy)->authorize($actor, $scope, 'commit', true);
                } catch (AuthorizationException $e) {
                    $denial = $e;
                }
                DB::table('wald_commit_attempts')->where('id', $attempt->id)->lockForUpdate()->firstOrFail();
                $outcome = DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->first();
                if ($denial) {
                    if (! $outcome) {
                        $this->finish($attempt, 'REFUSED', 'authority_denied');
                    }

                    return ['error' => $denial];
                }
                if ($outcome && $outcome->outcome !== 'SUCCEEDED') {
                    return ['error' => new ImportConflict('recorded_attempt_'.$outcome->category)];
                }
                try {
                    // Roll back business effects to this savepoint, retaining the outer audit lock.
                    $result = DB::transaction(fn () => $business());
                } catch (Throwable $e) {
                    if ($this->transient($e)) {
                        throw $e; // Retry the whole outer transaction, never a partial savepoint.
                    }
                    [$state, $category] = $this->classify($e);
                    $this->finish($attempt, $state, $category);

                    return ['error' => $e];
                }
                if (! $outcome) {
                    $receipt = DB::table('wald_import_receipts')->where('uuid', $result['receipt'])->firstOrFail();
                    $this->finish($attempt, 'SUCCEEDED', (int) $receipt->run_id === (int) $attempt->run_id ? 'commit_or_receipt_replay' : 'canonical_receipt_replay', $receipt->id);
                }

                return ['result' => $result];
            }, DB::getDriverName() === 'mysql' ? 3 : 1);
        } catch (Throwable $e) {
            while ($e instanceof DeadlockException && $e->getPrevious()) {
                $e = $e->getPrevious();
            }
            // Exhausted transient errors or final audit/commit failures roll back all business effects.
            // If another invocation recovered this command meanwhile, preserve that one terminal truth.
            $answer = DB::transaction(function () use ($attempt, $e): array {
                DB::table('wald_commit_attempts')->where('id', $attempt->id)->lockForUpdate()->firstOrFail();
                $outcome = DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->first();
                if (! $outcome) {
                    $this->finish($attempt, 'FAILED', $this->transient($e) ? 'transient_retries_exhausted' : 'persistence_failure');
                }

                return ['error' => $e];
            }, DB::getDriverName() === 'mysql' ? 3 : 1);
        }
        if (isset($answer['error'])) {
            throw $answer['error'];
        }

        return $answer['result'];
    }

    private function register(User $actor, KnowledgeScope $scope, string $command, string $hash, array $payload): object
    {
        return DB::transaction(function () use ($actor, $scope, $command, $hash, $payload): object {
            try {
                $fresh = (new ImportPolicy)->authorize($actor, $scope, 'commit', true);
                $run = (new BackendStore)->run($scope, $payload[0]);
            } catch (AuthorizationException $denial) {
                // Only an existing non-preview participant can leave a linked authority-loss attempt.
                // Unknown/external probes receive the same denial and never gain target information.
                if (! (config('wald_import.enabled') || (new WaldPilotAvailability)->enabled()) || ! $actor->exists || $actor->is_preview_user) {
                    throw $denial;
                }
                $fresh = User::query()->whereKey($actor->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->is_preview_user) {
                    throw $denial;
                }
                $run = DB::table('wald_import_runs')->where($scope->columns())->where('uuid', $payload[0])
                    ->where(function ($q) use ($fresh) {
                        $q->where('uploader_id', $fresh->id)->orWhereExists(fn ($p) => $p->selectRaw('1')->from('wald_import_previews')
                            ->whereColumn('wald_import_previews.run_id', 'wald_import_runs.id')->where('reviewer_id', $fresh->id));
                    })->first();
                if (! $run) {
                    throw $denial;
                }
            }
            $prior = DB::table('wald_commit_attempts')->where('actor_id', $fresh->id)->where('command_uuid', $command)->first();
            if ($prior) {
                if (! hash_equals($prior->command_hash, $hash)) {
                    throw new ImportConflict('import_command_conflict');
                }

                return $prior;
            }
            $preview = DB::table('wald_import_previews')->where('run_id', $run->id)->where('uuid', $payload[1])->first();
            $reviewed = $preview ? json_decode($preview->payload, true) : [];
            $manifest = $reviewed['manifest'] ?? [];
            $bindings = [];
            if ($preview) {
                foreach (DB::table('wald_staged_rows')->where('stage_id', $preview->stage_id)->orderBy('ordinal')->limit(BackendStore::MAX_ROWS + 1)->pluck('payload') as $json) {
                    $binding = json_decode($json, true)['binding'] ?? null;
                    if ($binding !== null) {
                        $bindings[Canonical::hash($binding)] = $binding;
                    }
                }
            }
            $metadata = ['action' => 'commit', 'scope' => $scope->columns(), 'actor' => ['id' => $fresh->id,
                'role' => DB::table('portal_roles')->where('id', $fresh->portal_role_id)->value('identifier'), 'active' => (bool) $fresh->is_active],
                'run' => $run->uuid, 'preview' => $preview?->uuid, 'preview_hash' => $preview?->payload_hash,
                'stage_id' => $preview?->stage_id, 'export_date' => $run->export_date, 'export_slot' => $run->export_slot,
                'review_blockers' => array_values(array_filter($reviewed['blockers'] ?? [], fn ($code) => is_string($code) && preg_match('/^[a-zA-Z_]{1,80}$/D', $code))),
                'workbook_hash' => $run->workbook_hash, 'bindings' => array_values($bindings),
                'pins' => $manifest['pins'] ?? [], 'profiles' => $manifest['knowledge']['profiles'] ?? [],
                'knowledge_hash' => Canonical::hash($manifest['knowledge'] ?? []), 'command_hash' => $hash];
            $id = DB::table('wald_commit_attempts')->insertGetId(['uuid' => (string) Str::uuid(), 'run_id' => $run->id,
                'preview_id' => $preview?->id, 'actor_id' => $fresh->id, 'command_uuid' => $command, 'command_hash' => $hash,
                'metadata' => Canonical::json($metadata, 65536), 'metadata_hash' => Canonical::hash($metadata), 'created_at' => now('UTC')]);

            return DB::table('wald_commit_attempts')->where('id', $id)->firstOrFail();
        }, DB::getDriverName() === 'mysql' ? 3 : 1);
    }

    private function finish(object $attempt, string $outcome, string $category, ?int $receipt = null): void
    {
        DB::table('wald_commit_attempt_outcomes')->insert(['attempt_id' => $attempt->id, 'outcome' => $outcome,
            'category' => $category, 'receipt_id' => $receipt, 'created_at' => now('UTC')]);
    }

    private function transient(Throwable $e): bool
    {
        return DB::getDriverName() === 'mysql' && (new ConcurrencyErrorDetector)->causedByConcurrencyError($e);
    }

    private function classify(Throwable $e): array
    {
        if ($e instanceof AuthorizationException) {
            return ['REFUSED', 'authority_denied'];
        }
        if (($e instanceof ImportConflict || $e instanceof KnowledgeConflict) && preg_match('/^[a-z_]{1,80}$/D', $e->getMessage())) {
            $code = $e->getMessage();

            return [str_contains($code, 'stale') || $code === 'source_site_binding_required' ? 'STALE' : (preg_match('/conflict|correction|identity|older_|predecessor/', $code) ? 'CONFLICTED' : 'REFUSED'), $code];
        }

        return ['FAILED', 'business_transaction_failed'];
    }

    public function audit(User $actor, KnowledgeScope $scope, string $runUuid, int $after = 0, int $limit = 50): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'audit');
        $run = (new BackendStore)->run($scope, $runUuid);

        return DB::table('wald_commit_attempts as a')->leftJoin('wald_commit_attempt_outcomes as o', 'o.attempt_id', '=', 'a.id')
            ->where('a.run_id', $run->id)->where('a.id', '>', max(0, $after))->orderBy('a.id')->limit(max(1, min(100, $limit)))
            ->get(['a.id', 'a.uuid', 'a.actor_id', 'a.command_uuid', 'a.metadata', 'a.created_at', 'o.outcome', 'o.category', 'o.receipt_id'])->all();
    }
}
