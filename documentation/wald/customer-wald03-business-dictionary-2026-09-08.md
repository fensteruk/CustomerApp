# CUSTOMER-WALD03 Business Dictionary Adapter Report

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **READY FOR DEDICATED QA — feature branch only.**
Not accepted for WALD04, integrated, on main, release-approved or deployed.

## Completed and immutable input

Explicit user approval was executed on `feature/customer-wald03-business-dictionary`,
created from exactly `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`.
Reader `wald-0.2.1`; XLSX/CSV adapters remain version 2.
Structure `wald.structure.v1.1`, reasoning `wald-0.3.0`,
rules `wald.generic-rules.v1`, confidence `wald.confidence.v1`.

Documentation reset `e84999cf66fc90aac3007842a538672b018b3e03` is present.
Reviewed scope metadata was carried from `76ed196cd19f2d207f2a5d50006041f1b3d8b814`;
that docs branch was not used as the runtime ancestor or merged. No SiteApp files were copied
or accessed for this phase. No packages were created.

| Audit stage | Commit |
|---|---|
| Approved package and baseline metadata | `544a4cdfafbf37067d1309c4fe225bb23028ff89` |
| Dictionary and result contracts | `9648f0da574741f9eb9fd4cebde7fa90f52eb1be` |
| Semantic adapter | `e65fa2e77b0ba51728f7c0629c32d7e179119890` |
| Synthetic tests and standalone replay; executable candidate | `1fee57de2830fc8a07cb0a14296e72c0ddbb2774` |
| Final evidence/current-status documentation | Commit containing this report; no executable changes after the candidate above. |

The implementation is wholly outside `App\Wald`, under `App\SourceImport\Semantics`.
The frozen generic core, existing tests, routes, application configuration, Portal models,
migrations, dependencies, lockfiles and GitHub Actions have no task changes.

## Dictionary identity

Version: `customerapp.source-dictionary.v1`.

Canonical SHA-256:
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.

Canonicalization recursively sorts associative keys bytewise, preserves list order and uses
unescaped Unicode/slashes and preserved zero-fraction JSON. Definitions include descriptions,
service mapping, exclusion and rollup sets, normalization, limits, scope and typo policy.
Labels are identity-bearing. A changed description, meaning, mapping or normalization requires
a new version and fingerprint; transition validation refuses changed content at the same supplied
previous version. The v1 pair is pinned by a golden regression assertion. Version changes remain
code-reviewed releases, not a dictionary-registration or persistence feature. Numeric legacy
`semantic_version = 3` is not adopted.

## Call types and completion

| Exact normalized code | Description | Service | Revisit |
|---|---|---|---|
| PC1 | Plot Install | windows | false |
| CC1 | Cavity Closer 1 | cavity_closers | false |
| CM1 | Revisit 1 | cml | true |
| CM2 | Revisit 2 | cml | true |
| CML | CML Call Off | cml | false |
| CC! | Invalid / likely typo | none | none |
| ZZ9 or any unknown | Unknown | none | none |

Codes use ASCII case normalization and surrounding whitespace trimming only. Punctuation,
internal whitespace, Unicode lookalikes and raw input are not repaired. No Snagging code exists.
CC! retains its exact raw value, returns INVALID / REQUIRES_CONFIRMATION, and offers only a
CC1 suggestion with LIKELY_TYPO; it has no match or mapped service.

Only trimmed, case-insensitive string Yes/No produces true/false completion flags. Blank,
numeric, Boolean and other text require clarification. A confirmed flag alone is not service
completion. `SemanticAdapter::interpretCall` requires resolved call and completion cells from
the same checksum, sheet, row and region. Unknown/invalid call plus Yes yields no service or
completed value, with BLOCKED resolution. Unknown completion retains a known service but no
completed value. Completion dates are never created; Portal completion transitions are absent.

## Products, quantities, rollups and BF

| Group | Exact codes |
|---|---|
| Total Windows | VS (Vertical Slider), TT (Tilt and Turn), BAY (Bay Window), ALI (Aluminium Windows), AOV (Automatic Opening Vent Window), FI (Fire Window) |
| Total Doors | PSU (PVC Door Utility), PSG (PVC Door Garage), CDF (Composite Door Front), CDU (Composite Door Utility), CDG (Composite Door Garage), PSP (PVC Sliding Patio), BF (Bifold) |
| Excluded | CAS, FLU, PFD, GLS, WP, MISC |

