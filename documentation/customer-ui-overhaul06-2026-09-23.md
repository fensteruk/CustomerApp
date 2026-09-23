# CustomerApp Plot + Live Amendment Report

Date: 23 September 2026
Task: CUSTOMER-UI-OVERHAUL06

## Overall Result

READY_FOR_INTEGRATION. Feature branch only. No production changes.

## Base SHA

`f94a750d820d5a635d6e4fcac87ef3bd02079fc2` — the exact requested clean redesign baseline.

Worktree: `C:\Users\madas\.codex\worktrees\customer-ui-overhaul06\CustomerApp`
Branch: `codex/customer-ui-overhaul06`
The original checkout and its uncommitted work were preserved. No configurable
lead-time branch was merged. The final commit SHA accompanies the task handoff.

## Visual Reference

Inspected `C:\Users\madas\Downloads\plotview.png`. Applied its plot-first header,
Windows/Doors totals, service cards, amendment entry, history and secondary source
information hierarchy. Retained the four approved services and current dictionary;
no unsupported sync claims, operational stages or response-time promises were added.

## Existing Amendment Architecture

Extended the existing RequestCallOffAmendmentAction, eligibility and rules, existing
date-negotiation/proposal records, status histories and post-commit event. No second
amendment system. Existing Office agreement/alternative actions remain authoritative.

## Plot Workspace

Plot and site context, recorded Windows/Doors totals, four independent service cards,
current requested/agreed date, amended timestamp and clear actions. Product detail is
available in a disclosure. Recent amendments are paginated five at a time. Source
completion stays authoritative. Totals reuse CustomerAppDictionary v8; no product
mapping changes. Unknown/unresolved totals render as unavailable, without fabrication.

## Effective Requested Date

`CallOffRequest::effectiveRequestedDate()` resolves the newest non-withdrawn amendment
with a requested date; otherwise the original request date, then legacy batch fallback.
A Fenster alternative or agreement never becomes a customer requested date. Original
`requested_date` stays unchanged. The `latestEffectiveAmendment` one-of-many relation
supports bounded eager loading. Existing Office review list loading adds this relation;
its template is unchanged, and query count is constant for one versus ten cards.

## Awaiting Fenster Amendments

An assigned authorised Site User can amend immediately before any Office decision.
Awaiting Site User and pending amendments also support correction. A new amendment
moves the same request to the existing `amendment_on_hold` lifecycle value so existing
Office attention and decision paths work. Without a prior agreement, customer wording
is Awaiting Fenster — Amended (or Awaiting Site User — Amended for an alternative).
`prior_agreed_date` is null when no date had been agreed; do not invent an agreement.

## Date Agreed Amendments

Preserved. The earlier agreement remains stored, is shown on hold, and is not an active
confirmed date while the new request is reviewed. Existing Office acceptance/alternative
negotiation, early-date acknowledgement and completion precedence remain intact.

## Multiple Amendments

Tested original submission at 06:00, amendment at 07:00 and second amendment at 07:30.
Dates are 1, 5 and 7 October 2026: all working days (3 October in the illustrative
brief is a Saturday). One request, one active amendment, latest effective date, all
three history events retained. Old pending cycles/proposals become Superseded; date
payloads and attribution remain unchanged. Stale reviews and stale Office/proposal
references fail. MySQL worker races verify one winner from the same reviewed revision.

## History / Audit

Original and amendment dates are visible in customer-safe history. Each amendment
retains actor/name/role, time, reason and request context. Existing immutable history
stores `amendment_uuid`, `amendment_requested_date`, `effective_requested_date`,
`prior_requested_date`, `superseded_negotiation_uuids`, `early_date_reason`,
`normal_earliest_date`, `is_early_date_exception`, `working_days_early` and actor fields.
Existing negotiation lifecycle status changes are preserved; history rows are not
rewritten. No backfill or fabricated history for older requests.

## Early-Date Behaviour

Exact baseline amendment policy: four weeks, five with positive BF, for all services.
Initial Cavity Closer 15-working-day policy remains separate. Existing future working
day/six-month limits, configured change reasons, Other explanation and three-working-day
urgent warning remain. Separate Early Date Reason is required server-side for an early
amendment at both review and locked submission. Review explains the working-day shortfall
and earliest date. Normal amendments require no Early Date Reason. Office acknowledgement
is still required; submission does not approve an exception.

## Office Notification Evidence

Each committed amendment uses the existing CallOffAmendmentRequested event and a distinct
cycle UUID. The 06:00/07:00/07:30 test verifies two Office amendment notifications and one
current dashboard amendment attention item. Rollback emits no new notifications. Existing
recipient authorisation and idempotency tests pass. No Office Amendments workspace UI added.

## Authorization

Current active Site User, customer and assigned-site checks remain server-side, including
revalidation inside the locked action. Cross-customer, wrong-site, unassigned, revoked,
inactive and Office-initiated requests are denied. All three external Portal roles retain
the existing permission model. Source-completed work cannot reopen, including after reversal.
Locking order remains service → request → cycles → proposals → history; final submission
uses bounded transaction retries. Request conflict identity remains active.

## RedZebra Boundary

No writeback, API or source mutation was added. Site User amendment → Office review/manual
RedZebra update → later controlled master import. Source completion rules stay authoritative.

## Migration

NONE. Existing negotiation, proposal and JSON history persistence is sufficient. No schema,
dependency lockfile, production database, production import or production configuration change.

## Integration Contract

