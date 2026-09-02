# Fenster Customer Portal
## Project Brief — Version 1
**Status:** Management-clarified specification
**Last Updated:** 20 August 2026

The management requirements confirmed on 20 August 2026, recorded in
`context-work-prompt.md`, supersede earlier assumptions in this brief wherever explicitly
contradictory. Section 17 is the consolidated Version 1 clarification. It defines product
requirements rather than the current implementation state.

---

# 1. Executive Summary

The Fenster Customer Portal is a separate customer-facing application that integrates with SiteApp.

It allows authorised customers to:

- view outstanding plots;
- request dates for Cavity Closers, Windows, Snagging and CML;
- request amendments;
- agree requested dates and respond to proposed alternatives;
- monitor customer-visible progress.

SiteApp remains Fenster’s internal operational system. The Customer Portal is a communication and request portal only.

It must not reproduce SiteApp workflow, verification, trade management, operational planning or administration.

---

# 2. Product Principles

- **Separate product:** its own domain model, authentication, permissions, interface and releases.
- **SiteApp authoritative:** operational data originates in SiteApp.
- **Customer isolation:** users only see their own organisation and authorised developments.
- **Simplicity:** mobile friendly, accessible, clear and suitable for non-technical users.
- **Traceability:** customer-facing requests, amendments and decisions retain history.

---

# 3. Users

The Customer Portal has four distinct initial roles. These are portal-specific roles and
must not reuse SiteApp permissions, policies or workflow responsibilities.

## Site Manager
- Select an assigned site.
- Access the site dashboard for the selected assigned site.
- Submit call-offs for that site.
- See all call-offs for the active assigned site in Version 1.

## Assistant Site Manager
- Select an assigned site.
- Access the site dashboard for the selected assigned site.
- Submit call-offs for that site.
- See all call-offs for the active assigned site in Version 1.

## Finishing Foreman
- Select an assigned site.
- Access the site dashboard for the selected assigned site.
- Submit call-offs for that site.
- See all call-offs for the active assigned site in Version 1.

## Fenster Office Staff
- Access the Review Requests dashboard.
- Review and approve or reject call-offs for assigned sites only.
- Publish a customer-visible decision and retain any private reason separately.

The three site roles are separate roles even where their presently confirmed capabilities
are the same. Their future permission differences must be explicit, rather than inferred
from SiteApp or job title.

For Version 1, Site Manager, Assistant Site Manager and Finishing Foreman have identical
Customer Portal permissions. They remain separate roles so future differences can be
introduced deliberately.

### Permission Matrix

| Capability | Site Manager | Assistant Site Manager | Finishing Foreman | Fenster Office Staff |
|---|---:|---:|---:|---:|
| Sign in and use the portal | Yes | Yes | Yes | Yes |
| Select an assigned site | Yes | Yes | Yes | Not required for the initial review dashboard |
| Access selected site dashboard | Yes, assigned sites only | Yes, assigned sites only | Yes, assigned sites only | No |
| Submit a call-off | Yes, assigned sites only | Yes, assigned sites only | Yes, assigned sites only | No |
| View call-offs | Yes, all call-offs for active assigned site | Yes, all call-offs for active assigned site | Yes, all call-offs for active assigned site | Yes, assigned sites only |
| Withdraw a pending call-off | Yes, active assigned site only | Yes, active assigned site only | Yes, active assigned site only | No |
| Trash a rejected or withdrawn call-off | Yes, active assigned site only | Yes, active assigned site only | Yes, active assigned site only | No |
| Restore eligible Trash item | Yes, active assigned site only | Yes, active assigned site only | Yes, active assigned site only | No |
| Approve or reject a call-off | No | No | No | Yes, assigned sites only |
| Use development role preview | Development only; never a production entitlement | Development only; never a production entitlement | Development only; never a production entitlement | Development only; never a production entitlement |

---

# 4. Authentication

Version 1 requires secure login, username or email, password authentication, password reset, secure sessions, active-account enforcement, rate limiting and server-side authorisation.

Public registration is disabled unless explicitly approved.

MFA and SSO are future features.

Site users must select an assigned site from a menu before accessing site-scoped data or
submitting a call-off. A future QR code may identify a site and assist with selecting that
context, but it must never replace authentication or authorisation.

---

# 5. Dashboard and Outstanding Plots

