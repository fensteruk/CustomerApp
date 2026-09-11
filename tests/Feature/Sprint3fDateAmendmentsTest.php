<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RejectAlternativeCallOffDateAction;
use App\Actions\CallOff\RequestCallOffAmendmentAction;
use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Contracts\HolidayProvider;
use App\Data\SourceRecord;
use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PlotOverallStatus;
use App\Enums\PlotServicePresentationState;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Events\CallOffAlternativeAccepted;
use App\Events\CallOffAlternativeProposed;
use App\Events\CallOffAlternativeRejected;
use App\Events\CallOffAmendmentRequested;
use App\Events\CallOffDateAgreed;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\CallOffDateViewService;
use App\Services\PlotOverviewQueryService;
use App\Services\SourceProjectionImportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['call_off_requests', 'call_off_date_negotiations', 'call_off_date_proposals', 'call_off_status_histories', 'portal_notifications'] as $table) {
        expect(DB::table($table)->count())->toBe(0, "Ordinary Sprint 3F boundary is contaminated: {$table}");
    }
    $this->travelTo(CarbonImmutable::parse('2026-09-03 10:00:00'));
    // Isolate the original workflow cases; confirmed production policy is tested below.
    config(['call_off_amendments.reasons' => ['test_reason' => 'Test reason']]);
});

function amendmentFixture(): array
{
    foreach (PortalRoleIdentifier::cases() as $role) {
        PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);
    }
    $org = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $org->id, 'external_source' => 'amendment-test', 'external_identifier' => 'site-'.fake()->uuid()]);
    $customer = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $org->id]);
    $responder = User::factory()->role(PortalRoleIdentifier::FinishingForeman)->create(['customer_organisation_id' => $org->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $customer->assignedSites()->attach($site);
    $responder->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'external_source' => 'amendment-test']);
    $service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows, 'source_present' => true, 'source_call_number' => fake()->uuid(), 'source_call_type' => 'PC1']);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $customer->id, 'requested_date' => '2026-10-08']);
    $request = CallOffRequest::query()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $service->id, 'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => '2026-10-08', 'status' => CallOffRequestStatus::AwaitingFenster,
    ]);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);

    return [$customer, $office, $request->fresh(), $site, $responder, $service];
}

function requestTestAmendment(User $actor, Site $site, CallOffRequest $request, string $date = '2026-10-22'): CallOffDateNegotiation
{
    return app(RequestCallOffAmendmentAction::class)->handle($actor, $site, $request, [
        'requested_date' => $date, 'reason_code' => 'test_reason', 'customer_response' => 'Customer explanation',
    ], app(CallOffAmendmentRules::class)->revision($request));
}

it('retains the original agreement and attributes the amendment to the actual assigned requester', function (): void {
    [$customer, , $request, $site, $responder] = amendmentFixture();
    $originalHistory = $request->histories()->get()->toArray();
    $amendment = requestTestAmendment($responder, $site, $request);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::AmendmentOnHold)
        ->and($request->fresh()->agreed_date->toDateString())->toBe('2026-10-08')
        ->and($request->fresh()->requested_date->toDateString())->toBe('2026-10-08')
        ->and($amendment->requested_by_user_id)->toBe($responder->id)
        ->and($amendment->prior_agreed_date->toDateString())->toBe('2026-10-08')
        ->and($amendment->requested_date->toDateString())->toBe('2026-10-22')
        ->and($request->histories()->orderBy('sequence')->first()->toArray())->toBe($originalHistory[0])
        ->and($request->fresh()->active_conflict_key)->not->toBeNull()
        ->and(CallOffRequest::count())->toBe(1);
});

it('agrees an amendment using the same engine without replacing original dates or history', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $amendment = requestTestAmendment($customer, $site, $request);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $amendment->uuid);
    expect($request->fresh()->agreed_date->toDateString())->toBe('2026-10-22')
        ->and($request->fresh()->requested_date->toDateString())->toBe('2026-10-08')
        ->and($amendment->fresh()->status)->toBe(CallOffNegotiationStatus::DateAgreed)
        ->and($amendment->fresh()->resulting_agreed_date->toDateString())->toBe('2026-10-22')
        ->and($request->histories()->where('event_type', 'date_agreed')->pluck('after_state')->map(fn ($s) => $s['agreed_date'])->all())->toBe(['2026-10-08', '2026-10-22']);
});

it('reuses ordered alternatives with real responders and per-cycle notifications', function (): void {
    [$customer, $office, $request, $site, $responder] = amendmentFixture();
    $amendment = requestTestAmendment($responder, $site, $request);
    $propose = app(ProposeAlternativeCallOffDateAction::class);
    for ($i = 0; $i < 3; $i++) {
        $proposal = $propose->handle($office, $request->fresh(), '2026-10-23', 'Public message', 'Private secret', $amendment->uuid);
        expect($request->fresh()->status)->toBe(CallOffRequestStatus::AmendmentOnHold);
        app(RejectAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal, 'Not suitable');
    }
    $proposal = $propose->handle($office, $request->fresh(), '2026-10-26', null, null, $amendment->uuid);
    app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal);
    expect($proposal->fresh()->responded_by_user_id)->toBe($customer->id)
        ->and($amendment->proposals()->pluck('sequence')->all())->toBe([1, 2, 3, 4, 5])
        ->and($amendment->fresh()->resulting_agreed_date->toDateString())->toBe('2026-10-26')
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and(PortalNotification::where('notifiable_user_id', $responder->id)->where('type', PortalNotificationType::CallOffAlternativeProposed)->count())->toBe(4)
        ->and(PortalNotification::where('notifiable_user_id', $customer->id)->where('type', PortalNotificationType::CallOffAlternativeProposed)->count())->toBe(0)
        ->and(PortalNotification::where('notifiable_user_id', $office->id)->where('type', PortalNotificationType::CallOffAmendmentRequested)->count())->toBe(1);
    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal))->toThrow(ValidationException::class);
});

