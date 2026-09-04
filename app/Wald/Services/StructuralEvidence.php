<?php

namespace App\Wald\Services;

use App\Wald\Contracts\SourceRange;

final class StructuralEvidence
{
    public static function make(string $rule, string $sheet, SourceRange $range, array $observation, string $explanation, array $limitations = []): array
    {
        $key = 'wald.structure.'.$rule;
        $candidate = $sheet.':'.$range->address();

        return [
            'evidence_id' => hash('sha256', $key.'|1|'.$candidate.'|'.json_encode($observation, JSON_THROW_ON_ERROR)),
            'rule_key' => $key, 'rule_version' => '1', 'candidate_id' => $candidate,
            'evidence_family' => 'surrounding_structure', 'correlation_key' => $key.':'.$candidate,
            'source_refs' => [['sheet_id' => $sheet, 'range' => $range->address()]],
            'observation' => $observation, 'direction' => 'supporting',
            'contribution' => null, 'cap' => null,
            'explanation_code' => $rule, 'explanation_parameters' => $observation, 'explanation' => $explanation,
            'coverage' => 'full_bounded_region', 'limitations' => $limitations,
            'profile_ref' => null, 'dictionary_ref' => null, 'clarification_ref' => null,
        ];
    }
}
