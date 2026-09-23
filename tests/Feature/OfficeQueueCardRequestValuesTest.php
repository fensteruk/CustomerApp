<?php

use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function queueCardFixture(): array
{
    $customer = CustomerOrganisation::factory()->create();
    $submitter = User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'Queue Card Submitter',
    ]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);
    $site = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'Queue Card Site',
    ]);
    $submitter->assignedSites()->attach($site);
    $batch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $submitter->id,
        'service_identifier' => CallOffServiceType::Windows,
        'requested_date' => '2026-10-01',
        'submitted_at' => '2026-09-10 09:00:00',
    ]);

    return [$office, $submitter, $site, $batch];
}

function queueCardRequest(
    Site $site,
    CallOffBatch $batch,
    string $plotReference,
    CallOffServiceType $service,
    string $requestedDate,
    CallOffRequestStatus $status = CallOffRequestStatus::AwaitingFenster,
): CallOffRequest {
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'plot_reference' => $plotReference,
    ]);
    $plotService = ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => $service,
        'source_present' => true,
    ]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id,
        'projected_plot_id' => $plot->id,
        'projected_plot_service_id' => $plotService->id,
        'service_identifier' => $service,
        'requested_date' => $requestedDate,
        'status' => $status,
    ]);
}

it('renders each queue card from its individual request rather than shared batch values', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    $requests = [
        queueCardRequest($site, $batch, 'Queue Plot A', CallOffServiceType::Windows, '2026-10-05'),
        queueCardRequest($site, $batch, 'Queue Plot B', CallOffServiceType::CavityClosers, '2026-10-12'),
        queueCardRequest($site, $batch, 'Queue Plot C', CallOffServiceType::Cml, '2026-10-19'),
    ];

    $queue = $this->actingAs($office)->get(route('portal.review-requests', [
        'status' => CallOffRequestStatus::AwaitingFenster->value,
    ]));

    $queue->assertOk()
        ->assertSeeInOrder(['Queue Plot C', 'CML', '19 Oct 2026'])
        ->assertSeeInOrder(['Queue Plot B', 'Cavity Closers', '12 Oct 2026'])
        ->assertSeeInOrder(['Queue Plot A', 'Windows', '5 Oct 2026']);

    foreach ($requests as $request) {
        $this->actingAs($office)
            ->get(route('portal.review-requests.show', $request))
            ->assertOk()
            ->assertSee($request->effectiveServiceIdentifier()->label())
            ->assertSee($request->requested_date->format('j M Y'));
    }
});

it('keeps dates request-specific when sibling requests use the same service', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    queueCardRequest($site, $batch, 'Same Service Later', CallOffServiceType::Windows, '2026-10-20');
    queueCardRequest($site, $batch, 'Same Service Earlier', CallOffServiceType::Windows, '2026-10-06');

    $this->actingAs($office)
        ->get(route('portal.review-requests', ['status' => CallOffRequestStatus::AwaitingFenster->value]))
        ->assertOk()
        ->assertSeeInOrder(['Same Service Earlier', 'Windows', '6 Oct 2026'])
        ->assertSeeInOrder(['Same Service Later', 'Windows', '20 Oct 2026']);
});

it('keeps services request-specific when sibling requests use the same date', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    queueCardRequest($site, $batch, 'Same Date CML', CallOffServiceType::Cml, '2026-10-13');
    queueCardRequest($site, $batch, 'Same Date Windows', CallOffServiceType::Windows, '2026-10-13');

    $this->actingAs($office)
        ->get(route('portal.review-requests', ['status' => CallOffRequestStatus::AwaitingFenster->value]))
        ->assertOk()
        ->assertSeeInOrder(['Same Date Windows', 'Windows', '13 Oct 2026'])
        ->assertSeeInOrder(['Same Date CML', 'CML', '13 Oct 2026']);
});

