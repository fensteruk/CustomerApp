<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\CustomerDetailWorkspaceQueryService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function detailOffice(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null, 'is_preview_user' => false]);
}

function detailRequest(Site $site, CallOffRequestStatus $status = CallOffRequestStatus::AwaitingFenster): CallOffRequest
{
    return CallOffRequest::factory()->create([
        'call_off_batch_id' => CallOffBatch::factory()->create(['site_id' => $site->id])->id,
        'projected_plot_id' => ProjectedPlot::factory()->create(['site_id' => $site->id])->id,
        'status' => $status,
    ]);
}

test('customer detail shows contained totals sites and safe large card navigation', function () {
    $customer = CustomerOrganisation::factory()->create(['name' => '<script>Homes</script>']);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Alder Park']);
    Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => false]);
    $request = detailRequest($site);
    $other = Site::factory()->create(['name' => 'Hidden Other Site']);
    detailRequest($other);
    $before = $request->fresh()->getAttributes();
    $response = $this->actingAs(detailOffice())->get(route('office.workspace.customers.show', $customer))
        ->assertOk()->assertSee('&lt;script&gt;Homes&lt;/script&gt;', false)->assertDontSee('<script>Homes</script>', false)
        ->assertSee('Alder Park')->assertDontSee('Hidden Other Site')->assertSee('Open site')
        ->assertSee(route('office.workspace.sites.create', $customer), false)
        ->assertSee(route('portal.review-requests.show', $request), false);
    expect($response->viewData('customer')['site_count'])->toBe(2)
        ->and($response->viewData('customer')['active_site_count'])->toBe(1)
        ->and($response->viewData('plotCount'))->toBe(1)
        ->and($response->viewData('attentionCount'))->toBe(1)
        ->and($request->fresh()->getAttributes())->toBe($before);
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//a//a')->length)->toBe(0)
        ->and($xpath->query('//aside//form[@aria-label="Site filters"]')->length)->toBe(0)
        ->and($xpath->query('//div[@data-customer-detail]//form[@aria-label="Site filters"]')->length)->toBe(1);
});

test('empty and inactive customers retain guidance without invalid add site action', function (bool $active) {
    $customer = CustomerOrganisation::factory()->create(['is_active' => $active]);
    $response = $this->actingAs(detailOffice())->get(route('office.workspace.customers.show', $customer))->assertOk()
        ->assertSee('No sites yet')->assertSee('No users belong')->assertSee('Customer administration')
        ->assertSee(route('office.workspace.customers.delete-preview', $customer), false)
        ->assertSee(route('office.workspace.customers.demo-purge-preview', $customer), false);
    expect($response->viewData('plotCount'))->toBe(0)->and($response->viewData('attentionCount'))->toBe(0);
    if ($active) {
        $response->assertSee(route('office.workspace.sites.create', $customer), false);
    } else {
        $response->assertDontSee(route('office.workspace.sites.create', $customer), false)->assertSee('Reactivate the customer before adding a site');
    }
})->with([true, false]);

test('customer users show bounded roles assignments and truthful access attention', function () {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id, 'name' => 'Assigned Person']);
    $user->assignedSites()->attach($site);
    User::factory()->role(PortalRoleIdentifier::FinishingForeman)->create(['customer_organisation_id' => $customer->id, 'name' => 'Unassigned Person']);
    User::factory()->create(['name' => 'Other Customer Person']);
    $this->actingAs(detailOffice())->get(route('office.workspace.customers.show', $customer))->assertOk()
        ->assertSee('Assigned Person')->assertSee('Site access assigned')->assertSee('No sites assigned')
        ->assertSee('Finishing Foreman')->assertSee('1 assigned site')->assertDontSee('Other Customer Person')
        ->assertSee(route('office.workspace.users.index', ['customer' => $customer->uuid]), false);
    $site->forceFill(['is_active' => false])->save();
    $this->get(route('office.workspace.customers.show', $customer))->assertSee('No active assigned sites');
});

test('customer attention uses current amendment response and excludes completed trashed and mismatched records', function () {
    $office = detailOffice();
    $site = Site::factory()->create();
    $request = detailRequest($site, CallOffRequestStatus::AmendmentOnHold);
    $cycle = $request->dateNegotiations()->create(['purpose' => 'amendment', 'status' => 'open', 'requested_date' => today()->addMonth(), 'opened_at' => now()]);
    $trashed = detailRequest($site);
    $trashed->update(['trashed_at' => now()]);
    detailRequest($site, CallOffRequestStatus::AwaitingSiteUser);
    $mismatch = detailRequest(Site::factory()->create());
    $mismatch->batch->update(['site_id' => $site->id]);
    $completed = detailRequest($site);
    $service = $completed->projectedPlot->services()->create(['service_identifier' => 'windows', 'source_present' => true, 'source_completion_observed_at' => now()]);
    $completed->update(['projected_plot_service_id' => $service->id]);
    $data = fn () => app(CustomerDetailWorkspaceQueryService::class)->workspace($office, $site->customerOrganisation, ['search' => '', 'active' => null]);
    expect($data()['attentionCount'])->toBe(1)->and($data()['sites'][0]['attention_count'])->toBe(1);
    $cycle->proposals()->create(['sequence' => 1, 'proposal_type' => 'fenster_alternative_date', 'status' => 'awaiting_response', 'proposed_date' => today()->addMonth(), 'proposed_by_user_id' => $office->id, 'proposed_at' => now()]);
    expect($data()['attentionCount'])->toBe(0);
});

test('customer site filters paginate while summary stays customer wide with bounded query growth', function () {
    $office = detailOffice();
    $customer = CustomerOrganisation::factory()->create();
    Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Find me']);
    $this->actingAs($office);
    DB::enableQueryLog();
    $this->get(route('office.workspace.customers.show', $customer))->assertOk();
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();
    Site::factory()->count(22)->sequence(fn (Sequence $sequence) => ['name' => 'Find me also '.$sequence->index])->create(['customer_organisation_id' => $customer->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->get(route('office.workspace.customers.show', [$customer, 'search' => 'Find', 'active' => '1']))->assertOk()->assertSee('page=2', false);
    expect($response->viewData('sites')->count())->toBe(20)
        ->and($response->viewData('customer')['site_count'])->toBe(23)
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual($small + 2);
    DB::disableQueryLog();
    $this->get(route('office.workspace.customers.show', [$customer, 'search' => 'Missing']))->assertSee('No matching sites');
});

test('external roles cannot access any customer detail', function (PortalRoleIdentifier $role) {
    $customer = CustomerOrganisation::factory()->create();
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $customer->id]);
    $this->actingAs($user)->get(route('office.workspace.customers.show', $customer))->assertForbidden();
    $this->get(route('office.workspace.customers.show', CustomerOrganisation::factory()->create()))->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());

test('inactive office and guests cannot access customer detail', function () {
    $customer = CustomerOrganisation::factory()->create();
    $this->get(route('office.workspace.customers.show', $customer))->assertRedirect(route('login'));
    $office = detailOffice();
    $office->forceFill(['is_active' => false])->save();
    $this->actingAs($office)->get(route('office.workspace.customers.show', $customer))->assertRedirect(route('login'));
    $this->assertGuest();
});
