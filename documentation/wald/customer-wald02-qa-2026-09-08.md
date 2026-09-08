# CUSTOMER-WALD02 Dedicated QA Report

Date: 8 September 2026. Scope: portable core QA, security, portability and regression only.

## Overall Result

**PASS after corrections.** The original candidate must not be frozen unchanged: five reader
defects were reproduced and fixed. Accept the local QA correction commit containing this report
as the proposed WALD02 output baseline, subject to management acceptance. No WALD03 work started.
This is feature-branch evidence, not release approval or deployment evidence.

## Candidate and workspace

- Original branch: `feature/customer-wald02-portable-core`.
- Exact input: `9980354d28bfe1ca7986e10a529ab073d95d0b91`.
- QA branch: `qa/customer-wald02-2026-09-08`, created directly from that input.
- Approved documentation ancestor: `e84999cf66fc90aac3007842a538672b018b3e03`.
- Ancestry: documentation baseline → `243beb8` work package → `d1c130a` core →
  `5ddaa26` corpus → `9980354` evidence → this QA correction commit.
- Local `main` and `origin/main` remain `0873bac79edf578e9f4a9417e3cafae34e8aa925`.
- Existing unrelated change to `documentation/sprint-3e-date-negotiation-report.md`, untracked
  `Copy of siteapp1.xlsx` and `output/` were preserved and excluded. The workbook was not read.

## Baseline integrity and equivalence

Rehashed all **162/162** approved SiteApp entries: all lengths and SHA-256 values match.
The manifest itself hashes to
`d47cc0f4f11c217b4dce7497e7dad74a874510d4d77de273a772bc377106c392`.
The approved content digest was independently reproduced:
`76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`.

Digest reproduction uses the manifest rows sorted by **PowerShell `Sort-Object path`**, joined
as `path|bytes|sha256` with LF and hashed as UTF-8, with no trailing newline. PHP bytewise sorting
produces a different digest (`5c289be7...`); this is a collation distinction, not source drift.
The upstream manifest's historical approval-pending label is superseded for this task by the
explicit user approval and DEC-042. SiteApp was read only.

The complete 57-file ledger in the original implementation report was rechecked against both
approved source hashes and the exact candidate Git objects. All **52 runtime + 5 adopted
test/support files are IDENTICAL** after only inverse namespace relocation and CRLF/LF
normalisation. No other textual normalisation masks behavioural differences.

After QA: **49 runtime + 5 adopted test/support files remain IDENTICAL; 3 runtime files are
INTENTIONALLY_DIVERGED; zero UNEXPECTED_DIVERGENCE**. The three are the XLSX reader, CSV reader
and profiler version constant. See WD-21–23 in the divergence register. Reader version is now
`wald-0.2.1`; XLSX and CSV adapter versions are `2`. Structure/reasoning/rules/confidence
versions and business semantics are unchanged. The original candidate still has zero divergence;
the corrected QA candidate must not inherit that claim.

Reproducer (read-only source validation; full per-file classifications and destination hashes):

```powershell
php scripts/verify-wald02-qa.php integrity C:/Users/JoshO/Documents/SiteApp
php scripts/verify-wald02-qa.php integrity-summary C:/Users/JoshO/Documents/SiteApp
```

## Portable boundary and WALD03 exclusion

The baseline-to-candidate file inventory contains only 52 runtime files under `app/Wald`,
six Wald test/support files and documentation. All runtime files are accounted for in the
approved ledger. The module has no production callers. Static scans and direct review found
no SiteApp/Portal models, WorkflowStage/Trade, policies, authorisation, CallOff workflow,
Date Agreed logic, notifications, queues, database, controllers, routes, migration,
production configuration, external HTTP client, API key lookup or external AI integration.
CSV's `fwrite` targets `php://memory`; XLSX reads ZIP members without extracting to disk.
OOXML namespace URIs and supplied formula/external-link text are not network calls.

