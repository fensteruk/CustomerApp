<?php

namespace App\SourceImport\Semantics;

use App\SourceImport\Semantics\Contracts\SourceBusinessDictionary;
use App\SourceImport\Semantics\Data\CallSemantics;
use App\SourceImport\Semantics\Data\CoreIdentity;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Data\SemanticResult;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\Enums\Classification as C;
use App\SourceImport\Semantics\Enums\Resolution as R;
use App\Wald\Contracts\Reasoning\ReasoningResult;
use App\Wald\Contracts\SourceRange;
use App\Wald\Services\AnalysisProblem;

/**
 * Composes trusted in-memory Wald evidence with exact dictionary terminology.
 * No confirmation bypass, inferred aliases, staging authority or source access.
 */
final readonly class SemanticAdapter
{
    public function __construct(private SourceBusinessDictionary $dictionary = new CustomerAppDictionary) {}

    public function interpret(ObservedCell $observation, ReasoningResult $reasoning, string $candidateId): SemanticResult
    {
        $data = $reasoning->toArray();
        $matches = array_values(array_filter($data['hypotheses'] ?? [],
            fn ($candidate) => ($candidate['hypothesis']['id'] ?? null) === $candidateId));
        $candidate = count($matches) === 1 ? $matches[0] : null;
        $target = $candidate['hypothesis']['target'] ?? [];
        $header = $target['label'] ?? '';
        $raw = $observation->cell->rawValue;
        $field = $this->dictionary->field($header);
        $semantic = match ($field->value) {
            'call_type' => $this->dictionary->callType($raw),
            'completion' => $this->dictionary->completion($raw),
            null => $this->dictionary->product($header, $raw),
            default => new SemanticResult('field', $raw, $field->lookupValue, $field->classification,
                $field->resolution, $field->dictionary, $field->match, $field->value,
                reasons: $field->reasons),
        };
        // An unknown heading is an unknown field, not an invented product meaning.
        if ($field->value === null && $semantic->classification === C::Unknown) {
            $semantic = new SemanticResult('field', $raw, $field->lookupValue, C::Unknown,
                R::RequiresConfirmation, $field->dictionary, reasons: ['UNKNOWN_FIELD_OR_PRODUCT']);
        }
        $clarifications = array_values(array_filter($data['clarifications'] ?? [],
            fn ($item) => in_array($candidateId, array_column($item['competing_candidates'] ?? [], 'id'), true)));
        $evidence = [...$semantic->evidence, 'observation' => $observation->jsonSerialize(),
            'raw_header' => $header, 'wald_candidate' => $candidate,
            'wald_clarifications' => $clarifications, 'wald_manifest' => $data['manifest'] ?? null,
            'wald_manifest_hash' => $data['manifest_hash'] ?? null];
        $reason = $this->boundaryFailure($observation, $data, $candidate);
        if ($reason !== null) {
            return $this->attach($semantic, $evidence, C::Invalid, R::Blocked, $reason);
        }
        if ($clarifications !== []) {
            return $this->attach($semantic, $evidence, C::Ambiguous, R::Blocked, 'WALD_CLARIFICATION_REQUIRED');
        }
        $decision = $candidate['decision'] ?? null;
        if ($decision !== 'accepted') {
            return $this->attach($semantic, $evidence,
                in_array($decision, ['rejected', 'contradiction'], true) ? C::Invalid : C::Ambiguous,
                R::Blocked, 'WALD_'.strtoupper($decision ?? 'MISSING_DECISION'));
        }
        // Preserve even inconsistent confirmation flags as a fail-closed boundary.
        if (in_array(true, $candidate['confirmation_flags'] ?? [], true)) {
            return $this->attach($semantic, $evidence, C::Ambiguous, R::Blocked, 'WALD_CONFIRMATION_FLAG');
        }

        return $this->attach($semantic, $evidence);
    }

    /** A service completion is only emitted for two resolved cells in the same source record. */
    public function interpretCall(
        ObservedCell $call,
        string $callCandidate,
        ObservedCell $complete,
        string $completionCandidate,
        ReasoningResult $reasoning,
    ): CallSemantics {
        $type = $this->interpret($call, $reasoning, $callCandidate);
        $flag = $this->interpret($complete, $reasoning, $completionCandidate);
        if ($call->sourceChecksum !== $complete->sourceChecksum || $call->sheetId !== $complete->sheetId
            || $call->cell->row !== $complete->cell->row
            || ($type->evidence['wald_candidate']['hypothesis']['target']['region_id'] ?? null)
                !== ($flag->evidence['wald_candidate']['hypothesis']['target']['region_id'] ?? null)) {
            return new CallSemantics($type, $flag, null, null, R::Blocked, ['DIFFERENT_SOURCE_RECORD']);
        }
        if ($type->concept !== 'call_type' || ! $type->isResolved() || ! is_string($type->value)) {
            return new CallSemantics($type, $flag, null, null, R::Blocked, ['SERVICE_IDENTITY_UNRESOLVED']);
        }
        if ($flag->concept !== 'completion' || ! $flag->isResolved() || ! is_bool($flag->value)) {
            return new CallSemantics($type, $flag, $type->value, null, R::Blocked, ['COMPLETION_UNRESOLVED']);
        }

        return new CallSemantics($type, $flag, $type->value, $flag->value, R::Resolved, ['RESOLVED_SOURCE_FACTS_ONLY']);
    }

    private function boundaryFailure(ObservedCell $observation, array $data, ?array $candidate): ?string
    {
        $manifest = $data['manifest'] ?? [];
        if (($data['schema'] ?? null) !== 'wald.reasoning.v1' || ($data['complete'] ?? false) !== true
            || ($manifest['structural_engine_version'] ?? null) !== CoreIdentity::READER
            || ($manifest['engine_version'] ?? null) !== CoreIdentity::snapshot()['reasoning']
            || ($manifest['structural_rules_version'] ?? null) !== CoreIdentity::snapshot()['structure']
            || ($manifest['ruleset_version'] ?? null) !== CoreIdentity::snapshot()['rules']
            || ($manifest['confidence_policy']['version'] ?? null) !== CoreIdentity::snapshot()['confidence']
            || ($data['manifest_hash'] ?? null) !== hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR))) {
            return 'INVALID_WALD_BASELINE';
        }
        if ($candidate === null) {
            return 'MISSING_OR_DUPLICATE_CANDIDATE';
        }
        $target = $candidate['hypothesis']['target'] ?? [];
        $refs = $target['source_refs'] ?? [];
        $cell = $observation->cell;
        if (($manifest['source_checksum'] ?? null) !== $observation->sourceChecksum
            || ($target['sheet_id'] ?? null) !== $observation->sheetId
            || ($target['column'] ?? null) !== $cell->column || count($refs) !== 1
            || ($refs[0]['source_checksum'] ?? null) !== $observation->sourceChecksum
            || ($refs[0]['sheet_id'] ?? null) !== $observation->sheetId
            || ($refs[0]['region_id'] ?? null) !== ($target['region_id'] ?? null)
            || ($refs[0]['column'] ?? null) !== $cell->column) {
            return 'OBSERVATION_PROVENANCE_MISMATCH';
        }
        try {
            if (! SourceRange::parse($refs[0]['range'] ?? '')->contains($cell->row, $cell->column)) {
                return 'OBSERVATION_OUTSIDE_TARGET';
            }
            $headerRefs = $refs[0]['header_refs'] ?? [];
            if ($headerRefs === []) {
                return 'MISSING_HEADER_EVIDENCE';
            }
            foreach ($headerRefs as $headerRef) {
                $headerRange = SourceRange::parse($headerRef['merge_range'] ?? $headerRef['cell'] ?? '');
                if ($cell->row <= $headerRange->endRow) {
                    return 'OBSERVATION_NOT_DATA_ROW';
                }
            }
        } catch (AnalysisProblem) {
            return 'INVALID_SOURCE_RANGE';
        }
        if ($cell->type === 'error') {
            return 'SOURCE_CELL_ERROR';
        }
        if ($cell->formula !== null) {
            return 'FORMULA_CACHE_REQUIRES_CONFIRMATION';
        }
        $targets = array_values(array_filter($data['targets'] ?? [],
            fn ($entry) => ($entry['target_id'] ?? null) === ($target['id'] ?? null)
                && ($entry['leading_candidate'] ?? null) === $candidate['hypothesis']['id']));
        if (count($targets) !== 1 || ($targets[0]['decision'] ?? null) !== ($candidate['decision'] ?? null)) {
            // A near tie legitimately has no leader; let its ambiguity evidence take precedence.
            foreach ($data['clarifications'] ?? [] as $clarification) {
                if (in_array($candidate['hypothesis']['id'], array_column($clarification['competing_candidates'] ?? [], 'id'), true)) {
                    return null;
                }
            }

            return 'CANDIDATE_NOT_TARGET_LEADER';
        }

        return null;
    }

    private function attach(SemanticResult $semantic, array $evidence, ?C $classification = null, ?R $resolution = null, ?string $reason = null): SemanticResult
    {
        return new SemanticResult($semantic->concept, $semantic->rawValue, $semantic->lookupValue,
            $classification ?? $semantic->classification, $resolution ?? $semantic->resolution,
            $semantic->dictionary, $semantic->match, $reason === null ? $semantic->value : null,
            $semantic->suggestions, [...$semantic->reasons, ...($reason === null ? [] : [$reason])], $evidence);
    }
}
