# CUSTOMER-UI-FINAL01 — Task A: Customer Detail
Date: 23 September 2026
Status: READY_FOR_INTEGRATION; feature branch only.

Base: 1fbb0ff25fd23645bc31007fb6b286924f5bf563.
Branch: codex/customer-ui-final01.
Worktree: C:/Users/madas/.codex/worktrees/customer-ui-final01/CustomerApp.

## Completed
Customer identity and active state, all-customer site/active-site/plot totals and Office-response request count.
Sites are the primary body, with inline search/status, pagination and large accessible Open site links.
Active customers expose Add site; inactive customers receive reactivation guidance.
Six users per page show roles, assignments and explicit access-attention labels.
Up to three actionable requests/current amendments link to existing review routes.
Administrative lifecycle, permanent delete and demo purge entry points retain existing safeguards in a secondary disclosure.

New bounded read-only CustomerDetailWorkspaceQueryService uses the existing administration authorization and query services.
Attention counts requests (not plots), excludes trashed/source-completed and mismatched-site records, and uses the latest effective amendment response.
Office historical visibility includes inactive-site requests; this scope is explained beside the activity list.
Site card attention uses page-bounded grouped counts; user access counts are eager-loaded.
No business logic, permissions, ownership, state transitions, routes, database schema, migrations or dependency changes.

## Checks
Focused CustomerDetailWorkspaceTest, OfficeCustomersWorkspaceTest and OfficeAdministrationPresentationTest:
50 passed, 279 assertions, 3.347 seconds.
New coverage includes active/inactive/empty/multi-site, access summaries, amendments, source completion, pagination, query growth and external-role/guest/inactive-Office denial.
Pint applied to changed PHP; final formatting/whitespace gates passed before commit.
npm run build passed.
Browser QA on isolated localhost fictional SQLite data at 1366x768, 768x1024, 390x844 and 320x740: no horizontal overflow, readable totals and long names.
Verified keyboard whole-card navigation, search retaining all-customer totals, active empty and inactive empty states.
No real-device or assistive-technology audit claimed.
Full regression qualification follows Task B.

## Files
- app/Http/Controllers/OfficeAdministrationPageController.php (Customer action/import only)
- app/Services/CustomerDetailWorkspaceQueryService.php
- resources/views/office/customers/show.blade.php
- resources/views/office/customers/detail-styles.blade.php
- tests/Feature/CustomerDetailWorkspaceTest.php
- tests/Feature/OfficeAdministrationPresentationTest.php (fixture and Add site casing)
- documentation/customer-ui-final01-task-a-2026-09-23.md

No concurrent page-specific files or original-checkout user changes were touched.
Task A is committed independently before Task B begins. Exact SHA is recorded in Git history and final report.
Push: NO. Deployment: NO.