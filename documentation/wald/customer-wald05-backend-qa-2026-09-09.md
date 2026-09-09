# CUSTOMER-WALD05 Dedicated Backend QA Report

Date: 9 September 2026. Owner: CustomerApp QA / Testing.

## Overall result

**FAIL — corrections required. Do not freeze this branch as an accepted WALD05 baseline.**

The bounded backend works through private intake, deterministic analysis, explicit review and
atomic projection commit. Independent QA found and corrected a namespace-wide visit ordering
bypass and a canonical digest mismatch. Two further bounded defects remain open: missing durable
refusal/failure audit and repeated profile-receipt queries under commit locks. Passing regression
tests do not constitute acceptance of those known gaps.

This is feature-branch QA only. No main, push, GitHub Actions, production, customer import, SiteApp,
WALD06, full Office UI or Composer-remediation merge occurred. Browser/mobile/accessibility and
HTTP upload security are later UI gates; no nonexistent endpoint is certified here.

## Candidate and accepted components

- Original branch: `feature/customer-wald05-import-review-integration`.
- Exact original candidate: `1dc6ee6a24026c970687472126dc295fb6f2b3c8`.
- Local QA branch: `qa/customer-wald05-backend-2026-09-09`, created from that exact candidate.
- Foundation: `d1b5de13b260dde77867fdb8ac73296e49994c14`, verified ancestor.
- Accepted WALD04: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`, verified ancestor.
- QA correction commit: `9f5751f7a6d62b4ae989e34f3894e3b57011a0a8`; it is **not an accepted baseline**.
- Local main and cached origin/main remain `0873bac79edf578e9f4a9417e3cafae34e8aa925`.

Reviewed the complete new integration runtime, both additive migrations, accepted-component delta,
all WALD05 test/helper files and implementation report. No routes, resources, lockfiles, dependency
manifests or GitHub workflows differ between accepted WALD04 and this candidate. The old importer
and mapper remain untouched and are not invoked by the new path.

DEC-052's narrowly namespaced Excel extension correction is the only generic reader delta from
WALD04: reader `wald-0.2.2`, XLSX adapter 3, CSV 2. It does not change structural scoring or source
date-system authority. WALD03 differs only in its approved reader identity pin. Dictionary v1
fingerprint remains `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.
Candidate generic tree `961831e9f727e41e0fe9c7a969376c2af7caad9f` and semantic tree
`40c8255f5951fe51844edd791ee6a7474df1704c` remain unchanged by QA.
WALD04 keeps its authority, provenance and fresh-evidence vetoes. QA changes only its new transient
digest implementation and collision-prone synthetic fixture naming, not persisted knowledge policy.

## Defects and disposition

| ID | Severity | Evidence and effect | Disposition |
|---|---|---|---|
| W5Q-01 | P2 | `evidenceHash(['nested' => [1 => 'one', 0 => 'zero']])` disagreed with `Canonical::hash` because list/object selection occurred before key sorting. | FIXED: re-evaluate list shape after canonical sorting. The original reproducer failed and now passes. Retained JSON ceilings and the 4 MiB transient ceiling remain unchanged. |
| W5Q-02 | P1 | Commit MORNING visit 1001 with VS 2.125; use the same namespace/site/date/slot but another workbook family and VS 99.000. The original candidate overwrote the visit without correction lineage. A new namespace could also take over the already-owned projection using an older date. Both independent reproducers failed. | FIXED: validate namespace-wide visit order and require the latest observation's run as explicit predecessor for changed same-slot facts; reject takeover of an existing Wald-owned service by a different source identity. Batched observation lookup adds one constant SELECT. Older cross-family observations now also refuse during review. |
| W5Q-03 | P2 | Review, expire it, then attempt commit. The exception is safe and all domain state is unchanged, but command count is unchanged and there is no separate durable refusal/attempt journal. Injected failed commits likewise leave no durable failure outcome. | OPEN: add a bounded, attributable attempt/refusal journal outside the all-or-none domain-effects transaction. Keep success receipts immutable and preserve existing rollback assertions. No new business rule is required. |
| W5Q-04 | P2 | Seven identical, valid, explicitly applied profile-use receipts cause 138 commit SELECTs, versus 60 for one receipt on the same seven-row shape. `ImportKnowledge::capture` calls `receiptEligible` independently for every use, repeatedly loading authority, context, profiles, versions and provenance. | OPEN: batch current receipt eligibility per scoped context with bounded queries; preserve each receipt's pins, fresh-answer veto, revocation and competing-profile checks. The original row-only benchmark did not cover this dimension. |
| W5Q-05 | P3 | A longer persistent MySQL race rehearsal failed on duplicate randomly generated organisation name `Heaney Group`, before the race itself. | FIXED: the shared Wald synthetic owner helper now uses UUID-qualified organisation names, as it already does for user email. No application factory or race assertion was weakened. |

