# Deterministic Spreadsheet Interpretation

Date: 2026-08-28

Status: local backend implementation and corrected SiteApp semantic interpretation complete;
remaining business fields and disposable MySQL evidence remain outstanding.

## Boundary

The interpreter is a local, deterministic XLSX transport component. It has no AI provider,
HTTP client, cloud inference, external machine-learning dependency or network path. Workbook
data remains inside CustomerApp. Interpretation never writes a call-off or projection. Its
only persistence is an Office-confirmed structural profile and the existing private preview
control row.

The boundary remains:

```text
XLSX
  -> XlsxWorkbookInspector
  -> SpreadsheetStructureInterpreter
  -> Office-confirmed closed-enum mapping where required
  -> XlsxSourceReader normalisation
  -> SourceRecord
  -> existing preview / SourceProjectionImportService
```

`SpreadsheetStructureInterpreter` is the replaceable interface. The only implementation is
`DeterministicSpreadsheetStructureInterpreter`. There is deliberately no AI implementation
or provider hook.

## Reference Workbook Evidence

`Copy of siteapp1.xlsx` was inspected locally and read-only on 2026-08-28. Its SHA-256 is
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`. The operational file
is not copied into the feature branch or test fixtures, and row values are not documented.

The workbook has one visible worksheet, `Sheet1`, with used range `A1:AA49`. Row 1 is the
header, row 20 is physically blank, and there are 47 data rows across 13 distinct source
site names. This proves a multi-site export, but does not prove that it is a complete global
snapshot. Missing-source evaluation therefore remains limited to exact represented and bound
site keys.

It uses the Excel 1900 date system. `Plot To Be Installed` cells are genuine date-formatted
numeric cells and were read as `DateTimeImmutable`; `Site Value` is GBP currency-formatted.
There are no hidden sheets, rows or columns, formulas, merged cells, filters, frozen panes,
macros, embeddings, external links or workbook connections.

There is no Job Stage or Completed Date column. The `complete` field has 20 true-like and 27
false-like values across mixed casing, but its business meaning is unresolved. It is now
UNKNOWN structural evidence and all 47 normalised rows carry a null completion flag. It
cannot complete or reverse a service, never borrows `Plot To Be Installed`, and never
invents a completion date. All observed product columns are non-negative; the data includes
zeroes and one blank quantity.

The exact 27 headers, in order, are:

1. `Call No.`
2. `Site Name`
3. `Plot Ref`
4. `Items` + line break + `Ordered Status`
5. `Plot To Be` + line break + `Installed`
6. `complete`
7. `Call type`
8. `CAS`
9. `FLU`
10. `VS`
11. `TT`
12. `BAY`
13. `PFD`
14. `PSU`
15. `PSG`
16. `CDF`
17. `CDU`
18. `CDG`
19. `GLS`
20. `PSP`
21. `BF`
22. `ALI`
23. `AOV`
24. `FI`
25. `WP`
26. `MISC`
27. `Site Value`

The interpreter selects `Sheet1`/row 1 with confidence 99. It classifies all four critical
fields safely, classifies the operational date and commercial value under their safety
roles, and recognises all 19 columns from the confirmed product registry. Both `complete`
and `Items Ordered Status` remain UNKNOWN because no persistence meaning is approved.
Plot To Be Installed and Site Value keep their restrictive safety classifications but their
final uses remain unresolved.

The reference workbook exposed and now regression-tests a real defect: typed Excel dates
could throw while non-header rows were scored as header candidates. Profiling now converts
`DateTimeInterface` values through one deterministic date-only string boundary instead of
casting date objects directly.

Confirmed call types and their current Portal import status are:

| Workbook code | Portal service |
|---|---|
| `PC1` — Plot Install | Windows |
| `CC1` — Cavity Closer 1 | Cavity Closers |
| `CM1` — Revisit 1 | No confirmed mapping; reconciliation required |
| `CM2` — Revisit 2 | No confirmed mapping; reconciliation required |
| `CML` — CML Call Off | CML |

`CC!` is invalid. A literal value is an `UNKNOWN_CALL_TYPE`, reported as a likely Shift+1
typo for CC1, and is never silently corrected. CM1 and CM2 are not unknown; they are
valid-but-not-importable source codes until a Portal service mapping is confirmed.

The reference workbook contains 21 PC1, 17 CC1, seven literal invalid CC!, one CM1 and one
CM2 rows. It contains no CML row. The seven typo rows are unknown and the two revisit rows
require reconciliation; no code-similarity fallback exists.

A corrected read-only parser pass confirmed 47 normalised rows, one blank row, 13 distinct
site keys and null completion flags on every row. It created no source import run or
projected plot.

## Structural Inspection

The inspector accepts XLSX only, validates its ZIP signature and mandatory workbook parts,
rejects macros and embedded active content, reads the workbook's 1900/1904 date system, and
profiles every worksheet. Hidden worksheets are reported but never automatically selected.
The profiler retains only a configurable sample (500 data rows by default) while counting
all meaningful rows, which bounds interpretation memory. The authoritative selected-sheet
reader enforces the 5,000-row import limit.

The first 30 physical rows are considered as header candidates. Candidate scoring combines:

- recognised alias roles;
- non-empty column count and density;
- textual balance;
- unique header names;
- contiguous populated-column span; and
- populated data support in rows below.

Title, explanatory, blank and formatting rows before the header are tolerated. Multiple
plausible visible worksheets within the configured score margin produce
`WORKSHEET_SELECTION_REQUIRED` rather than a guess.

## Alias Dictionary and Normalisation

Aliases live centrally in `config/manual_source_import.php`. Matching trims whitespace,
folds case and ignores safe punctuation/spacing differences. Startup interpretation rejects
an alias that normalises to two different roles.

The configured roles and aliases are:

- CALL_NUMBER: Call No., Call No, Call Number, Call #, CallNo, Call Ref, Call Reference;
- SITE_NAME: Site, Site Name, Development, Development Name, Project, Project Name;
- PLOT_REFERENCE: Plot, Plot Ref, Plot Reference, Plot No., Plot Number, Unit, Unit No.;
- CALL_TYPE: Call Type, Type, Call Code, Call Type Code;
- COMPLETION_FLAG: complete, Complete, Completed, Complete?, Is Complete;
- COMPLETED_DATE: Completed Date, Completion Date, Date Completed;
- OPERATIONAL_TARGET_DATE: Plot To Be Installed, Install Date, Installation Date, Target Install Date;
- COMMERCIAL_VALUE: Site Value, Value, Plot Value.

`SITE_EXTERNAL_ID` is a manual mapping role. It has no guessed alias. If present it becomes
the exact source-site binding key while SITE_NAME remains the display clue. Otherwise the
exact Site Name is the binding key. Neither is a Portal site identifier and neither triggers
fuzzy binding.

## Column Profiling and Product Inference

Every proposed column exposes non-empty, text, integer, decimal, date and boolean-like
percentages; uniqueness/repetition; numeric range; zero/blank frequency; negative count;
sample values; average string length; and formula/cache evidence. Commercial sample values
are withheld.

Inference combines alias evidence with expected value patterns: near-unique Call Nos.,
repeated sites, plot-like uniqueness, categorical confirmed Call Types, boolean completion
values and genuine typed/strictly parseable dates. Excel-formatted dates arrive as dates;
plain numeric values are not reinterpreted as dates merely because they resemble an Excel
serial number.

Dynamic PRODUCT_QUANTITY proposals require a short code-like header, a predominantly
non-negative numeric profile, no date/operational/commercial classification and either a
confirmed registry entry or meaningful zero/blank evidence. An unregistered column may be
shown as structurally product-like, but it cannot be confirmed into an import mapping until
its business meaning enters the authoritative registry. Negative quantities block mapping/import.

The exact registry is `documentation/siteapp-import-data-dictionary.md`. Customer output is
derived only as Total Windows (`VS + TT + BAY + ALI + AOV + FI`) and Total Doors
(`PSU + PSG + CDF + CDU + CDG + PSP + BF`). CAS, FLU, PFD, GLS, WP and MISC remain excluded
from customer totals. Office preview/audit may retain individual confirmed quantities.

## Closed Roles and Confidence

The closed enum contains CALL_NUMBER, SITE_NAME, SITE_EXTERNAL_ID, PLOT_REFERENCE,
CALL_TYPE, COMPLETION_FLAG, COMPLETED_DATE, OPERATIONAL_TARGET_DATE, PRODUCT_QUANTITY,
COMMERCIAL_VALUE, IGNORE and UNKNOWN.

The default policy is:

- 95–100: automatically proposed and still validated server-side;
- 75–94: Office confirmation for critical/product mappings;
- below 75: manual mapping;
- Call No., Site, Plot and Call Type must each resolve unambiguously before commit.

Every column response includes its one-based source index, original and normalised header,
role, optional product code, score, profile evidence, deterministic reasons,
confirmation-needed state and ignored state. Duplicate critical candidates, low-confidence
headers, missing critical roles and ambiguous worksheet selection block automatic mapping.

## Safety Overrides

- Plot To Be Installed and its aliases can only remain OPERATIONAL_TARGET_DATE or be ignored.
  They are never requested, proposed, agreed or negotiation dates and are not passed to the
  importer.
- Site Value and its aliases can only remain COMMERCIAL_VALUE or be ignored. They cannot be
  products and their samples are withheld from the API.
- Unknown Call Types remain unknown. The mapping screen cannot turn them into a service;
  service mapping remains central and server-side.
- The unconfirmed `complete` field may only remain UNKNOWN or be ignored. It cannot be mapped
  to COMPLETION_FLAG.
- Only confirmed product registry codes can be mapped as PRODUCT_QUANTITY.
- Negative product quantities block the row/mapping. Blank becomes zero and numeric zero is
  retained.
- A formula in an imported field is accepted only when the workbook supplies a usable cached
  value. A critical formula without one is blocking. Formula text is never executed.

## Office Confirmation Contract

`POST /portal/source-imports/previews/{preview_uuid}/interpretation` accepts:

```json
{
  "sheet": "Calls",
  "header_row": 4,
  "columns": [
    {"source_index": 1, "semantic_role": "CALL_NUMBER", "subtype": null},
    {"source_index": 2, "semantic_role": "SITE_NAME", "subtype": null},
    {"source_index": 3, "semantic_role": "PRODUCT_QUANTITY", "subtype": "CAS"},
    {"source_index": 4, "semantic_role": "IGNORE", "subtype": null}
  ],
  "confirm": true
}
```

The endpoint is restricted to active, complete, non-preview Fenster Office Staff and to the
preview owner. It reparses the private file, reruns interpretation, validates source indices,
enforces singleton/critical/product and safety rules, normalises through `XlsxSourceReader`,
rebuilds the non-mutating diff, and saves a confirmed profile. Client-supplied rows, scores,
diffs or evidence are never trusted.

Auto-mapped ready previews may also be explicitly confirmed or changed through this endpoint.
External Site Users receive 403 and cannot inspect, confirm or commit mappings.

## Saved Profiles and Similarity

`workbook_interpretation_profiles` stores UUID, source namespace, semantic version, selected sheet, structural
fingerprint, normalised ordered headers, type profile, confirmed closed-enum mappings,
represented-sites snapshot scope, confirmer, version and timestamps. Filename is not part of
the identity.

The exact fingerprint includes normalised sheet identifier, header order and deterministic
column-type evidence. Exact matches reuse the latest confirmed mapping but re-inspect the
workbook and revalidate every safety/value rule. Changed confirmation for the same fingerprint
creates the next version; an identical confirmation reuses its version.

Only profiles with the current SiteApp semantic version participate in exact or likely
matching. The correction pass advances that version to 2, invalidating profiles that could
contain the old CC!, CM or completion assumptions.

Near matches use a deterministic weighted comparison: Jaccard header-set similarity, ordered
header overlap, per-header type compatibility and sheet-name agreement. A likely match is
returned as a suggestion and always requires Office confirmation. Material differences are
interpreted afresh.

## Normalisation and Existing Importer

Confirmed mappings are converted into `XlsxSourceRow` and then the existing `SourceRecord`.
Excel objects never enter the importer. The reference workbook's unconfirmed `complete`
field is not mapped, so completion remains dependent on approved stage/Completed Date facts.

Source-site binding, Call No. idempotency, missing-source represented-site scope, completion
precedence, rollback, run audit and reconciliation remain owned by the existing manual-import
and Sprint 3B services.

## Test Coverage and Limitations

Automated coverage includes a fictional structure-equivalent 27-column reference fixture,
typed Excel-date regression coverage, shifted headers and title rows, reordered columns, aliases,
blank/irrelevant columns, unknown columns, product movement, completion casing, ambiguous plot
candidates, missing critical fields, multiple plausible sheets, commercial/operational safety,
formulas without cache, exact/near profile matching, profile versioning, source-site binding,
unconfirmed Call Types, completion import, no-network architecture and a 3,000-row synthetic
workbook.

An isolated local Windows/PHP 8.4 benchmark over 3,000 generated data rows completed
inspection plus interpretation in 3.876 seconds with a 30 MiB reported PHP peak and an
overall confidence of 96. The automated gate additionally requires less than 12 seconds and
less than 160 MiB incremental peak allocation to avoid a gross regression; it is a development
sanity bound, not a production throughput guarantee.

Remaining evidence and decisions:

1. Confirm a Portal service mapping for CM1/Revisit 1 and CM2/Revisit 2, or retain them as
   reconciliation-only source rows. CC! is conclusively invalid.
2. Define `complete`, Items Ordered Status, Plot To Be Installed and Site Value policy.
3. Confirm whether Site Name is durable and whether the normal export scope is selected-site,
   multi-site or global.
4. Run migration/profile uniqueness, versioning and import-transaction checks on a disposable
   MySQL 8.4 database. This host currently has no MySQL client/server, Docker runtime or local
   port 3306 listener. Production is not an acceptable substitute.

## Local Verification

- clean SQLite `migrate:fresh --seed`: passed through all 12 migrations, including `000009`
  and `000010`;
- actual-workbook rolled-back preview gate: 1 passed, 21 assertions;
- deterministic interpreter suite: 18 passed, 104 assertions;
- focused interpreter/manual-import/Sprint 3B suite: 49 passed, 257 assertions;
- complete Pest suite: 271 total, 256 passed, 1,399 assertions; 15 existing MySQL-only
  concurrency cases skipped on SQLite;
- Pint: passed;
- Composer validation: passed;
- Composer audit: no security vulnerability advisories;
- Vite production build: passed after rerunning outside the local sandbox because Windows
  denied the sandboxed child process with `spawn EPERM`;
- Git whitespace check: passed.

The actual workbook was rendered and inspected locally with no external network/API use. No
Forge, production database, deployment, external AI/API or GitHub Actions workflow was
contacted or changed.

## SiteApp Semantic Correction Verification — 2 September 2026

- Read-only actual-workbook pass: Sheet1, A1:AA49, 47 data rows, one blank row, 13 source
  sites, confidence 99; 19 confirmed product columns; `complete` and Items Ordered Status
  remain UNKNOWN; every normalised completion flag is null.
- Focused semantic/interpreter/import/lead-time/customer-projection regressions: 93 tests,
  519 assertions, all passed.
- Full Pest suite: 277 total, 262 passed, 1,455 assertions; 15 MySQL-only concurrency tests
  skipped on SQLite.
- Clean local SQLite migrate/seed passed through 14 migrations. Migration 000011 rollback
  and re-apply passed, and all migrations report Ran.
- Pint passed after formatting; Composer validation passed; Vite production build passed;
  Git whitespace check passed.
- Composer audit is not green: seven advisories published on 1 September affect the locked
  `filament/filament` 5.6.8 and `league/commonmark` 2.9.0 packages. Package/lockfile upgrades
  were outside this narrow task and were not made.
- Disposable MySQL 8.4 remains unavailable on this host: no MySQL client/server, Docker,
  Podman or port 3306 listener exists. MySQL migration/profile/import verification remains
  required before this branch can be considered release-ready.
