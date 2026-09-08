# CUSTOMER-WALD03 Acceptance + WALD04 Scope Report

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Outcome: **WALD03 accepted; WALD04 scoped only — governance decisions required.**

## Completed

Recorded explicit management acceptance of corrected WALD03, preserved the QA fixes and created
the proposed WALD04 knowledge/profile work package. No WALD04 implementation was performed.
This report and the work package distinguish approved existing rules from proposals requiring
management decisions; a recommendation does not grant access or approve a purge policy.

Documentation branch: `codex/docs-customer-wald04-scope-2026-09-08`, created exactly from the
accepted output below. Initial checkout was the QA branch at that same SHA. No unrelated
runtime/source changes were present or introduced.

## Frozen WALD03 baseline

| Item | Accepted value |
|---|---|
| WALD03 output / immutable WALD04 input | `a80ce7d14206cf3f3a9343448d406f01ae927b88` |
| QA branch | `qa/customer-wald03-2026-09-08` |
| Corrected executable/test revision included | `f4fda0f069bd5106a125b42615ca212294a9dfad` |
| Original implementation candidate — not accepted output | `574f19694595f300620713b0dbd4a7d27036d7d1` |
| Dictionary | `customerapp.source-dictionary.v1` |
| Dictionary fingerprint | `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` |
| Accepted WALD02 input | `4aa5ffb5a00527662ddfe66673edbfb18af9f0db` |
| Reader / confidence | `wald-0.2.1` / `wald.confidence.v1` |

The corrected executable and accepted full snapshot have the same runtime tree; the latter
includes the QA documentation. Generic Wald remains tree
`30d1fc65e575242004eb335ad46a4d8ec920127a`, identical to accepted WALD02.
DEC-045 records acceptance and the scoping-only authority; historical reports are unchanged.

## Accepted QA evidence

Evidence: [dedicated QA report](customer-wald03-qa-2026-09-08.md).

| Suite | Accepted QA result |
|---|---|
| Focused WALD03 | 470 passed; 2,550 assertions |
| Combined Wald | 678 passed; 3,812 assertions |
| Full CustomerApp | 897 passed; 15 existing environment-gated skips; 5,000 assertions |

These totals are accepted prior QA evidence, not full suites rerun by this documentation task.

Preserved corrections:

- W3Q-01 P1: precision-dependent quantity corruption; exact three-place round-trip validation
  and fixed-point arithmetic, no rounding invalid input into a valid quantity.
- W3Q-02 P2: missing confidence-version validation; retain the pinned version check even with
  an internally valid manifest hash.
- W3Q-03 P2: contradictory result-constructor states; no false resolved semantics, fabricated
  service completion or inconsistent aggregate states.

Raw evidence, structural ambiguity and semantic neutrality remain frozen requirements.

## WALD04 scope and architecture

[Work package](../work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md) defines:

- organisation-owned, site/namespace/workbook-family-contained knowledge, never learned global
  truth merely because one user confirmed a value;
- structural profiles separate from occurrence-specific semantic confirmation and future source
  site bindings;
- proposed Office-only knowledge abilities, including valid null-organisation Office users;
  no Site User knowledge grants inferred from Portal workflow access;
- one-time answers by default, separate explicit save-as-draft and activation, immutable versions,
  revocation, current authorisation and append-only audit;
- deterministic structural descriptors and EXACT_MATCH / COMPATIBLE_WITH_REVIEW / INCOMPATIBLE /
  STALE_VERSION outcomes, preserving current evidence and competing candidates;
- dictionary/component compatibility and supported corrected-build checks, without using a Git
  SHA as a database foreign key or assuming unchanged labels imply a safe implementation;
- narrowly separated knowledge contexts, questions, answers, profiles, versions, events,
  reuse receipts and evidence payloads; no migrations or generic profile catch-all;
- atomic answer/provenance/audit and activation operations, expected-version conflicts,
  idempotency, lock order and mandatory future disposable MySQL concurrency tests.

The accepted semantic adapter cannot simply be forced to accept by editing its result.
The proposed reviewed-interpretation envelope retains original results and authorised
selection/correction separately; it never fabricates Wald confidence or staging permission.

## Proposed knowledge rules

