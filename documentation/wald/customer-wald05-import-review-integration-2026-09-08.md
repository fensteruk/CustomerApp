# CUSTOMER-WALD05 Implementation Continuation Report

Date: 8 September 2026. Current result: **PARTIAL — W5-T01 corrected; WALD05 binding foundation
implemented and tested. No remaining workbook business-data blocker. End-to-end import is not
implemented and dedicated WALD05 QA must not begin yet.**

## Current branch, authority and reader identity

Branch: `feature/customer-wald05-import-review-integration`.
Prior audit checkpoint: `7be7aa1ecf450b55f160f64ce013e961a28ef1b6`.
Reader correction commit: `e9e1c3a2a3ff79cfe5f097bea143f51195becd55`.
Accepted historical WALD04 input: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`.
DEC-052 records the explicit user approval for the bounded core correction followed by resumed
WALD05 implementation. No further blanket permission is needed; no new business question is raised.

Reader is now `wald-0.2.2`, XLSX adapter 3, CSV adapter 2. CoreIdentity reader label,
KnowledgeIdentity adapter pins and the AnalysisSnapshot reader gate agree. Prior reader pins
become STALE without rewriting history. The adopted-input commit stays historical provenance;
the correction SHA above identifies the fork change. No dictionary version, fingerprint or
business meaning changed. [Correction detail](customer-wald05-reader-correction-2026-09-08.md).

## Workbook integrity, selection and concrete ambiguities

The original workbook remains unchanged and untracked, with SHA-256
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.
The corrected accepted reader now opens it without warnings and full generic profiling returns
`complete=true`. This is not a staging, review or commit receipt.

The completed audit below remains the evidence: 47 nonempty rows, 45 included, one CM2 exclusion
under DEC-050 and exact Nick TEST row 32 / Call No. 5181 excluded under DEC-051. Included services:
20 Windows, 24 Cavity Closers, one CML. Completion: 19 Yes / 26 No. Seven positive BF rows.
No duplicates, conflicting included site/plot, invalid completion or invalid product quantity.
Row 20 is blank, not an invalid source record. Twelve exact source site names need explicit Office
bindings before a real import; no real Portal mapping was inferred or created.

Concrete business ambiguity: **NONE**. W5-T01 is resolved, not a new permission gate. The historical
W5-P01/P02 synthetic cases do not block the actual selected sample. Future unresolved data must
still fail closed, not acquire invented meaning from these examples.

## Completed runtime slice

| Capability | Current implementation / limit |
|---|---|
| Source-site bindings | Office-only exact namespace + SOURCE_SITE_ID or EXACT_SITE_NAME. Separate immutable draft, explicit activation, reasoned supersession/revocation and immutable command history. No fuzzy matching, site creation or owner/site reassignment. Binding identity is independent of workbook family and structural profiles. |
| Authority | Current stored active non-preview Office role, selected valid owner/site, separate binding abilities and authorization inside each transaction. Null-organisation Office works; external roles, preview, inactive and stale-role callers refuse. |
| Default-off boundary | `CUSTOMER_WALD_IMPORT_ENABLED` defaults false. No HTTP routes/UI or production enablement added. |
| Versions and use pins | Immutable target/version/hash; active pointer and epoch on the identity root. Every draft/activation/revocation advances the epoch. `assertCurrent` requires a transaction and fresh exact pin equality; it alone grants no projection authority. Revoked versions cannot be resurrected through another workbook family. |
| Command replay and atomicity | Exact authenticated command replay returns the existing historical receipt; changed payload refuses. Binding/version and required command audit are one transaction; injected audit failure rolls back all and is not retried. MySQL native JSON ordering is normalized consistently. Historical replay is not a claim that a binding remains active: every new use must resolve/revalidate it. |
| History and retention | Append-only version/command records, authenticated actor ID/name snapshot, reason/before/after and bounded cursor reads. Command audit has a six-year retain-until floor; no purge, scheduler, deletion endpoint or production disposal is enabled. Final committed-import audit and dependency/hold integration remain future work. |
| Manual ordering | Pure validated ExportOrder value object implements declared date then MORNING/AFTERNOON and STAFF_DECLARED provenance. There is no durable stream ordering or committed-slot enforcement yet. |
| Reviewed selection | Pure exact-checksum helper implements DEC-050 CC! correction/CM2 exclusion and DEC-051 exact-row exclusion. Raw values and approval IDs survive. Changed checksum/row identity does not inherit exclusions; no global alias or test-site detector. It is not yet wired to an import run. |

## Persistence / migrations

One new forward migration, `2026_09_08_000011_create_wald_import_foundation.php`, adds:
`wald_source_bindings`, `wald_binding_versions`, `wald_import_commands`.
Six database triggers protect immutable root identity, version history and audit against direct
bulk updates/deletes. Composite version/active-pointer and site/organisation foreign keys retain
containment; exact source identities use a SHA-256 key to avoid case-insensitive name matching.
The 13 accepted migrations are unchanged. No local application/production database was migrated;
tests used in-memory SQLite and explicitly disposable loopback MySQL only.

## Not yet implemented — no end-to-end readiness claim

- Private upload, durable import runs, leases/recovery and upload confirmation capture.
- Full Wald/dictionary/WALD04 knowledge orchestration and immutable neutral staging.
- Immutable non-mutating preview, 24-hour validity and all dependency/stale checks.
- Source visit/observation persistence, duplicate gate across a full run and permanent use history.
- Durable manual stream ordering, same-slot correction successor and committed-run receipts.
- Deliberate projection adapter, partial-only commit and omission preservation in that adapter.
- Source completion/reversal transitions, Portal date/negotiation/amendment/history regressions.
- One-transaction whole-run projection/audit commit and all required commit races.
- Office UI, safe synthetic end-to-end parity, recovery/performance and dedicated QA.

No legacy importer was reconnected or retired. No real workbook was imported. No customer
product, completion, operational date, Requested Date, Date Agreed or Portal history was written.
Binding-command transaction tests are not whole-import atomicity/concurrency evidence.

## Verification and exact results

| Command / gate | Result |
|---|---|
| W5-T01 synthetic test before fix | Two positive cases reproduced invalid_xml; seven initial negative cases passed |
| Expanded reader suite | 11 passed; core date-system authority, exact extension lineage, spoofed/misplaced nodes and DTD refusal |
| `php artisan test tests/Feature/Wald05 tests/Unit/Wald05 --compact` | 57 total: 53 passed, 4 MySQL skips, 135 assertions |
| `php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 tests/Unit/Wald05 tests/Feature/Wald05 --compact` | 945 total: 930 passed, 15 skips, 4,375 assertions |
| `php artisan test --compact` | 1,179 total: 1,149 passed, 30 environment skips, 5,563 assertions |
| MySQL: `php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/SourceBindingTest.php tests/Unit/Wald05 --compact` | 53 total: 52 passed, one SQLite-only DDL test skipped, 133 assertions; final exit 0 |
| MySQL: `php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/MysqlFoundationConcurrencyTest.php --compact` | Four scenarios passed, 80 assertions: ten iterations each, 40 race groups / 80 workers |
| `php scripts/verify-wald05-foundation-upgrade.php` | PASS: 13 baseline + one additive migration; 12 existing Portal/knowledge tables compared unchanged; three new tables/six guards; empty rollback/reapply passes; populated rollback refused before DDL |
| SQLite migration coverage | Empty rollback/reapply and populated refusal pass in focused tests |
| `php storage/app/wald05-audit-20260908/read-wald.php` | Original workbook reader and full generic profile pass; no warnings; exact checksum unchanged |
| `vendor\bin\pint --test` | PASS; scoped formatting applied first |
| `composer validate --strict` | Valid |
| `composer audit --format=json` | Eight inherited advisories (Filament 3, CommonMark 4, Livewire 1); cache-write warning did not prevent retrieval. No remediation merge or lockfile change |
| `npm run build` | PASS, Vite 8.1.4; authorised local execution after previously established sandbox child-process restriction |
| `git diff --check` | PASS; final scoped path/staging checks recorded in handoff |

MySQL was Oracle 8.4.11 on `127.0.0.1:33486`, task-only directory
`storage/app/wald05-mysql-20260908/data`, schemas `customerapp_wald05_foundation` and
`customerapp_wald05_upgrade`. No .env edit. Verified port/datadir before clean shutdown;
server log confirms shutdown complete. Diagnostic data stays ignored, not committed.

The four foundation races are activate/activate, exact-command replay, draft/draft and
activate/revoke. They do not cover preview double commit, AM/PM, older/newer, source visit races,
profile revoke versus commit, projection epoch or same-slot corrections: those remain outstanding.

Failed attempts were resolved, not hidden: receipt map order first differed on SQLite and then
MySQL; both now return canonical order. The initial MySQL audit-failure fixture used trigger DDL,
which implicitly committed the test transaction. It was replaced with pre-insert failure injection,
retaining full rollback and exactly-one-attempt assertions. A rerun reported passes with exit 1;
direct Pest confirmation and the final expanded run exited 0. One diagnostic CLI flag was unsupported
and was removed. Existing tests/assertions were not weakened and no production setting was altered.

## Files changed / remaining gates

Reader correction files are listed in the separate correction report. Foundation files:

- `app/SourceImport/Integration/ImportConflict.php`, `ImportPolicy.php`, `ImportStore.php`,
  `SourceBindingService.php`, `ExportOrder.php`, `ReviewedWorkbookSelection.php`;
- `config/wald_import.php`;
- `database/migrations/2026_09_08_000011_create_wald_import_foundation.php`;
- `tests/Feature/Wald05/SourceBindingTest.php`, `MysqlFoundationConcurrencyTest.php`;
- `tests/Unit/Wald05/ContractsTest.php`, `tests/Support/Wald05ConcurrencyWorker.php`;
- `scripts/verify-wald05-foundation-upgrade.php`;
- current governing documentation, work package, divergence register and this report.

Unrelated Sprint 3E whitespace, untracked source workbook and output/ remain preserved. No source
workbook, private audit/profile, generated output or local database was staged. No push, main,
SiteApp, production, deployment, dependency remediation or WALD06 action occurred.

Next work is private upload/durable runs and reviewed analysis/staging, followed by preview and
the guarded projection transaction. This is remaining implementation, not a new approval blocker.
WALD05 is not ready for dedicated QA. WALD06 still requires complete/accepted WALD05 and its own
pilot/cutover plus storage/worker/backup/security/release approvals. G09 ownership gates unattended
disposal only; no deletion scheduler exists.

CUSTOMER-WALD05 partially complete — further implementation required

---

## Historical actual-workbook audit checkpoint (before DEC-052 reader correction)

The evidence below is retained. Its W5-T01 pause and no-runtime statements are superseded by
the current reader correction and binding-foundation implementation above.

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Current result: **PARTIAL — actual workbook audit completed; accepted reader compatibility failure reproduced. Not ready for WALD05 QA.**

## Current authority and branch

The latest management continuation instruction requires an actual workbook audit under DEC-050,
then implementation without another blanket approval unless a real blocker is found. DEC-051
subsequently excludes the exact Nick TEST record at the user's request. The earlier
synthetic W5-P01/P02 examples are not grounds to stop this selected workbook. DEC-049's implementation
authority remains valid. This report's current section supersedes the historical entry review below.

Branch: `feature/customer-wald05-import-review-integration`.
Audited checkpoint: `b221784be25e7cbff767a6b0ddad413d77c08036`.
Accepted WALD04: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`.
Documentation reset: `e84999cf66fc90aac3007842a538672b018b3e03`.
The generic, semantic and knowledge executable trees listed in the historical input table below
remain unchanged. No main, push, deployment, production or SiteApp action occurred.

