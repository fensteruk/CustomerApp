<?php

use App\Models\Site;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportClarification;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportRetention;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PrivateWorkbookStorage;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\Wald05QaProfiles;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('W5Q preserves canonical digest identity after numeric associative keys become a list', function () {
    $input = ['nested' => [1 => 'one', 0 => 'zero']];
    expect(Canonical::evidenceHash($input))->toBe(Canonical::hash($input));
});

it('W5Q forbids same-slot visit overwrite through a different workbook family', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $other = new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'other-family');
    expect(fn () => B::commit($actor, $other, B::reviewed($actor, $other, [0 => ['VS' => '99.000']])))->toThrow(ImportConflict::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(1);
});

it('W5Q refuses a new namespace taking over an already owned projection', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $other = new KnowledgeScope($scope->organisationId, $scope->siteId, 'other-source', $scope->family);
    B::binding($actor, $other);
    expect(fn () => B::commit($actor, $other, B::reviewed($actor, $other, [0 => ['VS' => '99.000']], date: '2026-09-08')))->toThrow(ImportConflict::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(1);
});

it('W5Q validates exact preview expiry without extending the review', function (int $offset, bool $allowed) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $this->freezeSecond();
    $preview = B::reviewed($actor, $scope);
    $expiry = DB::table('wald_import_previews')->where('uuid', $preview['preview'])->value('expires_at');
    $this->travelTo(Carbon::parse($expiry, 'UTC')->addSeconds($offset));
    if ($allowed) {
        B::commit($actor, $scope, $preview);
    } else {
        expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'stale_preview');
    }
    expect(DB::table('wald_import_receipts')->count())->toBe($allowed ? 1 : 0);
})->with([[-1, true], [0, false], [1, false]]);

it('W5Q denies all backend operations after stored Office authority is revoked', function (string $operation, string $mutation) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    $state = (new ImportIntake)->status($actor, $scope, $preview['run']);
    $changes = match ($mutation) {
        'inactive' => ['is_active' => false],
        'preview' => ['is_preview_user' => true],
        default => ['portal_role_id' => DB::table('portal_roles')->where('identifier', $mutation)->value('id')],
    };
    DB::table('users')->where('id', $actor->id)->update($changes);
    $action = match ($operation) {
        'upload' => fn () => (new ImportIntake)->upload($actor, $scope, B::workbook(), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command()),
        'analyse' => fn () => (new ImportAnalysis)->claim($actor, $scope, $state['run'], $state['epoch'], F::command()),
        'clarify' => fn () => (new ImportClarification)->questions($actor, $scope, $state['run']),
        'review' => fn () => (new ImportReview)->preview($actor, $scope, $state['run'], $state['epoch'], F::command()),
        'commit' => fn () => B::commit($actor, $scope, $preview),
        'retry' => fn () => (new ImportAnalysis)->retry($actor, $scope, $state['run'], $state['epoch'], F::command()),
        'audit' => fn () => (new ImportReview)->details($actor, $scope, $state['run']),
        'retention' => fn () => (new ImportRetention)->metadata($actor, $scope, $state['run']),
    };
    expect($action)->toThrow(AuthorizationException::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(0);
})->with(['upload', 'analyse', 'clarify', 'review', 'commit', 'retry', 'audit', 'retention'])
    ->with(['inactive', 'preview', 'site_manager', 'assistant_site_manager', 'finishing_foreman']);

it('W5Q rejects foreign run UUIDs across private backend reads and writes', function (string $operation) {
    [$actor, $scope] = F::owner();
    [, $foreign] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    $action = match ($operation) {
        'status' => fn () => (new ImportIntake)->status($actor, $foreign, $preview['run']),
        'details' => fn () => (new ImportReview)->details($actor, $foreign, $preview['run']),
        'questions' => fn () => (new ImportClarification)->questions($actor, $foreign, $preview['run']),
        'commit' => fn () => B::commit($actor, $foreign, $preview),
        'retention' => fn () => (new ImportRetention)->metadata($actor, $foreign, $preview['run']),
    };
    expect($action)->toThrow(RecordNotFoundException::class);
    expect(DB::table('wald_import_receipts')->count())->toBe(0);
})->with(['status', 'details', 'questions', 'commit', 'retention']);

it('W5Q confines hostile filenames to generated private keys', function (string $name) {
    [$actor, $scope] = F::owner();
    $file = B::workbook();
    $result = (new ImportIntake)->upload($actor, $scope, new UploadedFile($file->getPathname(), $name, null, null, true), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
    $run = DB::table('wald_import_runs')->where('uuid', $result['run'])->firstOrFail();
    $path = (new PrivateWorkbookStorage)->path($run);
    expect($run->storage_key)->toMatch('/^[a-f0-9-]{36}\.xlsx$/D')
        ->and(dirname($path))->toBe(storage_path('app/private/wald-imports'))
        ->and($run->original_name)->not->toContain('/', '\\');
})->with(['../../file.xlsx', '..\\..\\file.xlsx', '<script>.xlsx', 'a/b/c.xlsx']);

it('W5Q supports a single explicit source record', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $receipt = B::commit($actor, $scope, B::reviewed($actor, $scope, count: 1));
    expect($receipt['counts']['applied'])->toBe(1);
});

