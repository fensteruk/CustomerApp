<?php

namespace App\SourceImport\Knowledge;

use App\Models\User;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;
use App\SourceImport\Knowledge\Models\KnowledgeRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shared short-transaction discipline; callbacks never perform source analysis or Portal writes. */
final class KnowledgeStore
{
    public function __construct(private KnowledgePolicy $policy = new KnowledgePolicy) {}

    public function transact(User $actor, KnowledgeScope $scope, string $ability, string $command, array $payload, string $resultClass, callable $write): KnowledgeRecord
    {
        if (! Str::isUuid($command)) {
            throw new \InvalidArgumentException('invalid_knowledge_command');
        }
        $hash = Canonical::hash([$scope->columns(), $ability, $payload]);

        return DB::transaction(function () use ($actor, $scope, $ability, $command, $hash, $resultClass, $write): KnowledgeRecord {
            $fresh = $this->policy->authorize($actor, $scope, $ability, true);
            $existing = KnowledgeEvent::query()->where('actor_id', $fresh->id)->where('command_uuid', $command)->first();
            if ($existing) {
                if ($existing->command_hash !== $hash || $existing->action !== $ability) {
                    throw new KnowledgeConflict('command_payload_conflict');
                }

                return $resultClass::query()->where('uuid', $existing->result_uuid)->firstOrFail();
            }
            $pins = (new KnowledgeIdentity)->current();
            [$result, $before, $after, $reasonEvidence] = $write($fresh);
            $this->policy->authorize($fresh, $scope, $ability, true);
            if ($pins !== (new KnowledgeIdentity)->current()) {
                throw new KnowledgeConflict('policy_changed_during_write');
            }
            $this->insert(KnowledgeEvent::class, $fresh, [...$scope->columns(), 'action' => $ability,
                'command_uuid' => $command, 'command_hash' => $hash, 'policy_version' => KnowledgeIdentity::POLICY,
                'before_state' => $before, 'after_state' => $after, 'result_uuid' => $result->uuid,
                'reason_evidence_id' => $reasonEvidence?->id]);

            return $result;
        }, 3);
    }

    public function insert(string $class, User $actor, array $attributes, string $actorRole = 'fenster_office_staff'): KnowledgeRecord
    {
        $record = new $class;
        $record->forceFill([...$attributes, 'actor_id' => $actor->id, 'actor_role' => $actorRole,
            'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        $record->save();

        return $record;
    }

    public function scoped(string $class, KnowledgeScope $scope): Builder
    {
        return $class::query()->where($scope->columns());
    }

    public function context(KnowledgeScope $scope, string $uuid, bool $open = true): KnowledgeContext
    {
        $context = $this->scoped(KnowledgeContext::class, $scope)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
        if ($open && ($context->state !== 'OPEN' || now('UTC')->greaterThanOrEqualTo($context->expires_at)
            || (new KnowledgeIdentity)->compatible($context->pins) !== Compatibility::Exact)) {
            throw new KnowledgeConflict('stale_knowledge_context');
        }

        return $context;
    }

    public function snapshot(KnowledgeContext $context): array
    {
        $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->where('retention_class', 'analysis_snapshot')->firstOrFail();
        if (Canonical::hash($evidence->payload) !== $context->snapshot_hash || $evidence->payload_hash !== $context->snapshot_hash) {
            throw new KnowledgeConflict('knowledge_evidence_integrity');
        }

        return $evidence->payload;
    }

    public function evidence(User $actor, KnowledgeContext $context, array $payload, string $kind, string $actorRole = 'fenster_office_staff'): KnowledgeEvidence
    {
        Canonical::json($payload, $kind === 'analysis_snapshot' ? 262144 : 65536);

        return $this->insert(KnowledgeEvidence::class, $actor, ['context_id' => $context->id,
            'payload' => $payload, 'payload_hash' => Canonical::hash($payload), 'retention_class' => $kind], $actorRole);
    }

    public function reason(string $reason): string
    {
        if (trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('knowledge_reason_required_or_too_long');
        }

        return $reason;
    }
}
