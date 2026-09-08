# CUSTOMER-WALD05 — Import, Review and Controlled Commit Integration

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **SCOPED ONLY — DEC-047. GOVERNANCE AND IMPLEMENTATION APPROVAL REQUIRED.**

[Decision-resolution report](../wald/customer-wald05-governance-resolution-2026-09-08.md):
I03 source revision/order and I04 Call No. grain require Nick/source-owner evidence. Other
recommendations remain unapproved; this link records analysis, not implementation authority.

This work package proposes the bounded integration that turns private workbook evidence into
reviewed neutral records and, only after a separate explicit Office action, commits approved
source facts into CustomerApp projections. It does not authorise implementation, migration,
route/UI/queue/storage changes, import execution, production access, release or deployment.

## 1. Immutable entry baseline

| Identity | Frozen value |
|---|---|
| Accepted WALD04 output / proposed WALD05 input | `0e83eb2896e7c5144bc38c1be9713f3d205d93b8` |
| QA branch | `qa/customer-wald04-2026-09-08` |
| Corrected executable/test revision | `9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5` |
| Original WALD04 candidate — not accepted output | `2c7d0154e51a35b165c7e93f7dd256e2cf0f030f` |
| Generic Wald tree | `30d1fc65e575242004eb335ad46a4d8ec920127a` |
| CustomerApp semantic tree | `9cc8df4bc50a888ec49e6a93dddaba180a2d0d01` |
| WALD04 knowledge tree | `43135838725e11f6af1b614115f642fb800a2953` |
| Reader / adapters | `wald-0.2.1` / XLSX 2, CSV 2 |
| Dictionary | `customerapp.source-dictionary.v1` |
| Dictionary fingerprint | `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` |
| Documentation reset baseline | `e84999cf66fc90aac3007842a538672b018b3e03` |

Accepted [WALD04 QA](../wald/customer-wald04-qa-2026-09-08.md) corrected W4Q-01–04 and
passed the security, lifecycle, compatibility, immutability, query-shape and MySQL concurrency
gates. Those results are input evidence, not tests rerun by this documentation scope.

Any WALD05 implementation branch must start exactly from the accepted output above unless a
newer explicitly accepted correction supersedes it. No moving QA/feature working tree, copied
SiteApp source or undocumented non-main CustomerApp merge may become the baseline.

## 2. Core principle and outcome

Wald infers structure. The controlled dictionary defines meaning. A human resolves genuine
ambiguity. A separate authorised review approves a specific neutral set. Only the controlled
commit boundary may alter Portal projections.

```text
private artifact
  → durable analysis operation
  → deterministic Wald profile + semantic interpretation
  → clarification / reanalysis until resolved or refused
  → immutable neutral staging revision
  → current-state diff and explicit Office review
  → guarded commit plan
  → atomic Portal projection commit + immutable audit
```

WALD05 exits only when this whole path works without SiteApp availability and preserves
CustomerApp tenancy, date negotiation, source completion precedence and history. “Supported
arbitrary layout” means bounded structural variation with honest questions/refusal, never a
claim that every spreadsheet can be interpreted.

## 3. CustomerApp / Wald boundary

Wald and WALD04 may supply workbook observations, inferred structural candidates, confidence,
ambiguity, current compatibility and reviewed structural knowledge. They do not supply:

- organisation/site authority or source-site binding;
- source revision order, export completeness or deletion authority;
- dictionary additions or new customer services;
- permission to stage, review or commit;
- Portal eligibility, workflow or Date Agreed rules;
- commit atomicity, recovery or notification policy.

WALD05 owns integration orchestration and neutral staging. Existing Portal domain services own
authorised business transitions. The import boundary must never turn Wald confidence, profile
reuse or a prior receipt into a tenancy grant or final-commit permission.

## 4. Included scope

