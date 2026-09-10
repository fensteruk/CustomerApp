<?php

namespace App\Http\Controllers;

use App\Actions\Administration\CreateCustomerAction;
use App\Actions\Administration\DeactivateCustomerAction;
use App\Actions\Administration\ReactivateCustomerAction;
use App\Actions\Administration\RenameCustomerAction;
use App\Enums\AdministrativeEntityType;
use App\Http\Requests\Office\CustomerLifecycleRequest;
use App\Http\Requests\Office\RenameCustomerOrganisationRequest;
use App\Http\Requests\Office\StoreCustomerOrganisationRequest;
use App\Models\CustomerOrganisation;
use App\Services\OfficeAdministrationQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfficeCustomerController extends Controller
{
    public function index(Request $request, OfficeAdministrationQueryService $queries): JsonResponse
    {
        Gate::authorize('viewAny', CustomerOrganisation::class);
        $validated = $request->validate($this->directoryRules());

        return response()->json($queries->customers(
            $request->user(),
            $validated['search'] ?? null,
            array_key_exists('active', $validated) ? $request->boolean('active') : null,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function show(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $customerOrganisation);

        return response()->json([
            'customer' => $queries->customer($request->user(), $customerOrganisation->uuid),
        ]);
    }

    public function store(
        StoreCustomerOrganisationRequest $request,
        CreateCustomerAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('create', CustomerOrganisation::class);
        $customer = $action->handle($request->user(), $request->validated('name'));

        return response()->json([
            'customer' => $queries->customer($request->user(), $customer->uuid),
        ], 201);
    }

    public function update(
        RenameCustomerOrganisationRequest $request,
        CustomerOrganisation $customerOrganisation,
        RenameCustomerAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('update', $customerOrganisation);
        $customer = $action->handle(
            $request->user(),
            $customerOrganisation,
            $request->validated('name'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'customer' => $queries->customer($request->user(), $customer->uuid),
        ]);
    }

    public function deactivate(
        CustomerLifecycleRequest $request,
        CustomerOrganisation $customerOrganisation,
        DeactivateCustomerAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('deactivate', $customerOrganisation);
        $customer = $action->handle(
            $request->user(),
            $customerOrganisation,
            $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'customer' => $queries->customer($request->user(), $customer->uuid),
        ]);
    }

    public function reactivate(
        CustomerLifecycleRequest $request,
        CustomerOrganisation $customerOrganisation,
        ReactivateCustomerAction $action,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('reactivate', $customerOrganisation);
        $customer = $action->handle(
            $request->user(),
            $customerOrganisation,
            $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'customer' => $queries->customer($request->user(), $customer->uuid),
        ]);
    }

    public function audits(
        Request $request,
        CustomerOrganisation $customerOrganisation,
        OfficeAdministrationQueryService $queries,
    ): JsonResponse {
        Gate::authorize('view', $customerOrganisation);
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($queries->audits(
            $request->user(),
            AdministrativeEntityType::CustomerOrganisation->value,
            $customerOrganisation->uuid,
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    private function directoryRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
