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
- receive confirmations, revised dates or rejections;
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

## Customer User
- View authorised developments and outstanding plots.
- Submit and amend date requests.
- View customer-facing statuses and notifications.

## Customer Administrator
- Customer User abilities.
- Future ability to manage users within their own organisation.

## Internal Administrator
- Review portal requests.
- Confirm, revise or reject requested dates.
- Publish customer-visible responses.

Internal Administrator access applies only to Customer Portal approval functions.

---

# 4. Authentication

Version 1 requires secure login, username or email, password authentication, password reset, secure sessions, active-account enforcement, rate limiting and server-side authorisation.

Public registration is disabled unless explicitly approved.

MFA and SSO are future features.

---

# 5. Dashboard and Outstanding Plots

After login, customers should see planned Window, Cavity Closer and CML dates, outstanding requests and recently confirmed requests.

Only plots not reported complete by SiteApp may be selected for new requests. Historical requests for completed plots may remain read-only.

Filtering should support development, phase, plot, service, status and date. A simple plot search is required.

---

# 6. Requests

Each outstanding plot may have independent requests for:

- Cavity Closers;
- Windows;
- CML.

A plot may have only one active request per service. Later changes must use amendments or revisions rather than duplicate active requests.

A request records the plot, service, requested date, customer, submitting user, timestamp and optional note.

Bulk requests may be supported, but each plot and service must remain independently traceable.

A requested date is not confirmed until Fenster publishes a decision.

---

# 7. Approval and Amendments

Internal Administrators may confirm, propose a revised date or reject a request.

Customer-visible explanations must be separate from private internal reasons.

The portal records and communicates decisions; it must not calculate labour, manufacturing, delivery or resource availability.

Amendments must record the new requested date, reason, note, user, timestamp and previous customer-visible values. They return to review and must not overwrite history.

Completed services cannot be amended unless an authorised reopen process is explicitly designed.

---

# 8. Customer-Facing Statuses

- Submitted
- Confirmed
- In Production
- Delivery Made
- Installation Planned
- Installation Complete
- Amendment Requested
- Revised Date Proposed
- Rejected

These are customer-facing portal statuses, not SiteApp workflow statuses. Labels, icons, colours and transitions must be centralised and accessible.

---

# 9. Notifications

Customers should receive in-app notifications when a request is submitted, confirmed, amended, rejected, revised or moved to a new customer-facing status.

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
- customer requests and revisions;
- amendments and customer-visible responses;
- portal notifications and settings.

Any copied operational data is a projection or cache, not the source of truth.

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

# 15. Acceptance Criteria

Version 1 is complete when:

1. Customers log in securely.
2. Customers only access authorised organisations, developments and plots.
3. Completed plots cannot receive new requests.
4. Separate requests work for Cavity Closers, Windows and CML.
5. Duplicate active requests are prevented.
6. Internal Administrators can confirm, revise or reject.
7. Customers can request permitted amendments.
8. Revision history is retained.
9. Notifications and customer-facing statuses work.
10. No SiteApp internal data or workflow has been exposed or duplicated.
11. Critical tests pass on supported devices and databases.

---

# 16. Outstanding Decisions

1. Confirm the customer-facing meaning of CML.
2. Confirm bulk request rules.
3. Confirm Customer Administrator Version 1 abilities.
4. Decide whether customers must accept revised dates.
5. Define amendment cutoff statuses.
6. Define required email notifications.
7. Define synchronisation freshness.
8. Map operational information to customer-facing statuses.
9. Define retention periods.

---

# Success Criterion

> **The portal must make it immediately clear what remains outstanding, what was requested, what Fenster confirmed, and what happens next.**
