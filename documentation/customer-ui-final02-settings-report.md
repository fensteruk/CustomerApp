# CUSTOMER-UI-FINAL02 — Task B: Office Settings

Date: 23 September 2026.
Status: feature branch only; ready for integration.
Branch: `codex/customer-ui-final02`.
Base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.
Task A was completed and committed first as `2c366e80c2d19f28e3c128adfa933dbb2d9d85d3`.

## Completed

- Redesigned the existing Wald Settings page beneath the unchanged global shell.
- Separated actual pilot availability, saved application setting and read-only deployment access.
- Grouped the existing enable/disable form, required change reason, enabling confirmation, validation feedback and save/cancel controls.
- Added responsive immutable history cards with old/new value, actor, explicit UTC timestamp and reason; existing server pagination is retained.
- Kept the supervised Office-only and one-selected-site guidance visible.
- No new settings routes or fake editable lead-time controls.
- Documented the future four-service lead-time page and data, edit, audit, historical-context, accessibility and qualification requirements in `customer-ui-final02-lead-time-ui-contract.md`.

## Files changed

- `resources/views/office/settings/wald.blade.php`
- `tests/Feature/OfficeSettingsWorkspaceTest.php`
- `documentation/customer-ui-final02-lead-time-ui-contract.md`
- `documentation/customer-ui-final02-settings-report.md`

Task A's six files are listed separately in `customer-ui-final02-manage-user-report.md`.

## Verification

- `php artisan test --filter="OfficeSettingsWorkspaceTest|WaldPilotSettingTest|WaldEnvironmentGateTest"`: 34 passed, 153 assertions.
- `php artisan test`: 1,960 total; 1,871 passed; 89 skipped; 10,166 assertions; 158.100 seconds. Optional environment/database suites were not enabled for this presentation-only task.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: no vulnerability advisories.
- `npm run build`: passed, Vite 8.1.4, seven modules transformed.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

Focused coverage includes all four environment/application gate combinations; actual availability; required reason and confirmation feedback; preserving attempted selection after validation; escaped immutable history; pagination; all three external roles denied GET/PUT; guest/inactive denial; and existing stale, current-authority and strict environment-gate tests. Existing domain assertions were not weakened.

Browser QA used only the isolated localhost preview and fictional SQLite records. Reviewed 1366×768, 768×1024, 390×844 and 320×740 layouts, with no page-level horizontal overflow observed. Verified keyboard focus, labeled controls, mobile history wrapping, failed confirmation feedback, and successful enable/disable audit history. The deployment gate remained OFF throughout: saving Enabled still truthfully showed Pilot unavailable. Returned the local application setting to Disabled, stopped the QA server and closed the temporary tab.

## Boundaries / integration

- Lead-time feature merged: NO. `7ada4ff`, `e5fe235` and `codex/customer-calloff-history01` remain excluded.
- Domain changes: NONE. Existing controller, action, policy, validation, gate parsing, lock/version checks and audit transactions remain unchanged.
- Global layout/CSS changes: NONE.
- Migrations: NONE. Only a fresh disposable local QA database was migrated.
- Production data/configuration impact: NONE.
- Push: NO.
- Deployment: NO.
- Future lead-time behavior needs separate backend qualification; its absence does not block this explicitly documentation-only integration contract.
