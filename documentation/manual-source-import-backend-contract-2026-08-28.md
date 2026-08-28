# Manual Source Import Backend Contract

Date: 2026-08-28

Status: backend contract implemented on `feature/manual-source-import-backend`; operational workbook contract blocked pending the representative SiteApp XLSX export.

## Boundary

This adapter is an Office Staff transport into the existing Sprint 3B source projection importer. It does not duplicate source-to-domain rules and does not make the Customer Portal an operational system. SiteApp remains authoritative. Excel objects stop at `XlsxSourceReader`; only validated `SourceRecord` DTOs enter `SourceProjectionImportService`.

The fixed source namespace is `siteapp-xlsx`. It is stored on bindings, projected plots, previews and import runs. `production-test-fixture` is not accepted by this adapter.

## Operational Workbook Blocker

No representative operational workbook was found in the repository or supplied attachment workspace on 2026-08-28. The following exact artifact is still required:

- an unmodified representative `.xlsx` export produced by the intended SiteApp/manual-export process;
- with its real filename, all worksheets, hidden worksheet/column state, header row, exact header text, formulas, blank rows, typed date cells, numeric cells and a safe sample of each relevant call/product shape intact;
- without production credentials and with confidential row values redacted only in a way that preserves cell types and structure.

Until that file is inspected, these environment-backed contract values intentionally have no defaults: worksheet name, Excel date system (`1900` or `1904`), exact source site key/name headers, Call No., plot, call type, job stage, Completed Date, optional source timestamp/Plot To Be Installed headers, and approved product abbreviation headers. Upload therefore returns `409 WORKBOOK_CONTRACT_REQUIRED` rather than guessing.

Mechanical XLSX files generated in automated tests use a clearly labelled test-only worksheet and headers. They prove parser mechanics, not the operational schema.

## Authorisation

Every route uses the existing `auth` and `active.portal` middleware. Its action additionally requires an active, complete, non-preview `Fenster Office Staff` account through a policy gate and form-request authorisation.

- Unauthenticated requests follow the application's normal login redirect.
- external Site Users receive `403`;
- inactive users are logged out and redirected by the existing active-account middleware;
- development preview users receive `403`;
- a preview is readable and committable only by the Office Staff user who initiated it;
- result records are readable by active non-preview Office Staff for audit/reconciliation.

No endpoint creates customers, sites, site assignments or source identities implicitly.

