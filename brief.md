# Fenster Customer Portal — Current Product and Integration Brief

Last updated: 23 September 2026.

This document is the current product and integration contract for CustomerApp.

It defines what the application is intended to do.

It is deliberately not a deployment ledger.

Current implementation/release/deployment truth must be verified from:

- `current_sprint.md`
- `HANDOVER.md`
- current Git
- applicable QA/release reports
- Laravel Forge / production evidence

A feature described as required here is not automatically proof that it is
already deployed.

---

## 1. Product Purpose

CustomerApp is Fenster's customer-facing Portal for new-build site call-offs,
date communication and customer-safe progress.

Its job is to make it easy for authorised customer/site users and Fenster
Office Staff to answer:

- Which sites can I access?
- Which plots belong to this site?
- Which services have been called off?
- What date has been requested?
- What date has been agreed?
- Is a date change being requested?
- What has been completed?
- What changed?
- What action is required next?

The Portal must be simple enough for non-technical site users and Office Staff
to use without developer intervention.

---

## 2. Product Boundary

CustomerApp is separate from SiteApp / RedZebra.

SiteApp / RedZebra remains Fenster's operational source.

CustomerApp owns the customer-facing Portal workflow.

CustomerApp may:

- display authorised source-derived plot/service information;
- show customer-safe product facts;
- collect date requests;
- support Fenster/customer date negotiation;
- support customer date amendments;
- show customer-safe progress/history;
- manage customers/sites/users and site access;
- review and commit controlled source imports.

CustomerApp must not become a duplicate operational management system.

Do not recreate:

- trade sequencing;
- operational stages;
- readiness checks;
- internal approvals/sign-offs;
- labour planning;
- manufacturing planning;
- SiteApp administration;
- internal SiteApp notes/issues/statuses;
- direct SiteApp database/runtime dependencies.

Integration is one way:

**RedZebra / source → CustomerApp**

No spreadsheet or SiteApp write-back is currently part of the product.

---

## 3. Users and Roles

CustomerApp has four Portal roles:

1. Fenster Office Staff
2. Site Manager
3. Assistant Site Manager
4. Finishing Foreman

The three external roles remain distinct labels but currently use the same
Version 1 Site User permission model unless a later decision explicitly
changes that.

### Fenster Office Staff

Active Office Staff:

- have global Portal administrative scope;
- may have no customer organisation;
- may manage customers/sites/users according to authorised admin functions;
- may review customer requests;
- may operate supervised Wald imports.

Global access comes from the valid Office role, not from a null organisation.

### External Site Users

External users:

- require an active account;
- belong to one customer organisation;
- may be assigned to one or more sites under that customer;
- may access only authorised site/plot/customer workflow data.

They may not administer:

- other users;
- customers;
- sites;
- Wald;
- source bindings;
- private import evidence.

No public self-registration is provided.

Development role preview is local/test only.

---

## 4. Administration Model

Fenster Office Staff must be able to administer CustomerApp without developer
or database intervention.

The application must support the practical relationships:

**User → Customer → Site(s)**

and:

**Customer → Sites → Plots**

### Customer administration

Office should be able to:

- list/search customers;
- create customer;
- edit customer;
- deactivate customer;
- reactivate customer;
- inspect associated sites/users/history.

Customers are not hard-deleted merely for normal lifecycle changes.

Normal permanent deletion of an unused customer or site remains guarded by
business and Wald history. A separate Office-only **Purge demo/test data**
action may remove an explicitly certified disposable customer or site and its
site-owned Wald/import evidence. Office must review exact impact and blockers,
confirm that the records and related import data are demo/test, and type `PURGE`.
The server re-authorises and rejects a stale review. Shared master uploads and
unrelated site units remain; user accounts and the minimal purge audit remain.
Customer purge blocks while users still belong to that customer. Portal request,
batch, date and notification history is shown and blocks purge until separately
handled. No record is classified as demo by its name.

### Site administration

Office should be able to:

- list/search sites;
- create/edit approved site administration fields;
- deactivate/reactivate;
- inspect plots;
- inspect assigned users;
- inspect source binding/import information where authorised.