it('filters by request service without allowing a sibling batch value to leak', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    queueCardRequest($site, $batch, 'Filtered Windows', CallOffServiceType::Windows, '2026-10-05');
    queueCardRequest($site, $batch, 'Filtered Cavity', CallOffServiceType::CavityClosers, '2026-10-12');
    queueCardRequest($site, $batch, 'Filtered CML', CallOffServiceType::Cml, '2026-10-19');

    $this->actingAs($office)
        ->get(route('portal.review-requests', [
            'status' => CallOffRequestStatus::AwaitingFenster->value,
            'service' => CallOffServiceType::CavityClosers->value,
        ]))
        ->assertOk()
        ->assertSee('Filtered Cavity')
        ->assertDontSee('Filtered Windows')
        ->assertDontSee('Filtered CML')
        ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 1);

    $this->actingAs($office)
        ->get(route('portal.review-requests', [
            'status' => CallOffRequestStatus::AwaitingFenster->value,
            'service' => CallOffServiceType::Windows->value,
        ]))
        ->assertOk()
        ->assertSee('Filtered Windows')
        ->assertDontSee('Filtered Cavity')
        ->assertDontSee('Filtered CML')
        ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 1);
});

it('retains the legacy batch fallback for unmigrated service and date values', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    $legacy = queueCardRequest($site, $batch, 'Legacy Queue Plot', CallOffServiceType::Cml, '2026-10-19');
    $legacy->forceFill(['service_identifier' => null, 'requested_date' => null])->save();

    $this->actingAs($office)
        ->get(route('portal.review-requests', [
            'status' => CallOffRequestStatus::AwaitingFenster->value,
            'service' => CallOffServiceType::Windows->value,
        ]))
        ->assertOk()
        ->assertSeeInOrder(['Legacy Queue Plot', 'Windows', '1 Oct 2026'])
        ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 1);
});

it('keeps Requested date semantics across negotiation and amendment states', function (string $scenario, CallOffRequestStatus $status): void {
    [$office, , $site, $batch] = queueCardFixture();
    $request = queueCardRequest($site, $batch, 'Lifecycle '.$scenario, CallOffServiceType::Windows, '2026-10-05', $status);
    $otherDate = null;

    if ($scenario === 'alternative') {
        $cycle = CallOffDateNegotiation::query()->create([
            'call_off_request_id' => $request->id,
            'purpose' => 'initial',
            'status' => 'open',
            'active_negotiation_key' => 'queue-card-initial-'.$request->id,
            'requested_date' => '2026-10-05',
            'opened_at' => '2026-09-10 09:00:00',
        ]);
        CallOffDateProposal::query()->create([
            'call_off_date_negotiation_id' => $cycle->id,
            'sequence' => 1,
            'proposal_type' => 'fenster_alternative_date',
            'status' => 'awaiting_response',
            'proposed_date' => $otherDate = '2026-10-12',
            'proposed_by_user_id' => $office->id,
            'proposed_at' => '2026-09-10 10:00:00',
        ]);
    } elseif ($scenario === 'agreed') {
        $request->forceFill(['agreed_date' => $otherDate = '2026-10-13'])->save();
    } elseif ($scenario === 'amendment') {
        CallOffDateNegotiation::query()->create([
            'call_off_request_id' => $request->id,
            'purpose' => 'amendment',
            'status' => 'open',
            'active_negotiation_key' => 'queue-card-amendment-'.$request->id,
            'prior_agreed_date' => '2026-10-13',
            'requested_date' => $otherDate = '2026-10-20',
            'reason_code' => 'PROGRAMME_CHANGE',
            'reason_label' => 'Programme Change',
            'opened_at' => '2026-09-10 11:00:00',
        ]);
    }

    $queue = $this->actingAs($office)->get(route('portal.review-requests', ['status' => $status->value]));
    $queue->assertOk();
    if ($scenario === 'amendment') {
        $queue->assertDontSee('Lifecycle amendment')->assertViewHas('requests', fn ($rows) => $rows->total() === 0);
    } else {
        $queue->assertSeeInOrder(['Lifecycle '.$scenario, 'Windows', '5 Oct 2026']);
    }

    if ($otherDate !== null && $scenario !== 'amendment') {
        $queue->assertDontSee(Carbon::parse($otherDate)->format('j M Y'));
    }

    $detail = $this->actingAs($office)->get(route('portal.review-requests.show', $request));
    $detail->assertOk()->assertSeeInOrder(['Requested date', $scenario === 'amendment' ? '20 Oct 2026' : '5 Oct 2026']);
    if ($otherDate !== null) {
        $detail->assertSee(Carbon::parse($otherDate)->format('j M Y'));
    }
})->with([
    'awaiting Fenster' => ['awaiting', CallOffRequestStatus::AwaitingFenster],
    'proposed alternative' => ['alternative', CallOffRequestStatus::AwaitingSiteUser],
    'Date Agreed' => ['agreed', CallOffRequestStatus::DateAgreed],
    'amendment on hold' => ['amendment', CallOffRequestStatus::AmendmentOnHold],
]);

