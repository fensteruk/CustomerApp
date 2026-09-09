# Fenster Customer Portal Decisions

Current architecture additions: DEC-039 — standalone CustomerApp Wald adoption; DEC-040 —
reconciled source dictionary, completion, products, site identity and export scope; DEC-042 —
approved immutable CUSTOMER-WALD02 source baseline and bounded adoption rules; DEC-043–047 —
accepted corrected phase baselines and bounded phase authority; DEC-048 — final WALD05 I01–I10
governance approval, pending a separate implementation instruction.
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

---

## DEC-043

Date:
8 September 2026

Decision:
The corrected CUSTOMER-WALD02 QA commit
`4aa5ffb5a00527662ddfe66673edbfb18af9f0db` is accepted as the immutable input baseline
for CUSTOMER-WALD03. The original `9980354d28bfe1ca7986e10a529ab073d95d0b91` candidate
is superseded as an output baseline because dedicated QA found and corrected five reader
defects.

Rules:

- The accepted reader identity is `wald-0.2.1`; the XLSX and CSV adapters are version `2`.
- The malformed-XLSX, CSV memory-exhaustion and empty-XML corrections and their regressions
  are part of the frozen core contract and must not be lost in later work.
- `App\Wald` remains generic. CustomerApp business meaning must be implemented by composition
  in a separate semantic-adapter boundary, not added to the Wald core.
- CUSTOMER-WALD03 is scoped as a pure, versioned CustomerApp business dictionary and semantic
  adapter. Scoping does not authorise implementation, persistence, import integration, Portal
  workflow changes, dependency reconciliation, `main` or deployment.
- The eight inherited Composer advisories and separate remediation commit
  `5e7df0862648fd9c2ac964b31a13ad17df84fd12` remain a later combined-release concern.

Reason:
Independent QA established the portable core's safety and neutrality while exposing defects
that make the original candidate unsuitable as the next immutable baseline. Freezing the
corrected SHA prevents later dictionary work from regressing those protections or contaminating
generic inference with CustomerApp business truth.

---

## DEC-044

Date:
8 September 2026

Decision:
The user explicitly approved CUSTOMER-WALD03 implementation from exactly
`4aa5ffb5a00527662ddfe66673edbfb18af9f0db`, using a new non-deploying feature branch.
This supersedes only DEC-043's scope-only/implementation-permission status, not its frozen
core, generic/domain boundary or release restrictions.

Rules:

- Implement pure CustomerApp dictionary/result contracts and an evidence-composing adapter
  outside `App\Wald`; no core edits, database, SiteApp access, UI, workflow integration or WALD04.
- Dictionary identity is `customerapp.source-dictionary.v1` plus a canonical SHA-256 fingerprint.
  Labels, meanings, mappings and normalization rules are identity-bearing; changing them requires
  a new version/fingerprint and regression review.
- Implementation normalization uses fixed-point non-negative quantities with up to three decimal
  places and a 999,999,999.999 per-value/total limit, consistent with existing decimal(12,3)
  quantity precision. Blank/null is zero only within supplied input. No projection-absence authority.
- Unknown/invalid meanings and unresolved Wald evidence cannot become approved facts. Stronger
  export assertions still require downstream confirmation. Source completion needs known service
  identity; operational dates never become Portal dates.
- Eight inherited Composer advisories stay separate. Remediation `5e7df08` must not be merged here.
- Delivery on a feature branch is not dedicated-QA acceptance, permission for WALD04, release,
  a change to `main` or deployment. Freeze the accepted WALD03 output only after dedicated QA.

Reason:
The accepted WALD02 baseline and approved semantic contract permit a bounded implementation
without moving business truth into inference or silently adopting the parallel import runtime.

---

## DEC-045

Date:
8 September 2026

Decision:
Management accepts corrected CUSTOMER-WALD03 QA snapshot
`a80ce7d14206cf3f3a9343448d406f01ae927b88` on `qa/customer-wald03-2026-09-08`
as the immutable input to CUSTOMER-WALD04. Original implementation candidate
`574f19694595f300620713b0dbd4a7d27036d7d1` is not the accepted output.
This supersedes the pending-management-acceptance status after DEC-044, not its separation
of generic inference, business dictionary and Portal workflow.

Rules:

