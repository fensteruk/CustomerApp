<?php

use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportIntake;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;
use Tests\Support\Wald05Race;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable WALD05 MySQL 8.4.');
    }
    if (config('database.connections.mysql.host') !== '127.0.0.1' || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_') || ! str_starts_with(DB::selectOne('SELECT VERSION() AS v')->v, '8.4.')) {
        throw new RuntimeException('disposable_mysql84_required');
    }
    config(['wald_import.enabled' => true]);
});
afterEach(fn () => B::$callBase = 1001);

it('preserves atomic ordering and dependency outcomes in repeated independent connection races', function (string $scenario) {
    for ($iteration = 0; $iteration < 20; $iteration++) {
        [$first, $initialScope] = F::owner();
        $scope = new KnowledgeScope($initialScope->organisationId, $initialScope->siteId, 'race-'.F::command(), 'family-v1');
        B::$callBase = 100000 + $scope->siteId * 1000;
        $second = $first->replicate();
        $second->uuid = F::command();
        $second->email = F::command().'@example.test';
        $second->save();
        $binding = B::binding($first, $scope);
        $profile = $scenario === 'profile-revocation' ? F::active($first, $scope)[4] : null;
        $base = B::reviewed($first, $scope, date: '2026-09-08');
        B::commit($first, $scope, $base);
        $predecessor = $scenario === 'same-slot-correction' ? $base['run'] : null;
        $dateA = $predecessor ? '2026-09-08' : '2026-09-09';
        $a = B::reviewed($first, $scope, [0 => ['VS' => '3.000']], date: $dateA, predecessor: $predecessor);
        $args = fn ($p) => [$p['run'], $p['preview'], $p['hash'], F::command()];
        $before = DB::table('wald_import_receipts')->count();
        if ($scenario === 'double-commit') {
            $operations = [['commit', $args($a)], ['commit', $args($a)]];
        } elseif ($scenario === 'binding-change') {
            $operations = [['commit', $args($a)], ['revoke', [$binding['binding'], $binding['epoch'], 'Concurrent revocation.', F::command()]]];
        } elseif ($scenario === 'profile-revocation') {
            $operations = [['commit', $args($a)], ['profile_revoke', [$profile->uuid, $profile->lock_version, 'Concurrent revocation.', F::command()]]];
        } elseif ($scenario === 'projection-change') {
            $service = DB::table('projected_plot_services')->where('source_call_number', (string) B::$callBase)->firstOrFail();
            $operations = [['commit', $args($a)], ['projection_change', [$service->id, (int) $service->wald_epoch]]];
        } else {
            $b = B::reviewed($second, $scope, [0 => ['VS' => '4.000']], date: $scenario === 'older-newer' ? '2026-09-10' : $dateA,
                slot: $scenario === 'am-pm' ? 'AFTERNOON' : 'MORNING', predecessor: $predecessor);
            if ($predecessor) {
                $state = (new ImportIntake)->status($first, $scope, $a['run']);
                $a = (new ImportReview)->preview($first, $scope, $a['run'], $state['epoch'], F::command());
                (new ImportReview)->approve($first, $scope, $a['run'], $a['preview'], $a['hash'], F::command());
            }
            $operations = [['commit', $args($a)], ['commit', $args($b)]];
        }
        $results = Wald05Race::run([$first, $second], $scope, $operations);
        foreach ($results as $r) {
            if (! $r['ok']) {
                expect($r['exception'])->toBe(ImportConflict::class);
            }
        }
        $success = collect($results)->where('ok', true)->count();
        if ($scenario === 'double-commit') {
            expect($success)->toBe(2)->and($results[0]['result'])->toBe($results[1]['result']);
        } elseif (in_array($scenario, ['binding-change', 'profile-revocation'], true)) {
            expect($success)->toBeGreaterThanOrEqual(1);
        } else {
            expect($success)->toBe(1);
        }
        $added = DB::table('wald_import_receipts')->count() - $before;
        expect($added)->toBe(in_array($scenario, ['binding-change', 'profile-revocation', 'projection-change'], true) ? ($results[0]['ok'] ? 1 : 0) : 1);
        $stream = DB::table('wald_import_streams')->where('source_namespace', $scope->namespace)->firstOrFail();
        $newest = DB::table('wald_import_receipts')->where('stream_id', $stream->id)->orderByDesc('export_order')->orderByDesc('revision')->firstOrFail();
        expect($stream->latest_order)->toBe($newest->export_order);
    }
})->with(['double-commit', 'same-call-update', 'am-pm', 'older-newer', 'binding-change', 'profile-revocation', 'projection-change', 'same-slot-correction']);
