<?php

namespace App\Actions\Administration;

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManagePortalUserAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly RecordAdministrativeAuditAction $audit,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input): User
    {
        return DB::transaction(function () use ($actor, $input): User {
            $freshActor = $this->policy->authorize($actor, 'user_create', true);
            [$role, $customer, $sites] = $this->resolveScope($input);

            $user = User::query()->create([
                'name' => trim((string) $input['name']),
                'email' => strtolower(trim((string) $input['email'])),
                'password' => $input['password'],
                'portal_role_id' => $role->id,
                'customer_organisation_id' => $customer?->id,
                'is_active' => (bool) ($input['is_active'] ?? true),
                'is_preview_user' => false,
            ]);
            $user->assignedSites()->sync($sites->pluck('id')->all());
            $user->load(['portalRole', 'customerOrganisation', 'assignedSites.customerOrganisation']);

            $after = $this->snapshot($user);
            $this->audit->handle($freshActor, AdministrativeEntityType::User, $user->uuid, AdministrativeAction::Created, null, $after);
            foreach ($sites as $site) {
                $this->recordSiteChange($freshActor, $user, $site, AdministrativeAction::SiteAssigned);
            }

            return $user;
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function update(User $actor, User $target, array $input): User
    {
        return DB::transaction(function () use ($actor, $target, $input): User {
            $freshActor = $this->policy->authorize($actor, 'user_update', true);
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $this->assertVersion($target, $input);
            $target->load(['portalRole', 'customerOrganisation', 'assignedSites.customerOrganisation']);
            $before = $this->snapshot($target);
            $oldSiteIds = $target->assignedSites->pluck('id')->all();
            $oldRole = $target->portalRole?->identifier;
            $oldCustomerId = $target->customer_organisation_id;

            [$role, $customer, $sites] = $this->resolveScope($input);
            if ($role->identifier === PortalRoleIdentifier::FensterOfficeStaff->value
                && $oldRole === PortalRoleIdentifier::FensterOfficeStaff->value) {
                $customer = $target->customerOrganisation;
                $sites = $target->assignedSites;
            }
            if ($oldCustomerId !== null && $oldCustomerId !== $customer?->id && ! filter_var($input['confirm_customer_change'] ?? false, FILTER_VALIDATE_BOOL)) {
                throw ValidationException::withMessages([
                    'confirm_customer_change' => "Confirm that changing this user's customer will replace their current site access.",
                ]);
            }

            $target->forceFill([
                'name' => trim((string) $input['name']),
                'email' => strtolower(trim((string) $input['email'])),
                'portal_role_id' => $role->id,
                'customer_organisation_id' => $customer?->id,
                'password' => filled($input['password'] ?? null) ? $input['password'] : $target->password,
                'lock_version' => $target->lock_version + 1,
            ])->save();
            $target->assignedSites()->sync($sites->pluck('id')->all());
            $target->load(['portalRole', 'customerOrganisation', 'assignedSites.customerOrganisation']);
            $after = $this->snapshot($target);

            if ($oldRole !== $target->portalRole?->identifier) {
                $this->audit->handle($freshActor, AdministrativeEntityType::User, $target->uuid, AdministrativeAction::RoleChanged, $before, $after);
            }
            if ($oldCustomerId !== $target->customer_organisation_id) {
                $this->audit->handle($freshActor, AdministrativeEntityType::User, $target->uuid, AdministrativeAction::CustomerChanged, $before, $after);
            }

            $newSiteIds = $sites->pluck('id')->all();
            foreach (array_diff($newSiteIds, $oldSiteIds) as $siteId) {
                $this->recordSiteChange($freshActor, $target, $sites->firstWhere('id', $siteId), AdministrativeAction::SiteAssigned);
            }
            foreach (array_diff($oldSiteIds, $newSiteIds) as $siteId) {
                $this->recordSiteChange($freshActor, $target, Site::query()->findOrFail($siteId), AdministrativeAction::SiteRemoved);
            }

            if (Arr::except($before, ['role', 'customer', 'assigned_sites', 'lock_version']) !== Arr::except($after, ['role', 'customer', 'assigned_sites', 'lock_version'])
                || filled($input['password'] ?? null)) {
                $auditBefore = $before;
                $auditAfter = $after;
                if (filled($input['password'] ?? null)) {
                    $auditBefore['password_changed'] = false;
                    $auditAfter['password_changed'] = true;
                }
                $this->audit->handle($freshActor, AdministrativeEntityType::User, $target->uuid, AdministrativeAction::Updated, $auditBefore, $auditAfter);
            }

            return $target;
        }, 3);
    }

    public function setActive(User $actor, User $target, bool $active, int $lockVersion): User
    {
        return DB::transaction(function () use ($actor, $target, $active, $lockVersion): User {
            $freshActor = $this->policy->authorize($actor, $active ? 'user_reactivate' : 'user_deactivate', true);
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $this->assertVersion($target, ['lock_version' => $lockVersion]);
            $target->load(['portalRole', 'customerOrganisation', 'assignedSites.customerOrganisation']);
            $before = $this->snapshot($target);
            $target->forceFill(['is_active' => $active, 'lock_version' => $target->lock_version + 1])->save();
            $target->load(['portalRole', 'customerOrganisation', 'assignedSites.customerOrganisation']);
            $this->audit->handle(
                $freshActor,
                AdministrativeEntityType::User,
                $target->uuid,
                $active ? AdministrativeAction::Reactivated : AdministrativeAction::Deactivated,
                $before,
                $this->snapshot($target),
            );

            return $target;
        }, 3);
    }

    public function assign(User $actor, Site $site, User $target): void
    {
        DB::transaction(function () use ($actor, $site, $target): void {
            $freshActor = $this->policy->authorize($actor, 'user_assign_sites', true);
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $site = Site::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
            $target->load('portalRole');
            $this->assertAssignable($target, $site);
            if (! $target->assignedSites()->whereKey($site->id)->exists()) {
                $target->assignedSites()->attach($site->id);
                $target->increment('lock_version');
                $this->recordSiteChange($freshActor, $target, $site, AdministrativeAction::SiteAssigned);
            }
        }, 3);
    }

    public function remove(User $actor, Site $site, User $target): void
    {
        DB::transaction(function () use ($actor, $site, $target): void {
            $freshActor = $this->policy->authorize($actor, 'user_assign_sites', true);
            $target = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $site = Site::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $target->customer_organisation_id !== (int) $site->customer_organisation_id
                || ! $target->assignedSites()->whereKey($site->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'That user does not currently have access to this site.']);
            }
            $target->assignedSites()->detach($site->id);
            $target->increment('lock_version');
            $this->recordSiteChange($freshActor, $target, $site, AdministrativeAction::SiteRemoved);
        }, 3);
    }

    /** @param array<string, mixed> $input */
    private function resolveScope(array $input): array
    {
        $role = PortalRole::query()->where('identifier', $input['role'])->sharedLock()->firstOrFail();
        $identifier = PortalRoleIdentifier::from($role->identifier);
        $siteIds = array_values(array_unique(array_map('intval', $input['site_ids'] ?? [])));

        if ($identifier === PortalRoleIdentifier::FensterOfficeStaff) {
            return [$role, null, collect()];
        }

        $customer = CustomerOrganisation::query()->whereKey($input['customer_organisation_id'] ?? null)->sharedLock()->first();
        if (! $customer) {
            throw ValidationException::withMessages(['customer_organisation_id' => 'Select a customer for this external user.']);
        }
        $sites = Site::query()->whereIn('id', $siteIds)->lockForUpdate()->get();
        if ($sites->count() !== count($siteIds) || $sites->contains(fn (Site $site): bool => (int) $site->customer_organisation_id !== (int) $customer->id)) {
            throw ValidationException::withMessages(['site_ids' => 'Every assigned site must belong to the selected customer.']);
        }

        return [$role, $customer, $sites];
    }

    private function assertAssignable(User $target, Site $site): void
    {
        if (! $target->isSiteRole() || (int) $target->customer_organisation_id !== (int) $site->customer_organisation_id) {
            throw ValidationException::withMessages(['user' => 'Only external users from this customer can be assigned to this site.']);
        }
    }

    /** @param array<string, mixed> $input */
    private function assertVersion(User $target, array $input): void
    {
        if ((int) ($input['lock_version'] ?? 0) !== (int) $target->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'This user changed while you were editing. Reload and try again.']);
        }
    }

    private function recordSiteChange(User $actor, User $target, Site $site, AdministrativeAction $action): void
    {
        $state = ['site' => ['uuid' => $site->uuid, 'name' => $site->name]];
        $this->audit->handle($actor, AdministrativeEntityType::User, $target->uuid, $action, $action === AdministrativeAction::SiteRemoved ? $state : null, $action === AdministrativeAction::SiteAssigned ? $state : null);
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->portalRole ? ['identifier' => $user->portalRole->identifier, 'name' => $user->portalRole->name] : null,
            'customer' => $user->customerOrganisation ? ['uuid' => $user->customerOrganisation->uuid, 'name' => $user->customerOrganisation->name] : null,
            'assigned_sites' => $user->assignedSites->sortBy('name')->map(fn (Site $site): array => ['uuid' => $site->uuid, 'name' => $site->name])->values()->all(),
            'is_active' => (bool) $user->is_active,
            'lock_version' => (int) $user->lock_version,
        ];
    }
}
