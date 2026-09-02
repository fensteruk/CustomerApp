<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ManualSourceImportPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('manage-source-imports');

        $sites = Site::query()
            ->whereNotNull('uuid')
            ->with('customerOrganisation:id,name')
            ->orderBy('name')
            ->get(['id', 'uuid', 'customer_organisation_id', 'name'])
            ->map(fn (Site $site): array => [
                'uuid' => $site->uuid,
                'name' => $site->name,
                'customer' => $site->customerOrganisation->name,
            ])
            ->values();

        return view('portal.source-imports.index', [
            'portalSites' => $sites,
            'officeUserName' => $request->user()->name,
        ]);
    }
}
