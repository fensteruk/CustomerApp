<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** One immutable active binding, inside the caller's transaction. */
final class CreateExactSourceBinding
{
    public function handle(User $actor, object $upload, object $stream, array $source, object $site, string $reason, string $outcome): object
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('exact_binding_requires_transaction');
        }
        $identityHash = Canonical::hash([$stream->source_namespace, $source['kind'], $source['identity']]);
        DB::table('wald_source_bindings')->insertOrIgnore([
            'uuid' => (string) Str::uuid(), 'identity_hash' => $identityHash,
            'source_namespace' => $stream->source_namespace, 'identity_kind' => $source['kind'],
            'source_identity' => $source['identity'], 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $root = DB::table('wald_source_bindings')->where('identity_hash', $identityHash)->lockForUpdate()->firstOrFail();
        if ($root->latest_version !== 0 || $root->active_version !== null
            || $root->source_identity !== $source['identity'] || $root->identity_kind !== $source['kind']
            || $root->source_namespace !== $stream->source_namespace) {
            throw new ImportConflict('source_site_binding_changed');
        }
        $definition = ['identity_hash' => $identityHash, 'version' => 1,
            'organisation' => $site->customer_organisation_id, 'site' => $site->id];
        $digest = Canonical::hash($definition);
        DB::table('wald_binding_versions')->insert([
            'uuid' => (string) Str::uuid(), 'binding_id' => $root->id, 'version' => 1,
            'customer_organisation_id' => $site->customer_organisation_id, 'site_id' => $site->id,
            'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'reason' => $reason, 'definition_hash' => $digest, 'created_at' => now('UTC'),
        ]);
        DB::table('wald_source_bindings')->where('id', $root->id)->update([
            'latest_version' => 1, 'active_version' => 1, 'epoch' => 1, 'updated_at' => now('UTC'),
        ]);
        (new PilotImportAudit)->record($actor, $upload->id, 'pilot_exact_binding_activated', [
            'source_customer_code' => $source['customer_code'],
            'parsed_customer' => $source['hierarchy']['customer'], 'parsed_site' => $source['hierarchy']['site'],
            'matched_customer_id' => $site->customer_organisation_id, 'matched_site_id' => $site->id,
            'resolution_reason' => $outcome, 'binding' => $root->uuid,
            'definition_hash' => $digest,
        ]);

        return DB::table('wald_source_bindings')->where('id', $root->id)->firstOrFail();
    }
}
