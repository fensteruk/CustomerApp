# CustomerApp RC1 Recovery Strategy Report

Date: 9 September 2026

Authority: approved CUSTOMER-RELEASE03A

Branch: `release/customerapp-2026-09-09-rc1`

Frozen executable checkpoint: `ac250a8e9eef4b40591872de9275d802c76e4fba`

## Overall result

**RECOVERY_APPROVED.** RCQ-01 is
`RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`. Dedicated QA passed RC1's forward functional,
security, browser, migration and concurrency gates. The remaining incompatibility is bounded by
a strict maintenance-mode cutover: true snapshot-plus-code rollback before traffic, and compatible
roll-forward only after traffic or Sprint 3F writes.

This approval permits merge-to-main preparation. It does not authorise a main push, Forge change,
production backup, migration or deployment.

## RCQ-01

RC1 adds notification enum value `call_off_amendment_requested`. Old main at
`0873bac79edf578e9f4a9417e3cafae34e8aa925` does not recognise it, while the notification model
casts the persisted type to that enum. Dedicated QA created a genuine synthetic RC1 amendment
notification and reproduced a `ValueError` when reading it with the old enum; the same row loaded
with the RC1 enum.

The schema columns are not the failing component. The forward Sprint 3F migration adds nullable or
defaulted amendment metadata, so RC1 can read pre-Sprint3F records and the populated 11-to-12
migration rehearsal preserved the verified existing request, negotiation, proposal, history and
notification data. The incompatible boundary is data RC1 may persist after amendment activity.

Running the migration's populated `down()` is not a solution: it drops amendment metadata, can
destroy truthful data and does not make old main recognise the new notification value. Deleting or
rewriting notifications/history is also forbidden.

## Maintenance-mode boundary

The approved production sequence is:

1. Verify the current production application/database state.
2. Capture a fresh database backup/snapshot and its proof.
3. Capture the current served SHA, Forge release/deployment and migration ledger.
4. Enter maintenance mode and prevent normal Office/customer workflow traffic.
5. Deploy the exact approved documentation-inclusive RC commit and verify its executable tree is
   identical to frozen checkpoint `ac250a8e9eef4b40591872de9275d802c76e4fba`.
6. Install locked production dependencies/build assets as required; do not upgrade packages.
7. Apply the reviewed forward migration.
8. Rebuild approved application caches and verify the runtime uses the new release.
9. Run the controlled smoke checks below while maintenance/testing access remains controlled.
10. Reopen traffic only after the release owner accepts all evidence.

Until step 10, no normal customer or Office workflow traffic is permitted. Prefer read-only smoke.
If a fictional amendment write is intentionally used, the deployment has crossed the post-write
compatibility boundary even if maintenance mode is still active.

## Pre-traffic recovery

If deployment or smoke fails before reopening and before any Sprint 3F business write:

1. Leave maintenance mode active and stop new work from reaching the application.
2. Preserve deployment/migration/log/worker evidence.
3. Restore the fresh pre-deployment database snapshot.
4. Restore the previously verified production application release matching that snapshot.
5. Rebuild compatible configuration, route and view caches; ensure workers use the restored code.
6. Verify the migration ledger and application database point match the captured pre-release state.
7. Smoke application boot, authentication, tenant/site containment, Review Requests, dashboard,
   notifications and absence of enum/500/migration errors.
8. Reopen only after the recovery owner accepts the restored state.

This is the approved true rollback window. `php artisan migrate:rollback` is not its primary
mechanism.

## Post-traffic recovery

After maintenance mode is removed, or immediately after any Sprint 3F write, assume the database
may contain amendment metadata and new notification values. From that point:

- do not deploy old main over the newer database;
- keep or restore RC-generation-compatible code;
- disable the affected entry point only if an already-approved safe operational control exists;
- diagnose from the exact deployed release;
- branch the correction from that exact SHA or a descendant;
- run focused, regression, disposable-MySQL and build/security checks appropriate to the defect;
- deploy the tested compatible successor and monitor it.

No new feature flag is introduced by this documentation task.

## Database backup and restore

Before a production deployment, record fresh evidence rather than relying on an older recovery
report:

- backup/snapshot identifier, completion result and timestamp;
- the source production database/environment, without credentials;
- current migration ledger;
- current served SHA, Forge release path and deployment ID;
- restore owner and either a rehearsed restore result or an already-established verified restore
  mechanism suitable for this database.

Restoring the pre-deployment database after reopening would discard legitimate later logins,
notifications, requests, decisions or amendment writes. It is therefore not routine post-traffic
recovery and may be considered only as a separately authorised major-incident/business decision
that explicitly accepts and reconciles the data loss.

