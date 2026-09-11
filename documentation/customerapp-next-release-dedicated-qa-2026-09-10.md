# CustomerApp Next Release Dedicated QA Report

Date: 10 September 2026

## Overall Result

**PASS.** The frozen RC was verified on an isolated, non-deploying QA branch. One P3 test-harness
reliability defect was corrected without changing production code. No release blocker remains.

## Candidate

- Frozen RC branch: `release/customerapp-next-release-admin-demo-rc1`
- Original frozen RC SHA: `2e58bedcb70c487dfee1ae9f01a087c7ed8117e6`
- Production base: `e757bb651f9aa95d67b808a97433d27bc29d03c3`
- QA branch: `qa/customerapp-next-release-admin-demo-2026-09-10`
- Corrected executable QA SHA: `10f0a56ac1987754ab0c31b45fc08138ba25e3f8`

The correction changes two test-harness files only. The application/runtime candidate remains
byte-for-byte the frozen RC.

## Scope Integrity

The complete production-base-to-RC diff was inspected. The candidate contains only the accepted
sidebar, Office customer/site administration, lifecycle, audit, read-only views, truthful
unavailable states and local/testing synthetic demo scope. No unrelated feature branch or
unapproved administration domain was found.

## Migration / Upgrade

Production base has 12 migrations and the RC has 13. The only addition is
`database/migrations/2026_09_09_000014_add_customer_site_administration.php`.

Independent review found the migration additive and non-destructive. It backfills customer/site
UUID, active state and optimistic version values; creates immutable administrative audit storage;
preserves existing ownership and dependent records; and adds no hard-delete route. A clean
MySQL 8.4.11 install applied all 13 migrations. A disposable production-shaped 12-to-13 upgrade
passed all 17 current preservation checks, including users, assignments, plots, projected
services, request graphs, source references and Sprint 3F amendment history.

## Customer Admin

Office list, bounded pagination, search, create, view, rename, deactivate and reactivate passed.
Browser QA confirmed whitespace normalisation and an accessible duplicate-name error. Automated
tests cover exact, case/whitespace-normalised and inactive-customer duplicates. There is no hard
delete.

## Site Admin

Customer-to-site list, create, details, edit, deactivate and reactivate passed. Source reference
is optional. Same-customer normalised duplicates are blocked while the current cross-customer
contract is preserved. Parent/child containment remains enforced and no automatic binding occurs.

## Lifecycle

Customer and site deactivation retain sites, users, assignments, plots, requests, source
references, workflow history and audit. External access fails closed while Office retains
inspection. Reactivation restores access from existing relationships without recreating records.
Browser messages clearly explain these effects before confirmation.

## Plot Read-Only Safety

The Office inventory provides list, search, pagination and source-service inspection only. No Add,
Edit, Delete or bulk mutation control or route exists. Direct-action and hostile identifier tests
deny manual mutation. Sixty synthetic plots remained bounded to 20 items per page.

## Site Users

Assigned-user name, email, display role and active/inactive state render read-only. No assignment
editing control is present. Twenty-five synthetic users remained bounded to 20 items per page.

## Synthetic Demo Isolation

Local/testing requires the explicit demo flag. Production-mode route inventory contained zero
demo routes even with that flag forced true. A Chromium direct request to
`/development/import-studio` against a local server running in production mode returned 404.
Production navigation cannot render a demo link whose route does not exist.

## Synthetic Demo Journey

All 12 steps passed: example selection, export date, Morning/Afternoon, uploader, latest-export
confirmation, Wald analysis concept, detected site, binding concept, detected records,
clarifications, preview and disabled commit summary. `DEMO ONLY`, `SYNTHETIC DATA` and
`NO DATA WILL BE SAVED` remain explicit. The page has no file input or form, no enabled write or
submit control, no database persistence, binding mutation, real Wald call, commit endpoint,
external AI or network dependency.

## Sidebar Regression

