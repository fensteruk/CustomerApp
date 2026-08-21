<?php

use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Data\SourceRecord;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Enums\SourceProjectionIssueType;
use App\Models\CallOffBatch;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\SourceImportRun;
use App\Models\SourceProjectionEvent;
use App\Models\SourceProjectionIssue;
use App\Models\User;
use App\Services\SourceCallTypeMapper;
use App\Services\SourceProjectionImportService;
use App\Services\SourceProjectionIssueService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Call Type and completion-stage mappings are explicit, tolerant of whitespace and never guessed', function (): void {
    $mapper = app(SourceCallTypeMapper::class);

    expect($mapper->serviceFor(' pc1 '))->toBe(CallOffServiceType::Windows)
        ->and($mapper->serviceFor('CC!'))->toBe(CallOffServiceType::CavityClosers)
        ->and($mapper->serviceFor('cm1'))->toBe(CallOffServiceType::Snagging)
        ->and($mapper->serviceFor('CM2'))->toBe(CallOffServiceType::Cml)
        ->and($mapper->serviceFor('PC2'))->toBeNull()
        ->and($mapper->isCompletionStage(CallOffServiceType::CavityClosers, 'CC08'))->toBeTrue()
        ->and($mapper->isCompletionStage(CallOffServiceType::Windows, 'CA02'))->toBeTrue()
        ->and($mapper->isCompletionStage(CallOffServiceType::Windows, 'CA03'))->toBeTrue()
        ->and($mapper->isCompletionStage(CallOffServiceType::Snagging, 'SN05'))->toBeTrue()
        ->and($mapper->isCompletionStage(CallOffServiceType::Cml, 'CML4'))->toBeTrue()
        ->and($mapper->isCompletionStage(CallOffServiceType::Windows, 'CC08'))->toBeFalse();
});

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
    $repeat = $importer->import('fixture', [$record]);

    expect(ProjectedPlotService::query()->where('source_call_number', 'PC-1')->count())->toBe(1)
        ->and(ProjectedPlotService::query()->firstOrFail()->projectedPlot->products()->count())->toBe(2)
        ->and(ProjectedPlotService::query()->firstOrFail()->projectedPlot->products()->where('product_code', 'CAS')->value('quantity'))->toBe('0.000')
        ->and($repeat->records_unchanged)->toBe(1)
        ->and($repeat->records_updated)->toBe(0);
});

test('blank identities and malformed product quantities are rejected without creating ambiguous projections', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);

    $run = $importer->import('fixture', [
        sourceRecord('', 'PC1', 'P-blank'),
        sourceRecord('PC-invalid-product', 'PC1', 'P-invalid', products: ['BF-1' => 'not-a-number']),
    ]);

    expect($run->records_rejected)->toBe(2)
        ->and(ProjectedPlotService::query()->where('source_call_number', '')->exists())->toBeFalse()
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::InvalidSourceRecord)->count())->toBe(2);
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

test('a stable Call No. cannot change service and a plot service cannot be rebound to a new Call No.', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-stable', 'PC1', 'P-identity')]);

    $changedType = $importer->import('fixture', [sourceRecord('PC-stable', 'CM2', 'P-identity')]);
    $replacement = $importer->import('fixture', [sourceRecord('PC-replacement', 'PC1', 'P-identity')]);

    expect($changedType->records_rejected)->toBe(1)
        ->and($replacement->records_rejected)->toBe(1)
        ->and(ProjectedPlotService::query()->where('source_call_number', 'PC-stable')->firstOrFail()->service_identifier->value)->toBe('windows')
        ->and(ProjectedPlotService::query()->where('source_call_number', 'PC-replacement')->exists())->toBeFalse()
        ->and(SourceProjectionIssue::query()->where('issue_type', SourceProjectionIssueType::AssociationChanged)->count())->toBe(2);
});

test('a restored Call No. resolves its missing-source issue without duplicating its projection', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $record = sourceRecord('PC-returned', 'PC1', 'P-returned');
    $importer->import('fixture', [$record]);
    $importer->import('fixture', []);

    $issue = SourceProjectionIssue::query()->where('issue_key', 'missing-source-record:fixture:PC-returned')->firstOrFail();
    expect($issue->resolved_at)->toBeNull();

    $importer->import('fixture', [$record]);

    expect(ProjectedPlotService::query()->where('source_call_number', 'PC-returned')->count())->toBe(1)
        ->and($issue->fresh()->resolved_at)->not->toBeNull();
});

test('a legitimate completed-date correction is recorded once without replaying completion', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-date', 'PC1', 'P-date', completedDate: '2026-08-20')]);
    $importer->import('fixture', [sourceRecord('PC-date', 'PC1', 'P-date', completedDate: '2026-08-21')]);
    $importer->import('fixture', [sourceRecord('PC-date', 'PC1', 'P-date', completedDate: '2026-08-21')]);

    expect(ProjectedPlotService::query()->where('source_call_number', 'PC-date')->firstOrFail()->source_completed_at->toDateString())->toBe('2026-08-21')
        ->and(SourceProjectionEvent::query()->where('event_type', 'completion_recorded')->count())->toBe(1)
        ->and(SourceProjectionEvent::query()->where('event_type', 'completion_date_updated')->count())->toBe(1);
});