- private XLSX/CSV intake into CustomerApp-owned non-public storage;
- operation IDs, durable queue-agnostic analysis state, optional queued execution and resumption;
- accepted Wald profiling, semantic adapter and WALD04 clarification/profile composition;
- explicit source namespace/family, source-site binding, revision and coverage selection;
- immutable analysis/staging/review/commit revisions and lineage;
- a neutral typed source record that preserves raw and canonical evidence separately;
- Office review of additions, changes, preserved omissions, completions, reversals and blockers;
- a separately authorised controlled commit into CustomerApp source projections;
- current-state revalidation, transaction/locking/retry/idempotency and recovery evidence;
- safe results/history plus authorised private diagnostics;
- synthetic parity, failure, security, queue, storage and MySQL evidence;
- a default-off local/pilot boundary. Production/pilot enablement remains WALD06.

## 5. Explicit non-goals

- SiteApp API/database/filesystem/queue/runtime access or source modification;
- scheduled production synchronisation, automatic commit or unattended disposal;
- SiteApp operational workflow, statuses, trade sequencing, notes or administration;
- source write-back or spreadsheet editing;
- customer upload/import controls or customer-visible raw workbook evidence;
- external AI, LLMs, embeddings or third-party workbook interpretation;
- new dictionary meaning, service codes, CML expansion or Snagging source code;
- Sprint 3F, deployment, security dependency remediation or unrelated Portal UI;
- deletion inferred from workbook absence;
- retiring the current validated-record importer or historical non-main work without a
  separate parity/cutover decision.

## 6. Existing repository boundary to reconcile

The accepted checkout contains a transport-independent `SourceRecord`,
`SourceProjectionImportService`, `SourceCallTypeMapper`, source run/issues/events and current
Portal projection tables. These are Sprint 3B evidence, not a ready Wald commit boundary.

| Existing behaviour | WALD05 disposition |
|---|---|
| `SourceCallTypeMapper` maps `CC!` and speculative legacy codes/stages | **MUST NOT USE AS BUSINESS AUTHORITY.** Replace/adapt through the frozen dictionary; preserve old code until separately migrated. |
| `SourceRecord` carries legacy job-stage/completed-date fields and float-like product input | **REIMPLEMENT neutral contract.** Carry raw/canonical `complete`, operational date, exact fixed-point quantities and lineage without inventing completion dates. |
| Import transaction is per record, followed by separate product and missing passes | **NOT AN APPROVED REVIEWED COMMIT UNIT.** Reconcile under I07 before wiring WALD05. |
| Products absent from the supplied record are set to zero | **BLOCKED** unless confirmed authoritative coverage and grain expressly allow it. Partial export cannot zero unknown quantities. |
| Missing detection compares source-wide absent Call Nos. | **BLOCKED** for default partial exports. Absence has no effect without separately approved complete coverage. |
| `source_name`/optional `source_version` and site external identifiers | **INSUFFICIENT IDENTITY.** Introduce explicit namespace/family/revision/binding/coverage contracts rather than trusting labels. |
| Existing completion/reversal actions and immutable Portal history | **REUSE DOMAIN INTENT AFTER CORRECTION.** Source completion must follow the current dictionary and lock/current-state rules. |
| Existing projection issue/event models | **REFERENCE/EVOLVE.** Preserve history; do not rewrite old migration truth or overload one row as the complete staging audit. |

No WALD05 endpoint may call the current importer directly with browser/Wald output. The reviewed
neutral adapter and commit contract must be explicit, versioned and tested first.

## 7. Parallel non-main import work

The inspected chain `04b560f` → `a013ed1` → `1e8c22b` contains synchronous Office upload,
interpretation profiles, mutable bindings, preview/commit and UI. It is not on main and carries
mixed migrations and assumptions predating the accepted Wald/dictionary/knowledge contracts.

