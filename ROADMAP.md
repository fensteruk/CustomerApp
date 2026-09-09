# Fenster Customer Portal ROADMAP

*Last Updated: 25 August 2026*

# Project Overview

The Customer Portal is a standalone customer-facing request and communication application. It integrates with SiteApp without duplicating SiteApp's internal operational workflow.

# Current Version

**Current Milestone:** `Sprint 3F date amendments — product decisions integrated, dedicated QA next`

3 September 2026: the current user-approved sequence calls the initial agreement
workflow Sprint 3E and Date Amendments After Date Agreed Sprint 3F. That naming
supersedes the original management-programme numbering retained below.
The feature worktree starts from local main `0873bac`, which includes Sprint 3E and
the Office-organisation correction. Older release paragraphs below are historical.

The amendment engine and final management decisions are implemented. DEC-039 confirms
the seven reason codes, required Other explanation and existing On Hold overall-status
treatment. No Sprint 3F product decision remains. The Office race fix passed the full
MySQL gate; its targeted regression remains green after product integration.
Dedicated Sprint 3F QA, separate Composer security reconciliation and final combined
release-candidate verification remain. This is not deployment or company-test approval.
No deployment or main merge/push occurred.
See `documentation/sprint-3f-date-amendments-2026-09-03.md`.

Release-management update: production serves `main`
`f801c91113bd13656c6cbffdd3d82c12d4a95846`, deployed successfully after the documented
MySQL migration repair. The four repaired historical migrations are production-critical.
Sprint 3E dedicated QA passed and its validated work has been transplanted onto a release branch
from current `main`. It remains excluded from `main` and production pending the mandatory
disposable MySQL release gate and explicit merge/deployment approval.

Status: Laravel project and SiteApp-matched dependency stack created. Sprint 1A secure
access and domain foundation has been implemented and verified. Sprint 1B call-off domain
foundation has been implemented and QA verified. Sprint 1C.1 authenticated assigned-site
selection and site dashboard UI has been implemented and verified. Sprint 1C.2 New Call
Off UI has been implemented and verified against the audited domain actions. Sprint 1C.3
Office Staff review, approval and rejection UI has been implemented and QA verified after
a confirmation-integrity correction. Sprint 1D withdrawal, Trash, restore and quick Undo
UI has been implemented and QA verified after Undo ownership and dashboard summary
corrections. Sprint 1E backend notifications have been implemented; notification centre
UI has been implemented and QA verified after recipient-authorisation, preview-user and
no-JavaScript form corrections.

Sprint 3C has delivered the source-aware, plot-centric dashboard and authorised plot
details using the Sprint 3A/3B projections. Dedicated QA passed after an active-site UUID
containment correction; the full Pest suite passes (162 tests, 792 assertions), and the
expressly authorised local SQLite `migrate:fresh --seed` rehearsal passed. Sprint 1G locally
verifiable hardening and Sprint 2A readiness evidence remain historical
baselines. Management's 20 August requirements supersede the old three-service,
single-service/single-date batch, assigned-site Office Staff and Approved/Rejected product
assumptions. The Portal is therefore not ready for company testing until the full
management-confirmed programme and final integrated QA gate have completed. MySQL evidence
and production infrastructure controls remain release blockers.

## Management Requirements Programme — 20 August 2026

The authoritative programme is documented in
`documentation/management-gap-analysis-2026-08-20.md`. It replaces the former sequence of
small incremental Version 1 feature milestones with a controlled target-domain migration:

1. Sprint 3A — Target domain and access migration contract.
2. Sprint 3B — Source projection and service eligibility foundation.
3. Sprint 3C — Plot-centric overview and plot details.
4. Sprint 3D — Lead-time and eligibility engine.
5. Sprint 3E — Multi-plot/multi-service New Call Off.
6. Sprint 3F — Date negotiation and Office review.
7. Sprint 3G — Amendments and source completion reconciliation.
8. Sprint 3H — Structured attachments.
9. Sprint 3I — Notifications and reminders.
10. Sprint 3J — Calendar and PDF schedules.
11. Sprint 3K — Integrated QA and company-test preparation.

Sprint 3A has produced the additive schema/access migration spike and preservation tests.
SQLite QA passed on 20 August 2026 after a non-empty legacy migration rehearsal and two
narrow data-integrity corrections. Sprint 3B SQLite QA passed on 21 August 2026 after a
non-empty source-contract rehearsal and import-integrity corrections. MySQL rehearsal
remains mandatory before production or live source integration.