## Routes

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/portal/source-site-bindings` | List exact source-to-Portal site bindings. |
| `POST` | `/portal/source-site-bindings` | Create a binding. |
| `PUT` | `/portal/source-site-bindings/{binding_uuid}` | Change display label or mapped Portal site before projections exist. |
| `POST` | `/portal/source-imports/previews` | Upload one XLSX and build a non-mutating preview. |
| `GET` | `/portal/source-imports/previews/{preview_uuid}` | Read the initiating user's preview. |
| `POST` | `/portal/source-imports/previews/{preview_uuid}/commit` | Confirm the file hash and commit the authoritative rebuilt import. |
| `GET` | `/portal/source-imports/results/{result_uuid}` | Read import counts and reconciliation results. |

All identifiers exposed in these routes are UUIDs. Internal numeric database IDs, local paths and password/provider details are not returned.

## Source-site Binding Requests

Create request:

```json
{
  "source_namespace": "siteapp-xlsx",
  "source_site_key": "exact source key or exact source site name",
  "original_name": "original source display name",
  "display_name": "optional Office label",
  "portal_site_uuid": "existing-portal-site-uuid"
}
```

The namespace is normalised to lower case. Leading/trailing whitespace is removed from the key, names and UUID. The trimmed key remains case-sensitive and is stored with a SHA-256 lookup value; namespace plus exact-key hash is unique. A binding retains its UUID, exact key, original/display names, existing Portal site, creator and timestamps. The Portal site's existing customer organisation determines tenancy.

An update accepts `portal_site_uuid` and optional `display_name`. Once a binding has imported projections, moving it to another Portal site is blocked and requires explicit reconciliation. The source namespace, exact key, original name and creator cannot be rewritten by this endpoint.

Unknown workbook site keys yield the blocking category and code `SITE_MAPPING_REQUIRED`. They never trigger fuzzy matching or site/customer creation.

## Upload and Preview

The upload request is multipart with one `workbook` file. It accepts `.xlsx` only, with a 10 MiB default limit. Validation checks extension and allowed MIME, then verifies the ZIP signature and mandatory XLSX container entries. Macro payloads and embedded objects are rejected. The configured worksheet must exist and be visible; required exact headers must be unique and present. Formulas in imported source/product cells are blocking. `.xlsm` is not accepted.

The file is stored on the private Laravel `local` disk outside the public root using a random internal name. The original basename is retained only for audit. Temporary content is deleted after successful commit, on failed preview creation, or by the hourly expiry command. Ready previews expire after 30 minutes by default.

Preview creates only a `manual_source_import_previews` control/audit row and its private temporary file. It does not create a committed source run and does not mutate projections, call-offs, batches, negotiations, proposals, histories, notifications, source events or assignments.

Preview metadata contains:

```json
{
  "original_filename": "export.xlsx",
  "sha256": "64 lowercase hex characters",
  "row_count": 123,
  "namespace": "siteapp-xlsx",
  "worksheet": "confirmed worksheet",
  "blank_row_count": 2,
  "expires_at": "ISO-8601 timestamp"
}
```

Raw unused columns, commercial fields, internal file paths and internal database IDs are not included.

Each preview row contains:

- source row number;
- Call No.;
- source site key and original source site name;
- mapped Portal site name and customer name, if bound;
- plot reference;
- raw Call Type and mapped Portal service;
- approved product abbreviation/quantity pairs only;
- current source state and incoming source state;
- one diff category;
- structured warnings and errors with `code`, human-readable `message` and `blocking`.

Supported categories are `NEW`, `UNCHANGED`, `UPDATED`, `COMPLETED`, `COMPLETION_REVERSED`, `MISSING_FROM_SOURCE`, `SITE_MAPPING_REQUIRED`, `UNKNOWN_CALL_TYPE`, `INVALID` and `RECONCILIATION_REQUIRED`.

## Commit

Commit requires:

```json
{
  "confirm": true,
  "content_sha256": "the exact preview SHA-256"
}
```

The server never accepts client-produced rows or diff categories. Within a lock on the preview it verifies ownership, ready/unexpired status, confirmation hash, current workbook-contract fingerprint, private file existence and file hash. It reparses, revalidates and rebuilds the diff, then compares a fresh relevant-source fingerprint covering the represented site bindings and existing source projection state.

Blocking errors reject commit. A successful commit calls the existing transactional Sprint 3B importer with immutable context containing initiator, safe original filename, SHA-256 and represented exact source-site keys. The import run and committed preview are recorded atomically. Replaying a completed preview returns its existing result; it does not create another run.

## Source Mapping and Identity

Confirmed Call Type mapping remains central in `SourceCallTypeMapper`:

| Source Call Type | Portal service |
|---|---|
| `PC1` | Windows |
| `CC!` | Cavity Closers |
| `CM1` | Snagging |
| `CM2` | CML |

Whitespace/case is normalised for known codes. Unknown codes are blocking and never guessed.

`Call No.` remains the permanent idempotent identity under the existing projection schema, which currently enforces global uniqueness. A Call No. cannot change its site, plot or service association, and a projected plot/service cannot silently take a replacement Call No. Identity conflicts are blocking reconciliation results.

The projected plot identity is the source namespace plus exact bound source-site key plus plot reference. Product cells configured from the approved workbook abbreviation list become existing `ProjectedPlotProduct` projections. Blank quantity means zero; numeric zero is retained; positive numerics are accepted; invalid and negative values block the row. Unconfigured fields are ignored. `Site Value` and other commercial fields must never be configured as product/source output and are never returned by the adapter.

`Plot To Be Installed` is deliberately ignored by the adapter at this stage. It is not a requested date, agreed date, proposal date or Date Agreed history fact.

## Completion and Reconciliation

The existing Sprint 3B rules remain authoritative:

| Service | Completion stages |
|---|---|
| Cavity Closers | `CC08` |
| Windows | `CA02`, `CA03` |
| Snagging | `SN05` |
| CML | `CML4` |

A real Completed Date independently proves completion. A completion stage without a date completes without inventing a date and emits a reconciliation issue. A date without a completion stage completes using the real supplied date. Reversal follows the existing source-projection logic and never reopens obsolete negotiation.

Reconciliation readback includes severity, safe message, source row number when available, Call No., source site key, plot reference, blocking flag and resolution state. It covers unknown Call Type, mapping required, duplicate Call No., invalid values, identity conflict, missing source, missing completion date and unsafe completion reversal.

## Missing-source Scope

The workbook's completeness level is not yet known because the representative workbook is absent. The backend therefore uses the safest supported rule: missing evaluation is limited to `siteapp-xlsx` projections whose binding keys are actually represented by at least one row in the upload. It never evaluates other bindings or namespaces. Missing rows are retained, marked absent and reconciled; they are never deleted.

This supports one-site and multi-site files safely. A future global-snapshot claim requires evidence from the actual export contract before any broader scope may be enabled.

## Errors

| HTTP | Code | Meaning |
|---|---|---|
| `409` | `WORKBOOK_CONTRACT_REQUIRED` | Operational worksheet/header/date contract is not configured. |
| `422` | `INVALID_SOURCE_WORKBOOK` | File/container/header/row validation failed during preview. |
| `422` | `IMPORT_BLOCKED` | Authoritative commit revalidation found blocking rows. |
| `409` | `PREVIEW_NOT_COMMITTABLE` | Preview is expired, stale, altered, already unavailable or confirmation does not match. |
| `422` | `BINDING_REJECTED` | Binding identity/update violates the binding contract. |
| `403` | framework denial | Authenticated account is not authorised. |
| `404` | framework denial | Preview is not owned by the requesting Office Staff user or UUID does not exist. |

## Scheduled Cleanup

`source-import:prune-previews` expires ready previews whose TTL has elapsed and deletes their private files. It is scheduled hourly. It does not run or commit an import.

## UI Handoff

A later Office-only UI may consume these JSON routes. It must display the SHA-256, summary, categories and all blocking messages; require explicit confirmation; submit only the preview UUID, confirmation boolean and preview hash; and never send editable diff rows back as truth. Site mapping must be a deliberate selection of an existing Portal site. Customer-facing UI is outside this contract.
