# CustomerApp Wald Prerequisite + Plot Register Integration Report

Date: 7 October 2026.

Status: **BLOCKED** by the new independent-inventory/repeated-Call acceptance gate.
The approved prerequisite chain is integrated locally. No push, deployment,
production connection or genuine workbook Apply occurred.

## Baseline and integration

Fetched `main` and `origin/main` both resolved to
`04e48b55c6178f4e2352cd06e7cd657d0b1f081b` before integration.
The original working checkout remains on its user's robustness branch; its
untracked `.codex-build/` contents and other worktrees are preserved.

Clean isolated branch: `codex/wald-plot-register-integration-2026-10-07`.
Three reviewed fast-forwards preserve all accepted history:

1. Accepted audit/dry-run checkpoint `67dcc4c6b85cee77e20cefd14575d83905c32abf`.
2. Safe profile checkpoint `50be1f3d768fd006d2837e851ff682ab4893c7bd`, including
   the required dry-run error-rendering correction `9509faef1b5962b92548c7ad253d2869b6606c67`.
3. SOURCECONTRACT03 `56c6a9860a3ace52e851825d0ba8c64c40e83246`.

No conflict occurred. The application tree is the exact accepted SOURCECONTRACT03
tree; the integration adds only a regression reproducer and this evidence report.
Main has not been moved. The final evidence commit is the branch tip containing
this report; `git rev-parse codex/wald-plot-register-integration-2026-10-07`
provides its complete SHA without a self-referential hash in this file.

## Complete ancestry review

Review completed before any merge. All paths below are repository-relative.
No commit implements unresolved repeated-Call semantics, Import Reversal,
dependency/security upgrades or excluded Wald06 experiments. There were no
UNRELATED, SUPERSEDED or executable UNRESOLVED_REPEATED_CALL_WORK commits in
this twelve-commit range. Historical repeated-Call investigation is retained
as evidence only in the controlled-profile commit.

### 660fb16734acc241e99c7ab8a7f1eb0da8a74d0c

Subject: feat: add read-only Wald binding integrity audit.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: Office/CLI factual binding inspection, with no repair writes.
Files: `app/Console/Commands/WaldIntegrityCheck.php`,
`app/Http/Controllers/OfficeWaldIntegrityController.php`,
`app/SourceImport/Integration/BindingIntegrityAudit.php`,
`documentation/work-packages/WP-CUSTOMER-WALD-ROBUSTNESS01.md`,
`resources/views/office/settings/wald-integrity.blade.php`,
`resources/views/office/settings/wald.blade.php`, `routes/office-workspace.php`,
`tests/Feature/WaldRobustness/BindingIntegrityTest.php`.

### e999098873cd6b6dfba6aa2c67f63d0a94e0ff64

Subject: test: qualify corrupt and cleared Wald bindings.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: audit correctness for corrupt targets and cleared binding roots.
Files: `app/SourceImport/Integration/BindingIntegrityAudit.php`,
`tests/Feature/WaldRobustness/CorruptBindingAuditTest.php`.

### 6d4972f7e730e392b82e892005cb3909f8a93c95

Subject: fix: audit Wald binding history heads in bounded reads.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: qualify history-head comparisons and bound audit reads.
Files: `app/SourceImport/Integration/BindingIntegrityAudit.php`,
`tests/Feature/WaldRobustness/BindingIntegrityTest.php`.

### b017f0f3fc188401c7c75c6f74a58609b3defcd5

Subject: feat: add first-class non-mutating Wald dry run.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: transient analysis, exact resolver and deterministic report without persistence.
Files: `app/Http/Controllers/OfficeWaldDryRunController.php`,
`app/SourceImport/Integration/PilotWorkbookDiscovery.php`,
`app/SourceImport/Integration/WaldDryRun.php`,
`resources/views/office/pilot-import/dry-run.blade.php`,
`resources/views/office/pilot-import/index.blade.php`, `routes/office-workspace.php`,
`tests/Feature/WaldRobustness/DryRunTest.php`.

### c5eb608d0f97bebad852fd929cdd566acc2984f2

Subject: docs: block Wald reversal pending durable ownership evidence.
Classification: **DOCUMENTATION_ONLY**.
Purpose: retain the accepted no-reversal/evidence gate; no reversal runtime.
Files: `DECISIONS.md`, `HANDOVER.md`, `ROADMAP.md`, `brief.md`,
`current_sprint.md`, `documentation/source-integration-contract.md`,
`documentation/wald-divergence-register.md`,
`documentation/wald-robustness01-reversal-evidence-contract.md`.

