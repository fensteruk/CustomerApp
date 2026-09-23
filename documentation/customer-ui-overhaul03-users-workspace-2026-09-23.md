# CustomerApp Users Workspace Redesign Report

Date: 23 September 2026.

## Overall Result

READY_FOR_MERGE — feature branch only. No push or deployment.

## Visual Reference

Inspected `C:\Users\madas\Downloads\userspage.png`. The Users page follows its
summary cards, main-page filters, user cards, role emphasis, attention state and
access summaries. The existing shared application shell is retained.

## Page Summary

Real totals show all non-preview users, Office users, external users and external
users missing a customer or site assignment. Counts include inactive accounts,
are independent of list filters, and explain that scope beside the summary.

## Search / Filters

Name/email search, distinct Portal roles, customer and active/inactive status are
in the main page. GET filters persist through pagination. Existing search and
status behavior is preserved. The Users page supplies no sidebar filter slot.

## Office User Presentation

Office cards show Fenster Office Staff and Global Office access. They do not show
zero assigned sites as a problem. Active Office cards have a calm Access configured
label; inactive Office cards explicitly explain that access is paused.

## Site User Presentation

Each existing external role keeps its own label. Cards show name, email, account
status, customer, full assigned-site count and up to three site names. Remaining
assignments are counted and remain available in the existing details view.
Inactive customers/sites are labelled. Site-name previews are eagerly loaded and
bounded per account, avoiding a query per card.

## Access Attention States

Missing site assignments show No sites assigned and This user cannot access a
site yet. Assign site opens the existing edit form. A missing external customer
shows No customer assigned and Configure access; it never implies Office access.
These are presentation of existing records, not new permission rules.

## Manage User Action

One primary Manage user link per card opens existing details and edit capability.
The attention panel has a secondary contextual assignment link. Real links have
explicit account names and keyboard focus; there is no nested whole-card link.
Add user remains the primary page action.

## Tablet / Mobile

Verified with fictional records in a separate disposable local SQLite database:

| Viewport | Card columns | Horizontal overflow |
| --- | --- | --- |
| 1366 x 768 | 3 | None |
| 768 x 1024 | 2 | None |
| 390 x 844 | 1 | None |
| 320 x 720 | 1 | None |

Browser screenshots and DOM measurements confirmed wrapping of long emails,
customer/site names and readable attention text. All Users-page controls met the
44px height check at tablet/phone sizes. Keyboard Tab reached Manage user with a
visible focus ring at 320px; Enter opened the expected details page. Search and
the Assign site destination were verified in the browser. Viewport emulation was
used, not physical iPad hardware. The temporary server was stopped afterwards.

## Authorization

Unchanged. Existing route and query policies remain authoritative. Focused tests
include all three external-role denials, guest/inactive account denial and the
existing cross-customer assignment and lifecycle tests.

## Business Logic Changes

NONE. Read-only list filtering, summary counts and site previews were added.
No permission, account mutation, lifecycle, lead-time or call-off action changed.
No schema/migration or production database impact.

## Shared/Global Files Changed

NONE. No shared CSS, layout, navigation or other workspace page changed.

## Tests

- Focused Users workspace and existing user administration tests: 24 passed,
  150 assertions.
- `php artisan test`: 1,848 total; 1,763 passed, 85 skipped, 9,328 assertions.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed using the existing Herd Composer executable.
- `composer audit`: no security vulnerability advisories.
- `npm run build`: passed.
- `npm audit --omit=dev`: 0 vulnerabilities.
- `git diff --check`: passed.

PHP used the existing local extension configuration. Dependency audits used the
machine's trusted root certificates through temporary process environment
variables; TLS verification was not disabled. No dependency files changed.

## Files Changed

- `app/Http/Controllers/OfficeUserPageController.php`
- `app/Services/OfficeUserAdministrationQueryService.php`
- `resources/views/office/users/index.blade.php`
- `resources/views/office/users/card.blade.php`
- `resources/views/office/users/icon.blade.php`
- `tests/Feature/OfficeUsersWorkspaceTest.php`
- `documentation/customer-ui-overhaul03-users-workspace-2026-09-23.md`

## Commit / SHA

Branch: `codex/customer-ui-overhaul03`.
Fetched baseline: `04840b6b5964c59ef13a2be71eb236c2fe496110` (`origin/main`).
Candidate SHA is recorded in the final task handoff. Separate lead-time commits
`7ada4ff` and `e5fe235` are not included.

## Push

NO.

## Deployment

NO.

## Recommendation

Integrate the bounded Users workspace change with the other UI branches, then
follow the separately authorised release process. No shared dependency blocks
this change.

CustomerApp Users workspace redesign complete — ready for integration
