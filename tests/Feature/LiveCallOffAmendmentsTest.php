<?php

use App\Actions\CallOff\AcceptAlternativeCallOffDateAction;
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Actions\CallOff\ProposeAlternativeCallOffDateAction;
use App\Actions\CallOff\RequestCallOffAmendmentAction;
use App\Actions\CallOff\SubmitMultiCallOffBatchAction;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffAmendmentRules;
use App\Services\CallOffDateViewService;
use App\Services\OfficeAmendmentsWorkspaceQuery;
use App\Services\OfficeDashboardQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 06:00:00'));
    config(['call_off_amendments.reasons' => ['test_reason' => 'Programme changed']]);
});

function liveAmendmentFixture(): array
{
    $org = CustomerOrganisation::factory()->create();
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $org->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $site = Site::factory()->create(['customer_organisation_id' => $org->id]);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => 'Plot 1']);
    $service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows, 'source_present' => true]);
    $batch = app(SubmitMultiCallOffBatchAction::class)->handle($user, $site, [['plot_service_id' => $service->id, 'requested_date' => '2026-10-01', 'early_date_reason' => null]]);

    return [$user, $office, $batch->requests()->firstOrFail(), $site, $service];
}

function liveAmend(User $user, Site $site, CallOffRequest $request, string $date, ?string $earlyReason = null)
{
    return app(RequestCallOffAmendmentAction::class)->handle($user, $site, $request,
        ['requested_date' => $date, 'reason_code' => 'test_reason', 'early_date_reason' => $earlyReason],
        app(CallOffAmendmentRules::class)->revision($request));
}

it('keeps the exact 1 Oct to 3 Oct to 5 Oct sequence coherent across Plot and Office in a weekday calendar', function () {
    $this->travelTo(CarbonImmutable::parse('2029-09-03 06:00:00'));
    $org = CustomerOrganisation::factory()->create();
    $manager = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $org->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $site = Site::factory()->create(['customer_organisation_id' => $org->id]);
    $manager->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows, 'source_present' => true]);
    $request = app(SubmitMultiCallOffBatchAction::class)->handle($manager, $site,
        [['plot_service_id' => $service->id, 'requested_date' => '2029-10-01', 'early_date_reason' => null]])->requests()->firstOrFail();
    $this->travelTo(CarbonImmutable::parse('2029-09-03 07:00:00'));
    $first = liveAmend($manager, $site, $request, '2029-10-03');
    $this->travelTo(CarbonImmutable::parse('2029-09-03 07:30:00'));
    $latest = liveAmend($manager, $site, $request->fresh(), '2029-10-05');

    expect(CallOffRequest::count())->toBe(1)
        ->and($request->fresh()->effectiveRequestedDate()->toDateString())->toBe('2029-10-05')
        ->and($request->fresh()->requested_date->toDateString())->toBe('2029-10-01')
        ->and($first->fresh()->status)->toBe(CallOffNegotiationStatus::Superseded)
        ->and(app(OfficeDashboardQueryService::class)->forUser($office)['amendmentCount'])->toBe(1);
    $queue = app(OfficeAmendmentsWorkspaceQuery::class)->forUser($office);
    expect($queue['amendments'])->toHaveCount(1)
        ->and($queue['selected']->uuid)->toBe($latest->uuid)
        ->and($queue['comparison'][$latest->uuid]->after_state['prior_requested_date'])->toBe('2029-10-03');
    $this->actingAs($office)->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertSee('Previous requested')->assertSee('3 Oct 2029')->assertSee('5 Oct 2029');
    $this->actingAs($manager)->withSession(['active_site_id' => $site->id])
        ->get(route('portal.plots.show', $plot))->assertOk()->assertSee('5 October 2029');
    expect($request->histories()->orderBy('sequence')->pluck('after_state')->map(fn ($state) => $state['effective_requested_date'] ?? $state['requested_date'])->all())
        ->toBe(['2029-10-01', '2029-10-03', '2029-10-05']);
});

