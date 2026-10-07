<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Integration\WaldDryRun;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
});

function dryOffice(): User
{
    return User::factory()->create(['customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id')]);
}

function dryWorkbook(array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('private.csv', "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,Complete,VS\n".implode("\n", $rows)."\n");
}

it('produces deterministic reports and never mutates business binding or import evidence', function (): void {
    $office = dryOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Dry Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Dry Site', 'is_active' => true]);
    ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => '1']);
    $rows = ['DRY1,1001,Description,Dry Customer - Dry Site - Plot 1,PC1,No,2',
        'DRY1,1002,Description,Dry Customer - Dry Site - Plot 2,,Yes,2',
        'DRY2,1003,Description,Dry Customer - New Site - Plot Com 4,PC1,No,3',
        'DRY3,1004,Description,New Customer - New Site - Plot 1,PC1,No,1'];
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $first = (new WaldDryRun)->analyse($office, dryWorkbook($rows));
    $second = (new WaldDryRun)->analyse($office, dryWorkbook($rows));
    expect($first['fingerprint'])->toBe($second['fingerprint'])
        ->and($first['content'])->toBe($second['content'])
        ->and($first['content']['summary'])->toMatchArray(['total_rows' => 4, 'included' => 4, 'excluded' => 0,
            'automatic' => 1, 'new_site_proposals' => 1, 'new_customer_site_proposals' => 1, 'plots_reuse' => 1, 'plots_create' => 3])
        ->and(DB::table('wald_pilot_uploads')->count())->toBe(0)
        ->and(DB::table('wald_import_runs')->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0)
        ->and(DB::table('projected_plots')->count())->toBe(1);
    foreach ($queries as $query) {
        expect(preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query))->toBe(0);
    }
    $response = $this->actingAs($office)->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => dryWorkbook($rows)])
        ->assertOk()->assertSee('No data applied')->assertSee('Print or save report as PDF')->assertDontSee('private.csv')
        ->assertDontSee('COMMIT THIS ONE SITE');
    if (getenv('WALD_ROBUSTNESS_RENDER') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing/wald-robustness-ui'));
        File::put(storage_path('framework/testing/wald-robustness-ui/dry-run.html'), $response->getContent());
    }
});

it('reports unknown rows exclusions malformed groups product and service conflicts', function (): void {
    $office = dryOffice();
    $report = (new WaldDryRun)->analyse($office, dryWorkbook([
        'CONFLICT,1101,Description,Customer - Site - Plot 1,PC1,No,2',
        'CONFLICT,1102,Description,Customer - Site - Plot 1,PC1,No,3',
        'MALFORMED,1103,Description,not a hierarchy,PC1,No,2',
        ',1104,Description,Customer - Site - Plot 4,PC1,No,2',
        'UNKNOWN,1105,Description,Customer - Other Site - Plot 5,ZZQ,No,2',
        'XXTEST,1106,Description,ignored,PC1,No,2',
        'EXCLUDED,1107,Description,ignored,CU4,No,2',
    ]));
    expect($report['content']['summary'])->toMatchArray(['total_rows' => 7, 'included' => 4, 'excluded' => 2, 'unknown_rows' => 1,
        'malformed_groups' => 1, 'blocking_units' => 3]);
    $units = collect($report['content']['units'])->keyBy('customer_code');
    expect($units['CONFLICT']['issues'])->toContain('PRODUCT_QUANTITY_CONFLICT', 'DISTINCT_VISIT_PROJECTION_REQUIRES_REVIEW')
        ->and($units['UNKNOWN']['issues'])->toContain('UNKNOWN_CALL_TYPE')
        ->and($units['MALFORMED']['issues'])->toContain('MALFORMED_HIERARCHY');
});

it('reports current binding mismatch and scopes the same plot reference to each site', function (): void {
    $office = dryOffice();
    $a = CustomerOrganisation::factory()->create(['name' => 'Dry A', 'is_active' => true]);
    $b = CustomerOrganisation::factory()->create(['name' => 'Dry B', 'is_active' => true]);
    $siteA = Site::factory()->create(['customer_organisation_id' => $a->id, 'name' => 'Same Site', 'is_active' => true]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $b->id, 'name' => 'Same Site', 'is_active' => true]);
    ProjectedPlot::factory()->create(['site_id' => $siteA->id, 'plot_reference' => '1']);
    $scope = new KnowledgeScope($a->id, $siteA->id, 'redzebra', 'call-offs');
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, 'CUSTOMER_CODE', 'WRONG', 'Synthetic mismatch.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Synthetic mismatch.', (string) Str::uuid());
    $report = (new WaldDryRun)->analyse($office, dryWorkbook([
        'A,1201,Description,Dry A - Same Site - Plot 1,PC1,No,2',
        'B,1202,Description,Dry B - Same Site - Plot 1,PC1,No,2',
        'WRONG,1203,Description,Dry B - Same Site - Plot 2,PC1,No,2',
    ]));
    $units = collect($report['content']['units'])->keyBy('customer_code');
    expect($units['A']['plots_reuse'])->toBe(1)->and($units['B']['plots_create'])->toBe(1)
        ->and($units['WRONG']['issues'])->toContain('BINDING_CONFLICT')
        ->and($units['B']['site_uuid'])->toBe($siteB->uuid);
});

