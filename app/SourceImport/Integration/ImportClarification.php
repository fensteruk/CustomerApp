<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Support\Str;

final class ImportClarification
{
    public function questions(User $actor, KnowledgeScope $scope, string $uuid): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'clarify');
        $run = (new BackendStore)->run($scope, $uuid);
        $context = KnowledgeContext::query()->findOrFail($run->context_id);

        return (new KnowledgeQueries)->questions($actor, $scope, $context->uuid);
    }

    public function answer(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $question, int $sequence, ?string $candidate, string $reason, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'clarify', $command, [$uuid, $epoch, $question, $sequence, $candidate, $reason], function (User $fresh) use ($scope, $uuid, $epoch, $question, $sequence, $candidate, $reason): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            $this->editable($run, $epoch);
            $context = KnowledgeContext::query()->findOrFail($run->context_id);
            $answer = (new AnswerClarification)->handle($fresh, $scope, $context->uuid, $question, $sequence, $candidate, $reason, (string) Str::uuid());
            (new BackendStore)->state($run, 'NEEDS_CLARIFICATION', ['preview_id' => null]);

            return [['run' => $uuid, 'answer' => $answer->uuid, 'state' => 'NEEDS_CLARIFICATION'], ['state' => $run->state], ['answer' => $answer->uuid, 'requires_reanalysis' => true]];
        });
    }

    public function useProfile(User $actor, KnowledgeScope $scope, string $uuid, int $epoch, string $profile, int $profileEpoch, string $command): array
    {
        return (new ImportStore)->run($actor, $scope, 'clarify', $command, [$uuid, $epoch, $profile, $profileEpoch, 'reuse'], function (User $fresh) use ($scope, $uuid, $epoch, $profile, $profileEpoch): array {
            $run = (new BackendStore)->run($scope, $uuid, true);
            $this->editable($run, $epoch);
            $context = KnowledgeContext::query()->findOrFail($run->context_id);
            $receipt = (new UseProfile)->handle($fresh, $scope, $context->uuid, $profile, $profileEpoch, (string) Str::uuid());
            (new BackendStore)->state($run, 'NEEDS_CLARIFICATION', ['preview_id' => null]);

            return [['run' => $uuid, 'profile_receipt' => $receipt->uuid, 'applied' => $receipt->applied], ['state' => $run->state], ['receipt' => $receipt->uuid, 'requires_reanalysis' => true]];
        });
    }

    private function editable(object $run, int $epoch): void
    {
        if ((int) $run->epoch !== $epoch || ! in_array($run->state, ['NEEDS_CLARIFICATION', 'REQUIRES_REVIEW', 'REVIEWED', 'READY_TO_COMMIT'], true)) {
            throw new ImportConflict('clarification_state_conflict');
        }
    }
}
