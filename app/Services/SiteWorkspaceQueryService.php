<?php

namespace App\Services;

use App\Enums\CallOffRequestStatus;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/** Read-only presentation for the two existing Site workspace routes. */
final class SiteWorkspaceQueryService
{
    public function __construct(private PlotOverviewQueryService $overview) {}

    public function workspace(User $actor, Site $site, array $filters): array
    {
        $office = $actor->isFensterOfficeStaff();
        if ($office) {
            app(OfficeAdministrationPolicy::class)->authorize($actor, 'view');
        } else {
            abort_unless($actor->hasCompletePortalProfile() && $actor->isSiteRole() && $actor->canAccessSite($site), 403);
        }

        $plots = $this->overview->paginate($site, $filters);
        $models = new Collection($plots->getCollection()->pluck('plot')->all());
        $models->load('products:id,projected_plot_id,product_code,quantity');
        $requests = $this->requests($site)->whereIn('projected_plot_id', $models->modelKeys())
            ->with(['batch', 'latestEffectiveAmendment' => fn ($query) => $query->withExists(['proposals as awaiting_site_user' => fn (Builder $proposal) => $proposal
                ->where('proposal_type', 'fenster_alternative_date')->where('status', 'awaiting_response')])])->get();
        $byPlot = $requests->groupBy('projected_plot_id');
        $dictionary = CustomerAppDictionary::definition();
        $cards = $plots->getCollection()->mapWithKeys(function ($overview) use ($byPlot, $office, $dictionary): array {
            $plot = $overview->plot;
            $current = $byPlot->get($plot->id, collect());
            $dates = $current->map(fn ($request) => $this->date($request))->filter(fn ($date) => $date && $date['date']->gte(today()))->sortBy('date');

            return [$plot->uuid => [
                'overview' => $overview,
                'label' => str($plot->plot_reference)->lower()->startsWith('plot ') ? $plot->plot_reference : 'Plot '.$plot->plot_reference,
                'windows' => $this->total($plot, array_keys($dictionary['windows'])),
                'doors' => $this->total($plot, array_keys($dictionary['doors'])),
                'bifold' => $this->total($plot, ['BF']),
                'attention' => $current->contains(fn ($request) => $this->needsResponse($request, $office)),
                'amendment' => $current->contains('status', CallOffRequestStatus::AmendmentOnHold),
                'early' => $current->contains(fn ($request) => $request->is_early_date_exception && in_array($request->status, [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster, CallOffRequestStatus::AwaitingSiteUser], true)),
                'next' => $dates->first(),
                'request' => $current->first(fn ($request) => $this->needsResponse($request, $office)) ?? $current->first(),
            ]];
        });

        $attention = $this->requests($site)->where(function (Builder $query) use ($office): void {
            $query->whereIn('status', $office ? [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster] : [CallOffRequestStatus::AwaitingSiteUser])
                ->orWhere(function (Builder $amendment) use ($office): void {
                    $amendment->where('status', CallOffRequestStatus::AmendmentOnHold);
                    $method = $office ? 'whereDoesntHave' : 'whereHas';
                    $amendment->{$method}('latestEffectiveAmendment', fn (Builder $cycle) => $cycle
                        ->where('status', 'open')
                        ->whereHas('proposals', fn (Builder $proposal) => $proposal
                            ->where('proposal_type', 'fenster_alternative_date')->where('status', 'awaiting_response')));
                });
        })->distinct()->count('projected_plot_id');
        $dateSql = "CASE WHEN call_off_requests.status IN ('approved', 'date_agreed') THEN COALESCE(call_off_requests.agreed_date, call_off_requests.requested_date, site_batch.requested_date) ELSE COALESCE(call_off_requests.requested_date, site_batch.requested_date) END";
        $upcoming = $this->requests($site)->where('status', '!=', CallOffRequestStatus::AmendmentOnHold)
            ->join('call_off_batches as site_batch', 'site_batch.id', '=', 'call_off_requests.call_off_batch_id')
            ->select('call_off_requests.*')->selectRaw($dateSql.' AS workspace_date')
            ->whereRaw($dateSql.' >= ?', [today()->toDateString()])
            ->reorder()->orderBy('workspace_date')->orderBy('call_off_requests.id')
            ->with(['batch', 'latestEffectiveAmendment'])->limit(3)->get()
            ->map(fn ($request) => $this->date($request) + ['request' => $request]);

        return [
            'plots' => $plots,
            'reviewSiteId' => $site->id,
            'cards' => $cards,
            'siteSummary' => [
                'total' => $site->projectedPlots()->count(),
                'attention' => $attention,
                'upcoming' => $upcoming,
            ],
            'isOffice' => $office,
        ];
    }

    private function requests(Site $site): Builder
    {
        return CallOffRequest::query()
            ->whereHas('batch', fn (Builder $query) => $query->where('site_id', $site->id))
            ->whereHas('projectedPlot', fn (Builder $query) => $query->where('site_id', $site->id))
            ->whereNull('trashed_at')
            ->whereIn('status', array_filter(CallOffRequestStatus::cases(), fn ($status) => $status->isConflictActive()))
            ->whereDoesntHave('projectedPlotService', fn (Builder $query) => $query
                ->whereNotNull('source_completed_at')->orWhereNotNull('source_completion_observed_at'))
            ->with('projectedPlot:id,uuid,site_id,plot_reference')
            ->latest('call_off_requests.id');
    }

    private function needsResponse(CallOffRequest $request, bool $office): bool
    {
        if ($request->status === CallOffRequestStatus::AmendmentOnHold) {
            $cycle = $request->latestEffectiveAmendment;
            $awaitingSite = $cycle?->status->value === 'open' && $cycle->awaiting_site_user;

            return $office ? ! $awaitingSite : $awaitingSite;
        }

        return $office
            ? in_array($request->status, [CallOffRequestStatus::Submitted, CallOffRequestStatus::AwaitingFenster], true)
            : $request->status === CallOffRequestStatus::AwaitingSiteUser;
    }

    private function date(CallOffRequest $request): ?array
    {
        if ($request->status === CallOffRequestStatus::AmendmentOnHold) {
            $date = $request->effectiveRequestedDate();
            $label = 'Amendment requested';
        } elseif (in_array($request->status, [CallOffRequestStatus::Approved, CallOffRequestStatus::DateAgreed], true)) {
            $date = $request->agreed_date ?? $request->effectiveRequestedDate();
            $label = 'Date Agreed';
        } else {
            $date = $request->effectiveRequestedDate();
            $label = 'Requested · not agreed';
        }

        return $date ? ['date' => Carbon::parse($date), 'label' => $label, 'service' => $request->effectiveServiceIdentifier()?->label()] : null;
    }

    private function total(ProjectedPlot $plot, array $codes): string
    {
        $products = $plot->products->whereIn('product_code', $codes);
        if ($products->isEmpty()) {
            return 'Not supplied';
        }
        $units = $products->reduce(function (int $sum, $product): int {
            preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', (string) $product->quantity, $parts);

            return $sum + ((int) ($parts[1] ?? 0) * 1000) + (int) str_pad($parts[2] ?? '', 3, '0');
        }, 0);

        return rtrim(rtrim(intdiv($units, 1000).'.'.str_pad((string) ($units % 1000), 3, '0', STR_PAD_LEFT), '0'), '.');
    }
}