All 13 included descriptions and all six exclusions are covered individually. Unknown products
require clarification and never silently contribute.

Quantity normalization:

- Null, absent code or whitespace-only quantity is zero **inside supplied input only**.
- Non-negative integers, finite plain decimals and trimmed numeric strings are accepted with
  at most three fractional digits; leading zeros are safe for quantity lookup.
- Maximum per value and per group total is 999,999,999.999. Arithmetic uses integer thousandths
  on the verified 64-bit PHP environment; output totals are exact three-place decimal strings.
- Negative, nonnumeric, Boolean, exponent/grouped, over-precision and overflowing values are
  invalid; no rounding or silent correction. Finite floats are accepted only if their PHP
  plain-string representation satisfies the same grammar.
- Every known product quantity, including excluded codes, is validated. Valid exclusions are
  IGNORED and contribute nothing; malformed excluded quantities remain INVALID.
- Unknown/invalid quantities, normalized duplicate product codes or aggregate overflow make
  the rollup unresolved and suppress **both** totals. This avoids exposing a partial sum as final.
- Input keys are sorted for repeatable item ordering. Non-scalar quantities or numeric code
  keys are invalid API input and raise a bounded argument exception.

BF is an observed positive fact only: exact normalized BF with validated quantity greater
than zero. Absent, zero, invalid BF, lookalikes and positive non-BF door totals do not set it.
A valid positive BF observation remains observable even if another item makes aggregate totals
unresolved; neither that fact nor false carries staging or absence authority. A duplicate-code
rollup is always unresolved. No lead-time, earliest-date or working-day calculation exists.

Dictionary rollups are pure operations on one caller-supplied record, not workbook coverage
or structural approval. Consumers must not bypass the adapter's unresolved structural results
by calling dictionary primitives and treating their totals as staging permission.

## Field semantics and export scope

Only exact normalized approved headings are recognized; there are no fuzzy aliases, hardcoded
worksheet names or column positions.

- Items Ordered Status and Site Value: IGNORED; raw evidence stays private.
- Plot To Be Installed: `pc1_operational_install_date` role only, preserving raw/date-shaped
  evidence without conversion to requested/agreed/proposal/completion dates.
- Site Name: `transitional_site_clue`; no binding, lookup, tenancy or authorization.
- Source Site ID / Source Site Reference: `source_site_identity`; durable identity role when
  supplied, not a local key or persistence operation.
- Call No., Call Type and Complete: approved source reference/call/completion roles.

Scope defaults unconditionally to PARTIAL_FILTERED_EXPORT. Typed SITE_COMPLETE_SNAPSHOT and
GLOBAL_COMPLETE_SNAPSHOT may carry explicit caller assertions but remain REQUIRES_CONFIRMATION.
There is no workbook-based promotion, downstream-approval flag, deletion or projection zeroing.

## Semantic and clarification contracts

`SourceBusinessDictionary` defines immutable dictionary operations. `SemanticResult` carries
concept, raw value, normalized lookup, classification, resolution, match/description/service,
suggestions, reasons, private evidence and dictionary/core identities.

Classification: CONFIRMED, UNKNOWN, AMBIGUOUS, INVALID, IGNORED.
Resolution: RESOLVED, REQUIRES_CONFIRMATION, BLOCKED.
All results serialize with `ready_for_staging = false`. Resolved means only that the bounded
semantic fact is resolved, never that source identity, tenancy, coverage or review is approved.

`ObservedCell` wraps a Wald CellObservation with checksum and sheet identity. The adapter
accepts a Wald ReasoningResult and candidate ID; it retains the complete selected candidate,
confidence/evidence, relevant competing candidates, manifest/hash, raw heading and observation
(including style, formula metadata and source references). These are **private internal
contracts, not customer-safe DTOs or authorization tokens**. Trusted callers must supply the
original reader observation with its real checksum; this in-memory adapter does not read bytes,
authenticate submitted payloads or prove their external origin.

The adapter refuses incomplete/wrong-baseline manifests, missing/duplicate candidates,
checksum/sheet/column/range mismatch, missing header evidence, header rows, non-leading
candidates, unresolved core decisions, confirmation flags, cell errors and formula caches.
Dictionary matches may remain as tentative explanatory evidence, but effective value is null
when the structural boundary blocks. No code supplies a user-confirmation bypass. Unknown
required meanings return typed clarification requirements; WALD04 owns any later answer flow.

