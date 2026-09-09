<?php

use App\Models\PortalRole;
use App\SourceImport\Integration\CommitAttemptJournal;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportKnowledge;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\ProfileReceiptBatch;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RevokeProfile;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeStore;
use App\SourceImport\Knowledge\Models\Clarification;
use App\SourceImport\Knowledge\Models\ClarificationAnswer;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Knowledge\Models\ProfileUse;
use App\SourceImport\Knowledge\Models\ProfileVersion;
use App\SourceImport\Knowledge\ProfileState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\Wald05QaProfiles;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));
afterEach(fn () => B::$callBase = 1001);

it('W5Q03 durably records refusal with no partial effects for each stale dependency', function (string $change) {
    [$actor, $scope] = F::owner();
    $binding = B::binding($actor, $scope);
    if ($change === 'profile') {
        [$preview, $profile] = Wald05QaProfiles::reviewed($actor, $scope);
        (new RevokeProfile)->handle($actor, $scope, $profile->uuid, $profile->lock_version, 'QA revocation.', F::command());
    } else {
        if ($change === 'projection') {
            B::commit($actor, $scope, B::reviewed($actor, $scope, date: '2026-09-08'));
        }
        $preview = B::reviewed($actor, $scope);
    }
    match ($change) {
        'expiry' => $this->travel(25)->hours(),
        'binding' => (new SourceBindingService)->revoke($actor, $scope, $binding['binding'], $binding['epoch'], 'QA revocation.', F::command()),
        'projection' => DB::table('projected_plot_services')->increment('wald_epoch'),
        'actor' => DB::table('users')->where('id', $actor->id)->update(['is_active' => false]),
        'role' => DB::table('users')->where('id', $actor->id)->update(['portal_role_id' => PortalRole::where('identifier', 'site_manager')->value('id')]),
        default => null,
    };
    // Construct an immutable historical-build review, without disabling any DB guard.
    if (in_array($change, ['dictionary', 'wald'], true)) {
        $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail();
        $stage = (array) DB::table('wald_import_stages')->where('id', $run->stage_id)->firstOrFail();
        $manifest = json_decode($stage['manifest'], true);
        if ($change === 'dictionary') {
            $manifest['pins']['fingerprint'] = 'historical';
        } else {
            $manifest['pins']['core']['reader'] = 'historical';
        }
        unset($stage['id']);
        $stage['uuid'] = F::command();
        $stage['generation']++;
        $stage['manifest'] = Canonical::json($manifest);
        $stage['manifest_hash'] = Canonical::hash($manifest);
        $stageId = DB::table('wald_import_stages')->insertGetId($stage);
        foreach (DB::table('wald_staged_rows')->where('stage_id', $run->stage_id)->get() as $row) {
            $data = (array) $row;
            unset($data['id']);
            $data['stage_id'] = $stageId;
            DB::table('wald_staged_rows')->insert($data);
        }
        $old = (array) DB::table('wald_import_previews')->where('id', $run->preview_id)->firstOrFail();
        $payload = json_decode($old['payload'], true);
        $payload['manifest'] = $manifest;
        $payload['stage'] = $stage['uuid'];
        $payload['stage_hash'] = $stage['manifest_hash'];
        unset($old['id']);
        $old['uuid'] = F::command();
        $old['stage_id'] = $stageId;
        $old['payload'] = Canonical::json($payload);
        $old['payload_hash'] = Canonical::hash($payload);
        $previewId = DB::table('wald_import_previews')->insertGetId($old);
        DB::table('wald_import_runs')->where('id', $run->id)->update(['stage_id' => $stageId, 'preview_id' => $previewId]);
        $preview['preview'] = $old['uuid'];
        $preview['hash'] = $old['payload_hash'];
    }
    $snapshot = fn () => collect(['projected_plots', 'projected_plot_services', 'projected_plot_products', 'wald_visit_observations', 'wald_import_receipts', 'wald_import_commands'])
        ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    $before = $snapshot();
    $command = F::command();
    $invoke = fn () => (new ImportReview)->commit($actor, $scope, $preview['run'], $preview['preview'], $preview['hash'], $command);
    expect($invoke)->toThrow(in_array($change, ['actor', 'role'], true) ? AuthorizationException::class : ImportConflict::class);
    expect($snapshot())->toBe($before);
    $attempt = DB::table('wald_commit_attempts')->where('command_uuid', $command)->firstOrFail();
    $outcome = DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->firstOrFail();
    expect($outcome->outcome)->toBe(in_array($change, ['actor', 'role'], true) ? 'REFUSED' : 'STALE');
    expect($invoke)->toThrow(in_array($change, ['actor', 'role'], true) ? AuthorizationException::class : ImportConflict::class);
    expect(DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->count())->toBe(1)->and($snapshot())->toBe($before);
})->with(['expiry', 'binding', 'profile', 'projection', 'actor', 'role', 'dictionary', 'wald']);

