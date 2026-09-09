# CustomerApp Site + Import Admin Audit

_9 September 2026_

## Audit scope and evidence

This is the documentation-only `CUSTOMER-ADMIN-SITE01` audit. It inspects the current local
WALD05 QA line at `c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` and records its findings on the
isolated, non-deploying branch `docs/customer-admin-site01-audit-2026-09-09`.

The supplied audit prompt contains a superseded WALD05 statement: W5Q-03 and W5Q-04 are no longer
open corrections. The current [dedicated QA report](../wald/customer-wald05-backend-qa-2026-09-09.md)
records a fresh **PASS** against `dbd17c68a04c028418e2d8a08fc43312aae5fe3b` and says the bounded
backend is eligible to freeze. It is still feature-branch/local evidence, not proof of management
freeze, `main`, deployment, a full Office UI or pilot readiness.

Evidence included the current governing documents, source contracts, models, migrations,
controllers, routes, views, gates, seed data, the WALD05 services and tests, the existing local
SQLite database, the earlier account-admin audit draft, and read-only inspection of the non-main
`feature/manual-source-import-ui` worktree at `1e8c22bfd5021b82b9775892ebddb192cb173e16`.
The old feature line is evidence only. No workbook was opened, uploaded, interpreted or changed.

## Overall Result

**OPTION C IS SAFE ONLY AS TWO SEPARATE, EXPLICITLY BOUNDED BUILDS.**

CustomerApp has no browser-accessible customer, site, plot, assignment, source-binding or import
administration today. Filament is installed, but there is no registered panel, resource, page,
relation manager, action or `/admin` route. Office Staff currently land on Review Requests.

The corrected WALD05 backend is materially stronger than the stale prompt states: dedicated QA
has passed its bounded one-site/one-table backend. Nevertheless it remains default-off and has no
HTTP layer or Office screens. Its current unit cannot safely process multiple sites from one
shared export date/slot by simply splitting the workbook. The actual workbook has not been
imported.

The smallest useful path is therefore:

1. build audited customer/site administration against focused server-side actions;
2. build a strictly local/test-only Import Studio demonstration shell using one bundled,
   synthetic, precomputed scenario and no mutation endpoint;
3. formally freeze the QA-passed WALD05 backend before connecting any real intake/analysis UI;
4. approve the multi-site parent-export/unit contract before a real twelve-site pilot.

This gives Josh an honest product walkthrough without making the demo resemble an operational
import or creating a second parsing, semantic, profile, binding, staging or commit system.

## Current Customer Management

There is no Customer list, detail, create, rename, deactivate or site-management screen.

The current backend representation is limited to
[`CustomerOrganisation`](../../app/Models/CustomerOrganisation.php), whose only writable field is
`name` and whose relationships expose users and sites. The original access migration requires a
globally unique organisation name. It provides no public UUID, active/deactivated state, archive
state or admin history.

Office Staff can see a customer name only incidentally on an authorised call-off detail. They
cannot browse customers or open a customer record. Factories and the seeder create data for local
development/tests; they are not supported management workflows.

Current capability by requested action:

| Action | Current state |
|---|---|
| View customer directory | `NOT_IMPLEMENTED` |
| View customer details and its sites | `NOT_IMPLEMENTED` |
| Create customer | `NOT_IMPLEMENTED` |
| Rename customer | `NOT_IMPLEMENTED` |
| Deactivate/archive customer | `NOT_IMPLEMENTED`; schema has no such state |
| Delete customer | `NOT_IMPLEMENTED`; restrictive relationships make deletion inappropriate |

Any implementation needs a focused Office policy/action, validation, concurrency handling and an
immutable audit record. Generic `Model::create($request->validated())` is not an acceptable
management boundary.

## Current Site Management

There is no Office Site list, detail, create, edit, deactivate, assignment or import action.
Site users can select an assigned site, and Office Staff can filter Review Requests by site; those
are use/query interfaces, not management.

