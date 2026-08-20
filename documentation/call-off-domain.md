# Sprint 1B Call-Off Domain

Status: Approved for Sprint 1B implementation

Date: 5 August 2026

This document is the implementation reference for the Customer Portal Sprint 1B call-off
domain. It records the approved backend architecture before migrations, models, actions,
policies and tests are written.

The Customer Portal remains separate from SiteApp. It may project authorised customer data,
collect call-off requests, record decisions and show customer-facing progress. It must not
copy SiteApp workflow, operational planning, trade sequencing, readiness, verification,
internal statuses or administration.

## Version 1 Batch Rule

Each Version 1 call-off batch contains exactly:

- one site;
- one service type;
- one requested date;
- one submitting user;
- one or multiple projected plots.

Each selected projected plot creates one independently auditable call-off request within
the batch.

Mixed service types and mixed requested dates are not permitted in a single Version 1
batch.

## Core Tables

### projected_plots

`projected_plots` is the Customer Portal projection of authorised plot data.

It is not the operational SiteApp Plot model.

Expected fields:

- `id`;
- `uuid`;
- `site_id`;
- `external_source`;
- `external_identifier`;
- customer-facing plot reference fields;
- outstanding or completed projection state;
- `source_updated_at`;
- `synchronised_at`;
- timestamps.

Rules:

- Use a unique constraint on `external_source` and `external_identifier`.
- Store external identifiers separately from local primary keys.
- Do not add SiteApp workflow, readiness, trade sequencing or internal operational status.
- Completed projected plots must reject new call-off requests.

### call_off_batches

One batch represents one user submission action.

Expected fields:

- `id`;
- `uuid`;
- `site_id`;
- `submitted_by_user_id`;
- `service_identifier`;
- `requested_date`;
- timestamps.

Rules:

- Store service type and requested date on the batch.
- Do not duplicate `submitted_by_user_id` on individual requests.
- A batch may contain one or many requests.
- Every request in a batch must belong to a projected plot at the batch site.

### call_off_requests

One request represents one projected plot in a batch.

Expected fields:

- `id`;
- `uuid`;
- `call_off_batch_id`;
- `projected_plot_id`;
- `status`;
- `active_conflict_key`;
- `trashed_at`;
- `trash_expires_at`;
- `resubmitted_from_call_off_request_id`;
- timestamps.

Rules:

- The current status is the current lifecycle snapshot.
- Approved, submitted, rejected and withdrawn are request lifecycle states.
- Trash is not a request status.
- Approval decisions are per request, not per batch.
- A request derives its site, service type, requested date and submitting user from its
  batch.

### call_off_batch_operations

One operation records an audited multi-request action, such as withdrawal, Trash,
restoration or quick Undo.

Expected fields:

- `id`;
- `uuid`;
- `call_off_batch_id`;
- `performed_by_user_id`;
- `operation_type`;
- `performed_at`;
- `undo_expires_at`;
- `reversed_by_operation_id`;
- timestamps.

Rules:

- Operations are batch-scoped.
- Bulk operations must be authorised for every affected request.
- Confirmed atomic operations must change every eligible item or none.

### call_off_batch_operation_items

One item records a single request affected by a batch operation.

Expected fields:

- `id`;
- `uuid`;
- `call_off_batch_operation_id`;
- `call_off_request_id`;
- previous state snapshot;
- resulting state snapshot;
- timestamps.

Rules:

- Every affected request in a batch operation must have an operation item.
- Operation items preserve per-request audit detail for subset and bulk actions.

### call_off_status_histories

History records immutable events. It must not depend on the current status to explain
what happened.

Expected fields:

- `id`;
- `uuid`;
- `call_off_request_id`;
- `call_off_batch_operation_id`;
- `sequence`;
- `event_type`;
- `previous_status`;
- `new_status`;
- `performed_by_user_id`;
- `performed_at`;
- `customer_response`;
- `internal_reason`;
- previous value snapshot;
- resulting value snapshot;
- timestamps.

Rules:

- Use explicit `customer_response` and `internal_reason` fields.
- Do not introduce generic `notes` fields in the call-off domain.
- Customer-visible text and private Fenster rationale must remain separate.
- Allocate history sequence safely inside the same transaction as the state change.
- Events include submitted, approved, rejected, withdrawn, trashed, restored,
  undo_applied and resubmitted.

## Service Type

Use a portal-specific backed enum for the Version 1 service identifiers:

- `cavity_closers`;
- `windows`;
- `cml`.

The customer-facing meaning of CML remains an open product decision, but the service
identifier is valid for Sprint 1B.

## Active Conflict Key

`active_conflict_key` is a portable duplicate-prevention key for one active request per
projected plot and service.

Format:

```text
projected_plot:{projected_plot_id}:service:{service_identifier}
```

Rules:

