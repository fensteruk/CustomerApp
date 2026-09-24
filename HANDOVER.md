# Fenster Customer Portal Handover

## Wald disposable customer cleanup — 24 September 2026

The local `main` candidate adds an advanced certified purge for the old
`Sherford Countryside 2D - Vistry PShips` demo customer, including its Portal
request history. First confirm FNA2563 against Vistry → Sherford Countryside
2D in the existing import; the source-name and old-binding confirmations are
separate. Then review the complete demo-customer purge impact. It blocks while
a shared binding is still active or latest on a site to be removed. The
customer's users are deactivated and detached, shared master uploads and
active bindings elsewhere are retained. No production purge has been run.
Verify a production recovery point and a fresh preview before execution.

## Wald FNA2563 source-site name review — 24 September 2026

The local `main` candidate lets Office confirm that FNA2563's source label
`Countryside 2D` means the existing Vistry site `Sherford Countryside 2D`
for selected rows in the stored upload. The page requires this confirmation
and a separate confirmation to correct the old binding. Prior binding and
customer/source history stay intact. No production data was changed; the
candidate is not pushed or deployed. After deployment, reopen the existing
Unknown rows page and continue to one-site preview and Apply.

## Wald existing-site binding correction — 24 September 2026

The local `main` candidate lets Office correct a conflicting active
CustomerCode binding while reviewing the current stored upload. Choose the
existing active Customer and Site, confirm the binding conflict, select the
rows to include and continue to site preview and Apply. The prior binding
version and source audit remain; no new upload is needed. This is a general
CustomerCode workflow. No production data was changed or deployed in this
task.

## Wald CustomerCode review and Unknown queue — 24 September 2026

The local MASTER04 candidate adds one-code-at-a-time Office row confirmation,
all-selected-by-default review, deterministic string plot proposals and a
final Unknown queue. Malformed groups can be deferred without fabricating a
Customer/Site; Office can later resolve or exclude individual rows. Genuine
XLS and XLSX sources were qualified only in disposable MySQL, with no
customer-facing Apply, push or deployment. See
`documentation/wald-master04-customer-code-review-2026-09-24.md` for counts,
verification and safety boundaries.

## Wald Office structural approval — 24 September 2026

The local MASTER03 continuation adds explicit Office approval for new
customer/site structures found by the workbook resolver. The action uses
normal administration creation, one canonical exact binding action and an
atomic audit trail. Approved units immediately resolve to the active binding;
other proposals refresh, while conflicts remain blocked. No users or plots
are created by structural approval. No push or deployment occurred. See
`documentation/wald-master03-office-creation-2026-09-24.md` for QA evidence.

## Wald automatic resolution — 24 September 2026

The local `codex/wald-master02` candidate integrates the qualified hierarchy
parser and adds one grouped operational resolver per included CustomerCode.
Exact existing customer/site matches can acquire an audited binding during
Office selected-site review; new customer/site relationships remain proposals.
The genuine XLS qualification used a disposable test dataset, with no
production import or data change. The existing one-site commit boundary remains.
See `documentation/wald-master02-automatic-resolution-2026-09-24.md` for exact
counts and verification status. No push or deployment occurred.

## Second-wave UI and amendment integration — 23 September 2026

The four accepted second-wave branch tips are merged in order on the isolated
`codex/customer-ui-integration02` worktree from first-wave base `f94a750d`.
Canonical effective requested dates now drive Office and Site presentation;
one current amendment is counted per request. The exact weekday
1 Oct → 3 Oct → 5 Oct sequence is covered across Plot and Office. No migration,
push or deployment occurred. RedZebra writeback and automatic source-date
confirmation remain deferred. See
`documentation/customer-ui-integration02-second-wave-2026-09-23.md` for scope,
verification and remaining release-review checks. Earlier separate-branch
and first-wave notes below are historical.

## Plot workspace and live amendments — 23 September 2026

CUSTOMER-UI-OVERHAUL06 is a separate feature-branch candidate based directly on
`f94a750d820d5a635d6e4fcac87ef3bd02079fc2`. The Plot workspace and Site User
amendment entry/domain behavior are implemented in `codex/customer-ui-overhaul06`.
Awaiting Fenster and pending amendments can be corrected immediately on the same
request, preserving earlier history. No migration. No push or deployment.
See `documentation/customer-ui-overhaul06-2026-09-23.md` for qualification and
integration contracts. Concurrent Site workspace, Office Amendments and Wald
Reconciliation page ownership remains separate.

## Current handover — first-wave Office UI integration — 23 September 2026

Local `main` contains the accepted Dashboard, Customers, Users and Imports redesign
commits. Integrated browser QA covered desktop, tablet and narrow mobile widths with
fictional data. A small Office sidebar correction makes Dashboard directly reachable
and highlights its current route. No production push or deployment occurred, and the
second redesign wave has not started. The integration report records exact commit and
test evidence. Three pre-existing uncommitted Cavity Closer changes are still present
in the original checkout and are not part of the UI baseline.

## Current handover — product quantity correction candidate — 22 September 2026

DEC-075 maps CAS and FLU to Windows and PFD to Doors. GLS/WP/MISC remain
excluded from customer product projection with private raw evidence. Approved
row exclusions still apply. Knowledge and uncommitted previews
from the former dictionary become stale. Previously committed plots do not
gain skipped quantities until an explicitly reviewed correction import.
Disposable local qualification of the small real workbook verified Plot 591
with FLU 9, PFD 2 and PSU 1, yielding Windows 9 / Doors 3. No production
import, push or deployment has occurred. The genuine full XLS has positive
CAS, FLU and PFD on 1,848, 483 and 1,568 supported rows respectively. The
focused disposable MySQL 8.4 gate passed 42 tests and 204 assertions.

## Current handover — existing import re-analysis candidate — 22 September 2026

The feature branch adds a confirmed Office action for a fresh analysis of an
existing uncommitted selected-site upload under DEC-073. It retains the old
context, answers, staged rows and preview privately, and makes them stale for
review or commit. The same source bytes, date/slot and revision are reused.
No production import was re-analysed, approved, applied or otherwise changed.
See the candidate report for exact test and Git evidence before release review.

## Current handover — DEC-071 real-source semantics — 22 September 2026

The local feature branch now excludes `CU0`, `CU1`, `CU3`, `P04` and `zzz`
under DEC-071, with P04 explicitly temporary. Both real files passed local
pilot upload and representative non-mutating previews. The small file remains
16 total/15 included/one CU4 excluded. The genuine XLS has 4,358 total,
2,567 included, 1,791 excluded and zero unresolved Call Types. Of 145 source
units, 59 contain supported rows, 86 contain excluded rows only, and zero
have a Call Type semantic blocker. No real import was committed. See the
[qualification report](documentation/wald-final-call-type-qualification-2026-09-22.md).
Production runtime/advisory checks and controlled release review remain; no
push or deployment. Earlier code-count snapshots below are historical.

## Current handover — DEC-070 source exclusions — 22 September 2026

The feature branch adds 25 exact global irrelevant Call Types under DEC-070,
preserving the separate CU4 rule and approved service meanings. The full XLS
has 4,358 rows: 3,036 included, 1,322 excluded and 469 unresolved-code rows.
Of 145 source units, 13 are selectable with no Call Type semantic blocker,
28 contain only excluded rows, and 104 still contain unknown codes. Local
small/full representative previews are clean and non-mutating. No real data
was committed. See [qualification](documentation/wald-call-type-exclusions-2026-09-22.md).
Release/runtime/advisory checks remain; no push or deployment. The CU4-only
snapshot below is historical.

## Current handover — CU4 customer-care exclusion — 22 September 2026

DEC-069 approves ignoring every exact `CU4` Call Type row: Customer Care is outside
CustomerApp. The feature branch `codex/wald-realdata03-optional-completion` implements
the global dictionary exclusion with private provenance and dictionary v5 staleness.
The supplied small workbook now reaches a clean, non-mutating selected-site preview:
16 records, 15 included, one CU4 excluded. The genuine XLS is accepted directly:
4,358 records, 3,419 included, 939 CU4 excluded. No real data was committed. Other
unknown full-export codes still block their affected selected sites. Production PHP
extension/temp verification, a fresh Composer advisory check and release review remain.
No push or deployment has occurred. The earlier CU4 decision request below is historical.

## Current handover — real source qualification — 22 September 2026

**Feature branch only; management mapping decision required.** The combined XLS and
`Customer Number` candidate was privately qualified against the small `.xlsx` and full
4,358-record genuine `.xls` export. An approved-contract defect requiring optional
`Complete` was corrected on `codex/wald-realdata03-optional-completion`; a synthetic
partial-export regression preserves established completion. The small file reaches a
blocked selected-site preview because `CU4` is unknown (1 occurrence; 939 in the full
export). Representative approved-only full-export rows resolve exact site binding and
site-scoped plots. No real-source preview was approved or committed. The user raised
Forge upload/execution limits to 5 MB / 45 seconds; the agent verified the saved UI
values. Disposable MySQL 8.4 release groups passed: 285 passed, 4 skipped, 2,996
assertions. Production PHP extensions and temporary-directory readiness remain unverified;
the fresh Composer advisory endpoint timed out.
No candidate code was pushed or deployed; `main` remains `51635964`. See the
[real-source qualification report](documentation/wald-real-source-qualification-2026-09-22.md)
for aggregate evidence, test results and remaining release gates. Earlier entries below
are historical checkpoints.

## Current handover — legacy XLS master export candidate — 22 September 2026