### 56c6877ed3993a6743d637b737b11956a83a7d67

Subject: fix: detect orphaned Wald binding history targets.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: fail factual integrity inspection on orphaned history targets.
Files: `app/SourceImport/Integration/BindingIntegrityAudit.php`,
`tests/Feature/WaldRobustness/CorruptBindingAuditTest.php`.

### b8e1403d99612020b0cdf758efaa1614469c6aee

Subject: fix: retain ignored-column semantics in Wald dry runs.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: prevent ignored evidence becoming inferred source meaning.
Files: `app/SourceImport/Integration/WaldDryRun.php`, `routes/office-workspace.php`,
`tests/Feature/WaldRobustness/DryRunTest.php`.

### f19df5a4d48e96b9a2e776e1cfd038353157e253

Subject: perf: bound Wald resolver history and dry-run fact reads.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: bounded, batched source resolution and fact queries.
Files: `app/SourceImport/Integration/MasterSourceResolver.php`,
`app/SourceImport/Integration/WaldDryRun.php`.

### 67dcc4c6b85cee77e20cefd14575d83905c32abf

Subject: docs: record qualified Wald robustness phase one.
Classification: **DOCUMENTATION_ONLY**.
Purpose: retain accepted A/B qualification and blocked Task C evidence.
Files: `HANDOVER.md`, `ROADMAP.md`, `current_sprint.md`,
`documentation/wald-robustness01-report-2026-10-06.md`,
`documentation/work-packages/WP-CUSTOMER-WALD-ROBUSTNESS01.md`.

### 9509faef1b5962b92548c7ad253d2869b6606c67

Subject: fix(wald): render dry-run blockers without unsupported string method.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: safe error feedback for blocked dry runs, rather than HTTP 500.
Files: `app/Http/Controllers/OfficeWaldDryRunController.php`,
`resources/views/office/pilot-import/dry-run.blade.php`,
`tests/Feature/WaldRobustness/DryRunTest.php`.

### 50be1f3d768fd006d2837e851ff682ab4893c7bd

Subject: feat(wald): qualify alternate RedZebra export profile.
Classification: **REQUIRED_PREREQUISITE**.
Purpose: exact ordered alternate XLSX profile, controlled aliases, private
date/name evidence and clear missing-header guidance. No repeated-Call resolution.
Files: `DECISIONS.md`, `app/Http/Controllers/OfficeWaldDryRunController.php`,
`app/SourceImport/Integration/DryRunHeaderConflict.php`,
`app/SourceImport/Integration/PilotWorkbookDiscovery.php`,
`app/SourceImport/Integration/RedZebraExportProfile.php`,
`app/SourceImport/Integration/WaldDryRun.php`,
`app/SourceImport/Knowledge/AnalysisSnapshot.php`,
`app/SourceImport/Knowledge/KnowledgeIdentity.php`,
`documentation/siteapp-import-data-dictionary.md`,
`documentation/source-integration-contract.md`,
`documentation/wald-divergence-register.md`,
`documentation/wald-sourcecontract02-qualification-2026-10-06.md`,
`tests/Feature/WaldRobustness/DryRunTest.php`,
`tests/Feature/WaldSourceContract02/ExportProfileTest.php`.

### 56c6a9860a3ace52e851825d0ba8c64c40e83246

