# Sprint 2A — Company Test Readiness

**Status:** Backend and UI implementation complete; dedicated QA pending

**Evidence baseline:** `full-site-audit-2026-08-19.md`

## Purpose

Make the Customer Portal substantially easier and safer to test with real company users,
without beginning unrelated product work or claiming production readiness.

Sprint 2A is limited to release integrity, the highest-value usability gaps and accurate
evidence. It does not expand the Customer Portal into SiteApp operational functionality.

## Scope

| Audit item | Sprint 2A outcome |
| --- | --- |
| AUD-001 | A reproducible, automated SQLite migration rollback/reapply regression and a corrected rollback path. |
| AUD-002 | Notification fly-out remains visible, operable and readable at 320px and 390px widths. |
| AUD-003 | High-severity npm findings are triaged against actual use. A compatible fix is tested where available; otherwise a time-bounded, named risk decision is documented. |
| AUD-007 | Notification retrieval failure has a clear error state, retry action and notification-centre fallback. |
| AUD-005 / AUD-008 | Site dashboard and Trash have server-authorised pagination; dashboard has plot search, service/status filters and, only if unambiguous, a date filter. |
| AUD-012 | Lifecycle buttons remain server-protected and are disabled until there is an eligible selection. |
| AUD-013 | The unused welcome view no longer assumes public registration exists. |
| AUD-009 | Current documentation records the audit date, branch/baseline, exact verification results and unresolved release gates accurately. |
| AUD-006 | Implement only if DEC-034 is formally confirmed. |

## Bounded dashboard contract

- The active assigned site remains mandatory for every site-role dashboard request.
- Queries and filters are authorised and scoped server-side; query parameters never grant
  access to another site, customer organisation or request.
- The default list is paginated and ordered newest/relevant first using a documented,
  stable ordering.
- Plot search is a simple customer-facing plot-reference search.
- Service and portal-status filters use the existing supported portal values only.
- A requested-date filter may be added only if it has an explicit, plainly explained
  interpretation. Otherwise it is deferred.
- Development and phase filters are not part of this sprint because the current supported
  projection does not establish those browsing dimensions.
- The sprint introduces no reports, exports, operational planning, SiteApp statuses or
  advanced analytics.
- Pagination must not weaken bulk lifecycle-operation authorisation, confirmation or
  batch traceability.
- Customer-facing Trash is paginated with a stable default order. Its existing seven-day
  visibility and audit-history rules remain unchanged.

## Proposed rejected-call-off resubmission contract (AUD-006)

**Decision gate:** DEC-034 was formally confirmed on 19 August 2026.

When confirmed:

1. An authorised Site Manager, Assistant Site Manager or Finishing Foreman starts
   **Resubmit** from a rejected request visible for the active assigned site.
2. The source request's site, plot and service are shown as context. The original rejected
   request is never edited or overwritten.
3. The user may provide a new requested date and add or update the customer-facing
   submission message.
4. Before persistence, a review/confirmation screen shows the source rejection, the
   previous customer response, and the proposed new request values.
5. On confirmation, the system rechecks active account, customer organisation, assigned
   active-site scope, projected-plot ownership/outstanding state, supported service and
   duplicate-conflict eligibility.
6. A new call-off batch and new `submitted` request are created with lineage to the rejected
   source. The previous rejection and response remain visible in the source history.
7. Approved and withdrawn requests have no resubmit route or action.

This is compatible with the existing domain contract: rejected requests already clear the
active conflict key, and `ResubmitRejectedCallOffAction` is specified. The conflict is
documentation-to-implementation drift: the current UI provides no route, review or
confirmation flow that preserves this lineage. It is not a conflict with a confirmed
business rule.

## Acceptance criteria

1. The supported SQLite migration path passes migrate, rollback and reapply from a
   disposable database, with an automated regression.
2. The notification fly-out is not clipped at 320px or 390px and supports its existing
   accessible keyboard/focus behaviour.
3. Failed notification retrieval cannot be presented as an empty state; retry and centre
   fallback are available.
4. Dashboard browsing and customer-facing Trash are paginated. Dashboard queries remain
   active-site scoped and server-authorised; plot, service and status filters work together
   without cross-site or cross-organisation data disclosure.
5. Lifecycle controls are visually unavailable without eligible selected requests, while
   tampered or direct requests remain rejected server-side.
6. The welcome template cannot create a route error by referencing disabled public
   registration.
7. The exact npm audit result and its remediation or risk decision are recorded after the
   sprint verification run.
8. Documentation distinguishes the controlled company-test scope from outstanding
   production release gates.
9. If DEC-034 is confirmed, an end-to-end resubmission creates a linked new submitted
   request, preserves the source rejection and cannot bypass fresh eligibility checks.

