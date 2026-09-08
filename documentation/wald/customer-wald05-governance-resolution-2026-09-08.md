# CUSTOMER-WALD05 Governance Decision Resolution

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **DECISION PACKAGE ONLY — WALD05 IMPLEMENTATION NOT AUTHORISED.**

Accepted input: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`; corrected executable
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`; WALD05 scope documentation commit
`ec735cae7f333da067c8b68fbfe997553490e96f`.

## Outcome

The V1 recommendations for I01, I02 and I05–I10 are technically ready for management decision.
I03 and I04 are not: repository evidence does not establish an authoritative source revision/
ordering mechanism or the real grain of `Call No.`. Those require Nick/source-owner evidence.
WALD05 implementation must remain blocked until the source contract is settled and management
then explicitly approves the complete I01–I10 package.

`READY_TO_APPROVE` below means the recommendation is sufficiently evidenced for management to
approve; it does **not** mean already approved. No Ixx decision is made by this report.

## Confirmed source rules

The controlled dictionary remains authoritative:

- PC1 → Plot Install → Windows; CC1 → Cavity Closer 1 → Cavity Closers;
- CM1/CM2 are CML revisits; CML maps to CML; no Snagging source code is confirmed;
- literal CC! remains invalid/likely typo evidence and is never silently corrected;
- `complete=Yes` completes only the represented resolved source part, without inventing a date;
- Windows = VS + TT + BAY + ALI + AOV + FI;
- Doors = PSU + PSG + CDF + CDU + CDG + PSP + BF; exact positive BF remains observable;
- CAS, FLU, PFD, GLS, WP and MISC are excluded from customer roll-ups;
- Items Ordered Status and Site Value are ignored; Plot To Be Installed is PC1 operational
  arrival/install evidence only;
- every import defaults to `PARTIAL_FILTERED_EXPORT`; absence from it has no deletion meaning.

## I01–I10 decision table

| ID | Existing question | Recommended V1 decision | Approval status |
|---|---|---|---|
| I01 | Who may upload, interpret, clarify, bind, assert scope, review, commit, audit and retry; must actors differ? | Active, non-preview Fenster Office Staff only. Keep each action separately authorised/audited; allow the same authorised Office user to upload, review and commit. External Site Users receive none of these abilities. | READY_TO_APPROVE |
| I02 | What is the source-site binding authority/lifecycle? | Office-only `DRAFT → ACTIVE → SUPERSEDED` or `REVOKED`; one active binding per exact namespace/source-site identity; explicit activation; reasoned replacement/revocation; immutable history; no fuzzy match, site creation or movement of a used binding. | READY_TO_APPROVE |
| I03 | What identifies and orders source revisions? | Require source-owner/issuer evidence for a stable revision/order. Byte hash identifies an artifact but upload/receipt time never establishes chronology. Exact accepted replay is idempotent; conflicting or unordered observations block rather than “last upload wins.” | NEEDS_NICK/SOURCE_OWNER |
| I04 | What exactly does one `Call No.` identify, and may it span rows? | Preserve it as an exact permanent source identity, but do not assume row/plot-service/lifecycle grain. Until the source owner documents the grain and any stable subidentity, duplicate Call Nos. block and are never merged/first/last-selected. | NEEDS_NICK/SOURCE_OWNER |
| I05 | Who may assert stronger scope and what does absence mean? | Ship WALD05 V1 commit with `PARTIAL_FILTERED_EXPORT` only. Office-only explicit acknowledgement may be designed for future stronger scopes, but SITE/GLOBAL complete modes remain non-committable until source-owner coverage/grain rules are approved. Partial absence always causes no change. | READY_TO_APPROVE |
| I06 | What makes staging/review commit-ready? | One immutable staging revision and current-state diff; all required site/plot/service identities and business meanings resolved; no blocking Wald ambiguity, invalid mapping, duplicate or revision conflict. Ignored non-business columns may remain. Separate explicit Office review. | READY_TO_APPROVE |
| I07 | What commits atomically and how is uncertainty recovered? | One reviewed bounded import run is one atomic commit. Refuse/split before review if a technical limit is exceeded; never hidden-chunk or retain per-row commits. Revalidate all dependencies/current state; exact idempotency; correction is a successor, not SQL repair. | READY_TO_APPROVE |
| I08 | How long is final import audit retained? | Retain committed audit metadata/provenance for six years as an operational V1 policy, not a legal claim. Workbook/analysis periods remain separate; holds extend; no purge until owner/backup/restore/disposal controls are approved. | NEEDS_JOSH_DECISION |
| I09 | Must WALD05 wait for a persistent production queue? | No for local implementation/QA. Use a durable, queue-agnostic operation contract; analysis may use the configured driver. Commit correctness stays synchronous/transactional and never depends on delivery. Persistent workers/supervision gate pilot/release, not code design. Private storage is mandatory. | READY_TO_APPROVE |
| I10 | What happens to current and non-main import implementations? | Reimplement the neutral staging/controlled commit boundary; adapt safe domain transitions and test intent; do not directly call, merge or preserve unsafe semantics. Retire old execution only after WALD05 parity, QA and separate approval. | READY_TO_APPROVE |

