<?php

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function modalCallOffFixture(): array
{
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $user->assignedSites()->attach($site);

    return [$user, $site];
}

function modalCallOffPlot(Site $site, string $reference, array $services = [CallOffServiceType::Windows]): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);
    foreach ($services as $service) {
        ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => $service]);
    }

    return $plot;
}

function modalCallOffPayload(array $plots, array $serviceDates, array $extra = []): array
{
    return ['plots' => collect($plots)->pluck('uuid')->all(), 'service_dates' => $serviceDates, 'customer_response' => 'Please arrange access.'] + $extra;
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00'));
});

it('offers one Submit action and an accessible modal without the old review wording', function (): void {
    [$user, $site] = modalCallOffFixture();
    modalCallOffPlot($site, 'Plot 591');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.call-offs.create'))->assertOk()
        ->assertSee('>Submit</span>', false)
        ->assertSee('<dialog', false)
        ->assertSee('Confirm call-off')
        ->assertSee('You are about to call off:')
        ->assertDontSee('Check selected combinations')
        ->assertDontSee('3. Check');
    $this->assertDatabaseCount('call_off_requests', 0);
});

it('previews one and multiple selected combinations without creating requests', function (): void {
    [$user, $site] = modalCallOffFixture();
    $first = modalCallOffPlot($site, 'Plot 591', [CallOffServiceType::Windows, CallOffServiceType::Cml]);
    $second = modalCallOffPlot($site, 'Plot 592', [CallOffServiceType::Windows, CallOffServiceType::Cml]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $first->id, 'product_code' => 'VS', 'quantity' => 2]);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $dates = ['windows' => '2026-11-02', 'cml' => '2026-11-03'];

    $single = $this->postJson(route('portal.call-offs.matrix'), modalCallOffPayload([$first], ['windows' => $dates['windows']]))->assertOk();
    $single->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.plot_reference', 'Plot 591')
        ->assertJsonPath('rows.0.service_label', 'Windows')->assertJsonPath('rows.0.requested_display', '2 November 2026')
        ->assertJsonPath('rows.0.products_summary', 'Vertical Slider × 2');
    $this->assertDatabaseCount('call_off_requests', 0);

    $multiple = $this->postJson(route('portal.call-offs.matrix'), modalCallOffPayload([$first, $second], $dates))->assertOk();
    $multiple->assertJsonCount(4, 'rows')->assertJsonPath('rows.3.plot_reference', 'Plot 592')
        ->assertJsonPath('rows.3.service_label', 'CML')->assertJsonPath('rows.3.requested_display', '3 November 2026');
    $this->assertDatabaseCount('call_off_requests', 0);
});

it('submits only after final confirmation and consumes the signed review once', function (): void {
    [$user, $site] = modalCallOffFixture();
    $plot = modalCallOffPlot($site, 'Plot 593');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $payload = modalCallOffPayload([$plot], ['windows' => '2026-11-02']);
    $preview = $this->postJson(route('portal.call-offs.matrix'), $payload)->assertOk()->json();
    $this->assertDatabaseCount('call_off_requests', 0);

    $review = $this->postJson(route('portal.call-offs.review'), $payload + ['preview_signature' => $preview['preview_signature']])->assertOk()->json();
    $this->assertDatabaseCount('call_off_requests', 0);
    $this->postJson(route('portal.call-offs.store'), ['confirmation_signature' => $review['confirmation_signature']])
        ->assertOk()->assertJsonPath('redirect', route('portal.site-dashboard'))
        ->assertJsonPath('status', 'Call-off submitted for 1 plot / 1 service request.');
    $this->assertDatabaseCount('call_off_requests', 1);
    $this->postJson(route('portal.call-offs.store'), ['confirmation_signature' => $review['confirmation_signature']])
        ->assertUnprocessable()->assertJsonValidationErrors('request');
    $this->assertDatabaseCount('call_off_requests', 1);
});