The exact current [`Site`](../../app/Models/Site.php) and schema contract is:

| Field | Current database rule | V1 admin treatment |
|---|---|---|
| `customer_organisation_id` | Required FK; restrictive delete | Required, chosen from a server-authorised customer record |
| `name` | Required; unique with organisation | Required; trim and enforce the scoped uniqueness rule |
| `location` | Nullable string | Optional; do not silently make it required |
| `external_source` | Nullable string | Optional legacy source identity; do not confuse with a WALD binding |
| `external_identifier` | Nullable string | Optional legacy source identity; paired unique with `external_source` |
| active/archive state | Does not exist | Requires a product/schema decision before exposing a control |
| public UUID | Does not exist | Add before exposing predictable site IDs in management URLs, or use a separately reviewed binding strategy |

WALD04 also adds a composite `(id, customer_organisation_id)` uniqueness guarantee so binding
and knowledge foreign keys can prove tenant ownership. A future action must preserve it.

The model's fillable source and ownership fields are not proof that browser mutation is safe.
No controller, form request, action, policy or admin audit currently wraps those writes.

## Current Plot Management

There is no Office Plot resource or manual plot workflow. Site users can view authorised
[`ProjectedPlot`](../../app/Models/ProjectedPlot.php) records and their service state; they cannot
create or edit them.

The exact persisted plot requirements are: server-generated UUID, required site, required
`external_source`, required globally unique `(external_source, external_identifier)`, required
`plot_reference`, `is_completed` default false, and optional source/synchronisation timestamps.
There is no plot active/retired state. `is_completed` is projection state and must not be reused
as an admin archive flag.

Plots are presently created by projection import code. The accepted WALD05
[`ProjectionAdapter`](../../app/SourceImport/Integration/ProjectionAdapter.php) creates a missing
plot within the reviewed atomic commit and creates its four service rows. The retained legacy
importer can also create plots, but it has obsolete mapping/absence/transaction behaviour and is
not the future admin contract.

Safest V1 distinction:

- Office may **view/search** projected plots and source status from Site Details;
- real source imports create/update source-backed plot projections;
- no edit, retire, delete or arbitrary bulk-add is exposed in the first site-admin slice;
- a future manual-plot path, if genuinely required, needs an explicit source/identity/precedence
  decision and focused audited action rather than pretending a manual row came from RedZebra.

## Current Import UI

| Material | Classification | Finding |
|---|---|---|
| Review Requests and notification centre | `ACTIVE_CURRENT` | Current Office browser experience; unrelated to import administration. |
| Retained `SourceProjectionImportService` | `BACKEND_ONLY` | Accepts constructed records; no workbook/admin route. Retained for compatibility, not the WALD commit path. |
| WALD05 `ImportIntake`, `ImportAnalysis`, `ImportClarification`, `ImportReview`, `ImportRetention` | `BACKEND_ONLY` | QA-passed bounded application services on the local QA line; default off; no controllers/routes/screens. |
| WALD05 `SourceBindingService` | `BACKEND_ONLY` | Audited exact binding lifecycle; no UI. |
| Non-main `/portal/source-imports` wizard at `1e8c22b` | `OLD_REFERENCE` | Seven-screen Blade/Alpine prototype with upload, mapping, binding, dry run, confirmation and result. Not current or deployed. |
| Old direct parser/profile/orchestration and commit route | `SUPERSEDED` | Replaced architecturally by WALD03/04/05; must not be merged or used as a fallback. Only tested invariants and information-design ideas are reusable. |
| Current Filament import resource/page | `NOT_IMPLEMENTED` | No Filament panel or CustomerApp resource exists. |
| Current Import History page | `NOT_IMPLEMENTED` | WALD data exists only behind services/tables; no authorised query/presentation layer exists. |

`php artisan route:list --path=admin --except-vendor` and
`php artisan route:list --path=import --except-vendor` both return no matching application routes.

## WALD05 Backend Availability

Current state: **dedicated bounded-backend QA PASS; eligible to freeze; not yet a full Office
feature or release.**

