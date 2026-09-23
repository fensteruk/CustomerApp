# CustomerApp Customers Workspace Redesign Report

Date: 23 September 2026

## Overall Result

READY_FOR_MERGE. Local feature branch only; not pushed or deployed.

## Visual Reference

Inspected `C:\Users\madas\Downloads\customerpage.png`. Reproduced the workspace hierarchy, in-page toolbar, rounded customer cards, count icons, restrained status/attention colours and visible Open customer footer. The existing global shell is deliberately unchanged.

## Header / Summary

Office / Customers heading, explanatory subtitle and primary Add customer action. Global database totals show customers, sites and active sites independently of the current search. Active-site counts use the existing site's active flag; customer status remains separately visible.

## Search / Filters

Customers-only sidebar filters moved into the content toolbar. Name search supports Enter or Search; status and A-Z/Z-A changes submit through the existing Alpine runtime. The ordinary GET form works without JavaScript. Clear filters and pagination preserve predictable navigation.

## Customer Cards

Customer name and status lead; technical identifiers and competing Edit actions are absent. Existing customer details and their editing actions remain unchanged.

## Site / Plot Counts

Real total and active site counts plus projected plot counts, scoped through the owning site's customer. Paginated at 18 cards; aggregate subqueries avoid per-card database loads.

## Attention State

Counts only non-trashed requests with the existing Awaiting Fenster status at active customers and active sites. A visible explanation makes this limited definition explicit. Positive counts receive amber reinforcement; text and numbers remain authoritative. No new attention engine or business transition.

## Empty Customer State

No sites yet with an existing Add site link for active customers. Inactive customers instead direct the user to review customer status; no invalid creation promise or new route.

## Whole-Card Interaction

The Open customer anchor has a card-sized hit area and visible keyboard focus. Add site is a separate sibling link above the overlay, not a nested anchor. Browser checks verified card-body mouse click, Tab focus, Enter navigation, separate Add site navigation and retained customer-detail Edit action.

## Tablet / Mobile

Three desktop columns, two tablet columns and one mobile column. Local browser checks at 1440, 820, 390 and 320 CSS-pixel widths; no horizontal page overflow. Inputs/selects are at least 44px tall; card footer is at least 48px. Narrow filters wrap without clipping their selected values; narrow card status sits below the name. These are browser viewport checks, not physical iPad-device certification.

## Backend Query Changes

Added `OfficeCustomerWorkspaceQueryService`: existing Office view policy, deterministic read-only aggregate counts, name search, status filtering, validated alphabetical sorting and pagination. The Customers controller action uses this service; other actions and the original query service are unchanged. Query-count regression verifies that adding customer cards does not increase the number of queries.

## Business Rule Changes

NONE. No authorization, ownership, assignment, Wald, deletion/purge, schema, migration or production database changes.

## Shared/Global Files Changed

No global layout, sidebar, CSS, JavaScript or shared UI components changed. Two shared PHP files contain tightly scoped compatibility edits: the Customers action/import in `OfficeAdministrationPageController.php`, and Customers fixtures/assertions in `OfficeAdministrationPresentationTest.php`. Integrators should preserve concurrent Users/Dashboard/Imports edits to those files if present.

## Tests

- `php artisan test`: 1,757 passed, 85 skipped, 9,303 assertions (1,842 total); no failures. Skipped tests were not converted to passes.
- Focused Customers and Office presentation suites: 40 passed, 217 assertions, including authorization denials, real counts, active/inactive/empty states, search/sort/pagination, links, escaping and bounded query count.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: no security vulnerability advisories.
- `npm run build`: passed after final responsive refinements.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.
- Local browser: fictional records only, separate SQLite preview; search, automatic status filtering, mouse/keyboard actions and responsive layout verified. No captured browser errors or warnings. Temporary browser/server stopped after checks.

An initial focused run preceded the initial asset build and failed because the Vite manifest was missing. Building assets and rerunning resolved that setup failure without weakening tests.

## Files Changed

- `app/Http/Controllers/OfficeAdministrationPageController.php`
- `app/Services/OfficeCustomerWorkspaceQueryService.php`
- `resources/views/office/customers/index.blade.php`
- `resources/views/office/customers/card.blade.php`
- `resources/views/office/customers/icon.blade.php`
- `tests/Feature/OfficeAdministrationPresentationTest.php`
- `tests/Feature/OfficeCustomersWorkspaceTest.php`
- `documentation/customer-ui-overhaul02-customers-workspace-2026-09-23.md`

## Commit / SHA

Dedicated branch: `codex/customer-ui-overhaul02`.

Verified origin/main baseline: `04840b6b5964c59ef13a2be71eb236c2fe496110`.

The independently mergeable commit containing this report is reported by exact SHA in the task's final handoff. The isolated checkout preserves the original checkout's unrelated Cavity Closer correction and concurrent UI worktrees.

## Push

NO.

## Deployment

NO. Production remains untouched by this task.

## Recommendation

Integrate this bounded Customers commit with the independently owned UI branches, resolve only overlapping Customers adapter/test lines if necessary, and rerun integration checks before any separately authorised production release.

CustomerApp Customers workspace redesign complete — ready for integration
