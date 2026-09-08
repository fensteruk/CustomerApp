# CUSTOMER-WALD03 Dedicated QA Report

Date: 8 September 2026. Owner: CustomerApp QA. Status: **PASS after corrections; local QA branch only.**

## Overall Result

Completed the dedicated dictionary/semantic-adapter gate, including independent adversarial
tests, fixed-point arithmetic, baseline integrity, result invariants and standalone replay.
Three contract-defined defects were reproduced and fixed; no WALD03 blocker remains.
This is a QA recommendation, not management acceptance, combined-release approval or deployment.

## Candidate

- Original implementation branch: `feature/customer-wald03-business-dictionary`.
- Exact original candidate: `574f19694595f300620713b0dbd4a7d27036d7d1`.
- Accepted WALD02 input, verified ancestor: `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`.
- Local QA branch created from that exact candidate: `qa/customer-wald03-2026-09-08`.
- Corrected executable/test commit: `f4fda0f069bd5106a125b42615ca212294a9dfad`.
- This subsequent documentation-only commit records the gate and updates delivery status;
  the full delivered QA-head SHA is supplied in the final handoff. It contains the same
  verified executable tree. Do not freeze the original `574f196` candidate.

Reviewed the complete 28-file WALD03 candidate delta and the seven-file QA executable/test
delta. Candidate changes were pure semantic infrastructure, synthetic tests/scripts and
governance/evidence documents. Existing unrelated changes were preserved and excluded.

## WALD02 Core Integrity

PASS. All 52 runtime files under `app/Wald` are unchanged. Both accepted input and QA tree
resolve to Git tree `30d1fc65e575242004eb335ad46a4d8ec920127a`.
No diff in accepted Wald unit/feature tests or `scripts/verify-wald02-qa.php` either.

Reader `wald-0.2.1`, XLSX/CSV adapters `2`, structure `wald.structure.v1.1`, reasoning
`wald-0.3.0`, generic rules `wald.generic-rules.v1`, confidence `wald.confidence.v1`.
CustomerApp meanings remain exclusively in the 13-file `App\SourceImport\Semantics` module.
No SiteApp repository access, copying or backport was performed during this gate.

## Dictionary Identity

PASS. Version: `customerapp.source-dictionary.v1`.

