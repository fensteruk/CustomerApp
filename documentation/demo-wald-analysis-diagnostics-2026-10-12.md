# Demo Wald analysis diagnostics — 12 October 2026

Status: locally qualified; user approved the diagnostic-only demo release.
Deployment and live error capture still require verification. Demo target only:
`customerapp-jr72urxf.on-forge.com`, Forge site `3424057`.
Baseline: `71c0854dae7d926a35835863e055a71a6ffdd200` on
`codex/customerapp-wald-demo03-2026-10-12`.

## Evidence and purpose

Wald access was restored separately by changing the demo-only
`WALD_IMPORT_AVAILABLE` value from `false` to `true` and rebuilding configuration
(Forge command `13297532`). Nick's active Office account, saved enabled application
setting, Imports menu and Analyse-only page were verified. Production was untouched.

The supplied master workbook subsequently hit the existing generic safe analysis
error. Its exact SHA-256 is
`70619a389649f382e780f8022e29286706d3dc81d9af746f8e7e771585bdcbaf`.
Local analysis on the baseline succeeded: 16,989 data rows, 5,584 plots and 4,870
recognised call rows. Web PHP configuration showed 512M memory and a 45-second
execution limit; the active release lacked its earlier release-local `.user.ini`.
Neither observation proves the failure cause. Forge diagnostic output was missing
or incomplete, including a missing provision output-file error. No successful
server reproduction or precise exception has yet been established.

## Narrow implementation

`WALD_DRY_RUN_DIAGNOSTICS=true` is a new, independent opt-in. It defaults off and
accepts only the exact case-insensitive word `true` (no padding or generic truthiness).
It neither grants Wald authority nor modifies analysis/Apply limits.

The dry-run controller records its existing caught `AnalysisProblem` or
`InvalidArgumentException` through `WaldDryRunDiagnostics`. The existing browser
error/redirect remains unchanged. Only allowlisted error codes, limit names,
integer maximum, random diagnostic UUID, elapsed milliseconds, peak allocation,
PHP version/SAPI and configured PHP memory/execution limits are logged.
Known canonical payload-limit errors are distinguished. Unknown codes/messages
are replaced with generic classifications.

No exception object, trace, raw message, workbook name/path/contents, source rows,
customer identifiers, account details, credentials or request/session data is logged.
There are no schema, dependency, business-rule or customer UI changes. Grouped
RedZebra normal import/Apply remains refused.

## Verification

- Focused HTTP, privacy, disabled-default, gate-denial and grouped-layout regression:
  23 tests passed, 238 assertions.
- Full suite: 2,051 passed, 92 skipped, 11,934 assertions (431,962 ms).
- Pint and Composer strict validation passed.
- Build passed after the sandbox initially blocked Vite process creation.
- npm production audit: zero vulnerabilities.
- Composer audit: five existing advisories across four packages; no upgrades made.
- Final tracked diff and new-file whitespace checks passed.

## Approved demo release procedure

The user approved release in this chat. Commit/push only this narrow candidate
to the intended demo line and deploy only Forge site `3424057`. Enable
`WALD_DRY_RUN_DIAGNOSTICS=true` on that demo, refresh configuration, and retry the
same workbook through Analyse-only as Nick. Inspect the private log entry
`Wald Analyse-only stopped safely` for its actual code/limit/measurements.
Keep `APP_DEBUG=false`, authentication, current analysis safeguards and grouped
Apply denial. Do not touch production. Disable the diagnostic flag after capture.
