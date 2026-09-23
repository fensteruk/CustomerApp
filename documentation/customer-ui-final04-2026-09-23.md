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
