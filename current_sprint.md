# Current Sprint

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

## CUSTOMER-NEXT-RELEASE02 — Dedicated RC1 QA passed — 10 September 2026

**PASS; READY_FOR_MERGE_TO_MAIN_PREPARATION, not merged or deployed.** Frozen RC
`2e58bedcb70c487dfee1ae9f01a087c7ed8117e6` passed scope, migration, admin, security,
regression, browser, responsive, accessibility, performance, SQLite and MySQL 8.4.11 QA.
One P3 test-harness ordering defect was corrected without an application/runtime change at
`10f0a56ac1987754ab0c31b45fc08138ba25e3f8`. Full SQLite: 393 passed / 43 intentional skips /
2,784 assertions. Full MySQL: 430 passed / 1 intentional skip / 3,820 assertions, plus five
guarded admin races and 40 repeated deterministic Sprint 3E ordering cases. Production dependency
audits are clean; 14 locked development/build advisories do not block this release. No push,
`main` merge, production access, migration or deployment occurred. See
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
functional, security, browser, migration and concurrency QA. RCQ-01 is
`RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`: before reopening traffic, a failed release may restore
the fresh pre-deployment database snapshot and previous verified application release together;
after reopening or any Sprint 3F write, old main is forbidden and recovery is roll-forward from
the deployed RC or a compatible descendant. Forge code-release/symlink rollback does not restore
MySQL. CUSTOMER-RELEASE03A changes documentation only; it authorises merge-to-main preparation,
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