The fresh requalification closed durable failed/refused attempt audit and profile-receipt query
growth. It passed 1,314 CustomerApp tests with 49 environment skips and 6,193 assertions, plus
240 disjoint MySQL passes, one SQLite-only skip and 2,256 assertions. These are the current QA
report's results, not checks rerun by this documentation audit.

Available backend contracts include:

- private XLSX/UTF-8 CSV intake and generated non-public storage keys;
- Office-only, current-authority-checked operations;
- Export Date plus `MORNING`/`AFTERNOON` ordering and exact confirmation;
- deterministic analysis, clarification and profile use;
- immutable staging, preview, approval, atomic commit and receipts;
- exact source-site binding lifecycle;
- safe audit/status/retention metadata and correction successors.

Not available as a browser product:

- HTTP controllers, form requests, CSRF/throttling policy or routes;
- Office navigation/screens and accessible/responsive browser evidence;
- production worker/scheduler/storage operations;
- formal frozen baseline, cutover or deployment;
- real workbook import or twelve-site handling;
- a production-safe multi-site parent export/import-unit contract.

The feature remains disabled by default in
[`config/wald_import.php`](../../config/wald_import.php). Enabling the flag alone does not create a
safe Office product.

## Source-Site Binding UI

No binding UI exists. The current backend is not a mutable `site.external_identifier` picker; it
is an immutable, versioned association between an exact source identity and an existing
CustomerApp organisation/site.

Required binding inputs are:

- explicit `KnowledgeScope`: organisation ID, site ID, source namespace and workbook family;
- identity kind: exactly `SOURCE_SITE_ID` or `EXACT_SITE_NAME`;
- exact source identity, no fuzzy match;
- current epoch/version/hash for stale-state protection;
- Office reason and command UUID;
- current, active, non-preview Office actor resolved from stored authority.

Lifecycle:

```text
DRAFT → ACTIVE → SUPERSEDED
               ↘ REVOKED
```

Only one version is active. Used versions and audit stay immutable. A revoked version cannot be
reactivated; Office creates a new draft/successor. Binding never creates or moves a CustomerApp
site and is independent of a remembered workbook layout/profile.

A future Site Details screen should show the active binding, identity type, exact source value,
version, state and last action, with separate Draft, Activate, Replace and Revoke actions. Demo
mode must show this as synthetic read-only state; it must not expose these mutations.

## What Josh Can Do Today

Exact current local browser journey:

1. Open `https://customerapp.test/` in the local environment.
2. When signed out locally, the root route redirects to Development Role Preview.
3. Choose **Fenster Office Staff**. The controlled preview user is local/test only.
4. The dashboard redirects to **Review Requests**.
5. Review/filter call-off requests by status, site and service, open a request, and use the
   existing approved decision/date controls. The notification centre is also available.
6. There is no Customers, Sites, Accounts, Plots, Bindings, Imports or Import History link or
   route. A direct `/admin` or import URL does not unlock one.

The checked local SQLite database currently contains the seeded `Fenster Preview Customer`, the
sites `Meadow View`, `Oaklands` and `Willow Park`, four preview users and 18 projected plots. It
does **not** contain `TEST — Acme Developments`; the user-supplied example is stale. This database
has only 11 applied migrations and no WALD04/05 tables, so it is suitable for the existing Portal
preview only, not evidence of a working Wald browser demo.

Do not run `migrate:fresh --seed` merely to see these records: that is destructive to the selected
local database. Use a separate disposable test database when a future demo build is verified.

## Missing Admin Capabilities

- Office-only customer list, search, create, detail and safe rename/archive contract;
- Office-only site list, search, create, detail and safe edit/archive contract;
- public management identifiers and immutable admin audit;
- site-user assignment management backed by the separately scoped account work;
- read-only plot inventory/source-status management view;
- deliberate source-binding UI and history;
- central Imports list, status, needs-attention queue and history;
- real intake, analysis, clarification, preview and commit HTTP adapters;
- browser security, rate limiting, storage, error, keyboard, mobile and assistive-technology QA;
- multi-site export/import-unit architecture;
- production worker, retention, backup/recovery and cutover operations.

