# CUSTOMER-WALD04 — Knowledge Profiles and Clarification Governance

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **SCOPED ONLY — governance decisions G01–G09 and explicit implementation approval required.**
No runtime, migration, model, UI, policy, job or import-commit implementation is authorised by this document.

## 1. Immutable entry baseline and evidence

Management accepted the **corrected** WALD03 snapshot:

| Identity | Frozen value |
|---|---|
| WALD03 accepted output / WALD04 input | `a80ce7d14206cf3f3a9343448d406f01ae927b88` |
| QA branch at acceptance | `qa/customer-wald03-2026-09-08` |
| Corrected executable/test commit within that snapshot | `f4fda0f069bd5106a125b42615ca212294a9dfad` |
| Original implementation candidate — NOT accepted output | `574f19694595f300620713b0dbd4a7d27036d7d1` |
| Dictionary version | `customerapp.source-dictionary.v1` |
| Dictionary fingerprint | `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` |
| Accepted WALD02 core | `4aa5ffb5a00527662ddfe66673edbfb18af9f0db` |
| Reader / XLSX and CSV adapters | `wald-0.2.1` / `2` |
| Structure / reasoning | `wald.structure.v1.1` / `wald-0.3.0` |
| Rules / confidence policy | `wald.generic-rules.v1` / `wald.confidence.v1` |
| Generic core Git tree | `30d1fc65e575242004eb335ad46a4d8ec920127a` |

Accepted [QA evidence](../wald/customer-wald03-qa-2026-09-08.md): WALD03 470 passed / 2,550
assertions; combined Wald 678 / 3,812; full CustomerApp 897 passed, 15 existing environment
skips / 5,000 assertions. These are attributed QA results, not new tests run by this scope task.

Preserve W3Q-01 (P1 precision-dependent quantity corruption), W3Q-02 (P2 missing confidence
version validation) and W3Q-03 (P2 contradictory constructor states). No rounding of invalid
quantities, fabricated resolved results, weakened manifest checks, ambiguity loss or raw-value
loss is allowed. The dictionary definition/fingerprint did not change when these defects were fixed.

Authority: latest user acceptance, DEC-045, current [brief](../../brief.md),
[source contract](../source-integration-contract.md), [business dictionary](../siteapp-import-data-dictionary.md),
[WALD01](WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md) and
[accepted WALD03 contract](WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md).
This bounded scope supersedes WALD01's broader WALD04 session/source/staging-schema suggestion:
only minimal knowledge review context is proposed here; upload/orchestration/staging/commit remain WALD05.

## 2. Purpose and decision status

Remember explicitly reviewed interpretation safely for a later compatible workbook:

Wald observation → WALD03 interpretation → clarification → authorised answer →
optional draft profile → explicit activation → scoped compatibility check on fresh evidence.

Wald infers structure. The controlled dictionary defines meaning. Authorised humans resolve
genuine ambiguity. Remembered knowledge never supplies business truth, tenancy, source
completeness or permission to commit.

The following distinction governs the whole package:

- **Accepted now:** WALD03 output and invariants; architecture-only scope task; no implementation.
- **Existing approved policy:** four Portal roles; Office global access including null organisation;
  external active-account/organisation/assigned-site containment; private evidence; dictionary authority.
- **Proposed V1 design:** all knowledge-specific grants, lifecycle, retention and persistence below.
  They become an implementation contract only after explicit approval of the decision table.
- **Deferred:** global learned knowledge, source-site binding runtime, import commit and Office import UI.

## 3. Bounded architecture

Keep `App\Wald` and `App\SourceImport\Semantics` pure. Proposed application-owned
`App\SourceImport\Knowledge` contains deterministic compatibility/answer contracts;
repositories and focused Actions/Policies provide persistence and authorisation outside the core.

Reuse the frozen WorkbookProfile, ReasoningResult, ObservedCell and dictionary/result contracts.
Existing RuleContextProvider, rule registry and provenance-verifier extension points may be
evaluated without changing core rules or confidence values. The accepted baseline does **not**
already contain a complete persisted profile system: do not pretend the broader SiteApp profile
directories were adopted. Any additional upstream adoption requires a separately approved
path/hash inventory and parity review; no SiteApp access or copy is needed for this scoped model.

Proposed operations, all backend contracts rather than new endpoints:

- register immutable, server-validated knowledge-review context from trusted analysis evidence;
- record a one-time answer, with an expected context generation and question revision;
- create a profile draft from an eligible structural answer through a separate explicit action;
- activate/supersede/revoke an exact profile version;
- evaluate compatibility and resolve current locators, with full veto/permission checks;
- record a reuse receipt and append-only audit event.

No boolean `approved=true` from a client can unlock a blocked WALD03 result. Do not mutate a
ReasoningResult decision to accepted, recompute a hash to disguise an edit, or strip clarification
flags. Proposed `ReviewedInterpretation` is a separate envelope retaining original blocked
results, current evidence and the authorised decision/provenance. A known correction is
re-evaluated through the controlled dictionary as a distinct canonical value; raw source remains
unchanged. Structural selection is a governed selection of a current candidate, not a claim that
Wald independently selected it. Unresolved safety errors remain blocked. All envelopes remain
`ready_for_staging=false`; WALD05 must separately prove record validation/review readiness.

