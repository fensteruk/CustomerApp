# Sprint 3F — MySQL Test Isolation Remediation

Date: 3 September 2026.
Branch: `feature/sprint-3f-date-amendments`.
Base HEAD: `0873bac79edf578e9f4a9417e3cafae34e8aa925`; Sprint 3F and this correction remain uncommitted.

**Result: FAIL — a genuine Office amendment decision race was exposed.**
The original fixture contamination is corrected. Application remediation and further
gate execution stopped as instructed; the application files remain unchanged by this task.

## Scope and original failure

This task changes tests/helpers and this report only. No application logic, product
semantics, source mappings, migrations, dependencies, workflow triggers or UI changed.
The separate Composer security-remediation branch is not incorporated.

The prior MySQL gate failed at the ordinary amendment test's exact request-count
assertion: expected 1, actual 32. Its preceding process-race suite had committed 31
requests for 31 different services. It did not use a parent rollback transaction
(correctly, because children need committed fixtures), but supplied no cleanup.
Laravel's shared RefreshDatabaseState had already marked migrations complete.
The following RefreshDatabase test therefore began a transaction around the dirty
database rather than clearing it. Its own request was the 32nd. SQLite skipped the
MySQL races and never encountered that contamination.

## Test-only isolation correction

`tests/Support/CommittedMysqlFixtureScope.php` owns a serial committed-fixture scope.
It snapshots concrete IDs and complete baseline rows in the 19 domain/reference tables
touched by these fixtures. Cleanup deletes only newly created IDs in child-first FK-safe
order, with foreign keys still enabled. It then asserts every baseline row is identical.
It does not truncate, reset IDs, wipe databases, disable constraints or delete by prefix.

The concurrency file creates a scope before each test and cleans it after each test,
including assertion failures. Nested scopes bound repeated races and the cleanup regression.
The worker runner stops any remaining child before scope cleanup on error/timeout.
These tests require exclusive serial ownership of a disposable MySQL database; do not
run unrelated processes against the same database during their scope.

Safety guards require APP_ENV=testing, actual MySQL, an explicitly test-named database
(`customerapp_test` or `portal_sprint3f_gate_*`) and no parent transaction. Workers
apply the same guards. A standalone concurrency-first run creates missing schema via
normal forward migrations. It does not silently reset existing data.

Every ordinary Sprint 3F test now asserts zero requests, negotiations, proposals,
history and notifications at its starting boundary. The existing exact count of one
request is unchanged. A regression intentionally terminates a worker with exit 17
after it commits an agreement, cleans up its data, proves a pre-existing role row
unchanged, then creates one ordinary request and still asserts exactly one.

## Coverage added

- Amendment Office Accept Requested versus Propose Alternative: one success, one
  validation failure; consistent proposals, resulting date and history.
- Completion versus amendment Office proposal, both orderings.
- Completion versus amendment customer rejection, both orderings.
- Ten separate-process amendment acceptance/completion races, alternating five per
  ordering; exact agreement/history/notification/source-event counts, cleared conflict
  keys, repeated-source idempotency, completion reversal and rejection of stale reuse.
- Six end-of-source-transaction fault-injection datasets: SQLSTATE 40001, MySQL 1213,
  MySQL 1205, exhausted retry limit, non-transient 1062, and business validation.
- Explicit before-attempt checks for authoritative On Hold/incomplete source state;
  post-write injection proves all completion writes roll back before retry.
- Direct amendment route matrix for all three Site User roles, Office, wrong site,
  wrong customer, unassigned, inactive and guest accounts.
- Office decision denial for Site Users and cross-request amendment UUID substitution.
  Existing request/proposal UUID and revoked-between-review-and-submit tests remain.
- Exact notification counts for request, proposal, acceptance/rejection; Office
  rollback; action and listener replay; existing customer rollback tests retained.
- Explicit legacy Approved customer presentation and unchanged original history.

All original assertions and genuine child PHP/MySQL connections are retained.
Unexpected worker exception types now fail the runner; a deadlock cannot count as a
valid stale loser. Optional per-race JSON evidence records both worker outcomes and
their durable-state snapshots before fixtures are cleaned.

## Retry injection calibration

An initial harness-only run passed 32 cases, then the exhaustion dataset observed
zero injected attempts: Eloquent skipped its final plot update because the factory
and import shared the same timestamp. This was not an application retry failure.
The synthetic source record now supplies a distinct source-updated timestamp so the
target write is always dirty. Exact assertions remain. All six calibrated datasets
then passed, 132 assertions: three whole-transaction attempts for approved transient
conditions, one for non-transient failures, fresh state each time and no duplicates.