**Feature branch only; not deployed:** `codex/wald-xls-reader` combines the bounded binary
`.xls` upload/neutral reader commit `dde25a6` with the previously approved exact `Customer Number`
header commit, cherry-picked as `16964ff` under DEC-068. The supplied 22 September RedZebra XLS
passed private storage, structural staging and source discovery locally: 4,358 records in 145
source groups, with 192 MiB observed peak PHP memory. No binding, preview approval, commit or
production import was performed. Full local suite: 1,628 passed, 81 skipped, 8,500 assertions;
Pint, strict Composer validation, Composer audit, Vite build and production npm audit pass.
No migration or production configuration changed. The new PhpSpreadsheet runtime dependency and
production PHP extension/memory settings require release qualification. Disposable MySQL 8.4 and
Forge verification were not run. Review the exact candidate, dictionary-v4 knowledge staleness,
source questions and remaining release gates before any production push or re-upload. See the
[XLS candidate report](documentation/wald-xls-upload-2026-09-22.md). Entries below are historical
checkpoints and do not describe this combined branch.

## Current handover — `Customer Number` header candidate — 22 September 2026

`codex/wald-customer-number-header` adds only the exact approved RedZebra `Customer Number`
header to the existing CustomerCode role under DEC-068. Dictionary v4 has a new fingerprint;
prior analysis knowledge is stale and must not be silently reused. Synthetic tests prove the
new header, unchanged explicit binding, refusal of a near-match and competing code columns, and
old-pin staleness. Final local suite passes 1,705 tests / 8,481 assertions / 81 expected skips;
Pint and Vite build pass. Composer is not installed here; no disposable MySQL or production
qualification has run. No schema, dependency, workbook, production setting, import or deployment
changed. The live second upload is still failed Revision 2; Revision 1 remains superseded in
history. Obtain separate release review and complete remaining gates before any `main` push or
Forge action. Earlier handovers below remain historical release evidence.

## Current handover — product labels deployed and verified — 21 September 2026

**SUCCESS — PRODUCT LABELS VERIFIED.** Production/main remain
`b0bdca00ffd3cd8b307e28d798b133c8b4f978cc`, Forge `78156998` (31 seconds).
Live E2E VS labels/quantities and WALD-GL-01 Bifold/VS labels/check summaries pass.
Positive BF shows 26 October earliest (five weeks); other semantic boundaries retain tests.
Office totals/source evidence, Linked/bindings, E2E uniqueness and replacement history pass.
Site Manager Office/Imports denial and both-role fresh GET logout Back/Forward checks pass.
Earlier POST history cache-miss remains documented, not reclassified as a clean POST test.
Final served check at 02:06:32 UTC: 21 applied/0 pending, 0 queued/failed; `/up` healthy.
Final log check at 02:06:55 UTC has zero matches across all seven recorded error patterns.
No call-off submission, import, binding/data edit or additional deployment. App logged out.
See [final release report and exact verification limits](documentation/customer-product-labels-deployment-2026-09-21.md).
This branch remains local/non-deploying. Below checkpoints are historical and superseded.
Next proposed bounded task: import completion/status wording, separately approved.

## Current handover — product labels deployed; live acceptance pending — 21 September 2026

Production and remote main are `b0bdca00ffd3cd8b307e28d798b133c8b4f978cc`.
Forge `78156998` deployed successfully (31 seconds displayed); Nothing to migrate.
Served-SHA check at 01:37:17 UTC: 21 applied / 0 pending migrations, 0 queued/failed jobs.
Post-release `/up` passes and checked error counts at 01:37:52 UTC are zero.
Exact two-commit ancestry was reconciled; documentation parent `12d765c` was intentionally
retained. No rebase, import replay, data edit or call-off submission. Fresh focused tests pass.
**Site Manager labels/check-summary are verified live; Office acceptance is pending.**
Both E2E plots show Vertical Slider 2 and matrix summaries match. Office/Imports return 403;
normal logout plus first two Back checks show sign-in. Third Back hit browser ERR_CACHE_MISS
for the POST matrix; Forward was not exercised. No call-off submitted. User has been asked
to navigate normally to login and sign in as Office. Do not claim full acceptance yet.
See [saved release checkpoint and continuation checklist](documentation/customer-product-labels-deployment-2026-09-21.md).
This evidence branch is local/non-deploying. Earlier candidate and Overview entries below
are historical and superseded for deployment status. Do not push another production revision.

## Current handover — customer product labels candidate — 21 September 2026

`codex/customer-product-labels` preserves local documentation commit
`12d765ccec90b05973365f7845a54c184fb2325d` on deployed baseline
`590503b6c80ea186bc6001e43a11ab528298961a`. **READY FOR RELEASE REVIEW; not deployed.**
Shared `CustomerProductPresenter` consumes the unchanged approved dictionary. Plot Details
and call-off matrix/confirmation display friendly names; canonical review rows and every
business action remain unchanged. Office totals/source evidence are preserved.
24 focused / 119 assertions; 89 grouped / 538; Wald group 687 passed / 25 skipped / 3,359;
full suite 1,620 passed / 81 skipped / 8,465. Build, repository Pint, syntax, Composer and
production npm audit pass; four known development npm advisories remain. Local normal-login
Site Manager/Office walkthrough and read-only Frontend/UX review pass.
See [exact scope, commands and limitations](documentation/customer-product-labels-ux-fix-2026-09-21.md).
Do not push main, deploy, replay imports or modify production without separate approval.
Unknown legacy labels are a business-data gap; existing substring-based BF detection is a
separate pre-existing semantic concern, explicitly not changed by this presentation task.

## Current handover — Overview deployed and verified — 21 September 2026

Production and remote main are `590503b6c80ea186bc6001e43a11ab528298961a`.
Forge deployment `78155443` succeeded in 38 seconds; Nothing to migrate. Final served-SHA
check at 01:02:54 UTC confirms 21 applied migrations, none pending and zero failed/queued
jobs. Linked/unbound screens agree, existing Willow bindings and E2E plots are unchanged,
customer access boundaries and both-role logout Back/Forward checks pass.
See [complete release evidence](documentation/site-overview-source-binding-deployment-2026-09-21.md).
The Site Manager's reported 403 was an Office-only URL; normal site selection/dashboard
worked and showed the correct signed-in identity. No access or application fix was required.
Documentation is on a separate local non-deploying branch; do not push another production
revision from this handover. No call-off/import/binding/assignment changes were made.
Earlier handovers below are historical. Proposed next task: customer-facing product labels.

## Current handover — Overview binding consistency — 21 September 2026

Feature-branch candidate: `codex/fix-site-overview-source-binding`, parent/base
`0ac7082141156fdff29d296881bbb7d3299e20b5` (fresh `origin/main`). The latest user
request confirms production acceptance of the core workflow; the privacy candidate
status below is historical.
Only the Office Overview view changes at runtime. It now uses the existing
`sourceBindings()` availability and `has_active_binding` result already supplied by
the controller, not the hard-coded site-summary placeholder. Source detail, queries,
binding writes, imports, projection and policies are untouched. Local linked/unbound
browser checks pass. READY FOR RELEASE REVIEW: 9 focused / 108 grouped passes;
full suite 1,595 passes, 81 skips and the one known date-sensitive error. Build, Pint,
syntax and Composer pass; four existing development npm advisories remain unchanged.
See the [Overview repair report](documentation/site-overview-source-binding-fix-2026-09-21.md)
for exact tests, known baseline findings and release instructions.
No migrations, data rewrite, dependency changes, merge, push or deployment.
Require separate release approval; customer product labels are the next proposed UX task.

## Current handover — Logout privacy candidate — 20 September 2026

`codex/fix-logout-cache-privacy` starts at production/main
`76cabfcb3a906476e6a47b63b797db1025590e10` (Forge deployment `78135685`). A small web
response middleware adds no-store/private for authenticated responses, including POST
confirmation HTML, without changing logout/session, authorisation, Wald or assignments.
READY FOR RELEASE REVIEW: local Office and Site Manager Back/Forward checks pass;
7 focused / 103 grouped tests pass. Full regression has 1,586 passes / 81 skips and
the single known date-sensitive baseline error. Build, Pint, syntax and Composer pass;
the four existing development npm advisories are unchanged.
No main merge, push, production change or deployment has occurred. The
[privacy hotfix report](documentation/logout-browser-back-privacy-hotfix-2026-09-20.md)
records scope, evidence and release limitations. Earlier handovers are historical.

## Current handover — CUSTOMER-WALD-SOURCE02 — 15 September 2026

SOURCE02 is merged into local `main` at `2c5fa6d3888bcb9c6a46457c0ad3bbbb35972fa9`
and approved for push under DEC-067. It implements master-export same-slot revisions and
authoritative CustomerCode binding under DEC-066. Existing schema already supports successor uploads and generic
identity kinds, so there is no migration. The approved new private workbook remains local and
uncommitted; its SHA-qualified structure is 33 records / 9 CustomerCodes / 9 source identities.

Merged-main qualification is green: 63 focused cases with 3 expected skips / 269 assertions and
1,560 full-suite passes with 81 expected skips / 8,038 assertions, plus Pint, Composer validation
and audit, Vite build and diff checks. The controlled `main` push is authorised; do not enable
Wald, import real customer data or alter SiteApp. Record deployment separately rather than
inferring it from the push.

Earlier handovers below are historical evidence.

## CUSTOMER-WALD-GOLIVE02 handover — 15 September 2026