Proposed roles are limited to existing source-contract concepts: call reference, plot reference,
call type, completion, approved product-code quantity, source-site clue/identity, operational
PC1 date and ignored fields. No arbitrary role, date authority, new service or business code.
Unknown required roles remain blocked. A profile can bind a reviewed heading to an existing
role, never define a new role or change an existing heading's business meaning.

## 4. Ownership, scope and tenancy — proposal G01/G03

Fenster is knowledge custodian; the relevant CustomerOrganisation owns the tenant containment,
not the individual author. A source site and workbook family are narrowing dimensions, not
alternative owners. The author's departure does not transfer scope or erase audit history.

| Scope concept | Proposed V1 treatment |
|---|---|
| SYSTEM | Code-reviewed dictionary/structural defaults only; no runtime promotion of learned customer knowledge. |
| CUSTOMER_ORGANISATION | Required owner of every persisted learned record. Organisation-wide reuse disabled in V1. |
| SOURCE_SITE | Require one existing Portal site belonging to that organisation; operational source ID remains separate. |
| SOURCE_PROFILE / WORKBOOK_FAMILY | Require server-controlled source namespace plus family identifier and immutable profile version within that organisation/site. |
| ONE_TIME | Default answer scope: exact context generation, checksum, selected table/row/cell and question. No future reuse. |

V1 reusable scope is the **intersection** organisation + existing site + source namespace +
workbook family. Same headers, filename, source namespace or global Office access never broadens
it. A family is registered by an authorised actor and verified against fresh structure; a workbook
cannot announce its own trusted family/tenant.

An Office actor with null organisation is valid but must explicitly select the target
organisation/site through authorised server context. Never substitute a sentinel organisation
or treat nullable ownership columns as a wildcard. The current Site model has no public UUID;
use its established server-authorised context, not a copied non-main Site UUID migration.

External Site Users retain current Portal access only. Proposed V1 gives them no private
knowledge/evidence access or answer/profile-management abilities. If later enabled, every
operation must additionally enforce active account, matching organisation and current site
assignment; Portal workflow permission alone is insufficient. Multi-site workbooks cannot
bootstrap tenant ownership; unresolved/mixed scope blocks the dependent knowledge operation.

## 5. Explicit ability matrix — proposals, not grants

All entries require real active non-preview accounts and fresh server-side authorisation.

| Operation / proposed ability | External Site User | Fenster Office Staff — proposed V1 |
|---|---|---|
| Read raw evidence / `view-wald-evidence` | Deny | Allow only selected authorised knowledge context. |
| Answer once / `answer-wald-clarification` | Deny | Allow selected question/candidates; reason required for an override/correction. |
| Save reusable draft / `create-wald-profile` | Deny | Explicit action, structural knowledge only, scope cannot widen. |
| Approve/activate / `activate-wald-profile` | Deny | Explicit second action reviewing exact draft hash and site/family scope. |
| Reuse / `use-wald-profile` | Deny direct access | Backend acts for currently authorised Office user; scope and fresh evidence checked each use. |
| Revoke / `revoke-wald-profile` | Deny | Any authorised active Office actor may revoke within selected scope, with reason; not creator-only. |
| View audit / `view-wald-knowledge-audit` | Deny | Scoped Office view; no exposure through customer history/notifications. |
| Bind source site / `manage-source-site-binding` | Deny | Recommended Office ownership, but runtime deferred to separately approved WALD05 binding contract. |
| Global/organisation-wide activation | Deny | Disabled V1; separate governance approval, not an implied Office privilege. |

Recommend same Office actor may answer, draft and explicitly activate a **site/family-only
structural** profile; distinct audited actions, no automatic save-on-answer. This is the
practical V1 proposal G02, not a two-person control. If management requires dual approval,
activation must verify a different current Office actor before implementation. Do not invent a
fifth Portal role. Any later broader activation needs separate approval and a second reviewer.

Review/reuse rechecks the actor and target scope at execution and immediately before persistence.
Current authorisation controls use; historical role snapshots prove who acted, not present access.
No queued system superuser or stale role snapshot bypass is allowed.

## 6. Distinct knowledge types and what may be learned

| Type | May retain | May affect | Lifecycle |
|---|---|---|---|
| Structural profile | Selected table signature, header topology, coordinate-independent column selectors, approved aliases to existing roles, exclusions and selector constraints. | Role selection for a unique compatible current target, never source values or confidence fabrication. | Draft/version/explicit activation/revocation. |
| Semantic confirmation | Exact ambiguous occurrence, selected permitted interpretation, raw and canonical values, dictionary entry, actor and reason. | That occurrence's reviewed interpretation only. | Answer + immutable successor/correction history; no cross-import semantic alias in V1. |
| Source-site binding | Source namespace and stable source Site ID to existing Portal site; exact Site Name only transitional. | Destination identity, not structural meaning or completeness. | Separate ownership/version/conflict/audit contract; runtime deferred to WALD05. |

