<?php

namespace App\Services;

use App\Enums\CallOffRequestStatus;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Database\Eloquent\Builder;

/** Read-only counts for the Office Customers workspace. */
final class OfficeCustomerWorkspaceQueryService
{
    public function __construct(private readonly OfficeAdministrationPolicy $policy) {}

    public function index(User $actor, string $search, ?bool $active, string $sort): array
    {
        $this->policy->authorize($actor, 'view');

        $customers = CustomerOrganisation::query()
            ->select(['id', 'uuid', 'name', 'is_active'])
            ->withCount([
                'sites as site_count',
                'sites as active_site_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->addSelect([
                'plot_count' => ProjectedPlot::query()->selectRaw('count(*)')
                    ->join('sites', 'sites.id', '=', 'projected_plots.site_id')
                    ->whereColumn('sites.customer_organisation_id', 'customer_organisations.id'),
                'attention_count' => CallOffRequest::query()->selectRaw('count(*)')
                    ->join('call_off_batches', 'call_off_batches.id', '=', 'call_off_requests.call_off_batch_id')
                    ->join('sites', 'sites.id', '=', 'call_off_batches.site_id')
                    ->whereColumn('sites.customer_organisation_id', 'customer_organisations.id')
                    ->where('customer_organisations.is_active', true)
                    ->where('sites.is_active', true)
                    ->whereNull('call_off_requests.trashed_at')
                    ->where('call_off_requests.status', CallOffRequestStatus::AwaitingFenster),
            ])
            ->when(trim($search) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active))
            ->orderBy('name', $sort === 'name_desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(18)
            ->withQueryString();

        return [
            'customers' => $customers,
            'summary' => [
                'customers' => CustomerOrganisation::query()->count(),
                'sites' => Site::query()->count(),
                'active_sites' => Site::query()->where('is_active', true)->count(),
            ],
        ];
    }
}