The narrow strict-gate correction is on `codex/wald-strict-env-gate-2026-09-15`, based directly on
CUSTAPP2 candidate `ee00b3e3c538c514987e8986940c238afb553749`. `config/wald_import.php` now
accepts only the exact case-insensitive word `true`, and `WaldPilotAvailability` requires a real
boolean true. Numeric, yes/on, padded, parenthesized, missing, false-like and malformed values all
remain off. Laravel config-cache behaviour is explicitly tested.

Verification is green: focused strict-gate/availability coverage passed 22 tests / 57 assertions;
the combined Wald pilot, CUSTAPP2 and commit-boundary regression passed 63 tests with one
intentional MySQL-only skip / 198 assertions; and the complete application suite passed 1,550 tests
with 79 skips / 7,977 assertions. The exact committed correction SHA is recorded in the completion
report accompanying this handover.

Do not enable or import from this handover. Production remains on
`89768986a1a32d4258b5deebdf3012584acd6a6c` with `WALD_IMPORT_AVAILABLE=false`. Integration/DevOps
may resume at strict-gate merge/deployment and same-release OFF verification, then separately
deploy CUSTAPP2/migration and perform the approved fictional smoke before deliberate enablement.

No schema, source semantics, application-setting, authorisation, Wald workflow, SiteApp or
production change belongs to this correction.

Earlier CUSTAPP2/pilot entries below are historical inputs and do not override this status.

## CUSTOMER-WALD-CUSTAPP2-01 handover — 15 September 2026

Branch `codex/wald-custapp2-composite-2026-09-15` contains the bounded composite-workbook and
Plot/Source Row/Visit identity implementation. It is feature-branch work only. The private
workbook stayed outside the repository and its approved SHA matched.

Aggregate local qualification is PARTIAL: eight site units commit independently on SQLite and
MySQL; one seven-row site unit blocks because one plot contains conflicting explicit quantities
for two products. Preserve this refusal. Do not select a winner, edit private evidence or bypass
the site-unit blocker. Obtain corrected source evidence or an explicit authorised resolution and
repeat exact-SHA local qualification before dedicated QA.

The additive migration creates durable source-row identity/history, attaches visits to recognized
types and enforces unique exact site/plot identity. No prior migration was edited or deployed.
Production serves corrected release `89768986a1a32d4258b5deebdf3012584acd6a6c`; Wald remains
effectively OFF because `WALD_IMPORT_AVAILABLE=false`. Integration/DevOps owns the separate gate-
drift investigation. Do not re-enable, push `main`, migrate or deploy from this handover.

See [implementation evidence](documentation/wald/customer-wald-custapp2-composite-import-2026-09-15.md)
and [work package](documentation/work-packages/WP-CUSTOMER-WALD-CUSTAPP2-COMPOSITE.md).

Earlier pilot/WALD06 entries below are historical and do not override this status.

## CUSTOMER-WALD-PILOT01/02 release handover — 15 September 2026

Production serves `8a5a2384dd32c89852172c8dd99ffaba880dc698`. The first supervised fictional
pilot passed backup, storage, access, upload, binding, analysis, preview and approval but the
commit was refused by `commit_requires_top_level_boundary`: the controller wrapped the same
commit journal already owned by `ImportStore`. The refusal is immutable truthful evidence; no
receipt, projection or Portal workflow data changed. Production `WALD_IMPORT_AVAILABLE=false`
is the final emergency-gate state. The application setting remains enabled behind that hard-off.

The local non-deploying correction branch is
`codex/fix-wald-pilot-commit-boundary-2026-09-15`. The controller now validates/resolves and
delegates directly to `ImportReview::commit()`; the domain journal guard and all security,
transaction, retry, idempotency and audit behaviour are unchanged. Strict production-like
authenticated HTTP/MySQL, full SQLite and the established MySQL audit/concurrency gate pass.
Do not re-enable production from this handover. First obtain separate merge/deployment approval,
verify the served corrected SHA, take a fresh backup, use a fresh fictional preview and repeat the
one-site commit/reload/isolation smoke. See
[correction evidence](documentation/wald/customer-wald-pilot01-commit-boundary-correction-2026-09-15.md).

Earlier WALD06 status below is historical and does not override this pilot.

## CUSTOMER-WALD06 integration reconciliation — 11 September 2026

The canonical `feature/customer-wald-next` line retains accepted WALD02–05 at
`c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` and is being reconciled by a normal merge with
current local `main` `dcea43e3266c06044c498ab2540b25c233a5d847`. The merge combines the
current non-Wald product/release truth with the accepted corrected Wald backend; it does not
promote either feature-branch state to deployed truth. CUSTOMER-WALD06 is the approved next
bounded phase for a default-off multi-site Import Studio and supervised local/test pilot.

The WALD05 corrected executable baseline remains
`dbd17c68a04c028418e2d8a08fc43312aae5fe3b`. The actual workbook decisions remain exactly:
include PC1, CC1 (treat the observed `CC!` typo as CC1) and CM1; exclude CM2 and the explicitly
identified `NICK TEST` row. Do not expose the workbook, private source evidence or internal
itemised data to customers. No push, production access, deployment or SiteApp change is
authorised by this continuation.

Earlier entries below are preserved evidence and do not override this status.

## CUSTOMER-GIT-CLEANUP02 — Remote consolidation handover — 11 September 2026

Remote cleanup is complete. `origin/main` remains
`e757bb651f9aa95d67b808a97433d27bc29d03c3`; canonical accepted unreleased Wald is published as
`origin/feature/customer-wald-next` at `c9fe0620069a10fe07050e4ec2f30043a9a9a1ec`. All 12
annotated preservation tags were published and verified by their peeled commit targets. Ten
preserved non-main remote QA/feature/release branches were deleted only after per-tip ancestry
proof. Local `main` was not pushed, Forge/production was not contacted and no deployment occurred.

Do not resolve the remaining dirty worktrees as part of ordinary branch cleanup. The two attached
historical local branch refs and the detached Sprint 3E temp worktree require a separate user-file
preservation decision. See `documentation/customerapp-git-consolidation-2026-09-11.md`.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-GIT-CLEANUP01 — Git ownership handover — 11 September 2026

Prepared executable local `main` checkpoint `93737df1e8dae36b9d79b6b641e30b6c9af51908` is
the canonical accepted non-Wald product line; this cleanup adds only documentation above it.
Remote/production line `origin/main` remains
`e757bb651f9aa95d67b808a97433d27bc29d03c3`. No additional product merge is required and no
push/deployment occurred. Accepted unreleased WALD02–05 is now represented by one canonical local
branch, `feature/customer-wald-next`, at `c9fe0620069a10fe07050e4ec2f30043a9a9a1ec` with
corrected executable checkpoint `dbd17c68a04c028418e2d8a08fc43312aae5fe3b`.

Twelve annotated local tags preserve disconnected production/recovery, old scope, QA, original
admin/sidebar integration and superseded manual-import histories. Local branches were reduced
from 40 to four. The remaining historical branches are temporarily protected by dirty worktrees:
`docs/customer-admin01-audit-2026-09-09` has an untracked audit document, while
`release/sprint-3e-production-record-2026-08-27` is attached to an old temp worktree whose tracked
files appear deleted. A second detached Sprint 3E temp worktree has the same condition. Do not
force-remove, reset or clean any of them without a separate preservation decision.

The root worktree remains on canonical Wald and retains the user's modified Sprint 3E report,
`Copy of siteapp1.xlsx`, `local logins.docx`, `~$cal logins.docx` and `output/`. Remote publication
of the canonical Wald branch/tags was blocked pending a more explicit remote-egress approval, so
all 11 original remote branches remain and no remote deletion was attempted. Full classification
and cleanup evidence is in `documentation/customerapp-git-consolidation-2026-09-11.md`.

Branch policy: `main` is the accepted release/production line; feature branches are short-lived;
QA/release branches are removed after acceptance and preservation; unreleased Wald lives on one
canonical branch; historical milestones use annotated tags. A `main` push remains a separately
authorised production action because Forge Push to Deploy is enabled.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-NEXT-RELEASE02 — Dedicated RC1 QA passed — 10 September 2026

**PASS; READY_FOR_MERGE_TO_MAIN_PREPARATION, not merged or deployed.** Continue from the QA
lineage beginning at corrected executable SHA `10f0a56ac1987754ab0c31b45fc08138ba25e3f8`, which is
the frozen RC `2e58bedcb70c487dfee1ae9f01a087c7ed8117e6` plus a two-file test-harness-only ordering
correction. The application/runtime candidate is unchanged. Dedicated scope, migration, admin,
security, regression, browser, responsive, accessibility, performance, SQLite and MySQL 8.4.11
evidence passed. Production dependency audits are clean; 14 locked development/build advisories
remain a non-blocking maintenance item. No push, `main` merge, production access, migration or
deployment occurred. A separate release decision and production recovery controls are still
required before any deployment. See
`documentation/customerapp-next-release-dedicated-qa-2026-09-10.md`.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-NEXT-RELEASE01 — Sidebar + Admin + Synthetic Demo RC1 — 10 September 2026

**READY_FOR_DEDICATED_QA on a non-deploying release branch.** The candidate branch is
`release/customerapp-next-release-admin-demo-rc1`, built cleanly from exact production/main
`e757bb651f9aa95d67b808a97433d27bc29d03c3`. It has not been merged to `main`, pushed or
deployed, and production was not accessed.

