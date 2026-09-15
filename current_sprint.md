# Current Sprint

## CUSTOMER-WALD-SOURCE02 — master-export revisions + CustomerCode — 15 September 2026

**Implemented and fully verified on a separate non-deploying feature branch.** Same
date/slot imports now form retained successor revisions. Failed current uploads are replaced
automatically, non-failed current uploads require exact confirmation, identical workbook hashes
do not create noise, and current cards link to revision history. Supersession stales old
uncommitted reviews while committed projections remain governed by correction and partial-export
rules.

Exact `CustomerNo`/`CustomerCode` headers now supply the authoritative CustomerCode binding key.
Site Name remains visible review evidence; unknown codes require exact Office binding and missing
codes block the new master-export path. The new private workbook qualified locally at its approved
SHA: 33 records, nine codes and nine source identities. Existing schema is sufficient; no
migration, deployment, `main` push, production access or Wald enablement occurred.

Focused final SOURCE02 evidence: 8 passes / 66 assertions; the broader Wald integration checkpoint
passed 747 / 25 expected skips / 3,536 assertions. Disposable MySQL 8.4.11 applied all 17 migrations
and passed 48 replacement/binding/HTTP cases / 172 assertions with one intentional non-MySQL skip.
Final full regression: 1,561 passes / 80 expected skips / 8,052 assertions; the private-master test
separately passed 1 / 9 at its approved local path. Pint, Composer validation/audit, Vite build,
production npm audit and diff checks pass.

Earlier GOLIVE02/CUSTAPP2/pilot entries below are historical inputs and do not override this status.

## CUSTOMER-WALD-GOLIVE02 — strict environment gate — 15 September 2026

**Implemented and fully verified on a separate non-deploying feature branch.** The Wald
environment kill switch now accepts only the exact case-insensitive word `true`. Missing, empty,
false-like, numeric, parenthesized, padded and malformed values fail closed. Runtime availability
also requires the cached config value to be the boolean `true`, the audited application setting,
and current active non-preview Office authority.

Focused strict-gate/availability tests passed 22 tests / 57 assertions. The combined Wald pilot,
CUSTAPP2 and commit-boundary regression passed 63 tests with one intentional MySQL-only skip / 198
assertions. The complete application suite passed 1,550 tests with 79 skips / 7,977 assertions.

No migration or Wald business-rule change is included. Production remains on
`89768986a1a32d4258b5deebdf3012584acd6a6c` with `WALD_IMPORT_AVAILABLE=false`; no enablement,
production import, `main` push or deployment is authorised. The exact correction SHA is reported
in the completion handoff; Integration/DevOps may resume from controlled deployment stability
rather than restart earlier qualification.

Earlier CUSTAPP2/pilot entries below are historical inputs and do not override this status.

## CUSTOMER-WALD-CUSTAPP2-01 — composite workbook compatibility — 15 September 2026

**PARTIAL local qualification; feature branch only, not released or deployed.** Branch
`codex/wald-custapp2-composite-2026-09-15` implements deterministic composite structure,
CallNo header normalization, valid blank Call Type, distinct Plot/Source Row/Visit identities,
compatible product consolidation, exact provenance and one-site atomic review/commit.

The approved private workbook SHA and aggregate structure matched: 33 source rows, nine source
sites, 23 plot identities, 33 unique CallNos, 8 PC1, 10 CC1, 2 CM1 and 13 blanks. Eight selected
sites committed to disposable SQLite/MySQL databases. One seven-row site correctly blocks because
one plot has conflicting explicit evidence for two products. Do not pick a value or amend private
source data; obtain corrected source evidence or an authorised resolution, then requalify.

Production serves `89768986a1a32d4258b5deebdf3012584acd6a6c` and Wald remains effectively OFF
with `WALD_IMPORT_AVAILABLE=false`. The gate-drift investigation is separate. No production,
Forge, SiteApp, `main`, gate or customer-data change is part of this sprint. See the
[implementation report](documentation/wald/customer-wald-custapp2-composite-import-2026-09-15.md)
and [work package](documentation/work-packages/WP-CUSTOMER-WALD-CUSTAPP2-COMPOSITE.md).

Earlier pilot/WALD06 entries below are historical and do not override this status.

## CUSTOMER-WALD-PILOT01/02 — supervised weekend import — 15 September 2026

**PRODUCTION DISABLED AFTER TEST; correction requalified locally, not deployed.** Production SHA
`8a5a2384dd32c89852172c8dd99ffaba880dc698` passed deployment, backup, access, private storage,
fictional upload, binding, analysis, preview and approval. Commit was safely refused before any
business mutation by `commit_requires_top_level_boundary`, exposing a duplicate controller/domain
journal boundary. `WALD_IMPORT_AVAILABLE=false`; the audited application setting remains enabled
but is ineffective.

The narrow correction removes only the controller-owned duplicate wrapper and retains the
domain-owned journal, authorisation, transaction, retry, idempotency and projection rules. It is
requalified on local branch `codex/fix-wald-pilot-commit-boundary-2026-09-15` with authenticated
SQLite HTTP coverage, a strict production-like HTTP/MySQL test, full regression and the established
MySQL race gate. Production must remain unavailable until separately approved merge/deployment,
fresh backup, fresh fictional preview and supervised smoke. Full WALD06 remains paused. See
[work package](documentation/work-packages/WP-CUSTOMER-WALD-PILOT01-WEEKEND-LIVE-IMPORT.md)
and [correction evidence](documentation/wald/customer-wald-pilot01-commit-boundary-correction-2026-09-15.md).

Earlier WALD06 status below is historical and does not override this pilot.

## CUSTOMER-WALD06 — Multi-site Import Studio + pilot readiness — 11 September 2026

**Approved and in progress on `feature/customer-wald-next`; not released or deployed.** The
canonical accepted Wald checkpoint `c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` is being
reconciled by normal merge with current local `main`
`dcea43e3266c06044c498ab2540b25c233a5d847`. WALD06 owns the default-off Office Import
Studio, one-workbook parent export, site-specific review units, local/test actual-workbook pilot
and QA readiness. Production enablement, importer retirement, SiteApp changes, external messages,
main push and deployment remain out of scope.

The accepted selection rule remains PC1, CC1 (including observed `CC!` as the approved typo
correction) and CM1; CM2 and the explicitly identified `NICK TEST` row are excluded. The parent
date/slot governs ordering, while each site unit commits atomically and independently. Earlier
WALD05 evidence remains below and continues to govern the inherited backend.

## CUSTOMER-GIT-CLEANUP02 — Remote Git consolidation — 11 September 2026

**REMOTE_CONSOLIDATED.** Published only `feature/customer-wald-next` at
`c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` and the 12 approved annotated preservation tags.
After proving each exact historical remote tip was an ancestor of published `origin/main`,
canonical Wald or a named tag, deleted the ten preserved non-main remote branches. The final
remote heads are only `origin/main` and `origin/feature/customer-wald-next`.