Structural example: “the unique Call Type header in family X represents the call-type role.”
Do not store just “column D”: coordinates are resolved anew from deterministic selectors.

CC! → CC1 is proposed as an **occurrence-specific** correction only (G08), anchored to checksum,
context generation, row/cell, raw value and offered dictionary suggestion, with reason and actor.
Original CC! remains INVALID; the separately reviewed canonical CC1 is dictionary-validated.
The next CC! occurrence asks again. It never becomes a global or family-wide alias.
If structure itself is ambiguous, resolve that first; a value answer does not identify its column.

ZZ9 cannot become Windows merely because an actor selects a service. Record unresolved/rejected
or a non-effective proposal for separate dictionary governance; a code/version/fingerprint
release is required before reuse. Likewise PC1 → Cavity Closers conflicts with dictionary truth:
reject the attempted answer and disqualify conflicting legacy knowledge. Profiles cannot alter
completion vocabulary, product membership/precision, ignored fields, operational-date meaning,
BF lead-time policy or export scope. One-time stronger coverage assertions remain WALD05 review
inputs, never learned settings.

## 7. Deterministic family signatures and compatibility

Version proposed algorithms as `customerapp.wald-profile-signature.v1` and
`customerapp.wald-profile-compatibility.v1`; these are design identifiers, not implemented code.
Use canonical associative-key ordering and explicit list order. Retain the descriptor beside its
SHA-256; hash equality alone does not authenticate origin or replace descriptor/scope checks.

Descriptor inputs from the current complete Wald profile:

- reader capability/format class, selected visible sheet label and orientation;
- header path/depth, merge topology relative to the selected header, nonblank column sequence;
- normalized header tokens with punctuation and duplicate multiplicity preserved;
- explicit selected role/product/unit/date-format constraints, when genuinely declared;
- relevant hidden/repeated/multiple-table structure and selector uniqueness.

Structural-only normalization is versioned: surrounding trim, ASCII case folding and collapsed
header whitespace; raw headings remain audit evidence. No fuzzy spelling or punctuation removal.
This does not change WALD03's business-code/header normalization. Aliases are explicit reviewed
selectors and cannot redirect a known dictionary heading to contradictory meaning.
Reusable aliases apply to structural role headings, not source business-code values or unknown
product-code headings. Mapping an unknown quantity code to BF/VS would create business meaning,
not merely identify a column, and therefore requires the separate dictionary-governance process.

Exclude filename, upload time, actor ID, absolute row numbers, data-row count, source cell values,
customer names in data rows, product totals and raw sample values from reusable signatures.
Sheet/header labels may themselves contain customer information, so signatures/descriptors remain
private and tenant-contained. Current value-shape checks can veto reuse but are not copied as
customer row data into the family identity. Absolute cell/source locators stay in occurrence
evidence, not the reusable selector identity. Blank/title insertions can move coordinates without
moving semantic roles; verify the newly detected table/header before rebinding.

| Compatibility | Deterministic condition | Permitted result |
|---|---|---|
| EXACT_MATCH | Same supported versions, descriptor/fingerprint, scope, active immutable definition; unique current sheet/table/header/column selectors; no contradictory fresh evidence. | May reuse the explicitly activated structural choices, record receipt; no automatic import approval. |
| COMPATIBLE_WITH_REVIEW | Same unique required header/role set but reordered columns, recognised alias/rename, supported changed header topology, optional additions or worksheet rename. | Suggestions only; require a fresh one-time review. Changed structure is not silently installed as a new profile version. |
| INCOMPATIBLE | Missing/renamed unknown critical column, changed declared units/meaning, unsafe/unsupported input, duplicated selector, conflicting role or material shape contradiction. | No application; new clarification/profile draft if appropriate. |
| STALE_VERSION | Dictionary, reader/core/rule/confidence, selector, profile schema or matcher version is unsupported/changed. | No reuse pending explicit compatibility review and successor activation. |

Two competing tables, House No./Sales Plot ambiguity, or two equally applicable active profiles
always remain clarification-required, even if one fingerprint matches. Sort candidates for
stable display only; never choose by newest ID, score, author or creation date. A profile does
not prove the absence of a second plausible candidate. A complete current analysis is required.

A materially different workbook is an INCOMPATIBLE **match**, not automatic global deactivation
of an otherwise valid profile. STALE lifecycle is reserved for version/policy invalidation or an
explicit reviewed obsolescence event. No percentage similarity threshold grants reuse.
Profile candidate queries are first scope-filtered and bounded; an exceeded candidate/analysis
budget refuses or asks for narrower scope, never silently drops plausible competing candidates.

## 8. Dictionary, core and profile version compatibility

Persist the dictionary pair, complete component/version tuple, profile definition hash/version,
matcher/selector/schema versions, accepted baseline/build trace and current source/run hashes.
Git SHAs identify audited builds; do not use them as database foreign keys or the only compatibility
rule. Crucially, a build using unchanged version labels is not automatically trusted: compatibility
policy is code-owned and allowlists verified executable implementations. Only the corrected
WALD03 line is initially eligible; original 574f196 is explicitly excluded.