Deploying next, subject to dedicated QA and separate release approval: the accepted sidebar;
Office-only customer/site administration; active/inactive lifecycle; immutable audit; read-only
plot inventory and assigned-user visibility; truthful source/import unavailable states. The
synthetic Import Studio is local/testing-only, default-off and has no production route, upload,
write, binding, Wald invocation or commit. WALD02–05, all five WALD migrations, real import,
multi-site import and WALD06 remain excluded and preserved on their accepted feature lineage.

Evidence: 64 focused PHP tests / 440 assertions; 14 browser-side unit tests; 102 queue-card and
Sprint 3F regressions / 1,117 assertions; full SQLite 393 passed / 43 skipped / 2,784 assertions;
full MySQL 8.4.11 430 passed / 1 intentional skip / 3,820 assertions, plus the separately guarded
five-test admin race suite. A clean 13-migration MySQL install and 16-check production-shaped
upgrade passed. Browser QA passed at 1920, 1440, 1366, tablet, 390 and 320px with no console
errors. Pint, strict Composer validation, Composer audit, production build, production-only npm
audit and whitespace checks passed. Full npm audit retains 14 locked development/build-chain
advisories for release review.

See `documentation/customerapp-next-release-rc1-build-report-2026-09-10.md`. Dedicated QA must
use the exact frozen branch tip stated in the task completion report. Any change after freeze
requires explicit release handling.

Earlier entries below are historical evidence and do not override this status.


## CUSTOMER-FIX-QUEUECARD01 — Office queue-card correction — 10 September 2026

**READY_FOR_QA on a non-deploying patch branch.** Production/main checkpoint
`eb149a9ab28f9131f9d26971f13bd289ff1afba6` remains untouched. The executable fix
is `8f28cc50f513c2fae2464abdbf0e7b11ed13f7bf` on
`fix/office-queue-card-request-values`.

The inherited queue card defect was reproduced: child requests for Windows / 5 October,
Cavity Closers / 12 October and CML / 19 October all showed the parent batch's
Windows / 1 October values, while their details pages were correct. Cards and the service
filter now prefer authoritative request-level service/date values and retain the batch only
as a legacy null-field fallback. Batch identity, status/date semantics, workflow, Sprint 3F,
notifications, authorisation and persistence are unchanged.

Evidence: 11 focused tests / 62 assertions; 134 related Review Requests and Sprint 3E/3F
tests / 1,327 assertions; full suite 329 passed / 38 skipped / 2,344 assertions. A warmed
query check stayed at 8 queries for both one and ten cards. Pint, strict Composer validation,
clean Composer audit, production asset build and whitespace checks passed. No migration,
main push, deployment, production access or unrelated WALD/admin/sidebar change occurred.
Recommended release disposition: `NEXT_PATCH`, subject to dedicated QA and separate release
approval. See the
[correction report](documentation/office-queue-card-correction-2026-09-10.md).

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-RELEASE03A — RC1 recovery approved — 9 September 2026

Frozen executable RC checkpoint `ac250a8e9eef4b40591872de9275d802c76e4fba` passed dedicated
forward QA. RCQ-01 is `RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`. Before traffic is reopened, a
failed release may restore the fresh pre-deployment database snapshot and previous verified code
release together. After reopening or any Sprint 3F write, old main is forbidden; recover forward
from the exact deployed RC or a compatible descendant. A Forge code/symlink rollback does not
restore MySQL. CUSTOMER-RELEASE03A is documentation-only and permits merge-to-main preparation,
not a main push, deployment or production change. See
[RC1 recovery strategy](documentation/customer-release03a-rc1-recovery-strategy-2026-09-09.md).

## CUSTOMER-RELEASE02 — reduced RC1 build (historical pre-QA status) — 9 September 2026

Current delivery work is `release/customerapp-2026-09-09-rc1`, not WALD/admin integration.
Base `0873bac79edf578e9f4a9417e3cafae34e8aa925`; Sprint 3F through `60aa9e2`
merged first, security `5e7df08` second. Both are included in RC1, not newly on main
or deployed. DEC-055 records this approval and the decision-number provenance.

WALD02–05 remain accepted separate feature work but are excluded from RC1, as are
ADMIN-SITE02, old manual importer/interpreter/UI, unfinished source integration,
WALD06 and the multi-site pilot. Do not merge their runtime or migrations to obtain docs.
RC1 has exactly 12 migrations; no historical migration edits. Populated amendment rollback
would lose metadata and is not an approved production recovery strategy.

RC1-T01 was an inherited stale date fixture, not a product defect. CUSTOMER-RELEASE02A
approved a test-only correction; focused/full SQLite, disposable MySQL 8.4.11, Composer,
Pint and build gates passed. The commit containing this status is the frozen QA candidate;
use the exact SHA in the release handoff and do not change it after dedicated QA begins.
Pre-QA verification and final freeze are recorded in
[RC1 build record](documentation/customer-release02-rc1-build-2026-09-09.md).
Dedicated release QA must use the final frozen SHA; passing build checks is not deployment
approval. No main push, production access, Forge change, RC push or deployment is authorised.

Last repository-proved successful production release: Sprint 3E `9111d76`, Forge 76326195,
11 migrations. Main `0873bac` contains later fixes; its current live deployment is not
proved here. Fresh production verification is a later release prerequisite.

The entries below are historical branch evidence, including superseded deployment claims,
old sprint numbering and pre-security advisory counts. They do not override this status or
the current brief. Their reports and historical decisions remain preserved.

## Sprint 3F final product decisions — 3 September 2026

Product integration is complete on `feature/sprint-3f-date-amendments`, following
baseline `4773c37` and race correction `38b058f`. DEC-039 confirms all seven stable
reason codes, required Other explanation (maximum 2,000 characters) and optional text
for other reasons. Customer/Office history keeps reason and Additional information separate.
On Hold — Date Change Requested feeds existing Call-Offs In Progress; completed-service
precedence is unchanged. No new overall status or transaction/locking change was needed.

Verification: 91 ordinary Sprint 3F cases / 1,055 assertions; full SQLite 313 passed,
38 MySQL-only skipped / 2,268 assertions; targeted MySQL Office race 10/10 iterations,
292 assertions. Fresh isolated SQLite seed, Pint, Composer validation and build passed.
Composer audit still reports eight inherited advisories; dependencies were not changed.
After preview recovery, browser checks passed at desktop/mobile widths (1280px/390px),
including keyboard focus, conditional Other validation, On Hold, Office review and resolution.
No browser console errors/warnings were observed; dedicated QA is still required.

No current Sprint 3F product decisions remain. Next: dedicated Sprint 3F QA, separate
Composer security reconciliation and final combined release-candidate verification.
No main change, push, deployment or Wald/import modification. See
`documentation/sprint-3f-date-amendments-2026-09-03.md` for details and file list.

## Initial Sprint 3F implementation update — 3 September 2026 (historical)

Current work is Date Amendments After Date Agreed, reusing Sprint 3E. It is isolated on
`feature/sprint-3f-date-amendments` from local main `0873bac79edf578e9f4a9417e3cafae34e8aa925`.
This supersedes older release-status/numbering statements below for the current task.
Those entries remain historical evidence, not a claim about today's production state.

Implementation is **blocked for sign-off**, not complete: Product must approve amendment
reason values and confirm the preserved On Hold aggregate plot status. Reasons are empty
and customer initiation fails closed until confirmed. Disposable MySQL 8.4, dedicated
keyboard/mobile QA and reconciliation with the separate security dependency branch remain
release gates. No main merge, push, deployment or Wald/import modification was performed.

See `documentation/sprint-3f-date-amendments-2026-09-03.md` for the domain, files,
command evidence, migration rehearsal, eight new MySQL race cases and handoff actions.

Final local gate: 264 tests passed / 23 MySQL-only skipped, 1,378 assertions.
Fresh SQLite seed, non-empty upgrade, Pint, Composer validation and build passed.
Composer audit reports eight inherited advisories; security reconciliation remains separate.

## Release-management status — 25 August 2026

Production serves `main` commit `f801c91113bd13656c6cbffdd3d82c12d4a95846` through the
successful controlled migration-recovery deployment. The exact evidence is retained in
`documentation/production-deployment-recovery-2026-08-21.md`; do not replace the four
MySQL-safe repaired historical migrations with older variants.

Sprint 3E passed dedicated QA and was transplanted from `47e8ccc` plus QA commit `a38d557`
onto `release/sprint-3e-date-negotiation-2026-08-25`, which starts from current `main`.
It is not on `main` or production. The mandatory disposable MySQL clean-install, upgrade,
index and concurrency gate is unavailable in this environment, so the release candidate is
blocked pending a safe target and separate merge/deployment approval. See
`documentation/sprint-3e-release-candidate-2026-08-25.md`.

## Management Requirements Reset — 20 August 2026

Status:
Sprint 3D backend, UI and dedicated local QA are complete. New customer call-offs use the
shared multi-plot/multi-service matrix,
signed server-side review and atomic final submission. The exact UI contract is in
`documentation/sprint-3d-bulk-call-off-report.md`; do not add a competing creation path.
Local SQLite `migrate:fresh --seed`, the full Pest suite (162 tests, 771 assertions), Pint,
diff check and production Vite build passed on 21 August 2026.

The secure Sprint 1A–2A foundation remains in place, but its three-service,
single-service/single-date batch, assigned-site Office Staff and Approved/Rejected
assumptions are superseded for future work. Do not remove or rewrite historic records.

The current target, gap analysis and safe implementation order are in:

- `context-work-prompt.md`
- `brief.md` section 17
- `documentation/management-gap-analysis-2026-08-20.md`