`origin/main` remained `e757bb651f9aa95d67b808a97433d27bc29d03c3`; local `main` was not pushed,
Forge/production was not contacted and no deployment occurred. The two historical local branches
and three worktrees protected by dirty user state remain a separate cleanup task. See
`documentation/customerapp-git-consolidation-2026-09-11.md`.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-GIT-CLEANUP01 — Local Git consolidation — 11 September 2026

**PARTIAL: local product and Wald ownership are clear; remote publication requires separate
approval.** Prepared executable local `main` checkpoint
`93737df1e8dae36b9d79b6b641e30b6c9af51908` is the accepted non-Wald product line; this
cleanup adds only documentation above it. `origin/main` remains production line
`e757bb651f9aa95d67b808a97433d27bc29d03c3`. No product branch requires another merge.
Accepted unreleased WALD02–05 now has one canonical local branch,
`feature/customer-wald-next`, at `c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` (corrected
executable checkpoint `dbd17c68a04c028418e2d8a08fc43312aae5fe3b`). Historical manual
import work remains excluded from both lines and is archive-tagged.

Local branches were reduced from 40 to four after 12 annotated preservation tags were created.
Two historical refs remain only because dirty worktrees must not be force-removed. Clean stale
worktrees were removed. The canonical Wald branch and tags were not pushed, remote branches were
not deleted, and `main` was not pushed because the environment requires separate explicit remote
egress approval. See `documentation/customerapp-git-consolidation-2026-09-11.md`.

Branch policy: `main` is the accepted production/release line; feature branches are short-lived;
QA/release branches are removed after acceptance and preservation; accepted unreleased Wald work
lives on one canonical branch; historical milestones use annotated tags. Pushing `main` remains
a separately authorised production action.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-WALD05 correction requalification — 9 September 2026

**PASS — corrected bounded backend eligible to freeze.** DEC-054 authorises only W5Q-03/04
corrections; committed candidate `dbd17c68a04c028418e2d8a08fc43312aae5fe3b` remained fixed for
all acceptance processes on local `qa/customer-wald05-backend-2026-09-09`. Durable private attempt audit and bounded batch
profile eligibility are implemented; W5Q-01/02/05 remain preserved. Focused, combined, full
regression, independent MySQL non-race and both additive upgrade paths pass. Fresh MySQL
concurrency passes 320 groups / 640 workers; disjoint MySQL suites total 240 passes, one skip,
2,256 assertions, zero failures/errors. The disposable server was normally shut down.
The multi-site shared-slot **PILOT_BLOCKER** remains separately scoped WALD06/pilot design.
Full Office UI, cutover and release remain separate; eight inherited advisories are unchanged.
See [fresh QA evidence](documentation/wald/customer-wald05-backend-qa-2026-09-09.md).
No main, push, GitHub Actions, production, customer import, SiteApp, WALD06 or deployment.

## Historical CUSTOMER-WALD05 original dedicated backend QA — superseded by requalification above

**FAIL — corrections required; do not freeze or start WALD06.** Dedicated QA of exact candidate
`1dc6ee6a24026c970687472126dc295fb6f2b3c8` is recorded on local
`qa/customer-wald05-backend-2026-09-09`. W5Q-01/02 correct transient digest identity and
source-order/projection-ownership bypasses; W5Q-05 stabilises synthetic race owners.
W5Q-03 (durable refusal/failure audit) and W5Q-04 (receipt-count N+1 queries) remain open.
The shared date/slot rule separately blocks naive multi-site splitting for the later pilot.
See [dedicated QA evidence](documentation/wald/customer-wald05-backend-qa-2026-09-09.md).
Feature branch only; no main, push, production, customer import, SiteApp, full UI or deployment.

## Historical CUSTOMER-WALD05 implementation status — superseded by QA above

DEC-053 directs backend completion without the full Office UI. Private upload, durable leased
analysis, immutable staging, explicit knowledge/binding checks, pinned review and one-run atomic
projection commit are implemented on `feature/customer-wald05-import-review-integration`.
Ordering, idempotency, explicit successor correction, observation/receipt history and retention
metadata are included. Default off; no routes, production worker, purge or old-importer cutover.
Supported unit: one bound site, one visible unmerged table/sheet, maximum 500 nonempty rows.
Unsupported/mixed-site units refuse entirely. No real customer workbook import was performed.
The [backend completion report](documentation/wald/customer-wald05-backend-completion-2026-09-09.md)
is the current verification/QA handover: backend READY FOR QA. Focused 114 passes / 12 skips;
combined 991 / 23; full 1,210 / 38. MySQL 113 non-race passes / one skip plus 12 passing race
scenarios, 80 groups / 160 workers. Upgrade guards pass; disposable server stopped.
Acceptance, Office UI, WALD06 and release remain separate.
Eight inherited advisories remain; no main, push, SiteApp, Sprint 3F or deployment work.

## Historical CUSTOMER-WALD05 reader correction and binding foundation — 8 September 2026

DEC-052 authorises W5-T01 correction and continued implementation. Reader correction
`e9e1c3a2a3ff79cfe5f097bea143f51195becd55` uses wald-0.2.2 / XLSX adapter 3; old knowledge pins
become stale. The unchanged workbook now profiles successfully; DEC-050/051 still select 45 rows.
Default-off Office binding drafts/activation/supersession/revocation, immutable versions/audit,
epoch checks and pure order/selection contracts are implemented. One additive migration; no
upload/staging/preview/projection commit/UI yet. No new approval blocker; WALD05 is PARTIAL.
Focused: 53 passes / 4 MySQL skips. Combined Wald: 930 passes / 15 skips. Full: 1,149 passes /
30 skips. Disposable MySQL 8.4: 52 non-race passes / one SQLite-only skip plus four race scenarios,
40 groups / 80 workers; upgrade/rollback guards pass. Server stopped. No main/push/deployment.
[Current report](documentation/wald/customer-wald05-import-review-integration-2026-09-08.md).

## Historical CUSTOMER-WALD05 actual-workbook audit before reader correction — 8 September 2026

DEC-050/051 select 45 actual records and exclude CM2 plus the explicitly identified Nick TEST row.
No duplicate Call No., invalid completion/product value or competing included site/plot was found.
The prior synthetic W5-P01/P02 cases do not block this workbook. W5-T01 reproduces an accepted
XLSX-reader metadata rejection caused by an extension-namespace workbookPr. The core correction
needs a narrow review/versioned identity; no runtime integration or migration has started.
Implementation authority remains valid; no further blanket approval is needed. Combined Wald:
865 passed / 11 skipped; full application: 1,084 passed / 26 skipped. Eight inherited advisories
remain. [Current report](documentation/wald/customer-wald05-import-review-integration-2026-09-08.md).
No main, push, production, SiteApp or deployment action. The original workbook is unchanged.

