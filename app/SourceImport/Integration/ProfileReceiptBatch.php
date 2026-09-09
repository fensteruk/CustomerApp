<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\Compatibility;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\ProfileMatcher;
use App\SourceImport\Knowledge\ProfileProvenance;
use App\SourceImport\Knowledge\ProfileState;
use Illuminate\Support\Collection;

/** Operation-local evaluation of dependencies already selected under Office/owner locks. */
final class ProfileReceiptBatch
{
    public function assertEligible(KnowledgeContext $context, array $snapshot, Collection $profiles, Collection $questions, Collection $uses): void
    {
        if ($uses->isEmpty()) {
            return;
        }
        if ($profiles->count() > 50 || $uses->count() > 50) {
            throw new ImportConflict('knowledge_receipt_budget_exceeded');
        }
        $provenance = new ProfileProvenance;
        $versions = $provenance->activeVersions($profiles);
        $current = $provenance->currentVersionIds($versions);
        $eligible = [];
        $matches = [];
        foreach ($profiles as $profile) {
            $version = $versions->get($profile->id);
            if ($profile->state !== ProfileState::Active || now('UTC')->greaterThanOrEqualTo($profile->review_due_at)) {
                continue;
            }
            if (! $version) {
                throw new ImportConflict('stale_profile_receipt');
            }
            if (Canonical::hash($version->definition) !== $version->definition_hash) {
                throw new ImportConflict('profile_integrity_error');
            }
            $result = (new ProfileMatcher)->evaluate($version->definition, $snapshot);
            if (in_array($version->id, $current, true) && $result['compatibility'] === Compatibility::Exact->value) {
                $role = $version->definition['selection']['role'];
                $matches[$role] = ($matches[$role] ?? 0) + 1;
                $eligible[$profile->id] = [$profile, $version, $result['selection'], $role];
            }
        }
        $answered = $questions->filter(fn ($q) => $q->sequence > 0)->pluck('question_key')->all();
        foreach ($uses as $use) {
            [$profile, $version, $selection, $role] = $eligible[$use->profile_id] ?? [null, null, null, null];
            if (! $profile || ($matches[$role] ?? 0) !== 1 || in_array('structure:'.$role, $answered, true)
                || ! $use->applied || $use->context_id !== $context->id || $profile->lock_version !== $use->epoch
                || $profile->active_version !== $use->version || $version->version !== $use->version || $context->state !== 'OPEN' || now('UTC')->greaterThanOrEqualTo($context->expires_at)
                || $context->snapshot_hash !== $use->evidence_hash || (new KnowledgeIdentity)->compatible($use->pins) !== Compatibility::Exact
                || Canonical::hash($selection) !== Canonical::hash($use->selection)) {
                throw new ImportConflict('stale_profile_receipt');
            }
        }
    }
}
