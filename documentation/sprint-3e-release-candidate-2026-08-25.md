# Sprint 3E Release Candidate

## Overall Result

**BLOCKED.** The validated Sprint 3E and QA corrections were transplanted cleanly onto current `main`, and all available local release checks passed. The mandatory disposable MySQL migration/index/locking/concurrency gate could not be run because no safe MySQL target exists in this environment.

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

**Not run — blocking gate.** PHP has the MySQL driver, but this environment has no MySQL client, Docker runtime, MySQL/MariaDB service, configured host, database or credentials. No unknown or production database was contacted. Consequently clean install, normal upgrade, constraint inspection and the seven requested race scenarios have no new MySQL evidence.

## Tests

- Full release suite: **196 tests, 1,017 assertions passed**.
- Sprint 3E + Sprint 3D + source + notifications: **59 tests, 358 assertions passed**.
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

Do not merge this exact branch to `main` and do not deploy. Supply a named, isolated disposable MySQL 8.x target, rerun the complete MySQL gate, then make a separate merge/deployment decision.

Sprint 3E release blocked — MySQL/reconciliation gate incomplete
