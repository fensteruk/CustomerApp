# Wald Distribution Divergence Register

Last updated: 22 September 2026. Owner: Product and Architecture.

Current CustomerApp-only difference: WD-60 records DEC-071's current-scope
exclusions, including temporary P04 treatment. WD-59/58 retain the earlier
exact exclusions. These remain feature-branch only; no SiteApp backport or
production release is implied.

Earlier correction: W5Q-03/04 at `dbd17c68a04c028418e2d8a08fc43312aae5fe3b` adds CustomerApp-only
durable attempt audit and operation-local batch profile eligibility. Backend application identity
is v3; projection and transient digest remain v2, preserving W5Q-01/02 and synthetic-fixture
W5Q-05. Older staged application-v2 manifests become stale. Generic Wald and dictionary meaning
remain unchanged. Fresh backend acceptance passed on the local QA branch; no release or SiteApp
backport. See [dedicated QA](wald/customer-wald05-backend-qa-2026-09-09.md).

Historical foundation entry: DEC-052's W5-T01 correction is implemented separately at
`e9e1c3a2a3ff79cfe5f097bea143f51195becd55`: reader `wald-0.2.2`, XLSX adapter 3, CSV 2.
Exact extension namespace/lineage handling preserves core metadata and XML safety guards;
old knowledge pins become stale. The unchanged workbook now profiles successfully. DEC-050/051
select 45 records, excluding CM2 and the exact Nick TEST record, without business-data ambiguity.
The default-off Office binding/version/audit foundation is SQLite/MySQL tested; full import is
still unimplemented. DEC-049/052 and I01–I10 authorise continued implementation, with no new
blanket approval gate. No SiteApp backport, dedicated-QA acceptance or production change.
See [implementation entry report](wald/customer-wald05-import-review-integration-2026-09-08.md).
The earlier implementation-instruction gate below is satisfied; historical status is superseded.