## Historical CUSTOMER-WALD05 initial projection review — 8 September 2026

DEC-049 records explicit implementation authority on
`feature/customer-wald05-import-review-integration`, created from governance commit
`877bd3ff666873a0703c3b7671015ec4cfd2ee52`. Accepted WALD04 ancestry and unchanged executable
trees are verified. Entry review found that multiple distinct visits lack an approved rule for
their single Portal service completion/request association and plot/product quantity projection
(W5-P01/P02). I01–I10 remain approved; runtime/schema work is paused at that boundary.
[Implementation entry report](documentation/wald/customer-wald05-import-review-integration-2026-09-08.md).
No runtime, migration, production, SiteApp or deployment change. Earlier readiness statements are
superseded only as to the newly identified projection gap and the now-satisfied implementation
instruction requirement.

## CUSTOMER-WALD05 governance approved — 8 September 2026

DEC-048 approves I01–I10 against immutable WALD04 input
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8`. The temporary V1 source order is authenticated
Office-declared Export Date plus `MORNING`/`AFTERNOON`; one Call No. identifies one visit;
only partial/filtered exports may commit; one reviewed bounded run is atomic; and minimal
committed audit/provenance is retained six years. The existing and non-main import paths remain
reference/reuse evidence and are not approved runtime architecture.

Governance is complete, but this documentation task is not an implementation instruction. No
runtime, migration, route, UI, queue, storage, SiteApp, dependency, main, push, production or
deployment action occurred. G09's unnamed owner still blocks unattended production deletion;
persistent worker rollout, WALD06 pilot/cutover and security/release reconciliation remain later
gates. [Final approval](documentation/wald/customer-wald05-final-governance-approval-2026-09-08.md)
and [governing work package](documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md).

## Historical CUSTOMER-WALD04 acceptance / WALD05 scoping — 8 September 2026

Management accepts corrected WALD04 output
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8` on
`qa/customer-wald04-2026-09-08` as the immutable WALD05 input. Executable/test correction
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`, generic Wald tree `30d1fc65`, semantic
tree `9cc8df4b` and dictionary v1 fingerprint `18718ef5…f8357` are frozen. Original
candidate `2c7d015` is not the accepted output. DEC-047 records the acceptance.

WALD05 is architecture/work-package scope only on
`codex/docs-customer-wald05-scope-2026-09-08`. Proposed I01–I10 decisions cover Office
permissions, source-site bindings, source revision/order, duplicate Call No. grain,
complete-snapshot absence, neutral staging/review, atomic commit/recovery, final audit
retention, queue/storage ownership and current/non-main importer disposition. None is
implementation authority. [Acceptance report](documentation/wald/customer-wald04-acceptance-wald05-scope-2026-09-08.md)
and [WALD05 work package](documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md).

The existing `SourceProjectionImportService` and `SourceCallTypeMapper` cannot be wired
directly to Wald: current code retains outdated call-type/stage meanings, source-wide absence,
omitted-product zeroing and per-record commit behavior. The non-main manual import line remains
reference/reuse evidence only. No WALD05 runtime, migration, route, UI, queue or storage change;
no main, push, production, SiteApp, dependency-remediation or deployment action. Eight inherited
advisories remain. G09 named-owner nomination still gates unattended disposal.

## Historical CUSTOMER-WALD04 dedicated QA passed — 8 September 2026

Dedicated QA corrected candidate `2c7d0154e51a35b165c7e93f7dd256e2cf0f030f` on
`qa/customer-wald04-2026-09-08`. Executable/test correction commit:
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`. The final documentation-inclusive QA SHA is
reported in the task handoff and is recommended for freeze; management acceptance and release
remain separate. **Feature branch only — not on main, not deployed.**

Fixed MySQL protected-field collation bypass (forward migration), bounded profile/provenance
and retention queries, collision-free race fixtures and test-only full-suite memory allowance.
Final checks: focused SQLite 187 passes / 11 MySQL skips / 406 assertions; combined Wald
865 passes / 11 skips / 4,218 assertions; full application 1,084 passes / 26 skips / 5,406 assertions.
MySQL 8.4.11: 198 tests / 862 assertions across the final non-race gate and seven race scenarios;
20 iterations each, 140 groups / 280 workers. Clean/additive migration, empty rollback/reapply
and populated refusal passed. Disposable server stopped.

[Dedicated QA report](documentation/wald/customer-wald04-qa-2026-09-08.md) records commands,
failed attempts, W4Q-01–04, exact file changes and remaining separate gates. Frozen WALD02/03
trees and dictionary fingerprint are unchanged. Eight inherited Composer advisories remain.
No push, main change, production action, dependency remediation merge or WALD05 implementation.
G09 named-owner nomination still gates unattended disposal only; no disposal scheduler exists.

## Historical CUSTOMER-WALD04 implementation — 8 September 2026

