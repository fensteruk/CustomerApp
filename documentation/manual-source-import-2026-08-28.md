# Manual SiteApp XLSX Source Import

Date: 2026-08-28

## Outcome

The transport-independent Sprint 3B source importer now has a manual XLSX adapter, explicit source-site bindings, a non-mutating preview, stale-preview protection, an explicit atomic commit and an Office-only audit/readback contract.

The fixed-header parser configuration has been superseded locally by a deterministic adaptive interpreter. No matching reference workbook exists in the project, attachment workspace or usual local document paths inspected on 2026-08-28. The backend can now propose and confirm varying worksheet layouts safely, but the actual reference format and snapshot scope still cannot be claimed until the file is available.

Detailed interpretation rules and the Office mapping/profile contract are documented in `documentation/deterministic-spreadsheet-interpretation-2026-08-28.md`.

## Exact Workbook Evidence Still Required

Provide one representative, unmodified `.xlsx` produced by the intended operational export. It must preserve:

- original safe filename;
- every worksheet name and worksheet visibility state;
- hidden columns and their headers;
- exact header row number and text;
- the real source site identifier/name field;
- Call No., plot, Call Type, job stage and Completed Date fields;
- Excel date system and date cell types;
- numeric, zero, blank, invalid and negative quantity examples where safely possible;
- formulas and blank rows;
- product abbreviation columns;
- evidence whether an export covers one site, selected sites or a complete global snapshot.

Values may be anonymised, but structure and cell types must remain unchanged. Do not supply credentials, macros or production secrets.

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

- `PC1` maps to Windows.
- `CC!` maps to Cavity Closers.
- `CM1` maps to Snagging.
- `CM2` maps to CML.
- Unknown values are blocking; there is no guess/fallback mapping.

Only approved product abbreviation columns from the confirmed workbook contract are read. Test mechanics cover `CAS`, `PFD` and `BF`; that is not yet a claim that these are the complete operational column set. Blank means zero, zero is retained, positive numbers are accepted, and invalid/negative quantities block import. Other workbook columns are neither projected nor returned. In particular, Site Value and other commercial values are excluded.

`Plot To Be Installed` is source operational context only. The current adapter deliberately does not project it because the existing Sprint 3B contract has no customer-date destination for it. It never becomes requested, proposed, agreed or history data.

## Completion

The adapter delegates completion to the existing source importer:

- Cavity Closers: `CC08`;
- Windows: `CA02` or `CA03`;
- Snagging: `SN05`;
- CML: `CML4`.

Completed Date also independently proves completion. Completion stage without a date completes the service and creates a missing-date reconciliation issue; no date is invented. A real Completed Date completes with that date even if the stage is not a completion stage. Source reversal uses the current guarded reversal rule and does not revive obsolete negotiation.

## Missing-source Rule

Missing rows are never deletions. The adapter limits missing evaluation to the `siteapp-xlsx` namespace and exact mapped source-site keys present in the current upload. A one-site workbook therefore cannot mark another site's rows absent. This conservative scope remains mandatory until the representative workbook proves a broader snapshot boundary.

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

The representative workbook must determine the exact worksheet/header/date/product contract and whether the source site field is a durable key or an exact exported name. The source owner must also confirm export cadence and whether each workbook is one-site, multi-site selection or global. Until then, the endpoint correctly returns `WORKBOOK_CONTRACT_REQUIRED`; no UI or production release should treat the adapter as operationally ready.

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

Deterministic interpreter extension verification on 2026-08-28: migration `000010` completed
in a clean SQLite rebuild; the interpreter suite passed 16 tests/87 assertions; the full
suite passed 254 tests/1,381 assertions with 15 MySQL-only cases skipped; Pint, Composer
validation/audit, Vite build and Git whitespace checks passed. The disposable MySQL and real
reference-workbook gates remain outstanding as documented in
`documentation/deterministic-spreadsheet-interpretation-2026-08-28.md`.