it('amends a 0600 request at 0700 and 0730 without Office action preserving all three events', function () {
    [$user, $office, $request, $site] = liveAmendmentFixture();
    $original = $request->histories()->firstOrFail()->toArray();
    $this->travelTo(CarbonImmutable::parse('2026-09-03 07:00:00'));
    $first = liveAmend($user, $site, $request, '2026-10-05');
    $firstPayload = $first->only(['requested_date', 'requested_by_user_id', 'requester_name', 'opened_at']);
    $this->travelTo(CarbonImmutable::parse('2026-09-03 07:30:00'));
    $second = liveAmend($user, $site, $request->fresh(), '2026-10-07');
    $fresh = $request->fresh();
    expect($fresh->effectiveRequestedDate()->toDateString())->toBe('2026-10-07')
        ->and($fresh->load('dateNegotiations')->effectiveRequestedDate()->toDateString())->toBe('2026-10-07')
        ->and($fresh->requested_date->toDateString())->toBe('2026-10-01')
        ->and($fresh->agreed_date)->toBeNull()
        ->and($fresh->status)->toBe(CallOffRequestStatus::AmendmentOnHold)
        ->and(CallOffRequest::count())->toBe(1)
        ->and($fresh->active_conflict_key)->not->toBeNull()
        ->and($first->fresh()->status)->toBe(CallOffNegotiationStatus::Superseded)
        ->and($first->fresh()->only(array_keys($firstPayload)))->toEqual($firstPayload)
        ->and($second->prior_agreed_date)->toBeNull()
        ->and($request->dateNegotiations()->where('status', 'open')->count())->toBe(1)
        ->and($request->histories()->orderBy('sequence')->first()->toArray())->toBe($original)
        ->and($request->histories()->orderBy('sequence')->pluck('performed_at')->map->format('H:i')->all())->toBe(['06:00', '07:00', '07:30'])
        ->and($request->histories()->orderBy('sequence')->pluck('after_state')->map(fn ($s) => $s['effective_requested_date'] ?? $s['requested_date'])->all())->toBe(['2026-10-01', '2026-10-05', '2026-10-07'])
        ->and(PortalNotification::where('notifiable_user_id', $office->id)->where('type', PortalNotificationType::CallOffAmendmentRequested)->count())->toBe(2);
    $dashboard = app(OfficeDashboardQueryService::class)->forUser($office);
    expect($dashboard['amendmentCount'])->toBe(1)->and($dashboard['amendmentItems']->first()->uuid)->toBe($second->uuid);
    $this->actingAs($user)->withSession(['active_site_id' => $site->id])->get(route('portal.call-offs.show', $request))->assertOk()
        ->assertSee('Original requested date — 2026-10-01')->assertSee('Amended requested date — 2026-10-05')->assertSee('Amended requested date — 2026-10-07');
    expect(fn () => app(AgreeRequestedCallOffDateAction::class)->handle($office, $fresh, false, $first->uuid))->toThrow(ValidationException::class);
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $fresh, false, $second->uuid);
    expect($request->fresh()->agreed_date->toDateString())->toBe('2026-10-07');
});

it('requires a separate early reason before any change and snapshots it when supplied', function () {
    [$user, $office, $request, $site] = liveAmendmentFixture();
    foreach ([null, '', '   '] as $reason) {
        try {
            liveAmend($user, $site, $request, '2026-09-08', $reason);
            $this->fail('Early amendment was accepted without a reason');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('early_date_reason');
        }
    }
    expect($request->dateNegotiations()->count())->toBe(0)->and($request->histories()->count())->toBe(1);
    $cycle = liveAmend($user, $site, $request, '2026-09-08', 'Scaffolding is being removed');
    $history = $request->histories()->latest('sequence')->first()->after_state;
    expect($history['early_date_reason'])->toBe('Scaffolding is being removed')
        ->and($history['normal_earliest_date'])->toBe('2026-10-01')
        ->and($history['working_days_early'])->toBe(17)
        ->and($cycle->is_early_date_exception)->toBeTrue();
    $this->actingAs($office)->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertSee('Early date reason:')->assertSee('Scaffolding is being removed');
});

it('accepts normal amendments without an early reason', function () {
    [$user, , $request, $site] = liveAmendmentFixture();
    $cycle = liveAmend($user, $site, $request, '2026-10-05');
    expect($cycle->is_early_date_exception)->toBeFalse()
        ->and($request->histories()->latest('sequence')->first()->after_state['early_date_reason'])->toBeNull();
});

it('supersedes a pending Office alternative and denies its later acceptance', function () {
    [$user, $office, $request, $site] = liveAmendmentFixture();
    $proposal = app(ProposeAlternativeCallOffDateAction::class)->handle($office, $request, '2026-10-08');
    liveAmend($user, $site, $request->fresh(), '2026-10-05');
    expect(fn () => app(AcceptAlternativeCallOffDateAction::class)->handle($user, $request, $proposal))->toThrow(ValidationException::class);
    expect($proposal->fresh()->status->value)->toBe('superseded')
        ->and($request->fresh()->effectiveRequestedDate()->toDateString())->toBe('2026-10-05');
});

