# Fenster Customer Portal Decisions

Sprint 1 - Call Off Workflow

Next implementation milestone:
Sprint 1B - Call-Off Domain Design and Schema

Status:
Sprint 1A implemented and verified; Sprint 1B domain design approved for implementation.
## DEC-009

Date:
23 July 2026

Decision:
Call-offs are approved or rejected by Fenster Office Staff.

Reason:
Approval is an office responsibility rather than a site-management responsibility.

---

## DEC-010

Date:
23 July 2026

Decision:
The portal has four initial roles:

- Site Manager
- Assistant Site Manager
- Finishing Foreman
- Fenster Office Staff

Reason:
The first three roles submit call-offs. Fenster Office Staff review them.

---

## DEC-011

Date:
23 July 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman users may submit call-offs for assigned sites.

Reason:
These are the customer-side site roles responsible for requesting Fenster work.

---

## DEC-012

Date:
23 July 2026

Decision:
For the initial version, site users select their assigned site from a menu.

Future behaviour:
Users will scan a site QR code.

Security rule:
A QR code identifies a site but never replaces authentication or site authorisation.

---

## DEC-013

Date:
23 July 2026

Decision:
A development-only role-preview screen will allow selection of:

- Site Manager
- Assistant Site Manager
- Finishing Foreman
- Fenster Office Staff

Selecting Site Manager, Assistant Site Manager or Finishing Foreman leads to the
site dashboard with assigned-site selection. Selecting Fenster Office Staff leads to
the Review Requests dashboard.

Security rule:
Role preview must be disabled outside approved development environments, must not persist
or grant a role, and must never bypass authentication or authorisation.

---

## DEC-014

Date:
23 July 2026

Decision:
The confirmed call-off office decision lifecycle is:

```text
Submitted → Approved
          → Rejected
```

Fenster Office Staff are the only portal role permitted to approve or reject a submitted
call-off. The decision must be auditable and must not trigger, reproduce or expose SiteApp
operational workflow.

Withdrawal is a separate request lifecycle state confirmed in DEC-018. It is not an
approval or rejection decision.

Reason:
The portal records the customer-facing request and decision; SiteApp remains the
operational system.

---

## DEC-015

Date:
5 August 2026

Decision:
Fenster Office Staff may review and approve or reject call-offs only for sites assigned
to them in the Customer Portal.

Reason:
Assigned-site review scope keeps the approval model explicit, supports least-privilege
access and avoids giving office users automatic all-site visibility.

---

## DEC-016

Date:
5 August 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman users may see all call-offs
for their active assigned site in Version 1, not only call-offs they personally submitted.

Reason:
Call-offs are site-level requests. Site teams need shared visibility for the selected
site while still being restricted to assigned sites.

---

## DEC-017

Date:
5 August 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman remain three distinct portal
roles, but have identical Version 1 permissions.

Reason:
The roles must be recorded separately for future permission differences, but no Version 1
functional difference has been confirmed.

---

## DEC-018

Date:
5 August 2026

Decision:
A pending submitted call-off may be withdrawn before Fenster Office Staff approve or
reject it. Withdrawal is available to authorised site users for the active assigned site.

Rules:

- Withdrawal moves the request out of the active review queue.
- Withdrawal records the user, timestamp, previous state and reason or note where supplied.
- Withdrawal must not delete the request history.
- A withdrawn request no longer blocks a new active request for the same plot and service.

Reason:
Site teams need a safe way to remove an accidental or obsolete pending request before an
office decision is made.

---

## DEC-019

Date:
5 August 2026

Decision:
Approved call-offs cannot be deleted, trashed or cancelled by site users in Version 1.

Rules:

- Approved records remain visible in request history.
- Any later cancellation or reversal of an approved call-off requires a separately
  designed authorised process.
- The portal must not silently remove or overwrite approved decision audit records.

Reason:
Approval is an auditable Fenster decision and may already have downstream operational
meaning outside the portal.

---

## DEC-020

Date:
5 August 2026

Decision:
Rejected call-offs remain in history and may be moved to customer-facing Trash. A site
user may submit a new request for the same plot and service after rejection, but the new
submission must be traceable back to the rejected request.

Rules:

- Rejection records are never overwritten.
- Trashing a rejected request removes it from normal customer-facing lists only.
- Resubmission creates a new submitted request or explicit revision record; it does not
  change the original rejected decision.

Reason:
Rejected requests should not clutter active working lists forever, but the rejection and
any later resubmission must remain auditable.

---

## DEC-021

Date:
5 August 2026

Decision:
Eligible user-triggered withdrawal, trash and restoration actions must offer a
five-second quick Undo.

Rules:

- Quick Undo restores the request or bulk action to its previous customer-facing state.
- Quick Undo must restore the complete action atomically.
- Quick Undo must not bypass authorisation checks.
- Approved call-offs are excluded because they cannot be deleted, trashed or cancelled by
  site users in Version 1.

Reason:
A short undo window reduces accidental removals while preserving the audit trail.

---

## DEC-022

Date:
5 August 2026

Decision:
Customer-facing Trash keeps eligible withdrawn or rejected call-offs recoverable for
seven days.

Rules:

- Trash is a customer-facing list state, not permanent deletion.
- Restoration within seven days returns the complete request to the appropriate
  customer-facing history or list state.
- Trash and restoration record user, timestamp and previous values.
- Approved call-offs are not eligible for Trash in Version 1.

Reason:
Seven-day recovery gives site users a practical safety window without treating Trash as
the audit record.

---

## DEC-023

Date:
5 August 2026

Decision:
Bulk undo and bulk Trash restoration must be atomic.

Rules:

- A bulk undo either restores every affected item or none of them.
- A bulk restoration either restores every selected recoverable item or none of them.
- If any item fails authorisation, eligibility or expiry checks, the operation fails
  without partially changing the selected set.

Reason:
Partial restoration would make bulk request history difficult to understand and audit.

---

## DEC-024

Date:
5 August 2026

Decision:
Expired Trash records are hidden from customer-facing Trash after seven days and retained
as audit-critical history. They are not permanently deleted by the Version 1 Trash expiry
process.

Reason:
The customer-facing Trash should stay tidy, while decisions, withdrawals and request
history remain available for audit and support.

---

## DEC-025

Date:
5 August 2026

Decision:
The Customer Portal uses a call-off batch model for user submission actions.

Rules:

- One call-off batch represents one user submission action.
- One batch has exactly one site, service type, requested date and submitting user.
- One batch may contain one or many individual requests for different projected plots at
  that site.
- Mixed service types and requested dates are not permitted within a Version 1 batch.
- The batch groups submission, bulk withdrawal, quick Undo and Trash restoration.
- Each individual request owns its own status, approval, rejection, decision and history.
- Fenster Office Staff may decide individual requests independently after submission.
- Once requests in a batch have received different decisions, later batch-level operations
  must not overwrite those individual decisions.
- Batch-level operations must be authorised for every affected request and must remain
  atomic where confirmed by the bulk Undo and restoration rules.

Reason:
Customers may submit several plot/service requests at once, but approval decisions and
history must remain auditable per individual request. The batch groups the user action
without turning multiple requests into a single approval unit.

---

## DEC-026

Date:
5 August 2026

Decision:
Each Version 1 call-off batch contains exactly one site, one service type, one requested
date and one submitting user. It may contain one or multiple projected plots, with one
individual call-off request created for each selected projected plot.

Rules:

- Mixed service types are not permitted in a batch.
- Mixed requested dates are not permitted in a batch.
- Each request remains independently auditable and may later receive its own Office Staff
  decision.

Reason:
A single service and requested date make bulk submission, validation, Undo and audit
history clear without preventing independent request decisions.

---

## DEC-027

Date:
5 August 2026

Decision:
Submitted and approved call-offs remain conflict-active for the same plot and service.
Rejected and withdrawn call-offs do not block resubmission. Customer-facing Trash does
not independently affect duplicate blocking.

Rules:

- An approved call-off remains conflict-active until a future authorised lifecycle event
  marks it completed, superseded or otherwise formally closed.
- Completion, supersession and formal closure are outside Sprint 1B scope.
- The duplicate-prevention key must remain populated on approval and be cleared only when
  the request is rejected or withdrawn in Version 1.

Reason:
An approved call-off may already have downstream operational meaning. Allowing an
immediate duplicate would undermine the portal's request traceability.

---

## DEC-028

Date:
5 August 2026

