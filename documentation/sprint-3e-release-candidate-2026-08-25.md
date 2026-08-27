# Sprint 3E Release Candidate

## Overall Result

**BLOCKED — concurrency remediation in progress.** The disposable GitHub-hosted MySQL 8.4 gate subsequently reproduced a source-completion-versus-customer-acceptance deadlock twice. Production and Forge were not contacted. A non-deploying release-branch remediation is aligning source and portal transaction locks before the gate is rerun.

## Baselines

- `main`: `20d5f123906b7c88f1264f7df5ff90be02c15ec2`
- QA source: `codex/qa-sprint-3e-date-negotiation` at `a38d557c5a38b41d064b57a3cb9c4e2494a34fd3`
- Sprint 3E implementation source: `47e8ccc2f260e38696787bee773d8b7700d74dd7`

## Release Branch

`release/sprint-3e-date-negotiation-2026-08-25`, created directly from current `main`. Final local/remote SHA is recorded after the release-documentation commit and push.

## Transplant

The release branch cherry-picked only the two validated changesets:

1. `47e8ccc` — Sprint 3E application, UI, tests and supporting documentation.
2. `a38d557` — QAE-01, QAE-02, QAE-03, hostile tests and dedicated QA report.

No old Sprint 3A–3D feature history was replayed and the old QA branch was not merged.

## Conflicts

Only `HANDOVER.md`, `ROADMAP.md` and `current_sprint.md` conflicted. They were resolved in favour of current `main`'s production-recovery facts; release status is updated by this document and the operational-document update. No application or migration conflict occurred.

## QA Fix Preservation

- **QAE-01:** one truthful `Awaiting Site User → Date Agreed` transition; alternative acceptance remains a distinct event.
- **QAE-02:** agreement/acceptance revalidate present source data immediately before persistence.
- **QAE-03:** unavailable source data suppresses stale customer accept/reject controls; server validation remains authoritative.

## Production Migration Protection

The following release-branch blobs exactly match `main` and were not modified by either transplant:

- `2026_08_05_000002_create_call_off_domain_tables.php` — `6b21361be77eb821db40333c7b161905a8cb2e0a6`
- `2026_08_07_000003_create_portal_notifications_table.php` — `c00327aa9398cb44f9c24cda5af987e61ed1cc0a`
- `2026_08_20_000005_add_target_call_off_domain_foundation.php` — `7f5f2ce2c9f44747a72b6910832c5a43ec3da0a6`
- `2026_08_20_000007_add_source_projection_import_contract.php` — `144929c371e513f9e69de332ad3d6d943cceba8d`

Sprint 3E introduces **no new migration, column, index, foreign key, unique constraint or migration timestamp**. Its old branch has stale versions of these four historical migrations, but the commit-level transplant does not touch them.

## Functional / Authorization / Negotiation Verification

The reconstructed branch passed the dedicated hostile coverage for normal and early agreement, proposal/accept/reject loops, repeat/replay/stale handling, withdrawals, legacy presentation, source completion/availability, notification scope and direct-route permissions. The three active site roles, global Office Staff, unassigned/cross-site/cross-customer, inactive and unauthenticated paths remain covered.

## SQLite

Confirmed `APP_ENV=local` and `DB_CONNECTION=sqlite`. `php artisan migrate:fresh --seed` completed successfully; `migrate:status` showed all 11 migrations as ran.

## MySQL Clean Install / Upgrade / Concurrency

The GitHub-hosted disposable MySQL 8.4 workflow later supplied the required isolated target. Its clean migration and main-to-Sprint-3E upgrade job passed, but the concurrency job reproduced two release blockers:

- Source completion racing alternative acceptance deadlocked (`SQLSTATE[40001]`, MySQL 1213) while the source path locked the projected service and the portal path locked the request first.
- Source availability removal racing a date decision demonstrated that the assertion/evidence path needed durable state capture, and confirmed the availability check must occur after authoritative aggregate locks.

The first re-gate confirmed the state-race correction but found a narrow notification gap:
alternative acceptance committed a truthful Date Agreed history before later completion,
yet emitted only the Office-facing alternative-accepted event and no Date Agreed event for
the authorised submitting site user. The local correction emits the existing after-commit
`CallOffDateAgreed` event for committed alternative acceptance. It uses the established
recipient checks and idempotency key, does not notify for stale/rolled-back acceptance or
source completion, and does not alter locking, precedence or source retry.

The gate is not yet passed. The remediation adds a single source-first aggregate-lock action, source-side negotiation/proposal locks in the same order, post-lock source revalidation, bounded source-record retry for MySQL 1205/1213/40001 only, durable race snapshots, and the narrow committed-event notification correction. It must be rerun against the disposable database before any merge or deployment decision.

## Tests

- Full local release suite after the notification correction: **197 passed, 15 skipped, 1,019 assertions**.
- Focused date-negotiation regression after the notification correction: **10 tests, 44 assertions passed**.
- Fresh SQLite Sprint 3E hostile suite is included in the full result.

## Composer / npm / Build / Pint

- Composer validation and audit passed; no Composer advisories.
- `npm ci` completed. Production npm audit (`--omit=dev`) found no vulnerabilities; full audit reports 11 development-toolchain advisories (9 moderate, 2 high), with no automatic fix applied.
- Pint and `git diff --check` passed.
- Vite production build passed after the known sandbox-only Windows `spawn EPERM` limitation was rerun with approved local permissions.
- Laravel cache compatibility passed: optimize clear, config cache, route cache, view cache and route list; caches were cleared afterwards.

## UI / Responsive

Local preview regression at 320px, 768px and 1440px found no document overflow (305px, 753px and 1425px respectively), retained the skip link and labelled controls. Dedicated UI tests cover Office/customer negotiation controls, mandatory rejection reason, private-context exclusion and final history order. Physical-device and screen-reader verification remain limitations.

## Delta From Main

Sprint 3E adds portal-specific requested-date agreement, alternatives, controlled customer response, date-negotiation history, customer-safe request detail, notifications and associated tests. It adds no SiteApp operational workflow, amendments/Sprint 3F work, dependencies or database migration.

## Remaining Risks

- Disposable MySQL migration/index/concurrency proof is mandatory and incomplete.
- Holiday data ownership remains unresolved.
- Physical-device and assistive-technology testing remain outstanding.
- Full npm audit has development-only advisories; no automatic dependency change was made.

## Release Recommendation

Do not merge this branch to `main` and do not deploy. Commit the non-deploying notification correction only after review, then run the existing disposable MySQL 8.4 gate with repeated real-process acceptance-first/completion-first races, the remaining source races and an auditable full suite. A separate release decision follows only if that gate passes.

Sprint 3E release blocked — MySQL concurrency remediation incomplete
