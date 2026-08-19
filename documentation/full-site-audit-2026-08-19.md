# Full Site Audit — 19 August 2026

## Executive conclusion

**Overall health: Amber.** The local Customer Portal is a credible, secure-enough
Sprint 1 test candidate for the exercised happy paths, with sound server-side site
and role checks. It is **not ready for production release** and should not be
described as release-ready until the P1 items below have been resolved or formally
accepted with compensating controls.

No P0 finding was identified. The audit found six P1 items, five P2 items and two
P3 items. No production feature was changed during this audit.

| Priority | Count |
| --- | ---: |
| P0 | 0 |
| P1 | 6 |
| P2 | 5 |
| P3 | 2 |

The strongest areas are authentication, site/organisation scoping, call-off
validation, confirmation-bound persistence, duplicate prevention, history,
approval separation and notification ownership. The material risks are release
evidence, a proven migration rollback fault, mobile notification layout, scale,
dependency advisories and incomplete rejected-resubmission UX.

## Scope and method

This was an end-to-end audit of the current `main` branch at `888250a Week 2`.
The worktree was clean before the report was created. The required project,
domain, sprint, roadmap, handover, deployment and previous readiness documents
were reviewed before testing.

The audit covered the four portal roles, assigned-site selection, the site
dashboard, call-off submission and review, Office Staff decisions, withdrawal,
Trash/restore/Undo, notifications, responsive layouts, accessibility affordances,
server-side access controls, data integrity, source health, migrations and release
readiness. It did not make production code changes.

### Local baseline

| Item | Result |
| --- | --- |
| Branch / baseline | `main`, `888250a Week 2` |
| PHP / Composer | 8.4.23 / 2.10.1 |
| Node / npm | 26.5.0 / 11.17.0 |
| Laravel | 13.20.0 |
| Local database | SQLite; fresh seed completed |
| Production database evidence | Not available; no MySQL/MariaDB listener on port 3306 and no local client found |
| Local environment | `local`, debug enabled, synchronous queue, database sessions, log mailer; appropriate only as a local baseline |

### Commands run

| Command | Result |
| --- | --- |
| `php artisan migrate:fresh --seed` | Passed |
| `php artisan test` | Passed — 101 tests, 521 assertions |
| `vendor\\bin\\pint --test` | Passed |
| `npm run build` | Passed — Vite production build completed |
| `composer validate` | Passed; Composer reported only a non-fatal sandbox cache-directory warning |
| `composer audit` | Passed — no advisories |
| `npm audit` | Failed — 11 advisories: 9 moderate, 2 high |
| `git diff --check` | Passed — no whitespace errors |
| `php artisan migrate:status` | Passed — all six migrations are applied |

The first frontend-build attempt was blocked by the local sandbox's child-process
restriction; the same build passed when run with the required local process
permission. This is not an application build failure.

## Functional and role evidence

### Site Manager

The role-preview flow presented only the three assigned sites (Meadow View,
Oaklands and Willow Park) and required an active site before showing site-scoped
data. A Windows call-off for Meadow View plot 001 was submitted through the full
new-call-off, review and confirmation flow. The resulting dashboard card showed
the service, requested date, submitter, submission time, customer response state
and Submitted status.

### Assistant Site Manager

The role received the same authorised site list. After Office approval, the
request was visible as Approved and was not selectable for lifecycle actions.
The lifecycle form rejected an attempt to submit an action with no selected item
with a clear server-side message.

### Finishing Foreman

The role was able to submit a two-plot Cavity Closers batch at Oaklands. The
review screen accurately showed both plots before persistence, preserving the
per-plot request model. A submitted request was withdrawn through the
confirmation screen; the warning described withdrawal and the five-second Undo
window. It was then moved to Trash, restored, and presented the expected success
and Undo messaging. An attempted Undo after the automated interaction delay was
correctly rejected as expired; successful server-window Undo is covered by the
passing feature tests.

### Fenster Office Staff

The role was routed directly to Review Requests. It saw the submitted Meadow
View request, including history and customer-facing information. Approval with a
customer response and a separate private reason succeeded; the request left the
default submitted queue. A site role directly requesting the review route
received `403 Forbidden`. Existing feature tests also cover guest, unassigned
site, cross-organisation and revoked-assignment attempts.

### Notification behaviour

