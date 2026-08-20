<?php

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\QuickUndoCallOffOperationAction;
use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\RestoreCallOffRequestsAction;
use App\Actions\CallOff\ResubmitRejectedCallOffAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Actions\CallOff\TrashCallOffRequestsAction;
use App\Actions\CallOff\UpdateConflictKeyAction;
use App\Actions\CallOff\WithdrawCallOffRequestsAction;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffOperationType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffRequest;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function callOffUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null, array $attributes = []): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()
        ->role($role)
        ->create(array_merge([
            'customer_organisation_id' => $organisation->id,
            'password' => Hash::make('password'),
        ], $attributes));
}

function callOffAssignedSite(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge([
        'customer_organisation_id' => $user->customer_organisation_id,
    ], $attributes));

    $user->assignedSites()->attach($site);

    return $site;
}

function callOffPlot(Site $site, array $attributes = []): ProjectedPlot
{
    return ProjectedPlot::factory()->create(array_merge([
        'site_id' => $site->id,
    ], $attributes));
}

function callOffSubmit(User $user, Site $site, array $plots, CallOffServiceType $service = CallOffServiceType::Windows): CallOffBatch
{
    return app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: $service,
        requestedDate: now()->addWeek()->toDateString(),
        projectedPlots: $plots,
        customerResponse: 'Please book this in.',
    );
}

function callOffOfficeForSite(Site $site): User
{
    $officeUser = callOffUser(PortalRoleIdentifier::FensterOfficeStaff, $site->customerOrganisation);
    $officeUser->assignedSites()->attach($site);

    return $officeUser;
}

it('models projected plots as site-scoped projections with external identity', function (): void {
    $site = Site::factory()->create();
    $plot = callOffPlot($site, [
        'external_source' => 'siteapp',
        'external_identifier' => 'plot-100',
        'plot_reference' => 'Plot 100',
    ]);

    expect($plot->site->is($site))->toBeTrue()
        ->and($plot->uuid)->not->toBeEmpty()
        ->and($plot->callOffRequests)->toHaveCount(0);

    ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'external_source' => 'other_source',
        'external_identifier' => 'plot-100',
    ]);

    expect(fn () => ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'external_source' => 'siteapp',
        'external_identifier' => 'plot-100',
    ]))->toThrow(QueryException::class);
});

it('denies projected plot access across sites and organisations', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $assignedSite = callOffAssignedSite($user);
    $visiblePlot = callOffPlot($assignedSite);
    $hiddenPlot = callOffPlot(Site::factory()->create());

    expect(Gate::forUser($user)->allows('view-projected-plot', $visiblePlot))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view-projected-plot', $hiddenPlot))->toBeFalse();
});

it('submits a single projected plot as one batch and one request', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $plot = callOffPlot($site);

    $batch = callOffSubmit($user, $site, [$plot], CallOffServiceType::CavityClosers);
    $request = $batch->requests()->firstOrFail();

    expect($batch->site->is($site))->toBeTrue()
        ->and($batch->submittedBy->is($user))->toBeTrue()
        ->and($batch->service_identifier)->toBe(CallOffServiceType::CavityClosers)
        ->and($batch->requested_date->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and($batch->requests)->toHaveCount(1)
        ->and($request->projectedPlot->is($plot))->toBeTrue()
        ->and($request->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($request->active_conflict_key)->toBe(UpdateConflictKeyAction::keyFor($plot->id, CallOffServiceType::CavityClosers->value));
});

it('submits multiple projected plots as independently auditable requests in one batch', function (): void {
    $user = callOffUser(PortalRoleIdentifier::FinishingForeman);
    $site = callOffAssignedSite($user);
    $plots = [callOffPlot($site), callOffPlot($site), callOffPlot($site)];

    $batch = callOffSubmit($user, $site, $plots);

    expect($batch->requests)->toHaveCount(3)
        ->and($batch->requests->pluck('projected_plot_id')->sort()->values()->all())->toBe(collect($plots)->pluck('id')->sort()->values()->all())
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::Submitted)->count())->toBe(3);
});

