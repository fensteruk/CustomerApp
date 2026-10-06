# CustomerApp Full Plot Register and Optional Call Off Report

Date: 6 October 2026

Status: **READY_FOR_INTEGRATION**, feature branch only. Focused SQLite/MySQL acceptance
and the final full regression gates pass. No push, deployment or production Apply.

Base: `50be1f3d768fd006d2837e851ff682ab4893c7bd`

Branch: `codex/wald-sourcecontract03-2026-10-06`

## Product and source contract

DEC-086 separates plot inventory from optional call-off evidence. A valid CustomerCode
and approved hierarchy establish a base plot row when Call Type is blank or absent.
Call Number may also be blank or absent. Plot identity remains the exact resolved site
binding plus normalized Plot Ref; it does not depend on either call field.

A recognized call-off still requires a valid permanent Call Number and controlled Call
Type. Unknown nonblank types remain blocked/Unknown; target selection cannot approve
their meaning. Repeated nonblank Calls remain blocking, including the 88 groups from
SOURCECONTRACT02. The new inventory capability does not resolve that separate ambiguity.

Current approved Call Type and CustomerCode exclusions continue to exclude the whole
row, including customer/site/plot/product effects. No plot is created solely from an
excluded row. Base dates and completion fields make no assertion. Missing products make
no assertion, explicit zero remains exact, and conflicting quantities block Apply.
All exports remain PARTIAL_FILTERED_EXPORT even when intended to contain all plots.

## Capabilities and headers

Discovery records plot-register, Call Number, Call Type and existing-call-off-data
capabilities separately. Canonical controlled CustomerCode/hierarchy headers work with
neither call column. The exact alternate RedZebra XLSX profile may omit only Call and/or
Call Type, preserving the remaining five ordered fields. Customer Name, Arrival and
Complete Date remain private evidence.

The two-field register profile resolves the generic all-text header warning only when
exact approved identity/hierarchy roles occur on one unique header row. Competing
headers, tables, unsafe cells, hidden structure and other existing guards remain.
New register rows with missing/blank call fields cannot evade malformed hierarchy
review by falling back to the historical flat format. Previously accepted, explicitly
bound flat exports with populated Call references retain their compatibility path.

Exact approved call headers may be resolved automatically even when their columns are
entirely empty, under the plot-register capability. This records normal immutable
automatic structural answers; it grants no Apply approval and changes no business meaning.

## Evidence and persistence

Every physical base row remains staged, including repeated evidence for one Plot.
Unnumbered base rows create no fake Call Number, numbered Source Row or Visit. Committed
receipts retain each row's worksheet/physical row, target Plot ID, fact hash and provenance
hash. Numbered Source Rows retain their existing namespace + CallNo identity.

Reviewed Apply sets plot_register_established_at on new or reused included plots.
This permits the normal first Portal call-off before a source service exists. Base
import itself creates no services, requests, dates, completion, amendment, request
history or notifications. Portal submission creates only the explicitly requested
service with source_present false. Previously missing/completed source services keep
their guards. No user assignment is performed by Wald.

## Representative mixed workbook

The synthetic fixture contains 20 blank-call base rows, PC1 and CC1 rows for one of
those plots, and a CM1 row for a named Plot. Dry run, review, preview and disposable
Apply produce:

| Result | Count |
|---|---:|
| Physical rows | 23 |
| Base rows | 20 |
| Recognized call-off rows | 3 |
| Distinct site-scoped plots | 21 |
| Plots without source call-off evidence in this export | 19 |
| Plot creations on first Apply | 21 |
| Source visits | 3 |
| Source service rows | 3 |

The base + PC1 + CC1 rows for one Plot create one Plot with independent source service
facts. Separate fixtures cover repeated base observations, `033`, `Com 4`, all four
alternate call-column combinations, and the minimal two-column register.

## Site Manager acceptance

The automated HTTP acceptance journey imports a blank-call Plot 776, applies it to a
disposable database, assigns a fictional Site Manager, opens its customer page showing
Not Called Off, submits its first Windows request through review/confirmation, and
opens that request as Office. Office agrees the selected date. A later PC1 import
reuses the same Plot and service while preserving the request, requested/agreed dates
and history. Active-request protection prevents a second request; an unrelated
customer/site user receives 404 for the Plot. All four service choices and normal BF
lead-time/early-reason rules are covered separately.

No real workbook was applied. No production browser or database was accessed.

## Office summaries and review

Dry run distinguishes discovered Plots, base rows, Plots with no source call-off in
the current export, recognized call-off rows, excluded rows and unknown nonblank types.
The wording does not imply that an existing Portal request or earlier source visit has
disappeared. Normal import and selected-site summaries display base/call-off row counts.
Base rows participate in CustomerCode review with normal default row selection. Unknown
nonblank types enter the Unknown queue; choosing a Customer/Site does not authorize them.

