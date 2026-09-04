# CUSTOMER-WALD01 — Standalone Wald Import Engine Adoption

Date: 4 September 2026. Owner: Product and Architecture.
Status: CUSTOMER-WALD01A decision reconciliation complete. Implementation packages
defined; no code port, schema change, pilot activation or production release performed.

## 1. Decision and authority

CustomerApp will be used before SiteApp is ready for wider use. It will therefore own a
standalone **Wald Import Engine**, beginning with a controlled fork of the compatible
SiteApp WALD01–07 implementation. The user-facing name is simply **Wald**.

```text
Spreadsheet → CustomerApp Wald → CustomerApp staging/review → CustomerApp domain
Spreadsheet → SiteApp Wald    → SiteApp Import Studio     → SiteApp domain
```

Neither path requires the other application. CustomerApp owns its deployment, private
files, database, queue, source registration, analyses, questions, answers, profiles,
dictionaries, review decisions and commit history. There is no shared runtime database,
filesystem, queue, API, profile synchronisation or external interpretation service.

This newer instruction supersedes SiteApp WALD01's proposed SiteApp-hosted consumer
projection as the prerequisite for CustomerApp rollout. It does not change SiteApp files
or authorise a SiteApp backport. A separately agreed read-only integration may follow;
neither such an API nor a cross-application identity mapping is invented here.

The product remains a customer communication/request portal. Source spreadsheets remain
the operational evidence during standalone rollout; importing them does not make the
Portal an operational management system. Source completion and customer-negotiated dates
have different authority. SiteApp remains a separate operational product.

Non-negotiable principles:

- Wald infers structure; confirmed CustomerApp domain dictionaries define business meaning.
- Uncertainty produces a question or a safe unsupported-input result, never a silent guess.
- Confidence is evidence strength, not probability, approval or permission to write.
- No OpenAI/ChatGPT API, Anthropic, Gemini, external LLM, embeddings or external spreadsheet
  interpretation service, including fallback paths. No workbook/answer upload to AI.
- Retain generic concepts and names where compatible. Do not invent a competing scorer,
  profiler, clarification system or format-learning mechanism.
- Do not extract a shared Composer package now. The inspected persistence and integration
  layers are application-coupled; a package is not presently a trivial extraction.

### Decision-status classification

| Status | Items |
| --- | --- |
| **CONFIRMED BUSINESS RULE** | Valid call dictionary; literal CC! handling; part-level `complete=Yes`; Windows/Doors roll-ups and product meanings; excluded products; exact BF lead-time preservation; ignored fields; PC1 operational-date meaning; filtered-export default. |
| **ARCHITECTURAL DECISION** | Standalone CustomerApp Wald distribution; structure inference separated from controlled business meaning; private neutral staging/review/commit boundary; future source Site ID/reference as durable site identity; Site Name transitional only. |
| **TEMPORARY IMPLEMENTATION** | The non-main manual XLSX/interpreter/profile/UI feature line and any exact-Site-Name binding used before durable source Site IDs are supplied. Neither is the final production architecture by existence alone. |
| **UNRESOLVED GOVERNANCE DECISION (outside this semantic set)** | Source owner/revision authority; final import/knowledge abilities; retention; reviewed commit atomicity/recovery. |
| **UNRESOLVED TECHNICAL DELIVERY ITEM** | Approved immutable SiteApp Wald source baseline; additive schema and revision-order enforcement; MySQL/worker/storage/pilot evidence; delivery of permanent source Site ID. |

## 2. Evidence baseline and limits

Read CustomerApp `AGENTS.md`, `brief.md`, `ROADMAP.md`, `context-work-prompt.md`,
`DECISIONS.md`, `current_sprint.md`, `HANDOVER.md`, source/target-domain contracts and the
source-import report. Inspected the actual importer, mapper, DTO, product/completion
models, lead-time service, gates, routes, source migrations and regression tests.

Reference repository: `C:\Users\JoshO\Documents\SiteApp`, read-only. Read all seven
`documentation/work-packages/WP-WALD01…WP-WALD07` documents. Inspected Wald contracts,
profiling/confidence/semantic services, dictionary/schema allowlists, access/answer code,
profile matching/reuse, normalisation/staging/orchestration/job code, all three Wald
migrations and representative test assertions plus suite inventories.

| Evidence | Observed state |
| --- | --- |
| CustomerApp checkout | `codex/qa-office-staff-organisation-model-2026-08-27`, HEAD `27936f15d1bef756225dedfff1dd23e659ba5b5a`. This is not an audit of every other branch. |
| Existing CustomerApp local work | Modified `documentation/sprint-3e-date-negotiation-report.md`; untracked `Copy of siteapp1.xlsx` and `output/`. Preserved; workbook/customer data not opened or copied. |
| SiteApp HEAD | `73caab7bc76fce6a43808b870827a25cd4520549`. Wald code/docs/tests/migrations are largely untracked; related integration edits and unrelated edits are also present. |
| Fork baseline | **Not yet reproducible from HEAD alone.** Before copying, obtain an owner-approved immutable commit or reviewed path/hash manifest covering the exact portable source, dependencies and tests. Do not copy the entire dirty working tree. |
| SiteApp reported verification | WALD07 reports 354 combined Wald tests / 1,319 assertions and scoped local integration/build checks. These are upstream report results, not tests rerun in this audit. |
| Upstream release limits | MySQL runtime/lock rehearsal, real storage/worker checks and real-workbook pilot remain open in WALD07. Earlier full-suite preview-configuration failures are not declared resolved. “Proven reference” does not mean unconditional production certification. |
| CustomerApp release history | User confirmed Sprint 3E release at `9111d76ff05d702d68afd884ee8e42bc8e50c8e3`, Forge `76326195`. Older planning headers still describe it as unreleased. No production connection/reverification occurred here; local HEAD is a different evidence point. |

CUSTOMER-WALD01A additionally inspected the committed, non-main feature chain
`feature/manual-source-import-backend` (`04b560f`) →
`feature/deterministic-spreadsheet-interpreter` (`a013ed1`) →
`feature/manual-source-import-ui` (`1e8c22b`) through Git objects, without switching
branches or modifying its worktree. This evidence corrects the earlier checkout-only
statement that no spreadsheet reader/review flow exists. The feature line remains outside
`main`, has not been accepted as the final Wald architecture and retains an outstanding
disposable MySQL gate.

Local command checks succeeded: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0,
Git 2.55.0.windows.3. No packages installed/upgraded. This is not a whole-codebase QA
certification, a workbook accuracy assessment or evidence of Wald running in CustomerApp.

## 3. Portable-core audit and exclusions

Paths in this table are relative to the SiteApp reference repository. “Port” means a
future reviewed copy with its tests, not work performed by this document.

