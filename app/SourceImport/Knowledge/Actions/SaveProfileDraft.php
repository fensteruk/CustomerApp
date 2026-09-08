<?php

namespace App\SourceImport\Knowledge\Actions;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeConflict;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileVersion;

final class SaveProfileDraft
{
    public function __construct(private KnowledgeStore $store = new KnowledgeStore) {}

    public function handle(User $actor, KnowledgeScope $scope, string $contextUuid, string $answerUuid, string $command,
        ?string $profileUuid = null, int $expectedEpoch = 0): ProfileVersion
    {
        return $this->store->transact($actor, $scope, 'draft', $command,
            [$contextUuid, $answerUuid, $profileUuid, $expectedEpoch], ProfileVersion::class,
            function (User $fresh) use ($scope, $contextUuid, $answerUuid, $profileUuid, $expectedEpoch): array {
                $profile = $profileUuid ? $this->store->scoped(KnowledgeProfile::class, $scope)->where('uuid', $profileUuid)->lockForUpdate()->firstOrFail() : null;
                if ($profile && $profile->lock_version !== $expectedEpoch) {
                    throw new KnowledgeConflict('stale_profile_epoch');
                }
                $context = $this->store->context($scope, $contextUuid);
                $answer = ClarificationAnswer::query()->where('uuid', $answerUuid)
                    ->whereIn('clarification_id', Clarification::query()->select('id')->where('context_id', $context->id))->firstOrFail();
                $question = Clarification::query()->whereKey($answer->clarification_id)->lockForUpdate()->firstOrFail();
                if ($question->type !== 'STRUCTURAL' || $question->sequence !== $answer->sequence || $answer->decision !== 'SELECTED') {
                    throw new KnowledgeConflict('structural_answer_required');
                }
                $snapshot = $this->store->snapshot($context);
                $candidates = $snapshot['questions'][$question->question_key]['candidates'];
                $selection = collect($candidates)->firstWhere('id', $answer->candidate_id);
                if ($selection === null) {
                    throw new KnowledgeConflict('candidate_missing');
                }
                $definition = ['pins' => $snapshot['pins'], 'descriptor' => $snapshot['tables'][$selection['table']]['descriptor'],
                    'selection' => ['role' => $selection['role'], 'selector' => $selection['selector']]];
                $profile ??= $this->store->insert(KnowledgeProfile::class, $fresh, $scope->columns());
                $previous = (int) ProfileVersion::query()->where('profile_id', $profile->id)->max('version');
                $version = $this->store->insert(ProfileVersion::class, $fresh, ['profile_id' => $profile->id,
                    'version' => $previous + 1, 'predecessor_version' => $previous ?: null,
                    'definition' => $definition, 'definition_hash' => Canonical::hash($definition), 'answer_id' => $answer->id]);
                $profile->forceFill(['lock_version' => $profile->lock_version + 1])->save();

                return [$version, ['epoch' => $expectedEpoch], ['profile' => $profile->uuid, 'version' => $version->version,
                    'definition_hash' => $version->definition_hash, 'epoch' => $profile->lock_version], null];
            });
    }
}
