# CUSTOMER-ADMIN-SITE02 Backend Implementation Report

Date: 10 September 2026

## Overall Result

**READY_FOR_INTEGRATION.** The customer/site lifecycle, Office authorization, external-access
effects, immutable administration audit, bounded read models and UI-facing JSON endpoints are
implemented and verified on a non-deploying backend branch. Plots, assignments and accepted
WALD05 records remain read-only. No UI, import workflow, source binding mutation, production,
`main`, RC1 or ADMIN-SITE03 change was made.

This is a next-release integration candidate, not release or deployment evidence. The prepared
UI remains separately fixed at `13897dbc38e8615e0e1d2c0bca9bf28e33e9b934`.

## Branch / SHAs

- Branch: `feature/admin-site02a-customer-site-backend-security`.
- Base: `2c4e8701d0f76fa7b59cd0dd9a0313f004d81d64`.
- Lifecycle/schema: `d52cbb343ac9d4154da7e9292c141dfb27c2e214`.
- Authorization/actions/audit: `341c6367cadbfc6f92405f3063fb5f935bd790d7`.
- Read models: `b3b85d803f80b933c27145e97eff01d661c537c8`.
- Security/MySQL tests: `ab3a8e35cd9043bcacb11c1f55c11f8258a36a12`.
- Safe accepted-import summary refinement: `b3d79223f42ea47506ddd63d122b917b479a19d3`.
- Prepared UI reference, inspected read-only: `13897dbc38e8615e0e1d2c0bca9bf28e33e9b934`.

The final documentation commit is the branch HEAD containing this report. No commit was pushed.

## Base

The branch uses the accepted current CustomerApp/WALD05 development line containing the required
authorization, source projection and import-history models. It was not created from the dirty UI
worktree. The unrelated root-worktree Sprint 3E document edit, source workbook and generated
`output/` remain untouched.

## Customer Lifecycle

`CustomerOrganisation` now has a stable externally exposed UUID, `is_active` and an optimistic
`lock_version`. Existing rows backfill active with version 1. Office actions support create,
rename, deactivate and reactivate. Names are trimmed, internal whitespace is collapsed and the
existing global case-insensitive database identity is enforced across active and inactive rows.
There is no hard-delete action or route.

Customer deactivation does not rewrite users, sites, plots, requests or history. Its effect on
external access is derived at authorization time. Reactivation restores customer-level access
eligibility but does not override an independently inactive site or inactive user.

## Site Lifecycle

`Site` now has a stable UUID, `is_active` and `lock_version`; existing rows backfill active with
version 1. Office actions support create, safe metadata update, deactivate and reactivate. A site
requires a customer and name, while location and source reference remain optional. Creation and
ordinary edit cannot set or replace source identity. Existing legacy `external_source` and
`external_identifier` values are preserved and shown read-only.

Site names are normalized and unique within one customer. The same normalized site name may be
used by a different customer. Inactive rows continue to reserve their identity. Deactivation
preserves customer ownership, plots, assignments, source history and workflow history. No site
hard-delete action or route exists.

## External Access Effects

The existing complete-profile and assigned-site checks now require the external user's customer
to be active. Site assignment checks additionally require the selected site and its customer to
be active. This applies equally to Site Manager, Assistant Site Manager and Finishing Foreman.
Existing authenticated sessions fail at `active.portal`; stale inactive-site sessions are cleared
by `active.site`. Office inspection of inactive customer/site records remains allowed.

WALD import mutations also require an active customer and site. Existing private binding/import
audit reads remain available to valid Office actors for historical inspection.

## Admin Actions

The integration-facing actions are:

- `CreateCustomerAction`;
- `RenameCustomerAction`;
- `DeactivateCustomerAction`;
- `ReactivateCustomerAction`;
- `CreateSiteAction`;
- `UpdateSiteAction`;
- `DeactivateSiteAction`;
- `ReactivateSiteAction`; and
- `RecordAdministrativeAuditAction`.

Each mutation normalizes/validates explicit arguments, rechecks stored Office authority inside
its transaction, locks the actor and affected aggregate, checks the expected version where an
existing entity changes, writes only server-selected fields and appends the audit in the same
transaction.

## Admin Audit

`administrative_audits` records UUID, actor ID/name/role snapshot, target type/UUID, stable action,
UTC time, allowlisted before/after values and the bounded lifecycle reason. It does not contain
passwords, workbook content, private Wald evidence, client-supplied actor identity or arbitrary
request payloads.

Create, rename/update, deactivate and reactivate are covered for both entity types. Lifecycle
reasons are mandatory, trimmed and limited to 2,000 characters in both directions. Eloquent
guards and database `UPDATE`/`DELETE` triggers make history append-only. Direct database update
and delete attacks fail on SQLite and MySQL.

## Authorization