| Component | Classification and treatment |
| --- | --- |
| `app/Contracts/Wald/{WorkbookSource,WorkbookProfile,CellObservation,SheetObservation,SourceRange,CandidateRegion}.php` | Generic observation DTOs/interfaces: port with physical lineage and capability semantics. |
| `app/Services/Wald/{WorkbookSourceFactory,XlsxWorkbookSource,CsvWorkbookSource,AnalysisBudget,AnalysisProblem,WorkbookProfiler,SheetProfiler,RegionDetector,HeaderDetector,ValueProfiler,StructuralEvidence}.php` | Generic reader/profiler: port bounded XLSX/UTF-8 CSV behaviour. Preserve the WALD06 Notes-first correction and current structure version. |
| `app/Contracts/Wald/Reasoning/` and `app/Services/Wald/Reasoning/` | Generic framework, rules, evidence, scorer, constraints, confidence and explanations: port as a coherent tested set, including later provenance/context hooks and human-confirmed handling. |
| `app/Contracts/Wald/Semantics/` | Reusable typed concepts, **not** an unchanged domain contract. `ImportSchema` only names `plot_master`/`stage_schedule`; `DictionarySnapshot` hardcodes five namespaces; `SemanticScope` names SiteApp company scope. Adapt/version for Portal schemas and tenant scope. |
| `app/Services/Wald/Semantics/` | Reuse inference/full-validation mechanics and scoped-dictionary checks. Replace construction-role defaults, grain rules and domain providers. `SemanticPlan`/constraints/validator contain stage-specific logic; a namespace rename is insufficient. |
| `Semantics/SiteAppReferenceDictionaries.php` | **Exclude.** It directly queries active `Trade` and `WorkflowStage` models. CustomerApp supplies its own approved snapshots without these tables or queries. |
| `Clarification/{StructuralSelector,ConfirmedMappingRule,ConfirmedMappingConstraint,ConfirmedMappings}.php` | Mostly reusable mechanisms; adapt typed semantic scope/registry dependencies and keep provenance checks. |
| `Clarification/{QuestionFactory,ClarificationRegistry,AnswerValidator}.php` | Adapt wording, role/schema choices, requiredness and business-definition authority. SiteApp Trade/Stage questions and unrestricted adoption of its `define_values` path are not Portal permissions. |
| `Clarification/{WaldAccess,ClarificationService,KnowledgeRepository}.php` | Application adapters plus reusable lifecycle logic. Replace `ConstructionImport*`, company locks, role-assignment queries and `RolePreviewService`; preserve fresh authorisation, expected-run/sequence checks, idempotency and successor history. |
| `Profiles/{ProfileSignature,ProfileSimilarity,ProfileSuggestionRule}.php` | Portable signature/similarity/evidence logic, with typed registry adaptation where needed. Exact file SHA is not format identity. |
| `Profiles/{ProfileMatcher,ProfileReuse,ProfileAnalysis,ProfileEvolution}.php` | Mixed persistence/domain adapters. Reuse algorithms and lifecycle, but replace `ConstructionImportProfile`, Company scope, profile audit storage and access checks with Portal-owned equivalents. `ProfileReuse` itself queries Eloquent; do not label the entire directory pure. |
| `Integration/WaldSourceNormalizer.php` | Reusable source reread/lineage/budget approach, but currently accepts `WaldAnalysisRun`, assumes one Plot identity per region and returns trimmed strings plus Stage context. Needs a Portal call-record schema adapter and typed value validation, not blind copying. |
| `Integration/{WaldImportOrchestrator,WaldSourceLocalizer,WaldSessionRevisionService,WaldImportPresenter}.php`, `ProcessWaldImport`, recovery command | Port idempotent durable-operation, safe-localisation and resumable-UI patterns through new Portal adapters. Replace session models/configuration, role checks, command names and URLs. |
| `Integration/WaldStagingService.php` | **Do not transplant.** It creates SiteApp staging Site/phase/Plot candidates and groups stage rows into Plots. CustomerApp needs source-call/plot/service staging, not phase provisioning. |
| `ConstructionImportCommitService`, review/profile administration, Filament resources, policy classes and production lineage | **Exclude implementation.** Reuse documented safety patterns only. No SiteApp commit path or workflow provisioning in CustomerApp. |
| SiteApp Plot, Project, Site, Company, Trade, WorkflowStage, assignments, role preview, internal notes/statuses | **Exclude models, tables, services, permissions and data.** Portal-owned `Site`, `CustomerOrganisation` and `ProjectedPlot` are not renamed copies. |

The reader uses PHP ZIP/XMLReader/SimpleXML and native CSV, not a new parsing dependency.
Verify those extensions in the target worker during implementation. XLS, XLSM, ODS,
PDF/OCR, Office automation and universal freeform workbook support are not claimed.
Unsupported layouts must ask a bounded question or refuse safely.

### Migration audit

SiteApp `2026_09_03_000027_create_wald_clarification_memory.php` creates runs, questions,
decisions and knowledge; FKs reference `construction_import_sessions`, source files,
profiles, users and `companies`. `000028_add_wald_adaptive_profiles` extends existing
Import Studio profiles and adds bindings/matches. `000029_add_wald_import_orchestration`
extends Import Studio sessions and adds operations. These migrations **cannot run unchanged
in CustomerApp**. Their restrictive audit FKs, short constraint names, uniqueness and
populated-rollback refusals are patterns to retain in new additive Portal migrations.

## 4. Existing CustomerApp importer and non-main feature audit

The current audit checkout has a deterministic **projection importer**, not a workbook
interpretation engine. `SourceProjectionImportService::import(sourceName, records,
sourceVersion)` accepts already constructed `SourceRecord` objects. However, the separate
committed feature line ending at `feature/manual-source-import-ui` does contain a manual
XLSX reader, deterministic structure interpreter, source-site bindings, explicit export
scopes, dry-run/preview and commit protections, saved semantic profiles and an Office
import UI. It is non-main evidence, not an approved production baseline or a substitute
for standalone Wald.

