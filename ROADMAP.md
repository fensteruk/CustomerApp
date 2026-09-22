# Fenster Customer Portal ROADMAP

## Release hold — real RedZebra semantics — 22 September 2026

Keep the combined XLS/DEC-068 header candidate and optional-completion follow-up on
feature branches. The small real workbook reaches a selected-site preview but `CU4`
blocks commit under the approved dictionary; it occurs 939 times in the full export.
Obtain the precise management mapping decision, qualify production PHP extensions and
temporary-file handling, rerun the fresh Composer advisory check after its endpoint
timeout, then perform DEC-068 release review. Disposable MySQL 8.4 release gates passed.
The user raised Forge upload/execution limits to 5 MB / 45 seconds; no candidate code
was deployed. See the [qualification report](documentation/wald-real-source-qualification-2026-09-22.md).
Earlier roadmap entries below are historical checkpoints.

## Next release review — binary XLS and exact source header — 22 September 2026

The combined `codex/wald-xls-reader` candidate supports the original RedZebra `.xls` export
and DEC-068's exact `Customer Number` identity header. It is not on `main` or in production.
Review the [candidate evidence](documentation/wald-xls-upload-2026-09-22.md), dependency/runtime
requirements, dictionary-v4 stale knowledge and remaining disposable-MySQL/release gates before
a controlled deployment. After deployment, Office must explicitly review source questions and
binding before any selected-site commit. No automatic multi-site processing or new source meaning
is implied. The sections below record earlier milestones.

## Next release review — exact `Customer Number` header — 22 September 2026

DEC-068's narrow header extension is implemented on `codex/wald-customer-number-header`, not on
`main` or production. Local SQLite regression, Pint and build pass. Before release, review the
dictionary v4 knowledge-staleness effect, run the remaining Composer/disposable-MySQL gates and
approve a controlled deployment. Only after that should the failed 22 September master export
be re-uploaded and reviewed; `CU4`, completion and other source meanings remain separate decisions.
The earlier product-label deployment evidence below remains valid history.

## Product labels delivered and verified — 21 September 2026

**Deployed and live-verified:** `b0bdca00ffd3cd8b307e28d798b133c8b4f978cc`, Forge `78156998`.
Customer VS/BF labels/check summaries, Office/source boundaries, bindings/uniqueness/history
and both-role access/GET logout checks pass. No production data edits or call-off submissions.
See [final evidence and explicit live-test limits](documentation/customer-product-labels-deployment-2026-09-21.md).
Next recommended bounded UX task: import completion/status wording, with separate approval.
Filters/navigation, View/Edit User and Projected plots remain separate. Earlier checkpoints
below are historical and do not override this verified deployment state.

## Product labels deployed — live acceptance pending — 21 September 2026

**Deployed:** `b0bdca00ffd3cd8b307e28d798b133c8b4f978cc`, Forge `78156998`.
Release health and exact served revision verified; no migration/data rewrite.
Customer Plot Details and call-off check labels pass live; role denials pass. Office smoke
awaits Office sign-in, and the browser-history limitation remains documented. See the
[release checkpoint](documentation/customer-product-labels-deployment-2026-09-21.md).
Next: complete live acceptance, then separately approve import completion/status wording.
Earlier candidate/release-review entries below are historical. No additional deployment is planned.

## Customer product labels — release review — 21 September 2026

The bounded dictionary-backed customer label correction is **feature-branch only, READY FOR
RELEASE REVIEW**, on `codex/customer-product-labels`. It preserves the deployed Overview fix
and the unpushed `12d765c` deployment documentation. Plot Details and existing call-off product
summaries now use approved names without changing canonical source/review facts or workflow.
Full local tests and synthetic Site Manager/Office checks pass. See the
[candidate report](documentation/customer-product-labels-ux-fix-2026-09-21.md).
Next: obtain release approval, deploy/verify labels, then separately address import completion
wording. Filters/navigation, User UI and Projected plots terminology remain out of scope.
Production remains `590503b6c80ea186bc6001e43a11ab528298961a`; no deployment in this task.

