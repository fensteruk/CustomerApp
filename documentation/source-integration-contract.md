# Customer Portal — Source Integration Contract

_Sprint 3B — 20 August 2026_

## CUSTOMER-WALD01A reconciliation addendum — 4 September 2026

The current controlled field and value definitions are in
`siteapp-import-data-dictionary.md`. If older examples in this Sprint 3B contract conflict
with that dictionary or the current `brief.md`, the current dictionary/brief wins. This
contract remains authoritative for the transport and projection boundary within its scope.

The existing DTO/importer behaviour below remains the inspected Sprint 3B domain baseline,
not an already available spreadsheet upload/review implementation. DEC-039 and `brief.md`
§13 choose CustomerApp-owned Wald ingestion independent of SiteApp availability.
Implementation must follow
`work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md`.

Target path: private CustomerApp upload → Wald interpretation/clarification → neutral
staging → Portal review/current preview → explicitly authorised domain commit. Do not
pass browser input straight to `SourceProjectionImportService` or use Wald confidence as
approval. No SiteApp API, database, queue or filesystem dependency is introduced.

Before live integration, address the documented gaps: the current importer reconciles
absence source-wide, products omitted from its snapshot become zero, duplicate product
keys are merged by later value, and transactions are per record rather than whole import.
Snapshot customer/site/region coverage, authoritative completeness, revision ordering,
quantity grain and reviewed commit atomicity must be settled and tested. A partial upload
must not mark unrelated sites missing or zero unknown quantities. Preserve stable Call No.
associations and the customer-date/source-completion precedence below.

The confirmed source dictionary is now PC1/CC1/CM1/CM2/CML. Literal `CC!` is invalid
unknown/likely typo evidence and must never be silently corrected. `complete = Yes` is
authoritative completion evidence for that specific source call-off part and supplies no
date. Customer product output is the confirmed Total Windows/Total Doors roll-up while
exact BF remains available internally for the five-week rule. These business definitions
come from the controlled dictionary, not Wald confidence or code similarity.

All SiteApp exports contain whatever the user filtered and therefore default to
`PARTIAL_FILTERED_EXPORT`. Absence proves no deletion, including within a represented site.
`SITE_COMPLETE_SNAPSHOT` and `GLOBAL_COMPLETE_SNAPSHOT` require explicit confirmation.
The durable site identity target is the future source Site ID/reference; exact Site Name is
transitional binding evidence only.

A committed non-main feature line ending at `feature/manual-source-import-ui` (`1e8c22b`)
contains deterministic XLSX interpretation, source-site bindings, explicit scopes,
preview/commit protection, profiles and Office UI. It is evidence for CUSTOMER-WALD02–05,
not current `main`, not automatically the final architecture and not permission to bypass
Wald neutral staging/clarification or the remaining governance gates.

## Existing projection contract

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
| Site identity | Required source-specific identity. Use the future permanent source Site ID/reference when available; exact Site Name is a transitional explicit binding key only. |
| Plot reference | Required customer-safe source plot reference. |
| Call Type | Required operational code: PC1, CC1, CM1, CM2 or CML. Literal CC! remains unknown/likely typo evidence pending human confirmation. |
| Complete | Optional Yes/No source flag. Case-insensitive sensible Yes/No variants are accepted; Yes completes this specific source call-off part without inventing a date. |
| Job Stage / Completed Date | Not required by the confirmed spreadsheet contract. Retain as private evidence if later supplied, but do not give older speculative stages/dates completion authority without a separate decision. |
| Plot To Be Installed | Optional operational/source date for Fenster arrival to install PC1 only; never a customer Requested Date, Date Agreed or alternative date. |
| Products | Confirmed product-code quantities used to calculate Total Windows/Total Doors; exact BF must remain identifiable. Raw excluded values may be retained privately. |
| Source updated timestamp | Optional and only retained when supplied. |

