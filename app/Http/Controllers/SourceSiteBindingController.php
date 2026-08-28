<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceSiteBindingRequest;
use App\Http\Requests\UpdateSourceSiteBindingRequest;
use App\Models\Site;
use App\Models\SourceSiteBinding;
use App\Services\SourceSiteBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SourceSiteBindingController extends Controller
{
    public function __construct(private readonly SourceSiteBindingService $bindings) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage-source-site-bindings');

        return response()->json([
            'data' => SourceSiteBinding::query()
                ->with(['site.customerOrganisation', 'creator'])
                ->orderBy('source_namespace')
                ->orderBy('source_site_key')
                ->get()
                ->map(fn (SourceSiteBinding $binding): array => $this->view($binding)),
        ]);
    }

    public function store(StoreSourceSiteBindingRequest $request): JsonResponse
    {
        Gate::authorize('manage-source-site-bindings');
        $site = Site::query()->where('uuid', $request->string('portal_site_uuid')->toString())->firstOrFail();

        try {
            $binding = $this->bindings->create(
                $request->user(),
                $request->string('source_namespace')->toString(),
                $request->string('source_site_key')->toString(),
                $request->string('original_name')->toString(),
                $request->input('display_name'),
                $site,
            );
        } catch (\DomainException $exception) {
            return response()->json(['error' => 'BINDING_REJECTED', 'message' => $exception->getMessage()], 422);
        }

        return response()->json($this->view($binding->load(['site.customerOrganisation', 'creator'])), 201);
    }

    public function update(UpdateSourceSiteBindingRequest $request, SourceSiteBinding $sourceSiteBinding): JsonResponse
    {
        Gate::authorize('manage-source-site-bindings');
        $site = Site::query()->where('uuid', $request->string('portal_site_uuid')->toString())->firstOrFail();

        try {
            $binding = $this->bindings->update($request->user(), $sourceSiteBinding, $site, $request->input('display_name'));
        } catch (\DomainException $exception) {
            return response()->json(['error' => 'BINDING_REJECTED', 'message' => $exception->getMessage()], 422);
        }

        return response()->json($this->view($binding));
    }

    /** @return array<string, mixed> */
    private function view(SourceSiteBinding $binding): array
    {
        return [
            'binding_uuid' => $binding->uuid,
            'source_namespace' => $binding->source_namespace,
            'source_site_key' => $binding->source_site_key,
            'original_name' => $binding->original_name,
            'display_name' => $binding->display_name,
            'portal_site' => [
                'uuid' => $binding->site->uuid,
                'name' => $binding->site->name,
                'customer' => $binding->site->customerOrganisation->name,
            ],
            'created_by' => [
                'name' => $binding->creator->name,
                'role' => $binding->creator->portalRole?->name,
            ],
            'created_at' => $binding->created_at?->toIso8601String(),
            'updated_at' => $binding->updated_at?->toIso8601String(),
        ];
    }
}