After login, site users should see the site dashboard for their selected assigned site.
Fenster Office Staff should see the Review Requests dashboard.

In development only, a role-preview screen may select one of the four portal roles to
exercise dashboard routing. Selecting a site role leads to the site dashboard with the
assigned-site selector; selecting Fenster Office Staff leads to Review Requests. The
preview is not an authentication or authorisation mechanism and must not be enabled in
production.

### Dashboard Routing Matrix

| Authenticated portal role | Required context | Destination |
|---|---|---|
| Site Manager | Select an assigned site | Site dashboard for the active assigned site |
| Assistant Site Manager | Select an assigned site | Site dashboard for the active assigned site |
| Finishing Foreman | Select an assigned site | Site dashboard for the active assigned site |
| Fenster Office Staff | Assigned-site review scope | Review Requests dashboard |
| Development role preview | Development environment only; preview never grants access | Destination for the selected preview role, subject to normal authentication and authorisation |

Only projected plots not reported complete by SiteApp may be selected for new requests.
Historical requests for completed projected plots may remain read-only.

For the next company-test release, the site dashboard must keep the active assigned-site
scope mandatory and provide paginated call-offs, plot search, service and status filters.
A date filter may be included only if it remains simple and clear. Development and phase
filters remain deferred until those projected data dimensions are available. All filtering
and pagination must remain server-authorised; this is a browsing aid, not reporting.

---

# 6. Call-Off Requests

Each outstanding plot may have an independent call-off request for:

- Cavity Closers;
- Windows;
- CML.

A plot may have only one active request per service. Later changes must use amendments or revisions rather than duplicate active requests.

A batch records the site, service type, requested date, submitting user and timestamp.
Each request records the projected plot, current status and audit history. Customer-visible
responses and private internal reasons must use explicit fields and must not be stored as
generic notes.

### Call-Off Batch Model

One call-off batch represents one user submission action.

- One batch has exactly one site, one service type, one requested date and one submitting
  user.
- One batch may contain one or many individual call-off requests.
- Mixed service types and mixed requested dates are not permitted in a Version 1 batch.
- Each request applies to one projected plot for the batch service type.
- The batch groups submission, bulk withdrawal, quick Undo and Trash restoration.
- Each individual request owns its own status, approval, rejection, decision and history.
- Each request derives its site, service type, requested date and submitting user from the
  batch.
- Fenster Office Staff may decide individual requests independently after submission.
- Once requests in a batch have received different decisions, later batch-level operations
  must not overwrite those individual decisions.
- Batch-level operations must be authorised for every affected request.
- Bulk Undo and bulk restoration remain atomic: every eligible affected item changes, or
  none are changed.

A call-off is not approved until Fenster Office Staff publish a decision.

### Confirmed Call-Off Lifecycle

```text
Submitted → Approved
          → Rejected
          → Withdrawn
```

- A site role submits a call-off only for the active assigned site.
- Fenster Office Staff approve or reject the submitted call-off; no site role may perform
  either decision.
- Site Manager, Assistant Site Manager and Finishing Foreman users may see all call-offs
  for their active assigned site in Version 1.
- Fenster Office Staff may review, approve and reject submitted call-offs only for sites
  assigned to them in the Customer Portal.
- Every decision records the decision-maker, timestamp, prior values, customer-visible
  response and any private internal reason separately.
- Approval or rejection records a portal decision only. It must not calculate, create or
  expose SiteApp operational workflow, scheduling, labour, manufacturing or verification data.
- Pending submitted call-offs may be withdrawn by authorised site users for the active
  assigned site before an office decision is made.
- A withdrawn call-off no longer blocks a new active request for the same plot and service,
  but the withdrawn history must remain auditable.
- Approved call-offs cannot be deleted, trashed or cancelled by site users in Version 1.
- Approved call-offs remain active duplicate blockers until a future authorised lifecycle
  event marks them completed, superseded or otherwise formally closed.
- Rejected call-offs remain in history and may be moved to customer-facing Trash.
- A site user may submit a new request for the same plot and service after rejection, but
  the original rejected decision must remain unchanged and traceable.
- The exact rejected-request resubmission user journey is proposed in DEC-034 and must be
  formally confirmed before implementation. It must not be replaced with an unlinked new
  call-off flow.

### Trash and Undo Rules

