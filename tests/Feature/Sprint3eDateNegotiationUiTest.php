<?php

use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the office actions and early-date acknowledgement only while awaiting Fenster', function (): void {
    [, $office, $request, $site] = sprint3eUiRequest(early: true);

    $this->actingAs($office)
        ->get(route('portal.review-requests.show', $request))
        ->assertOk()
        ->assertSee('Accept Requested Date')
        ->assertSee('Propose Alternative Date')
        ->assertSee('Normal earliest date')
        ->assertSee('Customer reason')
        ->assertSee('name="early_date_acknowledgement"', false)
        ->assertSee(route('portal.review-requests.agree-requested-date', $request), false);
});

it('shows every current assigned site user the alternative-date response action without internal context', function (): void {
    [$submitter, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle(
        $office,
        $request,
        sprint3eUiWeekday(2),
        'The fitting team can attend on this date.',
        'Internal-only capacity discussion.',
    );

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request))
        ->assertOk()
        ->assertSee('Fenster has proposed an alternative date')
        ->assertSee('currently assigned site user needs to accept or reject')
        ->assertSee('Accepting this will make')
        ->assertSee('Accept Date')
        ->assertSee('Reject Date')
        ->assertSee('Reason for rejecting this date')
        ->assertSee($proposal->proposed_date->format('j M Y'))
        ->assertSee('The fitting team can attend on this date.')
        ->assertSee(route('portal.call-offs.alternative-dates.accept', [$request, $proposal]), false)
        ->assertSee(route('portal.call-offs.alternative-dates.reject', [$request, $proposal]), false)
        ->assertDontSee('Internal-only capacity discussion.');
});

it('keeps the customer on the useful request screen after accepting an alternative date', function (): void {
    [, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eUiWeekday(2));

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->post(route('portal.call-offs.alternative-dates.accept', [$request, $proposal]))
        ->assertRedirect(route('portal.call-offs.show', $request));

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request->fresh()))
        ->assertOk()
        ->assertSee('Date Agreed')
        ->assertSee($proposal->proposed_date->format('j M Y'))
        ->assertDontSee('Accept Date')
        ->assertDontSee('Reject Date')
        ->assertDontSee('Withdraw request');
});

it('requires a rejection reason and preserves the responder in the customer-safe history', function (): void {
    [, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eUiWeekday(2));

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->post(route('portal.call-offs.alternative-dates.reject', [$request, $proposal]), [])
        ->assertSessionHasErrors('customer_response');

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->post(route('portal.call-offs.alternative-dates.reject', [$request, $proposal]), ['customer_response' => 'The crane is unavailable.'])
        ->assertRedirect(route('portal.call-offs.show', $request));

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request->fresh()))
        ->assertOk()
        ->assertSee('Fenster is reviewing your requested date')
        ->assertSee('The crane is unavailable.')
        ->assertSee($secondSiteUser->name)
        ->assertSee('Withdraw request');
});

it('shows customer-safe repeated proposal history and the customer response in the office detail', function (): void {
    [$submitter, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    $propose = app(ProposeAlternativeCallOffDateAction::class);
    $first = $propose->handle($office, $request, sprint3eUiWeekday(2));
    $this->actingAs($secondSiteUser)->withSession(['active_site_id' => $site->id])->post(route('portal.call-offs.alternative-dates.reject', [$request, $first]), ['customer_response' => 'Crane unavailable.']);
    $second = $propose->handle($office, $request->fresh(), sprint3eUiWeekday(3));

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request->fresh()))
        ->assertOk()
        ->assertSee('Fenster proposed '.$first->proposed_date->format('j M Y'))
        ->assertSee('Crane unavailable.')
        ->assertSee('Fenster proposed '.$second->proposed_date->format('j M Y'));

    $this->actingAs($office)
        ->get(route('portal.review-requests.show', $request->fresh()))
        ->assertOk()
        ->assertSee('Alternative date history')
        ->assertSee('Customer rejected this date.')
        ->assertSee('Crane unavailable.')
        ->assertSee('Waiting for customer response');
});

it('keeps legacy approved and rejected requests distinct in the customer view', function (): void {
    [$submitter, , $approved, $site] = sprint3eUiRequest(status: CallOffRequestStatus::Approved);
    [, , $rejected] = sprint3eUiRequest(site: $site, status: CallOffRequestStatus::Rejected);

    $this->actingAs($submitter)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $approved))
        ->assertOk()
        ->assertSee('Date Agreed')
        ->assertDontSee('Accept Date')
        ->assertDontSee('Reject Date');

    $this->actingAs($submitter)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $rejected))
        ->assertOk()
        ->assertSee('Rejected')
        ->assertDontSee('Customer rejected this date.');
});

it('removes stale negotiation actions when source completion is visible', function (): void {
    [, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eUiWeekday(2));
    $request->projectedPlotService->update(['source_completed_at' => today()]);
    $request->update(['status' => CallOffRequestStatus::Completed]);

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request->fresh()))
        ->assertOk()
        ->assertSee('Completed')
        ->assertDontSee('Accept Date')
        ->assertDontSee('Reject Date')
        ->assertDontSee('Withdraw request');
});

it('does not present alternative response controls when the source service is unavailable', function (): void {
    [, $office, $request, $site, $secondSiteUser] = sprint3eUiRequest();
    app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eUiWeekday(2));
    $request->projectedPlotService->update(['source_present' => false]);

    $this->actingAs($secondSiteUser)
        ->withSession(['active_site_id' => $site->id])
        ->get(route('portal.call-offs.show', $request->fresh()))
        ->assertOk()
        ->assertDontSee('Accept Date')
        ->assertDontSee('Reject Date');
});

it('uses the detailed authorised request context when opening a site-user notification', function (): void {
    [$submitter, $office, $request, $site] = sprint3eUiRequest();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
    $notification = $submitter->portalNotifications()->firstOrFail();

    $this->actingAs($submitter)
        ->get(route('portal.notifications.open', $notification))
        ->assertRedirect(route('portal.call-offs.show', $request));
});

/** @return array{User, User, CallOffRequest, Site, User} */
function sprint3eUiRequest(?Site $site = null, CallOffRequestStatus $status = CallOffRequestStatus::AwaitingFenster, bool $early = false): array
{
    $organisation = $site?->customerOrganisation ?? CustomerOrganisation::factory()->create();
    $submitter = sprint3eUiUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = sprint3eUiUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $secondSiteUser = sprint3eUiUser(PortalRoleIdentifier::AssistantSiteManager, $organisation);
    $site ??= Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $submitter->assignedSites()->attach($site);
    $secondSiteUser->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $service = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_present' => true,
    ]);
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $submitter->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eUiWeekday(1),
    ]);
    $request = CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eUiWeekday(1),
        'normal_earliest_date' => sprint3eUiWeekday(2),
        'is_early_date_exception' => $early,
        'early_date_reason' => $early ? 'Customer needs an earlier installation.' : null,
        'agreed_date' => $status === CallOffRequestStatus::Approved ? sprint3eUiWeekday(1) : null,
        'status' => $status,
    ]);

    return [$submitter, $office, $request, $site, $secondSiteUser];
}

function sprint3eUiUser(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function sprint3eUiWeekday(int $weeks): string
{
    return CarbonImmutable::today()->addWeeks($weeks)->nextWeekday()->toDateString();
}