| Change | Result |
|---|---|
| Dictionary version and fingerprint unchanged; supported corrected implementation | Eligible for structural matching, not automatic permission. |
| Dictionary version changed (even if content hash same) | STALE_VERSION; deliberate successor review and revalidation. |
| Fingerprint changed at same version | Integrity/contract error; block, record safe diagnostic, do not bless by profile activation. |
| Version and fingerprint both changed | STALE_VERSION; validate mappings against the new dictionary, create successor; no silent reinterpretation of history. |
| Reader, adapter, structure, reasoning, rules or confidence version changed | STALE_VERSION until explicit code-reviewed compatibility policy and regression evidence approve it. |
| Signature/matcher/schema/selector version changed | STALE_VERSION; no opportunistic coercion of serialized payloads. |
| Git SHA changed but executable definitions/versions are identical (e.g. docs-only) | May remain compatible only when verified/allowlisted; keep both build traces. |
| Active profile version changed/revoked during work | Stale dependency receipt; re-evaluate before any further authorised use. |

Record current Wald evidence/confidence separately from matched knowledge/provenance. Do not
inflate confidence to 100, label it probability or overwrite fresh evidence. Current safety
contradictions veto stored knowledge. Active semantic knowledge contradicting the dictionary
must be unusable immediately and recorded stale/error; no background invalidator is necessary
for correctness because every read/use must evaluate current pins.

## 9. Precedence and reviewed-result contract

Precedence is conditional, not “manual always wins”:

1. Access, source-integrity, type-safety and controlled dictionary invariants are hard gates.
2. A current authorised occurrence-specific answer may select among valid current candidates.
3. A unique compatible ACTIVE structural profile may supply a remembered selection.
4. Fresh Wald inference remains independent evidence; unresolved conflicts ask again.

A current answer can resolve genuine structural ambiguity but cannot suppress a corrupt source,
unsupported cache, dictionary conflict, missing required meaning or wrong tenant. The same
limitations apply to saved answers. Fresh ambiguity after structural divergence needs fresh
confirmation. Every reviewed result retains original candidates, selected candidate IDs, current
confidence, knowledge UUID/version, compatibility category/reasons, current actor authorization
context, answer/reuse receipt and validation outcome.

## 10. Clarification and evidence record contract

Use existing integer primary key + server-generated public UUID convention for new externally
addressable entities; do not retrofit unrelated existing tables. Proposed question fields:

- context UUID, immutable generation, clarification type/schema, question revision and requiredness;
- owner organisation/site/source namespace/family; source checksum and explicit provenance handle;
- current sheet/table/header/row/cell references and exact candidate IDs, labels and evidence refs;
- original raw ambiguous value where necessary, lookup value and original WALD03 classification;
- dictionary version/fingerprint, full core/adapter tuple and accepted build trace;
- current structure/descriptor hash, semantic output hash and evidence payload hash;
- expected answer sequence/current status, expiration and retention class.

Answers are append-only: selected candidate/canonical value (or unresolved/reject), actor ID and
role snapshot, server UTC timestamp, internal_reason, reusable=false by default, predecessor/
supersedes reference, command UUID/idempotency hash and authorization-policy revision.
Record activation and revocation as separate events, not edits to the historical answer.
Reasons are private, never customer_response; do not store arbitrary operational notes.

Persist sufficient selected headers, coordinates, raw ambiguous occurrence and concise supporting/
contradictory evidence, not every row or a whole workbook embedded in JSON. Prefer one protected
evidence payload referenced by question/answer/profile version; pin its hash and schema.
Keep occurrence-level raw text and comments in retention-governed payloads rather than duplicating
them indefinitely across immutable event rows. Stable decision IDs, actor/role references and
before/after hashes retain traceability when an authorised evidence-removal event is later recorded.
Proposed bounds: 64 KiB per question evidence envelope, 50 candidates, 2,000 characters per
review comment, 256 KiB per profile definition. Larger evidence requires an explicit bounded
artifact reference or safe refusal, never silent truncation of required candidate evidence.
Hashes are integrity pointers, not recoverable replacements for destroyed raw evidence.
If evidence expires, report “original replay unavailable”; never claim full reproducibility.

## 11. Proposed persistence model — no DDL yet

Separate mutable coordination from immutable interpretation and audit. Names are proposals.

