<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ActiveSiteController extends Controller
{
    /**
     * Show sites assigned to the authenticated site user.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user?->isSiteRole(), 403);

        /** @var Collection<int, Site> $sites */
        $sites = Site::query()
            ->assignedTo($user)
            ->withCount([
                'projectedPlots as outstanding_projected_plots_count' => fn ($query) => $query->outstanding(),
            ])
            ->orderBy('name')
            ->get();

        $activeSite = $sites->firstWhere(
            'id',
            (int) $request->session()->get(EnsureActiveSiteIsAssigned::SESSION_KEY),
        );

        return view('portal.site-selection', [
            'activeSite' => $activeSite,
            'sites' => $sites,
        ]);
    }

    /**
     * Store a newly selected active site after server-side assignment checks.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSiteRole(), 403);

        $validated = $request->validate([
            'site_id' => ['required', 'integer'],
        ]);

        $site = Site::query()->findOrFail((int) $validated['site_id']);

        abort_unless($user->canAccessSite($site), 403);

        $request->session()->put(EnsureActiveSiteIsAssigned::SESSION_KEY, $site->id);

        return redirect()->route('portal.site-dashboard');
    }
}