it('preserves pagination and uses a bounded queue query count', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    queueCardRequest($site, $batch, 'Query Plot 01', CallOffServiceType::Windows, '2026-10-05');

    $this->actingAs($office)
        ->get(route('portal.review-requests', ['status' => CallOffRequestStatus::AwaitingFenster->value]))
        ->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($office)
        ->get(route('portal.review-requests', ['status' => CallOffRequestStatus::AwaitingFenster->value]))
        ->assertOk();
    $singleCardQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(2, 11) as $number) {
        queueCardRequest(
            $site,
            $batch,
            sprintf('Query Plot %02d', $number),
            $number % 2 === 0 ? CallOffServiceType::Cml : CallOffServiceType::Windows,
            '2026-10-'.str_pad((string) ($number + 5), 2, '0', STR_PAD_LEFT),
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $pageOne = $this->actingAs($office)->get(route('portal.review-requests', [
        'status' => CallOffRequestStatus::AwaitingFenster->value,
    ]));
    $tenCardQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $pageOne->assertOk()
        ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 11
            && $requests->currentPage() === 1
            && $requests->count() === 10);
    $this->actingAs($office)
        ->get(route('portal.review-requests', [
            'status' => CallOffRequestStatus::AwaitingFenster->value,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 11
            && $requests->currentPage() === 2
            && $requests->count() === 1);
    expect($tenCardQueries)->toBe($singleCardQueries);
});

it('does not change the Office boundary', function (): void {
    [, $siteUser, $site, $batch] = queueCardFixture();
    queueCardRequest($site, $batch, 'Protected Queue Plot', CallOffServiceType::Windows, '2026-10-05');

    $this->actingAs($siteUser)
        ->get(route('portal.review-requests', ['status' => CallOffRequestStatus::AwaitingFenster->value]))
        ->assertForbidden();
});

it('combines customer site service search and request-specific date filters', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    $wanted = queueCardRequest($site, $batch, 'Find Plot 01', CallOffServiceType::Windows, '2026-10-05');
    queueCardRequest($site, $batch, 'Find Plot 02', CallOffServiceType::Windows, '2026-10-06');
    $this->actingAs($office)->get(route('portal.review-requests', [
        'customer' => $site->customer_organisation_id, 'site' => $site->id,
        'service' => 'windows', 'search' => 'Find Plot', 'date' => '2026-10-05',
    ]))->assertOk()->assertViewHas('requests', fn ($rows) => $rows->pluck('id')->all() === [$wanted->id]);
    $this->get(route('portal.review-requests', ['date' => 'not-a-date']))->assertSessionHasErrors('date');
});

it('keeps ordinary attention separate while direct amended detail retains the current decision UUID', function (): void {
    [$office, , $site, $batch] = queueCardFixture();
    $ordinary = queueCardRequest($site, $batch, 'Ordinary attention', CallOffServiceType::Windows, '2026-10-05');
    $changed = queueCardRequest($site, $batch, 'Changed attention', CallOffServiceType::Windows, '2026-10-06', CallOffRequestStatus::AmendmentOnHold);
    $amendment = CallOffDateNegotiation::query()->create([
        'call_off_request_id' => $changed->id, 'purpose' => 'amendment', 'status' => 'open',
        'active_negotiation_key' => 'final04-'.$changed->id, 'requested_date' => '2026-10-20',
        'reason_code' => 'PROGRAMME_CHANGE', 'reason_label' => 'Programme Change', 'opened_at' => now(),
    ]);
    $this->actingAs($office)->get(route('portal.review-requests'))->assertOk()
        ->assertViewHas('requests', fn ($rows) => $rows->pluck('id')->all() === [$ordinary->id]);
    $this->get(route('portal.review-requests.show', $changed))->assertOk()
        ->assertSee('20 Oct 2026')->assertSee('View in Amendments')
        ->assertSee('name="negotiation_uuid" value="'.$amendment->uuid.'"', false);
});