| Category | Actual files/behaviour | Treatment |
| --- | --- | --- |
| Generic — replace/reuse via Wald | The audit checkout has no layout/header/type inference. The non-main feature line adds a deterministic interpreter, XLSX inspection, structural DTOs, fingerprinting and profile matching. | Compare these mechanics with the approved SiteApp Wald baseline. Reuse generic corrections/tests where they improve the standalone core; do not create a second permanent inference engine. |
| CustomerApp domain rule — keep | `app/Data/SourceRecord.php`, `SourceProjectionImportService.php`, `SourceProjectionIssueService.php`; `SourceImportRun`, `SourceProjectionEvent`, `SourceProjectionIssue` models. | Retain projection identity, safe issue reporting, source freshness, completion/reversal and audit behaviour behind an authorised reviewed commit boundary. |
| Confirmed dictionary — feed Wald/domain | `SourceCallTypeMapper.php`; `CallOffServiceType`; `brief.md` §§10–12; `documentation/siteapp-import-data-dictionary.md`. | One versioned Portal definition source for the valid call dictionary and completion signal; no independently maintained conflicting map in Wald. |
| Domain calculations/presentation — keep | `CallOffLeadTimeService.php`, `ProjectedPlotProduct::isBifold()`, `PlotOverviewQueryService`, eligibility and conflict Actions. | Keep downstream. Wald must not calculate request windows, statuses or operational availability. Audit BF assumptions below. |
| Duplicate implementation — retire only after parity | The non-main direct XLSX interpretation/profile flow overlaps the future standalone Wald front end. | It is a supersession candidate only after CUSTOMER-WALD02–05 parity and an explicit integration decision. Nothing is approved for deletion or cherry-pick wholesale. |
| Confirmed semantics | Call types, `complete = Yes`, customer product roll-ups, exact BF handling, ignored fields, PC1 operational date, transitional site-name mapping and filtered-export scope are now confirmed in §6. | Version them in the Portal dictionary and corpus. Structural inference must not recreate or override them. |

### A/B/C/D classification of the non-main feature line

| Classification | Feature work | Treatment |
| --- | --- | --- |
| **A — Candidate to backport/reuse in standalone Wald** | `SpreadsheetStructureInterpreter`; structural workbook/cell/sheet/column DTOs; bounded XLSX container/date inspection; deterministic header/role/value profiling; coordinate-independent fingerprints and match scoring; safe formula/container checks. | Compare file-by-file with the approved SiteApp Wald baseline. Port only a demonstrably useful generic correction with its tests and version entry; do not copy the complete feature branch. |
| **A — Candidate to backport/reuse in standalone Wald** | Generic parts of `WorkbookInterpretationProfileService`, `WorkbookMappingService` and the matching/confirmation tests. | Reuse algorithms or fixtures where compatible. Wald's clarification/profile lifecycle remains authoritative, so CustomerApp Eloquent persistence and simplified confirmation are not the target design. |
| **B — CustomerApp-specific/downstream** | `SourceRecord`/`SourceImportContext`, `SourceImportScope`, source-site binding/resolution, Portal projection import/reconciliation, Call No. identity, completion precedence, exact site/customer authorisation and stale-preview/commit revalidation. | Retain as Portal adapter/domain concerns and reconcile them with Wald neutral staging. Never move Portal workflow or tenant selection into the generic engine. |
| **B — CustomerApp-specific/downstream** | Controlled call-type/product/completion dictionary, Total Windows/Doors projection, exact BF lead-time input, PC1 operational date isolation, ignored commercial/operational fields and partial/site/global scope policy. | Keep in a versioned Portal/source-family dictionary and domain adapter. Wald may consume the snapshot but may not infer or redefine it. |
| **B — CustomerApp-specific/downstream** | Office authentication/gates, Portal routes/controllers, source-site binding UI needs, review/commit audit, private storage and run/reconciliation presentation. | Preserve requirements and useful safeguards. Rebuild against the final Wald orchestration and confirmed import entitlements rather than assuming this branch's Office-only grant is approved. |
| **C — Superseded by standalone Wald** | The fixed/direct `XlsxSourceReader` → manual preview/commit interpretation pipeline, `ManualSourceWorkbookContract`, direct `ManualSourceImportAnalysisService` orchestration and any parallel profile store that bypasses Wald runs/questions/neutral staging. | Do not merge as the final architecture. Use only as comparison evidence until CUSTOMER-WALD05 proves the Wald route; retirement still requires explicit approval. |
| **C — Superseded by standalone Wald** | Current Office import screen where it assumes a single synchronous/manual deterministic mapping flow. | Treat its route/UI implementation as provisional. The final screen must support queued Wald analysis, clarification, resumable generations, neutral review and controlled commit. |
| **D — Test/corpus/reference only** | The fictional 27-column workbook fixture, the read-only `siteapp1.xlsx` observations, shifted/reordered/header/date regression cases, explicit-scope cases, UI wording and MySQL runbook. | Carry forward anonymised fixtures and expected outcomes. Never commit the operational workbook or copy historical pass totals as new Wald certification. |
| **Mixed migrations (`000009`–`000012`)** | Bindings/preview/run/scope fields are Portal concepts; deterministic-profile persistence overlaps Wald; semantic-version migration records useful correction history. | Do not cherry-pick wholesale. CUSTOMER-WALD04/05 must design additive Portal-owned schema and preserve any reusable data contract through reviewed migrations. |

### Integration blockers found in the actual code

1. **Scope and completeness:** `markMissing()` scans every known service for a
   `sourceName`; it has no site, customer, selected-region or delta boundary. A one-site
   workbook or filtered review selection could mark other sites missing. Before live
   use, define server-authorised snapshot coverage; absence reconciliation must run only
   for a completely read, validated, explicitly reviewed authoritative scope. Failed,
   delta or incomplete inputs cannot imply absence. Do not invent a source name per
   upload to evade this: it would fragment identity.
2. **Trust boundary:** the importer is internal, with no actor/policy parameter. It
   resolves source sites globally. A browser or worker must never invoke it directly
   with submitted site IDs or raw DTOs. Commit must reauthorise the actor, selected
   organisation/site scope and every destination immediately before persistence.
3. **Atomicity:** current behaviour is one transaction per Call No., followed by separate
   product transactions and absence reconciliation. Valid rows survive rejected rows;
   an unexpected later failure can leave prior rows applied. It is not whole-import
   atomic. Preserve this fact in UI and tests; do not advertise whole-batch rollback.
   CUSTOMER-WALD05 must settle the reviewed commit unit (see §14) before exposure.
4. **Products:** `array_merge` combines maps by plot, so later duplicate product keys
   replace earlier values. Missing products become zero; `[]` cannot distinguish unknown
   products from an authoritative empty product snapshot. Conflicting quantities,
   repeated rows and omitted columns need an explicit completeness/aggregation contract.
   Do not sum every physical row or zero omitted columns automatically.
5. **Identity/grain:** DTO requires Call No., source site, plot reference and call type.
   Database has globally unique nullable `source_call_number` and one source Call No. per
   plot/service. Repeated plots across services are legitimate, but the existing contract
   rejects duplicate Call Nos. and rebinding. A plot-master schema requiring unique Plot
   values is therefore insufficient; do not generate Call Nos. from row positions.
