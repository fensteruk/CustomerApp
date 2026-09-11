# Sprint 3E Production Release Candidate — 27 August 2026

## Status

Ready for controlled merge and deployment approval. This preparation did not merge or
push `main`, trigger or edit GitHub Actions, contact Forge, or deploy.

## Baseline and release identity

| Item | Value |
|---|---|
| Refreshed `origin/main` baseline | `20d5f123906b7c88f1264f7df5ff90be02c15ec2` |
| Final release branch | `release/sprint-3e-production-2026-08-27` |
| Application integration commit | `4ea5f5511660be66459cea20ab6a1acafbff3784` |
| Exact gated source snapshot | `22f9746e416ca43e88662c90f76ff4080b0e5995` |
| Earlier QA reference | `a38d557c5a38b41d064b57a3cb9c4e2494a34fd3` |

The application integration commit was constructed from refreshed `origin/main`, not by
merging the old release branch. Runtime and test files were restored byte-for-byte from
the exact source snapshot used by the successful final MySQL gate. The disposable MySQL
workflow was deliberately omitted.

The pre-existing local edit to
`documentation/sprint-3e-date-negotiation-report.md` was not staged, committed or changed.
The historic report itself was omitted from this release because its committed blob still
contains the trailing-whitespace line that the explicitly excluded local edit corrects.
Current QA, concurrency and release-candidate evidence is included instead.

## Ordered source commit register

Classification: A = runtime, B = tests, C = documentation, D = workflow/CI only,
E = obsolete or superseded, F = already on main.

| Commit | Classification | Release treatment |
|---|---|---|
| `20d5f123906b7c88f1264f7df5ff90be02c15ec2` | F | Refreshed main baseline. |
| `68945bed04896a1b88423500d6f13e1ae3e04e0d` | A/B/C | Original Sprint 3E date negotiation, UI, notification and tests included; historic report excluded as noted above. |
| `58cc9cb7a6c434c17ac6787cba4cbd777f0d15b5` | A/B/C | QAE-01, QAE-02 and QAE-03 fixes, tests and QA report included. |
| `9aaa6ce0f60053a483f472b883dff2136e4e63fa` | C | Sprint 3E release documentation included. |
| `75d1faa28a7342c4df1158039e7c80114ed80d05` | B/D | MySQL configuration and test files included; workflow file excluded. |
| `0d5ecb44aa800ecb98be2741caed12370250b7af` | B/D | Final schema-test correction included; workflow edit excluded. |
| `06df937f3748f3a07186b7422d0e726419a04958` | B | MySQL schema test included. |
| `7d38f9332ba63e3c4cd35e92e80568d153ab57c7` | B | MySQL history FK coverage included. |
| `6edac908dee1eef15587e72b767be34201ea199b` | B | MySQL metadata relationship coverage included. |
| `b14147a1c76d4b44bbf94d467a066991fa264132` | B | Metadata casing correction included. |
| `09fb45b9c94742cf785c66a0ad64d7b893dffbd1` | B | Schema-test formatting included. |
| `08eec1c43cd59e468d1d36e88ac960fddb5b43fe` | B/D | MySQL test-environment correction included; workflow edit excluded. |
| `133185c6e6197e2d3f63041f6bd340fc898fd2cb` | B | Duplicate-index assertion correction included. |
| `8164c0e3f439c0ad8945008eb578849204dea0b2` | B/D | Schema-test stabilisation included; workflow edit excluded. |
| `ae83c746d0c6600ff6378dc1ccf1dfad4e624ac4` | D | Excluded; workflow diagnostics only. |
| `7c2e24d9dd15837cd9bca2d1ea1a291e5540d4fd` | D | Excluded; workflow diagnostics only. |
| `2368544f4ed5a9f3a14bd946f370eeaadf8b5cbc` | D | Excluded; workflow build step only. |
| `65433dc77c755ffebcdf2ec6d45bef772cbc8c7b` | A/B | Gated canonical JSON comparison fix and regression test included. |
| `0689f3234bd90f9cf2d23532e0e227d7cc969258` | B | Immutable worker timestamp correction included. |
| `06a0179e61751a46f00452005d3d7e16fc5440b6` | B | Concurrency worker-error evidence included. |
| `7f8e8f92585dbab3b0f24e75bdaa7ee87fb498bd` | D | Excluded; manual-only workflow change. |
| `5d3ebce4bdb85a959a13ef3769a74b2849233b51` | A/B/C | Canonical lock order, bounded retry, source precedence and concurrency evidence included. |
| `cd5330d5a3763ca989cc8294888f00a28a577cbe` | A/B/C | Alternative-acceptance notification event fix and regression coverage included. |
| `22f9746e416ca43e88662c90f76ff4080b0e5995` | A/B/C | Historical-truth notification guard and regression coverage included. |
| `a38d557c5a38b41d064b57a3cb9c4e2494a34fd3` | E | Earlier parallel QA checkpoint; superseded by the final gated release-line snapshot. |

## Runtime scope

The runtime delta contains only Sprint 3E date agreement and alternative-date negotiation,
the customer and Office request-detail UI, server-side authorisation and stale-action
protection, source completion/availability safeguards, notification/history behaviour and
the gated quick-undo JSON portability correction.