- Use `effectiveRequestedDate()` wherever the current Portal requested date is required.
  Eager-load `latestEffectiveAmendment` for lists, or complete `dateNegotiations` for history.
  Do not pass a filtered partial `dateNegotiations` collection to the resolver.
- Original request/batch date fields remain historical. Do not overwrite them to reconcile.
- Pending live corrections still have purpose `amendment`, status `open`, active key
  `amendment:{requestId}`, request status `amendment_on_hold`. Prior agreement may be null.
  Use the shared date view status label rather than assuming every amendment had an agreement.
- The latest amendment UUID is mandatory for an Office decision. A later submission
  supersedes old cycles and pending proposals. Re-authorise and use the existing locked actions.
- Find the submitted Early Date Reason in the AmendmentRequested history after_state,
  keyed by `amendment_uuid`; absence on older records means not recorded, not invented text.
  The shared date view also exposes `amendmentEarlyReasons` keyed by cycle UUID.
- Wald reconciliation must compare with current effective Portal truth while preserving
  original dates, amendments and history. Do not add writeback or overwrite Portal dates.
- The concurrent Site workspace should adopt shared date-view wording for pre-agreement
  amendments; its existing aggregate On Hold mapping was not redesigned here.
- No concurrent Site workspace, Office Amendments or Wald Reconciliation page files changed.
  ReviewRequestsController has only the necessary eager-load addition for the new domain date.
- The approved decision is appended under the task identifier to avoid decision-number
  collisions across branches with the same baseline. Reconcile documents during integration.

## Responsive / Accessibility

Local browser verification with fictional data at 1366×768, 768×1024, 390×844 and 320×740.
Plot and amendment form had no horizontal overflow: document widths were 1351, 753, 375
and 305 respectively (vertical scrollbar excluded). Four services, latest date/status and
amend action remain usable. Amendment target is 48px high; other service links are about
49.6px. Keyboard Shift+Tab/Enter verified, with visible blue focus ring. Date/reason fields
have associated labels. Early reason appears and becomes required when applicable; normal
date hides it and removes required. Early review and actual submission succeeded at 320px,
and retained history/updated Plot date were verified. Status is textual, not colour-only.
Temporary viewport override was reset and QA tabs closed. No production browser actions.

## Tests

Final passing evidence:

| Check | Result |
| --- | --- |
| `php artisan test --compact` | 1,802 passed, 89 skipped, 9,607 assertions; 1,891 total |
| Focused Plot/amendment regression | 117 passed, 1,189 assertions |
| Product/queue/live-amendment corrective check | 53 passed, 268 assertions |
| Disposable MySQL 8.4.11 amendment/schema/concurrency suites | 147 passed, 2,210 assertions |
| Final MySQL effective-date and Office queue check | 25 passed, 139 assertions |
| `vendor/bin/pint --test` | PASS |
| `composer validate --strict` | PASS |
| `composer audit` | PASS, no security advisories |
| `npm run build` | PASS |
| `npm audit --omit=dev` | PASS, 0 vulnerabilities |
| `git diff --check` | PASS |

SQLite skips include environment-specific MySQL gates and optional private-workbook tests;
they are not claimed as passed. Relevant MySQL locking/schema/history gates ran separately
in a new disposable local container/database. Four explicit UI06 worker cases cover
competing unagreed/repeated amendments and source completion in both commit orders.
The initial full run exposed old product-section markup assumptions, old amendment date
expectations and one query-count regression. All were resolved before the final passing run;
no concurrency assertions were weakened. Development-only npm install advisories were not
addressed by dependency upgrades in this bounded task; the required production audit is clear.

## Files Changed

All paths below are relative to the worktree recorded above:

- `app/Actions/CallOff/DetermineCallOffEligibilityAction.php`
- `app/Actions/CallOff/RequestCallOffAmendmentAction.php`
- `app/Http/Controllers/CallOffAmendmentController.php`
- `app/Http/Controllers/PlotDetailsController.php`
- `app/Http/Controllers/ReviewRequestsController.php`
- `app/Models/CallOffRequest.php`
- `app/Services/CallOffAmendmentRules.php`
- `app/Services/CallOffDateViewService.php`
- `resources/views/portal/call-offs/amendments/create.blade.php`
- `resources/views/portal/call-offs/amendments/history.blade.php`
- `resources/views/portal/call-offs/amendments/review.blade.php`
- `resources/views/portal/call-offs/show.blade.php`
- `resources/views/portal/plots/show.blade.php`
- `tests/Feature/LiveCallOffAmendmentsTest.php`
- `tests/Feature/OfficeQueueCardRequestValuesTest.php`
- `tests/Feature/Sprint3eMysqlConcurrencyTest.php`
- `tests/Feature/Sprint3fDateAmendmentsTest.php`
- `brief.md`
- `DECISIONS.md`
- `current_sprint.md`
- `HANDOVER.md`
- `ROADMAP.md`
- `documentation/customer-ui-overhaul06-2026-09-23.md`

## Commit / SHA

Dedicated feature branch `codex/customer-ui-overhaul06`. The commit containing this
report is recorded by SHA in the final task response and Backend handoff.

## Push

NO.

## Deployment

NO.

## Recommendation

Integrate this domain contract before final qualification of Office Amendments and Wald
Reconciliation. Resolve concurrent documentation/read-model changes deliberately and run
the combined regression gate. Production release requires its own authorised qualification.

CustomerApp Plot workspace and live amendments complete — ready for integration