Hard dictionary/type/access/source-integrity gates precede a current authorised one-time answer,
then a unique compatible activated structural profile. Fresh contradictions veto stored knowledge.
Two plausible tables/profiles/plot candidates remain clarification-required.

CC! may receive an explicitly reviewed occurrence-only CC1 correction under proposed G08.
The original invalid value remains in evidence and a later occurrence asks again. ZZ9 cannot
be assigned a service as a new business definition; dictionary additions require a separate
controlled version/fingerprint release. Source coverage, Portal dates and business quantities
are never learned profile defaults.

Revocation stops future reuse through current state/epoch checks, preserves history and
invalidates stale receipts. It does not rewrite previous import effects. WALD05 must recheck
those receipts at its eventual final commit boundary.

## Retention recommendation — approval required

Proposed operational periods, not legal claims:

| Material | Proposed policy |
|---|---|
| Source workbook | 30 days after terminal processing |
| Bulky parsed observation | 7 days after terminal processing |
| Preview | Valid 24 hours; bulky payload cleanup after 7 days |
| Clarification/minimal audit evidence | 24 months after closure/latest dependent retirement or use; protect active provenance |
| Profile | Reapproval after 12 months; retired history 24 months after latest retirement/use |
| Final import audit | Six years proposed for WALD05 decision, not an approved statutory period |

Holds and longer dependent audit requirements override shorter cleanup periods. Source files
are not retained indefinitely by default. Scope distinguishes audited removal of raw evidence
from historical decision truth and requires honest “replay unavailable” reporting once original
evidence expires. Hold/disposal owner and backup expiry still require approval.

## Governance decisions required

| ID | Decision needing approval | Recommended answer |
|---|---|---|
| G01 | Answer/save/reuse/audit access | Active non-preview Office only; no new Site User grants. |
| G02 | Approval/activation authority | Separate explicit activation; same Office actor permitted for site/family structural profiles. |
| G03 | Tenant/global scope | Organisation owner + one site + source namespace + family; no learned global or organisation-wide reuse. |
| G04 | Workbook/observation/preview retention | 30 days / 7 days / 24-hour validity with 7-day payload cleanup; WALD05 storage gate. |
| G05 | Profile/clarification retention | 12-month profile review; 24-month retired knowledge/audit policy with active dependency protection. |
| G06 | Revocation | Any currently authorised Office actor in selected scope, with reason; immutable history. |
| G07 | Source Site ID binding ownership | Office-controlled independent lifecycle; defer runtime to WALD05. |
| G08 | Semantic correction and parallel work | CC! occurrence-only; no reusable business aliases; accept the bounded comparison dispositions. |
| G09 | Hold/disposal/final audit policy | Nominate Fenster data owner; approve minimal retained identity, backup expiry and later final-audit period. |

Immediate WALD04 implementation gates: G01/G02/G03/G05/G06/G08 and applicable G09 evidence/hold
rules, plus separate explicit implementation approval. G04, binding runtime under G07 and final
import audit in G09 remain explicit WALD05 gates; their deferral must be approved.

## Parallel import/profile work

Read-only Git inspection of `04b560f` → `a013ed1` → `1e8c22b` examined actual profile
models/services, migrations 000009–000012, mapping confirmation, exact/likely reuse, binding
guards, stale-preview fingerprints and synthetic tests.

- **REUSE:** safe synthetic exact/reordered/changed-profile, stale-version/binding and scope tests.
- **REIMPLEMENT:** profile/version/matching intent with tenant/site containment and immutable
  approval/revocation; future binding invariants under a separate contract.
- **SUPERSEDED:** numeric semantic_version=3 compatibility, namespace-only/first/score-based
  matching, automatic profile save during mapping confirmation and mixed migration adoption.
- **REFERENCE_ONLY:** source-site binding runtime, full preview/commit orchestration and Office UI.

Observed gaps are scoped findings, not a whole-branch QA verdict: the profile service queries
namespace and numeric semantic version, not customer/site; it returns exact first/latest matches,
limits likely search to 50 and uses score 65; saving is coupled to confirmation. It has useful
version intent but not the proposed activation/revocation/event contract. Binding guards prevent
moving imported associations, but binding updates are mutable rather than a separate immutable
version history. These are reasons to reimplement bounded concepts, not copy or delete the branch.
Full path-by-path comparison is in work-package §16 and WD-33–37.