it('W5Q rejects unsupported structural units without a staged subset', function (string $kind) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $rows = [1 => [1 => 'Call No.', 2 => 'Site Name', 3 => 'Plot', 4 => 'Call Type', 5 => 'complete']];
    for ($i = 2; $i <= 8; $i++) {
        $rows[$i] = [1 => (string) (1000 + $i), 2 => 'Synthetic Site', 3 => 'P'.$i, 4 => 'PC1', 5 => 'No'];
    }
    $sheets = [['rows' => $rows]];
    if ($kind === 'empty') {
        $sheets[0]['rows'] = [1 => $rows[1]];
    } elseif ($kind === 'merged') {
        $sheets[0]['merges'] = ['A1:B1'];
    } elseif ($kind === 'hidden-row') {
        $sheets[0]['hidden_rows'] = [3];
    } else {
        $sheets[] = ['rows' => $rows, 'visibility' => $kind === 'hidden-sheet' ? 'hidden' : 'visible'];
    }
    $path = WaldFixtures::xlsx($sheets);
    $upload = (new ImportIntake)->upload($actor, $scope, new UploadedFile($path, 'bounded.xlsx', null, null, true), new ExportOrder('2026-09-09', 'MORNING'), ExportOrder::CONFIRMATION, F::command());
    expect(fn () => (new ImportAnalysis)->analyse($actor, $scope, $upload['run'], 0, F::command()))->toThrow(ImportConflict::class);
    expect(DB::table('wald_import_stages')->count())->toBe(0)->and(DB::table('wald_import_receipts')->count())->toBe(0);
})->with(['empty', 'merged', 'hidden-row', 'two-visible-sheets', 'hidden-sheet']);

it('W5Q refuses invalid runtime transitions with no state or audit mutation', function (string $state) {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    DB::table('wald_import_runs')->where('uuid', $preview['run'])->update(['state' => $state]);
    $before = (array) DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail();
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'commit_state_conflict');
    expect(fn () => (new ImportReview)->preview($actor, $scope, $preview['run'], $before['epoch'], F::command()))->toThrow(ImportConflict::class);
    expect((array) DB::table('wald_import_runs')->where('uuid', $preview['run'])->firstOrFail())->toBe($before)
        ->and(DB::table('wald_import_receipts')->count())->toBe(0);
})->with(['UPLOADED', 'FAILED', 'SUPERSEDED', 'COMMITTING', 'COMMITTED']);

it('W5Q exposes the shared-slot multi-site pilot boundary without silently splitting', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $site = Site::factory()->create(['customer_organisation_id' => $scope->organisationId]);
    $other = new KnowledgeScope($scope->organisationId, $site->id, $scope->namespace, $scope->family);
    $binding = new SourceBindingService;
    $draft = $binding->draft($actor, $other, 'EXACT_SITE_NAME', 'Second Site', 'Explicit second site.', F::command());
    $binding->activate($actor, $other, $draft['binding'], 1, $draft['definition_hash'], $draft['epoch'], 'Reviewed.', F::command());
    $rows = [];
    for ($i = 0; $i < 7; $i++) {
        $rows[$i] = ['Site Name' => 'Second Site', 'Call No.' => (string) (9000 + $i)];
    }
    expect(fn () => B::reviewed($actor, $other, $rows))->toThrow(ImportConflict::class, 'slot_scope_conflict');
    expect(DB::table('projected_plots')->where('site_id', $site->id)->count())->toBe(0);
});

it('W5Q preserves durable refusal audit without confusing it with partial commit', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    $preview = B::reviewed($actor, $scope);
    $before = DB::table('wald_import_commands')->count();
    $this->travel(25)->hours();
    expect(fn () => B::commit($actor, $scope, $preview))->toThrow(ImportConflict::class, 'stale_preview');
    expect(DB::table('wald_import_commands')->count())->toBe($before)
        ->and(DB::table('wald_import_receipts')->count())->toBe(0)
        ->and(DB::table('wald_commit_attempt_outcomes')->value('outcome'))->toBe('STALE');
});

it('W5Q measures the receipt-count query slope omitted by the row-only benchmark', function () {
    $counts = [];
    $measure = function ($actor, $scope, $preview): int {
        $selects = 0;
        $enabled = true;
        DB::listen(function ($query) use (&$selects, &$enabled) {
            if ($enabled && str_starts_with(strtolower($query->sql), 'select')) {
                $selects++;
            }
        });
        try {
            B::commit($actor, $scope, $preview);
        } finally {
            $enabled = false;
        }

        return $selects;
    };
    foreach ([1, 7, 20] as $uses) {
        [$actor, $initial] = F::owner();
        $scope = new KnowledgeScope($initial->organisationId, $initial->siteId, 'query-'.F::command(), 'family-v1');
        B::$callBase = 100000 + $scope->siteId * 1000;
        B::binding($actor, $scope);
        [$preview] = Wald05QaProfiles::reviewed($actor, $scope, $uses);
        try {
            $counts[$uses] = $measure($actor, $scope, $preview);
        } finally {
            B::$callBase = 1001;
        }
    }
    fwrite(STDERR, json_encode(['wald05_qa_profile_receipt_selects' => $counts]).PHP_EOL);
    expect(max($counts) - min($counts))->toBeLessThanOrEqual(2)
        ->and(max($counts))->toBeLessThanOrEqual(100);
});
