# CustomerApp Wald Robustness Phase 1 Report

Date: 6 October 2026.

## Overall Result

PARTIAL. TASK A READY. TASK B READY. TASK C BLOCKED BY EVIDENCE CONTRACT.
Final fixed-checkpoint qualification passed; no push or deployment. Composer's
five existing advisories remain a separate release-review item.

Verified runtime checkpoint: `f19df5a4d48e96b9a2e776e1cfd038353157e253`.

## Base SHA

`04e48b55c6178f4e2352cd06e7cd657d0b1f081b`, verified against fetched
`origin/main`. Branch: `codex/wald-robustness01-2026-10-06`.

## TASK A — Binding Consistency Audit

### Integrity Categories

`HEALTHY`, `STALE_SOURCE_EVIDENCE`, `CUSTOMER_MISMATCH`, `SITE_MISMATCH`,
`DUPLICATE_ACTIVE_BINDING`, `MISSING_TARGET`, `ORPHAN_BINDING_VERSION`,
`SOURCE_HIERARCHY_CONFLICT`, `UNVERIFIABLE`.

Missing/inactive targets, incorrect customer ownership, invalid active/revoked/latest
version pointers, identity/definition fingerprints, duplicate exact active identities
and orphan retained history are checked. Current parsed hierarchy is compared using
the existing controlled case/whitespace normalization. The same Site Name under
another customer never supplies a match. Missing source evidence remains a warning;
partial-export absence cannot delete or invalidate a binding.

### Office Integrity Page

Settings → Wald → Integrity check. Office-only, read-only, paginated cards show
healthy/warning/blocking totals, CustomerCode, target, source hierarchy, reason and
recommended review. Existing stored-import review is linked when evidence exists.
It remains inspectable while imports are disabled. No repair/rebind action is added.

### CLI/Service

`BindingIntegrityAudit::run()` and `php artisan wald:integrity-check [--json]`.
Exit 1 only for blocking findings; warnings alone exit 0. Reads run in one database
snapshot. Active/latest versions are batched, history heads are aggregated, and only
orphan history is streamed. There is no query per workbook row or unrestricted
history materialization.

### Findings

Synthetic checks prove healthy exact binding, mismatch under identically named
sites, stale evidence, inactive target, missing site, wrong owner, missing active
version, duplicate roots and orphan older history behind a valid active version.
A simulated correctly cleared post-purge root is acceptable and cannot resolve a
deleted target. The separate purge engine itself is not integrated here. No
production binding inventory was queried or changed.

### Tests

Focused audit and isolated corruption-fixture tests passed. Combined robustness
coverage: 18 tests / 164 assertions. The broader import/reconciliation checkpoint
passed 85 tests / 706 assertions. Final MySQL and full totals are below.

### Commit / SHA

Task A: `660fb16` initial service/UI/CLI; `e999098` corrupt/cleared root coverage;
`6d4972f` history-head checks; `56c6877` historical target checks.

## TASK B — First-Class Dry Run

### Mutation Guarantee

`WaldDryRun::analyse()` uses a transient generated private workbook copy and read-only
current-state queries. It creates no customers, sites, plots, products, services,
requests, amendments, assignments, bindings, uploads, import commands, selections,
knowledge contexts/profiles, approvals or receipts. The temporary copy is discarded
in `finally`. Normal authenticated session/HTTP handling remains framework-owned.
There is no persisted dry-run approval and no path from this report into Apply.

### UI

Imports → Analyse only — no changes applied. A dedicated file form explicitly
distinguishes analysis-only reporting from normal import review and Apply. It keeps
the existing Office and effective Wald access gates. File limits/readers stay bounded.

### Summary

Report includes total/included/excluded rows, source units, existing exact bindings,
exact matches needing bindings, new-site/customer proposals, hierarchy/malformed
groups, unknown rows, site-scoped plot create/reuse candidates, product/service
conflicts, blocking units and binding integrity issues. Resolution ratios are factual
counts, with no invented AI confidence score. Unknown/ignored rows stay outside
projection. Multiple dictionary-ignored columns do not become ambiguity blockers.

### Export/Report

Print-friendly HTML with Print or save report as PDF, using the browser's existing
print facilities. No reporting package, public workbook, persisted report or export
dependency is added.

