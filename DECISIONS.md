# Fenster Customer Portal Decisions

Current architecture additions: DEC-039 — standalone CustomerApp Wald adoption; DEC-040 —
reconciled source dictionary, completion, products, site identity and export scope; DEC-042 —
approved immutable CUSTOMER-WALD02 source baseline and bounded adoption rules.
Earlier sprint headers and decisions below are retained history; DEC-035 and subsequent
decisions supersede conflicting assumptions.

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

Date:
3 September 2026

Decision:
CustomerApp will own a standalone Wald Import Engine, initially a controlled fork of the
compatible SiteApp WALD01–07 generic engine and tests. CustomerApp rollout must not depend
on SiteApp runtime availability or a SiteApp-hosted import/projection API.

Rules:

- Each application owns its deployment, source storage, queue, database, profiles,
  clarification/answer memory, dictionaries, review, commit and audit history.
- No cross-application database/filesystem/queue dependency or automatic knowledge/code
  synchronisation. Generic backports require separate approval and testing.
- Preserve deterministic rules, evidence, uncertainty/clarification, conservative
  confidence and source lineage. No external AI, LLM, embeddings or interpretation API.
- Generic engine parity is the aim; do not transplant SiteApp operational models, policies,
  workflow/Trade references, Import Studio staging/commit or Filament administration.
- Portal business definitions and data ownership stay downstream of inference. Preserve
  requested/agreed dates, negotiations and history; apply confirmed source-completion
  precedence only through the existing Portal domain boundary.
- Require private source registration, neutral staging, authorised review/preview and
  explicit controlled commit. Analysis or profile recognition is not business approval.
- Freeze an approved reproducible upstream source baseline before copying: the inspected
  SiteApp Wald work is uncommitted and is not contained in its HEAD alone.
- No source code is copied by this decision/documentation task. Implementation is divided
  into CUSTOMER-WALD02–06 under the architecture contract.

Historical unresolved position at 3 September 2026:

This decision originally left call-type/completion details, product roll-ups,
`complete = Yes`, BF and export coverage unresolved or dependent on earlier assumptions.
DEC-040 now supersedes that semantic/coverage position. Source ownership/revision,
import/knowledge permissions, retention and reviewed commit atomicity remain gates. The
proposed Office-only import model and whole-reviewed-set atomic pilot commit are not
formally confirmed by DEC-039 or DEC-040.

References:
`brief.md` section 18;
`documentation/work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md`;
`documentation/wald-divergence-register.md`.

Reason:
CustomerApp must be usable before SiteApp is ready for wider use. A controlled generic
fork preserves proven design/testing investment without making the Portal an operational
system or creating a runtime release dependency.

---

## DEC-040

Date:
4 September 2026

Decision:
Management-confirmed source semantics and export rules supersede the conflicting assumptions
recorded in DEC-039 and earlier source documentation.

Rules:

- Valid source call types are PC1 = Plot Install, CC1 = Cavity Closer 1, CM1 = Revisit 1,
  CM2 = Revisit 2 and CML = CML Call Off. PC1 maps to Windows, CC1 maps to Cavity Closers,
  and CM1/CM2/CML map to CML. CM1 and CM2 are CML revisits, not new customer services.
- Literal `CC!` is an invalid/likely typo value. Preserve its evidence, optionally suggest
  CC1, require confirmation and never silently normalise it. No Snagging source call code
  is invented.
- `complete = Yes` means the particular source call-off part is done. Parse sensible Yes/No
  variants case-insensitively and never invent a completion date. Older speculative Job
  Stage/Completed Date/CC08/CA02/CA03/SN05/CML4 rules are not required or authoritative
  unless separately confirmed later.
- Total Windows = VS + TT + BAY + ALI + AOV + FI. Total Doors = PSU + PSG + CDF + CDU +
  CDG + PSP + BF. The descriptions and classification in `brief.md` §17.6 are controlled
  business definitions.
- CAS, FLU, PFD, GLS, WP and MISC are excluded/redundant from the final customer product
  model. Raw evidence may be retained privately for audit fidelity.
- BF means Bifold and belongs to Doors. Exact positive BF remains individually identifiable
  internally and changes the normal earliest request from four to five weeks; an aggregate
  or similar text does not prove BF.
- Items Ordered Status is ignored. Site Value is ignored/excluded. Plot To Be Installed is
  the operational source date Fenster arrives to install PC1 and is never a customer
  Requested Date, Date Agreed or alternative proposal date.