| Area | Disposition |
|---|---|
| File validation, private storage naming, content hashes, stale-preview checks, safe summaries | **REUSE test/invariant intent** after verifying current accepted reader limits. |
| Office-only route/request patterns and explicit scope confirmation | **REIMPLEMENT** against current stored authority and new scoped operation IDs. |
| Mapping/profile engine and automatic save on mapping confirmation | **SUPERSEDED** by WALD03/04; never import its learned rows. |
| Namespace-only profile matching and first/latest score winner | **SUPERSEDED** by scoped fresh-evidence compatibility and competing-profile refusal. |
| Source-site binding exact-key protections | **REUSE invariant intent**, but implement a separately approved immutable lifecycle. |
| Preview/result page information design | **REFERENCE_ONLY**; rebuild with current Blade/Tailwind accessibility and privacy rules. |
| Direct synchronous analysis/commit service | **SUPERSEDED as orchestration**; WALD05 requires durable operation/lease/recovery separation. |
| Existing commit fingerprint and partial-scope tests | **REUSE negative scenario intent**, not runtime or schema. |

No merge, cherry-pick, wholesale copy, legacy-profile data migration or deletion is authorised.

## 8. Proposed persistence separation

Names are provisional and no DDL is authorised. Prefer additive tables and forward migrations.

| Entity | Proposed responsibility |
|---|---|
| Source artifact | Private storage key, byte hash, safe media/size identity, retention/hold and uploader. Never public path or filename authority. |
| Analysis operation | Stable UUID/idempotency, actor/scope, accepted component pins, state, attempt/lease/fencing token, safe failure and artifact reference. |
| Source-site binding root/version | Namespace + exact source-site identity to existing organisation/site, immutable versions, activation/revocation/reason/audit. Never creates a site. |
| Source revision | Namespace/family supplied revision and deterministic content identity, received/observed time, ordering status and predecessor. |
| Staging revision | Immutable set hash, schema/dictionary/core/profile pins, source coverage and analysis/context lineage. |
| Neutral record | Stable row identity, raw/canonical values, exact quantity representation, physical source provenance and validation state. |
| Review revision/item | Actor/time, expected staging hash, decision/reason, included/excluded/blocking disposition and current-state diff hash. |
| Commit plan | Immutable proposed effects, scope locks, expected current-state hashes and idempotency key. No business write itself. |
| Commit receipt/event | Actual effects, before/after, actor, plan/version, retries, outcome and correction lineage. Append-only. |
| Integration issue | Stable issue identity, safe class/status, private evidence pointer, resolution/successor. |

WALD04 knowledge evidence remains its own schema. Do not copy raw workbooks or staging rows into
profile definitions. Staging references the exact knowledge context/answer/profile receipt used.

## 9. Neutral record contract

The proposed neutral record is transport-independent and contains no Portal workflow decision:

- source namespace, family, artifact hash, revision and explicit coverage;
- bound source-site identity plus selected Portal organisation/site identity;
- source `Call No.` raw/canonical identity and physical record provenance;
- plot reference as an exact source string, preserving leading zeroes;
- call type raw value, dictionary classification and canonical code/service only when resolved;
- raw `complete` plus approved Yes/No/unknown classification; no fabricated date;
- `Plot To Be Installed` as PC1 operational evidence only;
- exact fixed-point quantities for approved product codes, excluded/private codes and unknowns;
- validation/refusal issues, ambiguity and reviewed clarification lineage;
- explicit missing-versus-blank-versus-zero state inside the selected source coverage.

It must not contain Requested Date, alternative date, Date Agreed, inferred customer action,
SiteApp stage, readiness, dependency, or invented Snagging/CML meaning. Unknown required fields
remain blocking. Neutral records are immutable revisions; correction creates a successor.

## 10. Proposed operation lifecycle

```text
REGISTERED → QUEUED → CLAIMED → ANALYSING
  → NEEDS_CLARIFICATION ↔ QUEUED_REANALYSIS
  → STAGED → REVIEW_REQUIRED → REVIEWED
  → COMMITTING → COMMITTED
```

