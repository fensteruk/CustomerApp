<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\WaldDryRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->capacityOriginalMemory = ini_get('memory_limit');
});

afterEach(function (): void {
    ini_set('memory_limit', $this->capacityOriginalMemory);
});

it('qualifies bounded mixed XLSX inventory without mutations across the complete workbook', function (int $count): void {
    ini_set('memory_limit', '256M');
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
    $rows = [1 => [1 => 'CustomerNo', 2 => 'Call No.', 3 => 'Site Name', 4 => 'Plot Ref', 5 => 'Call Type', 6 => 'VS']];
    for ($i = 1; $i <= $count; $i++) {
        $group = $i % 145;
        $row = [1 => 'PERF'.$group, 3 => 'Description '.$group,
            4 => 'Fixture Customer '.$group.' - Fixture Site '.$group.' - Plot '.str_pad((string) $i, 5, '0', STR_PAD_LEFT)];
        if ($i % 4 === 0 || in_array($i, [1, 5001], true)) {
            $row[2] = in_array($i, [1, 5001], true) ? '90000001' : (string) (90000000 + $i);
            $row[5] = 'PC1';
            $row[6] = '2';
        }
        ksort($row);
        $rows[$i + 1] = $row;
    }
    $path = WaldFixtures::xlsx([['name' => 'Data', 'rows' => $rows]]);
    unset($rows);
    memory_reset_peak_usage();
    $baseline = [];
    foreach (['customer_organisations', 'sites', 'projected_plots', 'wald_source_bindings', 'wald_source_rows', 'wald_source_visits', 'projected_plot_services', 'call_off_requests', 'portal_notifications', 'wald_pilot_uploads', 'wald_import_runs'] as $table) {
        $baseline[$table] = DB::table($table)->count();
    }
    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        $queries++;
        expect(preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql))->toBe(0);
    });
    $result = (new WaldDryRun)->analyse($office, new UploadedFile($path, 'synthetic-capacity.xlsx', null, null, true));
    $analysisQueries = $queries;
    expect($result['content']['summary']['total_rows'])->toBe($count)
        ->and($result['content']['summary']['plots_discovered'])->toBe($count)
        ->and($result['content']['apply_approval'])->toBeFalse();
    if ($count > 5000) {
        expect($result['content']['call_findings'])->toHaveCount(1)
            ->and($result['content']['call_findings'][0]['call_number'])->toBe('90000001')
            ->and(array_column($result['content']['call_findings'][0]['rows'], 'row'))->toBe([2, 5002]);
    }
    if ($count === 11060) {
        $replay = (new WaldDryRun)->analyse($office, new UploadedFile($path, 'synthetic-capacity.xlsx', null, null, true));
        expect($replay['fingerprint'])->toBe($result['fingerprint'])->and($replay['content'])->toBe($result['content']);
    }
    foreach ($baseline as $table => $before) {
        expect(DB::table($table)->count())->toBe($before);
    }
    fwrite(STDOUT, json_encode(['capacity_rows' => $count, 'metrics' => $result['metrics'], 'queries' => $analysisQueries,
        'source_bytes' => filesize($path), 'report_bytes' => strlen(json_encode($result)), 'php_memory_limit' => ini_get('memory_limit')]).PHP_EOL);
})->with([5000, 11060, 20000]);
