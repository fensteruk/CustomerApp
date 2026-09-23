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
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\OfficeDashboardQueryService;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Wald05BackendFixtures;

uses(RefreshDatabase::class);

function overviewOffice(array $attributes = []): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null, 'is_active' => true, 'is_preview_user' => false,
        'name' => 'Alex Office', ...$attributes,
    ]);
}

function overviewRequest(Site $site, string $plot, CallOffRequestStatus $status, array $attributes = []): CallOffRequest
{
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'requested_date' => '2026-10-15']);
    $projectedPlot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $plot]);

    return CallOffRequest::factory()->create([
        'call_off_batch_id' => $batch->id, 'projected_plot_id' => $projectedPlot->id,
        'status' => $status, 'service_identifier' => CallOffServiceType::Windows, ...$attributes,
    ]);
}

function overviewAmendment(CallOffRequest $request, string $openedAt, array $attributes = []): CallOffDateNegotiation
{
    return $request->dateNegotiations()->create([
        'purpose' => CallOffNegotiationPurpose::Amendment, 'status' => CallOffNegotiationStatus::Open,
        'prior_agreed_date' => '2026-10-15', 'requested_date' => '2026-10-20',
        'opened_at' => $openedAt, 'requester_name' => 'Jamie Site',
        ...$attributes,
    ]);
}

it('renders a personalised Office overview with honest empty states and no import access when disabled', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 23)->setTime(9, 0));
    config(['wald_import.pilot_available' => false]);
    $office = overviewOffice(['name' => 'Alex <Office>']);
    $response = $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewIs('office.dashboard.index')
        ->assertSee('Good morning, Alex &lt;Office&gt;', false)
        ->assertSeeInOrder(['Needs attention', 'Amendments', 'Import reviews', 'Call-off actions', 'Active sites', 'Upcoming activity', 'Recent activity', 'Quick actions'])
        ->assertSee('No date amendments awaiting your response.')
        ->assertSee('No call-off requests awaiting your response.')
        ->assertSee('Imports are currently unavailable for this account.')
        ->assertDontSee('Import workbook')
        ->assertDontSee('Nick Powder')
        ->assertViewHas('attentionCount', 0)
        ->assertViewHas('importCount', null)
        ->assertViewHas('lastImport', null);
    expect(preg_match('/<a[^>]*href="'.preg_quote(route('dashboard'), '/').'"[^>]*aria-current="page"[^>]*>/', $response->getContent()))->toBe(1);
});

it('counts current work across customers without counting closed or trashed requests as open', function (): void {
    $office = overviewOffice();
    $site = Site::factory()->create();
    Site::factory()->create();
    Site::factory()->create(['is_active' => false]);
    Site::factory()->create(['customer_organisation_id' => CustomerOrganisation::factory()->create(['is_active' => false])->id]);
    overviewRequest($site, '101', CallOffRequestStatus::Submitted);
    overviewRequest($site, '102', CallOffRequestStatus::AwaitingFenster);
    overviewRequest($site, '103', CallOffRequestStatus::AwaitingSiteUser);
    overviewRequest($site, '104', CallOffRequestStatus::Completed);
    overviewRequest($site, '105', CallOffRequestStatus::Withdrawn);
    overviewRequest($site, '106', CallOffRequestStatus::Rejected);
    overviewRequest($site, '107', CallOffRequestStatus::Submitted, ['trashed_at' => now()]);

    $before = CallOffRequest::query()->orderBy('id')->get()->toArray();
    $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewHas('requestCount', 2)->assertViewHas('openRequestCount', 3)
        ->assertViewHas('activeSiteCount', 2)->assertViewHas('attentionCount', 2);
    expect(CallOffRequest::query()->orderBy('id')->get()->toArray())->toBe($before);
});

