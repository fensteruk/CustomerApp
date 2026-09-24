# CustomerApp Source Import Data Dictionary

**Status:** Current approved business dictionary

**Last updated:** 22 September 2026 (current-scope exclusions approved under DEC-071)

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
| `CU4` | Customer Care | None | Exclude the entire source row from CustomerApp discovery/projection; retain private evidence and exclude audit. |

DEC-070 also excludes these exact normalized Call Types globally as irrelevant to
CustomerApp: `CM8`, `P02`, `P06`, `P08`, `Q01`, `QU5`, `SS1`, `T03`, `T05`,
`T07`, `T09`, `T11`, `T13`, `T15`, `VC1`, `X10`, `X14`, `X16`, `X50`, `X99`,
`XR1`, `XR2`, `XX1`, `Z05`, `Z09`. Their rows retain private raw evidence with
exclusion provenance but contribute no plot, product, visit, service, completion,
customer date/workflow or notification. An excluded row does not block an otherwise
valid selected-site review. CU4 remains the separate DEC-069 Customer Care rule.

DEC-071 further excludes `CU0`, `CU1`, `CU3`, `P04` and `zzz` for the current
CustomerApp scope. `CU0`/`CU1`/`CU3` are Customer Care-related, consistent with
the existing `CU4` exclusion. `zzz` has no required CustomerApp meaning at
present; preserve its raw spelling while matching normalized `ZZZ`. **P04 is
temporarily excluded**, with current source description “Plot Installation -
2nd Visit (use TEAM)”. Management may revisit it. Do not map P04 to Windows
without a later explicit decision. All five preserve private row evidence and
make no CustomerApp projection assertion.

Other unknown call types remain unknown. Structural similarity, neighbouring rows or a familiar
label may support a suggestion but cannot create business meaning.

DEC-080 also excludes rows with exact CustomerCode `XXTrade`, `XXTEST` or `83`
(trimmed and case-normalized) regardless of Call Type. These rows have no
customer/site/plot/service projection while ignored. Office can view them in a
private Ignored CustomerCodes tab, restore one to ordinary guarded review, and
confirm the remaining ignored rows once per upload. The tab does not list rows
excluded solely by Call Type.

DEC-081 permits Office-reviewed fallback Plot derivation after one exact
Customer and Site are chosen for a CustomerCode group. Match only the chosen
names with controlled case/whitespace normalization and approved dash
separators; remove an initial `Plot ` token from the sole remaining value.
`033`, `Com 4` and `Block A` are valid string Plot Refs. Do not join multiple
unexplained fragments. Keep uncertain rows in the final Unknown queue for
manual plot confirmation or upload-scoped exclusion; neither changes global
Call Type meanings. Retain source row and Office decision evidence.

For the CUSTAPP2 composite profile, only PC1, CC1 and CM1 are recognized. That workbook does not
inherit checksum-scoped `CC!` correction or CM2 handling from an earlier approved artifact.

