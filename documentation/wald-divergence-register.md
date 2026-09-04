# Wald Distribution Divergence Register

Date: 4 September 2026. Owner: Product and Architecture.
Status: CUSTOMER-WALD01A reconciled; planned fork boundaries; no CustomerApp Wald code
copied yet.

Authority: DEC-039/DEC-040 and
[CUSTOMER-WALD01](work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md).
This register compares the inspected SiteApp implementation with the proposed CustomerApp
distribution. A planned difference is not an implemented feature or an upstream change.

## Baseline and version identity

SiteApp inspected HEAD: `73caab7bc76fce6a43808b870827a25cd4520549`. The WALD01–07 source
and integration changes are uncommitted/untracked in that working tree. **That hash alone
does not contain the reference implementation.** CUSTOMER-WALD02 must record an approved
immutable source commit or explicit path/SHA-256 manifest including required integration
hooks/tests before copying. Never harvest unrelated dirty files. No SiteApp edits here.

CustomerApp audit HEAD: `27936f15d1bef756225dedfff1dd23e659ba5b5a`.
CustomerApp fork commit/manifest: **not yet created**.

Non-main CustomerApp evidence: committed feature chain
`feature/manual-source-import-backend` (`04b560f`) →
`feature/deterministic-spreadsheet-interpreter` (`a013ed1`) →
`feature/manual-source-import-ui` (`1e8c22b`). It contains a deterministic reader,
profiles, source-site bindings, explicit scopes, preview/commit controls and Office UI.
It is not in `main`, is not the approved Wald source baseline and was inspected read-only.

Observed code versions (not just initial WALD02 labels):

| Component | SiteApp inspected value | CustomerApp rule |
| --- | --- | --- |
| Workbook profiler | `wald-0.2.0` | Retain when behaviour identical. |
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
| WD-01 | Temporary fork difference | Two in-repository copies/deployments instead of a shared installed package. No automatic code/profile sync. | Planned; 02 records baseline, Product reviews future extraction only when worthwhile. |
| WD-02 | SiteApp-specific | Trade/WorkflowStage reference queries, operational models, stage-schedule schema, Import Studio review/commit, role policies and Filament resources. | Exclude; 02–05 dependency-boundary tests. |
| WD-03 | CustomerApp-specific | Portal customer/site scope, four existing roles, nullable organisation for global Office Staff, separate import abilities still to confirm. | Planned; 04/05 auth and scope tests; never copy Administrator/MD split. |
| WD-04 | CustomerApp-specific | Portal call-record grain/Call No.; four customer services; valid PC1/CC1/CM1/CM2/CML meanings; literal CC! refusal; `complete=Yes` part completion; customer-date/history precedence. | Reconciled contract; 03/05 domain parity and safe-unknown tests. No Snagging source code is invented. |
| WD-05 | CustomerApp-specific | New Portal-owned import session/source/profile/staging/commit records; no existing `ConstructionImport*` stores to extend. | Planned; 04/05 schema review, SQLite/MySQL preservation tests. |
| WD-06 | CustomerApp-specific | Source revision/coverage and partial-snapshot-safe reconciliation before using current Portal projection importer. | Required integration gap; 05 test other sites/products are untouched outside reviewed coverage. |
| WD-07 | CustomerApp-specific | Product/Call answers cannot silently create authoritative business definitions; proposal/owner approval kept separate. | Planned; 03/04 dictionary conflict and permission tests. |
| WD-08 | CustomerApp-specific | Portal wording/layout/URLs, private storage/configuration and own worker/recovery command. | Planned; 05 resumption/access tests, 06 environment rehearsal. |
| WD-09 | Generic improvement candidate for both | Make existing schema/registry/dictionary and persistence boundaries injectable where needed, preserving deterministic algorithms. | Candidate, not a package-extraction commitment. 02–04 review each seam; backport only separately approved. |
| WD-10 | Generic improvement candidate for both | Any later merged-header, bounds, evidence, diagnostics or match correction proven by a failing generic fixture. | No new correction made here. Attach reproducer/version change before proposing a backport. |
| WD-11 | Temporary fork difference | Initial verification corpus/environment differs; SiteApp's reported tests and outstanding MySQL/preview gates are not Portal results. | 02–06 record actual local/target evidence; never copy passing totals as certification. |
| WD-12 | CustomerApp-specific | The audit checkout retains the internal DTO-import path; a workbook UI exists only on the non-main `feature/manual-source-import-ui` line and is not an accepted production fallback. | 05 must decide what Portal safeguards/UI concepts to adapt or explicitly defer; safe pause remains valid. |
| WD-13 | Generic improvement candidate for both | Non-main CustomerApp deterministic XLSX inspection/header/value profiling and structure-equivalent regression fixtures may expose generic corrections useful to either distribution. | A-class candidate only. CUSTOMER-WALD02 compares against the approved SiteApp baseline file-by-file; no wholesale cherry-pick. |
| WD-14 | CustomerApp-specific | Non-main source-site bindings, Call No. identity, explicit `PARTIAL_FILTERED_EXPORT`/stronger-scope confirmation, stale-preview checks, Portal projection reconciliation and Office review safeguards. | B-class downstream evidence for 04/05. Rebuild/adapt against final Wald staging and confirmed permissions. |
| WD-15 | Temporary fork difference | Non-main direct XLSX/manual interpretation and simplified Eloquent profile pipeline overlaps the planned Wald analysis/clarification/profile route. | C-class supersession candidate. Keep for comparison until 05 parity; no deletion or production adoption authorised. |
| WD-16 | CustomerApp-specific | Customer product output is Total Windows and Total Doors; exact BF remains private/internal enough to calculate the five-week rule; CAS/FLU/PFD/GLS/WP/MISC are excluded from the final customer product model. | Reconciled Portal dictionary for 03. Raw source values may remain private evidence. |
| WD-17 | CustomerApp-specific | `Items Ordered Status` and `Site Value` are ignored; `Plot To Be Installed` is PC1 operational arrival-to-install evidence and never a requested/agreed/alternative date. | Reconciled adapter contract for 03/05 with negative customer-date/status tests. |
| WD-18 | CustomerApp-specific | Durable site identity will use the future source Site ID/reference; exact Site Name is transitional binding evidence only. | Delivery dependency for 05/pilot; never infer tenant/site or freeze Site Name as permanent identity. |

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
