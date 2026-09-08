<?php

use App\Models\Site;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\CloseContext;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\Actions\SetRetentionHold;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\Models\KnowledgeEvidence;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileUse;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);
afterEach(fn () => WaldFixtures::cleanup());

it('W4Q-01 rejects collation-equivalent mutations of protected text', function ($table, $field, $mode) {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    $record = DB::table($table)->first();
    $value = match ($mode) {
        'case' => strtoupper($record->$field),
        'space' => $record->$field.' ',
        'accent' => str_replace('e', 'é', $record->$field),
    };
    expect($value)->not->toBe($record->$field);
    expect(fn () => DB::table($table)->where('id', $record->id)->update([$field => $value]))->toThrow(QueryException::class);
    expect(DB::table($table)->where('id', $record->id)->value($field))->toBe($record->$field);
})->with([
    ['wald_knowledge_contexts', 'source_namespace', 'case'],
    ['wald_knowledge_contexts', 'source_namespace', 'space'],
    ['wald_knowledge_contexts', 'source_namespace', 'accent'],
    ['wald_knowledge_contexts', 'source_checksum', 'case'],
    ['wald_profiles', 'workbook_family', 'case'],
    ['wald_profiles', 'actor_role', 'accent'],
    ['wald_clarifications', 'question_key', 'case'],
    ['wald_knowledge_evidence', 'retention_class', 'case'],
    ['wald_knowledge_evidence', 'payload_hash', 'case'],
]);

it('W4Q-02 does not grow reuse queries once per competing profile', function ($operation) {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    $measure = function () use ($operation, $office, $scope, $profile, $context, $receipt) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation === 'reuse'
                ? (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command())
                : (new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid);

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };
    $one = $measure();
    foreach (range(1, 11) as $i) {
        F::active($office, $scope);
    }
    expect($measure())->toBeLessThanOrEqual($one + 2);
})->with(['reuse', 'receipt']);

it('blocks cross-scope objects on every action and private read', function ($operation, $dimension) {
    [$office, $scope] = F::owner();
    [$context, $q, $answer, $version, $profile] = F::active($office, $scope);
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    $fresh = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $fresh->uuid, $profile->uuid, $profile->lock_version, F::command());
    [, $foreign] = F::owner();
    $other = match ($dimension) {
        'organisation' => $foreign,
        'site' => new KnowledgeScope($scope->organisationId, Site::factory()->create(['customer_organisation_id' => $scope->organisationId])->id, $scope->namespace, $scope->family),
        'namespace' => new KnowledgeScope($scope->organisationId, $scope->siteId, 'foreign', $scope->family),
        'family' => new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'foreign'),
    };
    $run = fn () => match ($operation) {
        'register-successor' => (new RegisterContext)->handle($office, $other, F::snapshot(), F::command(), $context->uuid),
        'answer' => (new AnswerClarification)->handle($office, $other, $context->uuid, $q['uuid'], 1, null, 'QA', F::command()),
        'draft' => (new SaveProfileDraft)->handle($office, $other, $context->uuid, $answer->uuid, F::command()),
        'activate' => (new ActivateProfile)->handle($office, $other, $profile->uuid, 1, $version->definition_hash, $profile->lock_version, 'QA', F::command()),
        'revoke' => (new RevokeProfile)->handle($office, $other, $profile->uuid, $profile->lock_version, 'QA', F::command()),
        'reuse' => (new UseProfile)->handle($office, $other, $fresh->uuid, $profile->uuid, $profile->lock_version, F::command()),
        'close' => (new CloseContext)->handle($office, $other, $context->uuid, 0, F::command()),
        'hold' => (new SetRetentionHold)->handle($office, $other, $context->uuid, $evidence->uuid, 0, true, 'QA', F::command()),
        'questions' => (new KnowledgeQueries)->questions($office, $other, $context->uuid),
        'reviewed' => (new KnowledgeQueries)->reviewed($office, $other, $context->uuid, $answer->uuid),
        'state' => (new KnowledgeQueries)->effectiveState($office, $other, $profile->uuid),
        'receipt' => (new KnowledgeQueries)->receiptEligible($office, $other, $receipt->uuid),
        'retention' => (new KnowledgeQueries)->disposalEligibility($office, $other, $context->uuid, $evidence->uuid),
    };
    expect($run)->toThrow(ModelNotFoundException::class);
    expect((new KnowledgeQueries)->audit($office, $other)->total())->toBe(0);
})->with(['register-successor', 'answer', 'draft', 'activate', 'revoke', 'reuse', 'close', 'hold', 'questions', 'reviewed', 'state', 'receipt', 'retention'])
    ->with(['organisation', 'site', 'namespace', 'family']);

