<?php

namespace App\Actions\Administration;

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\AdministrativeAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordAdministrativeAuditAction
{
    public function handle(
        User $actor,
        AdministrativeEntityType $entityType,
        string $entityUuid,
        AdministrativeAction $action,
        ?array $before,
        ?array $after,
        ?string $reason = null,
    ): AdministrativeAudit {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Administrative audit must be recorded in the domain transaction.');
        }

        return AdministrativeAudit::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'actor_role' => (string) $actor->portalRole?->identifier,
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'action' => $action,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
            'occurred_at' => now('UTC'),
        ]);
    }
}