UIQ-01 long references wrap safely; UIQ-02 the 320px header remains contained; UIQ-03 drawer and
notification Escape closure return focus to their own triggers. Desktop, tablet and mobile states
passed.

## Office Navigation

Production-safe Office navigation remains Review Requests, Customers and Notifications. Local
demo mode adds the explicitly labelled `Imports — demo only` entry. No dead production entry or
production demo-only navigation was found.

## Site User Navigation

Site Manager browser QA retained Plots & Call-Offs, Trash and Notifications with assigned-site
switching. Automated authorization/navigation tests cover Site Manager, Assistant Site Manager
and Finishing Foreman equivalently. None receives Office customer/site administration or demo
controls.

## Authorization

Server-side tests allow active, persisted Fenster Office Staff, including Office Staff with a null
customer organisation. Inactive Office Staff, stale role state, mismatched `portal_role_id`,
preview-only authority and all three external roles are denied. Authorization is repeated at the
persistence boundary.

## IDOR / Mass Assignment

Hostile customer, site, plot and audit identifiers fail closed. Forged customer owner, site owner,
lifecycle state, actor, audit data, source reference and deactivation metadata are rejected or
ignored. Cross-customer site binding is denied and server-owned values remain server-owned.

## Admin Audit

Customer and site create/edit/deactivate/reactivate produce attributed immutable records with
timestamps, before/after values and lifecycle reasons. Database triggers reject audit update and
delete. Forced audit failure rolls back the business mutation. Passwords and private source
evidence are not stored.

## Sprint 3F Regression

Amendment request, On Hold, Office acceptance/alternative, Site User response, source-completion
precedence, history and Date Agreed filtering passed. The dedicated queue/Sprint 3E/3F group
reported 128 tests passed with 1,299 assertions.

## Queue-Card Regression

Office queue cards continue to prefer request-level service and original requested date, using
legacy batch fallback only for null request values. No batch-level value regression was found.

## Filters

Site, plot, overall status, service, service status and Show Completed filters passed. Office
request-status, site, service and Date Agreed filters passed. Apply, clear and query persistence
remain covered by the green regression suite and labelled browser controls.

## Responsive

Sequential Chromium checks passed at 1920x1080, 1440x900, 1366x768, 1024x768, 768x1024,
390x844 and 320x568. Customer list, populated plot inventory, forms and all demo layouts were
checked at every size; lifecycle and site views were also exercised. Document, body and main
content widths stayed within the viewport, including deliberately long site/plot text.

## Accessibility

Keyboard and accessibility-tree smoke tests passed for landmarks, skip link, headings, form
labels, required fields, validation summary/field association, status text, disabled states,
drawer focus and notification-panel focus return. Lifecycle status is expressed in text rather
than colour alone. Native required-field validation moved focus to the missing field. This is a
targeted smoke review, not WCAG certification.

## Browser / Console

The in-app Chromium-family browser completed the end-to-end journey. Unexpected console warnings
or errors: 0. Failed Vite assets observed: 0. Unexpected application 4xx/5xx: 0; the sole 404 was
the required production-mode demo-isolation result. Firefox and standalone Edge were unavailable
and were not exercised.

## Performance

Synthetic data covered 38 customers, 110 sites, 60 plots and 25 assigned users. Bounded 20-item
read models used 4, 5, 7 and 5 queries respectively. The per-page query counts are constant because
counts/relationships are eager-loaded; no obvious N+1 pattern was found.

## Admin Concurrency

Five MySQL races passed with 30 assertions: two customer renames, edit versus deactivate,
deactivate versus reactivate, same-name site create and site edit versus deactivate. Outcomes were
explicit and internally consistent.

Sprint 3E ordering regression was repeated 10 times across four ordering cases: 40 passed with
640 assertions after the test harness was corrected to synchronise the first committed operation.

## SQLite

