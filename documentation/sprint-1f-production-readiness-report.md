# Sprint 1F Production Readiness Report

**Assessment date:** 7 August 2026  
**Scope:** verification and safe local/infrastructure checks only  
**Deployment:** not performed  
**SiteApp integration:** not started

## Overall status

**Not yet safe to deploy.**

Estimated readiness is **approximately 30%**. This is an evidence-weighted planning
estimate, not a formal service-level or release score. The local Laravel foundation is
healthy, but several production-critical controls remain untested or unavailable.

## Verified locally

- PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.0 and Herd 1.29.0 are available.
- Laravel 13.20.0, Livewire 4.3.3, Filament 5.6.8, Vite 8.1.4, Pest 4.7.5 and Pint 1.29.3 match the locked project stack.
- `php artisan optimize:clear`, route registration, configuration inspection and local migration status completed.
- Full Pest suite passed: **101 tests, 521 assertions**.
- Pint check and `git diff --check` passed.
- Vite production build passed after rerunning with external child-process access.
- Composer validation passed.
- Fresh SQLite migrations, seeding, rollback and reapply passed after correcting the secure-access rollback indexes.
- Development role preview is guarded to `local`/`testing`; authenticated routes use active-account and assigned-site middleware.
- Login rate limiting, password reset, session invalidation, CSRF forms and public-registration absence are present in the inspected code.
- Notification centre uses recipient-scoped queries, pagination, unread counts, idempotent event keys and customer-safe payload fields; current notification tests passed.
- Scheduled task inspection reports no scheduled tasks. Seven-day Trash visibility is query-derived and no destructive expiry job is enabled.
- `public/storage` is a local junction to private application storage; Version 1 has no customer-uploaded documents.
- Simulated responsive pass completed at 390×844, 768×1024 and 1440×900 for role preview, site selection, dashboard, New Call Off, Office Staff review and notification centre.

## Remaining blockers

1. No MySQL client, service or listener is available locally. MySQL migration, collation,
   constraint, locking, concurrency, rollback and restore checks are therefore unverified.
2. Production environment has not been exercised. Local configuration is `APP_ENV=local`,
   `APP_DEBUG=true`, SQLite, synchronous queue, file cache and log mail; production must
   explicitly use approved values and HTTPS/secure-cookie settings.
3. Queue workers are not currently required because notification listeners run synchronously.
   A production queue design, worker supervisor, retries, failed-job store and monitoring
   remain deployment work if asynchronous jobs are introduced.
4. No scheduler is currently required, but scheduler heartbeat/alerting is not configured
   or tested for production.
5. Backups, restore, disaster-recovery rehearsal, log aggregation, alerting, health-check
   monitoring and deployment/rollback rehearsal are not evidenced.
6. Physical iOS/Android testing, keyboard-only testing and assistive-technology testing
   are outstanding; browser viewport checks were simulated only.
7. Production-like performance and concurrency data-volume testing is outstanding. The
   site dashboard request list is currently unbounded (`get()`), so capacity testing and
   an agreed pagination/retention approach are required before go-live.
8. Dependency audits report unresolved findings. Composer reports Guzzle and
   league/commonmark advisories; npm reports 10 moderate PostCSS-chain advisories with no
   available fix. Locked dependencies were not changed.
9. The worktree contains the active Sprint 1 implementation and is not release-clean;
   no commit, tag or deployment artifact was created.

## MySQL status and schema notes

MySQL is **blocked/unavailable** locally. The migrations use JSON columns, nullable unique
`active_conflict_key`, UUID columns/unique indexes, restrictive foreign keys, a nullable
self-reference for resubmission and a nullable self-reference for reversed operations.
These need MySQL verification for collation/index behaviour, strict SQL mode, transaction
locking and concurrent duplicate prevention. SQLite rollback/reapply is now verified.

## Queue requirements

Current `.env` uses `QUEUE_CONNECTION=sync`; notification listeners are synchronous and
catch/log failures without rolling back a successful call-off transition. No queue table
work is required for current behaviour. If approved asynchronous work is enabled, the
production worker command should be documented and supervised as:

```text
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Workers must be restarted after releases, with failed-job retention and queue-age/failure
monitoring. This has not been provisioned or tested.

## Scheduler requirements

`php artisan schedule:list` reports no scheduled tasks. Trash expiry is derived from
`trash_expires_at` in queries, and no destructive expiry mutation is required. Production
still needs a documented scheduler policy/heartbeat if future retention or cleanup jobs
are approved; no such job should be enabled implicitly.

## Security findings

- Positive: preview routes are environment guarded; preview users are explicitly marked;
  active-account and assigned-site checks are server-side; public UUID route binding and
  recipient/site/organisation checks are present; `internal_reason` is excluded from
  customer-facing notification payloads; login attempts are rate limited.
- Not production verified: `APP_DEBUG=false`, HTTPS enforcement, secure cookie settings,
  trusted proxies, security headers, CORS, secret-store injection/rotation, production
  error pages and operator access controls.
- No secret values were included in this report. `.env` is not tracked by Git; `.env.example`
  contains placeholders only.

## Performance findings

Review queues and notification centre are paginated; notification unread counts use a
scoped count and notification list queries have supporting recipient/read/dismissal
indexes. The site dashboard loads all visible call-offs with `get()` and performs several
summary counts. This is acceptable for current preview data but is not production-scale
evidence; add representative MySQL load/concurrency testing and agree bounded list
behaviour before release.

## Accessibility and device findings

The viewport pass showed labelled controls, headings, landmarks, skip links, textual
status/empty states and mobile-sized controls at the tested dimensions. It was simulated
browser testing only. No physical-device, keyboard-only or screen-reader claim is made.

## Backup and monitoring status

No production backup, restore, encryption, retention, RPO/RTO, incident-response or
monitoring configuration was verified. Minimum required evidence is an encrypted,
access-controlled database backup and restore rehearsal, environment-secret recovery
process, log/failed-job collection, login/dashboard/notification health checks and named
responders.

## Dependency audit findings

- `composer validate`: passed.
- `composer audit`: failed with 11 advisories affecting Guzzle and league/commonmark.
- `npm audit`: failed with 10 moderate PostCSS-chain advisories; npm reports no available
  fix. No automatic upgrades were run.

## Git and release readiness

Branch is `main` tracking `origin/main`. The worktree has the existing Sprint 1 changes and
the new migration rollback correction/report; it is not clean enough for release. No
commit, reset, clean, tag or deployment was performed.

## Deployment-checklist reconciliation

| Checklist area | Classification | Evidence/next action |
|---|---|---|
| Runtime and locked dependencies | Ready locally; not production-tested | Versions and build verified locally; production runtime still needs evidence. |
| Production environment/configuration | Blocked | Must verify production env, HTTPS, secrets, cookies, caches and health checks. |
| Tenant/site security boundaries | Ready locally; not production-tested | Automated tests passed; production-like verification remains. |
| MySQL validation and concurrency | Blocked | No local MySQL; run clean/rollback/restore/concurrency rehearsal on approved MySQL. |
| Queue and failed-job operations | Ready for current sync scope; not production-provisioned | No async listener required today; document/provision worker only when approved. |
| Scheduler | Not applicable to current expiry rule; future scope for cleanup | No scheduled tasks defined; add only with an approved retention rule. |
| Storage | Ready locally; not production-tested | Storage link exists; verify production permissions, private storage and backups. |
| In-app notifications | Ready locally; MySQL/retention not verified | Tests pass; MySQL evidence and retention decision remain. |
| Logging and monitoring | Blocked | Local single-file logging exists; production collection, alerts and health checks absent. |
| Backup and recovery | Blocked | No configured or rehearsed production backup/restore evidence. |
| Accessibility | Ready for simulated pass; blocked for release evidence | Physical keyboard/assistive-technology review is outstanding. |
| Mobile devices | Blocked | Only simulated viewport pass completed; real/approved devices required. |
| Performance | Blocked | Production-like MySQL data volume, targets and concurrency tests absent. |
| Deployment/rollback sequence | Blocked | No infrastructure, immutable artifact, worker restart or rollback rehearsal. |
| SiteApp integration | Future scope / intentionally excluded | No contract or integration work started, per task instruction. |

## Recommended order to close blockers

1. Approve the production environment and MySQL version, then run migration, rollback,
   constraint, transaction, concurrency and restore rehearsals.
2. Resolve or formally accept the Composer/npm audit findings without changing locked
   versions during this release.
3. Define production queue/cache/session/storage/logging configuration and monitoring;
   keep current notifications synchronous unless asynchronous delivery is approved.
4. Complete named accessibility, physical-device and production-like performance tests.
5. Reconcile and sign the backup/restore, deployment and rollback checklists.
6. Prepare a portal-only release artifact only after the above evidence is attached. Do not
   begin SiteApp integration until its separate contract is approved.

**Decision: Not yet safe to deploy.**

## Sprint 1G hardening addendum - 7 August 2026

Sprint 1G completed the locally verifiable hardening actions without adding product
features or starting SiteApp integration. The secure-access migration rollback was fixed;
transaction and row-lock coverage was reviewed; notification failure logging was reduced
to safe exception class/code context; and the audited Composer dependency path was updated
to Guzzle 7.15.3, CommonMark 2.9.0, Guzzle promises 2.5.2, Guzzle PSR-7 2.13.0 and
nette/utils 4.1.5. `composer audit` is now clean.

MySQL remains unavailable locally, the npm PostCSS advisories remain unresolved with no
available fix, production infrastructure and backups are not configured, and physical
device/accessibility/performance evidence remains outstanding. See
`documentation/sprint-1g-production-hardening-report.md` for the complete Sprint 1G
report.
