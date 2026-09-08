<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileUse;
use App\SourceImport\Knowledge\ProfileMatcher;
use App\SourceImport\Knowledge\ProfileProvenance;
use App\SourceImport\Knowledge\ProfileState;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UseProfile
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $contextUuid, string $profileUuid, int $expectedEpoch, string $command): ProfileUse
    {
        return $this->store->transact($actor, $scope, 'reuse', $command, [$contextUuid, $profileUuid, $expectedEpoch], ProfileUse::class,
            function (User $fresh) use ($scope, $contextUuid, $profileUuid, $expectedEpoch): array {
                $profiles = $this->store->scoped(KnowledgeProfile::class, $scope)->orderBy('id')->limit(51)->lockForUpdate()->get();
                if ($profiles->count() > 50) {
                    throw new KnowledgeConflict('knowledge_profile_budget_exceeded');
                }
                $profile = $profiles->firstWhere('uuid', $profileUuid);
                if (! $profile) {
                    throw new ModelNotFoundException;
                }
                if ($profile->lock_version !== $expectedEpoch) {
                    throw new KnowledgeConflict('stale_profile_epoch');
                }
                $context = $this->store->context($scope, $contextUuid);
                $snapshot = $this->store->snapshot($context);
                $result = ['compatibility' => 'STALE_VERSION', 'applied' => false, 'reason' => 'PROFILE_UNAVAILABLE'];
                $provenance = new ProfileProvenance;
                $versions = $provenance->activeVersions($profiles);
                $currentVersionIds = $provenance->currentVersionIds($versions);
                $version = $versions->get($profile->id);
                if ($version && $profile->state === ProfileState::Active && now('UTC')->lessThan($profile->review_due_at)
                    && in_array($version->id, $currentVersionIds, true)) {
                    if (Canonical::hash($version->definition) !== $version->definition_hash) {
                        throw new KnowledgeConflict('profile_integrity_error');
                    }
                    $result = (new ProfileMatcher)->evaluate($version->definition, $snapshot);
                    $questionKey = 'structure:'.$version->definition['selection']['role'];
                    if (Clarification::query()->where('context_id', $context->id)->where('question_key', $questionKey)->where('sequence', '>', 0)->exists()) {
                        $result = ['compatibility' => 'COMPATIBLE_WITH_REVIEW', 'applied' => false, 'reason' => 'CURRENT_ANSWER_TAKES_PRECEDENCE'];
                    }
                    $competing = 0;
                    foreach ($profiles as $other) {
                        if ($other->state !== ProfileState::Active || now('UTC')->greaterThanOrEqualTo($other->review_due_at)) {
                            continue;
                        }
                        $otherVersion = $versions->get($other->id) ?? throw new ModelNotFoundException;
                        if (($otherVersion->definition['selection']['role'] ?? null) === $version->definition['selection']['role']
                            && in_array($otherVersion->id, $currentVersionIds, true)
                            && (new ProfileMatcher)->evaluate($otherVersion->definition, $snapshot)['compatibility'] === 'EXACT_MATCH') {
                            $competing++;
                        }
                    }
                    if ($competing > 1) {
                        $result = ['compatibility' => 'COMPATIBLE_WITH_REVIEW', 'applied' => false, 'reason' => 'COMPETING_PROFILES'];
                    }
                    $result['applied'] = $result['compatibility'] === 'EXACT_MATCH';
                }
                $receipt = $this->store->insert(ProfileUse::class, $fresh, ['context_id' => $context->id, 'profile_id' => $profile->id,
                    'version' => $version?->version, 'epoch' => $profile->lock_version, 'evidence_hash' => $context->snapshot_hash,
                    'compatibility' => $result['compatibility'], 'applied' => $result['applied'], 'reason' => $result['reason'],
                    'selection' => $result['applied'] ? $result['selection'] : null, 'pins' => (new KnowledgeIdentity)->current()]);

                return [$receipt, ['epoch' => $expectedEpoch], ['compatibility' => $receipt->compatibility, 'applied' => $receipt->applied], null];
            });
    }
}