it('requires a configured reason and rejects invalid or unchanged requested dates', function (array $input): void {
    [$customer, , $request, $site] = amendmentFixture();
    expect(fn () => app(RequestCallOffAmendmentAction::class)->handle($customer, $site, $request, $input, app(CallOffAmendmentRules::class)->revision($request)))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)->and($request->dateNegotiations()->count())->toBe(1);
})->with([
    [['requested_date' => '2026-10-22', 'reason_code' => 'invented']],
    [['requested_date' => '2026-10-08', 'reason_code' => 'test_reason']],
    [['requested_date' => '2026-10-24', 'reason_code' => 'test_reason']],
    [['requested_date' => '2026-09-01', 'reason_code' => 'test_reason']],
    [['requested_date' => '2027-06-01', 'reason_code' => 'test_reason']],
]);

it('fails closed until Product configures the reasons', function (): void {
    [$customer, , $request, $site] = amendmentFixture();
    config(['call_off_amendments.reasons' => []]);
    expect(fn () => requestTestAmendment($customer, $site, $request))->toThrow(ValidationException::class);
});

it('allows late amendments and computes the three-working-day warning server-side', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $request->update(['agreed_date' => '2026-09-07']);
    $amendment = requestTestAmendment($customer, $site, $request, '2026-09-08');
    expect($amendment->is_urgent)->toBeTrue()->and($amendment->is_early_date_exception)->toBeTrue();
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $amendment->uuid))->toThrow(ValidationException::class);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, true, $amendment->uuid);
    expect($amendment->proposals()->first()->earlier_date_acknowledged_at)->not->toBeNull();
});

it('counts working days with the replaceable holiday provider and treats past agreements as late', function (): void {
    app()->bind(HolidayProvider::class, fn () => new class implements HolidayProvider
    {
        public function isHoliday(CarbonInterface $date): bool
        {
            return $date->toDateString() === '2026-09-07';
        }
    });
    $rules = app(CallOffAmendmentRules::class);
    expect($rules->isUrgent(CarbonImmutable::parse('2026-09-09')))->toBeTrue()
        ->and($rules->isUrgent(CarbonImmutable::parse('2026-09-10')))->toBeFalse()
        ->and($rules->isUrgent(CarbonImmutable::parse('2026-09-01')))->toBeTrue();
});

it('revalidates early alternative acknowledgement before Office proposal', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    expect(fn () => app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-09-10', null, null, $cycle->uuid))->toThrow(ValidationException::class);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-09-10', null, null, $cycle->uuid, true);
    expect($proposal->earlier_date_acknowledged_at)->not->toBeNull();
});

it('denies wrong customers, unassigned users, Office initiation and deactivated stale actors', function (string $kind): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $actor = $customer;
    if ($kind === 'office') {
        $actor = $office;
    }
    if ($kind === 'unassigned') {
        $customer->assignedSites()->detach();
    }
    if ($kind === 'wrong_customer') {
        User::whereKey($customer->id)->update(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]);
    }
    if ($kind === 'inactive') {
        User::whereKey($customer->id)->update(['is_active' => false]);
    }
    expect(fn () => requestTestAmendment($actor, $site, $request))->toThrow(AuthorizationException::class);
})->with(['office', 'unassigned', 'wrong_customer', 'inactive']);

it('blocks duplicate amendments and stale Office forms from a previous cycle', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    expect(fn () => requestTestAmendment($customer, $site, $request))->toThrow(ValidationException::class);
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request))->toThrow(ValidationException::class);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid);
    $second = requestTestAmendment($customer, $site, $request->fresh(), '2026-10-29');
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid))->toThrow(ValidationException::class);
    expect($second->fresh()->status)->toBe(CallOffNegotiationStatus::Open);
});

it('makes source completion win over active amendments and never reopens on reversal', function (bool $withProposal): void {
    [$customer, $office, $request, $site, , $service] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    $proposal = $withProposal ? app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-23', null, null, $cycle->uuid) : null;
    $beforeNotifications = PortalNotification::count();
    $importer = app(SourceProjectionImportService::class);
    $run = $importer->import('amendment-test', [new SourceRecord($service->source_call_number, $site->external_identifier, $service->projectedPlot->plot_reference, 'PC1', null, CarbonImmutable::today())]);
    expect($run->records_rejected)->toBe(0)
        ->and($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Completed)
        ->and($cycle->fresh()->active_negotiation_key)->toBeNull()
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and(PortalNotification::count())->toBe($beforeNotifications);
    if ($proposal !== null) {
        expect($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::Superseded);
        expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal))->toThrow(ValidationException::class);
    }
    $importer->import('amendment-test', [new SourceRecord($service->source_call_number, $site->external_identifier, $service->projectedPlot->plot_reference, 'PC1', null, null)]);
    expect($service->fresh()->isSourceCompleted())->toBeFalse()
        ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Completed)
        ->and($cycle->fresh()->resulting_agreed_date)->toBeNull();
})->with([false, true]);

