# Sprint 3F — Date Amendments After Date Agreed

Date: 3 September 2026.
Status: **BLOCKED — product confirmation and release gates outstanding. Not QA-approved or complete.**

## Branch and scope

Implementation worktree: `C:/Users/JoshO/Documents/CustomerApp/.cursor/worktrees/sprint-3f-date-amendments`.
Branch: `feature/sprint-3f-date-amendments`.
Base/HEAD: `0873bac79edf578e9f4a9417e3cafae34e8aa925` (local main).
Changes are uncommitted for review; HEAD is the base, not a feature commit.

This base contains Sprint 3E and the Office-organisation correction. Older top-level
25 August documents saying Sprint 3E is absent from main are historical, superseded
by the inspected repository and this task's explicit description of the live workflow.
No production inspection or deployment was performed in this task.

No main writes, merges, pushes, SiteApp changes, Wald/import changes, GitHub Actions
changes, or dependency/lockfile changes. The original checkout's unrelated dirty
files are preserved. The separate security dependency branch is not included.

## Domain and schema

An amendment is an existing `CallOffDateNegotiation` with purpose `amendment`,
linked to the original request. There is no second negotiation engine or duplicate
call-off. UUID, prior agreed date, opened/closed timestamps, status, customer_response,
internal_reason and nullable unique active_negotiation_key already exist.

Forward migration `2026_09_03_000014_add_date_amendment_metadata.php` adds:

- requested_date and resulting_agreed_date;
- reason_code (80 characters) and reason_label snapshot;
- requested_by_user_id, requester_name and requester_role snapshots;
- is_urgent, is_early_date_exception and normal_earliest_date.

The requester FK uses the short MySQL-safe name `negotiation_requester_fk` and
restricts deletion. Existing rows receive nullable/default metadata; no history or
original dates are backfilled or rewritten. Do not roll this migration back after
recording real amendments: dropping the new columns would lose amendment metadata.

The active cycle key is exactly `amendment:<local request integer id>`, set only by
the initiation action and cleared on agreement or source completion. The existing
unique index prohibits duplicate non-null keys on SQLite and MySQL. The locked request
and eligibility check also prohibit any other open cycle. The existing request-level
plot/service active conflict key stays active throughout On Hold and Date Agreed.

## State machine and reuse

| Event | Request snapshot | Amendment cycle | Next action |
| --- | --- | --- | --- |
| Assigned customer requests change after Date Agreed | AmendmentOnHold | Open, original requested proposal | Awaiting Fenster |
| Office proposes alternative | AmendmentOnHold | Open, alternative awaiting response | Awaiting Site User |
| Site User rejects alternative with reason | AmendmentOnHold | Open, rejected proposal retained | Awaiting Fenster |
| Office accepts requested change | DateAgreed | DateAgreed, closed, resulting date recorded | None |
| Site User accepts current alternative | DateAgreed | DateAgreed, closed, resulting date recorded | None |
| Source completion during either waiting state | Completed | Completed, closed; pending proposals superseded | None |
| Source completion reverses | Old request stays closed | No reopening | Source projection reflects reversal |

`ResolveCallOffNegotiationAction` selects the initial or amendment cycle for the
four existing agree/propose/accept/reject actions. Amendment Office actions require
the current negotiation UUID. Customer alternatives are scoped to the authorised
request before acquiring negotiation/proposal locks. Superseded or already answered
proposals and stale cycle UUIDs cannot act again.

The original request requested_date is never replaced by an amendment date.
The current agreed_date changes only when a new agreement commits. Historical dates,
Office decisions, customer responses and ordered proposals remain retained.
Multiple successive amendments keep distinct cycle UUIDs and notifications.

## On Hold and current-date presentation

On Hold is a request snapshot, not a deletion of the original agreement.
The previous agreed date remains stored and visible as historical context.
The shared read model exposes no current confirmed date while On Hold, after source
completion, or after completion reversal of the closed request. Service labels and
filters distinguish On Hold from Date Agreed. No calendar has been built.

Before Sprint 3F, AmendmentOnHold contributed to overall Call-Offs In Progress via
AwaitingDate. That aggregate result is preserved, with partial/full source completion
still taking precedence. Product was asked to confirm it explicitly; no new aggregation
policy has been invented.

## Authorization and review

Only active, currently assigned Site Manager, Assistant Site Manager and Finishing
Foreman users can initiate. The active site must match the request. Office cannot
initiate, but active Office Staff have the existing global review authority without
needing a customer organisation. Any currently assigned Site User can respond;
the actual requester and responder are independently attributed.

The form requires a new requested date, configured reason and optional customer_response
(maximum 2,000 characters). Review data is held server-side, with a hashed single-use
confirmation token, actor/site, 15-minute expiry and state/history revision.
The final endpoint ignores mutated date/reason fields and consumes the token.
The decisive action reloads the actor and service/request state, reauthorizes,
revalidates source availability and date eligibility, and rejects stale review revisions.

