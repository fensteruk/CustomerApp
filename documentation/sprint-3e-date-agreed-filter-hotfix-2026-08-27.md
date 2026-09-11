# Sprint 3E — Date Agreed Office Filter Hotfix

**Date:** 27 August 2026
**Status:** Local implementation and verification passed; awaiting deployment approval.

## Production symptom

The authenticated Sprint 3E production smoke test found two visually identical
**Date Agreed** options in the Office Review status filter. The options represented the
legacy persisted `approved` status and the current `date_agreed` status.

## Root cause

Office Review passed every `CallOffRequestStatus` enum case directly to the filter while
the customer-facing status mapper intentionally labels both `approved` and `date_agreed`
as **Date Agreed**. The query then matched only the selected stored value, exposing an
internal compatibility distinction that Office Staff do not need at the presentation
level.

## Canonical filter semantics

Office Review now exposes one **Date Agreed** option. Selecting it matches requests whose
persisted status is either `approved` or `date_agreed`. A legacy `status=approved` URL is
accepted and normalised to the same canonical filter behaviour. Other status filters keep
their existing one-to-one query semantics.

## Legacy preservation

The hotfix is read-only with respect to request state. It does not update legacy Approved
rows, rewrite status history, create negotiations or proposals, emit notifications, or
alter resubmission lineage. Legacy `approved` remains the truthful persisted historical
status and continues to present as **Date Agreed**.

## Verification

Focused tests cover legacy Approved, modern Date Agreed, Awaiting Fenster, Awaiting Site
User and Completed fixtures; canonical and legacy query parameters; unchanged legacy
state/history/negotiation/proposal/notification counts; malformed parameter handling; and
existing Office Staff authorisation boundaries.

- Office Review focused suite: 17 tests, 119 assertions passed.
- Office Review plus Sprint 3E focused regression: 37 tests, 227 assertions passed.
- Full Pest suite: 222 passed, 15 skipped, 1,213 assertions.
- Laravel Pint check passed.
- Composer validation passed and Composer audit found no security advisories.
- Vite 8.1.4 production build passed after the known Windows sandbox-only process-spawn
  restriction was rerun with approved local permissions.
- `git diff --check` passed.

## Release recommendation

No migration or production-data operation is required. Subject to the final local test,
formatting, dependency, build and diff gates, this narrow controller/query, test and
documentation change is suitable for a separately approved production deployment.
