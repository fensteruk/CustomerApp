<?php

use App\Models\PortalRole;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

function w5Draft($actor, $scope, ?string $command = null): array
{
    return (new SourceBindingService)->draft($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', 'Reviewed exact identity.', $command ?? F::command());
}

function w5Activate($actor, $scope, array $draft): array
{
    return (new SourceBindingService)->activate($actor, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Explicit activation.', F::command());
}

it('supports separate immutable draft activation supersession and revocation', function () {
    [$actor, $scope] = F::owner();
    $service = new SourceBindingService;
    $draft = w5Draft($actor, $scope);
    expect($draft['state'])->toBe('DRAFT');
    expect(fn () => $service->resolve($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site'))->toThrow(ImportConflict::class);
    $active = w5Activate($actor, $scope, $draft);
    expect($service->resolve($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site'))->toBe($active);
    $oldVersion = (array) DB::table('wald_binding_versions')->first();
    $next = $service->draft($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', 'Reviewed successor.', F::command(), $active['epoch']);
    expect($service->resolve($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site')['version'])->toBe(1);
    $second = w5Activate($actor, $scope, $next);
    expect($second['version'])->toBe(2)->and((array) DB::table('wald_binding_versions')->where('version', 1)->first())->toBe($oldVersion);
    $history = $service->history($actor, $scope, $draft['binding']);
    expect($history)->toHaveCount(4)->and($history[3]['after']['superseded_version'])->toBe(1);
    $revoked = $service->revoke($actor, $scope, $draft['binding'], $second['epoch'], 'Source identity withdrawn.', F::command());
    expect($revoked['state'])->toBe('REVOKED');
    expect(fn () => $service->resolve($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site'))->toThrow(ImportConflict::class);
    $otherFamily = new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'another-family');
    expect(fn () => $service->activate($actor, $otherFamily, $draft['binding'], 2, $second['definition_hash'], $revoked['epoch'], 'Cannot resurrect.', F::command()))->toThrow(ImportConflict::class);
    expect(DB::table('wald_import_commands')->count())->toBe(5);
});

it('records current authenticated actor rather than caller-mutated uploader identity', function () {
    [$actor, $scope] = F::owner();
    $name = $actor->name;
    $actor->name = 'Untrusted supplied name';
    w5Draft($actor, $scope);
    expect(DB::table('wald_import_commands')->first()->actor_name)->toBe($name)
        ->and(DB::table('wald_binding_versions')->first()->actor_name)->toBe($name);
});

it('denies external inactive preview and stale stored roles', function (string $case) {
    [$actor, $scope] = F::owner();
    if ($case === 'inactive') {
        DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
    } elseif ($case === 'preview') {
        DB::table('users')->where('id', $actor->id)->update(['is_preview_user' => true]);
    } elseif ($case === 'caller-preview') {
        $actor->is_preview_user = true;
    } else {
        DB::table('users')->where('id', $actor->id)->update(['portal_role_id' => PortalRole::query()->where('identifier', $case)->value('id')]);
    }
    expect(fn () => w5Draft($actor, $scope))->toThrow(AuthorizationException::class);
    expect(DB::table('wald_source_bindings')->count())->toBe(0);
})->with(['site_manager', 'assistant_site_manager', 'finishing_foreman', 'inactive', 'preview', 'caller-preview']);

it('is default off and never gains authority from a null organisation', function () {
    [$actor, $scope] = F::owner();
    config(['wald_import.enabled' => false]);
    expect(fn () => w5Draft($actor, $scope))->toThrow(AuthorizationException::class);
});

it('requires exact names and keeps namespace identity separate from workbook family', function () {
    [$actor, $scope] = F::owner();
    $service = new SourceBindingService;
    $active = w5Activate($actor, $scope, w5Draft($actor, $scope));
    foreach (['synthetic site', 'Synthetic Site ', 'Synthetic Site Live'] as $name) {
        expect(fn () => $service->resolve($actor, $scope, 'EXACT_SITE_NAME', $name))->toThrow(ImportConflict::class);
    }
    $family = new KnowledgeScope($scope->organisationId, $scope->siteId, $scope->namespace, 'different');
    expect($service->resolve($actor, $family, 'EXACT_SITE_NAME', 'Synthetic Site'))->toBe($active);
    expect(fn () => $service->resolve($actor, $family, 'SOURCE_SITE_ID', 'Synthetic Site'))->toThrow(ImportConflict::class);
});

it('rejects cross-owner binding moves and mismatched site ownership', function () {
    [$actor, $scope] = F::owner();
    [, $other] = F::owner();
    $first = w5Activate($actor, $scope, w5Draft($actor, $scope));
    $service = new SourceBindingService;
    expect(fn () => $service->draft($actor, $other, 'EXACT_SITE_NAME', 'Synthetic Site', 'Attempted move.', F::command(), $first['epoch']))->toThrow(ImportConflict::class);
    expect(fn () => $service->history($actor, $other, $first['binding']))->toThrow(ImportConflict::class);
    $bad = new KnowledgeScope($scope->organisationId, $other->siteId, $scope->namespace, $scope->family);
    expect(fn () => w5Draft($actor, $bad))->toThrow(AuthorizationException::class);
    expect(DB::table('wald_binding_versions')->count())->toBe(1);
});

it('replays exact commands only after fresh authorization and refuses altered payloads', function () {
    [$actor, $scope] = F::owner();
    $command = F::command();
    $first = w5Draft($actor, $scope, $command);
    expect(w5Draft($actor, $scope, $command))->toBe($first)->and(DB::table('wald_import_commands')->count())->toBe(1);
    expect(fn () => (new SourceBindingService)->draft($actor, $scope, 'EXACT_SITE_NAME', 'Different', 'Different.', $command))->toThrow(ImportConflict::class);
    DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
    expect(fn () => w5Draft($actor, $scope, $command))->toThrow(AuthorizationException::class);
});

it('rejects stale epochs hashes and old versions without audit or binding effects', function () {
    [$actor, $scope] = F::owner();
    $service = new SourceBindingService;
    $draft = w5Draft($actor, $scope);
    foreach ([[$draft['version'], $draft['definition_hash'], 0], [1, str_repeat('0', 64), $draft['epoch']], [2, $draft['definition_hash'], $draft['epoch']]] as [$version, $hash, $epoch]) {
        expect(fn () => $service->activate($actor, $scope, $draft['binding'], $version, $hash, $epoch, 'Review.', F::command()))->toThrow(ImportConflict::class);
    }
    expect(DB::table('wald_import_commands')->count())->toBe(1)->and(DB::table('wald_source_bindings')->first()->active_version)->toBeNull();
});

it('protects version and audit history even against direct database writes', function (string $table, string $operation) {
    [$actor, $scope] = F::owner();
    w5Draft($actor, $scope);
    expect(fn () => $operation === 'delete' ? DB::table($table)->delete() : DB::table($table)->update(['actor_name' => 'Changed']))->toThrow(QueryException::class);
})->with(['wald_binding_versions', 'wald_import_commands'])->with(['delete', 'update']);

it('protects exact root identity case and spacing against bulk update', function (string $name) {
    [$actor, $scope] = F::owner();
    w5Draft($actor, $scope);
    expect(fn () => DB::table('wald_source_bindings')->update(['source_identity' => $name]))->toThrow(QueryException::class);
})->with(['synthetic site', 'Synthetic Site ']);

it('rolls back binding creation if its required audit insert fails', function () {
    [$actor, $scope] = F::owner();
    $enabled = true;
    $attempts = 0;
    // No MySQL DDL here: it would implicitly commit the test's outer transaction.
    DB::connection()->beforeExecuting(function (string $query) use (&$enabled, &$attempts): void {
        if ($enabled && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'wald_import_commands')) {
            $attempts++;
            throw new RuntimeException('test_audit_failure');
        }
    });
    try {
        expect(fn () => w5Draft($actor, $scope))->toThrow(RuntimeException::class, 'test_audit_failure');
        expect(DB::table('wald_source_bindings')->count())->toBe(0)->and(DB::table('wald_binding_versions')->count())->toBe(0)->and($attempts)->toBe(1);
    } finally {
        $enabled = false;
    }
});

it('requires the exact binding pin again under commit locks and invalidates it after a draft', function () {
    [$actor, $scope] = F::owner();
    $service = new SourceBindingService;
    $active = w5Activate($actor, $scope, w5Draft($actor, $scope));
    DB::transaction(fn () => $service->assertCurrent($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', $active));
    $service->draft($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', 'New review.', F::command(), $active['epoch']);
    expect(fn () => DB::transaction(fn () => $service->assertCurrent($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', $active)))->toThrow(ImportConflict::class);
});

it('provides bounded cursor history without granting another scope access', function () {
    [$actor, $scope] = F::owner();
    $draft = w5Draft($actor, $scope);
    w5Activate($actor, $scope, $draft);
    $service = new SourceBindingService;
    $page = $service->history($actor, $scope, $draft['binding'], 0, 1);
    expect($page)->toHaveCount(1)->and($service->history($actor, $scope, $draft['binding'], $page[0]['id'], 1))->toHaveCount(1);
});

it('rechecks stored authority on every binding operation', function (string $operation, string $role) {
    [$actor, $scope] = F::owner();
    $service = new SourceBindingService;
    $draft = w5Draft($actor, $scope);
    $active = w5Activate($actor, $scope, $draft);
    DB::table('users')->where('id', $actor->id)->update(['portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id')]);
    $action = match ($operation) {
        'activate' => fn () => w5Activate($actor, $scope, $draft),
        'revoke' => fn () => $service->revoke($actor, $scope, $draft['binding'], $active['epoch'], 'Untrusted.', F::command()),
        'resolve' => fn () => $service->resolve($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site'),
        'history' => fn () => $service->history($actor, $scope, $draft['binding']),
        'assertCurrent' => fn () => DB::transaction(fn () => $service->assertCurrent($actor, $scope, 'EXACT_SITE_NAME', 'Synthetic Site', $active)),
    };
    expect($action)->toThrow(AuthorizationException::class);
    expect(DB::table('wald_import_commands')->count())->toBe(2);
})->with(['activate', 'revoke', 'resolve', 'history', 'assertCurrent'])->with(['site_manager', 'assistant_site_manager', 'finishing_foreman']);

it('can roll back and reapply an empty SQLite foundation without touching Portal rows', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('MySQL DDL upgrade/rollback is verified outside transactional tests.');
    }
    [$actor] = F::owner();
    $before = $actor->fresh()->getRawOriginal();
    $migration = require database_path('migrations/2026_09_08_000011_create_wald_import_foundation.php');
    $migration->down();
    $migration->up();
    expect($actor->fresh()->getRawOriginal())->toBe($before)->and(DB::table('wald_source_bindings')->count())->toBe(0);
});

it('refuses populated rollback before removing any foundation table or trigger', function () {
    [$actor, $scope] = F::owner();
    w5Draft($actor, $scope);
    $migration = require database_path('migrations/2026_09_08_000011_create_wald_import_foundation.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Refusing rollback');
    expect(DB::table('wald_source_bindings')->count())->toBe(1)->and(DB::table('wald_binding_versions')->count())->toBe(1)
        ->and(DB::table('wald_import_commands')->count())->toBe(1);
});