## Overview correction delivered — 21 September 2026

**Deployed:** `590503b6c80ea186bc6001e43a11ab528298961a`, Forge `78155443`.
Live linked/unbound agreement, existing legacy/multiple binding presentation, Site Manager
access/plot visibility and Office/Site Manager logout privacy pass. No migration or data rewrite.
See [deployment evidence](documentation/site-overview-source-binding-deployment-2026-09-21.md).
Next recommended work is a separately approved customer product-label task. Import wording,
Filters/navigation, User UI and terminology remain separate. Earlier candidate entries below
are historical and do not override this verified release state.

## Bounded Overview consistency repair — 21 September 2026

The user accepts the current production workflow; freshly fetched `origin/main` is
`0ac7082141156fdff29d296881bbb7d3299e20b5`. Prior release-candidate entries below are
historical, not the current production acceptance state.
The separate `codex/fix-site-overview-source-binding` candidate corrects only the
Overview linked/unlinked presentation using the established Source Binding result.
READY FOR RELEASE REVIEW with the separately recorded known baseline test/audit findings.
See the [Overview repair report](documentation/site-overview-source-binding-fix-2026-09-21.md)
for verification and release limitations. No deployment in this task.
Next, after separate release approval: deploy and verify this correction, then address
customer product labels as a new bounded UX task. Other UX findings stay out of scope.

## Logout privacy release candidate — 20 September 2026

The isolated no-store/private hotfix is READY FOR RELEASE REVIEW after local browser,
focused/access and broader verification. Next: obtain separate release approval, then
repeat Office and Site Manager logout/Back acceptance
on freshly loaded production pages. Production baseline is
`76cabfcb3a906476e6a47b63b797db1025590e10`; this task does not deploy. Wald is already
production-verified and is not changed; P2 presentation work remains separate. See the
[privacy hotfix report](documentation/logout-browser-back-privacy-hotfix-2026-09-20.md).
Earlier release-status entries below are historical, not current deployment truth.

## CUSTOMER-WALD-SOURCE02 — master-export revisions + CustomerCode — 15 September 2026

**Implementation and release gates are complete and the release is merged into local `main` at
`2c5fa6d3888bcb9c6a46457c0ad3bbbb35972fa9`, approved for controlled push.** The
same RedZebra date/slot can receive retained revisions, with automatic failed-upload replacement,
confirmation for non-failed replacement, identical-hash suppression, current/history presentation
and stale-preview protection. Exact CustomerCode is authoritative for source-site bindings; Site
Name is descriptive evidence and unknown/missing codes fail safely.

Existing additive schema requires no migration. SQLite, private-workbook and disposable MySQL
8.4.11 gates pass. Wald remains OFF; deployment evidence, any later fictional smoke, enablement
and production import remain separate controlled steps.

Earlier entries below are historical inputs and do not override this status.

## CUSTOMER-WALD-GOLIVE02 — strict environment gate — 15 September 2026

**Narrow correction implemented and fully verified; controlled release handoff is next.** Only exact
case-insensitive `true` may enable the environment half of Wald availability. All other values fail
closed, and the application setting plus current Office authority remain mandatory.

Focused strict-gate/availability coverage passed 22 tests / 57 assertions; combined Wald pilot,
CUSTAPP2 and commit-boundary regression passed 63 tests with one intentional MySQL-only skip / 198
assertions; and the full application suite passed 1,550 tests with 79 skips / 7,977 assertions.

Next delivery order after the exact-SHA handoff: Integration/DevOps reviews and deploys the
strict-gate commit, proves false survives the release cycle, then separately handles the CUSTAPP2
candidate, migration and fresh fictional smoke. Deliberate enablement is last and remains outside
this task.

Production is unchanged on `89768986a1a32d4258b5deebdf3012584acd6a6c` with Wald OFF.