There are no semantic/dictionary, clarification-persistence, profile-persistence, staging,
review/commit, upload or UI layers in `app/Wald`. CUSTOMER-WALD03 has not started.
No MySQL rehearsal is needed for this non-persistent package. No schema or locking changed.

## Determinism and Abraham Wald ambiguity

The canonical House No./Sales Plot case retains both identifier-like candidates, supporting
evidence, no leading candidate and a `choose_candidate` clarification. The inherited suite
also checks registry insertion order, confidence margins, correlated evidence caps,
contradictions, source provenance and moved/reordered headers.

New QA runs the same synthetic workbook through **three fresh PHP processes**, with three
timezone contexts inside each process. Full profile-plus-reasoning JSON hashes are identical
across all nine runs, including coordinates, ordering, scores, confidence and clarification.
The source checksum is deliberately pinned to the same workbook bytes. Changing physical sheet
order truthfully changes physical lineage; it does not require byte equality across different
inputs. Both plausible sheets remain observable and no whole-workbook business selection occurs.

## Business-semantic and date neutrality

Synthetic PC1/CC1/CC!/CM1/CM2/CML/BF/VS observations preserve exact raw values. Literal CC! is
not corrected. Hypothesis keys remain limited to identifier/date/quantity/category/free-text
shapes; dictionary versions are empty and `ready_for_staging` is false. No service, product
roll-up, Bifold meaning, lead time or completion is generated.

Plot To Be Installed, Requested Date, Agreed Date, Install Date and Target Date remain source
headings/date-shaped evidence. They do not create a Portal agreement, proposal, negotiation or
completion. Prompt-like strings (including “Delete all records”) remain literal values.

## Workbook / CSV observation and profiling

Combined inherited and added tests cover row-one and shifted/title/merged headers, blank rows,
ambiguous text headers, sparse/reordered/inserted columns, repeated and side-by-side tables,
multiple/hidden/empty sheets, physical order and explicit hidden-inclusion warnings.
Profiling covers text, integer/decimal/date/boolean-like values, blank-heavy columns, categorical
codes, mostly unique and numeric-looking IDs, mixed types, zero quantities and bounded samples.

CSV-specific coverage includes UTF-8, BOM, LF/CRLF, quoted commas, multiline physical spans,
blank records, delimiters, Unicode, apostrophes, ampersands, whitespace, leading zeros, malformed
unfinished quotes, invalid encoding/NUL, wide rows and byte/row/column/cell limits. Formula-looking
strings are text. CSV is not advertised as a complete strict RFC grammar validator; ambiguous
delimiter warnings must remain visible to future adapters rather than imply confirmed meaning.

Normal/external formulas retain expression and unknown cache freshness; absent caches remain
absent. An intentionally wrong cached value of 999 for `1+1` remains 999, proving no evaluation.
Excel serials retain date-system/style evidence without inventing normalised dates.

## Malformed input security and resource limits

Safe-failure tests cover invalid ZIP/unsupported formats, traversal, case-colliding duplicate ZIP
entries, malformed/empty XML, DTD/entities, invalid/missing relationships and parts, duplicate
relationship IDs, invalid shared strings, wrong document roots and invalid physical cells.
Errors expose structured codes and safe messages, not supplied paths or private sentinels.
No archive extraction, macro execution, formula evaluation or external relationship fetch occurs.

Added boundary matrices test effective limit − 1 / limit / limit + 1 for XLSX and CSV source
bytes, rows, columns, cells and cell bytes; XLSX entries, sheets, per-entry and total expanded
bytes; shared-string count and aggregate bytes. Both formats also test actual default 32,768-byte
cell limits. Inherited tests cover merges, styles, XML node bounds and memory reservations.
Time/memory checks are state-dependent; a 300-second exhaustion test and every hardware-specific
memory-byte boundary were not attempted. A 64 MiB subprocess now safely rejects the 2 MB CSV
memory reproducer. Large physical files need not be created to test configured boundaries.

