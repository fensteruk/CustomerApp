<?php

namespace Tests\Support;

use App\SourceImport\Semantics\Data\CoreIdentity;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\Reasoning\ReasoningResult;
use App\Wald\Contracts\SourceRange;

/** SAFE_SYNTHETIC contract fixtures; actual engine integration is tested separately. */
final class Wald03Fixtures
{
    public static function reasoning(array $headers = ['Call Type', 'Complete']): ReasoningResult
    {
        $manifest = ['structural_engine_version' => CoreIdentity::READER,
            'engine_version' => 'wald-0.3.0', 'structural_rules_version' => 'wald.structure.v1.1',
            'ruleset_version' => 'wald.generic-rules.v1', 'source_checksum' => str_repeat('a', 64),
            'confidence_policy' => ['version' => 'wald.confidence.v1']];
        $targets = $hypotheses = [];
        foreach ($headers as $index => $header) {
            $column = $index + 1;
            $id = 'candidate-'.$column;
            $targetId = 'region:column-'.$column;
            $letter = SourceRange::columnLetters($column);
            $targets[] = ['target_id' => $targetId, 'leading_candidate' => $id, 'decision' => 'accepted'];
            $target = ['id' => $targetId, 'sheet_id' => 'sheet-1', 'region_id' => 'region',
                'column' => $column, 'label' => $header,
                'source_refs' => [['source_checksum' => str_repeat('a', 64), 'sheet_id' => 'sheet-1',
                    'region_id' => 'region', 'column' => $column, 'range' => $letter.'1:'.$letter.'11',
                    'header_refs' => [['cell' => $letter.'1', 'merge_range' => null]]]]];
            $hypotheses[] = ['hypothesis' => ['id' => $id, 'target' => $target],
                'decision' => 'accepted', 'confirmation_flags' => [],
                'confidence' => ['band' => 'high_confidence', 'is_probability' => false],
                'evidence' => [['id' => 'synthetic-evidence-'.$column]]];
        }

        return new ReasoningResult(['schema' => 'wald.reasoning.v1', 'complete' => true,
            'manifest' => $manifest, 'manifest_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
            'hypotheses' => $hypotheses, 'targets' => $targets, 'clarifications' => []]);
    }

    public static function cell(string $raw, int $column = 1, int $row = 2): ObservedCell
    {
        return new ObservedCell(str_repeat('a', 64), 'sheet-1',
            new CellObservation($row, $column, $raw, source: ['cell' => SourceRange::columnLetters($column).$row]));
    }
}