6. **Revision ordering:** `sourceVersion` is stored, not a stale-revision rejection rule.
   Plot timestamp handling retains the newest timestamp but does not prevent an older
   payload overwriting current service/product facts. Require reviewed source-family
   revision identity and stale/out-of-order conflict handling before repeated imports.
7. **Audit/neutral data:** the DTO has no workbook cells, profile, answer provenance,
   raw source fields, operational placeholder date, Notes or generic override metadata.
   Keep full lineage in new staging/commit associations. Do not force these into
   customer responses or silently discard them during conversion.
8. **Scale:** `Collection::make()` materialises the iterable; per-record service/plot
   queries and product passes are not a streaming high-volume pipeline. Existing 400-row
   tests establish behaviour, not production workbook capacity. Benchmark before choosing
   chunking/transaction boundaries; never optimise away scope/identity checks.

Relevant retained tests include `Sprint3bSourceProjectionImportTest.php` (mapping,
idempotency, malformed rows, duplicates, rebinding, source timestamps, completion/reversal),
Sprint 3C presentation, Sprint 3D eligibility and Sprint 3E negotiation/concurrency tests.
Their existence does not prove the new workbook boundary or its permissions.

## 5. CustomerApp semantic-role registry contract

Create a **Portal-owned**, versioned registry. Stable generic keys may remain where meaning
is unchanged; use an explicit Portal registry/schema identity. The following classifies
all 21 existing SiteApp roles; registration is not permission to publish a field.

| SiteApp role | CustomerApp treatment |
| --- | --- |
| Plot Identifier | Reuse candidate mechanics; required plot reference, not globally unique across call records/services. House No/Sales Plot requires explicit resolution. |
| Secondary Reference | Retain independently as source evidence; not a substitute primary key. |
| House Type | Optional source context; no SiteApp house-type model or workflow provisioning. |
| Site | Critical candidate; resolve only to authorised existing Portal site/source identity. Workbook text cannot choose tenant. |
| Project | Optional source reference/label, not permission to create a Project domain or assume Site equivalence. |
| Phase | Optional source grouping/lineage only; no trade sequencing or automatic phase records. |
| Trade | Exclude from Portal mapping; preserve unknown source evidence privately if encountered. |
| Workflow Stage | Exclude as a workflow concept. Separately identify a **source Job Stage code** only for the confirmed completion vocabulary. Never query SiteApp stages. |
| Planned Date | Source-only, meaning must be qualified. `Plot To Be Installed` is confirmed PC1 operational arrival-to-install evidence, not a customer Requested/Agreed/alternative date. |
| Actual Date | Critical source candidate, not automatically service completion without the confirmed field meaning. |
| Completion Date | Critical actual Completed Date; date-only normalisation with explicit locale/system and immutable raw value. |
| Start Date | Optional source-only observation; no scheduling behaviour. |
| End Date | Optional source-only observation; no scheduling behaviour. |
| Percentage | Source claim with explicit scale; never implicit completion or readiness. |
| Quantity | Material typed non-negative quantity with confirmed unit/product mapping and completeness. |
| Status | Source status only; never copied into a Portal request status. |
| Boolean Flag | `complete = Yes` is authoritative completion evidence for that specific source call-off part. Parse sensible Yes/No variants case-insensitively; retain raw evidence and never invent a date. Other booleans remain unconfirmed source claims. |
| Product Code | Critical known business vocabulary; unknown tokens retained and flagged. |
| Call Type | Critical namespace with the confirmed PC1/CC1/CM1/CM2/CML dictionary. A literal `CC!` is unknown/likely typo evidence, never a silent alias. |
| Notes | Optional confidential source text. Not automatically customer-visible, not `customer_response` or private decision rationale. |
| Customer Reference | Source identifier, not tenant-selection authority or automatically a plot identity. |

Portal additions: **Source Call Number** (critical permanent identity), **Service Code**
(separate from Product Code; only approved aliases to the four services), **Source Updated
Timestamp**, and **Source Job Stage Code** (as bounded above). No bare generic Date may
select planned, agreed or completed meaning by score alone.

Initial schema proposal: `customer_call_records.v1`, one source call record per permanent
Call No., with source-site/plot/call-type context. Product detail rows or multirow calls
require an explicit source-family grouping rule; do not borrow SiteApp's `(Plot, Stage)`
grain. A plot-only sheet can be analysed/staged as evidence but cannot commit through
`SourceRecord` without its required identifiers. Required unknown fields block dependent
records; optional ignored fields stay raw with a reason and cannot influence domain state.

## 6. Dictionary register and business authority

Dictionary snapshots must pin namespace, schema, version/hash, owner/confirmation source,
scope, approved canonical entries, aliases, value type/unit, permitted transformations,
and approval attribution. Generic terminology offers candidates, not business truth.
Code-owned versioned snapshots are sufficient initially; no generic dictionary CRUD/admin
product is needed. SiteApp's hardcoded namespace allowlist must be adapted explicitly.

| Category | Evidence and allowed interpretation |
| --- | --- |
| Call Types | **Confirmed business rule:** PC1 = Plot Install and maps to Windows; CC1 = Cavity Closer 1 and maps to Cavity Closers; CM1 = Revisit 1 and CM2 = Revisit 2, both CML revisits mapped to CML; CML = CML Call Off and maps to CML. These remain four customer-facing services; the two revisits are not new services. No source code for Snagging is invented. Literal `CC!` is unknown/likely typo evidence; optionally suggest CC1, require confirmation and never silently normalise it. |
| Service Types | **Confirmed:** Cavity Closers, Windows, Snagging, CML, independent services in that display order. Codes are not customer-facing labels. CML expansion remains TBC. |
| Completion vocabulary | **Confirmed business rule:** case-insensitive sensible Yes/No parsing of `complete`; `Yes` completes that particular source call-off part. It may produce truthful undated completion but does not supply a missing service mapping. Do not require or infer `Job Stage`, `Completed Date`, `CC08`, `CA02`/`CA03`, `SN05` or `CML4` unless separately confirmed later, and never invent a completion date. |
| Customer product roll-ups | **Confirmed business rule:** Total Windows = `VS + TT + BAY + ALI + AOV + FI`; Total Doors = `PSU + PSG + CDF + CDU + CDG + PSP + BF`. Individual approved source values remain available internally for calculation/evidence; the customer product model publishes the roll-ups rather than raw code inventory. |
| Product meanings | Windows: VS Vertical Slider; TT Tilt and Turn; BAY Bay Window; ALI Aluminium Windows; AOV Automatic Opening Vent Window; FI Fire Window. Doors: PSU PVC Door Utility; PSG PVC Door Garage; CDF Composite Door Front; CDU Composite Door Utility; CDG Composite Door Garage; PSP PVC Sliding Patio; BF Bifold. |
| Excluded/redundant products | `CAS`, `FLU`, `PFD`, `GLS`, `WP`, `MISC` do not enter the final customer product model. Raw values may remain private audit evidence. |
| BF lead time | **Confirmed business rule:** exact `BF` means Bifold, contributes to Total Doors and remains individually identifiable internally. Normal earliest request is four weeks; exact positive BF makes it five weeks. Similar text/substrings and Total Doors alone do not prove BF. |
| Ignored fields | `Items Ordered Status` is ignored and cannot drive status, completion, lead time or workflow. `Site Value` is ignored/excluded from the final Portal model. |
| Operational dates | `Plot To Be Installed` is the source date Fenster arrives to install PC1. It is operational/source evidence, never the customer's Requested Date, Date Agreed or an alternative proposal date. |
| Site identity | A future permanent source Site ID/reference is the durable identity. Exact Site Name may support transitional explicit mapping because management expects it to remain stable; it is not the permanent architecture and never creates/fuzzy-matches a Portal site. |
| Export scope | Every workbook defaults to `PARTIAL_FILTERED_EXPORT` because it contains whatever the exporter filtered. Absence proves no deletion, even within a represented site. `SITE_COMPLETE_SNAPSHOT` and `GLOBAL_COMPLETE_SNAPSHOT` require explicit confirmation; size, filename, row count and represented sites cannot upgrade scope. |
| Customer/source aliases | No source-family alias catalogue was verified. Version only explicitly confirmed scoped aliases; do not learn tenant names or meanings from filenames/titles. |