The notification bell, centre, read/dismiss actions and no-JavaScript forms are
implemented and their ownership, role changes, malformed UUIDs and safe link
resolution are covered by feature tests. Preview users deliberately do not create
or receive notifications, so the role-preview browser account cannot demonstrate
a populated notification panel. This is an intentional security boundary, not a
notification delivery failure.

## UX, responsive and accessibility review

The desktop dashboard has clear primary actions, status text in addition to
colour, customer-facing labels, focus styles, a skip link and explicit empty
states. The mobile new-call-off flow, Trash and notification centre had no
document-level horizontal overflow at 390 x 844, 430 x 932, 768 x 1024 or
1440 x 900 viewports.

However, the compact notification fly-out is visibly clipped off the **left** at
390 x 844. Its measured left edge was -68.75px while its width was 352px. The
heading was rendered as a clipped “ations”, making the panel look broken on a
common small-phone width. This is AUD-002.

The source includes a good keyboard design: opening the bell sends focus to the
close button, `Escape` calls `close()`, and close returns focus to the bell.
The in-app automation layer could not dispatch a native Escape event reliably,
so that exact physical-key interaction still needs manual keyboard confirmation.
The source behaviour and focus targets were reviewed; do not represent that as a
completed physical keyboard or assistive-technology test.

## Security, permissions and data integrity

### Positive evidence

- Public registration is absent; login, logout, reset, inactive-user rejection
  and rate-limit/framework paths are feature-tested.
- Protected routes use authentication plus active-user middleware. Preview login
  is guarded in both preview actions to `local` and `testing` environments.
- Site selection is server-side and verifies both organisation and assignment on
  every active-site request. Revoked or malformed context is cleared rather than
  trusted.
- Office Staff list and detail queries are constrained to assigned sites. Direct
  site-role access to Office review was verified as `403`; unassigned and
  cross-organisation paths are also feature-tested.
- New-call-off validation checks service values, date, plot UUIDs, active-site
  ownership, outstanding state and duplicate active conflicts both before review
  and immediately before persistence. The database contains a conflict-key unique
  guard and submission uses a transaction.
- Approval and rejection re-authorise in domain actions; customer response and
  private internal reason are separated. History has ordered immutable records
  and user/timestamp attribution.
- Lifecycle requests are confirmed with a session-bound HMAC tied to user, site,
  operation and selected UUIDs. Tests cover tampering, replay, mixed batches,
  stale decisions and cross-site Undo possession.
- Notification queries are recipient-scoped, active-role/scoped-site checked and
  do not disclose private decision reasons. Safe target-link resolution
  re-authorises before setting the active site or redirecting.
- No unescaped Blade output or raw SQL built from request parameters was found in
  the reviewed application paths.

### Limits

SQLite fresh migration and the automated suite are useful local evidence, not
evidence of MySQL lock, unique-index, collation, restore or concurrent-user
behaviour. Production HTTPS, secrets, mail, storage, session/cache, queue,
monitoring, alerting and backup/restore controls were not available to inspect.

## Findings register

### AUD-001 — SQLite migration rollback fails after dropping indexed user columns

| Field | Detail |
| --- | --- |
| Category / priority | BUG / P1 |
| Affected role / system | All roles; deployment and recovery |
| Description | The `down()` migration removes `is_active` and `is_preview_user` indexes and columns in one SQLite schema operation. A recorded rollback failed with `error in index users_is_active_index after drop column: no such column: is_active`. Fresh migration succeeds, but a release rollback/reapply path is broken on the supported local database. |
| Evidence | `storage/logs/laravel.log` records the failure at 2026-08-07 10:22:31 for `2026_08_05_000001_create_secure_access_domain_tables`; the current migration places index removal and the four column removals in the same `Schema::table` callback. |
| Recommendation | Reproduce in a disposable SQLite database, correct the rollback sequence or table-rebuild strategy, then add an automated migrate/rollback/reapply test. Run the equivalent approved MySQL rollback rehearsal before release. |
| Effort / dependencies | Small–medium; requires migration change and disposable database validation. |

### AUD-002 — Notification fly-out is clipped on a 390px mobile viewport

