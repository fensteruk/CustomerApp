<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\Compatibility;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use App\SourceImport\Knowledge\ProfileState;

final class ActivateProfile
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $profileUuid, int $versionNumber, string $definitionHash,
        int $expectedEpoch, string $reason, string $command): KnowledgeProfile
    {
        $this->store->reason($reason);

        return $this->store->transact($actor, $scope, 'activate', $command,
            [$profileUuid, $versionNumber, $definitionHash, $expectedEpoch, $reason], KnowledgeProfile::class,
            function (User $fresh) use ($scope, $profileUuid, $versionNumber, $definitionHash, $expectedEpoch, $reason): array {
                $profile = $this->store->scoped(KnowledgeProfile::class, $scope)->where('uuid', $profileUuid)->lockForUpdate()->firstOrFail();
                $version = ProfileVersion::query()->where('profile_id', $profile->id)->where('version', $versionNumber)->firstOrFail();
                if ($profile->lock_version !== $expectedEpoch || $version->definition_hash !== $definitionHash
                    || Canonical::hash($version->definition) !== $definitionHash) {
                    throw new KnowledgeConflict('stale_profile_activation');
                }
                if ((new KnowledgeIdentity)->compatible($version->definition['pins']) !== Compatibility::Exact) {
                    throw new KnowledgeConflict('incompatible_profile_identity');
                }
                $revoked = KnowledgeEvent::query()->where('action', 'revoke')->where('result_uuid', $profileUuid)->get();
                foreach ($revoked as $event) {
                    if (($event->before_state['highest_version'] ?? 0) >= $versionNumber) {
                        throw new KnowledgeConflict('revoked_version_requires_successor');
                    }
                }
                $answer = ClarificationAnswer::query()->findOrFail($version->answer_id);
                $question = Clarification::query()->findOrFail($answer->clarification_id);
                $contextUuid = KnowledgeContext::query()->whereKey($question->context_id)->value('uuid');
                // Reapproval can use retained, closed evidence, but never a corrected-away answer.
                $context = $this->store->context($scope, $contextUuid, false);
                $question = Clarification::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
                if ($question->sequence !== $answer->sequence || $context->state === 'SUPERSEDED') {
                    throw new KnowledgeConflict('approval_provenance_superseded');
                }
                $this->store->snapshot($context);
                $before = ['state' => $profile->state->value, 'version' => $profile->active_version, 'epoch' => $profile->lock_version];
                $profile->forceFill(['state' => ProfileState::Active, 'active_version' => $versionNumber,
                    'lock_version' => $expectedEpoch + 1, 'review_due_at' => now('UTC')->addMonthsNoOverflow(12),
                    'retired_at' => null, 'retain_until' => null])->save();
                $evidence = $this->store->evidence($fresh, $context, ['reason' => $reason, 'definition_hash' => $definitionHash], 'activation');

                return [$profile, $before, ['state' => 'ACTIVE', 'version' => $versionNumber, 'epoch' => $profile->lock_version], $evidence];
            });
    }
}