### User administration

Office should be able to:

- list/search users;
- create user;
- edit user;
- choose Portal role;
- set customer organisation for external users;
- assign one or multiple sites under that customer;
- remove site access;
- deactivate/reactivate accounts.

Reverse administration should also be available so Office can understand:

- which users belong to a customer;
- which users are assigned to a site;
- which sites are assigned to a user.

A user belonging to Customer A must never be assignable to Customer B's site.

Changing a user's customer must not leave invalid old site assignments.

Wald never assigns users automatically.

---

## 5. Customer → Site → Plot

The core hierarchy is:

**Customer → Site → Plot → Services**

Every CustomerApp plot belongs to one site.

A site belongs to one customer organisation according to the current domain
model.

Plot identity is site-scoped.

The same Plot Ref may therefore legitimately exist on more than one site.

Example:

Site A / Plot 1

and:

Site B / Plot 1

are different plots.

Never resolve Plot Ref globally across all customers/sites.

Plots are source-managed/read-only unless a later approved product decision
changes that.

Office should be able to see clearly which plots belong to a site.

---

## 6. Site User Scope

A Site User may have access to multiple sites under their customer.

The active site context controls which plots/call-offs are visible.

Expected behaviour:

assign user to Site A
→ Site A visible

assign user to Site B
→ Site A and Site B visible

remove Site A
→ Site A no longer visible

unrelated Customer/Site
→ never visible

Deactivated:

- user;
- customer;
- site

must not continue granting normal external access.

Office may retain appropriate administrative/historical visibility.

---

## 7. Customer-Facing Services

There are exactly four customer-facing services:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

These are independent customer services.

Their display order is not an operational dependency graph.

CML's expanded customer-facing wording remains subject to explicit product
decision if not already confirmed in `DECISIONS.md`.

Do not invent a source mapping for Snagging.

---

## 8. Service Presentation

Customer-facing service presentation includes the concepts:

- Nothing / Not Called Off
- Called Off — Awaiting Date
- Date Agreed
- On Hold — Date Change Requested
- Completed

Overall plot presentation may include:

- Nothing Called Off
- Call-Offs In Progress
- Dates Agreed
- Partially Completed
- Fully Completed

An open date-change request contributes to the in-progress state rather than
creating an invented overall operational stage.

Source completion retains appropriate precedence.

Completed plots remain in the system and may be hidden by default using a
Show Completed control.

---

## 9. Plot Details

Plot Details should show customer-safe information including:

- correct customer/site context;
- Plot Ref;
- approved non-zero product information;
- four customer services;
- current customer-facing service state;
- requested/agreed dates where applicable;
- safe ordered history.

Private source evidence must not leak to external users.

This includes:

- raw workbook rows;
- workbook filenames;
- worksheet names;
- source conflict keys;
- internal parse errors;
- private Office reasons.

---

## 10. Initial Call-Off Request

A Site User may submit one or more eligible plot/service call-offs.

One submission may include multiple plots/services.

Each request owns its own:

- service;
- requested date;
- agreed date;
- negotiation;
- status;
- decisions;
- history.

Different services may have different requested dates.

Do not allow more than one active request for the same plot/service.

Batch operations must not overwrite individually diverged decisions.

---

## 11. Lead-Time Rules

The current customer lead-time model includes:

- Cavity Closers: 15 working days after submission, independently callable for
  an authorised Site User and plot even without a source service projection;
- other services: normal customer window of four weeks;
- five weeks where exact positive BF applies to the relevant plot for those
  other services.

The underlying minimum includes the approved one-week customer buffer.

Dates are normally future weekdays and bounded by the current approved maximum
window.

UK holiday exclusion is an approved direction only when a concrete provider or
dataset is explicitly approved and implemented.

Do not claim holiday support merely because weekday logic exists.

Requesting an earlier date requires the current approved reason/warning flow.
For Cavity Closers, the requested-date screen states the 15-working-day rule
before selection, displays the calculated earliest standard date, and explains
the selected date and working-day shortfall. An earlier date remains selectable
but requires an Early Date Reason, enforced server-side. Office sees the
requested date, standard earliest date, shortfall and reason. Preserve the
lead-time context and requester in immutable request history.