it('W5Q03 preserves failed attempts outside rolled-back business and required success audit', function ($needle) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope, [0 => ['complete' => 'Yes']]);
    $enabled = true;
    DB::listen(function ($query) use (&$enabled, $needle) {
        if ($enabled && str_contains(str_replace('`', '"', strtolower($query->sql)), 'insert into "'.$needle.'"')) {
            $enabled = false;
            throw new RuntimeException('Secret workbook value / credential MUST NOT be audited');
        }
    });
    try {
        expect(fn () => B::commit($actor, $scope, $preview))->toThrow(RuntimeException::class);
    } finally {
        $enabled = false;
    }
    foreach (['projected_plots', 'projected_plot_products', 'wald_source_visits', 'wald_visit_observations', 'wald_import_receipts', 'source_projection_events'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    expect(DB::table('wald_commit_attempt_outcomes')->value('outcome'))->toBe('FAILED');
    $audit = (new CommitAttemptJournal)->audit($actor, $scope, $preview['run']);
    expect(json_encode($audit))->not->toContain('Secret workbook', 'credential', 'synthetic.xlsx', 'storage_key');
    expect((new ImportIntake)->status($actor, $scope, $preview['run'])['state'])->toBe('READY_TO_COMMIT');
})->with(['wald_visit_observations', 'projected_plot_products', 'wald_import_receipts', 'wald_import_commands', 'wald_commit_attempt_outcomes']);

it('W5Q03 bounds exact command replay and protects audit identity and tenant privacy', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $p = B::reviewed($actor, $scope);
    $command = F::command();
    $invoke = fn () => (new ImportReview)->commit($actor, $scope, $p['run'], $p['preview'], $p['hash'], $command);
    $result = $invoke();
    expect($invoke())->toBe($result)->and($invoke())->toBe($result);
    $attempt = DB::table('wald_commit_attempts')->firstOrFail();
    expect(DB::table('wald_commit_attempts')->count())->toBe(1)->and(DB::table('wald_commit_attempt_outcomes')->count())->toBe(1);
    expect(fn () => (new ImportReview)->commit($actor, $scope, $p['run'], $p['preview'], str_repeat('0', 64), $command))->toThrow(ImportConflict::class, 'import_command_conflict');
    foreach (['wald_commit_attempts' => 'metadata_hash', 'wald_commit_attempt_outcomes' => 'category'] as $table => $column) {
        expect(fn () => DB::table($table)->update([$column => 'changed']))->toThrow(QueryException::class);
        expect(fn () => DB::table($table)->delete())->toThrow(QueryException::class);
        $model = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };
        $model->setTable($table);
        $row = $model->newQuery()->firstOrFail();
        expect(fn () => $row->update([$column => 'ORM changed']))->toThrow(QueryException::class);
    }
    [, $foreign] = F::owner();
    expect(fn () => (new CommitAttemptJournal)->audit($actor, $foreign, $p['run']))->toThrow(RecordNotFoundException::class);
    [$external] = F::owner();
    DB::table('users')->where('id', $external->id)->update(['portal_role_id' => PortalRole::where('identifier', 'site_manager')->value('id')]);
    expect(fn () => (new ImportReview)->commit($external, $scope, $p['run'], $p['preview'], $p['hash'], F::command()))->toThrow(AuthorizationException::class);
    expect(fn () => (new CommitAttemptJournal)->audit($external, $scope, $p['run']))->toThrow(AuthorizationException::class);
    expect(DB::table('wald_commit_attempts')->count())->toBe(1);
    $metadata = json_decode($attempt->metadata, true);
    expect(Canonical::hash($metadata))->toBe($attempt->metadata_hash)->and($metadata['export_slot'])->toBe('MORNING')
        ->and($metadata['bindings'])->not->toBeEmpty()->and($metadata['pins'])->not->toBeEmpty();
});

