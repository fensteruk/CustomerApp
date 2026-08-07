# Sprint 1F — Production Readiness

**Status:** Implementation contract

**Scope:** Production-readiness evidence and release gates for the Fenster Customer
Portal

This checklist defines what must be verified before a production deployment is approved.
It is a release gate, not a deployment script. It does not introduce SiteApp endpoints,
workflow mappings or operational behaviour.

## 1. Readiness decision and current blockers

Production readiness is achieved only when every required gate below has evidence,
ownership and a recorded result. A green application test suite alone is not a production
approval.

The following are known gates at the start of Sprint 1F:

- MySQL-compatible production validation has not yet been completed; previous verification
  used local SQLite.
- Production queue, scheduler, mail and monitoring infrastructure must be exercised in a
  production-like environment.
- A live assistive-technology review and physical mobile-device pass remain outstanding.
- Long-term notification retention remains an open product decision. Sprint 1E therefore
  must not silently purge notifications.
- The initial SiteApp integration method and contract remain undefined. No full Version 1
  release may claim SiteApp integration readiness until that contract is explicitly
  approved and tested.

If any blocker remains open, the release owner must record whether the deployment is a
limited portal-only release or a no-go. A limited release must not be described as full
Version 1 production readiness.

## 2. Production environment requirements

### Runtime and application

- [ ] Production runs the locked application dependencies; no package upgrades or lockfile
      changes are made during deployment.
- [ ] PHP 8.4.x is installed and the production PHP executable is the one documented by
      the hosting platform.
- [ ] Laravel 13.20.0 and the locked Composer dependencies are installed with production
      settings and without development-only packages.
- [ ] The production database is the approved MySQL-compatible engine and version; SQLite
      is for local development and is not accepted as production evidence.
- [ ] Node.js and npm are available in the build environment at the locked project
      versions (Node 26.x and npm 11.x), or a verified build artifact is supplied. Node
      and npm are not required on the serving host when assets are built in CI.
- [ ] `APP_ENV=production` is set.
- [ ] `APP_DEBUG=false` is set and confirmed from the running application.
- [ ] `APP_KEY` is present, unique to the environment, stored as a secret and backed up
      through the approved secret-recovery process. It is never committed to Git.
- [ ] `APP_URL` and all public callback or asset URLs use the approved HTTPS domain.
- [ ] TLS is valid, automatically renewed and redirects HTTP to HTTPS.
- [ ] Production session cookies are secure, HTTP-only and use the approved same-site
      policy.
- [ ] Public registration remains disabled.
- [ ] Development role preview is unavailable in production, including direct URL and
      POST access.
- [ ] Production error pages do not expose stack traces, environment values, credentials,
      SQL, provider responses or internal paths.
- [ ] Production secrets are injected through the approved secret store or environment
      configuration and are not present in the repository, build artifact or logs.

### Configuration and access

- [ ] Database, cache, queue, mail and storage connections use separate least-privilege
      production credentials.
- [ ] Database credentials cannot create unrelated databases or access SiteApp directly.
- [ ] Operators, deployers and application users have separate accounts and permissions.
- [ ] The configured timezone policy is documented. Stored timestamps and comparisons use
      one deliberate, tested timezone convention.
- [ ] Configuration, route and view caches are built only from the production environment
      and are cleared/rebuilt as part of deployment.
- [ ] The health check reports application readiness without exposing secrets or customer
      data.

### Application boundary

- [ ] Customer organisation isolation is verified on every protected query and action.
- [ ] Assigned-site scope is enforced for site roles and Fenster Office Staff.
- [ ] Site Manager, Assistant Site Manager and Finishing Foreman remain portal-specific
      roles with identical Version 1 permissions.
- [ ] Public UUIDs are used externally; integer database IDs are not exposed.
- [ ] `internal_reason`, operation snapshots, conflict keys and private integration data
      are not included in customer-facing payloads or logs.
- [ ] No SiteApp workflow, status, role, policy, table, query or internal administration
      has been introduced into the deployment.

## 3. MySQL validation checklist

Validation must run against the same MySQL-compatible engine family and relevant settings
as production. SQLite success is not a substitute.

### Schema and connection