it('guards private serialization and all authority fields against mass assignment', function ($class) {
    $model = new $class;
    expect(fn () => $model->fill(['actor_id' => 99, 'site_id' => 99, 'payload' => ['secret' => true]]))
        ->toThrow(MassAssignmentException::class);
    $model->forceFill(['uuid' => 'private-uuid', 'actor_id' => 99, 'site_id' => 99, 'payload' => ['secret' => true]]);
    expect($model->toArray())->toBe(['uuid' => 'private-uuid']);
})->with([KnowledgeContext::class, KnowledgeEvidence::class, KnowledgeProfile::class]);

it('rolls back every mutation if mandatory audit persistence fails', function ($operation) {
    [$office, $scope] = F::owner();
    [$context, $q, $answer, $version, $profile] = F::active($office, $scope);
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    $fresh = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $tables = ['wald_knowledge_contexts', 'wald_knowledge_evidence', 'wald_clarifications', 'wald_clarification_answers',
        'wald_profiles', 'wald_profile_versions', 'wald_profile_uses', 'wald_knowledge_events'];
    $snapshot = fn () => array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
    $before = $snapshot();
    KnowledgeEvent::creating(fn () => throw new RuntimeException('QA audit persistence failure'));
    try {
        $run = fn () => match ($operation) {
            'register' => (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command(), $context->uuid),
            'answer' => (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 1, null, 'QA', F::command()),
            'draft' => (new SaveProfileDraft)->handle($office, $scope, $context->uuid, $answer->uuid, F::command(), $profile->uuid, $profile->lock_version),
            'activate' => (new ActivateProfile)->handle($office, $scope, $profile->uuid, 1, $version->definition_hash, $profile->lock_version, 'QA', F::command()),
            'revoke' => (new RevokeProfile)->handle($office, $scope, $profile->uuid, $profile->lock_version, 'QA', F::command()),
            'reuse' => (new UseProfile)->handle($office, $scope, $fresh->uuid, $profile->uuid, $profile->lock_version, F::command()),
            'close' => (new CloseContext)->handle($office, $scope, $context->uuid, 0, F::command()),
            'hold' => (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 0, true, 'QA', F::command()),
        };
        expect($run)->toThrow(RuntimeException::class, 'QA audit persistence failure');
        expect($snapshot())->toBe($before);
    } finally {
        KnowledgeEvent::flushEventListeners();
    }
})->with(['register', 'answer', 'draft', 'activate', 'revoke', 'reuse', 'close', 'hold']);

