# Sprint 3C — Plot-Centric Overview Report

Date: 21 August 2026

## Delivered

- Replaced the legacy request-card dashboard with an active-site, plot-centric overview.
- Each plot presents Cavity Closers, Windows, Snagging and CML in the fixed customer-facing order.
- Added central presentation state and overall-status query services. Source completion always takes precedence over legacy call-off state.
- Added `Not Called Off`, `Called Off — Awaiting Date`, `Date Agreed` and `Completed` service states, plus the five agreed overall plot states.
- Fully completed plots are hidden by default and can be included with `Show Completed`.
- Added plot, service-activity and service-status filtering with retained pagination query strings.
- Added authorised UUID plot-detail routes with customer-safe projected product quantities and no source identifiers.
- Preserved the existing withdrawal, resubmission and Trash controls as a secondary existing-call-off panel. Its server-side resubmission eligibility guard remains in place.

## Accessibility and responsive checks

- Desktop uses a semantic table with labelled columns; mobile uses labelled plot cards.
- Touch controls use the existing large-action styling and all service states include text, not colour alone.
- Browser checks confirmed no document-level horizontal overflow at 320px, 390px, 430px, 768px and 1440px.
- Browser checks confirmed the exact four-service order, the source freshness indication, filter behaviour and mobile plot details.

## Verification

- `php artisan test` — passed: 161 tests, 784 assertions.
- `vendor/bin/pint --test`, `npm run build` and `git diff --check` passed.
- `php artisan migrate:fresh --seed` passed after confirming `APP_ENV=local`,
  `DB_CONNECTION=sqlite`, and the target as the local CustomerApp
  `database/database.sqlite` file. No MySQL, Forge or production database was touched.

## Correction follow-up — 21 August 2026

The earlier “Sprint 3C requires correction before QA” result identified a test-environment
gate, not an application defect: the required destructive local migration-and-seed
rehearsal had not been authorised. The current task explicitly authorised that isolated
local SQLite reset, and it passed.

The regression coverage remains the focused `Sprint3cPlotOverviewTest`: it directly
covers the four-service order, source-completion precedence and reversal, all five
overall states, fully-completed visibility, filters, product privacy, role/site isolation
and bounded query count. A separate automated regression is not appropriate for the
original blocker because it would need to destroy a developer database.

Final browser verification used the freshly seeded local portal. It confirmed clear
plot reference and overall status, labelled four-service cards at 320px, 390px, 430px
and 768px, the semantic desktop table at 1440px, no document-level horizontal overflow,
and 48px-or-larger visible action targets. Plot Details retained the prominent status,
fixed service order and customer-safe products presentation. Existing workflow
compatibility is covered by the full suite, including New Call Off, withdrawal, Trash,
Undo, rejected resubmission, notifications, Office review and site switching.

## Dedicated QA gate — 21 August 2026

Dedicated visual, security, accessibility and regression QA passed. QA corrected one
active-site UUID containment defect in Plot Details: an assigned-but-not-selected site
could previously open through a direct UUID and render under the wrong site context. The
route now returns 404 outside the selected site context. The final full suite passed with
162 tests and 792 assertions. See
`documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md`.

## Scope kept out

No new call-off flow, bulk selection, amendments, negotiation, attachments, calendar/PDF, QR, source transport or SiteApp integration was added.