- [ ] A clean migration run succeeds on an empty MySQL database.
- [ ] A migration run against a representative existing database succeeds without data
      loss.
- [ ] `php artisan migrate:status` reports the expected migrations.
- [ ] Foreign keys, delete restrictions, nullable fields, UUID uniqueness and indexes are
      present as designed.
- [ ] `active_conflict_key` prevents two active requests for the same projected plot and
      service under concurrent submissions.
- [ ] Operation-item restrictions preserve bulk Undo and restoration audit snapshots.
- [ ] Notification storage supports per-recipient unread/read and dismissal state without
      cross-user access.
- [ ] Character set, collation, strict SQL mode and index lengths are compatible with the
      application schema and customer-facing text.
- [ ] Database connection failures fail safely and do not show credentials or SQL details
      to customers.
- [ ] Database transactions and row locks behave correctly under the production engine.

### Domain and concurrency tests

- [ ] Concurrent submissions cannot create duplicate active requests.
- [ ] Concurrent approval and rejection cannot overwrite an earlier decision.
- [ ] Diverged requests in one batch remain independently decided.
- [ ] Bulk withdrawal, Trash restoration and Undo remain all-or-nothing.
- [ ] Approved requests retain their active conflict key.
- [ ] Rejected and withdrawn requests clear the conflict key and permit traceable
      resubmission according to the confirmed lifecycle.
- [ ] Seven-day customer-facing Trash visibility is time-based and hides expired records
      without deleting audit history.
- [ ] Five-second Undo uses server timestamps and cannot be extended by client input.
- [ ] Notification recipient writes are complete, idempotent where required and cannot
      roll back a valid call-off transition.
- [ ] Revoked assignments, changed roles and cross-organisation access are rejected after
      notification creation as well as at initial request access.

### Evidence

- [ ] Test output, migration output, database version/settings and representative query
      results are attached to the release record.
- [ ] A verified backup has been restored into a disposable MySQL database.
- [ ] Any SQLite/MySQL difference is documented with an owner and remediation date.

## 4. Queue and scheduler requirements

### Queue

- [ ] A production queue connection is configured with credentials and failure handling.
- [ ] Queue workers run under a process supervisor with automatic restart and a controlled
      deployment restart procedure.
- [ ] Worker concurrency, timeout, retry and backoff settings are documented and tested
      against the application workload.
- [ ] Failed jobs are retained in the approved failed-job store and have an operator
      alerting path.
- [ ] Notification listener or delivery failures are logged and retried according to the
      approved policy without changing call-off state.
- [ ] Jobs are safe to retry and do not create duplicate notification records or duplicate
      customer-visible outcomes.
- [ ] Queue workers are restarted after each release so they do not continue running old
      application code.
- [ ] Queue health, age of oldest job, failure count and worker availability are monitored.

### Scheduler

- [ ] The scheduler process is configured with one active scheduler instance per
      environment, or the hosting platform's equivalent deduplication is verified.
- [ ] Scheduler heartbeat and failure alerts are tested.
- [ ] The current seven-day Trash rule is derived from timestamps; no destructive expiry
      scheduler is required for customer-facing visibility.
- [ ] No scheduler permanently deletes notification or call-off audit records.
- [ ] Any future retention, cleanup or reporting schedule has a separate approved rule,
      backup coverage and rollback plan before activation.

## 5. Storage requirements

- [ ] Application storage paths exist with the minimum permissions needed by the runtime.
- [ ] Private application storage is not directly web-accessible.
- [ ] Public assets are served only from the approved built asset path or explicitly
      approved public storage link.
- [ ] The current Version 1 scope has no customer-uploaded documents; no upload bucket,
      public file route or attachment retention policy is enabled speculatively.
- [ ] Session, cache, queue and log storage drivers are explicitly configured and their
      persistence/failure behaviour is documented.
- [ ] Storage capacity, inode/file-count limits and log rotation are monitored.
- [ ] Storage and backups use encryption at rest where supported and encrypted transport.
- [ ] Temporary files and build artifacts cannot contain `.env`, credentials or customer
      exports.
- [ ] Storage restore has been tested from the same backup source used in production.

## 6. Notification infrastructure

Sprint 1E production scope is the in-app notification centre only.

