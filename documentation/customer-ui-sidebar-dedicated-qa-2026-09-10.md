# CustomerApp Sidebar Dedicated UI QA Report

> Historical candidate QA evidence. The integrated RC1 scope and current release status are
> defined by `customerapp-next-release-rc1-build-report-2026-09-10.md` and DEC-061.

Date: 10 September 2026

QA gate: `CUSTOMER-UI-SIDEBAR02`

Original candidate: `feature/customer-ui-sidebar-workspace` at `d76ddbba47ba7b16e6376d2f089ab06b4cc46187`

Base: frozen RC1 `ac250a8e9eef4b40591872de9275d802c76e4fba`

## Overall Result

**PASS after three bounded QA corrections.** The redesign is a clear improvement and is suitable for the next release integration stream. It remains excluded from RC1 and has not been merged, pushed or deployed.

## Candidate and Scope Verification

The original 14-file candidate diff was inspected from the frozen RC1 base. It contains layout, filter-query and regression-test work only. It adds no migrations, packages, lockfile changes, routes, Wald/Import Studio/admin exposure, operational workflow, or new authorisation policy. The assigned-site selector remains scoped by `Site::assignedTo($user)`, Office retains its established global role scope, and direct cross-role route checks returned HTTP 403.

The following clear defects were corrected during this gate:

- **UIQ-01 — P1:** a valid, very long unbroken plot reference expanded the whole document to 1,773px at a 1,265px desktop client width and 914px at a 305px mobile client width, pushing critical actions off-screen. Plot labels now shrink and wrap within both table and card layouts. Post-fix document and body widths equal the client width at 1280 and 320 viewports, with row actions visible.
- **UIQ-02 — P2:** the compact header overflowed by 12px at 320px because Filters, its badge, the mobile wordmark, notification control and logout control could not fit. The redundant header wordmark is now hidden below 360px; the drawer retains the product identity and all essential controls remain visible with 44px minimum targets.
- **UIQ-03 — P1:** opening the drawer while the notification panel was open allowed the notification outside-click handler to return focus to the bell behind the modal drawer. Outside-click and navigation closure now suppress notification focus restoration. Focus lands on `Close menu`, remains inside the drawer, and returns to Filters only when the drawer closes.

## Before / After

**CLEAR_IMPROVEMENT.** At 1920px the measured outer table width is 1,631px, 415px wider than the frozen layout's capped 1,216px. At 1440px the raw area is 1,167px versus 1,216px before, but the former full-width filter panel is removed, all seven columns and both row actions remain visible, and the 992px compact table footprint makes the workspace materially easier to scan. The hierarchy and action visibility remain sound at 1366px and 1280px.

## Viewport and Interaction Results

| Area | Evidence and result |
| --- | --- |
| Desktop | PASS at 1920x1080, 1600x900, 1440x900, 1366x768 and 1280x800. Measured document widths matched scrollbar-adjusted client widths: 1905, 1585, 1425, 1351 and 1265px. Sidebar remained 232px and did not dominate the common-width layouts. |
| Sidebar width | PASS. Navigation and filter labels remained readable; long site names truncate within the selector rather than expanding the page. A width change is not justified by the evidence. |
| Breakpoint | PASS at 1281, 1280 and 1279px after the intended 200ms transition settled. Exactly one sidebar remained; the drawer trigger and card/table representations switched correctly without a horizontal jump. |
| Tablet | PASS at 1024x768, 834x1194 and 768x1024. Drawer controls, two-column service cards, row actions and bulk controls remained reachable. |
| Mobile | PASS at 390x844, 375x812, 360x800 and 320x568 after UIQ-01/UIQ-02. No horizontal overflow, clipped critical content or sub-44px primary target was observed. |
| Sticky header | PASS. At scrollY 1491 on 1440x900, the page header occupied 0–65px and the table header 64–108px; columns remained aligned and actions unobstructed. |
| Keyboard/focus | PASS. Drawer entry focus, Tab and Shift+Tab wrapping, Escape close, focus return, notification Escape, and notification/drawer interaction were exercised. Visible application focus styles remained present. |
| Semantics | PASS smoke. One main landmark, a labelled primary navigation landmark, labelled filter forms, modal drawer semantics below 1280px, a table caption, column `scope=col`, and plot row `scope=row` were present. Statuses retain text alongside non-essential colour/dot styling. |

