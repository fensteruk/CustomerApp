# Production migration 000002 repair rehearsal — 21 August 2026

## Scope and safety result

This is a completed **disposable-database rehearsal**, not a production repair.
No live database data, schema, migration ledger, cache, queue, Forge deployment or
Forge setting was changed.

The verified recovery source was:

- backup: `/home/forge/backups/customerapp/customerapp-production-2026-08-21-094607Z.sql.gz`;
- timestamp in filename: `2026-08-21-094607Z`;
- SHA-256 sidecar: passed with `sha256sum -c`;
- gzip integrity: passed with `gzip -t`.

The compatible rehearsal engine was Forge's isolated MySQL 8.4.10 instance. Every
rehearsal database was named `customerapp_rehearsal_000002_*`; none was `forge`.
Before every Artisan mutation, an in-process database override was set, the connection
was purged and its actual database name was asserted. This avoids relying on a shell
environment variable alone.

## Restored production state

The backup restore succeeded into multiple disposable databases. The restored state
matched the earlier read-only diagnosis:

| Evidence | Result |
|---|---|
| Migration ledger | base migrations ×3 and `2026_08_05_000001_create_secure_access_domain_tables` only |
| 000002 ledger row | absent / pending |
| Existing 000002 tables | `projected_plots`, `call_off_batches`, `call_off_requests`, `call_off_batch_operations`, `call_off_batch_operation_items` |
| First missing 000002 table | `call_off_status_histories` |
| Later table | `portal_notifications` absent |
| Data in existing 000002 tables | zero rows in every observed table |

The physical boundary is therefore after creation of `call_off_batch_operation_items`
and before `call_off_status_histories`. No gap with a later 000002 table present was
observed.

## Exact 000002 schema assessment

| Step | Intended object | Restored state | Repair action |
|---:|---|---|---|
| 1 | `projected_plots` with source identity, site FK, unique/indexes | present, compatible | preserve |
| 2 | `call_off_batches` with site/user FKs and browsing indexes | present, compatible | preserve |
| 3 | `call_off_requests` with batch/plot/self FKs, conflict unique and Trash indexes | present, compatible | preserve |
| 4 | `call_off_batch_operations` with batch/user/self FKs and Undo indexes | present, compatible | preserve |
| 5 | `call_off_batch_operation_items` with two restrict FKs, unique and request index | table present; zero rows; **both FKs missing** | add the two named restrict FKs |
| 6 | `call_off_status_histories` with four FKs, sequence unique and indexes | absent | create |

The first five table definitions matched a clean baseline apart from redundant explicit
`CHARACTER SET utf8mb4` text retained by the backup DDL. That difference normalised to
the same schema signature and does not change the effective `utf8mb4_unicode_ci`
behaviour. The initial comparison also exposed the missing operation-item FKs: a
generated MySQL constraint identifier was too long, so MySQL left the physical table
without either FK. The repair explicitly verifies and adds those relationships.

## Repair and ledger strategy

Strategy **B** was selected: retain migration identity 000002 and make it safely
resumable. This is the truthful route because Laravel still records 000002 only after
the complete `up()` method succeeds.

1. `createTableIfMissing()` preserves each physically complete existing table.
2. `call_off_batch_operation_items` uses short explicit FK names on new databases.
3. If that existing table is encountered, the migration inspects its foreign keys and
   adds only the missing two restrict relationships. A missing expected column throws;
   it is not silently accepted.
4. It creates the missing history table.
5. Laravel then inserts the 000002 row normally, only after all preceding work succeeds.
6. A normal `migrate --force` applies the later migrations.

No manual insert into `migrations` and no destructive schema operation is part of the
repair.

## Additional MySQL compatibility fixes

The rehearsal found three pending migration identifiers that exceed MySQL's 64-character
index-name limit. The candidate gives them explicit safe names:

| Migration | New named index |
|---|---|
| 000003 | `portal_notifications_user_read_state_index` |
| 000005 | `plot_services_service_completion_index` |
| 000007 | `source_projection_events_service_time_index`, `source_projection_events_request_time_index` |

These migrations are not recorded in production and their tables are absent there.
The modifications make clean and post-reconciliation MySQL migrations executable.

## Rehearsal results