- [ ] `call_off_submitted`, `call_off_approved` and `call_off_rejected` are the only
      enabled Version 1 notification types.
- [ ] Successful submission, approval and rejection actions trigger notifications only
      after the portal state transition succeeds.
- [ ] Withdrawal, Trash, restoration, Undo, amendments, QR activity and SiteApp events do
      not create Sprint 1E notifications.
- [ ] Submission recipients are the active submitting site user and active Fenster Office
      Staff assigned to the affected site; approval and rejection recipients are the
      active submitting site user only.
- [ ] Preview users cannot create, query or receive real notification records.
- [ ] Notification payloads contain public UUIDs and customer-facing request context only.
- [ ] Notification links re-check current role, organisation and site assignment before
      showing request details.
- [ ] Read, mark-all-read and dismissal operations are per-recipient and tenant-scoped.
- [ ] Dismissal does not change call-off status or history.
- [ ] Notification retention is independent of the seven-day call-off Trash visibility
      window, and no unapproved purge job is enabled.
- [ ] In-app notification persistence and unread counts are covered by MySQL tests.
- [ ] Email is disabled unless a separately approved configuration and audience exist.
- [ ] Push notification transport is disabled; no device token or push integration is
      enabled in Sprint 1F.

## 7. Logging and monitoring

- [ ] Application, web server, PHP, queue, scheduler and database connection logs are
      collected in the approved monitoring system.
- [ ] Logs include timestamp, environment, severity, correlation/request identifier and
      safe operation context where useful.
- [ ] Logs exclude passwords, `APP_KEY`, tokens, session contents, customer response text
      where not required, `internal_reason`, raw notification payloads and SiteApp
      provider responses.
- [ ] Authentication failures, inactive-account blocks, authorisation failures, revoked
      assignments, failed migrations, failed jobs and notification listener failures are
      observable and alertable.
- [ ] Repeated duplicate-conflict, database, queue and storage failures have thresholds
      and named responders.
- [ ] Error tracking groups incidents without exposing customer data.
- [ ] Uptime and health checks verify the login boundary, authenticated dashboard and
      notification centre using non-customer test accounts.
- [ ] Monitoring access is restricted and audited.
- [ ] Log rotation, retention and deletion follow the approved security and retention
      policy; no log is treated as a substitute for immutable call-off history.

## 8. Backup and recovery

- [ ] Automated database backups run on the production schedule approved by the service
      owner.
- [ ] A backup is taken before a release that changes schema or data behaviour.
- [ ] Backups are encrypted, access-controlled and stored separately from the primary
      application environment.
- [ ] Backup retention, recovery point objective and recovery time objective are recorded
      before go-live; no unapproved duration is assumed by this checklist.
- [ ] Restore has been tested for the application database, notification records,
      customer organisation assignments and call-off history.
- [ ] Restore verification confirms that public UUIDs, audit history, active conflict keys,
      Trash timestamps and notification read/dismissal state remain coherent.
- [ ] Secrets, APP_KEY and required deployment configuration have an approved recovery
      process without storing them in the database backup unnecessarily.
- [ ] Recovery steps identify who can declare an incident, who can restore, how traffic is
      paused and how customer-facing data integrity is checked.
- [ ] A disaster-recovery exercise has a dated result, identified gaps and assigned
      follow-up owners.

## 9. Security review

- [ ] Authentication, password reset, session invalidation, CSRF protection and rate
      limiting are tested in production-like configuration.
- [ ] Inactive users cannot sign in or continue using protected routes.
- [ ] Public registration remains unavailable.
- [ ] All four portal roles are distinct and server-authorised; no SiteApp role or policy
      is reused.
- [ ] Site roles can access only assigned active sites and all visible requests remain
      within their customer organisation.
- [ ] Fenster Office Staff can review, approve and reject assigned sites only.
- [ ] Approved requests cannot be deleted, trashed or cancelled by site users.
- [ ] Cross-site, cross-organisation and malformed/foreign UUID access attempts fail safely.
- [ ] Role preview and any development-only route are blocked in production.
- [ ] QR-code routes are not enabled as an authentication or authorisation bypass.
- [ ] Customer-facing output is escaped and sensitive fields are excluded.
- [ ] Security headers, HTTPS enforcement, cookie settings, CORS and trusted-proxy rules
      match the hosting architecture.
