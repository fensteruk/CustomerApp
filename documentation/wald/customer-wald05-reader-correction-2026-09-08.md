# CUSTOMER-WALD05 — W5-T01 reader correction

Date: 8 September 2026. Authority: DEC-052 (explicit user approval).
State: implemented on the non-deploying WALD05 feature branch; not independently QA-accepted,
on main or deployed. Accepted WALD04 remains the historical input, not a claim that the changed
reader has already received dedicated acceptance.

## Correction and identity

The reader now recognises only the inert Excel extension workbookPr with namespace
`http://schemas.microsoft.com/office/spreadsheetml/2010/11/main` at the exact expanded-name path
`workbook/extLst/ext/workbookPr`, with all three ancestors in the core SpreadsheetML namespace.
It does not use this extension to establish the date system. Children are still streamed and
checked; no blanket extension-subtree bypass is introduced.

Misplaced core nodes, spoofed parent namespaces, wrong namespaces, injected sheets/core
properties and DTD/entity content still refuse safely. Archive/XML resource limits, physical
lineage checks, no-formula-execution rules and the accepted WQ-01–05 corrections remain intact.
No workbook bytes were modified and no external or alternative runtime parser was added.

New identities:

- reader `wald-0.2.2` (was `wald-0.2.1`);
- XLSX adapter `3` (was `2`); CSV adapter remains `2`;
- dictionary version/fingerprint unchanged;
- downstream CoreIdentity, AnalysisSnapshot gate and KnowledgeIdentity adapter pins aligned;
- old reader/profile pins become STALE, not silently refreshed or rewritten.

CoreIdentity's commit field continues to identify immutable adopted input `4aa5ffb...`; the reader
version and this correction's separate Git revision identify the fork change. Semantic executable
`f4fda0f...` remains dictionary/adapter provenance; only its reader compatibility label changed.
The full corrected commit is recorded in the continuation handoff. No SiteApp backport occurred;
WD-44 is a candidate for review by the other distribution's owner.

## Evidence

`php artisan test tests/Unit/Wald/WorkbookExtensionCompatibilityTest.php --compact` initially
reproduced two failures on the old reader while seven negative cases passed. After correction,
the expanded 11-case suite passes, including two core-date-system cases and nine refusal cases.
The additional WALD04 compatibility test proves prior reader pins are stale with unchanged
dictionary fingerprint.

The unchanged local reviewed workbook now opens without reader warnings and passes full generic
profiling with `complete=true`, engine `wald-0.2.2`, checksum
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.
Private profile output stays under ignored `storage/app/wald05-audit-20260908/` and is not staged.
This proves structural reader compatibility, not authorised staging or Portal commit.

The current [continuation report](customer-wald05-import-review-integration-2026-09-08.md) records
combined/full regression totals, failed attempts, MySQL foundation evidence and incomplete WALD05
capabilities. DEC-050/051 selection remains 45 included and two explicitly excluded records;
there is no global CC! alias, global CM2 exclusion or general test-site filter.

## Exact correction files

- `app/Wald/Services/XlsxWorkbookSource.php`
- `app/Wald/Services/WorkbookProfiler.php`
- `app/SourceImport/Semantics/Data/CoreIdentity.php`
- `app/SourceImport/Knowledge/AnalysisSnapshot.php`
- `app/SourceImport/Knowledge/KnowledgeIdentity.php`
- `tests/Unit/Wald/WorkbookExtensionCompatibilityTest.php`
- `tests/Unit/Wald04/ContractsTest.php`
- `DECISIONS.md` (DEC-052)
- this report

No migration is required by the reader correction itself. The separately implemented WALD05
binding foundation has its own additive migration and is not part of the generic reader fix.