- Eligible user-triggered withdrawal, Trash and restoration actions must offer a
  five-second quick Undo.
- Quick Undo must restore the complete previous customer-facing state and must not bypass
  authorisation.
- Customer-facing Trash keeps eligible withdrawn or rejected call-offs recoverable for
  seven days.
- Trash is a customer-facing list state, not permanent deletion.
- Restoring from Trash within seven days returns the complete request to the appropriate
  customer-facing history or list state.
- Bulk Undo and bulk Trash restoration are atomic: either every eligible affected item is
  restored or none are changed.
- If any item in a bulk operation fails authorisation, eligibility or expiry checks, the
  operation fails without partially changing the selected set.
- Expired Trash records are hidden from customer-facing Trash after seven days and
  retained as audit-critical history. They are not permanently deleted by the Version 1
  Trash expiry process.

---

# 7. Approval and Amendments

Fenster Office Staff may approve or reject a call-off. No other Customer Portal role may
approve, reject or publish that decision.

Customer-visible explanations must be stored as `customer_response` and kept separate from
private `internal_reason`.

The portal records and communicates decisions; it must not calculate labour, manufacturing, delivery or resource availability.

If amendments are approved for call-offs, they must record the new requested date,
explicit customer-visible response or customer-provided reason where applicable, private
internal reason where applicable, user, timestamp and previous customer-visible values.
Their full eligibility and lifecycle remain unconfirmed beyond the Version 1 withdrawal,
rejected-request resubmission, Trash and Undo rules. Amendments must never overwrite
history.

Completed services cannot be amended unless an authorised reopen process is explicitly designed.

---

# 8. Customer-Facing Statuses

The confirmed call-off decision statuses are:

- Submitted
- Approved
- Rejected

Withdrawn is a Version 1 request lifecycle state for a submitted call-off removed before
an office decision. It is not a Fenster approval or rejection decision.

Trash is a customer-facing list state for eligible withdrawn or rejected requests. It is
not permanent deletion and must preserve audit-critical history.

Any later progress labels must remain customer-facing portal statuses, not SiteApp workflow
statuses. Their mapping, labels, icons, colours and transitions require an explicit decision
before implementation. Do not use a call-off approval to imply operational production,
delivery, installation or verification progress.

---

# 9. Notifications

Users should receive in-app notifications when a call-off is submitted, approved or rejected.
Notifications for amendments and later customer-facing status changes require their own
confirmed lifecycle rules.

Email may be added where configured. SMS and push are future options.

Notification delivery failure must not corrupt request state.

---

# 10. Data Ownership

## SiteApp owns
- developments, phases and plots;
- plot completion;
- operational production, delivery and installation information;
- internal planning and decisions.

## Customer Portal owns
- portal users, sessions and portal roles;
- portal role-to-site assignments and active-site context;
- customer call-off batches, requests and revisions;
- amendments and customer-visible responses;
- portal notifications and settings.

Any copied operational data is a projection or cache, not the source of truth. Customer
Portal plot projections are modelled as `projected_plots` to avoid confusing them with
SiteApp operational plots.

---

# 11. Integration Philosophy

Integration points will be defined after the foundation is established.

Use well-defined APIs, events or synchronisation services. Do not directly duplicate SiteApp tables, models, policies or services.

If SiteApp is unavailable, previously synchronised data may be read-only, stale data must be identified, failed submissions must not appear accepted, and internal errors must not be exposed.

---

# 12. Explicitly Out of Scope

Do not introduce:

- SiteApp workflow stages, trade sequencing, sign-offs, Black Hat approvals, dependencies, readiness or build verification;
- SiteApp roles or workflow policies;
- SiteApp Filament resources or company, site, plot, trade and workflow administration;
- SiteApp queries, templates, issues or internal notes;
- internal audit history, trade assignments, labour planning, manufacturing planning or capacity;
- SiteApp database, services, permissions or workflow models.

Portal-specific approval screens are allowed only for portal requests.

---

# 13. Version 1 Scope

Include secure authentication, customer isolation, dashboard, outstanding plots, service requests, amendments, portal approval, notifications, statuses, history, responsive design and automated tests.

Exclude SiteApp workflow, scheduling logic, labour/resource planning, trade management, build verification, SiteApp queries, general SiteApp administration, MFA, SSO, native apps, advanced analytics and uploads unless separately approved.