it('W5Q03 retains underlying validation and ordering blockers on refused direct commits', function (string $case) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    if ($case !== 'duplicate') {
        B::commit($actor, $scope, B::reviewed($actor, $scope, date: $case === 'older' ? '2026-09-10' : '2026-09-09'));
    }
    $rows = match ($case) {
        'duplicate' => [1 => ['Call No.' => '1001']], 'identity' => [0 => ['Plot' => '999']], default => [0 => ['VS' => '3.000']]
    };
    $run = B::staged($actor, $scope, $rows, slot: $case === 'identity' ? 'AFTERNOON' : 'MORNING');
    $preview = (new ImportReview)->preview($actor, $scope, $run['run'], $run['epoch'], F::command());
    expect($preview['blockers'])->not->toBeEmpty();
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class);
    $attempt = DB::table('wald_commit_attempts')->orderByDesc('id')->firstOrFail();
    expect(json_decode($attempt->metadata, true)['review_blockers'])->toBe($preview['blockers']);
    expect(DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->value('outcome'))->toBe('CONFLICTED');
})->with(['older', 'same-slot', 'identity', 'duplicate']);

it('W5Q04 rejects one invalid use in an otherwise valid batch without global hydration', function (string $change) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    [$preview, $profile] = Wald05QaProfiles::reviewed($actor, $scope, 2);
    $contextId = DB::table('wald_import_runs')->where('uuid', $preview['run'])->value('context_id');
    $store = new KnowledgeStore;
    $context = $store->scoped(KnowledgeContext::class, $scope)->findOrFail($contextId);
    $snapshot = $store->snapshot($context);
    $profiles = $store->scoped(KnowledgeProfile::class, $scope)->get();
    $questions = Clarification::where('context_id', $contextId)->get();
    $uses = ProfileUse::where('context_id', $contextId)->where('applied', true)->get();
    $bad = $uses->last();
    match ($change) {
        'foreign', 'missing' => $bad->profile_id = $profile->id + 1000000,
        'version' => $bad->version++,
        'epoch' => $bad->epoch++,
        'evidence' => $bad->evidence_hash = str_repeat('0', 64),
        'pins' => $bad->pins = [],
        'context' => $bad->context_id++,
        'selection' => $bad->selection = [...$bad->selection, 'column' => 999],
        'revoked' => $profiles->first()->state = ProfileState::Revoked,
        'expired' => $profiles->first()->review_due_at = now('UTC')->subSecond(),
        'superseded' => $profiles->first()->active_version++,
        'answered' => $questions->firstWhere('question_key', 'structure:quantity:VS')->sequence++,
        'fresh' => $snapshot['fresh']['complete'] = false,
    };
    expect(fn () => (new ProfileReceiptBatch)->assertEligible($context, $snapshot, $profiles, $questions, $uses))->toThrow(ImportConflict::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(0);
})->with(['foreign', 'missing', 'version', 'epoch', 'evidence', 'pins', 'context', 'selection', 'revoked', 'expired', 'superseded', 'answered', 'fresh']);

