<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\OfficeCustomerWorkspaceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function customerWorkspaceOffice(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
}

function customerWorkspaceRequest(User $actor, Site $site, ProjectedPlot $plot, CallOffRequestStatus $status, bool $trashed = false): void
{
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $actor->id]);
    CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id,
        'status' => $status, 'trashed_at' => $trashed ? now() : null,
    ]);
}

test('customer cards and workspace totals count real contained data and bounded attention', function (): void {
    $office = customerWorkspaceOffice();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Alder Homes']);
    $inactiveCustomer = CustomerOrganisation::factory()->create(['name' => 'Dormant Homes', 'is_active' => false]);
    CustomerOrganisation::factory()->create(['name' => 'Empty Homes']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $inactiveSite = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => false]);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $inactiveCustomer->id]);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $inactivePlot = ProjectedPlot::factory()->create(['site_id' => $inactiveSite->id]);
    $otherPlot = ProjectedPlot::factory()->create(['site_id' => $otherSite->id]);
    customerWorkspaceRequest($office, $site, $plot, CallOffRequestStatus::AwaitingFenster);
    customerWorkspaceRequest($office, $site, $plot, CallOffRequestStatus::AwaitingSiteUser);
    customerWorkspaceRequest($office, $site, $plot, CallOffRequestStatus::DateAgreed);
    customerWorkspaceRequest($office, $site, $plot, CallOffRequestStatus::AwaitingFenster, true);
    customerWorkspaceRequest($office, $inactiveSite, $inactivePlot, CallOffRequestStatus::AwaitingFenster);
    customerWorkspaceRequest($office, $otherSite, $otherPlot, CallOffRequestStatus::AwaitingFenster);

    $response = $this->actingAs($office)->get(route('office.workspace.customers.index'))->assertOk();
    expect($response->viewData('summary'))->toBe(['customers' => 3, 'sites' => 3, 'active_sites' => 2]);
    $cards = $response->viewData('customers')->keyBy('uuid');
    expect((int) $cards[$customer->uuid]['site_count'])->toBe(2)
        ->and((int) $cards[$customer->uuid]['active_site_count'])->toBe(1)
        ->and((int) $cards[$customer->uuid]['plot_count'])->toBe(2)
        ->and((int) $cards[$customer->uuid]['attention_count'])->toBe(1)
        ->and((int) $cards[$inactiveCustomer->uuid]['plot_count'])->toBe(1)
        ->and((int) $cards[$inactiveCustomer->uuid]['attention_count'])->toBe(0);
    $response->assertSee('Need attention')->assertSee('requests awaiting a Fenster response')
        ->assertSee('bg-amber-50', false)->assertSee('Inactive');
});

test('empty cards use valid separate add-site and whole-card links without competing edit links', function (): void {
    $office = customerWorkspaceOffice();
    $active = CustomerOrganisation::factory()->create(['name' => 'New Homes']);
    $inactive = CustomerOrganisation::factory()->create(['name' => 'Old Homes', 'is_active' => false]);
    $response = $this->actingAs($office)->get(route('office.workspace.customers.index'))->assertOk()
        ->assertSee('No sites yet')->assertSee('Open customer New Homes')
        ->assertSee(route('office.workspace.customers.show', $active), false)
        ->assertSee(route('office.workspace.sites.create', $active), false)
        ->assertDontSee(route('office.workspace.sites.create', $inactive), false)
        ->assertDontSee(route('office.workspace.customers.edit', $active), false)
        ->assertSee('after:inset-0', false)->assertSee('focus-visible:after:ring-2', false)
        ->assertSee('md:grid-cols-2 xl:grid-cols-3', false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//a//a')->length)->toBe(0)
        ->and($xpath->query('//aside//form[@aria-label="Customer filters"]')->length)->toBe(0)
        ->and($xpath->query('//section[@data-customer-workspace]//form[@aria-label="Customer filters"]')->length)->toBe(1);
    $this->get(route('office.workspace.customers.show', $active))->assertOk()
        ->assertSee(route('office.workspace.customers.edit', $active), false);
});

test('customer search status and alphabetical sort persist through pagination while totals remain global', function (): void {
    $office = customerWorkspaceOffice();
    foreach (range(1, 20) as $number) {
        CustomerOrganisation::factory()->create(['name' => sprintf('Match %02d', $number)]);
    }
    CustomerOrganisation::factory()->create(['name' => 'Match inactive', 'is_active' => false]);
    CustomerOrganisation::factory()->create(['name' => 'Different']);
    $response = $this->actingAs($office)->get(route('office.workspace.customers.index', [
        'search' => 'Match', 'active' => '1', 'sort' => 'name_desc',
    ]))->assertOk()->assertSee('20 customers found')->assertSee('page=2')
        ->assertSee('sort=name_desc')->assertSee('search=Match')->assertSee('active=1')
        ->assertDontSee('Match inactive')->assertDontSee('Different');
    expect($response->viewData('customers')->first()->name)->toBe('Match 20')
        ->and($response->viewData('summary')['customers'])->toBe(22);
    $this->get(route('office.workspace.customers.index', ['active' => '0']))
        ->assertOk()->assertSee('Match inactive')->assertDontSee('Match 20');
    $this->get(route('office.workspace.customers.index', ['search' => 'Missing']))
        ->assertOk()->assertSee('No matching customers')->assertSee('Clear filters');
    $this->get(route('office.workspace.customers.index', ['sort' => 'invalid']))
        ->assertRedirect()->assertSessionHasErrors('sort');
});

test('the workspace explains its first-customer state', function (): void {
    $this->actingAs(customerWorkspaceOffice())->get(route('office.workspace.customers.index'))
        ->assertOk()->assertSee('0 customers')->assertSee('No customers yet')
        ->assertSee(route('office.workspace.customers.create'), false);
});

test('customer workspace rejects external users', function (PortalRoleIdentifier $role): void {
    $user = User::factory()->role($role)->create();
    $this->actingAs($user)->get(route('office.workspace.customers.index'))->assertForbidden();
})->with([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman]);

test('customer workspace denies guests inactive Office and preview Office users', function (): void {
    $this->get(route('office.workspace.customers.index'))->assertRedirect(route('login'));
    $inactive = customerWorkspaceOffice();
    $inactive->forceFill(['is_active' => false])->save();
    $this->actingAs($inactive)->get(route('office.workspace.customers.index'))->assertRedirect(route('login'));
    $preview = customerWorkspaceOffice();
    $preview->forceFill(['is_preview_user' => true])->save();
    $this->actingAs($preview)->get(route('office.workspace.customers.index'))->assertForbidden();
});

test('workspace read query count does not grow with customer cards', function (): void {
    $office = customerWorkspaceOffice();
    CustomerOrganisation::factory()->create();
    $queries = app(OfficeCustomerWorkspaceQueryService::class);
    DB::enableQueryLog();
    $queries->index($office, '', null, 'name_asc');
    $first = count(DB::getQueryLog());
    DB::disableQueryLog();
    CustomerOrganisation::factory()->count(10)->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $queries->index($office, '', null, 'name_asc');
    expect(count(DB::getQueryLog()))->toBe($first);
    DB::disableQueryLog();
});
