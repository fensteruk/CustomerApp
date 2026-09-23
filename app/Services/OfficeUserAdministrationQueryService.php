<?php

namespace App\Services;

use App\Enums\AdministrativeEntityType;
use App\Enums\PortalRoleIdentifier;
use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OfficeUserAdministrationQueryService
{
    public function __construct(private readonly OfficeAdministrationPolicy $policy) {}

    public function users(User $actor, ?string $search, ?bool $active, int $perPage = 20, ?string $role = null, ?string $customer = null): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');

        return User::query()
            ->where('is_preview_user', false)
            ->with(['portalRole:id,identifier,name', 'customerOrganisation:id,uuid,name,is_active'])
            ->with(['assignedSites' => fn ($query) => $query->select('sites.id', 'sites.name', 'sites.is_active')->orderBy('name')->orderBy('sites.id')->limit(3)])
            ->withCount('assignedSites')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.trim((string) $search).'%')
                ->orWhere('email', 'like', '%'.trim((string) $search).'%')))
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active))
            ->when(filled($role), fn (Builder $query) => $query->whereHas('portalRole', fn (Builder $roles) => $roles->where('identifier', $role)))
            ->when(filled($customer), fn (Builder $query) => $query->whereHas('customerOrganisation', fn (Builder $customers) => $customers->where('uuid', $customer)))
            ->orderBy('name')->orderBy('id')->paginate(max(1, min(100, $perPage)));
    }

    /** Counts cover all non-preview accounts, independently of list filters. */
    public function summary(User $actor): array
    {
        $this->policy->authorize($actor, 'view');

        $users = User::query()->where('is_preview_user', false);
        $external = (clone $users)->whereHas('portalRole', fn (Builder $query) => $query
            ->whereIn('identifier', array_map(fn (PortalRoleIdentifier $role): string => $role->value, PortalRoleIdentifier::siteRoles())));

        return [
            'total' => (clone $users)->count(),
            'office' => (clone $users)->whereHas('portalRole', fn (Builder $query) => $query->where('identifier', PortalRoleIdentifier::FensterOfficeStaff->value))->count(),
            'external' => (clone $external)->count(),
            'attention' => (clone $external)->where(fn (Builder $query) => $query
                ->whereNull('customer_organisation_id')->orWhereDoesntHave('assignedSites'))->count(),
        ];
    }

    public function user(User $actor, User $target): User
    {
        $this->policy->authorize($actor, 'view');

        return $target->load(['portalRole:id,identifier,name', 'customerOrganisation:id,uuid,name,is_active', 'assignedSites' => fn ($query) => $query->with('customerOrganisation:id,uuid,name,is_active')->orderBy('name')]);
    }

    public function customerUsers(User $actor, CustomerOrganisation $customer, int $perPage = 10): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');

        return User::query()->where('customer_organisation_id', $customer->id)->where('is_preview_user', false)
            ->with('portalRole:id,identifier,name')->withCount('assignedSites')->orderBy('name')->orderBy('id')
            ->paginate($perPage, ['*'], 'users_page')->withQueryString();
    }

    public function eligibleForSite(User $actor, Site $site): Collection
    {
        $this->policy->authorize($actor, 'view');

        return User::query()->where('customer_organisation_id', $site->customer_organisation_id)
            ->where('is_preview_user', false)
            ->whereHas('portalRole', fn (Builder $query) => $query->whereIn('identifier', array_map(fn (PortalRoleIdentifier $role): string => $role->value, PortalRoleIdentifier::siteRoles())))
            ->whereDoesntHave('assignedSites', fn (Builder $query) => $query->whereKey($site->id))
            ->with('portalRole:id,identifier,name')->orderBy('name')->get();
    }

    public function customers(User $actor): Collection
    {
        $this->policy->authorize($actor, 'view');

        return CustomerOrganisation::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'uuid', 'name', 'is_active']);
    }

    public function sites(User $actor, ?CustomerOrganisation $customer): Collection
    {
        $this->policy->authorize($actor, 'view');

        return $customer ? Site::query()->where('customer_organisation_id', $customer->id)->orderByDesc('is_active')->orderBy('name')->get() : collect();
    }

    public function allSites(User $actor): Collection
    {
        $this->policy->authorize($actor, 'view');

        return Site::query()->with('customerOrganisation:id,uuid,name,is_active')->orderBy('customer_organisation_id')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function roles(User $actor): Collection
    {
        $this->policy->authorize($actor, 'view');

        return PortalRole::query()->orderBy('id')->get(['id', 'identifier', 'name']);
    }

    public function audits(User $actor, User $target): LengthAwarePaginator
    {
        $this->policy->authorize($actor, 'view');

        return AdministrativeAudit::query()->where('entity_type', AdministrativeEntityType::User)->where('entity_uuid', $target->uuid)->latest('id')->paginate(20, ['*'], 'activity_page');
    }
}
