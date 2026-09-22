<?php

namespace App\Http\Controllers;

use App\Actions\Administration\PermanentlyDeleteCustomerOrSiteAction;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Services\PermanentDeletionImpact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class OfficePermanentDeletionController extends Controller
{
    public function customerPreview(CustomerOrganisation $customerOrganisation, PermanentDeletionImpact $impacts): View
    {
        Gate::authorize('delete', $customerOrganisation);

        return view('office.permanent-delete', [
            'kind' => 'customer', 'impact' => $impacts->customer($customerOrganisation),
            'action' => route('office.workspace.customers.delete', $customerOrganisation),
            'cancel' => route('office.workspace.customers.show', $customerOrganisation),
        ]);
    }

    public function sitePreview(CustomerOrganisation $customerOrganisation, Site $site, PermanentDeletionImpact $impacts): View
    {
        Gate::authorize('delete', $site);
        abort_unless($site->customer_organisation_id === $customerOrganisation->getKey(), 404);

        return view('office.permanent-delete', [
            'kind' => 'site', 'impact' => $impacts->site($site),
            'action' => route('office.workspace.sites.delete', [$customerOrganisation, $site]),
            'cancel' => route('office.workspace.sites.show', [$customerOrganisation, $site]),
        ]);
    }

    public function customerDelete(Request $request, CustomerOrganisation $customerOrganisation, PermanentlyDeleteCustomerOrSiteAction $action): RedirectResponse
    {
        Gate::authorize('delete', $customerOrganisation);
        $data = $this->validateConfirmation($request);
        $action->customer($request->user(), $customerOrganisation, $data['fingerprint']);

        return redirect()->route('office.workspace.customers.index')->with('status', 'Customer permanently deleted.');
    }

    public function siteDelete(Request $request, CustomerOrganisation $customerOrganisation, Site $site, PermanentlyDeleteCustomerOrSiteAction $action): RedirectResponse
    {
        Gate::authorize('delete', $site);
        abort_unless($site->customer_organisation_id === $customerOrganisation->getKey(), 404);
        $data = $this->validateConfirmation($request);
        $action->site($request->user(), $customerOrganisation, $site, $data['fingerprint']);

        return redirect()->route('office.workspace.customers.show', $customerOrganisation)->with('status', 'Site permanently deleted.');
    }

    private function validateConfirmation(Request $request): array
    {
        return $request->validate([
            'understood' => ['accepted'],
            'confirmation' => ['required', 'in:DELETE'],
            'fingerprint' => ['required', 'string', 'size:64'],
        ]);
    }
}
