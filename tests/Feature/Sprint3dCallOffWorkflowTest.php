<?php

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\CallOffSubmissionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function sprint3dWorkflowUser(PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): array
{
    $organisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);

    return [$user, $site];
}

function sprint3dPlotWithServices(Site $site, string $reference, array $services): ProjectedPlot
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);

    foreach ($services as $service) {
        ProjectedPlotService::query()->create([
            'projected_plot_id' => $plot->id,
            'service_identifier' => $service,
        ]);
    }

    return $plot;
}

function sprint3dReviewPayload(array $plots, array $dates, array $extra = []): array
{
    return array_merge([
        'plots' => collect($plots)->pluck('uuid')->all(),
        'service_dates' => $dates,
        'customer_response' => 'Please arrange these call-offs.',
    ], $extra);
}

test('the shared controller workflow submits a reviewed request and consumes its confirmation', function (): void {
    [$user, $site] = sprint3dWorkflowUser();
    $plot = sprint3dPlotWithServices($site, 'Plot 301', [CallOffServiceType::Windows]);
    $date = CarbonImmutable::today()->addWeeks(6)->nextWeekday()->toDateString();

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    $this->post(route('portal.call-offs.matrix'), sprint3dReviewPayload([$plot], [CallOffServiceType::Windows->value => $date]))->assertOk();
    $this->post(route('portal.call-offs.review'), sprint3dReviewPayload([$plot], [CallOffServiceType::Windows->value => $date]))->assertOk();
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];

    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
        ->assertRedirect(route('portal.site-dashboard'));

    expect(CallOffBatch::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->first()->requested_date->toDateString())->toBe($date);

    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('request');

    expect(CallOffBatch::query()->count())->toBe(1)
        ->and(CallOffRequest::query()->count())->toBe(1);
});

test('the final endpoint cannot create a call-off without the server-side review', function (): void {
    [$user, $site] = sprint3dWorkflowUser();
    sprint3dPlotWithServices($site, 'Plot 302', [CallOffServiceType::Windows]);

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post(route('portal.call-offs.store'), ['confirmation_signature' => str_repeat('a', 64)])
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('request');

    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

test('reviewed state becomes invalid when an included source service changes', function (): void {
    [$user, $site] = sprint3dWorkflowUser();
    $plot = sprint3dPlotWithServices($site, 'Plot 303', [CallOffServiceType::Windows]);
    $date = CarbonImmutable::today()->addWeeks(6)->nextWeekday()->toDateString();
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    $this->post(route('portal.call-offs.review'), sprint3dReviewPayload([$plot], [CallOffServiceType::Windows->value => $date]))->assertOk();
    $signature = session(CallOffSubmissionWorkflow::SESSION_KEY)['signature'];
    $plot->services()->where('service_identifier', CallOffServiceType::Windows->value)->update(['source_present' => false]);

    $this->post(route('portal.call-offs.store'), ['confirmation_signature' => $signature])
        ->assertRedirect(route('portal.call-offs.create'))
        ->assertSessionHasErrors('request');

    expect(CallOffBatch::query()->count())->toBe(0)->and(CallOffRequest::query()->count())->toBe(0);
});

test('office staff cannot use customer call-off routes and cross-site plot UUIDs are rejected', function (): void {
    [$office, $site] = sprint3dWorkflowUser(PortalRoleIdentifier::FensterOfficeStaff);
    $this->actingAs($office)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.call-offs.create'))
        ->assertForbidden();

    [$user, $userSite] = sprint3dWorkflowUser();
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $otherPlot = sprint3dPlotWithServices($otherSite, 'Plot 304', [CallOffServiceType::Windows]);
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $userSite->id])
        ->post(route('portal.call-offs.dashboard-selection'), ['plots' => [$otherPlot->uuid]])
        ->assertSessionHasErrors('plots');
});

test('matrix construction uses bounded eager-loaded queries for fifteen plots and four services', function (): void {
    [$user, $site] = sprint3dWorkflowUser();
    $plots = collect(range(1, 15))->map(function (int $number) use ($site): ProjectedPlot {
        return sprint3dPlotWithServices($site, 'Plot '.(400 + $number), CallOffServiceType::cases());
    });
    $date = CarbonImmutable::today()->addWeeks(6)->nextWeekday()->toDateString();
    $dates = collect(CallOffServiceType::cases())->mapWithKeys(fn (CallOffServiceType $service): array => [$service->value => $date])->all();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = app(BuildCallOffMatrixAction::class)->handle($user, $site, $plots->pluck('uuid')->all(), $dates);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($rows)->toHaveCount(60)->and($queryCount)->toBeLessThanOrEqual(8);
});