QA result:
`documentation/sprint-3d-bulk-call-off-qa-report-2026-08-21.md` records the passed local
gate, two QA corrections and outstanding MySQL/holiday/accessibility/production limitations.
The historical statement that Sprint 3E had not begun is superseded by the
release-management status above. Do not change the signed matrix, session or final-submit
contract without backend review.

The target glossary is now reflected in the UI: legacy Approved is customer-presented as
Date Agreed, and source completion takes precedence. Excel integration, attachments,
calendar/PDF and company testing remain deferred.

## Sprint 3C — Plot-Centric Site Overview and Plot Details

Status: Dedicated QA passed; safe to begin Sprint 3D planning only.

- The dashboard is an active-site, plot-centric overview with Cavity Closers, Windows,
  Snagging and CML in a fixed order.
- Presentation logic is centralised in `PlotOverviewQueryService`; source completion wins
  over legacy call-off state, and legacy Approved presents as Date Agreed.
- Fully completed plots are hidden by default and can be included explicitly. Filtering,
  source freshness, empty states and customer-safe product quantities are included.
- `GET /portal/plots/{projectedPlot:uuid}` is active-site authorised and exposes only
  permitted projection data.
- Desktop uses semantic tables and mobile uses service-labelled cards. Browser checks found
  no document-level horizontal overflow from 320px through 1440px.
- The legacy withdrawal/resubmission/Trash controls remain secondary; their existing
  server-side eligibility checks are retained.
- No new call-off, bulk selection, negotiation, amendment, attachment, calendar/PDF, QR,
  source transport or SiteApp integration was added.
- QA corrected active-site UUID containment, so Plot Details no longer permits an
  assigned-but-not-selected site to render under the wrong site context.
- Evidence: `documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md`.
- The prior QA blocker was only the missing local fresh-migration/seed rehearsal. It now
  passed against confirmed local SQLite; no MySQL, Forge or production target was used.

## Sprint 3A — Target Domain and Access Migration Contract

Status: Backend implementation and SQLite architecture QA passed; no UI or source
integration was started.

- Added four-service projected plot-service and product projections, read-only source-import
  audit records, request-level service/date compatibility fields and ordered date
  negotiation/proposal entities.
- The migration backfills unambiguous legacy request values while preserving every legacy
  row, UUID, history, Trash/Undo operation, notification and rejected-resubmission link.
- Legacy Approved is represented as legacy Date Agreed without rewriting history or
  inventing a negotiation/proposal. Legacy Rejected remains intentionally unconverted
  pending a retained TBC decision.
- Fenster Office Staff are global reviewers/notification recipients; external Site Users
  remain restricted to active assigned sites.
- `documentation/target-domain-v3.md` is the Sprint 3B+ implementation reference.

QA result: non-empty SQLite migration rollback/reapply, regression tests, formatting and
frontend build passed. A MySQL migration/backfill/concurrency rehearsal is still required
before source ingestion or release work. See
`documentation/sprint-3a-domain-qa-report-2026-08-20.md`.

## Sprint 3B — Source Projection, Import Contract and MySQL Rehearsal

Status: SQLite implementation and QA passed; MySQL rehearsal is blocked by the absence of
a safe disposable database target.

- Added a transport-neutral, internal-only importer and durable source audit, issue and
  completion/reversal event records.
- Completion closes active negotiations and clears conflicts without sending notifications.
  Reversal never reactivates an older request when a newer active request exists.
- No live source path, XLSX parser, credentials, scheduler, public upload endpoint or
  SiteApp write-back was added.
- See `documentation/source-integration-contract.md` and
  `documentation/sprint-3b-source-import-report.md`.
- QA corrected Call No. rebinding, malformed-record rejection, idempotent audit counts,
  missing-issue resolution, completion-date audit events, timestamp preservation and safe
  failed-run logging. See `documentation/sprint-3b-source-qa-report-2026-08-21.md`.

Historical note:
Sprint 2A's 19 August QA evidence remains valid for the old implemented workflow, but it
is not permission to company-test the management-confirmed target product early.

## Sprint 1A - Secure Access and Domain Foundation

Status:
Implemented and verified.

## Migrations and Tables Added

Migration:

- `2026_08_05_000001_create_secure_access_domain_tables.php`

Tables added:

- `customer_organisations`
- `portal_roles`
- `sites`
- `site_user_assignments`

Existing `users` table extended with:

- `customer_organisation_id`
- `portal_role_id`
- `is_active`
- `is_preview_user`

## Models and Relationships

Models added:

- `CustomerOrganisation`
- `PortalRole`
- `Site`
- `SiteUserAssignment`

Updated:

- `User`

Relationships:

- Customer organisations have many users and sites.
- Users belong to one customer organisation.
- Users belong to one portal role.
- Users may be assigned to many sites.
- Sites belong to one customer organisation.
- Sites may have many assigned users.

## Roles and Identifiers

Stable role identifiers are centralised in `App\Enums\PortalRoleIdentifier`:

- `site_manager`
- `assistant_site_manager`
- `finishing_foreman`
- `fenster_office_staff`

The three site roles remain distinct records with identical Version 1 permissions.

## Routes