The Portal records its own `synchronised_at`, observation time and import-run timestamps;
these are not source facts. Presentation/layout details of a spreadsheet are outside this
contract.

## Mapping and validation

- PC1 (Plot Install) → Windows; CC1 (Cavity Closer 1) → Cavity Closers; CM1 (Revisit 1),
  CM2 (Revisit 2) and CML (CML Call Off) → CML. CM1/CM2 are CML revisits, not additional
  customer services. No source call code for Snagging is invented.
- Literal `CC!` is invalid. Preserve it, classify it as unknown/likely typo, optionally
  suggest CC1 and require confirmation; never silently normalise it.
- `complete = Yes` completes that particular source call-off part. Parse sensible Yes/No
  variants case-insensitively and never fabricate a completion date. Completion evidence
  does not supply a missing service mapping: an invalid/unknown Call Type still blocks the
  affected Portal projection.
- Total Windows = VS + TT + BAY + ALI + AOV + FI. Total Doors = PSU + PSG + CDF + CDU +
  CDG + PSP + BF. CAS, FLU, PFD, GLS, WP and MISC are excluded/redundant from the final
  customer product model but may remain private raw evidence.
- Exact positive BF means Bifold, contributes to Total Doors and changes the normal
  earliest request from four to five weeks. Similar text and the aggregate alone do not
  prove BF.
- `Items Ordered Status` and `Site Value` are ignored. `Plot To Be Installed` is PC1
  operational arrival-to-install evidence only and cannot alter Portal date negotiation.
- Unknown Call Types, invalid site identity, duplicate Call No. in a snapshot and changed
  Call No. site/plot associations are rejected as issues. They do not create or move a
  Portal projection.

## Import isolation and idempotency

The transport-independent `SourceProjectionImportService` processes one Call No. in a
database transaction. A malformed row is isolated and recorded; valid rows continue.
Product quantities are synchronised once per affected plot after its valid records are
processed. Repeating an identical payload updates freshness but does not duplicate plots,
services, products, events or issue identities. The first observed Call No. for an existing
blank plot/service row counts as a created source projection; subsequent run counts change
only for source facts, not for Portal observation timestamps.

Products may be zeroed or treated as absent only inside the explicitly reviewed authoritative
coverage and grain. A filtered/partial workbook cannot zero unrepresented source facts.
Conflicting duplicate evidence must be reconciled rather than resolved by last-row-wins.

## Missing, completion and reversal policy

Every workbook defaults to `PARTIAL_FILTERED_EXPORT`; known Call Nos. absent from it remain
untouched because absence proves nothing. A separately confirmed `SITE_COMPLETE_SNAPSHOT`
may compare only the named bound site(s), and a separately confirmed
`GLOBAL_COMPLETE_SNAPSHOT` may compare the whole namespace. No mode deletes records:
eligible missing facts remain visible from their last known projection and are reconciled.
When a Call No. returns, its existing projection is reused and any applicable missing-source
issue is resolved rather than duplicated.

When `complete = Yes` newly establishes completion for a source call-off part, the
corresponding active Portal request becomes Completed, open negotiation closes as
source-completed, its conflict key clears and a source projection event is recorded. The
state may truthfully have no completion date. No completion notification is emitted.

On reversal, the projection becomes non-complete and an audit event records the reversal.
The importer never reactivates an older completed request. If another active request exists,
it creates an unsafe-reversal reconciliation issue and preserves that newer request.

## Transport and scheduling

No XLSX/CSV parser, source path, credential or scheduler is configured on the audited
checkout. A committed non-main manual XLSX feature line exists, but it is not approved for
production adoption. The standalone Wald adapter will validate into this contract and
invoke the reviewed Portal import boundary. Source ownership, credentials, revision
identity and production cadence remain delivery items.

Unexpected importer failures mark their import run as failed and write only safe diagnostic
metadata (source name, import-run UUID and exception class) to the application log. Raw
payloads, credentials and exception messages must not be logged by this layer.