The following MySQL-gated semantics are unchanged:

- lock order: service, request, negotiation, proposal, history;
- source completion remains authoritative and clears active negotiation conflict;
- source availability is revalidated under the authoritative service lock;
- source retry is limited to three complete attempts for SQLSTATE `40001` and MySQL
  errors `1205` or `1213` only;
- committed Date Agreed history remains truthful when completion follows;
- Date Agreed notification eligibility can use committed historical truth;
- Date Agreed notification idempotency remains `date_agreed:<request UUID>`;
- completion emits no completion notification.

Sprint 3F, SiteApp write-back, source transport, production credentials, deployment code
and unrelated UI work are absent.

## Tests included

- Sprint 3E date-negotiation domain, UI and dedicated QA tests;
- disposable MySQL schema and real-process concurrency tests;
- the MySQL concurrency worker and MySQL PHPUnit configuration;
- source completion, availability, retry, history and notification regressions;
- canonical quick-undo JSON regression coverage.

## Workflow and CI treatment

`.github/workflows/sprint-3e-mysql-gate.yml` is not present in the release delta. This
excludes all temporary gate iterations, the manual-only trigger change, automatic
email-generating behaviour and any deployment trigger. No existing main workflow changed
and no new automatic workflow was introduced.

## Migration protection

Sprint 3E adds no migration. There is no `database/migrations` delta from main.

The protected migration blob hashes match main exactly:

| Migration | Blob hash |
|---|---|
| `2026_08_05_000002_create_call_off_domain_tables.php` | `6b21361be77eb821db40333c7b161905a8cb2e0a` |
| `2026_08_07_000003_create_portal_notifications_table.php` | `c00327aa9398cb44f9c24cda5af987e61ed1cc0a` |
| `2026_08_20_000005_add_target_call_off_domain_foundation.php` | `7f5f2ce2c9f44747a72b6910832c5a43ec3da0a6` |
| `2026_08_20_000007_add_source_projection_import_contract.php` | `144929c371e513f9e69de332ad3d6d943cceba8d` |

## Final MySQL gate reference

The isolated MySQL 8.4.10 gate completed against the source snapshot identified above:

- clean migration and seed: 11 migrations passed;
- schema gate: 1 test, 20 assertions;
- full MySQL suite: 213 tests, 1,237 assertions, 0 failures, 0 skipped;
- focused Sprint 3B/3D/3E/source/notification/concurrency gate: 84 tests,
  621 assertions, 0 failures;
- critical concurrency gate: 14 tests, 197 assertions, 0 failures;
- acceptance-first race: 10/10 independent runs, 90 assertions, 0 failures.

No release runtime or test file differs from that gated snapshot. Only workflow-only files
and the explicitly excluded historic report are absent, and this production release record
is additional documentation.

## Local reconstruction verification

The release worktree had no `.env` and used `APP_ENV=testing`, a dedicated disposable
SQLite database, array cache/session/mail and the synchronous test queue.

| Check | Result |
|---|---|
| `php artisan migrate:fresh --seed --force` | Passed; all 11 migrations and seed completed. |
| Full local suite | 213 discovered; 198 passed; 15 MySQL-only skipped; 1,020 assertions; 0 failures; 40.827 seconds. |
| Focused local suite | 84 discovered; 70 passed; 14 MySQL-only skipped; 424 assertions; 0 failures; 22.701 seconds. |
| `vendor\\bin\\pint --test` | Passed. |
| `composer validate --no-check-publish` | Passed. |
| `composer audit --no-interaction` | Passed; no security advisories. |
| `npm run build` | Passed with Vite 8.1.4. |
| `git diff --cached --check` before integration commit | Passed after the historic report was excluded. |

A first test bootstrap using dependency junctions was discarded because Composer resolved
the parent checkout's autoloader. After independent dependency installation, a pre-build
run correctly identified the missing Vite manifest. The reported results above are from
the clean rerun after the production asset build and are the auditable release checks.

## Files included

The integration commit changes 52 files: 2,858 insertions and 70 deletions. They comprise
the Sprint 3E actions, events, requests, controllers, notification/source services, views,
routes, domain documentation, current release documentation and tests listed by
`git diff --name-status 20d5f12..4ea5f55`. This release record is the only subsequent
documentation file.

## Forge compatibility

The repository evidence records this deployment sequence:

```text
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci || npm install
npm run build
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force
$ACTIVATE_RELEASE()
$RESTART_QUEUES()
```

The release remains compatible with Composer installation, npm/Vite build, Laravel
optimisation, storage linking, migration execution, zero-downtime activation and queue
restart. Because Sprint 3E adds no migration and main already contains migrations 000001
through 000008, the expected production migration output is `Nothing to migrate`.
Forge and production were not contacted during this preparation.

## Remaining non-blocking limitations

- The final MySQL gate remains the concurrency authority; local SQLite intentionally skips
  MySQL-only schema and process-race tests.
- Real source transport, credentials and scheduling remain outside Sprint 3E.
- The existing local historic-report whitespace edit remains uncommitted and outside this
  release.
- Merge to main and production deployment require separate explicit approval.

## Recommendation

The exact release branch is ready for a controlled merge into main and Forge deployment
after explicit approval. No further Sprint 3E development or QA change is recommended
before release.