## Forge rollback behaviour

Forge zero-downtime deployment builds versioned release directories and activates a successful
release through the site's `current` symlink. The inspected production site is configured for
zero-downtime deployments and retains four releases. This supports switching code releases, but
the application MySQL database is external/shared state and is not restored by changing the
release symlink. Database backup restore is a separate operation.

Reference: [Laravel Forge deployment documentation](https://forge.laravel.com/docs/sites/deployments)
and [Laravel Forge API documentation](https://forge.laravel.com/api-documentation).

Therefore a Forge previous-release/code switch is not a complete RC1 rollback after Sprint 3F
writes. Previous code is permitted only when the database has also been restored to its matching
pre-deployment snapshot within the pre-traffic window.

Read-only inspection on 9 September 2026 showed the latest Forge deployment entry as deployment
`76359579` for `0873bac79edf578e9f4a9417e3cafae34e8aa925`, with zero-downtime enabled, release
retention four and push-to-deploy enabled. This is deployment-record/settings evidence, not a
terminal-level `/current`-symlink attestation; production must be freshly verified during the
authorised deployment-preparation task.

Because push-to-deploy is enabled, do not treat a main push as a harmless Git-only step. Any push
that Forge watches must occur only inside the separately approved maintenance/backup deployment
run, or after a separately authorised configuration change prevents automatic deployment.

## Compatible hotfix base

Any emergency post-write correction must start from the exact deployed RC SHA or a descendant.
Do not base it on `0873bac79edf578e9f4a9417e3cafae34e8aa925` unless the database has first been
fully restored to the matching pre-deployment snapshot within the approved rollback window.

## Smoke before reopening

Under controlled maintenance/testing access, verify at minimum:

- application boot, HTTPS and assets;
- Office login and external Site User login with approved safe accounts;
- Review Requests, assigned-site dashboard/plot access and Date Agreed filtering;
- Sprint 3F amendment page/action availability without unnecessary mutation;
- notifications can be read;
- no enum/read failure, migration error or HTTP 500.

Smoke is read-only where sufficient. Any intentional fictional amendment write must be explicitly
recorded because it ends the old-main-compatible rollback window.

## Post-release monitoring

After reopening, watch the first Sprint 3F activity closely: Laravel/HTTP errors, amendment
requests, amendment notifications, Office decisions, queue/worker failures and 500s. Confirm
notification reads and links remain healthy without exposing customer data. A post-write defect
uses the roll-forward procedure; it does not trigger reflexive rollback to old main.

## RCQ-02 and RCQ-03

- **RCQ-02 / P3 — resolved as documentation labelling.** The August
  `release-notes-v1.0.0-rc.1.md` and the Sprint 1E notification-domain contract now carry explicit
  historical/superseded notices pointing to the current brief, decision ledger, RC build and this
  recovery record. Their historical bodies remain unchanged for audit.
- **RCQ-03 / P3 — accepted non-blocking follow-up.** Dedicated QA found minor notification copy:
  “Fenster agreed” can be imprecise when a Site User accepted an alternative, and “original
  submitter” can be imprecise where amendment delivery uses the actual amendment requester.
  Recipient selection, actor history, authorisation and persistence passed. No functional or copy
  code is changed in this recovery task; resolve through a separately reviewed, tested wording
  correction rather than mutating the frozen executable candidate.

## Candidate integrity

The executable RC remains frozen at
`ac250a8e9eef4b40591872de9275d802c76e4fba`. CUSTOMER-RELEASE03A modifies documentation only.
The exact documentation-inclusive descendant SHA is recorded in the release handoff/task report;
before merge preparation, compare the executable tree against the frozen checkpoint and require
no differences in application, routes, configuration, migrations, resources, dependencies,
build inputs, tests or workflows.

## Remaining production blockers

RCQ-01 is no longer an unresolved release blocker. The remaining work is operational release
preparation, not application feature remediation:

- obtain separate explicit approval before any main push or deployment;
- account for enabled push-to-deploy so a main push cannot race ahead of backup and maintenance;
- freshly verify the actual served SHA/release, database identity and migration ledger;
- create and prove the fresh pre-deployment recovery point;
- run the maintenance-mode deployment/smoke/reopen sequence under named ownership;
- monitor first live writes under the roll-forward policy.

## Recommendation

The documentation-inclusive RC may proceed to merge-to-main preparation. It must not be deployed
until the fresh production evidence and recovery point are captured under a separately authorised
release task.

CustomerApp RC1 recovery approved — candidate ready for merge-to-main preparation
