<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\ManualSourceImportPreview;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionIssue;
use App\Models\SourceSiteBinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (PortalRoleIdentifier::cases() as $role) {
        PortalRole::query()->firstOrCreate(
            ['identifier' => $role->value],
            ['name' => $role->label()],
        );
    }
});

test('only active non-preview Office Staff can open the source import UI', function (): void {
    $this->get(route('portal.source-imports.index'))->assertRedirect(route('login'));

    foreach (PortalRoleIdentifier::siteRoles() as $role) {
        $this->actingAs(sourceImportUiUser($role))
            ->get(route('portal.source-imports.index'))
            ->assertForbidden();
    }

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff, ['is_preview_user' => true]))
        ->get(route('portal.source-imports.index'))
        ->assertForbidden();

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff, ['is_active' => false]))
        ->get(route('portal.source-imports.index'))
        ->assertRedirect(route('login'));

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk();
});

test('the Office workflow presents all seven staged import screens', function (): void {
    $response = $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'));

    $response->assertOk()
        ->assertSee('Import SiteApp Spreadsheet')
        ->assertSeeInOrder([
            'Upload Source File',
            'Detected Spreadsheet Structure',
            'Confirm Column Mappings',
            'Map Source Sites',
            'Dry-Run Preview',
            'Confirm Import',
            'Import complete',
        ])
        ->assertSee('Nothing is imported during analysis.')
        ->assertSee('Cancel preview')
        ->assertSee('Import Another File')
        ->assertSee('Return to Office Review');
});

test('the upload UI defaults to the safe partial scope and explains stronger scopes', function (): void {
    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSee('Filtered / Partial Export')
        ->assertSee('value="PARTIAL_FILTERED_EXPORT"', false)
        ->assertSee('Complete export for selected site(s)')
        ->assertSee('Complete global export')
        ->assertSee('Missing rows are not treated as absent.')
        ->assertSee('Records absent from that scope may be flagged for reconciliation, but they will not be deleted.');
});

test('the mapping UI presents confirmed ignored and low-confidence handling', function (): void {
    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSee('Confirmed')
        ->assertSee('Needs confirmation')
        ->assertSee('Ignored')
        ->assertSee('Unknown')
        ->assertSee('Low confidence')
        ->assertSee('CAS, FLU, PFD, GLS, WP, MISC')
        ->assertSee('BF remains identifiable because it affects lead time.');

    expect(file_get_contents(resource_path('js/manual-source-import.js')))
        ->toContain("OPERATIONAL_TARGET_DATE: 'PC1 arrival / installation date'")
        ->toContain("COMMERCIAL_VALUE: 'Ignored commercial value'");
});

test('the import UI explains invalid CC codes and CML revisit presentation', function (): void {
    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSee('Possible typo — CC1')
        ->assertSee('CC! is shown as an unknown possible typo for CC1 and is never corrected automatically.')
        ->assertSee('CM1 and CM2 are shown as CML-related revisits, not separate Portal services.');
});

test('the site mapping UI receives only meaningful Portal site choices', function (): void {
    $organisation = CustomerOrganisation::factory()->create(['name' => 'Acme Developments']);
    $site = Site::factory()->for($organisation)->create(['name' => 'Willow Park']);

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSee('Acme Developments')
        ->assertSee('Willow Park')
        ->assertSee($site->uuid)
        ->assertSee('Sites are never matched automatically by similar names.')
        ->assertSee('A permanent Site ID will become the preferred source identifier once it is added to the export.');
});

test('the site mapping UI excludes portal sites without permanent identifiers', function (): void {
    $organisation = CustomerOrganisation::factory()->create(['name' => 'Legacy Customer']);
    $site = Site::factory()->for($organisation)->create(['name' => 'Legacy Seed Site']);
    Site::query()->whereKey($site)->update(['uuid' => null]);

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertDontSee('Legacy Seed Site')
        ->assertSee('No Portal sites with a permanent identifier are available');
});

test('the dry-run UI includes summary filters source-state separation and blocking feedback', function (): void {
    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSeeInOrder(['New', 'Unchanged', 'Updated', 'Completed', 'Completion reversed', 'Reconciliation needed', 'Mapping required', 'Invalid'])
        ->assertSee('Changed only')
        ->assertSee('Errors')
        ->assertSee('Warnings')
        ->assertSee('Current source state')
        ->assertSee('Incoming source state')
        ->assertSee('Portal workflow history is not being edited.')
        ->assertSee('The import cannot be confirmed yet.')
        ->assertSee(':disabled="!preview?.can_commit"', false);
});

test('confirmation results and reconciliation use Office-friendly audit details', function (): void {
    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff, ['name' => 'Fenster Tester']))
        ->get(route('portal.source-imports.index'))
        ->assertOk()
        ->assertSee('SHA-256 fingerprint')
        ->assertSee('Customer call-off and date-negotiation history will not be overwritten.')
        ->assertSee('Source run reference')
        ->assertSee('Initiated by')
        ->assertSee('Fenster Tester')
        ->assertSee('View Reconciliation')
        ->assertSee('Internal source checks from this import. These are not shown to customers.')
        ->assertSee('No reconciliation issues');
});

test('opening the UI does not mutate source or Portal workflow state', function (): void {
    $office = sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff);

    $this->actingAs($office)
        ->get(route('portal.source-imports.index'))
        ->assertOk();

    expect(ManualSourceImportPreview::query()->count())->toBe(0)
        ->and(SourceImportRun::query()->count())->toBe(0)
        ->and(SourceSiteBinding::query()->count())->toBe(0)
        ->and(SourceProjectionIssue::query()->count())->toBe(0)
        ->and(ProjectedPlot::query()->count())->toBe(0);
});

test('Office Review exposes the import entry only to authorised Office Staff', function (): void {
    $office = sourceImportUiUser(PortalRoleIdentifier::FensterOfficeStaff);

    $this->actingAs($office)
        ->get(route('portal.review-requests'))
        ->assertOk()
        ->assertSee('Import spreadsheet')
        ->assertSee(route('portal.source-imports.index'));

    $this->actingAs(sourceImportUiUser(PortalRoleIdentifier::SiteManager))
        ->get(route('portal.review-requests'))
        ->assertForbidden();
});

function sourceImportUiUser(PortalRoleIdentifier $role, array $attributes = []): User
{
    return User::factory()
        ->role($role)
        ->create($attributes);
}