Office acceptance inside the normal lead time requires the appropriate
acknowledgement.

Eligibility must be revalidated when a date decision is made.

---

## 12. Initial Date Agreement Workflow

Normal flow:

Site User submits requested date
→ Awaiting Fenster

Office may accept
→ Date Agreed

or:

Office proposes alternative
→ Awaiting Site User

An authorised assigned Site User may:

accept
→ Date Agreed

or:

reject with mandatory reason
→ Awaiting Fenster

Negotiation may repeat.

The actual responding user must be recorded truthfully.

Use the customer-facing term:

**Date Agreed**

Do not replace it with "Approved" for new/current state.

Historical legacy states remain historical evidence.

---

## 13. Withdrawal / Trash

Withdrawal is permitted only under the currently approved pre-Date-Agreed
rules.

Existing actor-bound Undo / Trash behaviour should remain consistent with the
current implementation and decision ledger.

Expiry may remove an item from ordinary customer Trash presentation but must
not erase audit history.

Do not invent new deletion semantics.

---

## 14. Date Amendments

An authorised Site User may amend an active call-off immediately, including
while Awaiting Fenster, Awaiting Site User, or an earlier amendment is awaiting
a response. No Office decision is required first. Date Agreed amendments remain
supported through the existing request/negotiation model.

The latest valid Site User amendment is the effective requested date on the same
request. Keep the original requested date and each amendment, actor, timestamp,
reason and lead-time context in history. Supersede pending cycles/proposals when
a later amendment commits; stale decisions and stale reviews cannot act on them.
A prior agreement is placed on hold; a pre-agreement amendment must not invent one.

Amendments retain the baseline four-week lead time, or five weeks with positive
BF quantities, for all four services. An early amendment needs a separate Early
Date Reason, validated server-side, with its normal earliest date and working-day
shortfall retained. Office acknowledgement remains required. Initial Cavity
Closer lead time remains separately governed by DEC-076.

The prior agreed date remains preserved as history.

It is not silently overwritten.

Approved reasons include:

- Site Not Ready
- Programme Change
- Access Issue
- Customer Requested Change
- Materials / Availability
- Weather
- Other

Other requires additional information.

The maximum explanation length is 2,000 characters at the domain boundary.

Actual requester/responder attribution must be preserved.

A request inside the currently approved three-working-day warning period is
Urgent / Late Amendment.

That is a warning rather than an automatic prohibition.

Office may:

- accept the new requested date;
- propose an alternative.

Assigned Site Users respond through the same authorised negotiation path.

Source completion closes/overrides an open amendment according to the current
approved completion precedence.

A later source reversal must not automatically reopen an obsolete amendment
or reinstate an old agreed date.

Fenster-originated Portal amendment initiation remains outside the current V1
model unless a newer decision explicitly changes it.

---

## 15. Portal-Owned State

The Portal owns customer communication state including:

- Requested Date;
- Date Agreed;
- alternative proposals;
- customer responses;
- amendment history;
- customer-visible request history.

Source imports must not overwrite that state.

Operational source dates are not automatically:

- Requested Date;
- Date Agreed;
- proposed date;
- amendment date;
- completion date.

No Portal requested/agreed/proposed date may manufacture source completion.

---

# SOURCE IMPORT / WALD

## 16. Wald Purpose

Wald is CustomerApp's deterministic spreadsheet interpretation and controlled
source-import system.

Core rule:

**Wald infers structure; the controlled business dictionary defines meaning.**

Wald is not an AI assistant.

It must not use:

- external LLMs;
- embeddings;
- cloud spreadsheet interpretation;
- fuzzy semantic guessing.

CustomerApp must not depend on live SiteApp runtime/API/database access to run
the import workflow.

---

## 17. Supervised Import Workflow

The bounded import flow is:

private workbook upload
→ structural analysis
→ clarification
→ source-site detection
→ exact binding
→ selected-site review
→ non-mutating preview
→ explicit approval
→ atomic commit
→ receipt/history

The current controlled model is Office-only.

A workbook may contain multiple sites, but the bounded pilot processes:

**one explicitly selected site per review/commit**

unless a later approved architecture replaces that model.

No automatic all-site commit is implied.

---

## 18. Master RedZebra Export

Management has confirmed that RedZebra operates from one master source sheet.

It is expected to be exported approximately twice per day:

- MORNING
- AFTERNOON

Therefore:

**Export Date + Slot**

identifies a logical revision family.

It must not behave as a permanent "this date/slot can only ever be uploaded
once" uniqueness rule.

Human corrections in RedZebra may require the same logical export to be
uploaded again.

---

## 19. Same-Slot Replacement

### Failed predecessor

If the previous same-date/slot revision failed:

- a new upload should be allowed easily;
- the old failed attempt remains historical evidence;
- a successor revision becomes current;
- the previous hard duplicate-upload error must not prevent normal recovery.

### Non-failed predecessor

If the current revision has not failed:

show explicit confirmation that the new upload will replace/supersede the
current revision.

The previous revision/history must remain available.

### Identical reupload

Where the exact same workbook/content is already known, avoid generating
pointless duplicate revisions.

Prefer showing/navigating to the existing import where supported.

### Replacement safety

Replacing the source revision does not automatically reverse earlier committed
facts.

Partial-export and correction rules still apply.

Older uncommitted previews/reviews must become stale when superseded.

---

## 20. CustomerCode as Source Identity

Management has confirmed that future RedZebra exports will contain a stable
CustomerCode to reduce the risk of Site Name typing errors.

The source workbook may currently expose this field as:

- `CustomerNo`
- `CustomerCode`
- `Customer Number`

where explicitly approved in the dictionary.

The identity priority is:

**CustomerCode first**

not Site Name.

The durable source binding is based on:

source namespace
+
CustomerCode

Site Name is descriptive/supporting evidence.

---

## 21. CustomerCode Behaviour

### Known CustomerCode

If an exact active binding exists:

use it.

A changed/mistyped Site Name must not create a second site merely because the
text changed.

### Unknown CustomerCode

Resolve the parsed composite `Plot Ref` customer and site by exact names. If
either does not exist, propose its creation for explicit Office approval,
then require an exact source binding before projection.
When both the customer and its site exist exactly and no source binding exists,
the Office selected-site action creates the audited binding in the same transaction.
Office need not make a separate binding draft for that exact match. Matching permits
only controlled case and whitespace normalization. A conflicting or inactive binding
blocks; Wald does not create a customer or site from its own proposal.

Do not:

- fuzzy-match;
- create a site automatically;
- guess based on similar Site Name.

### Missing CustomerCode

Under the current master-export contract, missing required CustomerCode blocks
the dependent source-site unit.

Do not silently fall back to Site Name.

Historical imports may require bounded compatibility handling, but that must
not weaken the current production identity contract.

### Same code / inconsistent names

Treat the CustomerCode as the identity, but surface materially inconsistent
Site Name evidence where review is required.

If evidence suggests the same code genuinely represents different sites,
block.

### Different codes / same name

Do not automatically merge them.

---

## 22. Source Site Binding

Office explicitly binds:

**source CustomerCode → CustomerApp Customer / Site**

One valid source-site binding controls all plot rows for that source identity.

Office should not have to bind every Plot Ref individually.

The UI should clearly show:

- CustomerCode;
- Source Site Name;
- parsed source customer and site;
- bound CustomerApp customer;
- bound CustomerApp site.

If no safe binding exists:

the dependent plot/service projection blocks.

---

## 23. Plot Projection

Once a source identity is safely bound:

all its Plot Ref rows inherit the target CustomerApp site.

Plot identity is:

**resolved site + normalized Plot Ref**

