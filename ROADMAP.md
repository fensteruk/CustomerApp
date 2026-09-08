# Fenster Customer Portal ROADMAP

*Last Updated: 8 September 2026*

Current Wald update: management accepted corrected WALD03 snapshot
`a80ce7d14206cf3f3a9343448d406f01ae927b88` on `qa/customer-wald03-2026-09-08`.
It is the immutable WALD04 input, not original candidate `574f196`. Reader `wald-0.2.1`,
dictionary `customerapp.source-dictionary.v1` and fingerprint
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` remain fixed.
WALD04 is scoped only: ownership/permissions/retention decisions and explicit implementation
approval are pending. No import integration, push or deployment.
Evidence: `documentation/wald/customer-wald03-acceptance-wald04-scope-2026-09-08.md`.

# Project Overview

The Customer Portal is a standalone customer-facing request and communication application.
Initial spreadsheet ingestion will use its own Wald instance without requiring SiteApp.
Any later read-only SiteApp integration must not duplicate its operational workflow.

# Current Planning Milestone

**CUSTOMER-WALD03 accepted; CUSTOMER-WALD04 scoped, governance approval required.**
The current authority is the rebuilt `brief.md`, DEC-039–045,
`documentation/siteapp-import-data-dictionary.md` and
`documentation/work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md`.
The contradiction register preserves stale/historical statements without treating them as
current work.

The approved checksum manifest unblocked CUSTOMER-WALD02. Its generic reader, profiler,
reasoning core and safe corpus passed dedicated QA after five reader corrections; corrected
commit `4aa5ffb` is the accepted output. No Portal semantics, persistence, workflow integration
or deployment was added in WALD02. Explicitly approved WALD03 now adds only pure dictionary
and adapter infrastructure. All later packages require their own scoped instructions.

The 4 September reconciliation confirms PC1/CC1/CM1/CM2/CML, rejects literal `CC!` as a
silent alias, makes `complete = Yes` authoritative for its source call-off part, confirms
Windows/Doors roll-ups and exact BF handling, ignores Items Ordered Status/Site Value,
qualifies Plot To Be Installed, sets future source Site ID as durable identity and makes
every export partial/filtered by default. No semantic business question from that set
remains. Source revision/ownership, permissions, retention and commit atomicity remain gates
for the later packages that need them. The SiteApp copy baseline for WALD02 is now fixed by
the approved manifest digest
`76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`.

A committed non-main CustomerApp line ending at `feature/manual-source-import-ui`
(`1e8c22b`) is recognised as architecture evidence. WALD02/03 recorded the relevant generic,
dictionary, supersession and corpus classifications; WALD04/05 must continue the downstream
profile/integration comparison. It must not be merged wholesale or treated as production.

| Order | Package | Exit |
| --- | --- | --- |
| 1 | CUSTOMER-WALD02 — Portable core + corpus | Accepted corrected baseline `4aa5ffb`; no domain writes or deployment. |
| 2 | CUSTOMER-WALD03 — Business dictionary + semantic adapter | Accepted corrected snapshot `a80ce7d`; 470 focused passes; W3Q-01–03 preserved. |
| 3 | CUSTOMER-WALD04 — Knowledge profiles and clarification governance | Scope only in `WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md`; G01–G09 proposals need approval; no runtime. |
| 4 | CUSTOMER-WALD05 — Import/review integration | Private upload, durable queue, resumable questions, neutral review/preview and controlled commit with preservation/parity tests. |
| 5 | CUSTOMER-WALD06 — Pilot/hardening | Supervised awkward-workbook pilot, held-out corpus, MySQL/concurrency/security/device/worker/storage/backup evidence and separately approved cutover. |

First usable milestone is the integrated 05 exit, not just a working profiler. It must
work with no SiteApp API/database/filesystem/queue access. Import permissions, source
revision/ownership, reviewed commit unit and retention are explicit gates in the work
package; the filtered-export scope and quantity/completion meanings are now confirmed.
Keep the existing projection importer. A non-main workbook pipeline is a parity/supersession
candidate, but nothing is approved for retirement. Track generic backport candidates in
`documentation/wald-divergence-register.md`.

Release-history clarification: Sprint 3E is explicitly evidenced as successfully released at
`9111d76ff05d702d68afd884ee8e42bc8e50c8e3`, Forge deployment `76326195`. Current
`origin/main` is `0873bac79edf578e9f4a9417e3cafae34e8aa925`, but this reset found no
repository deployment-success record for that later SHA. Verify Forge before claiming it is
deployed. The 25 August blocked-release account below is historical. Sprint 3F remains
feature-branch only at `60aa9e2` and is outside this documentation/Wald milestone.

# Historical Release Snapshot — 25 August 2026

**Milestone at that time:** `Sprint 3E release candidate (MySQL gate blocked)`

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

# Historical Project Health Snapshot

The following older table is retained for traceability, not current release or Wald
implementation evidence. Use the current planning milestone above for this workstream.

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
