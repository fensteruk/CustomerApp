# Customer-facing product labels — 21 September 2026

## 1. Verdict

**READY FOR RELEASE REVIEW**. Feature branch only; no push or deployment.

The customer display now uses existing approved product meanings without changing source
facts, quantities, import semantics, projection, identities, bindings or call-off workflow.

## 2. Root cause

`PlotDetailsController` passed positive `ProjectedPlotProduct` models directly to the view.
`portal/plots/show.blade.php` printed `product_code` verbatim and float-formatted its quantity,
so `VS = 2.000` appeared as `VS 2` instead of a business name. The dictionary already contained
the correct description, but the page did not consume it.

Two other customer-facing presentations (`call-offs/matrix` and `call-offs/confirm`) also
rendered canonical code/quantity arrays directly. These now use the same display presenter;
`BuildCallOffMatrixAction`, server-held canonical review rows, signatures, dates and submission
actions remain byte-for-byte unchanged.

## 3. Product source of truth

Approved meanings live in `documentation/siteapp-import-data-dictionary.md` and executable
`App\SourceImport\Semantics\Dictionary\CustomerAppDictionary`. Its `product()` result supplies
the approved description and exclusion classification to `CustomerProductPresenter`.
The dictionary, identity/fingerprint, importer and aggregation code are untouched. No new code
to label mapping exists in Blade or the presenter. No semantic result/evidence object is passed
to customer views: the presenter emits only `label` and formatted `quantity`.

Office's established aggregate presentation (Windows, Doors, Bifold) and source evidence are
not routed through this customer-only presenter and are unchanged.

## 4. Customer presentation

Existing product-card typography and definition-list layout are retained:

| Canonical fact / previous label | Plot Details label and quantity | Call-off summary |
| --- | --- | --- |
| VS = 2.000 / VS 2 | Vertical Slider — 2 | Vertical Slider × 2 |
| TT = 3.500 / TT 3.5 | Tilt and Turn — 3.5 | Tilt and Turn × 3.5 |
| BF = 1.000 / BF 1 | Bifold — 1 | Bifold × 1 |
| ZZ9 = 4.000 / ZZ9 4 | Product (ZZ9) — 4 | Product (ZZ9) × 4 |

The dash above describes the paired card fields; the actual Plot Details card places quantity
beneath its label. Known raw codes are not displayed secondarily because no customer need for
them was established. Unknown historical codes retain a neutral, escaped `Product (code)`
fallback, preserving the real fact without pretending to know its meaning.

## 5. Supported product labels

All 13 approved mappings are exercised individually by HTTP presentation tests:

| Code | Approved label |
| --- | --- |
| VS | Vertical Slider |
| TT | Tilt and Turn |
| BAY | Bay Window |
| ALI | Aluminium Windows |
| AOV | Automatic Opening Vent Window |
| FI | Fire Window |
| PSU | PVC Door Utility |
| PSG | PVC Door Garage |
| CDF | Composite Door Front |
| CDU | Composite Door Utility |
| CDG | Composite Door Garage |
| PSP | PVC Sliding Patio |
| BF | Bifold |

No approved friendly name exists for arbitrary legacy codes such as synthetic `ZZ9`.
That remains a business-data gap, not an invitation to infer a mapping.
`CAS`, `FLU`, `PFD`, `GLS`, `WP`, `MISC` are explicitly excluded from customer product output
by DEC-040 and the approved dictionary. They are filtered only at presentation, not deleted.
The old Sprint3c test expecting CAS/PFD visibility conflicted with that newer contract; its
expectations were corrected and independent tests prove all six facts remain stored.

## 6. Quantity semantics

- Positive stored quantities remain exact; decimal strings lose only trailing fractional zeros.
- No float conversion is used to format the visible value. The existing positive-display gate
  remains equivalent to `hasPositiveQuantity()`; it is not import validation or arithmetic.
- Explicit zero stays stored and hidden in these existing positive-only customer displays.
- Missing facts stay absent; no product row or zero assertion is created.
- Conflict refusal, consolidation, valid/invalid quantity rules and source absence semantics
  are unchanged. The presenter performs no aggregation, winner selection or persistence.
- Tests check per-label pairing, 0.001, 3.125, 999999999.999, zero, absence and unchanged records.

## 7. BF behaviour

BF now displays the approved name **Bifold**. Its canonical code/quantity remain unchanged.
Tests prove positive BF still gives the existing five-week earliest date and zero BF the
four-week date. The existing normal-earliest-date text remains on call-off review screens.
No new per-product lead-time wording or business rule is invented.

Separate pre-existing concern: `ProjectedPlotProduct::isBifold()` currently uses substring
matching for BF, rather than the approved exact-code rule. A hypothetical historical `XBF`
could therefore affect lead time. The presenter does not use that method for label lookup,
and this task does not change or claim to correct the wider detection rule. Review separately.

## 8. Customer / Office boundary and local walkthrough

Customer-facing raw codes remain only in the explicit unknown-code fallback. Known labels
contain no raw code or source evidence. Dictionary-excluded evidence stays private.
Private metadata leak found: **NO** on inspected customer pages and covered test responses.
Existing site/organisation checks are unchanged; all three external roles get the friendly
Plot Details display and cannot reach unrelated plots or Office site administration.

The computer-use walkthrough used normal login and synthetic data in a separate local SQLite
database, a loopback-only PHP server and production-style (non-preview) role checks. No live
CustomerApp or Forge session was used. Source facts were:
VS 2, TT 3.5, AOV 1, BF 1, PSU 2, FI 0, ZZ9 4, CAS 9.
Site Manager Plot Details visibly showed the six expected positive customer rows, including
readable long AOV wording and truthful ZZ9 fallback. FI zero and excluded CAS were absent;
private source/plot identifiers were absent. Screenshot and accessibility text were inspected.
Office's same synthetic plot still showed Windows 6.500, Doors 3.000, Bifold 1.000 and its
private source reference. Office did not lose traceability. Both local accounts were logged
out and the temporary browser tab closed. No call-off was submitted.

