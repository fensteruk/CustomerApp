<?php

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Actions\CallOff\SubmitMultiCallOffBatchAction;
use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffSubmissionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function sprint3dQaUser(): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);

    return [$user, $site];
}

function sprint3dQaPlot(Site $site, string $reference, bool $allServices = true): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);

    if ($allServices) {
        foreach (CallOffServiceType::cases() as $service) {
            ProjectedPlotService::query()->create([
                'projected_plot_id' => $plot->id,
                'service_identifier' => $service,
            ]);
        }
    }

    return $plot;
}

function sprint3dQaDates(int $weeks = 6): array
{
    $date = CarbonImmutable::today()->addWeeks($weeks)->nextWeekday()->toDateString();

    return collect(CallOffServiceType::cases())
        ->mapWithKeys(fn (CallOffServiceType $service): array => [$service->value => $date])
        ->all();
}

function sprint3dQaPayload(array $plots, array $dates, array $extra = []): array
{
    return array_merge([
        'plots' => collect($plots)->pluck('uuid')->all(),
        'service_dates' => $dates,
        'customer_response' => 'Fictional QA request.',
    ], $extra);
}

test('the matrix keeps all four services in order and safely distinguishes each unavailable cause', function (): void {
    [$user, $site] = sprint3dQaUser();
    $standard = sprint3dQaPlot($site, 'Plot 12');
    $bifold = sprint3dQaPlot($site, 'Plot 13');
    $unavailable = sprint3dQaPlot($site, 'Plot 14');
    $normal = sprint3dQaPlot($site, 'Plot 15');
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $standard->id, 'product_code' => 'CAS', 'quantity' => 2]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $standard->id, 'product_code' => 'PFD', 'quantity' => 1]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $standard->id, 'product_code' => 'BF', 'quantity' => 0]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $bifold->id, 'product_code' => 'BF', 'quantity' => 1]);

    $cavity = $unavailable->services()->where('service_identifier', CallOffServiceType::CavityClosers->value)->firstOrFail();
    $windows = $unavailable->services()->where('service_identifier', CallOffServiceType::Windows->value)->firstOrFail();
    $snagging = $unavailable->services()->where('service_identifier', CallOffServiceType::Snagging->value)->firstOrFail();
    $windows->update(['source_completed_at' => today()]);
    $snagging->update(['source_present' => false]);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);
    CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $unavailable->id,
        'projected_plot_service_id' => $cavity->id,
        'service_identifier' => CallOffServiceType::CavityClosers,
        'status' => CallOffRequestStatus::DateAgreed,
        'active_conflict_key' => UpdateConflictKeyAction::keyFor($unavailable->id, CallOffServiceType::CavityClosers->value),
    ]);

    $rows = app(BuildCallOffMatrixAction::class)->handle($user, $site, [$standard->uuid, $bifold->uuid, $unavailable->uuid, $normal->uuid], sprint3dQaDates());

    expect($rows)->toHaveCount(16)
        ->and(collect($rows)->where('plot_reference', 'Plot 12')->pluck('service')->all())->toBe(array_map(fn (CallOffServiceType $service) => $service->value, CallOffServiceType::cases()))
        ->and(collect($rows)->where('plot_reference', 'Plot 12')->first()['products'])->toHaveCount(2)
        ->and(collect($rows)->where('plot_reference', 'Plot 12')->first()['products'])->toContain(['code' => 'CAS', 'quantity' => '2.000'])
        ->and(collect($rows)->where('plot_reference', 'Plot 12')->first()['products'])->toContain(['code' => 'PFD', 'quantity' => '1.000'])
        ->and(collect($rows)->firstWhere('key', $unavailable->uuid.'|cavity_closers')['reason'])->toBe('A selected plot already has an active request for this service.')
        ->and(collect($rows)->firstWhere('key', $unavailable->uuid.'|windows')['reason'])->toBe('This completed plot service cannot receive a new call-off.')
        ->and(collect($rows)->firstWhere('key', $unavailable->uuid.'|snagging')['reason'])->toBe('Source information is not available for this plot service.')
        ->and(collect($rows)->firstWhere('key', $bifold->uuid.'|windows')['normal_earliest_date'])->not->toBe(collect($rows)->firstWhere('key', $standard->uuid.'|windows')['normal_earliest_date']);
});

