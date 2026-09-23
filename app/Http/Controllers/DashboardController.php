<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Services\OfficeDashboardQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Route an authenticated portal user to the correct dashboard.
     */
    public function __invoke(Request $request, OfficeDashboardQueryService $dashboard): RedirectResponse|View
    {
        $user = $request->user();

        if ($user?->isFensterOfficeStaff()) {
            return view('office.dashboard.index', $dashboard->forUser($user));
        }

        if ($user?->isSiteRole()) {
            $siteId = $request->session()->get(EnsureActiveSiteIsAssigned::SESSION_KEY);

            if ($siteId !== null) {
                return redirect()->route('portal.site-dashboard');
            }

            return redirect()->route('sites.select');
        }

        abort(403);
    }
}
