<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create());
    View::share('errors', new ViewErrorBag);
    // Isolated rendering contracts only. These names are supplied by ADMIN-SITE02A at integration.
    foreach ([
        'customers.store' => '/customers',
        'customers.update' => '/customers/{customerOrganisation}',
        'customers.deactivate' => '/customers/{customerOrganisation}/deactivate',
        'customers.reactivate' => '/customers/{customerOrganisation}/reactivate',
        'customers.sites.store' => '/customers/{customerOrganisation}/sites',
        'sites.update' => '/customers/{customerOrganisation}/sites/{site}',
        'sites.deactivate' => '/customers/{customerOrganisation}/sites/{site}/deactivate',
        'sites.reactivate' => '/customers/{customerOrganisation}/sites/{site}/reactivate',
    ] as $name => $path) {
        if (! Route::has('portal.office.'.$name)) {
            Route::post('/__render_contract'.$path, fn () => abort(501))->name('portal.office.'.$name);
        }
    }
});

function adminCustomerViewData(): array
{
    return ['uuid' => '00000000-0000-4000-8000-000000000001', 'name' => 'Synthetic Homes',
        'is_active' => true, 'lock_version' => 3, 'site_count' => 40, 'active_site_count' => 38,
        'user_count' => 12, 'created_at' => '2026-09-10T10:00:00Z', 'updated_at' => '2026-09-10T10:00:00Z'];
}

function adminSiteViewData(): array
{
    return ['uuid' => '00000000-0000-4000-8000-000000000002', 'name' => 'Synthetic Meadow',
        'customer' => adminCustomerViewData(), 'location' => null, 'is_active' => true,
        'effective_is_active' => true, 'lock_version' => 2, 'plot_count' => 250, 'assignment_count' => 30,
        'source_reference' => ['source' => 'synthetic', 'identifier' => 'SOURCE-SITE-42'],
        'source_binding_state' => 'NOT_YET_INTEGRATED'];
}

function adminPageItems(array $items = [], ?int $total = null): LengthAwarePaginator
{
    return new LengthAwarePaginator($items, $total ?? count($items), 20, 1, ['path' => '/portal/office/workspace/customers']);
}

it('renders bounded customer cards and escapes hostile names', function (): void {
    $customer = [...adminCustomerViewData(), 'name' => '<script>alert(1)</script>'];
    $html = view('office.customers.index', ['customers' => adminPageItems([$customer], 400),
        'filters' => ['search' => '', 'active' => null]])->render();
    expect($html)->toContain('400 customers', 'Add Customer', 'page=2', '&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>', 'Delete Customer');
});

it('distinguishes no customers from no matching customers', function (string $search, string $heading): void {
    $html = view('office.customers.index', ['customers' => adminPageItems(),
        'filters' => ['search' => $search, 'active' => null]])->render();
    expect($html)->toContain($heading);
})->with([['', 'No customers yet'], ['Missing', 'No matching customers']]);

it('keeps site lists under their customer and explains inactive customer access', function (): void {
    $customer = [...adminCustomerViewData(), 'is_active' => false];
    $html = view('office.customers.show', ['customer' => $customer, 'sites' => adminPageItems([adminSiteViewData()], 40),
        'filters' => ['search' => '', 'active' => null]])->render();
    expect($html)->toContain('External users cannot access', 'Synthetic Meadow', 'No location added', 'Reactivate', 'page=2')
        ->not->toContain('Delete Site');
});

it('renders only editable fields and the stale-write version in metadata forms', function (string $kind): void {
    $record = $kind === 'site' ? adminSiteViewData() : adminCustomerViewData();
    $html = view('office.form', [
        'kind' => $kind, 'record' => $record, 'customer' => $kind === 'site' ? adminCustomerViewData() : null,
        'intent' => 'save', 'title' => 'Edit '.ucfirst($kind), 'action' => '/safe-json-endpoint',
        'method' => 'PATCH', 'cancel' => '/back', 'redirect' => '/record/__UUID__',
    ])->render();
    expect($html)->toContain('name="name"', 'name="lock_version"', 'name="_token"', 'name="_method"', 'aria-describedby="admin-name-error"')
        ->not->toContain('name="customer_organisation_id"', 'name="external_source"', 'name="is_active"', 'name="actor_id"', 'name="source_binding_id"');
})->with(['customer', 'site']);

it('requires an explained confirmation for both lifecycle changes', function (string $intent): void {
    $html = view('office.form', [
        'kind' => 'customer', 'record' => adminCustomerViewData(), 'customer' => null,
        'intent' => $intent, 'title' => ucfirst($intent).' Customer', 'action' => '/safe-json-endpoint',
        'method' => 'POST', 'cancel' => '/back', 'redirect' => '/record/__UUID__',
    ])->render();
    expect($html)->toContain('Confirm '.ucfirst($intent), 'name="reason"', 'required maxlength="2000"', 'Cancel', 'activity history')
        ->not->toContain('name="name"', 'Delete');
})->with(['deactivate', 'reactivate']);

