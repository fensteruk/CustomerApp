<?php

use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportRetention;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('requires correction for changed same-slot content and preserves immutable predecessor receipts', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $first = B::reviewed($actor, $scope);
    $receipt = B::commit($actor, $scope, $first);
    $replay = B::reviewed($actor, $scope);
    expect(B::commit($actor, $scope, $replay))->toBe($receipt);
    expect(fn () => B::reviewed($actor, $scope, [0 => ['VS' => '3.000']]))->toThrow(ImportConflict::class, 'explicit_correction_required');
    $corrected = B::reviewed($actor, $scope, [0 => ['VS' => '3.000']], predecessor: $first['run']);
    $next = B::commit($actor, $scope, $corrected);
    expect($next['revision'])->toBe(2)->and($next['predecessor_receipt'])->toBe($receipt['receipt'])
        ->and(DB::table('wald_import_receipts')->count())->toBe(2)->and(B::commit($actor, $scope, $first))->toBe($receipt);
});

it('refuses earlier declared exports irrespective of upload time', function (string $date, string $slot) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope, date: '2026-09-10', slot: 'MORNING'));
    expect(fn () => B::reviewed($actor, $scope, date: $date, slot: $slot))->toThrow(ImportConflict::class, 'older_export_refused');
})->with([['2026-09-09', 'AFTERNOON'], ['2026-09-09', 'MORNING']]);

it('rejects stale preview dependencies without silently refreshing', function (string $change) {
    [$actor, $scope] = F::owner();
    $binding = B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    if ($change === 'binding') {
        (new SourceBindingService)->revoke($actor, $scope, $binding['binding'], $binding['epoch'], 'Revoked.', F::command());
    }
    if ($change === 'expiry') {
        $this->travel(25)->hours();
    }
    if ($change === 'role') {
        DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
    }
    if ($change === 'stream') {
        DB::table('wald_import_streams')->increment('epoch');
    }
    if ($change === 'answer') {
        $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->first();
        $context = KnowledgeContext::query()->findOrFail($run->context_id);
        $q = collect((new KnowledgeQueries)->questions($actor, $scope, $context->uuid))->firstWhere('key', 'structure:plot_reference');
        (new AnswerClarification)->handle($actor, $scope, $context->uuid, $q['uuid'], $q['sequence'], $q['evidence']['candidates'][0]['id'], 'Superseding decision.', F::command());
    }
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow($change === 'role' ? AuthorizationException::class : ImportConflict::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(0)->and(ProjectedPlot::query()->count())->toBe(0);
})->with(['binding', 'expiry', 'role', 'stream', 'answer']);

it('stales on a projection epoch even when source values were restored', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $preview = B::reviewed($actor, $scope, slot: 'AFTERNOON');
    DB::table('projected_plot_products')->where('product_code', 'BF')->update(['quantity' => '2.000']);
    DB::table('projected_plot_products')->where('product_code', 'BF')->update(['quantity' => '1.000']);
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'stale_projection');
});

it('rolls back all projection observation receipt and audit effects after injected failures', function (string $needle) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope, [0 => ['complete' => 'Yes']]);
    $beforeCommands = DB::table('wald_import_commands')->count();
    $enabled = true;
    $hits = 0;
    DB::connection()->beforeExecuting(function ($sql) use (&$enabled, &$hits, $needle) {
        if ($enabled && str_contains(str_replace('`', '"', strtolower($sql)), $needle)) {
            $hits++;
            throw new RuntimeException('synthetic_commit_failure');
        }
    });
    try {
        expect(fn () => B::commit($actor, $scope, $preview))->toThrow(RuntimeException::class, 'synthetic_commit_failure');
    } finally {
        $enabled = false;
    }
    expect($hits)->toBe(1)->and(ProjectedPlot::query()->count())->toBe(0)->and(ProjectedPlotProduct::query()->count())->toBe(0)
        ->and(DB::table('wald_source_visits')->count())->toBe(0)->and(DB::table('wald_visit_observations')->count())->toBe(0)
        ->and(DB::table('wald_import_receipts')->count())->toBe(0)->and(DB::table('source_projection_events')->count())->toBe(0)
        ->and(DB::table('wald_import_commands')->count())->toBe($beforeCommands)
        ->and((new ImportIntake)->status($actor, $scope, $preview['run'])['state'])->toBe('READY_TO_COMMIT');
    B::commit($actor, $scope, $preview);
    expect(DB::table('wald_import_receipts')->count())->toBe(1);
})->with(['insert into "wald_visit_observations"', 'update "wald_source_visits"', 'insert into "projected_plot_products"', 'update "source_import_runs"', 'insert into "source_projection_events"', 'insert into "wald_import_commands"', 'update "wald_import_streams"']);

