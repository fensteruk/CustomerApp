# Fenster Customer Portal Decisions

Sprint 1 - Call Off Workflow

Next implementation milestone:
Sprint 1B - Call-Off Domain Design and Schema

Status:
Sprint 1A implemented and verified; Sprint 1B domain design approved for implementation.
## DEC-009

Date:
23 July 2026

Decision:
Call-offs are approved or rejected by Fenster Office Staff.

Reason:
Approval is an office responsibility rather than a site-management responsibility.

---

## DEC-010

Date:
23 July 2026

Decision:
The portal has four initial roles:

- Site Manager
- Assistant Site Manager
- Finishing Foreman
- Fenster Office Staff

Reason:
The first three roles submit call-offs. Fenster Office Staff review them.

---

## DEC-011

Date:
23 July 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman users may submit call-offs for assigned sites.

Reason:
These are the customer-side site roles responsible for requesting Fenster work.

---

## DEC-012

Date:
23 July 2026

Decision:
For the initial version, site users select their assigned site from a menu.

Future behaviour:
Users will scan a site QR code.

Security rule:
A QR code identifies a site but never replaces authentication or site authorisation.

---

## DEC-013

Date:
23 July 2026

Decision:
A development-only role-preview screen will allow selection of:

- Site Manager
- Assistant Site Manager
- Finishing Foreman
- Fenster Office Staff

Selecting Site Manager, Assistant Site Manager or Finishing Foreman leads to the
site dashboard with assigned-site selection. Selecting Fenster Office Staff leads to
the Review Requests dashboard.

Security rule:
Role preview must be disabled outside approved development environments, must not persist
or grant a role, and must never bypass authentication or authorisation.

---

## DEC-014

Date:
23 July 2026

Decision:
The confirmed call-off office decision lifecycle is:

```text
Submitted → Approved
          → Rejected
```

Fenster Office Staff are the only portal role permitted to approve or reject a submitted
call-off. The decision must be auditable and must not trigger, reproduce or expose SiteApp
operational workflow.

Withdrawal is a separate request lifecycle state confirmed in DEC-018. It is not an
approval or rejection decision.

Reason:
The portal records the customer-facing request and decision; SiteApp remains the
operational system.

---

## DEC-015

Date:
5 August 2026

Decision:
Fenster Office Staff may review and approve or reject call-offs only for sites assigned
to them in the Customer Portal.

Reason:
Assigned-site review scope keeps the approval model explicit, supports least-privilege
access and avoids giving office users automatic all-site visibility.

---

## DEC-016

Date:
5 August 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman users may see all call-offs
for their active assigned site in Version 1, not only call-offs they personally submitted.

Reason:
Call-offs are site-level requests. Site teams need shared visibility for the selected
site while still being restricted to assigned sites.

---

## DEC-017

Date:
5 August 2026

Decision:
Site Manager, Assistant Site Manager and Finishing Foreman remain three distinct portal
roles, but have identical Version 1 permissions.

Reason:
The roles must be recorded separately for future permission differences, but no Version 1
functional difference has been confirmed.

---

## DEC-018

Date:
5 August 2026

Decision:
A pending submitted call-off may be withdrawn before Fenster Office Staff approve or
reject it. Withdrawal is available to authorised site users for the active assigned site.

Rules:

- Withdrawal moves the request out of the active review queue.
- Withdrawal records the user, timestamp, previous state and reason or note where supplied.
- Withdrawal must not delete the request history.
- A withdrawn request no longer blocks a new active request for the same plot and service.

Reason:
Site teams need a safe way to remove an accidental or obsolete pending request before an
office decision is made.

---

## DEC-019

Date:
5 August 2026

Decision:
Approved call-offs cannot be deleted, trashed or cancelled by site users in Version 1.

Rules:

- Approved records remain visible in request history.
- Any later cancellation or reversal of an approved call-off requires a separately
  designed authorised process.
- The portal must not silently remove or overwrite approved decision audit records.

Reason:
Approval is an auditable Fenster decision and may already have downstream operational
meaning outside the portal.

---

## DEC-020

