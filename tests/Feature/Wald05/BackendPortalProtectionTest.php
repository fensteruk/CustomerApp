<?php

use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\ProjectedPlotService;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\Wald05BackendFixtures as B;

uses(RefreshDatabase::class);
beforeEach(fn () => config(['wald_import.enabled' => true]));

it('preserves dates actors responses and old history while completion closes the current process without notifications', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $service = ProjectedPlotService::query()->where('source_call_number', '1001')->firstOrFail();
    $batch = CallOffBatch::factory()->create(['site_id' => $scope->siteId, 'submitted_by_user_id' => $actor->id, 'requested_date' => '2026-10-12']);
    $request = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id,
        'projected_plot_service_id' => $service->id, 'service_identifier' => CallOffServiceType::Windows, 'status' => CallOffRequestStatus::AmendmentOnHold,
        'requested_date' => '2026-10-12', 'agreed_date' => '2026-10-19', 'customer_response' => 'Keep this customer message.', 'active_conflict_key' => 'wald-test-active']);
    $negotiation = CallOffDateNegotiation::query()->create(['call_off_request_id' => $request->id, 'purpose' => 'amendment', 'status' => 'open',
        'active_negotiation_key' => 'wald-test-negotiation', 'prior_agreed_date' => '2026-10-19', 'customer_response' => 'Customer amendment response.', 'internal_reason' => 'Private retained reason.', 'opened_at' => now()]);
    $proposal = $negotiation->proposals()->create(['sequence' => 1, 'proposal_type' => CallOffDateProposalType::FensterAlternativeDate, 'status' => 'awaiting_response', 'proposed_date' => '2026-10-26',
        'proposed_by_user_id' => $actor->id, 'responded_by_user_id' => $actor->id, 'customer_response' => 'Retain response.', 'proposed_at' => now()]);
    $history = CallOffStatusHistory::factory()->create(['call_off_request_id' => $request->id, 'call_off_batch_id' => $batch->id, 'performed_by_user_id' => $actor->id]);
    $history->refresh();
    $batch->refresh();
    $historyHash = Canonical::hash($history->getRawOriginal());
    $batchHash = Canonical::hash($batch->getRawOriginal());
    $preview = B::reviewed($actor, $scope, [0 => ['complete' => 'Yes', 'Plot To Be Installed' => '2026-09-20']], slot: 'AFTERNOON', headers: ['Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF', 'Plot To Be Installed']);
    $inject = true;
    DB::listen(function ($query) use (&$inject) {
        if ($inject && str_contains(str_replace('`', '"', strtolower($query->sql)), 'insert into "source_projection_events"')) {
            throw new RuntimeException('after_portal_completion');
        }
    });
    try {
        expect(fn () => B::commit($actor, $scope, $preview))->toThrow(RuntimeException::class, 'after_portal_completion');
    } finally {
        $inject = false;
    }
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::AmendmentOnHold)
        ->and($negotiation->fresh()->status->value)->toBe('open')
        ->and($proposal->fresh()->status->value)->toBe('awaiting_response')
        ->and(DB::table('wald_import_receipts')->count())->toBe(1)
        ->and(Canonical::hash($history->fresh()->getRawOriginal()))->toBe($historyHash);
    B::commit($actor, $scope, $preview);
    $request->refresh();
    $service->refresh();
    expect($request->status)->toBe(CallOffRequestStatus::Completed)->and($request->active_conflict_key)->toBeNull()
        ->and($request->requested_date->toDateString())->toBe('2026-10-12')->and($request->agreed_date->toDateString())->toBe('2026-10-19')
        ->and($request->customer_response)->toBe('Keep this customer message.')->and($service->source_completed_at)->toBeNull()
        ->and($service->isSourceCompleted())->toBeTrue()->and(Canonical::hash($history->fresh()->getRawOriginal()))->toBe($historyHash)
        ->and(Canonical::hash($batch->fresh()->getRawOriginal()))->toBe($batchHash)
        ->and($negotiation->fresh()->status->value)->toBe('completed')->and($negotiation->fresh()->prior_agreed_date->toDateString())->toBe('2026-10-19')
        ->and($proposal->fresh()->proposed_date->toDateString())->toBe('2026-10-26')->and($proposal->fresh()->customer_response)->toBe('Retain response.')
        ->and($proposal->fresh()->proposed_by_user_id)->toBe($actor->id)->and(DB::table('portal_notifications')->count())->toBe(0);
    B::commit($actor, $scope, B::reviewed($actor, $scope, date: '2026-09-10'));
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)->and($service->fresh()->isSourceCompleted())->toBeFalse()
        ->and(DB::table('source_projection_events')->where('event_type', 'completion_reversed')->count())->toBe(1);
});

it('preserves entirely omitted plots and rejects identity reassignment', function () {
    [$actor, $scope] = F::owner();
    B::binding($actor, $scope);
    B::commit($actor, $scope, B::reviewed($actor, $scope));
    $before = DB::table('projected_plot_services')->where('source_call_number', '1007')->first();
    B::commit($actor, $scope, B::reviewed($actor, $scope, slot: 'AFTERNOON', count: 6));
    expect((array) DB::table('projected_plot_services')->where('source_call_number', '1007')->first())->toBe((array) $before);
    expect(fn () => B::reviewed($actor, $scope, [0 => ['Plot' => 'other']], date: '2026-09-10'))->toThrow(ImportConflict::class, 'call_identity_changed');
    expect(fn () => B::reviewed($actor, $scope, [0 => ['Call Type' => 'CC1']], date: '2026-09-10'))->toThrow(ImportConflict::class, 'call_identity_changed');
});
