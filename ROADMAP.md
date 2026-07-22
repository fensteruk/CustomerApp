# Fenster Customer Portal ROADMAP

*Last Updated: 22 July 2026*

# Project Overview

The Customer Portal is a standalone customer-facing request and communication application. It integrates with SiteApp without duplicating SiteApp's internal operational workflow.

# Current Version

**Current Milestone:** `v0.1 – Application Foundation (In Progress)`

Status: Laravel project and SiteApp-matched dependency stack created. Base layout and branding remain intentionally unstarted.

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
- Customer organisation model
- Customer User, Customer Administrator and Internal Administrator roles
- Server-side authorisation
- Cross-customer access tests

## v0.3 — Customer Data Foundation

- Customer-visible developments and phases
- Outstanding plot projection
- Service types: Cavity Closers, Windows and CML
- External SiteApp identifiers
- Synchronisation state
- Search, filtering and pagination

## v0.4 — Customer Request Workflow

- Requested dates and customer notes
- One active request per plot and service
- Bulk requests where approved
- Request detail and history
- Validation and authorisation tests

## v0.5 — Internal Portal Approval

- Internal request queue
- Confirm requested date
- Propose revised date
- Reject request
- Customer-visible response
- Private internal reason
- User attribution, timestamps and transition tests

## v0.6 — Amendments and Revisions

- Amendment requests
- Reasons and notes
- Previous and proposed date tracking
- Immutable revision history
- Resubmission and amendment eligibility rules

## v0.7 — Notifications and Status Tracking

- In-app notification centre
- Submission, confirmation, revision, rejection and amendment notifications
- Customer-facing progress statuses
- Read/unread state
- Email where approved

## v0.8 — Dashboard and Usability

- Dashboard summaries
- Planned service dates
- Outstanding and recently confirmed requests
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
- Date requests
- Amendments
- Portal approval
- Notifications
- Status tracking
- SiteApp integration
- Responsive production deployment

# Future Versions

## v1.1 — Customer User Management
Customer invitations, organisation role management and offboarding.

## v1.2 — Calendar Experience
Calendar view, upcoming work and calendar export.

## v1.3 — Evidence and Attachments
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
- Bulk request rules
- Customer Administrator Version 1 permissions
- Revised-date acceptance
- Amendment cutoff statuses
- Email notification requirements
- Synchronisation freshness
- Customer-facing status mapping
- Retention requirements
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
| Current Version | v0.1.0 (in progress) |
| Next Version | v0.1.0 completion |
| Project Created | Yes |
| Foundation Documents | ✅ Prepared |
| Authentication | ⚪ Not started |
| Customer Data | ⚪ Not started |
| Requests | ⚪ Not started |
| Approval | ⚪ Not started |
| Amendments | ⚪ Not started |
| Notifications | ⚪ Not started |
| SiteApp Integration | ⚪ Not defined |
| Production Ready | ❌ No |