DEC-046 explicitly approves the bounded knowledge/profile backend on
`feature/customer-wald04-knowledge-profiles`, created from exactly accepted WALD03
`a80ce7d14206cf3f3a9343448d406f01ae927b88`. Dictionary v1 fingerprint
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` and generic core remain frozen.

G01–G08 and G09's mechanism are approved: Office-only scoped knowledge; separate answer,
draft and activation; same actor allowed; immutable versions/history; explicit revocation;
12-month reapproval and 24-month history with holds/dependency protection. The named Fenster
data owner remains pending only before unattended production disposal. No disposal scheduler.

Local additive implementation and verification are recorded in
`documentation/wald/customer-wald04-knowledge-profiles-2026-09-08.md`.
Candidate `f0f97212b8b1be763b953414cfe65c8e3e3352e7` is ready for dedicated QA:
MySQL 102 passes / 355 assertions; combined Wald 774 passes / 6 MySQL skips;
full regression 993 passes / 21 environment skips. Eight inherited advisories remain separate.
Dedicated QA and a newly accepted immutable output remain required. Source-site bindings,
upload/import UI, review/commit and final-import audit retention remain WALD05. No main,
push, production, deployment, SiteApp or dependency-remediation action is authorised.
Earlier handoffs below are historical, not current phase-entry instructions.

## Historical WALD03 implementation handoff — 8 September 2026

Management accepted corrected CUSTOMER-WALD02 commit
`4aa5ffb5a00527662ddfe66673edbfb18af9f0db` as the immutable WALD03 input. Do not use
superseded candidate `9980354d28bfe1ca7986e10a529ab073d95d0b91`. Reader `wald-0.2.1`;
208 focused tests / 1,262 assertions and 427 full-suite passes / 15 environment-gated skips /
2,450 assertions. The five QA reader corrections remain frozen core behaviour.

CUSTOMER-WALD03 was explicitly approved and implemented on
`feature/customer-wald03-business-dictionary`, starting exactly from that frozen WALD02 SHA.
Candidate `1fee57de2830fc8a07cb0a14296e72c0ddbb2774` contains the pure dictionary, adapter and
synthetic tests. Focused WALD03: 344 passes / 1,707 assertions; combined Wald: 552 passes /
2,969 assertions; full CustomerApp: 771 passes / 15 environment skips / 4,157 assertions.
Dedicated QA and an accepted output SHA remain required; WALD04 has not started.
No generic core, import integration, dependency, migration, `main`, production or deployment
change. Evidence: `documentation/wald/customer-wald03-business-dictionary-2026-09-08.md`.
Earlier entries below are historical, not current phase gates.

## Current planning work — 4 September 2026

**Project documentation reset complete on a non-deploying documentation branch.** The
current product contract is now `brief.md`; safe-work governance is `AGENTS.md`; DEC-041
records the reset and
`documentation/customerapp-documentation-contradiction-register-2026-09-04.md` preserves
the stale/historical/open items without layering them into the brief.

CUSTOMER-WALD02's standalone portable core and synthetic corpus are implemented on
`feature/customer-wald02-portable-core` and ready for dedicated QA. The controlling SiteApp
manifest digest is `76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`;
the implementation commits are `d1c130a` and `5ddaa26`. This is isolated feature-branch work:
no import integration, dependency, migration, `main`, production or deployment change.
CUSTOMER-WALD03 has not begun and requires dedicated WALD02 acceptance plus its own approved
work package. Import permissions, source ownership/revision, retention and commit atomicity
remain open later-package gates.

Sprint 3F remains separate feature-branch work at
`feature/sprint-3f-date-amendments` (`60aa9e2`), ready for dedicated QA but not on `main`,
release-approved or deployed. This documentation task does not own or advance Sprint 3F.

Production state is deliberately qualified. Sprint 3E at
`9111d76ff05d702d68afd884ee8e42bc8e50c8e3` / Forge deployment `76326195` is the last
explicitly evidenced successful release. Current `origin/main` is
`0873bac79edf578e9f4a9417e3cafae34e8aa925`; verify Forge before claiming that later SHA is
deployed. The authenticated non-destructive production smoke remains outstanding.

No production code, migration, dependency, workbook, parser, queue, SiteApp repository or
production environment is changed by this reset.

## Historical release-management status — 25 August 2026

Production is serving `main` commit `f801c91113bd13656c6cbffdd3d82c12d4a95846` after
the controlled MySQL migration-recovery deployment recorded in
`documentation/production-deployment-recovery-2026-08-21.md`. The MySQL-safe migration
repairs are production-critical and must remain unchanged unless a later MySQL-rehearsed,
approved replacement exists.

Sprint 3E passed dedicated QA and has been reconstructed on the non-deploying release branch
`release/sprint-3e-date-negotiation-2026-08-25`. It remains excluded from `main` and production:
the mandatory disposable MySQL clean-install, upgrade, index and concurrency gate is blocked by
the absence of a safe target. See `documentation/sprint-3e-release-candidate-2026-08-25.md`.

Sprint 3D — Multi-Plot/Multi-Service Call-Off Backend Contract

Status:
Dedicated Sprint 3D QA passed locally on 21 August 2026 after two contained corrections:
the signed review retains selected plot/fixed service order, and Awaiting Fenster submission
notifications carry request-level service/date context. The historical statement that
Sprint 3E had not begun is superseded by the release-management status above: an unapproved
Sprint 3E implementation exists on a side branch and remains excluded. This remains subject
to the recorded holiday-provider, MySQL rehearsal and production-reconciliation limitations.

Implementation result:

- The old one-service/one-date creation route is replaced for new submissions by one
  shared start → matrix → signed review → final submit workflow.
- Dashboard multi-select and the separate New Call Off page validate UUIDs against the
  active site and feed the same workflow; Office Staff cannot enter it.
- The final endpoint posts only a server-stored confirmation signature, consumes it before
  persistence and rebuilds current eligibility before one atomic submission transaction.
- Each request holds its service/date/early-date facts and Date Requested history. Existing
  legacy requests, lifecycle actions, notifications and Office review remain readable.
- UI contract and deferred notification/holiday work are in
  `documentation/sprint-3d-bulk-call-off-report.md`.

Verification:

- Fresh local SQLite migration and seeding passed on 21 August 2026.
- Sprint 3D QA covers a realistic three-plot/four-service browser journey, confirmation
  mutations, stale conflict/completion/source/BF/access paths, atomicity, three external
  roles and per-request notification context.
- See `documentation/sprint-3d-bulk-call-off-qa-report-2026-08-21.md` for final command
  evidence (171 tests, 884 assertions) and remaining release limitations.

Previous Sprint Context:

Sprint 3C — Plot-Centric Site Overview and Plot Details

Status:
Sprint 3C dedicated QA passed. The authorised local SQLite fresh-migration-and-seed
rehearsal passed after confirmation that the target was the local CustomerApp SQLite
database, not MySQL, Forge or production. The final full Pest suite passes (162 tests,
792 assertions). Sprint 3D has not been started.

Authoritative planning:

- `context-work-prompt.md`
- `brief.md` section 17
- `documentation/management-gap-analysis-2026-08-20.md`
- `documentation/target-domain-v3.md`
- `documentation/source-integration-contract.md`

Sprint 3C implementation result:

- Replaced the request-card dashboard with an assigned-site plot overview using the fixed
  Cavity Closers, Windows, Snagging and CML order.
- Centralised source-aware service and overall presentation state outside Blade. Source
  completion wins; legacy Approved is displayed as Date Agreed.
- Added responsive semantic-table/mobile-card browsing, safe UUID plot details, source
  freshness messaging, filters, default-hidden fully completed plots and customer-safe
  product quantities.
- Kept the existing withdrawal, resubmission and Trash panel secondary, retaining its
  server-side eligibility checks. No source transport or new workflow was added.

QA result — 21 August 2026:

- QA corrected direct UUID access across an assigned-but-not-selected site, which could
  otherwise render Plot Details under the wrong active-site context. Out-of-context UUIDs
  now fail without revealing the plot.
- Full Pest suite passed: 162 tests, 792 assertions. Pint and the production Vite build
  passed. Browser checks at 320px, 390px, 430px, 768px and 1440px found no document-level
  horizontal overflow.
- See `documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md` for the dedicated
  QA evidence.
- The authorised local fresh migration/seed rehearsal passed. No production-like MySQL
  rehearsal was run or implied.

Purpose:
Define and rehearse the additive, non-destructive migration from the three-service,
single-service/single-date batch and Approved/Rejected lifecycle to the four-service,
per-plot/service, Date Agreed negotiation model.

Scope:

- target state glossary and legacy mapping approval;
- Office Staff global-scope policy/query migration plan;
- schema/data-migration contract for plot-service projections, request-level dates,
  negotiations/proposals and source-import audit;
- legacy UUID, history, notification, lineage and batch-operation preservation plan;
- SQLite and MySQL migration/backfill/reconciliation test plan.

Implementation result:

- Additive target-domain migration adds source-import audit, four projected plot services,
  source product quantities, request-level service/date fields and date
  negotiation/proposal records.
- Existing legacy data is preserved. Legacy Approved is interpreted as legacy Date Agreed
  from its truthful status/date without creating a fictional negotiation or proposal;
  legacy rejected/resubmission data remains unchanged.
- Fenster Office Staff are now globally authorised for review and notifications. Site User
  organisation and assigned-site boundaries remain enforced.
- The target domain, conflict-key and lead-time-provider contracts are recorded in
  `documentation/target-domain-v3.md`.

Explicit exclusions:

- production migration execution or data conversion rehearsal;
- Excel/source connection or import;
- dashboard, New Call Off, review, attachment, calendar or PDF UI work;
- date negotiation, amendment, notification or reminder implementation;
- company testing and production release.

Exit gate:

- Product has approved state names and historic rejected-record treatment.
- Backend has a reviewed additive schema/data plan with no destructive reset.
- QA has SQLite and MySQL fixture/rehearsal assertions for tenant boundaries, UUIDs,
  immutable history, conflict keys and legacy status mappings.

QA result — 20 August 2026:

- SQLite non-empty legacy migration rollback/reapply rehearsal passed: requests, UUIDs,
  histories, Undo/Trash operation evidence, notifications and rejected-resubmission lineage
  remained intact.
- The QA gate removed original synthetic legacy proposal records, corrected zero-quantity
  BF lead-time handling and prevented direct mass assignment of the computed conflict key.
- Full Pest suite passed: 135 tests, 661 assertions. Pint, diff check and production Vite
  build passed. See `documentation/sprint-3a-domain-qa-report-2026-08-20.md`.

Previous Sprint Context:

Sprint 2A - Company Test Readiness

Status:
Sprint 2A QA passed on 19 August 2026. Controlled company testing may begin; production
release remains blocked by the separately documented MySQL and operational gates.

Evidence baseline:
The 19 August 2026 full-site audit is the current source of truth for this sprint. It
supersedes older Sprint 1G claims where they conflict, including the recorded SQLite
rollback result and npm advisory count.

Final QA evidence — 19 August 2026:

- Fresh SQLite migration/seed, disposable migrate/rollback/reapply, full Pest suite
  (118 tests, 632 assertions), Pint, production Vite build, Composer validation and
  `composer audit` passed. `git diff --check` passed.
- `npm audit` remains non-zero with 11 build-toolchain advisories: 9 moderate and 2 high.
  No automatic compatible remediation is offered. This is a production-release risk, not
  a controlled local company-test blocker; do not process untrusted CSS/source-map input
  in the local build environment.
- Herd now lists `https://customerapp.test` as secured. Its certificate has
  `CN=customerapp.test` and SAN entries for `customerapp.test` and
  `*.customerapp.test`; HTTPS navigation succeeded in the browser without bypassing a
  warning. The prior local hostname certificate mismatch is resolved.