Fingerprint: `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.

Recomputed independently with recursive map-key sorting and the specified JSON flags;
recursively reversed maps yield the same hash. Mutating each top-level definition section
changes the fingerprint and is refused under the old version when compared to its snapshot.
Version/label/service/revisit tests also pass. Lists retain meaningful order.

The corrections enforce existing definitions; no code, label, mapping, quantity limit,
normalisation policy or roll-up definition was changed. Hence v1 and its golden hash remain
unchanged. The corrected Git SHA distinguishes the implementation fixes. Identity/results
remain stable across fresh processes, timezones, numeric locales and PHP precision settings.

## Call Types

PASS. PC1 → Plot Install / `windows`; CC1 → Cavity Closer 1 / `cavity_closers`;
CM1 → Revisit 1 / `cml`; CM2 → Revisit 2 / `cml`; CML → CML Call Off / `cml`.
CM1/CM2 retain revisit=true; other mappings false. No extra Portal service or Snagging source
code is invented. Only surrounding trim and ASCII case normalisation are applied.

## CC! / Unknown Codes

PASS. `CC!`, `cc!`, `Cc!`, and ` CC! ` remain INVALID / REQUIRES_CONFIRMATION with raw
spelling retained. CC1 is only a LIKELY_TYPO suggestion; match/value remain null.
ZZ9, ABC, PC2, blanks, numeric-looking and Unicode/lookalike codes remain UNKNOWN.
No fuzzy match, correction, known service completion or readiness occurs.

## Completion

PASS. Only string Yes/No, sensible case variants and surrounding trim resolve to booleans.
Y/N, 1/0, True/False, Complete/Done, blanks, arbitrary strings, numeric and boolean values
remain unknown. Unknown/invalid call + Yes retains the independent Yes observation but no
known completed service. No completion date or Portal transition is generated.
Cross-checksum, sheet, row and region compositions remain blocked.

## Products

PASS. All 13 included codes have exact descriptions/categories and independent single-code
contribution tests: VS/TT/BAY/ALI/AOV/FI for Windows and PSU/PSG/CDF/CDU/CDG/PSP/BF for Doors.
CAS/FLU/PFD/GLS/WP/MISC remain excluded, with raw evidence retained; invalid quantities in
excluded input still prevent a valid aggregate. XYZ/BFX/VS2/Bifold are unknown, not aliases.

## Quantity Contract

PASS after W3Q-01. Non-negative plain fixed-point decimals, maximum three fractional digits,
maximum `999999999.999` per quantity and total. Null/blank quantity means zero only inside
the supplied input. Whitespace and leading zeroes are retained raw, normalised separately.
Negative, non-finite, NaN/Infinity text, booleans, exponent/grouped notation, malformed and
excess-precision values are refused. No rounding of invalid precision is permitted.

Finite float input is accepted only when its three-place decimal round-trips to the exact
input float. This removes global `precision` dependence while preserving the approved
contract. Original float values remain raw evidence. Source cells themselves retain strings;
the float defect affected the public dictionary primitive, not the frozen workbook reader.

## Rollups

PASS. Windows is exactly VS + TT + BAY + ALI + AOV + FI; Doors is exactly PSU + PSG + CDF +
CDU + CDG + PSP + BF. Exact `0.1 + 0.2 = 0.300`, thousandths, maximum valid totals and both
group overflows pass. Invalid, unknown, duplicate-normalised or overflowing input suppresses
**both** totals, never just the affected side. Exclusions do not contribute.

Normalised duplicate VS/BF inputs preserve both raw items and report DUPLICATE_PRODUCT_CODE.
Input order does not change serialised results. The primitive accepts a PHP keyed map; a
caller must not collapse same-spelled source columns into an overwritten PHP key before
calling it. Structural observations retain source coordinates; later integration must preserve
that evidence before constructing any map. This module cannot recover data discarded by a caller.

## BF Fact

PASS. True requires an exact normalised BF with a validated positive quantity. Absent, blank,
zero, negative, invalid and lookalike inputs do not create a positive BF fact. Positive other
doors with BF=0 keep BF false. As explicitly scoped, BF remains an observed positive fact
even if another product or a duplicate makes the aggregate unresolved; both totals stay null.
This does not establish authoritative quantity/absence or permit staging. No four/five-week,
working-day, holiday, eligibility or workflow calculation exists here.

## Field Semantics

PASS. Items Ordered Status and Site Value remain ignored, including highly suggestive and
hostile values. Plot To Be Installed identifies only the PC1 operational-date role: valid,
invalid, blank and Excel-serial-shaped strings remain raw, with no date validation/invention.
Site Name is a transitional clue; Source Site ID/reference is an identity role only.
No matching, binding, tenant lookup, authorisation or persistence is performed.

## Export Scope

PASS. The pure scope API has no workbook/filename/worksheet/row-count input and always defaults
to PARTIAL_FILTERED_EXPORT. Content claiming FULL EXPORT, Complete Site or global scope cannot
promote it. Stronger enum assertions are explicitly caller-supplied and still require
downstream confirmation; they are not authenticated or approved here. No absence/deletion
authority is emitted. This is a signature/static boundary plus synthetic-content test, not a
claim that customer workbooks or a thousands-row production export were tested.

## Semantic Result Invariants

PASS after W3Q-03. Every classification/resolution pair is exercised. UNKNOWN/INVALID/AMBIGUOUS
cannot advertise RESOLVED or carry effective values. BLOCKED cannot carry an effective value.
CONFIRMED + BLOCKED is allowed only with null value; recognised dictionary evidence can remain
tentative. IGNORED + REQUIRES_CONFIRMATION is representable and unresolved. Stronger scope
assertions intentionally retain their asserted value while requiring confirmation.

Composed services reject missing/contradictory service or completion facts and incompatible
dictionary identities. Aggregates verify item types/concepts/identity, duplicate/resolution
state, exact totals, overflow and the observed BF fact. Invalid combinations raise bounded
InvalidArgumentException messages. These DTOs remain trusted in-memory contracts, not an
authentication mechanism or a validator for arbitrary untrusted deserialised objects.
Every serialized result path continues to report ready_for_staging=false.

## Structural Ambiguity

PASS. Actual generated XLSX → accepted WALD02 reasoning → adapter integration retains both
House No./Sales Plot identifier candidates, evidence and clarification, returning AMBIGUOUS /
BLOCKED without a dictionary tie-breaker. Additional competing call/product/date clarification
cases use explicit synthetic ReasoningResult contract fixtures; they are not claimed as new
end-to-end reader detections. Nonaccepted decisions, confirmation flags, formula caches,
source-cell errors and mismatched provenance cannot yield effective facts.

## Clarification Behaviour

PASS. CC! yields one non-binding suggestion with a bounded reason. Unknown calls/products
yield structured unknown reasons, no invented options. Structural clarification retains
competing candidate IDs and source evidence. Repeated results are deterministic and do not
imply an answer was selected. No clarification UI, answer store or learned profile was added.

## Raw Evidence

PASS. Whitespace, case, Unicode, leading zeroes, punctuation and literal CC! remain recoverable
beside normalised lookup values. Adapter output preserves observed checksum/sheet/row/column,
raw header/value, formula/style/source metadata, candidate evidence/confidence, clarification
and manifest identity. Non-finite PHP floats remain raw in memory and use the existing tagged
JSON representation. Evidence is private data, never rendered as HTML or sent to customers.

## Determinism

PASS. `scripts/verify-wald03-qa.php` repeats a mixed positive/negative/ambiguity corpus across
UTC/Europe-London/Pacific-Auckland, precision 3/14/17, C/de-DE/fr-FR numeric locales, reversed
product input and unrelated lookup order. Three fresh PHP processes yield 27 equal hashes:
`e89609be49f056a7f9a17672da3860ae0319657ba7634725ea9c16ae17db8c5f`.
Classification, resolution, raw/lookup values, evidence, suggestions, identity, totals and BF
are included; timing/memory are deliberately excluded. Randomised combined test order passes.

## Portability

PASS. Plain `php -n`, custom scoped autoload only: no Composer/Laravel bootstrap, loaded
Illuminate/Portal-model/SiteApp classes or available PDO drivers. No DB/auth/container/queue,
source file or network dependency. Static audit agrees. A nine-replay diagnostic run took
50 ms and 2,097,152 peak allocated bytes on this Windows/PHP 8.4.23 machine; this is bounded
semantic-corpus evidence, not a production throughput or large-workbook performance promise.

## Persistence/UI Boundary

PASS. No new migration, model, table, route, controller, policy, session, storage write, job,
queue, Livewire/Filament/Blade/frontend code, network/AI client, production configuration or
SiteApp dependency. No Portal production call site uses this new semantic layer yet.
Browser/mobile/accessibility and MySQL concurrency testing are not applicable to this pure,
headless, non-persistent slice; no new UI or locking/SQL behaviour was introduced.

Read-only review confirms the old import line 04b560f → a013ed1 → 1e8c22b remains separate.
Safe semantic test intent is REUSE; config/dictionary/scope intent REIMPLEMENT; direct mapper
and fixed workbook contract SUPERSEDED; profiles/bindings/preview/commit/Office UI REFERENCE_ONLY.
In particular its historical Job Stage completion and floating-point duplicate summing were
not imported. No wholesale merge/copy/deletion occurred.

## Defects

| ID | Severity | Reproduction and correction | State |
|---|---|---|---|
| W3Q-01 | P1 | At precision=3, float 1.125 became 1.120 and invalid 1.1254 became 1.130; precision=17 rejected valid 0.1/0.2. Replaced precision-sensitive casts with exact three-place float round-trip validation. | Fixed; six original failing cases, expanded 18-case precision matrix. |
| W3Q-02 | P2 | A missing or v2 confidence-policy version with a recomputed valid manifest hash still returned CONFIRMED. Adapter now checks the pinned confidence version alongside the other core versions. | Fixed; both negative cases pass. |
| W3Q-03 | P2 | Public constructors allowed contradictory resolved/invalid facts, fabricated service completion and inconsistent totals/BF. Added bounded constructor invariants; orthogonal tentative states remain supported. | Fixed; 18 original failing cases plus expanded matrix/aggregate checks. |

The original 344 tests passed before corrections. The independent first attack corpus exposed
26 failing assertions/cases across these three defects. Three initially assertion-free matrix
branches in the new QA harness were also corrected to assert rejection, not skipped or weakened.
No generic-core or new business-decision defect required escalation. WD-30–32 record the fixes.

## Tests / Checks

Toolchain verified: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.0.windows.3.
Commands ran locally; full suite uses configured SQLite `:memory:` and array mail transport.

| Command | Exact result |
|---|---|
| `php artisan test --compact tests/Unit/Wald03` before QA changes | PASS: 344 tests / 1,707 assertions. |
| Same command after corrections | PASS: 470 tests / 2,550 assertions; 126 additional QA cases. |
| `php artisan test --compact tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03` | PASS: 678 tests / 3,812 assertions. |
| Same combined command with `--order-by=random --random-order-seed=3082026` | PASS: 678 tests / 3,812 assertions. |
| `php artisan test --compact` | PASS: 912 total, 897 passed, 15 existing environment-gated skips / 5,000 assertions; final run 44.690 s. |
| `php -n scripts/verify-wald03-qa.php` | PASS: nine equal hashes; C/de-DE/fr-FR exercised; no forbidden classes, Composer or PDO drivers. |
| `php vendor/bin/pint --test` | PASS after formatting only the new QA test file. |
| `composer validate --strict` | PASS, exit 0. |
| `composer audit --format=json` | Exit 1: eight inherited advisories, detailed below. |
| `npm run build` | PASS, Vite 8.1.4; initial sandbox child-process EPERM resolved by the same approved local build outside sandbox. No deployment. |
| `git diff --check` / `git diff --cached --check` | PASS. |
| `git diff 4aa5ffb -- app/Wald tests/Unit/Wald tests/Feature/Wald scripts/verify-wald02-qa.php` | Empty; frozen core and accepted regressions unchanged. |
| `git diff 574f196 -- composer.json composer.lock package.json package-lock.json` | Empty; no dependency/lockfile changes. |

The 15 gated skips are not counted as passes or as new MySQL/concurrency evidence. Final
synthetic `wald-*` fixture inventory is empty. Generated build assets are local ignored output.

## Composer Audit

Eight inherited advisories across filament/filament (3), league/commonmark (4), livewire/livewire
(1): five high, two medium, one low. No clean-security claim. IDs: PKSA-fwm5-nrzy-yd41,
PKSA-r2v2-7j3d-th1m, PKSA-wst7-5d23-qy5j, PKSA-zyf5-hrxv-hrd7, PKSA-nv44-1b4d-6gjg,
PKSA-kr3s-894t-g5w2, PKSA-9q1p-3s19-bp1q, PKSA-bgw4-5zmg-2njg.
Separate remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` is not an ancestor and was
not merged. Audit ran successfully without writable cache; advisories remain a later
combined-release gate, not a newly introduced WALD03 dependency defect.

