# Customer Portal — Source Integration Contract

_Sprint 3B — 20 August 2026_

The Portal consumes a one-way operational projection:

```text
Excel / SiteApp export → validated transport adapter → Portal importer → Portal projection
```

Customer requests never read a workbook, and the Portal never writes requested or agreed
dates back to Excel/SiteApp.

## Canonical source record

Each transport adapter must convert its input into one `SourceRecord` containing:

| Field | Contract |
|---|---|
| Call No. | Required permanent, unique source identity; never reused. |
| Site identity | Required source-specific stable site identifier. |
| Plot reference | Required customer-safe source plot reference. |
| Call Type | Required operational code from `documentation/siteapp-import-data-dictionary.md`. |
| Job Stage | Optional operational completion signal. |
| Completed Date | Optional actual completion date; never substitute another date. |
| Completion flag | Optional source-part completion signal. For SiteApp XLSX, `complete=Yes` completes that specific call-off part without inventing a date. |
| Operational target date | Optional PC1-only Fenster arrival-to-install target. Never a requested, proposed, agreed or completion date. |
| Products | Product-code-to-quantity map; zero is valid. |
| Source updated timestamp | Optional and only retained when supplied. |

The Portal records its own `synchronised_at`, observation time and import-run timestamps;
these are not source facts. Presentation/layout details of a spreadsheet are outside this
contract.

## Mapping and validation

- PC1 → Windows; CC1 → Cavity Closers; CM1/CM2 → CML-related revisits on CML; CML → CML.
- `CC!` is invalid and remains unknown/likely typo for CC1. It is never silently corrected.
- Completion is indicated by CC08, CA02/CA03, SN05 or CML4 for the mapped service, or by a
  Completed Date regardless of stage.
- A completion stage without Completed Date remains complete but creates a reconciliation
  issue; no date is invented.
- Unknown Call Types, invalid site identity, duplicate Call No. in a snapshot and changed
  Call No. site/plot associations are rejected as issues. They do not create or move a
  Portal projection.

## Import isolation and idempotency

The transport-independent `SourceProjectionImportService` processes one Call No. in a
database transaction. A malformed row is isolated and recorded; valid rows continue.
Only confirmed product codes from `documentation/siteapp-import-data-dictionary.md` are
accepted. Product quantities are synchronised once per affected plot after its valid records are
processed. Repeating an identical payload updates freshness but does not duplicate plots,
services, products, events or issue identities. The first observed Call No. for an existing
blank plot/service row counts as a created source projection; subsequent run counts change
only for source facts, not for Portal observation timestamps.

Products omitted from the latest authoritative plot snapshot are retained at quantity zero.
This preserves the source truth and auditability while allowing future presentation to hide
zero quantities. Customer presentation derives only Total Windows and Total Doors; Office
audit detail may retain individual confirmed quantities. Exact positive `BF`, not Total Doors
or a code containing those letters, is the only BF lead-time signal.

## Missing, completion and reversal policy

Every import carries an explicit scope. `PARTIAL_FILTERED_EXPORT` is the manual-XLSX default
and draws no conclusion from absence. `SITE_COMPLETE_SNAPSHOT` compares only explicitly
confirmed complete bound sites. `GLOBAL_COMPLETE_SNAPSHOT` may compare the full namespace.
Known Call Nos. absent from an explicitly complete scope are never deleted. They remain visible from
their last known projection, are marked source-missing and create an idempotent issue.
When that Call No. returns, its existing projection is reused and the missing-source issue
is resolved rather than duplicated.

When source completion is newly established, active requests on that plot service become
Completed, open negotiations close as source-completed, their conflict keys clear, and a
source projection event is recorded. No completion notification is emitted.

On reversal, the projection becomes non-complete and an audit event records the reversal.
The importer never reactivates an older completed request. If another active request exists,
it creates an unsafe-reversal reconciliation issue and preserves that newer request.

## Transport and scheduling

Sprint 3B itself introduced no transport. The later manual `siteapp-xlsx` adapter parses a
secure local XLSX through deterministic interpretation and invokes this same importer.
Source ownership, credentials and the 1–2 hour production cadence remain TBC.

Unexpected importer failures mark their import run as failed and write only safe diagnostic
metadata (source name, import-run UUID and exception class) to the application log. Raw
payloads, credentials and exception messages must not be logged by this layer.