it('does not require a source reference when adding a site', function (): void {
    $html = view('office.form', [
        'kind' => 'site', 'record' => null, 'customer' => adminCustomerViewData(),
        'intent' => 'save', 'title' => 'Add Site', 'action' => '/safe-json-endpoint',
        'method' => 'POST', 'cancel' => '/back', 'redirect' => '/record/__UUID__',
    ])->render();
    expect($html)->toContain('source reference is not required', 'name="location"', 'Create Site')
        ->not->toContain('name="external_identifier"', 'name="lock_version"');
});

it('renders every empty site section safely', function (string $section, string $expected): void {
    $items = match ($section) {
        'overview', 'source' => ['availability' => 'NOT_YET_INTEGRATED', 'active_bindings' => [], 'bindings' => null],
        'imports' => ['availability' => 'NOT_YET_INTEGRATED', 'runs' => []],
        default => adminPageItems(),
    };
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => $section, 'items' => $items, 'search' => ''])->render();
    expect($html)->toContain($expected, 'Import Source Data', 'aria-label="Site sections"')
        ->not->toContain('Add Plot', 'Edit Plot', 'Delete Plot');
})->with([
    ['overview', 'Not available yet'], ['plots', 'No plots yet'], ['users', 'No assigned users'],
    ['source', 'Source binding information is not available yet'], ['imports', 'Import history is not available yet'], ['audit', 'No administration activity'],
]);

it('renders assigned user labels and escapes email and name', function (): void {
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => 'users', 'search' => '',
        'items' => adminPageItems([['name' => '<img src=x onerror=alert(1)>', 'email' => '<script>@example.test',
            'role_label' => 'Finishing Foreman', 'is_active' => false]])])->render();
    expect($html)->toContain('Finishing Foreman', 'Inactive', '&lt;img', '&lt;script&gt;')
        ->not->toContain('<img src=x onerror', 'Edit User', 'Remove User');
});

it('renders paginated source plots without turning source data into editing controls', function (): void {
    $reference = str_repeat('LONG-PLOT-', 28).'<script>';
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => 'plots', 'search' => 'LONG',
        'items' => adminPageItems([[
            'plot_reference' => $reference, 'source_identity' => ['identifier' => 'SOURCE-42'],
            'overall_status' => ['value' => 'available', 'label' => 'Available'],
            'product_totals' => ['windows' => '12', 'doors' => '2', 'bifold' => '1'],
            'is_completed' => false, 'synchronised_at' => '2026-09-10T11:00:00+01:00',
            'services' => [['service' => 'windows', 'source_present' => true,
                'portal_status' => ['value' => 'outstanding', 'label' => 'Outstanding', 'date' => null],
                'source_completed_at' => null, 'source_completion_observed_at' => null]],
        ]], 500)])->render();
    expect($html)->toContain('500 plots', 'page=2', 'name="section" value="plots"', 'value="LONG"',
        'SOURCE-42', 'Available', 'Windows 12', 'Doors 2', 'Bifold 1', 'Outstanding',
        '10 Sep 2026, 10:00 UTC', '&lt;script&gt;', 'Read-only source information')
        ->not->toContain($reference, 'Add Plot', 'Edit Plot', 'Delete Plot');
});

it('distinguishes unavailable source integrations from an empty integrated history', function (string $section, string $heading): void {
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => $section, 'search' => '',
        'items' => ['availability' => 'NOT_YET_INTEGRATED', 'active_bindings' => [], 'runs' => []]])->render();
    expect($html)->toContain($heading)->not->toContain('No imports recorded', '>Not linked<');
})->with([
    ['source', 'Source binding information is not available yet'],
    ['imports', 'Import history is not available yet'],
]);

it('shows commitment only from a supplied receipt with a correctly converted timestamp', function (): void {
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => 'imports', 'search' => '',
        'items' => ['availability' => 'AVAILABLE', 'runs' => adminPageItems([[
            'export_date' => '2026-09-10', 'export_slot' => 'AFTERNOON', 'state' => 'COMMITTED',
            'uploader_name' => 'Synthetic Office', 'receipt' => ['committed_at' => '2026-09-10T16:00:00+01:00'],
        ]])]])->render();
    expect($html)->toContain('Committed', 'Afternoon', '10 Sep 2026, 15:00 UTC')
        ->not->toContain('Not committed', '>Commit<');
});