Earlier CUSTAPP2/pilot entries below are historical inputs and do not override this status.

## CUSTOMER-WALD-CUSTAPP2-01 — composite workbook compatibility — 15 September 2026

**Implemented with PARTIAL local qualification on a non-deploying feature branch.** The bounded
backend now supports the approved composite table, deterministic CallNo headers, blank Call Type
without a manufactured visit, separate Plot/Source Row/Visit identity, exact physical provenance
and fail-closed product consolidation. One explicitly selected, exactly bound site remains the
atomic review/commit unit.

Eight of nine private-workbook site units qualify on both SQLite and MySQL. The ninth correctly
blocks due to conflicting explicit product evidence for one plot. Next: resolve that source
evidence through an authorised data correction/decision, re-run exact-SHA qualification, then use
a separate dedicated QA/release gate. Full WALD06 orchestration remains later work.

Production remains on `89768986a1a32d4258b5deebdf3012584acd6a6c`; Wald is effectively OFF via
`WALD_IMPORT_AVAILABLE=false`, and gate-drift investigation is separate. This work does not
authorise `main`, push, migration, production activation or deployment.

Earlier pilot/WALD06 entries below are historical and do not override this status.

## CUSTOMER-WALD-PILOT01/02 — supervised weekend pilot — 15 September 2026

**Production is deployed but the pilot is disabled after its first fictional smoke exposed a
duplicate commit-journal boundary.** Upload through approved preview worked; the commit guard
refused safely with no receipt or Portal mutation. A narrow feature-branch correction now makes
the domain import service the sole journal owner and has passed strict authenticated HTTP/MySQL,
full application and established MySQL race requalification.

Next delivery order is separate approval to merge and deploy the correction, verify the served
SHA, establish a fresh backup, create a fresh fictional one-site preview and repeat the supervised
commit/idempotency/isolation smoke. Keep `WALD_IMPORT_AVAILABLE=false` until that point. Full
WALD06 automatic multi-site orchestration remains deferred.

Earlier WALD06 status below is historical and does not override this pilot.

## CUSTOMER-WALD06 — Multi-site Import Studio and pilot readiness — 11 September 2026

**In progress on the one canonical Wald branch; not released or deployed.** The approved
architecture treats one uploaded workbook as one parent export event with one export date/slot
identity. It creates independently reviewable, independently atomic site units beneath that
parent. Parent ordering must not make all sites one database transaction, and child commits for
different sites must not collide merely because they share the parent slot.

The work must preserve the accepted WALD02–05 engine, governance, binding, review, projection,
source-completion and durable-audit contracts while adding the Office-only, default-off import
workspace. Actual-workbook evidence is local/test only and must use the approved PC1, CC1/`CC!`
and CM1 selection with CM2 and `NICK TEST` excluded. Production enablement, importer retirement,
automatic purge, SiteApp modification, main push and deployment remain separate decisions.

Earlier entries below are preserved evidence and do not override this status.

## CUSTOMER-GIT-CLEANUP02 — Remote branch cleanup complete — 11 September 2026

The remote repository now has two heads only: `origin/main` at
`e757bb651f9aa95d67b808a97433d27bc29d03c3` and canonical unreleased Wald at
`origin/feature/customer-wald-next` (`c9fe0620069a10fe07050e4ec2f30043a9a9a1ec`). Twelve
annotated milestone/archive tags preserve the removed histories. Local `main` was not pushed and
no production deployment occurred. Remaining cleanup is limited to separately resolving the
dirty historical worktrees without reset, clean or force removal.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-GIT-CLEANUP01 — Canonical branch ownership — 11 September 2026

**Local consolidation is complete except for protected dirty worktrees and remote publication.**
Prepared executable local `main` checkpoint `93737df1e8dae36b9d79b6b641e30b6c9af51908` is
the accepted non-Wald product line; this cleanup adds only documentation above it and no
additional feature merge is required. Accepted unreleased WALD02–05 is
owned by the single canonical local branch `feature/customer-wald-next` at
`c9fe0620069a10fe07050e4ec2f30043a9a9a1ec`. Superseded manual import/interpreter work is
archive-only and must not be merged.

