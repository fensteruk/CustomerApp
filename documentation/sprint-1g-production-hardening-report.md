# Sprint 1G Production Hardening Report

**Assessment date:** 7 August 2026  
**Scope:** hardening and verification only  
**Deployment:** not performed  
**SiteApp integration:** not started

## Overall result

The locally verifiable hardening work is complete, but the portal is **not yet safe to
deploy**. The evidence-weighted readiness estimate is **approximately 45%**. This is a
planning estimate, not a release approval score.

## MySQL result

No MySQL or MariaDB client, service, listener or approved test database is available on
this workstation. MySQL verification is therefore blocked and must be completed in the
deployment environment using a dedicated temporary Customer Portal database.

The deployment rehearsal must cover empty migrations, seeders, rollback/reapply, foreign
keys, nullable unique `active_conflict_key`, UUID indexes, JSON snapshots, self-referencing
foreign keys, timestamps, pagination/filter queries, duplicate submitted/approved calls,
rejection and withdrawal conflict-key clearing, stale approve/withdraw protection, bulk
atomicity, Undo, history sequencing and notification recipient writes.

SQLite fresh migration, seeding, rollback and reapply now pass.

## Concurrency result

Code review confirms transactions and row locks around submission, approval, rejection,
withdrawal, Trash, restore, Undo and rejected resubmission. History sequence allocation
uses a locked aggregate query. The unique active conflict key remains the final duplicate
guard. These controls are not MySQL evidence; lock timing, deadlocks and concurrent
transaction outcomes remain untested until MySQL is available.

## Dependency advisory assessment

The two Composer-advisory packages were updated through a targeted, architecture-neutral
dependency update:

- `guzzlehttp/guzzle` 7.14.2 → 7.15.3
- `league/commonmark` 2.8.3 → 2.9.0
- `guzzlehttp/promises` 2.5.1 → 2.5.2
- `guzzlehttp/psr7` 2.12.5 → 2.13.0
- `nette/utils` 4.1.4 → 4.1.5

`composer audit` now reports no advisories. `npm audit` still reports 10 moderate
PostCSS-chain advisories with no available fix. No forced or unrelated upgrades were run.

## Queue requirements

Notifications remain synchronous. Domain state transitions are not queued, and no queue
configuration was changed. If asynchronous work is approved later, use the simplest
infrastructure-supported connection and supervise:

```text
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

The production worker, failed-job retention, restart-on-release policy and queue health
alerts remain infrastructure tasks.

## Scheduler requirements

`php artisan schedule:list` reports no scheduled tasks. Trash visibility is derived from
`trash_expires_at`; no destructive expiry mutation is required. No scheduler job was
invented. A future cleanup or retention job requires a separate approved rule, idempotent
implementation, test and heartbeat/alerting.

## Performance findings

- Review Requests and notification centre are paginated.
- Notification queries are recipient-scoped and unread counts use a database count with
  supporting indexes.
- Site selection uses assignment scoping and projected-plot counts.
- Site dashboard call-off cards and Trash currently load unbounded collections. This is a
  production-scale risk requiring MySQL data-volume testing and an approved pagination
  decision before release; no speculative cache or denormalisation was added.

## Security findings

- Preview routes remain unavailable outside local/testing and preview users are excluded
  from real notification creation and access.
- Active account, portal role, organisation, assigned-site and UUID checks remain
  server-side.
- Customer-facing output excludes `internal_reason`, operation snapshots, conflict keys
  and internal numeric IDs.
- Notification failure logs now record only safe exception class/code context rather than
  serializing exception objects or messages.
- Login rate limiting, password reset, session invalidation and CSRF protections remain
  enabled.
- Production `APP_ENV`, `APP_DEBUG=false`, HTTPS, secure cookies, trusted proxies, security
  headers, CORS, secret-store injection/rotation and production error handling remain to
  be configured and verified.
- Git-tracked secret scan found no obvious key material. Secret values were not output.

## Session, cache, storage and production configuration

Local defaults are SQLite, database sessions, file cache, synchronous queue, local
filesystem and log mail. Production must provide non-secret values/categories for:

- `APP_ENV=production`, `APP_DEBUG=false`, approved HTTPS `APP_URL`, secret `APP_KEY`;
- approved MySQL connection and least-privilege credentials;
- durable session and cache stores;
- queue connection only if asynchronous work is approved;
- approved filesystem/private storage and permissions;
- logging channel/level, mail transport policy and trusted proxy settings where required.

No production `.env` was created or committed. The local storage junction is present and
Version 1 has no customer uploads.

## Logging and monitoring status

Application logging exists locally and notification failures are safe to log. Production
collection, correlation IDs, authentication/security-event visibility, queue failure
visibility, health checks, alert thresholds, log rotation and named responders remain
unconfigured/unverified.

## Backup and recovery contract

Before first deployment, the release owner must require an encrypted, access-controlled
MySQL backup; a separate recovery path for environment secrets and `APP_KEY`; release
artifact/Git revision capture; an agreed minimum daily frequency and retention; and a
restore test that verifies call-off history, conflict keys, Trash timestamps and
notifications. No backups are claimed as configured.

## Accessibility and device findings

The available browser pass covered narrow phone (390×844), tablet (768×1024) and desktop
(1440×900) layouts for login-adjacent role preview, site selection, dashboard, New Call Off,
Office Staff review and notification centre. It was simulated viewport testing. No
physical-device, keyboard-only or assistive-technology verification is claimed.

## Git and release status

The branch is `main` tracking `origin/main`. The worktree contains the existing Sprint 1A-1E
implementation plus Sprint 1F/1G documentation, the rollback correction, logging change
and dependency lockfile updates. It is not release-clean, and no files were staged or
committed. A release candidate such as `v1.0.0-rc.1` should wait until MySQL,
infrastructure, accessibility/device, performance and backup evidence is attached.

## Tests and command results

- `php artisan test`: **101 passed, 521 assertions**.
- `vendor\bin\pint --test`: passed.
- `npm run build`: passed.
- `composer validate`: passed.
- `composer audit`: passed with no advisories after targeted updates.
- `npm audit`: 10 moderate advisories remain; no fix available.
- `git diff --check`: passed.
- SQLite migration/seed/rollback/reapply rehearsal: all command exit codes 0.
- `php artisan schedule:list`: no scheduled tasks.
- `php artisan migrate:status`: all local migrations ran.

## Files changed

- `app/Listeners/CallOffNotificationListener.php`
- `composer.lock`
- `database/migrations/2026_08_05_000001_create_secure_access_domain_tables.php`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/sprint-1f-production-readiness-report.md`
- `documentation/sprint-1g-production-hardening-report.md`

## Remaining blockers

1. MySQL migration, locking, concurrency and restore evidence.
2. Production environment, HTTPS, storage, session/cache, queue, logging and monitoring
   configuration and verification.
3. Backup/recovery rehearsal and named operational ownership.
4. Production-scale performance testing and a bounded dashboard/Trash list decision.
5. Physical-device, keyboard-only and assistive-technology QA.
6. Review or formally accept the remaining npm advisories.

## Deployment checklist reconciliation

| Checklist area | Classification | Evidence |
|---|---|---|
| Runtime and locked dependencies | Ready locally; requires production environment | Locked versions, build and Composer validation passed. |
| Production configuration and HTTPS | Blocked | Local-only configuration is active; production values and transport are not verified. |
| Tenant/site security | Ready locally; requires production environment | Automated isolation and role tests passed; production rehearsal remains. |
| MySQL schema, constraints and concurrency | Blocked | No local MySQL/MariaDB instance. |
| Queue and failed jobs | Ready for synchronous scope; requires production environment | No current queued notification work; worker/monitoring setup remains future infrastructure. |
| Scheduler | Not applicable to current Trash rule | No scheduled tasks; expiry is query-derived. |
| Storage and permissions | Ready locally; requires production environment | Local storage link exists; production permissions, encryption and restore are unverified. |
| In-app notifications | Ready locally; MySQL/retention evidence outstanding | Tests, scoping, idempotency and safe payload checks pass. |
| Logging and monitoring | Blocked | Safe local logging exists; production collection and alerts are not configured. |
| Backup and recovery | Blocked | No configured or rehearsed production backup/restore evidence. |
| Accessibility and mobile devices | Blocked for release evidence | Simulated viewports only; no physical, keyboard-only or assistive-technology pass. |
| Performance | Blocked | No production-like MySQL volume/concurrency measurements; dashboard/Trash bounds remain. |
| Deployment and rollback rehearsal | Blocked | No infrastructure rehearsal or release artifact sign-off. |
| SiteApp integration | Future scope | Explicitly excluded from Sprint 1G. |

Not yet safe to deploy
