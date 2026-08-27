# Customer Portal — Target Domain V3

_Sprint 3A implementation contract — 20 August 2026_

This document is the additive target-domain contract for Sprints 3B onward. Management
requirements in `context-work-prompt.md` and `brief.md` section 17 remain authoritative.
It distinguishes the target domain from the still-supported legacy call-off workflow.

## Domain shape

```text
Site → ProjectedPlot → ProjectedPlotService (one per four services)
                    → ProjectedPlotProduct (source product/quantity projection)
Site → CallOffBatch → CallOffRequest → CallOffDateNegotiation → CallOffDateProposal
```

- `CallOffBatch` remains one audited customer submission action for one site and user.
  Its legacy service/date/message columns are retained solely for historical records.
- `CallOffRequest` is the plot/service request identity. New work stores service, requested
  date and customer response on the request; this permits mixed services and dates in one
  batch.
- `ProjectedPlotService` is the independent source projection and future call-off target.
  It has a stable service identifier, source Call No./type/stage/completion snapshots and
  source observation timestamps.
- `ProjectedPlotProduct` retains exact source product codes and quantities. It is source
  owned; a positive `BF` product is the current lead-time input.
- `CallOffDateNegotiation` is an initial or amendment cycle. Its nullable unique active key
  means a request cannot have two open cycles of the same kind.
- `CallOffDateProposal` is ordered and append-only in practice: each customer requested
  date or Fenster alternative is a separate row, preserving proposer, responder, explicit
  customer response/private internal reason and earlier-date acknowledgement. Sprint 3E's
  Actions must lock the negotiation, allow at most one awaiting-response proposal, reject
  responses to superseded/closed proposals, and preserve completed proposal fields rather
  than overwriting them.
- `SourceImportRun` is the audit contract for a future read-only source adapter. No source
  importer, scheduler or SiteApp/Excel connection exists in Sprint 3A.

## Services and source completion

The stable `CallOffServiceType` enum has exactly Cavity Closers, Windows, Snagging and CML.
Completion mappings for the future synchroniser are CC08, CA02/CA03, SN05 and CML4
respectively. A non-null source Completed Date independently proves completion. A reversal
clears that projected source completion fact without deleting the projection or history.

Source completion events and reversals are represented by the extended immutable history
vocabulary. The implemented source projection importer closes open negotiations and
supersedes unanswered alternatives before it marks an affected request Completed. Portal
staff have no manual completion Action.

## Legacy compatibility

No UUID, batch, request, history, operation, notification, Trash/Undo or resubmission row
is removed or rewritten. The migration creates all four plot-service rows for every
existing projected plot and backfills each request's request-level service/date/message and
plot-service reference from its legacy batch.

The migration does not create negotiations or proposals for any legacy request. The old
records do not reliably establish an ordered proposal conversation, the actual proposers
or a customer acceptance; fabricating those facts would corrupt the audit record. Legacy
`submitted`, `approved`, `rejected` and `withdrawn` status/history rows, including
`resubmitted_from_call_off_request_id` lineage, remain authoritative. A future one-time
legacy presentation/retention decision is still TBC.

Legacy Approved is therefore displayed/interpreted by future read models as legacy Date
Agreed without changing the historical `approved` status or Approved event.

## Conflict strategy

`UpdateConflictKeyAction` remains the only writer of `active_conflict_key`. The portable
key remains `projected_plot:{id}:service:{identifier}`, so legacy and target requests
participate in the same database uniqueness constraint. Conflict-active states are legacy
Submitted/Approved and target Awaiting Fenster, Awaiting Site User, Date Agreed and
Amendment On Hold. Rejected, Withdrawn and source Completed clear the key. Source-completed
services are separately ineligible; Sprint 3C will move eligibility to per-service source
facts and the lead-time engine. Sprint 3G's source-reversal Action must lock the affected
request and service, then either restore the original conflict key only when no newer
active request exists or stop for an authorised reconciliation. It must never silently
create two active plot/service requests.

## Access contract

Fenster Office Staff are global Portal staff: they may review every customer, site and
call-off. This is role-based and no longer depends on a site assignment. External Site
Manager, Assistant Site Manager and Finishing Foreman remain distinct audit/display roles
but form one Site User permission group: active account, organisation and assigned-site
checks remain mandatory for every external read/action. No plot-level assignment exists.

## Lead-time contract

`CallOffLeadTimeService` is backend-only. It calculates four weeks for standard products
and five weeks where a positive-quantity BF product applies, skips weekends and exposes a
six-month maximum. Its `HolidayProvider` dependency is intentionally unconfigured for UK
bank holidays until a managed holiday data source and owner are approved. Sprint 3E uses
that replaceable provider for alternative-date validation; its current production binding
therefore rejects weekends but has no invented UK bank-holiday dataset.

## Deferred work

Sprint 3A does not introduce source import, a dashboard redesign, new submission routes,
negotiation Actions/UI, amendments, attachments, calendar/PDF, reminders or a migration
that deletes legacy data. Sprint 3B owns source projection ingestion; 3C lead-time
enforcement; 3D new multi-service submission; 3E negotiation; and 3G amendments plus
source completion reconciliation.

## Sprint 3B source-projection update

The implemented source contract is in `documentation/source-integration-contract.md`.
`SourceProjectionImportService` is transport-neutral and records per-run audit counts,
source presence/freshness, durable reconciliation issues and source projection events.
It never accepts customer input or writes to SiteApp/Excel. MySQL rehearsal remains blocked
until a disposable, authorised database is available.