| Entity/table | Proposed fields and responsibilities |
|---|---|
| `wald_knowledge_contexts` | UUID, required organisation/site, namespace/family, immutable source checksum + analysis/semantic hashes and component pins, generation, source evidence reference, state/lock_version/expiry. Minimal knowledge context only; no uploaded-file/queue/import-run lifecycle. New generation is a successor, not rewritten evidence. |
| `wald_clarifications` | Context FK, immutable question/candidate/evidence snapshot/hash, type/revision, mutable current-answer pointer/sequence and status for coordination. Unique context + generation + question key. |
| `wald_clarification_answers` | Immutable typed decision, exact question revision, actor/role/time, raw/canonical distinction, reason, predecessor and command hash; unique clarification + sequence and scoped idempotency key. |
| `wald_profiles` | Required organisation/site/namespace/family, type=STRUCTURAL, root UUID, current lifecycle, active version pointer, monotonic lock_version; no nullable global scope. |
| `wald_profile_versions` | Profile FK + unique version, immutable selectors/aliases/descriptor/hash and dictionary/core pins, originating answer/evidence references, creator/time, supersedes version. No in-place mapping edits. |
| `wald_knowledge_events` | Append-only actor/role/time, action/sequence, before/after references and hashes, reason, context/question/profile references, command UUID, policy version; scope mandatory and consistent. |
| `wald_profile_uses` | Immutable context generation + profile version + compatibility and current evidence hash + actor/policy/epoch receipt. Records use, not authority to commit; one idempotent receipt per exact input tuple. |
| `wald_knowledge_evidence` | Separate bounded private evidence payload, hash/schema, retention class, expires_at/hold state; controlled evidence removal leaves a tombstone and immutable removal event. Raw-free descriptors needed for active reuse remain available. |

No `wald_profile_bindings` table is needed for the proposed single-site/family V1 root.
Do not overload profiles with source-site bindings. A separate future binding/version store
must model namespace + durable source identity → existing site and its audit, independently
of profile lifecycle. No binding schema or projection FK is implemented by WALD04.

Use restrictive FKs for immutable history and deliberate indexes for scoped lookup/status,
profile-version uniqueness and question/answer sequencing. Validate tenant/site consistency on
every relationship; where supported use composite keys/FKs backed by unique parent keys rather
than trusting duplicated owner columns. Do not add broad cascades or use nullable unique keys
as the sole active-profile constraint. Root lock + unique version + active pointer handles activation.
Authoritative fields are assigned by Actions, never mass-assigned from request arrays.

Follow existing HasUuid, enum casts, append-only history guards and focused Action conventions.
Model hooks alone are not an authorization boundary; service/repository writes must enforce
immutability, and tests must cover bulk-query bypass risks. No historical migration edits.
Future migration rollback must refuse to destroy populated audit/knowledge stores; rehearse
SQLite and disposable MySQL 8.4. User deletion must not erase attribution: restrictive audit FK
plus retained minimal actor snapshot pending a separately approved privacy/offboarding process.

## 12. Lifecycle and revocation — proposals G02/G06

One-time question: OPEN → ANSWERED or UNRESOLVED; stale generation → SUPERSEDED/EXPIRED.
An answer is never overwritten; correction appends a successor with a reason.

Profile: DRAFT → ACTIVE through explicit activation; ACTIVE → STALE or REVOKED;
a replacement version explicitly supersedes the active pointer and preserves the old version.
No active mapping edits and no automatic promotion from answer, similarity score or frequent use.
PENDING_APPROVAL is unnecessary for proposed single-actor site-only V1; introduce it explicitly
if management selects dual approval. REVOKED versions cannot be reactivated; create a reviewed
successor linked to the revoked version, not a new anonymous copy that conceals its history.

Revocation atomically increments the root epoch, removes future active eligibility and appends
actor/reason/history. No permission to rewrite old answers, reuse receipts or final import history.
Every subsequent match/reuse checks status and epoch; cached receipts cannot keep a revoked profile
alive. Historical uses remain truthful. A review receipt pins the exact version/epoch; WALD05 must
revalidate at its final commit boundary, never rely on a prior “valid” preview flag.

## 13. Retention — proposals G04/G05/G09, NOT legal requirements

These are operational recommendations for approval, not claims about law or existing deletion.
No purge runs or storage changes are authorised by this document.

| Record class | Proposed V1 retention / expiry | Boundary |
|---|---|---|
| Uploaded source workbook | 30 days after terminal processing; abandoned work terminates after 30 days inactivity, then retention clock starts. | WALD05 storage/cleanup; no indefinite upload retention assumed. |
| Parsed observation / bulky analysis | 7 days after terminal processing; minimum selected evidence moved to knowledge audit before eligibility for deletion. | WALD05 processing artifacts, not authoritative profile definitions. |
| Import preview | Valid 24 hours maximum; invalidate immediately on changed pins; discard bulky preview at 7 days after expiry/terminal processing. | WALD05 only; preview TTL does not define audit retention. |
| Clarification answers/events and necessary minimal evidence | 24 months after context closure, extended while an ACTIVE profile references the necessary evidence; then 24 months after its last dependent profile retirement/use, whichever is later. | WALD04; active knowledge cannot outlive its required approval provenance. |
| Reusable profile/version/use receipt | Review/reapprove at 12 months; due date makes it STALE for new reuse. Retain active profile and required provenance; retired/revoked history retained 24 months after last use/retirement, whichever is later. | WALD04; a reuse does not silently extend approval for another year. |
| Final import audit | Propose six years after commit/corrective chain closure; actual requirement must be approved before WALD05. | Separate WALD05 decision; this is not a statutory assertion. |

Earliest deletion is always delayed by a documented hold or a longer dependent audit requirement.
If final audit will need raw evidence longer than the proposed knowledge period, WALD05 must
choose a minimized retained snapshot or explicit extended retention before commit. Do not
silently keep entire workbooks because a profile references selected headers.

