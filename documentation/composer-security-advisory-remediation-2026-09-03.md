# Composer Security Advisory Remediation — 3 September 2026

## Scope and Baseline

This is a dependency-only security correction from verified `main`
`0873bac79edf578e9f4a9417e3cafae34e8aa925`. A read-only `git ls-remote` confirmed that GitHub
main matched local main. Work is isolated on `security/composer-advisories-2026-09-03` in
`.cursor/worktrees/composer-security-2026-09-03` with its own vendor installation and local
SQLite database. The existing root worktree edits, workbook and output folder are preserved.
The import/UI worktree is not modified. No Sprint 3F, Wald, import semantics, production,
Forge, merge, push or deployment is included.

The older roadmap/handover release status is historical, not evidence of today's deployed
state. Production was not inspected during this task. Current main does not include the
manual-source-import UI; its browser regression must occur on a later combined QA candidate.

## Advisories Found

Fresh `composer audit --locked --format=json` returned eight advisories, affecting three
packages. The table records Composer's current affected ranges (not guesses from the previous
report). CommonMark is transitive through Laravel; Filament and Livewire are direct root
requirements. No advisories were ignored or suppressed.

| Package / old version | Advisory / CVE | Upstream severity | Affected range | First fixed version on installed major | CustomerApp exposure |
|---|---|---|---|---|---|
| filament/filament 5.6.8 (direct) | [GHSA-r3j6-gpjw-qfjr](https://github.com/filamentphp/filament/security/advisories/GHSA-r3j6-gpjw-qfjr), PKSA-fwm5-nrzy-yd41, CVE-2026-84306 | Medium | >=5.0.0,<5.7.6 or >=4.0.0,<4.12.6 | 5.7.6 | LOW / NOT REACHABLE: app-MFA code replay path; no Filament panel or MFA configured. |
| filament/filament 5.6.8 (direct) | [GHSA-xwpv-pqxp-5v36](https://github.com/filamentphp/filament/security/advisories/GHSA-xwpv-pqxp-5v36), PKSA-r2v2-7j3d-th1m, CVE-2026-84307 | Low | >=5.0.0,<5.7.5 or >=4.0.0,<4.12.5 | 5.7.5 | LOW / NOT REACHABLE: panel-denied account/password disclosure requires Filament MFA login, which Portal does not use. |
| filament/filament 5.6.8 (direct) | [GHSA-52xp-w8hr-xv3c](https://github.com/filamentphp/filament/security/advisories/GHSA-52xp-w8hr-xv3c), PKSA-wst7-5d23-qy5j, CVE-2026-77567 | High | >=5.0.0,<5.7.0 or >=4.0.0,<4.12.0 | 5.7.0 | LOW / NOT REACHABLE: recovery-code/app-MFA bypass requires a configured panel/MFA feature. |
| league/commonmark 2.9.0 (transitive) | [GHSA-8rr7-cvq3-gmfh](https://github.com/thephpleague/commonmark/security/advisories/GHSA-8rr7-cvq3-gmfh), PKSA-zyf5-hrxv-hrd7; no CVE | High | >=1.5.0,<2.10.0 | 2.10.0 | LOW / NOT REACHABLE: distinct-attribute quadratic parsing requires AttributesExtension; Portal never registers it. |
| league/commonmark 2.9.0 (transitive) | [GHSA-jjv6-8j6v-6j52](https://github.com/thephpleague/commonmark/security/advisories/GHSA-jjv6-8j6v-6j52), PKSA-nv44-1b4d-6gjg; no CVE | High | >=1.5.0,<2.9.1 | 2.9.1 | LOW / NOT REACHABLE: optional SmartPunct/Attributes extensions are not registered by Portal. |
| league/commonmark 2.9.0 (transitive) | [GHSA-f8fg-pg57-v4j8](https://github.com/thephpleague/commonmark/security/advisories/GHSA-f8fg-pg57-v4j8), PKSA-kr3s-894t-g5w2; no CVE | High | >=2.7.0,<2.9.1 | 2.9.1 | LOW / NOT REACHABLE: form-feed event-attribute XSS requires AttributesExtension; customer text is escaped Blade/plain text. |
| league/commonmark 2.9.0 (transitive) | [GHSA-j8pm-gj4c-rq4x](https://github.com/thephpleague/commonmark/security/advisories/GHSA-j8pm-gj4c-rq4x), PKSA-9q1p-3s19-bp1q; no CVE | High | >=0.6.0,<2.9.1 | 2.9.1 | LOW / NOT REACHABLE from customer prose: core Markdown DoS; Portal has no arbitrary customer Markdown converter. Framework mail Markdown remains a dependency path, so it is patched and tested. |
| livewire/livewire 4.3.3 (direct and Filament dependency) | [GHSA-g3hc-697w-wm82](https://github.com/livewire/livewire/security/advisories/GHSA-g3hc-697w-wm82), PKSA-bgw4-5zmg-2njg, CVE-2026-81887 | Medium | >=4.0.0-beta.1,<=4.3.3 or >=3.0.0-beta.1,<=3.8.2 | 4.3.4 | MEDIUM (conservative): package auto-discovery remains active; no production Portal component/script mount was found, but public exploit details are limited and this is not treated as a waived risk. |

Some maintainer Filament advisory pages display affected-version endpoints inconsistent with
their patched-version sections and the current Composer/GitHub advisory database. This task
uses the conservative Composer ranges and the explicitly declared 5.7.6 fixed floor. The
discrepancy is not used to dismiss 5.6.8 findings.

Exposure evidence: source scan across `app`, `resources`, `routes`, `config` and `bootstrap`
found no Filament panel/provider, Livewire component/directive, Markdown conversion, or
optional CommonMark extension registration. `bootstrap/providers.php` contains only the
Portal AppServiceProvider. Authentication uses AuthenticatedSessionController/LoginRequest;
the Portal layout loads its own Vite Blade/Alpine bundle. A test-only Livewire probe is added
for compatibility; it is not registered as a customer feature. Absence of a known reachable
path reduces current exposure but does not replace patching.

## Dependency Tree and Minimum Plan

`composer why` established:

- Root `filament/filament: ^5.6`; its ten-package Filament family is coupled by `self.version`.
- Root `livewire/livewire: ^4.3`, additionally required by `filament/support: ^4.1`.
- `laravel/framework: 13.20.0` requires `league/commonmark: ^2.8.1`.

The selected fixed versions satisfy existing constraints and retain PHP 8.4/Laravel 13
compatibility. No `composer.json` change, major upgrade or Laravel update is needed.

Dry-run before mutation:

```text
composer update filament/filament:5.7.6 livewire/livewire:4.3.4 league/commonmark:2.10.0 -W -m --dry-run --no-scripts --no-install --no-interaction
```

It proposed exactly 12 updates, zero new/removal lock operations and zero known advisories.
`-W` is needed for the selected Filament family's exact-version dependencies; `-m` keeps other
locked dependencies unchanged. Actual update used the same selected package versions without
`--dry-run --no-install`, initially with `--no-scripts` so package hooks could be inspected and
run explicitly. Installing 161 packages into a new empty vendor directory is not 161 upgrades;
the lock comparison confirms only the 12 changes below.

| Package(s) | Before | After |
|---|---|---|
| filament/actions, filament/filament, filament/forms, filament/infolists, filament/notifications, filament/query-builder, filament/schemas, filament/support, filament/tables, filament/widgets | 5.6.8 | 5.7.6 |
| livewire/livewire | 4.3.3 | 4.3.4 |
| league/commonmark | 2.9.0 | 2.10.0 |

Laravel remains 13.20.0. Root constraints, development dependencies, npm manifests/lockfile and
all unrelated locked packages remain unchanged. No advisory-ignore or insecure Composer
policy override is introduced.

## Verification

Environment proof before migration: `APP_ENV=local`, SQLite database
`database/security-qa.sqlite` in this worktree only, blank DB URL, array mail, synchronous
local queue, file sessions and a unique local cookie. No existing environment file was
copied. Pest uses `APP_ENV=testing`, in-memory SQLite, array mail/cache/sessions and no DB URL
from `phpunit.xml`. No MySQL or production database was targeted.

| Gate | Result |
|---|---|
| Clean local `php artisan migrate:fresh --seed --force` | Passed: all 11 main-branch migrations through 000008; no migration changes |
| Full `php artisan test` | Passed: 242 total, 227 passed, 1,227 assertions, 15 existing MySQL-only skips, 53.326 seconds |
| Focused auth/Office/dashboard/plot/Sprint 3E/notifications/security suite | Passed: 107 tests, 531 assertions, 23.892 seconds |
| New security dependency regression suite | Passed: 5 tests, 14 assertions |
| `vendor/bin/pint --test` | Passed |
| `composer validate` | Passed |
| `composer audit --locked` and `composer audit` | Zero known security vulnerability advisories; no suppression |
| `composer check-platform-reqs` | Passed |
| `npm run build` | Passed, Vite 8.1.4; initial sandbox `spawn EPERM` resolved by approved execution outside sandbox |
| `git diff --check` | Passed |

The new tests verify patched version floors, framework mail Markdown rendering, rejection of
form-feed-prefixed event attributes with a separate valid-attribute control, escaped Livewire
state updates using a test-only component, and the absence of an unintended Filament login
panel. The first draft incorrectly expected a valid class to survive an entirely invalid
attribute block; the test was corrected to use a separate valid control. The XSS rejection
assertions remain intact. No application regression or compatibility-code change was needed.

Composer initially warned about the test probe's location during optimized autoload creation.
It was moved to its correct `Tests\\Support` PSR-4 path; the subsequent optimized autoload and
package discovery completed without that warning.

### Published Dependency Assets

The existing Composer `post-autoload-dump` hook runs `filament:upgrade`. It refreshed 16
already tracked Filament CSS/JS files under `public/css/filament` and `public/js/filament`.
All 16 were SHA-256 compared to the corresponding installed 5.7.6 vendor distribution files
and matched byte-for-byte. These are unedited generated dependency assets, not a Portal UI
redesign. Keeping the old published files would leave the checked-in asset set inconsistent
with the new lockfile. No fonts changed. `post-update-cmd` found no extra Laravel assets to
publish. No application controllers, domain rules, routes, models, views or npm files changed.

### Local HTTP and Browser Evidence

Normal CSRF/session-backed login succeeded for two fictional local accounts: Office Staff
(no customer organisation) and an assigned Site Manager. Requests reached the isolated
loopback server on port 8093. No authentication bypass or production account was used.

Thirteen authenticated page checks returned HTTP 200 with expected content: Office queue,
requested-date actions, pending alternative, Date Agreed, Office notification centre, site
selection, customer plot dashboard, Plot Details, Site User alternative response, Date
Agreed detail, Completed detail, New Call Off, and Site User notification centre. The customer
alternative did not expose the Office-private fixture note; Completed did not expose stale
Accept/Reject controls. Both sessions logged out normally. Three referenced CSS/JS assets
also returned HTTP 200. No new local ERROR/CRITICAL/ALERT/EMERGENCY log entries were found.
These are HTTP/content checks, not proof of JavaScript execution or visual correctness.

Interactive browser/console regression could not be completed: two browser tab creations
timed out waiting for webview attachment, the browser inventory contained no tabs, and a
final local panel-open attempt did not attach. No browser console or responsive pass is
claimed. Dedicated QA must complete that check. The manual-source-import UI is absent from
main and therefore was not tested or transplanted; test it on the later combined candidate.

The local server was stopped and temporary fixture/HTTP scripts were removed after checks.
Only fictional data exists in this worktree's ignored SQLite file; other local databases and
all production systems remain untouched.

## Residual Risks and Release Recommendation

The eight known Composer advisories are remediated. This branch is ready for dedicated QA,
not deployment approval. Dedicated QA must finish interactive browser/console verification.
The separate import/UI branch must be regression-tested after dependency integration; it is
intentionally not merged into this main-based security correction. Existing MySQL and wider
operational release gates are not claimed by a local SQLite dependency pass.