CM1 and CM2 are resolved as CML revisits. `CC!` is deliberately **not** resolved as a valid
business code: it remains raw unknown/likely typo evidence and may be suggested as CC1 only
for human confirmation. An actually unknown code, such as a synthetic `ZZ9`, follows the
same safe clarification principle. An ordinary answer must not redefine global business
meaning. A missing dictionary blocks use of that meaning, not profiling or the rest of
the architecture work.

## 7. Field-level precedence and customer-history protection

Interpretation precedence and data ownership are separate. Safety constraints and approved
business definitions always win. Within eligible structural mappings: a current explicit
workbook answer precedes a confirmed compatible profile, which precedes generic aliases.
Current contradictions, missing identities and ambiguous dates can still veto reuse.

| Data | Authority / permitted import effect |
| --- | --- |
| Tenant, active site, local/public IDs and access | Portal server-owned. Workbook cells cannot assign users, select organisations or move plots/calls. Existing association changes become issues. |
| Source Call No./site/plot/call type | Approved source identity plus reviewed binding. No array-position identity or silent reassignment. |
| Source product quantities | Reviewed authoritative source values under explicit coverage. Preserve individual BF before computing the confirmed Windows/Doors roll-ups. Missing/unknown outside the selected records is not automatically zero; conflicting duplicate evidence must not be last-row-wins. |
| Customer requested dates, Date Agreed, reasons, responses, agreement attribution | Portal Actions/history only. No spreadsheet value or field-role answer may create/replace agreement. |
| Open negotiations/amendments and source completion | `complete = Yes` for the specific source call-off part takes priority: close the corresponding process using the existing domain protection, supersede pending proposals, clear conflict state and retain all prior dates/history. This is not generic overwrite permission and supplies no completion date. |
| Completion reversal/correction | Follow confirmed source with an attributed source event. Do not silently reopen a completed request or reinstate a previous agreed date. Preserve newer active work. |
| Manual/confirmed values | Structural review corrections apply to a versioned interpretation, not a domain overwrite. No general manual-override field store exists in the audited importer. Any additional manual-authority rule needs a field-specific contract; do not invent “manual always wins”, which would contradict source completion. |
| Source Notes, commercial fields and raw evidence | Private import evidence unless explicitly allowlisted customer-safe. `Site Value`, source paths, raw diagnostics and internal notes never reach customer screens. |
| Source timestamps versus Portal observation | Preserve supplied source timestamp separately from `synchronised_at` and run times. Newer upload time does not prove newer source truth. |

Source completion alone sends no notification/email. Import must not create call-off
requests, Date Agreed notifications, general chat, operational workflow or internal stages.
Future amendment implementation is not included in this package; its preserved history
and completion precedence are requirements, not a claim that the full amendment flow exists.

## 8. Proposed Portal orchestration and persistence

```text
Authorised upload + private immutable source registration
  → queued profiling / adaptive profile matching / semantic interpretation
  → targeted clarification → successor analysis (repeat when necessary)
  → complete neutral staging
  → CustomerApp review + source revision/coverage comparison
  → current authorised preview
  → explicit controlled domain commit → projection/audit
```

Controllers authorise/validate/dispatch/respond; Actions/Services own workflow. No inference,
source queries or transitions in Blade/JavaScript. A “ready” semantic run does not itself
mean staged, reviewed or committed. Preserve truthful queued/analysing/needs-clarification/
ready-for-review/staged/failed/superseded/committed/cancelled/expired distinctions.

Proposed logical stores (names/DDL to be finalised in the implementation schema review):

| Store | Contract |
| --- | --- |
| Portal import session and source file | Server-authorised customer/site/source-family scope, actor, detected type, private disk/key, checksum, retention/security state and linked successor session. Separate from customer `CallOffBatch`. |
| Wald analysis run/questions/decisions | Immutable generations, expected run/sequence, actor, input/output hashes, pinned snapshots, bounded results/questions, correction/supersession chain. |
| Wald knowledge and profile versions | Portal-owned family/version/selector records, explicit draft/activation/revocation, immutable definition and bindings, scoped match history. No dependency on an absent Import Studio profile table. |
| Durable import operations | Operation ID, expected/result run, request hash, idempotency key, actor, lease/claim, attempts, safe failure and timing. Separate mutable execution state from immutable analysis results. |
| Neutral staging/review/commit ledger | Reviewed field/record disposition, previous/proposed projection, explicit source coverage/revision, preview fingerprint, idempotent commit receipt and field/source lineage linked to existing `SourceImportRun`/events. |

Use Portal integer keys plus public UUID conventions where appropriate, restrictive audit
relationships, deliberate unique/index definitions and SQLite/MySQL rehearsal. Do not copy
SiteApp migration filenames/sequence or edit CustomerApp's repaired historical migrations.
Store bounded evidence JSON/private artifacts, not a database row per empty cell. Schema
review must address failed-run audit retention and populated rollback refusal explicitly.

### Neutral representation

