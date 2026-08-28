# Manual SiteApp XLSX Source Import

Date: 2026-08-28

## Outcome

The transport-independent Sprint 3B source importer now has a manual XLSX adapter, explicit source-site bindings, a non-mutating preview, stale-preview protection, an explicit atomic commit and an Office-only audit/readback contract.

The fixed-header parser configuration has been superseded locally by a deterministic adaptive interpreter. `Copy of siteapp1.xlsx` has now been inspected locally and read-only. The backend proposes its real worksheet/header layout safely; source Call Type meanings, product confirmation, site bindings and global-snapshot status remain explicit gates rather than guesses.

Detailed interpretation rules and the Office mapping/profile contract are documented in `documentation/deterministic-spreadsheet-interpretation-2026-08-28.md`.

## Reference Workbook Evidence

The workbook contains one visible `Sheet1`, header row 1, range `A1:AA49`, 47 data rows,
one blank physical row and 13 distinct source site names. It uses the Excel 1900 date system;
`Plot To Be Installed` is date-formatted and `Site Value` is GBP currency-formatted. There
are no hidden sheets/rows/columns, formulas, macros, embeddings, external links or workbook
connections. Its SHA-256 is
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.

There is no Job Stage or Completed Date column. The `complete` flag contains 20 true-like
and 27 false-like values. A true flag may establish source completion but produces the
existing missing-completion-date reconciliation warning; no date is invented or copied from
`Plot To Be Installed`.

The complete structural/header evidence is recorded in
`documentation/deterministic-spreadsheet-interpretation-2026-08-28.md`. The workbook is not
committed and its row values are not reproduced. A fictional structure-equivalent fixture
provides regression coverage.

## Namespace and Identity

The adapter uses exactly `siteapp-xlsx`. The namespace is included in binding identity, projected plot source identity, preview audit and committed import audit.

`Call No.` is the permanent idempotent service-row identity. The current domain schema makes it globally unique, so an existing Call No. cannot be silently moved across a namespace, site, plot or service. Such input requires reconciliation.

Site names are not automatically treated as Portal site IDs. An Office Staff user explicitly binds:

`siteapp-xlsx + exact source site key/name -> existing Portal site UUID`

The binding records its own UUID, exact key, source/original name, optional display label, Portal site, creator and timestamps. The selected Portal site's existing customer organisation determines the tenancy boundary. No fuzzy match, inferred customer or implicit site creation exists.

## Transport Flow

1. An active non-preview Office Staff user creates or confirms exact source-site bindings.
2. The user uploads a single XLSX to a private random path.
3. Container, macro/embedding, worksheet, header, formula, date, identity and quantity rules are validated.
4. Rows are normalised into transport DTOs and compared with current projection truth without mutation.
5. A preview stores filename, SHA-256, namespace, expiry, summary and safe row results; no source run exists yet.
6. The same Office Staff user explicitly confirms the preview hash.
7. Commit locks the preview, verifies the contract/file/source fingerprints, reparses and rebuilds the authoritative diff.
8. Blocking results stop commit. Otherwise existing `SourceProjectionImportService` receives `SourceRecord` DTOs and applies its current transactions, completion precedence, audit and idempotency rules.
9. The committed preview points to its source import run and the private workbook is deleted.
10. Results and reconciliation are available by UUID to authorised Office Staff.

## Mapping Rules

The latest `siteapp-xlsx` source-family confirmations are:

- `PC1` maps to Windows;
- `CC1` maps to Cavity Closers;
- `CML` maps to CML.

Unknown values are blocking; there is no guess/fallback mapping. The workbook contains 21
`PC1`, 17 `CC1`, 7 `CC!`, 1 `CM1` and 1 `CM2` rows and no `CML` row. The 9
`CC!`/`CM1`/`CM2` rows are therefore deliberately blocked until the source owner confirms
their meaning for this exact source family. Older mappings in the transport-independent
importer do not silently broaden the XLSX adapter contract.

The workbook contains 19 code-like numeric product candidates: `CAS`, `FLU`, `VS`, `TT`,
`BAY`, `PFD`, `PSU`, `PSG`, `CDF`, `CDU`, `CDG`, `GLS`, `PSP`, `BF`, `ALI`, `AOV`, `FI`,
`WP` and `MISC`. `CAS`, `PFD` and `BF` are approved examples; the remaining 16 need Office
confirmation. Blank means zero, zero is retained, positive numbers are accepted, and
invalid/negative quantities block import. `Items Ordered Status` is not mapped to a domain
field. Site Value and other commercial values are excluded.

`Plot To Be Installed` is source operational context only. The current adapter deliberately does not project it because the existing Sprint 3B contract has no customer-date destination for it. It never becomes requested, proposed, agreed or history data.

## Completion

The adapter delegates completion to the existing source importer:

- Cavity Closers: `CC08`;
- Windows: `CA02` or `CA03`;
- Snagging: `SN05`;
- CML: `CML4`.

Completed Date also independently proves completion. Completion stage without a date completes the service and creates a missing-date reconciliation issue; no date is invented. A real Completed Date completes with that date even if the stage is not a completion stage. Source reversal uses the current guarded reversal rule and does not revive obsolete negotiation.

## Missing-source Rule

Missing rows are never deletions. The inspected workbook proves a multi-site export but not a
global one. The adapter limits missing evaluation to the `siteapp-xlsx` namespace and exact
mapped source-site keys present in the current upload. A selected-site workbook therefore
cannot mark other sites' rows absent. This conservative scope remains mandatory until the
source owner proves a global snapshot boundary.

## Preview and Staleness

The preview categories are `NEW`, `UNCHANGED`, `UPDATED`, `COMPLETED`, `COMPLETION_REVERSED`, `MISSING_FROM_SOURCE`, `SITE_MAPPING_REQUIRED`, `UNKNOWN_CALL_TYPE`, `INVALID` and `RECONCILIATION_REQUIRED`.

A preview is bound to its active Office Staff initiator, file SHA-256, workbook-contract fingerprint, relevant bindings/projections fingerprint and expiry. Commit never trusts client rows or categories. It serialises commits for one preview and reparses under the commit lock. A replay returns the existing result rather than importing again.

## Audit and Reconciliation

Committed source runs retain namespace, original safe filename, SHA-256, initiator, represented site keys, timestamps, status, record counts and reconciliation count. Local machine paths are not stored in the committed run or returned.

Safe reconciliation output includes severity, message, source row number where available, Call No., source site/plot context, blocking state and resolved state. Current issue classes cover missing source, missing completion date, unknown Call Type, mapping required, duplicate Call No., invalid date/quantity/identity and completion-reversal conflicts.

## Security

- XLSX only; `.xlsm`/macros and embedded objects rejected.
- 10 MiB and 5,000-row defaults.
- private Laravel storage with random internal filenames.
- expiry after 30 minutes and hourly cleanup.
- active, complete, non-preview Office Staff only.
- no external Site User access.
- no arbitrary customer/site creation or assignment changes.
- no client-authored diff applied.
- no commercial workbook values exposed.
- no SiteApp database access, API invention, deployment or production contact.

## Scheduled-sync Reuse

A future scheduled transport may produce the same validated `SourceRecord` collection and `SourceImportContext`, then call `SourceProjectionImportService`. It must retain its own explicit namespace, least-privilege transport credentials, idempotent source identity, bounded snapshot scope and audit. It must not reuse uploaded Excel objects inside the domain importer or broaden missing-source scope by assumption.

## Remaining Decisions

The source owner must confirm `CC!`, `CM1` and `CM2` for the `siteapp-xlsx` namespace,
confirm the 16 newly observed product headers, state whether `Site Name` is a durable exact
key or only a display name, and state whether this multi-site workbook is a selected-site or
complete global export. Export cadence/ownership also remains TBC. Until those decisions and
site bindings exist, the preview remains blocking; no UI or production release should treat
the adapter as operationally ready.

## Verification

Local isolated verification completed on 2026-08-28:

- clean SQLite migration plus seed: passed, including migration `000009`;
- `000009` rollback and re-apply: passed;
- complete test suite: 238 passed, 1,294 assertions; 15 existing MySQL-only Sprint 3E cases skipped on SQLite;
- focused manual import suite: 16 passed, 81 assertions;
- focused Sprint 3B source suite: 15 passed, 71 assertions;
- Pint: passed;
- Composer validation: passed;
- Composer security audit: no advisories;
- frontend production build: passed;
- PHP syntax and Git whitespace checks: passed.

A disposable MySQL 8.4 gate could not be run from this worktree. This host has no Docker engine, MySQL client/server or MySQL listener. The only repository workflow is the existing Sprint 3E workflow on a different release line; this branch does not contain it, and modifying/copying workflow definitions is outside this task. No Forge or production system was contacted. MySQL-specific migration, unique-key, transactional and concurrent-commit verification remains required after the representative workbook contract is finalised and before release.

Reference-workbook remediation verification on 2026-08-28 passed locally: the actual
workbook's rolled-back preview gate passed 1 test/21 assertions; the deterministic suite
passed 18/104; focused interpreter/manual-import/Sprint 3B tests passed 49/257; the complete
suite passed 256 tests/1,399 assertions with 15 MySQL-only cases skipped; clean SQLite
migration/seed, Pint, Composer validation/audit, Vite build and Git whitespace checks passed.
The disposable MySQL gate and source-semantics decisions remain outstanding as documented in
`documentation/deterministic-spreadsheet-interpretation-2026-08-28.md`.
