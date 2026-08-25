# Sprint 3E MySQL Concurrency Defect Remediation

## Status

In progress. Sprint 3E remains blocked and has not been merged, deployed or run against
Forge/production.

## Reproduction

The disposable GitHub-hosted MySQL 8.4 gate reproduced source completion racing an
authorised customer alternative-date acceptance twice. The source import worker failed
with `SQLSTATE[40001]` / MySQL 1213 while executing the active-request `FOR UPDATE`
query for the projected service. The clean migration and main-to-Sprint-3E upgrade jobs
passed. The ephemeral test database was discarded after each run.

A second race, source availability loss versus requested-date agreement, did not prove a
durable domain corruption but exposed an enum-object assertion and insufficient worker
state capture. The gate now records final, redacted durable state after both successful
and failed workers.

## Root Cause and Canonical Lock Order

The actual circular wait was caused by incompatible aggregate order:

| Path | Previous order |
| --- | --- |
| Source completion | projected plot service → active request → open negotiations → proposals |
| Portal agree/propose/accept/reject | request → source service check → proposal/negotiation |

All date transitions now use one compatible order:

```text
projected_plot_services → call_off_requests → call_off_date_negotiations → call_off_date_proposals → call_off_status_histories
```

The portal obtains a fresh request/service association, locks the source-owned service,
then locks the request. Source availability and completion are evaluated only after those
locks. Source completion locks requests, negotiations and pending proposals in the same
ordered sequence before superseding proposals, completing negotiations and clearing the
request conflict key.

## Precedence and Retry

- If completion locks first, later Office/customer transitions re-read source state and
  fail as stale/not actionable. No agreement, success notification or duplicate history
  is created.
- If a date decision commits first, a later completion records the source completion,
  supersedes open negotiation state and leaves the request `Completed`; a truthful earlier
  Date Agreed history is retained.
- Source availability loss locks the service before the portal action's decisive check.
  A later action fails safely. An already-open proposal is retained as audit history but
  is not actionable because source availability is authoritative.
- Source imports use at most three whole-record attempts only for MySQL SQLSTATE 40001
  or MySQL 1205/1213. Each retry starts from fresh committed state with a 25/50ms bounded
  delay. Validation errors and other failures never retry. Notifications remain after
  commit, so a rolled-back/retried attempt cannot emit a success notification.

## Gate Evidence Required

The revised real-MySQL worker returns the committed service/source status, request status,
agreed date, conflict key, negotiation/proposal state, ordered history actor/event data,
notifications and source events after success or failure. The gate covers completion versus
accept alternative, accept requested, propose alternative and reject alternative, and
availability loss versus both acceptance paths. The alternative-acceptance completion race
alternates both precedence orderings over ten genuine separate-process iterations.

## Date Agreed Notification Race — 25 August 2026

The first isolated Forge MySQL 8.4.10 re-gate passed clean migration/seeding (11
migrations) and the 20-assertion schema check, but the focused concurrency suite reported
12/14 passing tests and 117 assertions. The two failures were the acceptance-first
completion ordering and its ten-process repetition: source completion correctly left the
service and request `Completed`, cleared the conflict key, retained one truthful
`DateAgreed` history and recorded one source completion event, but there were zero
`CallOffDateAgreed` notifications where exactly one was required.

The trace showed this was not a mutable-state re-read or after-commit suppression. Both
date-agreement events implement `ShouldDispatchAfterCommit`, and the listener reloads the
request only after the committing transaction. Requested-date agreement emitted
`CallOffDateAgreed`; alternative acceptance emitted only `CallOffAlternativeAccepted`.
That Office-recipient event did not create the submitting site user's Date Agreed
notification, so an alternative acceptance had no Date Agreed event to persist at T2.

The narrow local correction emits the existing `CallOffDateAgreed` event after a committed
alternative acceptance, alongside the existing Office-facing alternative-accepted event.
The first implementation still failed because completion could commit between the event
dispatch and listener reload. The listener now permits this event only where the current
request is `Completed` *and* immutable `date_agreed` history exists. It retains
after-commit dispatch, existing recipient authorisation and the existing per-recipient
`date_agreed:<request UUID>` idempotency key. A rolled-back/stale acceptance still emits
neither event; completion-first therefore remains zero. Acceptance-first is eligible to
create exactly one historical Date Agreed notification even if later source completion
changes current state to `Completed`; completion emits no notification.

## Remaining Work

Local SQLite verification after the correction passed fresh migration/seeding, the focused
date-negotiation suite (10 tests, 44 assertions), and the full suite (197 passed, 15
skipped, 1,019 assertions). Pint, Composer validation/audit and the Vite build passed.
The dedicated disposable MySQL re-gate, including ten acceptance-first/completion-first
iterations and a diagnosable full suite, remains required before release.