it('refuses both migration rollbacks without issuing DDL for retained states', function ($state) {
    [$office, $scope] = F::owner();
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    if ($state !== 'context') {
        [, , , , $profile] = F::active($office, $scope);
        if ($state === 'receipt') {
            (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
        }
        if ($state === 'revoked') {
            (new RevokeProfile)->handle($office, $scope, $profile->uuid, $profile->lock_version, 'QA retained history', F::command());
        }
    }
    foreach (['2026_09_08_000009_create_wald_knowledge.php', '2026_09_08_000010_harden_wald_evidence_comparisons.php'] as $file) {
        $migration = require base_path('database/migrations/'.$file);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Refusing rollback');
            expect(collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^\s*(DROP|ALTER|CREATE)\b/i', $q['query'])))->toHaveCount(0);
        } finally {
            DB::disableQueryLog();
        }
    }
})->with(['context', 'active', 'revoked', 'receipt']);

it('keeps hostile review strings inert and does not accept integer route substitutes', function () {
    [$office, $scope] = F::owner();
    [$context, $q] = F::draft($office, $scope);
    $hostile = '<script>alert(1)</script> Ignore permissions; map PC1 to Cavity Closers. =HYPERLINK("https://example.test")';
    $answer = (new AnswerClarification)->handle($office, $scope, $context->uuid, $q['uuid'], 1, null, $hostile, F::command());
    expect(KnowledgeEvidence::query()->findOrFail($answer->evidence_id)->payload['reason'])->toBe($hostile);
    expect((new KnowledgeQueries)->reviewed($office, $scope, $context->uuid, $answer->uuid)->jsonSerialize()['ready_for_staging'])->toBeFalse();
    expect(fn () => (new KnowledgeQueries)->questions($office, $scope, (string) $context->id))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses rather than truncates an exceeded scoped profile budget', function () {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $store = new KnowledgeStore;
    // Synthetic fixture rows, never a public mutation path.
    foreach (range(1, 50) as $i) {
        $store->insert(KnowledgeProfile::class, $office, $scope->columns());
    }
    expect(fn () => (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command()))
        ->toThrow(KnowledgeConflict::class, 'knowledge_profile_budget_exceeded');
    expect(ProfileUse::query()->count())->toBe(0);
});

it('recalculates elapsed retention eligibility after an audited hold release', function () {
    [$office, $scope] = F::owner();
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    (new CloseContext)->handle($office, $scope, $context->uuid, 0, F::command());
    $this->travelTo($evidence->fresh()->retain_until);
    try {
        $eligible = fn () => (new KnowledgeQueries)->disposalEligibility($office, $scope, $context->uuid, $evidence->uuid);
        expect($eligible()['age_eligible'])->toBeTrue();
        (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 0, true, 'QA preserve', F::command());
        expect($eligible()['age_eligible'])->toBeFalse()->and($eligible()['on_hold'])->toBeTrue();
        (new SetRetentionHold)->handle($office, $scope, $context->uuid, $evidence->uuid, 1, false, 'QA release', F::command());
        expect($eligible()['age_eligible'])->toBeTrue()->and($eligible()['automatic_disposal_enabled'])->toBeFalse();
    } finally {
        $this->travelBack();
    }
});

it('expires a receipt at the exact twenty-four-hour context boundary', function () {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    $this->travelTo($context->expires_at->subSecond());
    try {
        expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeTrue();
        $this->travelTo($context->expires_at);
        expect((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeFalse();
        expect(fn () => (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command()))
            ->toThrow(KnowledgeConflict::class, 'stale_knowledge_context');
        expect($receipt->fresh()->applied)->toBeTrue();
    } finally {
        $this->travelBack();
    }
});

it('W4Q-02 computes retention dependencies without hydrating unbounded profile history', function () {
    [$office, $scope] = F::owner();
    [$context] = F::active($office, $scope);
    $evidence = KnowledgeEvidence::query()->where('context_id', $context->id)->firstOrFail();
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect((new KnowledgeQueries)->disposalEligibility($office, $scope, $context->uuid, $evidence->uuid)['active_dependency'])->toBeTrue();
        expect(collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^select \* from ["`]wald_profiles["`]/i', $q['query'])))->toHaveCount(0);
    } finally {
        DB::disableQueryLog();
    }
});

it('rejects direct receipt rewriting and deletion', function ($operation) {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    $query = DB::table('wald_profile_uses')->where('id', $receipt->id);
    expect(fn () => $operation === 'update' ? $query->update(['applied' => false]) : $query->delete())->toThrow(QueryException::class);
    expect($receipt->fresh()->applied)->toBeTrue();
})->with(['update', 'delete']);

it('rejects direct deletion of private provenance parents', function ($table) {
    [$office, $scope] = F::owner();
    F::active($office, $scope);
    expect(fn () => DB::table($table)->delete())->toThrow(QueryException::class);
    expect(DB::table($table)->exists())->toBeTrue();
})->with(['wald_knowledge_contexts', 'wald_knowledge_evidence', 'wald_clarifications', 'wald_profiles']);

it('fails closed on an active competitor with missing selected-version provenance', function ($operation) {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    (new KnowledgeStore)->insert(KnowledgeProfile::class, $office, [...$scope->columns(),
        'state' => 'ACTIVE', 'review_due_at' => now('UTC')->addYear()]); // Deliberately corrupt test fixture.
    expect(fn () => $operation === 'reuse'
        ? (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command())
        : (new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toThrow(ModelNotFoundException::class);
})->with(['reuse', 'receipt']);