Legacy Approved can initiate using its historically displayed agreement date; it is
not relabelled in old history and no original negotiation is fabricated.
Malformed DateAgreed records with no agreed date are rejected.

## Urgency, dates and lead time

Urgent/Late is calculated server-side when today reaches the prior agreed date minus
three working days, inclusive. Past agreements are late too. No amendment is rejected
solely because its old agreement is imminent or past.

Uses existing HolidayProvider/lead-time infrastructure. Current WeekdayHolidayProvider
has no approved bank-holiday dataset, so the current calculation is weekday-only.
A provider substitution test exercises a holiday; no holiday list was invented.

New requested amendment dates reuse the existing future working-day/six-month window.
Office accepting a requested early date must acknowledge it explicitly; a newly early
date is recalculated at the decision. Office alternative amendment proposals inside
normal lead time also require acknowledgement. Acknowledgement time, actor and date
facts are auditable. Original amendment requested date remains unchanged.

## History and privacy

Amendment metadata records requester name/role snapshots. New negotiation history
events also snapshot actor name/role, so subsequent profile edits do not rewrite their
attribution. Older history is not backfilled and retains its pre-existing actor links.
Proposal/history order and exact timestamps are displayed. New proposal events include
cycle/proposal UUIDs and date context; historical Date Agreed display reads the event's
after_state instead of the request's latest agreed_date.

Customer views render only customer-safe fields, escaped by Blade. internal_reason
stays separate. A service-scoped, allowlisted source-event timeline shows completion,
date correction and reversal without exposing provider internals. Source-import code
and source meanings are unchanged.

## Notifications and atomicity

Reuses after-commit domain events and existing in-app notification delivery.
Amendment requested notifies active appropriate Office Staff. Alternative proposals
and new agreements use the amendment requester as the primary customer recipient,
subject to current access. Accepted/rejected alternatives notify Office and retain the
actual responding user. No completion notifications, email, reminders or attachments.

Cycle/proposal UUID event keys isolate successive agreements and alternative rounds.
Amendment response text is taken from the matching immutable proposal history event.
Nested transaction rollback tests verify no amendment/agreement history or notification
leaks. Existing failure isolation and bounded source-import retry behaviour are preserved.

## UI

Reuses existing customer/Office layouts and date negotiation forms, with no redesign.
Customer path: Request Date Change → review → confirm.
Office sees Amendment request, previous/new dates, reason/explanation, urgency,
requester and time, with Accept New Date / Propose Alternative Date.
Awaiting Site User retains the current alternative's accept/reject controls only.
Shared status rules are outside Blade. Labels, native date/select controls, touch-sized
buttons and responsive layout classes follow existing conventions.
HTTP-rendered UI paths pass automated checks; manual browser keyboard/mobile QA has
not been performed in this task.

## Product decisions still needed

1. Approve the exact reason code/label list. `config/call_off_amendments.php` deliberately
   contains an empty list: customer initiation fails closed until approved. Synthetic
   test_reason exists only in automated tests and disposable gate fixtures.
   If Other is approved, clarify whether its explanation is mandatory.
2. Confirm the preserved overall plot-status aggregation for On Hold.

The failure/cancellation/old-date reinstatement workflow remains deferred and undefined.
No fail/cancel/reinstate endpoint was invented; the prior date remains available for a
future explicit Office decision. This does not prevent testing the specified negotiation loop.
The approved holiday provider/dataset remains a documented infrastructure decision.

## Verification

Final local evidence on 3 September 2026:

| Check | Result |
| --- | --- |
| PHP / Composer / Node / npm / Git availability | Passed: 8.4.23 / 2.10.1 / 26.5.0 / 11.17.0 / 2.55.0 |
| `php artisan migrate:fresh --seed` | Passed on the isolated worktree SQLite database; 12 migrations |
| `php tests/Support/Sprint3fMigrationRehearsal.php` | Passed; populated request, negotiation, proposal, history and notification tables preserved |
| `php artisan test --compact` | 287 total: **264 passed, 23 MySQL-only skipped; 1,378 assertions**, no failures |
| Focused `Sprint3fDateAmendmentsTest.php` | **42 passed; 165 assertions** |
| `php vendor/bin/pint --test` | Passed |
| `composer validate` | Passed |
| `composer audit --format=json` | Failed: 8 advisories across 3 packages; 5 high, 2 medium, 1 low |
| `npm run build` | Passed; local child-process permission was required |
| `git diff --check` | Passed |
| MySQL 8.4 / manual mobile-keyboard QA | Not run; outstanding |