it('rolls back a failed amendment without history or notification leakage', function (): void {
    [$customer, , $request, $site] = amendmentFixture();
    $notifications = PortalNotification::count();
    $histories = $request->histories()->count();
    expect(function () use ($customer, $request, $site): void {
        DB::transaction(function () use ($customer, $request, $site): void {
            requestTestAmendment($customer, $site, $request);
            throw new RuntimeException('Forced rollback');
        });
    })->toThrow(RuntimeException::class);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->histories()->count())->toBe($histories)
        ->and(PortalNotification::count())->toBe($notifications)
        ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0);
});

it('supports legacy Approved without fabricating an original negotiation', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    // Independent legacy row, rather than rewriting existing append-only history.
    $legacy = CallOffRequest::factory()->create([
        'call_off_batch_id' => $request->call_off_batch_id, 'projected_plot_id' => $request->projected_plot_id,
        'projected_plot_service_id' => $request->projected_plot_service_id,
        'service_identifier' => CallOffServiceType::Windows, 'requested_date' => '2026-10-08',
        'agreed_date' => '2026-10-08', 'status' => CallOffRequestStatus::Approved,
    ]);
    $request->status = CallOffRequestStatus::Completed;
    app(UpdateConflictKeyAction::class)->handle($request);
    expect(app(CallOffDateViewService::class)->forRequest($legacy->fresh(), $customer)['statusLabel'])->toBe('Date Agreed')
        ->and($legacy->dateNegotiations()->count())->toBe(0);
    $originalHistory = $request->histories()->get()->toArray();
    $cycle = requestTestAmendment($customer, $site, $legacy);
    expect($legacy->dateNegotiations()->count())->toBe(1)
        ->and($legacy->histories()->first()->previous_status)->toBe(CallOffRequestStatus::Approved);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $legacy, false, $cycle->uuid);
    expect($legacy->fresh()->agreed_date->toDateString())->toBe('2026-10-22');
    expect($request->histories()->get()->toArray())->toBe($originalHistory);
});

it('reviews server-held data, ignores tampered final fields and prevents replay', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
    $this->get(route('portal.call-offs.amendments.create', $request))->assertOk()->assertSee('Request Date Change');
    $response = $this->post(route('portal.call-offs.amendments.review', $request), ['requested_date' => '2026-10-22', 'reason_code' => 'test_reason']);
    $response->assertOk();
    $token = $response->viewData('token');
    $url = route('portal.call-offs.amendments.store', $request);
    $this->post($url, ['confirmation_token' => $token, 'requested_date' => '2030-01-01'])->assertRedirect(route('portal.call-offs.show', $request));
    expect($request->dateNegotiations()->where('purpose', 'amendment')->first()->requested_date->toDateString())->toBe('2026-10-22');
    $this->post($url, ['confirmation_token' => $token])->assertSessionHasErrors();
    $this->get(route('portal.call-offs.show', $request))->assertOk()->assertSee('On Hold')->assertDontSee('Withdraw request');
    $this->actingAs($office)->get(route('portal.review-requests.show', $request))->assertOk()->assertSee('Accept New Date');
});

it('denies direct UUID substitution including another assigned but inactive site', function (): void {
    [$customer, , $request, $site] = amendmentFixture();
    $other = Site::factory()->create(['customer_organisation_id' => $site->customer_organisation_id]);
    $customer->assignedSites()->attach($other);
    $this->actingAs($customer)->withSession(['active_site_id' => $other->id])
        ->get(route('portal.call-offs.amendments.create', $request))->assertNotFound();
    $this->post(route('portal.call-offs.amendments.review', $request), ['requested_date' => '2026-10-22', 'reason_code' => 'test_reason'])->assertNotFound();
});

it('keeps historical dates truthful and private reasons out of customer screens', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-23', 'Public reason', 'Secret office reason', $cycle->uuid);
    $this->actingAs($customer)->withSession(['active_site_id' => $site->id])->get(route('portal.call-offs.show', $request))
        ->assertOk()->assertSee('Public reason')->assertDontSee('Secret office reason')->assertSee('Accept Date');
    app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal);
    $this->get(route('portal.call-offs.show', $request))->assertOk()->assertSee('Date Agreed — 2026-10-08')->assertSee('Date Agreed — 2026-10-23');
});

it('allows each current Site User role to request a date change', function (PortalRoleIdentifier $role): void {
    [$customer, , $request, $site] = amendmentFixture();
    $customer->update(['portal_role_id' => PortalRole::where('identifier', $role->value)->firstOrFail()->id]);
    expect(requestTestAmendment($customer, $site, $request)->requester_role)->toBe($role->label());
})->with([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman]);

