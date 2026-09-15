<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Office-controlled exact identity, separate from reusable structural knowledge. */
final class SourceBindingService
{
    public function draft(User $actor, KnowledgeScope $scope, string $kind, string $identity, string $reason, string $command, ?int $expectedEpoch = null): array
    {
        $this->validateIdentity($kind, $identity);
        $this->reason($reason);
        $key = Canonical::hash([$scope->namespace, $kind, $identity]);

        return (new ImportStore)->run($actor, $scope, 'binding_draft', $command, [$kind, $identity, $reason, $expectedEpoch], function (User $fresh) use ($scope, $kind, $identity, $reason, $expectedEpoch, $key): array {
            DB::table('wald_source_bindings')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'identity_hash' => $key,
                'source_namespace' => $scope->namespace, 'identity_kind' => $kind, 'source_identity' => $identity,
                'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $root = DB::table('wald_source_bindings')->where('identity_hash', $key)->lockForUpdate()->firstOrFail();
            if ($root->source_identity !== $identity || $root->source_namespace !== $scope->namespace || $root->identity_kind !== $kind) {
                throw new ImportConflict('binding_identity_conflict');
            }
            if ($root->latest_version > 0) {
                $this->scopedVersion($root, (int) $root->latest_version, $scope);
                if ($expectedEpoch !== (int) $root->epoch) {
                    throw new ImportConflict('binding_epoch_conflict');
                }
            } elseif ($expectedEpoch !== null && $expectedEpoch !== 0) {
                throw new ImportConflict('binding_epoch_conflict');
            }
            $version = (int) $root->latest_version + 1;
            $definition = ['identity_hash' => $key, 'version' => $version, 'organisation' => $scope->organisationId, 'site' => $scope->siteId];
            $digest = Canonical::hash($definition);
            DB::table('wald_binding_versions')->insert(['uuid' => (string) Str::uuid(), 'binding_id' => $root->id,
                'version' => $version, 'customer_organisation_id' => $scope->organisationId, 'site_id' => $scope->siteId,
                'actor_id' => $fresh->id, 'actor_name' => $fresh->name, 'reason' => $reason,
                'definition_hash' => $digest, 'created_at' => now('UTC')]);
            DB::table('wald_source_bindings')->where('id', $root->id)->update(['latest_version' => $version, 'epoch' => $root->epoch + 1, 'updated_at' => now('UTC')]);
            $result = ['binding' => $root->uuid, 'version' => $version, 'definition_hash' => $digest, 'epoch' => (int) $root->epoch + 1, 'state' => 'DRAFT'];

            return [$result, ['active_version' => $root->active_version, 'latest_version' => $root->latest_version, 'epoch' => $root->epoch], $result];
        });
    }

    public function activate(User $actor, KnowledgeScope $scope, string $binding, int $version, string $hash, int $epoch, string $reason, string $command): array
    {
        $this->reason($reason);

        return (new ImportStore)->run($actor, $scope, 'binding_activate', $command, [$binding, $version, $hash, $epoch, $reason], function () use ($scope, $binding, $version, $hash, $epoch, $reason): array {
            $root = $this->root($scope, $binding);
            $record = $this->scopedVersion($root, $version, $scope);
            if ((int) $root->epoch !== $epoch || (int) $root->latest_version !== $version || (int) $root->active_version === $version || $record->definition_hash !== $hash) {
                throw new ImportConflict('binding_epoch_or_version_conflict');
            }
            // A revoked version cannot be resurrected; create a new immutable draft.
            if ($version <= (int) $root->revoked_through) {
                throw new ImportConflict('binding_version_revoked');
            }
            DB::table('wald_source_bindings')->where('id', $root->id)->update(['active_version' => $version, 'epoch' => $epoch + 1, 'updated_at' => now('UTC')]);
            $result = ['binding' => $binding, 'version' => $version, 'definition_hash' => $hash, 'epoch' => $epoch + 1, 'state' => 'ACTIVE'];

            return [$result, ['active_version' => $root->active_version, 'epoch' => $epoch], [...$result, 'superseded_version' => $root->active_version, 'reason' => $reason]];
        });
    }