## Filters and Empty States

Site filters passed individually and in combinations for plot text, all five overall statuses, all four services, service status and Show Completed. Apply produced the expected query string, reload retained state, Clear removed optional state, and the active-filter count matched selections. Six malformed/unsupported query shapes now have explicit regression coverage and safely redirect with validation errors.

Office defaulted to its established `awaiting_fenster` view. Combined status + site + service produced three active filters and a persistent query string; Clear restored the established default rather than pretending no default filter exists. Office no-results, assigned-site no-plots, no-matching-plots and no-notification states were deliberate and readable.

Role navigation matched the contract:

- Site Manager, Assistant Site Manager and Finishing Foreman: Plots & Call-Offs, Trash, Notifications.
- Fenster Office Staff: Review Requests, Notifications.

A Site role received HTTP 403 for direct Office review navigation, and Office received HTTP 403 for direct site-dashboard navigation.

## Long Lists and Performance

The isolated local SQLite fixture contained 250 synthetic plots, including 50 in each overall presentation state. The 50, 100 and 250 result sets all remained server-paginated at 15 rendered items. Query counts stayed fixed at four or five per page rather than growing with the result total. The slowest recorded local filtered request in the final sample was 78.027ms; the 250-result query was 31.728ms. No sticky-header jank, repeated layout thrashing or unbounded client-side loop was observed. These are local QA measurements, not production benchmarks.

## Browser Compatibility

Chromium-family browser QA passed. Firefox and a separately controlled Edge instance were unavailable in the local QA environment, so no claim is made for a fresh engine-specific pass. The implementation uses standard sticky positioning, `inert`, dialog attributes and keyboard handling, and the automated suite remained green.

## Screenshots

QA screenshots are stored under:

`C:\Users\JoshO\Documents\CustomerApp\.cursor\customer-ui-sidebar02-qa-20260910\screenshots`

The final set includes populated 1920, 1600, 1440, 1366 and 1280 desktop views; a 1440 sticky-header view; 1024 and 768 tablet views; 390 and 320 mobile views; fixed 1280 extreme-content evidence; and a populated 1440 Office Review Requests view. Pre-fix extreme-content captures are retained beside their fixed equivalents for audit comparison.

## Automated Verification

- Focused sidebar + notification tests: `php vendor/pestphp/pest/bin/pest tests/Feature/CustomerSidebarWorkspaceTest.php tests/Feature/PortalNotificationUiTest.php --compact` — **17 passed, 114 assertions**.
- Related dashboard, plot, call-off, review and notification regressions across 12 feature files — **121 passed, 748 assertions**.
- Full suite: `php artisan test --compact` — **331 passed, 38 skipped, 2,367 assertions** across 369 tests.
- Isolated 250-plot fixture verifier — **passed** for 50/100/250 sets, all five overall statuses, combined statuses, pagination/query counts and synthetic-login integrity.
- `npm run build` — **passed**, Vite 8.1.4, 5 modules, 2.79s. Two sandboxed attempts first failed with `spawn EPERM`; the same build passed when the local build process was permitted to start its bundled child process.
- `vendor\bin\pint --test` — **passed**.
- `composer validate --strict` — **passed**.
- `composer audit` — **passed**, no security advisories; Composer continued without its unwritable local cache.
- `git diff --check` — **passed**.

## Production Impact and Release Recommendation

No migrations, deployment, production access, merge to RC1/main or push occurred. Synthetic records and screenshots are confined to the ignored local QA workspace.

**Release recommendation: NEXT_RELEASE.** The redesign provides a meaningful everyday workspace improvement, the three discovered regressions have bounded fixes and regression coverage, and there is no remaining P1/P2 defect from the available QA scope. Keep it out of the already frozen RC1.

CustomerApp sidebar dedicated QA passed — ready for next release integration
