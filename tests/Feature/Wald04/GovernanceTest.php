<?php

use App\Models\PortalRole;
use App\Models\Site;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\Actions\SetRetentionHold;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgePolicy;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use App\SourceImport\Knowledge\ProfileState;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);
afterEach(function () {
    WaldFixtures::cleanup();
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

it('denies all Site User roles for every knowledge capability', function ($role, $ability) {
    [$office, $scope] = F::owner();
    $office->update(['portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id')]);
    expect(fn () => (new KnowledgePolicy)->authorize($office, $scope, $ability))->toThrow(AuthorizationException::class);
})->with(['site_manager', 'assistant_site_manager', 'finishing_foreman'])->with(KnowledgePolicy::ABILITIES);

it('fails closed on stale Office authority', function ($mutation) {
    [$office, $scope] = F::owner();
    $office->load('portalRole');
    DB::table('users')->where('id', $office->id)->update($mutation);
    expect(fn () => (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command()))->toThrow(AuthorizationException::class);
    expect(KnowledgeEvent::query()->count())->toBe(0);
})->with([[['is_active' => false]], [['is_preview_user' => true]], [['portal_role_id' => null]]]);

it('requires matching stored site organisation even for global Office', function () {
    [$office, $scope] = F::owner();
    [, $other] = F::owner();
    $bad = new KnowledgeScope($scope->organisationId, $other->siteId, $scope->namespace, $scope->family);
    expect(fn () => (new RegisterContext)->handle($office, $bad, F::snapshot(), F::command()))->toThrow(AuthorizationException::class);
});

it('does not resolve a foreign context through selected scope by UUID', function () {
    [$office, $scope] = F::owner();
    [, $other] = F::owner();
    $context = (new RegisterContext)->handle($office, $other, F::snapshot(), F::command());
    expect(fn () => (new KnowledgeQueries)->questions($office, $scope, $context->uuid))->toThrow(ModelNotFoundException::class);
});

it('keeps namespace family site and organisation collisions isolated', function ($dimension) {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    [, $other] = F::owner();
    $scope = match ($dimension) {
        'organisation' => $other,
        'site' => new KnowledgeScope($scope->organisationId, Site::factory()->create(['customer_organisation_id' => $scope->organisationId])->id, $scope->namespace, $scope->family),
        'namespace' => new KnowledgeScope($scope->organisationId, $scope->siteId, 'another', $scope->family),
        'family' => new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'another'),
    };
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    expect(fn () => (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command()))->toThrow(ModelNotFoundException::class);
})->with(['organisation', 'site', 'namespace', 'family']);

it('answers once idempotently rejects stale and tampered commands and creates no profile', function () {
    [$office, $scope] = F::owner();
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $q = (new KnowledgeQueries)->questions($office, $scope, $context->uuid)[0];
    $action = new AnswerClarification;
    $command = F::command();
    $answer = $action->handle($office, $scope, $context->uuid, $q['uuid'], 0, $q['evidence']['candidates'][0]['id'], 'One time.', $command);
    expect($action->handle($office, $scope, $context->uuid, $q['uuid'], 0, $q['evidence']['candidates'][0]['id'], 'One time.', $command)->uuid)->toBe($answer->uuid)
        ->and(KnowledgeProfile::query()->count())->toBe(0)->and(ClarificationAnswer::query()->count())->toBe(1);
    expect(fn () => $action->handle($office, $scope, $context->uuid, $q['uuid'], 0, null, 'Changed payload.', $command))->toThrow(KnowledgeConflict::class);
    expect(fn () => $action->handle($office, $scope, $context->uuid, $q['uuid'], 0, null, 'Stale review.', F::command()))->toThrow(KnowledgeConflict::class);
});

it('records successor answers rather than overwriting history', function () {
    [$office, $scope] = F::owner();
    [$context, $q, $first] = F::draft($office, $scope);
    $next = (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 1, null, 'Withdraw interpretation.', F::command());
    expect($next->predecessor_id)->toBe($first->id)->and($first->fresh()->decision)->toBe('SELECTED');
});