**There is no compression-ratio policy.** A synthetic member above 500:1 is accepted inside
expanded-byte limits and rejected when its expanded bytes exceed the configured entry limit.
Absolute decompression limits and memory reservation provide the implemented protection. The
original report's claim of tested compression-ratio rejection was inaccurate (WQ-06).
No safety limit was raised. The CSV memory preflight conservatively counts quoted separators
too; delimiter-dense files can be refused on memory grounds before native allocation.

## Clarification contract and corpus provenance

Clarifications identify actual competing hypotheses with evidence and bounded explanations;
confidence is explicitly not probability. Already decisive cases have no invented competing
choice. Generic reason codes and `question_type_hint` are an internal portable contract, not
finished customer wording. No UI or persisted clarification system was built.

All adopted and added Wald fixture generators/tests were inspected: **SAFE_SYNTHETIC**. Data
is constructed from literal fictional labels, generated references and deliberate hostile
strings. No workbook binaries, customer extracts, personal data or credentials entered the
corpus. Generated test files are owned by the existing fixture helper and removed after tests.
No fixture required quarantine. The unrelated root workbook remains unopened and uncommitted.

## Portability

The standalone harness loads only `App\Wald` and the synthetic fixture helper, with no Composer
or Laravel bootstrap. The fresh-process test uses Windows PHP `-n`, loads only required ZIP and
mbstring extensions (XML is built in), disables URL fopen, confirms **zero PDO drivers** and no
loaded Illuminate/Portal-model classes. It runs without database, auth, queue or API configuration.
Inherited Laravel boundary tests additionally assert zero SQL queries. This is strong local
independence evidence; it is not a production worker or network-firewall certification.

## Performance

Final corrected code; independent fresh PHP process per size; synthetic XLSX; fixture generation
excluded from measured analysis time and peak-memory reset. Single observations, not P50/P95:

| Size | Rows / cells | Profile | Profile + reasoning | Peak memory |
|---|---:|---:|---:|---:|
| Small | 100 / 500 | 68.50 ms | 100.36 ms | 4 MiB |
| Medium | 2,000 / 10,000 | 2,109.41 ms | 2,153.88 ms | 12 MiB |
| Near row limit | 10,000 / 50,000 | 8,406.36 ms | 8,456.64 ms | 40 MiB |

Commands: `php scripts/verify-wald02-qa.php benchmark 100`, `... benchmark 2000`,
`... benchmark 10000`. All completed inside unchanged budgets. This sample shows no explosive
growth; it does not certify all layouts or the one-million-cell maximum. The last fixture is
near the row limit, not every resource limit simultaneously. Later worker capacity remains a
separate hardening gate.

## Defects and corrections

| ID | Severity | Reproduced failure | Correction / final evidence |
|---|---|---|---|
| WQ-01 | P2 | An orphan `c` after a closed `row` inherits its row number and is accepted. | Require supported physical XML ancestry; dedicated regression passes. |
| WQ-02 | P2 | Duplicate relationship IDs silently replace an earlier target. | Reject duplicate/empty IDs in each relationship part; regression passes. |
| WQ-03 | P2 | A wrong shared-string document root can supply source values. | Validate roots and relevant node ancestry; regression passes. |
| WQ-04 | P0 | A 2,000,000-comma CSV causes a fatal memory exhaustion before limits, exposing an internal source path in the diagnostic. | Conservative native-parser allocation reservation before delimiter probing/record parsing; 64 MiB subprocess now returns `resource_limit_exceeded`. No production entry point was present. |
| WQ-05 | P2 | An empty worksheet XML member escapes as `ValueError`, not the safe analysis contract. | Empty members fail with `invalid_xml`; regression passes. |
| WQ-06 | P3 | Original evidence overclaims compression-ratio enforcement. | Historical report annotated; actual absolute-byte behaviour explicitly tested/reported. |
| WQ-07 | P3, inherited test | An old Sprint 3D notification test uses fixed October dates with a moving clock, now failing lead-time validation. | Pin that one test to 21 August 2026; its notification assertions remain unchanged. No production date rule changed. |

