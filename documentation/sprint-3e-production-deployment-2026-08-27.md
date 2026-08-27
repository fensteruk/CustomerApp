# Sprint 3E Production Deployment — 27 August 2026

## Overall result

**SUCCESS.** The approved Sprint 3E release was fast-forwarded to `main`, pushed once,
deployed once by Forge, and activated successfully. Production remained healthy. No
rollback or database restoration was required.

## Release identity

| Item | Value |
|---|---|
| Main before | `20d5f123906b7c88f1264f7df5ff90be02c15ec2` |
| Approved release branch | `release/sprint-3e-production-2026-08-27` |
| Approved release SHA | `9111d76ff05d702d68afd884ee8e42bc8e50c8e3` |
| Application integration commit | `4ea5f5511660be66459cea20ab6a1acafbff3784` |
| Main after | `9111d76ff05d702d68afd884ee8e42bc8e50c8e3` |
| Forge deployment | `76326195` |
| Active release | `/home/forge/fenstercustomer.on-forge.com/releases/76326195` |

The final remote preflight confirmed that `origin/main` and the approved release ref had
not moved. The merge was a clean fast-forward. The pushed delta remained exactly 53
files, 3,051 insertions and 70 deletions. It contained no migration delta, workflow
delta, Sprint 3F work, credentials, backup files or local environment files.

## Backup

A fresh logical database backup was created outside the web root before the push:

- path: `/home/forge/backups/customerapp/customerapp-pre-sprint3e-2026-08-27-081617Z.sql.gz`;
- SHA-256: `6304278518c92d59cf1a580063d17a1e74b55b63c227db6f87aaba67f464e126`;
- gzip integrity: passed;
- checksum sidecar verification: passed;
- backup and sidecar permissions: `0600`;
- free disk space before deployment: 17 GB.

The backup was not restored or otherwise used because deployment succeeded.

## Deployment result

Forge deployment `76326195` completed successfully in 29 seconds. Its log records:

1. zero-downtime release creation;
2. locked production Composer installation;
3. npm installation;
4. Vite 8.1.4 production build;
5. Laravel optimisation;
6. storage-link verification;
7. `artisan migrate --force` reporting `Nothing to migrate`;
8. release activation;
9. old-release cleanup;
10. deployment completion.

SSH verification after activation confirmed that the current symlink targets release
`76326195` and its Git HEAD is the exact pushed main SHA.

## Migration and repair preservation

Pre-deploy and post-deploy `migrate:status` both reported all 11 migrations as `Ran`.
Sprint 3E added no migration and Forge reported `Nothing to migrate`.

The four protected production migration blobs remained identical to the pre-release
main baseline. No manual DDL, migration-ledger change, rollback, seeder, database restore
or production test was run. The existing production migration repair therefore remains
intact.

## Runtime and public health

- `APP_ENV`: `production`;
- `APP_DEBUG`: `false`;
- maintenance mode: off;
- active Laravel version: 13.20.0;
- active PHP version: 8.5.9;
- HTTPS login: 200;
- all discovered application CSS and JavaScript assets: 200;
- development role-preview URL: 404 in production;
- no missing Vite assets or public 500 response observed.

## Authenticated smoke and Sprint 3E UI

No designated authenticated production session was available. The protected dashboard
route redirected to the sign-in page, so no account was created and no production
call-off, date decision, withdrawal or other workflow mutation was attempted.

The authenticated Sprint 3E request-detail UI was therefore **not completed as a live
production smoke test**. Its exact release snapshot remains covered by the approved local,
MySQL, concurrency and UI test evidence.

## Logs and queue

The shared Laravel log contained no new entries after the deployment start time. The
post-deploy review found zero new warning/error, SQL, missing class/table/column, route,
Vite, notification or queue exception entries.

Production continues to use the `sync` queue connection and Forge has no persistent
background process configured. The standard deployment queue-restart step completed; no
queue infrastructure redesign was included in this release.

## Access change

A dedicated `JoshO CustomerApp deployment` SSH public key was installed on the CustomerApp
Forge server after explicit approval. Its private key remains local and was not exposed.

## Remaining risks

- No authenticated production workflow or Sprint 3E protected-screen smoke test was
  available during deployment.
- Production continues to use synchronous queue execution with no persistent worker.
- The Forge npm installation reported two dependency advisories (one moderate and one
  high). The production build succeeded; package upgrades were deliberately excluded from
  this controlled release and require separate review.
- The unrelated local edit to
  `documentation/sprint-3e-date-negotiation-report.md` remains uncommitted and was not
  included, overwritten or discarded.

## Production changes

Production now serves the approved Sprint 3E requested-date agreement and alternative-date
negotiation release, including the associated request-detail UI, authorisation, stale-action
protection, source precedence, notification/history behaviour and regression coverage.
There was no schema change, workflow-file change, SiteApp write-back or Sprint 3F content.

## Recommendation

Treat Sprint 3E as deployed and healthy. Next, arrange a designated read-only authenticated
production smoke session and separately review the npm advisories and future queue
infrastructure. Do not begin or deploy Sprint 3F automatically.