Use short-lived feature branches, remove QA/release branches after accepted outcomes are
preserved, and use annotated milestone tags instead of permanent historical branches. Remote
cleanup is pending separate approval to publish the canonical Wald ref/tags; `origin/main`
remains `e757bb651f9aa95d67b808a97433d27bc29d03c3`. No push to `main` or deployment occurred.
See `documentation/customerapp-git-consolidation-2026-09-11.md`.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-NEXT-RELEASE02 — Dedicated RC1 QA passed — 10 September 2026

**Current delivery milestone: READY_FOR_MERGE_TO_MAIN_PREPARATION, not merged or deployed.**
Frozen RC `2e58bedcb70c487dfee1ae9f01a087c7ed8117e6` passed the dedicated release gate. One P3
test-harness ordering defect was corrected without an application/runtime change at
`10f0a56ac1987754ab0c31b45fc08138ba25e3f8`. Scope/Wald exclusion, 12-to-13 migration,
customer/site administration, lifecycle, authorization, IDOR/mass assignment, audit, Sprint 3F,
queue-card, filters, browser, responsive, accessibility, performance, SQLite and MySQL 8.4.11
checks passed. Production dependency audits are clean; 14 locked development/build advisories are
retained for maintenance and do not block this release. No push, `main` merge, production access,
migration or deployment occurred. See
`documentation/customerapp-next-release-dedicated-qa-2026-09-10.md`.

Earlier entries below are historical evidence and do not override this status.

## CUSTOMER-NEXT-RELEASE01 — Sidebar + Admin + Synthetic Demo RC1 — 10 September 2026

**Current delivery milestone: READY_FOR_DEDICATED_QA on a non-deploying release branch.** The candidate branch is
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
forward QA. RCQ-01 is `RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`: pre-traffic failure may restore
the fresh pre-deployment database snapshot plus previous verified release; post-traffic or
post-Sprint3F-write recovery is roll-forward only from the deployed RC or compatible descendant.
Old main is not a post-write rollback target, and a Forge code/symlink switch does not restore
MySQL. This documentation-only approval permits merge-to-main preparation but does not authorise
a main push, production deployment or production change. See
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

*Last Updated: 25 August 2026*

# Project Overview

The Customer Portal is a standalone customer-facing request and communication application. It integrates with SiteApp without duplicating SiteApp's internal operational workflow.

# Current Version

**Current Milestone:** `Sprint 3F date amendments — product decisions integrated, dedicated QA next`

3 September 2026: the current user-approved sequence calls the initial agreement
workflow Sprint 3E and Date Amendments After Date Agreed Sprint 3F. That naming
supersedes the original management-programme numbering retained below.
The feature worktree starts from local main `0873bac`, which includes Sprint 3E and
the Office-organisation correction. Older release paragraphs below are historical.

The amendment engine and final management decisions are implemented. DEC-039 confirms
the seven reason codes, required Other explanation and existing On Hold overall-status
treatment. No Sprint 3F product decision remains. The Office race fix passed the full
MySQL gate; its targeted regression remains green after product integration.
Dedicated Sprint 3F QA, separate Composer security reconciliation and final combined
release-candidate verification remain. This is not deployment or company-test approval.
No deployment or main merge/push occurred.
See `documentation/sprint-3f-date-amendments-2026-09-03.md`.

Release-management update: production serves `main`
`f801c91113bd13656c6cbffdd3d82c12d4a95846`, deployed successfully after the documented
MySQL migration repair. The four repaired historical migrations are production-critical.
Sprint 3E dedicated QA passed and its validated work has been transplanted onto a release branch
from current `main`. It remains excluded from `main` and production pending the mandatory
disposable MySQL release gate and explicit merge/deployment approval.