W5Q-01/02 advance the backend application/projection/digest identities to v2, so v1 staged
manifests cannot silently commit under corrected behavior. No stored historical evidence is rewritten.
The original two-reproducer run was 0/2 passing; subsequent focused checks pass.
The refusal-audit and receipt-query tests deliberately record the present deficiencies as diagnostic
evidence, not normative acceptance tests. They must be replaced by positive protection assertions
when the corresponding corrections are implemented.

## Schema / migrations

No historical migration was edited and no QA migration was added. The two candidate migrations
add three foundation tables and eight backend tables, six foundation guards, 19 backend guards
and three projection epoch columns. Composite site/organisation ownership, unique stream slot
revision, unique run receipt and visit observation versions are enforced. All immutable tables
reject update/delete; protected identity comparisons are binary on MySQL. Mutable stage/preview
pointers are checked against run ownership at application boundaries rather than relying on UUID
possession; these pointers are not client-writable endpoints.

Independent disposable MySQL 8.4.11 at **127.0.0.1:33488**, X protocol disabled, CustomerApp-owned
data directory `storage/app/wald05-qa-20260909/data`. Official local binary signature was Valid.
No persistent Windows service or environment-file change was made.
After all MySQL checks, the exact server port/bind address/data directory were reverified.
Normal shutdown completed at 10:35:21 UTC on 9 September 2026 and port 33488 no longer listens.
The five disposable synthetic schemas and local evidence are retained; none was dropped/reset.

- Clean schema creation executes through the MySQL feature-test setup.
- Existing backend upgrade script: 14 baseline migrations plus one backend migration; 15 old
  tables preserved; empty rollback/reapply passes; populated backend rollback refuses before DDL.
- Independent QA upgrade script: 13 accepted WALD04 migrations plus both WALD05 migrations;
  all 34 pre-existing tables snapshotted. Existing products, agreed dates, request/history,
  organisation/site and knowledge rows remain unchanged through upgrade and empty two-step
  rollback/reapply. Both populated migration rollbacks refuse without removing guards.
- Binary case/trailing-space identity attacks and MySQL constraint-name length checks pass.

The first independent upgrade attempt referenced a nonexistent service factory and stopped after
baseline setup. The corrected harness uses the actual models and a new empty `_v2` schema;
the first synthetic schema was retained, not reset or reused. The successful script result,
not Laravel's incidental exception-process exit status, is the acceptance evidence.

## Upload / storage / security

Private generated UUID keys live beneath `storage/app/private/wald-imports`. Original filenames
are display data, never path or permission authority. Four additional traversal/script-like
filename cases remain confined; they are not browser-rendering/XSS tests. Authenticated uploader
ID/name come from the stored account, not dirty caller attributes. Exact date/slot confirmation
is mandatory and immutable. Content type, format, size, reader open, content hash and reopen
integrity checks are present; stored symlinks and invalid keys refuse. CSV/XLSX use the accepted
non-executing reader. Formula/error cells block; prompt-like cells are inert data.

All three external roles, inactive Office and persisted preview status are independently tested
against upload, analysis claim, clarification read, review, commit, retry, private details and
retention. Foreign run UUIDs refuse under a different explicitly valid tenant/site scope.
Foundation tests independently cover binding draft/activate/revoke/resolve/history scope and
authority. Null-organisation active Office works only with explicit valid scope. No new durable
impersonation capability exists. No public upload/download or HTTP CSRF/throttling claim is made.

## Ordering / Call No. / correction / recovery

Export Date then MORNING/AFTERNOON is authoritative, never upload/file time. Older day/slot
refusals, same-slot conflict, explicit reasoned successor, immutable predecessor receipt, exact
successful replay and uncertain-client-response recovery are tested. A successful command replay
first reauthorises current access. An uncertain success is simulated by discarding the returned
result and reissuing the same and a new command; this is not a network fault-injection claim.

Duplicate Call Nos. block staging/review, including identical duplicate evidence. Later plot or
service reassignment refuses. Binding scope prevents cross-site reassignment. Distinct CM1/CM2
meaning remains dictionary-owned; a new Call No. is not collapsed into an old visit. The bounded
adapter refuses a competing new visit for an already represented plot/service rather than inventing
a revisit roll-up. W5Q-02 now closes the separate cross-family same-slot bypass.