I11 remains `DEFER_TO_WALD06`: default-off supervised pilot/cutover and any retirement decision.

## Decision consequences and alternatives

- **I01:** Office-only/same-actor keeps V1 within the existing role model while separate actions,
  stale checks and audit contain risk. A second-actor rule is possible but would require new
  assignment, availability, escalation and recovery policy. Management approval is required.
- **I02:** immutable binding versions prevent silent tenant movement and make old imports
  explainable. A mutable binding table is simpler but unsafe after use; fuzzy/automatic binding is
  incompatible with the source contract. Management approval is required.
- **I03:** authoritative ordering prevents an old export overwriting newer facts. Alternatives
  are issuer revision, immutable event ID, or proven per-record time; upload time/content hash do
  not order truth. Nick/source-owner evidence is genuinely required.
- **I04:** correct grain controls uniqueness, aggregation, completion and correction history.
  Possible row, plot/service-part or revisitable-lifecycle meanings have materially different
  schemas; existing importer assumptions cannot decide. Nick/source-owner evidence is required.
- **I05:** partial-only V1 removes unproved absence mutations. Enabling site/global complete scope
  now would require an authoritative dataset/grain/revision contract; carrying the enum with no
  commit effect adds unnecessary flexibility. Management approval is required for the restriction.
- **I06:** all blockers resolved before review keeps preview and commit truthful. Partial-row
  commits would need explicit grouping/status/retry UX and product approval. Management approval
  is required.
- **I07:** one bounded atomic unit gives exact preview/commit parity and simple recovery. If scale
  is unsafe, the only proposed alternative is explicit smaller review units created before
  approval, never hidden post-review chunks. Management approval is required; benchmarks set the
  technical maximum later.
- **I08:** six-year minimal audit supports long-lived provenance but increases storage/privacy
  responsibility. A different company standard or event-based period is possible. Josh must
  choose; six years is not derived from law.
- **I09:** queue-agnostic analysis lets local work proceed while keeping production operations
  honest. Requiring a persistent worker before coding would couple correctness to deployment;
  fully synchronous production analysis could exceed request budgets. Management can approve the
  architecture now; persistent operations remain a release gate.
- **I10:** reimplementation avoids preserving known semantic/data-loss defects while allowing
  safe domain logic/tests to survive. Wholesale merge or direct wrapping is unsafe; immediate
  retirement would remove the fallback before parity. Management approval is required.

## Permission recommendation

| Action | Recommended V1 actor | Required separation |
|---|---|---|
| Upload workbook | Active non-preview Office | Separate audited action |
| Start/retry interpretation | Active non-preview Office | Stable operation/idempotency; retry cannot bypass refusal |
| Answer one-time clarification | Active non-preview Office | Existing WALD04 action and immutable evidence |
| Select/activate source-site binding | Active non-preview Office | Separate binding action/epoch/reason |
| Assert stronger scope | Active non-preview Office only, but disabled for V1 commit pending I03/I04/coverage evidence | Explicit acknowledgement/audit; never inferred from contents |
| Review preview | Active non-preview Office | Pins exact staging/diff/dependencies |
| Commit import | Active non-preview Office | Separate explicit action and fresh in-lock authorisation |
| View private import audit | Active non-preview Office | Scope-first, paginated access |

Allowing the same user is proportionate for supervised V1 because preview is non-mutating,
review and commit are distinct, stale dependencies fail, commit is atomic/idempotent, binding
is explicit and all actions are attributable. Four-eyes approval would add administration not
present in the current Portal model. Alternative: require a second Office committer; this needs
new assignment/escalation/fallback rules and is not justified by current evidence.

Management approval is required because these are new import powers, even though Office-only
access aligns with the existing product architecture.

## Source identity and binding

Hierarchy:

1. permanent source Site ID/reference from RZ/SiteApp when supplied;
2. transitional exact Site Name binding, explicitly selected by Office and labelled transitional;
3. existing exact source Plot reference string within the bound site, preserving leading zeroes;
4. exact `Call No.` supplied by the source—no invented replacement identifier.

Recommended binding transitions:

- `DRAFT`: proposed exact source identity and existing Portal organisation/site;
- `ACTIVE`: explicit Office activation, unique within namespace/source identity;
- `SUPERSEDED`: atomically replaced by a successor version with mandatory reason;
- `REVOKED`: blocks future use without replacement; mandatory reason.

Used versions are immutable and cannot move another source identity's history between sites.
Display/source names are evidence, not authority. Binding selection never follows a Wald profile.
Management may approve this lifecycle now; actual permanent ID delivery remains a source gate.

## Call No. grain and duplicate matrix

Repository evidence establishes only that `Call No.` is expected to be permanent and unique for
a source call-off record. The existing importer assumes one stable plot/service association and
rejects duplicates, but the dictionary explicitly leaves duplicate/multi-row semantics open.
There is no approved evidence that it identifies one physical row, one plot/service part, or a
revisitable lifecycle. Therefore I04 requires source-owner confirmation.

Choosing wrongly could merge independent source parts, double/lose quantities, apply completion
to the wrong Portal service, reject valid revisits or destroy correction provenance.

| Situation | Safe recommendation before source evidence |
|---|---|
| A. Duplicate within one workbook | Block every occurrence, including byte/canonical-identical rows; their presence may represent accidental duplication or undocumented multi-row grain. |
| B. Same Call No. in a later workbook | If the exact canonical observation and accepted source identity/revision are unchanged, treat as an idempotent observation. Different payload/order remains a conflict. |
| C. Exact same workbook/review replay | Return the original successful receipt; do not create another commit/history chain. |
| D. Correction/revision | Require authoritative ordering or an explicit reviewed corrective successor under the approved source contract. Never overwrite because upload time is later. |
| E. Conflicting row content | Block staging/commit; preserve both observations privately; never choose first/last or merge quantities. |

## Source revision / ordering

No repository contract supplies a trustworthy workbook revision number, export sequence,
immutable source event ID or authoritative workbook timestamp. Current `source_version` is
optional/caller-supplied; content SHA-256 proves bytes only; per-row `source_updated_at` is optional;
upload and observation times are Portal facts. None can safely order conflicting exports.

Safest V1 until Nick/source-owner answers I03:

- exact artifact + scope + reviewed-set replay is idempotent;
- new unseen identities may be staged, but a conflicting previously observed Call No. cannot
  automatically replace current source facts;
- present a conflict and require authoritative revision evidence/corrective review;
- never let file name, upload order, modification metadata or a familiar profile mean “newer.”

Alternatives are an issuer-supplied monotonic revision, immutable event ID, or a documented
per-record timestamp with ordering/collision rules. The source owner must identify which, if any,
is reliable.

## Scope, missing records and products

Recommended simplest V1 is partial-only commit. This makes the settled rule executable without
inventing complete-snapshot grain:

- missing Call No./plot/service: no change;
- missing product column: unrepresented/unknown; preserve current quantity;
- column present with blank/null: retain raw blank and interpret zero only within that supplied
  record/column as already approved by DEC-044, subject to resolved Call No./product grain;
- column present with explicit zero: exact zero for that supplied record/column;
- column present with a valid positive quantity: exact fixed-point quantity;
- unknown/invalid quantity: blocker, not zero.

SITE/GLOBAL complete modes require an authoritative dataset definition, stable revision and
approved absence effect. When later approved, prefer an audited source-absent/superseded state,
never hard deletion; exact aggregation and reappearance rules still depend on I03/I04.

## Review, atomicity and recovery

A commit-ready preview has no unresolved required rows. Non-blocking ignored columns can remain,
but unknown site/plot/service, business mapping, duplicate/revision conflict or current Wald
ambiguity blocks the whole reviewed unit. Partial row commit is not recommended for V1 and would
require separate product approval.

One reviewed run commits atomically. If size benchmarks exceed a safe transaction limit, create
smaller explicit review units before approval; each has its own preview, hash, idempotency key and
receipt. Do not split invisibly after review.

Preview validity is 24 hours and becomes stale when workbook hash, dictionary/component pins,
semantic executable, profile/version/epoch, binding, scope, staging/diff, relevant projection
revision or authorised review state changes. Commit fails stale; it does not regenerate.

Failure/retry recommendation:

- failed transaction leaves no projection mutation and records a safe failed attempt;
- retry the same reviewed preview only when it remains valid and the prior attempt is known failed;
- uncertain/successful idempotency key resolves to the existing receipt, never a duplicate commit;
- stale review requires reanalysis/diff/review;
- corrections create a successor; no routine manual SQL repair.

## Completion and Portal interaction

The existing completion/reversal domain intent can be adapted, not reused as-is. Its present
trigger uses obsolete completed-date/job-stage mappings. WALD05 must invoke a focused transition
only after validated `complete=Yes` for a resolved dictionary service inside the atomic commit.

