# CUSTOMER-UI-FINAL02 — Task A: Manage User

Date: 23 September 2026.
Status: feature branch only; ready for integration.
Branch: `codex/customer-ui-final02`.
Base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.

## Completed

- Prominent identity, email, distinct Portal role and active/inactive status.
- Global Office access is distinguished from external customer/site access; retained historical Office assignments do not imply restricted scope.
- External access warnings cover missing customer/sites, inactive customer/sites and incompatible recorded assignments.
- Existing edit and assignment controls now have grouped sections, help text, inline associated errors and a clear primary save action.
- Secondary deactivate/reactivate action retains the existing confirmation and history-preserving workflow.
- No controller, validation, policy, domain, global CSS/layout or migration changes.

## Files changed

- `resources/views/office/users/show.blade.php`
- `resources/views/office/users/form.blade.php`
- `resources/views/office/users/lifecycle.blade.php`
- `resources/views/office/users/field-error.blade.php`
- `tests/Feature/OfficeManageUserWorkspaceTest.php`
- `documentation/customer-ui-final02-manage-user-report.md`

## Checks

- `php artisan test --filter="OfficeManageUserWorkspaceTest|OfficeUserAdministrationTest|OfficeUsersWorkspaceTest"`: 39 passed, 259 assertions.
- `php artisan test`: 1,948 total; 1,859 passed; 89 skipped; 10,070 assertions; 174.108 seconds. Skipped optional database/environment suites were not enabled for this presentation-only change.
- `vendor/bin/pint --test`: passed after normalizing the new test file.
- `composer validate --strict`: passed.
- `composer audit`: no vulnerability advisories.
- `npm run build`: passed, Vite 8.1.4.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

Coverage includes Office and all external roles, historical Office assignments, assigned and unassigned external users, inactive customer/site warnings, cross-customer rejection, inactive/reactivate, guests and external-role denial. Existing administration tests retain actual assignment/removal and historical-scope guarantees.

Browser QA used an isolated localhost preview, a fresh disposable SQLite database and fictional records only. Reviewed 1366×768, 768×1024, 390×844 and 320×740 layouts. No horizontal page overflow observed. Verified visible keyboard focus, named fields, mobile stacking, real duplicate-email validation feedback, saving a site removal and reactivating an inactive account with zero sites. The account correctly remained unable to access a site after reactivation until assigned.

The new validation test explicitly carries the session cookie across the redirect, matching browser behavior. Initial failures were fixture/session setup issues; production rules were not changed.

## Database / release

No migrations or production database changes. No MySQL-specific behavior changed. No push or deployment. No excluded lead-time branch or commits merged. Existing development-dependency advisories reported by `npm ci` were left outside scope; the required production dependency audit passed.

Task B must start only after this Task A change has been committed separately.