## Bounded unit / multi-site pilot implication

One explicitly bound site, one visible unmerged sheet/table, maximum 500 nonempty rows.
Independent one-row success and empty/merged/hidden-row/two-visible-sheet/hidden-sheet refusals
pass. Existing 7/100/500 cases pass and 501 refuses before any staged subset/receipt. Every
included source-site identity must bind to the chosen destination; unresolved foreign sites fail.
Wider evidence can hit independent retained-payload or analysis budgets below the row ceiling;
500 rows is a maximum, not a universal workbook-capacity promise.

**PILOT_BLOCKER for the proposed multi-site splitting workflow.** The one-site backend boundary
itself is safe, but a second explicitly reviewed site in the same namespace/family/date/slot
returns `slot_scope_conflict` after the first site commits. Therefore an Office UI cannot simply
split a twelve-site export into twelve same-slot commits under the approved ordering contract.
Do not fabricate per-site family names or change dates/slots to evade that rule. WALD06 needs an
explicit parent-export/unit ordering design or other approved contract change. This is separate
from the two open backend QA defects and does not authorise implementation now.

The actual twelve-site workbook was not uploaded, staged, reviewed or committed. DEC-050/051
synthetic helper tests check exact-artifact approval, raw CC! preservation, scoped CC1 correction,
CM2 exclusion and changed-hash/row refusal. Synthetic end-to-end occurrence clarification proves
an unrelated workbook needs its own answer. No synthetic file is falsely given the real file's
content hash at intake. No global CC! alias, CM2 exclusion or test-site rule exists.

## Partial exports / products / completion / Portal protection

Only PARTIAL_FILTERED_EXPORT commits. Both stronger scopes block. Omitted plot/call/service
facts stay unchanged; absent product columns preserve stored quantities. Present blank is
retained as raw presence and applies the existing supplied-record zero contract; explicit zero,
exact positive and invalid quantity paths remain distinct. The accepted dictionary regression
covers all approved Windows/Doors and excluded product codes. Integration exercises VS/BF,
excluded commercial/product fields, formula/date/percentage/boolean refusal and partial omissions.
Unknown quantities are not coerced to zero. Unknown headings receive no invented product meaning.

Only exact approved positive BF contributes the BF fact; no WALD05 lead-time calculation was added.
Yes/No completion and reversal use approved domain semantics, with no invented completion date.
Operational `Plot To Be Installed` remains private source evidence, not requested/agreed/proposed,
amended or completion date. Unknown service plus Yes blocks.

Preview captures current projections, products, requests, negotiations, proposals, batches and
history without mutating them. Review evidence alone persists. Existing Portal protection tests
seed amendment-purpose negotiation, agreed/requested dates, responses, actors and old history;
completion closes current process/proposals and conflict keys while retaining those facts.
Injected failure after completion rolls all effects back. Reversal never reopens the old request.
No completion notification. This is the amendment-shaped domain available on this branch,
not certification or integration of the separate Sprint 3F implementation.

## Preview / stale-state / lifecycle

Immutable manifests/previews pin workbook, canonical/stage hash, manual order, scope, binding,
knowledge/answers/use receipts, reader/dictionary/executable/backend identities, stream epoch,
projection/history digest, reviewer/time and expiry. Current dependencies are checked on approval
and commit. Source bytes and immutable date/slot fields cannot be edited through application APIs.
Changing a relevant mutable dependency refuses rather than refreshing a reviewed plan.

Controlled-clock tests pass just before preview expiry and refuse exactly at and after expiry.
Run-state matrix refuses UPLOADED/FAILED/SUPERSEDED/COMMITTING/COMMITTED → commit or review
without a valid allowed path; existing committed receipts retain their safe historical replay path.
Analysis uses generation/epoch/600-second lease fencing, explicit recovery and bounded claims.
Failed/expired work cannot jump directly to commit. Browser/persistent-worker recovery remains a
later interface/operations gate, not an implemented queue worker.

## Atomicity / audit / retention

One transaction covers projections, completion, immutable observations, receipt, stream/run state
and the required successful command audit. Injection before/after observation, product,
completion, receipt, source-run, stream and audit writes proves rollback. No per-row transaction
or legacy fallback is called. Existing immutable success replay creates no duplicate business
effects. Source observations and receipt predecessors preserve prior truth.

