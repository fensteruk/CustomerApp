<?php

namespace App\Http\Controllers\Development;

use App\Demo\SyntheticImportStudioScenario;
use App\Http\Controllers\Controller;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SyntheticImportStudioController extends Controller
{
    public function show(Request $request, SyntheticImportStudioScenario $scenario): View
    {
        $this->authorizeDemo($request);

        return $this->view($scenario);
    }

    public function site(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        SyntheticImportStudioScenario $scenario,
    ): View {
        $this->authorizeDemo($request);
        abort_unless((int) $site->customer_organisation_id === (int) $customerOrganisation->getKey(), 404);

        return $this->view($scenario, [
            'customer' => $customerOrganisation->name,
            'site' => $site->name,
            'return_url' => route('office.workspace.sites.show', [$customerOrganisation, $site]),
        ]);
    }

    private function authorizeDemo(Request $request): void
    {
        abort_unless(
            app()->environment(['local', 'testing']) && config('import-demo.enabled'),
            404,
        );

        $actor = User::query()->with('portalRole')->find($request->user()?->getAuthIdentifier());
        abort_unless($actor?->is_active && $actor->isFensterOfficeStaff(), 403);
    }

    /** @param array<string, string>|null $launchContext */
    private function view(SyntheticImportStudioScenario $scenario, ?array $launchContext = null): View
    {
        return view('development.import-studio', [
            'scenario' => $scenario->manifest(),
            'steps' => [
                'Select Example',
                'Export Date',
                'Export Slot',
                'Uploader',
                'Latest Export',
                'Wald Analysis',
                'Detected Site',
                'Source Binding',
                'Detected Records',
                'Clarifications',
                'Preview Changes',
                'Commit Summary',
            ],
            'launchContext' => $launchContext,
        ]);
    }
}