- Dictionary `customerapp.source-dictionary.v1`, fingerprint
  `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`, is unchanged and frozen.
  Accepted WALD02 core remains `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`, reader `wald-0.2.1`.
- Preserve corrections in executable `f4fda0f069bd5106a125b42615ca212294a9dfad`:
  W3Q-01 P1 exact quantities independent of PHP precision; W3Q-02 P2 confidence-version
  validation; W3Q-03 P2 semantic/result-constructor invariants. Ambiguity, raw evidence and
  semantic neutrality remain mandatory.
- Accepted QA evidence: 470 focused tests / 2,550 assertions; 678 combined Wald / 3,812;
  897 full CustomerApp passes, 15 existing environment-gated skips / 5,000 assertions.
- Authorise CUSTOMER-WALD04 architecture/work-package scoping only, for persisted knowledge,
  profiles and clarification governance. Do not implement runtime, migrations, UI or import commit.
- `documentation/work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md` records proposals
  for ownership, tenancy, abilities, activation, compatibility, revocation, retention and audit.
  Its G01–G09 recommendations are NOT approved permissions or retention policy. Resolve the
  required decisions and separately approve implementation before persistence work begins.
- Keep structural profiles, occurrence-specific semantic confirmation and source-site binding
  lifecycles distinct. No saved answer may override the controlled dictionary or become global
  merely because one user/site approved it. Source-site binding runtime and upload/review/commit
  remain separately scoped WALD05 work.
- Eight inherited advisories and separate remediation
  `5e7df0862648fd9c2ac964b31a13ad17df84fd12` remain a combined-release gate; no merge here.
- Acceptance is not release approval: no main, push, production, SiteApp or deployment action.

Reason:
Dedicated QA fixed one arithmetic and two contract defects. Freezing the corrected snapshot
preserves those protections while the next phase's real knowledge-governance decisions are
made explicitly, without treating historical non-main profile code as approved architecture.

---

## DEC-046

Date:
8 September 2026

Decision:
Management explicitly approves CUSTOMER-WALD04 implementation from exactly accepted
WALD03 a80ce7d14206cf3f3a9343448d406f01ae927b88. This supersedes DEC-045's scope-only
restriction and unapproved G01–G09 status, not the frozen core/dictionary or release boundary.

Rules:

- G01: active non-preview Office Staff alone may answer, save, activate, reuse, revoke and
  view private knowledge/audit. Server-side current authority and explicit scope are mandatory.
- G02: answer, save draft and activation are distinct audited commands. Same Office actor may
  perform all three; no four-eyes rule and no accidental learning.
- G03: organisation + existing site + source namespace + workbook family, no learned global
  or organisation-wide scope. Null-organisation Office is valid but knowledge is never tenantless.
- G04: retention metadata uses workbook terminal +30 days, bulky observations +7 days,
  preview validity 24 hours and payload cleanup 7 days. Holds override eligibility.
- G05: reapproval required after 12 months; stale eligibility never deletes history.
  Retired/revoked clarification/profile history retained 24 months, extended for dependencies/holds.
- G06: any currently authorised Office actor may revoke with mandatory reason; future reuse
  stops and stale receipts fail. Immutable historical decisions remain.
- G07: Office-controlled source-site bindings have a separate lifecycle; runtime stays WALD05.
- G08: structural reuse is allowed; semantic corrections stay occurrence-specific. CC! is
  never a learned alias; ZZ9 cannot become dictionary truth. No new business definitions.
- G09: implement hold state/provenance, audited retention and eventual disposal eligibility.
  Management has not nominated the Fenster data owner/role. This blocks unattended production
  disposal, not WALD04 persistence. No deletion scheduler or disposal enablement is authorised.
- Preserve the accepted dictionary identity/fingerprint and WALD02/WALD03 corrections. Use
  additive schema, immutable versions, current evidence, scoped policies, atomic writes and
  disposable MySQL 8.4 migration/concurrency evidence before declaring ready for dedicated QA.
- No WALD05, upload/import UI/commit, source binding runtime, production, deployment, main,
  push or Composer remediation merge. Final-import audit retention remains WALD05.

Reason:
Explicit governance allows safe local knowledge implementation without global learning,
dictionary invention or premature import/disposal authority.

---

## DEC-047

Date:
8 September 2026

