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
    // Synthetic test-only policy. No business reason list is enabled in production.
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