## 9. Tests and checks

Runtime: isolated copied locked dependencies, PHP 8.4.25, Composer 2.9.3, Node 20.19.6,
npm 10.8.2. A per-process local PHP extension configuration was used; no system settings changed.
Candidate app/bootstrap/config/database/resources/routes/tests file hashes match the tested
runtime (zero mismatches). No production database, account or source workbook was used.

| Check | Result |
| --- | --- |
| CustomerProductPresentationTest | 24 passed / 119 assertions |
| Customer/Plot Details/Office/call-off/Overview group | 89 passed / 538 assertions |
| Wald03 + WaldCustapp2 + Wald05 group | 687 passed / 25 skipped / 3,359 assertions |
| Full Pest suite | 1,620 passed / 81 skipped / 8,465 assertions, exit 0 |
| PHP syntax and repository Pint | PASS |
| Frontend Vite build | PASS |
| Composer strict validation / audit | PASS / no advisories |
| npm production audit | 0 vulnerabilities |
| npm full audit | 4 existing development advisories: 2 high, 2 moderate |
| Whitespace / documentation paths / diff review | PASS |
| Independent Frontend/UX review | No actionable blocker |

Commands, run with the verified PHP configuration:

```text
php vendor/pestphp/pest/bin/pest tests/Feature/CustomerProductPresentationTest.php --compact
php vendor/pestphp/pest/bin/pest tests/Feature/CustomerProductPresentationTest.php tests/Feature/Sprint3cPlotOverviewTest.php tests/Feature/OfficeAdministrationPresentationTest.php tests/Feature/Sprint3dCallOffWorkflowTest.php tests/Feature/Sprint3dBulkCallOffQaTest.php tests/Feature/SiteOverviewSourceBindingTest.php --compact
php vendor/pestphp/pest/bin/pest tests/Unit/Wald03 tests/Feature/WaldCustapp2 tests/Feature/Wald05 --compact
php vendor/pestphp/pest/bin/pest --compact
php -l app/Presenters/CustomerProductPresenter.php
php -l app/Http/Controllers/PlotDetailsController.php
php -l tests/Feature/CustomerProductPresentationTest.php
php vendor/bin/pint --test
php "C:/Program Files/Herd/resources/app.asar.unpacked/resources/bin/composer.phar" validate --strict
php "C:/Program Files/Herd/resources/app.asar.unpacked/resources/bin/composer.phar" audit
npm run build
npm audit --omit=dev
npm audit
git diff --check
```

The first focused run found an incorrect new test expectation: Office totals are deliberately
decimal:3 strings, not trimmed strings. Only the new assertion was corrected to existing
6.500/3.000/1.000 output; Office code was untouched. No test was weakened.
A broad Pint invocation in the disposable runtime initially included old non-repository
walkthrough helper files; repository-only Pint subsequently passed. Helpers are not shipped.
The historical weekday-sensitive full-suite test passed on Monday 21 September 2026; no
fixture or application fix was made, and it remains a date-sensitive baseline concern.
Development advisories (browserslist, nanoid, baseline-browser-mapping, postcss) are unchanged.
Skipped environment-dependent tests are not claimed as executed MySQL/concurrency verification.
This read-only presentation change introduces no locking or database-specific semantics.

The app crash happened after completed verification and browser cleanup. Resume checks proved
all eight changed runtime/test files still matched the verified checkpoint; work was preserved.

## 10. Files changed

- `app/Presenters/CustomerProductPresenter.php`: dictionary-backed customer-safe rows.
- `app/Http/Controllers/PlotDetailsController.php`: feed authorised facts into the presenter.
- `resources/views/portal/plots/show.blade.php`: show label and exact formatted quantity.
- `resources/views/components/customer-product-summary.blade.php`: shared escaped summary.
- `resources/views/portal/call-offs/matrix.blade.php`: use summary component only.
- `resources/views/portal/call-offs/confirm.blade.php`: use summary component only.
- `tests/Feature/CustomerProductPresentationTest.php`: focused naming/quantity/role/privacy/BF/Office tests.
- `tests/Feature/Sprint3cPlotOverviewTest.php`: reconcile stale excluded-code display expectations.
- This report, `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md`: candidate status and handover.

No unrelated changes were included; other checkouts and the deployment-report commit are preserved.
No fixture scripts, databases, source files, credentials, generated build assets or dependency
directories are committed.

## 11. Data / migration impact

Migration: **NO**. Data rewrite: **NO**. Import replay: **NO**. Dependency changes: **NO**.
No production data, bindings, assignments, imports, environment or access permissions changed.

## 12. Release candidate

Branch: `codex/customer-product-labels`.
Subject: `Present approved customer product labels`.
Parent: `12d765ccec90b05973365f7845a54c184fb2325d` (preserved local documentation commit).
Production/code base: `590503b6c80ea186bc6001e43a11ab528298961a`, confirmed by fresh origin fetch.
The final completion message records the candidate commit SHA; this report is included in it.
No merge to main, push, deployment or production tag is part of this task.

## 13. Remaining UX findings

Keep separate: mixed import completion wording (In Progress / Committed / Create under this
site), Filters hiding main navigation, inconsistent View/Edit User behaviour and Projected plots
terminology. None is fixed here. Unknown legacy label definitions and substring BF detection
are separately recorded gaps, not silently expanded scope.

## 14. Recommended next step

After separate approval, deploy and verify customer-facing labels on the existing approved
test plot. Then address import completion/status wording as the next bounded UX task.
