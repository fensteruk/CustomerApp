<?php

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffLeadTimeService;
use App\Services\CallOffSubmissionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cavityFixture(): array
{
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $manager = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $manager->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);

    return [$manager, $site, $plot];
}

function cavityPayload(ProjectedPlot $plot, string $date, array $extra = []): array
{
    return array_merge(['plots' => [$plot->uuid], 'service_dates' => [CallOffServiceType::CavityClosers->value => $date]], $extra);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00'));
});

test('fifteen working days and early shortfall exclude weekends at exact boundaries', function (): void {
    $lead = app(CallOffLeadTimeService::class);
    $earliest = $lead->earliestCavityCloserDate();
    expect($earliest->toDateString())->toBe('2026-10-12')
        ->and($lead->workingDaysEarly(CarbonImmutable::parse('2026-10-09'), $earliest))->toBe(1)
        ->and($lead->workingDaysEarly(CarbonImmutable::parse('2026-09-22'), $earliest))->toBe(14)
        ->and($lead->workingDaysEarly($earliest, $earliest))->toBe(0);
});

test('source-free cavity closers appear callable while other services remain source-gated', function (): void {
    [$manager, $site, $plot] = cavityFixture();
    $rows = app(BuildCallOffMatrixAction::class)->handle($manager, $site, [$plot->uuid], [
        CallOffServiceType::CavityClosers->value => '2026-10-12',
        CallOffServiceType::Windows->value => '2026-11-02',
    ]);
    expect($rows[0]['available'])->toBeTrue()->and($rows[0]['plot_service_id'])->toBeNull()
        ->and($rows[0]['normal_earliest_date'])->toBe('2026-10-12')
        ->and($rows[1]['available'])->toBeFalse();

    ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::CavityClosers, 'source_present' => false]);
    $rows = app(BuildCallOffMatrixAction::class)->handle($manager, $site, [$plot->uuid], [CallOffServiceType::CavityClosers->value => '2026-10-12']);
    expect($rows[0]['available'])->toBeTrue();
    expect(app(CallOffLeadTimeService::class)->earliestAmendmentDate($plot->services()->firstOrFail())->toDateString())->toBe('2026-10-19');
});

test('early date demands reason on server and explains the same working-day count', function (): void {
    [$manager, $site, $plot] = cavityFixture();
    $this->actingAs($manager)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->get(route('portal.call-offs.create'))->assertOk()->assertSee('15 working days')->assertSee('Monday 12 October 2026');
    $this->getJson(route('portal.call-offs.cavity-closer-date', ['date' => '2026-10-09']))
        ->assertOk()->assertJsonPath('working_days_early', 1)->assertJsonPath('is_early', true);
    $this->post(route('portal.call-offs.matrix'), cavityPayload($plot, '2026-10-09'))
        ->assertRedirect(route('portal.call-offs.create'))->assertSessionHasErrors('cavity_early_reason');
    $this->post(route('portal.call-offs.matrix'), cavityPayload($plot, '2026-10-09', ['cavity_early_reason' => 'Access window']))
        ->assertOk()->assertSee('1 working day early')->assertSee('Access window');
    $this->post(route('portal.call-offs.review'), cavityPayload($plot, '2026-10-09'))
        ->assertRedirect(route('portal.call-offs.create'))->assertSessionHasErrors();
});

test('normal and beyond-standard dates need no reason while fourteen and one working day dates do', function (): void {
    [$manager, $site, $plot] = cavityFixture();
    $this->actingAs($manager)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        $this->post(route('portal.call-offs.review'), cavityPayload($plot, $date))->assertOk();
    }
    foreach (['2026-10-09', '2026-09-22'] as $date) {
        $this->post(route('portal.call-offs.review'), cavityPayload($plot, $date))
            ->assertRedirect(route('portal.call-offs.create'))->assertSessionHasErrors();
    }
});

test('early request persists source-free service, office context and immutable history, and blocks duplicate', function (): void {
    [$manager, $site, $plot] = cavityFixture();
    $this->actingAs($manager)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $key = $plot->uuid.'|'.CallOffServiceType::CavityClosers->value;
    $payload = cavityPayload($plot, '2026-10-09', ['early_reasons' => [$key => 'Crane access is available only then.']]);
    $this->post(route('portal.call-offs.review'), $payload)->assertOk()->assertSee('1 working day early');
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])->assertRedirect(route('portal.site-dashboard'));
    $request = CallOffRequest::query()->firstOrFail();
    expect($request->projectedPlotService->source_present)->toBeFalse()
        ->and($request->normal_earliest_date->toDateString())->toBe('2026-10-12')
        ->and($request->early_date_reason)->toBe('Crane access is available only then.');
    $history = $request->histories()->firstOrFail();
    expect($history->after_state['lead_time_working_days'])->toBe(15)
        ->and($history->after_state['working_days_early'])->toBe(1)
        ->and($history->after_state['early_date_reason'])->toBe($request->early_date_reason)
        ->and($history->performed_by_user_id)->toBe($manager->id)
        ->and($history->performed_at)->not->toBeNull();

    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $this->actingAs($office)->get(route('portal.review-requests.show', $request))
        ->assertOk()->assertSee('1 working day early')->assertSee('Crane access is available only then.')
        ->assertSee('Friday 9 October 2026')->assertSee('Monday 12 October 2026');
    $this->post(route('portal.review-requests.agree-requested-date', $request))
        ->assertSessionHasErrors('early_date_acknowledgement');
    $this->post(route('portal.review-requests.agree-requested-date', $request), ['early_date_acknowledgement' => '1'])
        ->assertRedirect(route('portal.review-requests.show', $request));
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed);
    $rows = app(BuildCallOffMatrixAction::class)->handle($manager, $site, [$plot->uuid], [CallOffServiceType::CavityClosers->value => '2026-10-12']);
    expect($rows[0]['available'])->toBeFalse();
});

test('another tenant and Office cannot use the cavity date or submit paths', function (): void {
    [$manager, $site, $plot] = cavityFixture();
    [$other, $otherSite] = cavityFixture();
    $this->actingAs($other)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $otherSite->id]);
    $this->post(route('portal.call-offs.matrix'), cavityPayload($plot, '2026-10-12'))->assertRedirect()->assertSessionHasErrors();
    $this->getJson(route('portal.call-offs.cavity-closer-date', ['date' => '2026-10-09']))->assertOk();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $this->actingAs($office)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->getJson(route('portal.call-offs.cavity-closer-date', ['date' => '2026-10-09']))->assertForbidden();
    $this->post(route('portal.call-offs.review'), cavityPayload($plot, '2026-10-12'))->assertForbidden();
});
