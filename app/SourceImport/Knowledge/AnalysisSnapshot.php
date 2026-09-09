<?php

namespace App\SourceImport\Knowledge;

use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\SemanticAdapter;
use App\Wald\Contracts\SourceRange;
use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\Reasoning\ReasoningEngine;
use InvalidArgumentException;

/** Internal trusted-analysis boundary, never a request deserializer or upload endpoint. */
final readonly class AnalysisSnapshot
{
    private function __construct(public array $data) {}

    /** Observations must come from the same privately registered reader source. */
    public static function fromProfile(WorkbookProfile $profile, array $observations = []): self
    {
        $p = $profile->toArray();
        if (($p['schema'] ?? null) !== 'wald.workbook-profile.v1' || ($p['complete'] ?? null) !== true
            || ($p['engine_version'] ?? null) !== 'wald-0.2.2' || ($p['structural_rules_version'] ?? null) !== 'wald.structure.v1.1'
            || ! preg_match('/^[a-f0-9]{64}$/D', $p['source_checksum'] ?? '')) {
            throw new InvalidArgumentException('invalid_trusted_analysis');
        }
        $reasoning = (new ReasoningEngine)->reason($profile);
        $r = $reasoning->toArray();
        $tables = $questions = [];
        $dictionary = new CustomerAppDictionary;
        foreach ($p['sheets'] ?? [] as $sheet) {
            foreach ($sheet['regions'] ?? [] as $region) {
                if (($region['hypothesis'] ?? null) !== 'table' || ($region['headers']['range'] ?? null) === null) {
                    continue;
                }
                $id = $sheet['id'].':'.$region['id'];
                $columns = $safety = [];
                foreach ($region['headers']['paths'] as $path) {
                    if (trim($path['label']) === '') {
                        continue;
                    }
                    $header = $path['label'];
                    $token = self::token($header);
                    $role = $dictionary->field($header)->value;
                    if (in_array($token, ['plot', 'plot no.', 'plot number', 'house no.', 'sales plot'], true)) {
                        $role = 'plot_reference';
                    }
                    $product = $dictionary->product($header, '0');
                    if ($role === null && $product->isResolved()) {
                        $role = 'quantity:'.$product->lookupValue;
                    }
                    $columns[] = ['header' => $token, 'parts' => array_map(self::token(...), $path['parts']), 'role' => $role];
                    $distribution = array_values(array_filter($region['column_profiles'], fn ($column) => $column['column'] === $path['column']))[0] ?? [];
                    $types = $distribution['types'] ?? [];
                    $safety[$path['column']] = ['error_or_formula' => ($types['error'] ?? 0) > 0 || ($types['formula'] ?? 0) > 0,
                        'negative_quantity' => str_starts_with($role ?? '', 'quantity:') && ($distribution['numeric']['negative_count'] ?? 0) > 0,
                        'date_or_percent' => ($types['date_like'] ?? 0) > 0 || ($types['percentage_like'] ?? 0) > 0,
                        'empty' => ($distribution['observed'] ?? 0) === 0];
                    if ($role !== null) {
                        $candidate = ['id' => Canonical::hash([$id, $path['column'], $role]), 'table' => $id,
                            'column' => $path['column'], 'header' => $header, 'selector' => $token, 'role' => $role];
                        $questions['structure:'.$role]['type'] = 'STRUCTURAL';
                        $questions['structure:'.$role]['candidates'][] = $candidate;
                    }
                }
                $hr = $region['headers']['range'];
                $merges = [];
                foreach ($sheet['merge_ranges'] as $merge) {
                    if ($merge['start_row'] <= $hr['end_row'] && $merge['end_row'] >= $hr['start_row']) {
                        $merges[] = [$merge['start_row'] - $hr['start_row'], $merge['end_row'] - $hr['start_row'],
                            $merge['start_column'] - $hr['start_column'], $merge['end_column'] - $hr['start_column']];
                    }
                }
                $descriptor = ['sheet' => self::token($sheet['name']), 'visibility' => $sheet['visibility'],
                    'format' => $p['reader']['format'] ?? null, 'orientation' => $region['orientation'],
                    'depth' => $hr['end_row'] - $hr['start_row'] + 1, 'merges' => $merges, 'columns' => $columns,
                    'hidden_columns' => $sheet['hidden_column_count'] > 0, 'hidden_rows' => $sheet['hidden_row_count'] > 0];
                $tables[$id] = ['descriptor' => $descriptor, 'range' => $region['range'], 'header_range' => $hr,
                    'sheet_id' => $sheet['id'], 'region_id' => $region['id'], 'column_safety' => $safety,
                    'warnings' => array_values(array_unique([...$sheet['warnings'], ...($region['headers']['warnings'] ?? [])]))];
            }
        }
        foreach ($observations as $observation) {
            if (! $observation instanceof ObservedCell || $observation->sourceChecksum !== $p['source_checksum']) {
                throw new InvalidArgumentException('observation_provenance_mismatch');
            }
            foreach ($r['hypotheses'] ?? [] as $hypothesis) {
                $target = $hypothesis['hypothesis']['target'] ?? [];
                if (($target['sheet_id'] ?? null) !== $observation->sheetId || ($target['column'] ?? null) !== $observation->cell->column
                    || $dictionary->field($target['label'] ?? '')->value !== 'call_type') {
                    continue;
                }
                $semantic = (new SemanticAdapter)->interpret($observation, $reasoning, $hypothesis['hypothesis']['id']);
                $key = 'occurrence:'.Canonical::hash($observation->jsonSerialize());
                $questions[$key] = ['type' => 'SEMANTIC', 'candidates' => [], 'original' => $semantic->jsonSerialize(),
                    'observation' => $observation->jsonSerialize()];
                $questions[$key]['requires_structural_column'] = $observation->cell->column;
                $questions[$key]['requires_structural_sheet'] = $observation->sheetId;
                // Offer only a dictionary suggestion. Answering additionally requires an explicit
                // matching structural answer; no original blocked result is rewritten.
                $refs = $target['source_refs'] ?? [];
                $inside = count($refs) === 1 && isset($refs[0]['range'])
                    && SourceRange::parse($refs[0]['range'])->contains($observation->cell->row, $observation->cell->column);
                $afterHeader = true;
                foreach ($refs[0]['header_refs'] ?? [] as $ref) {
                    $afterHeader = $afterHeader && $observation->cell->row > SourceRange::parse($ref['merge_range'] ?? $ref['cell'])->endRow;
                }
                if ($dictionary->callType($observation->cell->rawValue)->lookupValue === 'CC!' && $inside && $afterHeader
                    && $observation->cell->formula === null && $observation->cell->type !== 'error') {
                    $questions[$key]['candidates'][] = ['id' => 'CC1', 'canonical' => 'CC1'];
                }
            }
        }
        ksort($tables, SORT_STRING);
        ksort($questions, SORT_STRING);
        foreach ($questions as &$question) {
            usort($question['candidates'], fn ($a, $b) => strcmp($a['id'], $b['id']));
            if (count($question['candidates']) > 50) {
                throw new InvalidArgumentException('knowledge_candidate_limit');
            }
            Canonical::json($question, 65536);
        }
        unset($question);
        $clarifications = $r['clarifications'] ?? [];
        foreach ($clarifications as &$clarification) {
            foreach ($clarification['competing_candidates'] as &$candidate) {
                unset($candidate['samples']);
            }
            unset($candidate);
        }
        unset($clarification);
        $data = ['source_checksum' => $p['source_checksum'], 'analysis_hash' => Canonical::evidenceHash($p),
            'reasoning_hash' => Canonical::evidenceHash($r), 'pins' => (new KnowledgeIdentity)->current(),
            'tables' => $tables, 'questions' => $questions,
            'fresh' => ['complete' => $r['complete'] ?? false, 'clarifications' => $clarifications,
                'targets' => $r['targets'] ?? [], 'warnings' => $r['warnings'] ?? []]];
        Canonical::json($data);

        return new self($data);
    }

    public static function token(string $header): string
    {
        return strtr(preg_replace('/\s+/u', ' ', trim($header)), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
