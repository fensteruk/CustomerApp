<?php

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Enums\PortalRoleIdentifier;
use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates an external user with one customer and multiple sites from the Office UI', function (): void {
    $office = officeUser();
    $customer = CustomerOrganisation::factory()->create(['name' => 'TEST — Acme Developments']);
    $siteA = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'TEST — Willow Park',
        'external_source' => 'redzebra',
        'external_identifier' => 'TEST-WILLOW-PARK',
    ]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Oaklands']);

    $response = $this->actingAs($office)->post(route('portal.office.users.store'), [
        'name' => 'Fictional Site Manager', 'email' => 'fictional@example.test',
        'role' => PortalRoleIdentifier::SiteManager->value, 'customer_organisation_id' => $customer->id,
        'site_ids' => [$siteA->id, $siteB->id], 'password' => 'SafePassword1234',
        'password_confirmation' => 'SafePassword1234', 'is_active' => true,
    ]);

    $user = User::query()->where('email', 'fictional@example.test')->firstOrFail();
    $response->assertRedirect(route('office.workspace.users.show', $user));
    expect($user->uuid)->not->toBeNull()
        ->and($user->customer_organisation_id)->toBe($customer->id)
        ->and($user->assignedSites()->pluck('sites.id')->sort()->values()->all())->toBe([$siteA->id, $siteB->id])
        ->and(Hash::check('SafePassword1234', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('SafePassword1234');

    expect(AdministrativeAudit::query()->where('entity_type', AdministrativeEntityType::User)->where('entity_uuid', $user->uuid)->pluck('action')->all())
        ->toBe([AdministrativeAction::Created, AdministrativeAction::SiteAssigned, AdministrativeAction::SiteAssigned]);
});

it('changes customer atomically only after confirmation and removes incompatible sites', function (): void {
    $office = officeUser();
    $customerA = CustomerOrganisation::factory()->create();
    $customerB = CustomerOrganisation::factory()->create();
    $siteA = Site::factory()->create(['customer_organisation_id' => $customerA->id]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $customerB->id]);
    $user = externalUser($customerA);
    $user->assignedSites()->attach($siteA);

    $payload = ['name' => $user->name, 'email' => $user->email, 'role' => PortalRoleIdentifier::SiteManager->value,
        'customer_organisation_id' => $customerB->id, 'site_ids' => [$siteB->id], 'lock_version' => 1];

    $this->actingAs($office)->patch(route('portal.office.users.update', $user), $payload)
        ->assertSessionHasErrors('confirm_customer_change');
    expect($user->fresh()->customer_organisation_id)->toBe($customerA->id)
        ->and($user->assignedSites()->pluck('sites.id')->all())->toBe([$siteA->id]);

    $this->patch(route('portal.office.users.update', $user), $payload + ['confirm_customer_change' => true])->assertRedirect();
    expect($user->fresh()->customer_organisation_id)->toBe($customerB->id)
        ->and($user->assignedSites()->pluck('sites.id')->all())->toBe([$siteB->id]);
});

it('blocks cross-customer assignments and forged user identifiers', function (): void {
    $office = officeUser();
    $customerA = CustomerOrganisation::factory()->create();
    $customerB = CustomerOrganisation::factory()->create();
    $siteA = Site::factory()->create(['customer_organisation_id' => $customerA->id]);
    $foreign = externalUser($customerB);

    $this->actingAs($office)->post(route('portal.office.sites.users.store', [$customerA, $siteA]), ['user_uuid' => $foreign->uuid])
        ->assertSessionHasErrors('user');
    $this->post(route('portal.office.sites.users.store', [$customerA, $siteA]), ['user_uuid' => '00000000-0000-4000-8000-000000000000'])
        ->assertSessionHasErrors('user_uuid');
    expect($foreign->assignedSites()->exists())->toBeFalse();
});