### Determinism

Current workbook, dictionary/component pins, exact bindings and relevant current
facts produce identical canonical content/fingerprint. Timing and peak process
memory are separate diagnostics. Analysis uses fresh evidence rather than activating
saved knowledge. A current-fact change alters the state fingerprint. Normal
clarification, ordering, preview, approval and stale-state checks still run in Apply.

### Performance

Synthetic master: 1,825 rows / 56 units; 23 queries, approximately 1.0–3.3 seconds
and 78–80 MiB standalone peak memory across qualification runs. Peak memory in a
full-suite process is higher because it includes earlier tests; these are local
diagnostics, not production SLAs.

The existing exact resolver now loads active/latest binding versions only. Dry-run
current-fact reads are constrained to the exact candidate site/plot pairs and
batched; unrelated plots and full version history are not materialized.

The historic 4,358-row XLS was not found in the known local source locations.
The approved private `CUSTAPP2 (2).xlsx` matched retained SHA-256
`c0a0ea2e21954099e6c15e063ca35a17d0992ceda9aaaf73f4e2a22473403f2e`.
Disposable analysis found 33 included rows / nine units, eight new-customer/site
proposals, one malformed group and three units requiring review. It used 19 queries,
1,212.5 ms and 54 MiB peak memory. Two runs matched, SQL listeners saw no writes,
and the original workbook checksum was unchanged. No real-source data was applied.

### Tests

No-write SQL checks, repeatability, summary counts, exclusions, unknown types,
malformed hierarchy, product/service conflicts, current binding mismatch, same
Plot Ref across sites, current-state fingerprints, ignored columns and denied
roles/gates are covered. Standalone dry-run coverage passed 7 tests / 118 assertions;
the final MySQL audit/dry-run group passed 12 tests / 149 assertions.

### Commit / SHA

Task B: `b017f0f` initial dry run; `b8e1403` ignored-column regression and final
route formatting; `f19df5a` bounded history and candidate-fact reads.

## TASK C — Import Reversal

### Provenance / Ownership Contract

BLOCKED. Original receipts retain row/visit effects, service epochs, binding pins,
actors and aggregate created/reused plot counts. Pinned previews contain useful
create/reuse candidates and quantity changes, but there is no complete durable
per-entity created UUID/ownership ledger or recoverable full prior product/service
state and prior owner. Digests cannot reconstruct before-images. Source observation
history does not prove ownership of all projection/admin/Portal fields.

See `wald-robustness01-reversal-evidence-contract.md` for exact code evidence and
the required forward contract. Historical ownership is not fabricated.

### Reversible Facts

None are advertised as automatically reversible under the current incomplete contract.

### Non-Reversible Facts

Portal-owned requests, dates, amendments, history and assignments; independently
approved customer/site/binding changes; later/shared import facts; source-completion
effects on Portal workflow; and ambiguous ownership remain protected.

### Reversal Preview

Not implemented under C1's stop condition. No destructive route/control or partial
reversal is added.

### Transaction

Future full-receipt reversal needs durable before/after ownership, locked impact
recalculation, atomic rollback and a separate immutable reversal receipt while
retaining the original receipt. No reversal transaction is implemented here.

### Stale Protection

The future reversal contract must pin the complete impact and compare it again
under final locks. This is documented design, not an implemented reversal preview.

### Safe Cascade Purge Integration

`SafeCascadePurgeAction` is not on the approved baseline. Inspected dependency:
`1ead0fd3b9c4841d6ece824f837ede9254c06ac8` on
`codex/customer-data-safedelete01-2026-10-06`. Not merged or copied. Any future
hierarchy reversal must deliberately integrate the accepted engine and preserve
original/reversal receipts; no duplicate deletion engine is allowed.

### Reapply Behaviour

Current import replacement/idempotency rules are unchanged. No reversed receipt or
implicit reapply state is created. A future reapply contract must be explicitly approved.

### Tests / Commit

No reversal, reversal-race or purge-engine integration test is claimed. The evidence
gate is documented separately in `c5eb608`.

## MySQL 8.4

