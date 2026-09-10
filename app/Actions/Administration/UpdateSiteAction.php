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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateSiteAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(
        User $actor,
        CustomerOrganisation $customer,
        Site $site,
        string $name,
        ?string $location,
        int $expectedVersion,
    ): Site {
        $name = $this->rules->name($name);
        $location = $this->rules->location($location);

        try {
            return DB::transaction(function () use ($actor, $customer, $site, $name, $location, $expectedVersion): Site {
                $freshActor = $this->policy->authorize($actor, 'site_update', true);
                $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
                $lockedSite = Site::query()
                    ->whereKey($site->getKey())
                    ->where('customer_organisation_id', $lockedCustomer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->rules->expectedVersion($expectedVersion, (int) $lockedSite->lock_version);

                if (Site::query()
                    ->where('customer_organisation_id', $lockedCustomer->getKey())
                    ->whereKeyNot($lockedSite->getKey())
                    ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                    ->exists()) {
                    throw ValidationException::withMessages(['name' => 'A site with this name already exists for this customer.']);
                }

                $before = $this->snapshots->site($lockedSite, $lockedCustomer);
                $lockedSite->name = $name;
                $lockedSite->location = $location;
                $lockedSite->lock_version++;
                $lockedSite->save();

                $this->recordAudit->handle(
                    $freshActor,
                    AdministrativeEntityType::Site,
                    $lockedSite->uuid,
                    AdministrativeAction::Updated,
                    $before,
                    $this->snapshots->site($lockedSite, $lockedCustomer),
                );

                return $lockedSite->setRelation('customerOrganisation', $lockedCustomer);
            });
        } catch (QueryException $exception) {
            if ($this->rules->isSiteNameConflict($exception)) {
                throw ValidationException::withMessages(['name' => 'A site with this name already exists for this customer.']);
            }

            throw $exception;
        }
    }
}
