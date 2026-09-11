# CustomerApp Next Release RC1 Build Report

Date: 10 September 2026

## Overall Result

**READY_FOR_DEDICATED_QA.** The approved production-facing sidebar and administration scope has
been composed onto a clean release branch. The synthetic Import Studio remains an isolated
local/testing demonstration. No production, main, remote or Forge action occurred.

## RC Branch

`release/customerapp-next-release-admin-demo-rc1`

## Production Base

`e757bb651f9aa95d67b808a97433d27bc29d03c3`

The branch was created in an isolated worktree. The original dirty root worktree and all feature
worktrees were preserved.

## Final RC SHA

The exact frozen branch-tip SHA is recorded in the task completion report after the documentation
commit. That tip is the only candidate authorised for dedicated QA; this document is part of it.

## Sidebar

Accepted sidebar correction `c80ab5b76a161f340b8786c709b93fecfae47633` and its redesign base
were integrated selectively. UIQ-01 long-reference wrapping, UIQ-02 320px header containment and
UIQ-03 drawer/notification focus handling remain present. Production Office navigation is Review
Requests, Customers and Notifications. Site roles retain Plots & Call-Offs, Trash and
Notifications. Demo-only Imports navigation is rendered only when the local/testing demo route
and flag both exist.

## Customer Admin

Active, currently persisted Office Staff can list/search, create, rename, deactivate, reactivate
and inspect customer activity. There is no hard delete. Bounded read models, optimistic versions,
normalised uniqueness and explicit lifecycle reasons are enforced.

## Site Admin

Office Staff can list/search, create, edit, deactivate, reactivate and inspect site activity under
the correct customer. Source reference remains optional. Parent/child binding is enforced and
customer/site identifiers cannot be reassigned through request data.

## Lifecycle

Customer and site deactivation is reversible and preserves users, assignments, plots, requests,
history and source references. It removes normal external scope immediately. Office retains
inspection/admin visibility. Reactivation restores permitted access without record recreation.

## Plot Inventory

Office plot inventory is read-only, searchable and bounded. Plot creation, editing, deletion and
bulk mutation are absent. Assigned site users are also read-only.

## Source / Import Production State

Real source binding and import integration are absent. The production-safe states are “Not
available yet” and “No import integration has been released yet”. They do not query missing Wald
tables, manufacture bindings/imports or create compatibility tables. The retained production-base
source projection components are unchanged and do not provide a new upload path.

## Synthetic Demo

The ADMIN-SITE03 walkthrough is default-off, local/testing-only and precomputed. All 12 steps were
traversed. DEMO ONLY, SYNTHETIC DATA and NO DATA WILL BE SAVED remain visible. There is no file
input, arbitrary upload, persistence, source binding mutation, Wald backend invocation, commit
endpoint, external AI or network request. The last step keeps commit disabled.

## Production Demo Isolation

With `APP_ENV=production` and the demo flag deliberately forced true, route inventory returned
zero `development/import-studio` routes. Production direct-access behavior is covered by hostile
route tests. The production sidebar cannot render its local demo entry because the route does not
exist.

## WALD Exclusion

The candidate adds no `App\Wald` runtime, Wald job, route, table, trigger or migration. WALD02–05
and their five migrations were not transplanted from the master ancestry. Real binding, upload,
analysis, staging, commit, multi-site import, WALD06, RedZebra API and automatic purge remain on
their separate accepted future lineage.

## Migration Inventory

Production base: **12** migrations. Candidate: **13** migrations. Exact delta:

- `database/migrations/2026_09_09_000014_add_customer_site_administration.php`

The migration is additive. It gives existing customers/sites active defaults, backfills UUIDs,
adds optimistic versions and creates immutable administrative audit storage. Relationships and
dependent rows are not deleted; no hard-delete cascade is introduced. MySQL index, foreign-key
and trigger creation passed on 8.4.11.

## Production-Shaped Upgrade