Subject: feat(wald): support plot registers with optional call-off data.
Classification: **SOURCECONTRACT03**.
Purpose: independent plot inventory capability and optional call evidence,
additive inventory timestamp and first Portal call-off eligibility.
Files: `DECISIONS.md`, `HANDOVER.md`, `brief.md`, `current_sprint.md`,
`app/Actions/CallOff/BuildCallOffMatrixAction.php`,
`app/Actions/CallOff/DetermineCallOffEligibilityAction.php`,
`app/Actions/CallOff/SubmitMultiCallOffBatchAction.php`,
`app/Http/Controllers/CallOffRequestDetailsController.php`,
`app/Http/Controllers/PlotDetailsController.php`,
`app/Http/Controllers/ReviewRequestsController.php`,
`app/Http/Controllers/SiteDashboardController.php`, `app/Models/ProjectedPlot.php`,
`app/Services/CallOffDateViewService.php`, `app/Services/CallOffLeadTimeService.php`,
`app/SourceImport/Integration/AutoResolveClarifications.php`,
`app/SourceImport/Integration/BackendStore.php`,
`app/SourceImport/Integration/CustomerCodeReviewWorkflow.php`,
`app/SourceImport/Integration/PilotImportWorkflow.php`,
`app/SourceImport/Integration/PilotWorkbookDiscovery.php`,
`app/SourceImport/Integration/PlotRegisterCapabilities.php`,
`app/SourceImport/Integration/ProjectionAdapter.php`,
`app/SourceImport/Integration/ProjectionSnapshot.php`,
`app/SourceImport/Integration/RedZebraExportProfile.php`,
`app/SourceImport/Integration/UnknownRowsWorkflow.php`,
`app/SourceImport/Integration/WaldDryRun.php`,
`app/SourceImport/Integration/WorkbookStager.php`,
`app/SourceImport/Knowledge/AnalysisSnapshot.php`,
`app/SourceImport/Knowledge/KnowledgeIdentity.php`,
`app/View/Presenters/ImportReviewPresentation.php`,
`database/migrations/2026_10_06_000026_add_plot_register_evidence.php`,
`documentation/siteapp-import-data-dictionary.md`,
`documentation/source-integration-contract.md`,
`documentation/wald-divergence-register.md`,
`documentation/wald-full-plot-register-2026-10-06.md`,
`resources/views/office/pilot-import/dry-run.blade.php`,
`resources/views/office/pilot-import/site-review.blade.php`,
`resources/views/office/pilot-import/source-sites.blade.php`,
`tests/Feature/WaldSourceContract03/PlotRegisterTest.php`.

## Essential repeated-Call isolation blocker

PREREQ01 explicitly adds a stronger acceptance criterion than the original
SOURCECONTRACT03 qualification: valid base Plot inventory must remain discoverable
when other rows have ambiguous repeated Call evidence. The historical report
instead describes every repeated nonblank Call as a workbook-wide hard blocker.
This newer instruction takes precedence for integration readiness.

The added regression supplies three fictional rows under one valid CustomerCode:

1. Plot 1, blank Call Number and Type (valid base evidence).
2. Plot 2, Call 3991, PC1, complete=No.
3. Plot 2, Call 3991, PC1, complete=Yes.

Expected: two plot candidates and one blocking source unit, no Apply approval,
no persistence, no guessed repeated-call outcome.
Actual: `PilotWorkbookDiscovery::inspect` throws `duplicate_call_number`
before returning any inventory/resolution report. `WaldDryRun::analyse` therefore
cannot expose even the independent valid base row. The retained existing
SOURCECONTRACT02 duplicate tests pass, demonstrating fail-closed behaviour,
but that global refusal does not meet PREREQ01's isolation requirement.

The runtime is unchanged; no deduplication or weakened assertion was introduced.
Keep the red acceptance reproducer. A bounded follow-up must separate inventory
discovery from ambiguous call eligibility while preserving full selected-site
atomicity, exact binding and the call/visit projection blocker. Do not infer
first/last/date precedence or rewrite Source Row/Visit identity.

## Migration and isolation

Only new migration:
`2026_10_06_000026_add_plot_register_evidence.php`.
It adds nullable `projected_plots.plot_register_established_at`, has no backfill,
and its rollback drops only the new column. Existing migrations are unchanged.

Disposable MySQL 8.4.11 runs at loopback `127.0.0.1:33707` with a new task-only
data directory and database `customerapp_prereq_test`. Restricted `prereq_test`
has only global USAGE plus privileges on that database. The isolated worktree
contains no `.env`; the process explicitly sets APP_ENV=testing, MySQL connection,
array mail, sync queue, array cache/session and test key. No production credentials
or endpoint are loaded.

Clean migration: 26 applied, none pending. Rollback/reapply of 000026 passes;
a pre-existing fictional plot keeps its ID/UUID. The nullable field persists a
timestamp when set, and reapply leaves existing plots null without backfill.
Read-only `php artisan wald:integrity-check` reports zero warnings and zero
blocking inconsistencies in the disposable fixture.

## Verification

After dependency installation and asset build, focused SQLite qualification:
`php artisan test tests/Feature/WaldSourceContract03 tests/Feature/WaldSourceContract02 tests/Feature/WaldRobustness --compact`
completed 50 cases: **49 passed, one error, 494 assertions**, 45,580 ms.
Only the added independent-inventory/repeated-Call acceptance errors.
The original accepted tests, including the duplicate-call fail-closed tests,
all pass. The first pre-build attempt had missing Vite manifest failures;
those disappeared after the normal build and were environment setup findings.

