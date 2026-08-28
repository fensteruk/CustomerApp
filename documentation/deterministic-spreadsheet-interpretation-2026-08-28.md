# Deterministic Spreadsheet Interpretation

Date: 2026-08-28

Status: local backend implementation complete; reference-workbook and disposable MySQL evidence remain outstanding.

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

## Reference Workbook Status

`Copy of siteapp1.xlsx` was not present in the project, the Codex attachment store, Desktop,
Downloads or Documents paths available to this task. Therefore no claim is made about its
actual worksheet names, hidden state, physical header row, formulas, date system, row count,
snapshot scope or complete product set.

The implementation and automated fixtures use only the semantics explicitly confirmed in
the task: Call No., Site Name, Plot Ref, Items Ordered Status, Plot To Be Installed,
`complete`, Call Type, product-code quantities and Site Value. `Items Ordered Status` remains
UNKNOWN because no persistence meaning was approved. It is not treated as a completion stage.

Confirmed manual-workbook Call Types are deliberately limited to:

| Workbook code | Portal service |
|---|---|
| `PC1` | Windows |
| `CC1` | Cavity Closers |
| `CML` | CML |

The transport-independent importer retains its older source-contract mappings for other
namespaces, but the `siteapp-xlsx` analysis boundary does not accept `CM1` or `CM2` without a
new explicit source decision. Unknown values produce `UNKNOWN_CALL_TYPE` and cannot commit.

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
known example (`CAS`, `PFD`, `BF`) or meaningful zero/blank evidence. Unknown product codes
remain confirmable; the product set is not fixed. Negative quantities block mapping/import.

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

`workbook_interpretation_profiles` stores UUID, source namespace, selected sheet, structural
fingerprint, normalised ordered headers, type profile, confirmed closed-enum mappings,
represented-sites snapshot scope, confirmer, version and timestamps. Filename is not part of
the identity.

The exact fingerprint includes normalised sheet identifier, header order and deterministic
column-type evidence. Exact matches reuse the latest confirmed mapping but re-inspect the
workbook and revalidate every safety/value rule. Changed confirmation for the same fingerprint
creates the next version; an identical confirmation reuses its version.

Near matches use a deterministic weighted comparison: Jaccard header-set similarity, ordered
header overlap, per-header type compatibility and sheet-name agreement. A likely match is
returned as a suggestion and always requires Office confirmation. Material differences are
interpreted afresh.

## Normalisation and Existing Importer

Confirmed mappings are converted into `XlsxSourceRow` and then the existing `SourceRecord`.
Excel objects never enter the importer. A confirmed completion flag is represented as an
optional source completion fact; true completes the projected service, false permits the
existing guarded reversal behavior, and completion without a real Completed Date creates the
existing reconciliation warning without inventing a date.

Source-site binding, Call No. idempotency, missing-source represented-site scope, completion
precedence, rollback, run audit and reconciliation remain owned by the existing manual-import
and Sprint 3B services.

## Test Coverage and Limitations

Automated coverage includes shifted headers and title rows, reordered columns, aliases,
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

Remaining evidence:

1. Supply `Copy of siteapp1.xlsx` in an accessible local path so its real structure can be
   inspected, rendered and exercised without committing confidential row data.
2. Run migration/profile uniqueness, versioning and import-transaction checks on a disposable
   MySQL 8.4 database. This host currently has no MySQL client/server, Docker runtime or local
   port 3306 listener. Production is not an acceptable substitute.
3. Confirm whether the real workbook is one-site, selected multi-site or global. Until then,
   every profile remains `represented_sites`; global missing-source evaluation is impossible.

## Local Verification

- clean SQLite `migrate:fresh --seed`: passed through 12 migrations, including `000009` and
  `000010`;
- deterministic interpreter suite: 16 passed, 87 assertions;
- focused interpreter/manual-import/Sprint 3B suite: 47 passed, 239 assertions;
- complete Pest suite: 254 passed, 1,381 assertions; 15 existing MySQL-only concurrency
  cases skipped on SQLite;
- Pint: passed;
- Composer validation: passed;
- Composer audit: no security vulnerability advisories;
- Vite production build: passed after rerunning outside the local sandbox because Windows
  denied the sandboxed child process with `spawn EPERM`;
- Git whitespace check: passed.

No Forge, production database, deployment, external AI/API or GitHub Actions workflow was
contacted or changed.
