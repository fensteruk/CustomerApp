# Logout / browser-Back privacy hotfix — 20 September 2026

## Status and scope

**READY FOR RELEASE REVIEW.** Release candidate on `codex/fix-logout-cache-privacy`, based on freshly fetched
`origin/main` / production baseline `76cabfcb3a906476e6a47b63b797db1025590e10`.
Not merged, pushed or deployed. Production deployment `78135685` is the prior
acceptance baseline, not a deployment of this repair.

The user approved only the P1 logout/history privacy correction and local verification.
Wald, import semantics, source bindings, plots, assignments, role policies and the two
known P2 presentation findings are unchanged. Older roadmap/release statements do not
override the user-confirmed 20 September production baseline.

## Root cause and repair

Protected pages inherited `Cache-Control: no-cache, private`, which did not prohibit
storage. In the local reproduction, Office customer HTML reappeared after logout and
Back without another protected request reaching the server. A test-only render marker
was unchanged and `pageshow.persisted` was false: this observed reproduction was cached
document/history restoration, not a demonstrated bfcache restoration.

Logout already calls `Auth::guard('web')->logout()`, invalidates the session and
regenerates the CSRF token. Reloading the previously restored page redirected to login.
No server authentication bypass was found; authentication code is unchanged.

`PreventAuthenticatedResponseCaching` is appended once to Laravel's `web` middleware
group. It records authenticated state before downstream handling and checks again
afterwards, covering authenticated content, successful login and logout responses.
It emits exactly `Cache-Control: no-store, private`.

The policy covers both Office route groups, customer routes, protected JSON and
POST-rendered confirmation HTML. Laravel's built-in `SetCacheHeaders` was inspected
but rejected for this task because it skips non-GET/HEAD requests. The actual
Livewire update pipeline also includes the middleware; no production Livewire
component was found. No JavaScript, route policy, controller or session rewrite is needed.

Guest login remains `Cache-Control: no-cache, private`. `/up` is outside the policy and
retains the same default header. Versioned files under `public/build` bypass application
routes. The local PHP static server returned no Cache-Control/Pragma/Expires for the
versioned JavaScript response; the repair adds none. This is not a claim about production
Nginx asset-cache duration. No Pragma or Expires header is introduced on any surface.

## Browser evidence

Tested in the same Codex in-app browser used for the production audit, against an
isolated loopback-only PHP server at `127.0.0.1:8765`. A new disposable SQLite database
held fictional normal Office and Site Manager accounts (not role-preview or impersonation).
No production credentials, environment file or data were used.

- Baseline Office customers → logout → Back: protected customer HTML visible; a fresh
  reload required login. This reproduced the production finding locally.
- Repaired Office customers → customer → site → Source Binding → logout: three Back
  and two Forward traversals showed login only. No protected content was observed.
- Repaired Site Manager login → assigned-site selection → dashboard → plot → logout:
  three Back and two Forward traversals showed login only. A fresh plot request also
  redirected to login. No protected content was observed.
- A Site Manager login that inherited a stale Office intended URL correctly received
  403; opening its own dashboard worked. No role permission was inverted.
- Server observations confirmed `no-store, private` on Office customer/site HTML,
  customer site/plot HTML, authenticated redirects and logout. Public login remained
  usable, and versioned JS returned 200 outside the policy.

Only the temporary harness emitted observational render IDs/pageshow logs; it did not
alter history, hide content, redirect, change cache headers or provide a safety safeguard.
None of that instrumentation belongs to the candidate.