- The column is nullable.
- A unique index is applied to the column.
- Submitted requests must have the key.
- Approved requests keep the key.
- Rejected requests clear the key.
- Withdrawn requests clear the key.
- Trash and restore do not independently change the key.
- Approved requests remain conflict-active until a future authorised lifecycle event marks
  them completed, superseded or otherwise formally closed.

`active_conflict_key` is a computed domain value. It must be changed only by
`UpdateConflictKeyAction`, called from lifecycle actions in the same transaction as the
state transition.

## Eligibility

Use `DetermineCallOffEligibilityAction` everywhere eligibility is needed.

Callers include:

- UI availability;
- submission validation;
- resubmission validation;
- API entry points;
- tests.

Eligibility must enforce:

- authenticated and active portal user;
- assigned site scope;
- customer organisation boundary;
- projected plot belongs to the selected site;
- projected plot is outstanding;
- service type is supported;
- no submitted or approved request already blocks the projected plot and service.

Eligibility must not calculate SiteApp operational availability, lead times, readiness,
manufacturing capacity or labour planning.

Submission and resubmission must re-check eligibility immediately before persistence.

## Lifecycle

Confirmed Version 1 current statuses:

- submitted;
- approved;
- rejected;
- withdrawn.

Confirmed duplicate-blocking behaviour:

- submitted requests block duplicates;
- approved requests block duplicates;
- rejected requests do not block resubmission;
- withdrawn requests do not block resubmission;
- Trash state does not independently determine duplicate blocking.

Approved requests remain conflict-active until a future authorised lifecycle event marks
them completed, superseded or otherwise formally closed. Those later lifecycle events are
outside Sprint 1B.

## Trash And Restore

Trash is a customer-facing list state, not a workflow status.

Rules:

- Only eligible withdrawn or rejected requests may be moved to Trash in Version 1.
- Approved requests cannot be trashed, deleted or cancelled by site users.
- Restoring a request from Trash leaves its underlying status unchanged.
- A restored withdrawn request remains withdrawn.
- A restored rejected request remains rejected.
- Expired Trash records are hidden from customer-facing Trash after seven days and remain
  audit history.

## Actions

Sprint 1B actions:

- `DetermineCallOffEligibilityAction`;
- `SubmitCallOffBatchAction`;
- `ApproveCallOffRequestAction`;
- `RejectCallOffRequestAction`;
- `WithdrawCallOffRequestsAction`;
- `TrashCallOffRequestsAction`;
- `RestoreCallOffRequestsAction`;
- `QuickUndoCallOffOperationAction`;
- `ResubmitRejectedCallOffAction`;
- `UpdateConflictKeyAction`;
- `RecordCallOffStatusHistoryAction`.

Rules:

- Controllers stay thin.
- Blade views contain no business logic.
- Authorise immediately before persistence.
- Use transactions for multi-record operations.
- Bulk and subset actions must lock and validate the affected requests before changing
  state.
- Do not silently overwrite dates or decision fields.

## Authorization

Site Manager, Assistant Site Manager and Finishing Foreman users may submit call-offs only
for their assigned active site.

Fenster Office Staff may approve or reject call-offs only within their explicitly assigned
review scope.

Hidden buttons are not security. Policies, gates, scopes, middleware or explicit action
checks must enforce every protected path.

## UUIDs

Every Sprint 1B business table must include:

- an integer `id` primary key;
- a unique server-generated `uuid`.

Expose UUIDs externally in links, API payloads, notifications, QR-code contexts and future
integration references. Keep integer IDs internal.

## Notifications

Use Laravel notifications later, once notification rules are implemented.

Notification delivery failure must not corrupt request state.

No custom notification table is part of the Sprint 1B domain foundation unless a later
requirement confirms it.

## Implementation Order

Recommended order:

- enums;
- migrations;
- models;
- relationships;
- actions;
- policies;
- tests;
- seeders.

Only after the backend contract is implemented should UI replace preview screens.

## Open Decisions That Do Not Block Sprint 1B Foundation

- Customer-facing meaning of CML.
- Bulk call-off creation limits and validation feedback.
- Amendment lifecycle beyond Version 1 withdrawal, rejected-request resubmission, Trash
  and Undo.
- Required email notification rules.
- Synchronisation freshness rules.
- Customer-facing status mapping beyond submitted, approved, rejected and withdrawn.
- Long-term retention outside the seven-day customer-facing Trash window.
- Initial SiteApp integration method.

These decisions must be resolved before implementing the affected later behaviour, but
they do not block Sprint 1B call-off domain implementation.
# Sprint 3A target-domain addendum

The active implementation contract for new work is
`documentation/target-domain-v3.md`. This document remains the historical Sprint 1B
domain record. Where it describes a three-service, single-service batch or
Approved/Rejected final lifecycle, treat that as legacy compatibility behaviour rather
than the target product design.