Retain compatible Wald concepts, but explicitly version a Portal call-record extension;
do not mislabel SiteApp's Plot-stage `wald.source.v1` payload as already interchangeable.
Envelope: distribution/schema, session/run/source checksum, pinned versions, output hash,
coverage/completeness, selected/excluded regions and reasons. Scope is server-owned.

Each record/field retains physical sheet/region/row/cell/header refs (CSV logical record
and physical line spans), raw value, typed/normalised value or unresolved alternatives,
semantic role, extraction quality, confidence band/strength, evidence, dictionary/profile/
clarification references, transformations, formula/cache/visibility flags and warnings.
The original remains private. Large immutable provenance can be referenced once per run,
provided every staged/committed field resolves back to it.

No general date/quantity normalisation is supplied by trimming strings. Validate locale,
date system, precision, leading-zero IDs, units and missing values before constructing
`SourceRecord`. Exactly one currently valid reviewed mapping must cover each required
field. No arbitrary client dictionary, path, company, profile snapshot or trusted fact.

### Commit guard

Pin source bytes, current run/output, answers, registry/dictionaries/profile, reviewed
selection, source family/revision/coverage and destination before-state in the preview.
Recheck authorisation and all pins under locks before persistence; reject stale previews.
Changing interpretation after staging creates a linked successor, not overwritten review.
Retries must not duplicate projections, history, source events or commit receipts.
Exactly one writer owns a source revision; shadow comparison never writes both outputs.

## 9. Permissions and knowledge scope

Existing Portal authority is confirmed: active Fenster Office Staff have global Portal
scope and may have no customer organisation (DEC-038); the three external Site User roles
have equal permissions restricted to assigned sites in their organisation. SiteApp's
Administrator/Managing Director split and role tables are inapplicable.

**Import entitlements have not been confirmed.** Proposed starting point: genuine active
Office Staff perform source upload/review/commit; Site Users have no import or dictionary
administration entitlement. This is a proposal, not a production role grant. Confirm
upload, evidence-read, answer, review, commit, profile activation and business-definition
approval abilities before enabling their endpoints. Do not invent a fifth Portal role.

No role-preview account may create durable imports or knowledge, irrespective of preview
toggle. Unauthenticated, inactive and revoked actors are denied; recheck in workers,
answers, profile activation, preview and commit, not just at upload. All object lookups
bind session/source/run/question/profile to the server-authorised scope.

Even a globally authorised Office actor must select the import's customer/site scope.
It is not inferred from their nullable organisation or workbook text. Private profiles,
answers, caches and samples do not cross customer/source boundaries because staff can
review globally. Default memory is workbook-only. Wider source/profile/organisation
memory requires explicit scope and activation; global promotion is a separate approved
dictionary/code release. Workbook field-role answers and business-code definitions are
distinct actions and audit facts.

Profile behaviour must preserve moved columns/header rows, blank/title insertions and
compatible optional additions. Confirmed aliases may assist known renames; arbitrary
renames ask again. Near-tied profiles require a choice, a score alone cannot activate a
profile, and contradictory current values override memory. Corrections/revocations
invalidate future influence without changing prior runs/commits. No profile exchange or
automatic synchronisation with SiteApp in this programme.

## 10. Queue, storage, security and performance

CustomerApp requires its **own durable asynchronous worker** for production Wald. The
previously reported production `sync` queue may serve the released portal, but is not
acceptable for this long-running pilot. No production queue was inspected or changed here.
The checked config defaults to database with 90-second `retry_after`; this is not a
verified worker setup and would not suit the upstream 300-second timeout/600-second lease.

Adapt WALD07's operation-ID jobs, request/delivery deduplication, expected-run checks,
claim fencing, bounded retries/backoff and abandoned-operation recovery. Dispatch only
after durable registration; recovery must also find work whose dispatch failed. Polling
is read-only and does not analyse the workbook. Browser close/reopen resumes persisted work.
Recovery defaults to inspection; apply is explicit. Terminal/stale/revoked work cannot
silently restart. Distinguish analysis recovery from reviewed domain-commit recovery.

Upstream starting values are 300-second job timeout, 600-second lease, 3 attempts and
30/120-second backoff; queue redelivery must exceed the lease (e.g. 660 seconds). These
are benchmark starting points, not changes made or certified CustomerApp settings.
Configure/rehearse scheduler, lock-capable cache, process supervision, worker restart and
failure monitoring. Do not borrow SiteApp's queue or watchdog. Company-wide long-held
locks in the reference may serialize work; measure tenant-lock contention before adopting
that scope or increasing concurrency.

Private workbook storage must be CustomerApp-controlled and readable by its web/worker
processes, not under public storage or SiteApp. Use generated keys, authorised download,
content hash/security/retention checks and bounded local copies for remote disks. Clean
only owned temporary artifacts; abrupt process death needs safe orphan handling. Back up
database plus original evidence/pinned artifacts and rehearse a coherent restore.
Source binary/artifact/answer/commit retention periods remain an explicit production gate;
do not copy SiteApp's 90-day setting or the call-off seven-day Trash rule.

Retain reader protections: signature/container validation; archive path/entry/decompression
limits; bounded sparse cells/rows/columns/strings/regions; no DTD/entity/network resolution;
reject macro/executable/encrypted/unsupported content; never run formulas, linked workbooks,
external relationships or Office. Cached formula results retain unknown freshness and
cannot bypass critical-field checks. Escape source text; prevent formula injection in any
future export without altering raw evidence. Safe errors/logs must exclude raw customer
values, paths, credentials and provider payloads. No successful partial/truncated profile.

Representative upstream limits: 25 MiB source, 100 MiB decompressed archive, 50 sheets,
1,000,000 physical cells, 10,000 rows/sheet, 1,024 meaningful columns, 300-second analysis
budget; adaptive PHP memory guard. WALD05 stores up to 8 MiB each of run input/result;
WALD07 bounds neutral records to 32 MiB and the configured item cap. Audit all actual
limits together, disclose coverage and fail safely rather than silently dropping rows.

Benchmark profiling, semantics/full validation, matching, answer reuse, normalisation,
staging, queue wait and controlled commit separately: P50/P95, peak memory, query counts,
artifact size, retries and lock duration at representative workbook/profile sizes.
Upstream WALD07 reported roughly 26–41 seconds analysis, 16–24 seconds staging and 406 MiB
whole test-process peak for 100k cells. That is local synthetic evidence, not CustomerApp
capacity. Approve target-worker budgets in CUSTOMER-WALD06; do not assume smaller input.

## 11. UX contract

Use the existing Portal layout/Blade/Tailwind/Alpine conventions, not copied SiteApp
Filament administration. Keep backend summaries and choices authoritative.

- “Wald analyses your spreadsheet, identifies the information the Customer App needs,
  and asks only when something is unclear.”