- Browser QA verified the notification panel within 320px and 390px viewport bounds,
  Escape/focus return, outside-click close, responsive no-overflow checks through desktop,
  site-role browsing, Office Staff review, withdrawal/Undo, Trash/restore and confirmed
  rejected-request resubmission.

Purpose:
Make the existing portal materially easier and safer to use in the next controlled company
testing session. This is not a production-readiness or new-feature sprint.

Scope:

- AUD-001 SQLite migration rollback integrity and automated rollback/reapply regression;
- AUD-002 responsive notification fly-out at 320px and 390px;
- AUD-003 evidence-led triage of the two high-severity npm advisories, with no dependency
  upgrade unless a compatible, tested remediation is approved;
- AUD-007 retryable notification-fetch failure presentation;
- AUD-005/AUD-008 paginated active-site dashboard and Trash list, with dashboard plot
  search, service and status filters plus a date filter only if it is simple and clear;
- AUD-012 disabled lifecycle controls until an eligible selection is made, while retaining
  server-side validation;
- AUD-013 removal or safe replacement of the dormant welcome-template registration link;
- AUD-009 documentation refresh using the audit's exact current evidence;
- AUD-006 traceable rejected-call-off resubmission only after formal confirmation of
  DEC-034.

Backend implementation — 19 August 2026:

- DEC-034 is now confirmed. Rejected call-offs can be resubmitted through a public UUID
  route with a new date and customer-facing message; the original rejected request remains
  unchanged and the new Submitted request records source lineage.
- The resubmission controller derives site, plot, service, source status and lineage from
  the authorised source request. Its review signature binds source UUID, date, message,
  active site and user. Final persistence calls `ResubmitRejectedCallOffAction`, which
  rechecks eligibility immediately before creating the new request.
- Dashboard requests are active-site scoped, paginated at 15 per page and ordered by batch
  submission time descending, then request ID descending. `plot`, `service` and `status`
  filters combine server-side and are retained in paginator query strings. Requested-date,
  development and phase filters remain omitted because no unambiguous approved contract
  exists for them.
- Customer-facing Trash is active-site scoped, unexpired/recoverable only, paginated at
  15 per page and ordered by Trash timestamp descending, then request ID descending.
- AUD-001 rollback now drops user indexes before indexed columns in separate SQLite schema
  operations. `scripts/verify-sqlite-migrations.ps1` performs disposable SQLite migrate,
  full rollback and reapply verification without relying on a fixed migration count.
- Added batch submission and request Trash indexes supporting the bounded list patterns.

UI implementation — 19 August 2026:

- The notification fly-out now uses a small-screen fixed inset layout and an independently
  scrollable list. It remains within the usable layout width at 320px and 390px, while
  retaining the existing keyboard close and focus-return behaviour.
- Notification loading failures now present the exact customer-safe message
  `Notifications could not be loaded.`, a retry action and the existing notification-centre
  fallback. The empty state is not rendered when the fetch has failed.
- The site dashboard renders the supplied active-site paginator, combined plot/service/status
  filters, clear-filters action and distinct no-result state. Trash renders its supplied
  paginator and states that selections apply to the current page only.
- Lifecycle bulk controls show selected-request count and remain disabled until every selected
  request is eligible for the relevant action. Server-side lifecycle authorisation and
  validation are unchanged.
- Rejected requests now expose the approved resubmission entry point, customer-safe new-date
  form and confirmation screen. Derived source context is display-only; only the new date,
  customer-facing message and server-issued confirmation signature are posted.
- Removed the unused Laravel welcome template. The root route continues to redirect to login,
  and public registration remains unavailable.

Acceptance criteria:

1. A SQLite migrate, rollback and reapply sequence succeeds from a disposable database and
   is covered by an automated regression.
2. The notification fly-out is fully visible and usable at 320px and 390px widths, with
   visual regression coverage.
