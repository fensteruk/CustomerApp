# Production Sprint 3A migration reconciliation — 21 August 2026

> **Superseded by verified evidence later on 21 August 2026.** The backup and
> disposable-MySQL rehearsal described in
> `production-migration-000002-repair-rehearsal-2026-08-21.md` establish the
> physical partial state and a forward-only repair candidate. This historical
> record is retained because its original stop decision was correct at the time.

## Overall result

**Blocked before remediation. No production database changes were made.**

## Recovery point

Forge database configuration was inspected on 21 August 2026. Forge reports that
database backups are unavailable on the current plan and require a Business Plan
upgrade. No successful automated backup, timestamped snapshot, restore procedure or
independent recovery point was available for verification.

The required recovery point therefore does not exist as verified evidence. The recovery
procedure requires a stop at this point; no schema, migration-ledger or data changes are
safe to make without it.

## Known production state from the previous verification

* Active release: `eea83fb239365ce045ae8678cc506a358fba2cb8` (`v0.8`).
* Failed release: `2fbcb5b9aced545ac377ce632eda903babd123c5` (Sprint 3A).
* Involved migration: `2026_08_05_000002_create_call_off_domain_tables`.
* Laravel reports that migration as pending, together with later portal migrations.
* MySQL already contains `projected_plots`; the failed deployment stopped when the
  pending migration attempted to create that table.
* The active live site serves HTTPS and its login page. The environment is production
  with debug disabled.

## Partial-execution map and root cause

The migration's physical execution state has **not** been mapped. The observed
`projected_plots` table demonstrates schema/ledger disagreement, but does not prove
whether the remainder of the migration was applied, whether the table matches the
expected definition, or when it was created. No inference beyond the original MySQL
`table already exists` failure is safe without a recovery point.

This remains an unclassified reconciliation incident. It must not be treated as a
missing migration-ledger row or repaired by marking the migration complete.

## Production changes made

None. Specifically, no backup, migration, ledger update, DDL, data query that exposes
records, cache change, queue operation, test, seed, deployment or source import was run.

## Required next action

1. Establish a recoverable backup or snapshot that covers the `forge` database and
   verify its completion time and restoration method.
2. Re-run the read-only schema and migration-ledger inspection, including every operation
   in the pending migration and all later migration artefacts.
3. Classify the physical state, rehearse a forward-only repair on compatible MySQL where
   possible, and review the repair before making any production change.
4. Only then decide whether the Sprint 3A release can be deployed. Do not deploy the
   current uncommitted Sprint 3D working tree.

## MySQL lesson

Laravel records a migration only after its `up()` method completes. MySQL DDL can leave
physical schema changes outside a transaction that Laravel can roll back. Migration design
and release rehearsal must therefore account for a table existing while its migration
remains absent from the ledger.

## Risks and limitations

The data, indexes, constraints, foreign keys, row counts and full partial-execution map
were intentionally not inspected in this run because no recovery point was available.
No claim is made that the existing table is valid, empty or safe to remove.

## Verified reconciliation update — later 21 August 2026

A verified manual backup is now available at
`/home/forge/backups/customerapp/customerapp-production-2026-08-21-094607Z.sql.gz`.
Its SHA-256 sidecar and gzip integrity check passed. The isolated restore confirmed
the production ledger has the three Laravel base migrations and 000001 only; 000002
is pending.

The restore has five 000002 tables (`projected_plots`, `call_off_batches`,
`call_off_requests`, `call_off_batch_operations` and
`call_off_batch_operation_items`) with zero rows. `call_off_status_histories` and
`portal_notifications` are absent. The operation-items table has **zero foreign
keys**: its generated MySQL foreign-key identifier exceeds MySQL's 64-character
limit, leaving a physical table without its intended relationships.

Candidate branch `migration-000002-repair-candidate` makes 000002 safely resumable,
uses short explicit operation-item foreign-key names, and adds those two missing
restrict foreign keys when reconciling an existing table. It also names three other
MySQL-overlong pending indexes in 000003, 000005 and 000007. The candidate was
rehearsed successfully from a restored production copy, an empty MySQL database and
a normal pre-000002 upgrade. No live schema, data, migration ledger, Forge setting or
deployment was changed. The detailed runbook and recovery conditions are in the
rehearsal report.
