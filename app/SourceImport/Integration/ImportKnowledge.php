<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileUse;

final class ImportKnowledge
{
    /** Called with the Office/owner locks held. Pins current knowledge, not client receipts. */
    public function capture(User $actor, KnowledgeScope $scope, int $contextId): array
    {
        $store = new KnowledgeStore;
        $profiles = $store->scoped(KnowledgeProfile::class, $scope)->orderBy('id')->limit(51)->lockForUpdate()->get();
        if ($profiles->count() > 50) {
            throw new ImportConflict('knowledge_profile_budget_exceeded');
        }
        $context = $store->scoped(KnowledgeContext::class, $scope)->whereKey($contextId)->firstOrFail();
        $context = $store->context($scope, $context->uuid);
        $snapshot = $store->snapshot($context);
        $questions = Clarification::query()->where('context_id', $context->id)->orderBy('id')->lockForUpdate()->get();
        $answers = ClarificationAnswer::query()->select('wald_clarification_answers.*')
            ->join('wald_clarifications as q', 'q.id', '=', 'wald_clarification_answers.clarification_id')
            ->whereIn('q.id', $questions->pluck('id'))->whereColumn('wald_clarification_answers.sequence', 'q.sequence')->get()->groupBy('clarification_id');
        $selections = [];
        $answerPins = [];
        foreach ($questions as $q) {
            $answerPins[$q->question_key] = (int) $q->sequence;
            if ($q->sequence > 0) {
                $answer = $answers->get($q->id)?->firstWhere('sequence', $q->sequence);
                $selection = collect($snapshot['questions'][$q->question_key]['candidates'])->firstWhere('id', $answer?->candidate_id);
                $selections[$q->question_key] = $selection === null ? null : [...$selection, 'answer_uuid' => $answer->uuid];
            }
        }
        $uses = ProfileUse::query()->where('context_id', $context->id)->where('applied', true)->orderBy('id')->limit(51)->get();
        if ($uses->count() > 50) {
            throw new ImportConflict('knowledge_receipt_budget_exceeded');
        }
        $usePins = [];
        foreach ($uses as $use) {
            if (! (new KnowledgeQueries)->receiptEligible($actor, $scope, $use->uuid)) {
                throw new ImportConflict('stale_profile_receipt');
            }
            $role = 'structure:'.$use->selection['role'];
            if (! array_key_exists($role, $selections)) {
                $selections[$role] = [...$use->selection, 'profile_receipt' => $use->uuid];
            }
            $usePins[] = $use->uuid;
        }

        return ['context' => $context->uuid, 'snapshot_hash' => $context->snapshot_hash, 'analysis_hash' => $context->analysis_hash,
            'context_epoch' => $context->lock_version, 'answers' => $answerPins, 'uses' => $usePins,
            'profiles' => $profiles->map(fn ($p) => [$p->uuid, $p->lock_version, $p->active_version, $p->state->value, $p->review_due_at?->toISOString()])->all(),
            'selections' => $selections];
    }

    public function assertCurrent(User $actor, KnowledgeScope $scope, int $contextId, array $pins): void
    {
        if (Canonical::hash($this->capture($actor, $scope, $contextId)) !== Canonical::hash($pins)) {
            throw new ImportConflict('stale_knowledge_dependencies');
        }
    }
}