Source completion may update source-derived completion state and close the current open Portal
negotiation/amendment as already approved. It must preserve Requested Date, Date Agreed,
alternatives, amendments, actors and immutable history; invent no completion date; send no
completion notification. Reversal records source change and never reopens an older request.

## Queue, storage and retention

Local implementation can proceed without a deployed persistent worker if operations are durable
and queue-agnostic. Analysis may be dispatched through Laravel's configured driver in tests/local;
production/pilot needs a persistent queue, supervision, retry/lease settings and named operational
owner before enablement. Final commit remains an explicit synchronous transaction and does not
depend on a notification/cleanup job being delivered.

Workbooks remain in generated private, non-public CustomerApp storage with scoped access and
approved reader limits. Existing 30-day workbook, 7-day bulky observation, 24-hour preview and
7-day payload metadata periods remain separate from final audit. Recommend six years for minimal
committed audit/provenance only. Six years is a management retention choice, not a legal claim;
full workbooks do not inherit it. Until approval and disposal controls, retain and do not purge.

## Old import feature disposition

Read-only verification of `04b560f → a013ed1 → 1e8c22b` supports:

- **REUSE:** safe upload/signature/content-hash guards where still covered by frozen Wald;
  idempotency, stale-preview, exact binding, explicit-scope and MySQL scenario intent;
- **REIMPLEMENT:** source-site binding, preview/review/commit, source scope/reconciliation and
  projection adapter under accepted WALD03/04 contracts;
- **SUPERSEDED:** old semantic mapping, automatic profile saving, namespace-only score/first
  match, source-wide absence, omitted-product zeroing, synchronous coupled orchestration and
  per-record commit;
- **REFERENCE_ONLY:** Office UI presentation and mixed preview/projection migrations.

No runtime, data or schema from that line is approved for merge or migration.

## Current Sprint 3B importer classification

| Component | Classification | Consequence |
|---|---|---|
| `SourceRecord` | REPLACE | Introduce immutable neutral raw/canonical staged record; retain old DTO until cutover. |
| `source_call_number` identity/association | ADAPT | Preserve exact value and stable-association guard, but finalize uniqueness/grain after I04. |
| `SourceImportRun` / source issues/events | ADAPT | Preserve history; add distinct artifact/analysis/staging/review/commit lineage rather than overloading the current run. |
| Completion/reversal | ADAPT | Reuse domain transition intent/locks/history only behind corrected `complete=Yes` semantics. |
| Projection updates | REPLACE | New bounded atomic commit action; do not call the current per-record flow. |
| Missing/product reconciliation | REPLACE | Partial-safe presence-aware rules; no source-wide missing or omitted-column zeroing. |
| `SourceProjectionImportService` execution path | RETIRE_AFTER_WALD05 | Keep until parity, dedicated QA and separate retirement approval; never wire it directly to Wald. |
| Existing projection relationships/history | KEEP_AS_IS | Additive evolution only; do not rewrite historical migrations/data. |

## Implementation readiness

### BLOCKS WALD05 IMPLEMENTATION

- I03: Nick/source owner must define authoritative source revision/order—or explicitly approve
  a conflict-only manual correction contract with no chronological inference.
- I04: Nick/source owner must define the true Call No. grain and any legitimate multi-row case.
- Management must then approve I01–I10, including I08's retention choice, and issue a separate
  implementation instruction. READY_TO_APPROVE entries are still unapproved.

### DEFERRED WITHOUT BLOCKING LOCAL WALD05 DESIGN

- persistent production queue/worker/supervision and operational rollout: DEFER_TO_RELEASE;
- named G09 unattended-disposal owner: DEFER_TO_RELEASE while purge stays disabled;
- production pilot/cutover, held-out capacity and old-path retirement: DEFER_TO_WALD06;
- Composer remediation and combined release security: DEFER_TO_RELEASE;
- SiteApp integration/write-back, scheduled sync and new dictionary meaning: outside WALD05.

## Files and checks

This task creates this decision report and adds a non-authoritative link from the scoped WALD05
work package. No governing decision, runtime, migration, test, route, UI, queue, storage,
dependency or configuration is changed. Historical reports remain unchanged.

Verification is documentation-only: accepted SHA/branch/status, current importer source/tests,
non-main service/migration/test evidence, link/path checks, contradiction review, runtime diff and
whitespace validation. Application/MySQL/build/audit suites are not rerun; WALD04 QA results remain
attributed evidence.

Recommendation: resolve I03 and I04 with Nick/source owner, decide I08, then approve the remaining
READY_TO_APPROVE recommendations as one explicit I01–I10 package before authorising implementation.

WALD05 governance blocked — source contract evidence required