- A future permanent source Site ID/reference is the durable site identity. Exact Site Name
  may be used for transitional explicit mapping only and never as the permanent identity or
  as authority to create/fuzzy-match a Portal site.
- Every SiteApp workbook defaults to `PARTIAL_FILTERED_EXPORT` because it contains whatever
  the exporter filtered. Absence never proves deletion, including within a represented
  site. `SITE_COMPLETE_SNAPSHOT` and `GLOBAL_COMPLETE_SNAPSHOT` require explicit
  confirmation; shape, filename, row count and represented sites cannot upgrade scope.
- Wald infers structure; the controlled business dictionary supplies meaning. Similarity
  may produce a suggestion or question but never a silent business mapping.
- The committed non-main feature line ending at `feature/manual-source-import-ui`
  (`1e8c22b`) is recognised architecture evidence. Generic interpretation mechanics are
  reuse candidates; Portal access/projection/scope safeguards remain downstream; overlapping
  direct interpretation/profile flow is a supersession candidate; fixtures and workbook
  observations are test/corpus material. It is not automatically approved for `main`.

Remaining decisions:

No business question remains from this reconciled semantic set. Existing governance and
delivery gates still require the approved SiteApp Wald source baseline, source owner and
revision contract, Portal import/knowledge permissions, retention and reviewed commit
atomicity before the affected production flow is enabled.

Reason:
These rules were confirmed directly by management after CUSTOMER-WALD01. Recording them as
controlled business truth prevents structure inference, historical assumptions or a
non-main implementation from silently defining Portal semantics.

---

## DEC-041

Date:
4 September 2026

Decision:
The CustomerApp governing documentation is reset to one concise current product contract
and one concise agent-governance contract. Historical decisions, sprint reports and release
records remain intact as evidence but are not permitted to override newer explicit decisions
or verified current state.

Rules:

- `brief.md` is the current product/scope contract and must be maintained by replacement or
  deliberate revision, not by appending a second truth beneath stale content.
- `AGENTS.md` governs safe work, source hierarchy, production protection, verification and
  reporting without duplicating the full product specification.
- Later numbered decisions supersede contradictory earlier decisions. The ledger remains
  append-only so the change history stays auditable.
- Reports must distinguish explicitly evidenced production, current `main`, feature-branch
  work and planned work. A branch, roadmap entry or push is not proof of a successful
  production deployment.
- As at this reset, Sprint 3E commit
  `9111d76ff05d702d68afd884ee8e42bc8e50c8e3` / Forge deployment `76326195` is the last
  explicitly evidenced successful production release. `origin/main` is
  `0873bac79edf578e9f4a9417e3cafae34e8aa925`; its deployment state requires Forge
  verification and must not be inferred.
- The current source semantics live in
  `documentation/siteapp-import-data-dictionary.md`; the dated contradiction register
  records stale, historical, open-decision and verification items without erasing evidence.
- A fresh standalone-Wald chat must use
  `documentation/work-packages/WP-CUSTOMER-WALD-CHAT-BOOTSTRAP.md` and may not begin
  CUSTOMER-WALD02 without the immutable approved upstream baseline and a scoped work package.

Reason:
Layered specifications had left superseded roles, services, lifecycle, batch and source rules
visible beside current decisions. A single current contract plus an explicit historical
register reduces implementation ambiguity while preserving the audit trail.

---

## DEC-042

Date:
4 September 2026

Decision:
The 162-file SiteApp Wald source manifest dated 4 September 2026, with content digest
`76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`, is the approved
immutable source baseline for the bounded CUSTOMER-WALD02 portable-core and synthetic-corpus
package.

Rules:

- CUSTOMER-WALD02 may adopt only the generic workbook observation, structural profiling and
  reasoning files identified by its scoped work package, plus safe synthetic fixtures/tests.
- CustomerApp owns the fork under `App\Wald`. Namespace/path relocation is permitted when
  recorded and verified as behaviourally identical; it does not create a new compatibility
  version by itself.
- SiteApp semantics, operational models, persistence, Import Studio, policies, routes, jobs,
  UI, source/customer workbooks and production data remain outside the approved copy boundary.
- The package adds no Portal semantics, staging, commit path, database, queue, external AI,
  package dependency or deployment. Later phases require separate work packages and approval.
- The branch implementation and tests are delivery evidence, not release or production proof.
  Dedicated QA is required before the resulting CustomerApp SHA becomes an accepted input to
  CUSTOMER-WALD03.

Reason:
The checksum-frozen manifest resolves CUSTOMER-WALD02's moving-source blocker while preserving
the controlling principle that Wald may infer structure but cannot define business meaning.