test('a mixed-date multi-service review persists only included combinations with per-request history', function (): void {
    [$user, $site] = sprint3dQaUser();
    $standard = sprint3dQaPlot($site, 'Plot 12');
    $bifold = sprint3dQaPlot($site, 'Plot 13');
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $bifold->id, 'product_code' => 'BF', 'quantity' => 1]);
    $dates = sprint3dQaDates();
    $dates[CallOffServiceType::Windows->value] = CarbonImmutable::today()->addWeeks(4)->nextWeekday()->toDateString();
    $earlyKey = $bifold->uuid.'|'.CallOffServiceType::Windows->value;

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$standard, $bifold], $dates, [
        'excluded' => [$standard->uuid.'|'.CallOffServiceType::Cml->value],
        'early_reasons' => [$earlyKey => 'Fictional customer needs this earlier date.'],
    ]))->assertOk()
        ->assertSee('7 service requests will be submitted.')
        ->assertSeeInOrder([
            'Plot 12 · Cavity Closers',
            'Plot 12 · Windows',
            'Plot 12 · Snagging',
            'Plot 12 · CML',
            'Plot 13 · Cavity Closers',
            'Plot 13 · Windows',
            'Plot 13 · Snagging',
            'Plot 13 · CML',
        ]);
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];

    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])->assertRedirect(route('portal.site-dashboard'));

    expect(CallOffBatch::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->count())->toBe(7)
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::DateRequested)->count())->toBe(7)
        ->and(CallOffRequest::query()->where('is_early_date_exception', true)->count())->toBe(1)
        ->and(CallOffRequest::query()->where('early_date_reason', 'Fictional customer needs this earlier date.')->count())->toBe(1)
        ->and(CallOffRequest::query()->where('projected_plot_id', $standard->id)->where('service_identifier', CallOffServiceType::Cml)->count())->toBe(0);
});

test('early-date exceptions, excluded rows and invalid requested dates are enforced server-side', function (): void {
    [$user, $site] = sprint3dQaUser();
    $plot = sprint3dQaPlot($site, 'Plot 13');
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'BF', 'quantity' => 1]);
    $dates = [CallOffServiceType::Windows->value => CarbonImmutable::today()->addWeeks(4)->nextWeekday()->toDateString()];

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$plot], $dates))
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('early_reasons.'.$plot->uuid.'|windows');
    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$plot], $dates, ['excluded' => [$plot->uuid.'|windows']]))
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('combinations');
    $this->post(route('portal.call-offs.matrix'), sprint3dQaPayload([$plot], [CallOffServiceType::Windows->value => CarbonImmutable::today()->next('Saturday')->toDateString()]))
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('service_dates');
    $this->post(route('portal.call-offs.matrix'), sprint3dQaPayload([$plot], [CallOffServiceType::Windows->value => CarbonImmutable::today()->addMonths(7)->nextWeekday()->toDateString()]))
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('service_dates');
});

