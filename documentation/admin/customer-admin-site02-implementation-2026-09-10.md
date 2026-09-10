# CUSTOMER-ADMIN-SITE02 Implementation Report

Date: 10 September 2026

## Overall Result

**PARTIAL — UI prepared; backend integration and dedicated QA remain outstanding.**

This is a feature-branch implementation checkpoint, not a working integrated admin release.
The user confirmed that another task owns ADMIN-SITE02A and instructed this task to consume
that backend when ready. Its uncommitted implementation was inspected read-only, not copied,
committed, modified or merged. No fake runtime backend or development authority was added.

## Branch / SHA

- UI branch: `feature/customer-admin-site02`.
- Worktree: `C:\Users\JoshO\Documents\CustomerApp\.cursor\worktrees\customer-admin-site02`.
- Clean starting checkpoint: `307e3fa78e88b26d0e102642087dc504a4a9572e`.
- Accepted sidebar ancestor: `c80ab5b76a161f340b8786c709b93fecfae47633` (verified).
- UI checkpoint: the commit containing this report; its exact SHA is returned in the task report.
- Separate backend branch: `feature/admin-site02a-customer-site-backend-security`.
- Backend HEAD inspected: `2c4e8701d0f76fa7b59cd0dd9a0313f004d81d64`, with uncommitted
  administration implementation; this HEAD is not an accepted backend implementation SHA.

## Completed

Prepared Blade/Tailwind administration screens, GET-only page adapters, Alpine form submission
handling and isolated UI tests against the inspected draft contract. Existing business
actions, policies, models, imports, locks, migrations and authentication were not changed.
The sidebar retains its accepted layout and drawer behaviour.

## Customer Management

Paginated list with search and lifecycle filters; customer/site counts; details; Add Customer;
Edit Customer; customer activity; explained deactivate/reactivate confirmation pages.
Only the name is editable. Names and audit text are escaped. Stable UUID destinations and
backend `lock_version` are carried through the forms; normalization and uniqueness remain
backend-owned. There is no delete control.

## Site Management

Paginated/searchable sites under their parent customer, optional location, plot/assignment
counts, Add Site, Edit Site and section navigation. Creation requires no source reference.
Forms have no customer ownership, binding identity, actor or audit inputs. The customer is
selected by the scoped route, not an editable browser-supplied ownership field.

## Lifecycle

Active/inactive text and effective external-access explanations are displayed from the
backend summaries. Both lifecycle forms use the draft backend's required reason and version
contract. A dedicated confirmation page gives impact, record name, reason, Confirm and Cancel;
it does not add another modal alongside the existing drawer. No lifecycle policy or database
transition is implemented in the UI. Preservation and access-blocking claims require the
finished backend's tests before QA approval.

## Plot Inventory

Read-only cards, reference search, pagination, source identifier, source-completion information,
service inspection and last-synchronised timestamp. Long references wrap. No Add/Edit/Delete
Plot control. Source completion is labelled as source information, not a reconstructed Portal
status. The draft does not supply customer-safe overall Portal status or product totals;
Backend must provide approved presentation fields where these data exist.

## Site User Visibility

Read-only paginated name, email, display role and active status. No assignment editing or dead
Accounts link. Assignment identity/management remains outside this UI slice.

## Source Binding Summary

Read-only active-binding summaries show identity, actor and last-changed date without hashes,
epochs, version UUIDs or private evidence. Distinguishes unavailable integration from no active
link. Draft/Superseded/Revoked history cannot be rendered truthfully from the draft's
`active_bindings` array. Backend must supply a bounded, site-scoped history/state contract;
the current draft also loads active bindings without pagination. General metadata forms never
mutate a binding. Existing legacy source references and list-level binding state are not
supplied by the draft site summary and remain integration requirements.

## Import History

Prepared safe paginated read-only export date/slot, uploader, friendly state and receipt-based
commitment display. Reviewed does not mean committed. Private failure codes are not rendered.
Unavailable and empty history have different messages. No history was fabricated in a running
application; test fixtures are synthetic. Additional receipt-count and replacement summaries
need a confirmed safe contract before presentation. No Wald code is integrated on this branch.

## Import Source Data Entry Point

Site Details → Import Source Data leads to a clearly labelled coming-next page. The global
Imports page has the same honest placeholder. Neither exposes upload, binding writes, preview
commit or New Import. ADMIN-SITE03 is not implemented here.

## Sidebar Integration

Adds policy-gated Customers and Imports beside existing Review Requests and Notifications.
Accounts is omitted until it has a real destination. The accepted sidebar, 320px header and
notification/drawer focus corrections remain ancestors and are not reimplemented.

## Admin Audit

Customer and site Activity views show backend action, actor/role, UTC timestamp, reason and
allowlisted before/after name, location and status changes. Empty history explains that older
records may predate auditing. Durable audit persistence remains ADMIN-SITE02A-owned.

## Authorisation

