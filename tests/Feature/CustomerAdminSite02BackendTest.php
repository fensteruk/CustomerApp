<?php

use App\Actions\Administration\DeactivateCustomerAction;
use App\Actions\Administration\RenameCustomerAction;
use App\Enums\AdministrativeAction;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotProduct;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\Services\OfficeAdministrationQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('runs the complete customer and site lifecycle with immutable attributable audit', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);

    $customerResponse = $this->actingAs($office)->postJson(route('portal.office.customers.store'), [
        'name' => '  North   Homes  ',
        'is_active' => false,
        'actor_user_id' => 999,
        'uuid' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('customer.name', 'North Homes')->assertJsonPath('customer.is_active', true);

    $customer = CustomerOrganisation::query()->where('uuid', $customerResponse->json('customer.uuid'))->firstOrFail();

    $this->patchJson(route('portal.office.customers.update', $customer), [
        'name' => 'North Homes Group',
        'lock_version' => 1,
        'is_active' => false,
    ])->assertOk()->assertJsonPath('customer.lock_version', 2);

    $siteResponse = $this->postJson(route('portal.office.customers.sites.store', $customer), [
        'name' => '  Riverside   Phase 1 ',
        'location' => '  York   Road ',
        'customer_organisation_id' => CustomerOrganisation::factory()->create()->id,
        'external_source' => 'attacker',
        'external_identifier' => 'attacker-site',
        'is_active' => false,
    ])->assertCreated()
        ->assertJsonPath('site.name', 'Riverside Phase 1')
        ->assertJsonPath('site.location', 'York Road')
        ->assertJsonPath('site.is_active', true)
        ->assertJsonPath('site.source_reference', null);

    $site = Site::query()->where('uuid', $siteResponse->json('site.uuid'))->firstOrFail();
    expect($site->customer_organisation_id)->toBe($customer->id)
        ->and($site->external_source)->toBeNull()
        ->and($site->external_identifier)->toBeNull();

    $this->patchJson(route('portal.office.sites.update', [$customer, $site]), [
        'name' => 'Riverside',
        'location' => 'Central York',
        'lock_version' => 1,
        'customer_organisation_id' => CustomerOrganisation::factory()->create()->id,
        'external_source' => 'attacker',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('site.lock_version', 2);

    $this->postJson(route('portal.office.sites.deactivate', [$customer, $site]), [
        'reason' => 'Site handover paused.',
        'lock_version' => 2,
    ])->assertOk()->assertJsonPath('site.is_active', false);
    $this->postJson(route('portal.office.sites.reactivate', [$customer, $site]), [
        'reason' => 'Site handover resumed.',
        'lock_version' => 3,
    ])->assertOk()->assertJsonPath('site.is_active', true);

    $this->postJson(route('portal.office.customers.deactivate', $customer), [
        'reason' => 'Customer account paused.',
        'lock_version' => 2,
    ])->assertOk()->assertJsonPath('customer.is_active', false);
    $this->postJson(route('portal.office.customers.reactivate', $customer), [
        'reason' => 'Customer account restored.',
        'lock_version' => 3,
    ])->assertOk()->assertJsonPath('customer.is_active', true);

    expect(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->pluck('action')->all())
        ->toEqual([
            AdministrativeAction::Created,
            AdministrativeAction::Renamed,
            AdministrativeAction::Deactivated,
            AdministrativeAction::Reactivated,
        ])
        ->and(AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->pluck('action')->all())
        ->toEqual([
            AdministrativeAction::Created,
            AdministrativeAction::Updated,
            AdministrativeAction::Deactivated,
            AdministrativeAction::Reactivated,
        ]);

    $audit = AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->where('action', AdministrativeAction::Deactivated)->firstOrFail();
    expect($audit->actor_user_id)->toBe($office->id)
        ->and($audit->actor_name)->toBe($office->name)
        ->and($audit->reason)->toBe('Site handover paused.')
        ->and($audit->before_state['is_active'])->toBeTrue()
        ->and($audit->after_state['is_active'])->toBeFalse();

    expect(fn () => DB::table('administrative_audits')->where('id', $audit->id)->update(['reason' => 'changed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('administrative_audits')->where('id', $audit->id)->delete())
        ->toThrow(QueryException::class);
});

it('requires lifecycle reasons and rejects stale optimistic versions', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);

    $reasonResponse = $this->actingAs($office)->postJson(route('portal.office.customers.deactivate', $customer), [
        'reason' => '   ',
        'lock_version' => 1,
    ]);
    $reasonResponse->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('portal.office.sites.deactivate', [$customer, $site]), [
        'lock_version' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->patchJson(route('portal.office.customers.update', $customer), [
        'name' => 'First update',
        'lock_version' => 1,
    ])->assertOk();
    $this->patchJson(route('portal.office.customers.update', $customer), [
        'name' => 'Stale update',
        'lock_version' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('lock_version');

    expect($customer->fresh()->name)->toBe('First update')
        ->and(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->count())->toBe(1);
});

it('enforces normalized customer and tenant-scoped site uniqueness including inactive rows', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customerA = CustomerOrganisation::factory()->create(['name' => 'Alpha Homes']);
    $customerB = CustomerOrganisation::factory()->create(['name' => 'Beta Homes']);
    $site = Site::factory()->create(['customer_organisation_id' => $customerA->id, 'name' => 'Oak View']);

    $this->actingAs($office)->postJson(route('portal.office.customers.store'), ['name' => ' alpha   homes '])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    DB::table('customer_organisations')->where('id', $customerA->id)->update(['is_active' => false]);
    $this->postJson(route('portal.office.customers.store'), ['name' => 'ALPHA HOMES'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->postJson(route('portal.office.customers.sites.store', $customerB), ['name' => ' oak   view ', 'location' => null])
        ->assertCreated();
    $this->postJson(route('portal.office.customers.sites.store', $customerA), ['name' => 'Other', 'location' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('customer');

    DB::table('customer_organisations')->where('id', $customerA->id)->update(['is_active' => true]);
    DB::table('sites')->where('id', $site->id)->update(['is_active' => false]);
    $this->postJson(route('portal.office.customers.sites.store', $customerA), ['name' => 'OAK VIEW', 'location' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('allows only current active persisted Office authority and fails closed for stale roles', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $nullOrganisationOffice = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff, null);
    $customer = CustomerOrganisation::factory()->create();

    foreach ([$office, $nullOrganisationOffice] as $allowed) {
        $this->actingAs($allowed)->getJson(route('portal.office.customers.index'))->assertOk();
    }

    foreach (PortalRoleIdentifier::siteRoles() as $role) {
        $external = adminSite02User($role, $customer);
        $this->actingAs($external)->getJson(route('portal.office.customers.index'))->assertForbidden();
        $this->postJson(route('portal.office.customers.store'), ['name' => 'Denied '.$role->value])->assertForbidden();
    }

    $preview = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff, null, ['is_preview_user' => true]);
    $this->actingAs($preview)->getJson(route('portal.office.customers.index'))->assertForbidden();

    $inactive = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff, null, ['is_active' => false]);
    $this->actingAs($inactive)->getJson(route('portal.office.customers.index'))->assertRedirect(route('login'));

    $missingRole = User::factory()->create(['customer_organisation_id' => null, 'portal_role_id' => null]);
    $this->actingAs($missingRole)->getJson(route('portal.office.customers.index'))->assertRedirect(route('login'));

    $siteRoleId = PortalRole::query()->where('identifier', PortalRoleIdentifier::SiteManager->value)->value('id');
    $stale = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $stale->load('portalRole');
    DB::table('users')->where('id', $stale->id)->update(['portal_role_id' => $siteRoleId]);
    expect(fn () => app(OfficeAdministrationPolicy::class)->authorize($stale, 'view'))
        ->toThrow(AuthorizationException::class);

    $mismatched = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $mismatched->setRelation('portalRole', PortalRole::query()->findOrFail($siteRoleId));
    expect(fn () => app(OfficeAdministrationPolicy::class)->authorize($mismatched, 'view'))
        ->toThrow(AuthorizationException::class);
});

it('removes inactive customer and site scope from every external role without deleting relationships', function (PortalRoleIdentifier $role): void {
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $user = adminSite02User($role, $customer);
    $user->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);

    expect($user->hasCompletePortalProfile())->toBeTrue()->and($user->canAccessSite($site))->toBeTrue();

    DB::table('customer_organisations')->where('id', $customer->id)->update(['is_active' => false]);
    expect($user->hasCompletePortalProfile())->toBeFalse()->and($user->canAccessSite($site))->toBeFalse();
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertRedirect(route('login'));

    DB::table('customer_organisations')->where('id', $customer->id)->update(['is_active' => true]);
    DB::table('sites')->where('id', $site->id)->update(['is_active' => false]);
    expect($user->hasCompletePortalProfile())->toBeTrue()->and($user->canAccessSite($site))->toBeFalse();
    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.plots.show', $plot))->assertRedirect(route('sites.select'));

    expect(DB::table('site_user_assignments')->where('site_id', $site->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(ProjectedPlot::query()->whereKey($plot->id)->exists())->toBeTrue();
})->with(PortalRoleIdentifier::siteRoles());

it('blocks external IDOR and enforces parent-child scope for Office routes', function (): void {
    $customerA = CustomerOrganisation::factory()->create();
    $customerB = CustomerOrganisation::factory()->create();
    $siteA = Site::factory()->create(['customer_organisation_id' => $customerA->id]);
    $plotA = ProjectedPlot::factory()->create(['site_id' => $siteA->id]);
    $external = adminSite02User(PortalRoleIdentifier::SiteManager, $customerA);
    $external->assignedSites()->attach($siteA);

    foreach ([
        route('portal.office.customers.show', $customerA),
        route('portal.office.sites.show', [$customerA, $siteA]),
        route('portal.office.sites.plots', [$customerA, $siteA]),
        route('portal.office.sites.users', [$customerA, $siteA]),
        route('portal.office.sites.source-binding', [$customerA, $siteA]),
        route('portal.office.sites.imports', [$customerA, $siteA]),
        route('portal.office.sites.audit', [$customerA, $siteA]),
    ] as $url) {
        $this->actingAs($external)->getJson($url)->assertForbidden();
    }

    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $this->actingAs($office)->getJson(route('portal.office.sites.show', [$customerB, $siteA]))->assertNotFound();
    $this->getJson(route('portal.office.sites.plots', [$customerB, $siteA]))->assertNotFound();

    expect(Route::has('portal.office.plots.store'))->toBeFalse()
        ->and(Route::has('portal.office.plots.update'))->toBeFalse()
        ->and(Route::has('portal.office.plots.destroy'))->toBeFalse()
        ->and(ProjectedPlot::query()->whereKey($plotA->id)->exists())->toBeTrue();
});

it('rolls back the business mutation when its required audit insert fails', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Atomic Customer']);
    $enabled = true;
    DB::connection()->beforeExecuting(function (string $query) use (&$enabled): void {
        if ($enabled && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'administrative_audits')) {
            throw new RuntimeException('test_admin_audit_failure');
        }
    });

    try {
        expect(fn () => app(DeactivateCustomerAction::class)->handle($office, $customer, 'Required reason.', 1))
            ->toThrow(RuntimeException::class, 'test_admin_audit_failure');
    } finally {
        $enabled = false;
    }

    expect($customer->fresh()->is_active)->toBeTrue()
        ->and($customer->fresh()->lock_version)->toBe(1)
        ->and(AdministrativeAudit::query()->count())->toBe(0);
});

it('does not translate unrelated database failures into duplicate-name validation', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customer = CustomerOrganisation::factory()->create();
    $enabled = true;
    DB::connection()->beforeExecuting(function (string $query) use (&$enabled): void {
        if ($enabled && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'administrative_audits')) {
            throw new QueryException('sqlite', $query, [], new RuntimeException('audit unavailable'));
        }
    });

    try {
        expect(fn () => app(RenameCustomerAction::class)->handle($office, $customer, 'Renamed', 1))
            ->toThrow(QueryException::class);
    } finally {
        $enabled = false;
    }

    expect($customer->fresh()->name)->not->toBe('Renamed');
});

it('provides bounded safe customer site plot user binding and import read models without N plus one queries', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Read Model Customer']);
    $site = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'Read Model Site',
        'external_source' => 'legacy-source',
        'external_identifier' => 'LEGACY-001',
    ]);
    $assigned = adminSite02User(PortalRoleIdentifier::AssistantSiteManager, $customer);
    $assigned->assignedSites()->attach($site);
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => 'Plot 100']);
    ProjectedPlotService::query()->create([
        'projected_plot_id' => $plot->id,
        'service_identifier' => CallOffServiceType::Windows,
        'source_completed_at' => now()->toDateString(),
        'source_present' => true,
    ]);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'VS', 'quantity' => '2.500']);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'CDG', 'quantity' => '1.000']);
    ProjectedPlotProduct::query()->create(['projected_plot_id' => $plot->id, 'product_code' => 'BF', 'quantity' => '3.000']);

    adminSite02Binding($office, $customer, $site, 'Draft source', null);
    adminSite02Binding($office, $customer, $site, 'Active source', 1);

    $this->actingAs($office)->getJson(route('portal.office.customers.index', ['per_page' => 1]))
        ->assertOk()->assertJsonPath('per_page', 1);
    $this->getJson(route('portal.office.customers.sites.index', [$customer, 'per_page' => 1]))
        ->assertOk()->assertJsonPath('per_page', 1)
        ->assertJsonPath('data.0.source_reference.identifier', 'LEGACY-001')
        ->assertJsonPath('data.0.source_binding_state', 'ACTIVE');
    $this->getJson(route('portal.office.sites.plots', [$customer, $site, 'per_page' => 1]))
        ->assertOk()
        ->assertJsonPath('data.0.plot_reference', 'Plot 100')
        ->assertJsonPath('data.0.overall_status.value', 'partially_completed')
        ->assertJsonPath('data.0.product_totals.windows', '2.500')
        ->assertJsonPath('data.0.product_totals.doors', '4.000')
        ->assertJsonPath('data.0.product_totals.bifold', '3.000');
    $this->getJson(route('portal.office.sites.users', [$customer, $site]))
        ->assertOk()->assertJsonMissingPath('data.0.id')->assertJsonMissingPath('data.0.customer_organisation_id')
        ->assertJsonPath('data.0.email', $assigned->email);
    $this->getJson(route('portal.office.sites.source-binding', [$customer, $site, 'per_page' => 1]))
        ->assertOk()->assertJsonPath('state', 'ACTIVE')->assertJsonPath('bindings.per_page', 1)
        ->assertJsonMissing(['definition_hash', 'identity_hash', 'epoch']);
    $this->getJson(route('portal.office.sites.imports', [$customer, $site, 'per_page' => 1]))
        ->assertOk()->assertJsonPath('availability', 'AVAILABLE')->assertJsonPath('runs.per_page', 1)
        ->assertJsonMissing(['storage_key', 'original_name', 'workbook_hash', 'failure_code']);

    Site::factory()->count(3)->create(['customer_organisation_id' => $customer->id]);
    ProjectedPlot::factory()->count(3)->create(['site_id' => $site->id]);

    $queries = app(OfficeAdministrationQueryService::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $queries->sites($office, null, null, null, 1);
    $oneSiteQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $queries->sites($office, null, null, null, 100);
    $manySiteQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $queries->plots($office, $customer, $site, null, 1);
    $onePlotQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $queries->plots($office, $customer, $site, null, 100);
    $manyPlotQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($manySiteQueries)->toBe($oneSiteQueries)->and($manyPlotQueries)->toBe($onePlotQueries);
});