Final disposable MySQL 8.4.11 gate on `f19df5a`: 12 passes / 149 assertions, no skips.
Database `customerapp_wald_robustness01`, loopback port 33492,
MySQL X disabled; no normal local or production database is used. Corruption fixtures
intentionally disable constraints only in an isolated in-memory SQLite database.
Reversal locks/races/purge integration are not run because Task C stopped at C1.
The final instance applied the existing 25 migrations through the disposable
fixture setup, shut down normally, and its task-only data directory was removed
after waiting for the exact server process to exit.

## Responsive / Accessibility

Both synthetic rendered Office pages checked in headless Edge at 1366×768,
768×1024, 390×844 and 320×740. Eight checks passed with no horizontal document/control
overflow and visible keyboard focus, including the new file, analysis, navigation
and print controls. Labels and result text remain readable and
statuses use text as well as colour. Print rendering hides navigation and upload
controls. Generated synthetic screenshots/PDFs remain ignored local QA output.
No production browser workflow was used.

## Migrations

NONE. No migration edited or added. Disposable schemas applied existing migrations;
no new persistence apply/rollback/reapply delta exists. No lockfile/package change.

## Composer Advisories

`composer audit --format=json` exits 1 for five existing advisories:

| Package | Severity | Advisory |
| --- | --- | --- |
| filament/filament | Medium | GHSA-7m6h-rg42-m449 / CVE-2026-104181 — MFA management password reauthentication |
| laravel/framework | Low | GHSA-jh5r-qr3c-85q8 / CVE-2026-102279 — debug page XSS |
| league/commonmark | Medium | GHSA-97jj-33gv-5xf9 — disallowed raw HTML bypass |
| league/commonmark | High | GHSA-3q6v-r5mr-hxv8 — GFM table block scan denial of service |
| league/flysystem | Low | GHSA-cxf4-7mrp-vvpr / CVE-2026-102601 — malformed UTF-8 path-normalizer bypass |

Dependencies are not upgraded or advisories suppressed. Separate release review is required.

## Full Regression

Final full regression on runtime checkpoint `f19df5a`: 2,076 tests, 1,984 passed,
92 intentionally environment-gated skips, 11,075 assertions, 675.707 seconds.
Pint, strict Composer validation, Vite build,
production npm audit (zero vulnerabilities), route discovery and whitespace checks
pass. Vite required the normal Windows sandbox process-spawn retry; Composer cache
write warnings did not prevent validation/audit.

## Files Changed

- `app/Console/Commands/WaldIntegrityCheck.php`
- `app/Http/Controllers/OfficeWaldIntegrityController.php`
- `app/Http/Controllers/OfficeWaldDryRunController.php`
- `app/SourceImport/Integration/BindingIntegrityAudit.php`
- `app/SourceImport/Integration/MasterSourceResolver.php`
- `app/SourceImport/Integration/WaldDryRun.php`
- `app/SourceImport/Integration/PilotWorkbookDiscovery.php`
- `routes/office-workspace.php`
- `resources/views/office/settings/wald.blade.php`
- `resources/views/office/settings/wald-integrity.blade.php`
- `resources/views/office/pilot-import/index.blade.php`
- `resources/views/office/pilot-import/dry-run.blade.php`
- `tests/Feature/WaldRobustness/BindingIntegrityTest.php`
- `tests/Feature/WaldRobustness/CorruptBindingAuditTest.php`
- `tests/Feature/WaldRobustness/DryRunTest.php`
- `documentation/work-packages/WP-CUSTOMER-WALD-ROBUSTNESS01.md`
- `documentation/wald-robustness01-reversal-evidence-contract.md`
- `documentation/wald-robustness01-report-2026-10-06.md`
- `documentation/source-integration-contract.md`
- `documentation/wald-divergence-register.md`
- `brief.md`, `DECISIONS.md`, `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md`

`AGENTS.md`, the controlled dictionary, generic Wald engine, package locks and
private source workbooks are unchanged. Generated QA files are ignored and excluded.

## Push

NO.

## Deployment

NO. Feature branch only; no production access, enablement or business mutation.

## Recommendation

Review Tasks A/B for controlled integration with a separate dependency-advisory
decision. Approve and implement a durable forward ownership receipt before Task C,
then deliberately integrate the accepted Safe Cascade Purge engine and qualify
full reversal, stale state, races and rollback. Do not automate historical reversal.

CustomerApp Wald robustness phase 1 partially complete — reversal contract requires further design
