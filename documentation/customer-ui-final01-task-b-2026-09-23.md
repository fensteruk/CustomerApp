# CUSTOMER-UI-FINAL01 — Task B and combined qualification

Date: 23 September 2026
Result: READY_FOR_INTEGRATION — feature branch only.

## Git baseline and sequence
- Exact base: 1fbb0ff25fd23645bc31007fb6b286924f5bf563.
- Branch: codex/customer-ui-final01.
- Worktree: C:/Users/madas/.codex/worktrees/customer-ui-final01/CustomerApp.
- Task A committed first: 00a4d9a71b879dbc797aed84a09861896af0c872.
- Task B is the separate commit containing this report; its exact SHA is in Git history and the final response.
- Original-checkout Cavity Closer changes were preserved.
- No push, main merge, deployment or production changes.

## Task A
See customer-ui-final01-task-a-2026-09-23.md for its seven-file inventory, bounded read-only queries, browser evidence and focused 50-test / 279-assertion result.
Customer Detail now prioritises identity, real counts, sites, access and existing response activity. Inactive customers receive reactivation guidance instead of Add site. Destructive actions remain behind their original review/confirmation routes.

## Task B — shared visual shell
- Office navigation grouped into Workspace, Customers & access, and Tools.
- Existing route destinations, role checks, policy gates and Wald availability predicates retained.
- Current-page aria-current and text labels retained across Dashboard, Customers, Users, Imports, Amendments, Review Requests and Settings.
- Page-specific sidebar slots render once in a compact main-content disclosure. Site Users see Switch site & filters; Office requests show Page filters. Existing forms, field names, methods and actions are retained.
- Main navigation contains no page-specific filters. The mobile trigger consistently says Menu; active-filter counts are beside page tools.
- Account name, role and existing CSRF-protected logout form grouped at the sidebar foot. Navigation scrolls independently; the account area can also scroll at short heights.
- Mobile menu keeps existing focus containment/Escape behavior and additionally makes background content inert while open.
- Added reusable x-page-header with title, description, optional context and actions. Adopted on Customers list and Customer Detail without changing their content or card arrangements.
- Shared pale background, card borders/radii, page spacing, headings, buttons, disabled/destructive treatments, form controls, alerts, focus and pagination sizing aligned.
- Existing page-specific icon, table overflow and status-text patterns reviewed and retained.
- Added safe-area bottom padding, header scroll clearance, wrapping and reduced-motion treatment.
- Shared topbar, navigation and notification-button targets are at least 44px. Text accompanies status colour.

## Verification

| Command/check | Result |
| --- | --- |
| Task A focused features | 50 passed; 279 assertions |
| Shared shell, sidebar, notification UI and Customer Detail features | 39 passed; 289 assertions |
| php artisan test | 1,866 passed; 89 skipped; 10,131 assertions; 173.054 seconds |
| php vendor/bin/pint --test | Passed |
| composer validate --strict | Passed |
| composer audit | No security vulnerability advisories |
| npm run build | Passed; final Vite build 1.37 seconds |
| npm audit --omit=dev | Zero vulnerabilities |
| git diff --check and staged whitespace validation | Passed before commit |

The full suite reported 1,955 tests including skips. Skipped tests were not executed and are not claimed as passed. After the full suite, a final shared touch-target/account-wrap refinement was checked with the final build and 39-test focused pass (3.450 seconds). No dependencies or lockfiles changed.

### Browser evidence
Isolated localhost preview, disposable fictional SQLite data and fictional Office/Site Manager accounts only. Production browser tabs were not used for development or changed.

Office Dashboard, Customers, Users, Imports, Amendments, Review Requests and Settings were checked at all four requested sizes:
1366x768, 768x1024, 390x844, 320x740.
Each had its correct current-page navigation label and document width no greater than viewport client width.

Site User dashboard and Task A Customer Detail were also checked at all four sizes. Verified long names, search, empty/inactive customer guidance, whole-card keyboard navigation, user-safe navigation and retained site tools.

Verified 320px menu opening, initial close-button focus, Tab wrapping from logout to the brand link, Escape-to-Menu focus return and removal of background inert state. At 768px the drawer exposes dialog/aria-modal semantics. At 1366x500, the navigation region scrolls (524px content in a 309px region) and logout stays vertically reachable.

Verified native page-tools keyboard expansion/collapse and readable light-theme controls. Final tablet header measurements were 44px for Menu, brand and notification targets. Browser console check returned no warnings/errors.

The sticky top header was reviewed with content clearance; page tools remain in normal flow and introduce no fixed bottom obstruction. This baseline has no persistent import action-bar component to qualify. Any action bars introduced by the concurrent import-detail task need combined integration QA; this task adds no global sticky-position overrides.

No physical-device, screen-reader or automated accessibility audit is claimed. Temporary viewport override and browser tab were cleaned up; the local preview server was stopped.

## Exact Task B files
1. resources/css/app.css
2. resources/views/layouts/portal.blade.php
3. resources/views/layouts/partials/portal-sidebar.blade.php
4. resources/views/layouts/partials/page-tools.blade.php
5. resources/views/components/page-header.blade.php
6. resources/views/office/customers/index.blade.php (header component adoption only)
7. resources/views/office/customers/show.blade.php (header and shared destructive button adoption)
8. tests/Feature/CustomerSidebarWorkspaceTest.php (updated presentation naming)
9. tests/Feature/PortalSharedShellTest.php
10. documentation/customer-ui-final01-task-b-2026-09-23.md

No page-specific User Detail, Settings, Review Requests, Notifications, support or Import Detail files were edited. Task B contains no controller, service, route, permission, domain or database changes.

## Integration notes
Apply Task A followed by Task B. Other concurrent page branches should keep the common shell and may adopt x-page-header where suitable.
Legacy sidebar slots are supported as page tools, so existing filters are not silently discarded. Pages that provide their own in-page filters should omit the old slot to avoid a second filter presentation after integration.
Preserved approved page-specific layouts are not forced into one identical template.
No shared dependency blocks this branch.

Business logic changes: NONE.
Migrations: NONE.
Push: NO.
Deployment: NO.