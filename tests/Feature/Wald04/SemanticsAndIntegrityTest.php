<?php

use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\CloseContext;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\Actions\SetRetentionHold;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);
afterEach(fn () => WaldFixtures::cleanup());

it('keeps CC bang correction occurrence-only and preserves blocked original evidence', function () {
    [$office, $scope] = F::owner();
    $snapshot = F::snapshot(rawCall: 'CC!');
    $context = (new RegisterContext)->handle($office, $scope, $snapshot, F::command());
    $questions = collect((new KnowledgeQueries)->questions($office, $scope, $context->uuid));
    $q = $questions->first(fn ($q) => $q['evidence']['type'] === 'SEMANTIC');
    expect(fn () => (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 0, 'CC1', 'Confirm typo.', F::command()))->toThrow(KnowledgeConflict::class);
    $structure = $questions->firstWhere('key', 'structure:call_type');
    (new AnswerClarification)->handle($office, $scope, $context->uuid, $structure['uuid'], 0, $structure['evidence']['candidates'][0]['id'], 'Confirmed call type column.', F::command());
    $answer = (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 0, 'CC1', 'Confirm occurrence typo.', F::command());
    $reviewed = (new KnowledgeQueries)->reviewed($office, $scope, $context->uuid, $answer->uuid)->jsonSerialize();
    expect($reviewed['human_selection']['canonical'])->toBe('CC1')->and($reviewed['original']['raw_value'])->toBe('CC!')
        ->and($reviewed['ready_for_staging'])->toBeFalse()->and(KnowledgeProfile::query()->where('customer_organisation_id', $scope->organisationId)->count())->toBe(0)
        ->and((new CustomerAppDictionary)->callType('CC!')->isResolved())->toBeFalse()
        ->and((new CustomerAppDictionary)->identity()->fingerprint)->toBe(KnowledgeIdentity::FINGERPRINT);
    expect(fn () => (new SaveProfileDraft)->handle($office, $scope, $context->uuid, $answer->uuid, F::command()))->toThrow(KnowledgeConflict::class);
    $later = (new RegisterContext)->handle($office, $scope, F::snapshot(rawCall: 'CC!'), F::command());
    expect(collect((new KnowledgeQueries)->questions($office, $scope, $later->uuid))->first(fn ($q) => $q['evidence']['type'] === 'SEMANTIC')['sequence'])->toBe(0);
});

it('records unknown review without allowing new dictionary truth', function () {
    [$office, $scope] = F::owner();
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(rawCall: 'ZZ9'), F::command());
    $q = collect((new KnowledgeQueries)->questions($office, $scope, $context->uuid))->first(fn ($q) => $q['evidence']['type'] === 'SEMANTIC');
    expect($q['evidence']['candidates'])->toBeEmpty();
    expect(fn () => (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 0, 'windows', 'Invent meaning.', F::command()))->toThrow(KnowledgeConflict::class);
    $answer = (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 0, null, 'Needs controlled dictionary review.', F::command());
    expect($answer->decision)->toBe('UNRESOLVED')->and((new CustomerAppDictionary)->callType('ZZ9')->isResolved())->toBeFalse();
});

it('invalidates reuse when approval provenance is corrected', function () {
    [$office, $scope] = F::owner();
    [$old, $question, , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    (new AnswerClarification)->handle($office, $scope, $old->uuid, $question['uuid'], 1, null, 'Withdraw prior approval.', F::command());
    expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeFalse();
    expect((new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command())->applied)->toBeFalse();
});

it('requires clarification when two profiles are equally applicable', function () {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    expect($receipt->applied)->toBeFalse()->and($receipt->reason)->toBe('COMPETING_PROFILES');
});

it('invalidates an earlier receipt when another equally eligible profile activates', function () {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeTrue();
    F::active($office, $scope);
    expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeFalse();
});

it('preserves active evidence after context closure and audits a protective hold', function () {
    [$office, $scope] = F::owner();
    [$context] = F::active($office, $scope);
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    (new CloseContext)->handle($office, $scope, $context->uuid, 0, F::command());
    $this->travel(25)->months();
    try {
        $eligibility = (new KnowledgeQueries)->disposalEligibility($office, $scope, $context->uuid, $evidence->uuid);
        expect($eligibility['age_eligible'])->toBeFalse()->and($eligibility['active_dependency'])->toBeTrue();
        (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 0, true, 'Keep evidence.', F::command());
        expect((new KnowledgeQueries)->disposalEligibility($office, $scope, $context->uuid, $evidence->uuid)['on_hold'])->toBeTrue();
    } finally {
        $this->travelBack();
    }
});

it('refuses in-place evidence and tenant mutation through bulk queries', function ($table, $field, $value) {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    expect(fn () => DB::table($table)->update([$field => $value]))->toThrow(QueryException::class);
})->with([
    ['wald_knowledge_contexts', 'source_checksum', str_repeat('f', 64)],
    ['wald_profiles', 'workbook_family', 'foreign-family'],
    ['wald_clarifications', 'question_hash', str_repeat('f', 64)],
    ['wald_knowledge_evidence', 'payload', '{}'],
]);

it('refuses migration rollback before dropping any populated history', function () {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    $migration = require base_path('database/migrations/2026_09_08_000009_create_wald_knowledge.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(KnowledgeProfile::query()->where('customer_organisation_id', $scope->organisationId)->count())->toBe(1);
});
