<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'name' => 'Office Reviewer', 'customer_organisation_id' => null,
    ]);
    $this->actingAs($this->office);
});

it('shows global Office access without an assigned-sites warning', function (): void {
    $this->get(route('office.workspace.users.index'))->assertOk()
        ->assertSee('Office Reviewer')->assertSee('Fenster Office Staff')->assertSee('Global Office access')
        ->assertSee('Access configured')->assertSee('Manage user '.$this->office->name)
        ->assertDontSee('No sites assigned')->assertDontSee('0 sites')
        ->assertViewHas('summary', ['total' => 1, 'office' => 1, 'external' => 0, 'attention' => 0]);
});

it('shows each external role and their customer and site names with a bounded preview', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create(['name' => 'Willow Homes']);
    $user = User::factory()->role($role)->create(['name' => 'External Person', 'customer_organisation_id' => $customer->id]);
    $sites = Site::factory()->count(5)->create(['customer_organisation_id' => $customer->id]);
    $user->assignedSites()->attach($sites);
    $response = $this->get(route('office.workspace.users.index', ['search' => 'External Person']))->assertOk()
        ->assertSee($role->label())->assertSee('Willow Homes')->assertSee('5 sites')->assertSee('+2 more')
        ->assertDontSee('No sites assigned');
    expect($response->viewData('users')->first()->assignedSites)->toHaveCount(3);
    foreach ($response->viewData('users')->first()->assignedSites as $site) {
        $response->assertSee($site->name);
    }
})->with(PortalRoleIdentifier::siteRoles());

it('highlights missing assignments and retains both management and direct edit actions', function (): void {
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create();
    $this->get(route('office.workspace.users.index'))->assertOk()
        ->assertSee('No sites assigned')->assertSee('This user cannot access a site yet.')
        ->assertSee('Assign site to '.$user->name)
        ->assertSee(route('office.workspace.users.edit', $user), false)
        ->assertSee(route('office.workspace.users.show', $user), false)
        ->assertViewHas('summary', ['total' => 2, 'office' => 1, 'external' => 1, 'attention' => 1]);
});

it('does not mistake a missing external customer for global Office access', function (): void {
    User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['name' => 'Missing Customer', 'customer_organisation_id' => null]);
    $this->get(route('office.workspace.users.index', ['search' => 'Missing Customer']))->assertOk()
        ->assertSee('No customer assigned')->assertSee('Configure access')
        ->assertDontSee('Global Office access')->assertDontSee('Access configured');
});

it('shows inactive Office accounts without claiming that they can currently manage access', function (): void {
    User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['name' => 'Paused Office', 'is_active' => false]);
    $this->get(route('office.workspace.users.index', ['active' => 'inactive']))->assertOk()
        ->assertSee('Paused Office')->assertSee('Inactive')->assertSee('Access is paused')
        ->assertDontSee('Access configured')->assertDontSee('Can manage all customers');
});

it('combines search role customer and status filters while keeping global summary counts', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $foreign = CustomerOrganisation::factory()->create();
    $wanted = User::factory()->role(PortalRoleIdentifier::AssistantSiteManager)->create([
        'name' => 'Wanted Person', 'email' => 'matching@example.test', 'customer_organisation_id' => $customer->id, 'is_active' => false,
    ]);
    User::factory()->role(PortalRoleIdentifier::AssistantSiteManager)->create(['name' => 'Foreign Person', 'email' => 'matching-foreign@example.test', 'customer_organisation_id' => $foreign->id, 'is_active' => false]);
    User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['email' => 'matching-role@example.test', 'customer_organisation_id' => $customer->id, 'is_active' => false]);
    User::factory()->role(PortalRoleIdentifier::AssistantSiteManager)->create(['email' => 'matching-active@example.test', 'customer_organisation_id' => $customer->id]);
    User::factory()->create(['is_preview_user' => true]);
    $response = $this->get(route('office.workspace.users.index', [
        'search' => 'matching', 'role' => PortalRoleIdentifier::AssistantSiteManager->value, 'customer' => $customer->uuid, 'active' => 'inactive',
    ]))->assertOk()->assertSee('Wanted Person')->assertDontSee('Foreign Person')
        ->assertViewHas('summary', ['total' => 5, 'office' => 1, 'external' => 4, 'attention' => 4]);
    expect($response->viewData('users')->pluck('id')->all())->toBe([$wanted->id]);
});

it('retains filters through pagination and excludes preview users', function (): void {
    User::factory()->count(21)->role(PortalRoleIdentifier::SiteManager)->create(['name' => 'Paged Person']);
    User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['name' => 'Paged Preview', 'is_preview_user' => true]);
    $response = $this->get(route('office.workspace.users.index', ['role' => 'site_manager', 'search' => 'Paged', 'active' => 'active']))
        ->assertOk()->assertDontSee('Paged Preview')->assertSee('page=2', false)->assertSee('role=site_manager', false);
    expect($response->viewData('users')->total())->toBe(21);
});

it('escapes account content and displays an empty filtered result', function (): void {
    User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['name' => '<script>alert(1)</script>']);
    $this->get(route('office.workspace.users.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('office.workspace.users.index', ['search' => 'No match']))->assertOk()->assertSee('No users found');
});

it('rejects malformed filter values', function (): void {
    $this->getJson(route('office.workspace.users.index', ['role' => 'admin', 'customer' => 'not-a-uuid', 'active' => 'yes']))
        ->assertUnprocessable()->assertJsonValidationErrors(['role', 'customer', 'active']);
});

it('denies direct access to every external role', function (PortalRoleIdentifier $role): void {
    $this->actingAs(User::factory()->role($role)->create())->get(route('office.workspace.users.index'))->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());

it('requires sign in and an active real Office account', function (): void {
    auth()->logout();
    $this->get(route('office.workspace.users.index'))->assertRedirect(route('login'));
    $this->office->update(['is_active' => false]);
    $this->actingAs($this->office)->get(route('office.workspace.users.index'))->assertRedirect(route('login'));
});
