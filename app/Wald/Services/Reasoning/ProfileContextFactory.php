<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\Hypothesis;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleContextProvider;
use App\Wald\Contracts\SourceRange;
use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\WorkbookProfiler;

final class ProfileContextFactory
{
    public function __construct(private readonly ?RuleContextProvider $provider = null) {}

    public function contexts(WorkbookProfile $profile, HypothesisCatalog $catalog): iterable
    {
        if ($this->provider !== null) {
            yield from $this->provider->contexts($profile, $catalog);

            return;
        }
        $data = $profile->toArray();
        if (($data['schema'] ?? null) !== 'wald.workbook-profile.v1' || ($data['complete'] ?? false) !== true || ($data['structural_rules_version'] ?? null) !== WorkbookProfiler::STRUCTURE_VERSION || ! is_string($data['source_checksum'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $data['source_checksum']) || ! is_array($data['sheets'] ?? null) || ! is_array($data['reader'] ?? null) || ! is_string($data['engine_version'] ?? null)) {
            throw new ReasoningProblem('invalid_structural_profile');
        }
        $targets = [];
        foreach ($data['sheets'] as $sheet) {
            if (! is_array($sheet) || ! is_string($sheet['id'] ?? null) || ! is_string($sheet['visibility'] ?? null)) {
                throw new ReasoningProblem('invalid_structural_profile');
            }
            foreach (['regions', 'hidden_column_ranges', 'hidden_rows', 'repeated_blocks'] as $key) {
                if (! is_array($sheet[$key] ?? null)) {
                    throw new ReasoningProblem('invalid_structural_profile');
                }
            }
            foreach ($sheet['hidden_column_ranges'] as $range) {
                if (! is_int($range['start'] ?? null) || ! is_int($range['end'] ?? null)) {
                    throw new ReasoningProblem('invalid_structural_profile');
                }
            }
            foreach ($sheet['repeated_blocks'] as $ids) {
                if (! is_array($ids)) {
                    throw new ReasoningProblem('invalid_structural_profile');
                }
            }
            foreach ($sheet['regions'] as $region) {
                if (($region['hypothesis'] ?? null) !== 'table') {
                    continue;
                }
                if (! is_string($region['id'] ?? null) || ! is_array($region['headers']['paths'] ?? null) || ! is_array($region['headers']['warnings'] ?? null)
                    || ! array_key_exists('range', $region['headers']) || ! is_array($region['column_profiles'] ?? null) || ! is_string($region['orientation'] ?? null)
                    || ! is_numeric($region['density'] ?? null) || ! is_int($region['range']['start_row'] ?? null) || ! is_int($region['range']['end_row'] ?? null)) {
                    throw new ReasoningProblem('invalid_structural_profile');
                }
                foreach ($region['headers']['paths'] as $header) {
                    if (! is_int($header['column'] ?? null) || ! is_string($header['label'] ?? null) || ! is_array($header['source_refs'] ?? null)) {
                        throw new ReasoningProblem('invalid_structural_profile');
                    }
                }
                $headerPaths = array_column($region['headers']['paths'], null, 'column');
                foreach ($region['column_profiles'] as $column) {
                    $this->validateColumn($column);
                    if (count($targets) >= 1024) {
                        throw new ReasoningProblem('reasoning_limit_exceeded');
                    }
                    $number = $column['column'];
                    $targetId = $region['id'].':column-'.$number;
                    if (isset($targets[$targetId]) || ! is_int($number) || $number < 1 || $column['population'] < 0 || $column['observed'] < 0 || $column['observed'] > $column['population']) {
                        throw new ReasoningProblem('invalid_structural_profile');
                    }
                    $targets[$targetId] = true;
                    $header = $headerPaths[$number] ?? ['label' => '', 'source_refs' => []];
                    $hidden = $sheet['visibility'] !== 'visible' || count(array_filter($sheet['hidden_column_ranges'], fn ($range) => $number >= $range['start'] && $number <= $range['end'])) > 0
                        || count(array_filter($sheet['hidden_rows'], fn ($row) => $row >= $region['range']['start_row'] && $row <= $region['range']['end_row'])) > 0;
                    $repeated = false;
                    foreach ($sheet['repeated_blocks'] as $ids) {
                        $repeated = $repeated || in_array($region['id'], $ids, true);
                    }
                    $target = ['id' => $targetId, 'sheet_id' => $sheet['id'], 'region_id' => $region['id'], 'column' => $number, 'label' => $header['label'],
                        'source_refs' => [['source_checksum' => $data['source_checksum'], 'sheet_id' => $sheet['id'], 'region_id' => $region['id'], 'column' => $number,
                            'range' => SourceRange::columnLetters($number).$region['range']['start_row'].':'.SourceRange::columnLetters($number).$region['range']['end_row'], 'header_refs' => $header['source_refs']]],
                        'samples' => array_slice($column['samples'], 0, 5)];
                    $structure = ['header' => $header['label'], 'has_header' => $region['headers']['range'] !== null, 'density' => $region['density'], 'hidden' => $hidden, 'repeated' => $repeated, 'orientation' => $region['orientation'],
                        'uncertain' => $region['headers']['warnings'] !== [] || ! in_array($region['orientation'], ['vertical_records'], true), 'critical_full_validation' => false];
                    $traits = $this->traits($column);
                    foreach ($catalog->forTraits($traits) as $definition) {
                        yield new RuleContext(new Hypothesis($definition, $target), $column, $structure, $traits);
                    }
                }
            }
        }
    }

    private function traits(array $column): array
    {
        $types = $column['types'];
        $observed = max(1, $column['observed']);
        $text = $types['text'] / $observed;
        $numeric = ($types['integer'] + $types['decimal']) / $observed;
        $identifier = $column['identifier']['count'] / $observed;

        return ['identifier_shaped' => $identifier >= GenericRuleSettings::RATIOS['compatible'], 'numeric' => $numeric >= GenericRuleSettings::RATIOS['compatible'], 'date_like' => GenericRuleSettings::RATIOS['compatible'] <= $types['date_like'] / $observed, 'percentage_like' => GenericRuleSettings::RATIOS['compatible'] <= $types['percentage_like'] / $observed,
            'low_cardinality' => $column['uniqueness'] !== null && $column['uniqueness'] <= GenericRuleSettings::RATIOS['low_cardinality'],
            'identifier_ratio' => $identifier, 'percentage_ratio' => $types['percentage_like'] / $observed, 'blank_ratio' => (float) ($types['blank'] / max(1, $column['population'])),
            'compatibility' => ['identifier_like' => $identifier, 'date_like' => $types['date_like'] / $observed, 'quantity_like' => $numeric,
                'category_like' => $text * ($column['text']['average_length'] <= GenericRuleSettings::FREE_TEXT_LENGTH ? 1 : GenericRuleSettings::RATIOS['incompatible']), 'free_text_like' => $text * ($column['text']['free_text_like'] ? 1 : GenericRuleSettings::RATIOS['incompatible'])]];
    }

    private function validateColumn(mixed $column): void
    {
        if (! is_array($column)) {
            throw new ReasoningProblem('invalid_structural_profile');
        }
        foreach (['column', 'population', 'observed', 'distinct_count'] as $key) {
            if (! is_int($column[$key] ?? null) || $column[$key] < 0) {
                throw new ReasoningProblem('invalid_structural_profile');
            }
        }
        foreach (['blank', 'text', 'integer', 'decimal', 'date_like', 'percentage_like', 'boolean_like', 'formula', 'identifier_shaped', 'error'] as $type) {
            if (! is_int($column['types'][$type] ?? null) || $column['types'][$type] < 0 || $column['types'][$type] > $column['population']) {
                throw new ReasoningProblem('invalid_structural_profile');
            }
        }
        if (! array_key_exists('uniqueness', $column) || ($column['uniqueness'] !== null && (! is_numeric($column['uniqueness']) || $column['uniqueness'] < 0 || $column['uniqueness'] > 1))
            || ! is_int($column['identifier']['count'] ?? null) || $column['identifier']['count'] < 0 || $column['identifier']['count'] > $column['observed']
            || ! is_bool($column['dates']['ambiguous'] ?? null) || ! is_numeric($column['text']['average_length'] ?? null)
            || ! is_bool($column['text']['free_text_like'] ?? null) || ! is_array($column['samples'] ?? null)) {
            throw new ReasoningProblem('invalid_structural_profile');
        }
    }
}
