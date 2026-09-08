# Fenster Customer Portal — Product Brief

**Version:** Current product contract

**Last updated:** 8 September 2026 (WALD05 actual workbook audit; reader compatibility failure)

**Scope:** CustomerApp Version 1 and approved delivery direction

This document replaces the previously layered brief. It states the current approved product
truth without treating feature-branch work as released. Historical decisions and delivery
evidence remain in `DECISIONS.md`, sprint reports and release records.

## 1. Executive Summary

The Fenster Customer Portal is a separate customer-facing application for authorised
customers and Fenster Office Staff. It shows authorised developments and plots, accepts
service date requests and amendments, supports date agreement, and presents customer-safe
progress and history.

SiteApp remains Fenster's internal operational system. CustomerApp must not reproduce its
operational workflow, trade management, planning, verification or administration.

Initial source data will be supplied through CustomerApp's own private, controlled spreadsheet
import and review path. A separately approved read-only SiteApp integration may follow.
CustomerApp must operate without SiteApp's API, database, filesystem, queues or runtime.

## 2. Product Objectives

CustomerApp should let an authorised user answer:

- Which plots and services remain outstanding?
- Which date has the customer requested?
- Has Fenster accepted that date or proposed an alternative?
- What date is currently agreed?
- What changed, who changed it and when?
- What customer action is required next?

It should reduce telephone and verbal call-off coordination, give site teams clear
plot/service information, structure customer requests, provide auditable date agreement and
make Fenster Office review clear and consistent.

The experience must be secure, mobile-first, accessible, auditable and clear to non-technical
users.

## 3. Product and System Boundary

CustomerApp owns:

- Portal authentication, accounts, organisations, roles and site access;
- customer-facing plot and service projections;
- customer date requests, negotiation, agreed dates and amendments;
- Portal history, notifications and presentation statuses;
- its private source upload, analysis, clarification, review and controlled commit process.

Source spreadsheets and, later, SiteApp own:

- stable source identifiers;
- source product quantities;
- operational completion evidence;
- internal operational data that is not copied into the customer model.

The supported direction is read-only:

```text
Source spreadsheet / future SiteApp feed → CustomerApp
```

CustomerApp does not write agreed dates or workflow state back to a spreadsheet or SiteApp.

## 4. Users, Roles and Access

CustomerApp has four distinct Portal roles:

| Role | Version 1 access |
|---|---|
| Site Manager | Select an assigned site; view all Portal call-offs for it; submit and respond within the site-user workflow. |
| Assistant Site Manager | Same Version 1 capability as Site Manager, retained as a distinct role. |
| Finishing Foreman | Same Version 1 capability as Site Manager, retained as a distinct role. |
| Fenster Office Staff | Globally review and act on Portal requests. This role may have no customer organisation. |

The three external roles require an active account, a customer organisation and an assigned
site. They may see all Portal call-offs for the active assigned site, not only requests they
personally submitted.

Fenster Office Staff global access is granted by the valid, active Office Staff role—not by a
null organisation. Office Staff are not assigned to individual customer sites in the current
approved model.

Public registration is disabled. Password reset and secure framework authentication apply.
Customer/account/site-assignment administration by authorised Fenster staff is a target
capability; the current repository does not yet contain a completed Office account-management
interface.

Site users currently select an assigned site from a menu. QR-assisted site selection is a
future option; a QR code can identify a site but can never authenticate or authorise a user.

A role-preview screen may exist only in local/test development. It must not be enabled in
production, grant real permissions or bypass authentication and server-side authorisation.

## 5. Customer Services

The four customer-facing services, in display order, are:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

They are independent services, not stages or dependencies. A customer may request any eligible
service without completing an earlier one.

The exact customer-facing expansion of **CML** remains unconfirmed. Use `CML` until management
approves the wording. Do not invent a source code for Snagging.

## 6. Dashboard and Plot Projection

Site users land on the dashboard for their active assigned site. Fenster Office Staff land on
Review Requests.

The site dashboard is plot-centred and should support authorised pagination, search, service
and status filters. Completed plots remain available but are hidden by default. Filters are
browsing aids and never expand server-side access.

Each plot presents four service states using accessible text in addition to colour or icons:

- **Nothing Called Off**
- **Called Off — Awaiting Date**
- **Date Agreed** (with the agreed date)
- **On Hold — Date Change Requested** (Sprint 3F amendment state)
- **Completed** (with a date only when the source actually provides one)