it('requires the exact draft hash and epoch for activation', function ($badHash, $badEpoch) {
    [$office, $scope] = F::owner();
    [, , , $v, $p] = F::draft($office, $scope);
    expect(fn () => (new ActivateProfile)->handle($office, $scope, $p->uuid, 1, $badHash ? str_repeat('f', 64) : $v->definition_hash,
        $badEpoch ? 0 : $p->lock_version, 'Review.', F::command()))->toThrow(KnowledgeConflict::class);
    expect($p->fresh()->state)->toBe(ProfileState::Draft);
})->with([[true, false], [false, true]]);

it('revocation preserves history and invalidates prior receipts immediately', function () {
    [$office, $scope] = F::owner();
    [, , , $v, $p] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $p->uuid, $p->lock_version, F::command());
    $revoked = (new RevokeProfile)->handle($office, $scope, $p->uuid, $p->lock_version, 'Incorrect reusable selection.', F::command());
    expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeFalse()
        ->and($receipt->fresh()->applied)->toBeTrue()->and(ProfileVersion::query()->count())->toBe(1);
    expect(fn () => (new ActivateProfile)->handle($office, $scope, $p->uuid, 1, $v->definition_hash, $revoked->lock_version, 'Retry old version.', F::command()))->toThrow(KnowledgeConflict::class);
});

it('requires mandatory bounded revocation reasons', function ($reason) {
    [$office, $scope] = F::owner();
    [, , , , $p] = F::active($office, $scope);
    expect(fn () => (new RevokeProfile)->handle($office, $scope, $p->uuid, $p->lock_version, $reason, F::command()))->toThrow(InvalidArgumentException::class);
})->with(['', '   ', str_repeat('x', 2001)]);

it('makes review expiry ineligible without deleting and allows explicit reapproval', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00 UTC'));
    [$office, $scope] = F::owner();
    [, , , $v, $p] = F::active($office, $scope);
    $this->travelTo(CarbonImmutable::parse('2027-09-08 12:00:00 UTC'));
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $use = (new UseProfile)->handle($office, $scope, $context->uuid, $p->uuid, $p->lock_version, F::command());
    expect($use->applied)->toBeFalse()->and($p->fresh()->state)->toBe(ProfileState::Active);
    $p = (new ActivateProfile)->handle($office, $scope, $p->uuid, 1, $v->definition_hash, $p->lock_version, 'Annual reapproval.', F::command());
    expect($p->review_due_at->format('Y-m-d'))->toBe('2028-09-08')->and(ProfileVersion::query()->count())->toBe(1);
});

it('rolls back an answer when its audit insert fails', function () {
    [$office, $scope] = F::owner();
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $q = (new KnowledgeQueries)->questions($office, $scope, $context->uuid)[0];
    KnowledgeEvent::creating(function () {
        throw new RuntimeException('synthetic audit failure');
    });
    try {
        expect(fn () => (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 0, null, 'Unresolved.', F::command()))->toThrow(RuntimeException::class);
        expect(ClarificationAnswer::query()->count())->toBe(0)->and(KnowledgeEvidence::query()->count())->toBe(1);
    } finally {
        KnowledgeEvent::flushEventListeners();
    }
});

it('protects immutable history against direct bulk writes', function ($table, $operation) {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    expect(fn () => $operation === 'delete' ? DB::table($table)->delete() : DB::table($table)->update(['actor_role' => 'forged']))
        ->toThrow(QueryException::class);
})->with(['wald_clarification_answers', 'wald_profile_versions', 'wald_knowledge_events'])->with(['update', 'delete']);

it('audits holds and refuses stale hold changes', function () {
    [$office, $scope] = F::owner();
    [$context] = F::draft($office, $scope);
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    $held = (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 0, true, 'Preserve for review.', F::command());
    expect($held->on_hold)->toBeTrue()->and(KnowledgeEvent::query()->where('action', 'retention')->count())->toBe(1);
    expect(fn () => (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 0, false, 'Stale release.', F::command()))->toThrow(KnowledgeConflict::class);
});
