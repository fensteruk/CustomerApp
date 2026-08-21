<?php

use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
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

function sprint2aUiUser(?CustomerOrganisation $organisation = null): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()->role(PortalRoleIdentifier::SiteManager)->create([
        'customer_organisation_id' => $organisation->id,
        'password' => Hash::make('password'),
    ]);
}

function sprint2aUiSite(User $user): Site
{
    $site = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id, 'name' => 'UI Test Site']);
    $user->assignedSites()->attach($site);

    return $site;
}

function sprint2aUiRequest(User $user, Site $site, string $reference, CallOffServiceType $service = CallOffServiceType::Windows): CallOffRequest
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);

    return app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: $service,
        requestedDate: Carbon::today()->addDays(10),
        projectedPlots: [$plot],
        customerResponse: 'Initial message.',
    )->requests->first();
}

it('renders the active-site browsing controls and retains filters in paginator links', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);

    foreach (range(1, 16) as $number) {
        sprint2aUiRequest($user, $site, sprintf('UI Plot %02d', $number), $number % 2 === 0 ? CallOffServiceType::Cml : CallOffServiceType::Windows);
    }

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $response = $this->get('/portal/site-dashboard?plot=UI+Plot&service=cml&status=awaiting_date');

    $response->assertOk()
        ->assertSee('Find a plot')
        ->assertSee('All services')
        ->assertSee('All statuses')
        ->assertSee('Clear filters')
        ->assertSee('UI Plot 02');

    $this->get('/portal/site-dashboard?plot=UI+Plot')->assertSee('page=2')->assertSee('plot=UI%20Plot');
});

it('renders a useful no-result state without leaking another sites call-offs', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $user->assignedSites()->attach($otherSite);
    sprint2aUiRequest($user, $otherSite, 'Other Site UI Plot');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard?plot=nothing')
        ->assertOk()
        ->assertSee('No projected plots')
        ->assertDontSee('Other Site UI Plot');
});

it('renders lifecycle controls with disabled-state bindings and keeps approved call-offs unselectable', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);
    $submitted = sprint2aUiRequest($user, $site, 'Submitted UI Plot');
    $approved = sprint2aUiRequest($user, $site, 'Approved UI Plot');
    $approved->forceFill(['status' => CallOffRequestStatus::Approved])->save();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee(':disabled="!can(\'withdraw\')"', false)
        ->assertSee(':disabled="!can(\'trash\')"', false)
        ->assertSee('data-withdraw="true"', false)
        ->assertDontSee('value="'.$approved->uuid.'"', false)
        ->assertSee($submitted->uuid, false);
});

it('renders Trash paging and restore selection only for the visible page', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);

    foreach (range(1, 16) as $number) {
        $request = sprint2aUiRequest($user, $site, sprintf('Trash UI Plot %02d', $number));
        $request->forceFill([
            'status' => CallOffRequestStatus::Withdrawn,
            'active_conflict_key' => null,
            'trashed_at' => now()->subMinutes($number),
            'trash_expires_at' => now()->addDays(7),
        ])->save();
    }

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/trash')
        ->assertOk()
        ->assertSee('Showing 1–15 of 16 restorable call-offs')
        ->assertSee('page=2')
        ->assertSee('Selections apply only to this page')
        ->assertSee(':disabled="!can(\'restore\')"', false);
});

it('shows Resubmit only for a rejected dashboard call-off and presents customer-safe source context', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $office->assignedSites()->attach($site);
    $rejected = sprint2aUiRequest($user, $site, 'Rejected UI Plot');
    app(RejectCallOffRequestAction::class)->handle($office, $rejected, 'Choose another date.', 'Private reason.');
    $submitted = sprint2aUiRequest($user, $site, 'Submitted UI Plot');

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee(route('portal.call-offs.resubmit.create', $rejected))
        ->assertDontSee(route('portal.call-offs.resubmit.create', $submitted));

    $this->get(route('portal.call-offs.resubmit.create', $rejected))
        ->assertOk()
        ->assertSee('UI Test Site')
        ->assertSee('Rejected UI Plot')
        ->assertSee('Choose another date.')
        ->assertDontSee('Private reason.')
        ->assertDontSee('site_id', false)
        ->assertDontSee('service_identifier', false)
        ->assertDontSee('plot_id', false);
});

it('hides Resubmit when a rejected request has a current active conflict', function (): void {
    $user = sprint2aUiUser();
    $site = sprint2aUiSite($user);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $office->assignedSites()->attach($site);
    $rejected = sprint2aUiRequest($user, $site, 'Conflicted Resubmission Plot');
    app(RejectCallOffRequestAction::class)->handle($office, $rejected, 'Choose another date.', 'Private reason.');

    app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: CallOffServiceType::Windows,
        requestedDate: Carbon::today()->addDays(21),
        projectedPlots: [$rejected->projectedPlot],
    );

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertOk()
        ->assertDontSee(route('portal.call-offs.resubmit.create', $rejected));
});

it('does not render the dormant Laravel welcome template or a registration route', function (): void {
    $this->get('/')->assertRedirect('/login');
    $this->get('/register')->assertNotFound();
    expect(file_exists(resource_path('views/welcome.blade.php')))->toBeFalse();
});

it('renders a responsive notification panel with a recoverable loading failure state', function (): void {
    $user = sprint2aUiUser();

    $this->actingAs($user)
        ->get('/sites/select')
        ->assertOk()
        ->assertSee('fixed inset-x-2 top-20', false)
        ->assertSee('Notifications could not be loaded.')
        ->assertSee('Try again')
        ->assertSee('View all notifications');

    $notificationScript = file_get_contents(resource_path('js/app.js'));

    expect($notificationScript)
        ->toContain('fetchError: false')
        ->toContain('this.fetchError = false;')
        ->toContain('this.fetchError = true;');
});