Customer and site policies delegate to `OfficeAdministrationPolicy`. Authority requires a real,
persisted, active, non-preview user whose current stored role resolves to exactly Fenster Office
Staff. A null customer organisation is valid for Office Staff but never grants authority by
itself. Dirty security attributes, mismatched loaded role relationships, stale database role,
missing/unknown role, inactive Office, preview identity and all external roles fail closed.

Routes retain `auth` and `active.portal`, add a named 120-per-minute per-user administration
limiter and use policy checks in Form Requests/controllers immediately before dispatch. Actions
repeat stored authority under transaction locks, so direct action invocation cannot bypass HTTP
authorization.

## IDOR / Mass Assignment

Customer/site routes expose UUIDs and use scoped parent-child binding. A site supplied beneath a
different customer returns 404. All detail services repeat parent containment. External users
receive no Office directory, plot, user, binding, import or audit access through guessed UUIDs.

Requests accept only documented fields. Forged customer owner, site owner, lifecycle state,
source identity, binding IDs, actor/audit data, UUIDs and timestamps are ignored or rejected and
cannot reach persistence. Exact duplicate database errors alone become name validation errors;
foreign-key/audit failures retain their real failure type and roll back.

## Plot Read Model

`OfficeAdministrationQueryService::plots()` returns an Office-only, site-scoped, searchable,
paginated projection containing plot UUID/reference, safe source identity, customer-facing overall
status, per-service presentation status/date and source completion/presence timestamps. Product
totals use the approved decimal quantity parser and the existing Windows/Doors/Bifold mappings.
No plot POST/PATCH/DELETE route or action was introduced.

## Site Users Read Model

`assignedUsers()` is Office-only, customer/site-contained and paginated. It returns name, email,
role identifier/display label and active state. Local numeric IDs, customer IDs and assignment
mutation controls are not exposed. `management_identity_available` remains explicitly false;
account/assignment management stays outside ADMIN-SITE02.

## Source Binding Summary

`sourceBindings()` reads accepted WALD05 data without issuing any mutation. It returns:

- `availability`: `AVAILABLE` or `NOT_YET_INTEGRATED`;
- aggregate `state`: `NOT_LINKED`, `DRAFT`, `ACTIVE`, `SUPERSEDED` or `REVOKED`;
- `has_active_binding`;
- at most 100 compatibility `active_bindings` plus a truthful truncation flag; and
- a bounded `bindings` paginator containing source namespace/kind/value, lifecycle state,
  version, actor, reason and safe timestamps.

Hashes, epochs, private evidence, version UUIDs and command internals are not returned. Site list
and detail summaries additionally include the optional legacy source reference and an efficient
active/not-linked binding state.

## Import History Summary

`importHistory()` reads accepted WALD05 run/receipt state and returns an explicit unavailable or
paginated available contract. Safe fields include run UUID, source namespace/family, uploader,
export date/slot, lifecycle state, correction/supersession flags, safe replacement reason,
timestamps and receipt UUID/revision/commit time with allowlisted counts (`seen`, `excluded`,
`applied`, `created`, `updated`, `unchanged`). A real synthetic committed run and explicit
replacement are exercised in the focused test.

Storage keys, original filenames, workbook hashes/content, private evidence, failure codes,
receipt payloads and exception messages are excluded. No upload, analysis, preview, activation,
revocation or commit endpoint was added.

## Schema / Migrations

Additive migration `2026_09_09_000014_add_customer_site_administration.php`:

- adds nullable-then-backfilled/unique UUID, indexed `is_active` and `lock_version` to existing
  customers and sites;
- preserves all existing integer primary/foreign keys and site ownership;
- creates indexed append-only `administrative_audits` with restrictive actor foreign key;
- prevents future null customer/site UUID inserts at the database boundary; and
- refuses rollback once audit or non-default lifecycle/version truth exists.

The simpler approved lifecycle equivalent is current state on the entity plus actor/time/reason
in immutable audit rather than duplicating `deactivated_at/by/reason` on mutable rows.

## Atomicity

Business mutation and audit append share one database transaction. Injected audit insertion
failures roll back entity state and version. The audit recorder refuses use outside a transaction.
Source identity is never updated by lifecycle actions.

## Concurrency

Existing entity mutations use row locks and `lock_version`; stale contenders receive a stable
validation response. Site creation locks its parent and still relies on the tenant-scoped unique
constraint as the final race boundary. MySQL two-connection races prove one consistent/audited
winner for:

- two customer renames;
- customer edit versus deactivate;
- customer deactivate versus reactivate;
- two same-customer/same-name site creates; and
- site edit versus deactivate.

No global lock system was added.

## MySQL Evidence

Disposable MySQL Community Server 8.4.11 ran only on `127.0.0.1:33488` with synthetic databases
`customerapp_admin_site02` and `customerapp_admin_site02_upgrade`.

- Focused lifecycle/security/read test plus concurrency suite: **18 passed, 1 SQLite-only skip,
  204 assertions**.