Sprint 3C implementation adds the four-service plot overview, central source-completion
precedence, completed-plot visibility control, filters, responsive card/table layouts and
authorised UUID plot detail pages. It deliberately excludes new call-off, negotiation,
attachment, calendar/PDF and source-transport work. See
`documentation/sprint-3c-plot-overview-report.md`.
Dedicated QA evidence is recorded in
`documentation/sprint-3c-plot-overview-qa-report-2026-08-21.md`.

Sprint 3D has replaced new-submission creation with a shared active-site UUID selection,
matrix, signed-review and atomic-final-submit backend flow. The dashboard selected-plots
entry and New Call Off entry use the same contract. Details for UI consumption, error
states, early-date exceptions, replay protection and notification limitations are in
`documentation/sprint-3d-bulk-call-off-report.md`. A later Sprint 3E implementation exists
only on the excluded side branch described above; it is not current approved work.

## Sprint 2A — Company Test Readiness

Planned scope:

- migration rollback integrity (AUD-001);
- mobile notification fly-out (AUD-002);
- high-severity npm advisory triage (AUD-003);
- notification fetch failure state (AUD-007);
- active-site dashboard and Trash pagination, plot search and bounded dashboard filters
  (AUD-005/AUD-008);
- lifecycle-button selection behaviour (AUD-012);
- stale welcome-template cleanup (AUD-013);
- documentation refresh against the 19 August audit (AUD-009);
- confirmed traceable rejected-call-off resubmission backend integration (DEC-034).

Backend result — 19 August 2026:

- AUD-001 SQLite rollback/reapply correction and disposable verification script completed.
- AUD-005/AUD-008 active-site dashboard and Trash pagination backend completed. Dashboard
  supports plot, service and status filters; date, development and phase remain deferred.
- DEC-034 confirmed and secure rejected-request resubmission review/confirmation/backend
  flow completed.
- UI remediation is complete: responsive notification error handling, dashboard/Trash
  browsing affordances, lifecycle selection states, rejected-request resubmission screens
  and welcome-template cleanup now consume the existing backend contracts.
- Dedicated Sprint 2A QA passed on 19 August 2026. The npm advisory remains a
  production-release risk; it is not a controlled local company-test blocker because the
  affected chain is build tooling and no untrusted CSS/source-map input is processed.

Excluded from Sprint 2A: MySQL and production infrastructure work, physical-device and
screen-reader release evidence, amendments, later progress statuses, SiteApp integration,
advanced reporting, email and push notifications.

# Version Roadmap

The historical version sequence below is retained for traceability. It must not be used as
an implementation contract where it conflicts with the Management Requirements Programme
above; in particular, Calendar, attachments and the four-service/date-agreement domain are
now Version 1 work, not later-version scope.

## v0.1 — Application Foundation

- Create the Laravel application in Herd
- Confirm and lock package versions
- Configure SQLite locally
- Establish repository structure
- Add base layout and branding foundation
- Configure tests and frontend build
- Confirm independent operation from SiteApp

**Exit:** application boots, tests run, assets build, and no SiteApp code has been copied.

## v0.2 — Authentication and Access Boundaries

- Secure login and password reset
- Disable public registration
- Active-account enforcement
- Customer organisation and portal site-assignment model
- Sites
- Four distinct portal roles: Site Manager, Assistant Site Manager, Finishing Foreman and Fenster Office Staff
- Active assigned-site selection for site roles
- Assigned-site review scope for Fenster Office Staff
- Development-only role-preview routing, excluded from production
- Server-side authorisation
- Cross-customer and cross-site access tests

**Sprint 1A Exit:** authenticated users are active, assigned to customer organisations,
hold portal-specific roles, and can access only authorised site contexts. Development
preview must be unavailable in production and must not bypass authentication or
authorisation.

## v0.3 — Customer Data Foundation

- Customer-visible developments and phases
- Outstanding projected plots
- Service types: Cavity Closers, Windows and CML
- External SiteApp identifiers
- Synchronisation state
- Search, filtering and pagination

## v0.4 — Call-Off Workflow

- Call-off dates and explicit customer-visible response fields
- Call-off batch model where one submission action has exactly one site, one service type,
  one requested date and one submitting user
- Individual request model where each request applies to one projected plot for the batch
  service type
