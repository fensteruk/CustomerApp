# Customer Portal — Source Integration Contract

_Sprint 3B — 20 August 2026; CUSTAPP2 identity/composite refinement updated 15 September 2026_

## CUSTOMER-WALD-CUSTAPP2-01 addendum — 15 September 2026

DEC-064 supersedes three narrow assumptions below wherever they conflict: the source record need
not be one physically contiguous table; CallNo headers need not equal only `Call No.`; and CallNo
does not by itself prove that a visit exists.

The current identities are:

- Plot = exact active source-site binding + normalized Plot Ref;
- Source Row = source namespace + CallNo;
- Visit = Source Row + recognized non-null Call Type.

A blank Call Type is valid null. It may create/update the resolved plot and compatible product
facts, but cannot create a visit/service/request, affect completion or map any operational date to
a Portal-owned date. A recognized type becoming blank in a later `PARTIAL_FILTERED_EXPORT` does
not reverse the visit. A recognized type change for the same Source Row blocks.

Product evidence consolidates per plot: missing/unrepresented is no assertion; explicit zero is
exact; equal explicit values agree; unrepresented plus explicit uses the explicit value; conflicting
explicit values block the complete site review unit. No row-order or calculated winner is allowed.

Compatible horizontal fragments may compose one logical table only with aligned worksheet/bounds,
spacer and complementary-role evidence and no stronger independent-table interpretation. Preserve
physical worksheet/cell/raw/logical-row/fragment provenance. Competing compositions require
clarification. Call-reference headers use exact normalized semantic tokens `call` + `no` or
`call` + `number` in either order; no fuzzy matching is allowed.

This addendum does not authorise automatic multi-site commit. One explicitly selected, exactly
bound site remains the atomic review/commit unit. CUSTAPP2 recognizes PC1/CC1/CM1 and does not
inherit the earlier workbook's checksum-specific `CC!`/CM2 treatment.

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

Before live integration, replace the documented unsafe behaviours: the current importer reconciles
absence source-wide, products omitted from its snapshot become zero, duplicate product
keys are merged by later value, and transactions are per record rather than whole import.
WALD05 V1 uses only partial/filtered scope, staff-declared Export Date/Slot ordering,
one Call No. per visit and one atomic reviewed commit. These contracts must be implemented and
tested. A partial upload
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
`SITE_COMPLETE_SNAPSHOT` and `GLOBAL_COMPLETE_SNAPSHOT` are non-committable in WALD05 V1.
The durable site identity target is the future source Site ID/reference; exact Site Name is
transitional binding evidence only.

RedZebra does not yet supply a native immutable export revision. WALD05 V1 therefore orders
imports by Office-declared `Export Date` plus `Export Slot` (`MORNING` or `AFTERNOON`), with a
later date newer and `AFTERNOON` newer than `MORNING` on the same date. The authenticated uploader
account ID/name are captured automatically and the user must confirm exactly: “I confirm this is
the latest RedZebra export available for this slot.” This is staff-declared provenance, not a
RedZebra-native revision. Upload/receipt/filesystem timestamps never establish source freshness.

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
| Call No. | Required Source Row identity within the source namespace. A Visit exists only when the row has a recognized non-null Call Type. |
| Site identity | Required source-specific identity. Use the future permanent source Site ID/reference when available; exact Site Name is a transitional explicit binding key only. |
| Plot reference | Required customer-safe source plot reference. |
| Call Type | Recognized operational code when a Visit exists. Genuine blank is valid null/no Visit; nonblank unknown remains blocking. Literal CC! remains unknown/likely typo evidence except under an explicit checksum-scoped rule. |
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
- Every duplicate Call No. within one workbook blocks the reviewed run, even when the rows are
  canonically identical. Never merge, select first/last or infer a subidentity.

## WALD05 atomicity and idempotency