it('revalidates open review forms after completion, source loss, expiry or access revocation', function (string $change): void {
    [$customer, , $request, $site, , $service] = amendmentFixture();
    $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
    $token = $this->post(route('portal.call-offs.amendments.review', $request), [
        'requested_date' => '2026-10-22', 'reason_code' => 'test_reason',
    ])->assertOk()->viewData('token');
    match ($change) {
        'completed' => $service->update(['source_completed_at' => now()]),
        'missing' => $service->update(['source_present' => false]),
        'revoked' => $customer->assignedSites()->detach($site),
        'inactive' => $customer->update(['is_active' => false]),
        'expired' => $this->travel(16)->minutes(),
    };
    $response = $this->post(route('portal.call-offs.amendments.store', $request), ['confirmation_token' => $token]);
    if (in_array($change, ['completed', 'missing', 'expired'], true)) {
        $response->assertSessionHasErrors();
    } else {
        expect($response->isRedirect() || $response->getStatusCode() === 403)->toBeTrue();
    }
    expect($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0)
        ->and($request->fresh()->agreed_date->toDateString())->toBe('2026-10-08');
})->with(['completed', 'missing', 'revoked', 'inactive', 'expired']);

it('rejects amendment routes for guests and Office initiators', function (): void {
    [, $office, $request] = amendmentFixture();
    $this->get(route('portal.call-offs.amendments.create', $request))->assertRedirect(route('login'));
    $this->post(route('portal.call-offs.amendments.review', $request), [])->assertRedirect(route('login'));
    $this->actingAs($office)->get(route('portal.call-offs.amendments.create', $request))->assertForbidden();
});

it('rechecks an Office actor loaded before deactivation without changing the amendment', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    User::whereKey($office->id)->update(['is_active' => false]);
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid))->toThrow(AuthorizationException::class)
        ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Open);
});

it('rejects proposal UUID substitution and superseded alternatives without side effects', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    $first = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-23', null, null, $cycle->uuid);
    app(RejectAlternativeCallOffDateAction::class)->handle($customer, $request, $first, 'Not suitable');
    $second = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-26', null, null, $cycle->uuid);
    [$other, $otherOffice, $otherRequest, $otherSite] = amendmentFixture();
    $otherCycle = requestTestAmendment($other, $otherSite, $otherRequest);
    $foreignProposal = app(ProposeAlternativeCallOffDateAction::class)->handle($otherOffice, $otherRequest, '2026-10-23', null, null, $otherCycle->uuid);
    $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
    foreach ([$first, $foreignProposal] as $stale) {
        $this->post(route('portal.call-offs.alternative-dates.accept', [$request, $stale]))->assertSessionHasErrors();
    }
    expect($second->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse)
        ->and($foreignProposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse);
});

it('preserves aggregate completion precedence and keeps On Hold out of confirmed dates', function (): void {
    [$customer, , $request, $site, , $service] = amendmentFixture();
    requestTestAmendment($customer, $site, $request);
    $query = app(PlotOverviewQueryService::class);
    $overview = $query->present($service->projectedPlot->fresh(['services', 'callOffRequests.batch']));
    expect($overview->overallStatus)->toBe(PlotOverallStatus::CallOffsInProgress)
        ->and($overview->services['windows']->state)->toBe(PlotServicePresentationState::OnHold)
        ->and($overview->services['windows']->date)->toBeNull()
        ->and(app(CallOffDateViewService::class)->forRequest($request->fresh(), $customer)['agreedDate'])->toBeNull()
        ->and($query->paginate($site, ['status' => 'on_hold'])->total())->toBe(1)
        ->and($query->paginate($site, ['status' => 'date_agreed'])->total())->toBe(0);
    $service->projectedPlot->services()->create(['service_identifier' => CallOffServiceType::CavityClosers, 'source_completed_at' => now()]);
    expect($query->present($service->projectedPlot->fresh(['services', 'callOffRequests.batch']))->overallStatus)->toBe(PlotOverallStatus::PartiallyCompleted);
});

it('snapshots amendment actors and separates notifications for subsequent amendment cycles', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $originalOfficeName = $office->name;
    $first = requestTestAmendment($customer, $site, $request);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $first->uuid);
    $second = requestTestAmendment($customer, $site, $request->fresh(), '2026-11-02');
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $second->uuid);
    $office->update(['name' => 'Changed display name']);
    expect($request->histories()->where('event_type', 'date_agreed')->get()->map->recordedActorName()->unique()->all())->toBe([$originalOfficeName])
        ->and(PortalNotification::where('notifiable_user_id', $customer->id)->where('type', PortalNotificationType::CallOffDateAgreed)->count())->toBe(3)
        ->and($second->prior_agreed_date->toDateString())->toBe('2026-10-22');
});