`CallSemantics` carries both leaf results and a service/completion pair only under the
same-record rule. `ProductRollup` carries per-item results, exact nullable totals, BF fact,
resolution/reasons and explicit supplied-input/no-absence-authority flags. Non-finite raw PHP
floats remain preserved in memory and serialize to lossless tagged values because JSON cannot
represent INF/NAN.

## Real structural ambiguity and deterministic portability

The real frozen profiler/reasoner processes a synthetic XLSX with House No. and Sales Plot.
Both identifier candidates still require clarification. The semantic adapter returns
AMBIGUOUS / BLOCKED for each and retains both competitors; dictionary vocabulary chooses neither.

Accepted-state unit fixtures separately isolate semantic composition from core confidence
thresholds. They are labelled synthetic contract fixtures, not claims that every familiar
business heading receives an accepted Wald decision. Reviewable/insufficient core evidence
remains blocked even for known business codes.

`php -n scripts/verify-wald03-semantics.php` runs with its own narrow class loader: no Composer,
Laravel container, authenticated user, models, database drivers, queue, source-site binding,
source workbooks or network. The PHP build has PDO compiled in but **zero available PDO drivers**;
no database connection is attempted. Three fresh processes each repeat UTC, Europe/London and
Pacific/Auckland. All nine result hashes match:

`3169aff4433666b0761436814efaaece713ef424d6282fe14e8adf26e78b46d6`.

No Illuminate/Portal model classes or Composer autoloader are loaded. The runtime boundary scan
covers all 13 new PHP files; deterministic operations use no clock, external I/O or external AI.

## Existing non-main import/reference work

Inspected read-only chain: `04b560f` → `a013ed1` → `1e8c22b`.

| Classification | Material and treatment |
|---|---|
| REUSE | Synthetic semantic test intent, recreated without DB/container/workflow assertions. |
| REIMPLEMENT | Approved config/dictionary meanings, exact field terminology, rollup and partial-scope concepts expressed as new pure contracts. |
| SUPERSEDED | Direct SourceCallTypeMapper, historical Job Stage completion rules and fixed workbook/header contract; not adopted. |
| REFERENCE_ONLY | Interpreter/profile/site-binding services, previews, projection importer and Office UI; later WALD04/05 comparison required. |

No wholesale merge/cherry-pick, runtime file copy, deletion or numeric-version compatibility
was inferred. WD-24–29 record implemented boundaries and the remaining downstream gap.

## Tests and checks

| Exact command | Final result |
|---|---|
| `php artisan test tests/Unit/Wald03 --compact` | PASS: 344 tests, 1,707 assertions. |
| `php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 --compact` | PASS: 552 tests, 2,969 assertions. |
| `php artisan test --compact` | PASS: 786 total, 771 passed, 15 existing environment-gated skips, 4,157 assertions. |
| `vendor/bin/pint --test` | PASS. |
| `composer validate --strict` | PASS: composer.json valid. |
| `composer audit` | Exit 1: eight inherited advisories across three packages; details below. |
| `npm run build` | PASS after permitted local child-process retry; Vite 8.1.4, 5 modules, 3.32 seconds. Initial sandbox attempt failed with spawn EPERM. No deployment. |
| `php -n scripts/verify-wald03-semantics.php` | PASS: three identical timezone hashes, no drivers/framework/models/Composer. |
| `git diff --check` and `git diff 4aa5ffb5a00527662ddfe66673edbfb18af9f0db --check` | PASS. |
| `git merge-base HEAD 4aa5ffb5a00527662ddfe66673edbfb18af9f0db` | Exact accepted baseline returned. |
| `git diff 4aa5ffb5a00527662ddfe66673edbfb18af9f0db -- app/Wald composer.json composer.lock package-lock.json .github` | Empty; generic core/dependency/CI boundary preserved. |

Final documentation path/link and contradiction review passed. Historical acceptance/scope
and WALD02 evidence retain their original context; current brief/status/package/divergence
and append-only DEC-044 record the new explicitly approved implementation state.
No MySQL-specific runtime changed; the inherited schema/concurrency release gates remain
skipped locally and are not claimed as verified.

One development-only test harness correction: its first run incorrectly expected the PDO
class to be absent under PHP -n. This PHP build compiles PDO in. The corrected test verifies the
actual database-free condition (no drivers and no framework/models), with no runtime change.