The inspected transport-independent `SourceProjectionImportService` processes one Call No. in a
database transaction and then runs separate product/missing passes. That historical behaviour is
not the WALD05 commit contract. WALD05 commits one explicitly reviewed bounded run in one atomic
transaction; any failure rolls the whole unit back. If limits require smaller work, the units are
created and reviewed explicitly before approval, never hidden-chunked or applied per row.

Within a source namespace/workbook family, at most one import may commit successfully per Export
Date/Export Slot. The same slot and same canonical workbook/content identity is idempotent and
returns the existing receipt. The same slot with different content is a conflict requiring an
explicit audited correction/replacement successor. An older declared slot cannot overwrite a
newer committed one. The same Call No. in a later allowed export is the same visit: unchanged
canonical content has no semantic effect; changed content is an ordered update/correction.

Only a supplied, present product column may carry an exact blank/zero/positive meaning under the
approved supplied-record contract. An absent column is unrepresented and preserves existing
data. A filtered/partial workbook cannot zero unrepresented source facts. Conflicting duplicate
evidence blocks rather than being resolved by last-row-wins.

## Missing, completion and reversal policy

Every workbook defaults to `PARTIAL_FILTERED_EXPORT`; known Call Nos. absent from it remain
untouched because absence proves nothing. `SITE_COMPLETE_SNAPSHOT` and
`GLOBAL_COMPLETE_SNAPSHOT` cannot commit in V1, regardless of workbook shape, contents or an
Office assertion. Any future stronger-scope absence effect requires a separate approved
coverage/grain/revision contract. No mode deletes records. When a Call No. returns, its existing
projection identity is reused rather than duplicated.

When `complete = Yes` newly establishes completion for a source call-off part, the
corresponding active Portal request becomes Completed, open negotiation closes as
source-completed, its conflict key clears and a source projection event is recorded. The
state may truthfully have no completion date. No completion notification is emitted.

On reversal, the projection becomes non-complete and an audit event records the reversal.
The importer never reactivates an older completed request. If another active request exists,
it creates an unsafe-reversal reconciliation issue and preserves that newer request.

## Transport and scheduling

DEC-053's feature-branch backend now provides CustomerApp-private XLSX/CSV upload and a
reviewed atomic source adapter. It is default off, with no HTTP route, scheduler or production
credential configured by this task. Its supported unit is one bound site, one visible unmerged
table/sheet and at most 500 nonempty rows; unsupported units refuse entirely. The old execution
path remains intact pending approved cutover. A separate non-main manual XLSX feature line is
reference/reuse material, not approved production architecture. The temporary manual order governs WALD05 V1
until RedZebra supplies a real revision/API. Credentials, production cadence and operational
rollout remain delivery items.

The retained legacy importer marks unexpected failures on its run and writes only safe diagnostic
metadata (source name, import-run UUID and exception class) to the application log. Raw
payloads, credentials and exception messages must not be logged by this layer. The WALD05 backend
stores only safe analysis failure codes in its private durable run/audit; it does not log raw
exceptions. A failed backend commit rolls back all business effects and leaves the prior
review state intact, never a partial success or a separately persisted failed business receipt.
Under DEC-054, a separate private immutable commit-attempt intent survives business rollback;
at most one terminal outcome records success, stale/refused/conflicted rejection or failure.
Success effects, business receipt and success outcome commit together. Exact actor/command
retries deduplicate; a terminal failed command needs a new command for a corrected attempt.
An unavailable outcome store may leave only the durable intent, never invented success; a
matching retry can recover that incomplete attempt. Runtime commit entry requires a top-level
transaction boundary. Audit metadata excludes raw rows, filenames, credentials and exception
messages. Existing holds and restrictive history references remain; no disposal policy or
scheduler is added. This is feature-branch behavior, not an HTTP endpoint or deployed service.
Current verification and parity limits are recorded in
`documentation/wald/customer-wald05-backend-qa-2026-09-09.md`; the earlier backend completion
report remains historical implementation evidence.
