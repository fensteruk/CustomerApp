# SiteApp Import Data Dictionary

_Last Updated: 2 September 2026_

This is the authoritative semantic dictionary for SiteApp spreadsheet imports into the
Customer Portal. It supersedes earlier mapping assumptions where they conflict. The
deterministic interpreter may identify structure and value shapes, but it must never infer
business meaning from a similar-looking code.

## Confirmed Call Types

| Source code | Confirmed source meaning | Portal import status |
|---|---|---|
| `PC1` | Plot Install | Importable as Windows |
| `CC1` | Cavity Closer 1 | Importable as Cavity Closers |
| `CM1` | Revisit 1 | Importable as a CML-related revisit on the CML service |
| `CM2` | Revisit 2 | Importable as a CML-related revisit on the CML service |
| `CML` | CML Call Off | Importable as CML |

`CC!` is invalid. It was a Shift+1 typo for `CC1`. A literal `CC!` source value remains
unknown, is reported as a likely typo, and requires Office correction/confirmation. It is
never silently normalised to `CC1`.

## Confirmed Products — Windows

| Code | Description | Customer roll-up | BF lead-time flag |
|---|---|---|---|
| `VS` | Vertical Slider | Total Windows | No |
| `TT` | Tilt and Turn | Total Windows | No |
| `BAY` | Bay Window | Total Windows | No |
| `ALI` | Aluminium Windows | Total Windows | No |
| `AOV` | Automatic Opening Vent Window | Total Windows | No |
| `FI` | Fire Window | Total Windows | No |

## Confirmed Products — Doors

| Code | Description | Customer roll-up | BF lead-time flag |
|---|---|---|---|
| `PSU` | PVC Door Utility | Total Doors | No |
| `PSG` | PVC Door Garage | Total Doors | No |
| `CDF` | Composite Door Front | Total Doors | No |
| `CDU` | Composite Door Utility | Total Doors | No |
| `CDG` | Composite Door Garage | Total Doors | No |
| `PSP` | PVC Sliding Patio | Total Doors | No |
| `BF` | Bifold | Total Doors | Yes |

## Excluded/Redundant Product Codes

`CAS`, `FLU`, `PFD`, `GLS`, `WP` and `MISC` are confirmed source columns retained when
useful for Office audit fidelity. They do not contribute to Total Windows or Total Doors,
must not appear as meaningful customer product types, and never affect BF lead time.

## Confirmed Spreadsheet Fields

- `Call No.` is the permanent idempotent external identity.
- `Site Name` is currently stable and may be used as an exact temporary source-site binding
  key. It is still only a source clue: it never creates or fuzzy-matches a Portal site.
- A future permanent RZ/SiteApp Site ID/reference is preferred whenever exported. Once it is
  available, it becomes the binding key and Site Name remains display evidence only.
- `Plot Ref` is the customer-safe source plot reference within the bound site.
- `Call type` contains a code from the dictionary above. Validity and Portal importability
  are separate decisions.
- The 19 product-code headers in the reference workbook match the confirmed product
  registry above. Blank quantities mean zero; numeric zero is valid; positive numerics are
  retained; negative or non-numeric quantities are invalid.
- `complete = Yes` means that specific source call-off part is complete. It establishes
  source completion but never invents a Completed Date. `No` means that source part is not
  complete and therefore participates in the existing guarded completion-reversal rules.
- `Items Ordered Status` is ignored.
- `Plot To Be Installed` is retained only for `PC1` as Fenster's operational arrival-to-install
  target date. It is never a customer requested, proposed, agreed or completion date.
- `Site Value` is ignored/excluded and never becomes a product or customer-visible value.
- The wider source contract may also accept genuine `Completed Date` and approved completion
  stage codes. Neither field exists in the reference workbook.

## Export Scope

Every manual SiteApp workbook contains whatever the exporter filtered. Row count and site
count do not prove completeness. The explicit scope values are:

- `PARTIAL_FILTERED_EXPORT` — mandatory default. Absence proves nothing and creates no
  missing-source conclusion.
- `SITE_COMPLETE_SNAPSHOT` — explicit Office confirmation that named bound source site(s)
  are complete. Missing comparison is limited to those sites.
- `GLOBAL_COMPLETE_SNAPSHOT` — explicit Office confirmation that the source namespace is
  globally complete. Only this scope permits namespace-wide missing comparison.

No scope deletes source records. Missing records are retained and reconciled. A represented
site is not assumed complete because the export can be filtered within that site.

## Safety Rules

- Customer product output contains only non-zero `Total Windows` and `Total Doors`.
- `Total Windows = VS + TT + BAY + ALI + AOV + FI`.
- `Total Doors = PSU + PSG + CDF + CDU + CDG + PSP + BF`.
- Missing product quantities are zero. Invalid negative or non-numeric quantities block the
  affected import row.
- Office detail may retain individual confirmed source quantities, including excluded codes.
- A positive quantity for exact code `BF` selects the five-week earliest normal request
  window. Other door quantities, names merely containing `BF`, and Total Doors do not.
- If a later source import sets or omits `BF`, the projected `BF` quantity is synchronised
  accordingly and the lead-time decision is recalculated from current source truth.
- Site names never create, merge or select a Portal site without an explicit binding.
- Saved workbook profiles are scoped to semantic version 3 and always retain the safe
  `PARTIAL_FILTERED_EXPORT` default. Earlier profiles are ignored for exact and likely
  matching so old completion, call-type or snapshot assumptions cannot be reused.

## Historical/Incorrect Assumptions Removed

- Removed `CC! → Cavity Closers`; `CC!` is invalid.
- Replaced “CC1 = Cavity Closer Delivery” with confirmed “CC1 = Cavity Closer 1”.
- Removed `CM1 → Snagging`; `CM1` is Revisit 1 and is now confirmed CML-related.
- Replaced the earlier unconfirmed/incorrect CM2 assumption with the confirmed rule that
  both `CM1` and `CM2` are CML-related revisits imported on the CML service.
- Removed raw `CAS`, `PFD`, `BF` and other individual-code customer presentation.
- Replaced the former unresolved `complete` assumption with the confirmed source-completion
  rule for the specific source call-off part; no Completed Date is invented.
- Removed represented-site/global completeness inference; every manual workbook defaults to
  a filtered/partial export unless Office explicitly confirms a stronger scope.
- Removed substring-based BF detection; only exact positive `BF` is authoritative.

Historical sprint and QA reports remain unchanged as evidence of what was understood and
tested at the time.
