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

## Remaining Work

Run the complete local regression and the existing GitHub disposable MySQL 8.4 gate. Do
not treat the code change as a release result until those races pass repeatedly.
