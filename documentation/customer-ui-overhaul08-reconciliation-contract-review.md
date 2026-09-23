# CustomerApp Master Import Reconciliation Report

Date: 23 September 2026

## Overall Result

READY_FOR_INTEGRATION.
Feature branch only. Automatic RedZebra date confirmation is deferred for every service.

### Superseded checkpoint

The earlier documentation-only checkpoint `a1ecc14d4aa5426c05ad5cd2ea2d594364565fd6`
blocked the entire workspace on a source-date decision. CUSTOMER-UI-OVERHAUL08A
explicitly supersedes that assumption: the workspace may proceed using retained
CustomerApp amendments while automatic source comparison remains deferred.
No historical decision ledger has been rewritten.

## Base SHA

`f94a750d820d5a635d6e4fcac87ef3bd02079fc2`

Branch: `codex/customer-ui-overhaul08`, isolated worktree
`C:\Users\madas\OneDrive\Documents\customerapp\reconciliation-workspace-20260923`.
The original shared checkout and concurrent branches were not changed. No newer
origin/main state was incorporated into the prescribed first-wave baseline.

## Visual Reference

Inspected `C:\Users\madas\Downloads\importreconcilliation.png` (the supplied file
uses a different spelling from the prompt). Implemented summary cards, a filtered
amendment list, a comparison panel, site context and a responsive stacked layout.
Unsupported illustrative product fields and mutation buttons were not implemented.

## Implemented Reconciliation

Office-only GET workspace at `/portal/office/workspace/imports/pilot/{upload}/reconciliation`,
linked from the existing supervised import review. It provides live Portal amendment
context for the source sites in a verified discovery manifest. It is not a historical
comparison snapshot or evidence that an amended plot appeared in this partial export.

Source totals, included/excluded counts and detected identities come from verified
stored discovery. Invalid or missing discovery blocks routing. Failed and superseded
imports carry explicit notices. Current Portal plot totals are labelled as such;
there is no invented count of source-projected plots or applied rows.

## CustomerCode Routing

Exact namespace + CUSTOMER_CODE + identity hash resolves an active, non-revoked
binding version to an active customer/site. Source identity is also compared exactly
after retrieval, independent of database case-insensitive collation. Site Name never
routes a row. Unknown/missing codes remain binding blockers; legacy non-CustomerCode
identities are not guessed. Binding queries are batched in groups of 400. Multiple
codes bound to one site do not duplicate global amendment/plot counts.

## Amendment Ownership / Amendment Resolver

CustomerApp owns amendments. No second persistence table or model was introduced.
`PortalAmendmentReadModel` reads the existing `call_off_date_negotiations` amendment
records through their `call_off_request_id`. Highest record ID with purpose amendment,
non-withdrawn status and a non-null requested date supplies the latest valid amendment
for that request. Original request/batch requested dates and agreed dates are untouched.
All prior amendments, including withdrawn and superseded ones, remain in paginated
request history. Office alternatives are not interpreted as customer requested dates.
Withdrawn, rejected and trashed requests are not included in the live amendment list.

### Backend integration seam

`PortalAmendmentReadModel::forSites()` is the bounded SQL read adapter. Its current
selection matches the in-progress Backend `CallOffRequest::effectiveRequestedDate()`
implementation inspected in the parallel OVERHAUL06 worktree on 23 September 2026.
No published OVERHAUL06 integration document was available at inspection. At integration,
verify parity against the final canonical resolver, especially amendment validity and
ordering. Adapt this one read adapter if Backend changes its model. Preserve set-based
querying: do not call a lazy relationship resolver for every displayed/source row.
No Backend model, transition, migration or page file was copied or edited here.

## Plot / Service Linking

Request `projected_plot_id` joins the actual plot primary key, and its batch must
belong to that plot's site. The projected service, when present, must reference the
same plot and effective service. Site IDs derive only from exact source bindings.
The same Plot Ref on another site cannot contribute an amendment or be selected
through a forged site/amendment parameter. Service identity remains one of Cavity
Closers, Windows, Snagging and CML; amendments on a plot are never collapsed into one date.

## Automatic Source Confirmation

| Service | Automatic source confirmation |
| --- | --- |
| Cavity Closers | DEFERRED |
| Windows | DEFERRED |
| Snagging | DEFERRED |
| CML | DEFERRED |

`SourceConfirmationContract` is the controlled per-service mapping seam. Every current
entry is unsupported with no Call Types or date field. Operational dates such as
`Plot To Be Installed` are not used as amendment confirmation.

## Match Classification

Deferred. No Synced/Confirmed result or zero-valued match metric is manufactured.

## Awaiting RedZebra Classification

The workspace uses "Portal amendment recorded" and "Awaiting RedZebra reconciliation".
This is an unresolved source-contract state, not a claim that a comparison found the
source still old. Existing source completion is presented separately.

## Conflict Classification

Deferred. No RedZebra conflict classification or automatic choice of date is made.

## Outstanding Source Contract

For each of the four services, approve:

- Recognised Call Type(s) and the exact authoritative RedZebra confirmation-date column.
- Whether that field really represents the customer's requested date for that service.
- Date parsing/timezone, blank/unrepresented handling and conflicting/repeated-visit values.
- The source baseline and revision/visit selection used for old-versus-new comparison.

After approval, implement and qualify the three match/still-old/conflict outcomes using
those real meanings, with stale-state checks and durable evidence before making claims.
Snagging currently has no inferred Call Type mapping. PC1 operational-date evidence does
not grant a confirmation-date contract for Windows or any other service.

## Site-Level Summary

