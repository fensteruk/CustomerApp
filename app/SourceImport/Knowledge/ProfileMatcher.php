<?php

namespace App\SourceImport\Knowledge;

final class ProfileMatcher
{
    public function evaluate(array $definition, array $fresh): array
    {
        $identity = new KnowledgeIdentity;
        foreach ([$definition['pins'] ?? [], $fresh['pins'] ?? []] as $pins) {
            $compatibility = $identity->compatible($pins);
            if ($compatibility !== Compatibility::Exact) {
                return $this->result($compatibility, 'INCOMPATIBLE_IDENTITY');
            }
        }
        $selection = $definition['selection'];
        $question = $fresh['questions']['structure:'.$selection['role']] ?? null;
        if ($question === null || $question['candidates'] === []) {
            return $this->result(Compatibility::Incompatible, 'MISSING_CRITICAL_ROLE');
        }
        if (! ($fresh['fresh']['complete'] ?? false) || count($fresh['tables']) !== 1 || count($question['candidates']) !== 1) {
            return $this->result(Compatibility::Review, 'CURRENT_AMBIGUITY');
        }
        $candidate = $question['candidates'][0];
        $table = $fresh['tables'][$candidate['table']];
        $current = $table['descriptor'];
        $safety = $table['column_safety'][$candidate['column']] ?? null;
        if ($safety === null || $safety['error_or_formula'] || $safety['negative_quantity'] || $safety['empty']
            || ($safety['date_or_percent'] && $selection['role'] !== 'pc1_operational_install_date')) {
            return $this->result(Compatibility::Incompatible, 'CONTRADICTORY_CURRENT_VALUES');
        }
        if ($table['warnings'] !== [] || $current['visibility'] !== 'visible' || $current['hidden_columns'] || $current['hidden_rows']
            || ! in_array($current['orientation'], ['vertical_records', 'matrix'], true)) {
            return $this->result(Compatibility::Incompatible, 'UNSAFE_CURRENT_STRUCTURE');
        }
        // Generic current ambiguity is not replaced with historic confidence.
        foreach ($fresh['fresh']['clarifications'] ?? [] as $clarification) {
            $related = array_values(array_filter($clarification['competing_candidates'] ?? [], function ($item) use ($candidate, $table) {
                foreach ($item['source_refs'] ?? [] as $ref) {
                    if (($ref['sheet_id'] ?? null) === $table['sheet_id'] && ($ref['column'] ?? null) === $candidate['column']) {
                        return true;
                    }
                }

                return false;
            }));
            if ($related !== [] && (count($clarification['competing_candidates']) > 1 || array_filter($related, fn ($item) => $item['veto'] ?? false))) {
                return $this->result(Compatibility::Review, 'FRESH_REASONING_REQUIRES_REVIEW');
            }
        }
        $old = $definition['descriptor'];
        $oldColumns = $old['columns'];
        $newColumns = $current['columns'];
        foreach ($oldColumns as $column) {
            if (! in_array($column, $newColumns, true)) {
                // Known role-header aliases can suggest a review, never silently apply a rename.
                $sameRole = array_filter($newColumns, fn ($currentColumn) => $column['role'] !== null
                    && ! str_starts_with($column['role'], 'quantity:') && $currentColumn['role'] === $column['role']);
                if (count($sameRole) !== 1) {
                    return $this->result(Compatibility::Incompatible, 'CRITICAL_STRUCTURE_CHANGED');
                }
            }
        }
        if (count(array_unique(array_column($newColumns, 'header'))) !== count($newColumns)) {
            return $this->result(Compatibility::Incompatible, 'DUPLICATE_SELECTOR');
        }
        $exact = Canonical::hash($old) === Canonical::hash($current);

        return [...$this->result($exact ? Compatibility::Exact : Compatibility::Review, $exact ? 'EXACT_REVIEWED_STRUCTURE' : 'STRUCTURE_CHANGED_REVIEW'),
            'selection' => $candidate];
    }

    private function result(Compatibility $compatibility, string $reason): array
    {
        return ['compatibility' => $compatibility->value, 'reason' => $reason, 'applied' => false];
    }
}