After approved retention ends: erase only eligible raw payloads/processing artifacts under an
audited cleanup action; preserve a minimal tombstone/decision identity until the approved audit
schedule permits further disposal. Redaction/erasure is a separate accountable event, not mutation
of prior decision meaning. Approval must cover the minimal actor-identification/tombstone fields,
backup expiry and hold owner; do not promise indefinite immutable personal data.

Recommend nominated Fenster data owner authorises holds/extensions and disposition; operations
executes controlled cleanup. These responsibilities and periods remain G09/G04/G05 proposals.
Deletion must use registered private artifact IDs, never user paths; retry safely, record actual
success, and retain “pending removal” on failure. No active evidence is removed mid-transaction.
Backups require a matching documented expiry/restore reapplication process before production.

## 14. Atomicity, idempotency and concurrency

Answer+its provenance+audit+current-answer pointer are one transaction. Explicit “save draft”
and activation are distinct later commands, not partial success of an answer: each has its own
atomic definition/provenance/event/pointer updates. An optional combined answer-and-draft command
must create both or neither; it still does not activate. Reuse validation+receipt is atomic.

Proposed short lock order: current actor row → owner organisation → profile roots (ascending IDs)
→ knowledge context → clarification → append-only answer/version/event/use rows.
Only acquire relevant locks; revocation need not lock every historical context. Reuse is checked
against the profile epoch. Creation uses the existing organisation row as a bounded serialization
anchor plus unique constraints, not a lock on a nonexistent profile. No global Office/tenant-wide
long-running analysis lock. Perform file/profile analysis outside DB locks, then recheck exact
hashes/generations/role/scope under locks. Do not mix this order with call-off aggregate locks;
WALD04 never locks or writes those aggregates.

| Race / failure | Required outcome |
|---|---|
| Two Office users answer the same question revision | First committed answer wins; second receives stale-question conflict and fresh state, not silent overwrite. |
| Same command retried | Same scoped actor/command key and payload hash returns original receipt; different payload with same key conflicts. |
| Two profile versions activate | Expected lock_version and active pointer serialize; loser conflicts. |
| Profile revoked while a context uses it | Transaction order determines whether receipt precedes revocation; after revocation no new use; prior receipts fail fresh epoch validation. |
| Profile version changes during later preview | Preview pins exact version/epoch; subsequent use/commit must stop and re-review. WALD04 supplies receipt-check contract, not preview/commit implementation. |
| Dictionary/core compatibility changes during review | Recheck code-owned compatibility identity at write/use; stale command conflicts. Deployment must drain incompatible in-flight writers before enabling new code. |
| Audit/provenance insert fails | Entire mutation rolls back; no answer/activation without its evidence/event. |
| Retention expiry races with use | Evidence availability/expiry checked under coordination locks; protect active dependencies; cleanup retries. |
| Transient deadlock/serialization error | Bounded retry of the complete idempotent transaction (propose three attempts); never retry a stale decision as a new approval. |

MySQL 8.4 multi-connection tests must establish these outcomes; SQLite alone is insufficient.
No attempt to implement import commit atomicity or recovery is included.

## 15. Security and privacy acceptance

Deny unauthenticated/inactive/preview/wrong-role actors. UUID knowledge does not grant access.
Resolve scoped parents before descendants; never accept client owner IDs, role snapshots,
activation status, current-answer pointers, fingerprints, arbitrary classes or storage paths.
Recompute candidate/definition hashes server-side from registered trusted evidence. Source
checksum alone is not authenticity and a client-supplied ReasoningResult is never trusted.

Reject mismatched checksum/sheet/region/generation, candidate substitution, dictionary override,
cross-tenant family collisions and replayed incompatible receipts. Reads, lists, search counts,
error paths and audit views must all enforce the same scope; cache keys include owner/site,
namespace, profile version and epoch, never just a fingerprint.

Labels/comments/cell contents are inert strings: no eval, dynamic class lookup, expression
execution, template execution, formula evaluation or external AI. Escape future presentation;
safe diagnostics omit raw values, paths and private evidence. No source evidence in customer
notifications, Portal history or generic logs. Prefer structural descriptors over row samples;
only retain an ambiguous value when necessary to explain the decision.

## 16. Parallel non-main profile work — inspected, not adopted

Inspected Git chain `04b560fb36fd61dd4c2ed981a2b0a5292d29fac1` →
`a013ed1bb7fe8a25f98f226c1a08f71684679bd3` →
`1e8c22bfd5021b82b9775892ebddb192cb173e16`. Paths below exist in that Git snapshot,
not necessarily this checkout. No branch merge/cherry-pick, runtime copy or data migration.