---

# 14. Technical Foundation

Expected foundation: PHP 8.3+, Laravel 13, Blade, Tailwind CSS, Alpine.js, Livewire where useful, SQLite locally, MySQL-compatible production, Pest and Laravel Pint.

Exact versions must be taken from the new repository lockfiles.

Do not assume Filament is required.

---

# 14A. Next Implementation Milestone

## Sprint 1A - Secure Access and Domain Foundation

Sprint 1A prepares the secure domain foundation before call-off workflow schema and
approval workflow implementation.

Scope:

- authentication;
- disabled public registration unless explicitly approved;
- active-account enforcement;
- customer organisations;
- four portal roles;
- sites;
- user-to-site assignments;
- assigned-site authorisation for site users and Fenster Office Staff;
- active-site selection for site roles;
- production-safe dashboard routing;
- development-only role preview guarded from production;
- database planning for call-off batches and individual call-off requests.

Out of scope:

- production call-off submission;
- Office Staff approval and rejection actions;
- Trash, Undo and restoration implementation;
- SiteApp integration;
- SiteApp roles, policies, workflows, tables or internal statuses.

Exit:

- authenticated users are active and assigned to a customer organisation;
- portal roles are distinct and portal-specific;
- site access is enforced server-side;
- development preview cannot be enabled in production or bypass authentication;
- the next schema task can implement call-off batches and requests against these access
  boundaries.

---

# 15. Acceptance Criteria

Version 1 is complete when:

1. Users log in securely and public registration remains disabled.
2. Site Manager, Assistant Site Manager, Finishing Foreman and Fenster Office Staff are
   enforced as four distinct portal roles.
3. Site roles can select only their assigned sites and cannot access another site's data or
   submit a call-off for it.
4. Site roles route to the site dashboard and see all call-offs for the active assigned
   site in Version 1.
5. Fenster Office Staff route to Review Requests and can review only assigned sites.
6. Only Fenster Office Staff can approve or reject a submitted call-off for their assigned
   sites.
7. Completed projected plots cannot receive new requests.
8. Separate call-offs work for Cavity Closers, Windows and CML, and duplicate active
   call-offs are prevented.
9. A call-off batch represents one submission action with exactly one site, one service
   type, one requested date and one submitting user, and may contain one or many
   individual projected plot requests.
10. Each individual request owns its own status, approval, rejection, decision and history.
11. Office Staff can decide individual requests independently after submission.
12. Batch-level operations never overwrite individual decisions once requests in the batch
   have diverged.
13. Submitted, approved, rejected and withdrawn request states retain user attribution,
   timestamp, previous values, customer-visible history and separate customer response
   and internal reason fields where applicable.
14. Approved call-offs cannot be deleted, trashed or cancelled by site users in Version 1
   and remain active duplicate blockers until a future authorised closing lifecycle event.
15. Rejected call-offs remain in history, may be trashed from customer-facing lists and
   support traceable resubmission once the proposed DEC-034 journey is formally confirmed.
16. Five-second quick Undo works for eligible withdrawal, Trash and restoration actions.
17. Customer-facing Trash supports seven-day recovery for eligible withdrawn or rejected
   call-offs, then hides expired Trash records while preserving audit-critical history.
18. Bulk Undo and bulk Trash restoration are atomic and cannot partially restore selected
   items.
19. Approved and rejected decisions retain user attribution, timestamp,
   previous values, customer-visible response and private reason separation.
20. Development role preview is unavailable in production and never bypasses authentication
   or authorisation.
21. QR codes, when introduced, identify a site but never replace authentication or
    authorisation.
22. Notifications for submitted, approved and rejected call-offs work.
23. No SiteApp internal data or workflow has been exposed or duplicated.
24. Critical tests pass on supported devices and databases.

---

# 16. Outstanding Decisions

1. Confirm the customer-facing meaning of CML.
2. Confirm bulk call-off creation limits and validation feedback.
3. Define amendment eligibility and lifecycle beyond Version 1 withdrawal,
   rejected-request resubmission, Trash and Undo.
4. Define required email notifications.
5. Define synchronisation freshness.
6. Map approved call-offs and any operational information to customer-facing statuses.
7. Define long-term retention periods outside the seven-day customer-facing Trash window.
8. Confirm the initial SiteApp integration method.
9. Confirm the proposed rejected-call-off resubmission journey in DEC-034 before its UI is
   implemented.