- “Wald needs your help” / “I found two possible Plot-number columns.”
- “Wald recognises this spreadsheet format.”
- “Remember this for similar spreadsheets.”

Show source heading, bounded examples, physical context and plain-language reason. Keep
technical fingerprints/rule IDs in authorised diagnostics. Explain that remembering a
layout does not approve future imports or redefine codes. Show selected customer/site,
source revision, affected plots/services and any omissions before review/commit.
Provide accessible keyboard/focus/error states, mobile touch targets, bounded source-grid
scrolling, resumable status and clear failure/retry states. No fabricated progress percent.

## 12. Tests and corpus travel with the fork

Port the compatible source **and tests together**, preserving the invariant while adapting
Portal models/policies. Synthetic SiteApp PC1/CM1 definitions are not Portal dictionary data.

| Reference tests | Required CustomerApp treatment |
| --- | --- |
| `tests/Support/WaldFixtures.php`, `WorkbookProfilerTest`, `WaldIntegrationBoundaryTest` | Port safe fixture generators, exact sparse locators, security/limit cases, determinism and zero-SQL profiling. Generated files stay in isolated testing storage. |
| `ReasoningEngineTest`, `WaldReasoningBoundaryTest` | Port evidence-family/correlation caps, ties, vetoes, prerequisites, malformed provenance, bound failures and stable ordering. |
| `WaldSemanticFixtures`, `SemanticInferenceTest`, `WaldSemanticBoundaryTest` | Retain generic roles/full-validation/scope/ambiguity cases. Replace Trade/Stage fixtures with Portal schemas; add negative assertions that SiteApp references never load. |
| `ClarificationEngineTest`, `WaldClarificationTest`, migration tests | Port successor immutability, candidate tampering, idempotency, stale answer/sequence, scope, revocation, correction and rollback tests with Portal-specific access/definition authority. |
| `AdaptiveProfileTest`, `WaldAdaptiveProfileTest`, migration tests | Port layout mutations, near ties, contradiction-over-memory, stale activation, scope isolation, compatible-cache-only reuse, immutable evolution and query budgets. |
| `WaldImportStudioTest`, frontend tests, benchmark scripts | Adapt queue/lease/recovery, resumption, linked corrections, staging atomicity and stale preview protections. Replace SiteApp commit assertions with Portal projection/history protections; do not copy SiteApp fixtures/admin setup. |
| Existing CustomerApp source/negotiation/eligibility/security suites | Retain as regressions and add current-Portal/source-completion races on MySQL. New import permissions need negative tests across all four roles and account states. |

Version each golden fixture with purpose, hash, scope/schema/versions, expected mappings,
questions, refusals, physical lineage, neutral output and expected projection effects.
Use synthetic/anonymised sources reflecting permitted formats; no confidential workbook
copies in Git. Hold out whole workbook families. Customer-supplied real samples require
permission, private storage and owner-reviewed expected answers; none were imported here.

Minimum Portal corpus:

- Five valid call types, literal `CC!` refusal/suggestion, case-varied `complete` Yes/No,
  truthful undated completion, PC1 operational dates, missing Call No./site, plot IDs with leading zeroes and competing
  House No/Sales Plot, repeated plots across independent services.
- VS, TT, BAY, ALI, AOV, FI, PSU, PSG, CDF, CDU, CDG, PSP and BF as source quantity
  headings; confirmed roll-ups, excluded-code retention, explicit missing/zero/conflicting
  values and unknown-code refusal.
- Customer refs/notes, hidden/commercial fields, duplicate identifiers and source aliases.
- Merged/two-/three-row headers, moved columns/header rows, title/blank rows and columns,
  totals, repeated phase blocks, side-by-side/multiple sheets, hidden data, formulas with
  missing/error/unknown-freshness caches and renamed/unknown headings.
- Benign seeded mutations preserve meaning while source locators change; semantic drift,
  misleading near-match, changed units and contradictory values require clarification.
- Partial/delta versus full-site/full-source coverage; missing products versus explicit
  zero; stale/out-of-order revisions; same Call No. rebinding; mixed-tenant source text.
- Open negotiation, Date Agreed, source completion/reversal and concurrent customer action;
  immutable history and no false completion/agreement notifications.

## 13. Migration, parity, cutover and rollback

1. Freeze the approved portable baseline/path manifest and preserve current importer/tests.
   Inventory any separately supplied importer branch before choosing a fallback.
2. Add the core behind a default-off Wald pilot boundary. No automatic routing of existing
   production source work into new interpretation. Do not install SiteApp runtime pieces.
3. Establish known-answer neutral records from owner-reviewed synthetic/approved workbooks.
   Run the current DTO importer and the Wald-derived DTO path against **separate disposable
   databases starting from identical fixtures**. There is no existing workbook parser here
   to serve as an oracle; record that limitation. Never fabricate a comparison result.
4. Compare per scope/Call No./plot/service: identities, raw/canonical codes, quantities,
   missing versus zero, completion dates/states, operational versus requested/agreed dates,
   warnings/issues, neutral lineage, resulting projections and preserved Portal history.
   Differences are reviewed, not automatically accepted because Wald is newer. Any approved
   baseline correction gets a focused regression and explicit decision.
5. Require zero unexplained domain/provenance differences on the approved corpus, correct
   abstention on unknown inputs, scope/security tests and CustomerApp MySQL rehearsals.
   Layout differences alone may change coordinates, not agreed business output.
6. Supervised pilot: explicit authorised source family/site scope, one production writer,
   reviewed preview/commit and retained evidence. Keep analysis/comparison separate from
   commit; no silent dual-write shadow mode or automatic scheduled publication.
7. Retire only identified duplicate **inference**, after parity/pilot and separate approval.
   Keep Portal domain projection/negotiation protections. No duplicate inference is currently
   identified, so no deletion/retirement task is authorised now.

Fallback in this checkout is the retained internal validated-record projection path. A
ready-made manual spreadsheet UI exists only on the non-main feature line and is not an
approved production fallback. CUSTOMER-WALD05 must decide whether any of its Portal-specific
controls are adapted. Do not bypass review/permissions via arbitrary console writes; if no
safe accepted fallback is available, pause imports while the rest of the Portal runs.

Rollback disables new pilot work, safely drains/stops its own worker, preserves sources,
analyses, answers, profiles, staging and commit lineage, and uses a rehearsed compatible
release. Older code must not commit Wald sessions without checking their pins. Do not drop
populated audit tables or attempt to reverse business effects by deleting a run. Already
committed source changes require a separate reviewed corrective revision with audit.

## 14. Reconciled decisions and remaining implementation gates

This register distinguishes resolved semantic decisions from the remaining gates for the
affected feature/pilot. None is a reason to rebuild the generic core.