| Evidence at 1e8c22b | Finding | WALD04 disposition |
|---|---|---|
| `app/Services/WorkbookInterpretationProfileService.php`, `app/Models/WorkbookInterpretationProfile.php` | Match queries namespace + numeric semantic_version, exact first match/latest version; likely search considers latest 50 profiles with score ≥65. No organisation/site containment, ACTIVE/revocation lifecycle or immutable event stream in that service/schema. | REIMPLEMENT scope-first deterministic matching and immutable version intent; SUPERSEDED global namespace/score/first-winner behaviour. |
| `database/migrations/2026_08_28_000010_add_deterministic_workbook_profiles.php` | Profile root/version combined; confirmed user and JSON mappings/type ratios; unique namespace/fingerprint/version, not a tenancy grant. Also modifies preview/projection tables. | REIMPLEMENT normalized independent root/version/history design. Do not cherry-pick mixed migration. |
| `database/migrations/2026_09_02_000011_version_siteapp_import_semantics.php` | Numeric semantic_version added; config at this line uses version 3. | SUPERSEDED compatibility identity; use frozen dictionary pair/component tuple. Old rows are not silently upgraded to v1. |
| `app/Services/ManualSourceImportService.php::confirmMapping` | Mapping confirmation automatically saves reusable profile inside locked preview transaction. Exact profile may be used during adaptive preview; likely match asks for confirmation. | REUSE negative/stale test intent; REIMPLEMENT explicit answer/draft/activation separation; automatic learning SUPERSEDED. |
| Same service commit checks and `ManualSourceImportFingerprintService` | Content/mapping/source fingerprints and relevant binding checks refuse stale preview; owned Office preview and transaction boundaries are useful. | REUSE bounded stale/idempotency scenarios; REFERENCE_ONLY commit implementation for WALD05. |
| `app/Services/SourceSiteBindingService.php`, `app/Models/SourceSiteBinding.php`, migration `2026_08_28_000009_add_manual_source_import_contract.php` | Exact namespace/key hash, existing site, Office/non-preview guard and refusal to move bindings with projected plots. Binding update is mutable and lacks a distinct version/revocation event lifecycle. | REIMPLEMENT invariant intent in separately approved binding contract; runtime REFERENCE_ONLY/deferred WALD05. No copy of Site UUID or projection alterations. |
| Migration `2026_09_02_000012_add_siteapp_export_scope_contract.php` | Updates profile snapshot_scope to partial and adds preview/run/projection fields. | REUSE partial-default test intent; profile activation must not grant snapshot coverage. Mixed migration SUPERSEDED as WALD04 input. |
| `tests/Feature/DeterministicSpreadsheetInterpretationTest.php`, `SiteAppImportSemanticCorrectionTest.php`, `ManualSourceImportBackendTest.php` | Exact/reordered/changed profile, stale numeric semantic version and stale binding scenarios exist. | REUSE synthetic scenario intent; rebuild assertions for scoped/versioned governance and retain frozen WALD03 matrix. |
| `ManualSourceImportPageController`, source-import Blade/JS and UI tests | Full synchronous Office upload/review/commit route. | REFERENCE_ONLY for WALD05; not WALD04 UI or authority. |

Current-repo convention references: `app/Models/User.php` active Office/null-organisation
and site-role logic; `app/Models/Site.php` organisation/assignment relationship;
`app/Models/CallOffStatusHistory.php` and `app/Actions/CallOff/RecordCallOffStatusHistoryAction.php`
append-only history, sequence, actor, before/after and private reason patterns. Reuse conventions,
not call-off tables/events for import knowledge. No non-main stored data is presumed present
or migrated; any later actual data inventory requires separate authority and a preservation plan.

## 17. Required future implementation tests

| Area | Required positive and negative cases |
|---|---|
| Frozen semantics | All 470 accepted WALD03 cases, precision 3/14/17, confidence-version and constructor invariants; no core/business definition changes. |
| Matching | Exact, moved header/title rows, reordered headers, optional columns, approved alias, unknown rename, missing critical header, duplicate heading, changed units, hidden/multiple tables and equal profile candidates. |
| Versions | Same dictionary pair; changed version; same-version changed hash; changed reader/confidence/core/schema/selector; docs-only build vs original defective 574f196; stale profile epoch. |
| Tenancy/security | Same fingerprint across organisations/sites, IDOR by UUID, mixed parent/child scope, source-family spoof, malicious labels/comments, mass-assigned grants, null-org non-Office denial. |
| Roles | All three Site User roles denied knowledge operations under proposed V1; active Office with and without organisation; inactive/preview/revoked Office denied; audit list/detail equally scoped. |
| Answers | One-time default, exact candidate constraints, raw evidence, repeat command idempotency, conflicting command, stale generation, invalid argument/oversize refusal, immutable correction chain. |
| Semantic learning | CC! exact occurrence to CC1 preserves both raw/selected values; different occurrence asks again; ZZ9 cannot create business truth; PC1 cannot change service; no learned coverage or dates. |
| Activation | No save-on-answer; explicit draft and activation; expected definition hash; same/different actor according to approved G02; no wider scope activation; immutable version replacement. |
| Revocation | Future reuse stops, stale receipt cannot pass revalidation, historical use/audit retained, revoked version cannot reactivate anonymously. |
| Persistence | SQLite fresh/additive upgrade and populated rollback protection; MySQL 8.4 FK/uniqueness/null/index/collation/JSON behaviour; two-user answer and activation races, revoke/use and retention/use races. |
| Atomicity | Inject answer/event/provenance/activation failures; zero partial success; bounded deadlock retry does not duplicate history; no last-write-wins. |
| Retention | Frozen test clock at exact expiry; active dependency and hold protection; raw payload removal/tombstone/audit; replay explicitly unavailable; cleanup failure/retry and backup restoration plan. |
| Determinism/bounds | Same descriptors/current inputs/pinned policy produce same compatibility and reasons independent of process/order/locale; timing/UUIDs excluded from semantic hash. Scope-first query budget and too-many-candidates refusal. |
| Non-goals | No projection/source-completion/product/workflow writes, upload/commit endpoint, Office UI, SiteApp runtime, new dependency, external AI or unapproved global knowledge. |

