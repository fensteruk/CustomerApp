<?php

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffHistoryEventType;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function lifecycleUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()->role($role)->create([
        'customer_organisation_id' => $organisation->id,
        'password' => Hash::make('password'),
    ]);
}

function lifecycleSite(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge(['customer_organisation_id' => $user->customer_organisation_id], $attributes));
    $user->assignedSites()->attach($site);

    return $site;
}

function lifecycleRequest(User $user, Site $site, string $plotReference = 'Plot 101'): CallOffRequest
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $plotReference]);

    return app(SubmitCallOffBatchAction::class)->handle(
        $user,
        $site,
        CallOffServiceType::Windows,
        Carbon::today()->addDays(5)->toDateString(),
        [$plot],
    )->requests->first();
}

function lifecycleConfirmation($test, string $operation, array $uuids): string
{
    $test->post('/portal/call-offs/lifecycle/confirm', [
        'operation' => $operation,
        'requests' => $uuids,
    ])->assertOk();

    return session('call_off_lifecycle_confirmation_signature');
}

it('allows each site role to withdraw a submitted request through confirmation', function (PortalRoleIdentifier $role): void {
    $user = lifecycleUser($role);
    $site = lifecycleSite($user);
    $request = lifecycleRequest($user, $site);

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'withdraw', [$request->uuid]);

    $this->post('/portal/call-offs/lifecycle/withdraw', [
        'operation' => 'withdraw',
        'requests' => [$request->uuid],
        'confirmation_signature' => $signature,
    ])->assertRedirect('/portal/site-dashboard')->assertSessionHas('quickUndo');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn)
        ->and($request->fresh()->active_conflict_key)->toBeNull()
        ->and($request->operationItems()->count())->toBe(1)
        ->and($request->histories()->where('event_type', CallOffHistoryEventType::Withdrawn)->count())->toBe(1);
})->with([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman]);

it('does not allow direct lifecycle persistence without a confirmation', function (): void {
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($user);
    $request = lifecycleRequest($user, $site);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/lifecycle/withdraw', ['operation' => 'withdraw', 'requests' => [$request->uuid]])
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHasErrors('requests');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Submitted);
});

it('rejects tampered final lifecycle operation and replayed confirmation submissions', function (): void {
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($user);
    $request = lifecycleRequest($user, $site);

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'withdraw', [$request->uuid]);

    $tamperedResponse = $this->post('/portal/call-offs/lifecycle/trash', [
        'operation' => 'withdraw',
        'requests' => [$request->uuid],
        'confirmation_signature' => $signature,
    ]);

    expect($tamperedResponse->status())->toBe(302)
        ->and($tamperedResponse->headers->get('Location'))->toEndWith('/portal/site-dashboard');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($request->operationItems()->count())->toBe(0);

    $this->post('/portal/call-offs/lifecycle/withdraw', [
        'operation' => 'withdraw',
        'requests' => [$request->uuid],
        'confirmation_signature' => $signature,
    ])->assertRedirect('/portal/site-dashboard');

    $replayResponse = $this->post('/portal/call-offs/lifecycle/withdraw', [
        'operation' => 'withdraw',
        'requests' => [$request->uuid],
        'confirmation_signature' => $signature,
    ]);

    expect($replayResponse->status())->toBe(302)
        ->and($replayResponse->headers->get('Location'))->toEndWith('/portal/site-dashboard');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn)
        ->and($request->operationItems()->count())->toBe(1);
});

it('rejects missing, duplicate and mixed batch selections without changing any request', function (): void {
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($user);
    $first = lifecycleRequest($user, $site, 'Plot 201');
    $second = lifecycleRequest($user, $site, 'Plot 202');

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);

    $mixedResponse = $this->post('/portal/call-offs/lifecycle/confirm', [
        'operation' => 'withdraw',
        'requests' => [$first->uuid, $second->uuid],
    ]);

    expect($mixedResponse->status())->toBe(302)
        ->and($mixedResponse->headers->get('Location'))->toEndWith('/portal/site-dashboard');

    $duplicateResponse = $this->post('/portal/call-offs/lifecycle/confirm', [
        'operation' => 'withdraw',
        'requests' => [$first->uuid, $first->uuid],
    ]);

    expect($duplicateResponse->status())->toBe(302);

    expect($first->fresh()->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($second->fresh()->status)->toBe(CallOffRequestStatus::Submitted);
});

it('does not allow approved, rejected or office staff requests to be withdrawn', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = lifecycleUser(PortalRoleIdentifier::SiteManager, $organisation);
    $officeUser = lifecycleUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = lifecycleSite($siteUser);
    $officeUser->assignedSites()->attach($site);
    $approved = lifecycleRequest($siteUser, $site);
    app(ApproveCallOffRequestAction::class)->handle($officeUser, $approved);

    $this->actingAs($siteUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/lifecycle/confirm', ['operation' => 'withdraw', 'requests' => [$approved->uuid]])
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHasErrors('requests');

    expect($approved->fresh()->status)->toBe(CallOffRequestStatus::Approved);
});