it('database uniqueness prevents a second active amendment even outside the action', function (): void {
    [$customer, , $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    expect(fn () => $request->dateNegotiations()->create([
        'purpose' => 'amendment', 'status' => 'open',
        'active_negotiation_key' => $cycle->active_negotiation_key, 'opened_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rolls back amendment acceptance and both notification events together', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-23', null, null, $cycle->uuid);
    $notifications = PortalNotification::count();
    $histories = $request->histories()->count();
    expect(function () use ($customer, $request, $proposal): void {
        DB::transaction(function () use ($customer, $request, $proposal): void {
            app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal);
            throw new RuntimeException('Simulated caller rollback');
        });
    })->toThrow(RuntimeException::class);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::AmendmentOnHold)
        ->and($request->fresh()->agreed_date->toDateString())->toBe('2026-10-08')
        ->and($cycle->fresh()->resulting_agreed_date)->toBeNull()
        ->and($proposal->fresh()->status)->toBe(CallOffDateProposalStatus::AwaitingResponse)
        ->and($request->histories()->count())->toBe($histories)
        ->and(PortalNotification::count())->toBe($notifications);
});

it('enforces the complete direct amendment route role and tenancy matrix', function (string $kind): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $actor = $customer;
    $activeSite = $site;
    if (in_array($kind, ['assistant_site_manager', 'finishing_foreman'], true)) {
        $customer->update(['portal_role_id' => PortalRole::where('identifier', $kind)->value('id')]);
    }
    if ($kind === 'office') {
        $actor = $office;
    }
    if (in_array($kind, ['wrong_site', 'wrong_customer'], true)) {
        $orgId = $kind === 'wrong_customer' ? CustomerOrganisation::factory()->create()->id : $site->customer_organisation_id;
        $activeSite = Site::factory()->create(['customer_organisation_id' => $orgId]);
        $customer->update(['customer_organisation_id' => $orgId]);
        $customer->assignedSites()->attach($activeSite);
    }
    if ($kind === 'unassigned') {
        $customer->assignedSites()->detach();
    }
    if ($kind === 'inactive') {
        $customer->update(['is_active' => false]);
    }
    if ($kind !== 'guest') {
        $this->actingAs($actor);
    }
    $this->withSession(['active_site_id' => $activeSite->id]);
    $response = $this->post(route('portal.call-offs.amendments.review', $request), ['requested_date' => '2026-10-22', 'reason_code' => 'test_reason']);
    match ($kind) {
        'guest', 'inactive' => $response->assertRedirect(route('login')),
        'office' => $response->assertForbidden(),
        'unassigned' => $response->assertRedirect(route('sites.select')),
        'wrong_site', 'wrong_customer' => $response->assertNotFound(),
        default => $response->assertOk(),
    };
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
        ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0);
})->with(['site_manager', 'assistant_site_manager', 'finishing_foreman', 'office', 'wrong_site', 'wrong_customer', 'unassigned', 'inactive', 'guest']);

it('denies direct Office decision by Site Users and cross-request amendment UUID substitution', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    [$other, , $otherRequest, $otherSite] = amendmentFixture();
    $otherCycle = requestTestAmendment($other, $otherSite, $otherRequest);
    $url = route('portal.review-requests.agree-requested-date', $request);
    $this->actingAs($customer)->post($url, ['negotiation_uuid' => $cycle->uuid])->assertForbidden();
    $this->actingAs($office)->post($url, ['negotiation_uuid' => $otherCycle->uuid])->assertSessionHasErrors('request');
    expect($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Open)
        ->and($otherCycle->fresh()->status)->toBe(CallOffNegotiationStatus::Open);
    $this->post($url, ['negotiation_uuid' => $cycle->uuid])->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed);
});

it('preserves exact amendment notification counts through rollback and action or listener replay', function (): void {
    [$customer, $office, $request, $site] = amendmentFixture();
    $cycle = requestTestAmendment($customer, $site, $request);
    $count = fn (PortalNotificationType $type, User $recipient): int => PortalNotification::where('request_uuid', $request->uuid)->where('type', $type)->where('notifiable_user_id', $recipient->id)->count();
    expect($count(PortalNotificationType::CallOffAmendmentRequested, $office))->toBe(1);
    event(new CallOffAmendmentRequested($request->id, $cycle->uuid));
    expect($count(PortalNotificationType::CallOffAmendmentRequested, $office))->toBe(1);
    $notifications = PortalNotification::count();
    $histories = $request->histories()->count();
    expect(function () use ($office, $request, $cycle): void {
        DB::transaction(function () use ($office, $request, $cycle): void {
            app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid);
            throw new RuntimeException('Synthetic Office decision rollback');
        });
    })->toThrow(RuntimeException::class);
    expect(PortalNotification::count())->toBe($notifications)
        ->and($request->histories()->count())->toBe($histories)
        ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Open);
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-23', null, null, $cycle->uuid);
    event(new CallOffAlternativeProposed($request->id, $proposal->uuid));
    expect($count(PortalNotificationType::CallOffAlternativeProposed, $customer))->toBe(1);
    expect(fn () => app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-26', null, null, $cycle->uuid))->toThrow(ValidationException::class);
    app(RejectAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal, 'Unavailable');
    event(new CallOffAlternativeRejected($request->id, $proposal->uuid));
    expect($count(PortalNotificationType::CallOffAlternativeRejected, $office))->toBe(1);
    expect(fn () => app(RejectAlternativeCallOffDateAction::class)->handle($customer, $request, $proposal, 'Replay'))->toThrow(ValidationException::class);
    $second = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-26', null, null, $cycle->uuid);
    app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $second);
    event(new CallOffAlternativeAccepted($request->id, $second->uuid));
    event(new CallOffDateAgreed($request->id, $cycle->uuid));
    expect($count(PortalNotificationType::CallOffAlternativeAccepted, $office))->toBe(1)
        ->and($count(PortalNotificationType::CallOffDateAgreed, $customer))->toBe(2)
        ->and($count(PortalNotificationType::CallOffAlternativeProposed, $customer))->toBe(2);
    $histories = $request->histories()->count();
    $notifications = PortalNotification::count();
    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($customer, $request, $second))->toThrow(ValidationException::class);
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid))->toThrow(ValidationException::class);
    expect($request->histories()->count())->toBe($histories)->and(PortalNotification::count())->toBe($notifications);
});

