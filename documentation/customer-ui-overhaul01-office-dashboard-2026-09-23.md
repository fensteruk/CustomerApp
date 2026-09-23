# CUSTOMER-UI-OVERHAUL01 — Office dashboard redesign

Date: 23 September 2026.
Status: READY_FOR_MERGE — feature branch only, not pushed or deployed.
Branch: `codex/customer-ui-overhaul01`.
Baseline: `04840b6b5964c59ef13a2be71eb236c2fe496110` (fetched origin/main).
Commit: this report is included in the bounded implementation commit; use Git history for its exact SHA.

## Completed

Office `/dashboard` now renders an overview instead of redirecting to Review Requests.
External user routing and the existing Review Requests route remain intact.
Reference inspected: `C:\Users\madas\Downloads\dashboardpage.png`.

The page follows the reference's pale background, greeting/date, prominent rose attention area,
three category cards, four summary cards, paired activity panels and quick actions.
The existing application shell is retained. Annotation bubbles and unsupported sample metrics/actions
from the image are omitted. More detail is shown for actual date amendments.

### Needs attention and the three categories

- Amendments: existing open date amendments awaiting Office, newest first, maximum two examples.
  Each shows plot, service, site, previous agreed date, requested date, requester and timestamp.
  Amendments awaiting a Site User response are excluded from the Office attention count.
- Import reviews: current uploads requiring review, clarification, binding or failure attention.
  Superseded revisions are excluded. An upload is counted once, rather than once per selected site.
  Import evidence and actions respect the existing environment/application gates and preview-account restriction.
  Disabled access is displayed as unavailable, not falsely as zero outstanding imports.
- Call-off actions: existing submitted or Awaiting Fenster requests, maximum two newest examples.

The categories summarize existing work; they do not create new notification records or unread semantics.
The broader future amendment domain is not implemented. Existing date-amendment records are supported
now, with honest empty states when none exist.

### Summaries and activity

Active sites counts effectively active sites under active customers.
Open requests counts requests still awaiting a response/date agreement and excludes trashed records.
Pending amendments includes open date amendments awaiting either Office or a Site User.
Last applied import requires an existing application receipt; an upload alone never qualifies.

Upcoming activity shows up to five future agreed dates with site, plot, service, status and a detail link.
Existing legacy approved-date fallback is preserved; source-completed services are omitted.
Recent activity combines up to five existing request-history and import-receipt events, newest first.
Recorded actor names are retained. Private reasons, workbook filenames and storage paths are not displayed.

Quick actions lead to existing Review Requests, Imports (when available), Customers and Users pages.
The existing portal logo returns to the overview. No new shared sidebar item was added.

## Responsive and accessibility evidence

Local browser QA used an isolated SQLite database and fictional test records only.
No production data or accounts were changed.

Inspected desktop 1366x768, tablet 768x1024, mobile 390x844 and narrow mobile 320x740.
Measured document width equalled scroll width at each size (no horizontal overflow).
Cards and activity panels stack on mobile; all dashboard links measured at least 44 pixels high.
Checked semantic heading structure, accessible count labels, textual statuses and visible keyboard focus.
Keyboard Tab moved through actionable items with a visible blue focus outline.
Verified the amendment category opens the existing Amendment On Hold filter and the import example
opens its real supervised review page. Checked lower activity panels and quick actions at 320 pixels.
A narrow-screen empty-state wording fix and duplicate Plot-prefix fix were made after inspection.

## Business logic / migration / database impact

NONE. No permissions, transitions, eligibility, lead times, Wald semantics, mappings, schema or migrations changed.
Queries are read-only and bounded. Existing domain services are used only by isolated test fixtures.
No production writes, push or deployment occurred. MySQL-specific concurrency qualification was not run
for this presentation-only change.

## Shared/global ownership

No shared layout, navigation component, global stylesheet, route definition or other redesign page changed.
The existing DashboardController changes only the Office rendering branch; external routing stays unchanged.
Two existing access test files now assert the Office overview rather than the old redirect.
This isolated worktree preserves unrelated uncommitted work in the main checkout:
CallOffDateViewService.php, portal/review-requests/show.blade.php and CavityCloserCallOffTest.php.

## Tests and checks

- Focused dashboard/access suite: 40 passed, 204 assertions.
- `php artisan test`: 1,755 passed, 85 skipped, 9,316 assertions, 139.836 seconds.
- Final dashboard tests after label changes: 7 passed, 73 assertions.
- `vendor/bin/pint --test`: passed, including after final PHP changes.
- `composer validate --strict`: passed.
- `composer audit`: no security vulnerability advisories found.
- `npm run build`: passed.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

The Windows PHP runtime required a temporary local configuration enabling the installed extensions
for the CLI and child test processes. Dependency locks were not changed. TLS checks were not disabled.
Temporary runtime files, SQLite data, dependencies and build output are not part of the commit.

## Exact task files

- app/Http/Controllers/DashboardController.php
- app/Services/OfficeDashboardQueryService.php
- resources/views/office/dashboard/index.blade.php
- resources/views/office/dashboard/styles.blade.php
- resources/views/office/dashboard/icon.blade.php
- tests/Feature/OfficeDashboardPresentationTest.php
- tests/Feature/AuthenticatedSiteDashboardTest.php
- tests/Feature/SecureAccessFoundationTest.php
- documentation/customer-ui-overhaul01-office-dashboard-2026-09-23.md

## Integration recommendation

Review and integrate the bounded feature commit with the concurrent page redesigns.
The shared shell is deliberately preserved. Production deployment requires a separate authorized release.