it('retains an agreed date on hold through repeated amendments', function () {
    [$user, $office, $request, $site] = liveAmendmentFixture();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
    liveAmend($user, $site, $request->fresh(), '2026-10-05');
    $second = liveAmend($user, $site, $request->fresh(), '2026-10-07');
    expect($second->prior_agreed_date->toDateString())->toBe('2026-10-01')
        ->and(app(CallOffDateViewService::class)->forRequest($request->fresh(), $user)['agreedDate'])->toBeNull();
    app(AgreeRequestedCallOffDateAction::class)->handle($office, $request, false, $second->uuid);
    expect($request->fresh()->agreed_date->toDateString())->toBe('2026-10-07')
        ->and($request->fresh()->requested_date->toDateString())->toBe('2026-10-01');
});

it('blocks live amendments outside current customer and site authority', function (string $change) {
    [$user, $office, $request, $site] = liveAmendmentFixture();
    match ($change) {
        'revoked' => $user->assignedSites()->detach(),
        'customer' => User::whereKey($user)->update(['customer_organisation_id' => CustomerOrganisation::factory()->create()->id]),
        'inactive' => User::whereKey($user)->update(['is_active' => false]),
        'office' => $user = $office,
    };
    expect(fn () => liveAmend($user, $site, $request, '2026-10-05'))->toThrow(AuthorizationException::class);
    expect($request->histories()->count())->toBe(1)->and($request->dateNegotiations()->count())->toBe(0);
})->with(['revoked', 'customer', 'inactive', 'office']);

it('cannot reopen source-completed or closed-after-reversal requests', function (bool $reversed) {
    [$user, , $request, $site, $service] = liveAmendmentFixture();
    $service->update(['source_completion_observed_at' => $reversed ? null : now()]);
    if ($reversed) {
        $request->update(['status' => CallOffRequestStatus::Completed]);
    }
    expect(fn () => liveAmend($user, $site, $request, '2026-10-05'))->toThrow(ValidationException::class);
    expect($request->dateNegotiations()->count())->toBe(0);
})->with([false, true]);

it('rejects a stale reviewed amendment after another user change and retains the committed date', function () {
    [$user, , $request, $site] = liveAmendmentFixture();
    $this->actingAs($user)->withSession(['active_site_id' => $site->id]);
    $token = $this->post(route('portal.call-offs.amendments.review', $request), ['requested_date' => '2026-10-05', 'reason_code' => 'test_reason'])->assertOk()->viewData('token');
    liveAmend($user, $site, $request, '2026-10-07');
    $this->post(route('portal.call-offs.amendments.store', $request), ['confirmation_token' => $token])->assertSessionHasErrors('request');
    expect($request->fresh()->effectiveRequestedDate()->toDateString())->toBe('2026-10-07')
        ->and($request->dateNegotiations()->count())->toBe(1);
});

it('rolls back superseding and the new amendment atomically without notifications', function () {
    [$user, , $request, $site] = liveAmendmentFixture();
    $first = liveAmend($user, $site, $request, '2026-10-05');
    $notifications = PortalNotification::count();
    expect(fn () => DB::transaction(function () use ($user, $site, $request) {
        liveAmend($user, $site, $request->fresh(), '2026-10-07');
        throw new RuntimeException('Rollback check');
    }))->toThrow(RuntimeException::class);
    expect($first->fresh()->status)->toBe(CallOffNegotiationStatus::Open)
        ->and($request->fresh()->effectiveRequestedDate()->toDateString())->toBe('2026-10-05')
        ->and($request->dateNegotiations()->count())->toBe(1)
        ->and(PortalNotification::count())->toBe($notifications);
});

it('renders the Plot workspace with approved totals and live amendment context', function () {
    [$user, , $request, $site, $service] = liveAmendmentFixture();
    foreach (['CAS' => 5, 'BAY' => 2, 'BF' => 1, 'CDF' => 2, 'MISC' => 99] as $code => $quantity) {
        $service->projectedPlot->products()->create(['product_code' => $code, 'quantity' => $quantity]);
    }
    liveAmend($user, $site, $request, '2026-10-08');
    $response = $this->actingAs($user)->withSession(['active_site_id' => $site->id])->get(route('portal.plots.show', $service->projectedPlot));
    $response->assertOk()->assertSee('Windows total')->assertSee('Doors total')
        ->assertSeeInOrder(['Cavity Closers', 'Windows', 'Snagging', 'CML'])
        ->assertSee('8 October 2026')->assertSee('Awaiting Fenster — Amended')
        ->assertSee('Amend Windows request')->assertSee('Recent amendments')->assertDontSee('No date had been agreed');
    expect($response->viewData('totals')->totalWindows)->toBe('7.000')
        ->and($response->viewData('totals')->totalDoors)->toBe('3.000');
});