Status: Laravel project and SiteApp-matched dependency stack created. Sprint 1A secure
access and domain foundation has been implemented and verified. Sprint 1B call-off domain
foundation has been implemented and QA verified. Sprint 1C.1 authenticated assigned-site
selection and site dashboard UI has been implemented and verified. Sprint 1C.2 New Call
Off UI has been implemented and verified against the audited domain actions. Sprint 1C.3
Office Staff review, approval and rejection UI has been implemented and QA verified after
a confirmation-integrity correction. Sprint 1D withdrawal, Trash, restore and quick Undo
UI has been implemented and QA verified after Undo ownership and dashboard summary
corrections. Sprint 1E backend notifications have been implemented; notification centre
UI has been implemented and QA verified after recipient-authorisation, preview-user and
no-JavaScript form corrections.

Sprint 3C has delivered the source-aware, plot-centric dashboard and authorised plot
details using the Sprint 3A/3B projections. Dedicated QA passed after an active-site UUID
containment correction; the full Pest suite passes (162 tests, 792 assertions), and the
expressly authorised local SQLite `migrate:fresh --seed` rehearsal passed. Sprint 1G locally
verifiable hardening and Sprint 2A readiness evidence remain historical
baselines. Management's 20 August requirements supersede the old three-service,
single-service/single-date batch, assigned-site Office Staff and Approved/Rejected product
assumptions. The Portal is therefore not ready for company testing until the full
management-confirmed programme and final integrated QA gate have completed. MySQL evidence
and production infrastructure controls remain release blockers.

## Management Requirements Programme — 20 August 2026

The authoritative programme is documented in
`documentation/management-gap-analysis-2026-08-20.md`. It replaces the former sequence of
small incremental Version 1 feature milestones with a controlled target-domain migration:

1. Sprint 3A — Target domain and access migration contract.
2. Sprint 3B — Source projection and service eligibility foundation.
3. Sprint 3C — Plot-centric overview and plot details.
4. Sprint 3D — Lead-time and eligibility engine.
5. Sprint 3E — Multi-plot/multi-service New Call Off.
6. Sprint 3F — Date negotiation and Office review.
7. Sprint 3G — Amendments and source completion reconciliation.
8. Sprint 3H — Structured attachments.
9. Sprint 3I — Notifications and reminders.
10. Sprint 3J — Calendar and PDF schedules.
11. Sprint 3K — Integrated QA and company-test preparation.

Sprint 3A has produced the additive schema/access migration spike and preservation tests.
SQLite QA passed on 20 August 2026 after a non-empty legacy migration rehearsal and two
narrow data-integrity corrections. Sprint 3B SQLite QA passed on 21 August 2026 after a
non-empty source-contract rehearsal and import-integrity corrections. MySQL rehearsal
remains mandatory before production or live source integration.

Sprint 3C implementation adds the four-service plot overview, central source-completion
precedence, completed-plot visibility control, filters, responsive card/table layouts and
authorised UUID plot detail pages. It deliberately excludes new call-off, negotiation,
attachment, calendar/PDF and source-transport work. See
`documentation/sprint-3c-plot-overview-report.md`.
Dedicated QA evidence is recorded in
`documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md`.

Sprint 3D has replaced new-submission creation with a shared active-site UUID selection,
matrix, signed-review and atomic-final-submit backend flow. The dashboard selected-plots
entry and New Call Off entry use the same contract. Details for UI consumption, error
states, early-date exceptions, replay protection and notification limitations are in
`documentation/sprint-3d-bulk-call-off-report.md`. A later Sprint 3E implementation exists
only on the excluded side branch described above; it is not current approved work.

## Sprint 2A — Company Test Readiness

Planned scope:

- migration rollback integrity (AUD-001);
- mobile notification fly-out (AUD-002);
- high-severity npm advisory triage (AUD-003);
- notification fetch failure state (AUD-007);
- active-site dashboard and Trash pagination, plot search and bounded dashboard filters
  (AUD-005/AUD-008);
