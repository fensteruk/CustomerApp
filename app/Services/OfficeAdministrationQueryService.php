<?php

namespace App\Services;

use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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

        return [
            'availability' => 'NOT_YET_INTEGRATED',
            'state' => 'NOT_YET_INTEGRATED',
            'has_active_binding' => false,
            'active_bindings' => [],
            'active_bindings_truncated' => false,
            'bindings' => null,
        ];
    }

    public function importHistory(User $actor, CustomerOrganisation $customer, Site $site, int $perPage = 20): array
    {
        $this->policy->authorize($actor, 'view');
        $this->assertContained($customer, $site);

        return [
            'availability' => 'NOT_YET_INTEGRATED',
            'empty_state' => 'No import integration has been released yet.',
            'runs' => [],
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
            'source_binding_state' => 'NOT_YET_INTEGRATED',
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

    /** @param array<int, string> $codes */
    private function productTotal(ProjectedPlot $plot, array $codes): string
    {
        $units = $plot->products
            ->whereIn('product_code', $codes)
            ->reduce(fn (int $total, $product): int => $total + $this->quantityUnits($product->quantity), 0);

        return intdiv($units, 1000).'.'.str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function quantityUnits(mixed $quantity): int
    {
        $value = trim((string) $quantity);
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 1000)
            + (int) str_pad($matches[2] ?? '', 3, '0', STR_PAD_RIGHT);
    }
}
