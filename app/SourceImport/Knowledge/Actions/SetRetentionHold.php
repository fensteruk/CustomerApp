<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;

/** Audited Office hold metadata. No erasure or unattended production disposal. */
final class SetRetentionHold
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $contextUuid, string $evidenceUuid, int $expectedEpoch,
        bool $hold, string $reason, string $command): KnowledgeEvidence
    {
        $this->store->reason($reason);

        return $this->store->transact($actor, $scope, 'retention', $command,
            [$contextUuid, $evidenceUuid, $expectedEpoch, $hold, $reason], KnowledgeEvidence::class,
            function (User $fresh) use ($scope, $contextUuid, $evidenceUuid, $expectedEpoch, $hold, $reason): array {
                $context = $this->store->context($scope, $contextUuid, false);
                $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->where('uuid', $evidenceUuid)->lockForUpdate()->firstOrFail();
                if ($evidence->lock_version !== $expectedEpoch) {
                    throw new KnowledgeConflict('stale_retention_state');
                }
                $before = ['hold' => $evidence->on_hold, 'epoch' => $expectedEpoch];
                $evidence->forceFill(['on_hold' => $hold, 'lock_version' => $expectedEpoch + 1])->save();
                $reasonEvidence = $this->store->evidence($fresh, $context, ['reason' => $reason, 'target' => $evidenceUuid], 'hold');

                return [$evidence, $before, ['hold' => $hold, 'epoch' => $evidence->lock_version], $reasonEvidence];
            });
    }
}
