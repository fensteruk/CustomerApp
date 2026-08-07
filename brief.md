# Fenster Customer Portal
## Project Brief — Version 1
**Status:** Foundation Specification  
**Last Updated:** 22 July 2026

---

# 1. Executive Summary

The Fenster Customer Portal is a separate customer-facing application that integrates with SiteApp.

It allows authorised customers to:

- view outstanding plots;
- request dates for Cavity Closers, Windows and CML;
- request amendments;
- receive approval or rejection decisions;
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

Filtering should support development, phase, plot, service, status and date. A simple plot search is required.

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
   support traceable resubmission.
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

---

# Success Criterion

> **The portal must make it immediately clear what remains outstanding, what was requested, what Fenster approved or rejected, and what happens next.**