Overall plot presentation is derived from service state:

| Overall status | Meaning |
|---|---|
| Nothing Called Off | No service has been called off. |
| Call-Offs In Progress | One or more called-off services remain unresolved, including an amendment on hold. |
| Dates Agreed | At least one service is Date Agreed or Completed and no called-off service remains unresolved. |
| Partially Completed | At least one, but not all four, services are Completed; this takes presentation precedence. |
| Fully Completed | All four services are Completed. |

There is no separate overall `Amendment In Progress` label in Version 1.

Plot Details shows overall status, the four services, customer-safe service history and only
non-zero customer product totals. Operational fields and raw source evidence remain private.

## 7. Eligibility, Batches and Dates

Eligibility is evaluated per plot and service from authorised source projection plus Portal
rules. Completed, already-active, ineligible or unresolved combinations remain visible but
disabled with a clear reason; they are not silently hidden.

- A plot/service can have no more than one active call-off.
- One batch represents one user submission action for one active site.
- A batch may contain one or many plot/service requests.
- Each selected service may have its own requested date.
- Each individual request owns its plot, service, requested date, state, decisions and history.
- Later batch actions must not overwrite individual values or decisions after requests diverge.
- Supported bulk undo/restoration is atomic: all eligible records change or none do.

The confirmed normal minimum lead time is three weeks for standard products and four weeks
when the exact plot has a positive BF/Bifold quantity. The customer-facing normal date adds a
one-week buffer, making the earliest normal request four weeks or five weeks respectively.

Normal dates are weekdays, no more than six months ahead. UK bank-holiday exclusion is the
approved target, but the provider/dataset and operational owner remain unresolved; the last
explicitly evidenced deployed behaviour is weekday-only. Do not claim holiday exclusion is
live until it is implemented and verified.

`Request Earlier Date` is the explicit exception route. It requires a reason, is prominently
flagged to Fenster and retained in history. Office acceptance inside lead time requires an
explicit acknowledgement of the exception.

## 8. Date-Agreement Workflow

The current approved customer workflow is:

```text
Site User submits requested date
  → Awaiting Fenster
  → Office accepts requested date → Date Agreed
  → Office proposes alternative → Awaiting Site User
       → authorised Site User accepts → Date Agreed
       → authorised Site User rejects with reason → Awaiting Fenster
```

Alternative negotiation may repeat on the same request. Any currently authorised site user
for the active site may respond; the original submitter remains the primary notification
recipient. The actual actor and timestamp are recorded.

Use **Date Agreed**, not **Approved**, as the final customer-facing state. Rejecting an
alternative is not rejection of the call-off and does not create a new request.

Site users may withdraw before Date Agreed, including while awaiting an alternative response.
After Date Agreed, withdrawal, customer trash and customer cancellation are unavailable; a
date change uses the amendment route.

Eligible withdrawal/trash actions offer a five-second quick Undo. Eligible customer-facing
Trash restoration lasts seven days. Expiry removes the item from the customer-facing Trash
view but preserves audit-critical history. Bulk undo and restoration are atomic.

Historical Submitted/Approved/Rejected records may remain for compatibility and audit, but
that earlier direct approval lifecycle is not the current target workflow.

## 9. Amendments

Sprint 3F defines changes after Date Agreed. It is implemented on
`feature/sprint-3f-date-amendments` at `60aa9e2` and is ready for dedicated QA; it is not on
`main`, release-approved or deployed.

A site user requests a new date against the existing agreed request. The existing agreed date
is preserved and the service becomes **On Hold — Date Change Requested**. There is no fixed
cutoff; a request within three working days of the current agreed date is flagged
**Urgent / Late Amendment** as a warning, not a prohibition.

Confirmed amendment reasons are:

- Site Not Ready
- Programme Change
- Access Issue
- Customer Requested Change
- Materials / Availability
- Weather
- Other

`Other` requires an explanation; an explanation is optional for the named reasons. Office may
accept the new date or use the existing alternative-date negotiation. A newly agreed date
becomes current while every prior agreed date remains in history.

Source completion wins over an open amendment, closes the cycle and sends no completion
notification. A later source reversal does not automatically reopen the amendment.

Failure/cancellation handling and any explicit reinstatement of an old agreed date remain
unconfirmed and must not be invented. Fenster-originated changes to an agreed date remain
outside the Portal unless separately approved.

## 10. Source Call Types and Completion