it('shows real current Office amendments newest first and keeps waiting-on-site and closed cycles out of attention', function (): void {
    $office = overviewOffice();
    $site = Site::factory()->create(['name' => 'Willow Park QA']);
    $old = overviewRequest($site, '501', CallOffRequestStatus::AmendmentOnHold);
    overviewAmendment($old, '2026-09-22 09:00:00');
    $new = overviewRequest($site, 'Plot 502', CallOffRequestStatus::AmendmentOnHold);
    overviewAmendment($new, '2026-09-20 09:00:00', ['status' => CallOffNegotiationStatus::Superseded, 'requester_name' => 'Superseded Person']);
    overviewAmendment($new, '2026-09-23 09:00:00', ['requester_name' => 'Taylor Site', 'internal_reason' => 'PRIVATE AMENDMENT REASON']);
    $waiting = overviewRequest($site, '503', CallOffRequestStatus::AmendmentOnHold);
    $cycle = overviewAmendment($waiting, '2026-09-23 10:00:00');
    $cycle->proposals()->create([
        'sequence' => 1, 'proposal_type' => CallOffDateProposalType::FensterAlternativeDate,
        'status' => CallOffDateProposalStatus::AwaitingResponse, 'proposed_date' => '2026-10-21',
        'proposed_by_user_id' => $office->id, 'proposed_at' => now(),
    ]);
    $closed = overviewRequest($site, '504', CallOffRequestStatus::Completed);
    overviewAmendment($closed, '2026-09-23 11:00:00', ['status' => CallOffNegotiationStatus::Completed]);

    $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewHas('amendmentCount', 2)->assertViewHas('pendingAmendmentCount', 3)
        ->assertViewHas('requestCount', 0)
        ->assertSeeInOrder(['Plot 502', 'Taylor Site', 'Plot 501', 'Jamie Site'])
        ->assertSee('Agreed 15 Oct 2026')->assertSee('Requested 20 Oct 2026')
        ->assertDontSee('Plot Plot')
        ->assertDontSee('PRIVATE AMENDMENT REASON')->assertDontSee('Superseded Person')
        ->assertDontSee('Plot 503')->assertDontSee('Plot 504')
        ->assertSee(route('office.workspace.amendments.index', ['request' => $new->uuid]), false);
});

it('orders Dashboard amendment cards by the latest amendment rather than request creation order', function (): void {
    $site = Site::factory()->create();
    $olderRequest = overviewRequest($site, '501', CallOffRequestStatus::AmendmentOnHold);
    $newerRequest = overviewRequest($site, '502', CallOffRequestStatus::AmendmentOnHold);
    overviewAmendment($newerRequest, '2026-09-23 07:00:00');
    $latest = overviewAmendment($olderRequest, '2026-09-23 07:30:00');

    $this->actingAs(overviewOffice())->get(route('dashboard'))->assertOk()
        ->assertViewHas('amendmentItems', fn ($rows) => $rows->pluck('uuid')->all() === [$latest->uuid, $newerRequest->latestEffectiveAmendment->uuid]);
});

it('uses future agreed dates for upcoming activity and recorded events for recent activity', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 23)->setTime(12, 0));
    $office = overviewOffice();
    $site = Site::factory()->create();
    $next = overviewRequest($site, 'NEXT', CallOffRequestStatus::DateAgreed, ['agreed_date' => '2026-09-24']);
    $legacy = overviewRequest($site, 'LEGACY', CallOffRequestStatus::Approved, ['service_identifier' => null]);
    overviewRequest($site, 'PAST', CallOffRequestStatus::DateAgreed, ['agreed_date' => '2026-09-22']);
    overviewRequest($site, 'UNAGREED', CallOffRequestStatus::AwaitingSiteUser, ['requested_date' => '2026-09-24']);
    $completed = overviewRequest($site, 'SOURCE-COMPLETE', CallOffRequestStatus::DateAgreed, ['agreed_date' => '2026-09-25']);
    $service = ProjectedPlotService::query()->create(['projected_plot_id' => $completed->projected_plot_id, 'service_identifier' => 'windows', 'source_completion_observed_at' => now()]);
    $completed->update(['projected_plot_service_id' => $service->id]);
    foreach ([$legacy, $next] as $index => $request) {
        CallOffStatusHistory::factory()->create([
            'call_off_request_id' => $request->id, 'call_off_batch_id' => $request->call_off_batch_id,
            'performed_by_user_id' => $office->id, 'event_type' => CallOffHistoryEventType::DateAgreed,
            'performed_at' => now()->subHours(2 - $index), 'internal_reason' => 'HIDDEN HISTORY',
            'after_state' => ['actor_name' => 'Recorded Office Name'],
        ]);
    }
    $response = $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewHas('upcoming', fn ($items) => $items->pluck('id')->all() === [$next->id, $legacy->id])
        ->assertViewHas('recent', fn ($items) => $items->first()['url'] === route('portal.review-requests.show', $next))
        ->assertSee('Recorded Office Name')->assertDontSee('HIDDEN HISTORY')
        ->assertDontSee('SOURCE-COMPLETE')->assertDontSee('UNAGREED')->assertDontSee('Plot PAST');
    expect($response->viewData('recent'))->toHaveCount(2);
});