## Recommended Office Navigation

Use one Office workspace within the existing CustomerApp Blade/Tailwind layout:

```text
Office
├── Review Requests
├── Customers
│   └── Customer Details
│       └── Sites
│           └── Site Details
│               ├── Overview
│               ├── Plots
│               ├── Users
│               ├── Source Binding
│               └── Import History
├── Accounts
└── Imports
    ├── New Import
    ├── Needs Attention
    └── Import History
```

Site Details should provide the contextual **Import Source Data** action. The central Imports
area supports Office-wide operations and resumption. Both should enter the same future import
workflow with the site context server-selected, never two implementations.

Filament 5 is installed, but
[`bootstrap/providers.php`](../../bootstrap/providers.php) registers no Panel Provider and there
is no current Filament design to extend. Filament could technically supply resources, relation
managers, tables and actions, but activating it would create a new authentication/navigation
surface and a second visual system. For this bounded V1, extend the established Portal shell with
Blade/Livewire and focused actions. Reconsider a dedicated Filament panel only through a separate
security/design decision; do not use package presence as the reason.

## Recommended Add-Site Journey

```text
Customers
→ Customer Details
→ Add Site
→ enter Site name and optional Location
→ optionally record a known source reference
→ review
→ Create Site
→ Site Details
→ Import Source Data
```

The server selects the customer from the authorised route context and rechecks it before the
transaction. Browser-supplied customer/site/source IDs are not authority. Creation records actor,
safe before/after state and time in immutable admin history.

A CustomerApp site shell should be created deliberately before importing. If its permanent source
ID is unknown, leave source identity unbound and add it later through the audited binding flow.
Do not require a fabricated source reference.

No automatic site creation from a workbook in V1. An unknown source site must produce **Site not
yet linked**. Office then chooses an existing site or deliberately leaves the import and creates a
site under the correct customer, returns, drafts the exact binding and explicitly activates it.
This matches the WALD05 contract.

## Recommended Import Journey

One future workflow, reachable centrally or from Site Details:

1. **Scope** — show the selected customer and site; choose the controlled source namespace/family.
2. **Upload spreadsheet** — XLSX/CSV limits, private handling and partial-export explanation.
3. **Export details** — Export Date, `MORNING` or `AFTERNOON`, automatic uploader, and the exact
   latest-export confirmation.
4. **Wald analysis** — resumable state with no invented percentage.
5. **Needs your help** — resolve only bounded structural clarifications; explain whether an answer
   is one-time or separately remembered.
6. **Site binding** — show each exact detected source identity and existing active binding; no
   fuzzy or automatic tenant selection.
7. **Review source records** — additions, changes, unchanged, preserved omissions, explicit
   exclusions, warnings and blockers.
8. **Preview changes** — affected plot/service, completion/reversal, product roll-ups, BF impact,
   operational dates and preserved Portal dates/history.
9. **Approve review** — records the exact review; explicitly does not commit.
10. **Commit** — separately authorised final action after a fresh dependency check.
11. **Result/history** — receipt, counts, safe result, replacement/supersession and next action.

Import History can eventually use WALD05 run/stream/stage/preview/receipt/attempt data for created
time, declared export date/slot, uploader, customer/site, state, row/effect counts, safe warning or
failure categories, committed state, predecessor/replacement and receipt. The stored original
filename is private evidence: show a safe display name only to authorised Office Staff on the run
detail, not to customers or broad notifications. Raw rows, worksheet/cell evidence, storage keys,
hashes and technical errors belong only in bounded authorised diagnostics.

## Safe Demo Mode

Recommend a presentation-only Import Studio shell with all of these controls:

- route and navigation exist only in `local`/`testing` and behind a dedicated default-off demo
  flag; production registration is absent, not merely visually hidden;