Metadata implements terminal workbook +30 days, bulky staging +7 days, preview validity 24 hours,
preview payload +7 days and minimal committed audit/observations six years. Explicit run holds
override age eligibility; no purge API, worker or scheduler exists. Named disposal ownership and
dependency-aware actual disposal remain future gates. W5Q-03 means **failure/refusal audit is not
complete**, even though success audit and atomic rollback are sound.
The six-year period is the approved company operational policy, not a legal-compliance claim.

## Lock order / retry / concurrency

Current effective import order: actor → shared role → organisation → shared site → import run →
source stream → scoped profile roots/context/questions → binding roots → plots → services →
products → requests → negotiations → proposals → history → batches → source-call services →
visits → observations/receipt/command. Intake replacement acquires stream before predecessor run;
same-organisation owner serialization protects that path. WALD04 begins with the same actor/owner
locks, then profiles/context. Source completion consumes the already locked Portal aggregate.
The adapter's service/request/negotiation/proposal/history subsequence is preserved. Cross-tenant
source collisions refuse and unique visit identity supplies the final guard.

Race coverage is real separate-process MySQL, not SQLite timing. Existing eight backend scenarios
are increased to 20 iterations each; four foundation scenarios remain ten each. Independent QA
adds 20 each of claim/claim, revocation against an actually applied profile receipt, and conflicting
cross-family visit commits. Exact loser exception/effect counts are asserted; no broad acceptance
of arbitrary exceptions. Tests exercise both possible serialization outcomes but do not claim to
prove the absence of every possible deadlock cycle or every separate Sprint 3F/customer-action race.

Recognized 1213/40001 and 1205/HY000 failures are server-signalled at receipt preparation: two
failures then success, persistent transient exhaustion at three, and nontransient 45000/1644 once.
These verify Laravel's bounded transaction retry path, not naturally occurring deadlock frequency.
An initial synthetic 1205 used an unrealistic message and was not classified by Laravel; the
fixture now uses the actual MySQL timeout wording. Authorization/stale/semantic/ordering conflicts
are not transaction concurrency exceptions and are not automatically retried.

## Verification totals and performance

Candidate-only focused baseline: 114 passed / 12 MySQL skips / 410 assertions.

| Final verification | Total | Passed | Skipped | Assertions |
|---|---:|---:|---:|---:|
| WALD05 focused | 203 | 183 | 20 | 573 |
| Combined Wald | 1,091 | 1,060 | 31 | 4,813 |
| Full CustomerApp | 1,325 | 1,279 | 46 | 6,001 |
| MySQL WALD05 non-race | 183 | 182 | 1 SQLite-only | 571 |
| MySQL race / retry / schema | 20 | 20 | 0 | 939 |

The final MySQL gate passed 260 race groups (520 separate worker processes): 160 existing
backend groups, 40 foundation groups and 60 new QA groups. All five final JUnit files record
zero failures and zero errors. MySQL totals across the two disjoint final suites are
202 passed / one SQLite-only skip / 1,510 assertions. Upgrade-script checks are additional.
Rehearsal qualification: the long-lived race runner started before the final namespace-ownership
guard was added; its already-loaded parent classes were not reloaded. Final focused, combined,
full and MySQL non-race runs cover that guard, but the race totals must not be presented as an
immutable full-SHA acceptance run. The next correction gate must rerun races from its frozen candidate.
No skipped guarantee is called a pass.

Ordinary full-suite MySQL-only skips are exactly:

| File under `tests/Feature` | Skips | Disposition |
|---|---:|---|
| `Sprint3eMysqlConcurrencyTest.php` | 14 | Existing separate release gate; not rerun here. |
| `Sprint3eMysqlSchemaTest.php` | 1 | Existing separate release gate; not rerun here. |
| `Wald04/MysqlConcurrencyTest.php` | 7 | Accepted baseline evidence retained; not counted as this task's MySQL passes. |
| `Wald04/MysqlRetryQaTest.php` | 4 | Accepted baseline evidence retained; WALD05 retry is independently tested. |
| `Wald05/MysqlBackendConcurrencyTest.php` | 8 | Independently exercised on disposable MySQL. |
| `Wald05/MysqlBackendQaTest.php` | 8 | Independent QA retry/schema/race cases on disposable MySQL. |
| `Wald05/MysqlFoundationConcurrencyTest.php` | 4 | Independently exercised on disposable MySQL. |

Pint, all 12 task PHP syntax checks, 31 checked documentation links and whitespace checks pass.

