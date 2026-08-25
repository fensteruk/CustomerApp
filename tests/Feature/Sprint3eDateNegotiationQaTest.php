<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows any active Office Staff user to agree a request across customer organisations', function (): void {
    [, , $request] = sprint3eQaRequest();
    $otherOrganisationOffice = sprint3eQaUser(PortalRoleIdentifier::FensterOfficeStaff, CustomerOrganisation::factory()->create());

    $this->actingAs($otherOrganisationOffice)
        ->post(route('portal.review-requests.agree-requested-date', $request))
        ->assertRedirect(route('portal.review-requests.show', $request));

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->fresh()->agreed_date->toDateString())->toBe($request->requested_date->toDateString());
});

it('rejects cross-organisation customer attempts before a proposal can be accepted', function (): void {
    [, $office, $request] = sprint3eQaRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eQaWeekday(2));
    $attackerOrganisation = CustomerOrganisation::factory()->create();
    $attacker = sprint3eQaUser(PortalRoleIdentifier::SiteManager, $attackerOrganisation);
    $attackerSite = Site::factory()->create(['customer_organisation_id' => $attackerOrganisation->id]);
    $attacker->assignedSites()->attach($attackerSite);

    $this->actingAs($attacker)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $attackerSite->id])
        ->post(route('portal.call-offs.alternative-dates.accept', [$request, $proposal]))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingSiteUser)
        ->and($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse);
});

it('rejects a proposal UUID substituted from a different request without changing either request', function (): void {
    [$siteUser, $office, $firstRequest] = sprint3eQaRequest();
    [, , $secondRequest, $secondSite] = sprint3eQaRequest();
    $siteUser->assignedSites()->attach($secondSite);
    $foreignProposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $secondRequest, sprint3eQaWeekday(2));

    $this->actingAs($siteUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $firstRequest->batch->site_id])
        ->post(route('portal.call-offs.alternative-dates.accept', [$firstRequest, $foreignProposal]))
        ->assertRedirect(route('portal.call-offs.show', $firstRequest))
        ->assertSessionHasErrors('status');

    expect($firstRequest->fresh()->status)->toBe(CallOffRequestStatus::AwaitingFenster)
        ->and($secondRequest->fresh()->status)->toBe(CallOffRequestStatus::AwaitingSiteUser)
        ->and($foreignProposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse);
});

it('requires an explicit early-date acknowledgement through the HTTP endpoint', function (): void {
    [, $office, $request] = sprint3eQaRequest(early: true);

    $this->actingAs($office)
        ->post(route('portal.review-requests.agree-requested-date', $request), ['early_date_acknowledgement' => '0'])
        ->assertRedirect(route('portal.review-requests.show', $request))
        ->assertSessionHasErrors('early_date_acknowledgement');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingFenster);
});

it('keeps private alternative context out of customer notifications and request pages', function (): void {
    [$siteUser, $office, $request, $site] = sprint3eQaRequest();
    $internalReason = 'Capacity notes <script>must never be customer-visible</script>';
    app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eQaWeekday(2), 'Please consider this alternative.', $internalReason);

    $notification = PortalNotification::query()
        ->where('notifiable_user_id', $siteUser->id)
        ->where('type', PortalNotificationType::CallOffAlternativeProposed)
        ->firstOrFail();

    expect(json_encode($notification->toArray()))->not->toContain($internalReason);

    $this->actingAs($siteUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.call-offs.show', $request))
        ->assertOk()
        ->assertSee('Please consider this alternative.')
        ->assertDontSee($internalReason);
});

it('allows every active portal site role, and no inactive site user, to respond on an assigned site', function (): void {
    foreach ([
        PortalRoleIdentifier::SiteManager,
        PortalRoleIdentifier::AssistantSiteManager,
        PortalRoleIdentifier::FinishingForeman,
    ] as $role) {
        [, $office, $request, $site] = sprint3eQaRequest();
        $responder = sprint3eQaUser($role, $site->customerOrganisation);
        $responder->assignedSites()->attach($site);
        $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eQaWeekday(2));

        app(AcceptAlternativeCallOffDateAction::class)->handle($responder, $request->fresh(), $proposal);

        expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed);
    }

    [$inactiveUser, $office, $request, $site] = sprint3eQaRequest();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, sprint3eQaWeekday(2));
    $inactiveUser->update(['is_active' => false]);

    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($inactiveUser->fresh(), $request->fresh(), $proposal))
        ->toThrow(AuthorizationException::class)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::AwaitingSiteUser);
});

/** @return array{User, User, CallOffRequest, Site} */
function sprint3eQaRequest(bool $early = false): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = sprint3eQaUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = sprint3eQaUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $siteUser->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $service = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_present' => true,
    ]);
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $siteUser->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eQaWeekday(1),
    ]);
    $request = CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => sprint3eQaWeekday(1),
        'normal_earliest_date' => sprint3eQaWeekday(2),
        'is_early_date_exception' => $early,
        'early_date_reason' => $early ? 'A date earlier than normal was requested.' : null,
        'status' => CallOffRequestStatus::AwaitingFenster,
    ]);

    return [$siteUser, $office, $request, $site];
}

function sprint3eQaUser(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function sprint3eQaWeekday(int $weeks): string
{
    return CarbonImmutable::today()->addWeeks($weeks)->nextWeekday()->toDateString();
}
