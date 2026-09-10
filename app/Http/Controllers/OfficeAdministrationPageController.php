<?php

namespace App\Http\Controllers;

use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Services\OfficeAdministrationQueryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/** Read-only page adapters; ADMIN-SITE02A owns every query and mutation. */
class OfficeAdministrationPageController extends Controller
{
    public function customers(Request $request, OfficeAdministrationQueryService $queries): View
    {
        $filters = $this->filters($request);

        return view('office.customers.index', [
            'customers' => $queries->customers($request->user(), $filters['search'], $filters['active'])->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function customer(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationQueryService $queries): View
    {
        $filters = $this->filters($request);

        return view('office.customers.show', [
            'customer' => $queries->customer($request->user(), $customerOrganisation->uuid),
            'sites' => $queries->sites($request->user(), $customerOrganisation, $filters['search'], $filters['active'])->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function customerForm(Request $request, OfficeAdministrationQueryService $queries, ?CustomerOrganisation $customerOrganisation = null): View
    {
        $customer = $customerOrganisation ? $queries->customer($request->user(), $customerOrganisation->uuid) : null;

        return view('office.form', [
            'kind' => 'customer', 'record' => $customer, 'customer' => null,
            'intent' => 'save', 'title' => $customer ? 'Edit Customer' : 'Add Customer',
            'action' => $customer ? route('portal.office.customers.update', $customer['uuid']) : route('portal.office.customers.store'),
            'method' => $customer ? 'PATCH' : 'POST',
            'cancel' => $customer ? route('office.workspace.customers.show', $customer['uuid']) : route('office.workspace.customers.index'),
            'redirect' => route('office.workspace.customers.show', '__UUID__'),
        ]);
    }

    public function customerLifecycle(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationQueryService $queries): View
    {
        $customer = $queries->customer($request->user(), $customerOrganisation->uuid);
        $intent = $customer['is_active'] ? 'deactivate' : 'reactivate';

        return view('office.form', [
            'kind' => 'customer', 'record' => $customer, 'customer' => null,
            'intent' => $intent, 'title' => ucfirst($intent).' Customer',
            'action' => route('portal.office.customers.'.$intent, $customer['uuid']), 'method' => 'POST',
            'cancel' => route('office.workspace.customers.show', $customer['uuid']),
            'redirect' => route('office.workspace.customers.show', '__UUID__'),
        ]);
    }

    public function site(Request $request, CustomerOrganisation $customerOrganisation, Site $site, OfficeAdministrationQueryService $queries): View
    {
        $input = $request->validate([
            'section' => ['nullable', 'in:overview,plots,users,source,imports,audit'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $section = $input['section'] ?? 'overview';
        $record = $queries->site($request->user(), $customerOrganisation, $site->uuid);
        $items = match ($section) {
            'plots' => $queries->plots($request->user(), $customerOrganisation, $site, $input['search'] ?? null)->withQueryString(),
            'users' => $queries->assignedUsers($request->user(), $customerOrganisation, $site)->withQueryString(),
            'source' => $queries->sourceBindings($request->user(), $customerOrganisation, $site),
            'imports' => $queries->importHistory($request->user(), $customerOrganisation, $site),
            'audit' => $queries->audits($request->user(), AdministrativeEntityType::Site->value, $site->uuid)->withQueryString(),
            default => $queries->sourceBindings($request->user(), $customerOrganisation, $site),
        };
        if ($section === 'imports' && ($items['runs'] ?? null) instanceof LengthAwarePaginator) {
            $items['runs']->withQueryString();
        }

        return view('office.sites.show', ['site' => $record, 'section' => $section, 'items' => $items, 'search' => $input['search'] ?? '']);
    }

    public function siteForm(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationQueryService $queries, ?Site $site = null): View
    {
        $customer = $queries->customer($request->user(), $customerOrganisation->uuid);
        $record = $site ? $queries->site($request->user(), $customerOrganisation, $site->uuid) : null;

        return view('office.form', [
            'kind' => 'site', 'record' => $record, 'customer' => $customer,
            'intent' => 'save', 'title' => $record ? 'Edit Site' : 'Add Site',
            'action' => $record
                ? route('portal.office.sites.update', [$customer['uuid'], $record['uuid']])
                : route('portal.office.customers.sites.store', $customer['uuid']),
            'method' => $record ? 'PATCH' : 'POST',
            'cancel' => $record
                ? route('office.workspace.sites.show', [$customer['uuid'], $record['uuid']])
                : route('office.workspace.customers.show', $customer['uuid']),
            'redirect' => route('office.workspace.sites.show', [$customer['uuid'], '__UUID__']),
        ]);
    }

    public function siteLifecycle(Request $request, CustomerOrganisation $customerOrganisation, Site $site, OfficeAdministrationQueryService $queries): View
    {
        $record = $queries->site($request->user(), $customerOrganisation, $site->uuid);
        $intent = $record['is_active'] ? 'deactivate' : 'reactivate';

        return view('office.form', [
            'kind' => 'site', 'record' => $record, 'customer' => $record['customer'],
            'intent' => $intent, 'title' => ucfirst($intent).' Site',
            'action' => route('portal.office.sites.'.$intent, [$record['customer']['uuid'], $record['uuid']]), 'method' => 'POST',
            'cancel' => route('office.workspace.sites.show', [$record['customer']['uuid'], $record['uuid']]),
            'redirect' => route('office.workspace.sites.show', [$record['customer']['uuid'], '__UUID__']),
        ]);
    }

    public function customerAudit(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationQueryService $queries): View
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return view('office.customers.audit', [
            'customer' => $queries->customer($request->user(), $customerOrganisation->uuid),
            'items' => $queries->audits($request->user(), AdministrativeEntityType::CustomerOrganisation->value, $customerOrganisation->uuid)->withQueryString(),
        ]);
    }

    public function imports(Request $request, OfficeAdministrationQueryService $queries, ?CustomerOrganisation $customerOrganisation = null, ?Site $site = null): View
    {
        return view('office.imports', [
            'site' => $site ? $queries->site($request->user(), $customerOrganisation, $site->uuid) : null,
        ]);
    }

    private function filters(Request $request): array
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'in:all,1,0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return ['search' => $input['search'] ?? '', 'active' => match ($input['active'] ?? 'all') {
            '1' => true, '0' => false, default => null,
        }];
    }
}
