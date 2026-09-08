<?php

namespace App\SourceImport\Knowledge;

use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProfileProvenance
{
    /** Exactly one selected version per already scope-filtered profile, not its full history. */
    public function activeVersions(Collection $profiles): Collection
    {
        return ProfileVersion::query()->select('wald_profile_versions.*')
            ->join('wald_profiles as p', 'p.id', '=', 'wald_profile_versions.profile_id')
            ->whereIn('p.id', $profiles->pluck('id'))
            ->whereColumn('wald_profile_versions.version', 'p.active_version')
            ->get()->keyBy('profile_id');
    }

    /** Batch provenance lookup keeps the candidate loop free of database queries. */
    public function currentVersionIds(Collection $versions): array
    {
        return DB::table('wald_profile_versions as v')
            ->join('wald_clarification_answers as a', 'a.id', '=', 'v.answer_id')
            ->join('wald_clarifications as q', 'q.id', '=', 'a.clarification_id')
            ->join('wald_knowledge_contexts as c', 'c.id', '=', 'q.context_id')
            ->whereIn('v.id', $versions->pluck('id'))
            ->whereColumn('q.sequence', 'a.sequence')->where('c.state', '!=', 'SUPERSEDED')
            ->pluck('v.id')->all();
    }

    public function current(ProfileVersion $version): bool
    {
        $answer = ClarificationAnswer::query()->findOrFail($version->answer_id);
        $question = Clarification::query()->findOrFail($answer->clarification_id);
        $context = KnowledgeContext::query()->findOrFail($question->context_id);

        return $question->sequence === $answer->sequence && $context->state !== 'SUPERSEDED';
    }
}
