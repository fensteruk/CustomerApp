# CUSTOMER-UI-OVERHAUL05 — Site Workspace Redesign

Date: 23 September 2026
Result: READY_FOR_INTEGRATION

## Baseline and delivery state

- Exact approved base: f94a750d820d5a635d6e4fcac87ef3bd02079fc2.
- Branch: codex/customer-ui-overhaul05.
- Worktree: C:/Users/madas/.codex/worktrees/customer-ui-overhaul05/CustomerApp.
- This report is included in the implementation commit; its exact SHA is recorded in Git history and the completion response.
- Feature branch only. Nothing pushed or deployed. No production verification is claimed.
- Original checkout's unrelated changes to CallOffDateViewService.php, portal/review-requests/show.blade.php and CavityCloserCallOffTest.php were preserved.

## Visual reference and scope

Inspected C:/Users/madas/Downloads/siteview.png. It depicts a Plot 591 amendment screen rather than a Site overview. Requested clarification, then used its pale blue background, white cards, blue actions, spacing and text/status treatment as the stated visual assumption. This is a Site-specific adaptation, not an exact reproduction of a supplied Site layout.

Only the Office Site workspace and authorised external Site dashboard changed. No Plot Details, Office Amendments workspace, master import reconciliation or global layout was changed.

## Presentation

- Header prioritises site name, customer, active state, total plots, role-specific attention and next activity.
- Inline plot search and existing overall-status choices; 15 plots per page; existing plot ordering and Site User completed-plot filtering retained.
- Cards show plot, Windows/Doors counts, four service states, upcoming requested/agreed date and existing attention/amendment/early-request indicators.
- Explicit product zero remains zero; missing product groups say Not supplied. Existing dictionary groupings are used and BF remains separately inspectable.
- Site Users get a large keyboard-accessible Plot Details link, separate selection controls and existing call-off actions.
- Office has no authorised Plot Details route at this baseline. Its large card target is a native disclosure containing existing site-held plot/source information and existing request links, without extending permissions.
- Upcoming activity is limited to three active requests with future dates, clearly distinguishes requested from agreed dates, and excludes completed/trashed records and amendments on hold from the concise activity list.
- Attention counts distinct plots requiring the current role's response using existing request/negotiation records.
- Office administration, full source references, binding evidence and source details are secondary disclosures. Required evidence and copy-reference behaviour remain available.
- External users retain the site switcher, existing filters and existing call-off lifecycle controls, with no Office/private-source controls exposed.

## Backend query changes

New read-only SiteWorkspaceQueryService composes the existing PlotOverviewQueryService result, page-sized product/request data, distinct-plot attention counts and a three-record upcoming query. It uses existing Office policy and external site access checks. Existing controller Site actions provide this presentation data. Queries explicitly constrain both plot and request batch to the current site.

No persistence, new attention state machine, authorization change, source binding change or business transition was introduced. Query-growth coverage checks one versus thirty plots.

## Verification

| Check | Result |
| --- | --- |
| Focused relevant feature tests | 49 passed; 336 assertions |
| Full php artisan test | 1,798 passed; 85 skipped; 9,612 assertions; 169.597 seconds |
| Final SiteWorkspacePresentationTest | 10 passed; 85 assertions |
| php vendor/bin/pint --test | Passed |
| composer validate --strict | Passed |
| composer audit | No security vulnerability advisories |
| npm run build | Passed, Vite 8.1.4 |
| npm audit --omit=dev | Zero vulnerabilities |
| git diff --check / staged whitespace check | Passed before commit |

The full suite reported 1,883 tests including 85 skipped tests; skipped coverage is not claimed as executed. Existing presentation assertions and isolated view fixtures were updated for the new markup. Authorization, cross-site denial and lifecycle expectations were retained. No dependency lockfiles changed.

### Browser checks

Used an isolated localhost server and disposable fictional SQLite fixtures, never production as a development environment.

Both Office and Site User layouts were checked at 1366 × 768, 768 × 1024, 390 × 844 and 320 × 740. Document width equalled viewport client width, with no horizontal overflow. Also checked empty site, one plot with long site/plot names, and a 38-plot site with pagination.

Verified inline search, keyboard card disclosure/navigation, visible focus, external selection enabling the existing call-off button, readable product/status content, text status labels and at least 44px visible action targets. Bulk request submission was not performed. Native details preserve keyboard access; no fixed content-obscuring overlay was introduced. Existing sidebar scrolling remained available.

This was browser viewport testing, not a real-device or automated accessibility audit.

## Migration / database impact

NONE. No schema, migration, production data or business-state changes. Local disposable QA fixtures only. No new MySQL-specific behaviour requiring additional qualification.

## Business logic changes

NONE. Permissions, assignments, call-off transitions, amendment rules, lead times, Wald semantics, binding and plot identity are unchanged.

## Shared / global files

No global layout or general-purpose shared component changed. New site-workspace partials are used only by the two Site views. Changes in OfficeAdministrationPageController are confined to the Site action and its imports; SiteDashboardController changes only Site presentation/filter normalization.

## Exact files changed

1. app/Http/Controllers/OfficeAdministrationPageController.php
2. app/Http/Controllers/SiteDashboardController.php
3. app/Services/SiteWorkspaceQueryService.php
4. resources/views/office/sites/show.blade.php
5. resources/views/office/sites/overview.blade.php
6. resources/views/office/sites/plots.blade.php
7. resources/views/portal/site-dashboard.blade.php
8. resources/views/site-workspace/styles.blade.php
9. resources/views/site-workspace/summary.blade.php
10. resources/views/site-workspace/card-content.blade.php
11. resources/views/site-workspace/cards.blade.php
12. resources/views/site-workspace/upcoming.blade.php
13. tests/Feature/AuthenticatedSiteDashboardTest.php
14. tests/Feature/CallOffLifecycleUiTest.php
15. tests/Feature/CustomerSidebarWorkspaceTest.php
16. tests/Feature/OfficeAdministrationPresentationTest.php
17. tests/Feature/Sprint2aUiTest.php
18. tests/Feature/SiteWorkspacePresentationTest.php
19. documentation/customer-ui-overhaul05-site-workspace-2026-09-23.md

## Integration recommendation

Integrate this bounded commit with the other second-wave branches, review any overlapping Site controller/presentation assertions and run the combined regression gate. Review the Site adaptation of the supplied Plot mockup during integration. No global dependency blocks this branch. Deployment remains a separate authorised operation.