## Disposable environment and verification

Fresh portable MySQL Community 8.4.11 / InnoDB / REPEATABLE-READ on
`127.0.0.1:13385`. No Windows service, Docker, remote tunnel, Forge or production
connection. Targets:

- `portal_sprint3f_gate_clean_20260903b` with `sprint3f_gate@127.0.0.1`.
- `portal_sprint3f_gate_upgrade_20260903b` with `sprint3f_upgrade@127.0.0.1`.

Each user has privileges only on its exact database; escaped underscores prevent
wildcard grants. Environment and actual PDO database/version/account verified before
test/destructive commands. Mail=array, queue=sync, cache/session=array, broadcast=null,
temporary app key and temporary database credentials. No production credentials loaded.

Both disposable databases were dropped and recreated empty after harness calibration,
before the final migration and focused checks. Clean migration: all 12 passed,
000014 included, nothing pending. Populated upgrade rehearsal: 11 main-era migrations
then only 000014; all pre-existing values/counts preserved in request, negotiation,
proposal, history and notification tables. This is a synthetic baseline, not a copy of
production data.

## Confirmed application defect: two competing Office decisions both commit

Failing test: `Sprint3F Office amendment acceptance and alternative proposal have one safe winner`
in `tests/Feature/Sprint3eMysqlConcurrencyTest.php:357`.

Failure output: `Failed asserting that actual size 2 matches expected size 1.`
The two child PHP processes use separate genuine MySQL connections. One invokes
`amend-agree` (Accept Requested Amendment), the other `amend-propose` (Propose Alternative),
against the same active amendment cycle. Both returned `ok: true`, with no exception or
SQLSTATE error. The test correctly requires one winner and one safe validation loser.

Saved snapshots show the alternative proposal committed first, followed by acceptance.
Synthetic evidence identifiers: request 98, customer 269, accepting Office user 271,
proposing Office user 272, projected plot 209. These are disposable fixture IDs only.

| State | After proposal | After subsequent acceptance |
| --- | --- | --- |
| Request | Amendment On Hold; agreed date 2026-09-11 | Date Agreed; agreed date 2026-10-02 |
| Amendment cycle | Open; active key `amendment:98` | Date Agreed; active key null |
| Requested-date proposal | Superseded by Office 272 | Accepted by Office 271 |
| Alternative proposal | Awaiting Response | **Still Awaiting Response inside the closed cycle** |
| Source service | Present, incomplete | Present, incomplete |
| Request conflict key | Present | Present (normal for an agreed, incomplete request) |

History is ordered: original Date Agreed (Office 271), Amendment Requested (customer 269),
Alternative Date Proposed (Office 272), Date Agreed (Office 271). Notifications total five:
original agreement to customer 269; amendment attention to each of Office 271 and 272;
alternative proposal to customer 269; new agreement to customer 269. No source event
was involved. Thus this is not a duplicate-fixture count or escaped deadlock: two
conflicting decisions were committed and both customer messages persisted.

### Code-inspection explanation (inference, not a captured SQL lock trace)

`LockCallOffDateNegotiationAggregateAction::handle()` performs a non-locking association
read before the service/request locking reads. On the tested REPEATABLE-READ connection,
that can establish a snapshot before waiting for the competing transaction.
`ResolveCallOffNegotiationAction::forOffice()` locks the cycle but checks for a pending
alternative using a **non-locking `exists()`** query. Proposing an amendment alternative
leaves the request Amendment On Hold and the same cycle UUID open; those guards cannot
detect the competing proposal. The consistent read can miss that newly committed proposal.
`requestedProposal()` then locks the requested proposal, but
`AgreeRequestedCallOffDateAction` sets it Accepted without requiring it to remain
Awaiting Response; a freshly read Superseded proposal can therefore be overwritten.
The cycle closes while the other proposal remains pending.

This is the best-supported cause from the observed state and actual query paths.
There was no general SQL/lock trace, so the precise scheduling of the snapshot is not
claimed as directly observed. No change to isolation level, lock order or application
logic was attempted. A narrow application correction must make decisive pending-proposal
validation use current authoritative state and reject stale requested-proposal acceptance.

## Auditable test results and limits

The final focused run stopped at the application failure: **93 executed, 92 passed,
1 failed, 718 assertions, zero errors/skips; 89.958 seconds** (runner wall time).
Within it the concurrency file executed 24 tests: 23 passed, 1 failed, 323 assertions.
Ordinary Sprint 3F tests and the automated schema test were later in the run and were
not reached. Direct schema/FK/index output was captured separately in `schema-final.log`;
do not equate that inspection with a completed automated schema gate.