Date:
5 August 2026

Decision:
Rejected call-offs remain in history and may be moved to customer-facing Trash. A site
user may submit a new request for the same plot and service after rejection, but the new
submission must be traceable back to the rejected request.

Rules:

- Rejection records are never overwritten.
- Trashing a rejected request removes it from normal customer-facing lists only.
- Resubmission creates a new submitted request or explicit revision record; it does not
  change the original rejected decision.

Reason:
Rejected requests should not clutter active working lists forever, but the rejection and
any later resubmission must remain auditable.

---

## DEC-021

Date:
5 August 2026

Decision:
Eligible user-triggered withdrawal, trash and restoration actions must offer a
five-second quick Undo.

Rules:

- Quick Undo restores the request or bulk action to its previous customer-facing state.
- Quick Undo must restore the complete action atomically.
- Quick Undo must not bypass authorisation checks.
- Approved call-offs are excluded because they cannot be deleted, trashed or cancelled by
  site users in Version 1.

Reason:
A short undo window reduces accidental removals while preserving the audit trail.

---

## DEC-022

Date:
5 August 2026

Decision:
Customer-facing Trash keeps eligible withdrawn or rejected call-offs recoverable for
seven days.

Rules:

- Trash is a customer-facing list state, not permanent deletion.
- Restoration within seven days returns the complete request to the appropriate
  customer-facing history or list state.
- Trash and restoration record user, timestamp and previous values.
- Approved call-offs are not eligible for Trash in Version 1.

Reason:
Seven-day recovery gives site users a practical safety window without treating Trash as
the audit record.

---

## DEC-023

Date:
5 August 2026

Decision:
Bulk undo and bulk Trash restoration must be atomic.

Rules:

- A bulk undo either restores every affected item or none of them.
- A bulk restoration either restores every selected recoverable item or none of them.
- If any item fails authorisation, eligibility or expiry checks, the operation fails
  without partially changing the selected set.

Reason:
Partial restoration would make bulk request history difficult to understand and audit.

---

## DEC-024

Date:
5 August 2026

Decision:
Expired Trash records are hidden from customer-facing Trash after seven days and retained
as audit-critical history. They are not permanently deleted by the Version 1 Trash expiry
process.

Reason:
The customer-facing Trash should stay tidy, while decisions, withdrawals and request
history remain available for audit and support.

---

## DEC-025

Date:
5 August 2026

Decision:
The Customer Portal uses a call-off batch model for user submission actions.

Rules:

- One call-off batch represents one user submission action.
- One batch has exactly one site, service type, requested date and submitting user.
- One batch may contain one or many individual requests for different projected plots at
  that site.
- Mixed service types and requested dates are not permitted within a Version 1 batch.
- The batch groups submission, bulk withdrawal, quick Undo and Trash restoration.
- Each individual request owns its own status, approval, rejection, decision and history.
- Fenster Office Staff may decide individual requests independently after submission.
- Once requests in a batch have received different decisions, later batch-level operations
  must not overwrite those individual decisions.
- Batch-level operations must be authorised for every affected request and must remain
  atomic where confirmed by the bulk Undo and restoration rules.

Reason:
Customers may submit several plot/service requests at once, but approval decisions and
history must remain auditable per individual request. The batch groups the user action
without turning multiple requests into a single approval unit.

---

## DEC-026

Date:
5 August 2026

Decision:
Each Version 1 call-off batch contains exactly one site, one service type, one requested
date and one submitting user. It may contain one or multiple projected plots, with one
individual call-off request created for each selected projected plot.

Rules:

- Mixed service types are not permitted in a batch.
- Mixed requested dates are not permitted in a batch.
- Each request remains independently auditable and may later receive its own Office Staff
  decision.

Reason:
A single service and requested date make bulk submission, validation, Undo and audit
history clear without preventing independent request decisions.

---

## DEC-027

Date:
5 August 2026

Decision:
Submitted and approved call-offs remain conflict-active for the same plot and service.
Rejected and withdrawn call-offs do not block resubmission. Customer-facing Trash does
not independently affect duplicate blocking.

Rules:

- An approved call-off remains conflict-active until a future authorised lifecycle event
  marks it completed, superseded or otherwise formally closed.
- Completion, supersession and formal closure are outside Sprint 1B scope.
- The duplicate-prevention key must remain populated on approval and be cleared only when
  the request is rejected or withdrawn in Version 1.

Reason:
An approved call-off may already have downstream operational meaning. Allowing an
immediate duplicate would undermine the portal's request traceability.

---

## DEC-028

Date:
5 August 2026

Decision:
The Customer Portal plot projection table is named `projected_plots`.

Rules:

- `projected_plots` is a Customer Portal projection of authorised plot data.
- It is not the operational SiteApp plot table or model.
- It stores a local integer primary key, a public UUID, an explicit external source and
  an external identifier.
- External identity is recorded separately from local identity.
- The projection must not introduce SiteApp workflow, readiness, trade sequencing or
  operational status rules.

Reason:
The explicit name prevents future developers confusing Customer Portal projected plot
records with SiteApp operational plots.

---

## DEC-029

Date:
5 August 2026

Decision:
`active_conflict_key` is a computed domain value controlled only by call-off lifecycle
actions.

Rules:

- Developers must not set or clear `active_conflict_key` directly from controllers, views,
  validation objects or unrelated services.
- `UpdateConflictKeyAction` owns the calculation and persistence of the key.
- Every state transition that may change duplicate-blocking behaviour must call
  `UpdateConflictKeyAction`.
- Submitted and approved requests receive and retain the key.
- Rejected and withdrawn requests have the key cleared in Version 1.
- Trash and restore operations do not independently change the key.

Reason:
Duplicate prevention is a business rule. Keeping the key behind a single action prevents
drift between submission, approval, rejection, withdrawal, resubmission and future API
paths.

---

## DEC-030

Date:
5 August 2026

Decision:
Call-off domain fields must use explicit response and reason names rather than generic
notes.

Rules:

- Use `customer_response` for customer-visible response text.
- Use `internal_reason` for private Fenster-only rationale.
- Do not introduce generic `notes` fields in the call-off domain.
- Customer-visible text and internal text must remain separate in validation, persistence,
  resources, events, notifications and tests.

Reason:
Explicit field names prevent accidental disclosure and remove ambiguity from future
maintenance.

---

## DEC-031

Date:
5 August 2026

Decision:
Every Sprint 1B business table has both an integer primary key and a UUID.

Rules:

- Integer `id` columns remain the primary keys.
- UUIDs are exposed externally in URLs, APIs, QR codes, notifications and integration
  references where a public identifier is needed.
- UUIDs must be unique and generated server-side.
- Internal integer IDs must not be trusted from customer-controlled input.

Reason:
Integer keys keep local relational data simple. UUIDs provide stable public identifiers
without exposing internal row counts or coupling external references to database keys.

---

## DEC-032

Date:
5 August 2026

Decision:
Call-off eligibility is centralised in `DetermineCallOffEligibilityAction`.

Rules:

- UI availability, submission validation, resubmission validation, API entry points and
  tests must ask `DetermineCallOffEligibilityAction`.
- Eligibility must enforce authorised site scope, outstanding projected plot state,
  supported service type and duplicate active request blocking.
- Eligibility must not calculate SiteApp operational availability, lead times,
  manufacturing capacity or readiness.
- Submission actions must re-check eligibility immediately before persistence.

Reason:
Eligibility is used in several paths. A single action keeps the business rule testable
and prevents duplicated checks from drifting apart.

---

## DEC-033

Date:
5 August 2026

Decision:
Call-off history records events, while request status stores only the current lifecycle
snapshot.

Rules:

- Status is the current request state, such as submitted, approved, rejected or withdrawn.
- History is an immutable event stream, such as submitted, approved, rejected, withdrawn,
  trashed, restored, undo_applied or resubmitted.
- Customer-facing Trash is recorded as an event/list state and must not become a request
  status.
- Restoring a trashed withdrawn request leaves the current status as withdrawn.
- History must not infer whether an event happened solely from the current status.

