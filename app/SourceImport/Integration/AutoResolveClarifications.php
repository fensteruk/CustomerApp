<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgePolicy;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Records unambiguous, dictionary-backed structural answers as private system decisions. */
final class AutoResolveClarifications
{
    public function forContext(User $actor, KnowledgeScope $scope, int $contextId, array $sheets = []): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('auto_resolution_transaction_required');
        }
        $fresh = (new KnowledgePolicy)->authorize($actor, $scope, 'answer', true);
        $store = new KnowledgeStore;
        $context = KnowledgeContext::query()->whereKey($contextId)->firstOrFail();
        $context = $store->context($scope, $context->uuid);
        $snapshot = $store->snapshot($context);
        if (count($snapshot['tables'] ?? []) !== 1 || ($snapshot['composite_candidate_count'] ?? 0) > 1) {
            return 0;
        }
        $tableId = array_key_first($snapshot['tables']);
        $table = $snapshot['tables'][$tableId];
        if (($table['warnings'] ?? []) !== []) {
            return 0;
        }

        $resolved = 0;
        $questions = Clarification::query()->where('context_id', $context->id)->orderBy('id')->lockForUpdate()->get();
        foreach ($questions as $question) {
            if ($question->state !== 'OPEN' || $question->sequence !== 0 || $question->type !== 'STRUCTURAL') {
                continue;
            }
            $evidence = $snapshot['questions'][$question->question_key] ?? null;
            if (! is_array($evidence)) {
                continue;
            }
            $candidates = $evidence['candidates'] ?? [];
            $reason = 'Only one safe exact dictionary candidate exists in the selected table.';
            $candidate = count($candidates) === 1 ? $candidates[0] : null;
            if ($question->question_key === 'structure:plot_reference' && count($candidates) === 2) {
                $candidate = $this->matchingPlotNumber($candidates, $snapshot, $table, $tableId, $sheets);
                $reason = 'Plot number agrees exactly with the number at the end of Plot Ref on every included row.';
            }
            if ($candidate === null) {
                continue;
            }
            if (! $this->valid($question->question_key, $candidate, $snapshot, $tableId, $table)) {
                continue;
            }
            if (! hash_equals($question->question_hash, Canonical::hash($evidence))) {
                throw new \LogicException('question_integrity_error');
            }
            $proof = $store->evidence($fresh, $context, [
                'resolution' => 'AUTO_RESOLVED', 'question' => $question->question_key,
                'candidates' => $evidence['candidates'], 'selected' => $candidate,
                'reason' => $reason, 'question_hash' => $question->question_hash,
                'dictionary' => (new KnowledgeIdentity)->current(), 'context' => $context->uuid,
                'resolved_at' => now('UTC')->toISOString(), 'initiated_by' => $fresh->id,
            ], 'answer', 'wald_automatic');
            $answer = $store->insert(ClarificationAnswer::class, $fresh, [
                'clarification_id' => $question->id, 'sequence' => 1, 'decision' => 'AUTO_SELECTED',
                'candidate_id' => $candidate['id'], 'reusable_intent' => false, 'evidence_id' => $proof->id,
            ], 'wald_automatic');
            $question->forceFill(['sequence' => 1, 'state' => 'ANSWERED'])->save();
            $store->insert(KnowledgeEvent::class, $fresh, [
                ...$scope->columns(), 'action' => 'auto_resolve', 'command_uuid' => (string) Str::uuid(),
                'command_hash' => Canonical::hash([$context->uuid, $question->question_key, $candidate['id']]),
                'policy_version' => KnowledgeIdentity::POLICY,
                'before_state' => ['sequence' => 0, 'state' => 'OPEN'],
                'after_state' => ['sequence' => 1, 'state' => 'ANSWERED', 'decision' => 'AUTO_SELECTED'],
                'result_uuid' => $answer->uuid, 'reason_evidence_id' => $proof->id,
            ], 'wald_automatic');
            $resolved++;
        }

        return $resolved;
    }

    private function valid(string $key, array $candidate, array $snapshot, string $tableId, array $table): bool
    {
        $role = $candidate['role'] ?? null;
        $column = $candidate['column'] ?? null;
        $header = $candidate['header'] ?? null;
        if (! is_string($role) || $role === 'ignored' || $key !== 'structure:'.$role
            || ($candidate['table'] ?? null) !== $tableId || ! is_int($column) || ! is_string($header)) {
            return false;
        }
        foreach ($snapshot['questions'] as $otherKey => $other) {
            if ($otherKey === $key || ($other['type'] ?? null) !== 'STRUCTURAL') {
                continue;
            }
            foreach ($other['candidates'] ?? [] as $otherCandidate) {
                if (($otherCandidate['table'] ?? null) === $tableId && ($otherCandidate['column'] ?? null) === $column) {
                    return false;
                }
            }
        }
        $safety = $table['column_safety'][$column] ?? [];
        if (in_array(true, $safety, true)) {
            return false;
        }
        $dictionary = new CustomerAppDictionary;
        if (str_starts_with($role, 'quantity:')) {
            $product = $dictionary->product($header, '0');

            return $product->isResolved() && $role === 'quantity:'.$product->lookupValue;
        }
        if ($role === 'plot_reference'
            && in_array(strtolower(trim($header)), ['plot', 'plot ref', 'plot no.', 'plot number', 'house no.', 'sales plot'], true)) {
            return true;
        }

        return $dictionary->field($header)->value === $role;
    }

    private function matchingPlotNumber(array $candidates, array $snapshot, array $table, string $tableId, array $sheets): ?array
    {
        $number = collect($candidates)->first(fn (array $candidate): bool => strcasecmp(trim((string) ($candidate['header'] ?? '')), 'Plot number') === 0);
        $reference = collect($candidates)->first(fn (array $candidate): bool => strcasecmp(trim((string) ($candidate['header'] ?? '')), 'Plot Ref') === 0);
        if (! $number || ! $reference || ($number['table'] ?? null) !== $tableId || ($reference['table'] ?? null) !== $tableId
            || $number['column'] === $reference['column']) {
            return null;
        }
        $sheet = collect($sheets)->firstWhere('id', $table['sheet_id']);
        if ($sheet === null) {
            return null;
        }
        $typeCandidates = $snapshot['questions']['structure:call_type']['candidates'] ?? [];
        $callType = count($typeCandidates) === 1 && ($typeCandidates[0]['table'] ?? null) === $tableId
            ? (int) $typeCandidates[0]['column'] : null;
        $start = (int) ($table['data_start_row'] ?? $table['header_range']['end_row'] + 1);
        $end = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        $matched = 0;
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $start || $rowNumber > $end || ! array_filter($cells, fn ($cell): bool => $cell->rawValue !== null && $cell->rawValue !== '')) {
                continue;
            }
            if ($callType !== null && CustomerAppDictionary::excludesCallType((string) ($cells[$callType]->rawValue ?? ''))) {
                continue;
            }
            $rawNumber = $cells[$number['column']]->rawValue ?? null;
            $rawReference = $cells[$reference['column']]->rawValue ?? null;
            if ((! is_int($rawNumber) && ! is_string($rawNumber)) || ! is_string($rawReference)
                || ! preg_match('/^[0-9]{1,100}$/D', trim((string) $rawNumber))
                || ! preg_match('/(?:^|[\s\x{2013}\x{2014}-])Plot\s+([0-9]{1,100})\s*$/iu', $rawReference, $parts)
                || ! hash_equals(trim((string) $rawNumber), $parts[1])) {
                return null;
            }
            $matched++;
        }

        return $matched > 0 ? $number : null;
    }
}