---

# Success Criterion

> **The portal must make it immediately clear what remains outstanding, what was requested
> or agreed, what has completed, and what the customer or Fenster should do next.**

---

# 17. Management Requirements Consolidation — 20 August 2026

This section is the authoritative clarification of Version 1 scope. It supersedes the
following earlier assumptions in this brief: three services rather than four; Office Staff
being restricted to assigned sites; one service and one requested date per submission
batch; final customer-facing **Approved/Rejected** terminology; and any direct Portal
write-back to SiteApp/Excel. Where this section conflicts with an earlier section, use
this section.

## 17.1 Product Boundary and Roles

The Customer Portal is a customer-facing communication and call-off system. It displays
authorised site and plot information, accepts customer call-offs, manages date agreement
and customer-requested amendments, displays customer-safe progress and completion,
maintains history, provides notifications, and offers agreed-work calendars and schedules.

SiteApp/Excel remain the operational source for plot, product and completion data. The
Portal must never become Fenster's manufacturing, planning, workflow, verification or
internal administration system.

Site Manager, Assistant Site Manager and Finishing Foreman remain distinct titles for
display, identity, reporting and history, but have identical Version 1 permissions as a
single Site User group. A Site User may be assigned to one or multiple sites in their
customer organisation, sees all plots and customer-safe records on active assigned sites,
and may manage authorised call-offs for those sites. There are no plot-level assignments.

Fenster Office Staff are the Version 1 internal staff group. Authorised staff share the
same internal permission level and can see all customers, sites, plots and customer
call-offs; manage customer and Site User accounts; and assign or remove Site Users from
sites. The former assigned-site restriction for Office Staff is superseded. Customers do
not self-register or administer users. Deactivated users retain their historic name and
role in audit history.

## 17.2 Authentication and Site Context

The live Portal uses username/email and password authentication, secure sessions,
password reset, active-account enforcement, rate limiting and server-side authorisation.
Public registration is disabled. MFA and SSO are future work.

Site Users manually select an assigned site before accessing site-scoped data or acting.
Future QR codes identify a site only: an authorised logged-in user continues to that
site's dashboard, an unauthenticated user signs in first, and an unauthorised user is
denied safely. QR codes never confer permissions and manual selection remains available.

Development-only role preview may exercise routing but must never be enabled in production
or bypass normal authentication and authorisation.

## 17.3 Plot-Centric Dashboard and Statuses

The Site Dashboard is a plot-centric overview. Each row represents one plot and contains
Plot Reference, Overall Status, actions and service columns in this exact order:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

Snagging is a full, independently callable service. Every service cell uses one of the
following customer-facing states:

- **Nothing / Not Called Off**;
- **Called Off — Awaiting Date**;
- **Date Agreed — [agreed date]**; or
- **Completed — [actual Completed Date]**.

Use text, labels or icons as well as colour. The physical/display order Cavity Closers →
Windows → Snagging → CML is not a dependency: customers may call off any eligible service
independently.

Overall plot status is calculated as follows:

| Status | Definition |
|---|---|
| Nothing Called Off | No service has been called off. |
| Call-Offs In Progress | One or more active call-offs remain unresolved or awaiting a date, unless that service is already Completed. |
| Dates Agreed | At least one service is Date Agreed or Completed and no currently called-off service remains Awaiting Date. Services not yet called off do not prevent this state. |
| Partially Completed | At least one service is Completed but not all four; this label takes precedence over unresolved call-offs. |
| Fully Completed | All four services are Completed. |

Fully Completed plots are highlighted, retained indefinitely, hidden by default and made
available through Show Completed/filter functionality. They are never automatically
archived or deleted.

Each plot row has a Call Off action, and users can select multiple plots for Call Off
Selected. A separate New Call Off page remains available. Both routes use the same
eligibility rules. Plot Details prominently shows overall status, non-zero product
quantities and the four services; each service opens its customer-safe history, dates,
attachments and relevant actions.

## 17.4 Product Eligibility, Submission Batches and Dates

Eligibility comes from source product/plot data and Portal rules. Customers cannot edit
operational data. Existing, completed, ineligible and unresolved plot/service
combinations stay visible but disabled with an explanation; they are never silently
removed.

