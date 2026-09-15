# CUSTOMER-WALD-PILOT01/02 — Weekend Live Import Release Evidence

Date: 15 September 2026.

## Candidate

Branch: feature/customer-wald-weekend-pilot.

Integration base: f13630fb3503bf1431405a965c1699e272b56372, the normal merge of accepted
WALD02–05 and current CustomerApp main. The release commit is the commit containing this report.

## Implemented

- Office-only real Imports page, private upload and history.
- Multi-site structural discovery with exactly one source site selected per child run.
- Exact active source binding, clarification, private row review, pinned preview, explicit
  approval and atomic one-site commit.
- Site-scoped receipt identity permits sibling sites from one parent export without weakening
  legacy WALD05 shared-slot rules.
- Immutable pilot upload/selection/event history and explicit pilot mode.
- Durable Office setting plus environment emergency kill switch.
- Responsive Settings and import screens with persistent pilot/supervised warnings.

## Evidence

The private local workbook SHA-256 remained
ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893.
It produced 47 records, 45 included records, two approved exclusions and 12 detected source
sites. Two different source sites were independently bound, reviewed and committed in isolated
databases; exactly two site-scoped receipts resulted and no third site received a projection.

Focused SQLite pilot/setting coverage passed 6 tests / 53 assertions. WALD05 portal,
security and dedicated-backend regression coverage passed 85 tests / 231 assertions.
Disposable MySQL 8.4.11 passed the additive migration, actual-workbook two-site pilot,
setting/audit checks and the 20-iteration concurrent double-commit scenario (80 assertions).
The current local SQLite database also upgraded in place from the product-line migration set
without losing its dummy records.

The final stable application regression run passed 1,571 tests: 1,494 passed, 77 intentionally
skipped and 7,845 assertions. The focused pilot plus preserved synthetic-demo compatibility run
passed 16 tests / 115 assertions. Pint, Composer strict validation, Composer audit, the production
frontend build, production-dependency npm audit and Git whitespace validation all passed.

The production frontend build passed after installing exact lockfile dependencies. Browser
checks verified Office login, Settings, Imports and warnings at desktop width, then verified a
390×844 mobile layout after rebuilding a stale local asset bundle. The browser file transfer
itself was not used as commit evidence because the first local PHP server lacked a writable
upload temp directory; the same real upload/preview/commit path is covered through HTTP/service
integration tests on SQLite and MySQL.

## Release controls and remaining risks

- Repository default: WALD_IMPORT_AVAILABLE=false.
- Migration default: wald_import_pilot_enabled=false.
- No production flag, database setting, Forge configuration or import was changed by the build.
- Locked npm development/build-chain audit remains 14 entries (five high, nine moderate);
  production application code introduces no new dependency and no lockfile was changed.
- This is one-site-at-a-time supervised import, not final automatic multi-site orchestration.
- A production backup/recovery point, migration, health check and authorised enablement remain
  deployment actions.

The candidate is suitable for a controlled, supervised weekend pilot using the current dummy
site data, subject to the deployment controls above.
