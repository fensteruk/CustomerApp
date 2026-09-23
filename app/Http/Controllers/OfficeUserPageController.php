<?php

namespace App\Http\Controllers;

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Services\OfficeUserAdministrationQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficeUserPageController extends Controller
{
    public function index(Request $request, OfficeUserAdministrationQueryService $queries): View
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'in:all,active,inactive'],
            'role' => ['nullable', Rule::enum(PortalRoleIdentifier::class)],
            'customer' => ['nullable', 'uuid'],
        ]);
        $active = match ($input['active'] ?? 'all') {
            'active' => true, 'inactive' => false, default => null
        };

        return view('office.users.index', [
            'users' => $queries->users($request->user(), $input['search'] ?? null, $active,
                role: $input['role'] ?? null, customer: $input['customer'] ?? null)->withQueryString(),
            'summary' => $queries->summary($request->user()),
            'customers' => $queries->customers($request->user()),
            'search' => $input['search'] ?? '',
            'active' => $input['active'] ?? 'all',
            'role' => $input['role'] ?? '',
            'customerFilter' => $input['customer'] ?? '',
        ]);
    }

    public function show(Request $request, User $user, OfficeUserAdministrationQueryService $queries): View
    {
        return view('office.users.show', ['target' => $queries->user($request->user(), $user), 'audits' => $queries->audits($request->user(), $user)]);
    }

    public function create(Request $request, OfficeUserAdministrationQueryService $queries): View
    {
        $input = $request->validate(['customer' => ['nullable', 'uuid'], 'site' => ['nullable', 'uuid']]);
        $customer = filled($input['customer'] ?? null) ? CustomerOrganisation::query()->where('uuid', $input['customer'])->firstOrFail() : null;
        $site = filled($input['site'] ?? null) ? Site::query()->where('uuid', $input['site'])->firstOrFail() : null;
        abort_if($site && (! $customer || (int) $site->customer_organisation_id !== (int) $customer->id), 404);

        return $this->form($request, $queries, null, $customer, $site);
    }

    public function edit(Request $request, User $user, OfficeUserAdministrationQueryService $queries): View
    {
        $target = $queries->user($request->user(), $user);

        return $this->form($request, $queries, $target, $target->customerOrganisation, null);
    }

    public function lifecycle(Request $request, User $user, OfficeUserAdministrationQueryService $queries): View
    {
        return view('office.users.lifecycle', ['target' => $queries->user($request->user(), $user)]);
    }

    public function assignToSite(Request $request, CustomerOrganisation $customerOrganisation, Site $site, OfficeUserAdministrationQueryService $queries): View
    {
        abort_unless((int) $site->customer_organisation_id === (int) $customerOrganisation->id, 404);

        return view('office.sites.assign-user', ['customer' => $customerOrganisation, 'site' => $site, 'eligible' => $queries->eligibleForSite($request->user(), $site)]);
    }

    private function form(Request $request, OfficeUserAdministrationQueryService $queries, ?User $target, ?CustomerOrganisation $customer, ?Site $site): View
    {
        return view('office.users.form', [
            'target' => $target,
            'roles' => $queries->roles($request->user()),
            'customers' => $queries->customers($request->user()),
            'sites' => $queries->allSites($request->user()),
            'preselectedCustomer' => $customer,
            'preselectedSite' => $site,
        ]);
    }
}