New page routes require existing authentication, active-portal middleware and Backend's
`viewAny` CustomerOrganisation policy. Read queries use Backend's administration query service;
mutations submit only to its existing named JSON routes. No policy, preview privilege or role
logic was added. **Until that backend is integrated, the administration directory is denied
and its navigation is hidden, including for Office users. This is not yet a usable feature.**

Three external roles have explicit directory/import-entry 403 checks in the isolated UI suite.
These do not substitute for integrated active/null-org/inactive/stale-role/preview security
tests. Positive Office page and mutation paths remain unverified.

## IDOR / Mass Assignment

Routes use UUID constraints and scoped customer/site binding. The page adapter asks Backend
to confirm parent containment before presenting site data. Forms contain only intended user
fields, CSRF/method metadata and version; hidden fields are not treated as security.
Server-owned-field rejection, guessed record/plot/audit identifiers, stale identities and
lifecycle race handling require integrated backend tests. They have not been claimed as passed.

## Schema / Migrations

No migration/model/schema change in this UI branch. No application database migration or
reset was run. Automated tests used disposable in-memory SQLite through the existing test
configuration. Backend's additive administration migration remains in its separate worktree.

## MySQL Evidence

Not run for this UI-only checkpoint. Backend must supply disposable MySQL 8.4 clean-install,
additive-upgrade, preservation/backfill, constraints and rollback evidence for its schema.
No historical MySQL result is presented as evidence for the pending administration schema.

## Responsive / Accessibility

Implemented mobile-first wrapping cards/actions, bounded page grids, large primary targets,
visible focus styles, labelled fields, semantic links/buttons, text lifecycle labels and
linked validation messages. Saving has an announced state; error summary receives focus.
Stale/access errors require reload. Network/server uncertainty prevents blind duplicate retries;
successful submissions stay disabled during navigation. No private exception text is shown.
Forms explicitly explain that JavaScript is required if it is disabled.

Automated checks cover escaped/long content, fields, confirmation text and error focus logic.
**No fresh browser/viewport, keyboard drawer interaction, screen-reader or visual contrast
pass is claimed for these administration pages.** Those require the integrated local backend.
The earlier sidebar QA report remains historical evidence only.

## Focused Tests

Commands ran in the UI worktree with a process-only generated test APP_KEY (not printed or saved).

- `php artisan test tests/Feature/OfficeAdministrationPresentationTest.php --compact`:
  **27 passed / 111 assertions**.
- `node --test tests/js/office-admin-form.test.mjs`: **13 passed / 0 failed / 0 skipped**.
- `php artisan test tests/Feature/CustomerSidebarWorkspaceTest.php tests/Feature/PortalNotificationUiTest.php --compact`:
  **17 passed / 114 assertions**.
- Related UI regression command below: **107 passed / 725 assertions**. This is this task's
  explicit 12-file selection, not a reuse of the earlier sidebar report's 121-test total.

```text
php artisan test tests/Feature/AuthenticatedSiteDashboardTest.php tests/Feature/CallOffLifecycleUiTest.php tests/Feature/NewCallOffTest.php tests/Feature/OfficeStaffReviewRequestsTest.php tests/Feature/PortalNotificationUiTest.php tests/Feature/PortalNotificationsTest.php tests/Feature/Sprint2aUiTest.php tests/Feature/Sprint3cPlotOverviewTest.php tests/Feature/Sprint3dBulkCallOffQaTest.php tests/Feature/Sprint3dCallOffWorkflowTest.php tests/Feature/Sprint3eDateNegotiationUiTest.php tests/Feature/CustomerSidebarWorkspaceTest.php --compact
```

The new PHP suite renders synthetic arrays matching the inspected contract. When absent,
test-only mutation route names render to a nonfunctional 501 endpoint; they do not simulate
successful persistence or add a runtime fallback. These are presentation tests, not full
administration workflow/security tests. Paginator totals test bounded rendering, not database
query counts or a production performance benchmark.

## Full CustomerApp Regression

`php artisan test --compact`: **358 passed / 38 skipped / 2,478 assertions**, 396 tests total.
This is the accepted-sidebar base plus UI tests, **not** the unintegrated ADMIN-SITE02A backend
or WALD suite. The skips are not passing MySQL evidence. Initial focused tests without a local
APP_KEY failed; rerunning with an ephemeral process-only key passed without creating `.env`.

## Composer / NPM

- Environment verified: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.
- `composer install --no-interaction --prefer-dist`: passed using the existing lockfile.
- `npm ci --no-audit --no-fund`: passed using the existing lockfile.
- `composer validate --strict`: passed.
- `composer audit --format=json`: passed, no advisories/abandoned packages. Composer could
  not write its local cache and continued without it.
- `npm audit --json`: 14 development/build dependency advisory entries, 5 high and 9 moderate
  (nonzero exit). No broad remediation attempted.
- `npm audit --omit=dev --json`: passed, zero production-dependency advisories.
- No dependency version or lockfile changes.

## Build / Pint

