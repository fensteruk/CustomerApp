<?php

namespace Tests\Support;

use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class WaldCustapp2Fixtures
{
    public static function workbook(array $records): UploadedFile
    {
        $headers = [3 => 'CallNo', 4 => 'Site Name', 5 => 'Plot Ref', 9 => 'Call Type', 10 => 'Arrival Date',
            11 => 'Completed', 15 => 'Completed Date', 18 => 'CAS', 19 => 'FLU', 20 => 'VS', 21 => 'TT',
            22 => 'BAY', 23 => 'PFD', 24 => 'PSU', 25 => 'PSG', 26 => 'CDF', 27 => 'CDU', 28 => 'CDG',
            29 => 'GLS', 30 => 'PSP', 31 => 'BF', 32 => 'ALI', 33 => 'AOV', 34 => 'FI', 35 => 'WP', 36 => 'MISC'];
        $rows = [2 => $headers];
        foreach (array_values($records) as $offset => $record) {
            $record = [
                'call' => (string) (1001 + $offset),
                'site' => 'Synthetic Site',
                'plot' => (string) ($offset + 1),
                'type' => 'PC1',
                'complete' => 'No',
                'products' => ['VS' => '2'],
                ...$record,
            ];
            $row = $offset + 4;
            $rows[$row] = [3 => $record['call'], 4 => $record['site'], 5 => $record['plot']];
            if ($record['type'] !== null) {
                $rows[$row][9] = $record['type'];
            }
            if ($record['complete'] !== null) {
                $rows[$row][11] = $record['complete'];
            }
            foreach ($record['products'] as $code => $quantity) {
                $column = array_search($code, $headers, true);
                if ($column !== false && $quantity !== null) {
                    $rows[$row][$column] = $quantity;
                }
            }
        }
        $path = WaldFixtures::xlsx([['name' => 'Data', 'rows' => $rows]]);

        return new UploadedFile($path, 'synthetic-composite.xlsx', null, null, true);
    }

    public static function staged(
        User $actor,
        KnowledgeScope $scope,
        array $records,
        string $date = '2026-09-15',
        string $slot = 'MORNING',
        ?string $predecessor = null,
    ): array {
        $run = (new ImportIntake)->upload(
            $actor,
            $scope,
            self::workbook($records),
            new ExportOrder($date, $slot),
            ExportOrder::CONFIRMATION,
            Wald04Fixtures::command(),
            predecessor: $predecessor,
            reason: $predecessor ? 'Explicit corrected composite export.' : null,
        );
        $analysis = new ImportAnalysis;
        try {
            $analysis->analyse($actor, $scope, $run['run'], 0, Wald04Fixtures::command());
        } catch (ImportConflict $exception) {
            if ($exception->getMessage() !== 'structural_clarification_required') {
                throw $exception;
            }
            $record = DB::table('wald_import_runs')->where('uuid', $run['run'])->firstOrFail();
            $context = KnowledgeContext::query()->findOrFail($record->context_id);
            foreach ((new KnowledgeQueries)->questions($actor, $scope, $context->uuid) as $question) {
                if ($question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                    (new AnswerClarification)->handle(
                        $actor,
                        $scope,
                        $context->uuid,
                        $question['uuid'],
                        $question['sequence'],
                        $question['evidence']['candidates'][0]['id'],
                        'Explicit synthetic composite header review.',
                        Wald04Fixtures::command(),
                    );
                }
            }
            $analysis->analyse($actor, $scope, $run['run'], (int) $record->epoch, Wald04Fixtures::command());
        }

        return (new ImportIntake)->status($actor, $scope, $run['run']);
    }

    public static function reviewed(User $actor, KnowledgeScope $scope, array $records, string $date = '2026-09-15', string $slot = 'MORNING'): array
    {
        $run = self::staged($actor, $scope, $records, $date, $slot);
        $review = new ImportReview;
        $preview = $review->preview($actor, $scope, $run['run'], $run['epoch'], Wald04Fixtures::command());
        if ($preview['blockers'] === []) {
            $review->approve($actor, $scope, $run['run'], $preview['preview'], $preview['hash'], Wald04Fixtures::command());
        }

        return $preview;
    }

    public static function commit(User $actor, KnowledgeScope $scope, array $preview): array
    {
        return (new ImportReview)->commit($actor, $scope, $preview['run'], $preview['preview'], $preview['hash'], Wald04Fixtures::command());
    }
}