Decision:
The Customer Portal plot projection table is named `projected_plots`.

Rules:

- `projected_plots` is a Customer Portal projection of authorised plot data.
- It is not the operational SiteApp plot table or model.
- It stores a local integer primary key, a public UUID, an explicit external source and
  an external identifier.
- External identity is recorded separately from local identity.
- The projection must not introduce SiteApp workflow, readiness, trade sequencing or
  operational status rules.

Reason:
The explicit name prevents future developers confusing Customer Portal projected plot
records with SiteApp operational plots.

---

## DEC-029

Date:
5 August 2026

Decision:
`active_conflict_key` is a computed domain value controlled only by call-off lifecycle
actions.

Rules:

- Developers must not set or clear `active_conflict_key` directly from controllers, views,
  validation objects or unrelated services.
- `UpdateConflictKeyAction` owns the calculation and persistence of the key.
- Every state transition that may change duplicate-blocking behaviour must call
  `UpdateConflictKeyAction`.
- Submitted and approved requests receive and retain the key.
- Rejected and withdrawn requests have the key cleared in Version 1.
- Trash and restore operations do not independently change the key.

Reason:
Duplicate prevention is a business rule. Keeping the key behind a single action prevents
drift between submission, approval, rejection, withdrawal, resubmission and future API
paths.

---

## DEC-030

Date:
5 August 2026

Decision:
Call-off domain fields must use explicit response and reason names rather than generic
notes.

Rules:

- Use `customer_response` for customer-visible response text.
- Use `internal_reason` for private Fenster-only rationale.
- Do not introduce generic `notes` fields in the call-off domain.
- Customer-visible text and internal text must remain separate in validation, persistence,
  resources, events, notifications and tests.

Reason:
Explicit field names prevent accidental disclosure and remove ambiguity from future
maintenance.

---

## DEC-031

Date:
5 August 2026

Decision:
Every Sprint 1B business table has both an integer primary key and a UUID.

Rules:

- Integer `id` columns remain the primary keys.
- UUIDs are exposed externally in URLs, APIs, QR codes, notifications and integration
  references where a public identifier is needed.
- UUIDs must be unique and generated server-side.
- Internal integer IDs must not be trusted from customer-controlled input.

Reason:
Integer keys keep local relational data simple. UUIDs provide stable public identifiers
without exposing internal row counts or coupling external references to database keys.

---

## DEC-032

Date:
5 August 2026

Decision:
Call-off eligibility is centralised in `DetermineCallOffEligibilityAction`.

Rules:

- UI availability, submission validation, resubmission validation, API entry points and
  tests must ask `DetermineCallOffEligibilityAction`.
- Eligibility must enforce authorised site scope, outstanding projected plot state,
  supported service type and duplicate active request blocking.
- Eligibility must not calculate SiteApp operational availability, lead times,
  manufacturing capacity or readiness.
- Submission actions must re-check eligibility immediately before persistence.

Reason:
Eligibility is used in several paths. A single action keeps the business rule testable
and prevents duplicated checks from drifting apart.

---

## DEC-033

Date:
5 August 2026

Decision:
Call-off history records events, while request status stores only the current lifecycle
snapshot.

Rules:

- Status is the current request state, such as submitted, approved, rejected or withdrawn.
- History is an immutable event stream, such as submitted, approved, rejected, withdrawn,
  trashed, restored, undo_applied or resubmitted.
- Customer-facing Trash is recorded as an event/list state and must not become a request
  status.
- Restoring a trashed withdrawn request leaves the current status as withdrawn.
- History must not infer whether an event happened solely from the current status.

Reason:
Event history preserves the full audit trail even when the current status no longer
describes earlier actions.

---

## Open Permission and Lifecycle Questions

The following remain unresolved and must be decided before the affected implementation:

1. The customer-facing meaning of CML.
2. Bulk call-off creation limits and validation feedback.
3. Amendment eligibility and lifecycle beyond withdrawal, rejected-request resubmission,
   Trash and Undo.
4. Required email notification rules.
5. Synchronisation freshness rules.
6. Customer-facing status mapping beyond submitted, approved, rejected and withdrawn
   request states.
7. Long-term retention periods outside the seven-day customer-facing Trash window.
8. Initial SiteApp integration method.
