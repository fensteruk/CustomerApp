# Forge production migration verification — 21 August 2026

## Outcome

**Production requires remediation before further work.**

No production migration, cache operation, maintenance-mode change, queue action,
seed, test, source activation, or customer-facing submission was performed.

## Scope and evidence reviewed

The production verification followed the project boundary and deployment guidance in
`AGENTS.md`, `brief.md`, `ROADMAP.md`, `context-work-prompt.md`, `current_sprint.md`,
`HANDOVER.md`, `documentation/target-domain-v3.md`,
`documentation/source-integration-contract.md`, and the Sprint 3A–3D QA/report
documents available in this working tree.

The Forge site investigated was `fenstercustomer.on-forge.com` on the CustomerApp
server (site ID 3346456), using the `fensteruk/CustomerApp` repository and `main`
branch. Forge reports zero-downtime deployments. The server is MySQL 8.4 and the
site PHP runtime is 8.5; the terminal reported PHP 8.5.9.

## Deployment state

* The active release is commit `eea83fb239365ce045ae8678cc506a358fba2cb8` (`v0.8`).
* Commit `2fbcb5b9aced545ac377ce632eda903babd123c5` (Sprint 3A) was built but its
  push deployment failed on 20 August 2026 at 15:38 UTC. It was therefore not made
  the active release.
* The failed deployment ran the configured build, cache warm-up and normal migration
  step before it failed. The failure was not retried during this verification.
* The active release's working tree reported storage-related Git deltas. This was
  not changed or cleaned because the deployed release and shared storage layout must
  be understood before any corrective deployment.

## Migration blocker

The failed deployment stopped at
`2026_08_05_000002_create_call_off_domain_tables` while creating
`projected_plots`. MySQL reported that the `projected_plots` table already exists.

Read-only `php artisan migrate:status --no-ansi` then showed the same migration, as
well as `2026_08_07_000003_create_portal_notifications_table` and
`2026_08_19_000004_add_call_off_browsing_indexes`, as pending. This is direct
evidence that the schema and Laravel migration ledger are not reconciled. Running
`php artisan migrate --force` again would deterministically encounter the same
unsafe failure, so it was deliberately not run.

## Safe preflight observations

* `php artisan env` reported `production` and `php artisan config:show app.debug`
  reported `false`.
* `php artisan about` identified MySQL, file cache, database sessions and a `sync`
  queue. Forge shows no configured background process and no scheduled job.
* `php artisan db:show --counts --no-ansi` confirmed the configured MySQL connection
  and 18 tables. No credentials are recorded here.
* The HTTPS site responded normally and displayed the Customer Portal sign-in page;
  no 500 response or public asset failure was observed. No account was used and no
  real customer action was attempted.
* No credible backup/recovery point was established before the blocker was found.
  No backup was created or altered.

## Recovery-point follow-up

On 21 August 2026 the Forge database configuration was inspected again before starting
any reconciliation. Forge explicitly reports that database backups require a Business
Plan upgrade; no automated database backup, successful backup timestamp, snapshot or
restore path was available in the current Forge configuration.

No independent infrastructure recovery point was supplied or verified during this task.
Accordingly, the production reconciliation was stopped before any database write or
further schema/ledger investigation. The safe next action is to establish and verify a
recoverable database backup or snapshot, then repeat the read-only reconciliation
preflight.

## Not performed after the stop condition

The following checks require a reconciled migration baseline and were not performed:

* a forward migration, post-migration status, cache refresh or maintenance mode;
* data-integrity queries, detailed table/index compatibility evidence, and the
  Sprint 3 source-projection/four-service schema checks;
* queue or scheduler execution, production tests, seeders, source synchronisation,
  or authenticated workflow checks.

## Required remediation before another deployment attempt

1. Create and verify a recoverable production database backup or other approved
   recovery point.
2. Compare the live `projected_plots` DDL and indexes with migration
   `2026_08_05_000002_create_call_off_domain_tables` and establish how the table was
   created. Also reconcile the migration table against all portal tables created by
   that migration.
3. Prepare a reviewed, forward-only recovery plan in source control. Do not drop,
   truncate, recreate, or manually mark migrations as run until the schema equivalence
   and recovery plan have been independently verified on MySQL.
4. Prove the corrected deployment and migration sequence against a MySQL-compatible
   disposable environment, then deploy a clean committed release.
5. Re-run the full production preflight before any `php artisan migrate --force`.
