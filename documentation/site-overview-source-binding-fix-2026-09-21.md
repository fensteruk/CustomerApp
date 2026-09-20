# Site Overview source-binding consistency — 21 September 2026

## Scope and release state

**READY FOR RELEASE REVIEW**, with the known baseline test/audit findings below.
**Feature branch only**, not deployed.
Branch: `codex/fix-site-overview-source-binding`.
Parent/base: `0ac7082141156fdff29d296881bbb7d3299e20b5`, freshly fetched from
`origin/main` before implementation. The user confirms that the core production
workflow has passed acceptance; this task neither repeats nor changes that acceptance.
Candidate subject: `Fix site overview source binding status`.

Only the misleading Site Overview source-binding status is repaired. No product-label,
import-status, Filters/menu, User UI or wider terminology changes are included.

## Root cause established before implementation

`OfficeAdministrationQueryService::siteSummary()` returns the historical placeholder
`source_binding_state = NOT_YET_INTEGRATED`. The Overview summary printed that as
"Not available yet"; its lower card also required that stale property to be `ACTIVE`,
so it fell through to "Not linked" even when active binding rows were supplied.

The controller already passes `OfficeAdministrationQueryService::sourceBindings()`
as `$items` for both Overview and Source Binding. That existing authorised read model
joins each binding to its active version and scopes the result to the customer and
site. It returns availability, `has_active_binding` and the detailed active rows.
The working Source Binding section renders those same rows. The defect was the
Overview's use of an unrelated placeholder, not absent binding data or plot projection.

## Repair and presentation

Only `resources/views/office/sites/overview.blade.php` changes application behaviour.
Both Overview status locations consume the existing `$items` availability and
`has_active_binding` fields. There is no new query, duplicated binding definition,
first-row assumption, source-name hard-coding or inference from plot existence.

| Existing authoritative result | Overview | Source Binding detail |
| --- | --- | --- |
| Active CustomerCode binding | Linked | Existing active CustomerCode details |
| Active legacy identity | Linked | Existing exact identity/type details |
| Multiple active bindings | One concise Linked state | Existing individual active rows |
| Available, no active binding | Not linked | Existing Not linked state |
| Binding information unavailable | Not available yet | Existing unavailable state |

The linked card says "Linked to source data. View Source Binding for connection
details." The existing View Source Binding action remains. Identities are deliberately
not enumerated on Overview, so legacy identities do not become invented CustomerCodes.
The independent existing Source reference field is unchanged.

## Security and data impact

Office policies, site/customer containment and customer routes are unchanged. Tests
cover Office access, 403 responses for all three external roles on both Office sections,
and customer dashboards that still display assigned plots without private binding values
or Source Binding content. No Office metadata is added to customer views.

- Migration required: **NO**.
- Data rewrite: **NO**.
- Dependency change: **NO**.
- Production data/bindings changed: **NO**.
- Binding creation, import selection, CREATE/REUSE, projection, replacement lineage,
  assignments, permissions and historical records changed: **NO**.

## Verification environment

Tests run in an isolated archive of the base plus the candidate view/test, with copied
locked dependencies. This avoids the workspace's dependency junctions resolving into
another checkout. Runtime app/bootstrap/config/database/resources/routes and lockfile
hashes match the candidate workspace. PHP 8.4.25, Composer 2.9.3, Node 20.19.6 and
npm 10.8.2 were verified. PHP uses a per-process local extension configuration, without
changing the user's system configuration. Automated tests use in-memory SQLite.
No MySQL/locking semantics or SQL changes require a new concurrency exercise.

The app restart interrupted the first full run; it is not counted as a completed gate.
The resumed run saves its result in the disposable runtime, outside Git.

## Automated gates

Before the view repair, the new regression file produced **6 passes / 3 failures**:
CustomerCode, legacy and multiple-binding Overview cases failed to show Linked.
After applying the repair and clearing disposable compiled views, **9 tests passed**.
Final strengthened regressions explicitly check both Overview status locations:
**9 tests / 69 assertions passed**. The adjacent Office/source/access group passed
**99 tests / 557 assertions**. The final combined run passed
**108 tests / 626 assertions**.