test('confirmation tampering, stale access and an invalid transactional item cannot create a partial batch', function (): void {
    [$user, $site] = sprint3dQaUser();
    $first = sprint3dQaPlot($site, 'Plot 12');
    $second = sprint3dQaPlot($site, 'Plot 15');
    $dates = [CallOffServiceType::Windows->value => sprint3dQaDates()[CallOffServiceType::Windows->value]];
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $tamper = [
        'message' => static function (array &$payload): void {
            $payload['message'] = 'Tampered after review.';
        },
        'row date' => static function (array &$payload): void {
            $payload['rows'][0]['requested_date'] = '2026-12-31';
        },
        'row inclusion' => static function (array &$payload): void {
            $payload['rows'][0]['included'] = false;
        },
        'plot identity' => static function (array &$payload): void {
            $payload['rows'][0]['plot_uuid'] = (string) str()->uuid();
        },
        'reviewed user' => static function (array &$payload): void {
            $payload['user_id']++;
        },
        'reviewed site' => static function (array &$payload): void {
            $payload['site_id']++;
        },
        'signature' => static function (array &$payload): void {
            $payload['signature'] = str_repeat('a', 64);
        },
    ];
    foreach ($tamper as $mutation) {
        $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$first, $second], $dates))->assertOk();
        $payload = session(CallOffSubmissionWorkflow::SESSION_KEY);
        $signature = $payload['signature'];
        $mutation($payload);
        $this->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id, CallOffSubmissionWorkflow::SESSION_KEY => $payload])
            ->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
            ->assertRedirect(route('portal.call-offs.create'))
            ->assertSessionHasErrors();
    }
    expect(CallOffBatch::query()->count())->toBe(0);

    $items = $first->services()->where('service_identifier', CallOffServiceType::Windows->value)->get()
        ->concat($second->services()->where('service_identifier', CallOffServiceType::Windows->value)->get())
        ->values()
        ->map(fn (ProjectedPlotService $service, int $index): array => [
            'plot_service_id' => $service->id,
            'requested_date' => $index === 0 ? sprint3dQaDates()[CallOffServiceType::Windows->value] : CarbonImmutable::today()->next('Saturday')->toDateString(),
            'early_date_reason' => null,
        ])->all();
    expect(fn () => app(SubmitMultiCallOffBatchAction::class)->handle($user, $site, $items, 'Atomic QA failure.'))->toThrow(ValidationException::class);
    expect(CallOffBatch::query()->count())->toBe(0)->and(CallOffRequest::query()->count())->toBe(0);

    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$first, $second], $dates))->assertOk();
    $payload = session(CallOffSubmissionWorkflow::SESSION_KEY);
    $user->assignedSites()->detach($site);
    $this->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id, CallOffSubmissionWorkflow::SESSION_KEY => $payload])
        ->post(route('portal.call-offs.store'), ['confirmation_signature' => $payload['signature']])
        ->assertRedirect(route('sites.select'));
    expect(CallOffBatch::query()->count())->toBe(0);
});

test('completion, missing-source, BF lead-time and inactive-account changes invalidate a reviewed submission', function (): void {
    [$user, $site] = sprint3dQaUser();
    $plot = sprint3dQaPlot($site, 'Plot 13');
    $service = $plot->services()->where('service_identifier', CallOffServiceType::Windows->value)->firstOrFail();
    $dates = [CallOffServiceType::Windows->value => CarbonImmutable::today()->addWeeks(4)->nextWeekday()->toDateString()];
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    foreach ([
        'completion' => static fn (): int => $service->update(['source_completed_at' => today()]),
        'missing source' => static fn (): int => $service->update(['source_present' => false]),
        'BF lead time' => static fn (): bool => (bool) ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'BF', 'quantity' => 1]),
    ] as $change) {
        $service->update(['source_completed_at' => null, 'source_present' => true]);
        $plot->products()->delete();
        $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$plot], $dates))->assertOk();
        $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
        $change();
        $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
            ->assertRedirect(route('portal.call-offs.create'))
            ->assertSessionHasErrors();
        expect(CallOffBatch::query()->count())->toBe(0);
    }

    $service->update(['source_completed_at' => null, 'source_present' => true]);
    $plot->products()->delete();
    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$plot], $dates))->assertOk();
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
    $user->update(['is_active' => false]);
    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
        ->assertRedirect(route('login'));
    expect(CallOffBatch::query()->count())->toBe(0);
});

