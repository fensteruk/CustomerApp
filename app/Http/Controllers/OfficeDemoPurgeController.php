<?php

namespace App\Http\Controllers;

use App\Actions\Administration\PurgeDemoCustomerOrSiteAction;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Policies\OfficeAdministrationPolicy;
use App\Services\DemoPurgeImpact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OfficeDemoPurgeController extends Controller
{
    public function customerPreview(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationPolicy $policy, DemoPurgeImpact $impacts): View
    {
        $policy->authorize($request->user(), 'demo_purge');

        return view('office.demo-purge', [
            'kind' => 'customer', 'impact' => $impacts->customer($customerOrganisation),
            'action' => route('office.workspace.customers.demo-purge', $customerOrganisation),
            'cancel' => route('office.workspace.customers.show', $customerOrganisation),
        ]);
    }

    public function sitePreview(Request $request, CustomerOrganisation $customerOrganisation, Site $site, OfficeAdministrationPolicy $policy, DemoPurgeImpact $impacts): View
    {
        $policy->authorize($request->user(), 'demo_purge');
        abort_unless($site->customer_organisation_id === $customerOrganisation->getKey(), 404);

        return view('office.demo-purge', [
            'kind' => 'site', 'impact' => $impacts->site($site),
            'action' => route('office.workspace.sites.demo-purge', [$customerOrganisation, $site]),
            'cancel' => route('office.workspace.sites.show', [$customerOrganisation, $site]),
        ]);
    }

    public function customerPurge(Request $request, CustomerOrganisation $customerOrganisation, OfficeAdministrationPolicy $policy, PurgeDemoCustomerOrSiteAction $action): RedirectResponse
    {
        $policy->authorize($request->user(), 'demo_purge');
        $data = $this->validateCertification($request);
        $action->customer($request->user(), $customerOrganisation, $data['fingerprint']);

        return redirect()->route('office.workspace.customers.index')->with('status', 'Demo customer and its eligible site data permanently purged.');
    }

    public function sitePurge(Request $request, CustomerOrganisation $customerOrganisation, Site $site, OfficeAdministrationPolicy $policy, PurgeDemoCustomerOrSiteAction $action): RedirectResponse
    {
        $policy->authorize($request->user(), 'demo_purge');
        abort_unless($site->customer_organisation_id === $customerOrganisation->getKey(), 404);
        $data = $this->validateCertification($request);
        $action->site($request->user(), $customerOrganisation, $site, $data['fingerprint']);

        return redirect()->route('office.workspace.customers.show', $customerOrganisation)->with('status', 'Demo site and its eligible import data permanently purged.');
    }

    private function validateCertification(Request $request): array
    {
        return $request->validate([
            'certified_demo' => ['accepted'],
            'confirmation' => ['required', 'in:PURGE'],
            'fingerprint' => ['required', 'string', 'size:64'],
        ]);
    }
}
