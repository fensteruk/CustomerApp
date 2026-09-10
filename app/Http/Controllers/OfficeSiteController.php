<?php

namespace App\Http\Controllers;

use App\Actions\Administration\CreateSiteAction;
use App\Actions\Administration\DeactivateSiteAction;
use App\Actions\Administration\ReactivateSiteAction;
use App\Actions\Administration\UpdateSiteAction;
use App\Enums\AdministrativeEntityType;
use App\Http\Requests\Office\SiteLifecycleRequest;
use App\Http\Requests\Office\StoreSiteRequest;
use App\Http\Requests\Office\UpdateSiteRequest;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Services\OfficeAdministrationQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfficeSiteController extends Controller
{
    public function index(Request $request, OfficeAdministrationQueryService $queries): JsonResponse
    {
        Gate::authorize('viewAny', Site::class);

        return response()->json($this->directory($request, $queries));
    }

    public function customerIndex(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $customerOrganisation);

        return response()->json($this->directory($request, $queries, $customerOrganisation));
    }

    public function show(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);

        return response()->json([
            'site' => $queries->site($request->user(), $customerOrganisation, $site->uuid),
        ]);
    }

    public function store(
        StoreSiteRequest $request,
        CustomerOrganisation $customerOrganisation,
        CreateSiteAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('create', Site::class);
        $site = $action->handle(
            $request->user(),
            $customerOrganisation,
            $request->validated('name'),
            $request->validated('location'),
        );

        return response()->json([
            'site' => $queries->site($request->user(), $customerOrganisation, $site->uuid),
        ], 201);
    }

    public function update(
        UpdateSiteRequest $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        UpdateSiteAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('update', $site);
        $updated = $action->handle(
            $request->user(),
            $customerOrganisation,
            $site,
            $request->validated('name'),
            $request->validated('location'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'site' => $queries->site($request->user(), $customerOrganisation, $updated->uuid),
        ]);
    }

    public function deactivate(
        SiteLifecycleRequest $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        DeactivateSiteAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('deactivate', $site);
        $updated = $action->handle(
            $request->user(),
            $customerOrganisation,
            $site,
            $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'site' => $queries->site($request->user(), $customerOrganisation, $updated->uuid),
        ]);
    }

    public function reactivate(
        SiteLifecycleRequest $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        ReactivateSiteAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('reactivate', $site);
        $updated = $action->handle(
            $request->user(),
            $customerOrganisation,
            $site,
            $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'site' => $queries->site($request->user(), $customerOrganisation, $updated->uuid),
        ]);
    }

    public function plots(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);
        $validated = $request->validate($this->readListRules());

        return response()->json($queries->plots(
            $request->user(),
            $customerOrganisation,
            $site,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function users(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($queries->assignedUsers(
            $request->user(),
            $customerOrganisation,
            $site,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function sourceBindings(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($queries->sourceBindings(
            $request->user(),
            $customerOrganisation,
            $site,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function importHistory(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($queries->importHistory(
            $request->user(),
            $customerOrganisation,
            $site,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function audits(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        Site $site,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $site);
        abort_unless((int) $site->customer_organisation_id === (int) $customerOrganisation->getKey(), 404);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($queries->audits(
            $request->user(),
            AdministrativeEntityType::Site->value,
            $site->uuid,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    private function directory(
        Request $request,
        OfficeAdministrationQueryService $queries,
        ?CustomerOrganisation $customer = null,
    ) {
        $validated = $request->validate($this->directoryRules());

        return $queries->sites(
            $request->user(),
            $customer,
            $validated['search'] ?? null,
            array_key_exists('active', $validated) ? $request->boolean('active') : null,
            (int) ($validated['per_page'] ?? 20),
        );
    }

    private function directoryRules(): array
    {
        return [
            ...$this->readListRules(),
            'active' => ['nullable', 'boolean'],
        ];
    }

    private function readListRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