test('a newly active conflict invalidates a reviewed multi-request submission without a partial batch', function (): void {
    [$user, $site] = sprint3dQaUser();
    $first = sprint3dQaPlot($site, 'Plot 12');
    $second = sprint3dQaPlot($site, 'Plot 15');
    $dates = [CallOffServiceType::Windows->value => sprint3dQaDates()[CallOffServiceType::Windows->value]];
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->post(route('portal.call-offs.review'), sprint3dQaPayload([$first, $second], $dates))->assertOk();
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
    $service = $first->services()->where('service_identifier', CallOffServiceType::Windows->value)->firstOrFail();
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);
    CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $first->id,
        'projected_plot_service_id' => $service->id,
        'service_identifier' => CallOffServiceType::Windows,
        'status' => CallOffRequestStatus::AwaitingFenster,
        'active_conflict_key' => UpdateConflictKeyAction::keyFor($first->id, CallOffServiceType::Windows->value),
    ]);

    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors();
    expect(CallOffBatch::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->count())->toBe(1)
        ->and(CallOffStatusHistory::query()->count())->toBe(0);
});

test('matrix construction remains bounded for ten plots and four services', function (): void {
    [$user, $site] = sprint3dQaUser();
    $plots = collect(range(1, 10))->map(fn (int $number): ProjectedPlot => sprint3dQaPlot($site, 'Plot '.(100 + $number)));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = app(BuildCallOffMatrixAction::class)->handle($user, $site, $plots->pluck('uuid')->all(), sprint3dQaDates());
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($rows)->toHaveCount(40)->and($queryCount)->toBeLessThanOrEqual(8);
});

test('the temporary per-request notifications use each multi-service request context', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-21 12:00:00'));
    [$user, $site] = sprint3dQaUser();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $plot = sprint3dQaPlot($site, 'Plot 12');
    $dates = ['2026-10-02', '2026-10-05', '2026-10-06', '2026-10-07'];
    $items = collect(CallOffServiceType::cases())->map(function (CallOffServiceType $service, int $index) use ($dates, $plot): array {
        return [
            'plot_service_id' => $plot->services()->where('service_identifier', $service->value)->value('id'),
            'requested_date' => $dates[$index],
            'early_date_reason' => null,
        ];
    })->all();

    $batch = app(SubmitMultiCallOffBatchAction::class)->handle($user, $site, $items, 'Notification QA.');
    $notifications = PortalNotification::query()->where('batch_uuid', $batch->uuid)->get();

    expect($notifications)->toHaveCount(8)
        ->and($notifications->where('notifiable_user_id', $user->id))->toHaveCount(4)
        ->and($notifications->where('notifiable_user_id', $office->id))->toHaveCount(4)
        ->and($notifications->pluck('service_identifier')->sort()->values()->all())->toBe([
            CallOffServiceType::CavityClosers->value,
            CallOffServiceType::CavityClosers->value,
            CallOffServiceType::Cml->value,
            CallOffServiceType::Cml->value,
            CallOffServiceType::Snagging->value,
            CallOffServiceType::Snagging->value,
            CallOffServiceType::Windows->value,
            CallOffServiceType::Windows->value,
        ])
        ->and($notifications->pluck('requested_date')->unique())->toHaveCount(4)
        ->and($notifications->pluck('current_status')->unique()->all())->toBe([CallOffRequestStatus::AwaitingFenster->value]);
});

test('each external portal role can submit for an assigned site and remains the recorded actor', function (): void {
    foreach ([
        PortalRoleIdentifier::SiteManager,
        PortalRoleIdentifier::AssistantSiteManager,
        PortalRoleIdentifier::FinishingForeman,
    ] as $role) {
        $organisation = CustomerOrganisation::factory()->create();
        $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
        $user = User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
        $user->assignedSites()->attach($site);
        $plot = sprint3dQaPlot($site, 'Plot '.$role->value);
        $service = $plot->services()->where('service_identifier', CallOffServiceType::Windows->value)->firstOrFail();

        $batch = app(SubmitMultiCallOffBatchAction::class)->handle($user, $site, [[
            'plot_service_id' => $service->id,
            'requested_date' => sprint3dQaDates()[CallOffServiceType::Windows->value],
            'early_date_reason' => null,
        ]]);
        $request = $batch->requests->firstOrFail();

        expect($request->status)->toBe(CallOffRequestStatus::AwaitingFenster)
            ->and($request->histories()->sole()->performed_by_user_id)->toBe($user->id);
    }
});