- active controlled Office preview users may open it, but it never changes their role or grants
  import authority;
- one bundled synthetic scenario and precomputed, versioned display manifest; no customer file,
  arbitrary upload, workbook parser or WALD service call;
- conspicuous persistent labels: **DEMO ONLY**, **SYNTHETIC DATA**, **NO DATA WILL BE SAVED**;
- every stage is read-only client/session presentation; refresh resets the scenario;
- the final page says **Commit unavailable in demo** and has no commit form/action/endpoint;
- no database writes, private artifact storage, queues, emails, projection actions, bindings,
  profiles, staging, receipts or import audit rows;
- feature and browser tests prove the production route is 404/absent and that walking every demo
  step leaves all domain/import tables unchanged;
- accessibility uses real headings, step status text, focus movement, validation examples, large
  touch targets, keyboard operation and mobile layouts.

This is deliberately a product storyboard, not a fake successful import. It may label the sample
control **Use synthetic example spreadsheet** rather than pretending the user uploaded an
arbitrary file. It must not reimplement or mock parser output dynamically. When real integration
is approved, replace the demo data adapter with accepted services behind separately secured HTTP
adapters; do not grow the storyboard into a second backend.

If Josh needs a remotely shared demonstration rather than local use, do not relax this design.
Use a separately approved, isolated non-production environment/database with synthetic accounts,
production route absence and reset/recovery controls.

## Multi-Site Workbook UX

Future proposal only:

```text
Upload parent workbook
→ Wald reports “12 source sites detected”
→ Office reviews the parent export identity/date/slot once
→ system creates explicit, ordered site import units
→ bind/review each unit independently
→ parent summary shows Not started / Needs help / Ready / Committed / Blocked
```

The UI should allow search/filter and one site at a time, preserve a clear parent-workbook summary,
and prevent a misleading “Import all” until atomicity/recovery semantics are approved. It should
never invent per-site workbook families, dates or slots.

Do not implement this from the sketch. Current WALD05 supports one bound site, one visible
unmerged table and at most 500 nonempty rows. A second same-family/date/slot site commit conflicts
with the first. WALD06 needs an approved parent-export identity, child-unit ordering, retry,
replacement, review and partial-parent outcome contract before the real twelve-site UX exists.

## Security / Permissions

All customer/site/plot/assignment/import administration is Office-only. Existing fixed Portal
roles remain unchanged. External Site Manager, Assistant Site Manager and Finishing Foreman users
must not access:

- customer or site administration;
- plot/source administration;
- site assignments;
- source bindings or their history;
- uploads, clarifications, profiles or private evidence;
- import runs/history/diagnostics;
- review approval or commit controls.

Every real route needs `auth`, active-profile middleware, an Office management gate and a fresh
stored-authority check inside the focused action. Null organisation is valid for Office but is not
authority; every import still selects and verifies an explicit organisation/site scope. Preview
users remain denied by the real [`ImportPolicy`](../../app/SourceImport/Integration/ImportPolicy.php).
UUID possession, file ownership, a remembered profile or a prior binding never grants access.

Admin writes need CSRF, deliberate rate limits, validation, transactions, immutable audit,
tenant-contained lookups, hostile IDOR tests, stale/concurrent-write handling and server-generated
public identifiers. Do not expose raw database IDs, storage keys, source rows, filenames, sheets,
private notes, exception messages or customer data outside the exact authorised scope.

## Minimum Useful V1

### ADMIN-SITE02 — real site/customer administration

- Office Customers list/search/detail and safe create/rename;
- Office Sites list/search/detail and explicit Add Site under a customer;
- Site Overview with customer, name, location and source-binding summary;
- read-only Plots and Import History placeholders with honest unavailable/empty states;
- link to the separately scoped Accounts area for assigned users;
- public identifiers, policies, validators, focused transaction actions and immutable admin audit;
- no delete, automatic site import, plot mutation or real Wald connection.