## Compatibility versions

- Knowledge policy: v5.
- Plot-register capabilities: v1.
- Source-profile registry: v2.
- Alternate profile: redzebra-master-optional-call.v2.
- Minimal register profile: redzebra-plot-register.v1.
- Pilot discovery: v9.
- Backend application: pilot-v5; projection: v5.
- Generic Wald and business dictionary: unchanged.

Older uncommitted interpretations become stale; committed history is preserved.

## Database qualification

Disposable MySQL 8.4.11, loopback only, 26 migrations. The focused acceptance file
passes its acceptance scenarios on both SQLite and MySQL. The final MySQL run is
21 tests / 261 assertions. MySQL covers base Apply,
unnumbered persistence, first Portal request/agreement, later source reconciliation,
product conflict and stale Apply rejection. A separate rollback/reapply of additive
000026 preserves an existing fictional Plot and leaves its inventory evidence null;
it does not retrospectively certify old plots.

## Verification

Focused SQLite acceptance/UI checks: 30 passed / two expected skips / 370 assertions
across 32 tests, including all 21 SOURCECONTRACT03 scenarios. MySQL: 21 passed / 261
assertions. Related compatibility checks: 135 passed / 10 expected skips / 1,137
assertions. Final full suite: 2,107 tests / 2,015 passed / 92 expected skips / 11,405
assertions. Pint and whitespace validation pass for the final candidate.

Strict Composer validation and frontend build pass. npm production audit reports zero
vulnerabilities. Composer reports five pre-existing advisories affecting four packages:

| Package | Severity | Advisory |
|---|---|---|
| filament/filament | Medium | PKSA-cnr1-6g7k-bp3d |
| laravel/framework | Low | PKSA-d5tc-s1qs-h781 |
| league/commonmark | Medium | PKSA-m2dq-1fhr-29b1 |
| league/commonmark | High | PKSA-m4t9-vsgq-8khn |
| league/flysystem | Low | PKSA-w9tt-7782-78jx |

No dependency or lockfile changes are included.

The disposable MySQL server was shut down normally and its task-only database,
configuration and scripts were removed. Generated private test uploads were removed.
The user's original workbooks remain unchanged.

## Migration and files

One additive migration, 2026_10_06_000026_add_plot_register_evidence.php, adds a nullable
timestamp to projected_plots. Existing migrations are unchanged.

Task-related changes are:

- app/Actions/CallOff/BuildCallOffMatrixAction.php
- app/Actions/CallOff/DetermineCallOffEligibilityAction.php
- app/Actions/CallOff/SubmitMultiCallOffBatchAction.php
- app/Http/Controllers/CallOffRequestDetailsController.php
- app/Http/Controllers/PlotDetailsController.php
- app/Http/Controllers/ReviewRequestsController.php
- app/Http/Controllers/SiteDashboardController.php
- app/Models/ProjectedPlot.php
- app/Services/CallOffDateViewService.php
- app/Services/CallOffLeadTimeService.php
- app/SourceImport/Integration/AutoResolveClarifications.php
- app/SourceImport/Integration/BackendStore.php
- app/SourceImport/Integration/CustomerCodeReviewWorkflow.php
- app/SourceImport/Integration/PilotImportWorkflow.php
- app/SourceImport/Integration/PilotWorkbookDiscovery.php
- app/SourceImport/Integration/PlotRegisterCapabilities.php
- app/SourceImport/Integration/ProjectionAdapter.php
- app/SourceImport/Integration/ProjectionSnapshot.php
- app/SourceImport/Integration/RedZebraExportProfile.php
- app/SourceImport/Integration/UnknownRowsWorkflow.php
- app/SourceImport/Integration/WaldDryRun.php
- app/SourceImport/Integration/WorkbookStager.php
- app/SourceImport/Knowledge/AnalysisSnapshot.php
- app/SourceImport/Knowledge/KnowledgeIdentity.php
- app/View/Presenters/ImportReviewPresentation.php
- database/migrations/2026_10_06_000026_add_plot_register_evidence.php
- resources/views/office/pilot-import/dry-run.blade.php
- resources/views/office/pilot-import/site-review.blade.php
- resources/views/office/pilot-import/source-sites.blade.php
- tests/Feature/WaldSourceContract03/PlotRegisterTest.php
- DECISIONS.md, brief.md, current_sprint.md, HANDOVER.md
- documentation/source-integration-contract.md
- documentation/siteapp-import-data-dictionary.md
- documentation/wald-divergence-register.md
- this qualification report.

## Integration disposition

Feature branch only. No push, main merge, deployment or production impact. Controlled
integration must include additive 000026 and refresh older uncommitted analyses. A
genuine full-register export should be analysed/reviewed before any live Apply. The
existing SOURCECONTRACT02 workbook remains subject to its nonblank repeated-Call gate.