it('rejects invalid or unauthorised submission selections atomically', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $validPlot = callOffPlot($site);
    $otherSitePlot = callOffPlot(Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]));
    $completedPlot = callOffPlot($site, ['is_completed' => true]);

    expect(fn () => callOffSubmit($user, $site, [$completedPlot]))->toThrow(ValidationException::class);
    expect(fn () => callOffSubmit($user, $site, [$validPlot, $otherSitePlot]))->toThrow(ValidationException::class);
    expect(fn () => app(SubmitCallOffBatchAction::class)->handle($user, $site, 'unsupported', now(), [$validPlot]))->toThrow(ValidationException::class);
    expect(CallOffRequest::query()->count())->toBe(0);
});

it('does not silently omit a selected plot that no longer exists', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $validPlot = callOffPlot($site);
    $missingPlot = new ProjectedPlot;
    $missingPlot->id = 999999;

    expect(fn () => callOffSubmit($user, $site, [$validPlot, $missingPlot]))->toThrow(ValidationException::class);
    expect(CallOffBatch::query()->count())->toBe(0)
        ->and(CallOffRequest::query()->count())->toBe(0);
});

it('blocks submitted and approved duplicates but permits rejected and withdrawn resubmission', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $plot = callOffPlot($site);
    $officeUser = callOffOfficeForSite($site);

    $submitted = callOffSubmit($user, $site, [$plot])->requests()->firstOrFail();
    expect(fn () => callOffSubmit($user, $site, [$plot]))->toThrow(ValidationException::class);

    app(ApproveCallOffRequestAction::class)->handle($officeUser, $submitted);
    expect(fn () => callOffSubmit($user, $site, [$plot]))->toThrow(ValidationException::class);

    $rejectedPlot = callOffPlot($site);
    $rejected = callOffSubmit($user, $site, [$rejectedPlot])->requests()->firstOrFail();
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejected);
    expect(callOffSubmit($user, $site, [$rejectedPlot])->requests()->first())->not->toBeNull();

    $withdrawnPlot = callOffPlot($site);
    $withdrawn = callOffSubmit($user, $site, [$withdrawnPlot])->requests()->firstOrFail();
    app(WithdrawCallOffRequestsAction::class)->handle($user, [$withdrawn]);
    expect(callOffSubmit($user, $site, [$withdrawnPlot])->requests()->first())->not->toBeNull();
});

it('also protects duplicate active conflict keys with a database unique index', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $plot = callOffPlot($site);
    $request = callOffSubmit($user, $site, [$plot])->requests()->firstOrFail();

    $otherBatch = CallOffBatch::factory()->create([
        'site_id' => $site->id,
        'submitted_by_user_id' => $user->id,
        'service_identifier' => CallOffServiceType::Windows,
    ]);

    expect(fn () => CallOffRequest::factory()->create([
        'call_off_batch_id' => $otherBatch->id,
        'projected_plot_id' => $plot->id,
        'status' => CallOffRequestStatus::Submitted,
        'active_conflict_key' => $request->active_conflict_key,
    ]))->toThrow(QueryException::class);
});

it('allows global Office Staff decisions and preserves response separation', function (): void {
    $siteUser = callOffUser(PortalRoleIdentifier::AssistantSiteManager);
    $site = callOffAssignedSite($siteUser);
    $request = callOffSubmit($siteUser, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $officeUser = callOffOfficeForSite($site);
    $unassignedOffice = callOffUser(PortalRoleIdentifier::FensterOfficeStaff, $site->customerOrganisation);

    expect(fn () => app(ApproveCallOffRequestAction::class)->handle($siteUser, $request))->toThrow(AuthorizationException::class);

    $approved = app(ApproveCallOffRequestAction::class)->handle($unassignedOffice, $request, 'Approved for customer', 'Private check complete');
    $history = $approved->histories()->latest('sequence')->firstOrFail();

    expect($approved->status)->toBe(CallOffRequestStatus::Approved)
        ->and($approved->active_conflict_key)->not->toBeNull()
        ->and($history->customer_response)->toBe('Approved for customer')
        ->and($history->internal_reason)->toBe('Private check complete')
        ->and($history->toArray())->not->toHaveKey('internal_reason');
});

it('clears the conflict key on rejection and denies invalid rejection actors', function (): void {
    $siteUser = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($siteUser);
    $request = callOffSubmit($siteUser, $site, [callOffPlot($site)])->requests()->firstOrFail();

    expect(fn () => app(RejectCallOffRequestAction::class)->handle($siteUser, $request))->toThrow(AuthorizationException::class);

    $rejected = app(RejectCallOffRequestAction::class)->handle(callOffOfficeForSite($site), $request, 'No', 'Private reason');

    expect($rejected->status)->toBe(CallOffRequestStatus::Rejected)
        ->and($rejected->active_conflict_key)->toBeNull();
});

it('withdraws submitted requests atomically with operation items and history', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $batch = callOffSubmit($user, $site, [callOffPlot($site), callOffPlot($site)]);

    $operation = app(WithdrawCallOffRequestsAction::class)->handle($user, $batch->requests);

    expect($operation->operation_type)->toBe(CallOffOperationType::Withdrawal)
        ->and($operation->items)->toHaveCount(2)
        ->and($operation->undo_expires_at)->not->toBeNull()
        ->and(CallOffRequest::query()->where('status', CallOffRequestStatus::Withdrawn)->count())->toBe(2)
        ->and(CallOffRequest::query()->whereNotNull('active_conflict_key')->count())->toBe(0)
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::Withdrawn)->count())->toBe(2);
});