- [ ] Composer and npm dependency audits have been reviewed without changing locked
      versions during this release.
- [ ] Secrets are rotated where required and no secret appears in Git history, artifacts,
      logs or error pages.
- [ ] Incident response contacts, security reporting path and access revocation procedure
      are documented.

## 10. Accessibility review

- [ ] A named reviewer has completed a keyboard-only pass on login, site selection,
      dashboard, New Call Off, review/decision screens, lifecycle screens and notification
      centre.
- [ ] Every interactive control has an accessible name and an obvious visible focus state.
- [ ] Heading order, landmarks, form labels, required fields and validation errors are
      understandable without sight or colour perception.
- [ ] Read/unread notification state is announced textually and is not conveyed by colour
      or icon alone.
- [ ] Loading, empty, error, success and confirmation states are announced or otherwise
      understandable without relying on JavaScript timing.
- [ ] No-JavaScript form paths remain safe and usable for authentication, decisions,
      lifecycle actions and notification read/dismissal operations.
- [ ] Touch targets, spacing, contrast, zoom and text reflow are reviewed against the
      accessibility standard adopted by the product owner.
- [ ] Screen-reader checks cover the notification bell, panel focus order, Escape-to-close
      behaviour, focus return and safe-open failure messages.
- [ ] The result, assistive technology/version and any exceptions are recorded.

## 11. Mobile-device testing

- [ ] Real or approved representative iOS and Android devices are tested, not only a
      desktop browser viewport.
- [ ] Supported mobile browsers are documented and tested over the supported version range.
- [ ] Login, site selection, dashboard, New Call Off, review decisions, Trash/Undo and
      notification centre work in portrait and landscape orientations.
- [ ] Forms remain usable with mobile keyboards, date controls, validation messages and
      long customer-facing text.
- [ ] Touch targets, focus indicators, sticky or compact notification controls and modal
      confirmations do not block the user.
- [ ] Slow network and interrupted navigation do not create duplicate submissions or
      imply a failed/accepted transition incorrectly.
- [ ] The visual five-second Undo countdown is treated as display-only; server timestamps
      remain authoritative on mobile.
- [ ] Screenshots, device/browser versions, defects and retest results are attached to the
      release record.

## 12. Performance review

- [ ] Performance testing uses production-like MySQL data volumes, organisations, sites,
      projected plots, call-off history and notification counts.
- [ ] Dashboard, review queue, notification centre and unread-count queries are checked
      for tenant/site scope, indexes, pagination and N+1 queries.
- [ ] Bulk lifecycle operations remain bounded, transactional and atomic under the
      approved request-size limit.
- [ ] Login, dashboard, New Call Off confirmation, review decisions and notification
      centre response times are measured under representative load.
- [ ] Queue throughput, worker capacity, failed-job rate and database contention are
      measured for notification events.
- [ ] Built assets are minified/versioned and cache headers are verified.
- [ ] Error and timeout behaviour remains customer-safe under load.
- [ ] Performance targets, test data assumptions and accepted limits are recorded by the
      owner; this document does not invent service-level numbers.

## 13. Deployment sequence

1. **Release approval:** confirm scope, migrations, security review, accessibility/device
   evidence, MySQL evidence, backup/restore evidence and named owner for every exception.
2. **Freeze and backup:** pause unrelated changes, verify the artifact checksum and take a
   pre-deployment database backup.
3. **Prepare infrastructure:** verify secrets, database connectivity, storage, queue,
   scheduler, monitoring and HTTPS before changing application traffic.
4. **Enable maintenance or drain traffic:** use the hosting platform's safe mechanism and
   preserve in-flight request/queue handling.
5. **Deploy the immutable artifact:** install the locked production dependencies and
   verified frontend assets. Do not run package upgrades.
6. **Apply schema changes:** run the reviewed migrations with the production-safe force
   option after the backup is confirmed.
7. **Refresh runtime state:** rebuild configuration, route and view caches; run only the
   approved storage-link step if the release requires it.
8. **Restart workers:** restart queue workers and scheduler processes so they use the new
   artifact. Confirm no old workers remain.
