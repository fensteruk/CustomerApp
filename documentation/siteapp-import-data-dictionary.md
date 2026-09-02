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
| `CM1` | Revisit 1 | Valid source code, but no confirmed four-service Portal mapping; reconciliation required |
| `CM2` | Revisit 2 | Valid source code, but no confirmed four-service Portal mapping; reconciliation required |
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
- `Site Name` is a source site clue used only through an explicit source-site binding. Its
  long-term durability as a key remains unresolved.
- `Plot Ref` is the customer-safe source plot reference within the bound site.
- `Call type` contains a code from the dictionary above. Validity and Portal importability
  are separate decisions.
- The 19 product-code headers in the reference workbook match the confirmed product
  registry above. Blank quantities mean zero; numeric zero is valid; positive numerics are
  retained; negative or non-numeric quantities are invalid.
- The wider source contract may accept genuine `Completed Date` and approved completion
  stage codes. Neither field exists in the reference workbook.

## Unconfirmed Spreadsheet Fields

The following fields are structural evidence only and must not be assigned an unapproved
Portal meaning:

1. `complete` — meaning is unresolved. It does not currently set or reverse completion.
2. `Items Ordered Status` — final Portal use is unresolved.
3. `Plot To Be Installed` — may be an arrival-date note; it is operational context only and
   never becomes a requested, proposed, agreed or completion date.
4. `Site Value` — policy remains unresolved. It is commercial data, excluded from customer
   output and never treated as a product quantity.
5. `Site Name` durability — whether it is a stable key or display name remains unresolved.
6. Export scope — selected-site, multi-site or global completeness remains unproven.
7. Any source code absent from this dictionary.

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
- Saved workbook profiles are scoped to semantic version 2. Version 1 profiles are ignored
  for exact and likely matching so old `CC!` or `CM1`/`CM2` assumptions cannot be reused.

## Historical/Incorrect Assumptions Removed

- Removed `CC! → Cavity Closers`; `CC!` is invalid.
- Replaced “CC1 = Cavity Closer Delivery” with confirmed “CC1 = Cavity Closer 1”.
- Removed `CM1 → Snagging`; `CM1` means Revisit 1 and has no confirmed Portal service.
- Removed `CM2 → CML`; `CM2` means Revisit 2 and has no confirmed Portal service.
- Removed raw `CAS`, `PFD`, `BF` and other individual-code customer presentation.
- Removed the assumption that the workbook's `complete` field proves completion.
- Removed substring-based BF detection; only exact positive `BF` is authoritative.

Historical sprint and QA reports remain unchanged as evidence of what was understood and
tested at the time.