Product information is projected from Excel/SiteApp. Customer-facing product output is
limited to non-zero Total Windows and Total Doors, calculated from the confirmed registry
in `documentation/siteapp-import-data-dictionary.md`. Individual source codes remain
Office/audit detail and excluded codes such as CAS, PFD and MISC are not customer product
types. Do not show products in the main overview; show the approved totals in Plot Details
and relevant call-off selection/review.

A call-off batch represents one Site User submission action for one active site. It may
contain one or many plot/service combinations. Each individual request owns its plot,
service, requested date, status and full history.

- A user may select one or many plots and one or many services.
- Each selected service may have a different requested date.
- By default each selected service applies to every selected plot, but users may untick
  individual plot/service combinations.
- Mixed services and mixed requested dates are valid within one batch.
- No more than one active call-off may exist for the same plot and service.
- Batch-level presentation or bulk actions must never overwrite individual dates,
  decisions or history once records diverge.
- Where supported, bulk Undo and restoration remain atomic: all eligible records change
  or none do.

Normal minimum lead time is three weeks for standard products and four weeks where the
exact source product code `BF` has a positive quantity. The customer-facing normal request
window adds a one-week buffer, so the earliest normal date is four weeks normally and five
weeks only for positive `BF`. Other door codes and Total Doors do not trigger this rule.
Calculate this independently per plot/service from current source truth.

Normal requested dates are Monday–Friday only, exclude UK bank holidays and may be no
more than six months ahead. The date picker prevents dates earlier than the individually
calculated normal date.

**Request Earlier Date** is the explicit exception route. It requires a reason/message,
is prominently flagged to Fenster and recorded in history. Fenster may Accept Date or
Propose Alternative Date. Accepting an inside-lead-time date requires additional
acknowledgement of the exception.

## 17.5 Date Agreement, Withdrawal and Amendments

After a Site User submits a requested date, Fenster Office Staff may **Accept Date** or
**Propose Alternative Date**. Accepting immediately makes the service **Date Agreed**;
the customer does not need to confirm it.

For a proposed alternative, the original submitter is the primary action recipient, but
any currently authorised Site User on the site may Accept Alternative or Reject Alternative
with a mandatory reason. History records the actual responder. Acceptance makes the date
Date Agreed. Rejection leaves the call-off alive and permits another alternative until a
date is agreed or the call-off is withdrawn.

Use **Date Agreed**, not **Approved**, as the final customer-facing state. Rejecting a
proposed alternative is not a rejected call-off and must not become a separate
resubmission workflow. No new direct final call-off rejection flow is defined by this
clarification.

Site Users may withdraw a call-off at any point before Date Agreed, including while an
alternative awaits response. Once Date Agreed, withdrawal is unavailable and changes use
the amendment route. Existing five-second quick Undo and seven-day customer-facing Trash
recovery requirements continue for eligible withdrawal, Trash and restoration actions.
Expiry removes an item from customer-facing Trash while preserving audit-critical history.

After Date Agreed, a Site User may submit an amendment with a new requested date, a
predefined amendment reason and optional explanation. There is no fixed amendment cutoff.
An amendment within three working days of the current agreed date is marked **Urgent / Late
Amendment** as a warning, not a prohibition.

An amendment places the existing Date Agreed date **On Hold** rather than erasing it.
Fenster may accept the amended date or propose an alternative using the original
negotiation process. A newly agreed date becomes Date Agreed while prior dates remain in
history. If agreement fails, Fenster staff decide whether an On Hold date can be
reinstated; it is never restored automatically. Fenster-originated changes to an agreed
date are handled outside the Portal.

## 17.6 Completion and Source Integration

Completion is sourced from Excel/SiteApp only; Fenster staff cannot manually mark a
service Completed in the Portal. Use Job Stage plus Completed Date where available:

| Service | Job Stage |
|---|---|
| Cavity Closers | `CC08` |
| Windows / Plot Calloff Installation | `CA02` or `CA03` |
| Snagging | `SN05` |
| CML | `CML4` |

Display **Completed — [Completed Date]**. A Completed Date without the expected stage
code still makes a service Completed; never use requested, agreed or planned dates as an
actual completion date.