it('uses fresh knowledge and changes its state fingerprint after current facts change', function (): void {
    $office = dryOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Fresh Customer', 'is_active' => true]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Fresh Site', 'is_active' => true]);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => '1']);
    $rows = ['FRESH,1301,Description,Fresh Customer - Fresh Site - Plot 1,PC1,No,2'];
    $first = (new WaldDryRun)->analyse($office, dryWorkbook($rows));
    DB::table('projected_plots')->where('id', $plot->id)->update(['external_identifier' => 'changed-current-source']);
    $next = (new WaldDryRun)->analyse($office, dryWorkbook($rows));
    expect($first['fingerprint'])->not->toBe($next['fingerprint'])
        ->and($next['content']['pins'])->toBe((new KnowledgeIdentity)->current())
        ->and($next['content']['apply_approval'])->toBeFalse();
});

it('qualifies a large synthetic master export with bounded database queries', function (): void {
    $office = dryOffice();
    $rows = [];
    for ($index = 1; $index <= 1825; $index++) {
        $group = $index % 56;
        $rows[] = "LARGE{$group},".(20000 + $index).",Description,Large Customer {$group} - Large Site {$group} - Plot {$index},PC1,No,2";
    }
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $report = (new WaldDryRun)->analyse($office, dryWorkbook($rows));
    expect($report['content']['summary'])->toMatchArray(['total_rows' => 1825, 'source_units' => 56,
        'new_customer_site_proposals' => 56, 'plots_create' => 1825, 'blocking_units' => 0])
        ->and($queries)->toBeLessThan(35);
    fwrite(STDOUT, json_encode(['wald_dry_run' => $report['metrics'], 'rows' => 1825, 'queries' => $queries])."\n");
});

it('denies customer roles and refuses dry run when the import gate is off', function (): void {
    $office = dryOffice();
    config(['wald_import.pilot_available' => false]);
    $this->actingAs($office)->get(route('office.workspace.imports.dry-run'))->assertForbidden();
    config(['wald_import.pilot_available' => true]);
    foreach (['site_manager', 'assistant_site_manager', 'finishing_foreman'] as $role) {
        $external = User::factory()->create(['is_active' => true, 'portal_role_id' => PortalRole::query()->where('identifier', $role)->value('id')]);
        $this->actingAs($external)->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => dryWorkbook(['A,1401,X,A - B - Plot 1,PC1,No,2'])])->assertForbidden();
    }
});

it('preserves dictionary-ignored columns without treating them as competing meanings', function (): void {
    $office = dryOffice();
    $file = UploadedFile::fake()->createWithContent('ignored.csv',
        "CustomerNo,Call No.,Site Name,Plot Ref,Call Type,Complete,VS,Items Ordered Status,Site Value\n"
        ."IGNORED-FIELDS,1501,Description,Customer - Site - Plot 1,PC1,No,2,Internal status,12345\n");
    $report = (new WaldDryRun)->analyse($office, $file);
    expect($report['content']['summary']['blocking_units'])->toBe(0)
        ->and($report['content']['summary']['total_rows'])->toBe(1)
        ->and(DB::table('wald_pilot_uploads')->count())->toBe(0);
});

it('renders stopped analysis as a readable validation error instead of a server error', function (): void {
    $office = dryOffice();
    $this->actingAs($office)->from(route('office.workspace.imports.dry-run'))
        ->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => dryWorkbook([
            'INVALID,invalid,Description,Customer - Site - Plot 1,PC1,No,2',
        ])])->assertRedirect(route('office.workspace.imports.dry-run'))
        ->assertSessionHasErrors(['workbook' => 'Analysis stopped safely: Invalid call number.']);
    expect(DB::table('wald_pilot_uploads')->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0);
});

it('renders unit blockers and unknown-row reasons through the actual HTTP report', function (): void {
    $office = dryOffice();
    $this->actingAs($office)->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => dryWorkbook([
        'UNKNOWN-TYPE,1701,Description,Customer - Site - Plot 1,ZZQ,No,2',
        ',1702,Description,Customer - Site - Plot 2,PC1,No,2',
    ])])->assertOk()->assertSee('Unknown call type.')->assertSee('Customer code missing.')
        ->assertSee('No data applied');
    expect(DB::table('projected_plots')->count())->toBe(0)
        ->and(DB::table('wald_pilot_uploads')->count())->toBe(0);
});

it('lists unresolved alternate export headers without approving their meanings or deduplicating rows', function (): void {
    $office = dryOffice();
    $file = UploadedFile::fake()->createWithContent('alternate.csv',
        "Call,Call Type,Site Name,Customer Name,Arrival,Complete Date,Customer\n"
        ."1801,PC1,Fictional - Example - Plot 033,Example description,2026-01-01,,EXAMPLE\n"
        ."1801,PC1,Fictional - Example - Plot 033,Example description,2026-01-02,,EXAMPLE\n");
    $this->followingRedirects()->actingAs($office)->from(route('office.workspace.imports.dry-run'))
        ->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => $file])
        ->assertOk()
        ->assertSee('Observed header labels: Call, Call Type, Site Name, Customer Name, Arrival, Complete Date, Customer.')
        ->assertSee('Call Number: no recognised column.')
        ->assertSee('CustomerCode for a new master export: no recognised column.')
        ->assertSee('Plot hierarchy: no recognised column.')
        ->assertSee('No data was applied. No column mapping was approved.')
        ->assertSee('do not rename columns or discard repeated rows');
    expect(DB::table('wald_pilot_uploads')->count())->toBe(0)
        ->and(DB::table('wald_source_bindings')->count())->toBe(0)
        ->and(DB::table('projected_plots')->count())->toBe(0);
});
