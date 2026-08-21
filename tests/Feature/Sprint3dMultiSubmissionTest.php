<?php

use App\Actions\CallOff\SubmitMultiCallOffBatchAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('one multi-service batch creates independently dated request items atomically', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    PortalRole::firstOrCreate(['identifier' => PortalRoleIdentifier::SiteManager->value], ['name' => 'Site Manager']);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $windows = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    $snagging = ProjectedPlotService::create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Snagging]);

    $batch = app(SubmitMultiCallOffBatchAction::class)->handle($user, $site, [
        ['plot_service_id' => $windows->id, 'requested_date' => CarbonImmutable::today()->addWeeks(5)->toDateString(), 'early_date_reason' => null],
        ['plot_service_id' => $snagging->id, 'requested_date' => CarbonImmutable::today()->addWeeks(6)->toDateString(), 'early_date_reason' => null],
    ], 'Please arrange these services.');

    expect($batch->requests)->toHaveCount(2)
        ->and($batch->requests->pluck('status')->unique()->first())->toBe(CallOffRequestStatus::AwaitingFenster)
        ->and($batch->requests->pluck('requested_date')->map->toDateString()->all())->toEqualCanonicalizing([
            CarbonImmutable::today()->addWeeks(5)->toDateString(), CarbonImmutable::today()->addWeeks(6)->toDateString(),
        ]);
});