Terminal alternatives are REFUSED, FAILED, EXPIRED, SUPERSEDED and REVOKED. State names are
proposals. Browser polling reads state only. Browser close/reopen does not cancel durable work.
Clarification or mapping changes produce a new analysis/staging generation; they do not edit
prior results. A reviewed generation cannot commit if artifact, scope, binding, revision,
dictionary, knowledge receipt, staging hash or relevant Portal current state changed.

## 11. Execution, queue and recovery proposal

- register artifact/operation transactionally before execution or after-commit dispatch;
- permit bounded local/test analysis through the configured synchronous driver; persistent
  queue rollout is not a prerequisite for implementation;
- if analysis is queued, use one CustomerApp queue only; never SiteApp queue/workers;
- stable operation and delivery IDs with unique idempotency constraints;
- finite attempts, lease expiry, fencing token and expected-generation checks;
- recovery finds registered-but-unexecuted and, when queued, abandoned claimed work;
- stale worker completion cannot overwrite a successor or terminal operation;
- no analysis, network or workbook parsing inside database write locks;
- retry only recognised transient failures; validation/authorisation/refusal is not retried;
- analysis recovery and domain-commit recovery are distinct, with explicit operator action;
- safe diagnostics record class/code and operation UUID, never raw content/path/credentials.

Starting limits from WALD01 (300-second analysis, 600-second lease, three attempts and bounded
backoff) are benchmarks, not approved production settings. WALD05 implementation evidence must
measure and justify the actual values. Final commit is a synchronous explicit transaction and
never depends on queue delivery. Persistent worker/scheduler/supervision gates any queued
production/pilot mode, not local implementation.

## 12. Proposed permissions

Recommend active, non-preview Fenster Office Staff only for intake, analysis, clarification,
binding proposal, review, commit, recovery inspection and private diagnostics in V1. Each action
must be a distinct server-side capability and audit operation. Null-organisation Office may act
only after selecting a valid explicit organisation/site/source scope.

The three Site User roles receive no import/evidence access. A UUID, upload ownership, profile
receipt or selected site never grants authority. Current stored authority and scope must be
rechecked immediately before every mutation and again inside final commit locks.

Whether the same Office actor may upload, resolve, review and commit is **I01**, not inherited
from WALD04's same-actor profile activation decision. No four-eyes rule is invented by scoping.

## 13. Proposed source-site binding lifecycle

- exact namespace + source-site stable key, never fuzzy name or workbook filename;
- binds to one existing Portal site and its organisation; never creates/moves that site;
- separate draft/activate/revoke/successor commands with actor, reason and epoch;
- display/original names are evidence, not durable identity;
- first commit pins the binding version in immutable lineage;
- moving a used binding is prohibited; reconciliation requires a successor/correction plan;
- revocation blocks new analysis/commit but preserves prior import truth;
- concurrent activation/revocation/commit uses explicit locks and stale-epoch conflicts;
- exact Site Name may be a transitional candidate only until the source supplies a permanent ID.

The binding is independent of a Wald profile. Learning workbook layout cannot select a customer,
organisation or site. Final binding authority and actor separation require I02 approval.

## 14. Revision, ordering and idempotency proposal

Content SHA-256 proves byte identity, not source authenticity or chronological authority.
Require an explicit source revision contract per namespace/family: issuer/owner, revision key,
ordering rule and collision handling. Received/upload time is not a source revision.

Recommend rejecting an already committed revision with different bytes, treating exact repeat
as idempotent, and blocking older/unordered revisions until reviewed. A later upload with the
same data but different container bytes is not silently equivalent unless canonical source-set
identity and provenance are explicitly defined. These are I03 decisions.

## 15. Export scope and absence

Default is always `PARTIAL_FILTERED_EXPORT`. Filename, familiar format, row count, represented
sites, prior profile or user history cannot upgrade it. In partial scope:

- absence never marks a Call No. missing;
- omitted products never become zero;
- unrepresented plots/services remain unchanged;
- a narrower new staging set does not retract a previous commit.

`SITE_COMPLETE_SNAPSHOT` requires explicit bound sites, dataset/grain definition, revision and
Office confirmation. `GLOBAL_COMPLETE_SNAPSHOT` additionally requires approved global authority.
Neither mode deletes rows; its exact missing/reconciliation effects remain I05. Stronger scope
must be shown prominently in review and pinned into staging/commit lineage.

## 16. Duplicate and multi-row Call No.

The dictionary says `Call No.` is the permanent unique source record identity, but actual
duplicate/multi-row export semantics remain unresolved. Safe default: every duplicate Call No.
blocks the affected reviewed set, even if rows look identical. Do not use first/last row,
merge product quantities or infer one logical call automatically.

I04 must decide whether any documented source row grain permits multiple rows, how their stable
subidentity/order is supplied, how conflicts are represented and which owner approves it. Until
then there is no lossless normalisation or commit for duplicate identifiers.

## 17. Review and diff contract

The review must be generated from immutable staging against locked/revalidated current state and
show, in customer-safe Office language:

- selected organisation/site/source family/revision and export scope;
- records added, changed, unchanged, preserved because absent, blocked and explicitly excluded;
- plot/service identity, source completion/reversal, product roll-up and BF lead-time impact;
- operational dates clearly separated from Portal Requested/Agreed dates;
- source-site binding and any one-time clarification/profile receipt used;
- blockers, warnings and why no effect will occur;
- exact commit unit and recovery consequence.

Raw workbook cells/filenames remain authorised private evidence and need not appear in general
result lists. Review approval records the exact staging/diff hash and expires on any dependency
change. Review is not profile activation and cannot mutate dictionary truth.

## 18. Controlled commit proposal

Recommend one atomic commit for the entire explicitly reviewed bounded set. Under deterministic
locks, revalidate actor, scope, binding version, source revision/order, export coverage,
dictionary/component pins, knowledge receipts, staging/review hashes and relevant projection/
call-off current state. Then apply projection changes, source-completion domain transitions,
issues/events and final audit in one transaction.

No failed record may be silently skipped after the user approved an all-or-none preview. If
management chooses partial per-record application, I07 must specify visible grouping, blockers,
retry/recovery, product aggregation and how users distinguish committed from refused rows.
The current per-record importer is not evidence that partial commit is approved.

Use canonical lock order for affected projection/service/request/negotiation/proposal/history
aggregates, sort stable IDs, and bound recognised MySQL retries around the whole idempotent commit.
An idempotency key may return a prior receipt only after current actor access is rechecked.
Never rerun an uncertain commit as a new approval. Recovery inspects receipts/effects and creates
an explicit corrective revision; it does not delete audit or restore from workbook assumptions.

## 19. Portal protections

- dictionary mapping only: PC1→Windows; CC1→Cavity Closers; CM1/CM2/CML→CML;
- no source code for Snagging; `CC!` remains occurrence-specific reviewed evidence;
- `complete=Yes` affects that resolved source part and may complete the current Portal request;
- no completion date is invented; operational date never becomes Requested/Agreed/completion;
- source completion closes open negotiation/amendment per current domain rules and sends no
  completion notification;
- reversal updates source projection/history and never silently reopens an old request;
- active customer request/date/history is never overwritten by generic spreadsheet dates;
- exact positive BF remains separately identifiable and applies the approved five-week rule;
- only approved product roll-ups become customer data; excluded/unknown codes remain private;
- no imported internal notes, statuses, stages, workflow or commercial fields.

The integration must use focused domain actions at the commit boundary rather than directly
mass-assigning protected Portal state. Existing outdated mapper/stage behaviour is a regression
target, not an allowed compatibility mode.

## 20. Storage, privacy and retention proposal

