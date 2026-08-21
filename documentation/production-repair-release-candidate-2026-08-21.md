# Production repair release candidate — 21 August 2026

## Classification

**Ready for explicit production deployment approval.** This document records a release
candidate only. It does not authorise a merge to `main`, a Forge deployment or a
production database change.

## Deliberate application baseline

The release is based on the latest evidenced dedicated QA checkpoint for Sprint 3D:

- baseline: `8853b1592b7b287d378b77f7674bb34c9949ccec`
  (`docs: record Sprint 3D QA checkpoint`);
- QA correction: `34531d2`;
- Sprint 3D implementation checkpoint: `10be06f`.

Sprint 3E is **excluded**. Its implementation commit `47e8ccc` is newer but the Sprint
3E report records it as ready for dedicated QA, not as having passed that gate. Passing
development checks do not substitute for that approval.

## Release branch and contents

- branch: `release/production-migration-repair-2026-08-21`;
- release code SHA: `1fe5293a62b2458a7edd4afbf3e2d55026a6870d`.

The branch may contain documentation-only commits after this code SHA. The code SHA above
is the exact migration/application content that was rehearsed and requires approval.

The branch is the Sprint 3D baseline plus only these cherry-picked repair commits:

| Release commit | Source repair commit | Purpose |
|---|---|---|
| `d5f5592` | `75ce989` | Resume partial 000002 safely |
| `007fca3` | `bee8941` | Name the portal-notification read-state index |
| `4509527` | `8d3d090` | Name the projected-service completion index |
| `31387f9` | `d088387` | Name source-projection event indexes |
| `1fe5293` | `c942ea0` | Reconcile missing operation-item foreign keys |

The release changes only the four rehearsed migration files:

- `2026_08_05_000002_create_call_off_domain_tables.php`
- `2026_08_07_000003_create_portal_notifications_table.php`
- `2026_08_20_000005_add_target_call_off_domain_foundation.php`
- `2026_08_20_000007_add_source_projection_import_contract.php`

The resulting migration semantics match the successful MySQL rehearsal: 000002 preserves
the known correct physical tables, adds the two missing restrict foreign keys to
`call_off_batch_operation_items`, creates the missing history table and only then lets
Laravel record the migration. The later named indexes remain below MySQL's identifier
limit.

## Release verification

| Check | Result |
|---|---|
| SQLite `migrate:fresh --seed` | passed |
| SQLite full rollback/reapply script | passed |
| Pest | 171 tests, 884 assertions passed |
| Pint and whitespace check | passed |
| Composer validation | passed |
| Composer audit | no advisories |
| Production asset build | passed |
| Production npm audit | no advisories |
| Full npm audit | 1 high and 1 moderate development-tooling advisory; no dependency change made |
| Laravel cache compatibility | `optimize:clear`, config cache, route cache, view cache and route list passed; caches cleared afterwards |

## Final MySQL proof

The exact pushed release SHA was checked out on Forge only in a temporary rehearsal
directory and run only against isolated MySQL 8.4.10 databases named
`customerapp_releaseproof_*`.

| Scenario | Result |
|---|---|
| Restored verified production partial state | 000002 reconciled, then 000003–000008 completed |
| Clean empty MySQL database | all migrations completed |
| Normal base-plus-000001 upgrade | ordinary forward migration completed |
| Ledger/schema assertion | 11 migrations, exactly one 000002 row, operation-items 2 FKs, histories 4 FKs |
| Named-index assertion | all required named indexes from 000003, 000005 and 000007 present |
| Migration status | no pending migration; command exit status 0 |

No live `forge` database tables, rows, migration records or settings were changed.

## Forge deployment compatibility

The release remains compatible with the configured Forge script:

```text
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci || npm install
npm run build
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force
$ACTIVATE_RELEASE()
$RESTART_QUEUES()
```

Composer, npm/Vite, Laravel optimisation/cache compatibility and the exact MySQL migration
sequence have all been checked. `storage:link`, release activation and queue restart remain
production deployment observations, not actions performed during this preparation.

## Deployment strategy and explicit stop conditions

Forge watches `main` and Quick Deploy is enabled. Neither setting was changed.

After explicit deployment approval, use one controlled release action: merge this reviewed
release branch into `main` and observe the resulting Quick Deploy from start to finish.
Do not merge unapproved Sprint 3E work. Do not push unrelated commits to `main` in the same
operation. Treat the `main` update as the deployment trigger rather than changing Quick
Deploy settings immediately before the repair.

Before that action:

1. Verify the manual backup still exists and passes SHA-256/gzip checks.
2. Confirm the live release is still `eea83fb` unless a documented change exists.
3. Check HTTPS/login health and the production migration ledger read-only.
4. Take a fresh verified pre-change backup/snapshot.

STOP without improvising if any preflight differs, the backup fails, the live health check
fails, the deployment reports a migration error, 000002 is not recorded exactly once, a
required FK/index is absent, release activation is not reached, queue restart fails, or
the post-deploy login/dashboard is unhealthy. Do not edit the migration ledger, drop
tables or use `migrate:rollback` as recovery.

## Work intentionally excluded

- Sprint 3E date-negotiation backend/UI and its unapproved documentation/tests;
- every unrelated application or UI change;
- production credentials, backups and temporary database content;
- Forge configuration changes;
- production deployment and database reconciliation.

## Exact next production action

Obtain explicit deployment approval for release SHA
`1fe5293a62b2458a7edd4afbf3e2d55026a6870d`, then perform the documented preflight and
one observed `main` merge/Quick Deploy. Until that approval, leave `main` untouched.