it('W5Q04 scopes profile SQL and fails safely above receipt budget without truncation', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    [$preview] = Wald05QaProfiles::reviewed($actor, $scope);
    $contextId = DB::table('wald_import_runs')->where('uuid', $preview['run'])->value('context_id');
    $queries = [];
    $enabled = true;
    DB::listen(function ($query) use (&$queries, &$enabled) {
        if ($enabled) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });
    try {
        (new ImportKnowledge)->capture($actor, $scope, $contextId);
    } finally {
        $enabled = false;
    }
    $root = collect($queries)->first(fn ($q) => str_contains(str_replace('`', '"', $q[0]), 'from "wald_profiles"'));
    foreach (array_keys($scope->columns()) as $column) {
        expect($root[0])->toContain($column);
    }
    foreach ($scope->columns() as $value) {
        expect($root[1])->toContain($value);
    }
    $context = KnowledgeContext::findOrFail($contextId);
    $use = ProfileUse::where('context_id', $contextId)->where('applied', true)->firstOrFail();
    expect(fn () => (new ProfileReceiptBatch)->assertEligible($context, [], collect(), collect(), collect(array_fill(0, 51, $use))))
        ->toThrow(ImportConflict::class, 'knowledge_receipt_budget_exceeded');
});

it('W5Q04 rechecks competing profiles and superseded answer provenance after review', function (string $case) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    [$preview, $profile] = Wald05QaProfiles::reviewed($actor, $scope, 2);
    $version = ProfileVersion::where('profile_id', $profile->id)->firstOrFail();
    $answer = ClarificationAnswer::findOrFail($version->answer_id);
    $question = Clarification::findOrFail($answer->clarification_id);
    $context = KnowledgeContext::findOrFail($question->context_id);
    if ($case === 'competitor') {
        $other = (new SaveProfileDraft)->handle($actor, $scope, $context->uuid, $answer->uuid, F::command());
        $root = KnowledgeProfile::findOrFail($other->profile_id);
        (new ActivateProfile)->handle($actor, $scope, $root->uuid, $other->version, $other->definition_hash, $root->lock_version, 'QA competing profile.', F::command());
    } else {
        (new AnswerClarification)->handle($actor, $scope, $context->uuid, $question->uuid, $question->sequence, $answer->candidate_id, 'QA successor answer.', F::command());
    }
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'stale_profile_receipt');
    expect(DB::table('wald_import_receipts')->count())->toBe(0)
        ->and(DB::table('wald_commit_attempt_outcomes')->value('outcome'))->toBe('STALE');
})->with(['competitor', 'provenance']);

it('W5Q03 retains unfinished intent when audit storage fails and recovers the exact command', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $p = B::reviewed($actor, $scope);
    $command = F::command();
    $enabled = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$enabled) {
        if ($enabled && str_contains($sql, 'insert into') && str_contains($sql, 'wald_commit_attempt_outcomes')) {
            throw new RuntimeException('synthetic_audit_store_unavailable');
        }
    });
    $invoke = fn () => (new ImportReview)->commit($actor, $scope, $p['run'], $p['preview'], $p['hash'], $command);
    try {
        expect($invoke)->toThrow(RuntimeException::class, 'synthetic_audit_store_unavailable');
    } finally {
        $enabled = false;
    }
    expect(DB::table('wald_commit_attempts')->count())->toBe(1)->and(DB::table('wald_commit_attempt_outcomes')->count())->toBe(0)
        ->and(DB::table('wald_import_receipts')->count())->toBe(0)->and(DB::table('projected_plots')->count())->toBe(0);
    $receipt = $invoke();
    expect($invoke())->toBe($receipt)->and(DB::table('wald_commit_attempts')->count())->toBe(1)
        ->and(DB::table('wald_commit_attempt_outcomes')->count())->toBe(1)->and(DB::table('wald_commit_attempt_outcomes')->value('outcome'))->toBe('SUCCEEDED');
});
