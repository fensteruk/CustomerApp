<?php

namespace App\Actions\Administration;

use App\Domain\Administration\AdministrationRules;
use App\Domain\Administration\AdministrativeSnapshots;
use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReactivateCustomerAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(User $actor, CustomerOrganisation $customer, string $reason, int $expectedVersion): CustomerOrganisation
    {
        $reason = $this->rules->reason($reason);

        return DB::transaction(function () use ($actor, $customer, $reason, $expectedVersion): CustomerOrganisation {
            $freshActor = $this->policy->authorize($actor, 'customer_reactivate', true);
            $locked = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $this->rules->expectedVersion($expectedVersion, (int) $locked->lock_version);

            if ($locked->is_active) {
                throw ValidationException::withMessages(['customer' => 'This customer is already active.']);
            }

            $before = $this->snapshots->customer($locked);
            $locked->is_active = true;
            $locked->lock_version++;
            $locked->save();

            $this->recordAudit->handle(
                $freshActor,
                AdministrativeEntityType::CustomerOrganisation,
                $locked->uuid,
                AdministrativeAction::Reactivated,
                $before,
                $this->snapshots->customer($locked),
                $reason,
            );

            return $locked;
        });
    }
}