    public function revoke(User $actor, KnowledgeScope $scope, string $binding, int $epoch, string $reason, string $command): array
    {
        $this->reason($reason);

        return (new ImportStore)->run($actor, $scope, 'binding_revoke', $command, [$binding, $epoch, $reason], function () use ($scope, $binding, $epoch, $reason): array {
            $root = $this->root($scope, $binding);
            if ((int) $root->epoch !== $epoch || $root->active_version === null) {
                throw new ImportConflict('binding_not_active_or_stale');
            }
            $this->scopedVersion($root, (int) $root->active_version, $scope);
            DB::table('wald_source_bindings')->where('id', $root->id)->update(['active_version' => null, 'revoked_through' => $root->active_version, 'epoch' => $epoch + 1, 'updated_at' => now('UTC')]);
            $result = ['binding' => $binding, 'version' => (int) $root->active_version, 'epoch' => $epoch + 1, 'state' => 'REVOKED'];

            return [$result, ['active_version' => $root->active_version, 'epoch' => $epoch], [...$result, 'reason' => $reason]];
        });
    }

    public function resolve(User $actor, KnowledgeScope $scope, string $kind, string $identity, bool $lock = false): array
    {
        if ($lock && DB::transactionLevel() === 0) {
            throw new \LogicException('binding_lock_requires_transaction');
        }
        (new ImportPolicy)->authorize($actor, $scope, 'binding_audit', $lock);
        $this->validateIdentity($kind, $identity);
        $query = DB::table('wald_source_bindings')->where('identity_hash', Canonical::hash([$scope->namespace, $kind, $identity]));
        $root = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $root || $root->active_version === null) {
            throw new ImportConflict('source_site_binding_required');
        }
        $version = $this->scopedVersion($root, (int) $root->active_version, $scope);

        return ['binding' => $root->uuid, 'definition_hash' => $version->definition_hash, 'epoch' => (int) $root->epoch, 'state' => 'ACTIVE', 'version' => (int) $root->active_version];
    }

    public function history(User $actor, KnowledgeScope $scope, string $binding, int $afterId = 0, int $limit = 50): array
    {
        (new ImportPolicy)->authorize($actor, $scope, 'binding_audit');
        $root = DB::table('wald_source_bindings')->where('uuid', $binding)->where('source_namespace', $scope->namespace)->firstOrFail();
        $this->scopedVersion($root, (int) $root->latest_version, $scope);

        return DB::table('wald_import_commands')->where('subject_uuid', $binding)->where('id', '>', max(0, $afterId))
            ->orderBy('id')->limit(max(1, min(100, $limit)))->get()->map(fn ($r) => ['id' => $r->id,
                'action' => $r->ability, 'actor_id' => $r->actor_id, 'actor_name' => $r->actor_name,
                'at' => $r->created_at, 'before' => json_decode($r->before_state, true, flags: JSON_THROW_ON_ERROR),
                'after' => json_decode($r->after_state, true, flags: JSON_THROW_ON_ERROR)])->all();
    }

    /** For the future commit transaction; this alone grants no projection-write authority. */
    public function assertCurrent(User $actor, KnowledgeScope $scope, string $kind, string $identity, array $pin): void
    {
        if (Canonical::hash($this->resolve($actor, $scope, $kind, $identity, true)) !== Canonical::hash($pin)) {
            throw new ImportConflict('stale_source_binding');
        }
    }

    private function root(KnowledgeScope $scope, string $uuid): object
    {
        return DB::table('wald_source_bindings')->where('uuid', $uuid)->where('source_namespace', $scope->namespace)->lockForUpdate()->firstOrFail();
    }

    private function scopedVersion(object $root, int $number, KnowledgeScope $scope): object
    {
        $version = DB::table('wald_binding_versions')->where('binding_id', $root->id)->where('version', $number)
            ->where('customer_organisation_id', $scope->organisationId)->where('site_id', $scope->siteId)->first();
        if (! $version) {
            throw new ImportConflict('binding_scope_conflict');
        }

        return $version;
    }

    private function reason(string $reason): void
    {
        if (trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw new \InvalidArgumentException('binding_reason_required');
        }
    }

    private function validateIdentity(string $kind, string $identity): void
    {
        if (! in_array($kind, ['CUSTOMER_CODE', 'SOURCE_SITE_ID', 'EXACT_SITE_NAME'], true) || trim($identity) === '' || mb_strlen($identity) > 512 || preg_match('/[\x00-\x1f\x7f]/', $identity)) {
            throw new \InvalidArgumentException('invalid_source_site_identity');
        }
    }
}