| ID | Decision required | Safe state until resolved |
| --- | --- | --- |
| CW-D01 — resolved | `complete = Yes` completes that specific source call-off part; sensible variants are case-insensitive and no date is invented. Older stage/date codes are not required without later confirmation. | Version the rule and test `No`, unknown and reversal paths; preserve raw evidence. |
| CW-D02 — resolved semantics | The Windows/Doors formulae and included/excluded codes in §6 are authoritative customer roll-ups. | Implement typed validation and deterministic duplicate/conflict handling without inventing absent values. |
| CW-D03 — resolved | Exact positive `BF` means Bifold, remains internally identifiable and changes the normal earliest request from four to five weeks. | Exact-code tests only; no substring inference. |
| CW-D04 | Source owner/operator, stable source-family/revision identity, stale-revision handling and multirow Call No. semantics. Export scope is now resolved: safe default partial/filtered; stronger scope explicit only. | No automatic absence/zeroing, identity synthesis or live source commit on an unclear revision contract. |
| CW-D05 | Portal import/evidence/review/commit abilities; who can activate reusable knowledge and approve business dictionaries. | No new production access granted; Office-only model is proposed. |
| CW-D06 | Raw sources, analysis artifacts, answers, review/commit audit retention and holds. | No destructive purge policy invented; production enablement gated. |
| CW-D07 | Reviewed commit unit and recovery: proposed whole-reviewed-set atomic commit for the bounded pilot versus explicitly approved per-record partial application. Existing importer is per-record. | No claim of whole-import atomicity and no exposed commit until settled/tested. |
| CW-D08 | Which nonessential source fields, if any, are customer-safe to publish (Notes, refs, operational dates). | Private evidence only; current customer allowlist unchanged. |

Existing unrelated decisions (CML expansion, amendment reasons, holiday provider and wider
release work) remain with their owners. This package does not implement Sprint 3F or amend
the released date-negotiation workflow. The exact upstream portable revision also requires
technical owner sign-off because the current reference is uncommitted.

## 15. Implementation packages and dependency order

All packages below are **planned**, not implemented by CUSTOMER-WALD01. Each requires a
scoped implementation instruction, review and evidence; this document is not deployment
approval. Keep the existing Sprint 3 programme visible rather than renumbering it silently.

| Package | Bounded deliverable | Entry / exit gate |
| --- | --- | --- |
| CUSTOMER-WALD02 — Portable core + corpus | Freeze approved baseline; port reader/profiler/reasoning/diagnostic contracts with compatible tests and deterministic synthetic fixtures. No UI/domain writes or SiteApp persistence. | Architecture accepted; exact source manifest approved. Exit: standalone pure/boundary/security tests and version parity. |
| CUSTOMER-WALD03 — Portal semantics/dictionaries | Portal schema/role registry, approved dictionaries, full-column call-record validation and domain-adapter contract; customer-specific fixtures and unresolved-code handling. | 02 contracts stable. Exit: the reconciled call/completion/product/BF/field/scope map is correct; unknowns block dependent use; no SiteApp reference imports. |
| CUSTOMER-WALD04 — Clarification/profiles | Portal-owned additive session/source/run/answer/profile/knowledge storage; scoped access; correction/revocation; adaptive matches and explicit activation. Contract-first staging/commit schema design. | 02–03; CW-D05 before usable access. Exit: isolation, tampering, stale/idempotent actions, immutable history and SQLite/MySQL migration plan; no commit path yet. |
| CUSTOMER-WALD05 — Import/review integration | Private upload, durable queued orchestration/recovery, browser resumption, neutral staging, review/diff/preview and guarded Portal commit. Retained fallback and disposable parity harness. | 04; CW-D04/05/07 resolved and complete source/provenance contract. Exit: full synthetic end-to-end milestone below, preservation tests, real MySQL gate and no SiteApp dependency. |
| CUSTOMER-WALD06 — Pilot/hardening | Held-out ugly workbooks, supervised source-family pilot, scale/security/concurrency/accessibility/device tests, queue/storage/retention/backup/rollback rehearsal and evidence-led corrections. | 05 plus all applicable CW-D gates and separate pilot approval. Exit: zero unexplained parity differences, safe abstention and signed operational readiness; any retirement separately approved. |

First working milestone is the **integrated CUSTOMER-WALD05 exit**, not reader-only
completion: XLSX/CSV upload → non-template/moved/merged layout → Plot/code/quantity/status/
date candidates → targeted question → explicitly remembered answer → changed-family
recognition → neutral staging → human review → controlled commit, entirely without SiteApp.
“Arbitrary layout” means supported bounded variable layouts plus honest clarification/
refusal, not a promise to understand every workbook. Unconfirmed optional domain meanings
may remain unused, but required mappings cannot be skipped into commit.

### Mandatory independence and release acceptance

1. In isolated CustomerApp test infrastructure with no SiteApp checkout/mount/database/
   credentials, run the entire milestone and repeated-family import. Deny SiteApp hosts
   and record/assert no attempted HTTP, SQL, filesystem or queue access to SiteApp.
   Do not shut down or alter the real SiteApp to prove independence.
2. Dependency/static checks reject SiteApp domain/policy/admin imports in the portable
   and Portal adapter layers. No external-AI call/fallback. All new routes deny the
   negative-role/inactive/revoked/preview/cross-scope cases.
3. Same pinned source/versions/scope/answers reproduce semantic output; changed dictionary,
   source or profile yields a successor or conflict, never silent historical mutation.
4. Golden and held-out/mutated corpus proves both correct mappings and safe refusals;
   selected scope confirmation, authoritative coverage and customer-date preservation are
   release blockers.
5. Jobs survive browser departure and worker recovery without duplicate staging/commit;
   stale/revoked operations and failed persistence cannot appear successful.
6. SQLite and disposable representative MySQL fresh/upgrade/rollback-preservation,
   uniqueness/JSON/locking/concurrency checks pass. No production reset, repaired migration
   replacement or destructive audit-table rollback.
7. Private storage/worker/scheduler/monitoring, retention and coherent backup/restore are
   tested in the actual target environment before pilot enablement.
8. CustomerApp changes leave SiteApp source untouched. Any generic backport is a separate
   reviewed/tested task under the [divergence register](../wald-divergence-register.md).

## 16. Handoff and verification of this package

Architecture boundary documented; no production application code, migrations, tests,
routes, environment, lockfiles or customer data changed. No source copying, application
tests, benchmarks, migration, commit, push, production access or deployment performed.
Verification for this task is document consistency, relative-link/file checks and Git
diff/whitespace review. Implementation results must be added by the package that actually
runs them; upstream results must remain clearly attributed.
