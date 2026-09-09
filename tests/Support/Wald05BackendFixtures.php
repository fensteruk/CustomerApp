<?php

namespace Tests\Support;

use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class Wald05BackendFixtures
{
    public static int $callBase = 1001;

    public static function binding(User $actor, KnowledgeScope $scope): array
    {
        $bindings = new SourceBindingService;
        $draft = $bindings->draft($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', 'Explicit fixture mapping.', Wald04Fixtures::command());

        return $bindings->activate($actor, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Explicit fixture activation.', Wald04Fixtures::command());
    }

    public static function workbook(array $overrides = [], array $headers = ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'], int $count = 7): UploadedFile
    {
        $rows = [1 => array_combine(range(1, count($headers)), $headers)];
        for ($i = 0; $i < $count; $i++) {
            $record = ['Call No.' => (string) (self::$callBase + $i), 'Site Name' => 'Synthetic Site', 'Plot' => sprintf('%03d', $i + 1), 'Call Type' => 'PC1', 'complete' => 'No', 'VS' => '2.125', 'BF' => '1.000'];
            $record = [...$record, ...($overrides[$i] ?? [])];
            foreach ($headers as $column => $header) {
                $rows[$i + 2][$column + 1] = $record[$header] ?? '';
            }
        }
        $path = WaldFixtures::xlsx([['name' => 'Data', 'rows' => $rows]]);

        return new UploadedFile($path, 'synthetic.xlsx', null, null, true);
    }

    public static function staged(User $actor, KnowledgeScope $scope, array $overrides = [], string $date = '2026-09-09', string $slot = 'MORNING', ?string $predecessor = null, array $headers = ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'], int $count = 7, string $coverage = 'PARTIAL_FILTERED_EXPORT'): array
    {
        $run = (new ImportIntake)->upload($actor, $scope, self::workbook($overrides, $headers, $count), new ExportOrder($date, $slot), ExportOrder::CONFIRMATION,
            Wald04Fixtures::command(), coverage: $coverage, predecessor: $predecessor, reason: $predecessor ? 'Explicit corrected export.' : null);
        $analysis = new ImportAnalysis;
        try {
            $analysis->analyse($actor, $scope, $run['run'], 0, Wald04Fixtures::command());
        } catch (ImportConflict $e) {
            if ($e->getMessage() !== 'structural_clarification_required') {
                throw $e;
            }
            $record = DB::table('wald_import_runs')->where('uuid', $run['run'])->firstOrFail();
            $context = KnowledgeContext::query()->findOrFail($record->context_id);
            foreach ((new KnowledgeQueries)->questions($actor, $scope, $context->uuid) as $question) {
                if ($question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                    (new AnswerClarification)->handle($actor, $scope, $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Explicit synthetic header review.', Wald04Fixtures::command());
                }
            }
            $analysis->analyse($actor, $scope, $run['run'], (int) $record->epoch, Wald04Fixtures::command());
        }

        return (new ImportIntake)->status($actor, $scope, $run['run']);
    }

    public static function reviewed(User $actor, KnowledgeScope $scope, array $overrides = [], string $date = '2026-09-09', string $slot = 'MORNING', ?string $predecessor = null, array $headers = ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'], int $count = 7): array
    {
        $run = self::staged($actor, $scope, $overrides, $date, $slot, $predecessor, $headers, $count);
        $review = new ImportReview;
        $preview = $review->preview($actor, $scope, $run['run'], $run['epoch'], Wald04Fixtures::command());
        if ($preview['blockers'] !== []) {
            throw new ImportConflict(implode(',', $preview['blockers']));
        }
        $review->approve($actor, $scope, $run['run'], $preview['preview'], $preview['hash'], Wald04Fixtures::command());

        return $preview;
    }

    public static function commit(User $actor, KnowledgeScope $scope, array $preview): array
    {
        return (new ImportReview)->commit($actor, $scope, $preview['run'], $preview['preview'], $preview['hash'], Wald04Fixtures::command());
    }
}
