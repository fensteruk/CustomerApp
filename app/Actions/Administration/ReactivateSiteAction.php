<?php

namespace App\Actions\Administration;

use App\Domain\Administration\AdministrationRules;
use App\Domain\Administration\AdministrativeSnapshots;
use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReactivateSiteAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(User $actor, CustomerOrganisation $customer, Site $site, string $reason, int $expectedVersion): Site
    {
        $reason = $this->rules->reason($reason);

        return DB::transaction(function () use ($actor, $customer, $site, $reason, $expectedVersion): Site {
            $freshActor = $this->policy->authorize($actor, 'site_reactivate', true);
            $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $lockedSite = Site::query()
                ->whereKey($site->getKey())
                ->where('customer_organisation_id', $lockedCustomer->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->rules->expectedVersion($expectedVersion, (int) $lockedSite->lock_version);

            if ($lockedSite->is_active) {
                throw ValidationException::withMessages(['site' => 'This site is already active.']);
            }

            $before = $this->snapshots->site($lockedSite, $lockedCustomer);
            $lockedSite->is_active = true;
            $lockedSite->lock_version++;
            $lockedSite->save();

            $this->recordAudit->handle(
                $freshActor,
                AdministrativeEntityType::Site,
                $lockedSite->uuid,
                AdministrativeAction::Reactivated,
                $before,
                $this->snapshots->site($lockedSite, $lockedCustomer),
                $reason,
            );

            return $lockedSite->setRelation('customerOrganisation', $lockedCustomer);
        });
    }
}
