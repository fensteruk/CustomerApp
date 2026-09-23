# CustomerApp First-Wave UI Integration Report

Date: 23 September 2026

## Overall Result

The first UI wave is integrated into local `main` and qualified for the second redesign wave. No push or deployment occurred. The original checkout retains three unrelated, pre-existing uncommitted Cavity Closer changes; they were not staged, committed, stashed or overwritten.

## Baseline and accepted ancestry

Local and `origin/main` initially matched at `04840b6b5964c59ef13a2be71eb236c2fe496110`. A fresh fetch found no origin advance. Normal merge commits preserve the exact accepted commits in this order:

1. Dashboard: `bafd4f0a5552d67ae768fe89945a8e36abde5f72`.
2. Customers: `58e23d3010304b98a402d14cfb590b2fb1d35021`.
3. Users: `6b1dc7ec8a84ad532c77b69acf385f36983d1ea1`.
4. Imports: `ce02eb7a528d2620de11c8bdd84dec7c35ae24a2`.

Conflicts: NONE. Branch diffs were reviewed against current `origin/main`: page-specific presentation, read-only query services and focused tests; no migrations, role/permission changes, call-off/lead-time domain changes, Wald semantic or import-commit/gate changes, or customer/site ownership changes.

## Integration correction

The approved Office Dashboard became the landing route, but the shared Office sidebar had no Dashboard entry or active marker. A single Dashboard link, route highlight and focused assertion were added. No other global layout redesign was made.

## Combined workspace QA

- Dashboard: real attention categories for amendments, import reviews and call-off actions; active sites, open requests, pending amendments, last applied import, upcoming/recent activity and quick links. Empty states remain honest. A fictional local preview showed current counts and direct links.
- Customers: database totals, in-page filters, responsive cards, full-card link, valid Add site empty state and scoped attention counts. Query count is constant as customer cards grow.
- Users: totals and role/customer/status filters; fictional Office, assigned external and unassigned external cards showed Global Office access, site/customer access, No sites assigned, Assign site and Manage user as appropriate.
- Imports: compact upload, exactly three recent cards, source-safe names and truthful stage-dependent statistics. Fictional history showed 10 records on page 1, a next-page link, and a working Failed filter. Upload remained separate from apply; the existing Wald gate was unchanged.
- Navigation: Dashboard, Customers, Users and Imports routes and current-page markers verified. The mobile menu opened and dismissed with the keyboard. Customer, User and Import filters are in their pages; no obsolete Office filter panel remained in the sidebar.

The local browser was checked at 1366×768, 768×1024, 390×844 and 320×740 CSS pixels. All four pages had semantic primary headings, text status indicators, no horizontal document overflow and no controls extending beyond the viewport. Customers use 3/2/1 card columns. Links, focus and import-history pagination were exercised; no browser warnings/errors were captured. These are browser viewport checks, not physical device or assistive-technology certification. The four supplied local mockups were inspected and the integrated pages remain recognisably aligned with them; the existing sidebar is retained for the later shell wave.

## Query / performance review

Dashboard activity lists are capped; Customers and Users lists paginate with eager/scoped card data; Imports uses three recent uploads, ten history rows per page, and a bounded stage preview. No observed N+1 query growth. Users customer-filter options currently load the full customer name list for the selector; this is a future scale consideration, not an observed integration failure. No cache or database schema change was introduced.

## Tests and tooling

- Four focused workspace suites after the navigation correction: 40 passed, 285 assertions.
- Final Dashboard navigation suite: 7 passed, 74 assertions.
- Final full integrated suite after the navigation correction: 1,789 passed, 85 skipped, 9,536 assertions across 1,874 tests; no failures.
- Pint: passed. Strict Composer validation: passed. Composer audit: no advisories. Vite build: passed. Production npm audit: zero vulnerabilities. `git diff --check` passed.

No MySQL-specific test was triggered: there were no migration or database-query merge conflicts and no observed SQLite/MySQL divergence in the read-only additions. This does not qualify the separate lead-time branch's outstanding MySQL 8.4 concurrency/migration work.

## Git and release state

The lead-time commits `7ada4ff` and `e5fe235` were not merged; they remain on `codex/customer-calloff-history01`. No UI-wave migration was added. A bounded integration commit records the sidebar correction and this handoff; its exact SHA is in the final task response. `origin/main` remains at the original baseline. Current production served SHA was not safely established: the available older Forge deployment tab returned Page not found. No production inference was made from that tab.

The main checkout is **not clean** solely because the three Cavity Closer edits were already present before integration. Their paths are `app/Services/CallOffDateViewService.php`, `resources/views/portal/review-requests/show.blade.php`, and `tests/Feature/CavityCloserCallOffTest.php`. They are intentionally preserved for their owning work; they are not part of the UI integration commit.

The localhost server was stopped. The temporary copied fictional SQLite database and its two temporary seed scripts were removed after browser QA; the original fictional preview fixture in the separate Customers worktree was not changed.

Recommendation: start the second UI wave from the final local `main` commit in separate clean worktrees, leaving the three unrelated edits in this checkout for their owner. Production requires a separate authorised release review and deployment.