Decision:
Management accepts corrected CUSTOMER-WALD04 dedicated-QA output
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8` on
`qa/customer-wald04-2026-09-08` as the immutable input to CUSTOMER-WALD05. The original
implementation candidate `2c7d0154e51a35b165c7e93f7dd256e2cf0f030f` is not the accepted
output. This supersedes the pending-management-acceptance status after DEC-046, not the
frozen Wald/dictionary boundary, WALD04 governance or release restrictions.

Rules:

- Corrected executable/test revision `9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5` is part of
  the accepted baseline. Preserve W4Q-01 binary MySQL evidence guards, W4Q-02 bounded
  profile/provenance/retention queries and W4Q-03/04 deterministic test-harness corrections.
- Accepted QA evidence is 198 MySQL tests / 862 assertions, including 140 real race groups /
  280 independent workers; 187 focused SQLite passes / 11 MySQL skips / 406 assertions;
  865 combined Wald passes / 11 skips / 4,218 assertions; and 1,084 full CustomerApp passes /
  26 environment skips / 5,406 assertions.
- Generic Wald tree `30d1fc65e575242004eb335ad46a4d8ec920127a`, semantic tree
  `9cc8df4bc50a888ec49e6a93dddaba180a2d0d01`, dictionary
  `customerapp.source-dictionary.v1` and fingerprint
  `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`
  remain frozen. WALD05 must compose these contracts rather than redefine them.
- Authorise CUSTOMER-WALD05 architecture/work-package scoping only. Scoping must cover
  CustomerApp-owned private intake, queued/resumable analysis, clarification, neutral staging,
  explicit review, source/site/revision/coverage controls and a guarded Portal commit boundary.
  It must reconcile the existing projection importer and non-main manual import evidence.
- The WALD05 work package's proposed permissions, source revision rules, source-site binding
  lifecycle, duplicate/multi-row Call No. treatment, commit unit/recovery and final-audit
  retention are not approved by this acceptance. Record the choices and safe blocked states;
  separately approve them before implementation.
- The current `SourceProjectionImportService`, `SourceCallTypeMapper` and source schema are
  retained evidence, not a ready Wald commit path. WALD05 must not route reviewed data into
  their outdated mappings, source-wide absence handling, omitted-product zeroing or per-record
  transactions without an approved corrective integration contract and regressions.
- Non-main manual import/profile/binding/UI work ending at `1e8c22b` remains reference/reuse
  material only. Do not merge, cherry-pick, migrate its profile data or adopt automatic learning.
- No WALD05 runtime, migration, route, UI, queue, storage, binding, staging, import commit,
  dictionary change, dependency remediation, `main`, push, production, SiteApp or deployment
  action is authorised by this acceptance/scope task.
- Eight inherited Composer advisories and separate remediation
  `5e7df0862648fd9c2ac964b31a13ad17df84fd12` remain a combined-release concern.

Reason:
Dedicated QA established the WALD04 knowledge layer after correcting a MySQL immutability
bypass and query-growth defect. Freezing the documentation-inclusive corrected output gives
WALD05 a stable input while requiring the much broader import, source identity, commit and
operational decisions to be made explicitly before application code is changed.

---

## DEC-048

Date:
8 September 2026

Decision:
Management explicitly approves CUSTOMER-WALD05 governance decisions I01–I10. This supersedes
DEC-047's unapproved-governance state and the unresolved I03/I04 conclusions in the historical
WALD05 decision-resolution report. It does not supersede the accepted WALD04 baseline, frozen
generic/dictionary/knowledge boundaries or release restrictions, and it is not an implementation
instruction.

Rules:

- I01: active non-preview Fenster Office Staff alone may upload, interpret, answer one-time
  clarification, bind, review, commit, audit and retry. Each is separately authorised/audited;
  the same authorised actor may perform all. External Site Users receive no import ability.
- I02: Office-controlled source-site bindings use `DRAFT → ACTIVE → SUPERSEDED/REVOKED`, one
  active binding per exact identity, explicit activation, reasoned replacement/revocation and
  immutable version/use history. Prefer permanent RedZebra/SiteApp Site ID; exact Site Name is
  transitional only; never fuzzy-match.
- I03: temporary V1 source order is declared Export Date plus `MORNING`/`AFTERNOON`; later date
  wins and afternoon follows morning on the same date. Capture authenticated uploader account
  ID/name automatically and require: “I confirm this is the latest RedZebra export available for
  this slot.” This is staff-declared provenance, not a RedZebra revision. One successful commit
  per namespace/family/date/slot; exact canonical replay is idempotent; differing content in the
  same slot requires an audited correction/replacement successor; older slots cannot overwrite
  newer commits; upload time has no freshness authority.
- I04: one Call No. identifies one individual visit/call-off. Each revisit, including CM1/CM2,
  receives a new Call No. Any within-workbook duplicate blocks even if identical. A later ordered
  occurrence is the same visit: unchanged canonical content has no effect; changed content is an
  ordered update/correction.
- I05: only `PARTIAL_FILTERED_EXPORT` is committable in V1. Absence means no change. Site/global
  complete modes are non-committable and cannot be inferred from contents.
- I06: unresolved required site/plot/Call No./service, Wald ambiguity, invalid mapping, duplicate,
  ordering conflict or stale dependency blocks review/commit. Ignored fields may remain. No
  partial-row commit.
- I07: one reviewed bounded run commits atomically; any failure rolls the whole transaction back.
  Smaller units, if required, are explicit before approval; no hidden chunking/per-row commit.
- I08: retain minimal committed import audit metadata/provenance for six years as a company V1
  operational policy, not a legal claim. Uploaded workbook retention remains separate.
- I09: analysis/work state is durable and queue-agnostic; final commit is synchronous and
  transactional; source files use generated private CustomerApp storage keys/paths. Persistent
  production workers remain a pilot/release gate.
- I10: reimplement neutral staging/review/controlled commit under the accepted semantics; preserve
  safe Portal relationships/history additively and reuse safe test/invariant intent only. Reject
  obsolete mappings, source-wide absence, omitted-column zero and per-record commit. Do not merge
  the non-main importer wholesale or retire old paths without later parity/cutover approval.
- Product presence is explicit: absent column is unrepresented/preserves existing; present blank
  keeps raw evidence and follows the supplied-record semantic contract; explicit zero and valid
  positive fixed-point values are exact; invalid/unknown values block. Absence never implies zero.
- Source completion requires resolved Call No./site/plot/service and valid current review/commit;
  it invents no date and preserves every customer-owned date, proposal, amendment, response,
  actor and history record. Operational dates never become Portal dates.
- Preview validity is 24 hours and is invalidated by any relevant artifact/content, declared
  order, component/dictionary/executable, knowledge/profile, binding, scope, projection or
  authority change. Fail stale; do not regenerate during commit.
- Failed atomic work leaves no partial projection effect. Resolve uncertain outcomes by durable
  receipt; exact successful replay returns the existing result; corrections use audited ordered
  successors rather than routine SQL repair.
- I11 pilot/cutover remains deferred to WALD06. The unnamed G09 unattended-disposal owner blocks
  only automatic production deletion. Persistent worker rollout, security/release reconciliation,
  old-path retirement and deployment remain independently gated.

Reason:
Management has supplied the temporary RedZebra export-ordering rule and confirmed the Call No.
grain, allowing the remaining WALD05 governance package to be approved coherently. The resulting
contract permits a separately instructed implementation while keeping source meaning, tenancy,
Portal workflow and production operations outside Wald inference.

---

## DEC-049

Date:
8 September 2026

Decision:
Management explicitly authorises CUSTOMER-WALD05 source binding, reviewed import and atomic
commit implementation on `feature/customer-wald05-import-review-integration`. The working base
is governance-inclusive `877bd3ff666873a0703c3b7671015ec4cfd2ee52`, after verifying ancestry
from accepted WALD04 `0e83eb2896e7c5144bc38c1be9713f3d205d93b8` and an exclusively
documentation delta. These entry checks passed. This supersedes DEC-048's pending implementation
instruction and the earlier direct-from-WALD04 branch requirement, while preserving I01–I10,
the accepted executable/dictionary boundaries and all production restrictions.

Rules:

- Implement the approved source-site binding, private intake, authenticated uploader,
  STAFF_DECLARED Export Date/Slot ordering, distinct visit identities, neutral staging,
  immutable review, current-state verification, atomic commit, receipts and audit contracts.
- Preserve accepted Wald/semantic/knowledge corrections and use additive forward migrations.
  No old importer or non-main profile/UI implementation is approved for wholesale merge.
- Require focused/full regression, synthetic failure/role/staleness tests, disposable MySQL 8.4
  schema and real concurrency evidence before declaring ready for dedicated QA.
- No main, production, deployment, WALD06, SiteApp, GitHub Actions, push or Composer-remediation
  merge is authorised.

Implementation discovery, not a newly approved business rule:

Entry review found that the current Portal schema has one service state per plot/service and
one quantity per plot/product, whereas approved I04 allows several distinct visits for the same
plot/service. The frozen dictionary rolls up one supplied record and does not define the
cross-visit projection. W5-P01 (completion/request association across visits) and W5-P02 (product
quantity aggregation across visits) require business clarification. Concrete cases and source
evidence are recorded in
`documentation/wald/customer-wald05-import-review-integration-2026-09-08.md`.

I01–I10 remain approved. No sum/latest/any/all visit policy is inferred from code or export order.
Affected projection schema/commit implementation pauses until the missing rule is supplied.
The implementation permission above remains valid and need not be requested again.

Reason:
The explicit implementation instruction removes the final permission gate and selects a base
containing the approved documentation. Entry review must still distinguish settled visit
identity from the missing business rule for projecting multiple visits, so the implementation
does not invent completion, quantity or BF lead-time truth.

---

## DEC-050

Date:
8 September 2026

Decision:
The user explicitly confirms workbook-specific interpretation for the inspected local artifact
`Copy of siteapp1.xlsx`, SHA-256
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`:
PC1 and CM1 in Call Type are full call-off examples; every literal `CC!` in Call Type is a
confirmed typo for CC1; CM2 records in this workbook are to be ignored.

