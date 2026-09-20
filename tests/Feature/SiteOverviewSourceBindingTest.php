<?php

use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')
        ->where('key', 'wald_import_pilot_enabled')
        ->update(['enabled' => true]);
});

function sourceBindingOffice(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
        'name' => 'Source Binding Reviewer',
    ]);
}

/** @return array{0: CustomerOrganisation, 1: Site} */
function sourceBindingSite(string $name = 'Willow Equivalent'): array
{
    $customer = CustomerOrganisation::factory()->create(['name' => 'Fixture Customer']);
    $site = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => $name,
    ]);

    return [$customer, $site];
}

/** @return array{binding: string, version: int, definition_hash: string, epoch: int, state: string} */
function activateSourceBinding(User $office, CustomerOrganisation $customer, Site $site, string $kind, string $identity): array
{
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
    $service = new SourceBindingService;
    $draft = $service->draft(
        $office,
        $scope,
        $kind,
        $identity,
        'Reviewed exact source mapping.',
        (string) Str::uuid(),
    );

    return $service->activate(
        $office,
        $scope,
        $draft['binding'],
        $draft['version'],
        $draft['definition_hash'],
        $draft['epoch'],
        'Approved source mapping.',
        (string) Str::uuid(),
    );
}

it('keeps overview and source detail consistent for an active CustomerCode binding', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite();
    activateSourceBinding($office, $customer, $site, 'CUSTOMER_CODE', 'ACME-WILLOW-E2E');

    $overview = $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]));
    $overview->assertOk()
        ->assertSee('>Linked<', false)
        ->assertSee('Linked to source data.')
        ->assertDontSee('Not available yet')
        ->assertDontSee('Not linked')
        ->assertDontSee('ACME-WILLOW-E2E');

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('CustomerCode')
        ->assertSee('ACME-WILLOW-E2E');
});

it('shows a legacy source identity as linked without inventing or exposing a CustomerCode on overview', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Legacy Site');
    activateSourceBinding($office, $customer, $site, 'EXACT_SITE_NAME', 'Legacy Source Site Name');

    $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('>Linked<', false)
        ->assertSee('Linked to source data.')
        ->assertDontSee('Not available yet')
        ->assertDontSee('Not linked')
        ->assertDontSee('CustomerCode')
        ->assertDontSee('Legacy Source Site Name');

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertOk()
        ->assertSee('Exact source site name')
        ->assertSee('Legacy Source Site Name');
});

it('does not infer a source link from projected plots on an unbound site', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Unbound Site');
    ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => 'PLOT-WITHOUT-BINDING']);

    $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('Not linked')
        ->assertDontSee('Not available yet')
        ->assertDontSee('>Linked<', false);

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertOk()
        ->assertSee('Not linked');
});

it('ignores draft revoked and other-site bindings when deciding whether this site is linked', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Inactive Binding Site');
    $otherSite = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'Other Linked Site',
    ]);
    $service = new SourceBindingService;
    $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');

    $service->draft(
        $office,
        $scope,
        'CUSTOMER_CODE',
        'DRAFT-CUSTOMER-CODE',
        'Not yet approved.',
        (string) Str::uuid(),
    );
    $active = activateSourceBinding($office, $customer, $site, 'SOURCE_SITE_ID', 'REVOKED-SOURCE-ID');
    $service->revoke(
        $office,
        $scope,
        $active['binding'],
        $active['epoch'],
        'Binding no longer applies.',
        (string) Str::uuid(),
    );
    activateSourceBinding($office, $customer, $otherSite, 'CUSTOMER_CODE', 'OTHER-SITE-CUSTOMER-CODE');

    $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('Not linked')
        ->assertDontSee('>Linked<', false)
        ->assertDontSee('DRAFT-CUSTOMER-CODE')
        ->assertDontSee('REVOKED-SOURCE-ID')
        ->assertDontSee('OTHER-SITE-CUSTOMER-CODE');
});

it('renders multiple active bindings as one stable concise linked state', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Multiple Binding Site');
    activateSourceBinding($office, $customer, $site, 'CUSTOMER_CODE', 'MULTI-CUSTOMER-CODE');
    activateSourceBinding($office, $customer, $site, 'SOURCE_SITE_ID', 'MULTI-LEGACY-ID');

    $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('>Linked<', false)
        ->assertSee('Linked to source data.')
        ->assertDontSee('Not available yet')
        ->assertDontSee('Not linked')
        ->assertDontSee('MULTI-CUSTOMER-CODE')
        ->assertDontSee('MULTI-LEGACY-ID')
        ->assertDontSee('Undefined array key');

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertOk()
        ->assertSee('MULTI-CUSTOMER-CODE')
        ->assertSee('MULTI-LEGACY-ID');
});

it('reports source binding availability truthfully when the pilot gate is off', function (): void {
    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Unavailable Binding Site');
    activateSourceBinding($office, $customer, $site, 'CUSTOMER_CODE', 'GATED-CUSTOMER-CODE');
    config(['wald_import.pilot_available' => false]);

    $this->actingAs($office)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('Not available yet')
        ->assertDontSee('Not linked')
        ->assertDontSee('>Linked<', false)
        ->assertDontSee('GATED-CUSTOMER-CODE');

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertOk()
        ->assertSee('Source binding information is not available yet')
        ->assertDontSee('Not linked');
});

it('denies every external role the Office overview and keeps binding metadata off its customer dashboard', function (PortalRoleIdentifier $role): void {
    config(['import-demo.enabled' => false]);

    $office = sourceBindingOffice();
    [$customer, $site] = sourceBindingSite('Customer Visible Site');
    ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => 'SAFE-PLOT']);
    activateSourceBinding($office, $customer, $site, 'CUSTOMER_CODE', 'PRIVATE-CUSTOMER-CODE');

    $external = User::factory()->role($role)->create(['customer_organisation_id' => $customer->id]);
    $external->assignedSites()->attach($site);

    $this->actingAs($external)
        ->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertForbidden();

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'source']))
        ->assertForbidden();

    $this->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))
        ->assertOk()
        ->assertSee('SAFE-PLOT')
        ->assertDontSee('PRIVATE-CUSTOMER-CODE')
        ->assertDontSee('Source Binding');
})->with(PortalRoleIdentifier::siteRoles());