| Field | Detail |
| --- | --- |
| Category / priority | BUG / P1 |
| Affected role / system | All authenticated portal roles; mobile header notifications |
| Description | At 390px width, the fly-out anchored with `right-0` is positioned outside the viewport to the left. The Notifications heading and content are partially inaccessible. |
| Evidence | Browser test at 390 x 844: panel left -68.75px, width 352px; visual output clipped the heading to “ations”. `resources/views/layouts/portal.blade.php` uses a right-anchored fixed-width fly-out within the header. |
| Recommendation | Make the fly-out viewport-aware at small widths (for example, use a fixed/inset mobile presentation or an explicit responsive anchor), then add a 320px/390px visual browser regression. Verify on physical iOS and Android devices. |
| Effort / dependencies | Small; no external dependency. |

### AUD-003 — Frontend dependency audit has two high-severity advisories

| Field | Detail |
| --- | --- |
| Category / priority | SECURITY / P1 |
| Affected role / system | Build/toolchain and release process |
| Description | `npm audit` reports 11 vulnerabilities: 9 moderate and 2 high. The high entries are the direct PostCSS chain and nested `nanoid`; one advisory concerns attacker-controlled source maps and the other an infinite loop in a custom generator with size zero. The audit currently reports no automatic fix for the affected direct chain. |
| Evidence | Current `npm audit --json`: `postcss` and `nanoid` are high; `autoprefixer`, Tailwind, Vite and Laravel Vite plugin are affected through the same chain. Existing readiness/release documents state “10 moderate” and therefore understate the current result. |
| Recommendation | Triage against the actual build environment and attack surface, upgrade or replace the dependency chain when a compatible resolution is approved, and record a time-bounded risk acceptance only if no safe update exists. Do not ship a build pipeline that processes untrusted CSS/source-map input without compensating isolation. |
| Effort / dependencies | Medium; compatibility testing and approved dependency update required. |

### AUD-004 — Production and MySQL release evidence remains absent

| Field | Detail |
| --- | --- |
| Category / priority | PRODUCTION / P1 |
| Affected role / system | All roles; data durability, availability and recovery |
| Description | The portal has local SQLite-only evidence. No MySQL/MariaDB instance or listener is available, and no approved production environment evidence exists for HTTPS, storage permissions, secrets, sessions/cache, queue/failed jobs, mail, logging, monitoring, alerting, backups or restore. |
| Evidence | Local port 3306 check failed; project handover, deployment checklist and Sprint 1G report explicitly leave these items outstanding. The local `.env` is correctly local (`APP_DEBUG=true`, synchronous queue and log mailer) but cannot be promoted as production proof. |
| Recommendation | Treat these as release gates: provision an approved MySQL-compatible test environment; execute clean migrate, rollback/reapply, concurrent duplicate/decision/lifecycle tests, backup restore and least-privilege checks; then record production configuration, health/alert ownership and rollback evidence. |
| Effort / dependencies | Large; requires infrastructure, credentials and operational ownership. |

### AUD-005 — Dashboard and Trash retrieve unbounded request collections

| Field | Detail |
| --- | --- |
| Category / priority | PERFORMANCE / P1 |
| Affected role / system | Site roles; dashboard and Trash at high data volume |
| Description | The site dashboard loads every non-trashed call-off for the active site, and Trash loads every eligible trashed request. Both render full collections rather than paginate or apply a bounded query. This will increase memory, query time and DOM size as company testing and live usage add requests. |
| Evidence | `SiteDashboardController` and `CallOffLifecycleController::trash()` each end their request list query with `->get()`. Sprint 1G documentation also identifies dashboard/Trash bounds as a production-scale risk. |
| Recommendation | Define user-facing default scope and pagination/cursor behaviour, add indexes validated on MySQL, and run data-volume tests across a representative site before release. Preserve bulk-action traceability when paginating. |
| Effort / dependencies | Medium; requires product decision on list scope and production-like MySQL data. |

### AUD-006 — Rejected request can be recreated but not traceably re-submitted in the UI

| Field | Detail |
| --- | --- |
| Category / priority | FEATURE GAP / P1 |
| Affected role / system | Site Manager, Assistant Site Manager, Finishing Foreman; rejection lifecycle |
| Description | The domain action supports rejected-request resubmission with lineage, and its unit/feature coverage passes. There is no route, controller action, button or review flow exposing it. A user can submit a new call-off after rejection, but that UI path does not set `resubmitted_from_call_off_request_id`; the required traceable relationship is unavailable end-to-end. |
| Evidence | `ResubmitRejectedCallOffAction` exists only in the action and domain test search results. The route list provides create/confirm/store and lifecycle operations but no resubmit endpoint or view. |
| Recommendation | Add a deliberately confirmed site-role resubmission flow that displays the source decision/customer response, permits an amended date where rules allow, preserves lineage and cannot bypass eligibility/authorisation. Add UI and policy regression coverage. |
| Effort / dependencies | Medium; requires explicit product rule for whether rejected dates may change. |

