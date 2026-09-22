<?php

namespace App\Actions\Administration;

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\Services\PermanentDeletionImpact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PermanentlyDeleteCustomerOrSiteAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly PermanentDeletionImpact $impacts,
        private readonly RecordAdministrativeAuditAction $audit,
    ) {}

    public function site(User $actor, CustomerOrganisation $customer, Site $site, string $fingerprint): void
    {
        DB::transaction(function () use ($actor, $customer, $site, $fingerprint): void {
            $freshActor = $this->policy->authorize($actor, 'site_delete', true);
            $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $lockedSite = Site::query()->whereKey($site->getKey())->where('customer_organisation_id', $lockedCustomer->getKey())->lockForUpdate()->firstOrFail();
            $impact = $this->impacts->site($lockedSite);
            $this->ensureEligible($impact, $fingerprint);
            $this->deleteSite($lockedSite);
            $this->audit->handle($freshActor, AdministrativeEntityType::Site, $lockedSite->uuid, AdministrativeAction::PermanentlyDeleted,
                ['name' => $lockedSite->name, 'customer_uuid' => $lockedCustomer->uuid, 'removed' => $impact['counts']], null);
        }, 3);
    }

    public function customer(User $actor, CustomerOrganisation $customer, string $fingerprint): void
    {
        DB::transaction(function () use ($actor, $customer, $fingerprint): void {
            $freshActor = $this->policy->authorize($actor, 'customer_delete', true);
            $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            Site::query()->where('customer_organisation_id', $lockedCustomer->getKey())->orderBy('id')->lockForUpdate()->get();
            $impact = $this->impacts->customer($lockedCustomer);
            $this->ensureEligible($impact, $fingerprint);
            foreach (Site::query()->where('customer_organisation_id', $lockedCustomer->getKey())->orderBy('id')->get() as $site) {
                $this->deleteSite($site);
                $this->audit->handle($freshActor, AdministrativeEntityType::Site, $site->uuid, AdministrativeAction::PermanentlyDeleted,
                    ['name' => $site->name, 'customer_uuid' => $lockedCustomer->uuid, 'removed' => collect($impact['sites'])->firstWhere('uuid', $site->uuid)['counts']], null);
            }
            $lockedCustomer->delete();
            $this->audit->handle($freshActor, AdministrativeEntityType::CustomerOrganisation, $lockedCustomer->uuid, AdministrativeAction::PermanentlyDeleted,
                ['name' => $lockedCustomer->name, 'removed' => $impact['counts']], null);
        }, 3);
    }

    private function ensureEligible(array $impact, string $fingerprint): void
    {
        if (! hash_equals($impact['fingerprint'], $fingerprint)) {
            throw ValidationException::withMessages(['confirmation' => 'The record changed. Review the deletion impact again.']);
        }
        if ($impact['blockers'] !== []) {
            throw ValidationException::withMessages(['confirmation' => 'This record has retained business or source history. Deactivate it instead.']);
        }
    }

    private function deleteSite(Site $site): void
    {
        $plots = DB::table('projected_plots')->where('site_id', $site->getKey())->pluck('id');
        DB::table('site_user_assignments')->where('site_id', $site->getKey())->delete();
        DB::table('projected_plot_products')->whereIn('projected_plot_id', $plots)->delete();
        DB::table('projected_plot_services')->whereIn('projected_plot_id', $plots)->delete();
        DB::table('projected_plots')->where('site_id', $site->getKey())->delete();
        $site->delete();
    }
}
