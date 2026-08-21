# Sprint 3D — Bulk Call-Off Implementation Report

## Result

Sprint 3D supplies the secure backend contract and its initial UI for a multi-plot,
multi-service customer call-off. It remains a Customer Portal request workflow: it reads
only local projected source facts and does not recreate SiteApp operations, planning or
write-back. Dedicated final UI/browser QA passed on 21 August 2026 after two contained
corrections: review rows now retain selected plot/fixed service order, and per-request
submission notifications now recognise Awaiting Fenster and use the individual request
service/date. See `documentation/sprint-3d-bulk-call-off-qa-report-2026-08-21.md`.

## Route and controller contract

All routes require authentication, an active account and an active assigned site. They are
available to Site Manager, Assistant Site Manager and Finishing Foreman only; Office Staff
is denied before the controller action.

| Route name | Method | Purpose |
| --- | --- | --- |
| `portal.call-offs.create` | GET | New Call Off entry and plot/service/date selection. `plots[]` may contain only active-site plot UUIDs. |
| `portal.call-offs.dashboard-selection` | POST | Validates page-scoped dashboard plot UUIDs, then starts the same flow. |
| `portal.call-offs.matrix` | POST | Builds the authoritative selection matrix. |
| `portal.call-offs.review` | POST | Applies exclusions and early reasons, then creates the signed review held server-side. |
| `portal.call-offs.store` | POST | Accepts only the confirmation signature and persists the reviewed submission. |

`NewCallOffController` is deliberately thin. `BuildCallOffMatrixAction` produces the
matrix; `CallOffSubmissionWorkflow` owns reviewed-session binding and revalidation; and
`SubmitMultiCallOffBatchAction` is the sole persistence route.

## Matrix and UI handoff contract

The customer-safe review payload contains the customer-visible site context plus rows with:

- `key`, `plot_uuid`, `plot_reference`, `service`, `requested_date`;
- `included`, `available`, and a safe unavailable `reason`;
- `normal_earliest_date`, `is_early_exception`, `early_reason`;
- positive-quantity `products` as `{code, quantity}`; and
- the customer message and total `request_count`.

It never renders database IDs, conflict keys, source reconciliation state or private
reasons. A combination is included by default when available. Posting its `key` in
`excluded[]` removes it. Unavailable rows must remain shown, disabled/excluded, with their
safe explanation; the client must not silently omit them.

The customer selects a requested date per service, which is applied to each selected plot.
The matrix calculates the normal earliest date independently for each plot/service. A
weekday no more than six months ahead is required. If an included row is earlier than its
normal date, `early_reasons[row.key]` is mandatory. Excluding that row removes the reason
requirement. Positive BF quantity produces the five-week normal window; other rows use
four weeks.

## Confirmation, replay and stale state

The canonical review is stored in the server session, signed with the application key and
bound to the current user and active site. The confirmation page posts only its 64-character
signature. The final endpoint consumes the session record before rebuilding the matrix from
the current database facts. A direct, modified, stale, cross-site, reassigned, inactive or
replayed submission therefore cannot persist a request. Any change to selected rows,
availability, product-derived lead time, source completion/missing state or conflict causes
safe rejection and no batch is created.

## Persistence, status and notifications

The final action locks the selected projected service rows, rechecks eligibility and creates
the batch, requests, conflict keys and immutable Date Requested history in one database
transaction. A failure in that transaction rolls back all database records. The dashboard
continues to derive the resulting cells through its projection layer as **Called Off —
Awaiting Date**; the controller does not hard-code cell state.

Existing `CallOffSubmitted` events are deliberately dispatched after the transaction. The
existing listener catches/logs notification failures, so notification failure never rolls
back a valid call-off. Current behaviour is one notification event per request, which is
bounded by the submission size but not yet batch-digested. A batch-level confirmation for
the submitter and one Office Staff notification per batch is a Sprint 3I refinement; it was
not redesigned in Sprint 3D.

## Dashboard selection contract

Dashboard multi-select submits only UUIDs visible in the current rendered desktop page.
Changing page, plot search, service filter or status filter reloads the page and clears the
selection. The dashboard-selection route validates every UUID against the active site before
redirecting into the common New Call Off flow. Individual desktop and mobile Call Off links
start the same flow pre-selected with one UUID.

## Performance sanity

The matrix action eager-loads selected plots, their services and positive customer-visible
products, then reads active conflicts in one query. The automated 15 plots × 4 services
exercise produces 60 rows within a maximum of eight observed database queries; it guards
against a per-cell query regression. The same bounded read shape applies to the 10 × 4
case. Final persistence still rechecks each included row inside its transaction by design.

## Expected UI errors

- Invalid, malformed, cross-site or unauthorised plot selection: a safe validation error;
- unavailable service combination: shown in matrix as unavailable with explanation;
- no included combination: safe validation error;
- invalid weekday/six-month date or missing early reason: safe validation error;
- expired, stale, altered or replayed confirmation: return to New Call Off and review again.

## Remaining limitations

- The holiday-provider implementation is intentionally a weekday-only placeholder until an
  approved UK bank-holiday source is supplied.
- No customer-facing bulk-size limit has been approved.
- A safe disposable MySQL target is still required for locking/concurrency rehearsal.
- Negotiation, amendments, attachments, calendar/PDF and notification digest work remain
  later sprints.