it('preserves per-combination exclusion when preparing a multiple call-off', function (): void {
    [$user, $site] = modalCallOffFixture();
    $first = modalCallOffPlot($site, 'Plot 601');
    $second = modalCallOffPlot($site, 'Plot 602');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $payload = modalCallOffPayload([$first, $second], ['windows' => '2026-11-02']);
    $preview = $this->postJson(route('portal.call-offs.matrix'), $payload)->assertOk()->json();
    $excluded = $second->uuid.'|windows';
    $review = $this->postJson(route('portal.call-offs.review'), $payload + [
        'preview_signature' => $preview['preview_signature'], 'excluded' => [$excluded],
    ])->assertOk()->json();
    $this->postJson(route('portal.call-offs.store'), ['confirmation_signature' => $review['confirmation_signature']])->assertOk();
    expect(CallOffRequest::query()->pluck('projected_plot_id')->all())->toBe([$first->id]);
});

it('returns the early-date shortfall and reason context before confirmation', function (): void {
    [$user, $site] = modalCallOffFixture();
    $plot = modalCallOffPlot($site, 'Plot 591', []);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $payload = modalCallOffPayload([$plot], ['cavity_closers' => '2026-10-09'], ['cavity_early_reason' => 'Programme brought forward']);
    $preview = $this->postJson(route('portal.call-offs.matrix'), $payload)->assertOk()
        ->assertJsonPath('rows.0.is_early_exception', true)
        ->assertJsonPath('rows.0.earliest_display', '12 October 2026')
        ->assertJsonPath('rows.0.working_days_early', 1)->json();
    $this->postJson(route('portal.call-offs.review'), $payload + ['preview_signature' => $preview['preview_signature']])
        ->assertUnprocessable()->assertJsonValidationErrors('early_reasons.'.$plot->uuid.'|cavity_closers');
    $this->postJson(route('portal.call-offs.review'), $payload + [
        'preview_signature' => $preview['preview_signature'],
        'early_reasons' => [$plot->uuid.'|cavity_closers' => 'Programme brought forward'],
    ])->assertOk();
    $this->assertDatabaseCount('call_off_requests', 0);
});

it('rejects an edited source fact between modal opening and confirmation', function (): void {
    [$user, $site] = modalCallOffFixture();
    $plot = modalCallOffPlot($site, 'Plot 603');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $payload = modalCallOffPayload([$plot], ['windows' => '2026-11-02']);
    $preview = $this->postJson(route('portal.call-offs.matrix'), $payload)->assertOk()->json();
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'VS', 'quantity' => 3]);
    $this->postJson(route('portal.call-offs.review'), $payload + ['preview_signature' => $preview['preview_signature']])
        ->assertUnprocessable()->assertJsonValidationErrors('request');
    $this->assertDatabaseCount('call_off_requests', 0);
});

it('rechecks current source eligibility after review before creating a request', function (): void {
    [$user, $site] = modalCallOffFixture();
    $plot = modalCallOffPlot($site, 'Plot 604');
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $payload = modalCallOffPayload([$plot], ['windows' => '2026-11-02']);
    $preview = $this->postJson(route('portal.call-offs.matrix'), $payload)->assertOk()->json();
    $review = $this->postJson(route('portal.call-offs.review'), $payload + ['preview_signature' => $preview['preview_signature']])->assertOk()->json();
    $plot->services()->where('service_identifier', CallOffServiceType::Windows->value)->update(['source_present' => false]);
    $this->postJson(route('portal.call-offs.store'), ['confirmation_signature' => $review['confirmation_signature']])
        ->assertUnprocessable()->assertJsonValidationErrors('request');
    $this->assertDatabaseCount('call_off_requests', 0);
});

it('denies Office and cross-site users at the modal preview and final review endpoints', function (): void {
    [$user, $site] = modalCallOffFixture();
    $plot = modalCallOffPlot($site, 'Plot 605');
    [$otherUser, $otherSite] = modalCallOffFixture();
    $payload = modalCallOffPayload([$plot], ['windows' => '2026-11-02']);
    $this->actingAs($otherUser)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $otherSite->id])
        ->postJson(route('portal.call-offs.matrix'), $payload)->assertUnprocessable();
    $this->postJson(route('portal.call-offs.review'), $payload)->assertUnprocessable();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $this->actingAs($office)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->postJson(route('portal.call-offs.matrix'), $payload)->assertForbidden();
    $this->postJson(route('portal.call-offs.review'), $payload)->assertForbidden();
    $this->assertDatabaseCount('call_off_requests', 0);
});