A disposable MySQL 8.4.11 database was migrated to the production base, the administration
migration was removed, and synthetic production-shaped customers, sites, users, assignments,
plots, projected services, call-off batches/requests/status history and Sprint 3F amendment
history were inserted. Reapplying the migration passed all **16** preservation checks, including
UUID/active/version backfill and exact dependent-graph retention.

## Authorization

Verified: active persisted Office Staff are allowed, including a null customer organisation;
inactive Office, preview-only authority, stale/mismatched Office roles, Site Manager, Assistant
Site Manager and Finishing Foreman are denied. All mutations re-authorise immediately before
persistence. External inactive customer/site scope fails closed.

## Admin Audit

Customer create/edit/deactivate/reactivate and site create/edit/deactivate/reactivate produce
attributed audit records with before/after state and lifecycle reason where required. Database
triggers reject audit update/delete. Failure to write required audit rolls back the business
mutation. No private source content is stored in admin audit.

## IDOR / Mass Assignment

Hostile customer/site/owner, lifecycle state, actor, deactivation metadata, source reference and
audit-field inputs were rejected or ignored. Cross-customer child binding and all external-role
admin access fail server-side. No hidden control is relied upon for authorization.

## Responsive / Accessibility

One browser session covered 1920×1080, 1440×900, 1366×768, tablet 768×1024, 390×844 and
320×800. Review Requests, Customers, customer/site detail, every site section, source/import empty
states, site selection and Plots & Call-Offs rendered without document-level horizontal overflow.
Mobile drawers expose named controls, contain focus, close with Escape and return focus to the
trigger. Headings, landmarks, field labels, status text and disabled states are exposed in the
accessibility tree. Browser console warnings/errors: **0**; failed assets observed: **0**.

## Focused Tests

- Admin backend, presentation, sidebar and demo: **64 passed, 440 assertions**.
- Browser-side admin/demo behavior: **14 passed**.
- Queue-card and Sprint 3F regression group: **102 passed, 1,117 assertions**.

## Full Regression

SQLite: **436 total — 393 passed, 43 MySQL-only skipped, 2,784 assertions**.

## MySQL

- Version: **MySQL Community Server 8.4.11**.
- Clean install: all **13 migrations passed**.
- Full suite excluding the separately guarded admin-race file: **431 total — 430 passed,
  1 intentional upgrade-script skip, 3,820 assertions**.
- Focused admin/security/presentation/demo/Sprint 3F group: **160 total — 159 passed,
  1 intentional upgrade-script skip, 1,518 assertions**.
- The five real admin concurrency races passed inside that focused group under their dedicated
  database-name safety guard.
- Production-shaped upgrade: **16/16 checks passed**.

The disposable MySQL server was stopped after verification.

## Composer / NPM

- `composer install`: exact lockfile, **0 updates**, completed.
- `composer validate --strict`: passed.
- `composer audit`: no security advisories.
- `npm ci`: exact lockfile, completed.
- `npm audit --omit=dev`: **0 vulnerabilities**.
- Full `npm audit`: **14 locked development/build-chain advisories** (9 moderate, 5 high), with
  no currently available compatible fix reported. No dependency or lockfile upgrade was made.

## Build / Pint

- `npm run build`: passed with Vite 8.1.4.
- `vendor\bin\pint --test`: passed.
- `git diff --check`: passed before documentation reconciliation and is rerun at freeze.

## Documentation

DEC-061 records the approved boundary. `brief.md`, `current_sprint.md`, `ROADMAP.md` and
`HANDOVER.md` distinguish production-facing sidebar/admin scope, local/testing demo scope and
excluded real Wald/import scope. Imported feature reports are labelled historical evidence.

## Production Impact

None. No main merge, push, remote update, production access, Forge action, migration or deployment
was performed.

## Remaining Release Blockers

Dedicated QA against the exact frozen RC tip is the only release gate identified by this build.
The locked development npm advisories remain a visible release-review item but production-only npm
and Composer audits are clean.

## Recommendation

Dedicated next-release QA may begin against the exact frozen branch tip from the completion
report. Do not merge or deploy until that QA and a separate release decision pass.
