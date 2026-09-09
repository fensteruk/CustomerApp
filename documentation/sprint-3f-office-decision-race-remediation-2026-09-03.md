# Sprint 3F — Office Decision Race Remediation

Date: 3 September 2026.
Branch: `feature/sprint-3f-date-amendments`.
Scope: narrow application concurrency correction; no product, UI, import, dependency or schema changes.

## Result

**FIXED.** The exact Office race was reproduced on
disposable MySQL 8.4.11. The corrected synchronized regression passes both decision
orderings, both test-suite orders pass, and the full MySQL application suite passes
all 313 tests (2,743 assertions; no failures, errors or skips).

The correction is technically ready for dedicated QA. The user approved separate
baseline and correction commits after verification. No release approval is claimed.

This report supersedes the race-blocked result in
`sprint-3f-mysql-test-isolation-remediation-2026-09-03.md` only for checks explicitly
rerun here. Earlier failure evidence is retained, not rewritten.

## Proven root cause

Both actions call `LockCallOffDateNegotiationAggregateAction`. Its initial non-locking
request→service association lookup establishes an InnoDB REPEATABLE READ consistent
snapshot before the service/request locks. That association lookup does not itself
make a decision and remains unchanged to preserve service-first locking.

Both actions subsequently lock service → request → amendment cycle. However,
`ResolveCallOffNegotiationAction::forOffice()` used a plain pending-proposal
`exists()` query. Locking the aggregate does not refresh that earlier consistent-read
snapshot. An alternative proposal leaves the request AmendmentOnHold and the cycle Open,
with the same cycle UUID. Therefore the aggregate status/UUID guards alone do not
detect a competing proposal.

The synchronized regression deliberately establishes both real transaction snapshots
before either decision. It lets the proposing worker commit before releasing the
accepting worker into its aggregate locking reads. Captured SQL shows:

1. Both transactions perform the non-locking association read.
2. The proposing worker locks the aggregate, creates its alternative and commits.
3. The accepting worker locks the now-current service, request and open cycle.
4. Its plain proposal `exists()` still uses the older snapshot and misses the alternative.
5. It locks the original requested proposal, now Superseded, marks it Accepted and closes
   the cycle. Both decision histories and both notifications are committed.

Before correction, the first proposal-first iteration failed with **two successes
instead of one**, reproducing the closed DateAgreed cycle containing an
AwaitingResponse alternative. It was not an escaped deadlock or test-fixture leak.
The failure's SQL trace and durable snapshots are retained in
`race-evidence/race-before/race-a3fa2fca-f387-4eb2-88cc-506ab13da07c.json`.

## Smallest robust correction

Only two application files change:

- `ResolveCallOffNegotiationAction.php`: the decisive pending alternative check is now
  an ordered, cycle-scoped `SELECT id … LIMIT 1 FOR UPDATE` current read.
- `ProposeAlternativeCallOffDateAction.php`: removes its redundant non-locking
  pending-proposal check. Both Office paths use the same authoritative check.

The query is constrained by cycle ID and proposal state, backed by the existing
`negotiation_proposal_status_index`. There are no table locks, new indexes or migrations.
The aggregate locks already serialize writers when the pending-proposal result is empty.
No isolation-level change or optimistic version column is necessary.

Lock order stays:

service → request → amendment/negotiation → proposal → history.

Eligibility, source-completion checks and actor authorization remain inside the
existing decisive transactions. Acceptance-first is rejected by the loser's current
request/cycle state; proposal-first is rejected by the current pending-proposal read.

No strict rule requiring the original requested proposal to remain AwaitingResponse
was introduced: a superseded original date can still be agreed after its alternatives
have been rejected and the cycle legitimately returns to Awaiting Fenster. Blocking
that established negotiation path would be a product change. It is the current open
cycle with no pending alternative that authorizes the Office decision.

## Regression and synchronization

The existing genuine multi-process MySQL test runner now optionally synchronizes Office
transactions after their first non-locking association query. A test-only query listener
signals both snapshots, then holds the nominated loser until the winner has committed.
No production hooks, action mocks, artificial source semantics or isolation changes
are used. Child processes remain genuine independent PHP/PDO connections.

Two datasets run five iterations each: proposing first and accepting first.
The existing unsynchronized Office race is also retained. Worker output optionally
captures SQL order, transaction levels, scheduling markers, exception class, SQLSTATE
and driver code. Existing fixture cleanup remains unchanged and runs after failure too.

The ten synchronized post-fix races assert:

- one successful action and one ValidationException, with the nominated winner;
- coherent request status, agreed date, cycle status/key/resulting date/closure;
- unchanged active conflict key;
- exactly one requested proposal accepted, or one pending alternative with the
  requested proposal superseded;
- no pending alternative in a closed DateAgreed cycle;
- exactly one new Office decision history event with the winning actor;
- every pre-existing history record unchanged;
- exactly one new customer notification with the existing event-key format;
- no SQLSTATE/driver error counted as an acceptable loser.

Standalone repeat result: **10/10 races passed** (2 datasets, 292 assertions).
Winner distribution: 5 Accept Requested, 5 Propose Alternative.
Each losing worker reported validation failure. Zero escaping deadlocks, lock timeouts
or other SQL errors were observed. No Office retry loop was introduced.

The same ten iterations also passed in the focused gate, each test-order gate and
the full application gate: **50/50 synchronized races in total**, 25 Accept Requested
wins and 25 Propose Alternative wins, with 50 validation losers and zero SQL errors.

## Notifications and history