it('denies withdrawal for approved and rejected requests', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $officeUser = callOffOfficeForSite($site);
    $approved = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $rejected = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();

    app(ApproveCallOffRequestAction::class)->handle($officeUser, $approved);
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejected);

    expect(fn () => app(WithdrawCallOffRequestsAction::class)->handle($user, [$approved]))->toThrow(ValidationException::class);
    expect(fn () => app(WithdrawCallOffRequestsAction::class)->handle($user, [$rejected]))->toThrow(ValidationException::class);
});

it('moves only rejected or withdrawn requests to Trash and keeps their underlying status', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $officeUser = callOffOfficeForSite($site);
    $rejected = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $submitted = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejected);

    expect(fn () => app(TrashCallOffRequestsAction::class)->handle($user, [$submitted]))->toThrow(ValidationException::class);

    $operation = app(TrashCallOffRequestsAction::class)->handle($user, [$rejected]);
    $trashed = $rejected->fresh();

    expect($operation->items)->toHaveCount(1)
        ->and($trashed->status)->toBe(CallOffRequestStatus::Rejected)
        ->and($trashed->trashed_at)->not->toBeNull()
        ->and($trashed->trash_expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());
});

it('restores unexpired Trash without changing underlying status and hides expired Trash', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $request = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    app(WithdrawCallOffRequestsAction::class)->handle($user, [$request]);
    app(TrashCallOffRequestsAction::class)->handle($user, [$request->fresh()]);

    expect(CallOffRequest::query()->customerTrash()->count())->toBe(1);

    app(RestoreCallOffRequestsAction::class)->handle($user, [$request->fresh()]);
    $restored = $request->fresh();

    expect($restored->status)->toBe(CallOffRequestStatus::Withdrawn)
        ->and($restored->trashed_at)->toBeNull()
        ->and($restored->trash_expires_at)->toBeNull();

    $restored->forceFill(['trashed_at' => now()->subDays(8), 'trash_expires_at' => now()->subDay()])->save();
    expect(CallOffRequest::query()->customerTrash()->count())->toBe(0);
    expect(fn () => app(RestoreCallOffRequestsAction::class)->handle($user, [$restored->fresh()]))->toThrow(ValidationException::class);
});

it('rejects mixed-eligibility bulk Trash without partial changes', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $officeUser = callOffOfficeForSite($site);
    $rejected = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $submitted = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejected);

    expect(fn () => app(TrashCallOffRequestsAction::class)->handle($user, [$rejected->fresh(), $submitted]))->toThrow(ValidationException::class);
    expect($rejected->fresh()->trashed_at)->toBeNull();
});

it('does not silently omit missing or duplicated bulk-operation selections', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $request = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $missingRequest = new CallOffRequest;
    $missingRequest->id = 999999;

    expect(fn () => app(WithdrawCallOffRequestsAction::class)->handle($user, [$request, $missingRequest]))->toThrow(ValidationException::class);
    expect(fn () => app(WithdrawCallOffRequestsAction::class)->handle($user, [$request, $request]))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Submitted)
        ->and(CallOffBatchOperation::query()->count())->toBe(0);
});