## WALD04 test strategy and WALD05 entry

Future tests cover exact/reordered/materially changed structures, competing targets, dictionary/
core/profile compatibility, all four roles, null-organisation Office, tenant isolation, IDOR,
one-time/reusable separation, activation, revocation, immutable corrections, concurrent answers,
stale receipts, raw evidence/retention, CC! occurrence-only treatment, unknown-code refusal,
determinism, budgets and rollback safety. MySQL 8.4 race/schema evidence is mandatory once
persistence is implemented; SQLite is not claimed sufficient.

WALD05 requires accepted WALD04 implementation/QA and immutable output, approved governance,
tested persistence/audit/concurrency and receipt contracts, explicit binding/source-revision/
coverage/record-grain rules, upload/review/commit permissions and retention, commit atomicity/
recovery, non-main parity decisions and its own approved work package. Production storage/
worker/backup/security requirements remain separate integration/pilot gates.

## Files changed

Nine exact task files:

- `DECISIONS.md` — appended DEC-045 only.
- `brief.md` — current acceptance and planned-work status.
- `current_sprint.md` — current acceptance/scope at top.
- `ROADMAP.md` — accepted output and WALD04 planning gate.
- `HANDOVER.md` — exact baseline, recommendations and next approval.
- `documentation/wald-divergence-register.md` — accepted baseline and scoped WD-33–37.
- `documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md` — accepted output status.
- `documentation/work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md` — new proposed package.
- `documentation/wald/customer-wald03-acceptance-wald04-scope-2026-09-08.md` — this acceptance/scope evidence.

Neither historical WALD03 implementation nor dedicated QA report was rewritten.

## Checks performed

- `git status --short`, `git branch --show-current`, `git log` and `git show`: verified exact
  QA checkout, accepted commit and executable/documentation ancestry before branch creation.
- `git rev-parse a80ce7d:app/Wald 4aa5ffb:app/Wald`: identical core tree above.
- `git diff --name-only f4fda0f a80ce7d`: documentation-only delta.
- `php -n scripts/verify-wald03-qa.php`: PASS in this task, nine equal hashes across three
  time zones/precision settings and C/de-DE/fr-FR numeric locales; expected dictionary pair;
  no Composer/framework/Portal-model classes or PDO drivers.
  Replay hash: `e89609be49f056a7f9a17672da3860ae0319657ba7634725ea9c16ae17db8c5f`.
- `git diff --check` and staged diff check: PASS.
- Runtime boundary diff against accepted a80ce7d for app/tests/scripts/database/routes/config/
  resources/dependencies/CI: empty; status also confirms no untracked runtime files.
- Local Markdown link/path validation and contradiction review: passed; all task files exist.
  Current status no longer says management acceptance is pending. Historical phase statements
  remain labelled historical. All governance grants/periods are labelled proposed.
- Main/origin-main remain `0873bac79edf578e9f4a9417e3cafae34e8aa925`; no fetch or production
  inspection occurred, so no new deployment assertion is made.

Full application suites, build, Pint, Composer validation/audit and MySQL were not rerun:
this task changed documentation only and no executable artifact. Prior QA totals/security
results are clearly attributed rather than presented as fresh verification.

## Composer security

Eight inherited advisories remain on the accepted line per dedicated QA: Filament 3,
CommonMark 4, Livewire 1. No clean audit claim. Separate remediation
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged; no dependencies changed.

## Migrations/deployment and notes

No runtime code, migrations/DDL, model, policy, route, UI, job, upload, source binding, import
commit, dependency remediation, main mutation, push, production access, SiteApp change or
deployment. No source/customer workbook was opened.

Preserved uncommitted unrelated edit: `documentation/sprint-3e-date-negotiation-report.md`.
Preserved unopened untracked `Copy of siteapp1.xlsx` and existing `output/`.

No baseline contradiction was found. The earlier QA recommendation is superseded by this
explicit acceptance; the original implementation SHA remains historical only. WALD04 governance
is genuinely unresolved and is not silently settled by repository existence or this proposal.

Recommendation: management should approve/amend the decision table first, then issue an explicit
WALD04 implementation instruction. No implementation now.