`php artisan test --compact` completed **2,108 cases: 2,015 passed, 92 skipped,
one error, 11,405 assertions**, 507,080 ms. The only error is the same new
acceptance at `PilotWorkbookDiscovery.php:83`, `duplicate_call_number`.
This is an explicit blocked qualification, not a full-suite pass.

The passing acceptance covers blank/absent Call Number and Type, the minimal
two-field register, all four alternate call-column combinations, named/zero-padded
plot references, 20 base rows plus PC1/CC1/CM1 (21 plots, three visits), one plot
with base/PC1/CC1 evidence, unknown types, malformed hierarchy, existing excluded
codes, product absence/zero/conflict, stale Apply, no base completion/date meaning,
CustomerCode/Unknown review and a Site Manager's first request/Office agreement.
Later source PC1 reuses the plot/service and preserves requested/agreed dates and
history; active-request uniqueness and cross-customer denial remain covered.
Office dry-run routes, external denials, deterministic non-mutation and temporary
file cleanup pass. Large dry run: 1,825 rows, 23 queries, 1,204.5 ms in the full run.

Versions: policy v5, capabilities v1, source-profile registry v2, discovery v9,
backend application pilot-v5/projection v5. Older pins remain stale. Business
dictionary/frozen generic core are unchanged.

Other gates:

- `vendor/bin/pint --test`: PASS.
- `composer validate --strict`: PASS.
- `npm run build`: PASS after the sandbox initially blocked required subprocesses;
  the permitted retry completed normally. No build/config code change.
- `npm audit --omit=dev`: PASS, zero vulnerabilities.
- `git diff --check`: PASS.
- `composer audit`: five known advisories affecting four packages; not suppressed
  or remediated in this integration. No lockfile changes.

| Package | Severity | Advisory |
|---|---|---|
| filament/filament | Medium | PKSA-cnr1-6g7k-bp3d |
| laravel/framework | Low | PKSA-d5tc-s1qs-h781 |
| league/commonmark | Medium | PKSA-m2dq-1fhr-29b1 |
| league/commonmark | High | PKSA-m4t9-vsgq-8khn |
| league/flysystem | Low | PKSA-w9tt-7782-78jx |

The full development npm dependency tree reports 22 advisories (nine moderate,
eleven high, two critical) during locked `npm ci`; production-only audit is clean.
Dependency remediation remains separate work.

Disposable MySQL 8.4.11 focused command (explicit testing environment and
`phpunit.mysql.xml`):
`php -d memory_limit=512M vendor/pestphp/pest/bin/pest --configuration phpunit.mysql.xml tests/Feature/WaldSourceContract03 tests/Feature/WaldSourceContract02 tests/Feature/WaldRobustness --compact`.
Result: **50 cases: 43 passed, six expected SQLite-only corruption-fixture skips,
one error, 479 assertions**, 65,236 ms. The only error is the identical new
repeated-Call inventory acceptance, not a MySQL migration/transaction defect.
The six skipped cases explicitly use SQLite trigger replacement to construct
corrupt audit fixtures; their full SQLite coverage passed.

Final `migrate:status` confirms 26 applied with none pending; final read-only
integrity check reports zero warnings/blockers. The temporary database and
restricted user were dropped and independently counted as zero/zero. The
loopback server shut down via mysqladmin; the task-only data directory was
removed after checking its absolute target. Test output remains local and
ignored. Production was not contacted or changed.

Integration evidence files changed beyond accepted `56c6a986`:

- `tests/Feature/WaldSourceContract03/PlotRegisterTest.php`: one retained red
  acceptance proving the newly required independent inventory behaviour.
- `documentation/wald-prerequisite-plot-register-integration-2026-10-07.md`:
  complete ancestry map, source boundary review and fresh gate evidence.
- `current_sprint.md`, `HANDOVER.md`, `ROADMAP.md`: current local integration
  blocker at the top, preserving historical qualification reports unchanged.

## Scope and recommendation

No dependency or lockfile changes, frozen generic Wald or business dictionary
changes, new fuzzy matching, guessed identity or Import Reversal runtime entered.
Separate Safe Cascade Purge/security/Wald06 branches were not merged. Existing
main-approved demo purge behaviour remains baseline functionality, not reversal.

The accepted chain is available for local correction and review. It is not
ready for a genuine full-register dry run until the essential repeated-Call
inventory isolation regression passes. No push, deployment or live Apply.
