<?php

namespace App\Services;

use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\SourceImport\Semantics\Dictionary\Quantity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OfficeAdministrationQueryService
{
    private const WINDOWS_PRODUCTS = ['VS', 'TT', 'BAY', 'ALI', 'AOV', 'FI'];

    private const DOOR_PRODUCTS = ['PSU', 'PSG', 'CDF', 'CDU', 'CDG', 'PSP', 'BF'];

    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly PlotOverviewQueryService $plotOverview,
    ) {}

    public function customers(User $actor, ?string $search, ?bool $active, int $perPage = 20): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');

        return CustomerOrganisation::query()
            ->select(['id', 'uuid', 'name', 'is_active', 'lock_version', 'created_at', 'updated_at'])
            ->withCount([
                'sites',
                'sites as active_sites_count' => fn (Builder $query) => $query->where('is_active', true),
                'users',
            ])
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query
                ->where('name', 'like', '%'.trim($search).'%'))
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage($perPage))
            ->through(fn (CustomerOrganisation $customer): array => $this->customerSummary($customer));
    }

    public function customer(User $actor, string $uuid): array
    {
        $this->policy->authorize($actor, 'view');

        $customer = CustomerOrganisation::query()
            ->where('uuid', $uuid)
            ->withCount([
                'sites',
                'sites as active_sites_count' => fn (Builder $query) => $query->where('is_active', true),
                'users',
            ])
            ->firstOrFail();

        return $this->customerSummary($customer);
    }

    public function sites(
        User $actor,
        ?CustomerOrganisation $customer,
        ?string $search,
        ?bool $active,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $this->policy->authorize($actor, 'view');

        $query = Site::query()
            ->select([
                'id', 'uuid', 'customer_organisation_id', 'name', 'location', 'external_source',
                'external_identifier', 'is_active', 'lock_version', 'created_at', 'updated_at',
            ])
            ->with('customerOrganisation:id,uuid,name,is_active')
            ->withCount(['projectedPlots', 'assignments'])
            ->when($customer !== null, fn (Builder $query) => $query
                ->where('customer_organisation_id', $customer->getKey()))
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query
                ->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.trim($search).'%')
                        ->orWhere('location', 'like', '%'.trim($search).'%');
                }))
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active));

        $this->addActiveBindingCount($query);

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage($perPage))
            ->through(fn (Site $site): array => $this->siteSummary($site));
    }

    public function site(User $actor, CustomerOrganisation $customer, string $siteUuid): array
    {
        $this->policy->authorize($actor, 'view');

        $query = Site::query()
            ->where('customer_organisation_id', $customer->getKey())
            ->where('uuid', $siteUuid)
            ->with('customerOrganisation:id,uuid,name,is_active')
            ->withCount(['projectedPlots', 'assignments']);

        $this->addActiveBindingCount($query);

        $site = $query->firstOrFail();

        return $this->siteSummary($site);
    }

    public function plots(User $actor, CustomerOrganisation $customer, Site $site, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');
        $this->assertContained($customer, $site);

        return ProjectedPlot::query()
            ->where('site_id', $site->getKey())
            ->select([
                'id', 'uuid', 'site_id', 'external_source', 'external_identifier', 'plot_reference',
                'is_completed', 'source_updated_at', 'synchronised_at', 'created_at',
            ])
            ->with([
                'services:id,projected_plot_id,service_identifier,source_completed_at,source_completion_observed_at,source_present',
                'products:id,projected_plot_id,product_code,quantity',
                'callOffRequests' => fn ($query) => $query
                    ->select([
                        'id', 'call_off_batch_id', 'projected_plot_id', 'projected_plot_service_id',
                        'service_identifier', 'requested_date', 'agreed_date', 'status', 'trashed_at',
                    ])
                    ->whereNull('trashed_at')
                    ->with('batch:id,service_identifier,requested_date'),
            ])
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query
                ->where('plot_reference', 'like', '%'.trim($search).'%'))
            ->orderBy('plot_reference')
            ->orderBy('id')
            ->paginate($this->perPage($perPage))
            ->through(function (ProjectedPlot $plot): array {
                $overview = $this->plotOverview->present($plot);

                return [
                    'uuid' => $plot->uuid,
                    'plot_reference' => $plot->plot_reference,
                    'source_identity' => [
                        'source' => $plot->external_source,
                        'identifier' => $plot->external_identifier,
                    ],
                    'overall_status' => [
                        'value' => $overview->overallStatus->value,
                        'label' => $overview->overallStatus->label(),
                    ],
                    'product_totals' => [
                        'windows' => $this->productTotal($plot, self::WINDOWS_PRODUCTS),
                        'doors' => $this->productTotal($plot, self::DOOR_PRODUCTS),
                        'bifold' => $this->productTotal($plot, ['BF']),
                    ],
                    'is_completed' => (bool) $plot->is_completed,
                    'source_updated_at' => $plot->source_updated_at?->toISOString(),
                    'synchronised_at' => $plot->synchronised_at?->toISOString(),
                    'created_at' => $plot->created_at?->toISOString(),
                    'services' => $plot->services->map(function ($service) use ($overview): array {
                        $presentation = $overview->services[$service->service_identifier->value];

                        return [
                            'service' => $service->service_identifier->value,
                            'portal_status' => [
                                'value' => $presentation->state->value,
                                'label' => $presentation->state->label(),
                                'date' => $presentation->date?->toDateString(),
                            ],
                            'source_present' => (bool) $service->source_present,
                            'source_completed_at' => $service->source_completed_at?->toISOString(),
                            'source_completion_observed_at' => $service->source_completion_observed_at?->toISOString(),
                        ];
                    })->values()->all(),
                ];
            });
    }

    public function assignedUsers(User $actor, CustomerOrganisation $customer, Site $site, int $perPage = 20): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');
        $this->assertContained($customer, $site);

        return User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.portal_role_id', 'users.is_active'])
            ->join('site_user_assignments', 'site_user_assignments.user_id', '=', 'users.id')
            ->where('site_user_assignments.site_id', $site->getKey())
            ->where('users.customer_organisation_id', $customer->getKey())
            ->with('portalRole:id,identifier,name')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate($this->perPage($perPage))
            ->through(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->portalRole?->identifier,
                'role_label' => $user->portalRole?->name,
                'is_active' => (bool) $user->is_active,
                'management_identity_available' => false,
            ]);
    }

    public function sourceBindings(User $actor, CustomerOrganisation $customer, Site $site, int $perPage = 20): array
    {
        $this->policy->authorize($actor, 'view');
        $this->assertContained($customer, $site);

        if (! Schema::hasTable('wald_source_bindings')) {
            return [
                'availability' => 'NOT_YET_INTEGRATED',
                'state' => 'NOT_LINKED',
                'active_bindings' => [],
                'bindings' => null,
            ];
        }

        $scopedVersions = DB::table('wald_binding_versions as version')
            ->join('wald_source_bindings as binding', 'binding.id', '=', 'version.binding_id')
            ->where('version.customer_organisation_id', $customer->getKey())
            ->where('version.site_id', $site->getKey())
            ->select([
                'binding.source_namespace', 'binding.identity_kind', 'binding.source_identity',
                'binding.latest_version', 'binding.active_version', 'binding.revoked_through',
                'binding.updated_at', 'version.version', 'version.actor_name', 'version.reason',
                'version.created_at',
            ]);

        $bindings = (clone $scopedVersions)
            ->orderByDesc('version.id')
            ->paginate($this->perPage($perPage))
            ->through(fn ($binding): array => $this->bindingSummary($binding));

        $activeBindingRows = (clone $scopedVersions)
            ->whereColumn('version.version', 'binding.active_version')
            ->orderBy('binding.source_namespace')
            ->orderBy('binding.id')
            ->limit(101)
            ->get();
        $activeBindingsTruncated = $activeBindingRows->count() > 100;
        $activeBindings = $activeBindingRows
            ->take(100)
            ->map(fn ($binding): array => $this->bindingSummary($binding))
            ->all();

        $latest = (clone $scopedVersions)->orderByDesc('version.id')->first();

        return [
            'availability' => 'AVAILABLE',
            'state' => $activeBindings !== [] ? 'ACTIVE' : ($latest === null ? 'NOT_LINKED' : $this->bindingState($latest)),
            'has_active_binding' => $activeBindings !== [],
            'active_bindings' => $activeBindings,
            'active_bindings_truncated' => $activeBindingsTruncated,
            'bindings' => $bindings,
        ];
    }

    public function importHistory(User $actor, CustomerOrganisation $customer, Site $site, int $perPage = 20): array
    {
        $this->policy->authorize($actor, 'view');
        $this->assertContained($customer, $site);

        if (! Schema::hasTable('wald_import_runs')) {
            return ['availability' => 'NOT_YET_INTEGRATED', 'empty_state' => 'Import history is not integrated yet.', 'runs' => []];
        }

        $runs = DB::table('wald_import_runs as run')
            ->leftJoin('wald_import_receipts as receipt', 'receipt.run_id', '=', 'run.id')
            ->where('run.customer_organisation_id', $customer->getKey())
            ->where('run.site_id', $site->getKey())
            ->orderByDesc('run.id')
            ->select([
                'run.uuid', 'run.source_namespace', 'run.workbook_family', 'run.uploader_name',
                'run.export_date', 'run.export_slot', 'run.state', 'run.predecessor_id', 'run.created_at',
                'run.replacement_reason', 'run.updated_at', 'run.terminal_at', 'receipt.uuid as receipt_uuid', 'receipt.revision',
                'receipt.payload as receipt_payload', 'receipt.created_at as committed_at',
            ])
            ->paginate($this->perPage($perPage));

        $runs->through(function ($run): array {
            $payload = $run->receipt_payload === null ? [] : json_decode($run->receipt_payload, true);
            $payload = is_array($payload) ? $payload : [];

            return [
                'uuid' => $run->uuid,
                'source_namespace' => $run->source_namespace,
                'workbook_family' => $run->workbook_family,
                'uploader_name' => $run->uploader_name,
                'export_date' => $run->export_date,
                'export_slot' => $run->export_slot,
                'state' => $run->state,
                'is_correction' => $run->predecessor_id !== null,
                'is_superseded' => $run->state === 'SUPERSEDED',
                'replacement_reason' => $run->replacement_reason,
                'created_at' => $run->created_at,
                'updated_at' => $run->updated_at,
                'terminal_at' => $run->terminal_at,
                'receipt' => $run->receipt_uuid === null ? null : [
                    'uuid' => $run->receipt_uuid,
                    'revision' => (int) $run->revision,
                    'committed_at' => $run->committed_at,
                    'counts' => $this->safeImportCounts($payload['counts'] ?? null),
                ],
            ];
        });

        return [
            'availability' => 'AVAILABLE',
            'empty_state' => $runs->isEmpty() ? 'No imports have been recorded for this site.' : null,
            'runs' => $runs,
        ];
    }

    public function audits(User $actor, string $entityType, string $entityUuid, int $perPage = 20): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');

        return AdministrativeAudit::query()
            ->where('entity_type', $entityType)
            ->where('entity_uuid', $entityUuid)
            ->latest('id')
            ->paginate($this->perPage($perPage))
            ->through(fn (AdministrativeAudit $audit): array => [
                'uuid' => $audit->uuid,
                'action' => $audit->action->value,
                'actor_name' => $audit->actor_name,
                'actor_role' => $audit->actor_role,
                'before' => $audit->before_state,
                'after' => $audit->after_state,
                'reason' => $audit->reason,
                'occurred_at' => $audit->occurred_at->toISOString(),
            ]);
    }

    private function customerSummary(CustomerOrganisation $customer): array
    {
        return [
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'is_active' => (bool) $customer->is_active,
            'lock_version' => (int) $customer->lock_version,
            'site_count' => (int) ($customer->sites_count ?? 0),
            'active_site_count' => (int) ($customer->active_sites_count ?? 0),
            'user_count' => (int) ($customer->users_count ?? 0),
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }

    private function siteSummary(Site $site): array
    {
        return [
            'uuid' => $site->uuid,
            'customer' => [
                'uuid' => $site->customerOrganisation->uuid,
                'name' => $site->customerOrganisation->name,
                'is_active' => (bool) $site->customerOrganisation->is_active,
            ],
            'name' => $site->name,
            'location' => $site->location,
            'is_active' => (bool) $site->is_active,
            'effective_is_active' => (bool) $site->is_active && (bool) $site->customerOrganisation->is_active,
            'lock_version' => (int) $site->lock_version,
            'plot_count' => (int) ($site->projected_plots_count ?? 0),
            'assignment_count' => (int) ($site->assignments_count ?? 0),
            'source_reference' => $site->external_source === null && $site->external_identifier === null ? null : [
                'source' => $site->external_source,
                'identifier' => $site->external_identifier,
            ],
            'source_binding_state' => (int) ($site->active_source_bindings_count ?? 0) > 0 ? 'ACTIVE' : 'NOT_LINKED',
            'created_at' => $site->created_at?->toISOString(),
            'updated_at' => $site->updated_at?->toISOString(),
        ];
    }

    private function assertContained(CustomerOrganisation $customer, Site $site): void
    {
        abort_unless((int) $site->customer_organisation_id === (int) $customer->getKey(), 404);
    }

    private function perPage(int $perPage): int
    {
        return max(1, min(100, $perPage));
    }

    /** @param Builder<Site> $query */
    private function addActiveBindingCount(Builder $query): void
    {
        if (! Schema::hasTable('wald_source_bindings')) {
            return;
        }

        $query->addSelect([
            'active_source_bindings_count' => DB::table('wald_binding_versions as binding_version')
                ->join('wald_source_bindings as binding', 'binding.id', '=', 'binding_version.binding_id')
                ->selectRaw('COUNT(*)')
                ->whereColumn('binding_version.site_id', 'sites.id')
                ->whereColumn('binding_version.version', 'binding.active_version'),
        ]);
    }

    /** @param array<int, string> $codes */
    private function productTotal(ProjectedPlot $plot, array $codes): string
    {
        $units = $plot->products
            ->whereIn('product_code', $codes)
            ->reduce(fn (int $total, $product): int => $total + (Quantity::units($product->quantity) ?? 0), 0);

        return Quantity::decimal($units);
    }

    private function bindingSummary(object $binding): array
    {
        return [
            'source_namespace' => $binding->source_namespace,
            'identity_kind' => $binding->identity_kind,
            'source_identity' => $binding->source_identity,
            'state' => $this->bindingState($binding),
            'version' => (int) $binding->version,
            'created_by' => $binding->actor_name,
            'reason' => $binding->reason,
            'created_at' => $binding->created_at,
            'last_changed_at' => $binding->updated_at,
        ];
    }

    private function bindingState(object $binding): string
    {
        $version = (int) $binding->version;
        $activeVersion = $binding->active_version === null ? null : (int) $binding->active_version;
        $revokedThrough = (int) $binding->revoked_through;

        if ($activeVersion === $version) {
            return 'ACTIVE';
        }

        if ($revokedThrough === $version) {
            return 'REVOKED';
        }

        if (($activeVersion !== null && $version < $activeVersion) || ($revokedThrough > 0 && $version < $revokedThrough)) {
            return 'SUPERSEDED';
        }

        return 'DRAFT';
    }

    private function safeImportCounts(mixed $counts): ?array
    {
        if (! is_array($counts)) {
            return null;
        }

        return collect(['seen', 'excluded', 'applied', 'created', 'updated', 'unchanged'])
            ->filter(fn (string $key): bool => is_int($counts[$key] ?? null))
            ->mapWithKeys(fn (string $key): array => [$key => $counts[$key]])
            ->all();
    }
}