## Workbook integrity and actual audit

The original local workbook remains unchanged and untracked. SHA-256 before/after inspection:
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`, matching DEC-050.
One sheet, `Sheet1`, occupied range A1:AA49. Row 1 is the header; row 20 is entirely blank,
not a source record with a missing Call No. Every other row from 2 through 49 is inventoried.

| Actual finding | Result |
|---|---|
| Non-empty source records | 47 |
| INCLUDED under the scoped business rules | 45 |
| IGNORED_BY_DEC050 | 1; CM2 at row 19 |
| IGNORED_BY_USER (DEC-051) | 1; Nick TEST at row 32, Call No. 5181 |
| BLOCKING_INVALID / BLOCKING_AMBIGUOUS source records | 0 / 0 in the audited required fields |
| Windows / Cavity Closers / CML selected records | 20 / 24 / 1 |
| Raw PC1 / CC1 / CC! / CM1 / CM2 | 21 / 17 / 7 / 1 / 1 |
| Included complete Yes / No / invalid | 19 / 26 / 0 |
| Duplicate Call Nos., identical or conflicting | 0, across all 47 records including the excluded CM2 |
| Blank / malformed Call Nos. in non-empty records | 0 / 0; every supplied identity is a positive exact integer |
| Included exact source site names / site-and-plot identities | 12 / 45 |
| Missing site or plot / competing included visits for an exact site-and-plot | 0 / 0 |
| Formula cells | 0 |
| Positive BF included rows | 7; rows 2, 5, 9, 42, 43, 44, 45 |
| Blank BF included rows | None; the original blank at row 32 is now excluded by DEC-051 |
| PC1 operational date values | 20 populated numeric date/time values; no Portal date authority |

`INCLUDED` means selected by the workbook-specific business audit, **not staged, bound, reviewed
or committable**. No Portal site mapping has been invented or verified by reading application data.
All 12 included exact source names require explicit Office bindings to existing Portal sites before a real
import. Names with/without a suffix remain separate exact source identities; descriptive Plot Ref
strings are preserved whole, including leading zeroes. No name similarity, test-looking label,
embedded plot number or date was used to infer a merge, exclusion, past-plot filter or new plot.
Row 32 was excluded only after the user's explicit instruction, not because its label looked like
test data. Its inventory class is IGNORED_BY_USER rather than misattributing it to DEC-050.

All 13 approved product columns are physically present. None is absent. Included-record counts:

| Product | Blank | Explicit zero | Positive | Invalid |
|---|---:|---:|---:|---:|
| VS, TT, BAY, ALI, AOV, FI (each) | 0 | 45 | 0 | 0 |
| PSU | 0 | 21 | 24 | 0 |
| PSG | 0 | 44 | 1 | 0 |
| CDF | 0 | 26 | 19 | 0 |
| CDU, CDG (each) | 0 | 45 | 0 | 0 |
| PSP | 0 | 44 | 1 | 0 |
| BF | 0 | 38 | 7 | 0 |

All six excluded product columns are present and remain excluded. In particular, nonzero CAS
does not become a Window quantity; the approved Windows columns in this sample are all zero.
Absence safety still needs synthetic coverage because this sample has no absent approved column.
Operational dates remain private PC1 arrival/install evidence. No lead-time date or completion
date was calculated or imported. Both excluded rows produce no product or completion effect.

The detailed inventory, raw-cell evidence, source values and exact name list are local only under
`storage/app/wald05-audit-20260908/`, outside public storage and ignored by Git. The primary inventory
is `row-inventory.json`. It contains every source coordinate, raw/effective code, Call No., site,
plot, completion, operational date, product presence/value, classification and reason. It must not
be staged or copied into the test corpus. The workbook was read with the bundled spreadsheet
analysis tooling and independently compared against read-only OOXML extraction: 1,323 cell
positions matched, zero disagreements. The accepted dictionary Quantity parser separately checked
all 585 currently included product positions with zero invalid values (598 before DEC-051).
These are audit checks, not an
alternative runtime interpretation engine and not a successful Wald analysis.

## Concrete ambiguity and accepted-reader gate

**Concrete business-data ambiguity found: NONE.** The previous hypothetical cross-visit examples
are not present in the selected data and do not block it. DEC-050 remains pinned to the exact
workbook hash: seven CC! occurrences are confirmed CC1; one CM2 is ignored; the global dictionary
and fingerprint are unchanged. Other workbooks inherit neither exception.

**W5-T01 — reproduced technical failure:** the accepted `WorkbookSourceFactory` / XLSX reader
refuses these exact bytes with `AnalysisProblem(problemCode: invalid_xml)` before returning sheets.
In `xl/workbook.xml`, both these nodes are present:

- core `{http://schemas.openxmlformats.org/spreadsheetml/2006/main}workbookPr`
  at `workbook/workbookPr`;
- extension `{http://schemas.microsoft.com/office/spreadsheetml/2010/11/main}workbookPr`
  at `workbook/extLst/ext/workbookPr`, with `chartTrackingRefBase="1"`.

`app/Wald/Services/XlsxWorkbookSource.php`, `nodes()`, selects wanted nodes by local name, then
requires every workbookPr to have the core parent path. The differently namespaced extension
therefore reaches the wrong path check and throws at line 318. This is an observed reader
compatibility problem, not a duplicate, unknown business meaning or evidence that the workbook's
records are invalid. The existing passing suites do not cover this actual metadata shape.

The accepted generic core is frozen input to WALD05. No bypass, source rewrite, exception removal
or unversioned core edit was made. The next technical prerequisite is a narrowly reviewed,
versioned reader compatibility correction with a safe synthetic extension fixture, preserved
physical-lineage/unsafe-XML negative tests, generic-core regression and a newly recorded executable
identity. This is a specific core-baseline correction decision, not a request to reapprove WALD05.
Do not substitute the local diagnostic extraction for the accepted runtime reader.

## Runtime, persistence and integration state

No WALD05 runtime implementation, migrations, source-site binding lifecycle, private intake/run
state, manual ordering, analysis/knowledge integration, immutable staging, preview/staleness,
projection adapter, completion transition, atomic commit, receipt/correction or concurrency work
was added in this continuation. The private local audit files are not an upload/import subsystem.
Portal dates, requests, histories and production structures remain untouched. No live source-site
binding or import was performed. No MySQL WALD05 migration, upgrade or race evidence exists yet.
No real workbook or customer-data fixture has been committed.

## Fresh verification

| Command / check | Actual result |
|---|---|
| `Get-FileHash -LiteralPath 'Copy of siteapp1.xlsx' -Algorithm SHA256` | DEC-050 match before/after; original unchanged |
| Local bundled spreadsheet read and independent OOXML comparison | 47 records, 1,323 cell comparisons, zero differences |
| `php storage/app/wald05-audit-20260908/verify-quantities.php` | 585 approved parser checks after DEC-051, zero invalid coordinates, exit 0 |
| `php storage/app/wald05-audit-20260908/read-wald.php` | Reproduced `invalid_xml`, incomplete analysis, exit 2; initial uncaught diagnostic attempt exited 255 |
| `php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 --compact` | 876 total: 865 passed, 11 skipped, 4,218 assertions; exit 0 |
| `php artisan test --compact` | 1,110 total: 1,084 passed, 26 skipped, 5,406 assertions; exit 0 |
| `vendor\bin\pint --test` | Passed |
| `composer validate --strict` | Valid |
| `composer audit --format=json` | Eight advisories across Filament (3), CommonMark (4), Livewire (1); exit 1. Cache-write warning, advisory retrieval succeeded |
| `npm run build` | Initial sandbox child-process EPERM; authorised local retry passed, Vite 8.1.4 |
| Focused WALD05 suite | Not implemented; no WALD05 tests run |
| Disposable MySQL 8.4 WALD05 migrations/upgrade/races | Not run; no new schema or commit implementation to test |

The eight inherited advisories remain separate. Remediation
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged; no lockfile changed.
Final documentation path, diff, whitespace, workbook integrity and executable-tree checks are
recorded in the handoff. Existing full regression passes do not establish WALD05 readiness or
cover W5-T01; dedicated QA must not start yet.

## Files, preserved work and next step

Current task documentation: this report, `documentation/wald-divergence-register.md`,
the WALD05 work package, `DECISIONS.md`, `brief.md`, `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md`.
Local diagnostic helpers/evidence remain ignored under `storage/app/wald05-audit-20260908/`.
Unrelated `documentation/sprint-3e-date-negotiation-report.md`, the untracked original workbook
and `output/` are preserved. No application file, accepted migration or dependency was edited.

Resolve the narrowly scoped W5-T01 core compatibility correction and verify a new reader identity,
then continue the already authorised WALD05 implementation. There is no renewed W5-P01/P02 question
for this actual sample. WALD06 still requires completed and independently accepted WALD05, its own
pilot/cutover approval, production storage/worker/backup/security evidence and separately approved
deployment. G09's named unattended-disposal owner remains a disposal-only gate.

CUSTOMER-WALD05 partially complete — further implementation required

---

# Historical initial projection-contract entry review

The following is retained as historical evidence. Its synthetic examples and pause recommendation
are superseded for the audited DEC-050 sample by the current continuation report above.

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **BLOCKED at projection-contract review; runtime implementation has not started.**

## Authority and verified inputs

The latest management instruction explicitly authorises WALD05 implementation and the branch
`feature/customer-wald05-import-review-integration`. It supersedes the previous requirement for
a separate implementation instruction. I01–I10 remain approved; none is reopened by this report.

The branch was created directly from governance commit
`877bd3ff666873a0703c3b7671015ec4cfd2ee52`. Accepted WALD04
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8` is its ancestor. The intervening changes comprise
14 Markdown files only, with no application, migration, test, configuration, dependency or
workflow changes. Corrected WALD04 executable remains
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`.

Verified trees at the implementation starting point:

| Component | Git tree |
|---|---|
| Generic Wald | `30d1fc65e575242004eb335ad46a4d8ec920127a` |
| CustomerApp semantics | `9cc8df4bc50a888ec49e6a93dddaba180a2d0d01` |
| WALD04 knowledge | `43135838725e11f6af1b614115f642fb800a2953` |

Dictionary remains `customerapp.source-dictionary.v1`, fingerprint
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.

## Newly exposed projection-contract gap

I04 correctly establishes one permanent Call No. per individual visit. Both CM1 and CM2 map to
CML and may identify separate visits for the same plot. That source grain is settled. The
integration still needs the rule converting several distinct visits into one Portal service
state and one plot/product quantity.

The existing schema has one `projected_plot_services` row per `(projected_plot_id,
service_identifier)` and one `source_call_number` on that row. The same migration makes
`(projected_plot_id, product_code)` unique in `projected_plot_products`. See
`database/migrations/2026_08_20_000005_add_target_call_off_domain_foundation.php`, lines 32–61.
These existing constraints are evidence of the old projection shape, not permission to reject
legitimate distinct visits or choose one as business truth.

The approved dictionary's `CustomerAppDictionary::rollup()` explicitly processes one supplied
record. It defines which product codes contribute to Windows/Doors but supplies no cross-visit
aggregation rule. Export Date/Slot orders observations of a visit; two distinct visits in one
export have the same declared slot, so that ordering cannot choose between them.

| Required clarification | Concrete synthetic case | Why an implementation choice changes business meaning |
|---|---|---|
| W5-P01 — service completion and request association | One plot has Call A / CM1 / complete=Yes and Call B / CM2 / complete=No in the same export. | Both visits resolve to CML. The contract does not say whether the Portal's CML is complete, outstanding, or tied to a designated visit; nor which visit may close the active Portal request. This affects eligibility, completion/reversal and negotiation precedence. |
| W5-P02 — product quantity across distinct visits | One plot has two distinct Call Nos. with VS=5 and VS=2, or BF=1 and BF=0, in the same export. | The contract does not say whether quantities are per-visit contributions, repeated plot totals or values owned by a designated visit/call type. Sum, maximum, latest visit or first/last row give different customer totals and potentially different BF lead time. |

The required answer must also cover later partial exports: the approved absence rule preserves
unrepresented visits, so omitting a previously known visit cannot implicitly remove its influence
on an aggregate. No ordering may be inferred from Call No. numeric value, row order, upload time
or CM1/CM2 labels unless that rule is explicitly approved.

This corrects the earlier readiness report's implication that the entire projection contract
was complete. It does not change I03 ordering, I04 visit identity, the dictionary mappings or any
other approved I01–I10 decision. AGENTS.md requires an unresolved business rule to be recorded
rather than invented; the implementation instruction expressly permits escalation of a genuine
integration-contract contradiction. The affected schema/commit implementation is paused here.

## Existing and parallel importer disposition

Read-only inspection confirms the existing importer and the non-main line at `1e8c22b` both
reject a second Call No. targeting an already occupied plot/service. Their product pass merges
maps by plot, allowing later rows to replace earlier quantities. Those behaviours cannot resolve
W5-P01/P02 because they are specifically superseded by the approved adoption boundary.

| Material | Disposition |
|---|---|
| Existing Portal relationships, dates and immutable history | KEEP_AS_IS; evolve additively after the projection rule is confirmed. |
| Source run/issue/event concepts and completion/reversal transition intent | ADAPT behind the new authorised atomic boundary; legacy mappings are not authority. |
| SourceRecord, binding, staging, preview, ordering and commit adapter | REPLACE/REIMPLEMENT under approved contracts and the clarified projection rule. |
| Old importer execution | RETIRE_AFTER_WALD05 only through separately approved WALD06 parity/cutover; retained now. |
| Non-main upload/hash/stale/idempotency tests | REUSE invariant/test intent only. |
| Non-main profile learning, score/first selection, source-wide absence, omitted-product zeroing and per-record commit | SUPERSEDED; no code or data imported. |
| Non-main Office UI | REFERENCE_ONLY. |

## Implementation state

No binding, upload, run, observation, staging, review, receipt or audit table has been added.
No migration has run. No runtime, endpoint, UI, storage configuration, queue, dictionary or
projection code has changed. Accordingly, no I01–I10 feature is claimed implemented by this task.
No atomicity, retry, concurrency, idempotency, performance or MySQL result is claimed.

The explicit implementation approval remains valid once W5-P01/P02 are answered. Work should
resume on this branch from the verified governance baseline plus this entry record; it does not
require another blanket implementation approval.

## Checks and environment

| Check | Result |
|---|---|
| `git merge-base --is-ancestor 0e83eb2896e7c5144bc38c1be9713f3d205d93b8 877bd3ff666873a0703c3b7671015ec4cfd2ee52` | Exit 0; accepted baseline is an ancestor. |
| `git diff --stat 0e83eb2896e7c5144bc38c1be9713f3d205d93b8 877bd3ff666873a0703c3b7671015ec4cfd2ee52` | Fourteen Markdown files only. |
| Path-restricted diff of app/database/tests/config/routes/dependencies/.github over the same range | Empty. |
| `git rev-parse HEAD:app/Wald HEAD:app/SourceImport/Semantics HEAD:app/SourceImport/Knowledge` at branch creation | Matches all three accepted tree identities above. |
| `php -v`; `composer --version`; `node -v`; `npm -v`; `git --version` | Available: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.0.windows.3. |
| Current model/migration/importer/dictionary and non-main Git-object inspection | Confirms the projection-grain gap and superseded legacy handling described above. |

Application suites, disposable MySQL, Pint, build and Composer audit are not rerun for this
documentation-only entry review. Eight Composer advisories remain inherited reported evidence;
remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. No fresh clean-security
claim is made. Documentation path/diff/whitespace checks are recorded in the task handoff.

## Files changed and preserved work

Task files: this report; `DECISIONS.md`; `brief.md`; `ROADMAP.md`; `current_sprint.md`; `HANDOVER.md`;
`documentation/wald-divergence-register.md`; and
`documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md`.

The unrelated two-space change in `documentation/sprint-3e-date-negotiation-report.md`, untracked
`Copy of siteapp1.xlsx` and untracked `output/` remain preserved and excluded. Historical reports
are unchanged. No customer workbook, SiteApp filesystem or production system was accessed.

## Recommendation

Do not start dedicated WALD05 QA yet. Confirm W5-P01 service completion/request association and
W5-P02 product quantity aggregation across distinct visits, then continue the already authorised
implementation. G09 unattended disposal, WALD06 pilot/cutover, persistent production worker
operations, security reconciliation and deployment remain separate later gates.

CUSTOMER-WALD05 blocked — integration contract correction required
