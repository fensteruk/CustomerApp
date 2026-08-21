# Sprint 3C Plot Overview QA Report

_21 August 2026_

## Result

**Pass.** The Site User can glance across one row (desktop) or one clearly bounded card
(mobile) and understand the current position of each plot across Cavity Closers, Windows,
Snagging and CML. Sprint 3D may be planned, but was not started during this gate.

## Overall product and prototype assessment

The implementation meets the management prototype's information model: one plot per row,
the exact four-service order, a prominent overall state and clear details/call-off actions.
The desktop table supports cross-service comparison, while the mobile cards preserve plot
identity, service labels and action separation. This is not a pixel reproduction, but it
achieves the intended quick, site-level understanding without exposing source identifiers,
operational stages or private reasons.

## Findings

### P0

None open.

### P1 resolved during this gate

- A public Plot Details UUID could be opened for a second assigned site while another site
  remained selected. The page then identified the selected site rather than the plot's
  site, and its Call Off action used the selected-site context. Plot Details now requires
  the UUID plot to belong to the active site and returns 404 for any out-of-context UUID,
  avoiding cross-site existence disclosure.

### P2/P3

None open.

## Functional results

- **Service states and order:** Passed. Cavity Closers, Windows, Snagging and CML remain in
  this order in desktop, mobile, Plot Details and filters. Not Called Off, Called Off —
  Awaiting Date, Date Agreed and Completed use customer-facing wording and dates are shown
  with the applicable state. Legacy Approved is presented as Date Agreed without changing
  history.
- **Source completion and overall status:** Passed. Source completion overrides Date
  Agreed; a reversal returns to the active Portal state. The five-state matrix, including
  Partially Completed precedence, is centrally calculated and covered by Pest.
- **Fully Completed:** Passed. Fully completed plots are retained, labelled and highlighted,
  hidden by default, returned by Show Completed and represented by an explanatory all-done
  empty state.
- **Plot Details and products:** Passed. The page shows active-site identity, overall state,
  the four services in order and positive CAS/PFD/BF quantities only. It does not render
  source identifiers or zero quantities.
- **Filters and pagination:** Passed. Partial/no-match search, service activity and
  customer-facing service-state filters narrow rows server-side; query parameters persist
  through pagination and Clear filters restores the default completed-hidden view.
- **Source freshness/missing source:** Passed by code and feature coverage. Last successful
  Portal synchronisation is shown when present; no-sync and missing-source wording is
  restrained and does not expose reconciliation terminology or issue codes.

## Security and roles

Direct UUID tests cover the active assigned site, another assigned but inactive-site
context, an unassigned same-organisation site, another organisation, malformed UUID and
an inactive account. All three external roles have equivalent assigned-site access.
Existing focused coverage confirms Fenster Office Staff remain global reviewers; Sprint 3C
did not change that scope. No customer-facing source Call No., internal ID or private
reason was found in the overview or Plot Details.

## Browser, mobile and accessibility

The real freshly seeded local portal was checked at 320px, 390px, 430px, 768px and 1440px.
At 320–768px, labelled cards separate consecutive plots and retain clear actions; at
1440px, the semantic table retains the exact headers and horizontal comparison. No
document-level horizontal overflow was detected. Visible Call Off, View details and filter
actions measured 48px or larger. The no-match filter state rendered clearly, and browser
console logs contained no errors or warnings.

Desktop has scoped table headers/row headers and a caption; mobile cards use headings and
labelled definition lists. Filters have labels, status is textual rather than colour-only,
and controls use the existing visible focus styling. Automated keyboard traversal in the
local browser bridge did not advance the reported active element, so this is a browser-tool
limitation rather than a claimed manual keyboard or screen-reader result. No screen-reader
test was performed.

## Query and regression results

The overview paginates in the database at 15 plots and eager-loads services and active
requests. The 15-plot/four-service query-count test remained at six or fewer database
queries, rather than adding a query per cell. Overview data does not load products; Plot
Details loads products only when needed.

The full suite covers the preserved New Call Off, withdrawal, Trash, Undo, rejected
resubmission, notifications, Office review and site switching workflows. No regression was
observed.

## Tests added

- Active-site UUID containment and non-disclosing response for assigned-other-site,
  unassigned-site and cross-organisation UUIDs.
- Malformed UUID safe failure and inactive-account access rejection.

## Files changed during QA

- `app/Http/Controllers/PlotDetailsController.php`
- `tests/Feature/Sprint3cPlotOverviewTest.php`
- `documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md`
- `documentation/sprint-3c-plot-overview-report.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

## Commands and results

- Local-environment verification: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0 and
  Git 2.55.0.windows.3 available.
- `php artisan migrate:fresh --seed` — passed after confirming `APP_ENV=local`, SQLite and
  the local `database/database.sqlite` target; no MySQL, Forge or production target used.
- `php artisan test tests/Feature/Sprint3cPlotOverviewTest.php` — passed: 12 tests, 60 assertions.
- `php artisan test` — passed: 162 tests, 792 assertions.
- `vendor\\bin\\pint --test` — passed.
- `npm run build` — passed. The sandboxed attempt hit its known Windows process-spawn
  restriction (`EPERM`); the same local build passed outside that restriction.
- `git diff --check` — passed.

## Remaining limitations

- MySQL/MariaDB rehearsal and concurrent source-import evidence remain production/live-source
  blockers from Sprint 3B; they do not block this local read-only dashboard gate.
- The CML expansion, source ownership/credentials, bank-holiday provider, amendment reasons
  and long-term retention remain TBC.
- Physical-device and screen-reader evidence remains outstanding.

Safe to begin Sprint 3D