## Files Changed

Corrected executable/test commit (seven exact files):

- `app/SourceImport/Semantics/Data/CallSemantics.php`
- `app/SourceImport/Semantics/Data/ProductRollup.php`
- `app/SourceImport/Semantics/Data/SemanticResult.php`
- `app/SourceImport/Semantics/Dictionary/Quantity.php`
- `app/SourceImport/Semantics/SemanticAdapter.php`
- `scripts/verify-wald03-qa.php`
- `tests/Unit/Wald03/DedicatedQaTest.php`

Documentation-only gate record (eight exact files):

- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/wald-divergence-register.md`
- `documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md`
- `documentation/wald/customer-wald03-business-dictionary-2026-09-08.md` (historical label only)
- `documentation/wald/customer-wald03-qa-2026-09-08.md`

## Migrations / Production Impact

None. No standalone migration/DDL, production connection, account, queue, workbook, deployment,
tag, remote push, main merge or GitHub workflow was invoked. Ordinary regression fixtures run
in disposable in-memory test databases. Local `main` and existing `origin/main` refs remain
`0873bac79edf578e9f4a9417e3cafae34e8aa925`; no remote refresh or deployment claim is made.
No experiment used customer source data; only synthetic fixtures were used and cleaned up.

## Notes / Remaining Blockers

No remaining WALD03 blocker. Preserved unrelated `documentation/sprint-3e-date-negotiation-report.md`
edit, unopened `Copy of siteapp1.xlsx` and existing `output/`; none are staged or committed.
This work triggers no GitHub push/CI or Forge deployment.

Management acceptance/freeze and a separately approved WALD04 work package remain manual
next actions. Ownership, tenancy, permissions, retention, activation and profile reconciliation
must be decided for that later phase. Future integration must validate caller provenance,
preserve duplicate source observations and authorise scope; pure dictionary facts do not do
any of those jobs. Inherited dependency remediation remains separately gated.

## Recommendation

Freeze the delivered corrected QA snapshot containing executable `f4fda0f` and this report,
not original candidate `574f196`. Then scope WALD04 through its separate approval process.
No deployment, production integration or WALD04 implementation is authorised by this QA pass.

CUSTOMER-WALD03 dedicated QA passed — freeze candidate and scope CUSTOMER-WALD04
