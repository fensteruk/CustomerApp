# CustomerApp Source Import Data Dictionary

**Status:** Current approved business dictionary

**Last updated:** 4 September 2026

**Applies to:** CustomerApp spreadsheet import, Wald clarification and Portal projection

This document defines confirmed source meanings. It is not evidence that an import path is on
`main` or deployed. Wald may infer workbook structure; it may not infer or replace this business
meaning.

## 1. Customer Services

CustomerApp has exactly four customer-facing services:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

The display order is not a workflow dependency. The exact customer-facing expansion of `CML`
is still unconfirmed. No Snagging source call-type code has been confirmed.

## 2. Call Types

| Source value | Confirmed meaning | Customer service | Import rule |
|---|---|---|---|
| `PC1` | Plot Install | Windows | Recognised mapping. |
| `CC1` | Cavity Closer 1 | Cavity Closers | Recognised mapping. |
| `CM1` | Revisit 1 | CML | Recognised mapping; not a separate service. |
| `CM2` | Revisit 2 | CML | Recognised mapping; not a separate service. |
| `CML` | CML Call Off | CML | Recognised mapping. |
| `CC!` | Unknown; likely a typo | None until confirmed | Preserve raw value, optionally suggest `CC1`, and require human confirmation. Never silently normalise. |

An unknown call type remains unknown. Structural similarity, neighbouring rows or a familiar
label may support a suggestion but cannot create business meaning.

## 3. Completion

The source field `complete` applies to the specific source call-off part represented by the
record.

- A sensible case-insensitive `Yes` means that source part is complete.
- A sensible case-insensitive `No` means the source does not report that part complete.
- Blank, malformed or unknown values are not silently treated as complete.
- Preserve the raw value as private evidence.
- Do not invent a completion date when none is supplied.
- Never use requested, proposed, agreed or operational dates as completion dates.

Older speculative `Job Stage`, `Completed Date`, `CC08`, `CA02`, `CA03`, `SN05` and `CML4`
rules are not authoritative without a later explicit decision.

Source completion takes precedence over open Portal negotiation or amendment. It closes the
current process, preserves history and sends no completion notification. A later reversal
updates current source projection and records the change; it does not erase history or
automatically reopen a closed process.

## 4. Product Codes and Customer Roll-ups

Only the following customer roll-ups are approved:

```text
Total Windows = VS + TT + BAY + ALI + AOV + FI
Total Doors   = PSU + PSG + CDF + CDU + CDG + PSP + BF
```

| Code | Meaning | Customer roll-up |
|---|---|---|
| `VS` | Vertical Slider | Total Windows |
| `TT` | Tilt and Turn | Total Windows |
| `BAY` | Bay Window | Total Windows |
| `ALI` | Aluminium Windows | Total Windows |
| `AOV` | Automatic Opening Vent Window | Total Windows |
| `FI` | Fire Window | Total Windows |
| `PSU` | PVC Door Utility | Total Doors |
| `PSG` | PVC Door Garage | Total Doors |
| `CDF` | Composite Door Front | Total Doors |
| `CDU` | Composite Door Utility | Total Doors |
| `CDG` | Composite Door Garage | Total Doors |
| `PSP` | PVC Sliding Patio | Total Doors |
| `BF` | Bifold | Total Doors |

`BF` contributes to Total Doors and must also remain separately identifiable internally. An
exact positive BF quantity changes the customer-facing normal earliest request from four weeks
to five weeks.

`CAS`, `FLU`, `PFD`, `GLS`, `WP` and `MISC` are excluded/redundant for the final customer
product model. Raw values may be retained privately for evidence but must not be exposed as
customer product types or added to either roll-up.

Absence, blank or zero has meaning only inside the workbook's confirmed coverage. A filtered
export cannot zero or delete values outside that coverage.

## 5. Other Confirmed Fields

| Field | Treatment |
|---|---|
| `Call No.` | Permanent unique source reference expected for a source call-off record. Duplicate/multi-row semantics still require an implementation decision; never silently collapse conflicting rows. |
| `Site Name` | Transitional exact-match input only. It must map to an existing Portal site explicitly and must not fuzzy-match or create one. |
| Permanent Source Site ID/reference | Required durable site identity when the source provides it. Keep separate from the Portal primary key. |
| `Plot To Be Installed` | Operational arrival-to-install date for PC1. Never map to Requested Date, alternative date, Date Agreed or completion date. |
| `Items Ordered Status` | Ignore. It must not drive eligibility, status, completion, lead time or workflow. |
| `Site Value` | Exclude from the final Portal model and customer output. |

No other source field receives customer meaning without an explicit dictionary decision.

## 6. Export Scope

Every workbook defaults to `PARTIAL_FILTERED_EXPORT`. Users may filter the source before export,
so absence never proves deletion, completion or zero quantity—even for a represented site.

Stronger scopes require explicit confirmation:

| Scope | Required evidence | Permitted absence meaning |
|---|---|---|
| `PARTIAL_FILTERED_EXPORT` | Default; no stronger assertion | None. Preserve last known data outside authoritative rows. |
| `SITE_COMPLETE_SNAPSHOT` | Explicit site identity and confirmation that the export contains the complete defined dataset for that site | Only the separately approved reconciliation rule for that stated coverage. |
| `GLOBAL_COMPLETE_SNAPSHOT` | Explicit confirmation of complete global coverage and source revision | Only the separately approved reconciliation rule for that stated coverage. |

Filename, workbook size, row count, represented sites or familiar layout must not imply a
stronger scope.

## 7. Import and Clarification Safety

- Preserve original workbook metadata, sheet/cell provenance and raw values privately.
- Unknown required meanings block dependent staging/commit.
- A human clarification answer changes interpretation knowledge, not authorisation to commit
  Portal business records.
- Site identity, source revision and authorised coverage must be explicit before live commit.
- Imports must be idempotent and must not silently overwrite customer-owned Requested Date,
  alternative, Date Agreed, amendment or history.
- Source completion follows the approved domain transition; placeholder/source operational
  dates never become customer-owned dates.
- No direct source or SiteApp write-back is permitted.
- Do not expose raw workbook content, filenames, worksheets, rejected rows, customer data or
  internal diagnostics to unauthorised users.

## 8. Unresolved Contract Items

The confirmed values above are not open semantic questions. The following are implementation
and governance gates:

- source owner/operator and delivery mechanism;
- immutable source revision identity and stale/out-of-order handling;
- duplicate/multi-row `Call No.` treatment;
- import, review, commit and dictionary/knowledge-approval permissions;
- raw evidence, clarification, staging and audit retention;
- commit atomicity and recovery from partial failure;
- approved behaviour for complete-snapshot absence;
- any additional customer-safe source fields.
