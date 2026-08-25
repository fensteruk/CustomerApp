# Sprint 3E Dedicated QA Report

## Overall Result

Passed locally after three QA corrections. The candidate stays within the approved date-agreement and alternative-date-negotiation scope; no SiteApp workflow, deployment work, amendments, scheduling or source transport was added.

## Candidate

- Starting branch/SHA: `release-candidate/sprint-3d` at `47e8ccc2f260e38696787bee773d8b7700d74dd7`.
- Final QA branch/SHA: `codex/qa-sprint-3e-date-negotiation`; final SHA is recorded after the QA commit.
- Protected production baseline: `main` at `20d5f123906b7c88f1264f7df5ff90be02c15ec2`.

This QA branch starts from the pre-recovery Sprint 3E candidate. Do not merge it wholesale into current `main`: release preparation must start from current `main`, apply the candidate and QA correction commit, resolve recovery-era conflicts, then rerun relevant checks.

## Functional Coverage

- Office agreement, alternative proposal, customer acceptance/rejection and repeated loops.
- Pending-decision withdrawal, with Date Agreed withdrawal rejection.
- Future weekday and configured-holiday validation, without invented lead-time rules.
- Legacy Approved customer presentation as Date Agreed; Legacy Rejected remains distinct.

## State Machine

Verified `Awaiting Fenster → Awaiting Site User → Awaiting Fenster` per rejection loop, and either `Awaiting Fenster → Date Agreed` or `Awaiting Site User → Date Agreed` for agreement. Only one outstanding alternative can be answered; stale and replayed responses are rejected.

## Authorization

- Global active Fenster Office Staff can decide across customer organisations, as DEC-037 requires.
- Every active Site Manager, Assistant Site Manager and Finishing Foreman can respond only for a current assigned site.
- Cross-organisation customer attempts, revoked/inactive users, unassigned users and substituted proposal UUIDs are rejected server-side.

## Defects Found

| ID | Severity | Behaviour and cause | Fix / status |
| --- | --- | --- | --- |
| QAE-01 | High | Alternative acceptance logged the real transition under “Alternative date accepted,” then a misleading `Date Agreed → Date Agreed` no-op. | Date Agreed now records the only request-state transition; acceptance remains a separate truthful proposal event. Regression test added. Fixed. |
| QAE-02 | High | A source service marked unavailable after submission could still be agreed or accepted because eligibility checked completion but not present source data. | Date decisions now reject missing/unavailable source services. Regression test added. Fixed. |
| QAE-03 | Medium | Customer accept/reject controls still appeared after source data became unavailable, although the server should refuse the action. | Controls are hidden for unavailable source services and the availability flag is loaded. Regression test added. Fixed. |

## Repeated Negotiation

Three rejection cycles followed by acceptance were exercised. Proposal order is stable, the customer-requested proposal is superseded once, rejected alternatives remain retained, and acceptance closes the negotiation. Duplicate response fails without another state change.

## Earlier-Date Handling

Early requested dates fail until Office explicitly acknowledges the exception. The direct action and HTTP endpoint were tested, including a forged false acknowledgement. The acknowledgement evidence is retained separately from the Date Agreed transition.

## Withdrawal

Withdrawal is permitted in `Submitted`, `Awaiting Fenster` and `Awaiting Site User`; it is blocked after Date Agreed. Lifecycle confirmation, authorisation and history remain covered by regression tests.

## Legacy Compatibility

Legacy Approved records remain untouched and display as Date Agreed without manufacturing a negotiation. Legacy Rejected remains Rejected, with no proposal-flow customer response leaked.

## Source Completion Precedence

Completion blocks late decisions and customer actions. Source-import regression confirms completion closes negotiations, supersedes unanswered alternatives and clears active conflict state. A completion reversal records a safe source event and does not silently reopen an obsolete request when unsafe.

## Notifications

Post-commit agreement, proposal, acceptance and rejection events were checked. Agreement/proposal go to the original submitter; acceptance/rejection go to active global Office Staff. Proposal event keys use proposal UUIDs for repeat-loop idempotency. Private Office context is absent from customer notification data and pages. Notification failure remains non-transactional to the core transition under existing regression coverage.

## Race / Replay / Stale State

Actions lock request, proposal and negotiation rows, then reauthorise and revalidate within a transaction. Coverage includes duplicate response, foreign proposal UUID, source completion/unavailability after page load and revoked/unassigned users. No live MySQL concurrency rehearsal was possible.

## History

History is append-only, ordered and actor-attributed. QAE-01 makes the timeline truthful: proposal acceptance followed by one real Date Agreed status transition with correct before/after state.

## UI / Responsive / Accessibility

Browser QA exercised customer submission, Office proposal, customer request detail and acceptance to Date Agreed against local preview users. At 320px, 768px and 1440px document width remained below viewport width. The detail has a skip link, semantic headings, labelled controls, native required rejection reason, labelled history and primary touch targets of at least 48px. Private Office context did not appear in the customer DOM. The fresh preview notification panel loaded its empty state successfully.

Physical-device and assistive-technology screen-reader testing remain release limitations.

## Sprint 3D Regression

Sprint 3D bulk matrix, signed review/final-submit, atomicity, conflict handling, query budget and notification context passed with Sprint 3E coverage. No regression was found.

## SQLite

Confirmed `DB_CONNECTION=sqlite` and local target `C:\Users\JoshO\Documents\CustomerApp\database\database.sqlite`. The authorised `php artisan migrate:fresh --seed` completed successfully, followed by another full Sprint 3E QA run.

## MySQL

The PHP MySQL driver is installed, but no safe MySQL database, host or credentials are configured. No connection was attempted. MySQL migration, unique-index and concurrent-lock rehearsal remain required before production release.

## Tests

- Sprint 3E focused/hostile suite after fresh SQLite: 25 tests, 133 assertions — passed.
- Sprint 3E + Sprint 3D + source import + notifications: 59 tests, 358 assertions — passed.
- Wider feature/unit suite, in bounded groups: 196 tests, 1,017 assertions total — passed.
- `composer validate --no-check-publish`, `vendor/bin/pint --test` and `git diff --check` — passed.
- `npm run build` — passed. The sandboxed attempt hit Windows `spawn EPERM`; the approved rerun completed successfully.

## Composer / npm / Build / Pint

Composer is valid. No packages or lockfiles changed. Pint is clean. The production Vite build completed. No dependency audit or upgrade was performed in this gate.

## Remaining Risks

- MySQL migration/locking/index behaviour lacks a disposable MySQL rehearsal.
- The weekday-only holiday provider needs an approved UK bank-holiday data owner and source.
- Physical-device and assistive-technology evidence is outstanding.
- The candidate predates current `main` recovery commits; release preparation must transplant it onto `main` and repeat checks before merge.

## Release Recommendation

It is safe to prepare a non-deploying release branch from current `main` and apply the validated Sprint 3E plus QA commits. Do not merge to `main`, deploy, or run a production migration until reconciliation and the MySQL release gate pass.

Sprint 3E QA passed — ready for release preparation
