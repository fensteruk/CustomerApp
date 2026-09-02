# SiteApp Import Disposable MySQL 8.4 Gate

_Prepared: 2 September 2026_

## Boundary

Run this gate only in an isolated disposable environment against MySQL 8.4. Never point it
at Forge or a persistent CustomerApp database. Use a temporary database and a temporary user
whose grants are limited to that database. Use fake/test mail and notification transports.

Before any migration or test, record evidence that:

- `APP_ENV=testing`;
- the database name is a task-specific disposable name, not the production database;
- the temporary user has privileges only on that disposable database;
- the resolved Laravel connection names that disposable database;
- no production credentials, URL, queue or mail transport is loaded.

If any proof fails, stop without running a destructive database command.

## Required Database Pass

1. Create an empty temporary MySQL 8.4 database and restricted user.
2. Run the clean migration and seed gate against that database.
3. Confirm migrations `000009`, `000010`, `000011` and `000012` are recorded as run.
4. Roll back only `000012`, verify its added columns are removed, reapply it, and verify the
   columns and profile default migration are restored.
5. Run the full Pest suite on MySQL.
6. Run focused import suites:
   - `tests/Feature/ManualSourceImportBackendTest.php`;
   - `tests/Feature/DeterministicSpreadsheetInterpretationTest.php`;
   - `tests/Feature/SiteAppImportSemanticCorrectionTest.php`;
   - `tests/Feature/Sprint3bSourceProjectionImportTest.php`.
7. Repeat the export-scope regressions and record durable state after every import:
   - A+B followed by partial A leaves B present;
   - ten A rows followed by two partial A rows leaves the other eight present;
   - site-complete marks only its explicit site missing;
   - global-complete may mark all absent namespace rows missing;
   - no mode deletes a projected service, plot, product, request or history row.
8. Verify MySQL uniqueness and transactional behaviour for source-site bindings, workbook
   profile fingerprint/version/semantic version, commit replay/idempotency and rollback after
   a simulated importer failure.
9. Verify `complete=Yes` completes exactly once without an invented date, PC1 operational
   target persistence does not change Portal requested/agreed/completed dates, exact positive
   BF selects the five-week rule, and later BF removal returns to the four-week rule.
10. Inspect the final schema, foreign keys and indexes for the preview/run/profile/binding and
    projected-service tables.

## Required Audit Evidence

Capture MySQL version, temporary database/user names, restricted grants, resolved Laravel
environment, migration output, exact test totals, failure output if any, schema/index evidence,
scope audit JSON for all three enum values, rollback evidence and cleanup confirmation.

Delete the temporary database and temporary user after the run. Confirm the temporary
resources are gone and that production was never contacted or changed.

## Release Rule

The import/interpreter branch remains blocked from release until this disposable MySQL 8.4
gate is fully green. A SQLite pass is necessary local evidence but is not a substitute for
MySQL migration, uniqueness and transaction proof.
