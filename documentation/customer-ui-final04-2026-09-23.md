# Review Requests and support screens — 23 September 2026

Base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.
Branch: `codex/customer-ui-final04`, isolated worktree
`C:\Users\madas\OneDrive\Documents\customerapp\final04-support-20260923`.
No push or deployment is authorised by this work.

## Task A — Review Requests

The ordinary-request read query excludes requests with a latest effective
amendment; direct details retain the current effective date and the existing
amendment UUID form fields. Customer, site, service, search and requested-date
filters combine before pagination, with the legacy batch date fallback intact.
Customer context and early-date indicators are visible in the scan-friendly
list. Details expose the current proposal and jump links, place the existing
decision forms before the timeline, and retain original history and actors.
Transitions, authorization, lead-time calculations and stale guards are unchanged.

Focused tests: 51 passed / 350 assertions across OfficeQueueCardRequestValues,
OfficeStaffReviewRequests, LiveCallOffAmendments and CavityCloserCallOff.
The amended-list expectation was deliberately changed to assert queue separation;
direct detail continues to assert the latest date. New tests cover combined filters
and the current decision UUID. Existing live-amendment tests exercise stale actions.

Real browser: list plus early Awaiting Fenster, alternative, Date Agreed and
amended details at 1366x768, 768x1024, 390x844 and 320x740. All 20 page/viewport
checks had zero horizontal overflow. Search returned the one matching early
request. Keyboard Tab displayed a visible focus outline. All fixtures were local
and fictional. Pint and whitespace checks passed. Initial view tests needed the
new worktree's generated Vite manifest; they passed after building the assets.

Task A commit: `dcb7f94197ebfbee4424aa8a6f1f9be8ed450091`.

## Task B — Support screens

Page-scoped presentation brings Notifications, customer/site forms, user forms,
login, password recovery and reset into a consistent card/form treatment. User
forms group identity, role/site access and sign-in/status, with explicit global
Office access and customer-before-site guidance. Customer/site forms explain
the next step and source-binding separation. Existing validation, submissions,
authorization and assignment rules are unchanged. No separate account/profile
route exists in this candidate.

Notifications now show visible read/unread text in the tray and explicit unread
totals and destination context in the centre. Empty copy describes current date
workflows. A browser-proven narrow-phone tray issue was fixed locally in the bell
partial: the scrollable list shrinks between the header and footer, keeping View
all notifications reachable. Shared shell templates, global CSS and JavaScript
were not edited. The bell partial is the only shared presentation fragment changed.

Browser checks used 1366x768, 768x1024, 390x844 and 320x740 for Notifications,
Add Customer, Add Site, Add User, customer status confirmation, login, forgot
password and reset-password forms: 32 page/viewport checks, all without horizontal
overflow. The tray was open at all four sizes; its footer stayed inside each
viewport. Escape returned focus to the bell. User role selection correctly
switched from customer/site fields to global Office help, and Tab reached the
next visible field with a focus outline. Notification mark-read updated its label
and unread count; its direct destination opened the matching request. Blank
customer submission was blocked by required validation; creating a fictional
customer navigated to its new record and the appropriate no-sites state. Password
reset was render-only with a fictional token; no email or password change was sent.
Browser console reported no warnings/errors after the final checks.

## Qualification

- Task B focused existing tests: **82 passed, 390 assertions** (PortalNotificationUi,
  PortalNotifications, OfficeUserAdministration, OfficeAdministrationPresentation,
  SecureAccessFoundation).
- Final full SQLite suite: **1,846 passed, 89 skipped, 9,972 assertions**, 1,935 total.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: valid.
- `composer audit`: no security advisories; TLS verification retained.
- `npm run build`: passed.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

The test commands used the installed PHP 8.4 executable. MySQL concurrency
qualification from the baseline remains historical evidence; this UI task did
not alter transactions, stale guards or schema and did not rerun MySQL races.
The first preview startup pointed at an empty fixture file; it was corrected to
the isolated fictional database before browser QA. No real customer data was used.

Changed Task A files: ReviewRequestsController; review-requests index/show/styles;
OfficeQueueCardRequestValuesTest; this report. Changed Task B files: auth
login/forgot-password/reset-password; notification-bell partial; office form;
office users form; office support-styles partial; notifications index; this report.
All paths are under the isolated worktree. Existing tests verify authorization,
early-date rules, current amendment UUID and stale rejection. No business logic,
migration, lockfile, notification creation or production configuration changed.

Result: **READY_FOR_INTEGRATION**. Task A and Task B are separate local commits.
No push or deployment. The original checkout's unrelated Cavity Closer edits
remain untouched. Temporary preview data is disposable and removed after QA.