Private source artifacts use generated storage keys outside public storage, validated content
signatures and accepted Wald resource limits. Reject macro-enabled, encrypted, executable,
unsupported or dangerous archive/XML content through the frozen reader contract. Never execute
formulas, links or source text.

Approved WALD04 metadata periods remain: source workbook terminal +30 days, bulky observations
+7 days, preview validity 24 hours and preview payload +7 days; holds/dependencies extend them.
WALD05 must define terminal state, minimum retained evidence and failed/abandoned operation
clocks. Final staging/commit audit retention, raw-evidence relationship, backup expiry and the
named data owner remain I08/G09 decisions. No cleanup scheduler or delete action is approved.

Customer-safe history must not expose filenames, sheets, rows, source values or technical errors.
Office diagnostics remain scope-authorised, paginated and bounded. Logs contain safe identifiers
and exception classes only. Upload content must not be emailed or copied to SiteApp.

## 21. Proposed UI boundary

Use CustomerApp Blade/Tailwind/Alpine/Livewire conventions; do not copy SiteApp Filament UI.
The Office-only flow needs accessible, mobile-capable screens for intake, resumable status,
clarification, site binding, scope/revision confirmation, staging diff, explicit commit and result.

Buttons must name the action: answering does not save a profile, remembering does not approve
an import, review does not commit, and commit does not imply future automation. Use more than
colour for blockers/states, keyboard/focus-safe dialogs, large targets and bounded virtualised/
paginated record views. Polling cannot perform analysis or commit. No fabricated progress percent.

## 22. Migration, parity and fallback

Use forward additive migrations; never edit the 13 accepted migrations. Fresh SQLite/MySQL,
upgrade from accepted WALD04 with existing Portal/knowledge data, empty rollback/reapply and
populated rollback preservation are mandatory. A migration must not rewrite historical source
facts or import non-main profile/binding rows automatically.

Build known-answer neutral fixtures and compare a corrected current validated-record path and
Wald-derived path in separate disposable databases from identical fixtures. Compare identities,
quantities, missing/zero, completion/reversal, issues, projection state and immutable history.
The existing importer is not an oracle where it contradicts the current dictionary. Every
explained correction gets an explicit focused regression.

Fallback is to stop new workbook imports while the rest of CustomerApp continues. Do not bypass
review through console writes or fall back to the non-main upload UI. Disabling a pilot preserves
artifacts, staging, knowledge, commit receipts and prior Portal effects.

## 23. Required verification

Implementation evidence must include:

- unit/integration tests for artifact/revision/scope/binding/neutral record and all state changes;
- all four roles, inactive/preview/stale-role, null-organisation Office and full IDOR matrix;
- malicious filenames/cells/formulas/XML/archive limits and safe logging;
- moved/merged/multi-sheet/hidden/duplicate/unknown/unsafe workbook cases;
- every call/completion/product mapping, CC!, ZZ9, BF, excluded fields and exact quantities;
- partial/site/global scope and missing/zero preservation;
- stale artifact/revision/binding/profile/answer/review/current-state conflicts;
- exact idempotency and audit failure rollback for every mutation;
- browser-close resumption, dispatch failure, lease expiry, stale worker fencing and recovery;
- real MySQL races for claim/claim, answer/reanalysis, binding/review, review/commit,
  commit/customer action, completion/amendment and recovery/retry;
- query budgets, 50-profile overflow refusal, pagination and representative scale benchmarks;
- separate disposable-database parity and zero unexplained approved-domain differences;
- full accepted Wald/WALD03/WALD04 and CustomerApp regressions, Pint, Composer validation/audit,
  Vite build, diff/path checks and an explicit SiteApp-independence/static-import gate;
- manual Office browser accessibility/responsive checks using synthetic safe data;
- no production test, source workbook or real account without separate approval.

SQLite is not concurrency proof. MySQL must be version 8.4 on loopback with unmistakably
disposable database names and worker guards. Test-process memory changes must not alter production
limits. Failed attempts and inherited advisories are reported separately from final passes.