3. The current npm audit result is recorded accurately; each high finding has either a
   compatible tested fix or a documented, time-bounded risk decision.
4. A notification fetch failure cannot appear as an empty notification list: it presents a
   clear retry action and a notification-centre fallback.
5. The site dashboard and Trash list use bounded pagination. The dashboard remains scoped
   to the active assigned site and uses server-authorised plot search, service and status
   filters. A date filter is included only when it has a clear defined meaning.
6. Lifecycle actions are unavailable without an eligible selection, but every server-side
   authorisation and validation check remains intact.
7. The dormant welcome template cannot reference public registration.
8. The documented audit baseline, advisory count, verification date and current risks are
   accurate after the sprint's QA run.
9. If DEC-034 is confirmed, rejected resubmission preserves the original decision and
   creates a new linked submitted batch/request only after review, confirmation and fresh
   eligibility checks. If it is not confirmed, the feature is excluded from Sprint 2A
   implementation and company-test acceptance.

Implementation order:

1. Reproduce and correct AUD-001, then add its regression test.
2. Correct AUD-002 and AUD-007, with narrow viewport and failure-state coverage.
3. Implement the bounded dashboard and Trash contract (AUD-005/AUD-008), preserving
   active-site authorisation and lifecycle bulk-action traceability.
4. Implement AUD-012 and AUD-013.
5. Triage AUD-003; make no package change without approved compatible remediation and
   full relevant verification.
6. Implement AUD-006 only after DEC-034 confirmation.
7. Run focused QA, update evidence documentation and re-check company-test acceptance.

Work split:

- UI: notification fly-out/error state, dashboard and Trash pagination presentation,
  dashboard controls, selection-state affordances, welcome-template cleanup and
  resubmission screens if approved.
- Backend: rollback integrity, server-side dashboard and Trash query bounds, secure filter
  validation, pagination, and the existing resubmission action's route/controller/policy
  integration only if DEC-034 is approved.
- QA: migration rollback/reapply, 320px/390px visual checks, notification failure state,
  active-site/tenant filter isolation, dashboard and Trash pagination, selection-state
  behaviour, npm audit evidence and end-to-end resubmission lineage if implemented.

Explicitly deferred:

- MySQL validation, backup/restore, monitoring, production configuration and deployment;
- physical-device, keyboard-only and screen-reader release evidence;
- amendments, approved-request change/reopen and later customer-facing progress statuses;
- SiteApp integration, QR context, email, push, reporting and advanced analytics;
- development or phase filters until supported projection data exists.

Product decision status:
DEC-034 was confirmed on 19 August 2026. No additional product decision blocks the
remaining Sprint 2A work; the optional date filter remains omitted because its user-facing
semantics have not been confirmed.

---

Sprint 1G - Production Hardening

Status:
Implemented for locally verifiable hardening; deployment remains blocked.

Purpose:
Prove the existing Sprint 1A-1E portal is safe to prepare for a first production
deployment without adding product features or starting SiteApp integration.

Completed in this sprint:

- Verified the local locked toolchain and reran the full regression suite.
- Corrected SQLite rollback of the secure-access migration by dropping its single-column
  indexes before removing the indexed columns.
- Reviewed transaction and row-lock coverage for submission, approval/rejection,
  withdrawal, Trash, restore, Undo and history sequencing.
- Upgraded only the audited Composer dependency path: Guzzle 7.15.3, CommonMark 2.9.0,
  Guzzle promises 2.5.2, Guzzle PSR-7 2.13.0 and nette/utils 4.1.5.
- Hardened notification failure logging so exception payloads and messages are not written
  to application logs.
- Confirmed current notifications remain synchronous; no queue or scheduler mutation was
  introduced.
- Documented MySQL, production configuration, backup, monitoring, device/accessibility
  and performance blockers in the Sprint 1G report.

Current evidence:

- Pest: 101 tests and 521 assertions passed.
- Pint, Composer validation, Vite build and SQLite migration/seed/rollback/reapply pass.
- Composer audit is clean after the targeted dependency update.
- npm audit still reports 10 moderate PostCSS-chain advisories with no available fix.
- MySQL is unavailable locally; MySQL-specific migration, locking and concurrency checks
  remain deployment-environment work.

Release decision:

- Not yet safe to deploy.
- No production deployment, SiteApp integration, QR scanning, commit, tag or release
  candidate was created.

Sprint 1E - In-App Notifications

Status:
Implemented and QA verified after correction

Purpose:
Add database-backed, customer-safe in-app notifications for successful submitted,
approved and rejected call-off transitions. Email, push and SiteApp integration remain
out of scope.

Implemented:

- Added `portal_notifications` with public UUIDs, recipient ownership, stable event keys,
  customer-safe request context, read timestamps and dismissal timestamps.
- Added `CallOffSubmitted`, `CallOffApproved` and `CallOffRejected` events. The audited
  submission, approval and rejection actions dispatch events only after their database
  transaction succeeds.
- Added `CallOffNotificationListener` and `PortalNotificationService`. Submission goes
  to the submitting site user and assigned Office Staff; approval and rejection go only
  to the submitting site user. Recipients must remain active and assigned to the request
  site in the same customer organisation.
- Added idempotent event keys per recipient and request. Listener failures are logged and
  do not roll back a successful call-off transition.
- Added `PortalNotificationQueryService` for recipient-scoped active lists, unread count,
  mark-one-read, mark-all-read and dismissal.
- Added authenticated JSON endpoints for listing, unread count, mark-one-read,
  mark-all-read, dismissal and safe notification opening. Opening re-authorises the
  current user and sets the active site only after site access is confirmed.
- Notification payloads exclude `internal_reason`, operation snapshots, conflict keys and
  internal numeric identifiers. Withdrawal, Trash, restoration and Undo emit no events.
- Notification queries and actions re-check the recipient's active account, current portal
  role, organisation and current site assignment; revoked or role-changed recipients see
  neither retained notification data nor safe-open links. Development preview users are
  excluded from notification creation and access.
- Recipient notification writes are transactional, so a failed recipient write cannot
  leave a misleading partial set for a successful event.
- Added an authenticated notification bell to the shared portal layout with a server-backed
  unread badge, accessible label and mobile-sized touch target.
- Added an Alpine-powered recent notification panel using the existing JSON feed, with
  mark-read, mark-all-read and dismissal actions plus a no-JavaScript link to the centre.
- Added a server-rendered notification centre at `/portal/notifications/centre` with
  newest-first pagination, read/unread styling, safe-open links, no-JavaScript forms,
  dismissal and empty states.

Routes added:

- `GET /portal/notifications`
- `GET /portal/notifications/unread-count`
- `GET /portal/notifications/{notificationUuid}/open`
- `POST /portal/notifications/{notificationUuid}/read`
- `POST /portal/notifications/read-all`
- `POST /portal/notifications/{notificationUuid}/dismiss`
- `GET /portal/notifications/centre`