9. **Run smoke checks:** verify HTTPS, login, active-account enforcement, assigned-site
   routing, Office Staff scope, notification centre, safe-open authorisation and a
   non-destructive database query.
10. **Enable traffic:** remove maintenance mode or restore traffic only after smoke checks
    pass.
11. **Monitor:** observe application errors, queue depth, failed jobs, database health,
    storage and notification creation for the agreed post-deploy window.
12. **Record outcome:** mark the release accepted, accepted with an approved exception or
    rolled back. Attach logs and evidence without customer data.

No deployment step may create or imply SiteApp operational updates until the separately
approved integration contract exists.

## 14. Rollback procedure

Rollback is a controlled incident procedure, not an automatic response to a single failed
request.

1. Declare the rollback owner and record the reason, time, release identifier and observed
   customer impact.
2. Pause new traffic or enable maintenance mode and drain or safely stop workers.
3. Preserve logs, failed jobs, migration output and the pre-deployment backup.
4. Re-deploy the last known-good immutable application artifact.
5. Restart workers and scheduler processes against the restored artifact.
6. Do not automatically reverse migrations that may destroy data. Use a backward-compatible
   application rollback where possible. Any database restore or migration reversal needs
   explicit owner approval and an integrity check.
7. If a database restore is approved, restore the verified backup into the approved
   recovery path and confirm call-off status/history, conflict keys, Trash timestamps and
   notification records before reopening traffic.
8. Clear/rebuild runtime caches and verify configuration points to the restored artifact
   and database.
9. Run the smoke checks from the deployment sequence, including tenant isolation and
   notification safe-open checks.
10. Reopen traffic only after the rollback owner accepts the result. Monitor queue retries,
    duplicate notifications, failed submissions and customer-visible errors.
11. Record the incident, data-impact assessment, customer communication decision and
    corrective action before another release attempt.

Queued notification work must be checked during rollback so an old worker cannot emit a
duplicate or stale customer-facing notification after the application is restored.

## 15. Production acceptance checklist

The release owner may mark Sprint 1F accepted only when each applicable item has evidence:

- [ ] Runtime and production configuration requirements passed.
- [ ] MySQL migrations, constraints, indexes, transactions and concurrency tests passed.
- [ ] Queue workers, failed-job handling, retries and monitoring passed.
- [ ] Scheduler heartbeat passed and no unapproved destructive cleanup is active.
- [ ] Storage permissions, encryption, capacity, logging and restore checks passed.
- [ ] In-app notification types, recipients, safe payloads, read state, dismissal and
      retention checks passed.
- [ ] Email and push are either explicitly out of scope for this release or separately
      approved and tested; no unconfigured transport is enabled.
- [ ] Logging, alerting, health checks and incident contacts passed.
- [ ] Backup, restore and disaster-recovery evidence passed.
- [ ] Security review passed, including tenant isolation, role scope and production
      preview blocking.
- [ ] Accessibility review passed with recorded assistive-technology evidence or an
      explicitly approved exception.
- [ ] Real-device mobile review passed with recorded browsers, devices and retests.
- [ ] Performance review passed against recorded targets and production-like data.
- [ ] Deployment and rollback rehearsal passed.
- [ ] Customer-facing smoke checks passed after deployment.
- [ ] The SiteApp integration contract is approved and its tests pass, or the release is
      explicitly recorded as a portal-only release that does not claim full Version 1
      integration readiness.
- [ ] Open product decisions affecting the release have owners and dates; no unresolved
      decision is silently implemented as a production rule.

### Go/no-go record

- **Release identifier:**
- **Environment:**
- **Release owner:**
- **Deployment date/time:**
- **Evidence location:**
- **Approved exceptions:**
- **Rollback owner:**
- **Decision:** Go / No-go / Portal-only exception
- **Sign-off:**

## 16. Explicit exclusions

This contract does not define or approve:

- SiteApp API endpoints, events, database access, status mappings or operational writes;
- email audience, consent or delivery requirements beyond the future notification mapping;
- push devices, tokens, preferences or delivery behaviour;
- a long-term notification retention duration;
- completion, supersession or cancellation of approved call-offs;
- MFA, SSO, native apps, reporting or unrelated SiteApp functionality.

Those items require separate product, security or integration decisions before they are
enabled in production.