Audit findings affect filament/filament (3), league/commonmark (4) and
livewire/livewire (1). Composer could not write its external cache but completed the
audit without caching and reported these advisories. This branch deliberately retains
the approved main lockfile and does not include the separate
`security/composer-advisories-2026-09-03` branch; release reconciliation must review
and rerun the security update independently.

The initial full regression run found a 403/404 response-contract difference for a
cross-customer alternative. This was corrected without changing the security assertion;
all existing and new tests now pass. A new test's route-name typo was also corrected.

Fresh migration replaced only the new worktree's disposable SQLite tables; no original
checkout or production records were deleted. Its contents are synthetic/recreatable.
No test result from SQLite is evidence of real MySQL locking behaviour.

## MySQL release gate and manual actions

No mysql, mysqld or docker executable, or matching local MySQL/MariaDB/Docker service,
was available. **Disposable MySQL 8.4 has not run for Sprint 3F.**

Eight amendment-specific process-race cases extend the existing MySQL suite:
simultaneous submissions, submission versus completion in both orders, alternative
accept/reject, and Office/customer agreement versus completion in both orders.
Workers require a testing MySQL environment. Existing source retry/idempotency tests
remain part of the full gate. These race cases still require execution and review.

`tests/Support/Sprint3fMigrationRehearsal.php` creates a new SQLite scratch database by
default, migrates the main-era schema, seeds a real initial agreement, then applies the
new forward migration and compares the five populated domain/audit tables unchanged.
For MySQL it requires `SPRINT3F_GATE_CONNECTION=mysql`, a new empty database named `portal_sprint3f_gate_*`
and explicit test-only DB_* environment values. It refuses existing tables.
Do not reuse production or the original checkout database.

Before release: provision disposable MySQL 8.4; run fresh migrations/seed, the non-empty
upgrade rehearsal on a separate empty target, schema/index checks and the full
`phpunit.mysql.xml` suite. Reconcile the separately reviewed security dependency work,
rerun audit/regressions, obtain product decisions and perform dedicated QA.
No release approval, automatic deployment or completion claim is made here.

## Files changed

44 project files; all paths are relative to the isolated feature worktree:

- `HANDOVER.md`
- `ROADMAP.md`
- `app/Actions/CallOff/AcceptAlternativeCallOffDateAction.php`
- `app/Actions/CallOff/AgreeRequestedCallOffDateAction.php`
- `app/Actions/CallOff/DetermineCallOffEligibilityAction.php`
- `app/Actions/CallOff/ProposeAlternativeCallOffDateAction.php`
- `app/Actions/CallOff/RecordCallOffStatusHistoryAction.php`
- `app/Actions/CallOff/RejectAlternativeCallOffDateAction.php`
- `app/Enums/PlotServicePresentationState.php`
- `app/Enums/PortalNotificationType.php`
- `app/Events/CallOffDateAgreed.php`
- `app/Http/Controllers/CallOffDateNegotiationController.php`
- `app/Http/Controllers/CallOffRequestDetailsController.php`
- `app/Http/Controllers/PortalNotificationController.php`
- `app/Http/Controllers/ReviewRequestsController.php`
- `app/Http/Requests/AgreeRequestedCallOffDateRequest.php`
- `app/Http/Requests/ProposeAlternativeCallOffDateRequest.php`
- `app/Listeners/CallOffNotificationListener.php`
- `app/Models/CallOffDateNegotiation.php`
- `app/Models/CallOffStatusHistory.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/PlotOverviewQueryService.php`
- `app/Services/PortalNotificationService.php`
- `current_sprint.md`
- `resources/views/portal/call-offs/show.blade.php`
- `resources/views/portal/review-requests/show.blade.php`
- `routes/web.php`
- `tests/Feature/Sprint3eMysqlConcurrencyTest.php`
- `tests/Support/Sprint3eMysqlConcurrencyWorker.php`
- `app/Actions/CallOff/RequestCallOffAmendmentAction.php`
- `app/Actions/CallOff/ResolveCallOffNegotiationAction.php`
- `app/Events/CallOffAmendmentRequested.php`
- `app/Http/Controllers/CallOffAmendmentController.php`
- `app/Services/CallOffAmendmentRules.php`
- `app/Services/CallOffDateViewService.php`
- `config/call_off_amendments.php`
- `database/migrations/2026_09_03_000014_add_date_amendment_metadata.php`
- `documentation/sprint-3f-date-amendments-2026-09-03.md`
- `resources/views/portal/call-offs/amendments/create.blade.php`
- `resources/views/portal/call-offs/amendments/history.blade.php`
- `resources/views/portal/call-offs/amendments/review.blade.php`
- `resources/views/portal/call-offs/amendments/source-history.blade.php`
- `tests/Feature/Sprint3fDateAmendmentsTest.php`
- `tests/Support/Sprint3fMigrationRehearsal.php`
