<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use App\SourceImport\Knowledge\ProfileState;

final class RevokeProfile
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $profileUuid, int $expectedEpoch, string $reason, string $command): KnowledgeProfile
    {
        $this->store->reason($reason);

        return $this->store->transact($actor, $scope, 'revoke', $command, [$profileUuid, $expectedEpoch, $reason], KnowledgeProfile::class,
            function (User $fresh) use ($scope, $profileUuid, $expectedEpoch, $reason): array {
                $profile = $this->store->scoped(KnowledgeProfile::class, $scope)->where('uuid', $profileUuid)->lockForUpdate()->firstOrFail();
                if ($profile->lock_version !== $expectedEpoch || $profile->state === ProfileState::Revoked) {
                    throw new KnowledgeConflict('stale_profile_revocation');
                }
                $version = ProfileVersion::query()->where('profile_id', $profile->id)->orderByDesc('version')->firstOrFail();
                $answer = ClarificationAnswer::query()->findOrFail($version->answer_id);
                $question = Clarification::query()->findOrFail($answer->clarification_id);
                $contextUuid = KnowledgeContext::query()->whereKey($question->context_id)->value('uuid');
                $context = $this->store->context($scope, $contextUuid, false);
                $before = ['state' => $profile->state->value, 'version' => $profile->active_version, 'epoch' => $profile->lock_version,
                    'highest_version' => $version->version];
                $profile->forceFill(['state' => ProfileState::Revoked, 'lock_version' => $expectedEpoch + 1,
                    'retired_at' => now('UTC'), 'retain_until' => now('UTC')->addMonthsNoOverflow(24)])->save();
                $evidence = $this->store->evidence($fresh, $context, ['reason' => $reason], 'revocation');

                return [$profile, $before, ['state' => 'REVOKED', 'epoch' => $profile->lock_version], $evidence];
            });
    }
}
