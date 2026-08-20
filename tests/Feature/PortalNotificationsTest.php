<?php

use App\Actions\CallOff\ApproveCallOffRequestAction;
use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\PortalNotification;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function notificationUser(PortalRoleIdentifier $role, CustomerOrganisation $organisation): User
{
    return User::factory()->role($role)->create([
        'customer_organisation_id' => $organisation->id,
        'password' => Hash::make('password'),
    ]);
}

function notificationSite(User $user, string $name = 'Notification Site'): Site
{
    $site = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => $name,
    ]);
    $user->assignedSites()->attach($site);

    return $site;
}

function notificationCallOff(User $siteUser, Site $site, string $plotReference = 'Plot N1'): CallOffRequest
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $plotReference]);

    return app(SubmitCallOffBatchAction::class)->handle(
        user: $siteUser,
        site: $site,
        serviceType: CallOffServiceType::Windows,
        requestedDate: Carbon::today()->addDays(10),
        projectedPlots: [$plot],
        customerResponse: 'Please schedule this plot.',
    )->requests->first();
}

it('notifies the submitter and all active Office Staff for submission', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $unassignedOffice = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = notificationSite($siteUser);
    $office->assignedSites()->attach($site);
    $request = notificationCallOff($siteUser, $site);

    expect(PortalNotification::query()->where('type', PortalNotificationType::CallOffSubmitted)->count())->toBe(3)
        ->and($siteUser->portalNotifications()->where('request_uuid', $request->uuid)->exists())->toBeTrue()
        ->and($office->portalNotifications()->where('request_uuid', $request->uuid)->exists())->toBeTrue()
        ->and($unassignedOffice->portalNotifications()->where('request_uuid', $request->uuid)->exists())->toBeTrue();
});

it('notifies the submitting site user after approval without exposing private reason', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::AssistantSiteManager, $organisation);
    $office = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = notificationSite($siteUser);
    $office->assignedSites()->attach($site);
    $request = notificationCallOff($siteUser, $site, 'Approved Plot');

    app(ApproveCallOffRequestAction::class)->handle($office, $request, 'Approved for the requested date.', 'Private reason.');

    $notification = $siteUser->portalNotifications()->where('type', PortalNotificationType::CallOffApproved)->firstOrFail();

    expect($notification->customer_response)->toBe('Approved for the requested date.')
        ->and($notification->toArray())->not->toHaveKey('internal_reason')
        ->and(json_encode($notification->toArray()))->not->toContain('Private reason');
});

it('notifies the submitting site user after rejection with the customer response only', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::FinishingForeman, $organisation);
    $office = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = notificationSite($siteUser);
    $office->assignedSites()->attach($site);
    $request = notificationCallOff($siteUser, $site, 'Rejected Plot');

    app(RejectCallOffRequestAction::class)->handle($office, $request, 'Please select another date.', 'Internal-only reason.');

    $notification = $siteUser->portalNotifications()->where('type', PortalNotificationType::CallOffRejected)->firstOrFail();

    expect($notification->customer_response)->toBe('Please select another date.')
        ->and(json_encode($notification->toArray()))->not->toContain('Internal-only reason');
});

it('does not create or expose notifications for development preview users', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $previewUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $previewUser->forceFill(['is_preview_user' => true])->save();
    $site = notificationSite($previewUser);

    notificationCallOff($previewUser, $site, 'Preview Plot');

    expect(PortalNotification::query()->count())->toBe(0);

    $this->actingAs($previewUser)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'data');
});

it('is idempotent when the same event is handled more than once', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $site = notificationSite($siteUser);
    $request = notificationCallOff($siteUser, $site);
    $service = app(PortalNotificationService::class);

    $service->createForRequest($request, PortalNotificationType::CallOffSubmitted);
    $service->createForRequest($request, PortalNotificationType::CallOffSubmitted);

    expect($siteUser->portalNotifications()->where('event_key', 'call_off_submitted:'.$request->uuid)->count())->toBe(1);
});

it('scopes unread count, mark-read, mark-all-read and dismissal to the signed-in user', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $otherUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $site = notificationSite($siteUser);
    $request = notificationCallOff($siteUser, $site);
    $notification = $siteUser->portalNotifications()->firstOrFail();

    $this->actingAs($siteUser)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.request_uuid', $request->uuid)
        ->assertJsonMissingPath('data.0.internal_reason');

    $this->withHeader('Accept', 'application/json')
        ->post('/portal/notifications/'.$notification->uuid.'/read')
        ->assertOk()
        ->assertJsonPath('status', 'read');
    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->withHeader('Accept', 'application/json')
        ->post('/portal/notifications/read-all')->assertOk()->assertJsonPath('marked_read', 0);
    $this->withHeader('Accept', 'application/json')
        ->post('/portal/notifications/'.$notification->uuid.'/dismiss')->assertOk();
    expect($notification->fresh()->dismissed_at)->not->toBeNull()
        ->and($siteUser->portalNotifications()->active()->count())->toBe(0);

    $this->actingAs($otherUser)
        ->post('/portal/notifications/'.$notification->uuid.'/read')
        ->assertNotFound();
});

it('marks all own unread notifications and does not affect another recipient', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $secondSiteUser = notificationUser(PortalRoleIdentifier::AssistantSiteManager, $organisation);
    $site = notificationSite($siteUser);
    $secondSiteUser->assignedSites()->attach($site);
    notificationCallOff($siteUser, $site, 'Read All Plot');

    $this->actingAs($siteUser)
        ->withHeader('Accept', 'application/json')
        ->post('/portal/notifications/read-all')->assertJsonPath('marked_read', 1);

    expect($siteUser->portalNotifications()->unread()->count())->toBe(0)
        ->and($secondSiteUser->portalNotifications()->unread()->count())->toBe(0);
});

it('fails malformed, cross-user and revoked notification links safely', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $site = notificationSite($siteUser);
    notificationCallOff($siteUser, $site);
    $notification = $siteUser->portalNotifications()->firstOrFail();

    $this->actingAs($siteUser)->get('/portal/notifications/not-a-uuid/open')->assertNotFound();

    $otherUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $this->actingAs($otherUser)->get('/portal/notifications/'.$notification->uuid.'/open')->assertNotFound();

    $siteUser->assignedSites()->detach($site);
    $this->actingAs($siteUser)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'data');
    $this->actingAs($siteUser)->get('/portal/notifications/'.$notification->uuid.'/open')->assertNotFound();
});

it('hides retained notifications after the recipient role changes', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = notificationSite($siteUser);
    $office->assignedSites()->attach($site);
    notificationCallOff($siteUser, $site, 'Role Changed Plot');
    $notification = $siteUser->portalNotifications()->firstOrFail();

    $siteUser->forceFill(['portal_role_id' => $office->portal_role_id])->save();
    $siteUser->refresh();

    $this->actingAs($siteUser)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'data');

    $this->actingAs($siteUser)
        ->get('/portal/notifications/'.$notification->uuid.'/open')
        ->assertNotFound();
});

it('keeps the call-off transition successful when notification creation fails', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = notificationUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = notificationUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = notificationSite($siteUser);
    $office->assignedSites()->attach($site);
    $request = notificationCallOff($siteUser, $site, 'Failure Plot');

    $mock = Mockery::mock(PortalNotificationService::class);
    $mock->shouldReceive('createForRequest')->andThrow(new RuntimeException('notification store unavailable'));
    $this->app->instance(PortalNotificationService::class, $mock);

    app(ApproveCallOffRequestAction::class)->handle($office, $request, 'Approved.', 'Private.');

    expect($request->fresh()->status)->toBe(CallOffRequestStatus::Approved);
});