Full Pest run: **1,677 total; 1,595 passed; 81 skipped; 1 error; 8,336 assertions**,
318.967 seconds. Exit 2 is not a green full-suite result. The sole error remains the
previously recorded `Sprint3dMultiSubmissionTest` weekday-sensitive fixture:
"Each requested date must be a weekday within six months." Its test and production
action are unchanged. No unrelated baseline repair was attempted.
The full run loaded the nine-test regression file before its final seven additional
presentation assertions; the final **9/69** and **108/626** reruns verify those additions.
Runtime application code is identical between these runs and matches the candidate.
The 81 skipped cases are not claimed as executed verification.

Commands (run with the verified PHP configuration; checks do not install/update packages):

```text
php vendor/pestphp/pest/bin/pest tests/Feature/SiteOverviewSourceBindingTest.php --compact
php vendor/pestphp/pest/bin/pest tests/Feature/SiteOverviewSourceBindingTest.php tests/Feature/OfficeAdministrationPresentationTest.php tests/Feature/Wald05/SourceBindingTest.php tests/Feature/CustomerAdminSite02BackendTest.php tests/Feature/OfficeStaffOrganisationModelQaTest.php --compact
php vendor/pestphp/pest/bin/pest --compact
php -l tests/Feature/SiteOverviewSourceBindingTest.php
php -l resources/views/office/sites/overview.blade.php
php vendor/bin/pint --test
php "C:/Program Files/Herd/resources/app.asar.unpacked/resources/bin/composer.phar" validate --strict
php "C:/Program Files/Herd/resources/app.asar.unpacked/resources/bin/composer.phar" audit
npm run build
npm audit --omit=dev
npm audit
git diff --check
```

Syntax, Pint and `git diff --check` pass. Documentation paths resolve and the diff was
reviewed for contradictory current-state claims. A final read-only Frontend/UX review
found no actionable issue. Composer strict validation passes and audit reports no advisories.
Frontend build passes (Vite 8.1.4). Production npm audit reports **0 vulnerabilities**.
Full npm audit retains **4 development-only advisories: 2 high, 2 moderate**, in
browserslist, nanoid, baseline-browser-mapping and postcss. These are existing baseline
findings; lockfiles are unchanged and no unrelated dependency fixes were attempted.

## Manual local UX acceptance

Normal sign-in with a synthetic Office account in a disposable, localhost-only app;
no production account or data used. Browser accessibility output and screenshots were
inspected for all four screens:

1. LOCAL Linked Willow Overview: Linked in the summary and linked explanatory card.
2. Its Source Binding section: two Active identities, a CustomerCode and a legacy exact
   source-site name. Overview does not duplicate those technical details.
3. LOCAL Unbound Meadow Overview: Not linked in both locations.
4. Its Source Binding section: Not linked, with no active binding recorded.

The linked fixture has zero plots, also demonstrating that the status is not derived
from plot count. Automated coverage additionally checks an unbound site with a plot.
Both screen pairs agree. Existing card layout remains readable and concise.
Temporary browser tab closed after verification; no fixture, database, generated asset
or temporary helper is included in the candidate.

## Files changed

- `resources/views/office/sites/overview.blade.php`: consume existing authoritative state.
- `tests/Feature/SiteOverviewSourceBindingTest.php`: nine regression/access cases.
- `documentation/site-overview-source-binding-fix-2026-09-21.md`: scope, evidence and limits.
- `current_sprint.md`: current bounded task and feature-branch status.
- `ROADMAP.md`: controlled release-review step and separate next UX task.
- `HANDOVER.md`: current candidate handover; older records remain historical.

Unrelated changes in other checkouts were preserved. No merge, push, production tag,
deployment or production mutation is part of this task.

## Remaining UX findings — recorded, not repaired

- Product details expose unexplained codes such as `VS 2`.
- Completed imports mix In Progress, Committed and Create under this site wording.
- Filters hides the main navigation.
- View/Edit User behaviour is inconsistent.
- Projected plots is overly technical wording.

## Recommended next step

After separate approval, deploy and verify the Overview correction against the existing
approved test site and an unbound site. Then take customer product labels as the next
bounded UX task. This report does not authorise deployment.