The authoritative detailed mapping is
`documentation/siteapp-import-data-dictionary.md`. Confirmed call types are:

| Source value | Meaning | Customer service |
|---|---|---|
| `PC1` | Plot Install | Windows |
| `CC1` | Cavity Closer 1 | Cavity Closers |
| `CM1` | Revisit 1 | CML |
| `CM2` | Revisit 2 | CML |
| `CML` | CML Call Off | CML |

CM1 and CM2 are CML revisits, not new services. Literal `CC!` is invalid/likely typo evidence:
preserve it, optionally suggest `CC1`, require human confirmation and never silently correct it.

`complete = Yes` means that specific source call-off part is complete. Sensible yes/no casing
may be parsed case-insensitively. Do not invent a completion date when the source has none, and
do not infer completion from Portal requested, alternative, agreed or operational dates.

Source completion has precedence over open negotiation/amendment. Preserve the full Portal
history. A later source reversal updates the current projection and records the reversal; it
does not erase history or automatically reopen the closed process. Completion sends no in-app
or email notification.

Older speculative `Job Stage`, `Completed Date`, `CC08`, `CA02`, `CA03`, `SN05` and `CML4`
rules are not authoritative without a later explicit decision.

## 11. Product Projection

Customer product information is deliberately rolled up:

```text
Total Windows = VS + TT + BAY + ALI + AOV + FI
Total Doors   = PSU + PSG + CDF + CDU + CDG + PSP + BF
```

| Code | Meaning | Roll-up |
|---|---|---|
| VS | Vertical Slider | Windows |
| TT | Tilt and Turn | Windows |
| BAY | Bay Window | Windows |
| ALI | Aluminium Windows | Windows |
| AOV | Automatic Opening Vent Window | Windows |
| FI | Fire Window | Windows |
| PSU | PVC Door Utility | Doors |
| PSG | PVC Door Garage | Doors |
| CDF | Composite Door Front | Doors |
| CDU | Composite Door Utility | Doors |
| CDG | Composite Door Garage | Doors |
| PSP | PVC Sliding Patio | Doors |
| BF | Bifold | Doors |

Exact positive `BF` must also remain separately identifiable internally because it changes
the confirmed lead-time rule. `CAS`, `FLU`, `PFD`, `GLS`, `WP` and `MISC` are excluded from the
final customer product model; their raw values may be retained privately for evidence.

Product presence is explicit. An absent column is unrepresented and preserves existing data.
A present blank/null retains the raw blank and follows the supplied-record semantic contract.
An explicit zero is exact zero for that supplied record/column; a valid positive value is the
exact approved fixed-point quantity. Invalid or unknown values block and are never coerced to
zero. A filtered export cannot zero or delete values outside supplied records.

`Items Ordered Status` and `Site Value` are ignored. `Plot To Be Installed` is operational
arrival-to-install evidence for PC1 and never a customer Requested Date, alternative proposal,
Date Agreed or completion date.

## 12. Site Identity, Export Scope and Refresh

A permanent source Site ID/reference is the required durable identity. Exact Site Name may be
used only for explicit transitional mapping; it must not fuzzy-match or create a Portal site.

Every workbook defaults to `PARTIAL_FILTERED_EXPORT`, because the source may have been filtered
before export. It is the only committable WALD05 V1 scope. Absence never proves deletion or zero
quantity. `SITE_COMPLETE_SNAPSHOT` and `GLOBAL_COMPLETE_SNAPSHOT` are non-committable in V1.

One `Call No.` identifies one individual visit/call-off; every revisit, including CM1/CM2, has a
new Call No. Any within-workbook duplicate blocks, even if identical. The same Call No. in a later
permitted export is the same visit and may change only under the approved source-order contract.

Until RedZebra supplies a native revision/API, active Office Staff declare Export Date plus
`MORNING`/`AFTERNOON`, confirm the latest export for that slot and are identified automatically by
their authenticated account. Date then slot defines order; upload time does not. Exact same-slot
canonical replay is idempotent, different content conflicts and an older slot cannot overwrite a
newer commit. A failed or delayed refresh preserves the last successful customer-safe projection
and its last-updated time; it never silently hides known records.

## 13. Standalone Wald Import Direction