After governance approval and implementation: focused tests, full Wald, full CustomerApp,
Pint, Composer validation/audit, build and diff checks; disposable MySQL evidence required for
persistence/concurrency. Retain exact commands/results and separate inherited advisories.
No test suite is invented or claimed to exist for this planned WALD04 scope.

## 18. Governance decision table — explicit approval still required

| ID | Decision | Recommended V1 | Approval still required? |
|---|---|---|---|
| G01 | Who may answer/save/use/view knowledge? | Active non-preview Office only; Site Users no new grant; selected tenant/site context always required. | Yes |
| G02 | Who may approve/activate; separation of duties? | Same Office actor may explicitly activate a site/family structural draft; no automatic activation. Broader scope disabled. | Yes |
| G03 | Tenant/global ownership and reuse scope? | Organisation-owned, narrowed to one existing site + namespace + family. SYSTEM learned and organisation-wide reuse disabled. | Yes |
| G04 | Workbook/observation/preview retention? | 30 days workbook after terminal state; 7 days bulky observations; 24-hour preview validity/7-day cleanup, with holds. | Yes, before affected WALD05 storage |
| G05 | Profile/clarification retention and review? | 12-month profile reapproval; active provenance retained; retired knowledge/answers 24 months after latest dependency/use. | Yes, before WALD04 persistence |
| G06 | Revocation and audit visibility? | Any currently authorised Office actor may revoke in selected scope with reason; Office-only audit, no history rewriting. | Yes |
| G07 | Source Site ID binding ownership and delivery? | Office-controlled separate binding lifecycle; defer runtime to WALD05; no fuzzy/automatic site creation or rebinding. | Yes |
| G08 | Semantic confirmations / non-main reconciliation? | CC! occurrence-only correction, no reusable value aliases or ZZ9 service definitions; accept §16 dispositions, no legacy data auto-import. | Yes |
| G09 | Hold, final audit and disposal governance? | Nominate Fenster data owner; approve minimal retained identity/backup expiry; proposed six-year final audit reviewed before WALD05. | Yes |

G01/G02/G03/G05/G06/G08 and the WALD04-relevant evidence/hold portions of G09 are hard
implementation-entry choices. G04, final-import audit in G09 and binding runtime G07 remain
WALD05 gates; approve their deferral explicitly rather than silently implementing them now.
Management may approve or amend recommendations by ID; only approved answers become authority.
After decisions are recorded, issue a separate explicit WALD04 implementation instruction.

## 19. Non-goals, delivery and WALD05 entry

WALD04 does not implement: upload/session UI, full Office Import Studio, import preview/commit,
source projection, call-off completion, reconciliation, missing-record handling, product/service
writes, date workflow, operational rules, notification rollout, source-site binding runtime,
global knowledge sharing, dictionary CRUD, source scheduling, production deployment or dependency
remediation. Minimal future knowledge administration presentation needs its own UI approval.

Future implementation uses a new non-deploying branch from accepted a80ce7d with this approved
metadata; no main action or wholesale non-main import merge. Stage contracts, persistence,
authorised Actions, tests and evidence separately. No migration until scope/governance approval.

WALD05 entry requires:

1. Explicit WALD04 implementation approval after required governance decisions.
2. Dedicated WALD04 QA pass, immutable accepted output and supported knowledge schema/component pins.
3. SQLite and disposable MySQL 8.4 evidence for containment, audit, idempotency and concurrency;
   populated rollback/upgrade preservation and revocation-vs-use behaviour accepted.
4. Stable trusted-context, reviewed-interpretation and reuse-receipt/revalidation contracts.
5. Approved source operator/namespace/revision ordering, durable/transitional binding ownership,
   duplicate/multi-row Call No. grain and coverage handling.
6. Separately approved upload/evidence/review/commit permissions, raw/preview/final-audit retention,
   commit unit/atomicity/recovery and partial-failure behaviour.
7. Explicit non-main profile/binding/preview/commit parity or supersession disposition.
8. Private storage, own durable worker/recovery, backup/restore and remaining security/dependency
   gates addressed for the integration/pilot stage; no production claim from schema tests alone.
9. A scoped WALD05 work package and explicit approval. WALD04 acceptance never authorises commit/UI.

Eight inherited Composer advisories remain an independent combined-release gate.
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` is the separate remediation reference, not merged here.

Recommendation: resolve the governance table, then seek explicit WALD04 implementation approval.
