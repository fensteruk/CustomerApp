# Customer Portal controlled production deployment — 21 August 2026

## Outcome

**Succeeded.** The controlled migration-repair release deployed to production and the
live site remained healthy. No rollback or database restoration was required.

## Pre-deployment recovery verification

The previous verified recovery point remained intact. Immediately before deployment, a
new owner-only backup was created and verified:

- `/home/forge/backups/customerapp/customerapp-predeploy-2026-08-21-123242Z.sql.gz`
- gzip integrity passed;
- SHA-256 sidecar verification passed;
- both files have restrictive `0600` permissions.

No credentials or backup contents are recorded here.

## Release and deployment record

| Item | Value |
|---|---|
| Live commit before | `eea83fb239365ce045ae8678cc506a358fba2cb8` (`v0.8`) |
| Approved release branch | `release/production-migration-repair-2026-08-21` |
| Rehearsed repair code | `1fe5293a62b2458a7edd4afbf3e2d55026a6870d` |
| Merged/pushed main commit | `f801c91113bd13656c6cbffdd3d82c12d4a95846` |
| Forge deployment | `75938097` |
| Forge result | Deployed, 36 seconds |
| Active release after | `/home/forge/fenstercustomer.on-forge.com/releases/75938097` |

Sprint 3E was excluded from the merged history.

## Deployment and migration result

Forge completed Composer installation, npm installation, Vite build, Laravel optimise,
storage link, `migrate --force`, release activation and deployment completion. The log
records successful execution of:

- `2026_08_05_000002_create_call_off_domain_tables`;
- `2026_08_07_000003_create_portal_notifications_table`;
- `2026_08_19_000004_add_call_off_browsing_indexes`;
- `2026_08_20_000005_add_target_call_off_domain_foundation`;
- `2026_08_20_000006_remove_synthetic_legacy_date_proposals`;
- `2026_08_20_000007_add_source_projection_import_contract`;
- `2026_08_21_000008_add_multi_call_off_request_fields`.

`migrate:status` afterwards reported every migration in the approved release as ran.
The migration ledger has 11 rows and exactly one 000002 row.

## Schema checks

Read-only production checks confirmed:

- `call_off_batch_operation_items` has 2 foreign keys;
- `call_off_status_histories` has 4 foreign keys;
- named indexes exist for portal-notification read state, projected-service completion,
  source-projection service time and source-projection request time;
- no expected migration remains pending.

## Health, logs and queues

- Current symlink HEAD is `f801c91113bd13656c6cbffdd3d82c12d4a95846`.
- HTTPS login returned 200; deployed CSS and JavaScript assets returned 200.
- No authenticated workflow was run and no customer data was changed.
- Forge reports no configured background process. The deployed queue default is `sync`,
  so queue restart has no persistent worker to restart.
- The shared Laravel log had no new post-deployment entry. Its matching SQL error entry
  was the historical failed release `75874307`, not the new active release.

## Production changes made

The approved Sprint 3D plus migration-repair release was merged to `main`, deployed once
by Forge, and applied the forward migrations above. Laravel deployment caches and the
new release assets were generated as part of the standard deployment script. No manual
DDL, migration-ledger edit, destructive database operation, seeder, production test or
Forge-setting change was performed.

## Remaining risks and next action

The deployment resolves the migration reconciliation incident. Separate follow-up items
remain: no persistent queue worker is configured, no authenticated customer workflow was
performed during deployment, physical-device/assistive-technology evidence remains
outstanding, and full npm audit retains development-tooling advisories. Do not deploy
Sprint 3E until it has completed its dedicated QA and release process.