- Five real two-process race scenarios: **5 passed, 30 assertions**.
- Clean migration: all 15 repository migrations, including the new additive migration, passed.
- Additive upgrade script: MySQL 8.4.11 passed all 12 preservation/backfill/table/source/audit
  checks.

The local server shut down normally and its downloaded package/data were removed after the gate.
The synthetic schemas were disposable evidence, not production access.

## Query Performance

All potentially growing customer, site, plot, assigned-user, binding-version, import-run and audit
lists are paginated with a server maximum of 100. Counts use SQL subqueries; relationships needed
for summaries are eager-loaded. The focused test compares one-row and multi-row query totals for
site and plot pages and confirms constant query counts.

## UI Integration Contract

Mutation route names prepared by the UI are present:

- `portal.office.customers.store|update|deactivate|reactivate`;
- `portal.office.customers.sites.store`; and
- `portal.office.sites.update|deactivate|reactivate`.

Backend GET routes additionally expose customer/site directories/details, plots, users, source
binding history, import history and audit under `/portal/office`. Inputs use UUID route keys,
`name`, optional `location`, mandatory `lock_version` for existing entities and mandatory `reason`
for lifecycle changes. JSON success wraps detail as `customer` or `site`; lists use Laravel's
length-aware paginator shape. Validation returns 422 JSON for Office JSON requests; authorization,
containment and throttling return 403, 404 and 429 respectively.

Lifecycle values are booleans `is_active` plus `effective_is_active` for sites. Audit actions are
`created`, `renamed`, `updated`, `deactivated`, `reactivated`; target types are
`customer_organisation` and `site`.

## UI Integration Gaps

The integration task must reconcile, without redesigning the UI:

1. Merge both branches onto a clean next-release line and resolve their parallel `routes/web.php`,
   `DECISIONS.md`, `current_sprint.md`, `ROADMAP.md` and `HANDOVER.md` additions deliberately.
2. Retain the UI workspace GET routes while pointing forms to the backend mutation route names
   above; do not duplicate controller business logic.
3. Render the now-supplied `site.source_reference` and `site.source_binding_state` fields.
4. Replace the UI's active-only binding assumption with `state`, the bounded `bindings` paginator
   and its `DRAFT`/`ACTIVE`/`SUPERSEDED`/`REVOKED` rows; `active_bindings` remains a compatibility
   summary, not the history source.
5. Render `overall_status` and `product_totals` from plot DTOs where desired rather than inferring
   them in Blade.
6. Optionally render receipt counts and `replacement_reason` from the safe import summary. Never
   render absent/private backend fields.
7. Treat 422 JSON as field errors, 403 as lost authority, 404 as stale/foreign scope and 429 as a
   retryable rate limit. Refresh after a `lock_version` conflict.
8. Preserve the prepared UI branch's clean dependency/security state while selecting these code
   commits. This backend branch intentionally did not merge separate lockfile remediation.

No backend contract requires the UI to expose source binding/import or plot mutation controls.

## Focused Tests

Process-local `APP_KEY` was used and never written to `.env`.

- SQLite administration/related security selection: **111 passed, 5 MySQL-only skips,
  550 assertions**.
- Administration backend alone after the accepted-import refinement: **14 passed,
  181 assertions**.
- PHP syntax: all task PHP files passed.

## Full Regression

The final full CustomerApp run after all executable changes passed **1,328 tests, 54
environment-dependent skips and 6,374 assertions** (1,382 total). No test or assertion was
weakened to obtain a pass.

## Composer Audit

`composer validate --strict` passes. `composer audit --format=json` completes but exits non-zero
with eight inherited advisories: three Filament, four CommonMark and one Livewire (five high, two
medium, one low). This branch did not change packages or lockfiles; the prepared UI line reports a
separately remediated clean dependency state. Integration must preserve that reviewed security
line and rerun the audit. This is not authority for a package change in this task.

## Build / Pint

Pint and `git diff --check` pass. Vite 8.1.4 production build passes with 5 modules after the
sandboxed attempt's expected child-process restriction was rerun with local process permission.
No generated build file is committed.

## Production Impact

None. No production access, customer data, migration, deployment, push, `main`, RC1, Forge,
SiteApp, source workbook, import execution, binding mutation, ADMIN-SITE03 or WALD06 action.

## Remaining Blockers

No backend implementation blocker remains for UI integration. Integration and dedicated
end-to-end admin QA are still required before release acceptance. The integration line must retain
the prepared UI/security dependency state and rerun Composer audit, full MySQL, browser/mobile,
keyboard/focus and complete security/regression gates. The multi-site Wald pilot blocker remains
separate and does not block read-only ADMIN-SITE02 integration.

## Recommendation

Integrate the backend implementation commits with prepared UI checkpoint
`13897dbc38e8615e0e1d2c0bca9bf28e33e9b934` on a new non-deploying next-release integration
branch. Then run one dedicated end-to-end ADMIN-SITE02 QA pass. Do not merge to `main` or deploy.

CUSTOMER-ADMIN-SITE02 backend ready — integrate with prepared admin UI next
