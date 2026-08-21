# Sprint 3D Bulk Call-Off QA Report

_21 August 2026 — local QA gate on `release-candidate/sprint-3d`_

## Pass / Fail

**Pass, after two confirmed Sprint 3D corrections.** This local SQLite/browser gate does
not approve a Forge deployment, production database work, a merge to `main`, or starting
Sprint 3E.

## Overall usability assessment

A Site Manager completed the core journey without developer help: select three active-site
plots, choose all four services, use different service dates, see source-completed and
source-missing rows, exclude one combination, give a BF early-date reason, review and
submit. At 320px this full journey had no document-level horizontal overflow.

## P0/P1/P2/P3 findings

### P0

None.

### P1 resolved during this gate

- Signed-review rows were sorted by opaque UUID key, breaking the fixed four-service order
  and shuffling plots. The workflow now preserves matrix order through revalidation.
- Target Awaiting Fenster requests silently received no submission notifications because the
  legacy notification service accepted only Submitted. It now accepts both statuses and
  serialises each request's service/date, not the batch's arbitrary first values.

### P2

None open. Per-request notifications are an accepted temporary Sprint 3D limitation; batch
digest remains Sprint 3I work before the integrated company-test gate.

### P3

None open.

## Dashboard-entry result

Passed. The active Meadow View dashboard showed a count of three, enabled Call Off Selected
and entered the shared New Call Off flow. Selection is page-scoped; server UUID validation
rejects foreign selections.

## New Call Off result

Passed. The separate entry uses the same matrix, review and final-submit contract.

## Four-service result

Passed. Cavity Closers, Windows, Snagging and CML appear in that exact order in create,
matrix and corrected review screens. One-, two- and four-service paths are supported.

## Matrix/exclusion result

Passed. The three-by-four matrix showed all 12 combinations. Source-completed Windows and
source-missing Snagging stayed visible with customer-safe explanations and were excluded.
Excluding Plot 001/CML affected only that row. The resulting complex submission correctly
showed nine requests: 12 less two unavailable rows and one manual exclusion.

## Lead-time result

Passed. The server supplies normal earliest dates: four weeks for standard products and
five for positive BF. Focused checks reject weekdays before normal date, weekends and dates
beyond six months. The UI does not calculate lead time.

## BF result

Passed. Positive BF appears with its quantity and produces five weeks; positive CAS/PFD
display unchanged, while BF quantity zero remains hidden and does not extend lead time.

## Earlier Date result

Passed. The BF row shows requested/normal earliest date, requires a reason while included,
and retains the reason through review/persistence. Excluding it removes that requirement
without changing other Windows rows.

## Review result

Passed after the ordering correction. The review showed site, plot/service combinations,
dates, included/excluded state, BF products, early reason, message and exact request count.

## Persistence result

Passed. The browser journey created one batch and nine requests. Feature coverage verifies
request-level plot/service/date/earliest/early fields, conflict keys and Date Requested
history; unavailable and excluded rows never persist. The dashboard showed Called Off —
Awaiting Date only for included services.

## Confirmation/tampering result

Passed. Direct final POST; changed signature, user, site, message, plot, inclusion or date;
and fresh availability changes all fail safely without partial persistence.

## Replay result

Passed. The confirmation is consumed before persistence. A replay requires new review and
creates no duplicate batch, request or history.

## Stale-state result

Passed. New active conflict, source completion, source absence, BF lead-time change,
revoked assignment and account deactivation all invalidate review without a new batch.

## Atomicity result

Passed on SQLite. A multi-item action with an invalid later item rolls back batch, requests
and histories. Notifications run after the business transaction, so listener failure cannot
roll back a valid call-off. MySQL concurrency evidence remains outstanding.

## Notification result

Passed after correction. Four mixed-service requests to a Site User and Office Staff create
eight customer-safe notifications, each with its own service/date and Awaiting Fenster
status. A nine-request moderate submission creates nine notifications per recipient. That
is acceptable only as the documented temporary behaviour; digest remains future work.

## Role/security result

Passed. Site Manager, Assistant Site Manager and Finishing Foreman submit for assigned
sites and remain recorded history actors. Office Staff is denied submission. Existing and
focused coverage rejects foreign, cross-site, cross-organisation and malformed UUID use.

## 320px result

Passed. Full journey at 320px: dashboard → selection → matrix → exclusion → early reason →
review → submit → success. Client and document width were both 305px; primary controls were
at least 48px high.

## 390px result

Passed. Representative 12-row matrix: 375px client/document width, no overflow and 48px
review control.

## 430px result

Passed. Representative 12-row matrix: 415px client/document width, no clipping and 48px
review control.

## 768px result

Passed. Tablet matrix retained readable stacked cards at 753px client/document width; review
control remained 48px high.

## Desktop result

Passed. At 1440px the dashboard table supports service comparison and the 12-row matrix is
scannable; no document overflow (1425px client/document width) and 48px primary control.

## Accessibility result

Passed for structural/visible-browser evidence: labelled fieldsets/controls, headings,
textual Included/Excluded/Unavailable states and status/alert feedback. The browser bridge
did not advance reported Tab focus, so no manual keyboard-only or screen-reader pass is
claimed.

## Performance result

Passed locally. 10×4 returns 40 rows and 15×4 returns 60 within eight observed queries.
The real 12-card matrices were responsive at 390–1440px.

## Existing workflow regression

Passed in full Pest: plot overview/details, filters, pagination, Show Completed, withdrawal,
Trash, Undo, rejected resubmission, notification centre, site switching and Office review.

## Corrections made

- Preserved selected plot and fixed service order in signed review/revalidation payloads.
- Enabled Awaiting Fenster submission notifications and corrected request-level notification
  context while retaining legacy requested-date fallback.

## Tests added

`Sprint3dBulkCallOffQaTest` covers matrix reasons/products/order; mixed persistence/history;
early/exclusion/date validation; confirmation mutations; stale conflict/completion/source/BF/
access paths; atomicity; 10×4 performance; external roles; and notifications.

## Files changed

- `app/Services/CallOffSubmissionWorkflow.php`
- `app/Services/PortalNotificationService.php`
- `tests/Feature/Sprint3dBulkCallOffQaTest.php`
- This QA report and the status/handover/roadmap documents.

## Commands/results

- Confirmed local `APP_ENV=local` and local CustomerApp SQLite, then ran `php artisan
  migrate:fresh --seed` successfully.
- Focused Sprint 3D/notification checks passed: 24 tests, 186 assertions.
- `php artisan test --compact` passed: 171 tests, 884 assertions. `vendor\\bin\\pint --test`
  passed; the approved local Vite production build passed; `git diff --check` passed.
- Sandboxed Vite hit the known Windows child-process `EPERM`; approved local Vite build
  passed.

## Remaining limitations

- MySQL/MariaDB migration, locking and concurrency rehearsal remain open.
- Holiday enforcement remains weekday-only pending an owned UK holiday provider.
- No approved bulk size limit; physical-device, manual keyboard-only and screen-reader
  evidence remain outstanding.
- Forge recovery/production reconciliation are explicitly outside this local QA gate.

## Release-candidate implication

The corrections are local, tested and documented on `release-candidate/sprint-3d`. Nothing
was merged, pushed, deployed or applied to production. Later release preparation must create
and verify a new checkpoint; `main` remains untouched.

## Recommendation

Safe to begin Sprint 3E
