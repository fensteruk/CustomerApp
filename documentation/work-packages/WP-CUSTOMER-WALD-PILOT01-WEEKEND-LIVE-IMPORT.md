# CUSTOMER-WALD-PILOT01/02 — Supervised Weekend Live Import

Status: Implemented release candidate; production availability remains off by default.

## Objective

Provide the smallest safe production-capable Office workflow on the accepted WALD02–05
backend:

Office → private upload → inspect multi-site workbook → choose exactly one source site →
exact binding → resolve/review → non-mutating preview → explicit one-site atomic commit.

This package is a temporary supervised pilot and does not complete CUSTOMER-WALD06.

## Fixed scope and boundaries

- Known RedZebra call-off workbook family only; XLSX/CSV, 20 MiB upload ceiling.
- One parent workbook may expose many source identities, but each review/commit selects exactly
  one source site. No automatic splitting or all-site commit.
- Every run is PILOT_SINGLE_SITE_SELECTION and PARTIAL_FILTERED_EXPORT. Absence never deletes,
  reconciles or zeroes data.
- Exact active source-site binding is mandatory. No fuzzy match or automatic site creation.
- One Call No. is one visit. A duplicate Call No. anywhere in the parent workbook blocks it.
- The 500 non-empty-row child limit is unchanged; parent structural discovery is bounded at
  5,000 records.
- Wald infers structure only. Business meaning stays in the controlled dictionary and confirmed
  mappings.
- The approved reviewed-artifact exception remains checksum-bound: PC1, CC1 (including the
  observed CC! typo), and CM1 are included; CM2 and the exact NICK TEST record are excluded.
  Raw evidence is retained and these exceptions are not globalised.
- There is no RedZebra API, SiteApp runtime dependency/writeback, queue requirement, automatic
  purge, customer notification or customer-facing source evidence.
- Requested Date, Date Agreed, proposals, customer responses, amendments and Portal history are
  protected by the inherited WALD05 projection adapter.

## Enablement

Effective availability is:

WALD_IMPORT_AVAILABLE=true AND durable application setting
wald_import_pilot_enabled=true AND current active non-preview Fenster Office Staff.

Both code/config and the durable application setting default off. The environment value is the
emergency kill switch and always wins. Office Settings may change only the application setting,
with current-role revalidation, optimistic locking, a required reason and explicit confirmation
for off-to-on. Every state transition has immutable actor/before/after audit. Disabling prevents
all new upload, analysis, review, approval and commit actions without deleting history or undoing
committed imports.

## Release and operational contract

Deployment may apply the additive migration while the feature remains unavailable. After
successful migration, health checks and backup/recovery confirmation, an authorised operator may
set WALD_IMPORT_AVAILABLE=true; an authorised Office user may then explicitly enable the
application setting. For emergency stop, set the environment gate false and refresh application
configuration. Do not roll back data, purge history or run an uncontrolled multi-site import.

The production pilot remains supervised and uses dummy/test data unless separately approved.
CUSTOMER-WALD06 queues, automatic multi-site orchestration and cutover remain separately scoped.