it('moves rejected and withdrawn call-offs to scoped customer Trash without exposing internal reasons', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = lifecycleUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = lifecycleSite($user, ['name' => 'Visible Site']);
    $office->assignedSites()->attach($site);
    $rejected = lifecycleRequest($user, $site, 'Rejected Plot');
    app(RejectCallOffRequestAction::class)->handle($office, $rejected, 'Choose another date.', 'Private capacity detail');

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'trash', [$rejected->uuid]);
    $this->post('/portal/call-offs/lifecycle/trash', ['operation' => 'trash', 'requests' => [$rejected->uuid], 'confirmation_signature' => $signature])
        ->assertRedirect('/portal/site-dashboard');

    $this->get('/portal/call-offs/trash')
        ->assertOk()
        ->assertSee('Rejected Plot')
        ->assertSee('Rejected')
        ->assertSee('Choose another date.')
        ->assertDontSee('Private capacity detail');

    $this->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('No requests')
        ->assertSeeInOrder(['Rejected', '0']);

    expect($rejected->fresh()->status)->toBe(CallOffRequestStatus::Rejected)
        ->and($rejected->fresh()->trashed_at)->not->toBeNull()
        ->and($rejected->fresh()->trash_expires_at->isBetween(Carbon::now()->addDays(6), Carbon::now()->addDays(8)))->toBeTrue();
});

it('hides expired and other-site Trash records', function (): void {
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($user);
    $visible = lifecycleRequest($user, $site, 'Visible Trash Plot');
    $expired = lifecycleRequest($user, $site, 'Expired Trash Plot');
    $expired->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'trashed_at' => now()->subDays(8), 'trash_expires_at' => now()->subSecond()])->save();
    $visible->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'trashed_at' => now(), 'trash_expires_at' => now()->addDays(7)])->save();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/trash')
        ->assertOk()
        ->assertSee('Visible Trash Plot')
        ->assertDontSee('Expired Trash Plot');
});

it('restores from Trash and allows the restoration to be undone within the server window', function (): void {
    Carbon::setTestNow('2026-08-06 10:00:00');
    $user = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($user);
    $request = lifecycleRequest($user, $site);
    $request->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'active_conflict_key' => null, 'trashed_at' => now(), 'trash_expires_at' => now()->addDays(7)])->save();

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'restore', [$request->uuid]);
    $this->post('/portal/call-offs/lifecycle/restore', ['operation' => 'restore', 'requests' => [$request->uuid], 'confirmation_signature' => $signature])
        ->assertRedirect('/portal/call-offs/trash');

    $operationUuid = session('quickUndo.operation_uuid');
    expect($request->fresh()->trashed_at)->toBeNull()->and($request->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn);

    $this->post('/portal/call-offs/operations/'.$operationUuid.'/undo')
        ->assertRedirect('/portal/site-dashboard');

    expect($request->fresh()->trashed_at)->not->toBeNull()
        ->and($request->histories()->where('event_type', CallOffHistoryEventType::UndoApplied)->count())->toBe(1);

    Carbon::setTestNow();
});

it('rejects expired Undo and cross-site UUID possession safely', function (): void {
    Carbon::setTestNow('2026-08-06 10:00:00');
    $owner = lifecycleUser(PortalRoleIdentifier::SiteManager);
    $site = lifecycleSite($owner);
    $request = lifecycleRequest($owner, $site);
    $intruder = lifecycleUser(PortalRoleIdentifier::SiteManager, $owner->customerOrganisation);
    lifecycleSite($intruder);

    $this->actingAs($owner)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'withdraw', [$request->uuid]);
    $this->post('/portal/call-offs/lifecycle/withdraw', ['operation' => 'withdraw', 'requests' => [$request->uuid], 'confirmation_signature' => $signature]);
    $operationUuid = session('quickUndo.operation_uuid');

    Carbon::setTestNow('2026-08-06 10:00:06');
    $this->post('/portal/call-offs/operations/'.$operationUuid.'/undo')
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHasErrors('operation');

    $this->actingAs($intruder)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => lifecycleSite($intruder)->id])
        ->post('/portal/call-offs/operations/'.$operationUuid.'/undo')
        ->assertNotFound();

    Carbon::setTestNow();
});

it('rejects Undo by another currently assigned user on the same site', function (): void {
    Carbon::setTestNow('2026-08-06 10:00:00');
    $organisation = CustomerOrganisation::factory()->create();
    $owner = lifecycleUser(PortalRoleIdentifier::SiteManager, $organisation);
    $otherAssignedUser = lifecycleUser(PortalRoleIdentifier::AssistantSiteManager, $organisation);
    $site = lifecycleSite($owner);
    $otherAssignedUser->assignedSites()->attach($site);
    $request = lifecycleRequest($owner, $site);

    $this->actingAs($owner)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $signature = lifecycleConfirmation($this, 'withdraw', [$request->uuid]);
    $this->post('/portal/call-offs/lifecycle/withdraw', [
        'operation' => 'withdraw',
        'requests' => [$request->uuid],
        'confirmation_signature' => $signature,
    ]);

    $operationUuid = session('quickUndo.operation_uuid');

    $this->actingAs($otherAssignedUser)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/operations/'.$operationUuid.'/undo')
        ->assertRedirect('/portal/site-dashboard')
        ->assertSessionHasErrors('operation');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Withdrawn)
        ->and($request->histories()->where('event_type', CallOffHistoryEventType::UndoApplied)->count())->toBe(0);

    Carbon::setTestNow();
});
