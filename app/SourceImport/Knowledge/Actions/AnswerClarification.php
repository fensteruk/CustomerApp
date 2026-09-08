<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

final class AnswerClarification
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $contextUuid, string $questionUuid, int $expectedSequence,
        ?string $candidateId, string $reason, string $command, bool $reusableIntent = false): ClarificationAnswer
    {
        $this->store->reason($reason);

        return $this->store->transact($actor, $scope, 'answer', $command,
            [$contextUuid, $questionUuid, $expectedSequence, $candidateId, $reason, $reusableIntent], ClarificationAnswer::class,
            function (User $fresh) use ($scope, $contextUuid, $questionUuid, $expectedSequence, $candidateId, $reason, $reusableIntent): array {
                $context = $this->store->context($scope, $contextUuid);
                $question = Clarification::query()->where('context_id', $context->id)->where('uuid', $questionUuid)->lockForUpdate()->firstOrFail();
                if ($question->sequence !== $expectedSequence) {
                    throw new KnowledgeConflict('stale_question_sequence');
                }
                $snapshot = $this->store->snapshot($context)['questions'][$question->question_key];
                if (Canonical::hash($snapshot) !== $question->question_hash) {
                    throw new KnowledgeConflict('question_integrity_error');
                }
                $selection = null;
                foreach ($snapshot['candidates'] as $candidate) {
                    if ($candidate['id'] === $candidateId) {
                        $selection = $candidate;
                    }
                }
                if (($candidateId !== null && $selection === null) || ($reusableIntent && $question->type !== 'STRUCTURAL')) {
                    throw new KnowledgeConflict('answer_not_permitted');
                }
                if ($question->type === 'SEMANTIC' && $selection !== null) {
                    $structure = Clarification::query()->where('context_id', $context->id)->where('question_key', 'structure:call_type')->firstOrFail();
                    $structuralAnswer = ClarificationAnswer::query()->where('clarification_id', $structure->id)->where('sequence', $structure->sequence)->first();
                    $analysis = $this->store->snapshot($context);
                    $selected = collect($analysis['questions']['structure:call_type']['candidates'])
                        ->firstWhere('id', $structuralAnswer?->candidate_id);
                    if ($selected === null || $selected['column'] !== $snapshot['requires_structural_column']
                        || $analysis['tables'][$selected['table']]['sheet_id'] !== $snapshot['requires_structural_sheet']) {
                        throw new KnowledgeConflict('structural_answer_required_before_semantic_correction');
                    }
                    $selection['structural_answer_uuid'] = $structuralAnswer->uuid;
                    $selection['canonical_interpretation'] = (new CustomerAppDictionary)->callType($selection['canonical'])->jsonSerialize();
                }
                $predecessor = ClarificationAnswer::query()->where('clarification_id', $question->id)->where('sequence', $expectedSequence)->first();
                $evidence = $this->store->evidence($fresh, $context, ['reason' => $reason, 'selection' => $selection,
                    'original' => $snapshot['original'] ?? null, 'question_hash' => $question->question_hash], 'answer');
                $answer = $this->store->insert(ClarificationAnswer::class, $fresh, ['clarification_id' => $question->id,
                    'sequence' => $expectedSequence + 1, 'decision' => $selection === null ? 'UNRESOLVED' : 'SELECTED',
                    'candidate_id' => $candidateId, 'reusable_intent' => $reusableIntent,
                    'predecessor_id' => $predecessor?->id, 'evidence_id' => $evidence->id]);
                $question->forceFill(['sequence' => $answer->sequence, 'state' => $selection === null ? 'UNRESOLVED' : 'ANSWERED'])->save();

                return [$answer, ['sequence' => $expectedSequence], ['sequence' => $answer->sequence, 'decision' => $answer->decision], $evidence];
            });
    }
}
