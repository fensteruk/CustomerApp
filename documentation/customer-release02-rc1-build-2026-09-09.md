# CustomerApp RC1 Build Report

Date: 9 September 2026. Authority: approved CUSTOMER-RELEASE02 and test-only correction/
freeze approval CUSTOMER-RELEASE02A. This record belongs only to the non-deploying RC worktree.

## Overall result

**READY FOR DEDICATED QA.** The approved merges, narrow inherited date-fixture correction,
complete SQLite regression, disposable MySQL 8.4.11 gate and quality checks passed.
No application business logic, lead-time rule or assertion was changed.

## Branch and exact inputs

- RC: `release/customerapp-2026-09-09-rc1`.
- Worktree: `.cursor/release-worktrees/customerapp-2026-09-09-rc1` under CustomerApp.
- Base: `0873bac79edf578e9f4a9417e3cafae34e8aa925`.
- Sprint 3F input: `60aa9e2c72074dea2f8a4812aab238ff5d2791d5`.
- Sprint 3F merge: `43a9b1feeffb9fd7216ec2683515a206deae6b5d`.
- Security input: `5e7df0862648fd9c2ac964b31a13ad17df84fd12`.
- Security merge/current code checkpoint: `45b3c57582de407b30b4caa890ac47a5d2b5d8a7`.
- Final frozen QA SHA: the commit containing this record and DEC-056; record its exact SHA
  in the release handoff/final task report and use only that immutable commit for QA.

All input refs matched the exact approved pins, both feature merge bases were exact main,
and the branch/path did not exist before creation. Both merges used `--no-ff`, preserving
all feature history; neither required a conflict resolution. The documentation and approved
test-only correction are committed together as the final freeze after every gate passes.

## Included and excluded scope

Included: main's Office authorisation and Date Agreed filter fixes; the complete Sprint 3F
amendment lifecycle, reasons, On Hold, urgent/late handling, Office accept/propose, Site User
responses, source-completion precedence, historical dates, current-read race correction;
and exact reviewed security dependencies/published assets/tests.

Excluded: WALD02–05, their runtime/migrations/bindings/profiles/triggers, ADMIN-SITE02,
old manual import/interpreter/UI branches, unfinished source integration, WALD06 and
multi-site pilot. Existing Sprint 3B source code remains exactly the approved baseline,
not newly authorised for real workbook ingestion.

Static checks confirmed application/routes/config/resources/schema exactly match Sprint 3F,
and composer.lock plus published Filament CSS/JS exactly match the security pin.
No excluded runtime symbols were found in app/routes/config/migrations. composer.json,
package.json, package-lock.json and GitHub workflows are unchanged from main. There is
no main merge/push, RC push, deployment, Forge change or production connection.

## Dependencies

Exactly 12 package versions change from main: Filament actions/filament/forms/infolists/
notifications/query-builder/schemas/support/tables/widgets 5.6.8 → 5.7.6;
Livewire 4.3.3 → 4.3.4; CommonMark 2.9.0 → 2.10.0.
Laravel remains 13.20.0. No broad update, added/removed package or npm lock change.

Fresh isolated installation used `composer install --no-interaction --prefer-dist --no-scripts`
(161 installs, zero update/removal operations on the locked installation), then the reviewed
post-autoload-dump hooks. Published assets remained byte-identical to the reviewed commit.
`composer validate --strict`, `composer check-platform-reqs` and `composer audit --format=json`
passed; the audit has zero advisories and zero abandoned packages, with no suppression.