CustomerApp is adopting a controlled fork of compatible generic SiteApp WALD01–07 engine code
and tests. CUSTOMER-WALD02's portable core and safe synthetic corpus are implemented on the
non-deploying `feature/customer-wald02-portable-core` branch. Dedicated local QA passed on
8 September after five reader corrections on `qa/customer-wald02-2026-09-08`; management
acceptance froze corrected SHA `4aa5ffb5a00527662ddfe66673edbfb18af9f0db` as the immutable
WALD03 input (see `documentation/wald/customer-wald02-qa-2026-09-08.md`). They are
not integrated into the import workflow, on `main`, release-approved or deployed.

The path is:

```text
private upload
  → deterministic analysis
  → explicit clarification and reanalysis
  → neutral staging
  → authorised Portal review against current state
  → explicit controlled commit
```

Wald infers structure; the approved CustomerApp dictionary defines business meaning. It must
remain deterministic and explainable, with no external AI/LLM, embeddings or third-party
spreadsheet interpretation.

The non-main line ending at `feature/manual-source-import-ui` (`1e8c22b`) is evidence, not
production truth or the final architecture. Generic interpreter mechanics may be compared for
reuse; Portal projection, tenant access, scope and reconciliation rules remain downstream;
overlapping manual interpretation/profile/UI work is a supersession candidate, not approved
for wholesale merge or deletion.

The CUSTOMER-WALD02 source baseline is the approved 162-file manifest with digest
`76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`; its package is
`documentation/work-packages/WP-CUSTOMER-WALD02-PORTABLE-CORE-CORPUS.md`.
Explicitly approved CUSTOMER-WALD03 is implemented on the non-deploying
`feature/customer-wald03-business-dictionary` branch, outside generic `App\Wald`.
Management accepted corrected QA snapshot `a80ce7d14206cf3f3a9343448d406f01ae927b88`
on `qa/customer-wald03-2026-09-08`, containing executable corrections `f4fda0f`, as the
immutable WALD04 input. Original candidate `574f196` is not the accepted output.
Dictionary `customerapp.source-dictionary.v1`, fingerprint
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`, remains unchanged.
DEC-046 approves WALD04 knowledge/profile implementation under
`documentation/work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md`. Office-only knowledge,
organisation/site/namespace/family containment, separate answer/draft/activation, 12-month review
and 24-month retained history are approved. Dedicated QA passed after corrections on
`qa/customer-wald04-2026-09-08`; management accepted documentation-inclusive SHA
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8` as the immutable WALD05 input, including
executable/test correction `9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`.
DEC-048 approves WALD05 governance decisions I01–I10, including Office-only same-actor import,
immutable exact source-site bindings, temporary manual export ordering, one Call No. per visit,
partial-only commit, complete staging, atomic commit, six-year minimal committed audit and
queue-agnostic analysis. DEC-049 subsequently authorises implementation from governance-inclusive
commit `877bd3ff666873a0703c3b7671015ec4cfd2ee52`. The DEC-050/051 actual workbook audit selects
45 records and excludes CM2 plus the exact Nick TEST record. It found no concrete business-data
ambiguity; earlier hypothetical W5-P01/P02 cases do not block this sample. The frozen reader
instead rejects an extension-namespace workbookPr (W5-T01); a narrowly reviewed, versioned core
compatibility correction is needed before this workbook can traverse accepted Wald analysis.
No global dictionary change, import fallback or runtime integration has been made.
G09's unnamed owner blocks unattended
production disposal only; no deletion scheduling is enabled.

## 14. Notifications, History and Customer Content

Every material action records the actual actor, role, exact time, previous/current values and
customer-safe context. Private Fenster reasons are stored and authorised separately. Source
raw evidence and SiteApp internal audit data are never exposed.

In-app notifications cover meaningful submitted, accepted, proposed, responded-to and amended
events. They identify the plot, service, event and next action and use links that are
re-authorised on access. Read/dismissed state never removes domain history. Completion sends no
notification.

Email/Resend, reminder cadence and push notifications are planned, not currently evidenced as
implemented. The last explicitly evidenced production queue driver is `sync`; a persistent
worker is a separate production-readiness item.

Attachments are planned only for defined customer actions that include a reason/message. They
belong to the related history event, not a general file library. Upload limits, malware
controls and retention require an approved implementation contract before release.

## 15. Current Delivery State

Status is deliberately separated from product intent:

| State | Evidence as at 4 September 2026 |
|---|---|
| Last explicitly evidenced production release | Sprint 3E at `9111d76ff05d702d68afd884ee8e42bc8e50c8e3`; Forge deployment `76326195`; all 11 migrations intact and nothing pending. |
| Production follow-up | Authenticated, non-destructive production smoke test remains outstanding. Production queue was `sync`; two npm advisories were recorded at that release. |
| Current `origin/main` | `0873bac79edf578e9f4a9417e3cafae34e8aa925`, four commits beyond the evidenced Sprint 3E SHA. It contains the Office Staff organisation model correction and Office Date Agreed filter, but repository records inspected for this reset do not prove a successful deployment of that SHA. Verify Forge before describing it as deployed. |
| Sprint 3F | Feature branch `feature/sprint-3f-date-amendments` at `60aa9e2`; decisions integrated; dedicated QA and release approval still required. |
| Manual source import | Non-main evidence line ending at `feature/manual-source-import-ui` (`1e8c22b`); not production and subject to Wald reconciliation. |
| Dependency security | Separate branch `security/composer-advisories-2026-09-03` at `5e7df08`; reconciliation/release status must be verified before claiming remediation. |
| Standalone Wald (8 September update) | Corrected WALD04 snapshot `0e83eb2896e7c5144bc38c1be9713f3d205d93b8` remains the executable baseline. DEC-049 authorises implementation; DEC-050/051 actual selection has no concrete business-data ambiguity. W5-T01 accepted-reader metadata failure needs a versioned compatibility correction. No runtime, main change or deployment. |

Implemented foundation already evidenced in the repository includes authentication, customer
organisations, four Portal roles, site assignments and active site context, server-side
policies/gates, secure routing, development-only role preview, call-off domain/history,
customer and Office dashboards, date negotiation, Trash/Undo and database-backed in-app
notifications. Exact release state must still follow the table above.

## 16. Planned and Future Work

Approved direction, not a delivery claim:

- dedicated QA and release decision for Sprint 3F;
- implementation of CUSTOMER-WALD05 after the W5-T01 reader compatibility correction,
  then independently gated CUSTOMER-WALD06 pilot and hardening;
- safe source scheduling/synchronisation after manual import is proven;
- completed Office account/organisation/site-assignment administration;
- approved UK bank-holiday provider and operational ownership;
- persistent production queue and worker supervision;
- selective email, reminders and later push mapping;
- attachments under a security/retention contract;
- agreed-work calendar and customer-safe PDF schedules;
- responsive/accessibility/performance hardening and production smoke coverage;
- future QR-assisted site selection;
- future customer-safe CML document/certificate behaviour only if explicitly approved.
- management reporting built only from approved customer-safe Portal data.

## 17. Non-Goals

Version 1 does not include:

- SiteApp workflow stages, trade sequencing, sign-offs or internal statuses;
- readiness/build verification or operational availability calculation;
- trade assignments, labour planning or manufacturing planning;
- SiteApp administration, Filament resources, direct database queries or write-back;
- generic CRM, free-form chat or a general file library;
- native mobile applications, AI scheduling or speculative microservices;
- external AI/LLM spreadsheet interpretation;
- public self-registration, MFA or SSO;
- automatic correction of unknown source meaning;
- deletion inferred from absence in a filtered workbook.

## 18. Open Decisions and Verification Gates

Do not invent answers for:

1. Exact customer-facing expansion of CML and any future certificate/document access.
2. UK bank-holiday provider/dataset, update ownership and failure behaviour.
3. A future RedZebra-native revision/API to replace the approved temporary Office-declared
   Export Date/Slot ordering without rewriting history.
4. G09 named unattended-disposal owner; WALD04 processing/knowledge periods, WALD05 six-year
   minimal committed-audit policy and hold mechanism are approved.
5. Any customer-safe projection of source fields beyond the approved product/status allowlist.
6. Amendment failure/cancellation and explicit old-date reinstatement rules.
7. Scheduled source-sync mechanism, credentials, cadence and operational alert ownership.
8. Email provider/event mapping, reminder cadence and production queue-worker rollout.
9. The actual currently deployed SHA beyond the last evidenced deployment; verify Forge rather
    than infer it from `origin/main`.

The confirmed per-visit semantic set in Sections 10–12 remains approved. WALD05 implementation
authority is recorded by DEC-049. DEC-050/051 are exact-workbook exceptions, not global dictionary
changes. The actual selected data has no W5-P01/P02 conflict; W5-T01 is a technical reader failure,
not an unanswered business rule. Dedicated QA and independently approved pilot/release gates
follow implementation.
