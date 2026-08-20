<?php

use App\Data\SourceRecord;
use App\Enums\CallOffRequestStatus;
use App\Enums\PortalRoleIdentifier;
use App\Enums\SourceProjectionIssueType;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceProjectionEvent;
use App\Models\SourceProjectionIssue;
use App\Models\User;
use App\Services\SourceProjectionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the importer centrally maps all four Call Types and creates a four-service plot projection', function (): void {
    $site = sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $records = [
        sourceRecord('CC-1', 'CC!', 'P-100'), sourceRecord('PC-1', 'PC1', 'P-100'),
        sourceRecord('SN-1', 'CM1', 'P-100'), sourceRecord('CM-1', 'CM2', 'P-100'),
    ];

    $run = $importer->import('fixture', $records, 'snapshot-1');

    expect($run->status)->toBe('completed')
        ->and(ProjectedPlotService::query()->where('projected_plot_id', $site->projectedPlots()->firstOrFail()->id)->count())->toBe(4);
});

test('repeat imports are idempotent and retain zero product quantities', function (): void {
    sourceSite();
    $record = sourceRecord('PC-1', 'PC1', 'P-101', products: ['BF-101' => 1, 'CAS' => 0]);
    $importer = app(SourceProjectionImportService::class);

    $importer->import('fixture', [$record]);
    $importer->import('fixture', [$record]);

    expect(ProjectedPlotService::query()->where('source_call_number', 'PC-1')->count())->toBe(1)
        ->and(ProjectedPlotService::query()->firstOrFail()->projectedPlot->products()->count())->toBe(2)
        ->and(ProjectedPlotService::query()->firstOrFail()->projectedPlot->products()->where('product_code', 'CAS')->value('quantity'))->toBe('0.000');
});

test('unknown Call Types and missing source records create reconciliation issues without corrupting projections', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-102')]);
    $run = $importer->import('fixture', [sourceRecord('UNKNOWN-1', 'NOPE', 'P-103')]);

    expect($run->status)->toBe('partial')
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::UnknownCallType)->exists())->toBeTrue()
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::MissingSourceRecord)->exists())->toBeTrue()
        ->and(ProjectedPlotService::query()->where('source_call_number', 'PC-1')->firstOrFail()->source_present)->toBeFalse();
});

test('stage mappings and Completed Date independently establish completion without inventing a date', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $stageComplete = new SourceRecord('CC-2', 'SITE-1', 'P-105', 'CC!', 'CC08', null);
    $dateComplete = sourceRecord('CM-2', 'CM2', 'P-106', completedDate: '2026-08-20');

    $importer->import('fixture', [$stageComplete, $dateComplete]);

    expect(ProjectedPlotService::query()->where('source_call_number', 'CC-2')->firstOrFail()->isSourceCompleted())->toBeTrue()
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CC-2')->firstOrFail()->source_completed_at)->toBeNull()
        ->and(ProjectedPlotService::query()->where('source_call_number', 'CM-2')->firstOrFail()->source_completed_at->toDateString())->toBe('2026-08-20')
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::CompletionDateMissing)->exists())->toBeTrue();
});

test('duplicate snapshot Call Numbers and association changes become issues rather than projection mutations', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-3', 'PC1', 'P-107')]);
    $importer->import('fixture', [sourceRecord('PC-3', 'PC1', 'P-108')]);
    $run = $importer->import('fixture', [sourceRecord('PC-4', 'PC1', 'P-108'), sourceRecord('PC-4', 'PC1', 'P-108')]);

    expect($run->records_rejected)->toBe(2)
        ->and(ProjectedPlotService::query()->where('source_call_number', 'PC-3')->firstOrFail()->projectedPlot->plot_reference)->toBe('P-107')
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::AssociationChanged)->exists())->toBeTrue()
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::DuplicateCallNumber)->exists())->toBeTrue();
});

test('source completion closes active negotiations without notifications and reversal never reactivates an old request', function (): void {
    $site = sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104')]);
    $service = ProjectedPlotService::query()->where('source_call_number', 'PC-1')->firstOrFail();
    $user = sourceUser($site->customerOrganisation);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);
    $request = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id, 'projected_plot_service_id' => $service->id, 'service_identifier' => 'windows']);

    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104', completedDate: '2026-08-20')]);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull();

    $newer = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id, 'projected_plot_service_id' => $service->id, 'service_identifier' => 'windows', 'status' => CallOffRequestStatus::Submitted]);
    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104')]);

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($newer->fresh()->status)->toBe(CallOffRequestStatus::Submitted)
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::UnsafeCompletionReversal)->exists())->toBeTrue()
        ->and(SourceProjectionEvent::query()->where('event_type', 'completion_reversed')->exists())->toBeTrue();
});

function sourceSite(): Site
{
    $organisation = CustomerOrganisation::factory()->create();

    return Site::factory()->create(['customer_organisation_id' => $organisation->id, 'external_source' => 'fixture', 'external_identifier' => 'SITE-1']);
}

function sourceRecord(string $callNumber, string $callType, string $plot, array $products = [], ?string $completedDate = null): SourceRecord
{
    return new SourceRecord($callNumber, 'SITE-1', $plot, $callType, null, $completedDate === null ? null : CarbonImmutable::parse($completedDate), $products, CarbonImmutable::parse('2026-08-20 10:00:00'));
}

function sourceUser(CustomerOrganisation $organisation): User
{
    PortalRole::firstOrCreate(['identifier' => PortalRoleIdentifier::SiteManager->value], ['name' => 'Site Manager']);

    return User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $organisation->id]);
}
