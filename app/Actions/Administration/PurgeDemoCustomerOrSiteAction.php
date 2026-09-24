<?php

namespace App\Actions\Administration;

use App\Enums\AdministrativeAction;
use App\Enums\AdministrativeEntityType;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\Services\DemoPurgeImpact;
use App\SourceImport\Integration\PrivateWorkbookStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class PurgeDemoCustomerOrSiteAction
{
    public function __construct(
        private readonly OfficeAdministrationPolicy $policy,
        private readonly DemoPurgeImpact $impacts,
        private readonly RecordAdministrativeAuditAction $audit,
    ) {}

    public function site(User $actor, CustomerOrganisation $customer, Site $site, string $fingerprint): void
    {
        DB::transaction(function () use ($actor, $customer, $site, $fingerprint): void {
            $freshActor = $this->policy->authorize($actor, 'demo_purge', true);
            $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $lockedSite = Site::query()->whereKey($site->getKey())->where('customer_organisation_id', $lockedCustomer->getKey())->lockForUpdate()->firstOrFail();
            $this->lockImportRoots([$lockedSite->getKey()]);
            $impact = $this->impacts->site($lockedSite);
            $this->ensureEligible($impact, $fingerprint);
            $this->scheduleUnreferencedWorkbooks($impact['ids']['wald_import_runs']);
            $this->openGate($freshActor, $lockedSite->uuid);
            $this->purgeSite($lockedSite, $impact, [$lockedSite->getKey()]);
            $this->closeGate();
            $this->recordSiteAudit($freshActor, $lockedCustomer, $lockedSite, $impact);
        }, 3);
    }

    public function customer(User $actor, CustomerOrganisation $customer, string $fingerprint,
        bool $includePortalHistory = false): void
    {
        DB::transaction(function () use ($actor, $customer, $fingerprint, $includePortalHistory): void {
            $freshActor = $this->policy->authorize($actor, 'demo_purge', true);
            $lockedCustomer = CustomerOrganisation::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $sites = Site::query()->where('customer_organisation_id', $lockedCustomer->getKey())->orderBy('id')->lockForUpdate()->get();
            $users = User::query()->where('customer_organisation_id', $lockedCustomer->getKey())
                ->orderBy('id')->lockForUpdate()->get();
            $this->lockImportRoots($sites->pluck('id')->all());
            $impact = $this->impacts->customer($lockedCustomer, $includePortalHistory);
            $this->ensureEligible($impact, $fingerprint);
            $this->scheduleUnreferencedWorkbooks(array_merge(...array_map(fn (array $unit): array => $unit['ids']['wald_import_runs'], $impact['sites'])));
            $this->openGate($freshActor, $lockedCustomer->uuid);
            foreach ($sites as $site) {
                $unit = collect($impact['sites'])->firstWhere('uuid', $site->uuid);
                $this->purgeSite($site, $unit, $sites->pluck('id')->all(), $includePortalHistory);
                $this->recordSiteAudit($freshActor, $lockedCustomer, $site, $unit);
            }
            if ($includePortalHistory) {
                foreach ($users as $user) {
                    $before = ['customer_uuid' => $lockedCustomer->uuid, 'is_active' => $user->is_active];
                    $user->forceFill(['customer_organisation_id' => null, 'is_active' => false])->save();
                    $this->audit->handle($freshActor, AdministrativeEntityType::User, $user->uuid,
                        AdministrativeAction::CustomerChanged, $before,
                        ['customer_uuid' => null, 'is_active' => false], 'Demo customer and Portal history purge');
                }
            }
            $lockedCustomer->delete();
            $this->closeGate();
            $this->audit->handle($freshActor, AdministrativeEntityType::CustomerOrganisation, $lockedCustomer->uuid,
                AdministrativeAction::DemoTestPurged, ['name' => $lockedCustomer->name, 'reason' => 'DEMO_TEST_PURGE', 'removed' => $impact['counts']], null);
        }, 3);
    }

    private function lockImportRoots(array $siteIds): void
    {
        $bindingIds = DB::table('wald_binding_versions')->whereIn('site_id', $siteIds)
            ->distinct()->pluck('binding_id')->all();
        DB::table('wald_source_bindings')->whereIn('id', $bindingIds)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('wald_import_runs')->whereIn('site_id', $siteIds)->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('wald_binding_versions')->whereIn('site_id', $siteIds)->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('wald_pilot_selections')->whereIn('site_id', $siteIds)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function ensureEligible(array $impact, string $fingerprint): void
    {
        if (! hash_equals($impact['fingerprint'], $fingerprint)) {
            throw ValidationException::withMessages(['confirmation' => 'The demo purge impact changed. Review it again.']);
        }
        if ($impact['blockers'] !== []) {
            throw ValidationException::withMessages(['confirmation' => 'The demo purge has protected or shared dependencies. Review the blockers.']);
        }
    }

    private function openGate(User $actor, string $entityUuid): void
    {
        DB::table('demo_purge_gate')->insert(['id' => 1, 'actor_id' => $actor->getKey(), 'entity_uuid' => $entityUuid, 'created_at' => now('UTC')]);
    }

    private function closeGate(): void
    {
        DB::table('demo_purge_gate')->where('id', 1)->delete();
    }

    private function purgeSite(Site $site, array $impact, array $scopeSiteIds,
        bool $includePortalHistory = false): void
    {
        $ids = $impact['ids'];

        foreach (['source_projection_events', 'source_projection_issues', 'wald_commit_attempt_outcomes',
            'wald_commit_attempts', 'wald_import_receipts', 'wald_staged_rows', 'wald_import_previews',
            'wald_import_stages', 'wald_visit_observations', 'wald_source_row_observations',
            'wald_pilot_events'] as $table) {
            $this->deleteIds($table, $ids[$table]);
        }
        DB::table('wald_pilot_selections')->whereIn('id', $ids['wald_pilot_selections'])->update(['run_id' => null]);
        $this->deleteIds('wald_import_runs', $ids['wald_import_runs']);
        $this->deleteIds('wald_pilot_selections', $ids['wald_pilot_selections']);
        $this->deleteIds('wald_source_visits', $ids['wald_source_visits']);
        $this->deleteIds('wald_source_rows', $ids['wald_source_rows']);

        $this->deleteIds('wald_knowledge_events', $ids['wald_knowledge_events']);
        $this->deleteIds('wald_profile_uses', $ids['wald_profile_uses']);
        DB::table('wald_profiles')->whereIn('id', $ids['wald_profiles'])->update(['active_version' => null]);
        $this->deleteIds('wald_profile_versions', $ids['wald_profile_versions']);
        $this->deleteIds('wald_profiles', $ids['wald_profiles']);
        $this->deleteIds('wald_clarification_answers', $ids['wald_clarification_answers']);
        $this->deleteIds('wald_clarifications', $ids['wald_clarifications']);
        $this->deleteIds('wald_knowledge_evidence', $ids['wald_knowledge_evidence']);
        $this->deleteIds('wald_knowledge_contexts', $ids['wald_knowledge_contexts']);

        $sharedRoots = DB::table('wald_binding_versions')->whereIn('binding_id', $ids['wald_source_bindings'])
            ->whereNotIn('site_id', $scopeSiteIds)->distinct()->pluck('binding_id')->all();
        DB::table('wald_source_bindings')->whereIn('id', array_diff($ids['wald_source_bindings'], $sharedRoots))
            ->update(['active_version' => null]);
        $this->deleteIds('wald_binding_versions', $ids['wald_binding_versions']);
        foreach ($ids['wald_source_bindings'] as $bindingId) {
            if (! DB::table('wald_binding_versions')->where('binding_id', $bindingId)->exists()) {
                DB::table('wald_source_bindings')->where('id', $bindingId)->delete();
            }
        }
        if ($includePortalHistory) {
            foreach (['portal_notifications', 'call_off_batch_operation_items', 'call_off_status_histories',
                'call_off_date_proposals', 'call_off_date_negotiations', 'call_off_batch_operations',
                'call_off_requests', 'call_off_batches'] as $table) {
                $this->deleteIds($table, $ids[$table]);
            }
        }
        $this->deleteIds('site_user_assignments', $ids['site_user_assignments']);
        $this->deleteIds('projected_plot_products', $ids['projected_plot_products']);
        $this->deleteIds('projected_plot_services', $ids['projected_plot_services']);
        $this->deleteIds('projected_plots', $ids['projected_plots']);
        $site->delete();
    }

    private function deleteIds(string $table, array $ids): void
    {
        foreach (array_reverse($ids) as $id) {
            DB::table($table)->where('id', $id)->delete();
        }
    }

    private function recordSiteAudit(User $actor, CustomerOrganisation $customer, Site $site, array $impact): void
    {
        $this->audit->handle($actor, AdministrativeEntityType::Site, $site->uuid, AdministrativeAction::DemoTestPurged,
            ['name' => $site->name, 'customer_uuid' => $customer->uuid, 'reason' => 'DEMO_TEST_PURGE', 'removed' => $impact['counts']], null);
    }

    private function scheduleUnreferencedWorkbooks(array $runIds): void
    {
        $keys = DB::table('wald_import_runs')->whereIn('id', $runIds)->distinct()->pluck('storage_key')->all();
        DB::afterCommit(static function () use ($keys): void {
            foreach ($keys as $key) {
                try {
                    (new PrivateWorkbookStorage)->discardUnregistered($key);
                } catch (\Throwable $exception) {
                    Log::warning('Demo purge retained a private workbook after the database commit.', ['error_type' => $exception::class]);
                }
            }
        });
    }
}
