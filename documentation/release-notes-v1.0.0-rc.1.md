# Fenster Customer Portal v1.0.0-rc.1

> **Historical/superseded release-preparation record (7 August 2026).** This document describes
> an earlier three-service, pre-amendment candidate and is not the current RC1 release contract.
> Use `brief.md`, `DECISIONS.md`, `documentation/customer-release02-rc1-build-2026-09-09.md`
> and `documentation/customer-release03a-rc1-recovery-strategy-2026-09-09.md` for the current
> reduced RC1 scope, QA evidence and recovery policy. The historical content below is retained
> unchanged for audit traceability.

**Status:** Release-candidate preparation only — not approved for production deployment

**Assessment date:** 7 August 2026

**Branch:** `main`

## Release purpose

This release candidate packages the locally verified Customer Portal foundation and
customer-facing call-off workflow for final infrastructure and production-environment
review. It is intended to provide one auditable candidate scope for MySQL validation,
deployment rehearsal, accessibility/device review, backup testing and operational
sign-off.

No deployment, tag, commit or SiteApp integration is included in this preparation task.

## Included functionality

The candidate worktree contains the completed and QA-verified Sprint 1A through Sprint 1E
portal scope, plus the locally verifiable Sprint 1G hardening work:

- secure login, password reset, session handling, rate limiting and active-account checks;
- four portal-specific roles: Site Manager, Assistant Site Manager, Finishing Foreman and
  Fenster Office Staff;
- customer organisation boundaries, sites, assigned-site scope and active-site context;
- production-blocked development role preview and role-based dashboard routing;
- projected plots and the independent Cavity Closers, Windows and CML call-off domain;
- one-batch submission with one individual request per selected projected plot;
- centralised eligibility, duplicate prevention and UUID-based external references;
- Office Staff review, approval and rejection with customer-visible response and private
  internal reason separation;
- pending withdrawal, customer-facing Trash, seven-day visibility, restoration and
  five-second server-authoritative Undo;
- immutable status history, atomic bulk lifecycle operations and traceable rejection
  resubmission;
- in-app `call_off_submitted`, `call_off_approved` and `call_off_rejected` notifications;
- recipient-scoped notification centre, unread/read state, mark-all-read, dismissal and
  safe-open authorisation;
- transaction/locking review, safe notification failure logging and the corrected secure
  access migration rollback;
- targeted Composer dependency hardening without unrelated package upgrades.

Amendments, email, push, QR scanning, SiteApp integration, completion/supersession and
operational SiteApp workflow remain outside this candidate.

## Security and QA highlights

- Customer organisation and assigned-site checks are enforced server-side.
- Site roles cannot approve or reject; only assigned Fenster Office Staff can decide.
- Development preview is unavailable outside local/testing environments and is not an
  authorisation mechanism.
- Public registration is not routed.
- External links use public UUIDs rather than integer database identifiers.
- Notification payloads exclude `internal_reason`, operation snapshots, conflict keys and
  internal numeric identifiers.
- Notification safe-open rechecks current active account, role, organisation and site
  assignment; a notification UUID is not an access grant.
- Approved call-offs remain protected duplicate blockers and cannot be deleted, trashed or
  cancelled by site users.
- Failed, stale or unauthorised domain transitions do not create notifications.
- Notification listener failures do not roll back a successfully persisted call-off.
- Secure-access migration fresh, rollback and reapply paths have been locally corrected
  and verified.

## Verification evidence

The following commands were run against the local SQLite configuration only:

| Check | Result |
|---|---|
| `php artisan migrate:fresh --seed` | Passed |
| `php artisan migrate:rollback` | Passed |
| `php artisan migrate` | Passed |
| `php artisan db:seed` | Passed |
| `php artisan test` | 101 tests, 521 assertions passed |
| `vendor\\bin\\pint --test` | Passed |
| `npm run build` | Passed after the known Windows `spawn EPERM` sandbox rerun with external process access |
| `npm ci --ignore-scripts --dry-run` | Passed; lockfile resolves as up to date |
| `composer validate` | Passed |
| `composer audit` | Passed; no advisories |
| `npm audit` | 10 moderate PostCSS-chain advisories; no fix available |
| `git diff --check` | Passed |
| `php artisan migrate:status` | All local migrations ran |
| `php artisan schedule:list` | No scheduled tasks defined |

The build, migration and test evidence does not constitute MySQL or production
infrastructure evidence.

