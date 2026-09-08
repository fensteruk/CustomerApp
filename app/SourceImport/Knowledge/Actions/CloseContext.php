<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;

final class CloseContext
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $contextUuid, int $expectedEpoch, string $command): KnowledgeContext
    {
        return $this->store->transact($actor, $scope, 'retention', $command, [$contextUuid, $expectedEpoch], KnowledgeContext::class,
            function (User $fresh) use ($scope, $contextUuid, $expectedEpoch): array {
                $context = $this->store->context($scope, $contextUuid, false);
                if ($context->state !== 'OPEN' || $context->lock_version !== $expectedEpoch) {
                    throw new KnowledgeConflict('stale_context_close');
                }
                $context->forceFill(['state' => 'CLOSED', 'closed_at' => now('UTC'), 'lock_version' => $expectedEpoch + 1])->save();
                KnowledgeEvidence::query()->where('context_id', $context->id)->update([
                    'terminal_at' => now('UTC'), 'retain_until' => now('UTC')->addMonthsNoOverflow(24)]);

                return [$context, ['state' => 'OPEN'], ['state' => 'CLOSED', 'epoch' => $context->lock_version], null];
            });
    }
}
