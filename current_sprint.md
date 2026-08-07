# Current Sprint

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