Additional npm risk: unchanged `npm ci --ignore-scripts --no-fund` completed, but a fresh
`npm audit --json` reports 14 affected-package entries: nine moderate, five high, zero critical.
These are in the Vite/Tailwind/PostCSS/Browserslist build-tool graph, including inherited
PostCSS source-map, nanoid custom-generator and Browserslist issues. They are not 14 distinct
independent advisories and are not resolved by Composer remediation. No arbitrary customer
CSS/source maps are built in this task. Dedicated release review must triage/fix or explicitly
accept these risks; no npm upgrade or advisory suppression was authorised or performed.
All affected entries are development/build dependencies; `npm audit --omit=dev --json`
reports zero production-dependency vulnerabilities. The reviewed paths execute during the
trusted Vite/Tailwind/PostCSS build and are not shipped as callable browser/server packages.
No direct exploit path through the deployed application was identified from current use.
They remain build/CI exposure and require explicit disposition during dedicated RC QA.

## Exact migration inventory

1. `0001_01_01_000000_create_users_table.php`
2. `0001_01_01_000001_create_cache_table.php`
3. `0001_01_01_000002_create_jobs_table.php`
4. `2026_08_05_000001_create_secure_access_domain_tables.php`
5. `2026_08_05_000002_create_call_off_domain_tables.php`
6. `2026_08_07_000003_create_portal_notifications_table.php`
7. `2026_08_19_000004_add_call_off_browsing_indexes.php`
8. `2026_08_20_000005_add_target_call_off_domain_foundation.php`
9. `2026_08_20_000006_remove_synthetic_legacy_date_proposals.php`
10. `2026_08_20_000007_add_source_projection_import_contract.php`
11. `2026_08_21_000008_add_multi_call_off_request_fields.php`
12. `2026_09_03_000014_add_date_amendment_metadata.php`

The 11 baseline files are unchanged. The only forward addition is 35 lines of Sprint 3F
migration code: ten nullable/default negotiation metadata fields, short MySQL-safe
`negotiation_requester_fk`, restrictive user deletion. It does not rewrite old history/dates.
Its populated down drops amendment metadata and is not the production recovery strategy.
No WALD/admin/old import migration, filename collision or new trigger exists in RC1.

## Documentation reconciliation

- `brief.md`: replaced the layered historical brief with one concise current product/release
  contract, explicitly distinguishing included RC behaviour from excluded future source targets.
- `DECISIONS.md`: appended DEC-055; retained existing Sprint 3F DEC-039 byte-for-byte and
  explained its distinct provenance from the excluded WALD branch's DEC-039–054. No blind
  import or historical renumbering.
- `current_sprint.md`, `HANDOVER.md`, `ROADMAP.md`: added current RC1 status at the top and
  clearly qualified older entries as historical, including obsolete deployment/advisory claims.
- This build record: exact merge/verification/recovery evidence and remaining gates.

Historical sprint/security reports are untouched. No source workbook, .env, credential,
generated build output, local database or unrelated edit is staged for a commit.

## SQLite and local pre-QA

Environment created from scratch in the RC worktree: APP_ENV=testing, SQLite `:memory:`,
blank DB URL/credentials, test-only key, array mail/cache/session, sync queue, no production
configuration copied and no pre-existing config cache. Local PHP 8.4.23, Composer 2.10.1,
Node 26.5.0, npm 11.17.0, Git 2.55.0. No test ran against another worktree's database.

| Check | Exact result |
| --- | --- |
| First focused Sprint 3F run | 82 passed / nine failures / 1,019 assertions; missing Vite manifest after sandbox-blocked build; not an application correction |
| Asset build retry | `npm run build` passed under approved child-process permission; Vite 8.1.4, 6.81 seconds |
| Focused Sprint 3F after build | 91 passed / 1,055 assertions / zero failures; 17.371 seconds |
| Full SQLite | 317 passed / 38 MySQL-only skips / one error / 2,276 assertions; 48.346 seconds |
| Fixed-date failure alone | One error / zero passes / zero assertions; 1.282 seconds; confirms not suite-order contamination |
| Corrected fixture alone | 1 passed / 6 assertions / zero failures; 2.213 seconds |
| Focused Sprint 3F final | 91 passed / 1,055 assertions / zero failures; 17.053 seconds |
| Full SQLite final | 356 total: 318 passed / 38 intentional MySQL-only skips / 2,282 assertions / zero failures or errors; 69.659 seconds |
| Pint | `php vendor/bin/pint --test` passed |
| Composer validate/platform/audit | Passed; zero Composer advisories |
| Whitespace | `git diff --check` passed |