Chrome can admit no-store documents into bfcache under restricted circumstances and
evicts them when cookies change. The existing logout rotates the session. This is why
local browser evidence and a post-deployment repeat are required, rather than claiming
headers prove every browser implementation:
[Chrome's no-store/bfcache guidance](https://developer.chrome.com/docs/web-platform/bfcache-ccns).
No separate Chrome/Firefox/Safari matrix, offline history test or production verification
of this unreleased repair is claimed. Previously stored pre-hotfix pages are not
retroactively purged by new response headers; release smoke must load fresh candidate pages.

## Automated verification

All tests used local/in-memory SQLite; no production connection was used. The isolated
candidate copy is `C:\Users\madas\AppData\Local\Temp\customerapp-logout-browser-99fff20c`.
The per-process PHP configuration is
`C:\Users\madas\AppData\Local\Temp\customerapp-final-audit-php\php.ini`.
Neither changes system PHP configuration.

- New focused suite: **7 passed / 37 assertions**.
- Failing-before evidence: the original five focused cases produced four failures for
  missing no-store and one pass, before the middleware was installed.
- Grouped privacy/auth/Office/customer regression: **103 passed / 530 assertions**.
- Full suite: **1,586 passed / 81 skipped / 8,274 assertions / 1 known error**
  (1,668 total, 160.936 seconds). The unchanged `Sprint3dMultiSubmissionTest` case
  `one multi service batch creates independently dated request items atomically`
  reports: `Each requested date must be a weekday within six months.` This is the
  same baseline error recorded before the hotfix, not a clean full-suite claim.
- Full repository Pint: passed. Final Git whitespace/link checks: passed.
- PHP syntax for all three changed PHP files: passed.
- Composer strict validation: valid. Composer audit: no advisories.
- Vite 8.1.4 production build: passed.
- `npm audit --omit=dev`: zero vulnerabilities.
- Full `npm audit`: unchanged four development-chain advisories (two high, two moderate),
  in browserslist, nanoid, baseline-browser-mapping and postcss. No upgrade was applied.

Commands (run from the candidate copy unless stated otherwise):

```powershell
$env:PHPRC='C:\Users\madas\AppData\Local\Temp\customerapp-final-audit-php\php.ini'
php vendor/pestphp/pest/bin/pest tests/Feature/ProtectedResponseCachePolicyTest.php --compact
php vendor/pestphp/pest/bin/pest tests/Feature/ProtectedResponseCachePolicyTest.php tests/Feature/SecureAccessFoundationTest.php tests/Feature/OfficeAdministrationPresentationTest.php tests/Feature/OfficeUserAdministrationTest.php tests/Feature/CustomerSidebarWorkspaceTest.php tests/Feature/Sprint3cPlotOverviewTest.php tests/Feature/WaldPilot01/WaldPilotSettingTest.php --compact
php vendor/pestphp/pest/bin/pest --compact
php -l bootstrap/app.php
php -l app/Http/Middleware/PreventAuthenticatedResponseCaching.php
php -l tests/Feature/ProtectedResponseCachePolicyTest.php
php vendor/bin/pint --test
php 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' validate --strict
php 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' audit --locked
& 'C:\nvm4w\nodejs\npm.cmd' run build
& 'C:\nvm4w\nodejs\npm.cmd' audit --omit=dev
& 'C:\nvm4w\nodejs\npm.cmd' audit
git diff --check
```

Pint and Git final checks run in the repository. An earlier whole-directory Pint check
in the disposable copy flagged only its three uncommitted browser-harness helpers;
those helpers are not application/candidate files.

## Impact, release and remaining issues

No migration, production data, dependency/lockfile or environment-configuration change.
The sole application configuration edit is code-level middleware registration in
`bootstrap/app.php`; no Forge/environment setting is required. No push or deployment.

Candidate commit subject: `Fix authenticated page caching after logout`.
Parent: `76cabfcb3a906476e6a47b63b797db1025590e10`. The final completion message records
the commit SHA; the candidate commit contains this report, avoiding a self-referential SHA.

Changed files: `app/Http/Middleware/PreventAuthenticatedResponseCaching.php` (policy),
`bootstrap/app.php` (central registration), `tests/Feature/ProtectedResponseCachePolicyTest.php`
(seven regressions), this report, and the current-status sections of `current_sprint.md`,
`ROADMAP.md` and `HANDOVER.md`. No unrelated user changes were present or discarded.

After separate approval, release this exact reviewed candidate and repeat the Office
and Site Manager logout/Back/Forward test on freshly loaded production pages. Keep
the existing session/CSRF and authorisation boundaries unchanged. Do not perform P2
UX changes first or re-run production Wald commits for this repair.

Separate follow-ups: Overview/source-binding contradiction; customer product totals;
multi-hop Wald lineage; the known date-sensitive `Sprint3dMultiSubmissionTest` baseline;
development dependency advisories; backup retention and restore rehearsal.