Tests added:

- `PortalNotificationsTest` covers recipient scope, customer-safe approved/rejected
  payloads, idempotency, read/unread state, mark-all-read, dismissal, revoked links and
  transition success when notification creation fails.
- `PortalNotificationUiTest` covers the authenticated bell and unread count, recipient
  isolation, server-rendered centre cards, read/unread presentation and no-JavaScript
  mark-read/mark-all-read/dismiss forms, including redirect behaviour.
- Focused QA coverage verifies revoked assignments, role changes, preview-user exclusion
  and current-authorisation filtering.

Remaining risks:

- Mobile-device and assistive-technology QA for the notification bell and centre remains
  to be completed; a live local browser interaction pass was completed.
- MySQL concurrency and long-term notification retention remain unverified/open.

Previous Sprint Context:

Sprint 1D - Withdrawal, Trash and Undo

Status:
Implemented and QA verified after correction

Purpose:
Add authenticated active-site withdrawal, Trash, restoration and quick Undo UI without
changing the audited Sprint 1B lifecycle domain.

Implemented:

- Added active-site confirmation screens for withdrawal, moving to Trash and restoration.
  Confirmation is server/session signed against the exact operation, active site, user and
  selected request UUIDs; direct final actions and tampered selections are rejected.
- Added dashboard controls for eligible Submitted, Rejected and Withdrawn requests.
  Operations require a complete selection from one batch; Approved requests have no
  destructive site-user action.
- Added an active-site Trash screen for current, unexpired records only. It shows public
  request details and customer-visible responses, never internal reasons or IDs.
- Added a five-second Undo notice after withdrawal, Trash and restoration. The browser
  countdown is visual only; the existing domain action remains server-authoritative.
- Controllers consume only `WithdrawCallOffRequestsAction`,
  `TrashCallOffRequestsAction`, `RestoreCallOffRequestsAction` and
  `QuickUndoCallOffOperationAction`.
- Expiry visibility derives from `customerTrash()` and `trash_expires_at`; no scheduler
  is needed for this UI behaviour.

Routes added:

- `GET /portal/call-offs/trash`
- `POST /portal/call-offs/lifecycle/confirm`
- `POST /portal/call-offs/lifecycle/{withdraw|trash|restore}`
- `POST /portal/call-offs/operations/{operationUuid}/undo`

Tests added:

- `CallOffLifecycleUiTest` covers site-role withdrawal, confirmation enforcement,
  mixed/duplicate selection rejection, Approved protection, scoped Trash,
  internal-reason non-disclosure, expiry filtering, restoration and server-timed Undo.
- Sprint 1D QA added coverage for tampered final lifecycle operations, replayed
  confirmations, dashboard status counts excluding trashed records and same-site
  cross-user Undo rejection.

QA result - 6 August 2026:

- Formal Sprint 1D QA audit passed after two defects were corrected.
- Quick Undo is now restricted to the user who performed the original operation, while
  still requiring current active-site assignment.
- Site dashboard status summaries now exclude trashed requests, matching the active
  dashboard request list.
- Lifecycle confirmation binds operation, ordered selected request UUIDs, active site ID
  and acting user ID.
- Customer-facing Trash visibility uses server-time expiry and does not delete expired
  audit records.

Remaining risks:

- MySQL-specific locking and unique-index behaviour remain unverified.
- A live assistive-technology and mobile-device pass remains before production readiness.

Previous Sprint Context:

Out of Scope:

- Trash.
- Withdraw.
- Undo.
- Notifications.
- QR scanning.
- SiteApp integration.
- Email.
- Completion or supersession workflow.
- Filament resources.

Verification - 5 August 2026:

- Formal Sprint 1C submission-to-decision QA audit passed after a confirmation-integrity
  defect was corrected.
- Final New Call Off submission now requires the exact server/session-confirmed payload
  reviewed on the confirmation screen.
- Added coverage for direct final-submit rejection, tampered confirmation-payload
  rejection and eligibility recheck after confirmation.
- `php artisan test tests/Feature/OfficeStaffReviewRequestsTest.php` passed.
- `php artisan test tests\Feature` passed.
- `php artisan test` passed.
- `vendor/bin/pint --test` passed after a formatter fix was applied.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.

Previous Sprint Context:

Sprint 1C.2 - New Call Off

Status:
Implemented and verified

Purpose:
Add the first production customer-facing call-off submission UI against the audited
Sprint 1B domain actions and Sprint 1C.1 authenticated active-site dashboard.

Implemented:

- Added a New Call Off entry point from the authenticated active-site dashboard.
- Added a New Call Off form for active assigned-site users only.
- The form captures one active site, one service type, one requested date, one or more
  projected plots and customer-facing submission text.
- Eligible projected plots are calculated per service by calling
  `DetermineCallOffEligibilityAction`; the UI does not duplicate call-off eligibility
  rules.
- Completed projected plots, cross-site projected plots and projected plots with active
  duplicate conflicts are not offered for submission.
- Added a confirmation screen so the user reviews the call-off before any batch or request
  records are created.
- Final submission calls `SubmitCallOffBatchAction`; controllers do not manually create
  call-off batches or requests.
- Submission creates one batch and one individual request per selected projected plot.
- Submission success redirects back to the site dashboard and the new requests appear in
  the dashboard's real request list immediately.
- Validation returns clear customer-facing feedback without exposing internals.
- Submit buttons show a waiting state to reduce accidental duplicate clicks.

Out of Scope:

- Review Requests queue.
- Approval and rejection UI.
- Notifications.
- Trash.
- Undo.
- QR.
- SiteApp integration.

Verification - 5 August 2026:

- `php artisan test tests/Feature/NewCallOffTest.php` passed.
- `php artisan test` passed.
- `vendor/bin/pint --test` passed.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.
- `git diff --check` passed.

Previous Sprint Context:

Sprint 1C.1 - Authenticated Site Dashboard

Status:
Implemented and verified

Purpose:
Replace the remaining authenticated site preview screens with the Sprint 1C.1 customer
site path:

- Laravel authentication;
- assigned-site selection for site roles;
- authenticated active-site dashboard using real Sprint 1A and Sprint 1B relationships;
- Office Staff routing to a Review Requests placeholder only.

Implemented:

- Site users see only sites assigned to their authenticated portal account.
- Site selection stores the active site in the session only after server-side assignment
  and customer-organisation checks.
- The active site can be changed from the dashboard.
- The site dashboard shows the active site name, customer, signed-in assigned user,
  outstanding projected plot count, outstanding projected plot references, existing
  call-off requests and current statuses.
- Call-off cards show plot, service, requested date, status, submitted by, submission
  date and customer-visible decision response where one exists.
- Preview data, hardcoded call-off cards, Delete controls and New Call Off UI were
  removed from the authenticated site dashboard.