### RC1-T01 — inherited fixed-date notification fixture

`tests/Feature/Sprint3dBulkCallOffQaTest.php:308` hard-codes 2026-10-02, 2026-10-05,
2026-10-06 and 2026-10-07, with no frozen clock and null early-date reasons. As of
2026-09-09 the standard four-week boundary is 2026-10-07: the first three dates are early.
The correct production action therefore throws `Explain each Request Earlier Date exception.`

Both this test file and `SubmitMultiCallOffBatchAction.php` are unchanged from main.
The standalone failure is identical to the full-suite error. This is a dated test fixture,
not evidence that Sprint 3F/security weakened or broke the lead-time domain rule.

CUSTOMER-RELEASE02A approved the narrow repair. The four literals are replaced by a first
weekday derived from `CarbonImmutable::today()->addWeeks(6)->nextWeekday()`, followed by the
next three weekdays. The scenario therefore retains four distinct valid dates without adding
another calendar value that will age. All existing notification count, recipient, service,
date-distinctness and current-state assertions remain unchanged. No early reason, skip,
validation relaxation or application-code change was made.

Similar fixture review found no other equivalent stale value. Sprint 3F dates are
`INTENTIONALLY_FIXED_DATE`, anchored by `travelTo(2026-09-03 10:00:00)`. Sprint 3E's 2030
weekend/holiday values are `INTENTIONALLY_FIXED_DATE`. Sprint 3D's 2026-12-31 value is
`SAFE_FIXED_HISTORICAL_DATE` for signed-confirmation tampering: it is never accepted as a
request date. Only RC1-T01 was corrected.

## Disposable MySQL 8.4 pre-QA

**PASS:** clean migration/status, populated upgrade and the direct-Pest diagnostic run.
Pre-correction diagnostic run: 129 passed / 2,098 assertions / zero failures, errors or
skips / 190.543 seconds.
The PHPUnit event trace explicitly ends `PHPUnit Finished (Shell Exit Code: 0)`; the
PowerShell harness also exited 0. No warning/risky/error/deprecation event was found.
The local Oracle-signed 8.4.11 binary is reused read-only with a new data directory,
loopback-only port 33509, no Windows service and no production/Forge access. Databases:
`portal_sprint3f_gate_rc1_20260909` and `portal_sprint3f_gate_rc1_upgrade_20260909`.
Separate restricted users have only their exact escaped database grant plus USAGE; no
global operational privilege. Proof checks actual PDO database/account/version/port,
REPEATABLE-READ, APP_ENV testing, array transports, blank DB URL and absent config cache.
Root credentials are not inherited by test/worker processes.

Initial local harness issues are retained as failed evidence, not product defects:
skip-name-resolve prevented initial root bootstrap over loopback, before any application
DB existed; the exact disposable process was stopped and host resolution corrected.
The first MySQL test attempt then omitted the race-evidence output directory: 99 passes,
30 file-output errors, 1,313 assertions. All 12 migrations had passed, but the race gate did
not. Both databases/users were removed and the server stopped. A new data directory/run
with an explicitly created evidence directory repeats the complete gate unchanged.

That repeat reported 129 passes / 2,098 assertions / zero failures, errors or skips in
173.325 seconds, confirmed in JUnit (173.255717 seconds). Breakdown: ordinary Sprint 3F
91 / 1,055; critical concurrency/retry 37 / 1,023; schema 1 / 20. However, `artisan test`
returned exit 1 despite the passing JSON/JUnit. This is retained as a runner discrepancy,
not silently counted as a successful command. Cleanup confirmed both DBs/users absent
and the server stopped. Its 56 race evidence JSON files are retained.

