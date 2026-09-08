<?php

namespace App\SourceImport\Knowledge;

use App\Models\User;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileUse;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class KnowledgeQueries
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore, private KnowledgePolicy $policy = new KnowledgePolicy) {}

    public function questions(User $actor, KnowledgeScope $scope, string $contextUuid): array
    {
        $this->policy->authorize($actor, $scope, 'evidence');
        $context = $this->store->scoped(KnowledgeContext::class, $scope)->where('uuid', $contextUuid)->firstOrFail();
        $snapshot = $this->store->snapshot($context);

        return Clarification::query()->where('context_id', $context->id)->orderBy('id')->get()
            ->map(fn ($q) => ['uuid' => $q->uuid, 'sequence' => $q->sequence, 'state' => $q->state,
                'key' => $q->question_key, 'evidence' => $snapshot['questions'][$q->question_key]])->all();
    }

    public function reviewed(User $actor, KnowledgeScope $scope, string $contextUuid, string $answerUuid): ReviewedInterpretation
    {
        $this->policy->authorize($actor, $scope, 'evidence');
        $context = $this->store->scoped(KnowledgeContext::class, $scope)->where('uuid', $contextUuid)->firstOrFail();
        $answer = ClarificationAnswer::query()->where('uuid', $answerUuid)
            ->whereIn('clarification_id', Clarification::query()->select('id')->where('context_id', $context->id))->firstOrFail();
        $payload = KnowledgeEvidence::query()->whereKey($answer->evidence_id)->where('context_id', $context->id)->firstOrFail()->payload;

        return new ReviewedInterpretation($payload['original'] ?? [], $payload['selection'], $answer->uuid);
    }

    public function audit(User $actor, KnowledgeScope $scope, int $page = 1): LengthAwarePaginator
    {
        $this->policy->authorize($actor, $scope, 'audit');

        return $this->store->scoped(KnowledgeEvent::class, $scope)->orderByDesc('id')->paginate(50, ['*'], 'page', max(1, $page));
    }

    public function effectiveState(User $actor, KnowledgeScope $scope, string $profileUuid): ProfileState
    {
        $this->policy->authorize($actor, $scope, 'audit');
        $profile = $this->store->scoped(KnowledgeProfile::class, $scope)->where('uuid', $profileUuid)->firstOrFail();
        if ($profile->state !== ProfileState::Active) {
            return $profile->state;
        }
        $version = ProfileVersion::query()->where('profile_id', $profile->id)->where('version', $profile->active_version)->firstOrFail();

        return now('UTC')->greaterThanOrEqualTo($profile->review_due_at)
            || (new KnowledgeIdentity)->compatible($version->definition['pins']) !== Compatibility::Exact
            || ! (new ProfileProvenance)->current($version) ? ProfileState::Stale : ProfileState::Active;
    }

    public function disposalEligibility(User $actor, KnowledgeScope $scope, string $contextUuid, string $evidenceUuid): array
    {
        $this->policy->authorize($actor, $scope, 'retention');
        $context = $this->store->scoped(KnowledgeContext::class, $scope)->where('uuid', $contextUuid)->firstOrFail();
        $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->where('uuid', $evidenceUuid)->firstOrFail();
        $profiles = $this->store->scoped(KnowledgeProfile::class, $scope)->whereIn('id',
            ProfileVersion::query()->select('profile_id')->whereIn('answer_id',
                ClarificationAnswer::query()->select('id')->whereIn('clarification_id', Clarification::query()->select('id')->where('context_id', $context->id))))->get();
        $active = $profiles->contains(fn ($profile) => $profile->state !== ProfileState::Revoked);
        $due = $evidence->retain_until;
        foreach ($profiles as $profile) {
            if ($profile->retain_until && ($due === null || $profile->retain_until->greaterThan($due))) {
                $due = $profile->retain_until;
            }
        }
        // A context hold protects all linked provenance, not merely the one payload.
        $hold = KnowledgeEvidence::query()->where('context_id', $context->id)->where('on_hold', true)->exists();

        return ['age_eligible' => (new RetentionPolicy)->eligible($due, CarbonImmutable::now('UTC'), $hold, $active || $context->state === 'OPEN'),
            'on_hold' => $hold, 'active_dependency' => $active, 'automatic_disposal_enabled' => false];
    }

    /** Fresh advisory check only. WALD05 must recheck inside its separately designed commit locks. */
    public function receiptEligible(User $actor, KnowledgeScope $scope, string $receiptUuid): bool
    {
        $this->policy->authorize($actor, $scope, 'reuse');
        $receipt = ProfileUse::query()->where('uuid', $receiptUuid)
            ->whereIn('profile_id', $this->store->scoped(KnowledgeProfile::class, $scope)->select('id'))
            ->whereIn('context_id', $this->store->scoped(KnowledgeContext::class, $scope)->select('id'))->firstOrFail();
        $profile = KnowledgeProfile::query()->findOrFail($receipt->profile_id);
        $context = KnowledgeContext::query()->findOrFail($receipt->context_id);
        $version = ProfileVersion::query()->where('profile_id', $profile->id)->where('version', $receipt->version)->first();
        $answered = $version && Clarification::query()->where('context_id', $context->id)
            ->where('question_key', 'structure:'.$version->definition['selection']['role'])->where('sequence', '>', 0)->exists();
        $candidates = $this->store->scoped(KnowledgeProfile::class, $scope)->where('state', 'ACTIVE')->limit(51)->get();
        if ($candidates->count() > 50 || $version === null) {
            return false;
        }
        $matches = 0;
        $snapshot = $this->store->snapshot($context);
        foreach ($candidates as $candidate) {
            if (now('UTC')->greaterThanOrEqualTo($candidate->review_due_at)) {
                continue;
            }
            $candidateVersion = ProfileVersion::query()->where('profile_id', $candidate->id)->where('version', $candidate->active_version)->firstOrFail();
            if (($candidateVersion->definition['selection']['role'] ?? null) === $version->definition['selection']['role']
                && (new ProfileProvenance)->current($candidateVersion)
                && (new ProfileMatcher)->evaluate($candidateVersion->definition, $snapshot)['compatibility'] === 'EXACT_MATCH') {
                $matches++;
            }
        }

        return $matches === 1 && ! $answered && (new ProfileProvenance)->current($version)
            && $receipt->applied && $profile->state === ProfileState::Active && $profile->lock_version === $receipt->epoch
            && $profile->active_version === $receipt->version && now('UTC')->lessThan($profile->review_due_at)
            && $context->state === 'OPEN' && now('UTC')->lessThan($context->expires_at)
            && $context->snapshot_hash === $receipt->evidence_hash
            && (new KnowledgeIdentity)->compatible($receipt->pins) === Compatibility::Exact;
    }
}
