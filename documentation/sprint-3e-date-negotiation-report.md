# Sprint 3E — Date Agreement and Alternative-Date Negotiation

**Date:** 21 August 2026  
**Status:** Local backend and UI implementation complete; ready for dedicated Sprint 3E QA. No deployment performed.

## Scope delivered

Sprint 3E implements the Customer Portal's individual-request date conversation without
adding SiteApp operational workflow, a production deployment, source transport, email,
amendments, attachments, calendar/PDF, reminders or a new customer-screen design.

- Fenster Office Staff may agree the requested date while a request is `awaiting_fenster`.
- They may instead propose a future weekday alternative, which moves the request to
  `awaiting_site_user` (the existing portal enum name for Awaiting Customer).
- Any active Site Manager, Assistant Site Manager or Finishing Foreman assigned to the
  request site may accept or reject that outstanding alternative. Rejection requires a
  customer-visible reason and returns the request to `awaiting_fenster`.
- Agreement moves the request to `date_agreed` and records its agreed date. Date Agreed
  stays conflict-active, so a second active call-off cannot be created for the same
  plot/service.
- Legacy `approved` records remain untouched and are customer-presented as Date Agreed.

## Integrity and security controls

Every agreement, proposal and response locks the current request and relevant proposal/
negotiation rows in a transaction, then reauthorises and validates current status before
writing. Ordered proposals preserve both the customer-requested date and each alternative;
rejected/superseded records are retained instead of being overwritten.

An early requested date needs an explicit Office acknowledgement. Alternative dates are
server-validated as future Monday–Friday dates and consult `HolidayProvider`. The bound
provider intentionally has no UK bank-holiday dataset yet, so weekday-only enforcement is
the documented temporary behaviour until Fenster approves a maintained provider and owner.

Source completion wins over portal negotiation. It closes open negotiations, supersedes
unanswered alternatives and actions reject a stale agreement or customer response when the
projected service is already completed. Withdrawal remains permitted only while awaiting a
date decision, never once a date is agreed.

## HTTP contract

Authenticated, server-authorised endpoints are consumed by the Office and customer views:

| Endpoint | Actor | Purpose |
|---|---|---|
| `POST /portal/review-requests/{request}/agree-requested-date` | Global Office Staff | Agree requested date; early acknowledgement is required where applicable. |
| `POST /portal/review-requests/{request}/alternative-date` | Global Office Staff | Propose a customer-visible alternative date. |
| `POST /portal/call-offs/{request}/alternative-dates/{proposal}/accept` | Assigned Site User | Accept one outstanding alternative. |
| `POST /portal/call-offs/{request}/alternative-dates/{proposal}/reject` | Assigned Site User | Reject one outstanding alternative with a reason. |

The action layer owns all authorisation and state checks; route binding and hidden controls
are not treated as security boundaries.

## UI delivered

- The Office queue defaults to `Awaiting Fenster` when such work exists, with a legacy
  Submitted fallback for a legacy-only queue. Its cards distinguish Fenster action,
  customer response, Date Agreed and Completed records without treating Awaiting Customer
  as an Office action.
- Office request detail exposes Accept Requested Date, the required early-date
  acknowledgement, and Propose Alternative Date. Rejected alternatives remain visible to
  Office Staff with the customer reason and responder.
- Site Users reach a dedicated request page from the affected Plot Details service card
  and from new notification safe-open links. It explains who needs to act, shows original
  and proposed dates, and provides clear Accept Date and Reject Date actions.
- The customer-safe timeline retains repeated proposals, response dates/times, users and
  roles, messages and rejection reasons. It does not render internal reasons, snapshots,
  conflict keys or persistence identifiers.
- Awaiting Fenster and Awaiting Customer remain `Called Off — Awaiting Date` in the plot
  overview. Date Agreed and source Completed take precedence and remove stale actions.
- Withdrawal is exposed while awaiting Fenster or a customer response, but never after
  Date Agreed. No amendment control was introduced.

## UI corrections found during verification

- Added an explicit customer request-details route and avoided its collision with the
  existing static Trash route.
- Disabled scoped nested binding for the existing proposal endpoints: the action layer
  already locks and verifies that a proposal belongs to the request, while Laravel could
  not infer that indirect relationship from `CallOffRequest`.
- Updated notification visibility and safe-open to recognise the detailed site-user target,
  while retaining the existing active-account, role and current-site-assignment checks.

## Notifications

Post-commit in-app notification events are now generated for date agreement, alternative
proposal, acceptance and rejection. The original submitting Site User receives agreement
and proposal notifications. Active global Office Staff receive acceptance/rejection
notifications. Event keys include the proposal UUID where repeats are valid, making the
proposal loop idempotent. Customer payloads exclude internal reasons.

## Focused verification

`tests/Feature/Sprint3eDateNegotiationTest.php` and
`tests/Feature/Sprint3eDateNegotiationUiTest.php` passed locally on 21 August 2026:

- 16 focused tests, 89 assertions;
- normal and early-date agreement evidence;
- three rejected alternatives followed by acceptance;
- global Office and assigned-site customer authority;
- stale source-completion response rejection;
- repeated-response protection;
- withdrawal eligibility; and
- weekend/configured-holiday validation.

Final local verification passed on 21 August 2026: SQLite `migrate:fresh --seed`, the
complete Pest suite (187 tests, 973 assertions), Pint, `git diff --check` and the production
Vite build. Browser scenario evidence covered early-date request → Office alternative
proposal → customer Plot Details/request screen → acceptance → Date Agreed. At 320, 390,
430, 768 and 1440px the customer request document did not overflow horizontally; no browser
console warnings or errors were captured. Keyboard-visible focus, native required fields,
semantic headings/lists, labelled controls and large touch targets were inspected; no
assistive-technology pass was performed. MySQL lock/concurrency rehearsal,
holiday-provider ownership/data, production migration reconciliation and release approval
remain separate blockers.
