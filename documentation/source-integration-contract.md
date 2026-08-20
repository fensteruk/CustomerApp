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
| Call Type | Required operational code: PC1, CC!, CM1 or CM2. |
| Job Stage | Optional operational completion signal. |
| Completed Date | Optional actual completion date; never substitute another date. |
| Products | Product-code-to-quantity map; zero is valid. |
| Source updated timestamp | Optional and only retained when supplied. |

The Portal records its own `synchronised_at`, observation time and import-run timestamps;
these are not source facts. Presentation/layout details of a spreadsheet are outside this
contract.

## Mapping and validation

- PC1 → Windows; CC! → Cavity Closers; CM1 → Snagging; CM2 → CML.
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
Product quantities are synchronised once per affected plot after its valid records are
processed. Repeating an identical payload updates freshness but does not duplicate plots,
services, products, events or issue identities.

Products omitted from the latest authoritative plot snapshot are retained at quantity zero.
This preserves the source truth and auditability while allowing future presentation to hide
zero quantities.

## Missing, completion and reversal policy

After a snapshot, known Call Nos. absent from it are never deleted. They remain visible from
their last known projection, are marked source-missing and create an idempotent issue.

When source completion is newly established, active requests on that plot service become
Completed, open negotiations close as source-completed, their conflict keys clear, and a
source projection event is recorded. No completion notification is emitted.

On reversal, the projection becomes non-complete and an audit event records the reversal.
The importer never reactivates an older completed request. If another active request exists,
it creates an unsafe-reversal reconciliation issue and preserves that newer request.

## Transport and scheduling

No XLSX/CSV parser, source path, credential or scheduler is configured in Sprint 3B. A
future adapter may parse named headers from XLSX, CSV or an API, validate into this contract
and invoke the importer. Source ownership, credentials and the 1–2 hour production cadence
remain TBC.
