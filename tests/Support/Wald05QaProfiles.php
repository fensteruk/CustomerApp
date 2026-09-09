<?php

namespace Tests\Support;

use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportClarification;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use Illuminate\Support\Facades\DB;

final class Wald05QaProfiles
{
    public static function reviewed($actor, $scope, int $uses = 1): array
    {
        $first = Wald05BackendFixtures::staged($actor, $scope);
        $context = KnowledgeContext::findOrFail(DB::table('wald_import_runs')->where('uuid', $first['run'])->value('context_id'));
        $q = collect((new KnowledgeQueries)->questions($actor, $scope, $context->uuid))->firstWhere('key', 'structure:quantity:VS');
        $answer = (new AnswerClarification)->handle($actor, $scope, $context->uuid, $q['uuid'], $q['sequence'], $q['evidence']['candidates'][0]['id'], 'QA reviewed structure.', Wald04Fixtures::command());
        $version = (new SaveProfileDraft)->handle($actor, $scope, $context->uuid, $answer->uuid, Wald04Fixtures::command());
        $profile = KnowledgeProfile::findOrFail($version->profile_id);
        $profile = (new ActivateProfile)->handle($actor, $scope, $profile->uuid, $version->version, $version->definition_hash, $profile->lock_version, 'QA activation.', Wald04Fixtures::command());
        $run = (new ImportIntake)->upload($actor, $scope, Wald05BackendFixtures::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, Wald04Fixtures::command());
        try {
            (new ImportAnalysis)->analyse($actor, $scope, $run['run'], 0, Wald04Fixtures::command());
        } catch (ImportConflict $e) {
            if ($e->getMessage() !== 'structural_clarification_required') {
                throw $e;
            }
        }
        $clarify = new ImportClarification;
        for ($i = 0; $i < $uses; $i++) {
            $state = (new ImportIntake)->status($actor, $scope, $run['run']);
            $use = $clarify->useProfile($actor, $scope, $run['run'], $state['epoch'], $profile->uuid, $profile->lock_version, Wald04Fixtures::command());
            if (! $use['applied']) {
                throw new \RuntimeException('QA requires an actually applied profile receipt');
            }
        }
        foreach ($clarify->questions($actor, $scope, $run['run']) as $q) {
            if ($q['key'] !== 'structure:quantity:VS' && $q['evidence']['type'] === 'STRUCTURAL' && count($q['evidence']['candidates']) === 1) {
                $state = (new ImportIntake)->status($actor, $scope, $run['run']);
                $clarify->answer($actor, $scope, $run['run'], $state['epoch'], $q['uuid'], $q['sequence'], $q['evidence']['candidates'][0]['id'], 'QA review.', Wald04Fixtures::command());
            }
        }
        $state = (new ImportIntake)->status($actor, $scope, $run['run']);
        (new ImportAnalysis)->analyse($actor, $scope, $run['run'], $state['epoch'], Wald04Fixtures::command());
        $state = (new ImportIntake)->status($actor, $scope, $run['run']);
        $preview = (new ImportReview)->preview($actor, $scope, $run['run'], $state['epoch'], Wald04Fixtures::command());
        (new ImportReview)->approve($actor, $scope, $run['run'], $preview['preview'], $preview['hash'], Wald04Fixtures::command());

        return [$preview, $profile];
    }
}
