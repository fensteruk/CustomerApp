# CustomerApp Imports Workspace Redesign Report

Date: 23 September 2026

## Overall Result

**READY_FOR_MERGE.** Feature branch only; not pushed or deployed.

## Baseline and Isolation

- Branch: `codex/imports-workspace-2026-09-23`.
- Base: fetched `origin/main`, `04840b6b5964c59ef13a2be71eb236c2fe496110`.
- Worktree: `C:\Users\madas\OneDrive\Documents\customerapp\imports-workspace-20260923`.
- The shared checkout's existing changes to `CallOffDateViewService.php`,
  `portal/review-requests/show.blade.php`, and `CavityCloserCallOffTest.php`
  were preserved. No concurrent branches were merged.

## Visual Reference

Inspected `C:\Users\madas\Downloads\importspage.png`. The implementation follows
its compact upload, recent-three cards, metrics, content previews, status emphasis,
and separate older-history structure. Existing global navigation is unchanged.

## Upload Experience

The upload panel retains the native labelled file chooser and uses “Upload and
check file”. Date, morning/afternoon slot, and the required freshness confirmation
are in an expandable section. Choosing a file or continuing opens that section.
Replacement warnings and explicit confirmation remain intact. The page explicitly
states that uploading does not apply data and review happens before application.
Upload validation and application file-size limits are unchanged.

## Latest Three Imports

Exactly the newest three uploads, ordered by creation time and ID, are primary
cards. Older uploads, including superseded revisions, appear only in the paginated
history. History filters and page navigation do not change these cards.

## Import Display Naming

Titles use checksum-verified discovery evidence: a single source's code and site
name, a numbered multi-site export, or “RedZebra master export” for 50 or more
detected source identities. Unknown evidence falls back to “RedZebra export”.
The size labels and threshold are presentation only and do not affect identity,
uniqueness, binding, or import behaviour.

## Headline Statistics

Shows discovery row totals, detected source identities, included/excluded rows,
and blocked rows from analysed selections when known. Single-site plot counts
come from included staged facts, not workbook row counts. Unknown values are
explicitly unknown. Multi-site workbooks do not invent an aggregate plot count.
Source identities are explicitly distinguished from matched CustomerApp sites.

## Workbook Preview

Recent single-site cards show up to five staged rows with plot, call type, and
service when analysis is available and current. Otherwise they show safely known
source sites. Multi-site cards show the first five source identities and the
remaining count. Private filenames and storage paths are not queried for display;
the page uses stored analysis rather than reopening workbook files.

## Status / Primary Actions

Summaries distinguish upload, analysis, clarification, blockers, fresh-review
requirements, review, approval, application, partial application, failure, and
supersession. Applied requires receipts for every detected source site; partial
receipts are not presented as a completed workbook. Each card has one primary
link to the existing controlled review/result page. These links do not approve
or apply data directly. The review page remains authoritative for current gates.

## Older Import Pagination

Server-side pages contain ten older uploads. Filters are All, Applied, Failed,
and Needs attention. Applied includes uploads with any receipt and explicitly
explains partial application. Pagination retains the filter and history anchor.
History becomes labelled rows/cards on smaller screens.

## Master Import Compatibility

Large exports are understandable as multi-site workbooks today. There is no
automatic routing, all-site commit, unattended import, or new orchestration.
The existing detail page remains the entry point for future result extensions.

## Current Wald Safety

Unchanged: Office authority, environment and application gates, private upload,
exact binding, one selected site per review/commit, non-mutating preview, explicit
approval, atomic apply, identical-upload handling, stale-preview rejection,
receipts, and audit history. Dictionary semantics are unchanged.

## Amendment/Reconciliation Scope

Not implemented. No call-off, amendment, or reconciliation domain changes.

## Responsive / Accessibility

Inspected fictional rendered pages at 1366×768, 768×1024, 390×844, and 320×768.
No horizontal page overflow was observed. Cards stack, metrics wrap, and history
uses labelled grid rows at narrow widths. Keyboard checks covered the export
disclosure and visible focus on primary actions and the history filter. Status
has text, file/date/slot/filter controls have labels, sections have headings,
and pagination has accessible labels. This was local layout qualification,
not a production-browser smoke test.

## Backend/Query Changes

Only the Imports index action now validates history parameters and delegates to
an Office-authorized read service. The service fetches three recent records and
one ten-row history page, with database aggregates for selected-site states and
receipts. It detects changed dictionary/application pins and expired previews.
Only recent single-site cards read staged payloads, bounded by the existing
500-row pilot ceiling. No history row loads staged payloads. The pre-existing
bounded replacement-slot lookup is preserved.

## Shared/Global Files Changed

None. No shell, sidebar, global styles, dashboard, Customers, or Users changes.

## Tests

- Focused SQLite: 14 passed, 1 skipped, 141 assertions.
- Focused disposable MySQL 8.4: 14 passed, 1 skipped, 141 assertions.
- The focused skip requires a private workbook fixture unavailable here.
- Updated gate/replacement screen-copy tests: 17 passed, 161 assertions. Their
  authorization and replacement checks remain intact.
- Focused coverage includes ordering, history separation/pagination, filters,
  titles, counts, source preview, escaped/corrupt evidence, actual
  analysis/preview/approval/commit, stale dictionary, expired preview, blockers,
  partial receipts, supersession, and denied external/inactive/preview accounts.
- `php artisan test`: passed, 1,757 passed, 85 skipped, 9,313 assertions
  (1,842 tests total; 134.979 seconds). Skips are the suite's environment-specific
  MySQL gates and private workbook qualification fixtures. The new query was
  separately qualified on disposable MySQL 8.4 as recorded above.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: no security vulnerability advisories.
- `npm run build`: passed.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check` and `git diff --cached --check`: passed.

## Files Changed

- `app/Http/Controllers/OfficePilotImportController.php`
- `app/Services/OfficeImportsWorkspaceQuery.php`
- `resources/views/office/pilot-import/index.blade.php`
- `resources/views/office/pilot-import/upload-form.blade.php`
- `resources/views/office/pilot-import/summary-card.blade.php`
- `resources/views/office/pilot-import/workspace-styles.blade.php`
- `tests/Feature/WaldPilot01/ImportsWorkspaceTest.php`
- `tests/Feature/WaldPilot01/WeekendPilotTest.php`
- `tests/Feature/WaldPilot01/WaldPilotSettingTest.php`
- `tests/Feature/WaldSource02/MasterExportReplacementTest.php`
- `documentation/customer-ui-overhaul04-imports-workspace-report.md`

## Migration / Database Impact

No migration or persistence changes. New application work is read-only.
Test databases and fictional uploads were local and disposable. Production was
not accessed or changed for this task.

## Commit / SHA

The implementation commit containing this report is the reviewable candidate;
its exact SHA is recorded in the task completion response.

## Push

NO.

## Deployment

NO.

## Recommendation

Integrate this qualified bounded branch after normal review.
Deployment remains a separate authorised action.

CustomerApp Imports workspace redesign complete — ready for integration