- Empty states cover no assigned sites, no projected plots and no call-off requests.
- Site selection includes an accessible loading state during submission and error
  feedback for invalid selections.
- Fenster Office Staff route to the existing Review Requests placeholder without building
  the review queue or decision actions.

Out of Scope:

- New Call Off.
- Review Requests queue and approval/rejection actions.
- Notifications.
- Trash.
- Undo.
- QR.
- SiteApp integration.

Verification - 5 August 2026:

- `php artisan test` passed.
- `vendor/bin/pint --test` passed after a formatter fix was applied.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.
- `git diff --check` passed.

Previous Sprint Context:

Sprint 1B - Call-Off Domain Implementation

Status:
Implemented and QA verified

Purpose:
Implement the approved call-off domain foundation against the Sprint 1A access and
authorisation boundaries.

Inherited Foundation:

- Authentication routes, forms and session handling are in place.
- Public registration is unavailable.
- Active-account middleware blocks inactive or incomplete portal profiles.
- Customer organisations, portal roles, sites and site assignments are modelled.
- Site-role dashboard routing uses active-site context.
- Fenster Office Staff routing uses assigned-site review scope.
- Development role preview uses controlled preview users in local/test only.
- Pest coverage has been added for the Sprint 1A access and authorisation contract.
- Database planning for call-off batches and individual call-off requests is complete.

Scope:

- Service type enum for Cavity Closers, Windows and CML.
- `projected_plots` as the Customer Portal plot projection.
- Call-off batches with one site, one service type, one requested date and one submitting
  user.
- Individual call-off requests for each selected projected plot.
- UUIDs on Sprint 1B business tables.
- Centralised eligibility through `DetermineCallOffEligibilityAction`.
- Computed duplicate blocking through `UpdateConflictKeyAction`.
- Submitted, approved, rejected and withdrawn lifecycle.
- Customer-facing Trash as a list state, not a status.
- Immutable event history with separate `customer_response` and `internal_reason`.
- Actions, policies, models, relationships, migrations, tests and seeders for the approved
  Sprint 1B domain.

Out of Scope:

- UI replacement for preview screens until the backend contract is implemented.
- Notifications.
- Calendar.
- Reporting.
- Photos.
- Manufacturing.
- Build Verification.
- Mobile App.
- SiteApp workflow, roles, policies, tables, queries or internal statuses.

Confirmed Routing:

- Site Manager, Assistant Site Manager and Finishing Foreman select an assigned site and
  open the site dashboard.
- Fenster Office Staff open the Review Requests dashboard scoped to assigned sites.
- Role preview is development-only and cannot be enabled in production.
- A future QR code may identify a site, but never bypasses authentication or
  authorisation.

Confirmed Version 1 Decisions:

- Fenster Office Staff review, approve and reject call-offs for assigned sites only.
- Site Manager, Assistant Site Manager and Finishing Foreman users see all call-offs for
  the active assigned site.
- Site Manager, Assistant Site Manager and Finishing Foreman remain distinct roles with
  identical Version 1 permissions.
- Pending submitted call-offs can be withdrawn by authorised site users for the active
  assigned site before an office decision.
- Submitted requests block duplicate active requests for the same projected plot and
  service.
- Approved requests block duplicate active requests for the same projected plot and
  service until a future authorised lifecycle event marks them completed, superseded or
  otherwise formally closed.
- Rejected and withdrawn requests do not block resubmission.
- Approved call-offs cannot be deleted, trashed or cancelled by site users in Version 1.
- Rejected call-offs remain in history, may be moved to customer-facing Trash and support
  traceable resubmission.
- Eligible withdrawal, Trash and restoration actions must offer a five-second quick Undo.
- Customer-facing Trash keeps eligible withdrawn or rejected call-offs recoverable for
  seven days.
- Trash does not independently determine duplicate blocking.
- Bulk Undo and bulk Trash restoration are atomic.
- Expired Trash records are hidden from customer-facing Trash after seven days and
  retained as audit-critical history, not permanently deleted by the Version 1 Trash
  expiry process.

Confirmed Batch Model:

- One call-off batch represents one user submission action.
- One call-off batch has exactly one site, one service type, one requested date and one
  submitting user.
- One batch may contain one or many individual call-off requests.
- Mixed service types and mixed requested dates are not permitted in a Version 1 batch.
- Each request applies to one projected plot for the batch service type.
- The batch groups submission, bulk withdrawal, quick Undo and Trash restoration.
- Each individual request owns its own status, approval, rejection, decision and history.
- Fenster Office Staff may decide individual requests independently after submission.
- Once requests in a batch have received different decisions, later batch-level operations
  must not overwrite those individual decisions.

Confirmed Implementation Refinements:

- The portal plot projection table is named `projected_plots`.
- `active_conflict_key` is a computed domain value updated only by
  `UpdateConflictKeyAction`.
- Approved requests retain their active conflict key.
- Rejected and withdrawn requests clear their active conflict key.
- Trash and restore do not independently affect duplicate blocking.
- Use explicit `customer_response` and `internal_reason` fields, not generic notes.
- Every Sprint 1B business table has an integer primary key and a public UUID.
- Call-off eligibility is centralised in `DetermineCallOffEligibilityAction`.
- History records events separately from the current request status.

Pending Product Decisions:

- Customer-facing meaning of CML.
- Bulk call-off creation limits and validation feedback.
- Amendment eligibility and lifecycle beyond Version 1 withdrawal, rejected-request
  resubmission, Trash and Undo.
- Required email notification rules.
- Synchronisation freshness.
- Customer-facing status mapping beyond submitted, approved, rejected and withdrawn
  request states.
- Long-term retention periods outside the seven-day customer-facing Trash window.
- Initial SiteApp integration method.

Sprint Owner:
Joshua Oakley

Implementation Chat Alignment:

- Product: guard scope, terminology, acceptance criteria and unresolved business rules.
- UI: wait for the backend contract before replacing preview screens.
- Backend: implement enums, migrations, models, relationships, actions, policies, tests
  and seeders in that order.
- QA: prioritise eligibility, duplicate blocking, approval authority, Trash and history
  tests.
- Platform: keep Herd, SQLite, build and test tooling stable without package upgrades.

Implementation Result - 5 August 2026:

- Sprint 1B call-off domain foundation has been implemented in backend/domain code.
- Enums, migrations, models, relationships, actions, gates, factories, seed data and Pest
  coverage are in place.
- The final customer-facing call-off UI and Office Staff review UI remain out of scope.
- Email notifications, QR scanning, SiteApp integration, amendments beyond rejected
  resubmission and approved-request closure lifecycle remain out of scope.

QA Result - 5 August 2026:

- The dedicated QA audit passed after integrity corrections.
- UI integration may consume the audited domain actions only through authenticated,
  server-authorised entry points that use the active assigned-site context.
- MySQL-specific locking and migration behaviour remain to be verified against a MySQL
  environment before production readiness.
