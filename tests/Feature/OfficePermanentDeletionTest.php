<?php

use App\Enums\AdministrativeAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\AdministrativeAudit;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Services\PermanentDeletionImpact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('deletes an eligible site and its plots while preserving sibling access and the user account', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $sibling = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $user->assignedSites()->attach([$site->id, $sibling->id]);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'VS', 'quantity' => 2]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();

    $impact = app(PermanentDeletionImpact::class)->site($site);
    $this->actingAs($office)->get(route('office.workspace.sites.delete-preview', [$customer, $site]))
        ->assertOk()->assertSee('Site Assignments')->assertSee('Plot Products');
    $this->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact))
        ->assertRedirect(route('office.workspace.customers.show', $customer));

    expect(Site::query()->whereKey($site->id)->exists())->toBeFalse()
        ->and(ProjectedPlot::query()->whereKey($plot->id)->exists())->toBeFalse()
        ->and(ProjectedPlotService::query()->where('projected_plot_id', $plot->id)->exists())->toBeFalse()
        ->and(ProjectedPlotProduct::query()->where('projected_plot_id', $plot->id)->exists())->toBeFalse()
        ->and(DB::table('site_user_assignments')->where('site_id', $site->id)->exists())->toBeFalse()
        ->and(DB::table('site_user_assignments')->where('site_id', $sibling->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->where('action', AdministrativeAction::PermanentlyDeleted)->exists())->toBeTrue();
    $this->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact))->assertNotFound();
});

it('deletes an eligible deactivated customer and its sites without removing Office accounts', function (): void {
    $customer = CustomerOrganisation::factory()->create(['is_active' => false]);
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $other = CustomerOrganisation::factory()->create();
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $impact = app(PermanentDeletionImpact::class)->customer($customer);

    $this->actingAs($office)->postJson(route('office.workspace.customers.delete', $customer), confirmDelete($impact))
        ->assertRedirect(route('office.workspace.customers.index'));

    expect(CustomerOrganisation::query()->whereKey($customer->id)->exists())->toBeFalse()
        ->and(Site::query()->whereKey($site->id)->exists())->toBeFalse()
        ->and(CustomerOrganisation::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($office->id)->exists())->toBeTrue();
});

it('blocks stale confirmations and customer accounts', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $impact = app(PermanentDeletionImpact::class)->site($site);
    ProjectedPlot::factory()->create(['site_id' => $site->id]);

    $this->actingAs($office)->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact))
        ->assertSessionHasErrors('confirmation');
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();

    User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $customer->id]);
    $this->get(route('office.workspace.customers.delete-preview', $customer))->assertOk()->assertSee('Cannot delete yet')->assertSee('Customer Users');
});

it('blocks immutable Wald bindings and keeps the site as their valid target', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $bindingId = DB::table('wald_source_bindings')->insertGetId([
        'uuid' => (string) Str::uuid(), 'identity_hash' => str_repeat('a', 64),
        'source_namespace' => 'test', 'identity_kind' => 'CUSTOMER_CODE', 'source_identity' => 'TEST-1',
        'latest_version' => 1, 'active_version' => null, 'revoked_through' => 0, 'epoch' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('wald_binding_versions')->insert([
        'uuid' => (string) Str::uuid(), 'binding_id' => $bindingId, 'version' => 1,
        'customer_organisation_id' => $customer->id, 'site_id' => $site->id,
        'actor_id' => $office->id, 'actor_name' => $office->name, 'reason' => 'Test binding',
        'definition_hash' => str_repeat('b', 64), 'created_at' => now(),
    ]);
    $impact = app(PermanentDeletionImpact::class)->site($site);

    $this->actingAs($office)->get(route('office.workspace.sites.delete-preview', [$customer, $site]))
        ->assertOk()->assertSee('Cannot delete yet')->assertSee('Wald Binding Versions');
    $this->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact))
        ->assertSessionHasErrors('confirmation');
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
});

it('blocks sites with customer request history and recommends deactivation', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
    $batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $office->id]);
    CallOffRequest::factory()->create(['call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id]);
    $impact = app(PermanentDeletionImpact::class)->site($site);

    $this->actingAs($office)->get(route('office.workspace.sites.delete-preview', [$customer, $site]))
        ->assertOk()->assertSee('Cannot delete yet')->assertSee('Customer Requests')->assertSee('Deactivate it instead');
    $this->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact))
        ->assertSessionHasErrors('confirmation');
    expect(CallOffRequest::query()->count())->toBe(1);
});

it('rejects a cross-customer site route and an incomplete confirmation', function (): void {
    $customer = CustomerOrganisation::factory()->create();
    $other = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
    $impact = app(PermanentDeletionImpact::class)->site($site);

    $this->actingAs($office)->get(route('office.workspace.sites.delete-preview', [$other, $site]))->assertNotFound();
    $this->post(route('office.workspace.sites.delete', [$other, $site]), confirmDelete($impact))->assertNotFound();
    $this->post(route('office.workspace.sites.delete', [$customer, $site]), ['fingerprint' => $impact['fingerprint']])
        ->assertSessionHasErrors(['understood', 'confirmation']);
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
});

it('denies permanent deletion to external and inactive Office users', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $actor = User::factory()->role($role)->create(['is_active' => $role !== PortalRoleIdentifier::FensterOfficeStaff]);
    $impact = app(PermanentDeletionImpact::class)->site($site);

    $response = $this->actingAs($actor)->get(route('office.workspace.sites.delete-preview', [$customer, $site]));
    $submitted = $this->post(route('office.workspace.sites.delete', [$customer, $site]), confirmDelete($impact));
    if ($role === PortalRoleIdentifier::FensterOfficeStaff) {
        $response->assertRedirect();
        $submitted->assertRedirect();
    } else {
        $response->assertForbidden();
        $submitted->assertForbidden();
    }
    expect(Site::query()->whereKey($site->id)->exists())->toBeTrue();
})->with([...PortalRoleIdentifier::siteRoles(), PortalRoleIdentifier::FensterOfficeStaff]);

function confirmDelete(array $impact): array
{
    return ['understood' => '1', 'confirmation' => 'DELETE', 'fingerprint' => $impact['fingerprint']];
}
