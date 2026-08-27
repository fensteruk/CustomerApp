<?php

use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Contracts\HolidayProvider;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffLeadTimeService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('the service catalogue includes the four stable customer-facing services', function (): void {
    expect(CallOffServiceType::cases())->toHaveCount(4)
        ->and(CallOffServiceType::Snagging->label())->toBe('Snagging');
});

test('legacy three-service requests remain readable through their batch values', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $user->id,
        'service_identifier' => CallOffServiceType::Windows,
    ]);
    $request = CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'service_identifier' => null,
        'requested_date' => null,
        'status' => CallOffRequestStatus::Approved,
    ]);

    expect($request->fresh()->effectiveServiceIdentifier())->toBe(CallOffServiceType::Windows)
        ->and($request->fresh()->isLegacyDateAgreed())->toBeTrue();
});

test('a projected plot has one independently addressable row per service', function (): void {
    [, , $plot] = sprint3aSiteUser();

    foreach (CallOffServiceType::cases() as $service) {
        ProjectedPlotService::create([
            'projected_plot_id' => $plot->id,
            'service_identifier' => $service,
        ]);
    }

    expect($plot->services()->get()->map(fn (ProjectedPlotService $service) => $service->service_identifier->value)->all())
        ->toEqualCanonicalizing(array_map(fn (CallOffServiceType $service) => $service->value, CallOffServiceType::cases()));
});

test('request-level fields support mixed services and dates inside one submission batch', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);
    $windows = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    $snagging = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Snagging]);

    CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id, 'projected_plot_service_id' => $windows->id,
        'service_identifier' => CallOffServiceType::Windows, 'requested_date' => '2026-10-01',
    ]);
    CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id, 'projected_plot_service_id' => $snagging->id,
        'service_identifier' => CallOffServiceType::Snagging, 'requested_date' => '2026-10-15',
    ]);

    expect($batch->requests()->get()->map(fn (CallOffRequest $request) => $request->requested_date->toDateString())->all())
        ->toEqualCanonicalizing(['2026-10-01', '2026-10-15']);
});

test('date proposals are ordered append-only records on a negotiation', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $request = sprint3aRequest($user, $site, $plot);
    $negotiation = CallOffDateNegotiation::create([
        'call_off_request_id' => $request->id, 'purpose' => 'initial', 'status' => 'open',
        'active_negotiation_key' => "call_off_request:{$request->id}:initial", 'opened_at' => now(),
    ]);
    CallOffDateProposal::create([
        'call_off_date_negotiation_id' => $negotiation->id, 'sequence' => 1,
        'proposal_type' => CallOffDateProposalType::CustomerRequestedDate, 'status' => CallOffDateProposalStatus::AwaitingResponse,
        'proposed_date' => '2026-10-01', 'proposed_by_user_id' => $user->id, 'proposed_at' => now(),
    ]);

    expect($request->dateNegotiations()->firstOrFail()->proposals()->firstOrFail()->proposed_date->toDateString())->toBe('2026-10-01');
});

test('source completion is represented on the plot service and can be reversed without deleting the service', function (): void {
    [, , $plot] = sprint3aSiteUser();
    $service = ProjectedPlotService::create([
        'projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::CavityClosers,
        'source_job_stage' => 'CC08', 'source_completed_at' => '2026-08-20',
    ]);

    expect($service->isSourceCompleted())->toBeTrue();

    $service->update(['source_job_stage' => null, 'source_completed_at' => null]);

    expect($service->fresh()->isSourceCompleted())->toBeFalse();
});

test('the lead-time foundation calculates standard and BF dates without a holiday data source', function (): void {
    [, , $plot] = sprint3aSiteUser();
    $service = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    $calculator = app(CallOffLeadTimeService::class);
    $from = CarbonImmutable::parse('2026-08-17');

    expect($calculator->earliestNormalDate($service, $from)->toDateString())->toBe('2026-09-14');

    ProjectedPlotProduct::create(['projected_plot_id' => $plot->id, 'product_code' => 'BF-100', 'quantity' => 1]);

    expect($calculator->earliestNormalDate($service, $from)->toDateString())->toBe('2026-09-21')
        ->and($calculator->latestNormalDate($from)->toDateString())->toBe('2027-02-17');
});

test('zero-quantity BF products do not extend the lead time', function (): void {
    [, , $plot] = sprint3aSiteUser();
    $service = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    ProjectedPlotProduct::create(['projected_plot_id' => $plot->id, 'product_code' => 'BF-100', 'quantity' => 0]);

    expect(app(CallOffLeadTimeService::class)->earliestNormalDate($service, CarbonImmutable::parse('2026-08-17'))->toDateString())
        ->toBe('2026-09-14');
});