Row-only commit reads are constant at 47 for new incomplete visits and 49 for existing-request
completion across 7/100/500 rows, one more than the original 46/48 because W5Q-02 adds a batched
observation check. Writes scale with required per-visit observations/events. Final disposable
MySQL timings were 1.284/4.188/18.035 seconds incomplete and
1.389/6.106/54.808 seconds completion; process memory reached 96 MiB. These are local diagnostics,
not production SLAs. W5Q-04 is the separate receipt-count slope: 60 → 138 SELECTs for 1 → 7 receipts.

Composer validation passes. Composer audit exits 1 with the same eight advisories: five high,
two medium, one low across Filament/CommonMark/Livewire. No dependency changed and remediation
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. This remains a separate release gate.
Build passes after the known Windows sandbox child-process EPERM and an approved local rerun.
No deployment was triggered by the build.

## Commands and reproducible evidence

All test data is synthetic. Command outputs/JUnit and disposable databases remain ignored/local.
No secret or actual source workbook is committed.

```powershell
php -v
composer --version
node -v
npm -v
git --version
php artisan test tests/Feature/Wald05 tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-focused-v2.xml
php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 tests/Unit/Wald05 tests/Feature/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-combined-v2.xml
php artisan test --compact --log-junit=storage/framework/testing/wald05-qa-final-full.xml
# Process-local only, no .env modification:
$env:APP_ENV='testing'; $env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'
$env:DB_PORT='33488'; $env:DB_DATABASE='customerapp_wald05_qa'
$env:DB_USERNAME='root'; $env:DB_PASSWORD=''; $env:DB_URL=''
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/MysqlBackendQaTest.php tests/Feature/Wald05/MysqlBackendConcurrencyTest.php tests/Feature/Wald05/MysqlFoundationConcurrencyTest.php --compact --log-junit=storage/framework/testing/wald05-qa-final-mysql-races.xml
$env:DB_DATABASE='customerapp_wald05_qa_nonrace'
$qaTests = @(rg --files tests/Feature/Wald05 | Where-Object { $_ -notmatch 'Mysql' })
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml @qaTests tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-mysql-nonrace.xml
# Upgrade scripts use their exact separate empty database names, recorded above.
php scripts/verify-wald05-backend-upgrade.php
php scripts/verify-wald05-qa-upgrade.php
php vendor/bin/pint --test
composer validate --strict
composer audit --format=json
npm run build
git diff --check
```

## Existing importer / pilot / recommendation

Retain the old importer but never call it from Wald. Its obsolete stage mapping, source-wide
absence, missing-product zeroing and per-record commits are not the parity oracle. No wholesale
non-main UI/profile merge, retirement or full cross-database legacy comparison was performed.
WALD06 still needs approved parity/cutover, explicit multi-site ordering design, Office UI,
worker/storage/backup/retention operations, real-world corpus, accessibility/device testing and
combined security/release reconciliation. No new source meaning has been invented here.

Complete W5Q-03 and W5Q-04 on a separately reviewed correction candidate, rerun their positive
regressions and the relevant full/MySQL gates, then return for final WALD05 backend acceptance.
Do not freeze either the original candidate or this partially corrected QA branch yet.

## Files changed / production impact

The 18 task files are:

- `app/SourceImport/Integration/BackendStore.php`
- `app/SourceImport/Integration/ImportReview.php`
- `app/SourceImport/Integration/ProjectionSnapshot.php`
- `app/SourceImport/Knowledge/Canonical.php`
- `tests/Feature/Wald05/DedicatedBackendQaTest.php`
- `tests/Feature/Wald05/MysqlBackendQaTest.php`
- `tests/Feature/Wald05/MysqlBackendConcurrencyTest.php`
- `tests/Support/Wald04Fixtures.php`
- `tests/Support/Wald05ConcurrencyWorker.php`
- `tests/Support/Wald05Race.php`
- `tests/Support/Wald05QaProfiles.php`
- `scripts/verify-wald05-qa-upgrade.php`
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/wald-divergence-register.md`
- `documentation/wald/customer-wald05-backend-qa-2026-09-09.md`

The final local commit identity is recorded in the completion handoff.
The unrelated modified Sprint 3E report, untracked `Copy of siteapp1.xlsx` and `output/` were
preserved and excluded. No migration ran outside the disposable schemas; no normal local
application database or production state changed. No main merge/push, tag or deployment.

CUSTOMER-WALD05 dedicated backend QA failed — corrections required