it('rolls back even when an exception is raised after receipt or required audit insertion', function (string $table) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    $before = DB::table('wald_import_commands')->count();
    $enabled = true;
    DB::listen(function ($query) use (&$enabled, $table) {
        if ($enabled && str_contains(str_replace('`', '"', strtolower($query->sql)), 'insert into "'.$table.'"')) {
            throw new RuntimeException('synthetic_after_insert_failure');
        }
    });
    try {
        expect(fn () => B::commit($actor, $scope, $preview))->toThrow(RuntimeException::class, 'synthetic_after_insert_failure');
    } finally {
        $enabled = false;
    }
    expect(DB::table('wald_import_commands')->count())->toBe($before)->and(DB::table('wald_import_receipts')->count())->toBe(0)
        ->and(DB::table('wald_visit_observations')->count())->toBe(0)->and(ProjectedPlot::query()->count())->toBe(0);
})->with(['wald_import_receipts', 'wald_import_commands']);

it('denies every external role and stale stored Office role before upload', function (string $role) {
    [$actor, $scope] = F::owner();
    $id = PortalRole::query()->where('identifier', $role)->value('id');
    DB::table('users')->where('id', $actor->id)->update(['portal_role_id' => $id]);
    expect(fn () => (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command()))->toThrow(AuthorizationException::class);
    expect(DB::table('wald_import_runs')->count())->toBe(0);
})->with(['site_manager', 'assistant_site_manager', 'finishing_foreman']);

it('refuses forged scope preview and confirmation and keeps retained artifacts private', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    [, $otherScope] = F::owner();
    expect(fn () => (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), 'yes', F::command()))->toThrow(ImportConflict::class);
    $preview = B::reviewed($actor, $scope);
    expect(fn () => (new ImportReview)->commit($actor, $otherScope, $preview['run'], $preview['preview'], $preview['hash'], F::command()))->toThrow(RecordNotFoundException::class);
    expect(fn () => (new ImportReview)->commit($actor, $scope, $preview['run'], $preview['preview'], str_repeat('0', 64), F::command()))->toThrow(ImportConflict::class);
    $run = DB::table('wald_import_runs')->where('uuid', $preview['run'])->first();
    expect($run->uploader_id)->toBe($actor->id)->and($run->storage_key)->not->toContain('synthetic')
        ->and((new ImportRetention)->metadata($actor, $scope, $preview['run'])['automatic_disposal_enabled'])->toBeFalse();
});

it('recovers expired analysis leases and fences abandoned workers', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $run = (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
    $analysis = new ImportAnalysis;
    $claim = $analysis->claim($actor, $scope, $run['run'], 0, F::command());
    expect(fn () => $analysis->retry($actor, $scope, $run['run'], 1, F::command()))->toThrow(ImportConflict::class);
    $this->travel(11)->minutes();
    $analysis->retry($actor, $scope, $run['run'], 1, F::command());
    expect(fn () => $analysis->execute($actor, $scope, $run['run'], $claim['token']))->toThrow(ImportConflict::class, 'stale_analysis_lease');
    expect((new ImportIntake)->status($actor, $scope, $run['run'])['state'])->toBe('UPLOADED');
});

it('keeps stored-payload limits while hashing transient evidence in the same canonical format', function () {
    $small = ['unicode' => 'café', 'n' => 2.0, 'nested' => ['z' => null, 'a' => [1, 'x']]];
    expect(Canonical::evidenceHash($small))->toBe(Canonical::hash($small));
    $large = array_fill(0, 2000, str_repeat('x', 200));
    expect(fn () => Canonical::json($large))->toThrow(InvalidArgumentException::class, 'knowledge_payload_limit');
    expect(Canonical::evidenceHash($large))->toBe(hash('sha256', Canonical::json($large, 1048576)));
    expect(fn () => Canonical::evidenceHash(array_fill(0, 30000, str_repeat('x', 200))))->toThrow(InvalidArgumentException::class, 'transient_evidence_hash_limit');
});