Paginated source-site cards show CustomerCode, descriptive source name, exact target
customer/site, included source rows, current Portal plots, latest amendments per
request and closure by completion. Unknown bindings appear first. Site, service,
status and text filters apply to the amendment list.

## Plot-Level Comparison / Evidence

A request row and detail panel label previous RedZebra date, latest Portal amendment
and new RedZebra date separately. Both source values remain "Not compared". The Portal
value shows its requester/time and retained history. A normal request link opens the
existing authorised workflow; the reconciliation page offers no request mutation.

Technical disclosure identifies the import UUID, workbook/discovery hashes, current
binding UUID/version/epoch, actual plot ID, request/amendment UUIDs, any linked immutable
amendment-history event, adapter version and UTC observation time. Existing immutable
history remains untouched. Since no source comparison occurs, no comparison receipt is
created and no historical outcome is claimed. Future automatic comparisons must pin
source row/value, amendment version, classification and time using the approved evidence
architecture; they must not mistake this live view for an immutable receipt.

## Partial Export Behaviour

Omitted rows/plots/values do not delete, zero, reverse or overwrite anything. A real
synthetic upload test verifies that excluded CU4 rows do not route their site into the
workspace, that a changed descriptive site name still routes by code, and that an
omitted amended plot remains explicitly labelled live Portal context.

## Source Completion

Request/amendment completion and genuine projected source-completion facts close the
amendment context. Retained request closure survives a later source reversal. Customer
amendment dates never reopen a completed request. History remains visible.

## Wald Safety / Multi-Site Commit

Existing private-upload, fail-closed environment gate, audited setting, fresh Office
authority, binding, dictionary, exclusion, one-site preview/approval/atomic commit,
staleness, receipt, idempotency and immutable evidence paths are unchanged.
Multi-site reads only; multi-site commit is NOT enabled. No RedZebra writeback.
A focused query-log assertion verifies that reading the workspace performs no writes.

## Performance / Query Review

Bounded source discovery retains the existing 5,000-parent-row ceiling and existing
manifest size limits. Binding fetches are batched; Portal counts use grouped queries.
Amendments, selected-request history and source-site lists paginate independently at
10 records. The read path loads only the selected request's history page, never all
amendment history. A 100-site / 1,000-request fixture asserts at most 18 queries for
service-level read with selected amendment and history. No query per source row.

## Responsive / Accessibility

Browser inspected using a fictional server-rendered fixture at 1366x768, 768x1024,
390x844 and 320x740. Document widths respectively 1351, 753, 375 and 305 CSS pixels
(the difference is the vertical scrollbar); no horizontal overflow. Desktop retains
table plus comparison panel. Narrow screens use labelled stacked rows and a single
comparison column. Native labels, semantic headings, text statuses and visible focus
are present. Keyboard Tab reached the Status filter with a visible outline; Enter
expanded Technical provenance at 320 pixels, with long hashes wrapping. Existing
sidebar/header remains unchanged. This was visual/keyboard inspection, not a formal
screen-reader audit. HTTP feature tests cover filter/selection/pagination behaviour;
the static visual fixture itself has no live server actions.

## Migration / Database Impact

NONE. No migration, schema, persistent reconciliation records or amendment writes.
SQLite regression and disposable MySQL 8.4 qualification only; no production database.

## Tests / Checks

- Focused SQLite: `php artisan test tests/Feature/WaldReconciliation --compact`:
  **17 passed, 107 assertions** (11.990 seconds).
- Full SQLite: `php artisan test --compact`: **1,890 tests; 1,805 passed,
  85 skipped; 9,637 assertions** (166.749 seconds). Existing environment-gated
  skips remain; these are not claimed as passing tests.
- Disposable MySQL 8.4: `php artisan test tests/Feature/WaldReconciliation --compact`: **17 passed, 107 assertions** (96.214 seconds), including fixture creation.
- `php vendor/bin/pint --test`: passed.
- `php storage/app/composer.phar validate --strict`: valid.
- `php storage/app/composer.phar audit`: no security vulnerability advisories.
- `npm run build`: passed (Vite 8.1.4).
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

Initial test setup needed writable local cache directories and built frontend assets
following the crash. Test-only corrections normalised SQLite's midnight date rendering
and used an unambiguous plot search instead of text also present in site FNA100. No
business guard or production configuration was relaxed.

## Files Changed

- `app/Http/Controllers/OfficeImportReconciliationController.php`
- `app/Services/Reconciliation/MasterReconciliationWorkspace.php`
- `app/Services/Reconciliation/PortalAmendmentReadModel.php`
- `app/Services/Reconciliation/SourceConfirmationContract.php`
- `resources/views/office/reconciliation/show.blade.php`
- `resources/views/office/reconciliation/styles.blade.php`
- `resources/views/office/pilot-import/show.blade.php` (navigation link only)
- `routes/office-workspace.php` (authorised GET route only)
- `tests/Feature/WaldReconciliation/MasterReconciliationWorkspaceTest.php`
- `documentation/customer-ui-overhaul08-reconciliation-contract-review.md`

## Commit / SHA

The implementation commit contains this report; obtain its exact SHA with
`git log -1 --format=%H -- documentation/customer-ui-overhaul08-reconciliation-contract-review.md`.
The final task response records that SHA. The earlier documentation checkpoint remains
in history without amendment or rewrite.

## Push

NO.

## Deployment

NO. Feature branch only; no claim of production behaviour.

## Recommendation

Integrate the read-only workspace with the parallel Backend and Office Amendments
branches, verify adapter parity, and retain all four source-contract deferrals until
explicit service-by-service business approval and comparison qualification.