it('backfills legacy customer and site lifecycle columns and preserves ownership on upgrade', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('MySQL upgrade is covered by the disposable MySQL gate.');
    }

    $migration = require database_path('migrations/2026_09_09_000014_add_customer_site_administration.php');
    $migration->down();
    $customerId = DB::table('customer_organisations')->insertGetId([
        'name' => 'Legacy Customer', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $siteId = DB::table('sites')->insertGetId([
        'customer_organisation_id' => $customerId,
        'name' => 'Legacy Site',
        'location' => null,
        'external_source' => null,
        'external_identifier' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $customer = DB::table('customer_organisations')->where('id', $customerId)->first();
    $site = DB::table('sites')->where('id', $siteId)->first();
    expect($customer->uuid)->not->toBeNull()->and((int) $customer->is_active)->toBe(1)->and((int) $customer->lock_version)->toBe(1)
        ->and($site->uuid)->not->toBeNull()->and((int) $site->is_active)->toBe(1)->and((int) $site->lock_version)->toBe(1)
        ->and((int) $site->customer_organisation_id)->toBe($customerId);
});

it('refuses a rollback that would discard lifecycle or audit truth', function (): void {
    $office = adminSite02User(PortalRoleIdentifier::FensterOfficeStaff);
    $customer = CustomerOrganisation::factory()->create();
    app(DeactivateCustomerAction::class)->handle($office, $customer, 'Preserve this history.', 1);
    $migration = require database_path('migrations/2026_09_09_000014_add_customer_site_administration.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Refusing rollback');
    expect(CustomerOrganisation::query()->whereKey($customer->id)->exists())->toBeTrue()
        ->and(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->exists())->toBeTrue();
});

function adminSite02User(
    PortalRoleIdentifier $role,
    ?CustomerOrganisation $customer = null,
    array $attributes = [],
): User {
    return User::factory()->role($role)->create(array_merge([
        'customer_organisation_id' => $role === PortalRoleIdentifier::FensterOfficeStaff ? $customer?->id : ($customer ?? CustomerOrganisation::factory()->create())->id,
    ], $attributes));
}

function adminSite02Binding(
    User $actor,
    CustomerOrganisation $customer,
    Site $site,
    string $identity,
    ?int $activeVersion,
): void {
    $bindingId = DB::table('wald_source_bindings')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'identity_hash' => hash('sha256', $identity),
        'source_namespace' => 'siteapp',
        'identity_kind' => 'EXACT_SITE_NAME',
        'source_identity' => $identity,
        'latest_version' => 1,
        'active_version' => null,
        'revoked_through' => 0,
        'epoch' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('wald_binding_versions')->insert([
        'uuid' => (string) Str::uuid(),
        'binding_id' => $bindingId,
        'version' => 1,
        'customer_organisation_id' => $customer->id,
        'site_id' => $site->id,
        'actor_id' => $actor->id,
        'actor_name' => $actor->name,
        'reason' => 'Approved test binding.',
        'definition_hash' => hash('sha256', $identity.'definition'),
        'created_at' => now(),
    ]);

    if ($activeVersion !== null) {
        DB::table('wald_source_bindings')->where('id', $bindingId)->update([
            'active_version' => $activeVersion,
            'epoch' => 1,
            'updated_at' => now(),
        ]);
    }
}
