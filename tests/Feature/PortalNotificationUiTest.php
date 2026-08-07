<?php

use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function uiNotificationUser(CustomerOrganisation $organisation, PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): User
{
    return User::factory()->role($role)->create([
        'customer_organisation_id' => $organisation->id,
        'password' => Hash::make('password'),
    ]);
}

function uiNotificationCallOff(User $user, Site $site, string $plotReference = 'Plot UI-1'): void
{
    $plot = ProjectedPlot::factory()->create([
        'site_id' => $site->id,
        'plot_reference' => $plotReference,
    ]);

    app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: CallOffServiceType::Windows,
        requestedDate: Carbon::today()->addDays(10),
        projectedPlots: [$plot],
        customerResponse: 'Please schedule this plot.',
    );
}

it('shows the authenticated notification bell and authorised unread count', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = uiNotificationUser($organisation);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);
    uiNotificationCallOff($user, $site);

    $this->actingAs($user)
        ->get('/sites/select')
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('1 unread')
        ->assertSee(route('portal.notifications.centre'));
});

it('renders the full centre without javascript using only the signed-in users notifications', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = uiNotificationUser($organisation);
    $otherUser = uiNotificationUser($organisation, PortalRoleIdentifier::AssistantSiteManager);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);
    $otherUser->assignedSites()->attach($site);
    uiNotificationCallOff($user, $site, 'Visible UI Plot');

    $this->actingAs($user)
        ->get('/portal/notifications/centre')
        ->assertOk()
        ->assertSee('Call-off submitted')
        ->assertSee('Visible UI Plot')
        ->assertSee('Unread')
        ->assertSee('Open update')
        ->assertSee('/portal/notifications/')
        ->assertDontSee('internal_reason');

    $this->actingAs($otherUser)
        ->get('/portal/notifications/centre')
        ->assertOk()
        ->assertSee('No notifications yet')
        ->assertDontSee('Visible UI Plot');
});

it('shows a read notification distinctly after the mark-read endpoint is used', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = uiNotificationUser($organisation);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);
    uiNotificationCallOff($user, $site, 'Read UI Plot');
    $notification = $user->portalNotifications()->firstOrFail();

    $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post(route('portal.notifications.read', $notification->uuid))->assertOk();

    $this->actingAs($user)
        ->get('/portal/notifications/centre')
        ->assertOk()
        ->assertSee('Read')
        ->assertDontSee('>Unread<')
        ->assertDontSee('Mark all as read');
});

it('provides no-javascript forms for mark-all-read and dismissal', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = uiNotificationUser($organisation);
    $site = Site::factory()->create(['customer_organisation_id' => $organisation->id]);
    $user->assignedSites()->attach($site);
    uiNotificationCallOff($user, $site, 'Action UI Plot');
    $notification = $user->portalNotifications()->firstOrFail();

    $this->actingAs($user)
        ->get('/portal/notifications/centre')
        ->assertSee(route('portal.notifications.read-all'))
        ->assertSee(route('portal.notifications.read', $notification->uuid))
        ->assertSee(route('portal.notifications.dismiss', $notification->uuid));

    $this->actingAs($user)
        ->post(route('portal.notifications.read-all'))
        ->assertRedirect(route('portal.notifications.centre'))
        ->assertSessionHas('status', '1 notification marked as read.');
    $this->actingAs($user)
        ->post(route('portal.notifications.dismiss', $notification->uuid))
        ->assertRedirect(route('portal.notifications.centre'))
        ->assertSessionHas('status', 'Notification dismissed.');

    $this->actingAs($user)->get('/portal/notifications/centre')->assertSee('No notifications yet');
});