In the current RedZebra master export, the recognized `Plot Ref` header carries
exactly three nonempty components: Customer – Site – Plot. Separators may be
spaced ASCII hyphen, en dash or em dash; the approved compact variant uses
exactly two unspaced ASCII hyphens. The final component starts with
`Plot `; the nonempty remainder is the canonical string plot identity. For
example, `Vistry – Countryside 2D – Plot 776` resolves to Vistry,
Countryside 2D and plot `776`; `Plot Com 4` resolves to plot `Com 4`.
The confirmed FNA2561 form `Vistry - Northam PH3-{digits}` is a scoped
exception: the first hyphen is spaced, the final hyphen is unspaced, and all
final digits are the plot reference.
Site Name remains descriptive. Included malformed or conflicting hierarchy
blocks the affected selected-site import. Exact CustomerCode binding and site
ownership must agree with the parsed hierarchy. Approved excluded Call Type
rows do not contribute hierarchy blockers.

For an older applicable workbook with both `Plot Ref` and `Plot number`, Wald may select
`Plot number` as the customer-facing plot identifier without asking Office
only when its value agrees exactly with the trailing `Plot N` in `Plot Ref`
for every included row. Keep the full `Plot Ref` as private source evidence.
If any included row differs or either value is missing, require review rather
than guessing. The exact source-site binding still determines the plot's site.

An existing uncommitted selected-site import may be explicitly re-analysed
from its original private workbook. Re-analysis creates a fresh knowledge
context and staging generation under current rules, supersedes the old
clarification context, and invalidates its preview or approval. Earlier
answers, staged rows and previews remain in private history. A committed
import requires the separate correction/revision process. Older staged
interpretations must be refreshed before review or commit.

If the plot does not exist under that site:

Wald may create the source-managed plot according to the approved projection
rules.

If the plot already exists under that site:

reuse it.

Do not create duplicate plots.

A historical/source identity that would move an existing plot/source row to a
different site must block for review.

No free-floating plot should be created.

The import preview should visibly show for each plot:

- Plot Ref;
- target CustomerApp site;
- Create / Reuse outcome.

---

## 24. Site Details → Plots

Office Site Details should make source linkage understandable.

It should show:

- owning customer/site;
- Plot Ref;
- source-managed/read-only status;
- appropriate source reference/system;
- customer-safe product/service/completion information.

This presentation is important because automatic site-scoped linking should
not look as though manual plot-by-plot linking is missing.

---

## 25. Plot, Source Row and Visit Identity

The source model separates three identities.

### Plot

Exact resolved source-site binding + normalized Plot Ref.

### Source Row

Source namespace + CallNo.

### Visit

Source Row + recognised non-null Call Type.

CallNo does not by itself prove a call-off visit exists.

---

## 26. Blank Call Type

A genuinely blank Call Type is valid `null`.

Meaning:

**the plot exists, but no call-off has started.**

A null Call Type may contribute:

- plot identity;
- approved product facts.

It must not:

- create a visit;
- select/create a customer service;
- create a Portal request;
- create/reverse completion;
- map Arrival Date into Portal dates;
- manufacture workflow state.

A later blank in a partial export does not erase an already committed visit.

A nonblank unrecognised Call Type remains a blocking unknown.

---

## 27. Recognised Call Types

Current confirmed mappings include:

- `PC1` → Windows
- `CC1` → Cavity Closers
- `CM1` → CML-related visit

`CU4` means Customer Care and all such source rows are excluded from
CustomerApp discovery and projection under DEC-069. They make no plot,
product, visit, completion or customer date assertion. DEC-070 additionally
excludes exact codes `CM8`, `P02`, `P06`, `P08`, `Q01`, `QU5`, `SS1`, `T03`,
`T05`, `T07`, `T09`, `T11`, `T13`, `T15`, `VC1`, `X10`, `X14`, `X16`, `X50`,
`X99`, `XR1`, `XR2`, `XX1`, `Z05` and `Z09` on the same terms. Other nonblank
unknown Call Types remain blocking for their selected site unit. DEC-071 also
excludes Customer Care-related `CU0`, `CU1`, `CU3`, and currently ignored `zzz`.
`P04` is temporarily excluded from the current CustomerApp scope; it denotes
Plot Installation - 2nd Visit (use TEAM), and management may later assign it
a customer-facing meaning. It is not mapped to Windows now.

Do not invent a Snagging code.

Workbook-specific corrections remain scoped to their explicitly approved
workbook/context.

Do not convert one workbook's typo handling into global dictionary truth.

---

## 28. Call Number Recognition