Rules:

- Apply this explicit human confirmation to all Call Type occurrences of `CC!` in this exact
  workbook. Retain raw `CC!` privately alongside canonical CC1 and this approval's provenance.
  It is not an inferred correction and needs no repeated business confirmation for each cell.
- Exclude CM2 records from this workbook's proposed customer projection. Record intentional
  exclusion in private review evidence; do not translate CM2 into CM1 or treat excluded records
  as deletion, reversal or zero-quantity instructions.
- PC1 maps to Windows, CC1 to Cavity Closers and CM1 to CML under the existing dictionary.
- Do not rewrite the original workbook or change the frozen dictionary/fingerprint. CC! does
  not become a global learned alias, and CM2 remains a valid dictionary call type outside this
  workbook-specific exclusion. Changed source bytes require a new matching approval context.
- This narrows the supplied example: the earlier W5-P01 CM1-versus-CM2 case does not apply to
  this selected workbook projection. It does not establish an ordering between distinct Call
  Nos., a cross-visit quantity rule, or a universal PC1/CC1/CM1-only source filter.

Reason:
The user's inspection supplies the required human authority for the typo correction and a
bounded exclusion. Keeping that authority attached to the artifact preserves original evidence
and prevents the clarification from silently redefining other imports.

---

## DEC-051