Earlier disposable harness calibration produced 32 passes and one harness-injection
failure (33 executed, 670 assertions). That harness-only failure was corrected as
described above. These earlier passes are partial evidence, not final release sign-off:

| Race/check | Observed evidence |
| --- | --- |
| Existing 3E races, including retained 3F acceptance/source checks | 22 existing concurrency tests passed in the final focused run |
| Intentional worker crash and cleanup regression | Passed in final focused run; reference row preserved |
| Amendment Office acceptance vs alternative proposal | Passed once in calibration, **failed in final run**; timing-dependent defect |
| Source completion vs amendment Office proposal | Both orderings passed during calibration; not reached in final run |
| Source completion vs amendment customer rejection | Both orderings passed during calibration; not reached in final run |
| Amendment alternative acceptance vs completion | Ten iterations passed during calibration, five per ordering; 206 assertions |
| Closed amendment after completion reversal | Included in those ten iterations; remained closed/non-actionable |
| Repeated completion/source import | Included in ten iterations; no duplicate completion event/history/notification |
| Bounded retry fault injection after calibration fix | Separate run: 6 passed, 132 assertions; all attempt counts/fresh-state checks passed |

Ten successful acceptance/completion iterations do not clear the separate Office
decision failure. Whole-suite order robustness in both directions was **not run**;
the targeted cleanup regression and empty post-failure state are evidence for the
isolation correction, not a claim that both full order permutations passed.
Full MySQL application suite: **not run (zero full-suite executions)** after the
stop condition. Ordinary amendment notification atomicity, full direct-route role/tenant
matrix, legacy Approved and replay assertions passed locally but were **not reached on
MySQL in the final run**. These remain mandatory after application correction.

Fault injection proved maximum three complete attempts only for SQLSTATE 40001 or
MySQL 1205/1213, single attempts for integrity/validation errors, original state on
each retry, rollback on exhaustion and no duplicate completion/history/notification
or import audit. No retry policy was changed.

Local regression after final test edits: 311 total; 275 passed, 36 MySQL-only skipped;
1,700 assertions, 44.022 seconds. Pint passed. Composer validate passed. Composer audit
still reports eight advisories across filament/filament, league/commonmark and
livewire/livewire (five high, two medium, one low). Dependencies were not upgraded.
Frontend build passed; diff check passed.

All 53 ordinary Sprint 3F cases passed in the local regression. Local success does not
override the real MySQL race failure or substitute for the unreached MySQL coverage.

## Evidence and cleanup

Evidence directory (outside committed source):
`C:\Users\JoshO\Documents\CustomerApp\.cursor\sprint3f-mysql-regate-20260903`.
Key artifacts: `clean-final.log`, `upgrade-final.log`, `schema-final.log`, `focused.log`,
`focused.xml`, `concurrency.xml`, `retry.xml`, `local-test-final.log`, `pint-final.log`,
`durable-state.json`, `cleanup.log` and
`race-evidence/race-6ec39390-3408-4d0a-a216-a89b87cdfe31.json`.
The raw race evidence retains both worker outcomes before fixture cleanup.

After the failed race, read-only inspection found zero requests, zero distinct request
services, and no remaining proposals, negotiations, histories, notifications or source
events. The cleanup hooks therefore also ran successfully on assertion failure.

Final cleanup confirmed both disposable databases and both restricted users absent;
temporary server PID 11068 stopped; port 13385 closed. The data directory, encrypted
temporary credential file, downloaded runtime and archive were removed. Non-secret
logs and test evidence are retained. No tunnel or SSH keys were created. No production
connection, production credential load, Forge action, deployment or production change
occurred. Disposable data is intentionally deleted; diagnostic snapshots remain.

Task changes are limited to the two test files, the worker helper, new committed-fixture
scope helper and this document. Pre-existing uncommitted Sprint 3F application/UI/docs
and unrelated root-worktree changes were preserved. No commit SHA was created or pushed.

## Product and release boundaries

Still pending: final amendment reason list and overall plot-status treatment while
On Hold. Synthetic reasons remain test-only. Those decisions are not MySQL test failures.
The application race must be corrected in a separate authorised task before another
fresh MySQL re-gate, including full ordinary/focused/application suites and both order
permutations. Sprint 3F is **not technically ready** for dedicated QA sign-off/release.
Dedicated QA, browser/accessibility QA and separate dependency-security reconciliation
remain required. No deployment, main change, commit or push performed.

Sprint 3F MySQL re-gate failed — application correction required
