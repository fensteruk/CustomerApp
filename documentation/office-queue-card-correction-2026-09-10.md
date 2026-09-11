# Office Queue Card Correction Report

Date: 10 September 2026

## Overall Result

**READY_FOR_QA.** The inherited Office Review Requests card mismatch was reproduced and
corrected on a non-deploying branch from the exact healthy production/main checkpoint.
This is a read-side presentation/query correction; no workflow, persistence or schema
behaviour changed.

## Branch / SHA

- Branch: `fix/office-queue-card-request-values`
- Base: `eb149a9ab28f9131f9d26971f13bd289ff1afba6`
- Executable fix and regression tests:
  `8f28cc50f513c2fae2464abdbf0e7b11ed13f7bf`
- Documentation descendant: the final branch SHA returned with this report

## Reproduction

A synthetic batch retained legacy batch values **Windows / 1 October 2026**. It contained:

| Card/request | Request service | Request date | Queue before | Details before |
| --- | --- | --- | --- | --- |
| Queue Plot A | Windows | 5 October 2026 | Windows / 1 October | Windows / 5 October |
| Queue Plot B | Cavity Closers | 12 October 2026 | Windows / 1 October | Cavity Closers / 12 October |
| Queue Plot C | CML | 19 October 2026 | Windows / 1 October | CML / 19 October |

The pre-fix regression failed after rendering all three cards with the batch's Windows /
1 October values. The request details pages displayed each child's request-level service
and date correctly. The first attempted reproduction was blocked only by the new worktree's
missing Vite manifest; the locked asset build was run, after which the presentation defect
was reproduced exactly. This is not attributed to Sprint 3F.

## Root Cause

One queue `article` is keyed and linked by one `CallOffRequest` UUID, but its Blade card
read `service_identifier` and `requested_date` directly from `CallOffBatch`. The
service filter also queried only the batch field. Sprint 3A retained those batch columns for
legacy dual-read compatibility while new multi-service submissions store the authoritative
values on each child request. The detail path already preferred those request fields.

## Correct Card Grain

One card represents one individual request: one plot/service/date/status and one Review
request destination. The parent batch remains authoritative for shared site, submission
attribution, submission timestamp and customer-facing submission text. No grouping,
persistence or navigation model changed.

## Service Fix

Cards now use the existing `effectiveServiceIdentifier()`: request value first, legacy
batch fallback only when the request field is null. Labels remain the approved enum labels:
Cavity Closers, Windows, Snagging and CML. No source call codes are exposed.

The service filter now selects the request's `service_identifier`. Its fallback checks the
batch only for a null legacy request field, preventing a populated sibling request from
matching an unrelated batch service.

## Date Fix

A matching `effectiveRequestedDate()` model method centralises request-first, legacy-batch
fallback semantics. The queue, details page and date presentation service use this method.
The queue label remains **Requested date** and continues to mean the original request date.
It does not switch to an agreed date, Fenster proposal or amendment-requested date.

## Batch Behaviour

Focused tests cover a three-service/three-date batch, same-service/different-date siblings,
different-service/same-date siblings and legacy null request fields. Each populated child
now shows its own values; the legacy row still safely reads its historic batch values.
Batch records and shared metadata were not changed.

## Sprint 3F Impact

None expected. Regression coverage verifies Awaiting Fenster, Awaiting Site User with a
Fenster alternative, Date Agreed and Amendment On Hold. In each case the queue retains the
original Requested date while details continue to show the relevant proposal/agreed/
amendment data. No amendment state, On Hold presentation, action, history, lock order or
notification code changed.

## Filter / Pagination Regression

- Request-level service filtering returns only the matching child, even when all siblings
  share a different batch-level service.
- Legacy null request fields remain filterable through the batch fallback.
- Existing site/status/Date Agreed filter tests pass unchanged.
- The focused fixture verifies 11 results paginate as 10 on page 1 and 1 on page 2 with the
  total/count unchanged.

## Query Performance

The queue still eager-loads projected plot, batch, site and submitter. The request-level
card values use already-selected request columns and add no query per card. A warmed local
SQLite measurement recorded **8 queries for one rendered card and 8 queries for ten rendered
cards**. The service fallback is an SQL `EXISTS` condition inside the paginated query, not
an application-side loop. These are local regression measurements, not production timings.

## Tests

- Initial post-build reproduction: **1 failed / 2 assertions** because Queue Plot C showed
  Windows / 1 October instead of CML / 19 October.
- Focused correction:
  `php artisan test tests/Feature/OfficeQueueCardRequestValuesTest.php --compact` —
  **11 passed / 62 assertions**.
- Related queue, Sprint 3E and Sprint 3F:
  `php artisan test tests/Feature/OfficeQueueCardRequestValuesTest.php tests/Feature/OfficeStaffReviewRequestsTest.php tests/Feature/Sprint3eDateNegotiationUiTest.php tests/Feature/Sprint3eDateNegotiationQaTest.php tests/Feature/Sprint3fDateAmendmentsTest.php --compact` —
  **134 passed / 1,327 assertions**.
- Full CustomerApp:
  `php artisan test --compact` —
  **329 passed / 38 skipped / 2,344 assertions**, 367 tests total.

Tests used Laravel's configured disposable in-memory SQLite and a generated process-only
APP_KEY that was neither printed nor saved. The skipped tests are existing environment-
specific gates and are not counted as passes.

## Composer / Build / Pint

- Environment: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0,
  Git 2.55.0.windows.3.
- `composer install --no-interaction --prefer-dist`: passed from the existing lockfile,
  161 installs / 0 updates / 0 removals. Composer continued without its unwritable cache.
- `npm ci --no-audit --no-fund`: passed, 170 packages from the existing lockfile.
- `npm run build`: passed, Vite 8.1.4, 5 modules, 3.05 seconds.
- `php vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit --format=json`: passed, zero advisories or abandoned packages.
- `npm audit --json`: inherited build/development result, 14 entries
  (5 high / 9 moderate); no dependency remediation or lockfile change.
- `npm audit --omit=dev --json`: passed, zero production-dependency advisories.
- `git diff --check`: passed before the executable checkpoint and is repeated for the
  documentation handoff.

## Files Changed

Executable checkpoint:

- `app/Http/Controllers/ReviewRequestsController.php`
- `app/Models/CallOffRequest.php`
- `app/Services/CallOffDateViewService.php`
- `resources/views/portal/review-requests/index.blade.php`
- `resources/views/portal/review-requests/show.blade.php`
- `tests/Feature/OfficeQueueCardRequestValuesTest.php`

Documentation descendant:

- `documentation/office-queue-card-correction-2026-09-10.md`
- `current_sprint.md`
- `HANDOVER.md`
- `ROADMAP.md`

## Migrations / Production Impact

No schema, migration, seed, production account/data, queue, provider, Forge, deployment,
tag, push or main modification. No business transition or persistence logic changed.
Unrelated WALD, admin, sidebar and root-worktree files remain untouched.

## Recommended Release

**NEXT_PATCH.** This corrects visible Office presentation and filtering against already
correct underlying request data. The implementation is small, read-only and protected by
focused plus full regression coverage. Dedicated QA should confirm the mixed batch in the
Office queue and compare its cards with Request Details before any separately approved
release action.

Office queue-card correction ready for QA