test('source timestamps remain source facts and the plot retains the newest known source update', function (): void {
    sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $newer = CarbonImmutable::parse('2026-08-21 11:00:00');
    $older = CarbonImmutable::parse('2026-08-20 09:00:00');

    $importer->import('fixture', [
        new SourceRecord('PC-fresh', 'SITE-1', 'P-fresh', 'PC1', null, null, [], $newer),
        new SourceRecord('CC-stale', 'SITE-1', 'P-fresh', 'CC!', null, null, [], $older),
    ]);
    $importer->import('fixture', [new SourceRecord('PC-fresh', 'SITE-1', 'P-fresh', 'PC1', null, null)]);

    $service = ProjectedPlotService::query()->where('source_call_number', 'PC-fresh')->firstOrFail();

    expect($service->source_updated_at->toIso8601String())->toBe($newer->toIso8601String())
        ->and($service->projectedPlot->source_updated_at->toIso8601String())->toBe($newer->toIso8601String())
        ->and($service->last_observed_at)->not->toBeNull()
        ->and($service->projectedPlot->synchronised_at)->not->toBeNull();
});

test('a representative four-service snapshot imports one hundred plots without projection growth', function (): void {
    sourceSite();
    $records = collect(range(1, 100))->flatMap(function (int $plot): array {
        $reference = 'P-'.str_pad((string) $plot, 3, '0', STR_PAD_LEFT);

        return [
            sourceRecord('CC-'.$plot, 'CC!', $reference, ['CAS' => 1]),
            sourceRecord('PC-'.$plot, 'PC1', $reference, ['PFD' => 1]),
            sourceRecord('SN-'.$plot, 'CM1', $reference, ['BF-'.$plot => $plot === 1 ? 1 : 0]),
            sourceRecord('CM-'.$plot, 'CM2', $reference),
        ];
    });

    $run = app(SourceProjectionImportService::class)->import('fixture', $records, 'performance-fixture');

    expect($run->records_seen)->toBe(400)
        ->and($run->records_created)->toBe(400)
        ->and(ProjectedPlotService::query()->whereNotNull('source_call_number')->count())->toBe(400)
        ->and(ProjectedPlotService::query()->count())->toBe(400);
});

test('a malformed source row rolls back independently while valid rows continue', function (): void {
    sourceSite();
    $run = app(SourceProjectionImportService::class)->import('fixture', [
        sourceRecord('PC-valid-a', 'PC1', 'P-valid-a'),
        sourceRecord('PC-invalid-b', 'PC1', 'P-invalid-b', ['CAS' => 'bad']),
        sourceRecord('PC-valid-c', 'PC1', 'P-valid-c'),
    ]);

    expect($run->status)->toBe('partial')
        ->and($run->records_applied)->toBe(2)
        ->and($run->records_rejected)->toBe(1)
        ->and(ProjectedPlotService::query()->whereIn('source_call_number', ['PC-valid-a', 'PC-valid-c'])->count())->toBe(2)
        ->and(ProjectedPlotService::query()->where('source_call_number', 'PC-invalid-b')->exists())->toBeFalse();
});

test('an unexpected import failure is safely recorded against its import run', function (): void {
    sourceSite();
    $importer = new SourceProjectionImportService(
        new class extends SourceCallTypeMapper
        {
            public function serviceFor(string $callType): ?CallOffServiceType
            {
                throw new RuntimeException('Simulated adapter failure.');
            }
        },
        app(SourceProjectionIssueService::class),
        app(UpdateConflictKeyAction::class),
    );

    expect(fn () => $importer->import('fixture', [sourceRecord('PC-failure', 'PC1', 'P-failure')]))
        ->toThrow(RuntimeException::class);

    $run = SourceImportRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe('failed')
        ->and($run->safe_error_summary)->toBe('Source import did not complete. See application logs.')
        ->and($run->finished_at)->not->toBeNull();
});

test('source completion closes active negotiations without notifications and reversal never reactivates an old request', function (): void {
    $site = sourceSite();
    $importer = app(SourceProjectionImportService::class);
    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104')]);
    $service = ProjectedPlotService::query()->where('source_call_number', 'PC-1')->firstOrFail();
    $user = sourceUser($site->customerOrganisation);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $user->id]);
    $request = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id, 'projected_plot_service_id' => $service->id, 'service_identifier' => 'windows']);
    app(UpdateConflictKeyAction::class)->handle($request);
    $negotiation = CallOffDateNegotiation::create(['call_off_request_id' => $request->id, 'purpose' => 'initial', 'status' => 'open', 'active_negotiation_key' => "call_off_request:{$request->id}:initial", 'opened_at' => now()]);

    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104', completedDate: '2026-08-20')]);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($negotiation->fresh()->status->value)->toBe('completed')
        ->and($negotiation->fresh()->active_negotiation_key)->toBeNull();

    $newer = CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $service->projected_plot_id, 'projected_plot_service_id' => $service->id, 'service_identifier' => 'windows', 'status' => CallOffRequestStatus::Submitted]);
    app(UpdateConflictKeyAction::class)->handle($newer);
    $importer->import('fixture', [sourceRecord('PC-1', 'PC1', 'P-104')]);

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Completed)
        ->and($newer->fresh()->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($newer->fresh()->active_conflict_key)->not->toBeNull()
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