Call-reference headers are recognised using deterministic structural token
normalisation.

Approved meaning is exact:

- `call` + `no`
- or `call` + `number`

Structural differences may include:

- case;
- punctuation;
- spaces;
- underscore;
- hyphen;
- concatenation;
- word order.

Examples may include:

- Call No.
- CallNo
- CAllNo
- CALL-NUMBER
- call_number
- No Call
- NumberCall

Do not use fuzzy matching.

Do not automatically recognise headers with extra semantic words such as:

- Call Date Number
- Phone Number
- Number of Calls

---

## 29. Composite Spreadsheet Structure

The RedZebra export may represent one logical table across physically separated
cell regions.

Wald may compose compatible fragments where deterministic evidence supports one
logical table.

Example logical reference:

`C2:E2+I2:AJ2`

Composition must preserve the physical workbook provenance of every interpreted
cell.

Wald must retain:

- worksheet;
- raw coordinate;
- raw value;
- logical row;
- fragment identity.

Spacer rows/columns may be structural rather than separate tables.

If two plausible compositions remain:

ask for clarification.

Do not silently join unrelated side-by-side tables.

Unlabelled populated columns remain private unmapped evidence.

---

## 30. Product Semantics

Approved product facts are exact source evidence.

### Windows

Known approved source product codes include:

- VS — Vertical Slider
- TT — Tilt and Turn
- BAY — Bay Window
- ALI — Aluminium Windows
- AOV — Automatic Opening Vent Window
- FI — Fire Window
- FLU — Flush Window
- CAS — Casement window

### Doors

Known approved source product codes include:

- PSU
- PSG
- CDF
- CDU
- CDG
- PSP
- BF
- PFD — Patio/French Door

`GLS`, `WP` and `MISC` are excluded from customer product projection and totals;
their raw values remain private source evidence. Approved irrelevant Call Type
rows remain excluded independently of product treatment.

BF remains individually significant for approved lead-time behaviour.

### Product evidence rules

- unrepresented/missing → no assertion;
- explicit zero → exact zero;
- explicit positive quantity → exact fact;
- invalid quantity → block dependent projection;
- repeated agreeing values → agreement;
- unrepresented + explicit → use explicit evidence;
- conflicting explicit values → block the complete selected-site unit.

Never choose:

- first row;
- last row;
- highest;
- lowest;
- average

as an automatic winner.

---

## 31. Partial Export Contract

Every ordinary source workbook defaults to:

`PARTIAL_FILTERED_EXPORT`

Therefore:

**absence never proves deletion.**

Missing:

- row;
- plot;
- visit;
- product value;
- site

does not imply deletion, reversal or zero.

Do not introduce absence-based reconciliation without a separately approved
complete-snapshot contract.

---

## 32. Source Completion

Approved source completion is based on explicit source evidence such as
`complete = Yes` under the current dictionary.

Completion applies to the relevant source visit/service.

Do not invent a completion date if the source does not provide one.

Source completion may close relevant Portal negotiation/amendment state
according to the current approved precedence.

A later source reversal does not automatically reopen an obsolete negotiation.

---

## 33. Operational Dates

Source operational dates such as arrival/install dates remain operational
evidence.

They must not automatically become:

- Requested Date;
- Date Agreed;
- proposed alternative;
- amendment date;
- completion date.

Portal-owned customer communication state remains separate.

---

## 34. Preview

Preview is non-mutating.

Before commit, Office should be able to understand at minimum:

- source export/revision;
- CustomerCode;
- Source Site Name;
- bound CustomerApp customer/site;
- selected source site;
- Plot Ref;
- target site;
- create/reuse plot outcome;
- recognised service/call type;
- product facts;
- completion facts;
- warnings;
- blockers;
- proposed changes.

If the target site cannot be resolved safely:

block before commit.

If product/source evidence genuinely conflicts:

block rather than guess.

---

## 35. Commit

One reviewed selected-site unit is committed atomically.

Commit requires:

- active authorised Office identity;
- current source revision;
- current exact site binding;
- current approved preview;
- no unresolved blockers;
- explicit confirmation.

A failed business mutation must not leave partial projection state.