Authentication:

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /forgot-password`
- `POST /forgot-password`
- `GET /reset-password/{token}`
- `POST /reset-password`

Protected portal routes:

- `GET /dashboard`
- `GET /sites/select`
- `POST /sites/active`
- `GET /portal/site-dashboard`
- `GET /portal/review-requests`

Development preview:

- `GET /development/role-preview`
- `POST /development/role-preview`

Public registration is not routed.

## Middleware, Policies and Gates

Middleware added:

- `active.portal` enforces active users with complete portal profile.
- `active.site` re-authorises active site context from the session before site dashboard access.

Gates added:

- `select-site`
- `view-site-dashboard`
- `view-review-requests`

Authorisation rules:

- Site roles can select and use assigned sites only.
- Office Staff review scope is assigned sites only.
- Active site context stores only the selected site ID and is rechecked server-side.
- Cross-organisation active-site context is rejected even if a bad assignment exists.

## Seeders and Preview Users

`DatabaseSeeder` now:

- confirms the four portal role records;
- creates a preview customer organisation;
- creates preview sites;
- creates controlled preview users for each portal role;
- assigns preview users to preview sites.

Development role preview signs in controlled preview users only in local or test environments.
It does not mutate a real user's role and is unavailable outside local/test.

## Tests Added

Added `tests/Feature/SecureAccessFoundationTest.php`.

Coverage includes:

- valid login;
- invalid login;
- logout;
- forgot-password request;
- password reset;
- public registration unavailable;
- inactive-user login and retained-access blocking;
- four portal roles;
- assigned-site visibility for site users;
- assigned-site review scope for Office Staff;
- unassigned site ID rejection;
- active-site assignment requirement;
- cross-customer active-site rejection;
- dashboard routing by role;
- local/test role preview;
- production role-preview blocking;
- browser input cannot mutate a user's role;
- unauthenticated protected-route blocking.

## Backend Contracts for Next Sprint

Next backend sprint can implement call-off batch and request schema against these access
boundaries.

Required contracts:

- each request must belong to one batch;
- each request must target one plot and one service;
- request creation must authorise the active assigned site;
- Office Staff decisions must authorise assigned-site review scope;
- batch-level operations must authorise every affected request;
- batch-level operations must not overwrite individual request decisions after divergence.

## Unresolved Risks

- Customer-facing meaning of CML remains unresolved.
- Bulk call-off creation limits and validation feedback remain unresolved.
- Amendment lifecycle beyond confirmed withdrawal, rejected-request resubmission, Trash and Undo remains unresolved.
- Email notification requirements remain unresolved.
- SiteApp integration method and sync freshness remain unresolved.
- Long-term retention beyond seven-day customer-facing Trash visibility remains unresolved.

## Sprint 1A QA Audit - 5 August 2026

Status: Passed after corrective changes.

Fixes made:

- Removed standalone local development dashboard routes that rendered portal screens without
  authentication, active-account validation or assigned-site authorisation.
- Restored no-JavaScript access to the review preview content by removing its unconditional
  Alpine `x-cloak` state.

Additional automated coverage:

- Revoked active-site assignments invalidate an existing active-site session context.
- Site roles cannot access the Office Staff review dashboard.
- Office Staff cannot select or access a site dashboard.
- Development role-preview POST access is rejected in production.
- Removed standalone development dashboard URLs remain unavailable.

Recommendation for Sprint 1B:

- Continue to enforce every request action through the active-site and assigned-site boundary.
- Use database constraints and transactions for future batch-level lifecycle guarantees.
- Add a MySQL-backed migration check before production readiness; this audit ran against the
  required local SQLite database only.

## Sprint 1B - Call-Off Domain Foundation

Status:
Implemented; pending dedicated Sprint 1B QA audit.

Implemented:

- `projected_plots` projection table and model.
- Call-off service, request status, operation type and history event enums.
- Call-off batches with one site, one service type, one requested date and one submitting
  user.
- One independently auditable call-off request per selected projected plot.
- UUIDs on Sprint 1B business tables while retaining integer primary keys.
- Nullable unique `active_conflict_key` for submitted and approved duplicate blocking.
- `DetermineCallOffEligibilityAction` as the central eligibility source.
- `UpdateConflictKeyAction` as the only domain owner of `active_conflict_key`.
- Submission, approval, rejection, withdrawal, Trash, restore, quick Undo and rejected
  resubmission actions.
- Direct `call_off_batch_operation_items` membership with before/after state snapshots.
- Append-only call-off status history with separate `customer_response` and
  `internal_reason`.
- Gates for projected plot visibility and call-off lifecycle actions.
- Factories and local/test projected plot seed data.

Verification:

- `php artisan migrate` passed.
- `php artisan test` passed.
- `vendor\bin\pint --test` passed after formatter fixes were applied.
- `npm run build` passed after rerunning outside the sandbox due to a Windows `spawn EPERM`
  permission failure.
- `git diff --check` passed.
- `php artisan migrate:fresh --seed` passed against local SQLite.

Out of Scope Not Implemented:

- Final customer-facing call-off UI.
- Final Office Staff review UI.
- Email notifications.
- QR scanning.
- SiteApp API integration.
- Manufacturing scheduling.
- Completion, supersession or closure lifecycle for approved requests.
- Amendments beyond rejected resubmission.
- Filament resources.

QA Recommendation:

- Run a dedicated Sprint 1B QA audit before UI implementation.
- Focus on concurrency, conflict keys, atomic Undo, operation item membership, history
  sequencing, customer-facing serialization and cross-site/cross-organisation
  authorisation.

## Sprint 1B QA Audit - 5 August 2026

Status:
Passed after corrective changes. Safe to begin Sprint 1C UI integration.

Defects found and fixed:

- Submission now rejects a selected projected plot that no longer exists instead of
  silently creating a partial batch.
- Withdrawal, Trash and restoration now reject missing or duplicate request selections
  before any state changes, preserving atomic bulk behaviour.
- Quick Undo delegates active conflict-key restoration to `UpdateConflictKeyAction`,
  preserving its single-owner domain boundary.
- Operation-item foreign keys now restrict operation deletion, preserving operation
  snapshots as audit-critical data.
- Business UUIDs are not mass assignable, and application-level history records reject
  update and delete operations.

Tests added:

- Missing selected plot rejection and full batch rollback.
- Missing and duplicate bulk request selection rejection.
- Server-generated UUID protection.
- Append-only history mutation and deletion protection.

Safe UI contracts:

- Submit, approve, reject, withdraw, Trash, restore, Undo and rejected-resubmission
  actions enforce their domain eligibility immediately before persistence.
- UI must resolve the active assigned site through existing middleware and pass only that
  server-authorised site to submission actions; it must never trust client-provided IDs.
- UI must expose public UUIDs rather than integer primary keys and must never serialise
  `internal_reason` to site users.
- Bulk UI actions must pass the exact selected request set; the actions reject missing,
  duplicate, cross-batch or ineligible items atomically.

Known limitations:

- The domain and migrations were verified with local SQLite only. MySQL-specific locking,
  unique-index and migration behaviour have not been executed against MySQL.
- There are no final customer or Office Staff lifecycle routes or views in Sprint 1B;
  their controllers must apply authentication, active-account and active-site middleware
  before invoking these actions.

## Sprint 1C.1 - Authenticated Site Dashboard

Status:
Implemented and verified.

Implemented:

- Replaced the authenticated assigned-site selection preview with a real assigned-site
  list scoped to the signed-in site user.
- Site selection now displays the current active site when one is authorised.
- Site selection stores the active site only after server-side customer-organisation and
  site-assignment checks.
- Replaced the site dashboard preview data with real relationships from the active site,
  projected plots, call-off batches, call-off requests, submitter and customer-visible
  decision history.
- Site dashboard now shows site name, customer, signed-in assigned user, outstanding
  projected plot count, current request status counts, outstanding projected plots and
  existing call-off request cards.
- Each call-off card shows plot, service, requested date, status, submitted by,
  submission date and customer-visible decision response where applicable.
- Removed authenticated dashboard preview data, Delete controls and New Call Off UI.
- Added empty states for no assigned sites, no projected plots and no requests.
- Kept Office Staff routing to a Review Requests placeholder only; no review queue,
  approval or rejection UI was built in this sprint.

Tests added:

- Assigned-site selection displays only authorised sites and active-site context.
- No-assigned-sites empty state.
- Authenticated site dashboard renders real projected plots and call-offs and hides
  completed/cross-site/private data.
- No projected plots and no requests empty states.
- Office Staff route to the Review Requests placeholder without decision controls.

Verification:

- `php artisan test` passed.
- `vendor/bin/pint --test` passed after a formatter fix was applied.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.
- `git diff --check` passed.

Still out of scope:

- New Call Off.
- Review Requests queue.
- Approve and Reject UI/actions.
- Notifications.
- Trash.
- Undo.
- QR.
- SiteApp integration.

## Sprint 1C.2 - New Call Off

Status:
Implemented and verified.

Implemented:

- Added authenticated active-site New Call Off routes for create, confirmation and final
  submission.
- Added a thin `NewCallOffController` that resolves the server-authorised active site,
  delegates eligibility to `DetermineCallOffEligibilityAction` and delegates persistence
  to `SubmitCallOffBatchAction`.
- Added `NewCallOffRequest` validation for service type, requested date, selected
  projected plot UUIDs and customer-facing submission text.
- Added the New Call Off form with active site, service type, requested date, projected
  plot multi-selection, customer-facing submission text and duplicate-click waiting state.
- Projected plot options are shown only when eligible for the currently selected service.
- Added a confirmation screen so no batch is created until the user submits from review.
- Submission creates one batch and one individual submitted request per selected projected
  plot through the domain action.
- Successful submission redirects to the site dashboard with a customer-facing success
  message; newly submitted requests appear immediately from the dashboard's real data.

Tests added:

- Authentication and site-role active-site access for New Call Off.
- Eligible plot display excludes completed, cross-site and duplicate-conflicted projected
  plots.
- Validation feedback before confirmation.
- Confirmation does not create batches or requests.
- Final submission creates one batch and one request per projected plot.
- Tampered cross-site UUIDs are rejected without persistence.
- Final submission rechecks eligibility and blocks duplicate active submissions.

Verification:

- `php artisan test tests/Feature/NewCallOffTest.php` passed.
- `php artisan test` passed.
- `vendor/bin/pint --test` passed.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.
- `git diff --check` passed.

Still out of scope:

- Review Requests queue.
- Approve and Reject UI/actions.
- Notifications.
- Trash.
- Undo.
- QR.
- SiteApp integration.

## Sprint 1C.3 - Office Staff Review, Approval and Rejection

Status:
Implemented and verified.

Implemented:

- Replaced the Office Staff Review Requests placeholder with a real assigned-site review
  dashboard.
- Review dashboard defaults to Submitted requests and supports server-side filters for
  status, assigned site and service type.
- Review dashboard shows request cards with site, plot, service type, requested date,
  submitting user, submission date, customer-facing submission text and current status.
- Added request detail routes using public call-off request UUIDs.
- Detail screen shows site, customer organisation, plot, service, requested date,
  submitter, submitted timestamp, customer-facing submission text, current status and
  Office Staff request history.
- Added approve and reject form submissions.
- Approve consumes `ApproveCallOffRequestAction`; reject consumes
  `RejectCallOffRequestAction`.
- Reject requires `customer_response`; approve allows optional `customer_response`.
- Both decision forms support optional `internal_reason` for Office Staff only.
- Site roles and unauthenticated users are blocked from review and decision routes.
- Decision submissions re-authorise assigned Office Staff scope through the audited domain
  action immediately before persistence.
- Stale decisions, withdrawn requests and revoked Office Staff assignments fail safely
  without overwriting existing decisions.
- Approved and rejected requests leave the default Submitted queue.
- Site-user dashboard reflects approved and rejected decisions on reload and never exposes
  `internal_reason`.

Routes added:

- `GET /portal/review-requests`
- `GET /portal/review-requests/{callOffRequest:uuid}`
- `POST /portal/review-requests/{callOffRequest:uuid}/approve`
- `POST /portal/review-requests/{callOffRequest:uuid}/reject`

Tests added:

- Assigned Office Staff can view Submitted requests for assigned sites.
- Office Staff cannot see unassigned-site requests.
- Site roles and unauthenticated users cannot access review or decision routes.
- Request detail respects assigned-site and cross-organisation boundaries.
- Assigned Office Staff can approve and reject.
- Approval persists `customer_response`.
- Rejection requires customer-visible response.
- `internal_reason` is not shown on the site-user dashboard.
- Approved and rejected requests leave the default Submitted queue.
- Site-user dashboard shows Approved and Rejected results.
- Stale second approval, approval after rejection and rejection after approval fail safely.
- Withdrawn requests cannot be decided.
- Revoked Office Staff assignment prevents decisions.
- Foreign or malformed UUIDs fail safely.
- Filters do not leak unauthorised records.

Verification:

- `php artisan test tests/Feature/OfficeStaffReviewRequestsTest.php` passed.
- `php artisan test tests\Feature` passed.
- `php artisan test` passed.
- `vendor/bin/pint --test` passed after a formatter fix was applied.
- `npm run build` passed after rerunning outside the sandbox because the first run hit a
  Windows `spawn EPERM` environment permission failure.

Still out of scope:

- Trash.
- Withdraw.
- Undo.
- Notifications.
- QR scanning.
- SiteApp integration.
- Email.
- Completion or supersession workflow.
- Filament resources.

## Sprint 1C Submission-to-Decision QA Audit - 5 August 2026

Status:
Passed after one workflow-integrity correction. Safe to begin Sprint 1D
withdrawal/Trash/Undo work, provided that work remains within the already confirmed
lifecycle rules.

Defects found and fixed:

- Final New Call Off submission could previously be posted directly, or with edited hidden
  confirmation fields, without proving that the exact submitted values had been reviewed
  on the confirmation screen. The final submit path now requires a server/session-backed
  confirmation signature for the reviewed payload and rejects direct or tampered final
  submissions before persistence.

Tests added:

- Final New Call Off submission without the confirmation screen is rejected.
- Tampered confirmation payload values are rejected before batch or request creation.
- Final submit still rechecks eligibility after confirmation, so a duplicate active
  request created between review and final submit blocks persistence.

Contracts verified:

- Site Manager, Assistant Site Manager and Finishing Foreman can submit call-offs only for
  their active assigned site.
- New Call Off validation rejects unsupported services, past dates, empty plot selection,
  duplicate plot UUIDs and unavailable projected plots.
- Confirmation creates no batch or request until final submission.
- Final submission calls `SubmitCallOffBatchAction`, and the domain action rechecks
  eligibility immediately before persistence.
- Fenster Office Staff review queues are scoped to assigned sites and default to
  Submitted requests.
- Approval and rejection call the audited domain actions, lock the current request,
  re-authorise assigned Office Staff scope and reject stale or conflicting decisions.
- Approval retains the active conflict key, rejection clears it through
  `UpdateConflictKeyAction`, and decision history records actor, timestamp,
  customer-visible response and private internal reason separately.
- Site dashboards show the latest approved/rejected status and customer-visible response
  for the active assigned site only.
- `internal_reason` remains Office Staff-only and is not loaded or rendered on the
  site-user dashboard.

Remaining risks and limitations:

- Browser-level accessibility, mobile layout and no-JavaScript behaviour were reviewed
  from Blade structure and automated feature coverage only; no live browser or assistive
  technology pass was performed in this audit.
- MySQL-specific locking, unique-index and migration behaviour remain unverified; this
  audit ran against the required local SQLite setup only.
- Notifications, withdrawal, Trash, Undo, QR, SiteApp integration, email, completion and
  supersession remain out of scope and were not started.

## Sprint 1D - Withdrawal, Trash and Undo

Status:
Implemented; ready for dedicated QA audit.

Customer-facing routes and screens:

- Active-site dashboard selection controls and confirmation screens for withdrawal and
  Trash.
- `GET /portal/call-offs/trash` for unexpired, active-site customer Trash.
- Confirmation, final lifecycle and operation UUID Undo routes recorded in
  `current_sprint.md`.

Implementation contract:

- The UI does not mutate request status, Trash timestamps, conflict keys, histories or
  operation records. It consumes the four audited Sprint 1B actions only.
- The confirmation payload is HMAC signed and stored in session. It binds the operation,
  ordered complete UUID selection, active site and acting user. A final action requires
  this exact reviewed payload.
- Quick Undo uses the original operation UUID, scoped by active assigned site; the browser
  countdown is display-only and server timestamps decide eligibility.
- Customer Trash uses `CallOffRequest::customerTrash()`: expired records are hidden but
  remain retained. No scheduler is required for this derived visibility rule.

QA focus:

- Confirm all selected UUIDs are required, duplicate/missing/stale and mixed-batch
  selections fail atomically, and direct/tampered final posts do not persist changes.
- Verify Submitted can withdraw; only Rejected/Withdrawn can enter Trash; Approved never
  exposes or accepts destructive site-user actions.
- Verify cross-site, cross-organisation and revoked-assignment access cannot view, restore
  or Undo by UUID possession.
- Verify Undo expiry, already-reversed and divergence paths against the audited backend
  action, plus exact history and operation-item snapshots.
- Perform an accessibility and mobile-device pass. MySQL-specific locking and index
  behaviour remain unverified by local SQLite tests.

## Sprint 1D QA Audit - 6 August 2026

Status:
Passed after corrective changes. Safe to close Sprint 1D and begin the next scoped
milestone.

Defects found and fixed:

- Quick Undo could be performed by any currently assigned site user who possessed a valid
  operation UUID. Undo is now restricted to the user who performed the original
  withdrawal, Trash or restore operation, while still rechecking current active-site
  assignment inside the domain action.
- The site dashboard hid trashed request cards but still included trashed requests in the
  status summary counts. Status counts now exclude `trashed_at` records so the dashboard
  and customer-facing Trash list remain consistent.

Tests added:

- Tampered final lifecycle operation routes do not mutate requests.
- Replayed lifecycle confirmations do not create duplicate operations.
- Trashed request cards leave the active dashboard and are excluded from status summary
  counts.
- Quick Undo by another currently assigned user on the same site is rejected.

Confirmation contract verified:

- Lifecycle confirmation validates the complete selected UUID list, rejects duplicate,
  missing, malformed, cross-site and mixed-batch selections, and stores an HMAC signature
  in session.
- The signed payload binds the operation, ordered selected request UUIDs, active site ID
  and acting user ID. The final action must present the matching route operation,
  submitted operation value, selected UUIDs and confirmation signature.
- Final persistence re-resolves the selected UUIDs for the active site and invokes only
  the audited domain actions, which re-authorise and revalidate state inside a
  transaction.

Undo boundary behaviour:

- Withdrawal, Trash and restore operations create `undo_expires_at` using server time at
  five seconds after the operation.
- The browser countdown is visual only; `QuickUndoCallOffOperationAction` and
  `DetermineCallOffEligibilityAction::ensureCanUndo()` are authoritative.
- An Undo at the exact stored expiry instant is currently accepted because Laravel's
  `isPast()` returns false at equality; Undo after that instant fails.

Trash visibility and expiry contract:

- Customer-facing Trash uses `CallOffRequest::customerTrash()` with server time:
  `trashed_at` present and `trash_expires_at` greater than `now()`.
- Expired Trash records are hidden from the customer-facing Trash screen but remain stored
  for audit.
- Restore preserves the underlying rejected or withdrawn status and clears only Trash
  fields through the domain action.

Remaining risks:

- MySQL-specific locking, unique-index and timestamp behaviour remain unverified; this
  audit ran against local SQLite only.
- Browser-level accessibility, mobile layout and assistive-technology verification were
  reviewed from Blade structure and automated feature tests only; no live browser/device
  pass was performed.
- Notifications, email, QR, SiteApp integration, completion, supersession, amendments and
  Filament resources remain out of scope and were not started.

## Sprint 1E - In-App Notifications Backend

Status:
Backend implemented; ready for notification UI and dedicated QA.

Implemented contract:

- `CallOffSubmitted`, `CallOffApproved` and `CallOffRejected` are dispatched by the
  successful audited domain actions after persistence.
- `CallOffNotificationListener` delegates to `PortalNotificationService`; listener
  failures are logged and cannot roll back a valid call-off state change.
- Submission recipients are the active submitting site user plus active Fenster Office
  Staff assigned to the affected site. Approval and rejection recipients are only the
  active submitting site user. No broadcast to other site users is created.
- `PortalNotificationQueryService` scopes every read, count, mark-read, mark-all-read and
  dismissal operation to the signed-in user's notification records.
- `PortalNotificationLinkService` re-checks current role, organisation and site
  assignment before opening a request-linked screen. Notification UUID possession is not
  an access grant.
- Notification records retain customer-safe request context only. `internal_reason`,
  operation snapshots, conflict keys and internal IDs are excluded from serialized
  payloads. Notification retention is independent of seven-day call-off Trash expiry.

Backend routes:

- `GET /portal/notifications`
- `GET /portal/notifications/unread-count`
- `GET /portal/notifications/{notificationUuid}/open`
- `POST /portal/notifications/{notificationUuid}/read`
- `POST /portal/notifications/read-all`
- `POST /portal/notifications/{notificationUuid}/dismiss`

QA focus for the next slice:

- Verify all three recipient mappings with revoked assignments, cross-site and
  cross-organisation users.
- Verify event idempotency, failed notification persistence, per-recipient read state,
  dismissal retention and safe link failure after access revocation.
- Confirm withdrawals, Trash, restoration and Undo do not create Sprint 1E notifications.
- Build the notification bell/list UI against these routes; email, push and SiteApp
  integration remain out of scope.

## Sprint 1E - Notification Centre UI

Status:
Implemented and automated checks passed; ready for focused browser and accessibility QA.

Screens and behaviour added:

- Authenticated portal header bell with an authorised unread count, accessible label,
  99+ visual cap and mobile-sized touch target.
- Compact recent-notifications panel loaded from `GET /portal/notifications`, showing
  type, customer-safe message, timestamp and read/unread state.
- Mark-one-read, mark-all-read and dismissal controls use the existing server endpoints.
- Full server-rendered centre at `GET /portal/notifications/centre` with newest-first
  pagination, empty state, read/unread styling, safe-open links and regular POST forms
  that remain usable without JavaScript.
- Notification presentation uses only customer-safe payload fields. `internal_reason`,
  snapshots, conflict keys and internal IDs are not rendered.

Files added or updated:

- `app/Http/Controllers/PortalNotificationController.php`
- `app/Enums/PortalNotificationType.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/layouts/portal.blade.php`
- `resources/views/portal/notifications/index.blade.php`
- `resources/js/app.js`
- `routes/web.php`
- `tests/Feature/PortalNotificationUiTest.php`

Verification:

- `php artisan test` — 99 tests, 506 assertions passed.
- `vendor/bin/pint --test` passed.
- `npm run build` passed after the Windows sandbox spawn restriction was approved for
  the bundler process.
- `git diff --check` passed.

Focused QA handover:

- Check bell focus, Escape/outside-click behaviour, panel focus order and screen-reader
  announcements at desktop, tablet and mobile widths.
- Verify revoked-site safe-open returns no unauthorised request details and gives the
  expected friendly response for the deployed error handling.
- Verify repeated read/dismiss actions, cross-user isolation, notification ordering and
  large unread counts in a live browser session.
- Email, push, preferences, new notification types and SiteApp integration remain out
  of scope.

## Sprint 1E QA Audit - 7 August 2026

Status:
Passed after corrective changes; safe to close Sprint 1E and begin the next scoped
milestone.

Defects found and fixed:

- Notification query, read, dismissal and safe-open paths originally trusted only the
  recipient user ID. They now re-check active account state, current portal role,
  organisation and current assignment to the request site. Revoked assignments,
  organisation changes and role changes no longer expose retained notification data.
- Development role-preview users could have created or viewed real notification records.
  Preview users are now excluded from event recipient creation and notification queries.
- Full-centre no-JavaScript forms posted to JSON-only responses and left users on raw JSON.
  Non-JSON form requests now redirect back to the centre with a safe status message while
  AJAX requests retain their JSON responses.
- Compact-panel read/unread state was conveyed by colour and button availability alone.
  An explicit screen-reader-only read/unread cue is now rendered for each notification.
- Recipient notification writes are now wrapped in a transaction so a failure cannot leave
  only part of an expected recipient set persisted.

Trigger and recipient contract verified:

- Successful submission creates `call_off_submitted` notifications for the submitting
  non-preview site user and assigned active Office Staff only.
- Successful approval and rejection create one corresponding notification for the
  submitting non-preview site user only.
- Withdrawal, Trash, restore and Undo remain outside the Sprint 1E trigger set.
- Failed, unauthorised and stale transitions do not create notifications; a notification
  listener failure does not roll back the already-persisted call-off transition.

Payload and safe-open findings:

- API and rendered payloads contain only public UUIDs and customer-facing request context;
  internal reasons, state snapshots, conflict keys, operation IDs and numeric persistence
  IDs are not serialized or rendered.
- Safe-open re-checks the current request/site authorisation and marks read only after the
  target is authorised. A foreign, malformed, revoked or role-incompatible UUID returns
  not found without exposing target details.
- Dismissal remains per-recipient presentation state, sets unread notifications read, and
  does not alter call-off state or history. Notification retention remains independent of
  the seven-day call-off Trash window.

Tests added or strengthened:

- `PortalNotificationsTest` now covers revoked assignment query hiding, role-change
  hiding, development preview exclusion and JSON/form content negotiation in addition to
  existing recipient, idempotency, payload, read/unread, dismissal and failure tests.
- `PortalNotificationUiTest` verifies no-JavaScript form redirects and the visible read
  state after an AJAX mark-read action.

Browser/accessibility review:

- Live local browser review confirmed the authenticated bell, no-unread state, panel
  semantics, visible keyboard focus, Escape-to-close focus return, clear empty state,
  centre headings and no browser console warnings.
- The Browser viewport capability was unavailable for an automated width matrix, and no
  assistive technology or physical-device pass was performed. Static mobile-first layout
  and touch-target review remains the available evidence.

Known limitations:

- Verification ran on local SQLite; MySQL locking/index behaviour, production queue/mail
  infrastructure and long-term retention remain unverified/open.
- No email, push, QR, SiteApp integration, reporting or dashboard redesign was started.

## Sprint 1G - Production Hardening

Status:
Locally verifiable hardening implemented; not yet safe to deploy.

Completed:

- Secure-access migration rollback corrected to drop the `is_active` and `is_preview_user`
  indexes before dropping their columns. Fresh SQLite migration, seed, rollback and
  reapply now pass.
- Submission, approval/rejection, withdrawal, Trash, restore, Undo and history sequencing
  were reviewed for transaction and `lockForUpdate` coverage. MySQL locking and
  concurrency remain unverified because no local MySQL/MariaDB instance is available.
- Composer advisories were remediated with a targeted compatible update to Guzzle 7.15.3,
  CommonMark 2.9.0, Guzzle promises 2.5.2, Guzzle PSR-7 2.13.0 and nette/utils 4.1.5.
- Notification listener logging no longer serializes exception objects or messages into
  application logs.
- Current notification transitions remain synchronous; no queue or scheduler behaviour was
  changed.

Verification:

- Pest: 101 tests and 521 assertions passed.
- Pint, Composer validation, Vite build and SQLite migration rehearsal passed.
- Composer audit reports no advisories after the targeted update.
- npm audit still reports 10 moderate PostCSS-chain advisories with no available fix.

Open blockers:

- Approved MySQL environment and production-like concurrency/restore evidence.
- Production session, cache, queue, storage, HTTPS, logging, monitoring and backup setup.
- Physical-device, keyboard-only and assistive-technology QA.
- Production-scale performance evidence and release/rollback rehearsal.

No deployment, SiteApp integration, QR scanning, commit, tag or release candidate was
created. See `documentation/sprint-1g-production-hardening-report.md` for the full
readiness report.

## Sprint 2A — Company Test Readiness Planning — 19 August 2026

Status:
Planned only. No production code was changed while preparing this handover.

Current evidence:
`documentation/full-site-audit-2026-08-19.md` is the current evidence baseline. It
overrides conflicting historic Sprint 1G statements: the audit recorded a SQLite rollback
failure, a clipped small-phone notification fly-out and two high-severity npm advisories.

Implementation contract:
`documentation/sprint-2a-company-test-readiness.md` defines the limited remediation,
usability and QA scope for the next company test.

Decision gate:
Traceable rejected-call-off resubmission is not approved for implementation yet. It may
proceed only if proposed DEC-034 receives formal confirmation; otherwise it is excluded
from Sprint 2A acceptance.

Production boundary:
Sprint 2A does not close MySQL, backup/restore, monitoring, production configuration,
physical-device or assistive-technology release gates.

## Sprint 2A Backend — 19 August 2026

Status:
Backend implementation complete; UI and dedicated QA remain.

Completed:

- DEC-034 formally confirmed. Added secure rejected-call-off resubmission routes, request
  validation, review/confirmation binding and server-side action integration.
- The flow derives source context from the authorised rejected request only. It accepts a
  new requested date and customer-facing message, creates a new linked Submitted request
  through `ResubmitRejectedCallOffAction`, and preserves the source rejection/history.
- Active-site Dashboard and recoverable Trash queries now paginate at 15 items. Dashboard
  supports combined `plot`, `service` and `status` filters; date, development and phase
  were deliberately omitted because no unambiguous approved contract exists.
- AUD-001 SQLite rollback fixed by dropping indexed user columns only after their indexes.
  `scripts/verify-sqlite-migrations.ps1` is the repeatable disposable migration,
  full-rollback and reapply check.
- Added browsing indexes for batch site/submitted ordering and request batch/Trash lookup.

UI handover:

- Render the existing paginator objects from `callOffRequests` and `trashedRequests`,
  retaining dashboard `plot`, `service` and `status` query parameters.
- Build the resubmission entry point only for rejected active-site requests and consume:
  `GET /portal/call-offs/{callOffRequest:uuid}/resubmit`, confirmation POST to
  `/resubmit/confirm`, then final POST to `/resubmit` with the returned confirmation
  signature. Do not submit site, plot, service, status or lineage inputs.
- Date filtering is not part of the backend contract. Keep it absent unless a future
  explicit decision defines its customer-facing meaning.

Remaining risks:

- MySQL rollback, index and concurrency behaviour are not verified.
- npm audit remains 11 advisories: 9 moderate and 2 high, with no automatic fix reported.
- Mobile notification, notification-fetch-error, lifecycle-button and welcome-template
  UI items remain for the UI Sprint 2A slice.

## Sprint 2A UI — 19 August 2026

Status:
UI implementation complete; controlled company-test QA remains.

Completed:

- AUD-002: the notification panel now occupies the safe small-screen inset and scrolls its
  contents independently. Local browser measurements after a production asset rebuild were
  8px–297px at a 320px viewport and 8px–367px at a 390px viewport; document width did not
  exceed the usable layout width. Escape closed the panel and returned focus to its bell.
- AUD-007: fetch failure is visible as `Notifications could not be loaded.` with `Try again`
  and the notification-centre fallback. A failure no longer renders the all-caught-up state.
- AUD-005/AUD-008/AUD-012: dashboard filters, pagination, active-filter empty state, Trash
  pagination and disabled selection-dependent lifecycle controls consume the backend's
  existing authorised contracts. Trash states that selections apply to this page only.
- AUD-006/DEC-034: rejected requests expose customer-safe resubmission create and confirmation
  screens. No client-submitted source identifiers, status or lineage inputs were added.
- AUD-013: deleted the unused Laravel welcome view. The root redirect and disabled registration
  route are covered by a UI feature test.

UI verification:

- `php artisan test tests\\Feature\\Sprint2aUiTest.php` passed: 7 tests, 45 assertions.
- `php artisan test` passed: 117 tests, 630 assertions.
- `vendor\\bin\\pint --test`, `npm run build` and `git diff --check` passed.

Remaining QA and risks:

- Final QA repaired the local Herd certificate for `customerapp.test`. Herd now reports the
  site as secured with a certificate whose CN/SAN includes `customerapp.test`; browser
  navigation to HTTPS succeeds without bypassing a warning.
- Fetch-failure interaction is feature-tested at markup/state level; exercise it against a
  controlled failed endpoint during dedicated QA.
- MySQL/index/concurrency, physical-device and assistive-technology release evidence remain
  outside this UI slice.