Reason:
Event history preserves the full audit trail even when the current status no longer
describes earlier actions.

---

## Open Permission and Lifecycle Questions

The following remain unresolved and must be decided before the affected implementation:

1. Confirm the proposed rejected-call-off resubmission journey in DEC-034 before its UI
   is implemented.
2. The customer-facing meaning of CML.
3. Bulk call-off creation limits and validation feedback.
4. Amendment eligibility and lifecycle beyond withdrawal, rejected-request resubmission,
   Trash and Undo.
5. Required email notification rules.
6. Synchronisation freshness rules.
7. Customer-facing status mapping beyond submitted, approved, rejected and withdrawn
   request states.
8. Long-term retention periods outside the seven-day customer-facing Trash window.
9. Initial SiteApp integration method.

---

## DEC-034

Date:
19 August 2026

Decision:
A rejected call-off may be traceably resubmitted by an authorised site user for the active
assigned site.

Rules:

- The original rejected request and its Office Staff customer response remain unchanged.
- The site user starts the flow from that rejected request; the existing site, plot and
  service context is displayed, not chosen anew.
- The user may enter a new requested date and add or update the customer-facing submission
  message.
- A review and confirmation screen is required before persistence.
- Confirmation creates a new batch and submitted request linked to the rejected source.
- Authorisation, active-site scope, customer organisation, projected-plot completion and
  active duplicate-conflict eligibility are rechecked immediately before persistence.
- The new request begins as `submitted`; the source rejection and customer response remain
  visible in history.
- Approved and withdrawn requests do not use this flow.

Status:
Confirmed 19 August 2026. This is the Sprint 2A resubmission contract.

---

## DEC-035

Date:
20 August 2026

Decision:
Requirements confirmed directly with Fenster management on 20 August 2026 are the
authoritative target product specification. They are recorded in `context-work-prompt.md`,
consolidated in `brief.md` section 17, and assessed in
`documentation/management-gap-analysis-2026-08-20.md`.

Superseded assumptions:

- the Portal has three services rather than Cavity Closers, Windows, Snagging and CML;
- Fenster Office Staff are restricted to assigned sites;
- a call-off batch has one service and one requested date;
- Approved/Rejected is the final customer-facing date lifecycle; and
- direct Portal write-back to Excel/SiteApp is permitted or required.

Consequences:

- The historical decisions remain audit records. Their implementation contracts must not
  be silently reused where they conflict with the management specification.
- DEC-014, DEC-015, DEC-018–DEC-020, DEC-025–DEC-029, DEC-032 and DEC-034 require an
  additive migration/transition treatment in Sprint 3A rather than a destructive rewrite.
- Existing UUIDs, histories, batch-operation audit records, Trash/Undo evidence and
  tenant protections must be preserved.
- Company testing is deferred until the complete management-confirmed programme and final
  integrated QA gate have passed.

Reason:
The prior workflow is a secure, working foundation but does not represent the newly
confirmed four-service, source-driven, negotiated-date product model.

---

## DEC-036

Date:
20 August 2026

Decision:
Sprint 3A introduces the target call-off domain additively. `ProjectedPlotService` is the
independent four-service projection; batches remain submission groups while request-level
fields own service/date; and ordered date negotiations/proposals preserve the future
agreement loop.

Rules:

- Legacy batch fields, statuses, UUIDs, histories, Trash/Undo operations, notifications and
  rejected-resubmission lineage remain intact.
- Legacy Approved is mapped as legacy Date Agreed without rewriting its historical status
  or event; direct legacy Rejected remains a terminal legacy record pending a separate
  retention/presentation decision.
- `UpdateConflictKeyAction` remains the sole conflict-key writer. Date Agreed and
  Amendment On Hold are conflict-active; source Completed is not.
- Fenster Office Staff are global Portal reviewers. Site assignment remains an external
  Site User boundary only.
- A holiday-provider interface is required before lead-time enforcement; Sprint 3A does
  not invent a UK bank-holiday dataset.

Reason:
This gives later sprints stable, auditable data structures without disrupting the working
legacy workflow or making unsupported source-integration assumptions.

