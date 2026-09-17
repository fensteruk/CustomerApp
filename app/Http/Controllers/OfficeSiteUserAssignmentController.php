<?php

namespace App\Http\Controllers;

use App\Actions\Administration\ManagePortalUserAction;
use App\Http\Requests\Office\AssignSiteUserRequest;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OfficeSiteUserAssignmentController extends Controller
{
    public function store(AssignSiteUserRequest $request, CustomerOrganisation $customerOrganisation, Site $site, ManagePortalUserAction $action): RedirectResponse
    {
        $this->contained($customerOrganisation, $site);
        $target = User::query()->where('uuid', $request->validated('user_uuid'))->firstOrFail();
        $action->assign($request->user(), $site, $target);

        return redirect()->route('office.workspace.sites.show', [$customerOrganisation, $site, 'section' => 'users'])->with('status', 'Site access assigned.');
    }

    public function destroy(Request $request, CustomerOrganisation $customerOrganisation, Site $site, User $user, ManagePortalUserAction $action): RedirectResponse
    {
        $this->contained($customerOrganisation, $site);
        $action->remove($request->user(), $site, $user);

        return redirect()->route('office.workspace.sites.show', [$customerOrganisation, $site, 'section' => 'users'])->with('status', 'Site access removed.');
    }

    private function contained(CustomerOrganisation $customer, Site $site): void
    {
        abort_unless((int) $site->customer_organisation_id === (int) $customer->id, 404);
    }
}