it('keeps the Office overview inaccessible to guests inactive users and all external roles', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    foreach ([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('sites.select'));
        expect(fn () => app(OfficeDashboardQueryService::class)->forUser($user))
            ->toThrow(HttpException::class);
    }
    $this->actingAs(overviewOffice(['is_active' => false]))->get(route('dashboard'))->assertRedirect(route('login'));
});

it('counts current import reviews without treating uploads as applied or exposing private filenames', function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = overviewOffice();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload($office, Wald05BackendFixtures::workbook(
        overrides: [0 => ['CustomerNo' => 'DASHBOARD-QA-001']],
        headers: ['CustomerNo', 'Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'], count: 1,
    ), new ExportOrder('2099-08-01', 'MORNING'), ExportOrder::CONFIRMATION, (string) Str::uuid());

    $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewHas('importCount', 1)->assertViewHas('lastImport', null)
        ->assertSee('Import workbook')->assertDontSee('synthetic.xlsx')
        ->assertSee(route('office.workspace.pilot-import.show', $pilot['upload']), false);

    if ($pilot['state'] === 'NEEDS_CLARIFICATION') {
        $pilot = $workflow->confirmStructure($office, $pilot['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }
    $site = Site::factory()->create(['name' => 'Receipt-proven QA site']);
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $source = $pilot['sources'][0];
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Dashboard test binding.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Dashboard test activation.', (string) Str::uuid());
    $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
    $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->first();
    try {
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, $run->epoch, (string) Str::uuid());
    } catch (ImportConflict $exception) {
        if ($exception->getMessage() !== 'structural_clarification_required') {
            throw $exception;
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->first();
        $context = KnowledgeContext::findOrFail($run->context_id);
        foreach ((new KnowledgeQueries)->questions($office, $scope, $context->uuid) as $question) {
            if ($question['state'] !== 'ANSWERED' && $question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], $question['sequence'], $question['evidence']['candidates'][0]['id'], 'Synthetic dashboard fixture.', (string) Str::uuid());
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->first();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, $run->epoch, (string) Str::uuid());
    }
    $run = DB::table('wald_import_runs')->where('id', $run->id)->first();
    $review = new ImportReview;
    $preview = $review->preview($office, $scope, $run->uuid, $run->epoch, (string) Str::uuid());
    $review->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
    $review->commit($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

    $this->actingAs($office)->get(route('dashboard'))->assertOk()
        ->assertViewHas('importCount', 0)
        ->assertViewHas('lastImport', fn ($item) => $item['detail'] === 'Receipt-proven QA site')
        ->assertSee('Site import applied')->assertDontSee('synthetic.xlsx');

    DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->update(['state' => 'SUPERSEDED']);
    $this->get(route('dashboard'))->assertOk()->assertViewHas('importCount', 0);
    config(['wald_import.pilot_available' => false]);
    $this->get(route('dashboard'))->assertOk()->assertViewHas('lastImport', null)
        ->assertDontSee('Receipt-proven QA site')->assertDontSee('Site import applied');
});

it('does not expose import evidence or actions to preview Office accounts', function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $this->actingAs(overviewOffice(['is_preview_user' => true]))->get(route('dashboard'))->assertOk()
        ->assertViewHas('importsAvailable', false)->assertDontSee('Import workbook');
});
