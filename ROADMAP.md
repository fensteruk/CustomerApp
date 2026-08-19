# Fenster Customer Portal ROADMAP

*Last Updated: 19 August 2026*

# Project Overview

The Customer Portal is a standalone customer-facing request and communication application. It integrates with SiteApp without duplicating SiteApp's internal operational workflow.

# Current Version

**Current Milestone:** `Sprint 2A - Company Test Readiness (QA passed; controlled company testing may begin)`

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

Sprint 1G locally verifiable hardening was reported complete, but the 19 August full-site
audit is the current evidence baseline: it reproduced a SQLite rollback failure, found a
small-phone notification fly-out defect and two high-severity npm advisories. Sprint 2A is
a deliberately small remediation and usability slice for the next controlled company test.
It does not make the product production-ready. MySQL evidence and production infrastructure
controls remain release blockers.

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
