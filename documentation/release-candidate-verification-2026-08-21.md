# Customer Portal Release Candidate Verification

_21 August 2026_

## Classification

**A — development checkpoint / release candidate suitable for a non-deploying Git push.**
It is not production-approved. Sprint 3D's dedicated local QA gate has passed, but
production database recovery/reconciliation and the remaining production release gates are
separate work.

## Repository and remote baseline

- Starting branch and HEAD: `main` at `2fbcb5b9aced545ac377ce632eda903babd123c5`
  (`Sprint 3A`).
- Configured remote: `origin` → `https://github.com/fensteruk/CustomerApp.git`.
- After `git fetch --all --prune`, `origin/main` remained at the same SHA; the local
  branch was neither behind nor diverged.
- No merge, rebase or staged operation was in progress.
- The Forge verification evidence says pushes to `main` perform a deployment. This
  checkpoint therefore uses `release-candidate/sprint-3d`, not `main`.

## Worktree review

The reviewed changes are the intended Sprint 3A/3B source/domain foundation, Sprint 3C
plot-centric overview and its QA correction, Sprint 3D multi-plot/multi-service call-off
implementation, associated tests, migrations and documentation. The only new migration is
`2026_08_21_000008_add_multi_call_off_request_fields.php`; it follows migrations 000001–
000007 without a duplicate timestamp or reversed dependency.

Ignored local-only files were deliberately excluded: `.env`, the local SQLite database,
`node_modules`, `vendor`, generated build/cache/storage files, the local presentation and
the password file. No generated build artefact is versioned by repository convention.

## Sprint 3D QA correction checkpoint

- Original remote checkpoint: `10be06f6d910f266819d707e627459ee356ad550`.
- QA correction commit: `34531d2` (`fix: complete Sprint 3D QA corrections`).
- The reviewed changes preserve submitted plot/service order through signed-review
  revalidation and emit Awaiting Fenster notifications with each request's own service and
  requested date.
- The commit includes the dedicated 3D QA coverage and QA/status documentation only; it
  does not introduce Sprint 3E work, production configuration, credentials or generated
  artefacts.

## Verification

- PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0 and Git 2.55.0.windows.3 available.
- `composer validate --no-check-publish` — passed.
- `composer audit --no-interaction` — no advisories.
- `npm ci --offline --no-audit --no-fund` — passed; 170 packages installed from the lockfile.
- `npm audit --omit=dev --json` — no production-dependency advisories.
- `npm audit --json` — 2 high and 9 moderate development-tooling advisories, with no
  automated fix available; no dependency was changed in this checkpoint.
- Local Laravel optimisation checks (`config:cache`, `route:cache`, `view:cache`) — passed,
  then cleared to restore normal local development behaviour.
- `php artisan migrate:fresh --seed` — passed only after confirming `APP_ENV=local`, SQLite
  and the local `database/database.sqlite` target.
- `scripts/verify-sqlite-migrations.ps1` — passed: disposable SQLite migrate, full rollback
  and reapply, including migration 000008.
- Sprint 3A — 17 tests / 31 assertions; Sprint 3B — 15 / 71; Sprint 3C — 12 / 60;
  Sprint 3D — 6 / 35; full suite — 162 tests / 771 assertions, all passed.
- `vendor\\bin\\pint --test`, `git diff --check`, conflict-marker search and release debug
  scan — passed with no changed-file debug calls, hard-coded local URLs/paths or merge
  markers found.
- `npm run build` — passed. The sandboxed attempt was blocked by its Windows process-spawn
  restriction; the equivalent local build passed outside that restriction.
- The final Sprint 3D focused verification passed: 29 tests / 218 assertions.
- Fresh local SQLite migration/seeding passed; the final full suite passed: 171 tests /
  884 assertions. Pint, whitespace checking and the approved local Vite production build
  also passed.

## Production blockers

- Forge production's migration ledger and schema are unreconciled following the failed
  Sprint 3A deployment. Production recovery/reconciliation remains a separate gate.
- MySQL-compatible migration and concurrency rehearsal remains outstanding.
- Physical-device and screen-reader evidence remain outstanding.

## Deployment safety

No Forge deployment, production migration, production database access or production
environment change was performed. The release-candidate branch must not be merged into
`main` until the database-reconciliation and QA gates have passed.

## Commit and remote verification

Checkpoint commit subject: `feat: add Sprint 3A-3D portal checkpoint`.

The Sprint 3D correction commit is `34531d2`. The final remote-tracking SHA is recorded in
the release handoff after the non-deploying push: a Git commit cannot truthfully contain its
own final SHA because changing this document changes that SHA. `main` remains untouched and
no Forge deployment is triggered by this release-candidate checkpoint.