- lifecycle-button selection behaviour (AUD-012);
- stale welcome-template cleanup (AUD-013);
- documentation refresh against the 19 August audit (AUD-009);
- confirmed traceable rejected-call-off resubmission backend integration (DEC-034).

Backend result — 19 August 2026:

- AUD-001 SQLite rollback/reapply correction and disposable verification script completed.
- AUD-005/AUD-008 active-site dashboard and Trash pagination backend completed. Dashboard
  supports plot, service and status filters; date, development and phase remain deferred.
- DEC-034 confirmed and secure rejected-request resubmission review/confirmation/backend
  flow completed.
- UI remediation is complete: responsive notification error handling, dashboard/Trash
  browsing affordances, lifecycle selection states, rejected-request resubmission screens
  and welcome-template cleanup now consume the existing backend contracts.
- Dedicated Sprint 2A QA passed on 19 August 2026. The npm advisory remains a
  production-release risk; it is not a controlled local company-test blocker because the
  affected chain is build tooling and no untrusted CSS/source-map input is processed.

Excluded from Sprint 2A: MySQL and production infrastructure work, physical-device and
screen-reader release evidence, amendments, later progress statuses, SiteApp integration,
advanced reporting, email and push notifications.

# Version Roadmap

The historical version sequence below is retained for traceability. It must not be used as
an implementation contract where it conflicts with the Management Requirements Programme
above; in particular, Calendar, attachments and the four-service/date-agreement domain are
now Version 1 work, not later-version scope.

## v0.1 — Application Foundation

- Create the Laravel application in Herd
- Confirm and lock package versions
- Configure SQLite locally
- Establish repository structure
- Add base layout and branding foundation
- Configure tests and frontend build
- Confirm independent operation from SiteApp

**Exit:** application boots, tests run, assets build, and no SiteApp code has been copied.

## v0.2 — Authentication and Access Boundaries

- Secure login and password reset
- Disable public registration
- Active-account enforcement
- Customer organisation and portal site-assignment model
- Sites
- Four distinct portal roles: Site Manager, Assistant Site Manager, Finishing Foreman and Fenster Office Staff
- Active assigned-site selection for site roles
- Assigned-site review scope for Fenster Office Staff
- Development-only role-preview routing, excluded from production
- Server-side authorisation
- Cross-customer and cross-site access tests

**Sprint 1A Exit:** authenticated users are active, assigned to customer organisations,
hold portal-specific roles, and can access only authorised site contexts. Development
preview must be unavailable in production and must not bypass authentication or
authorisation.

## v0.3 — Customer Data Foundation

- Customer-visible developments and phases
- Outstanding projected plots
- Service types: Cavity Closers, Windows and CML
- External SiteApp identifiers
- Synchronisation state
- Search, filtering and pagination

## v0.4 — Call-Off Workflow

- Call-off dates and explicit customer-visible response fields
- Call-off batch model where one submission action has exactly one site, one service type,
  one requested date and one submitting user
- Individual request model where each request applies to one projected plot for the batch
  service type
- Independent request decisions after submission
- One active request per plot and service
- Centralised call-off eligibility action
- Computed active conflict key for duplicate prevention
- Bulk requests where approved
- Request detail and history
- Submitted call-off lifecycle and validation
- Pending request withdrawal
- Rejected request Trash and traceable resubmission
- Five-second quick Undo for eligible actions
- Seven-day customer-facing Trash recovery and expiry handling
- Validation, authorisation and cross-site access tests

## v0.5 — Fenster Office Approval

- Fenster Office Staff Review Requests dashboard
- Assigned-site review queue
- Approve a submitted call-off
- Reject a submitted call-off
- Customer-visible response
- Private internal reason
- User attribution, timestamps and transition tests

## v0.6 — Amendments and Revisions