## Backend implementation clarification — 19 August 2026

- Dashboard list contract: active assigned site only; 15 items per page; newest batch
  submission first, then request ID; supported combined query parameters are `plot`,
  `service` and `status`. Paginator links retain filters. Requested-date, development and
  phase filters are intentionally omitted.
- Trash list contract: active assigned site only; unexpired recoverable records only; 15
  items per page; newest Trash timestamp first, then request ID.
- Resubmission routes accept only a source request UUID plus new requested date and
  customer-facing message. Site, plot, service, source status and lineage are derived from
  the authorised source. The server/session confirmation signature binds the source UUID,
  date, message, active site and acting user.
- `ResubmitRejectedCallOffAction` creates the linked request and rechecks all current
  eligibility. No direct source mutation, client-controlled lineage or unconfirmed final
  post is accepted.
- `scripts/verify-sqlite-migrations.ps1` is the repeatable disposable SQLite migrate,
  rollback and reapply process for AUD-001. It is local SQLite evidence only.

## UI implementation clarification — 19 August 2026

- The notification panel uses `fixed inset-x-2` at small widths, switching back to the
  anchored desktop panel at the `sm` breakpoint. Its list area scrolls independently and
  preserves the existing Escape, outside-click and focus-return behaviours.
- A failed notification fetch displays `Notifications could not be loaded.`, `Try again`
  and the notification-centre link. Retry resets the failure state before it fetches again;
  the all-caught-up empty state is withheld while failure is present.
- The dashboard now renders the backend's `plot`, `service` and `status` parameters,
  paginator links and active-filter empty state. Trash renders its 15-item paginator and
  confines selection state to the visible page.
- Rejected-request resubmission uses only the supplied UUID route plus a new requested date,
  customer-facing submission message and confirmation signature. Site, plot, service,
  previous date and rejection response remain read-only customer context.
- The unused welcome Blade view was removed; registration remains disabled.

## Implementation verification — 19 August 2026

- Initial focused UI feature coverage passed: 7 tests and 45 assertions.
- Initial full Pest suite passed: 117 tests and 630 assertions.
- Final QA added the stale-resubmission-action regression; focused UI coverage then passed:
  8 tests and 47 assertions. The final full Pest suite passed: 118 tests and 632 assertions.
- Pint check, production Vite build and `git diff --check` passed.
- Initial browser checks used HTTP because the local Herd certificate was not configured for
  `customerapp.test`. Final QA repaired that local site certificate and verified HTTPS without
  bypassing a warning. At requested 320px and 390px viewports, the panel bounds were
  respectively 8px–297px and 8px–367px within the browser's usable layout width, without
  document horizontal overflow. Escape closed the panel and returned focus to the notification
  bell. The same no-overflow check was repeated at 430px, 768px and 1440px.

## Implementation order

1. AUD-001 integrity correction and regression.
2. AUD-002 and AUD-007 notification behaviour, then small-width/failure-state coverage.
3. AUD-005/AUD-008 bounded dashboard and Trash queries, dashboard filters and pagination.
4. AUD-012 selection-state affordances and AUD-013 template cleanup.
5. AUD-003 npm triage and an approved compatible remediation only where justified.
6. AUD-006 route, review, confirmation and lineage UI only after DEC-034 confirmation.
7. Focused QA and evidence/documentation refresh.

## Responsibilities

| Area | Work |
| --- | --- |
| Backend | Migration rollback correction/test; scoped dashboard and Trash queries, filters and pagination; resubmission integration only after decision confirmation. |
| UI | Responsive notification panel; fetch-failure UX; dashboard browsing, dashboard/Trash pagination and selection states; welcome-template cleanup; approved resubmission screens. |
| QA | Rollback/reapply; 320px/390px visual checks; fetch failure; filter/tenant isolation; dashboard/Trash pagination; lifecycle controls; npm evidence; end-to-end lineage if enabled. |
| Product | Confirm or reject DEC-034; preserve scope and maintain accurate release evidence. |

## Explicitly deferred

- MySQL validation, production configuration, backup/restore, monitoring and deployment;
- physical-device, keyboard-only and screen-reader release evidence;
- amendments, approved-request cancellation/reopen and later customer-facing statuses;
- SiteApp integration, QR flows, email, push, reporting and advanced analytics;
- development/phase filters and any data projection needed to support them.

## Remaining decision before coding

Formal confirmation of DEC-034 is the only product decision required for the scoped
resubmission feature. The rest of Sprint 2A can begin without a new business decision.
If date-filter semantics are not simple and clear, omit that optional control rather than
expand the sprint.
