# Sprint 3B — Source Projection and MySQL Rehearsal Report

## Overall result

The transport-independent source-projection layer is implemented and SQLite verified. No
real spreadsheet, API, scheduled sync or customer-facing import endpoint was added.

## Architecture and safety

`SourceRecord` DTO → `SourceCallTypeMapper` → `SourceProjectionImportService` → Portal
projection tables. Each logical source row runs in its own transaction, preventing malformed
rows from partially changing a plot/service while allowing other records to continue.

## Projection behaviour

- All four plot-service rows exist for source-created plots.
- Call No. is unique and association changes are reconciliation anomalies, never silent
  moves.
- Product codes/quantities retain zero values; missing products are set to zero.
- Completed Date or a mapped stage establishes source completion. Stage-only completion is
  retained with an issue rather than an invented date.
- Completion closes active negotiations, completes active requests and clears conflict keys
  without notifications. Reversal does not revive old requests or overwrite a newer one.
- Missing source records are retained, marked missing and surfaced as internal issues.

## MySQL result

Blocked: no local MySQL/MariaDB client, configured database target or authorised disposable
Forge database was available. No production/Forge system was contacted. SQLite validates the
schema and behaviour only; MySQL DDL, nullable unique indexes, locking and races remain a
mandatory QA gate.

## Remaining TBCs

Source transport/ownership and credentials, UK bank-holiday provider, CML expansion,
amendment reasons, long-term retention and a safe MySQL rehearsal database.