### ADMIN-SITE03 — safe demonstration shell

- local/test-only Office Import Studio entry from central Imports and Site Details;
- bundled synthetic, precomputed scenario showing scope, export details, analysis, binding,
  clarification, records, preview and disabled commit;
- persistent demo/synthetic/no-save labelling and production route absence;
- no parser, durable import data or business mutation.

This is enough to demonstrate the intended customer/site/import mental model without presenting an
unreleased data path as operational.

## Recommended Option

**OPTION C — build site/customer administration plus a safe non-mutating import demo, then connect
the real backend after formal freeze and a separately approved integration scope.**

Option C is safe only with the hard isolation in this report. If the demo is expected to accept
arbitrary workbooks, call current WALD services, persist uploads/runs/bindings, or exist in
production, it ceases to be ADMIN-SITE03 and must wait for the real integration/security work.

Option A is unnecessarily restrictive because the corrected bounded backend QA has already passed
and the presentation shell need not touch it. Option B remains the fallback if local/test-only
route absence and zero-write proof cannot be guaranteed.

## Implementation Sequence

1. **Management freeze** — formally accept/freeze QA-passed WALD05 corrected candidate
   `dbd17c68a04c028418e2d8a08fc43312aae5fe3b`. This corrects the stale sequence in the supplied
   prompt; fresh backend QA is already complete.
2. **ADMIN-SITE02A decisions/backend** — approve the decisions below; implement public identities,
   audit and focused customer/site actions with hostile role/tenant tests.
3. **ADMIN-SITE02B UI** — build the Office workspace, Customers/Sites pages and Site Details using
   the established Blade/Livewire design.
4. **ADMIN-SITE03 demo** — build and QA the local/test-only synthetic storyboard with no real
   upload, import service or commit route.
5. **WALD05 Office integration scope** — design secured HTTP adapters and full browser journey
   against the frozen services; do not reuse the old manual importer.
6. **WALD06 architecture/pilot** — approve parent-export/multi-site units, real held-out workbook,
   worker/storage/retention/backup/recovery/cutover and supervised pilot contract before starting.
7. **Dedicated QA** — independently test each real admin and import slice, including SQLite,
   disposable MySQL, security, keyboard, responsive, assistive-technology and target-environment
   operations. Release remains a separate approval.

## Decisions Needed From Josh

1. **Customer/site lifecycle.** Should V1 support deactivate/reactivate, or create/rename only?
   Safest first slice: no delete; add explicit audited deactivate/reactivate only if inactive
   customers/sites have a defined effect on logins, assignments, call-offs and import scope.
2. **Manual site source fields.** May Office create a site shell without any source reference and
   bind it later? Recommended: yes. Keep location and legacy source reference optional; never
   fabricate an ID.
3. **Manual plots.** Is there a genuine V1 need to add/edit plots outside source import?
   Recommended: no. Make the admin plot list read-only and import-managed until a manual source
   identity and precedence contract is approved.
4. **Demo location.** Is local/test-only demonstration sufficient, or is a shared non-production
   demo environment required? Recommended: local/test-only first. A shared demo needs its own
   isolated database, accounts and operational approval.

The earlier account-admin audit separately asks who may manage Office accounts, whether an
external account may activate with no sites, and whether customer transfers are permitted. Those
remain account-work decisions and should not be silently answered by Site Details.

## Recommendation

Approve Option C with strict separation. Build **ADMIN-SITE02** next as a real Office-only
customer/site management boundary, backend/security first and UI second. Then build
**ADMIN-SITE03** as a local/test-only, synthetic, precomputed Import Studio walkthrough with no
real upload, persistence, WALD call or commit endpoint. Formally freeze the corrected WALD05
backend before any real UI connection, and do not begin multi-site implementation until its
WALD06 contract is approved.

No runtime code, route, resource, migration, database record, workbook, package, production state,
`main`, push or deployment was changed by this audit.

CustomerApp site/import admin audit complete — safe demo implementation recommended