it('preserves historical Office scope on ordinary edits and explicitly clears external scope on role conversion', function (): void {
    $actor = officeUser();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $historicalOffice = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => $customer->id]);
    $historicalOffice->assignedSites()->attach($site);

    $this->actingAs($actor)->patch(route('portal.office.users.update', $historicalOffice), [
        'name' => 'Updated Office Name', 'email' => $historicalOffice->email,
        'role' => PortalRoleIdentifier::FensterOfficeStaff->value, 'lock_version' => 1,
    ])->assertRedirect();
    expect($historicalOffice->fresh()->customer_organisation_id)->toBe($customer->id)
        ->and($historicalOffice->assignedSites()->whereKey($site->id)->exists())->toBeTrue();

    $external = externalUser($customer);
    $external->assignedSites()->attach($site);
    $payload = ['name' => $external->name, 'email' => $external->email,
        'role' => PortalRoleIdentifier::FensterOfficeStaff->value, 'lock_version' => 1];
    $this->patch(route('portal.office.users.update', $external), $payload)->assertSessionHasErrors('confirm_customer_change');
    $this->patch(route('portal.office.users.update', $external), $payload + ['confirm_customer_change' => true])->assertRedirect();
    expect($external->fresh()->customer_organisation_id)->toBeNull()
        ->and($external->assignedSites()->exists())->toBeFalse()
        ->and($external->fresh()->isFensterOfficeStaff())->toBeTrue();
});

it('assigns and removes site access from the site workflow with immediate scope changes', function (): void {
    $office = officeUser();
    $customer = CustomerOrganisation::factory()->create();
    $siteA = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $siteB = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = externalUser($customer);

    $this->actingAs($office)->post(route('portal.office.sites.users.store', [$customer, $siteA]), ['user_uuid' => $user->uuid])->assertRedirect();
    expect($user->canAccessSite($siteA))->toBeTrue()->and($user->canAccessSite($siteB))->toBeFalse();
    $this->post(route('portal.office.sites.users.store', [$customer, $siteB]), ['user_uuid' => $user->uuid])->assertRedirect();
    expect($user->fresh()->canAccessSite($siteA))->toBeTrue()->and($user->fresh()->canAccessSite($siteB))->toBeTrue();
    $this->delete(route('portal.office.sites.users.destroy', [$customer, $siteA, $user]))->assertRedirect();
    expect($user->fresh()->canAccessSite($siteA))->toBeFalse()->and($user->fresh()->canAccessSite($siteB))->toBeTrue();
});

it('deactivates without deleting assignments and denies inactive customer or site access', function (): void {
    $office = officeUser();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = externalUser($customer);
    $user->assignedSites()->attach($site);

    $this->actingAs($office)->post(route('portal.office.users.deactivate', $user), ['lock_version' => 1])->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse()->and($user->assignedSites()->whereKey($site->id)->exists())->toBeTrue();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));

    $user->update(['is_active' => true]);
    $site->forceFill(['is_active' => false])->save();
    expect($user->fresh()->canAccessSite($site))->toBeFalse();
    $site->forceFill(['is_active' => true])->save();
    $customer->forceFill(['is_active' => false])->save();
    expect($user->fresh()->hasCompletePortalProfile())->toBeFalse();
});

it('shows reverse customer and site user views and denies every external role', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = externalUser($customer, $role);
    $user->assignedSites()->attach($site);
    $office = officeUser();

    $this->actingAs($office)->get(route('office.workspace.customers.show', $customer))->assertOk()->assertSee($user->name);
    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'users']))->assertOk()->assertSee($user->name)->assertSee('Remove Access');
    $this->actingAs($user)->get(route('office.workspace.users.index'))->assertForbidden();
    $this->post(route('portal.office.sites.users.store', [$customer, $site]), ['user_uuid' => $user->uuid])->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());

function officeUser(): User
{
    return User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
}

function externalUser(CustomerOrganisation $customer, PortalRoleIdentifier $role = PortalRoleIdentifier::SiteManager): User
{
    return User::factory()->role($role)->create(['customer_organisation_id' => $customer->id]);
}
