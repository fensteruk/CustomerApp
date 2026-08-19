# Sprint 2A Company Test Readiness QA Report

**Audit date:** 19 August 2026  
**Result:** Pass  
**Company-test readiness:** Yes  
**Overall health:** Amber

## Decision

The Customer Portal is safe for a controlled local company test. Revised journeys are understandable, server-authorised and verified across all four portal roles. This is not a production-release approval.

| Priority | Open count | Meaning |
| --- | ---: | --- |
| P0 | 0 | None |
| P1 | 2 | Production-only: npm advisory risk and missing MySQL/operational evidence |
| P2 | 3 | Production/deferred: physical accessibility evidence, amendments/progress, live notification-failure interception evidence |
| P3 | 0 | None open |

## Regression baseline

| Check | Result |
| --- | --- |
| \`php artisan migrate:fresh --seed\` | Passed |
| \`scripts/verify-sqlite-migrations.ps1\` | Passed: disposable migrate, full rollback and reapply |
| \`php artisan test\` | Passed: 118 tests, 632 assertions |
| \`vendor\\bin\\pint --test\` | Passed |
| \`npm run build\` | Passed after normal sandbox child-process permission |
| \`composer validate\` | Passed |
| \`composer audit\` | Passed: no advisories |
| \`npm audit\` | Failed: 11 advisories, 9 moderate and 2 high |
| \`git diff --check\` | Passed |

Composer emitted only a non-fatal read-only cache warning.

## Audit-item status

| Item | Status | Evidence |
| --- | --- | --- |
| AUD-001 | Resolved | Fresh seed and disposable SQLite migrate/reset/reapply passed. MySQL is not claimed. |
| AUD-002 | Resolved | Panel bounds: 320px = 8–297px; 390px = 8–367px. No document overflow. |
| AUD-003 | Open, production-only | npm reports 11 build-toolchain findings including 2 high; no automatic compatible remediation. |
| AUD-005 | Resolved | Active-site dashboard is server-authorised and paginated at 15; test data exercises 16 records. |
| AUD-006 | Resolved | Confirmed DEC-034 flow creates a new Submitted request with preserved source lineage. |
| AUD-007 | Resolved; evidence limit | Error, Retry and centre fallback are rendered and feature-tested. Browser request interception was unavailable, so a live failed fetch was not forced without disrupting Herd. |
| AUD-008 | Resolved | Plot/service/status filters combine server-side and persist through paginator links. |
| AUD-009 | Resolved | Current operational docs reflect final evidence; historic reports remain historic. |
| AUD-012 | Resolved | Lifecycle actions start disabled, enable only for eligible selection, and retain server validation. |
| AUD-013 | Resolved | \`/\` is intentional, registration is absent, and welcome template is removed. |

## HTTPS certificate status

**Resolved.** This was Herd certificate state, not Laravel routing, DNS or application configuration.

- \`customerapp.test\` resolves to \`127.0.0.1\` and is linked to CustomerApp.
- Herd previously listed it as HTTP-only with no hostname certificate.
- The scoped repair \`herd secure customerapp.test\` completed successfully.
- Herd now lists \`https://customerapp.test\` as secured through 19 August 2027.
- The certificate CN is \`customerapp.test\`; SANs include \`customerapp.test\` and \`*.customerapp.test\`; issuer is the existing Laravel Valet local CA.
- Browser HTTPS navigation succeeded without bypassing any certificate warning.

Windows command-line TLS clients in this environment still reported a local Schannel credential error. That is not a hostname mismatch or browser certificate warning. No private key material was inspected or exposed.

## Role and usability results

### Site Manager

- Assigned-site selection was clear and scoped.
- Completed single-plot Windows and multi-plot Cavity Closers submissions.
- Review pages clearly showed site, service, date, plots and customer message.
- Combined filters showed only active-site data.

### Assistant Site Manager

- Completed rejected-request resubmission: Rejected → Resubmit → edit → review → submit → dashboard.
- Read-only source context showed site, plot, service, prior date and rejection response.
- Private Office Staff reason was not displayed.
- Confirmation made clear that the original rejection remains in history and a new Submitted request is created.

### Finishing Foreman

- Used combined dashboard filters within Meadow View.
- Withdrew a submitted request and used immediate Undo successfully.
- Moved a rejected request to Trash, restored it, and received clear recovery/Undo feedback.

### Fenster Office Staff

- Entered the assigned-site review queue directly.
- Rejected a submitted request with customer-visible response.
- The processed item left the default Submitted queue.
- Site-role Office review access remains forbidden.

## Mobile, accessibility and notification results

Dashboard, New Call Off, Trash, notification centre, resubmission and Office review were checked at 320px, 390px, 430px, 768px and 1440px. No document-level horizontal overflow was detected. The notification panel fitted at both critical small widths.

Escape closed the open panel and returned focus to the bell; outside-click close passed. The no-JavaScript notification centre remains available. Preview accounts deliberately receive no notifications, so live unread-badge and long-content exercise needs an approved non-preview fixture. Physical-device, keyboard-only and screen-reader evidence remains production-only.

## Security results

No new security defect was found.

- Active-site and organisation isolation remain server-side.
- Office Staff review remains assigned-site scoped.
- Resubmission derives source and lineage server-side, binds confirmation to source/date/message/site/user, and rechecks eligibility before persistence.
- Feature coverage includes completed plots, conflicts, revoked assignment, cross-site and cross-organisation UUIDs, direct post and confirmation tampering.
- \`internal_reason\` was not displayed in site-role resubmission screens.
- Notification and lifecycle ownership protections remain covered by the full suite.
- Preview remains local/testing-only and outside the notification audience.

## QA repair made during audit

A small P3 usability defect was found and repaired. Once a rejected request had a new active replacement, its old card still displayed **Resubmit**, but opening it correctly returned \`403\`. The dashboard now shows Resubmit only when current eligibility permits it. During the repair, the dashboard plot projection was corrected to eager-load \`site_id\` and \`is_completed\`, which eligibility needs. A regression test covers the stale-link case.

Focused UI tests passed: 8 tests, 47 assertions. Full regression then passed: 118 tests, 632 assertions.

## Files changed during final QA

- \`app/Http/Controllers/SiteDashboardController.php\`
- \`resources/views/portal/site-dashboard.blade.php\`
- \`tests/Feature/Sprint2aUiTest.php\`
- \`current_sprint.md\`
- \`ROADMAP.md\`
- \`HANDOVER.md\`
- \`documentation/sprint-2a-company-test-readiness.md\`
- \`documentation/sprint-2a-company-test-readiness-qa-report-2026-08-19.md\`

The local Herd configuration was also safely updated by \`herd secure customerapp.test\`. No dependency change, SiteApp work, QR work, production deployment or schema change was made during final QA.

## Remaining company-test blockers

None. Testers should use \`https://customerapp.test\`.

## Remaining production-only blockers

- MySQL migration, lock, index, concurrency and restore evidence.
- Production secrets, HTTPS/cookies, session/cache, mail, storage, queue, monitoring, alerting, backup/restore and deployment-rehearsal evidence.
- The unresolved npm advisories, including two high build-toolchain findings, with named owner and time-bounded remediation/risk decision.
- Physical-device, keyboard-only and assistive-technology release evidence.
- Deferred amendments and later customer-facing progress.

## Recommended company-test script

### Site Manager / Assistant Site Manager / Finishing Foreman

1. Open \`https://customerapp.test\`, sign in and select an assigned site.
2. Find a call-off with plot, service and status filters; clear filters.
3. Submit one single-plot and one multi-plot call-off, reviewing each before submission.
4. Withdraw an eligible request and use Undo.
5. Move a rejected or withdrawn request to Trash, then restore it.
6. For a rejected request, use Resubmit, enter a new date/message and confirm the new Submitted request while checking the original remains visible.
7. Open the notification bell and centre; report any loading error rather than treating it as an empty list.

### Fenster Office Staff

1. Open \`https://customerapp.test\`, sign in and confirm the Review Requests queue.
2. Filter by site, service and status.
3. Publish one approval with customer response.
4. Reject another submitted request with customer-visible response and, if needed, a separate private reason.
5. Confirm processed requests leave Submitted and customer-facing screens never show private reasons.