## Dependency status

`composer.lock` contains the targeted audited updates:

- Guzzle 7.15.3;
- Guzzle promises 2.5.2;
- Guzzle PSR-7 2.13.0;
- League CommonMark 2.9.0;
- Nette Utils 4.1.5.

`composer audit` is clean. `package-lock.json` is consistent with `npm ci --dry-run` and
was not changed during this task. `npm audit` continues to report 10 moderate PostCSS
chain advisories with no available fix. This is documented as a release-candidate risk,
not as production approval; it must be reviewed and explicitly accepted or remediated
before production deployment.

## Release-candidate acceptance checklist

### Passed locally

- [x] Full Pest suite passes: 101 tests and 521 assertions.
- [x] Pint check passes.
- [x] Vite production build passes.
- [x] Composer validation passes.
- [x] Composer audit reports no advisories.
- [x] npm lockfile consistency check passes.
- [x] SQLite fresh migration and seed pass.
- [x] SQLite rollback and reapply pass.
- [x] No `.env`, SQLite database, logs, caches, vendor files or build output is tracked.
- [x] Documentation and worktree contents have been reviewed.
- [x] No deployment, SiteApp integration, commit, tag or production database access was
      performed.

### Pending release gates

- [ ] MySQL clean migration, rollback/reapply, constraints, collation, locking,
      concurrency, restore and notification-recipient verification.
- [ ] Approved production environment values, HTTPS, secure cookies, secrets, cache,
      session, storage and error handling.
- [ ] Queue/worker and failed-job policy, if asynchronous work is approved; current
      notifications remain synchronous.
- [ ] Scheduler heartbeat policy; the current Trash expiry rule requires no destructive
      scheduler.
- [ ] Encrypted backups, restore rehearsal, recovery ownership and RPO/RTO sign-off.
- [ ] Production logging, monitoring, health checks, alerting and named responders.
- [ ] Physical iOS/Android testing, keyboard-only testing and assistive-technology review.
- [ ] Production-like MySQL data-volume, performance and concurrency testing, including a
      decision on bounded dashboard and Trash lists.
- [ ] Deployment and rollback rehearsal with an immutable artifact.
- [ ] Review or formally accept the remaining npm advisories.
- [ ] Approval of the initial SiteApp integration contract before any full Version 1
      integration claim.

## Known limitations

- MySQL is unavailable on this workstation; all database evidence is SQLite-only.
- Local configuration is intentionally non-production: `APP_ENV=local`,
  `APP_DEBUG=true`, SQLite, synchronous queue, file cache and log mail.
- No production infrastructure, monitoring, backup or restore service has been configured.
- No physical-device, keyboard-only or assistive-technology pass has been completed.
- Site dashboard and Trash collections still require production-scale bounded-list and
  performance decisions.
- Long-term notification retention remains unresolved; no unapproved purge should be
  enabled.
- Email, push, QR scanning, amendments and SiteApp integration are not included.

## Worktree and commit plan

The worktree is not clean. It contains the uncommitted Sprint 1A–1G implementation,
documentation, migrations, tests, frontend changes and the audited `composer.lock` update.
No files are staged. Ignored local files include `.env`, `database/database.sqlite`,
`vendor`, `node_modules`, `public/build`, `public/storage`, compiled views and logs.

The implementation is interdependent across migrations, models, actions, routes, views,
tests and documentation. Once the outstanding release gates are closed, the safest
strategy is one reviewed release-candidate commit containing the complete portal scope and
locked dependency update, followed by a separate tag commit or annotated tag process if
the repository policy requires it. Before that commit:

1. Select only intended source, migration, test, documentation and lockfile content.
2. Leave `.env`, database files, logs, caches, build output, dependencies and temporary
   reports untracked/ignored.
3. Run the complete acceptance checklist against the staged tree.
4. Obtain security, product and operations sign-off before creating `v1.0.0-rc.1`.

Splitting the current uncommitted foundation into historical feature commits would require
careful dependency reconstruction and is not safe to infer from the current worktree.

## Production decision

**Not yet safe to commit or approve as a production release candidate.**

The repository is locally verifiable and suitable for the next MySQL and infrastructure
verification stage, but it is not yet production-ready. The remaining gates are
environmental and operational rather than a reason to add product features. Do not deploy
or begin SiteApp integration until the required evidence and contracts are approved.