Full suite: **436 total — 393 passed, 43 intentional MySQL-only skips, 2,784 assertions**.

Focused admin/presentation/sidebar/demo: **64 passed, 440 assertions**. Browser-side admin/demo
JavaScript: **14 passed**.

## MySQL

Disposable MySQL Community Server 8.4.11 evidence:

- clean install: all 13 migrations passed;
- production-shaped upgrade: 17/17 preservation checks passed;
- admin-focused group: 64 total, 63 passed, 1 intentional MySQL-environment skip,
  433 assertions;
- authorization-focused group: 62 total, 61 passed, 1 intentional skip, 418 assertions;
- dedicated admin races: 5 passed, 30 assertions;
- full suite excluding the separately guarded admin-race file: 431 total, 430 passed,
  1 intentional upgrade-script skip, 3,820 assertions;
- corrected deterministic Sprint 3E ordering: 40 passed, 640 assertions.

Every disposable server/database was shut down after verification.

## Composer Audit

`composer validate --strict` passed. `composer audit --format=json` returned zero advisories and
zero abandoned packages. The lockfile was installed without updates.

## NPM Risk Disposition

**DOES_NOT_BLOCK_RELEASE.** `npm audit --omit=dev --json` reports zero production dependency
vulnerabilities. Full audit remains 14 locked development/build-chain findings (9 moderate,
5 high), with no compatible fix for the direct locked chain. The production build passes and no
dependency or lockfile was changed during QA. Retain the findings for dependency-maintenance
review rather than broadening this release.

## WALD Exclusion

PASS. Final diff/static review found no new `App\Wald` runtime, Wald job, migration, table,
trigger, real binding, upload, staging, commit or import route. The retained production-base
source projection code is unchanged. The candidate's source-binding/import read models return
truthful `NOT_YET_INTEGRATED`/empty states only.

## Defects

- **NRQ-01 — P3 — RESOLVED.** One Sprint 3E MySQL ordering test used a 300ms process-start delay
  as an ordering guarantee. A valid reverse ordering could therefore fail the assertion despite a
  consistent final state. Corrected at `10f0a56ac1987754ab0c31b45fc08138ba25e3f8` with an explicit
  test-only completion marker. No application/runtime file changed. Ten repeated runs of all four
  cases passed.

No P0, P1 or P2 defect remains.

## Production Impact

None. No `main` merge or push, remote update, tag, production access, Forge action, production
migration or deployment occurred.

## Remaining Blockers

None.

## Recommendation

**MERGE_TO_MAIN_PREPARATION.** Use the corrected QA lineage beginning at
`10f0a56ac1987754ab0c31b45fc08138ba25e3f8`; do not merge or deploy without the separate release
approval and required production recovery controls.

## Completed

The final dedicated RC gate covered scope, migration, functional, authorization, security,
concurrency, regression, responsive, accessibility, browser, performance and dependency risks.

## Files Changed

- `tests/Feature/Sprint3eMysqlConcurrencyTest.php`
- `tests/Support/Sprint3eMysqlConcurrencyWorker.php`
- `documentation/customerapp-next-release-dedicated-qa-2026-09-10.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

## Tests / Checks

Focused and full SQLite/MySQL totals are recorded above. Final quality gates passed:
`vendor\\bin\\pint --test`, `composer validate --strict`, `composer audit --format=json`,
`npm run build`, `npm audit --omit=dev --json` and `git diff --check`. Full npm audit disposition
is recorded separately above.

## Migrations / Deployment

The migration ran only against disposable local SQLite/MySQL databases. Production was untouched;
no merge, push or deployment occurred.

## Notes

The user's unrelated dirty root worktree was preserved. Browser data, local databases, generated
assets and disposable MySQL evidence were not committed. Initial Vite child-process denial was a
Windows sandbox restriction; the same build passed when granted the required local process
permission.

CustomerApp next release dedicated QA passed — ready for merge-to-main preparation