Production event/listener/history code is unchanged. A failed transaction cannot reach
the post-transaction event dispatch. Existing ShouldDispatchAfterCommit handling and
idempotency keys are preserved. The losing decision has no status, proposal, history,
notification or conflict-key effect; no compensating cleanup is used in application code.

## Disposable MySQL gate

Runtime: official Oracle-signed portable MySQL Community 8.4.11, InnoDB,
REPEATABLE-READ, loopback `127.0.0.1:13386`. No Windows service or remote connection.

Fresh exact targets:

- `portal_sprint3f_gate_clean_20260903race`, restricted `sprint3f_gate@127.0.0.1`;
- `portal_sprint3f_gate_upgrade_20260903race`, restricted `sprint3f_upgrade@127.0.0.1`.

The two users have privileges only on their respective exact database names
(underscores escaped in grants). APP_ENV=testing, mail=array, queue=sync,
cache/session=array, broadcast=null and temporary credentials/key are used.
Actual PDO database, account, port, version and isolation are checked before gate steps.
No production credentials, production data, Forge or deployment operations are involved.

After reproduction/targeted validation, both disposable targets were recreated empty
for the final re-gate. All 12 clean migrations passed. The non-empty upgrade rehearsal
ran the 11 main-era migrations, populated a real synthetic agreement, applied 000014,
and preserved every existing value/count in requests, negotiations, proposals, histories
and notifications. Direct schema inspection confirmed the existing keys and constraints.

## Verification results

| Check | Result |
| --- | --- |
| Exact pre-fix synchronized reproduction | Failed as expected: 2 successes; 1 test, 5 assertions |
| Standalone synchronized post-fix race | 2 datasets / 10 iterations passed; 292 assertions |
| Ordinary Sprint 3F, SQLite | 53 passed; 487 assertions |
| Full SQLite suite | 275 passed, 38 MySQL-only skipped; 1,700 assertions |
| Fresh MySQL clean migrations | 12 passed, none pending |
| Populated MySQL upgrade | Passed, five populated domain/audit tables preserved |
| Focused MySQL workflow/security gate | 160 passed; 1,925 assertions; no skips/errors/failures |
| Ordinary Sprint 3F within focused MySQL | All 53 passed; 487 assertions |
| Concurrency/retry within focused MySQL | All 37 passed; 1,023 assertions |
| Automated MySQL schema test | Passed; 20 assertions |
| MySQL concurrency-first → ordinary | 90 passed; 1,510 assertions |
| MySQL ordinary-first → concurrency | 90 passed; 1,510 assertions |
| Full MySQL application suite | 313 passed; 2,743 assertions; no skips/errors/failures |
| Pint / diff check | Passed |

The focused concurrency run includes Office acceptance/proposal versus completion
in both orderings, customer acceptance versus rejection, the retained 3E races and
six bounded source-retry fault-injection datasets. Retry datasets passed 132 assertions:
maximum three whole-transaction attempts only for SQLSTATE 40001 or MySQL 1205/1213;
integrity/business errors were not broadened into retries. No import/retry code changed.

No frontend build was required for this correction: frontend sources and dependencies
are unchanged. Existing dependency advisories remain a separate release concern.

## Files changed by this correction

Relative to the preserved pre-task Sprint 3F baseline commit:

1. `app/Actions/CallOff/ResolveCallOffNegotiationAction.php`
2. `app/Actions/CallOff/ProposeAlternativeCallOffDateAction.php`
3. `tests/Feature/Sprint3eMysqlConcurrencyTest.php`
4. `tests/Support/Sprint3eMysqlConcurrencyWorker.php`
5. `documentation/sprint-3f-office-decision-race-remediation-2026-09-03.md`

No amendment reason list, On Hold label/aggregation, source importer, dependency,
migration, route, controller or view was changed by this task.
The existing committed-fixture isolation helper and ordinary Sprint 3F tests are unchanged.

## Git and remaining decisions

The task started with the entire Sprint 3F foundation and previous test-isolation work
uncommitted at base `0873bac79edf578e9f4a9417e3cafae34e8aa925` on
`feature/sprint-3f-date-amendments`. These 46 pre-existing changed
files include the new shared resolver itself, so a correction-only commit cannot
also provide a coherent, independently testable baseline without including earlier work.

The user subsequently approved recording that exact pre-task baseline separately,
then committing only this correction. Baseline commit
`4773c372cae947e58e920de5251250c52f2fc51f` (`feat: record Sprint 3F date amendment baseline`)
contains the 46 preserved files. It intentionally retains the historical race defect;
it is not the corrected release candidate.

The following correction commit, `fix: serialize competing Office amendment decisions`,
contains only the four source/test changes listed above and this report. This report's
containing commit identifies the corrected candidate without a self-referential hash.
The baseline was staged from the saved file snapshot; the tested application files
were never replaced or reverted. No application/test content changed after the recorded
full MySQL gate. The pre-task source snapshot remains available for comparison.

The same product decisions remain unresolved and untouched:

- approved amendment reason list;
- On Hold overall plot-status treatment.

Dedicated QA and release authorization remain separate. Existing dependency-security
reconciliation is not part of this race correction. No main merge, push or deployment.

## Evidence and cleanup

Non-secret logs, JUnit results and per-race SQL/durable-state evidence are under
`C:/Users/JoshO/Documents/CustomerApp/.cursor/sprint3f-office-race-20260903`.
Final inspection found zero retained request or notification fixtures in the clean
gate database. Both disposable databases and both temporary accounts were dropped
and their absence verified. The task's local server was shut down; its data directory,
encrypted credentials, downloaded runtime and archive were removed after exact-path
and process checks. No production/user data was deleted. Test logs, evidence and the
pre-task source snapshot remain available; disposable test data is not retained.