A genuinely blank Call Type is valid null and means no Visit has been established. It may support
plot/product facts under the rules below, but cannot create a customer service/request, mutate
completion or manufacture a Portal-owned date. A nonblank unknown remains blocking.

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
Total Windows = CAS + FLU + VS + TT + BAY + ALI + AOV + FI
Total Doors   = PSU + PSG + CDF + CDU + CDG + PSP + BF + PFD
```

| Code | Meaning | Customer roll-up |
|---|---|---|
| `VS` | Vertical Slider | Total Windows |
| `TT` | Tilt and Turn | Total Windows |
| `BAY` | Bay Window | Total Windows |
| `ALI` | Aluminium Windows | Total Windows |
| `AOV` | Automatic Opening Vent Window | Total Windows |
| `FI` | Fire Window | Total Windows |
| `FLU` | Flush Window | Total Windows |
| `CAS` | Casement window | Total Windows |
| `PSU` | PVC Door Utility | Total Doors |
| `PSG` | PVC Door Garage | Total Doors |
| `CDF` | Composite Door Front | Total Doors |
| `CDU` | Composite Door Utility | Total Doors |
| `CDG` | Composite Door Garage | Total Doors |
| `PSP` | PVC Sliding Patio | Total Doors |
| `BF` | Bifold | Total Doors |
| `PFD` | Patio/French Door | Total Doors |
| `GLS` | Excluded | Neither total; private raw evidence |
| `WP` | Excluded | Neither total; private raw evidence |
| `MISC` | Excluded | Neither total; private raw evidence |

`BF` contributes to Total Doors and must also remain separately identifiable internally. An
exact positive BF quantity changes the customer-facing normal earliest request from four weeks
to five weeks.

GLS, WP and MISC are excluded from customer product projection and totals;
their raw source values remain private evidence. Approved irrelevant Call Type
rows are separately excluded from the selected-site projection.

Product presence and multi-row plot consolidation are explicit:

- absent column: unrepresented; preserve existing data;
- missing/unrepresented value: no assertion; preserve existing data;
- explicit zero: exact zero for that supplied record/column;
- valid positive value: exact approved fixed-point quantity;
- invalid or unknown value: block; never coerce to zero.
- equal explicit values for the same plot/product agree;
- unrepresented plus explicit uses the explicit fact;
- conflicting explicit values for the same plot/product block the complete selected-site unit.

Never choose first, last, minimum, maximum or average. Blank-Call-Type rows may contribute valid
product facts to the resolved plot without establishing a Visit.

A filtered export cannot zero or delete values outside supplied records.

## 5. Other Confirmed Fields

| Field | Treatment |
|---|---|
| `CustomerNo` / `CustomerCode` / `Customer Number` | Exact approved headers for authoritative `customer_code`. Source namespace + exact code is the durable source-site binding key. Never fuzzy-match or fall back to Site Name. |
| `Call No.` | Permanent Source Row reference within the source namespace. A recognized non-null Call Type establishes a Visit; CallNo alone does not. Every within-workbook duplicate still blocks; never merge or select first/last. |
| `Site Name` | Descriptive source evidence under the new master-export contract. Retain changed/variant names for review, but do not use them to identify, create, split or rebind a CustomerCode. Exact Site Name remains only for explicitly bounded historical workbook compatibility. |
| `Plot Ref` | Current composite master-export hierarchy: exact Customer – Site – Plot in three nonempty components, separated by spaced `-`, `–` or `—`, or by exactly two compact ASCII hyphens. The final component starts `Plot ` and the remaining nonempty text is the site-scoped plot reference, including named plots such as `Com 4`. Preserve the raw cell. Additional or mixed separators remain blocking ambiguity. |
| Permanent Source Site ID/reference | Required durable site identity when the source provides it. Keep separate from the Portal primary key. |
| `Plot To Be Installed` | Operational arrival-to-install date for PC1. Never map to Requested Date, alternative date, Date Agreed or completion date. |
| `Items Ordered Status` | Ignore. It must not drive eligibility, status, completion, lead time or workflow. |
| `Site Value` | Exclude from the final Portal model and customer output. |

No other source field receives customer meaning without an explicit dictionary decision.

Header recognition for CallNo is deterministic: after removing structural punctuation/spacing and
normalizing case, accept exactly the semantic tokens `call` + `no` or `call` + `number` in either
order. Concatenation is allowed; fuzzy/edit-distance matching is not.

## 6. Export Scope

Every workbook defaults to `PARTIAL_FILTERED_EXPORT`. Users may filter the source before export,
so absence never proves deletion, completion or zero quantity—even for a represented site.

Stronger scopes are reserved for a future separately approved contract:

| Scope | Required evidence | Permitted absence meaning |
|---|---|---|
| `PARTIAL_FILTERED_EXPORT` | Default; no stronger assertion | None. Preserve last known data outside authoritative rows. |
| `SITE_COMPLETE_SNAPSHOT` | Not committable in WALD05 V1 | None in V1. |
| `GLOBAL_COMPLETE_SNAPSHOT` | Not committable in WALD05 V1 | None in V1. |

Filename, workbook size, row count, represented sites or familiar layout must not imply a
stronger scope.

## 7. Temporary V1 export ordering

RedZebra does not currently provide an immutable native export revision. WALD05 V1 uses an
Office-declared `Export Date` plus `Export Slot`, exactly `MORNING` or `AFTERNOON`. A later date is
newer; on the same date, `AFTERNOON` is newer than `MORNING`. Upload, receipt, file modification
and other Portal timestamps do not establish source freshness.

The uploader's authenticated CustomerApp account ID and name are captured automatically, never
entered as free text. Before review, the uploader confirms exactly: “I confirm this is the latest
RedZebra export available for this slot.” This is an attributed staff assertion, not a claim of a
RedZebra-native revision.

Within a source namespace/workbook family, each date/slot is a logical master-export revision
family. The same workbook hash returns the existing current import without a new revision. A
different upload creates an audited successor: failed current revisions need no extra replacement
confirmation; non-failed current revisions require the exact confirmation. The predecessor and
receipts remain, older uncommitted previews become stale, and committed facts change only through
the normal reviewed correction path. An older slot cannot overwrite a newer committed slot.

The same Call No. in a later permitted export denotes the same visit. Unchanged canonical content
has no semantic effect; changed content is an update/correction only under these ordering and
successor rules. A future RedZebra revision/API may replace this temporary mechanism without
rewriting retained history.

## 8. Import and Clarification Safety

- Preserve original workbook metadata, sheet/cell provenance and raw values privately.
- Unknown required meanings block dependent staging/commit.
- A human clarification answer changes interpretation knowledge, not authorisation to commit
  Portal business records.
- Site identity, source revision and authorised coverage must be explicit before live commit.
- Imports must be idempotent and must not silently overwrite customer-owned Requested Date,
  alternative, Date Agreed, amendment or history.
- Missing a required site, plot, Call No. or service meaning; Wald ambiguity; invalid mapping;
  duplicate Call No.; source-order conflict; or stale dependency blocks the whole reviewed unit.
  Ignored fields may remain. Partial-row commit is not permitted.
- Source completion follows the approved domain transition; placeholder/source operational
  dates never become customer-owned dates.
- No direct source or SiteApp write-back is permitted.
- Do not expose raw workbook content, filenames, worksheets, rejected rows, customer data or
  internal diagnostics to unauthorised users.

## 9. Remaining delivery and later-phase items

The confirmed values above are not open semantic questions. DEC-048 approves the WALD05 V1
permissions, binding, ordering, Call No. grain, partial-only scope, readiness, atomicity,
six-year minimal committed-audit retention, queue/storage model and importer disposition.
DEC-049 and DEC-053 supply explicit WALD05 implementation authority. The bounded backend candidate
is documented in `documentation/wald/customer-wald05-backend-completion-2026-09-09.md`; this does
not change the dictionary's meanings or fingerprint. Dedicated backend QA, full Office UI,
production queue/worker operations, unattended disposal ownership, WALD06 pilot/cutover and any
additional customer-safe source field remain separate gates.