## Composer security — separate release gate

Audit completed despite an unwritable Composer cache warning. Eight advisories remain:

| Package | Advisory IDs | Count |
|---|---|---|
| filament/filament | PKSA-fwm5-nrzy-yd41, PKSA-r2v2-7j3d-th1m, PKSA-wst7-5d23-qy5j | 3 |
| league/commonmark | PKSA-zyf5-hrxv-hrd7, PKSA-nv44-1b4d-6gjg, PKSA-kr3s-894t-g5w2, PKSA-9q1p-3s19-bp1q | 4 |
| livewire/livewire | PKSA-bgw4-5zmg-2njg | 1 |

Remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. No package or
lockfile changed. This is not a clean security audit or a combined-release approval.

## Files changed

Repository-relative exact task paths:

- `app/SourceImport/Semantics/Contracts/SourceBusinessDictionary.php`
- `app/SourceImport/Semantics/Data/CallSemantics.php`
- `app/SourceImport/Semantics/Data/CoreIdentity.php`
- `app/SourceImport/Semantics/Data/DictionaryIdentity.php`
- `app/SourceImport/Semantics/Data/ObservedCell.php`
- `app/SourceImport/Semantics/Data/ProductRollup.php`
- `app/SourceImport/Semantics/Data/SemanticResult.php`
- `app/SourceImport/Semantics/Dictionary/CustomerAppDictionary.php`
- `app/SourceImport/Semantics/Dictionary/Quantity.php`
- `app/SourceImport/Semantics/Enums/Classification.php`
- `app/SourceImport/Semantics/Enums/ExportScope.php`
- `app/SourceImport/Semantics/Enums/Resolution.php`
- `app/SourceImport/Semantics/SemanticAdapter.php`
- `tests/Unit/Wald03/AdapterTest.php`
- `tests/Unit/Wald03/DictionaryTest.php`
- `tests/Unit/Wald03/PortabilityTest.php`
- `tests/Support/Wald03Fixtures.php`
- `scripts/verify-wald03-semantics.php`
- `DECISIONS.md`
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/wald-divergence-register.md`
- `documentation/work-packages/WP-CUSTOMER-WALD02-PORTABLE-CORE-CORPUS.md` (acceptance metadata carried from reviewed scope only)
- `documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md`
- `documentation/wald/customer-wald02-acceptance-wald03-scope-2026-09-08.md` (reviewed scope metadata carried unchanged)
- `documentation/wald/customer-wald03-business-dictionary-2026-09-08.md`

## Migrations/deployment and preserved work

No migration/schema, persistence, runtime registration, upload, UI, routes, queue, source binding,
review/commit integration, Portal workflow, SiteApp change, main action, push or deployment.
The new module is not referenced by production entry points. Build output is local/ignored only.

Preserved and excluded from commits: existing change to
`documentation/sprint-3e-date-negotiation-report.md`, untracked `Copy of siteapp1.xlsx`,
and untracked `output/`. No user workbook or output contents were used.

## Remaining WALD03 blockers and exact WALD04 prerequisites

No known WALD03 implementation blocker remains. Dedicated QA may begin against the executable
candidate plus final evidence commit. This report is implementation verification, not independent
QA acceptance. No contradictory business contract was found.

WALD04 must not start until:

1. Dedicated WALD03 QA passes and management explicitly accepts/fixes its immutable output SHA,
   dictionary version/fingerprint and frozen core identity.
2. The semantic and clarification contracts, negative matrix and standalone determinism evidence
   are accepted; no unresolved generic/domain boundary defect remains.
3. A separate scoped WALD04 work package and explicit implementation approval exist.
4. Knowledge ownership, tenant/site scope, profile activation/review/mutation permissions and
   source-site/profile ownership are approved.
5. Retention and immutable audit/history rules for learned answers, dictionary snapshots, profiles
   and source evidence are approved before designing persistence.
6. The parallel non-main profile/site-binding route receives an explicit adapt/supersede/defer
   decision; no silent merger or deletion.
7. The inherited Composer advisories remain explicitly tracked as a separate combined-release
   gate; any later dependency reconciliation has its own authority and regression evidence.

WALD05 still owns source revision/coverage, authorization, neutral staging, atomic review/commit
and projection preservation. Nothing in WALD03 supplies these decisions or begins them.

Recommendation: begin dedicated CUSTOMER-WALD03 QA; do not begin WALD04 or deploy.
