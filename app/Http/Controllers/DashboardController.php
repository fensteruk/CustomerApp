<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Route an authenticated portal user to the correct dashboard.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isFensterOfficeStaff()) {
            return redirect()->route('portal.review-requests');
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