## 24. Proposed decision table — explicit approval required

| ID | Decision | Recommended V1 | Safe state until approved |
|---|---|---|---|
| I01 | Who may upload, clarify, review and commit; same actor? | Active non-preview Office only; distinct capabilities; same actor permitted for supervised V1 with explicit review/commit. | No routes or grants. |
| I02 | Source-site binding authority/lifecycle | Active Office; immutable versions, activate/revoke/reason; exact source key to existing org/site; used binding never moved. | No binding or site inference. |
| I03 | Source owner, revision and stale ordering | Name a Fenster source owner; require issuer-defined stable revision/order plus byte hash; exact replay idempotent, collision/older/unknown order blocks. | No commit. |
| I04 | Duplicate/multi-row `Call No.` grain | Require unique Call No.; all duplicates block until source owner documents stable subidentity/aggregation. | No collapse/last-row-wins. |
| I05 | Complete-snapshot absence effects | WALD05 V1 commit supports partial scope only. Site/global scope stays non-committable until source revision, dataset/grain and absence rules are approved; never delete/zero from unproved absence. | Preserve all absent facts. |
| I06 | Neutral staging/review authority | Immutable complete staging revision and current-state diff; all blockers resolved; separate explicit review. | `ready_for_staging=false`; no projection write. |
| I07 | Commit unit and recovery | Whole reviewed bounded set atomic; exact idempotency/current-state recheck; corrective successor after uncertainty. | No commit endpoint. |
| I08 | Final staging/commit audit retention | Propose six years as an operational policy for approval, not a legal assertion; holds/backup expiry/data owner explicit. | Retain; no purge scheduler. |
| I09 | Queue/storage operational owner and limits | CustomerApp-owned private storage and durable operation state. Local implementation may use the configured synchronous driver; commit is never queued. Persistent worker/supervision is required only before queued pilot/release. | Local/test only, no production worker. |
| I10 | Current importer/non-main disposition | Reimplement neutral/commit boundary; reuse safe tests/invariants; no wholesale merge; keep old path disabled from Wald until parity. | No direct call/cherry-pick/retirement. |
| I11 | Pilot and cutover | Default off; WALD06 supervised family/site pilot after WALD05 QA; retirement separately approved. | No live source routing. |

I01–I10 plus a separate explicit implementation instruction are WALD05 entry gates. I11 remains
a WALD06/pilot decision but its default-off boundary must be implemented and tested in WALD05.
Management may approve or amend recommendations by ID. A recommendation is not authority.

## 25. WALD05 implementation and exit gates

Before implementation:

1. accept immutable WALD04 output (satisfied by DEC-047);
2. approve/amend I01–I10 and name the source/revision and queue/storage operational owners;
   the G09 data-owner nomination may remain deferred while automatic disposal stays disabled;
3. approve the exact neutral schema, binding lifecycle and commit/recovery contract;
4. issue a separate explicit WALD05 implementation instruction and branch name.

Before declaring ready for dedicated QA:

1. complete the end-to-end synthetic milestone through explicit commit with no SiteApp access;
2. demonstrate honest clarification/refusal and no dictionary/tenancy/coverage invention;
3. pass SQLite plus disposable MySQL 8.4 schema, locking, recovery and concurrency gates;
4. prove partial-export preservation and customer date/history/source-completion precedence;
5. reconcile current/non-main importer behavior with no unexplained parity difference;
6. document operational limits, remaining production/pilot blockers and exact task files.

Dedicated QA, management acceptance, combined security/release reconciliation, WALD06 pilot and
production deployment are later independent decisions.

## 26. Scope-task verification and handoff

This scope task changes documentation only. It does not run application suites or claim a new
executable result. Required checks are baseline/ancestry verification, actual current/non-main
source inspection, contradiction/link/path review, runtime-diff confirmation and whitespace
validation. The accompanying acceptance report records exact files and checks.