test('the lead-time foundation honours an injected holiday provider and normal date boundaries', function (): void {
    app()->instance(HolidayProvider::class, new class implements HolidayProvider
    {
        public function isHoliday(CarbonInterface $date): bool
        {
            return in_array($date->toDateString(), ['2026-09-14', '2026-09-15'], true);
        }
    });

    [, , $plot] = sprint3aSiteUser();
    $service = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    $calculator = app(CallOffLeadTimeService::class);
    $from = CarbonImmutable::parse('2026-08-17');

    expect($calculator->earliestNormalDate($service, $from)->toDateString())->toBe('2026-09-16')
        ->and($calculator->isWithinNormalWindow($service, CarbonImmutable::parse('2026-09-15'), $from))->toBeFalse()
        ->and($calculator->isWithinNormalWindow($service, CarbonImmutable::parse('2026-09-16'), $from))->toBeTrue()
        ->and($calculator->isWithinNormalWindow($service, CarbonImmutable::parse('2027-02-17'), $from))->toBeTrue()
        ->and($calculator->isWithinNormalWindow($service, CarbonImmutable::parse('2027-02-18'), $from))->toBeFalse();
});

test('Fenster Office Staff have global review access while Site Users stay restricted to assigned sites', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $otherOrganisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $otherOrganisation->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);
    $siteUser = sprint3aUser(PortalRoleIdentifier::SiteManager, $organisation);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $request = sprint3aRequest($siteUser, $site, $plot);

    expect($office->hasCompletePortalProfile())->toBeTrue()
        ->and(Gate::forUser($office)->allows('review-call-off', $request))->toBeTrue()
        ->and(Gate::forUser($office)->allows('manage-portal-accounts'))->toBeTrue()
        ->and(Gate::forUser($office)->allows('manage-site-assignments'))->toBeTrue()
        ->and(Gate::forUser($office)->allows('view-projected-plot', $plot))->toBeTrue()
        ->and($siteUser->canAccessSite($site))->toBeFalse();
});

test('the three external role identifiers share the Site User permission group', function (PortalRoleIdentifier $role): void {
    expect($role->isSiteRole())->toBeTrue();
})->with(PortalRoleIdentifier::siteRoles());

test('legacy histories remain immutable and rejected resubmission lineage is preserved', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $rejected = sprint3aRequest($user, $site, $plot, CallOffRequestStatus::Rejected);
    $resubmission = sprint3aRequest($user, $site, $plot, CallOffRequestStatus::Submitted, $rejected->id);
    $history = CallOffStatusHistory::factory()->create([
        'call_off_request_id' => $rejected->id, 'call_off_batch_id' => $rejected->call_off_batch_id,
        'performed_by_user_id' => $user->id, 'event_type' => CallOffHistoryEventType::Rejected,
    ]);

    expect($resubmission->resubmittedFrom->is($rejected))->toBeTrue();
    expect(fn () => $history->update(['customer_response' => 'changed']))->toThrow(LogicException::class);
});

test('the central conflict key keeps Date Agreed active and completed inactive', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $request = sprint3aRequest($user, $site, $plot, CallOffRequestStatus::DateAgreed);

    app(UpdateConflictKeyAction::class)->handle($request);
    expect($request->fresh()->active_conflict_key)->not->toBeNull();

    $request->update(['status' => CallOffRequestStatus::Completed]);
    app(UpdateConflictKeyAction::class)->handle($request);
    expect($request->fresh()->active_conflict_key)->toBeNull();
});

test('the computed active conflict key cannot be mass assigned', function (): void {
    $request = new CallOffRequest;
    $request->fill(['active_conflict_key' => 'forged-by-input']);

    expect($request->active_conflict_key)->toBeNull();
});

test('the unique conflict key blocks two conflict-active requests for one plot service', function (): void {
    [$user, $site, $plot] = sprint3aSiteUser();
    $first = sprint3aRequest($user, $site, $plot, CallOffRequestStatus::DateAgreed);
    $second = sprint3aRequest($user, $site, $plot, CallOffRequestStatus::DateAgreed);

    app(UpdateConflictKeyAction::class)->handle($first);

    expect(fn () => app(UpdateConflictKeyAction::class)->handle($second))
        ->toThrow(UniqueConstraintViolationException::class);
});

/** @return array{User, Site, ProjectedPlot} */
function sprint3aSiteUser(): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user = sprint3aUser(PortalRoleIdentifier::SiteManager, $organisation);
    $user->assignedSites()->attach($site);

    return [$user, $site, ProjectedPlot::factory()->create(['site_id' => $site->id])];
}

function sprint3aUser(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);

    return User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
}

function sprint3aRequest(User $user, Site $site, ProjectedPlot $plot, CallOffRequestStatus $status = CallOffRequestStatus::Submitted, ?int $resubmittedFrom = null): CallOffRequest
{
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'status' => $status,
        'resubmitted_from_call_off_request_id' => $resubmittedFrom,
    ]);
}