A further entirely fresh run invokes the same Pest suite directly with `--display-all-issues`
and a PHPUnit event trace, without changing assertions or dependencies. Before those tests,
clean migration/status passed again and the isolated populated 11-to-12 upgrade rehearsal
passed: one existing agreed request's values and row counts were preserved across requests,
negotiations, proposals, histories and notifications. No historical date/history rewrite.
All three test groups passed with the same 91/37/1 tests and 1,055/1,023/20 assertions.
The earlier Artisan wrapper exit discrepancy is not assigned an invented root cause;
the direct runner with full event evidence supplies the successful MySQL gate result.

The repeated suite contains ten source-completion/alternative-acceptance process races,
ten amendment-acceptance/completion process races and five synchronized Office races for
each winner ordering, plus its remaining conflict, availability and bounded-retry cases.
This is not a claim that the full MySQL application suite ran; scope is the approved pre-QA
selection. Dedicated release QA must still rerun against the final frozen SHA.

After the fixture correction, a fourth entirely fresh disposable environment repeated the
whole approved gate against the final working-tree content: all 12 clean migrations/status
passed; populated 11-to-12 upgrade again preserved the five checked tables; and MySQL tests
passed 129 / 2,098 / zero failures, errors or skips in 149.980 seconds with exit 0. Breakdown
remained Sprint 3F 91 / 1,055, concurrency/retry 37 / 1,023 and schema 1 / 20. Cleanup again
confirmed both temporary databases and restricted users absent and the server exited.

Local evidence is retained outside tracked files under `.cursor/rc1-preqa-20260909`:
SQLite JUnit/reproduction, npm audit, isolation/migration/cleanup logs, MySQL JUnit and
race evidence. Earlier stopped scratch data directories have been removed, not evidence
or the reused binary. Final cleanup verified both temporary databases and restricted users
absent and the disposable server exited. All four task-created scratch data directories
were then removed after process/path checks; generated non-secret evidence remains.

## Production impact and remaining gates

Production impact: **none**. No production credentials loaded, DB contacted, data/config
changed, queue/worker altered, migration run, Forge setting touched, main pushed or deployment.
The original dirty Sprint 3E report, workbook, output directory and active admin/WALD worktrees
are preserved. Existing MySQL runtime files belonging to the WALD task are not changed.

RC1-T01 is resolved and the candidate is frozen by the commit containing this report.
Remaining release gates: dedicated frozen-RC QA (including browser/accessibility/security
and npm build-risk disposition), and later separately approved
production-state/backup/upgrade/deployment verification. No further feature is required.

CustomerApp RC1 built — ready for dedicated release QA

## CUSTOMER-RELEASE03A recovery addendum — 9 September 2026

Dedicated QA ran against frozen executable checkpoint
`ac250a8e9eef4b40591872de9275d802c76e4fba` and passed the forward functional, security,
browser, migration and concurrency gates. It retained RCQ-01/P1: old main cannot enum-cast an
actual RC1 `call_off_amendment_requested` notification row. That original QA finding remains
valid evidence and is not erased by this addendum.

DEC-057 resolves RCQ-01 as `RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`. RC1 must be cut over under
maintenance after a fresh verified database backup and capture of current SHA/release/migration
ledger. Before reopening and before any Sprint 3F write, failure may restore the snapshot and
previous verified code together. After reopening or any Sprint 3F write, old main is forbidden;
recovery is roll-forward from the exact deployed RC or a compatible descendant. A Forge release
symlink/code switch does not restore MySQL, and the populated amendment migration down is not a
recovery plan.

This addendum and the associated release-document corrections are documentation-only descendants
of the frozen executable checkpoint. They permit merge-to-main preparation only and do not
authorise a main push, deployment, Forge change or production operation. See
`documentation/customer-release03a-rc1-recovery-strategy-2026-09-09.md`.
