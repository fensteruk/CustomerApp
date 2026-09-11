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

final class CreateSiteAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(User $actor, CustomerOrganisation $customer, string $name, ?string $location): Site
    {
        $name = $this->rules->name($name);
        $location = $this->rules->location($location);

        try {
            return DB::transaction(function () use ($actor, $customer, $name, $location): Site {
                $freshActor = $this->policy->authorize($actor, 'site_create', true);
                $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

                if (! $lockedCustomer->is_active) {
                    throw ValidationException::withMessages(['customer' => 'A site cannot be added to an inactive customer.']);
                }

                if (Site::query()
                    ->where('customer_organisation_id', $lockedCustomer->getKey())
                    ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                    ->exists()) {
                    throw ValidationException::withMessages(['name' => 'A site with this name already exists for this customer.']);
                }

                $site = new Site;
                $site->customer_organisation_id = $lockedCustomer->getKey();
                $site->name = $name;
                $site->location = $location;
                $site->is_active = true;
                $site->lock_version = 1;
                $site->save();

                $this->recordAudit->handle(
                    $freshActor,
                    AdministrativeEntityType::Site,
                    $site->uuid,
                    AdministrativeAction::Created,
                    null,
                    $this->snapshots->site($site, $lockedCustomer),
                );

                return $site->setRelation('customerOrganisation', $lockedCustomer);
            });
        } catch (QueryException $exception) {
            if ($this->rules->isSiteNameConflict($exception)) {
                throw ValidationException::withMessages(['name' => 'A site with this name already exists for this customer.']);
            }

            throw $exception;
        }
    }
}