### AUD-007 — Notification panel hides fetch failures as an empty state

| Field | Detail |
| --- | --- |
| Category / priority | UX / P2 |
| Affected role / system | All authenticated portal roles; notification panel |
| Description | If the notification index fetch fails, client code empties the list and clears loading without presenting an error or retry affordance. A genuine service failure is indistinguishable from “all caught up”. |
| Evidence | `resources/js/app.js` catches `load()` errors and sets `notifications = []`; there is no error state. The no-JavaScript notification centre remains a useful fallback, but the enhanced panel masks the failure. |
| Recommendation | Present a concise retryable error state and a link to the notification centre; add browser coverage for a failed notification request. |
| Effort / dependencies | Small; no external dependency. |

### AUD-008 — Site dashboard lacks the required browsing controls

| Field | Detail |
| --- | --- |
| Category / priority | FEATURE GAP / P2 |
| Affected role / system | Site roles; high-volume request review |
| Description | The dashboard shows all visible requests in one chronological stream with status counts, but has no plot search or filters for service, status or date. The project brief requires dashboard filtering by development, phase, plot, service, status and date, with simple plot search required. |
| Evidence | The rendered dashboard and `SiteDashboardController` expose no request parameters or filtering UI. Office Staff review has scoped filters and pagination, demonstrating the capability is not present in the site dashboard. |
| Recommendation | Confirm the currently supported data dimensions (development/phase may not yet exist), then implement scoped service/status/date filters and plot search together with AUD-005 pagination. |
| Effort / dependencies | Medium; requires product confirmation for development/phase data. |

### AUD-009 — Release documentation is materially stale

| Field | Detail |
| --- | --- |
| Category / priority | TECH DEBT / P2 |
| Affected role / system | Release governance and QA handover |
| Description | Sprint 1G, current sprint, handover and release notes still state that `npm audit` has 10 moderate advisories and some documents describe an uncommitted/unclean worktree. The current audit observed 11 advisories including two high findings, while `main` at `888250a` was clean before this report. |
| Evidence | Current audit commands versus `documentation/sprint-1g-production-hardening-report.md`, `current_sprint.md`, `HANDOVER.md` and `documentation/release-notes-v1.0.0-rc.1.md`. |
| Recommendation | Update release evidence after each verification run with command date, exact totals, baseline commit and ownership. Keep historic reports clearly marked as historic rather than presenting them as current state. |
| Effort / dependencies | Small; requires release-owner discipline. |

### AUD-010 — Physical-device, keyboard-only and assistive-technology release evidence is missing

| Field | Detail |
| --- | --- |
| Category / priority | PRODUCTION / P2 |
| Affected role / system | All users, especially keyboard and assistive-technology users |
| Description | Simulated responsive coverage and source-level accessibility affordances are encouraging, but no named physical iOS/Android, keyboard-only or screen-reader test has been completed. The mobile fly-out defect demonstrates why this must not be inferred from desktop or viewport checks. |
| Evidence | Deployment checklist, handover and Sprint 1G report explicitly list the evidence as outstanding. This audit could not complete a physical-device or native assistive-technology pass. |
| Recommendation | Run and record a named manual test matrix covering login, site selection, new call-off, confirmation, review decisions, notification bell/centre, Trash and Undo on physical devices and with keyboard plus a supported screen reader. |
| Effort / dependencies | Medium; requires devices and named reviewers. |

### AUD-011 — Amendments and later customer-facing progress are still deferred

| Field | Detail |
| --- | --- |
| Category / priority | FEATURE GAP / P2 |
| Affected role / system | Site roles and Office Staff; post-submission lifecycle |
| Description | The brief requires amendments/revision history and customer-facing progress. Current implementation protects approved requests from ordinary lifecycle change and supports initial Submitted/Approved/Rejected only, but does not provide the approved amendment/review lifecycle or an agreed mapping for later customer-facing progress. |
| Evidence | Roadmap/current-sprint and domain documentation identify these as unresolved or future work; the current route, action and view inventory contains no amendment flow or later progress-status mapping. |
| Recommendation | Keep this explicitly out of company-test acceptance unless the business rules are approved. Before implementation, define amendment eligibility, customer/office responsibilities, revision visibility, reopen policy and non-SiteApp progress mapping. |
| Effort / dependencies | Large; requires business decisions and integration contract. |