---

## DEC-037

Date:
21 August 2026

Decision:
Sprint 3E implements date agreement and alternative-date negotiation only for individual
target-domain `CallOffRequest` records. Legacy Approved remains a truthful historical
status but is presented as Date Agreed; its history is not rewritten.

Rules:

- Office Staff have global portal review scope. Any active Site User assigned to the
  request site may accept or reject an outstanding alternative; rejection has a mandatory
  customer-visible reason.
- An early requested date requires explicit Office acknowledgement before agreement.
  Alternative dates must be future weekdays and consult the replaceable `HolidayProvider`.
  The current provider intentionally has no UK bank-holiday dataset pending an approved
  data source and owner.
- The request, negotiation and proposal are locked and revalidated immediately before
  persistence. Proposals/history remain append-only; Date Agreed remains conflict-active.
- Source completion takes precedence: it closes open negotiations, supersedes unanswered
  alternatives and rejects stale agreement/response attempts.
- Notifications remain in-app, customer-safe and post-commit only. No email, live source
  write-back, operational scheduling, amendment, attachment, calendar/PDF or deployment is
  authorised by this decision.

Reason:
This creates an auditable, portal-specific date conversation without importing SiteApp
workflow or inventing holiday or scheduling rules.

---

## DEC-038

Date:
27 August 2026

Decision:
Fenster Office Staff are internal, globally scoped Portal users and do not require a
customer organisation. `customer_organisation_id` remains the external customer-tenancy
relationship for Site Manager, Assistant Site Manager and Finishing Foreman users.

Rules:

- Active Office Staff require their Fenster Office Staff role but may have
  `customer_organisation_id = NULL`.
- Every external Site User continues to require a legitimate customer organisation and an
  assigned site for site-scoped access.
- A fake or sentinel Fenster customer organisation must not be created to satisfy profile
  validation.
- Existing Office Staff rows with a historical customer organisation remain valid until a
  separate data-cleanup decision is made; this correction does not rewrite them.
- Global Office access remains role-based and must not weaken external customer/site
  isolation.

Reason:
The existing entity is explicitly a customer organisation and has no internal/customer
classification. The users foreign key has always been nullable, while a historical
application profile check incorrectly required it for every role. Making the requirement
role-specific implements the already-confirmed global Office model without inventing a
Fenster customer record.

---

## DEC-039

Date: 3 September 2026

Decision: management confirmed the final Sprint 3F amendment reason and On Hold policy.

Approved stable codes and customer labels:

| Code | Label |
| --- | --- |
| SITE_NOT_READY | Site Not Ready |
| PROGRAMME_CHANGE | Programme Change |
| ACCESS_ISSUE | Access Issue |
| CUSTOMER_REQUESTED_CHANGE | Customer Requested Change |
| MATERIALS_AVAILABILITY | Materials / Availability |
| WEATHER | Weather |
| OTHER | Other |

- OTHER requires Additional information; all other explanations are optional. Normalize
  edge whitespace, reject blank Other text, and retain the existing 2,000-character limit.
- Store code, label snapshot and separate customer_response. Customer/Office history shows
  the friendly label, separate explanation, requester and exact time, never raw codes.
- An active amendment displays On Hold — Date Change Requested for that service, with
  the old agreement preserved as history rather than a current confirmed date.
- The overall plot uses existing Call-Offs In Progress. Partially Completed and Fully
  Completed retain precedence. No Amendment In Progress overall enum is introduced.
- Agreement restores normal Date Agreed presentation with the new date. Source completion
  closes the process without completion notifications. Legacy Approved history is not rewritten.
- Service/request/cycle/proposal/history locking, authorization and audit boundaries remain
  unchanged. These are Portal communication rules, not SiteApp operational workflow.

This resolves both former Sprint 3F product blockers. Dedicated QA, Composer security
reconciliation and final release-candidate verification remain separate gates.


---

## DEC-055

Date: 9 September 2026

