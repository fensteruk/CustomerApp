<?php

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\OfficeAmendmentsWorkspaceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function amendmentsOffice(array $attributes = []): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false, ...$attributes,
    ]);
}

function amendmentsRequest(?Site $site = null, array $attributes = []): CallOffRequest
{
    $site ??= Site::factory()->create();

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => CallOffBatch::factory()->create(['site_id' => $site->id])->id,
        'projected_plot_id' => ProjectedPlot::factory()->create(['site_id' => $site->id])->id,
        'service_identifier' => CallOffServiceType::Windows,
        'status' => CallOffRequestStatus::AmendmentOnHold, ...$attributes,
    ]);
}

function amendmentsCycle(CallOffRequest $request, array $attributes = []): CallOffDateNegotiation
{
    return $request->dateNegotiations()->create([
        'purpose' => CallOffNegotiationPurpose::Amendment, 'status' => CallOffNegotiationStatus::Open,
        'prior_agreed_date' => '2026-10-01', 'requested_date' => '2026-10-05',
        'opened_at' => '2026-09-23 07:30:00', 'requester_name' => 'Sarah <Miller>',
        'reason_label' => 'Programme Change', ...$attributes,
    ]);
}

it('allows real Office with no customer and denies guest external inactive and preview access', function (): void {
    $url = route('office.workspace.amendments.index');
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(amendmentsOffice())->get($url)->assertOk();
    foreach ([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get($url)->assertForbidden();
        expect(fn () => app(OfficeAmendmentsWorkspaceQuery::class)->forUser($user))->toThrow(AuthorizationException::class);
    }
    $this->actingAs(amendmentsOffice(['is_preview_user' => true]))->get($url)->assertForbidden();
    $this->actingAs(amendmentsOffice(['is_active' => false]))->get($url)->assertRedirect(route('login'));
});

it('renders an intentional empty state without pretending to track RedZebra updates', function (): void {
    $this->actingAs(amendmentsOffice())->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertSee('No amendments need attention')->assertDontSee('Synced today')->assertDontSee('Mark updated')
        ->assertViewHas('counts', ['attention' => 0, 'waiting' => 0, 'closed' => 0]);
});

it('shows one latest amendment per request ordered newest first with stable ties and no ordinary calloffs', function (): void {
    $office = amendmentsOffice();
    $first = amendmentsRequest();
    amendmentsCycle($first, ['opened_at' => '2026-09-23 06:00:00', 'status' => CallOffNegotiationStatus::Superseded]);
    amendmentsCycle($first, ['opened_at' => '2026-09-23 07:00:00', 'status' => CallOffNegotiationStatus::Superseded]);
    $latest = amendmentsCycle($first);
    $tie = amendmentsCycle(amendmentsRequest());
    $older = amendmentsCycle(amendmentsRequest(), ['opened_at' => '2026-09-22 06:00:00']);
    amendmentsRequest(attributes: ['status' => CallOffRequestStatus::AwaitingFenster]);
    amendmentsCycle(amendmentsRequest(), ['purpose' => CallOffNegotiationPurpose::Initial]);
    $this->actingAs($office)->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertViewHas('amendments', fn ($rows) => $rows->pluck('id')->all() === [$tie->id, $latest->id, $older->id])
        ->assertViewHas('counts', ['attention' => 3, 'waiting' => 0, 'closed' => 0]);
});

it('renders context exact dates recorded actor and early and urgent warnings without private reasons', function (): void {
    $site = Site::factory()->create(['name' => 'Willow Park', 'customer_organisation_id' => CustomerOrganisation::factory()->create(['name' => 'Example Homes'])->id]);
    $request = amendmentsRequest($site);
    $request->projectedPlot->update(['plot_reference' => '591']);
    amendmentsCycle($request, ['is_early_date_exception' => true, 'normal_earliest_date' => '2026-10-20', 'is_urgent' => true, 'internal_reason' => 'PRIVATE ONLY']);
    $this->actingAs(amendmentsOffice())->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertSee('Example Homes')->assertSee('Willow Park')->assertSee('Plot 591')->assertSee('Windows')
        ->assertSee('1 Oct 2026')->assertSee('5 Oct 2026')->assertSee('Sarah &lt;Miller&gt;', false)
        ->assertSee('23 Sep 2026, 07:30')->assertSee('Amendment On Hold')
        ->assertSee('Early date requested')->assertSee('20 Oct 2026')->assertSee('Urgent / late amendment')
        ->assertSee('This page does not send updates')->assertDontSee('PRIVATE ONLY');
});

it('separates waiting and closed cycles and retains trashed history in all view', function (): void {
    $office = amendmentsOffice();
    $waiting = amendmentsCycle(amendmentsRequest());
    $waiting->proposals()->create([
        'sequence' => 1, 'proposal_type' => CallOffDateProposalType::FensterAlternativeDate,
        'status' => CallOffDateProposalStatus::AwaitingResponse, 'proposed_date' => '2026-10-10',
        'proposed_by_user_id' => $office->id, 'proposed_at' => now(),
    ]);
    $closed = amendmentsCycle(amendmentsRequest(attributes: ['status' => CallOffRequestStatus::Completed]), ['status' => CallOffNegotiationStatus::Completed]);
    $trashed = amendmentsCycle(amendmentsRequest(attributes: ['trashed_at' => now()]));
    $this->actingAs($office)->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertViewHas('amendments', fn ($rows) => $rows->isEmpty())
        ->assertViewHas('counts', ['attention' => 0, 'waiting' => 1, 'closed' => 1]);
    $this->get(route('office.workspace.amendments.index', ['status' => 'waiting']))->assertOk()
        ->assertViewHas('amendments', fn ($rows) => $rows->pluck('id')->all() === [$waiting->id]);
    $this->get(route('office.workspace.amendments.index', ['status' => 'closed']))->assertOk()->assertSee('Closed by source completion')
        ->assertViewHas('amendments', fn ($rows) => $rows->pluck('id')->all() === [$closed->id]);
    $this->get(route('office.workspace.amendments.index', ['status' => 'all', 'request' => $trashed->callOffRequest->uuid]))->assertOk()
        ->assertSee('In Trash')->assertViewHas('amendments', fn ($rows) => $rows->total() === 3);
});

it('filters customer site service and literal search including legacy service fallback', function (): void {
    $site = Site::factory()->create(['name' => 'Literal 100% Park']);
    $target = amendmentsRequest($site, ['service_identifier' => null]);
    $target->batch->update(['service_identifier' => CallOffServiceType::CavityClosers]);
    $cycle = amendmentsCycle($target);
    amendmentsCycle(amendmentsRequest());
    $filters = ['customer' => $site->customerOrganisation->uuid, 'site' => $site->uuid, 'service' => 'cavity_closers', 'search' => '100%'];
    $this->actingAs(amendmentsOffice())->get(route('office.workspace.amendments.index', $filters))->assertOk()
        ->assertViewHas('amendments', fn ($rows) => $rows->pluck('id')->all() === [$cycle->id]);
    foreach (['customer' => CustomerOrganisation::factory()->create()->uuid, 'site' => Site::factory()->create()->uuid, 'service' => 'windows', 'search' => 'absent'] as $key => $value) {
        $this->get(route('office.workspace.amendments.index', [...$filters, $key => $value]))->assertOk()
            ->assertSee('No amendments match these filters')->assertViewHas('amendments', fn ($rows) => $rows->isEmpty());
    }
});

it('paginates queue and immutable multi amendment timeline without mutating records', function (): void {
    $office = amendmentsOffice();
    $request = amendmentsRequest();
    amendmentsCycle($request, ['opened_at' => '2026-09-23 06:00:00', 'status' => CallOffNegotiationStatus::Superseded]);
    $latest = amendmentsCycle($request);
    for ($i = 1; $i <= 12; $i++) {
        CallOffStatusHistory::factory()->create([
            'call_off_request_id' => $request->id, 'call_off_batch_id' => $request->call_off_batch_id,
            'sequence' => $i, 'event_type' => CallOffHistoryEventType::AmendmentRequested,
            'before_state' => ['agreed_date' => '2026-10-01'],
            'after_state' => ['amendment_requested_date' => '2026-10-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'actor_name' => 'Recorded actor '.$i],
            'performed_at' => now()->addMinutes($i), 'internal_reason' => 'HIDDEN HISTORY',
        ]);
    }
    for ($i = 0; $i < 16; $i++) {
        amendmentsCycle(amendmentsRequest());
    }
    $before = [$request->fresh()->toArray(), CallOffStatusHistory::count(), CallOffDateNegotiation::count()];
    $url = route('office.workspace.amendments.index', ['request' => $request->uuid, 'status' => 'all']);
    $this->actingAs($office)->get($url)->assertOk()
        ->assertViewHas('selected', fn ($row) => $row->id === $latest->id)
        ->assertViewHas('amendments', fn ($rows) => $rows->count() === 15 && $rows->total() === 17)
        ->assertViewHas('history', fn ($rows) => $rows->count() === 10 && $rows->total() === 12)
        ->assertSeeInOrder(['Recorded actor 1', 'Recorded actor 2', 'Recorded actor 3'])
        ->assertDontSee('HIDDEN HISTORY');
    $this->get($url.'&page=2&history_page=2')->assertOk()
        ->assertViewHas('amendments', fn ($rows) => $rows->count() === 2)
        ->assertViewHas('history', fn ($rows) => $rows->pluck('sequence')->all() === [11, 12])
        ->assertSee('Recorded actor 12')->assertSee('13 Oct 2026');
    expect([$request->fresh()->toArray(), CallOffStatusHistory::count(), CallOffDateNegotiation::count()])->toBe($before);
});

it('validates filters and only selects requests that have amendment records', function (): void {
    $this->actingAs(amendmentsOffice());
    foreach (['status' => 'synced', 'customer' => '1', 'service' => 'manufacturing', 'search' => str_repeat('x', 101), 'page' => 0, 'history_page' => -1, 'request' => '1'] as $key => $value) {
        $this->getJson(route('office.workspace.amendments.index', [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }
    $this->get(route('office.workspace.amendments.index', ['request' => (string) Str::uuid()]))->assertNotFound();
    $this->get(route('office.workspace.amendments.index', ['request' => amendmentsRequest()->uuid]))->assertNotFound();
});

it('links the Dashboard amendment category and selected items to the workspace', function (): void {
    $request = amendmentsRequest();
    amendmentsCycle($request);
    $this->actingAs(amendmentsOffice())->get(route('dashboard'))->assertOk()
        ->assertSee(route('office.workspace.amendments.index'), false)
        ->assertSee(route('office.workspace.amendments.index', ['request' => $request->uuid]), false);
});

it('does not resurrect an older cycle when the latest cycle is closed even with equal timestamps', function (): void {
    $request = amendmentsRequest(attributes: ['status' => CallOffRequestStatus::DateAgreed]);
    amendmentsCycle($request);
    $latest = amendmentsCycle($request, ['status' => CallOffNegotiationStatus::DateAgreed, 'prior_agreed_date' => null]);
    $this->actingAs(amendmentsOffice())->get(route('office.workspace.amendments.index'))->assertOk()
        ->assertViewHas('counts', ['attention' => 0, 'waiting' => 0, 'closed' => 1]);
    $this->get(route('office.workspace.amendments.index', ['status' => 'all', 'request' => $request->uuid]))->assertOk()
        ->assertSee('Not recorded')->assertViewHas('amendments', fn ($rows) => $rows->pluck('id')->all() === [$latest->id])
        ->assertViewHas('selected', fn ($row) => $row->id === $latest->id);
});

it('keeps query counts bounded and never loads entire proposal or history collections', function (): void {
    $office = amendmentsOffice();
    $cycle = amendmentsCycle(amendmentsRequest());
    $query = app(OfficeAmendmentsWorkspaceQuery::class);
    DB::enableQueryLog();
    $one = $query->forUser($office);
    $oneCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    for ($i = 0; $i < 19; $i++) {
        amendmentsCycle(amendmentsRequest());
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $many = $query->forUser($office);
    $manyCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($manyCount)->toBe($oneCount);
    expect($many['amendments'])->toHaveCount(15);
    foreach ($many['amendments'] as $amendment) {
        expect($amendment->relationLoaded('proposals'))->toBeFalse();
        expect($amendment->callOffRequest->relationLoaded('histories'))->toBeFalse();
    }
});