- Amendment requests
- Explicit customer-visible responses and private internal reasons
- Previous and proposed date tracking
- Immutable revision history
- Resubmission and amendment eligibility rules

## v0.7 — Notifications and Status Tracking

- In-app notification centre
- Submission, approval and rejection notifications
- Customer-facing progress statuses
- Read/unread state
- Email where approved

## v0.8 — Dashboard and Usability

- Dashboard summaries
- Planned service dates
- Outstanding and recently approved requests
- Due-soon view
- Filters and plot search
- Mobile layouts
- Accessible empty, loading and error states

## v0.9 — SiteApp Integration

- Approve the integration contract
- Define API, event or synchronisation approach
- Secure service authentication
- Synchronise authorised customer, development and plot data
- Receive completion and customer-visible progress updates
- Submit or export requests through the approved boundary
- Idempotency, retries, stale-data indicators and integration tests

## v0.10 — Production Readiness

- Security and privacy review
- Performance review
- MySQL validation
- Production queue, cache and mail validation
- Logging and failure monitoring
- Backup and recovery plan
- CI/CD and deployment documentation
- User acceptance testing

## v1.0 — Production Release

- Authentication
- Customer dashboard
- Outstanding plots
- Call-off requests
- Amendments
- Fenster Office approval
- Notifications
- Status tracking
- SiteApp integration
- Responsive production deployment

# Future Versions

## v1.1 – Portal User Management
Customer invitations, portal role management, site assignments and offboarding.

## v1.2 – Calendar Experience
Calendar view, upcoming work and calendar export.

## Future – QR Site Context
QR codes may identify a site and assist a signed-in, authorised site user with selecting
that site. They must never authenticate a user, grant site access or bypass authorisation.

## v1.3 – Evidence and Attachments
Secure customer documents and optional delay evidence.

## v1.4 — Enhanced Notifications
SMS, push notifications, preferences and digests.

## v1.5 — Reporting
Request reports, date-change summaries and exports.

## v2.0 — Enterprise Authentication
MFA, SSO and Microsoft Entra ID.

# Explicit Non-Roadmap Items

Do not add SiteApp workflow, trade sequencing, trade sign-off, Black Hat approval, readiness, build verification, SiteApp queries, SiteApp administration, manufacturing scheduling, labour planning or operational resource planning unless the brief is explicitly changed.

# Outstanding Decisions

- Customer-facing meaning of CML
- Bulk request creation limits and validation feedback
- Amendment lifecycle eligibility beyond Version 1 withdrawal, rejected-request resubmission, Trash and Undo
- Email notification requirements
- Synchronisation freshness
- Customer-facing status mapping
- Long-term retention requirements outside the seven-day customer-facing Trash window
- Initial SiteApp integration method

# Release Checklist

Each release should include:

- tests passing;
- formatting checks passing;
- frontend build passing;
- migrations reviewed;
- customer isolation tested;
- documentation updated;
- no SiteApp internal functionality introduced;
- manual deployment actions recorded.

# Project Health

| Item | Status |
|---|---|
| Current Version | Sprint 2A - Company Test Readiness (planned) |
| Next Version | Controlled company-test readiness; production release remains blocked |
| Project Created | Yes |
| Foundation Documents | ✅ Prepared |
| Authentication | Implemented for Sprint 1A |
| Customer Data | Organisation and site foundation implemented |
| Requests | Sprint 1B backend/domain foundation implemented; Sprint 1C.2 submission UI and Sprint 1D withdrawal/Trash/Undo UI implemented |
| Approval | Sprint 1B backend/domain actions implemented; Sprint 1C.3 Office Staff decision UI implemented |
| Notifications | Sprint 1E backend and notification centre UI implemented and QA verified |
| Amendments | ⚪ Not started; remains out of Sprint 1G scope |
| SiteApp Integration | ⚪ Not defined |
| Production Hardening | Locally verified; production evidence outstanding |
| Production Ready | ❌ No |
