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

final class RenameCustomerAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly AdministrationRules $rules,
        private readonly AdministrativeSnapshots $snapshots,
        private readonly RecordAdministrativeAuditAction $recordAudit,
    ) {}

    public function handle(User $actor, CustomerOrganisation $customer, string $name, int $expectedVersion): CustomerOrganisation
    {
        $name = $this->rules->name($name);

        try {
            return DB::transaction(function () use ($actor, $customer, $name, $expectedVersion): CustomerOrganisation {
                $freshActor = $this->policy->authorize($actor, 'customer_update', true);
                $locked = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
                $this->rules->expectedVersion($expectedVersion, (int) $locked->lock_version);

                if (CustomerOrganisation::query()
                    ->whereKeyNot($locked->getKey())
                    ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                    ->exists()) {
                    throw ValidationException::withMessages(['name' => 'A customer with this name already exists.']);
                }

                $before = $this->snapshots->customer($locked);
                $locked->name = $name;
                $locked->lock_version++;
                $locked->save();

                $this->recordAudit->handle(
                    $freshActor,
                    AdministrativeEntityType::CustomerOrganisation,
                    $locked->uuid,
                    AdministrativeAction::Renamed,
                    $before,
                    $this->snapshots->customer($locked),
                );

                return $locked;
            });
        } catch (QueryException $exception) {
            if ($this->rules->isCustomerNameConflict($exception)) {
                throw ValidationException::withMessages(['name' => 'A customer with this name already exists.']);
            }

            throw $exception;
        }
    }
}
