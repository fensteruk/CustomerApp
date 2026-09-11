# CustomerApp sidebar workspace redesign evidence

> Historical candidate evidence. The accepted integration and current release state are
> defined by `customerapp-next-release-rc1-build-report-2026-09-10.md` and DEC-061.

Date: 2026-09-09

Branch: `feature/customer-ui-sidebar-workspace`

Base: frozen RC1 commit `ac250a8e9eef4b40591872de9275d802c76e4fba`

## Scope and boundary

This work changes the authenticated CustomerApp presentation only. It does not change call-off transitions, persistence, authorisation policy, migrations, Wald, import, admin, production configuration or deployment state.

## Before-change layout audit

The frozen RC1 site dashboard used a centred `max-w-7xl` page with large responsive gutters. The site context occupied three tall summary cards, the full filter form occupied a five-column card above the results, and the table used larger cells inside nested horizontal-overflow containers. The site selector was a separate navigation action rather than part of the working controls. The shared authenticated layout had a header but no durable navigation workspace.

The Office review page repeated its filters in a full-width card above the review cards. Site and Office users therefore spent main-page width and vertical space on controls instead of the records they were working with.

## Implemented layout

- Added one shared, fixed 232px Fenster-branded sidebar for authenticated pages.
- Kept the sidebar visible at desktop widths and independently scrollable.
- Changed the authenticated main shell to consume all width remaining after the sidebar.
- Converted the sidebar to a labelled, modal off-canvas drawer below 1280px.
- Kept the top bar to user context, notifications, logout and the mobile Filters trigger.
- Kept unauthenticated login, reset and local role-preview pages outside the authenticated shell.

## Role-aware navigation

Site roles receive server-rendered links for Plots & Call-Offs, Trash and Notifications. Fenster Office Staff receive Review Requests and Notifications. Wald, imports and admin site management are not present. Existing route middleware and policies remain authoritative.

## Filters moved

Site workspace:

- authorised assigned-site selector;
- plot search;
- five established overall statuses: Nothing Called Off, Call-Offs In Progress, Dates Agreed, Partially Completed and Fully Completed;
- exact service vocabulary: Cavity Closers, Windows, Snagging and CML;
- the existing service-status filter under Advanced filters;
- Show Completed;
- Apply filters and Clear filters;
- active-filter count.

Office workspace:

- existing request status;
- globally authorised site selection;
- service type;
- Apply filters and Clear filters;
- active-filter count.

All filter state remains GET/query-string state. The new overall-status UI uses the existing `PlotOverallStatus` presentation enum and query relationships. It does not change stored workflow state.

## Main workspace and density

The main workspace no longer has a centred maximum width. The top filter panels and tall summary cards have been removed from the data column. The site table now has compact cells, a 992px minimum footprint, the four service states, overall status and two existing row actions. The header is sticky without introducing a nested scrolling region. Tablet and mobile retain the established card representation and a page-scoped sticky bulk-action bar.

Width evidence:

| Viewport | Frozen RC1 outer table area | New outer table area | Useful-area result |
| --- | ---: | ---: | --- |
| 1440px | 1216px, constrained by `max-w-7xl` and 32px gutters | 1167px after the permanent 232px sidebar | Raw table width is 49px smaller, but the table minimum footprint falls to 992px and the filters no longer consume the data column, leaving materially more usable column headroom. |
| 1920px | 1216px, still capped | 1631px measured in the browser | 415px / 34.1% more outer table width. |

At 390px the document `scrollWidth` equalled the 375px content width reported by the browser, so no page-level horizontal overflow was introduced. At 1920px the document `scrollWidth` equalled its 1905px scrollbar-adjusted client width.

## Accessibility and responsive verification

- Semantic `nav`, `aside`, `main`, labelled regions and labelled filters are present.
- The drawer exposes `aria-expanded`, `aria-controls`, dialog/modal semantics and `inert` while closed.
- Opening moves focus to Close menu; Tab/Shift+Tab are contained; Escape closes the drawer and returns focus to the Filters trigger.
- The existing notification Escape handler now returns focus only when its own panel was open, preventing it from stealing drawer focus.
- Filter rows and primary mobile controls retain at least 44px touch height.
- Status components retain text and icons/dots, so meaning is not communicated by colour alone.
- Browser inspection covered 390x844, 1024x768, 1280x900, 1440x900 and 1920x1080 layouts, plus site-role and Office-role navigation.

## Local screenshots

The screenshots are local QA output and remain ignored by Git:

- `storage/app/private/ui-qa/customer-ui-sidebar01/site-dashboard-desktop-1440x900.png`
- `storage/app/private/ui-qa/customer-ui-sidebar01/site-dashboard-tablet-1024x768.png`
- `storage/app/private/ui-qa/customer-ui-sidebar01/site-dashboard-mobile-390x844.png`
- `storage/app/private/ui-qa/customer-ui-sidebar01/office-review-desktop-1280x800.png`

## Verification evidence

- New sidebar and notification focused run: 10 tests, 82 assertions passed.
- Related UI regression run: 74 tests, 388 assertions passed.
- Full suite: 362 tests discovered; 324 passed, 38 skipped, 2335 assertions; no failures.
- `composer validate --strict`: passed.
- `composer audit`: passed with no security advisories; Composer reported only that its sandbox cache directory was not writable and continued without cache.
- `npm run build`: passed with Vite 8.1.4.
- `git diff --check`: passed before final documentation review.

No migration or deployment was run. The screenshots use seeded local SQLite data and controlled development-preview users only.
