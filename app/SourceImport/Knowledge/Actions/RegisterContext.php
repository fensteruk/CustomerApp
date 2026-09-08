<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\AnalysisSnapshot;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\KnowledgeContext;

final class RegisterContext
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, AnalysisSnapshot $analysis, string $command, ?string $predecessorUuid = null): KnowledgeContext
    {
        return $this->store->transact($actor, $scope, 'register', $command, [$analysis->data, $predecessorUuid], KnowledgeContext::class,
            function (User $fresh) use ($scope, $analysis, $predecessorUuid): array {
                $previous = $predecessorUuid ? $this->store->context($scope, $predecessorUuid, false) : null;
                if ($previous && $previous->state === 'SUPERSEDED') {
                    throw new KnowledgeConflict('context_already_superseded');
                }
                $data = $analysis->data;
                $context = $this->store->insert(KnowledgeContext::class, $fresh, [...$scope->columns(),
                    'generation' => ($previous?->generation ?? 0) + 1, 'predecessor_id' => $previous?->id,
                    'source_checksum' => $data['source_checksum'], 'analysis_hash' => $data['analysis_hash'],
                    'snapshot_hash' => Canonical::hash($data), 'pins' => $data['pins'], 'expires_at' => now('UTC')->addHours(24)]);
                $this->store->evidence($fresh, $context, $data, 'analysis_snapshot');
                foreach ($data['questions'] as $key => $question) {
                    $this->store->insert(Clarification::class, $fresh, ['context_id' => $context->id,
                        'question_key' => $key, 'question_hash' => Canonical::hash($question), 'type' => $question['type']]);
                }
                if ($previous) {
                    $previous->forceFill(['state' => 'SUPERSEDED', 'closed_at' => now('UTC'), 'lock_version' => $previous->lock_version + 1])->save();
                }

                return [$context, ['predecessor' => $previous?->uuid], ['generation' => $context->generation, 'snapshot_hash' => $context->snapshot_hash], null];
            });
    }
}
