<?php

namespace App\SourceImport\Integration;

use App\Actions\Administration\CreateCustomerAction;
use App\Actions\Administration\CreateSiteAction;
use App\Models\CustomerOrganisation;
use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Office approval of one current source-backed customer/site proposal. */
final class ApproveMasterHierarchyProposal
{
    public function __construct(
        private readonly CreateCustomerAction $createCustomer,
        private readonly CreateSiteAction $createSite,
        private readonly CreateExactSourceBinding $createBinding,
    ) {}

    public function handle(User $actor, string $uploadUuid, string $sourceHash, string $manifestHash,
        int $expectedEpoch, string $expectedOutcome, string $expectedCustomer, string $expectedSite, string $command): array
    {
        (new PilotImportPolicy)->authorize($actor);
        if (! Str::isUuid($command) || ! in_array($expectedOutcome, ['EXACT_CUSTOMER_NEW_SITE', 'NEW_CUSTOMER_AND_SITE'], true)) {
            throw new ImportConflict('invalid_hierarchy_proposal');
        }

        try {
            return DB::transaction(function () use ($actor, $uploadUuid, $sourceHash, $manifestHash, $expectedEpoch, $expectedOutcome, $expectedCustomer, $expectedSite, $command): array {
                $candidate = DB::table('wald_pilot_uploads')->where('uuid', $uploadUuid)->firstOrFail();
                $stream = DB::table('wald_import_streams')->where('id', $candidate->stream_id)->lockForUpdate()->firstOrFail();
                $upload = DB::table('wald_pilot_uploads')->where('id', $candidate->id)->lockForUpdate()->firstOrFail();
                $fresh = (new PilotImportPolicy)->authorize($actor, true);

                $prior = DB::table('wald_pilot_events')->where('actor_id', $fresh->id)
                    ->where('command_uuid', $command)->first();
                if ($prior) {
                    if ($prior->action !== 'pilot_hierarchy_creation_approved'
                        || (int) $prior->pilot_upload_id !== (int) $upload->id) {
                        throw new ImportConflict('proposal_command_conflict');
                    }
                    $payload = json_decode($prior->payload, true, flags: JSON_THROW_ON_ERROR);
                    if (($payload['source_identity_hash'] ?? null) !== $sourceHash) {
                        throw new ImportConflict('proposal_command_conflict');
                    }

                    return $payload;
                }

                if (! in_array($upload->state, ['READY', 'IN_PROGRESS'], true)
                    || (int) $upload->epoch !== $expectedEpoch
                    || ! hash_equals((string) $upload->source_manifest_hash, $manifestHash)
                    || (int) DB::table('wald_pilot_uploads')->where('stream_id', $upload->stream_id)
                        ->where('export_order', $upload->export_order)->max('revision') !== (int) $upload->revision) {
                    throw new ImportConflict('proposal_stale_refresh');
                }
                $manifest = json_decode($upload->source_manifest, true, flags: JSON_THROW_ON_ERROR);
                if (($manifest['schema'] ?? null) !== PilotWorkbookDiscovery::SCHEMA
                    || ($manifest['knowledge_policy'] ?? null) !== KnowledgeIdentity::POLICY
                    || ($manifest['resolver_version'] ?? null) !== MasterSourceResolver::VERSION
                    || $upload->source_manifest_hash !== Canonical::hash($manifest)) {
                    throw new ImportConflict('proposal_stale_refresh');
                }
                $source = collect($manifest['sources'])->firstWhere('hash', $sourceHash);
                if (! $source || ($source['hierarchy_mode'] ?? null) !== 'COMPOSITE'
                    || ($source['customer_code'] ?? null) === null) {
                    throw new ImportConflict('proposal_not_in_upload');
                }
                if (DB::table('wald_pilot_selections')->where('pilot_upload_id', $upload->id)
                    ->where('source_identity_hash', $sourceHash)->exists()) {
                    throw new ImportConflict('proposal_already_selected');
                }
                $source = (new HierarchyClarifications)->resolve($source,
                    (new HierarchyClarifications)->all($upload->id)[$sourceHash] ?? []);
                $resolution = (new MasterSourceResolver)->resolve([$source], $stream->source_namespace)[$sourceHash];
                if ($resolution['state'] !== $expectedOutcome
                    || $resolution['customer'] !== $expectedCustomer
                    || $resolution['site'] !== $expectedSite) {
                    throw new ImportConflict('proposal_stale_refresh');
                }

                $customer = $expectedOutcome === 'NEW_CUSTOMER_AND_SITE'
                    ? $this->createCustomer->handle($fresh, $resolution['customer'])
                    : CustomerOrganisation::query()->whereKey($resolution['customer_id'])->lockForUpdate()->firstOrFail();
                if (! $customer->is_active || ! MasterSourceResolver::sameName($customer->name, $source['hierarchy']['customer'])) {
                    throw new ImportConflict('proposal_stale_refresh');
                }
                $site = $this->createSite->handle($fresh, $customer, $resolution['site'], null);
                $binding = $this->createBinding->handle($fresh, $upload, $stream, $source, $site,
                    'Office approved the exact source-backed customer and site proposal.', $expectedOutcome);
                $resulting = (new MasterSourceResolver)->resolve([$source], $stream->source_namespace)[$sourceHash];
                if ($resulting['state'] !== 'EXACT_EXISTING_BINDING') {
                    throw new ImportConflict('proposal_resolution_failed');
                }
                $event = [
                    'source_identity_hash' => $sourceHash,
                    'customer_code' => $source['customer_code'],
                    'parsed_customer' => $source['hierarchy']['customer'],
                    'parsed_site' => $source['hierarchy']['site'],
                    'original_outcome' => $expectedOutcome,
                    'customer_id' => $customer->id, 'customer_uuid' => $customer->uuid,
                    'customer_created' => $expectedOutcome === 'NEW_CUSTOMER_AND_SITE',
                    'site_id' => $site->id, 'site_uuid' => $site->uuid,
                    'site_created' => true,
                    'binding_id' => $binding->id, 'binding_uuid' => $binding->uuid,
                    'resulting_resolution' => $resulting['state'],
                    'revision' => (int) $upload->revision,
                    'upload_uuid' => $upload->uuid, 'source_namespace' => $stream->source_namespace,
                    'source_manifest_hash' => $manifestHash,
                    'analysis_hash' => $manifest['analysis_hash'],
                    'analysis_generation' => (int) $upload->epoch,
                    'analysis_stage' => 'UPLOAD_DISCOVERY',
                    'rows' => $resolution['rows'], 'plots' => $resolution['plot_count'],
                ];
                (new PilotImportAudit)->record($fresh, $upload->id, 'pilot_hierarchy_creation_approved', $event, command: $command);

                return $event;
            }, 3);
        } catch (ValidationException|QueryException $exception) {
            throw new ImportConflict('proposal_stale_refresh', previous: $exception);
        }
    }
}