- `npm run build`: passed, Vite 8.1.4, 6 modules, final build 2.44s.
- `php vendor/bin/pint --test`: passed after formatting the two new PHP files.
- `git diff --check`: passed; final staged diff and documentation path checks recorded at handoff.
- Sandboxed Node/build attempts initially could not spawn a child process; the same local
  checks passed with approved process permission. This was not fixed by changing dependencies.

## Production Impact

None. No production access, migration, deployment, push, main/RC1 change or source workbook
use. Release disposition remains NEXT_RELEASE. Local generated assets are not committed.
No source/business logic has been copied from SiteApp.

## Remaining Blockers

1. Backend finishes and supplies a committed integration candidate and its approved contract/
   tests. Do not consume the still-dirty worktree or reproduce its implementation.
2. Reconcile all page adapters with the final route names, payloads, policy and schema; integrate
   only approved commits on a non-deploying feature line. Do not merge whole historical docs
   or unrelated Wald/RC1 streams to obtain individual dependencies.
3. Complete missing safe site source-reference/list binding state, Portal plot summary/products,
   bounded binding lifecycle history and optional safe import counts/replacement fields.
4. Add positive HTTP and full security/mutation tests: create/edit/duplicate names, lifecycle,
   audit persistence, null-org Office, inactive/stale/preview actors, guessed IDs and forged fields.
5. Run integrated full regression and disposable MySQL schema gates; measure database query
   bounds/N+1 behaviour with many synthetic customers/sites/plots/users.
6. Perform browser desktop/tablet/320px mobile, long-content, form validation/loading/error,
   keyboard/focus, drawer/notification and accessible colour/label checks on the finished UI.

## ADMIN-SITE03 Entry Points

After backend integration, authenticate normally as persisted active Office Staff. Do not use
development preview as administration authority. QA must use only disposable synthetic data.

| Destination | GET path / route name |
| --- | --- |
| Customers | `/portal/office/workspace/customers` — `office.workspace.customers.index` |
| Add Customer | `/portal/office/workspace/customers/new` — `office.workspace.customers.create` |
| Customer | `/portal/office/workspace/customers/{customer_uuid}` — `office.workspace.customers.show` |
| Add Site | `/portal/office/workspace/customers/{customer_uuid}/sites/new` — `office.workspace.sites.create` |
| Site Details | `/portal/office/workspace/customers/{customer_uuid}/sites/{site_uuid}` — `office.workspace.sites.show` |
| Site sections | Same Site Details URL with `section=overview`, `plots`, `users`, `source`, `imports` or `audit` |
| Site Import Source Data | Site Details URL + `/import-source-data` — `office.workspace.sites.import` |
| Global Imports placeholder | `/portal/office/workspace/imports` — `office.workspace.imports` |

Customer/site metadata forms use `/edit`; lifecycle confirmation uses `/change-status`.
Customer activity uses `/audit`. The controller holds no mutation logic: `portal.office.*`
JSON mutation route names and ADMIN-SITE02A services/policies are mandatory dependencies.
ADMIN-SITE03 can replace the two placeholder views after its own approval; no demo/upload/commit
control is currently offered. This report does not authorise starting ADMIN-SITE03.

## Files Changed

Exact task files relative to the UI worktree:

```text
app/Http/Controllers/OfficeAdministrationPageController.php
resources/css/app.css
resources/js/app.js
resources/js/office-admin-form.js
resources/views/layouts/partials/portal-sidebar.blade.php
resources/views/office/customers/audit.blade.php
resources/views/office/customers/index.blade.php
resources/views/office/customers/show.blade.php
resources/views/office/form.blade.php
resources/views/office/imports.blade.php
resources/views/office/partials/audit.blade.php
resources/views/office/partials/feedback.blade.php
resources/views/office/partials/filters.blade.php
resources/views/office/partials/status.blade.php
resources/views/office/sites/audit.blade.php
resources/views/office/sites/imports.blade.php
resources/views/office/sites/overview.blade.php
resources/views/office/sites/plots.blade.php
resources/views/office/sites/show.blade.php
resources/views/office/sites/source.blade.php
resources/views/office/sites/users.blade.php
routes/office-workspace.php
routes/web.php
tests/Feature/OfficeAdministrationPresentationTest.php
tests/js/office-admin-form.test.mjs
DECISIONS.md
HANDOVER.md
ROADMAP.md
current_sprint.md
documentation/admin/customer-admin-site02-implementation-2026-09-10.md
```

## Notes

Unrelated root-worktree date-negotiation report edits, `Copy of siteapp1.xlsx`, `output/`,
the backend task's worktree and other feature/release worktrees remain untouched. Historical
reports are preserved. The new top-level status and DEC-058 separate approved scope from
implementation and release evidence. The next manual handoff is the completed backend SHA;
this task has not created a background monitor or automatic integration/deployment.

## Recommendation

Do not begin dedicated full ADMIN-SITE02 QA yet. Review the prepared UI code if useful, then
consume the finished backend, close the contract gaps and execute the integrated gates above.

CUSTOMER-ADMIN-SITE02 partially complete — further implementation required
