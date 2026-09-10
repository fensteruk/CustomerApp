# Fenster Customer Portal Handover

## CUSTOMER-ADMIN-SITE02 UI preparation — 10 September 2026

**PARTIAL — feature branch only; not ready for dedicated QA.** The UI work on
`feature/customer-admin-site02` starts from accepted sidebar documentation checkpoint
`307e3fa78e88b26d0e102642087dc504a4a9572e` and retains sidebar code
`c80ab5b76a161f340b8786c709b93fecfae47633`.

Customer/site lists, metadata and lifecycle forms, read-only site sections, safe import
placeholders and presentation tests are prepared against the inspected ADMIN-SITE02A draft
contract. The user confirmed that another task owns that backend: consume it when ready,
do not duplicate it. Its worktree remains uncommitted at this checkpoint, so no backend
implementation or migration has been copied into this branch.

The workspace is **not currently available as a working administration feature**:
the required backend query service, policies, UUID/lifecycle schema and JSON mutations
are not yet integrated. Existing application regression passes do not establish new
administration workflow correctness. Positive HTTP, security, MySQL and responsive browser
verification remain integration gates. See the
[implementation and backend handoff](documentation/admin/customer-admin-site02-implementation-2026-09-10.md)
for exact entry points, evidence and missing contract fields.

A01/A02/A03/A04 are recorded in DEC-058: active/inactive lifecycle, optional source
reference, source-managed read-only plots and a separately scoped ADMIN-SITE03 demo.
This work remains `NEXT_RELEASE`; it does not reopen RC1, merge to main, deploy, or
claim any current production state. Earlier dated entries below are historical evidence
and do not override this feature-branch status.

## CUSTOMER-UI-SIDEBAR accepted baseline — 10 September 2026

Dedicated QA passed for corrected candidate
`c80ab5b76a161f340b8786c709b93fecfae47633` on
`feature/customer-ui-sidebar-workspace`. It is the accepted `NEXT_RELEASE` sidebar
baseline. Original candidate `d76ddbba47ba7b16e6376d2f089ab06b4cc46187`
is superseded as an acceptance candidate.

The accepted candidate includes QA corrections for extreme plot-reference wrapping,
320px header overflow and notification/drawer focus interaction. The final evidence is
17 focused tests / 114 assertions, 121 related UI regression tests / 748 assertions and
331 full-suite passes / 38 skips / 2,367 assertions, with responsive, access,
accessibility, long-list, Composer audit and Vite build gates passing.

Release disposition is `NEXT_RELEASE`. RC1 is unchanged and must not be reopened for this
feature. No merge to RC1 or `main`, push or deployment occurred. Later integration may
consider the accepted sidebar, ADMIN-SITE02 only after QA approval and possibly ADMIN-SITE03
after separate synthetic-import-demo QA. See the
[acceptance record](documentation/customer-ui-sidebar-acceptance-2026-09-10.md) and
[preserved QA report](documentation/customer-ui-sidebar-dedicated-qa-2026-09-10.md).

## CUSTOMER-RELEASE02 — reduced RC1 build — 9 September 2026

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
