<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $this->actingAs($this->office);
});

it('presents Office identity and global access without a missing assignment warning', function (): void {
    $this->get(route('office.workspace.users.show', $this->office))->assertOk()
        ->assertSee($this->office->name)->assertSee($this->office->email)
        ->assertSee('Fenster Office Staff')->assertSee('Global Office access')->assertSee('Access configured')
        ->assertSee('No site assignments required')->assertDontSee('No sites assigned')
        ->assertSee('You cannot deactivate your own account.')->assertDontSee('Delete user');
});

it('presents each external role with its customer and assigned sites', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create(['name' => 'Fictional Homes']);
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $customer->id]);
    $sites = Site::factory()->count(2)->create(['customer_organisation_id' => $customer->id]);
    $user->assignedSites()->attach($sites);
    $response = $this->get(route('office.workspace.users.show', $user))->assertOk()
        ->assertSee($user->name)->assertSee($user->email)->assertSee($role->label())->assertSee('Fictional Homes')
        ->assertSee('Customer + assigned sites')->assertSee('2 assigned')->assertSee('Access configured')
        ->assertSee(route('office.workspace.users.edit', $user).'#user-access', false);
    foreach ($sites as $site) {
        $response->assertSee($site->name);
    }
})->with(PortalRoleIdentifier::siteRoles());

it('explains zero assignments and distinguishes a missing customer from Office access', function (): void {
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => null]);
    $this->get(route('office.workspace.users.show', $user))->assertOk()
        ->assertSee('No customer assigned')->assertSee('No sites assigned')
        ->assertSee('This user cannot access a site yet.')->assertDontSee('Global Office access')
        ->assertDontSee('Access configured');
});

it('does not present inactive customer or site assignments as usable access', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'is_active' => false]);
    $user->assignedSites()->attach($site);
    $this->get(route('office.workspace.users.show', $user))->assertOk()
        ->assertSee('No available sites')->assertDontSee('Access configured');
    $customer->forceFill(['is_active' => false])->save();
    $this->get(route('office.workspace.users.show', $user))->assertOk()
        ->assertSee('Customer inactive')->assertDontSee('Access configured');
});

it('labels historical Office assignments without restricting global access', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $this->office->update(['customer_organisation_id' => $customer->id]);
    $this->office->assignedSites()->attach($site);
    $this->get(route('office.workspace.users.show', $this->office))->assertOk()
        ->assertSee('Global Office access')->assertSee('Recorded assignments')->assertSee($site->name)
        ->assertSee('they do not limit global Office access.')->assertDontSee('No sites assigned');
});

it('keeps the inactive state clear and reactivates through the existing confirmation route', function (PortalRoleIdentifier $role): void {
    $user = User::factory()->role($role)->create(['is_active' => false]);
    $this->get(route('office.workspace.users.show', $user))->assertOk()
        ->assertSee('Access paused')->assertSee('Reactivate user')->assertDontSee('Access configured');
    $this->get(route('office.workspace.users.lifecycle', $user))->assertOk()
        ->assertSee($user->email)->assertSee('Confirm Reactivation')->assertSee('name="lock_version"', false);
    $this->post(route('portal.office.users.reactivate', $user), ['lock_version' => $user->fresh()->lock_version])->assertRedirect();
    expect($user->fresh()->is_active)->toBeTrue();
})->with([PortalRoleIdentifier::FensterOfficeStaff, PortalRoleIdentifier::SiteManager]);

it('renders editing labels and associated validation errors while retaining attempted input', function (): void {
    $user = User::factory()->role(PortalRoleIdentifier::FinishingForeman)->create();
    $this->from(route('office.workspace.users.edit', $user))->patch(route('portal.office.users.update', $user), [
        'name' => '', 'email' => 'attempt@example.test', 'role' => PortalRoleIdentifier::FinishingForeman->value,
        'customer_organisation_id' => $user->customer_organisation_id, 'lock_version' => $user->fresh()->lock_version,
    ])->assertSessionHasErrors('name');
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('office.workspace.users.edit', $user))->assertOk()
        ->assertSee('Identity')->assertSee('Role &amp; access', false)->assertSee('Assigned Sites')
        ->assertSee('aria-describedby="name-error"', false)->assertSee('id="name-error"', false)
        ->assertSee('attempt@example.test')->assertSee('Leave both fields blank to keep the current password.')
        ->assertSee('Save Changes')->assertSee('Confirm customer change.');
});

it('keeps cross-customer tampering rejected through the redesigned edit form endpoint', function (): void {
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create();
    $foreignSite = Site::factory()->create();
    $this->patch(route('portal.office.users.update', $user), [
        'name' => $user->name, 'email' => $user->email, 'role' => PortalRoleIdentifier::SiteManager->value,
        'customer_organisation_id' => $user->customer_organisation_id,
        'site_ids' => [$foreignSite->id], 'lock_version' => $user->fresh()->lock_version,
    ])->assertSessionHasErrors('site_ids');
    expect($user->fresh()->assignedSites)->toBeEmpty();
});

it('denies external roles direct access to detail edit and lifecycle pages', function (PortalRoleIdentifier $role): void {
    $this->actingAs(User::factory()->role($role)->create());
    foreach (['show', 'edit', 'lifecycle'] as $page) {
        $this->get(route('office.workspace.users.'.$page, $this->office))->assertForbidden();
    }
})->with(PortalRoleIdentifier::siteRoles());

it('requires an active signed-in Office account for user detail', function (): void {
    auth()->logout();
    $this->get(route('office.workspace.users.show', $this->office))->assertRedirect(route('login'));
    $this->office->update(['is_active' => false]);
    $this->actingAs($this->office)->get(route('office.workspace.users.show', $this->office))->assertRedirect(route('login'));
});