If a source update reports a service Completed while negotiation or amendment is open,
completion takes priority: close that Portal process, preserve its history and show the
actual completed date. If source data later reverses completion, follow the source,
retain previous history and show the reversal with date/time. Completion itself does not
send an in-app notification or email.

Integration is read-only from the Portal's perspective:

```text
Excel / SiteApp → Customer Portal
```

The Portal does not write agreed dates to Excel/SiteApp. It owns requested dates,
negotiation, Date Agreed, amendments and Portal communication/history. Excel/SiteApp own
product quantities, operational completion and source identifiers.

`Call No.` is a permanent unique source identifier. Confirmed call types are `PC1` (Plot
Install), `CC1` (Cavity Closer 1), `CM1` (Revisit 1), `CM2` (Revisit 2) and `CML` (CML Call
Off). Only PC1, CC1 and CML currently have confirmed four-service Portal mappings. CM1 and
CM2 require reconciliation rather than an invented service mapping. `CC!` is invalid and a
literal value is treated as a likely typo for CC1, never silently corrected. The complete
field, Items Ordered Status, Site Name durability, export scope and the final use of Plot To
Be Installed and Site Value remain unresolved as recorded in
`documentation/siteapp-import-data-dictionary.md`.

Source data refreshes approximately every one to two hours. On a failed or delayed sync,
show the last successful information and **Last updated: [date/time]**. If a known Call
No. disappears, preserve last known data and Portal history, flag the issue internally and
do not silently delete or hide it.

Use CML on the overview. Its full customer-facing expansion is TBC and must be available
when the service is opened. Customers cannot download the actual CML certificate in the
Portal.

## 17.7 History, Attachments, Notifications and Calendar

Each plot/service timeline contains original requests, alternatives, customer responses,
customer-visible reasons/messages, Date Agreed and On Hold states, amendments, previous
agreed dates, withdrawals, completion/reversal events and related attachments. Every
event records exact date/time, actual person's name and role. Fenster staff may see
permitted private internal reasons; Site Users must never see private Fenster information.

Communication remains structured around defined actions. Do not add general chat or a
conversation thread. Site Users may attach photos/documents to customer actions that
include a reason/message, including New Call Off, Reject Alternative Date, Request Earlier
Date and Amendment Request. Fenster staff can view but do not upload through this
workflow. Attachments belong to their history event, not a general file library.

In-app notifications cover meaningful workflow changes: call-off submitted, requested date
accepted/Date Agreed, alternative proposed/accepted/rejected, amendment requested,
amendment accepted, amendment alternative proposed and other meaningful action-required
changes. They identify plot, service, action and next step and link to the record.
Dismissing or reading a notification never removes history. The original submitter gets
the primary alternative-date action notification/email; all assigned Site Users see status
in the Portal; Fenster staff receive relevant customer-action in-app notifications. Email
is reserved for important/action-required events and must not duplicate every in-app
notification. Use reminders without noise for Completed services. Completion is excluded
from notification/email.

The agreed-work calendar shows Portal **Date Agreed** records with plot, service and
agreed date. It supports appropriate site, plot, service and date filters. Calendar
entries use service-specific colours for Cavity Closers, Windows, Snagging and CML but
retain accessible text/icons and hover/focus explanations. On mobile, first tap opens a
summary and View Details opens the record. Calendar/PDF schedules use Portal Date Agreed
data only and identify site, plot, service, agreed date and relevant status.

## 17.8 Company Testing and Remaining TBC Items

The first company test uses a fictional customer/site with dummy plots, so staff can
submit, accept, reject alternatives, amend and withdraw without affecting operations. It
includes BF products, differing lead times, completed plots and alternative-date
negotiation. Use at least one Fenster staff member and two or three representatives of the
external Site User roles.

After testing, collect feedback before changing the application. Classify it as a bug,
important usability improvement, genuine new requirement or personal preference, then
agree priorities with management.

The following remain TBC and must not be invented:

1. The exact customer-facing expansion of CML.
2. The predefined amendment-reason list.
3. Ownership and operational contract for updates to the Excel/source data.
4. The detailed source integration mechanism, credentials and reconciliation process.
5. Long-term retention periods beyond the seven-day customer-facing Trash window.
6. Any migration treatment for historic direct rejected-call-off records created under
   earlier assumptions; rejecting an alternative is already defined and is not a rejected
   call-off.