### AUD-012 — Lifecycle action buttons invite an avoidable empty submission

| Field | Detail |
| --- | --- |
| Category / priority | UX / P3 |
| Affected role / system | Site roles; dashboard lifecycle actions |
| Description | Withdraw and Move to Trash remain enabled when no eligible request is selected, including when only approved cards are shown. The server correctly rejects it, but users make a needless round trip and receive an error. |
| Evidence | Browser test as Assistant Site Manager: buttons remained enabled with an approved request, then returned “Select at least one call-off request.” `portal/site-dashboard.blade.php` renders active submit buttons without a client-side selected-state guard. |
| Recommendation | Disable lifecycle actions until an eligible selection exists while retaining the existing server-side validation. |
| Effort / dependencies | Small; no external dependency. |

### AUD-013 — Unused welcome template references an unavailable registration route

| Field | Detail |
| --- | --- |
| Category / priority | TECH DEBT / P3 |
| Affected role / system | Dormant root/welcome template |
| Description | Public registration is correctly absent, but the unused `welcome.blade.php` still contains `route('register')`. The active root route redirects to preview/login, so it is not currently user-reachable; restoring that template would produce a route error. |
| Evidence | `resources/views/welcome.blade.php` references the route; `routes/web.php` has no `register` route and does not render the template. |
| Recommendation | Remove or update the stale template during routine cleanup, preserving the intentional no-public-registration policy. |
| Effort / dependencies | Small; no external dependency. |

## Regression and test-coverage assessment

The 101-test suite is well targeted for the implemented scope: access foundation,
role preview restrictions, assigned-site context, tenant isolation, validation,
duplicate database guard, multi-plot traceability, decisions, private-reason
separation, history, lifecycle confirmation/tampering, Trash/Undo, notification
ownership/idempotency and no-JavaScript notification forms are all represented.

The notable missing regressions are:

- automated SQLite migration rollback/reapply (AUD-001);
- browser visual assertions at 320px and 390px, particularly the notification
  panel (AUD-002);
- browser failure-state coverage for notification retrieval (AUD-007);
- end-to-end UI coverage for traceable rejected resubmission once that route is
  introduced (AUD-006);
- realistic MySQL concurrency, index and data-volume performance tests
  (AUD-004/AUD-005);
- physical keyboard and assistive-technology evidence (AUD-010).

## What is safe to use now

Subject to the P1 mobile-fly-out fix, the local implementation is suitable for a
bounded company test focused on authorised site selection, submitted call-offs,
Office approval/rejection, customer-facing decision response, withdrawal,
Trash/restore and role/tenant isolation. The test charter should explicitly
exclude production claims, MySQL concurrency, amendments, later progress mapping
and traceable rejected resubmission until their blockers are resolved.

## Recommended action

Do not start unrelated feature work. Run a short **release-integrity and company
test readiness** slice in this order:

1. Fix and add regression coverage for AUD-001 and AUD-002.
2. Decide and expose the traceable rejected-resubmission journey (AUD-006), or
   formally exclude it from company-test acceptance.
3. Triage the current high-severity frontend audit against the actual build
   environment and update the stale release evidence (AUD-003/AUD-009).
4. Define bounded dashboard/Trash behaviour and validate it with representative
   MySQL volume and concurrency (AUD-004/AUD-005/AUD-008).
5. Complete the named production, backup/restore, physical-device, keyboard and
   assistive-technology evidence before any production release (AUD-004/AUD-010).

## Deferred items and assumptions

- No package update was attempted because the audit remit excluded upgrades.
- No migration rollback command was run during this audit because it would alter
  the freshly seeded local database; the recorded failure and migration source
  provide evidence for AUD-001.
- No SiteApp endpoint, operational workflow status or production infrastructure
  assumption was invented.
- Preview accounts are intentionally excluded from notification audiences. A
  populated live-notification browser demonstration requires an approved
  non-preview test account or a controlled fixture; this audit relied on passing
  server-side notification tests for that restricted path.