it('displays source binding summaries without raw private metadata', function (): void {
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => 'source', 'search' => '',
        'items' => ['availability' => 'AVAILABLE', 'active_bindings' => [], 'bindings' => adminPageItems([[
            'state' => 'ACTIVE', 'source_identity' => 'SOURCE-42', 'identity_kind' => 'SOURCE_SITE_ID',
            'source_namespace' => 'synthetic', 'version' => 2, 'reason' => 'Approved mapping',
            'created_by' => 'Synthetic Office', 'last_changed_at' => '2026-09-10T10:00:00Z',
            'epoch' => 29, 'hash' => 'PRIVATE-HASH', 'private_evidence' => 'PRIVATE-WORKBOOK',
        ]])]])->render();
    expect($html)->toContain('SOURCE-42', 'Active', 'Synthetic Office', 'Version', 'Approved mapping')
        ->not->toContain('PRIVATE-HASH', 'PRIVATE-WORKBOOK', 'Revoke', 'Activate Binding');
});

it('does not claim a reviewed import is committed or expose failure internals', function (): void {
    $html = view('office.sites.show', ['site' => adminSiteViewData(), 'section' => 'imports', 'search' => '',
        'items' => ['availability' => 'AVAILABLE', 'runs' => adminPageItems([[
            'export_date' => '2026-09-10', 'export_slot' => 'MORNING', 'state' => 'READY_TO_COMMIT',
            'uploader_name' => 'Synthetic Office', 'receipt' => null, 'failure_code' => 'PRIVATE-DEBUG',
        ]])]])->render();
    expect($html)->toContain('Review approved', 'Not committed', 'Morning')
        ->not->toContain('PRIVATE-DEBUG', '>Commit<');
});

it('renders only allowlisted audit values with truthful role attribution', function (): void {
    $html = view('office.customers.audit', ['customer' => adminCustomerViewData(), 'items' => adminPageItems([[
        'action' => 'renamed', 'actor_name' => 'Synthetic Office', 'actor_role' => 'fenster_office_staff',
        'before' => ['name' => 'Earlier name', 'private_field' => 'SECRET-BEFORE'],
        'after' => ['name' => 'New name', 'private_field' => 'SECRET-AFTER'],
        'reason' => 'Naming correction', 'occurred_at' => '2026-09-10T10:00:00Z',
    ]])])->render();
    expect($html)->toContain('Earlier name', 'New name', 'Fenster Office Staff', 'Naming correction')
        ->not->toContain('SECRET-BEFORE', 'SECRET-AFTER');
});

it('makes the future import entry honest and has no upload or commit control', function (): void {
    $html = view('office.imports', ['site' => adminSiteViewData()])->render();
    expect($html)->toContain('Not available yet', 'Import Studio is being prepared', 'Synthetic Meadow')
        ->not->toContain('type="file"', 'multipart/form-data', '>Commit<', 'New Import');
});

it('binds the administration workspace to the real secured lifecycle endpoints', function (): void {
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create([
        'customer_organisation_id' => null,
    ]);

    $customerResponse = $this->actingAs($office)->postJson(route('portal.office.customers.store'), [
        'name' => 'Integrated Customer',
    ])->assertCreated();
    $customer = CustomerOrganisation::query()->where('uuid', $customerResponse->json('customer.uuid'))->firstOrFail();

    $this->get(route('office.workspace.customers.show', $customer))
        ->assertOk()
        ->assertSee('Integrated Customer')
        ->assertSee('Add Site');

    $siteResponse = $this->postJson(route('portal.office.customers.sites.store', $customer), [
        'name' => 'Integrated Site',
        'location' => 'York',
    ])->assertCreated();
    $site = Site::query()->where('uuid', $siteResponse->json('site.uuid'))->firstOrFail();

    $this->get(route('office.workspace.sites.show', [$customer, $site]))
        ->assertOk()
        ->assertSee('Integrated Site')
        ->assertSee('Not available yet')
        ->assertSee('External access');

    $this->postJson(route('portal.office.sites.deactivate', [$customer, $site]), [
        'reason' => 'Integration lifecycle check.',
        'lock_version' => 1,
    ])->assertOk();

    $this->get(route('office.workspace.sites.show', [$customer, $site, 'section' => 'audit']))
        ->assertOk()
        ->assertSee('External access is blocked')
        ->assertSee('Integration lifecycle check.');
});

it('denies every external role direct workspace entry', function (PortalRoleIdentifier $role): void {
    $user = User::factory()->role($role)->create();
    $this->actingAs($user)->get('/portal/office/workspace/customers')->assertForbidden();
    $this->get('/portal/office/workspace/imports')->assertForbidden();
})->with(PortalRoleIdentifier::siteRoles());