Status: corrected WALD03 snapshot `a80ce7d14206cf3f3a9343448d406f01ae927b88`
on `qa/customer-wald03-2026-09-08` accepted as immutable WALD04 input, including executable
corrections `f4fda0f`. Original implementation candidate `574f196` is not the accepted output.
Dictionary v1 fingerprint remains
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.
WALD04 corrected output `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`, including executable
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`, is accepted as immutable WALD05 input.
[Acceptance/scope evidence](wald/customer-wald04-acceptance-wald05-scope-2026-09-08.md).
DEC-048 approves WALD05 I01–I10 governance, including temporary manual source ordering and
one-visit Call No. grain. DEC-049 subsequently supplies implementation authority and DEC-052
approves the reader correction. G09 owner nomination still gates unattended disposal. No main,
push or deployment. Original scope-stage rows below are superseded by WD-38–46 as applicable.

Authority: DEC-039/DEC-040/DEC-042/DEC-043/DEC-044/DEC-045/DEC-046/DEC-047/DEC-048/DEC-049/DEC-050/DEC-051/DEC-052 and
[CUSTOMER-WALD01](work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md).
This register compares the inspected SiteApp implementation with the proposed CustomerApp
distribution. A planned difference is not an implemented feature or an upstream change.

## Baseline and version identity

The approved SiteApp source is the 162-file manifest at
`C:\Users\JoshO\Documents\SiteApp\documentation\wald\siteapp-wald-source-baseline-2026-09-04.md`,
with content digest
`76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a` and
SiteApp HEAD-at-capture `4e2955b489e386a2a5b664bd88e72d9b5793ead9`. The HEAD alone does
not contain Wald; the manifest's per-file checksums control. CUSTOMER-WALD02 revalidated all
162 entries with zero mismatches before adoption. No SiteApp edit occurred.

CustomerApp documentation baseline: `e84999cf66fc90aac3007842a538672b018b3e03`.
CustomerApp branch: `feature/customer-wald02-portable-core`; work package `243beb8`, portable
core `d1c130a`, corpus/boundary tests `5ddaa26`. The complete adoption ledger and evidence are
in `documentation/wald/customer-wald02-portable-core-2026-09-04.md`.

Accepted CustomerApp WALD02 output: QA branch `qa/customer-wald02-2026-09-08`, commit
`4aa5ffb5a00527662ddfe66673edbfb18af9f0db`, reader `wald-0.2.1`, adapter versions `2`.
Original candidate `9980354` is superseded as a downstream baseline.

Non-main CustomerApp evidence: committed feature chain
`feature/manual-source-import-backend` (`04b560f`) →
`feature/deterministic-spreadsheet-interpreter` (`a013ed1`) →
`feature/manual-source-import-ui` (`1e8c22b`). It contains a deterministic reader,
profiles, source-site bindings, explicit scopes, preview/commit controls and Office UI.
It is not in `main`, is not the approved Wald source baseline and was inspected read-only.

Observed code versions (not just initial WALD02 labels):

| Component | SiteApp inspected value | CustomerApp rule |
| --- | --- | --- |
| Workbook profiler | `wald-0.2.0` | Accepted input reader `wald-0.2.1` includes WD-21–23 QA safety corrections; DEC-052 correction is now `wald-0.2.2` / XLSX 3 (WD-44), not yet dedicated-QA accepted. |
| Structural rules | `wald.structure.v1.1` | Includes WALD06 Notes-first fix; do not revert to v1. |
| Reasoning / ruleset | `wald-0.3.0` / `wald.generic-rules.v1` | Preserve coherent later context/provenance hooks. |
| Confidence policy | `wald.confidence.v1` | Same caps, thresholds, ties and human-confirmed safeguards unless separately reviewed/versioned. |
| Semantic engine / role registry | `wald.construction-semantics.v1.1` / `wald.construction-roles.v1` | Portal semantics/registry need an explicit new version identity; no false compatibility claim. |
| Terminology / full validation | `wald.terminology-normalisation.v1` / `wald.full-column-validation.v1` | Retain compatible algorithms; version changed call-record validation semantics. |
| Clarification / questions | `wald.clarification.v1.1` / `wald.questions.v1` | Preserve lifecycle; version Portal wording, requiredness and authority changes. |
| Structural selector | `wald.selector.v1` | Preserve compatible coordinate-free matching. |
| Adaptive signature / matcher | `wald.adaptive-signature.v1` / `wald.profile-matcher.v1` | Preserve compatible algorithms and record any threshold change. |
| Neutral source | `wald.source.v1` | Add an explicitly versioned Portal call-record contract, not silent reuse of Plot-stage payload semantics. |
| Distribution | No separate value verified | Record `wald_distribution = customer-app` in future run diagnostics/manifests. |

Every future manifest records the upstream baseline, CustomerApp build/commit, distribution,
reader/capabilities, component/rule/schema versions, source SHA, dictionary snapshots,
profile/version, answer chain and normalisation settings. Different local database IDs,
run UUIDs and execution times are not semantic parity differences. Retain exact pinned
inputs; report replay unavailable when the original/version is unavailable.

## Divergence ledger

Categories are deliberately explicit: **Generic improvement candidate for both**,
**SiteApp-specific**, **CustomerApp-specific**, **Temporary fork difference**.

| ID | Category | Difference / treatment | Status and verification owner |
| --- | --- | --- | --- |
| WD-01 | Temporary fork difference | Two in-repository copies/deployments instead of a shared installed package. No automatic code/profile sync. | Implemented for the CustomerApp portable core at `d1c130a`; no package created. Product reviews future extraction only when worthwhile. |
| WD-02 | SiteApp-specific | Trade/WorkflowStage reference queries, operational models, stage-schedule schema, Import Studio review/commit, role policies and Filament resources. | Exclude; 02–05 dependency-boundary tests. |
| WD-03 | CustomerApp-specific | Portal customer/site scope, four existing roles, nullable organisation for global Office Staff, separate import abilities still to confirm. | Planned; 04/05 auth and scope tests; never copy Administrator/MD split. |
| WD-04 | CustomerApp-specific | Portal call-record grain/Call No.; four customer services; valid PC1/CC1/CM1/CM2/CML meanings; literal CC! refusal; `complete=Yes` part completion; customer-date/history precedence. | Reconciled contract; 03/05 domain parity and safe-unknown tests. No Snagging source code is invented. |
| WD-05 | CustomerApp-specific | New Portal-owned import session/source/profile/staging/commit records; no existing `ConstructionImport*` stores to extend. | Planned; 04/05 schema review, SQLite/MySQL preservation tests. |
| WD-06 | CustomerApp-specific | Source revision/coverage and partial-snapshot-safe reconciliation before using current Portal projection importer. | Required integration gap; 05 test other sites/products are untouched outside reviewed coverage. |
| WD-07 | CustomerApp-specific | Product/Call answers cannot silently create authoritative business definitions; proposal/owner approval kept separate. | Planned; 03/04 dictionary conflict and permission tests. |
| WD-08 | CustomerApp-specific | Portal wording/layout/URLs, private storage/configuration and own worker/recovery command. | Planned; 05 resumption/access tests, 06 environment rehearsal. |
| WD-09 | Generic improvement candidate for both | Make existing schema/registry/dictionary and persistence boundaries injectable where needed, preserving deterministic algorithms. | Candidate, not a package-extraction commitment. 02–04 review each seam; backport only separately approved. |
| WD-10 | Generic improvement candidate for both | Any later merged-header, bounds, evidence, diagnostics or match correction proven by a failing generic fixture. | No new correction made here. Attach reproducer/version change before proposing a backport. |
| WD-11 | Temporary fork difference | Initial verification corpus/environment differs; SiteApp's reported tests and outstanding MySQL/preview gates are not Portal results. | Accepted WALD02 evidence: focused 208 tests/1,262 assertions; full CustomerApp 427 passes, 15 environment skips/2,450 assertions. Later phases record their own evidence. |
| WD-12 | CustomerApp-specific | The audit checkout retains the internal DTO-import path; a workbook UI exists only on the non-main `feature/manual-source-import-ui` line and is not an accepted production fallback. | 05 must decide what Portal safeguards/UI concepts to adapt or explicitly defer; safe pause remains valid. |
| WD-13 | Generic improvement candidate for both | Non-main CustomerApp deterministic XLSX inspection/header/value profiling and structure-equivalent regression fixtures may expose generic corrections useful to either distribution. | Compared in WALD02. The approved baseline supplies the broader generic core; no non-main runtime was merged or generic correction was required. |
| WD-14 | CustomerApp-specific | Non-main source-site bindings, Call No. identity, explicit `PARTIAL_FILTERED_EXPORT`/stronger-scope confirmation, stale-preview checks, Portal projection reconciliation and Office review safeguards. | B-class downstream evidence for 04/05. Rebuild/adapt against final Wald staging and confirmed permissions. |
| WD-15 | Temporary fork difference | Non-main direct XLSX/manual interpretation and simplified Eloquent profile pipeline overlaps the planned Wald analysis/clarification/profile route. | C-class supersession candidate. Keep for comparison until 05 parity; no deletion or production adoption authorised. |
| WD-16 | CustomerApp-specific | Customer product output is Total Windows and Total Doors; exact BF remains private/internal enough to calculate the five-week rule; CAS/FLU/PFD/GLS/WP/MISC are excluded from the final customer product model. | Reconciled Portal dictionary for 03. Raw source values may remain private evidence. |
| WD-17 | CustomerApp-specific | `Items Ordered Status` and `Site Value` are ignored; `Plot To Be Installed` is PC1 operational arrival-to-install evidence and never a requested/agreed/alternative date. | Reconciled adapter contract for 03/05 with negative customer-date/status tests. |
| WD-18 | CustomerApp-specific | Durable site identity will use the future source Site ID/reference; exact Site Name is transitional binding evidence only. | Delivery dependency for 05/pilot; never infer tenant/site or freeze Site Name as permanent identity. |
| WD-19 | Temporary fork difference | Portable files live under CustomerApp-owned `App\Wald` rather than SiteApp's root Wald namespaces. | Namespace/path relocation only. All 52 runtime and five adopted test/support files are `IDENTICAL` after inverse relocation; no component version change. |
| WD-20 | CustomerApp-specific | Safe synthetic boundary coverage adds the canonical `House No.` versus `Sales Plot` ambiguity and literal unknown-value preservation. | Three CustomerApp-owned tests at `5ddaa26`; no core change. Both identifier candidates require clarification, replay is identical and no `CC!` correction or Portal date meaning is invented. |

## Dedicated QA additions — 8 September 2026

The original `9980354` candidate remains 57/57 identical after inverse namespace/EOL
normalisation. The corrected QA branch has 54 identical and three intentionally diverged
adopted files. All 162 original source hashes still match. The following entries supersede
WD-19's no-version-change claim for the corrected candidate only; SiteApp is unchanged.

| ID | Category | Difference / treatment | Status and verification owner |
|---|---|---|---|
| WD-21 | Generic improvement candidate for both | `app/Wald/Services/XlsxWorkbookSource.php`: WQ-01/02/03/05 reject invalid node ancestry, duplicate relationship IDs, wrong XML roots and empty XML with safe errors. | Implemented and regression-tested by QA; XLSX adapter version 2. Separate SiteApp backport approval required. |
| WD-22 | Generic improvement candidate for both | `app/Wald/Services/CsvWorkbookSource.php`: WQ-04 preflights native CSV allocation before delimiter sniffing/record parsing. Uses existing memory budget and conservatively includes quoted separators. | Implemented; CSV adapter version 2; 2 MB hostile row safely rejected in 64 MiB subprocess. Separate backport approval required. |
| WD-23 | Temporary fork difference | `app/Wald/Services/WorkbookProfiler.php` reader version is `wald-0.2.1` to identify changed refusal behaviour. Structure, reasoning, rules and confidence remain unchanged. | Implemented; 208 focused tests / 1,262 assertions; 427 full-suite passes, 15 environment skips / 2,450 assertions. |

QA added `CustomerWaldDedicatedQaTest.php` and `scripts/verify-wald02-qa.php` (synthetic
corpus/integrity/standalone replay/benchmark only), and anchored one pre-existing Sprint 3D
notification fixture's clock. These are not adopted upstream runtime files. No generic fix
has been propagated to SiteApp and no Portal business-semantic change was made.

## CUSTOMER-WALD03 implemented differences — 8 September 2026

| ID | Category | Difference / treatment | Status and verification owner |
|---|---|---|---|
| WD-24 | CustomerApp-specific | Business meanings live under `App\SourceImport\Semantics`; core candidates/evidence are composed, not mutated. | Implemented at `9648f0d`/`e65fa2e`; no diff in `app/Wald` from accepted `4aa5ffb`. Dedicated QA pending. |
| WD-25 | Temporary fork difference | Identity `customerapp.source-dictionary.v1`, fingerprint `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`; numeric `semantic_version = 3` is not compatible identity. | Implemented, golden identity/version-change tests at `1fee57d`. Every semantic leaf retains dictionary and accepted core identity; persistence remains WALD04+. |
| WD-26 | CustomerApp-specific | Classification (`CONFIRMED`, `UNKNOWN`, `AMBIGUOUS`, `INVALID`, `IGNORED`) is separate from resolution (`RESOLVED`, `REQUIRES_CONFIRMATION`, `BLOCKED`). | Implemented. 344 focused tests / 1,707 assertions cover raw values, clarification, provenance and fail-closed composition; staging readiness always false. |
| WD-27 | Temporary fork difference | Non-main dictionary/config intent is reimplemented purely; direct mapper and fixed workbook contract are superseded; semantic test intent is reused. | Completed bounded read-only comparison of `04b560f` → `a013ed1` → `1e8c22b`. No branch merge, copy or deletion; classifications below. |
| WD-28 | CustomerApp-specific | Quantity normalization is fixed-point decimal(12,3); invalid/unknown/duplicate/overflow input suppresses both totals. BF is only an observed exact-code positive fact; no absence or lead-time authority. | Implemented; full product/quantity/BF matrix. Supplied-input rollups are dictionary primitives, not approval to stage rows. |
| WD-29 | CustomerApp-specific | Semantic adapter checks source checksum, sheet, range, header/data-row distinction, target leader, manifest and confirmation state; formula caches/errors remain blocked. | Implemented with typed private observations. Real House No./Sales Plot ambiguity retains both core candidates; no tie-breaker or core change. |

### WALD03 non-main import classification

| Material | Classification | Implemented treatment |
|---|---|---|
| Synthetic semantic cases from `SiteAppImportSemanticCorrectionTest` | REUSE | Test intent recreated in pure WALD03 fixtures, not copied with DB/workflow dependencies. |
| `SiteAppImportDataDictionary`, approved `config/siteapp_import.php` definitions and scope concepts | REIMPLEMENT | New immutable dictionary, fixed-point rollups, typed scope and canonical identity; no config/container or Portal enum coupling. |
| `SourceCallTypeMapper`, `ManualSourceWorkbookContract`, fixed worksheet/header selection | SUPERSEDED | No historical Job Stage completion rules or direct mapper adopted. Exact field terminology is downstream of Wald evidence. |
| `SpreadsheetStructureInterpreter`, profile/site-binding services, previews, projection import and Office UI | REFERENCE_ONLY | Read-only comparison material; WALD04/05 must explicitly adapt/supersede/defer. No runtime adoption or deletion. |

Implementation report: [WALD03 evidence](wald/customer-wald03-business-dictionary-2026-09-08.md).
Combined Wald tests: 552 passes / 2,969 assertions. Full CustomerApp: 771 passes / 15 existing
environment-gated skips / 4,157 assertions. No SiteApp, dependency or production change.

## WALD03 dedicated QA corrections — 8 September 2026

These results supersede the implementation-stage "QA pending" and test counts above.
See [WALD03 QA](wald/customer-wald03-qa-2026-09-08.md). The 52-file `app/Wald` tree remains
exactly `30d1fc65e575242004eb335ad46a4d8ec920127a`, identical to accepted WALD02.

| ID | Category | Difference / treatment | Verification |
|---|---|---|---|
| WD-30 | CustomerApp-specific | W3Q-01: validate finite float quantities through an exact three-place round trip, not global-precision-dependent string casts. | Excess precision is rejected, not rounded; fixed-point totals and dictionary v1 definitions/fingerprint unchanged. |
| WD-31 | CustomerApp-specific | W3Q-02: reject missing/changed confidence-policy version at the semantic adapter boundary. | Checks pinned `wald.confidence.v1` even when the supplied manifest hash is internally valid. No generic-core change. |
| WD-32 | CustomerApp-specific | W3Q-03: enforce semantic, service-composition and aggregate constructor invariants. | Contradictory resolution/value, completion/service and totals/BF states throw bounded exceptions. No persistence or business-rule expansion. |

Corrected executable `f4fda0f`: 470 focused passes / 2,550 assertions; 678 combined Wald
passes / 3,812; 897 full application passes / 15 existing environment skips / 5,000.
These corrections are local CustomerApp adapter changes, not an upstream backport.

## WALD04 scoped differences — 8 September 2026

These are design proposals, not implemented features or approved role grants. The governing
[WALD04 work package](work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md) includes G01–G09
approval questions and the actual non-main file comparison. Accepted WALD03 QA results remain
470 focused / 2,550 assertions; 678 combined / 3,812; 897 full passes, 15 skips / 5,000.

| ID | Category | Proposed difference / treatment | State |
|---|---|---|---|
| WD-33 | CustomerApp-specific | Organisation-owned knowledge narrowed to site + source namespace + family; no learned SYSTEM/global scope. Nullable Office organisation does not imply global knowledge ownership. | Proposed; G01/G03 permission/scope approval required. |
| WD-34 | CustomerApp-specific | Separate one-time clarification, immutable structural profile versions and explicit activation/revocation. Semantic corrections occurrence-only; dictionary never overridden. | Proposed; G02/G06/G08 approval required. Source-site binding remains separate/deferred WALD05. |
| WD-35 | Temporary fork difference | Old numeric semantic_version, namespace-only matching, first/score winner and save-on-confirmation are not the WALD04 target. Use dictionary pair, component versions, supported corrected builds, current evidence and scoped compatibility. | REIMPLEMENT compatible intent; unsafe/overlapping behavior SUPERSEDED. No code or data imported. |
| WD-36 | CustomerApp-specific | Minimal knowledge context/questions/answers/profiles/versions/events/use receipts and separately retained evidence; no full upload/session/preview/commit persistence yet. | Proposed additive schema only; governance, transaction and MySQL gates before implementation. |
| WD-37 | CustomerApp-specific | Explicit temporary artifact versus long-lived knowledge/audit retention, protected active provenance, expiry/hold/revocation receipts and immutable correction history. | Retention periods and hold ownership proposed only; G04/G05/G09 unresolved. No cleanup executed. |

### WALD04 non-main comparison disposition

Read-only inspection of `04b560f` → `a013ed1` → `1e8c22b` confirms:

- **REUSE:** synthetic exact/reordered/changed-profile, stale semantic version, stale binding,
  partial-scope and idempotency scenario intent.
- **REIMPLEMENT:** `WorkbookInterpretationProfileService` matching/version intent with tenant/site
  containment, activation, immutable audit and current core/dictionary compatibility; separate
  future source binding invariants. Mixed migrations 000009–000012 are not adopted.
- **SUPERSEDED:** numeric `semantic_version = 3` as compatibility identity; namespace-only
  profile search, automatic reusable save during confirmation and score/first-match selection.
- **REFERENCE_ONLY:** source binding runtime, preview/commit service, Office UI and associated
  projection schema; WALD05 owns explicit parity/adapt/supersede decisions.

Existing historical records/code are neither deleted nor silently migrated. No SiteApp source
access/copy, generic-core modification or dependency change in this scope task.

## WALD04 implementation differences — 8 September 2026

| ID | Category | Implemented difference | Evidence |
|---|---|---|---|
| WD-38 | CustomerApp-specific | `App\\SourceImport\\Knowledge` owns Office-only scope, separate one-time answers/drafts/activation, immutable versions and revocation receipts. | DEC-046; focused security/lifecycle tests. Frozen Wald/semantic trees unchanged. |
| WD-39 | CustomerApp-specific | Eight additive tables, composite ownership/version FKs and database-level immutable-history/evidence guards. Canonical JSON preserves numeric type across persistence. | SQLite and disposable MySQL 8.4 schema, rollback, upgrade and race verification in implementation report. |
| WD-40 | Temporary fork difference | CustomerApp signature/matcher/schema v1; explicit structural role selectors, scoped fresh-evidence vetoes and no score/first winner. Metadata-only retention, holds and review expiry. | No SiteApp adoption/backport, no global aliases, no disposal scheduler or WALD05 binding runtime. |
| WD-41 | CustomerApp-specific | Dedicated QA hardens MySQL protected-field equality with a forward binary-comparison trigger migration and bounds profile/provenance/retention queries. Test-only UUID fixtures and memory allowance stabilize expanded gates. | W4Q-01–04, correction `9284bf5`; 198 MySQL tests covered, 140 race groups; frozen generic and semantic trees unchanged. No upstream adoption/backport or new business meaning. |
| WD-42 | CustomerApp-specific | WALD05 proposes CustomerApp-owned artifact/operation/binding/staging/review/commit boundaries around the accepted core. Current legacy projection importer and non-main manual UI are evidence only, not direct commit authority. | DEC-047 and WALD05 scope; no runtime implemented, copied, merged or retired. |
| WD-43 | CustomerApp-specific | WALD05 V1 governance fixes authenticated Office-only import, immutable exact site bindings, manual Export Date/Slot ordering, one Call No. per visit, partial-only scope, complete staging, one atomic reviewed commit, six-year minimal audit and queue-agnostic analysis. | Approved by DEC-048. Implementation still requires a separate instruction; no generic/semantic/knowledge tree or runtime changed. |

Report: [WALD04 implementation](wald/customer-wald04-knowledge-profiles-2026-09-08.md).
No approved production or dedicated-QA acceptance claim is made by this implementation entry.

## WALD05 audit findings — 8 September 2026

| ID | Category | Finding / disposition | Evidence |
|---|---|---|---|
| WD-44 | Candidate for both Wald distributions; IMPLEMENTED IN CUSTOMERAPP ONLY | DEC-052 corrects W5-T01 with exact namespace/ancestor matching for Excel extension workbookPr. Core date-system authority, physical lineage and XML safety remain guarded. Reader wald-0.2.2 / XLSX 3; previous knowledge pins stale. | Origin e9e1c3a2a3ff79cfe5f097bea143f51195becd55; XlsxWorkbookSource, WorkbookProfiler and identity gates; WorkbookExtensionCompatibilityTest has 11 passing synthetic cases. Original artifact now profiles. No SiteApp access/backport. |
| WD-45 | CustomerApp-specific reviewed selection; PURE HELPER IMPLEMENTED, NOT CONNECTED TO IMPORT | ReviewedWorkbookSelection implements DEC-050 CC! correction/CM2 exclusion only for exact approved bytes and DEC-051 exact row 32 / Call No. 5181 / Nick TEST exclusion. | ContractsTest covers changed checksum/tuple refusal and raw evidence preservation. 45 included, two excluded; dictionary unchanged. No global alias or inferred test-site filter. |
| WD-46 | CustomerApp-specific binding foundation; PARTIAL WALD05 | Office-controlled exact source identity, immutable binding versions/command audit, current epoch pins, authenticated replay and default-off policy. One additive migration creates three tables and six immutability guards. | SourceImport/Integration, SourceBindingTest and MysqlFoundationConcurrencyTest; SQLite and disposable MySQL checks pass, including 40 race groups / 80 workers and upgrade guards. No upload/staging/preview/whole-import commit/UI or source-use history yet. |

## WALD05 backend differences — 9 September 2026

The WD-45/46 rows above describe the historical foundation checkpoint. WD-47 now connects that
selection and binding foundation to the bounded backend; no SiteApp adoption/backport occurred.

| ID | Category | Implemented difference | Evidence / identity |
|---|---|---|---|
| WD-47 | CustomerApp-specific integration | Private artifacts, leased durable runs, accepted analysis/clarification, immutable staged rows/reviews, downstream projection adapter, ordering, one-run atomic commit, replacement, observations and receipts. One bound site/table, 500-row bound; unsupported units refuse entirely. | `app/SourceImport/Integration`, additive 2026_09_09_000012 migration; backend application/projection identities v1. Origin is the feature-branch backend commit containing the 9 September completion report. No full UI, old-importer retirement or production operation. |
| WD-48 | CustomerApp-specific adapter correction, W5-T02 | Accepted analysis snapshots previously used the small retained-payload hashing ceiling for complete transient reasoning evidence, rejecting an ordinary synthetic seven-row workbook. `Canonical::evidenceHash` streams identical canonical bytes under a separate 4 MiB ceiling; only snapshot profile/reasoning hashing and bounded staged-rowset hashing use it. Persistent JSON/hash ceilings, matching/activation policy, generic core and dictionary remain unchanged. | `Knowledge/Canonical.php`, `Knowledge/AnalysisSnapshot.php`, BackendSafetyTest digest invariants and pipeline/performance suites. `customerapp.wald-transient-evidence-digest.v1` is pinned in backend identity. No automatic profile migration, semantic override or upstream backport. Dedicated backend QA must review this narrow integration correction. |

Historical implementation verification and old-importer parity disposition:
[backend completion report](wald/customer-wald05-backend-completion-2026-09-09.md).

## WALD05 dedicated QA corrections — 9 September 2026

These CustomerApp integration corrections do not change portable Wald or source meaning.
WD-47/48 retain their original implementation identity; the current identities below supersede it.

| ID | Category | Corrected difference | Evidence / identity |
|---|---|---|---|
| WD-49 | CustomerApp-specific QA correction, W5Q-01/02/05 | Canonical transient digest parity, namespace-wide visit ordering and projection ownership protection; collision-resistant synthetic race owners. | `9f5751f7a6d62b4ae989e34f3894e3b57011a0a8`; projection/digest v2, preserved by fresh correction candidate. Original QA still failed W5Q-03/04. |
| WD-50 | CustomerApp-specific QA correction, W5Q-03/04 | Independent immutable commit intent with one terminal outcome; atomic business/savepoint boundary and bounded retry/recovery. Operation-local batched current profile/version/provenance eligibility replaces repeated per-receipt queries; existing budgets and vetoes remain. | `dbd17c68a04c028418e2d8a08fc43312aae5fe3b`; Integration classes, additive 000013 audit migration and positive CorrectionRequalification/DedicatedBackendQa/MysqlAttemptConcurrency tests. Application v3, projection/digest v2. Fresh gate in linked QA report; no generic backport, full UI, pilot or deployment. |

## Weekend pilot differences — 15 September 2026

| ID | Category | Implemented difference | Evidence / identity |
|---|---|---|---|
| WD-51 | CustomerApp-specific temporary integration | One multi-site parent workbook with manually selected, independently atomic one-site review units. Site-scoped receipt identity permits sibling units to share the parent date/slot without weakening legacy WALD05 global-slot behavior. | CUSTOMER-WALD-PILOT01; additive 000015 migration, PilotImportWorkflow/Discovery, actual-workbook SQLite/MySQL two-site isolation evidence. PILOT_SINGLE_SITE_SELECTION, partial only. |
| WD-52 | CustomerApp-specific operational control | Durable audited wald_import_pilot_enabled setting composed with hard environment gate WALD_IMPORT_AVAILABLE and current Office authority. Both defaults are false; environment OFF wins and denies every action. | CUSTOMER-WALD-PILOT02; WaldPilotAvailability, UpdateWaldPilotSettingAction and focused SQLite/MySQL setting tests. No generic Wald or SiteApp backport candidate. |

## CUSTAPP2 composite/identity differences — 15 September 2026

| ID | Category | Implemented difference | Evidence / identity |
|---|---|---|---|
| WD-53 | Generic improvement candidate for both | Deterministic composition of compatible horizontal table fragments, with physical cell/fragment provenance, genuine-table precedence and ambiguity refusal. Exact CallNo semantic-token recognition replaces punctuation/case-sensitive header matching. | CustomerApp `CompositeTableDetector`, `CallReferenceHeader` and synthetic structure/header tests. Potential SiteApp applicability requires separate review/backport approval; no upstream change occurred. |
| WD-54 | CustomerApp-specific integration | Plot = bound site + normalized Plot Ref; Source Row = namespace + CallNo; Visit = Source Row + recognized non-null Call Type. Blank type may project compatible plot/products without a visit; partial exports never reverse an established visit merely through blank/absence. | DEC-064; additive 000016 migration; identity/transition/security/SQLite/MySQL tests. Backend application/projection and dictionary identities advance; no historical receipt is rewritten. |
| WD-55 | CustomerApp-specific semantics | Per-plot product consolidation treats unrepresented as no assertion, explicit zero exactly, equal facts as agreement and conflicting explicit facts as a blocking site-unit conflict. CUSTAPP2 profile recognizes PC1/CC1/CM1 only and does not inherit checksum-scoped CC!/CM2 behaviour. | Private exact-SHA qualification: eight site units pass, one site unit blocks on genuine conflicting source evidence. PARTIAL; no source repair, production import or customer exposure. |
| WD-56 | CustomerApp-specific integration | RedZebra date/slot is a retained master-export revision family; failed replacement is automatic, non-failed replacement requires exact confirmation, identical hashes deduplicate, and supersession stales uncommitted review. Exact CustomerNo/CustomerCode supplies source namespace + CustomerCode binding identity; Site Name is descriptive evidence. | DEC-066 / CUSTOMER-WALD-SOURCE02; dictionary v3, pilot discovery v3, backend pilot v3, projection v4. Existing schema reused; no SiteApp backport, deployment or enablement. |
| WD-57 | CustomerApp-specific source semantics | Exact `Customer Number` joins `CustomerNo`/`CustomerCode` as one approved `source_customer_code` header. Value identity, binding, missing/ambiguous refusal and one-site review are unchanged. Older knowledge is stale under dictionary v4. | DEC-068; 22 September browser failure `customer_code_missing`, synthetic success/refusal and stale-pin tests. Feature branch only; no SiteApp backport, migration, production re-upload or deployment. |
| WD-58 | CustomerApp-specific source semantics | Exact normalized `CU4` is Customer Care and its rows are excluded from CustomerApp discovery and projection regardless of checksum. Private evidence and exclusion provenance remain. Other unknown codes still block affected site units; dictionary v5 stales older knowledge. | DEC-069; synthetic exclusion and real-file local qualification. Feature branch only; no SiteApp backport, migration, production import or deployment. |
| WD-59 | CustomerApp-specific source semantics | Twenty-five additional exact normalized Call Types are globally irrelevant; rows preserve private provenance but make no CustomerApp projection. Dictionary v6 stales older knowledge. Other unknown codes remain blocking. | DEC-070; synthetic code coverage and both real-file local pilot paths. Feature branch only; no SiteApp backport, migration, production import or deployment. |
| WD-60 | CustomerApp-specific current-scope semantics | CU0/CU1/CU3 Customer Care-related and zzz are currently ignored; P04 is temporarily ignored pending possible future meaning. Exact excluded rows retain private evidence and make no CustomerApp projection. Dictionary v7 stales older knowledge. | DEC-071; synthetic exclusion and local genuine XLS qualification. Feature branch only; no SiteApp backport, migration, production import or deployment. |

## Backport process

1. For each nontrivial fork change, add/update a ledger entry with reason, category, source
   and destination paths, affected versions, originating commit and fixture/reproducer.
2. Keep generic fixes separate from domain mappings, policies and migrations. Label useful
   generic fixes **Candidate for both Wald distributions**; do not propagate automatically.
3. The other application's owner reviews applicability and separately authorises the
   backport. No source/profile/dictionary data exchange is implied by code approval.
4. Port narrowly on that application's authorised branch; run its core corpus, negative
   boundaries, integration regressions and relevant performance/DB checks. Retain safeguards
   rather than weakening assertions to match the source implementation.
5. Record destination commit, test evidence, component-version/compatibility change and
   accepted/deferred/rejected result. Each application deploys independently.
6. At the end of each implementation package, review outstanding temporary differences
   and candidates. Future `wald-core` extraction requires its own dependency/security/
   versioning plan; it must not delay this standalone rollout by assumption.

Knowledge is never merged by commit, filesystem copy or matching profile name. Any future
profile/dictionary export/import requires a separate tenant/privacy, ownership, compatibility
and explicit-activation contract. It is out of scope now.