it('quick undoes eligible withdrawal operations within five seconds', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $request = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $operation = app(WithdrawCallOffRequestsAction::class)->handle($user, [$request]);

    $undo = app(QuickUndoCallOffOperationAction::class)->handle($user, $operation);
    $restored = $request->fresh();

    expect($undo->operation_type)->toBe(CallOffOperationType::QuickUndo)
        ->and($operation->fresh()->reversed_by_operation_id)->toBe($undo->id)
        ->and($restored->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($restored->active_conflict_key)->not->toBeNull()
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::UndoApplied)->count())->toBe(1);
});

it('rejects expired or diverged quick Undo operations atomically', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $request = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $operation = app(WithdrawCallOffRequestsAction::class)->handle($user, [$request]);
    $operation->forceFill(['undo_expires_at' => now()->subSecond()])->save();

    expect(fn () => app(QuickUndoCallOffOperationAction::class)->handle($user, $operation))->toThrow(ValidationException::class);

    $freshOperation = app(TrashCallOffRequestsAction::class)->handle($user, [$request->fresh()]);
    $request->fresh()->forceFill(['trash_expires_at' => now()->addDays(6)])->save();

    expect(fn () => app(QuickUndoCallOffOperationAction::class)->handle($user, $freshOperation))->toThrow(ValidationException::class);
    expect($freshOperation->fresh()->reversed_by_operation_id)->toBeNull();
});

it('resubmits rejected requests with lineage and rejects other statuses', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $officeUser = callOffOfficeForSite($site);
    $rejected = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    $approved = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    app(RejectCallOffRequestAction::class)->handle($officeUser, $rejected);
    app(ApproveCallOffRequestAction::class)->handle($officeUser, $approved);

    $resubmitted = app(ResubmitRejectedCallOffAction::class)->handle($user, $rejected->fresh(), 'Trying again');

    expect($resubmitted->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($resubmitted->resubmitted_from_call_off_request_id)->toBe($rejected->id)
        ->and($resubmitted->active_conflict_key)->not->toBeNull()
        ->and($rejected->fresh()->status)->toBe(CallOffRequestStatus::Rejected)
        ->and(CallOffStatusHistory::query()->where('event_type', CallOffHistoryEventType::Resubmitted)->count())->toBe(1);

    expect(fn () => app(ResubmitRejectedCallOffAction::class)->handle($user, $approved->fresh()))->toThrow(ValidationException::class);
});

it('keeps per-request history sequence ordered and unique', function (): void {
    $user = callOffUser(PortalRoleIdentifier::SiteManager);
    $site = callOffAssignedSite($user);
    $request = callOffSubmit($user, $site, [callOffPlot($site)])->requests()->firstOrFail();
    app(WithdrawCallOffRequestsAction::class)->handle($user, [$request]);

    $events = $request->histories()->orderBy('sequence')->get();

    expect($events->pluck('sequence')->all())->toBe([1, 2])
        ->and($events->pluck('event_type')->all())->toBe([
            CallOffHistoryEventType::Submitted,
            CallOffHistoryEventType::Withdrawn,
        ]);
});

it('generates business UUIDs server-side and protects audit records from mutation', function (): void {
    $site = Site::factory()->create();
    $plot = ProjectedPlot::query()->create([
        'uuid' => 'client-supplied-value',
        'site_id' => $site->id,
        'external_source' => 'qa',
        'external_identifier' => 'server-generated-uuid',
        'plot_reference' => 'Plot QA',
    ]);

    $user = callOffUser(PortalRoleIdentifier::SiteManager, $site->customerOrganisation);
    $user->assignedSites()->attach($site);
    $request = callOffSubmit($user, $site, [$plot])->requests()->firstOrFail();
    $history = $request->histories()->firstOrFail();

    expect($plot->uuid)->not->toBe('client-supplied-value');

    $history->customer_response = 'Tampered';
    expect(fn () => $history->save())->toThrow(LogicException::class);
    expect(fn () => $history->delete())->toThrow(LogicException::class);
});

it('does not expose ordinary history mutation routes', function (): void {
    $this->patch('/call-off-status-histories/1')->assertNotFound();
    $this->delete('/call-off-status-histories/1')->assertNotFound();
});
