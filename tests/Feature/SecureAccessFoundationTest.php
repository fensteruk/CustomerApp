<?php

use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function portalRoleForTest(PortalRoleIdentifier $identifier): PortalRole
{
    return PortalRole::query()->where('identifier', $identifier->value)->firstOrFail();
}

function portalUserForTest(
    PortalRoleIdentifier $role,
    ?CustomerOrganisation $organisation = null,
    array $attributes = [],
): User {
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()
        ->role($role)
        ->create(array_merge([
            'customer_organisation_id' => $organisation->id,
            'password' => Hash::make('password'),
        ], $attributes));
}

function assignedSiteForTest(User $user, array $attributes = []): Site
{
    $site = Site::factory()->create(array_merge([
        'customer_organisation_id' => $user->customer_organisation_id,
    ], $attributes));

    $user->assignedSites()->attach($site);

    return $site;
}

it('logs in with valid credentials', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out authenticated users', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('sends password reset links', function (): void {
    Notification::fake();

    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets passwords with a valid token', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect('/login');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('does not expose public registration', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

it('blocks inactive users from login and protected access', function (): void {
    $inactiveUser = portalUserForTest(PortalRoleIdentifier::SiteManager, attributes: ['is_active' => false]);

    $this->post('/login', [
        'email' => $inactiveUser->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $activeUser = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->actingAs($activeUser);
    $activeUser->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('creates the four stable portal roles', function (): void {
    expect(PortalRole::query()->pluck('identifier')->sort()->values()->all())->toBe([
        PortalRoleIdentifier::AssistantSiteManager->value,
        PortalRoleIdentifier::FensterOfficeStaff->value,
        PortalRoleIdentifier::FinishingForeman->value,
        PortalRoleIdentifier::SiteManager->value,
    ]);
});

it('shows site users only their assigned sites', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::AssistantSiteManager);
    $assignedSite = assignedSiteForTest($user, ['name' => 'Assigned Alpha']);

    Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => 'Hidden Beta',
    ]);

    $this->actingAs($user)
        ->get('/sites/select')
        ->assertOk()
        ->assertSee($assignedSite->name)
        ->assertDontSee('Hidden Beta');
});

it('shows office staff only assigned review sites', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::FensterOfficeStaff);
    $assignedSite = assignedSiteForTest($user, ['name' => 'Assigned Review Scope']);

    Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
        'name' => 'Hidden Review Scope',
    ]);

    $this->actingAs($user)
        ->get('/portal/review-requests')
        ->assertOk()
        ->assertSee($assignedSite->name)
        ->assertDontSee('Hidden Review Scope');
});

it('rejects unassigned site ids server side', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $site = Site::factory()->create([
        'customer_organisation_id' => $user->customer_organisation_id,
    ]);

    $this->actingAs($user)
        ->post('/sites/active', ['site_id' => $site->id])
        ->assertForbidden();

    $this->assertFalse(session()->has(EnsureActiveSiteIsAssigned::SESSION_KEY));
});

it('requires active-site selection to use an assigned site', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $site = assignedSiteForTest($user);

    $this->actingAs($user)
        ->post('/sites/active', ['site_id' => $site->id])
        ->assertRedirect('/portal/site-dashboard');

    expect(session(EnsureActiveSiteIsAssigned::SESSION_KEY))->toBe($site->id);
});

it('clears a revoked active-site assignment before showing site-scoped data', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $site = assignedSiteForTest($user);

    $user->assignedSites()->detach($site);

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertRedirect('/sites/select');

    $this->assertFalse(session()->has(EnsureActiveSiteIsAssigned::SESSION_KEY));
});

it('prevents active-site context from crossing customer boundaries', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $otherOrganisation = CustomerOrganisation::factory()->create();
    $otherSite = Site::factory()->create([
        'customer_organisation_id' => $otherOrganisation->id,
    ]);

    $user->assignedSites()->attach($otherSite);

    $this->actingAs($user)
        ->post('/sites/active', ['site_id' => $otherSite->id])
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $otherSite->id])
        ->get('/portal/site-dashboard')
        ->assertRedirect('/sites/select');
});

it('routes site roles to site selection or the active-site dashboard', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::FinishingForeman);
    $site = assignedSiteForTest($user);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/sites/select');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/dashboard')
        ->assertRedirect('/portal/site-dashboard');
});

it('routes office staff to Review Requests', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::FensterOfficeStaff);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/portal/review-requests');
});

it('does not let site roles access the office review dashboard', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);

    $this->actingAs($user)
        ->get('/portal/review-requests')
        ->assertForbidden();
});

it('does not let office staff select or access a site dashboard', function (): void {
    $user = portalUserForTest(PortalRoleIdentifier::FensterOfficeStaff);
    $site = assignedSiteForTest($user);

    $this->actingAs($user)
        ->get('/sites/select')
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/sites/active', ['site_id' => $site->id])
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard')
        ->assertForbidden();
});

it('allows role preview in local or test using controlled preview users', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->post('/development/role-preview', [
        'role' => PortalRoleIdentifier::SiteManager->value,
    ])->assertRedirect('/dashboard');

    $user = auth()->user();

    expect($user)->not->toBeNull()
        ->and($user->is_preview_user)->toBeTrue()
        ->and($user->hasPortalRole(PortalRoleIdentifier::SiteManager))->toBeTrue();
});

it('does not let browser input mutate a real user role during preview', function (): void {
    $this->seed(DatabaseSeeder::class);

    $user = portalUserForTest(PortalRoleIdentifier::SiteManager);
    $officeRole = portalRoleForTest(PortalRoleIdentifier::FensterOfficeStaff);

    $this->actingAs($user)
        ->post('/development/role-preview', [
            'role' => PortalRoleIdentifier::SiteManager->value,
            'portal_role_id' => $officeRole->id,
        ])
        ->assertRedirect('/dashboard');

    expect($user->fresh()->hasPortalRole(PortalRoleIdentifier::SiteManager))->toBeTrue();
});

it('hides role preview outside local and test environments', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->get('/development/role-preview')->assertNotFound();
    $this->withSession(['_token' => 'preview-guard-token'])
        ->post('/development/role-preview', [
            'role' => PortalRoleIdentifier::SiteManager->value,
            '_token' => 'preview-guard-token',
        ])
        ->assertNotFound();
});

it('does not expose standalone development dashboard routes without access checks', function (): void {
    $this->get('/development/select-site')->assertNotFound();
    $this->get('/development/site-dashboard')->assertNotFound();
    $this->get('/development/review-requests')->assertNotFound();
});

it('redirects unauthenticated users away from protected routes', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/sites/select')->assertRedirect('/login');
    $this->get('/portal/site-dashboard')->assertRedirect('/login');
    $this->get('/portal/review-requests')->assertRedirect('/login');
});