Date:
8 September 2026

Decision:
The latest user continuation confirms that WALD05 implementation remains authorised and that
actual workbook evidence, not hypothetical cross-visit cases, controls the immediate data gate.
After the row audit, the user explicitly instructs exclusion of the `Nick TEST` record from this
workbook's proposed import.

Rules:

- Retain DEC-050 unchanged: its CC! correction and CM2 exclusion remain workbook-specific.
- Additionally exclude Sheet1 row 32, Call No. 5181, exact Site Name `Nick TEST`, from the workbook
  with SHA-256 `ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.
  Keep the original source evidence. Do not edit the workbook, delete Portal data or produce any
  completion, reversal or product effect from this excluded record.
- This is an explicit row-specific selection, not a general test-site detector or future-workbook
  alias. Changed bytes require separately matching reviewed provenance.
- Continue the already authorised implementation after resolving actual blockers; do not request
  another blanket WALD05 approval or revive W5-P01/P02 merely from synthetic possibilities.

Read-only implementation discovery, not a new business decision:

The audit now selects 45 records and excludes two (DEC-050 CM2 and this DEC-051 row). It found no
duplicate Call No., conflicting included site/plot, invalid completion or invalid product value.
The frozen XLSX reader nevertheless rejects workbook metadata with `invalid_xml`: an extension
namespace's workbookPr reaches the core workbookPr parent-path check (W5-T01). The current
continuation report distinguishes that technical compatibility failure from the cleared business
data gate. No generic-core correction or runtime implementation is claimed by this decision.

Reason:
The user has explicitly narrowed the reviewed source selection. Keeping the exact artifact and
row identity in its provenance prevents an audit exclusion becoming a global business rule.

---

## DEC-052

Date:
8 September 2026

Decision:
The user explicitly approves the narrowly scoped W5-T01 XLSX reader compatibility correction,
synthetic regression coverage and a new recorded reader identity, followed by resumption of
the already authorised WALD05 implementation.

Rules:

- This is a bounded exception to the accepted generic-reader freeze, not permission for a
  new parser, weakened XML safety or broad core redesign. Preserve physical source lineage,
  resource limits, unsafe-XML refusal and no formula execution.
- Distinguish the inert Excel extension workbookPr from core workbook properties by namespace
  and exact parent lineage. The extension must not establish a date system or source data.
- Version the correction and stale incompatible knowledge/profile pins; do not silently rewrite
  historical receipts. Keep the accepted WALD04/input SHAs as historical provenance.
- DEC-050/051 remain exact-artifact interpretations. No dictionary meaning/fingerprint change,
  global CC! alias, global CM2 exclusion or inferred test-site filter is authorised.
- Resume WALD05 after the correction checks. No further blanket implementation approval is
  required. No main, push, SiteApp, production, deployment, WALD06 or dependency remediation.

Reason:
The actual workbook exposed a reproducible metadata compatibility defect. Explicit permission
allows a narrow versioned repair while retaining the accepted safety boundaries and history.

---

## DEC-053

Date:
9 September 2026

Decision:
The latest explicit user instruction is to complete the CUSTOMER-WALD05 backend/application
layer for dedicated QA on `feature/customer-wald05-import-review-integration`, continuing from
reader correction `e9e1c3a2a3ff79cfe5f097bea143f51195becd55` and binding foundation
`d1b5de13b260dde77867fdb8ac73296e49994c14`. This supersedes the earlier foundation-only delivery
checkpoint, not the accepted business dictionary or I01–I10 governance.

Rules:

- Finish private upload, durable analysis/run lifecycle, immutable staging, non-mutating review,
  dependency-pinned preview, source projection adapter, one-run atomic commit, ordering,
  idempotency, explicit replacement, immutable observation/receipt history and retention metadata.
- Do not build the full Office UI in this task. The application services are the tested backend
  entry points; no public endpoint, queue worker, production purge or cutover is authorised.
- Preserve DEC-050/051 exact-artifact selection, the approved dictionary, explicit knowledge
  activation and source bindings. No global CC! alias, CM2 exclusion or cross-visit business rule.
- Unsupported data/layouts must refuse the complete bounded unit, never silently split or commit
  a subset. Document implemented limits and unverified real-workbook coverage honestly.
- Require focused, combined and full regression, failure injection, disposable MySQL 8.4
  migration/upgrade and repeated real concurrency evidence before backend QA readiness.
- Preserve the old importer until independently approved WALD06 cutover. Record parity gaps and
  retirement criteria; do not merge the non-main manual UI/profile implementation wholesale.
- Keep eight inherited Composer advisories separate; do not merge remediation
  `5e7df0862648fd9c2ac964b31a13ad17df84fd12` in this task. No main, push, deployment,
  production, SiteApp, Sprint 3F or WALD06 action.

Implementation evidence, not a new management approval:

The backend candidate is described in
`documentation/wald/customer-wald05-backend-completion-2026-09-09.md`. Its supported atomic unit
is one explicitly bound Portal organisation/site, one visible unmerged table/sheet and at most
500 nonempty rows. Mixed-site workbooks and competing distinct visits for a single plot refuse
review/commit; no new sum/latest/any/all rule is inferred. This is not evidence of importing the
actual twelve-site sample. W5-T02 adds a separate bounded transient evidence digest in the
CustomerApp knowledge adapter; persistent payload ceilings, generic core, matching policy and
dictionary remain unchanged. Dedicated QA must review this integration change and these bounds.

Reason:
The user explicitly requests completion rather than another foundation checkpoint or blanket
approval question. Backend QA readiness is distinct from full Office UI, pilot, acceptance,
release and production readiness.