Successful commit creates durable audit/receipt evidence.

Idempotent retry must not duplicate:

- plot;
- Source Row;
- Visit;
- product facts;
- receipt/business effects.

---

## 36. Wald Environment Safety

Production Wald availability has three controls:

1. `WALD_IMPORT_AVAILABLE`
2. audited application setting
3. valid current Office Staff authority

The environment gate fails closed.

Only the exact case-insensitive word:

`true`

enables the environment layer.

Do not accept generic truthy strings/numbers.

The environment gate is the emergency hard-off.

Disabling it must stop new Wald actions without deleting prior history or
reversing committed imports.

---

## 37. Wald Access

Real Wald import functionality is Office-only.

External roles must never access:

- uploads;
- source bindings;
- structural clarifications;
- private previews;
- commit controls;
- private import history;
- Wald settings;
- raw workbook/source evidence.

Server-side authorisation is mandatory.

Navigation hiding is not sufficient.

---

## 38. Audit and Provenance

Preserve immutable evidence for important source/import actions, including as
applicable:

- uploader;
- export date/slot;
- revision/predecessor;
- workbook hash;
- selected source identity;
- binding;
- clarification;
- reviewer;
- preview;
- commit actor/time;
- attempt/outcome;
- receipt;
- replacement/supersession.

Replacement never means deleting historical evidence.

Private technical evidence remains private.

---

## 39. User Assignment After Import

Wald source binding does not grant Portal access to people.

The end-to-end business process is:

1. import/review source workbook;
2. bind CustomerCode to CustomerApp site;
3. source-managed plots appear under that site;
4. Office assigns appropriate Site User(s);
5. Site User logs in;
6. assigned site and plots become visible;
7. unrelated sites remain hidden.

This end-to-end experience is an important product acceptance criterion.

---

## 40. Notifications

Notifications derive from committed Portal/domain truth.

They must be authorised and idempotent.

Notification reading/dismissal does not modify request history.

Source completion currently does not generate a completion notification unless
a later explicit decision changes this.

Do not expose private source/import reasons to customers.

---

## 41. Current Non-Goals

Unless explicitly approved by a newer decision, CustomerApp does not provide:

- automatic RedZebra synchronisation;
- unattended imports;
- automatic all-site workbook commit;
- fuzzy customer/site matching;
- automatic source-conflict repair;
- spreadsheet writeback;
- SiteApp API/runtime dependency;
- automatic data purge;
- public registration;
- Site User administration by customers;
- SiteApp trade workflow;
- manufacturing/labour planning;
- external AI spreadsheet interpretation.

Full Wald multi-site orchestration remains separate future work.

---

## 42. Known / Explicitly Unresolved Areas

Do not invent answers for these areas if they remain unresolved in
`DECISIONS.md`:

- Snagging source mapping;
- expanded customer-facing CML wording;
- holiday provider/dataset;
- any future complete-snapshot reconciliation contract;
- unattended retention/purge ownership;
- automatic multi-site Wald orchestration.

A genuine source-data conflict should remain blocked until the source is
corrected or management explicitly approves a deterministic resolution.

---

## 43. Current Practical Product Goal

The immediate success criterion for CustomerApp/Wald is not merely:

"the spreadsheet parser ran."

The practical chain must work:

**CustomerCode
→ exact source-site binding
→ CustomerApp site
→ plots
→ assigned users
→ Site Manager visibility
→ customer-safe services/history**

Office Staff must be able to understand and manage this chain through the UI.

No developer/database intervention should be required for ordinary account,
site-assignment or supervised import operation.

---

## 44. Release Truth

This brief intentionally avoids pinning itself to one production SHA.

A release/feature may be:

- required by this product contract;
- implemented on a feature branch;
- merged to `main`;
- deployed;
- or still pending operational verification.

Those are different states.

Before claiming any particular item is live, verify:

- current `origin/main`;
- relevant task/release report;
- Forge deployment;
- served SHA;
- migration state;
- production configuration where relevant.

`current_sprint.md` and `HANDOVER.md` should carry the current short-lived
release truth.

The brief should remain the durable product contract.