Candidate head tested: `c942ea0d6c1fc2b9aa874515949417e45a1ca7b2`.

| Scenario | Result |
|---|---|
| Restored partial production copy | 000002 created history, restored the two missing operation-item FKs, then recorded 000002 |
| Subsequent migrations | 000003 through 000008 completed successfully |
| Ledger after completion | 11 migrations, including exactly one 000002 row |
| Final constraint check | operation-items has 2 FKs; status histories has 4 FKs |
| Idempotency | rerunning 000002 path then ordinary `migrate --force` reported `Nothing to migrate` twice |
| Empty MySQL install | all migrations completed successfully |
| Normal pre-000002 upgrade | base migrations plus 000001, followed by ordinary `migrate --force`, completed successfully |
| Application checks on repaired DB | `migrate:status`, `about` and `route:list --json` returned exit status 0 |
| Local SQLite regression | `migrate:fresh --seed`, full Pest suite (187 tests, 973 assertions), Pint and diff check passed |
| Local assets | `npm run build` passed |

The actual database unique indexes for active conflict keys, source Call No. and proposal
sequence were present. A dedicated parallel-writer race test was not run; MySQL database
constraints remain the enforced backstop and application concurrency remains a separate
release evidence item.

## Production runbook — only after explicit approval

1. **Confirm release authority and target commit.**
   - Expected: a signed-off commit based on an approved release baseline, containing the
     repair changes below.
   - STOP: do not merge or deploy `release-candidate/sprint-3d` merely because it is newer.
     Current evidence does not authorise its later work for production.
2. **Verify recovery point and live health.**
   - Re-run the backup SHA-256/gzip checks; verify login HTTPS response, current symlink,
     commit and migration ledger read-only.
   - STOP: checksum failure, unexpected served commit, unhealthy login, changed partial
     schema/data, or a missing recovery point.
3. **Take a fresh pre-change backup/snapshot.**
   - Expected: successful checksum and documented restore method.
   - STOP: no verified current backup. Do not start migration work.
4. **Optional maintenance mode.**
   - Use only under approved release authority to avoid concurrent writes while schema is
     reconciled. Keep a second authenticated verification path available.
   - STOP: do not proceed if maintenance cannot be entered or safely exited.
5. **Deploy the approved repair release through the normal Forge `main` workflow.**
   - The deployed code must contain the resumable 000002 and the three named-index fixes.
   - Expected: deployment reaches `php artisan migrate --force` without object-name or
     duplicate-table errors.
   - STOP: any migration error; do not edit the ledger, retry blindly, drop tables, or
     run rollback.
6. **Verify schema and ledger immediately.**
   - Expected: 000002 recorded exactly once; operation-items has its two restrict FKs;
     histories has four FKs; `migrate:status` has no pending migration in the approved set.
   - STOP: a missing FK/index/table, unexpected row change, or pending 000002.
7. **Verify release health.**
   - Expected: normal migration command reports nothing pending; login, route/application
     checks, logs and queue/process health are normal; exit maintenance mode if used.
   - STOP: customer-visible error, unsafe logs, queue failure or unexpected database error.

## Recovery and abort plan

Do not use `migrate:rollback` as a recovery mechanism. MySQL DDL can have already
persisted physical objects while Laravel has not recorded the migration.

If a production repair fails before 000002 records, stop traffic if necessary, preserve
the exact error and read-only state, and compare the physical tables/FKs with this report.
Do not manually add a migration row. Restore the verified fresh pre-change backup only
when the release owner confirms it is necessary to return to the known state and accepts
the loss window; otherwise retain the database for controlled diagnosis and rehearse the
new exact state before another attempt.

## Deployment recommendation and remaining blocker

Forge watches `main`; Quick Deploy remains enabled and was not changed. The candidate
branch is deliberately non-auto-deployed. Its head is
`c942ea0d6c1fc2b9aa874515949417e45a1ca7b2`, but it is based on the later release-candidate
line, not on currently deployed `main`.

There is no evidenced, approved production target commit after the repair. Do **not**
deploy the candidate unchanged. First choose the release baseline, obtain its QA/release
approval, and prepare a reviewable `main`-based repair release containing these migration
fixes. Then use the runbook above. This is a release-governance blocker, not a technical
rehearsal failure.