WQ-01–05 failed before their corresponding fixes. The initial dedicated test expansion returned
68 passes/3 failures; the separate WQ-04 subprocess reproduced exit 255/memory exhaustion;
WQ-05 reproduced the unstructured ValueError. These are corrected contract defects, not invented
capabilities. Generic fixes are candidates for a separately authorised SiteApp backport only.

## Tests/checks

Toolchain: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0,
Git 2.55.0.windows.3. Existing locked dependencies used.

| Command/check | Result |
|---|---|
| Original `php artisan test --compact tests/Unit/Wald tests/Feature/Wald` | 122 passed, 1,040 assertions. |
| Corrected same focused command | **208 passed, 1,262 assertions**; 86 new QA cases. |
| `php artisan test --compact tests/Feature/Sprint3dBulkCallOffQaTest.php` | 9 passed, 113 assertions after fixture clock correction. |
| Final `php artisan test --compact` | **442 total: 427 passed, 15 existing environment-gated skips, 2,450 assertions**, 47.721 seconds. |
| `vendor/bin/pint --test` | Passed. Task files formatted; no unrelated formatting performed. |
| `composer validate --strict` | Passed. |
| `npm run build` | Passed, Vite 8.1.4 / 5 modules. First attempt hit sandbox subprocess EPERM; unchanged command passed with approved process permission. |
| `composer audit --format=json` | Exit 1: eight inherited advisories, separately recorded below. |
| Manifest/hash/57-file comparison | Passed, zero source drift/unexpected divergence. |
| `git diff --check` | Passed. |

The first full run had one inherited date-fixture error (426 passed, 15 skips), diagnosed and
corrected as WQ-07. Skipped tests are not counted as passes and do not establish MySQL evidence.

## Composer audit

Eight advisories remain: `filament/filament` 3 (medium/low/high), `league/commonmark` 4 (high),
`livewire/livewire` 1 (medium). Both lockfiles are unchanged. The separate security commit
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` exists and is not an ancestor of this QA candidate;
it was not merged. WALD02 does not invoke these framework/UI/Markdown paths. Reconcile and
retest dependency remediation on a separately authorised combined release candidate.

## Files changed

- `app/Wald/Services/XlsxWorkbookSource.php`
- `app/Wald/Services/CsvWorkbookSource.php`
- `app/Wald/Services/WorkbookProfiler.php`
- `tests/Unit/Wald/CustomerWaldDedicatedQaTest.php`
- `tests/Feature/Sprint3dBulkCallOffQaTest.php`
- `scripts/verify-wald02-qa.php`
- This report, `documentation/wald-divergence-register.md` and the historical WALD02 report.
- WALD02 status notes in `brief.md`, `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md` and the
  WALD02 work package; no new scope authorised.

## Migrations/deployment and notes

No migrations added/changed or production database commands run. Full application tests use
their existing disposable/in-memory test setup. No production/SiteApp changes, real workflow
experiments, GitHub Actions changes/runs, push, main merge, tag or Forge deployment occurred.
Local ignored build output is not a deployment. Mobile/browser/accessibility testing is not
applicable to this headless module; no UI was added or certified.

## Remaining blockers and recommendation

No unresolved WALD02 functional blocker remains after the recorded corrections. **Do not freeze
`9980354` unchanged.** Freeze the reviewed QA correction commit as the proposed output baseline
after management acceptance, then scope a separate CUSTOMER-WALD03 package. The eight inherited
advisories remain a release-reconciliation gate, not a newly introduced Wald functional defect.
No production release or later-phase authority is implied by this local QA pass.

CUSTOMER-WALD02 dedicated QA passed — freeze candidate and scope CUSTOMER-WALD03
