<?php

use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportClarification;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('uses an exact actively reviewed profile receipt and refuses commit after its revocation', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $first = B::staged($actor, $scope);
    $run = DB::table('wald_import_runs')->where('uuid', $first['run'])->first();
    $context = KnowledgeContext::query()->findOrFail($run->context_id);
    $q = collect((new KnowledgeQueries)->questions($actor, $scope, $context->uuid))->firstWhere('key', 'structure:quantity:VS');
    $answer = (new AnswerClarification)->handle($actor, $scope, $context->uuid, $q['uuid'], $q['sequence'], $q['evidence']['candidates'][0]['id'], 'Remember this reviewed structure.', F::command());
    $version = (new SaveProfileDraft)->handle($actor, $scope, $context->uuid, $answer->uuid, F::command());
    $profile = KnowledgeProfile::query()->findOrFail($version->profile_id);
    $profile = (new ActivateProfile)->handle($actor, $scope, $profile->uuid, $version->version, $version->definition_hash, $profile->lock_version, 'Explicit activation.', F::command());
    $upload = (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
    try {
        (new ImportAnalysis)->analyse($actor, $scope, $upload['run'], 0, F::command());
    } catch (ImportConflict $e) {
        expect($e->getMessage())->toBe('structural_clarification_required');
    }
    $second = (new ImportIntake)->status($actor, $scope, $upload['run']);
    $clarify = new ImportClarification;
    $receipt = $clarify->useProfile($actor, $scope, $second['run'], $second['epoch'], $profile->uuid, $profile->lock_version, F::command());
    $use = DB::table('wald_profile_uses')->where('uuid', $receipt['profile_receipt'])->firstOrFail();
    expect($use->reason)->toBe('EXACT_REVIEWED_STRUCTURE');
    expect($receipt['applied'])->toBeTrue();
    foreach ($clarify->questions($actor, $scope, $second['run']) as $q) {
        if ($q['key'] !== 'structure:quantity:VS' && $q['evidence']['type'] === 'STRUCTURAL' && count($q['evidence']['candidates']) === 1) {
            $state = (new ImportIntake)->status($actor, $scope, $second['run']);
            $clarify->answer($actor, $scope, $state['run'], $state['epoch'], $q['uuid'], $q['sequence'], $q['evidence']['candidates'][0]['id'], 'Review other source columns.', F::command());
        }
    }
    $state = (new ImportIntake)->status($actor, $scope, $second['run']);
    (new ImportAnalysis)->analyse($actor, $scope, $state['run'], $state['epoch'], F::command());
    $state = (new ImportIntake)->status($actor, $scope, $second['run']);
    $preview = (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command());
    (new ImportReview)->approve($actor, $scope, $state['run'], $preview['preview'], $preview['hash'], F::command());
    (new RevokeProfile)->handle($actor, $scope, $profile->uuid, $profile->lock_version, 'Revoked before commit.', F::command());
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'stale_profile_receipt');
});

it('applies only the explicitly answered occurrence correction and preserves raw CC exclamation evidence', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $state = B::staged($actor, $scope, [0 => ['Call Type' => 'CC!']]);
    $clarify = new ImportClarification;
    $questions = collect($clarify->questions($actor, $scope, $state['run']));
    $structure = $questions->firstWhere('key', 'structure:call_type');
    $clarify->answer($actor, $scope, $state['run'], $state['epoch'], $structure['uuid'], $structure['sequence'], $structure['evidence']['candidates'][0]['id'], 'Confirm source call-type column.', F::command());
    $state = (new ImportIntake)->status($actor, $scope, $state['run']);
    $semantic = $questions->first(fn ($q) => $q['evidence']['type'] === 'SEMANTIC');
    $clarify->answer($actor, $scope, $state['run'], $state['epoch'], $semantic['uuid'], $semantic['sequence'], 'CC1', 'This exact occurrence is a typo.', F::command());
    $state = (new ImportIntake)->status($actor, $scope, $state['run']);
    (new ImportAnalysis)->analyse($actor, $scope, $state['run'], $state['epoch'], F::command());
    $state = (new ImportIntake)->status($actor, $scope, $state['run']);
    $preview = (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command());
    expect($preview['blockers'])->toBe([]);
    $row = (new ImportReview)->details($actor, $scope, $state['run'])[0];
    expect($row['provenance']['raw_call_type'])->toBe('CC!')->and($row['facts']['call_type'])->toBe('CC1');
    $other = B::staged($actor, $scope, [0 => ['Call Type' => 'CC!']]);
    $otherPreview = (new ImportReview)->preview($actor, $scope, $other['run'], $other['epoch'], F::command());
    expect($otherPreview['blockers'])->toContain('BLOCKED_STAGED_RECORDS');
});
