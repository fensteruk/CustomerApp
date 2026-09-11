<?php

namespace App\Actions\Administration;

use App\Domain\Administration\AdministrationRules;
use App\Domain\Administration\AdministrativeSnapshots;
use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCustomerAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(User $actor, string $name): CustomerOrganisation
    {
        $name = $this->rules->name($name);

        try {
            return DB::transaction(function () use ($actor, $name): CustomerOrganisation {
                $freshActor = $this->policy->authorize($actor, 'customer_create', true);

                if (CustomerOrganisation::query()->whereRaw('LOWER(name) = LOWER(?)', [$name])->exists()) {
                    throw ValidationException::withMessages(['name' => 'A customer with this name already exists.']);
                }

                $customer = new CustomerOrganisation;
                $customer->name = $name;
                $customer->is_active = true;
                $customer->lock_version = 1;
                $customer->save();

                $this->recordAudit->handle(
                    $freshActor,
                    AdministrativeEntityType::CustomerOrganisation,
                    $customer->uuid,
                    AdministrativeAction::Created,
                    null,
                    $this->snapshots->customer($customer),
                );

                return $customer;
            });
        } catch (QueryException $exception) {
            if ($this->rules->isCustomerNameConflict($exception)) {
                throw ValidationException::withMessages(['name' => 'A customer with this name already exists.']);
            }

            throw $exception;
        }
    }
}