it('refuses invalid Date Agreed or unavailable source data before creating an amendment', function (string $condition): void {
    [$customer, , $request, $site, , $service] = amendmentFixture();
    match ($condition) {
        'missing_date' => $request->update(['agreed_date' => null]),
        'completed_plot' => $request->projectedPlot->update(['is_completed' => true]),
        'missing_source' => $service->update(['source_present' => false]),
    };
    expect(fn () => requestTestAmendment($customer, $site, $request))->toThrow(ValidationException::class)
        ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0);
})->with(['missing_date', 'completed_plot', 'missing_source']);

describe('Sprint 3F confirmed product decisions', function (): void {
    beforeEach(function (): void {
        config(['call_off_amendments' => require config_path('call_off_amendments.php')]);
    });

    $approvedReasons = [
        ['SITE_NOT_READY', 'Site Not Ready'],
        ['PROGRAMME_CHANGE', 'Programme Change'],
        ['ACCESS_ISSUE', 'Access Issue'],
        ['CUSTOMER_REQUESTED_CHANGE', 'Customer Requested Change'],
        ['MATERIALS_AVAILABILITY', 'Materials / Availability'],
        ['WEATHER', 'Weather'],
        ['OTHER', 'Other'],
    ];

    it('ships exactly the approved ordered code and label list', function () use ($approvedReasons): void {
        expect(app(CallOffAmendmentRules::class)->reasons())->toBe(array_column($approvedReasons, 1, 0));
    });

    it('stores every approved code through review and confirmation with friendly audited history', function (string $code, string $label): void {
        [$customer, $office, $request, $site] = amendmentFixture();
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
        $input = ['requested_date' => '2026-10-22', 'reason_code' => $code];
        if ($code === 'OTHER') {
            $input['customer_response'] = "  Specific site circumstances. \n";
        }
        $review = $this->post(route('portal.call-offs.amendments.review', $request), $input)
            ->assertOk()->assertSee($label)->assertDontSee($code);
        $this->post(route('portal.call-offs.amendments.store', $request), [
            'confirmation_token' => $review->viewData('token'),
            'reason_code' => 'FORGED_FINAL_CODE', 'customer_response' => 'FORGED_FINAL_TEXT',
        ])->assertSessionHasNoErrors()->assertRedirect(route('portal.call-offs.show', $request));
        $cycle = $request->dateNegotiations()->where('purpose', 'amendment')->sole();
        $history = $request->histories()->where('event_type', 'amendment_requested')->sole();
        $explanation = $code === 'OTHER' ? 'Specific site circumstances.' : null;
        expect($cycle->reason_code)->toBe($code)->and($cycle->reason_label)->toBe($label)
            ->and($cycle->customer_response)->toBe($explanation)
            ->and($cycle->requested_by_user_id)->toBe($customer->id)
            ->and($cycle->opened_at->toDateTimeString())->toBe('2026-09-03 10:00:00')
            ->and($history->after_state['reason_code'])->toBe($code)
            ->and($history->after_state['reason_label'])->toBe($label)
            ->and($history->customer_response)->toBe($explanation)
            ->and($history->performed_by_user_id)->toBe($customer->id);
        $this->get(route('portal.call-offs.show', $request))->assertOk()->assertSee($label)
            ->assertSee($customer->name)->assertSee('3 Sep 2026, 10:00:00')
            ->assertDontSee($code)->assertDontSee('FORGED_FINAL_TEXT');
        $officeView = $this->actingAs($office)->get(route('portal.review-requests.show', $request))
            ->assertOk()->assertSee($label)->assertDontSee($code)
            ->assertSee('Previous agreed date')->assertSee('Requested new date');
        if ($explanation !== null) {
            $officeView->assertSee('Additional information')->assertSee($explanation);
        }
    })->with($approvedReasons);

    it('normalizes optional additional information without changing its content', function (string $code): void {
        [$customer, , $request, $site] = amendmentFixture();
        $cycle = app(RequestCallOffAmendmentAction::class)->handle($customer, $site, $request, [
            'requested_date' => '2026-10-22', 'reason_code' => $code,
            'customer_response' => "\u{00a0} Details <script>alert('unsafe')</script> \n second line. \u{00a0}",
        ], app(CallOffAmendmentRules::class)->revision($request));
        $expected = "Details <script>alert('unsafe')</script> \n second line.";
        expect($cycle->customer_response)->toBe($expected)
            ->and($request->histories()->where('event_type', 'amendment_requested')->sole()->customer_response)->toBe($expected);
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id])
            ->get(route('portal.call-offs.show', $request))->assertOk()->assertSee($expected)
            ->assertDontSee("<script>alert('unsafe')</script>", false);
    })->with(array_column(array_slice($approvedReasons, 0, 6), 0));

    it('rejects invalid reasons and explanations at both HTTP review and the action boundary', function (array $input, string $field): void {
        [$customer, , $request, $site] = amendmentFixture();
        $input += ['requested_date' => '2026-10-22'];
        $historyCount = $request->histories()->count();
        $notificationCount = PortalNotification::count();
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id])
            ->post(route('portal.call-offs.amendments.review', $request), $input)->assertSessionHasErrors($field);
        expect(fn () => app(RequestCallOffAmendmentAction::class)->handle($customer, $site, $request, $input, app(CallOffAmendmentRules::class)->revision($request)))
            ->toThrow(ValidationException::class);
        expect($request->fresh()->status)->toBe(CallOffRequestStatus::DateAgreed)
            ->and($request->histories()->count())->toBe($historyCount)
            ->and(PortalNotification::count())->toBe($notificationCount)
            ->and($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0);
    })->with([
        'missing Other explanation' => [['reason_code' => 'OTHER'], 'customer_response'],
        'empty Other explanation' => [['reason_code' => 'OTHER', 'customer_response' => ''], 'customer_response'],
        'whitespace Other explanation' => [['reason_code' => 'OTHER', 'customer_response' => " \t\r\n "], 'customer_response'],
        'Unicode whitespace Other explanation' => [['reason_code' => 'OTHER', 'customer_response' => "\u{00a0}\u{2003}"], 'customer_response'],
        'Other too long' => [['reason_code' => 'OTHER', 'customer_response' => str_repeat('x', 2001)], 'customer_response'],
        'optional explanation too long' => [['reason_code' => 'WEATHER', 'customer_response' => str_repeat('x', 2001)], 'customer_response'],
        'non-string explanation' => [['reason_code' => 'OTHER', 'customer_response' => ['text']], 'customer_response'],
        'unknown code' => [['reason_code' => 'UNAPPROVED'], 'reason_code'],
        'label instead of code' => [['reason_code' => 'Site Not Ready'], 'reason_code'],
        'lowercase code' => [['reason_code' => 'site_not_ready'], 'reason_code'],
        'array code' => [['reason_code' => ['SITE_NOT_READY']], 'reason_code'],
        'missing code' => [[], 'reason_code'],
    ]);

    it('accepts the exact explanation length boundary and normalizes blank optional text', function (): void {
        $rules = app(CallOffAmendmentRules::class);
        $valid = $rules->validate(['requested_date' => '2026-10-22', 'reason_code' => 'OTHER', 'customer_response' => str_repeat('x', 2000)]);
        expect(strlen($valid['customer_response']))->toBe(2000)
            ->and($rules->validate(['requested_date' => '2026-10-22', 'reason_code' => 'WEATHER', 'customer_response' => " \n\u{00a0}"])['customer_response'])->toBeNull();
    });

    it('keeps reason validation behind direct POST authorization', function (string $actorState, string $reason): void {
        [$customer, , $request, $site] = amendmentFixture();
        if ($actorState === 'wrong_site') {
            $site = Site::factory()->create(['customer_organisation_id' => $site->customer_organisation_id]);
            $customer->assignedSites()->attach($site);
        } else {
            $customer->update(['is_active' => false]);
        }
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
        $response = $this->post(route('portal.call-offs.amendments.review', $request), [
            'requested_date' => '2026-10-22', 'reason_code' => $reason,
        ]);
        $actorState === 'wrong_site' ? $response->assertNotFound() : $response->assertRedirect(route('login'));
        $response->assertSessionDoesntHaveErrors('reason_code');
        expect($request->dateNegotiations()->where('purpose', 'amendment')->count())->toBe(0);
    })->with(['wrong_site', 'inactive'])->with(['SITE_NOT_READY', 'UNKNOWN']);

    it('presents the approved form labels with a no-JavaScript explanation rule', function (): void {
        [$customer, , $request, $site] = amendmentFixture();
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id])
            ->get(route('portal.call-offs.amendments.create', $request))->assertOk()
            ->assertSee('Reason for date change')->assertSee('Additional information')
            ->assertSee('Required when Other is selected; otherwise optional.')
            ->assertSee('aria-describedby="customer-response-help"', false);
    });

    it('preserves the confirmed plot status matrix through an amendment lifecycle', function (string $scenario): void {
        [$customer, $office, $request, $site, , $service] = amendmentFixture();
        $plot = $service->projectedPlot;
        $query = app(PlotOverviewQueryService::class);
        $present = fn () => $query->present($plot->fresh(['services', 'callOffRequests.batch']));
        expect($present()->overallStatus)->toBe(PlotOverallStatus::DatesAgreed);
        if ($scenario === 'completed_peer') {
            $plot->services()->create(['service_identifier' => CallOffServiceType::CavityClosers, 'source_completed_at' => now()]);
        }
        if ($scenario === 'unaffected_peers') {
            foreach ([CallOffServiceType::Cml, CallOffServiceType::Snagging] as $peerType) {
                $peer = $plot->services()->create(['service_identifier' => $peerType, 'source_present' => true]);
                CallOffRequest::factory()->create([
                    'call_off_batch_id' => $request->call_off_batch_id, 'projected_plot_id' => $plot->id,
                    'projected_plot_service_id' => $peer->id, 'service_identifier' => $peerType,
                    'requested_date' => '2026-10-08', 'agreed_date' => $peerType === CallOffServiceType::Cml ? '2026-10-08' : null,
                    'status' => $peerType === CallOffServiceType::Cml ? CallOffRequestStatus::DateAgreed : CallOffRequestStatus::AwaitingFenster,
                ]);
            }
        }
        $cycle = app(RequestCallOffAmendmentAction::class)->handle($customer, $site, $request, [
            'requested_date' => '2026-10-22', 'reason_code' => 'SITE_NOT_READY',
        ], app(CallOffAmendmentRules::class)->revision($request));
        expect($present()->services['windows']->state)->toBe(PlotServicePresentationState::OnHold)
            ->and($present()->services['windows']->state->label())->toBe('On Hold — Date Change Requested')
            ->and($present()->services['windows']->date)->toBeNull()
            ->and($present()->overallStatus)->toBe($scenario === 'completed_peer' ? PlotOverallStatus::PartiallyCompleted : PlotOverallStatus::CallOffsInProgress);
        if ($scenario === 'completed_peer') {
            expect($present()->services['cavity_closers']->state)->toBe(PlotServicePresentationState::Completed);
        }
        if ($scenario === 'unaffected_peers') {
            expect($present()->services['cml']->state)->toBe(PlotServicePresentationState::DateAgreed)
                ->and($present()->services['cml']->date->toDateString())->toBe('2026-10-08')
                ->and($present()->services['snagging']->state)->toBe(PlotServicePresentationState::AwaitingDate)
                ->and($present()->services['cavity_closers']->state)->toBe(PlotServicePresentationState::NotCalledOff);
        }
        if ($scenario === 'resolved') {
            app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $cycle->uuid);
            expect($present()->services['windows']->state)->toBe(PlotServicePresentationState::DateAgreed)
                ->and($present()->services['windows']->date->toDateString())->toBe('2026-10-22')
                ->and($present()->overallStatus)->toBe(PlotOverallStatus::DatesAgreed);
        }
        if ($scenario === 'source_completion') {
            $notifications = PortalNotification::count();
            $history = $request->histories()->get()->toArray();
            app(SourceProjectionImportService::class)->import('amendment-test', [new SourceRecord($service->source_call_number, $site->external_identifier, $plot->plot_reference, 'PC1', null, CarbonImmutable::today())]);
            expect($present()->services['windows']->state)->toBe(PlotServicePresentationState::Completed)
                ->and($present()->overallStatus)->toBe(PlotOverallStatus::PartiallyCompleted)
                ->and($cycle->fresh()->status)->toBe(CallOffNegotiationStatus::Completed)
                ->and($cycle->fresh()->active_negotiation_key)->toBeNull()
                ->and(PortalNotification::count())->toBe($notifications)
                ->and($request->histories()->whereIn('id', array_column($history, 'id'))->get()->toArray())->toBe($history)
                ->and(app(CallOffDateViewService::class)->forRequest($request->fresh(), $customer)['isOnHold'])->toBeFalse();
        }
        expect(array_column(PlotOverallStatus::cases(), 'value'))->toBe(['nothing_called_off', 'call_offs_in_progress', 'dates_agreed', 'partially_completed', 'fully_completed']);
    })->with(['single_on_hold', 'completed_peer', 'resolved', 'source_completion', 'unaffected_peers']);

    it('applies confirmed reasons and On Hold presentation to legacy Approved without inventing history', function (): void {
        [$customer, , $request, $site] = amendmentFixture();
        $request->status = CallOffRequestStatus::Completed;
        app(UpdateConflictKeyAction::class)->handle($request);
        $legacy = CallOffRequest::factory()->create([
            'call_off_batch_id' => $request->call_off_batch_id, 'projected_plot_id' => $request->projected_plot_id,
            'projected_plot_service_id' => $request->projected_plot_service_id, 'service_identifier' => CallOffServiceType::Windows,
            'requested_date' => '2026-10-08', 'agreed_date' => null, 'status' => CallOffRequestStatus::Approved,
        ]);
        $this->actingAs($customer)->withSession(['active_site_id' => $site->id]);
        $this->get(route('portal.call-offs.show', $legacy))->assertOk()->assertSee('Date Agreed')->assertSee('Request Date Change');
        $review = $this->post(route('portal.call-offs.amendments.review', $legacy), ['requested_date' => '2026-10-22', 'reason_code' => 'PROGRAMME_CHANGE'])->assertOk();
        $this->post(route('portal.call-offs.amendments.store', $legacy), ['confirmation_token' => $review->viewData('token')])->assertSessionHasNoErrors();
        $this->get(route('portal.call-offs.show', $legacy))->assertOk()->assertSee('On Hold — Date Change Requested')->assertSee('Programme Change')->assertSee('8 Oct 2026');
        expect($legacy->dateNegotiations()->count())->toBe(1)
            ->and($legacy->histories()->count())->toBe(1)
            ->and($legacy->histories()->sole()->previous_status)->toBe(CallOffRequestStatus::Approved)
            ->and($legacy->dateNegotiations()->sole()->prior_agreed_date->toDateString())->toBe('2026-10-08');
    });
});