Decision: CUSTOMER-RELEASE02 explicitly approves building only reduced RC1 on
`release/customerapp-2026-09-09-rc1`, from main
`0873bac79edf578e9f4a9417e3cafae34e8aa925`, merging the complete Sprint 3F line
`60aa9e2c72074dea2f8a4812aab238ff5d2791d5` first and reviewed security remediation
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` second, with merge commits.

Rules:

- Include amendment lifecycle/final product decisions/current-read concurrency correction,
  main's Office/filter fixes, exact reviewed dependency/assets changes and release-facing docs.
- Exclude WALD02–05 despite accepted work, ADMIN-SITE02, old manual import/interpreter/UI,
  unfinished source import, WALD06 and multi-site pilot. No runtime, migration, profile or
  branch-wide documentation merge from these streams is approved.
- Preserve source-completion precedence, immutable dates/history/notifications and all
  authorisation boundaries. No new business rule or package upgrade is authorised.
- RC has 12 migrations: 11 unchanged baseline files plus one additive Sprint 3F file.
  Populated rollback is not recovery if it would delete amendment metadata.
- Require pre-QA SQLite/MySQL/build/security checks before final documentation commit/freeze.
  Dedicated release QA must run against that exact frozen SHA. No main merge/push,
  production access, Forge change, deployment or RC push is authorised by this task.

Decision-number reconciliation:

This branch preserves its historical DEC-039 (Sprint 3F reasons/On Hold) byte-for-byte.
The separate current WALD documentation line through `c9fe062` uses DEC-039 for standalone
Wald and DEC-040–054 for its later decisions. Those records remain authoritative in their
bounded source/work packages but their code is excluded here. They are not blindly imported
or renumbered. DEC-055 deliberately follows the highest inspected current number and defines
the approved cross-stream release boundary, not a reversal of accepted Wald work.

Earlier status statements that Sprint 3F/security still need merging are superseded for RC1,
not for main or production. Source semantic targets remain separately gated; the retained
legacy importer is not newly authorised for real workbook ingestion by this release.

---

## DEC-056

Date: 9 September 2026

Decision: CUSTOMER-RELEASE02A classifies RC1-T01 as an inherited stale test fixture, not a
Sprint 3F product defect, and approves changing only its four hard-coded requested dates to
four distinct dynamically generated weekdays beyond the normal lead-time window.

Rules and outcome:

- Preserve all notification, record-count, service-context, date-distinctness and status
  assertions; do not change application logic, lead-time/BF rules or validation behaviour.
- Sprint 3F's fixed dates remain intentionally anchored by its controlled 3 September 2026
  test clock. Sprint 3E's 2030 dates remain intentional weekend/holiday test values. The
  Sprint 3D 2026-12-31 value is tampered confirmation data, not an accepted fixture date.
- Require the isolated correction test, focused Sprint 3F, full SQLite, a fresh disposable
  MySQL 8.4.11 clean/upgrade/concurrency gate, Composer/Pint/build and whitespace checks.
- Those gates passed. The commit containing this decision and the RC1 build report freezes
  CUSTOMERAPP RC1 for dedicated QA. It is not approval to push main, deploy or touch Forge/
  production. Any later candidate change requires a newly identified QA failure and explicit
  release handling.
- npm audit remains a separate release-risk review item: 14 development/build dependency
  entries (five high, nine moderate); production dependencies are clean under `--omit=dev`.

---

## DEC-057

Date: 9 September 2026

Decision: CUSTOMER-RELEASE03A approves a two-stage RC1 production recovery contract and
classifies RCQ-01 as `RESOLVED_BY_DEPLOYMENT_RECOVERY_POLICY`.

- RC1 can persist notification type `call_off_amendment_requested`; old main at
  `0873bac79edf578e9f4a9417e3cafae34e8aa925` cannot cast that enum value. Old main is therefore
  not an approved rollback target after Sprint 3F writes may exist.
- The deployment remains in maintenance mode from the final pre-deployment evidence/backup
  through deployment, forward migration, cache refresh and controlled smoke. No normal Office
  or customer workflow traffic is allowed before the release owner reopens the site.
- Before reopening and before any Sprint 3F business write, failure may be recovered by restoring
  the fresh pre-deployment database snapshot and previous verified application release together,
  then verifying ledger, caches and old-production smoke before reopening.
- After reopening or any Sprint 3F write, recovery is roll-forward only from the exact deployed
  RC or a compatible descendant. A code-release/symlink switch does not restore MySQL. Restoring
  a pre-deployment database after traffic would discard legitimate writes and requires a separate
  major-incident/business data-loss decision.
- Do not use populated `migrate:rollback` as the recovery strategy: the additive Sprint 3F
  migration's down path removes amendment metadata and cannot make old code recognise the new
  notification value.
- Frozen executable checkpoint
  `ac250a8e9eef4b40591872de9275d802c76e4fba` remains unchanged. Documentation-only descendants
  may record this decision and historical-document labels without changing the executable tree.
- This decision permits merge-to-main preparation only. It does not authorise a main push,
  production deployment, Forge change, migration, backup operation or production write.

Reason:
Dedicated QA proved forward behaviour but reproduced a `ValueError` when old main read a genuine
RC1 amendment-notification row. The schema migration is additive; persisted enum/domain data is
the compatibility boundary. A maintenance-mode cutover creates an auditable rollback window,
while roll-forward preserves post-release writes and truth once the site has reopened.

---

## DEC-061

Date: 10 September 2026

Decision: CUSTOMER-NEXT-RELEASE01 approves a clean, non-deploying next-release candidate from
production/main `e757bb651f9aa95d67b808a97433d27bc29d03c3`, containing the accepted sidebar,
Office-only customer/site administration and a strictly local/testing synthetic Import Studio
demonstration. WALD02–05 and their five migrations are deliberately excluded.

Rules:

- The release branch is `release/customerapp-next-release-admin-demo-rc1`. It is a candidate for
  dedicated QA only; this decision does not authorise a main merge, push, Forge action,
  production access or deployment.
- Production-facing scope is the accepted responsive sidebar; customer and site list/search,
  create, edit, deactivate, reactivate and immutable audit; read-only plot inventory; read-only
  assigned-user visibility; and truthful source/import unavailable states.
- Only active, currently persisted Fenster Office Staff may administer customers/sites. A null
  customer organisation remains valid for Office Staff. Inactive, stale-role and all three site
  roles are denied. Deactivation preserves sites, users, assignments, plots, requests and history;
  no hard delete is introduced.
- The production Office sidebar contains Review Requests, Customers and Notifications. No demo,
  Wald or non-functional import destination is exposed in production navigation.
- The synthetic Import Studio is default-off, local/testing-only and precomputed. Its production
  routes remain absent even if its flag is set. It has no arbitrary file input, persistence,
  binding mutation, Wald invocation, commit endpoint, job or external network/AI call, and keeps
  DEMO ONLY, SYNTHETIC DATA and NO DATA WILL BE SAVED visible.
- The sole database delta is additive
  `2026_09_09_000014_add_customer_site_administration.php`: 12 production-base migrations become
  13 candidate migrations. Existing customers/sites are backfilled active with UUIDs; dependent
  data and relationships are preserved; no WALD migration/table/trigger is introduced.
- WALD02–05 accepted SHAs and branch lineage remain intact for the future coherent WALD06/import
  release after multi-site architecture is decided. Real binding, upload, analysis, staging,
  commit, multi-site import, RedZebra API and automatic purge are not part of this release.
- Dedicated QA must use the exact frozen branch tip reported by the build task. The 14 locked
  npm development/build-chain advisories remain a release-review item; `npm audit --omit=dev`
  and Composer production dependency audit are clean. No unapproved dependency upgrade is made.

Decision-number reconciliation:

This production-line ledger ended at DEC-057. Parallel Wald/admin documentation used later
numbers through DEC-060 outside this branch. DEC-061 follows the highest inspected current
number and supersedes the earlier next-release inclusion choice without importing excluded Wald
runtime or rewriting either ledger's history.

Reason:

The sidebar and administration provide independently valuable, bounded functionality. Shipping
five unused Wald migrations before the real multi-site import workflow is resolved would enlarge
production database and runtime scope without corresponding production capability.