- Independent request decisions after submission
- One active request per plot and service
- Centralised call-off eligibility action
- Computed active conflict key for duplicate prevention
- Bulk requests where approved
- Request detail and history
- Submitted call-off lifecycle and validation
- Pending request withdrawal
- Rejected request Trash and traceable resubmission
- Five-second quick Undo for eligible actions
- Seven-day customer-facing Trash recovery and expiry handling
- Validation, authorisation and cross-site access tests

## v0.5 — Fenster Office Approval

- Fenster Office Staff Review Requests dashboard
- Assigned-site review queue
- Approve a submitted call-off
- Reject a submitted call-off
- Customer-visible response
- Private internal reason
- User attribution, timestamps and transition tests

## v0.6 — Amendments and Revisions

- Amendment requests
- Explicit customer-visible responses and private internal reasons
- Previous and proposed date tracking
- Immutable revision history
- Resubmission and amendment eligibility rules

## v0.7 — Notifications and Status Tracking

- In-app notification centre
- Submission, approval and rejection notifications
- Customer-facing progress statuses
- Read/unread state
- Email where approved

## v0.8 — Dashboard and Usability

- Dashboard summaries
- Planned service dates
- Outstanding and recently approved requests
- Due-soon view
- Filters and plot search
- Mobile layouts
- Accessible empty, loading and error states

## v0.9 — SiteApp Integration

- Approve the integration contract
- Define API, event or synchronisation approach
- Secure service authentication
- Synchronise authorised customer, development and plot data
- Receive completion and customer-visible progress updates
- Submit or export requests through the approved boundary
- Idempotency, retries, stale-data indicators and integration tests

## v0.10 — Production Readiness

- Security and privacy review
- Performance review
- MySQL validation
- Production queue, cache and mail validation
- Logging and failure monitoring
- Backup and recovery plan
- CI/CD and deployment documentation
- User acceptance testing

## v1.0 — Production Release

- Authentication
- Customer dashboard
- Outstanding plots
- Call-off requests
- Amendments
- Fenster Office approval
- Notifications
- Status tracking
- SiteApp integration
- Responsive production deployment

# Future Versions

## v1.1 – Portal User Management
Customer invitations, portal role management, site assignments and offboarding.

## v1.2 – Calendar Experience
Calendar view, upcoming work and calendar export.

## Future – QR Site Context
QR codes may identify a site and assist a signed-in, authorised site user with selecting
that site. They must never authenticate a user, grant site access or bypass authorisation.

## v1.3 – Evidence and Attachments
Secure customer documents and optional delay evidence.

## v1.4 — Enhanced Notifications
SMS, push notifications, preferences and digests.

## v1.5 — Reporting
Request reports, date-change summaries and exports.

## v2.0 — Enterprise Authentication
MFA, SSO and Microsoft Entra ID.

# Explicit Non-Roadmap Items

Do not add SiteApp workflow, trade sequencing, trade sign-off, Black Hat approval, readiness, build verification, SiteApp queries, SiteApp administration, manufacturing scheduling, labour planning or operational resource planning unless the brief is explicitly changed.

# Outstanding Decisions

- Customer-facing meaning of CML
- Bulk request creation limits and validation feedback
- Amendment lifecycle eligibility beyond Version 1 withdrawal, rejected-request resubmission, Trash and Undo
- Email notification requirements
- Synchronisation freshness
- Customer-facing status mapping
- Long-term retention requirements outside the seven-day customer-facing Trash window
- Initial SiteApp integration method

# Release Checklist

Each release should include:

- tests passing;
- formatting checks passing;
- frontend build passing;
- migrations reviewed;
- customer isolation tested;
- documentation updated;
- no SiteApp internal functionality introduced;
- manual deployment actions recorded.

# Project Health

| Item | Status |
|---|---|
| Current Version | Sprint 2A - Company Test Readiness (planned) |
| Next Version | Controlled company-test readiness; production release remains blocked |
| Project Created | Yes |
| Foundation Documents | ✅ Prepared |
| Authentication | Implemented for Sprint 1A |
| Customer Data | Organisation and site foundation implemented |
| Requests | Sprint 1B backend/domain foundation implemented; Sprint 1C.2 submission UI and Sprint 1D withdrawal/Trash/Undo UI implemented |
| Approval | Sprint 1B backend/domain actions implemented; Sprint 1C.3 Office Staff decision UI implemented |
| Notifications | Sprint 1E backend and notification centre UI implemented and QA verified |
| Amendments | ⚪ Not started; remains out of Sprint 1G scope |
| SiteApp Integration | ⚪ Not defined |
| Production Hardening | Locally verified; production evidence outstanding |
| Production Ready | ❌ No |
