# CUSTOMERAPP-WALD-UX02 — implementation report

Date: 20 October 2026

Baseline: `origin/main` at `08cd40130832eb2e122e7c02253eb030e9b3d2d2`

Branch: `codex/customerapp-wald-ux02`

Worktree: `C:\Users\madas\Documents\customerapp\CustomerApp\.release-worktree\wald-ux02`

## Completed

The shared Site Plot listing now offers an accessible `Table | Cards` choice to Office staff
and assigned external Site Users. The existing Cards partial is unchanged. The Table is a
compact view over the same authorised 15-record paginator and the same presentation objects.

The choice is immediate and local to the page. A valid stored preference is scoped to the
signed-in account and wins over the default. Storage reads and writes fail safely. Without a
stored preference, Sites with at least 100 total Plots default to Table and smaller Sites
default to Cards. The stable Site total drives this threshold, so filtering does not change it.

Only the selected Alpine template is added to the live document. This avoids duplicated active
checkboxes, IDs and submission values. The parent Site User selection array remains in place
while switching views. A `noscript` Cards fallback preserves the existing basic listing and
links when JavaScript is unavailable.

The Table provides:

- Plot reference;
- Cavity Closers;
- Windows;
- Snagging;
- CML;
- Overall status;
- role-appropriate actions and compact supporting details.

External Plot references retain the existing authorised Plot detail route. Office Plot
references are keyboard-operable controls which open the row's existing source/Plot disclosure;
no new route or permission was added. External product details remain at Cards parity (Windows
and Doors). Office retains the existing Bifold and source-only disclosures.

Reference sorting is allowlisted to `plot_asc` and `plot_desc`, remains string based, adds the
record ID as a deterministic tie-breaker, and preserves values such as `033`, `APT 03` and
`Com 4`. Search, existing status filters, counts, query parameters and pagination are shared by
both presentations.

## Historical recovery evidence

The last compatible pre-card table is at commit
`f94a750d820d5a635d6e4fcac87ef3bd02079fc2` in
`resources/views/portal/site-dashboard.blade.php`.

Commit `16edc4b5bca5ff2f293053954da5a432c8f615d9` replaced it with the shared Site workspace Cards.
The historical columns were Plot, Cavity Closers, Windows, Snagging, CML, Overall Status and
Actions. Service cells used the text/date `x-plot-service-status` component rather than source
presence ticks. It used a page-scoped selection and the existing paginator.

The implementation recovers that compatible layout while continuing to use current components,
read models, authorisation and routes. It does not restore obsolete business logic.

## Actual pages and routes

- Office: `office.workspace.sites.show` with `section=plots`, rendered through
  `resources/views/office/sites/plots.blade.php`.
- Assigned Site User: `portal.site-dashboard`, rendered through
  `resources/views/portal/site-dashboard.blade.php`.
- Shared listing: `resources/views/site-workspace/plot-list.blade.php`, with the unchanged
  `cards.blade.php` and the new `table.blade.php`.

Both routes continue through `SiteWorkspaceQueryService` and `PlotOverviewQueryService` and use
one 15-record paginator. The original bounded-query regression test grows to 30 fictional Plots.
Independent Backend QA separately measured a 130-Plot Site: 15 records per page across nine
pages, with exactly four queries—the same count as a one-Plot Site. Its independent focused run
also passed 13 tests with 122 assertions.

## Status and source accuracy

No service, status, date, completion, approval, import or permission semantics changed. The
Table uses the current `x-plot-service-status` and `x-plot-overall-status` components.

The current presentation precedence remains source completion, an approved/date-agreed Portal
request, an open amendment hold, another active Portal request awaiting a date, then Not Called
Off. Doors remains a product quantity and is not a fifth service.

Office-only source evidence remains a separate disclosure with the existing Completed, Not
completed, Not supplied and No service information supplied wording. External users do not
receive source references or Office source inspection data. Missing quantities remain Not
supplied. The Table introduces no ticks and does not turn unresolved evidence into a positive
fact.

## Tests and checks

The worktree uses the original checkout's installed dependencies. Pest was run from the original
root with:

```text
C:\Users\madas\.config\herd\bin\php84\php.exe
-d auto_prepend_file=C:/Users/madas/Documents/customerapp/CustomerApp/.release-worktree/wald-ux02/storage/framework/testing/ux02-worktree-bootstrap.php
vendor/bin/pest
--test-directory=.release-worktree/wald-ux02/tests
--configuration=C:/Users/madas/Documents/customerapp/CustomerApp/.release-worktree/wald-ux02/storage/framework/testing/phpunit.ux02.xml
```

Reflection confirmed these runtime paths:

- `App\Services\PlotOverviewQueryService` loaded from this UX02 worktree;
- `Tests\TestCase` loaded from this UX02 worktree.

Results:

- UX02 focused presentation file: **13 passed, 122 assertions**.
- Affected Site workspace, Sprint 3c and Office presentation files: **56 passed,
  336 assertions**.
- Full suite: **2,096 tests; 2,002 passed; 92 skipped; 2 known baseline failures**.
- `vendor\bin\pint --test`: passed after formatting the focused test file.
- `composer validate --strict`: passed.
- `npm run build`: passed with Vite 8.1.4.
- `npm audit --omit=dev`: passed, zero vulnerabilities.
- PHP syntax checks for the changed PHP/test files: passed.
- `git diff --check`: passed.

The two remaining full-suite failures are the previously reproduced
`WeekendPilotTest` exact-key expectations for READY_TO_COMMIT and COMMITTED legacy manifests.
The actual manifests contain `calloff_rows`, `inventory_rows` and
`requires_manual_review`. UX02 does not touch that code or those tests.

`composer audit` reported the known baseline of six advisories across five dependencies
(`filament/filament`, `laravel/framework`, `league/commonmark` twice, `league/flysystem`, and
`symfony/http-foundation`). This presentation task did not change dependencies.

## Browser and independent QA handoff

The implementation owner did not run a separate lengthy browser programme after Main assigned
final browser, context and access verification to independent Backend QA. The available
purpose-built in-app browser can be used against a localhost-only synthetic environment.

Suggested synthetic verification:

1. Create one fictional Site below 100 Plots and one at or above 100 Plots, including references
   `033`, `APT 03` and `Com 4`.
2. Use fictional Office, Site Manager, Assistant Site Manager and Finishing Foreman accounts
   with the normal assigned/unassigned Site boundaries.
3. Verify the default, persisted preference, storage-failure fallback, keyboard focus, mobile
   horizontal scroll, selection retention, sort/filter/page query retention and role-specific
   details/actions.
4. Confirm external responses contain no source reference and that an unassigned Site remains
   denied by the existing policy.

No real customer data, workbook or import action is needed.

## Files changed

- `app/Http/Controllers/OfficeAdministrationPageController.php`
- `app/Http/Controllers/SiteDashboardController.php`
- `app/Services/PlotOverviewQueryService.php`
- `resources/views/office/sites/plots.blade.php`
- `resources/views/portal/site-dashboard.blade.php`
- `resources/views/site-workspace/plot-list.blade.php`
- `resources/views/site-workspace/styles.blade.php`
- `resources/views/site-workspace/table.blade.php`
- `tests/Feature/SiteWorkspacePresentationTest.php`
- `documentation/customerapp-wald-ux02-implementation.md`

## Migration, Git and production impact

There is no migration, schema, database, queue, dependency, import or backend state-machine
change. No live import or production data was used.

This report is included in the local candidate commit; the exact commit SHA is supplied in the
handoff. Nothing was merged, pushed or deployed. Production remains unchanged.
