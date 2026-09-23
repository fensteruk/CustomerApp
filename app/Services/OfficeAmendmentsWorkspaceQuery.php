<?php

namespace App\Services;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffStatusHistory;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read-only adapter for the baseline amendment domain. OVERHAUL06 can replace
 * latestAmendments() when its canonical latest-effective representation lands.
 * Negotiation response state is deliberately not a RedZebra acknowledgement.
 */
final class OfficeAmendmentsWorkspaceQuery
{
    public function forUser(User $actor, array $filters = []): array
    {
        app(OfficeAdministrationPolicy::class)->authorize($actor, 'view');
        $filters = array_merge(['status' => 'attention', 'customer' => '', 'site' => '', 'service' => '', 'search' => ''], array_filter($filters, fn ($value) => $value !== null));
        $base = $this->latestAmendments();
        $counts = [];
        foreach (['attention', 'waiting', 'closed'] as $status) {
            $counts[$status] = $this->status(clone $base, $status)->count();
        }

        $queue = $this->status(clone $base, $filters['status']);
        $queue->when($filters['customer'], fn (Builder $q, string $uuid) => $q->whereHas('callOffRequest.batch.site.customerOrganisation', fn (Builder $c) => $c->where('uuid', $uuid)))
            ->when($filters['site'], fn (Builder $q, string $uuid) => $q->whereHas('callOffRequest.batch.site', fn (Builder $s) => $s->where('uuid', $uuid)))
            ->when($filters['service'], fn (Builder $q, string $service) => $q->whereHas('callOffRequest', fn (Builder $r) => $r->where(fn (Builder $r) => $r->where('service_identifier', $service)->orWhere(fn (Builder $r) => $r->whereNull('service_identifier')->whereHas('batch', fn (Builder $b) => $b->where('service_identifier', $service))))))
            ->when(trim($filters['search']) !== '', function (Builder $q) use ($filters): void {
                // Treat wildcard characters literally, consistently on SQLite/MySQL.
                $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
                $q->where(fn (Builder $q) => $q
                    ->whereHas('callOffRequest.projectedPlot', fn (Builder $p) => $p->whereRaw("plot_reference LIKE ? ESCAPE '!'", [$needle]))
                    ->orWhereHas('callOffRequest.batch.site', fn (Builder $s) => $s->whereRaw("name LIKE ? ESCAPE '!'", [$needle]))
                    ->orWhereHas('callOffRequest.batch.site.customerOrganisation', fn (Builder $c) => $c->whereRaw("name LIKE ? ESCAPE '!'", [$needle])));
            });
        $amendments = $this->withContext($queue)->orderByDesc('opened_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        // Selection is independent of filtering: a stable request link always
        // resolves its latest cycle, never an obsolete cycle from a stale URL.
        $selected = ! empty($filters['request'])
            ? $this->withContext(clone $base)->whereHas('callOffRequest', fn (Builder $r) => $r->where('uuid', $filters['request']))->firstOrFail()
            : $amendments->first();
        $history = $selected
            ? CallOffStatusHistory::query()->where('call_off_request_id', $selected->call_off_request_id)
                ->with('performedBy.portalRole')->orderBy('sequence')->orderBy('id')
                ->paginate(10, ['*'], 'history_page')->appends([...$filters, 'request' => $selected->callOffRequest->uuid])
            : null;

        // Bounded native selects; search remains available beyond the option cap.
        $customers = CustomerOrganisation::query()->orderBy('name')->orderBy('id')->limit(100)->get(['id', 'uuid', 'name']);
        $sites = Site::query()->when($filters['customer'], fn (Builder $s, string $uuid) => $s->whereHas('customerOrganisation', fn (Builder $c) => $c->where('uuid', $uuid)))
            ->orderBy('name')->orderBy('id')->limit(100)->get(['id', 'uuid', 'name']);
        foreach (['customer' => [$customers, CustomerOrganisation::class], 'site' => [$sites, Site::class]] as $key => [$options, $model]) {
            if ($filters[$key] && ! $options->contains('uuid', $filters[$key])) {
                $chosen = $model::query()->where('uuid', $filters[$key])->first(['id', 'uuid', 'name']);
                if ($chosen) {
                    $options->push($chosen);
                }
            }
        }

        return compact('amendments', 'selected', 'history', 'counts', 'filters', 'customers', 'sites') + ['services' => CallOffServiceType::cases()];
    }

    private function latestAmendments(): Builder
    {
        return CallOffDateNegotiation::query()->where('purpose', CallOffNegotiationPurpose::Amendment)
            ->whereHas('callOffRequest')
            ->whereNotExists(function (QueryBuilder $newer): void {
                $newer->selectRaw('1')->from('call_off_date_negotiations as newer')
                    ->whereColumn('newer.call_off_request_id', 'call_off_date_negotiations.call_off_request_id')
                    ->where('newer.purpose', CallOffNegotiationPurpose::Amendment->value)
                    ->where(fn (QueryBuilder $q) => $q->whereColumn('newer.opened_at', '>', 'call_off_date_negotiations.opened_at')
                        ->orWhere(fn (QueryBuilder $q) => $q->whereColumn('newer.opened_at', 'call_off_date_negotiations.opened_at')->whereColumn('newer.id', '>', 'call_off_date_negotiations.id')));
            });
    }

    private function pendingProposal(Builder $query): void
    {
        $query->where('proposal_type', CallOffDateProposalType::FensterAlternativeDate)
            ->where('status', CallOffDateProposalStatus::AwaitingResponse);
    }

    private function status(Builder $query, string $status): Builder
    {
        if (in_array($status, ['attention', 'waiting'], true)) {
            $query->where('status', CallOffNegotiationStatus::Open)
                ->whereHas('callOffRequest', fn (Builder $r) => $r->whereNull('trashed_at')->where('status', CallOffRequestStatus::AmendmentOnHold));
            $status === 'waiting'
                ? $query->whereHas('proposals', $this->pendingProposal(...))
                : $query->whereDoesntHave('proposals', $this->pendingProposal(...));
        } elseif ($status === 'closed') {
            $query->where('status', '!=', CallOffNegotiationStatus::Open);
        }

        return $query;
    }

    private function withContext(Builder $query): Builder
    {
        return $query->with(['callOffRequest.projectedPlot', 'callOffRequest.batch.site.customerOrganisation'])
            ->withExists(['proposals as awaiting_site_user' => $this->pendingProposal(...)]);
    }

    public function stateLabel(CallOffDateNegotiation $amendment): string
    {
        return $amendment->status === CallOffNegotiationStatus::Open
            ? ($amendment->awaiting_site_user ? 'Awaiting Site User' : 'Awaiting Fenster')
            : $amendment->progressLabel();
    }
}
