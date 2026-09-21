# Fenster CustomerApp — Agent Instructions

Last updated: 19 September 2026.

This file defines how coding, QA, documentation and release agents must work on
CustomerApp.

It is a working contract, not a historical report.

---

## 1. Read Before Every Task

Before changing CustomerApp, read these files in order:

1. `AGENTS.md`
2. `brief.md`
3. `DECISIONS.md`
4. `current_sprint.md`
5. `ROADMAP.md`
6. `HANDOVER.md`, when present
7. the task-specific work package, contract, schema or integration document

For source-import or Wald work, also read:

- `documentation/source-integration-contract.md`
- `documentation/siteapp-import-data-dictionary.md`
- `documentation/wald-divergence-register.md`
- the approved work package for the current Wald task

For release or production work, also read the latest applicable:

- release report;
- recovery strategy;
- deployment evidence;
- production handover.

Historical reports are evidence of what happened at the time. They are not
automatically current instructions.

If a current user/management instruction conflicts with an older document,
do not silently choose one. Apply the newest explicitly approved decision and
record the superseded assumption where necessary.

---

## 2. Sources of Truth

Resolve contradictions in this order:

1. latest explicit management/user decision;
2. newest applicable authoritative entry in `DECISIONS.md`;
3. current `brief.md`;
4. successfully QA/release-approved behaviour with verified deployment evidence;
5. current code, migrations and tests;
6. `current_sprint.md`, `HANDOVER.md` and `ROADMAP.md`;
7. older reports, work packages and historical briefs.

`DECISIONS.md` is append-only.

Newer numbered decisions may supersede older decisions, but do not rewrite the
historical decision ledger.

Always distinguish:

- **Deployed** — verified as successfully running in production.
- **On `main`** — merged/pushed to the production branch, but deployment still
  needs evidence.
- **Feature branch only** — implemented/committed but not merged or deployed.
- **Approved / planned** — agreed requirement without implementation proof.
- **Historical** — true for an earlier release or task, not automatically now.

Never turn a feature-branch report into production truth.

---

## 3. Workspace Portability

CustomerApp may be worked on from more than one computer.

Do not hard-code one developer-machine path as an architectural requirement.

The current working checkout must be established from Git before work begins.

For the temporary home-machine checkout, the known path is:

`C:\Users\madas\Documents\customerapp\CustomerApp`

The historical office checkout has been:

`C:\Users\JoshO\Documents\CustomerApp`

GitHub / `origin/main` is the source of truth when moving between machines.

Before changing code:

- inspect the repository path;
- run/fetch current Git state;
- inspect the active branch;
- inspect `git status`;
- pull the intended base branch where appropriate;
- record the baseline SHA for substantial work.

Do not copy an old project directory over a fresh checkout to "sync" machines.

Use Git.

---

## 4. Git and Production Safety

Preserve user work and shared history.

Do not:

- force-push;
- `git reset --hard`;
- `git clean -fd`;
- discard unreviewed changes;
- rewrite shared history;
- delete unknown files;
- remove worktrees containing unreviewed changes.

unless explicitly authorised.

Use a bounded feature branch for implementation unless instructed otherwise.

Stage and commit only task-related files.

Never commit:

- `.env`;
- passwords;
- credentials;
- private source workbooks;
- customer exports;
- local database files;
- generated output;
- local login documents;
- unrelated user files.

Do not change package lockfiles or broadly upgrade dependencies unless that is
the approved task.

### `main` is production-sensitive

Laravel Forge push-to-deploy may be enabled.

Therefore:

**pushing `main` is a production action.**

Do not merge/push `main` merely as Git housekeeping.

Before an authorised production push:

- identify exact candidate SHA;
- review exact diff;
- pass required tests;
- understand migration delta;
- establish required backup/recovery evidence;
- know what Forge will deploy.

After a production push:

- verify Forge deployment;
- verify served SHA;
- verify migrations;
- verify configuration;
- smoke-test application health;
- inspect logs/failed jobs.

---

## 5. Production

Production site:

`https://fenstercustomer.on-forge.com`

Do not use production as a development environment.

Production experiments must not use real customer data unless separately
authorised with a defined scope and recovery plan.

Never run against production:

- seeders;
- `migrate:fresh`;
- destructive database resets;
- destructive DDL;
- ad-hoc bulk repair statements;
- unsafe fixture creation.

Require an approved recovery point before production database/schema changes.

Do not expose:

- credentials;
- `.env` contents;
- session/cookie material;
- private source rows;
- internal provider output;
- workbook storage paths unnecessarily.

Use fictional/test records for production smoke testing wherever practical.

---

## 6. Product Boundary

CustomerApp is a customer-facing Portal and communication application.

SiteApp / RedZebra remains Fenster's operational source system.

CustomerApp may:

- show authorised customers/sites/plots;
- show customer-safe source facts;
- show customer-facing service progress;
- collect date requests;
- support date negotiation;
- support customer date amendments;
- manage customer/site/user access;
- review and import controlled source data;
- preserve customer-safe history and notifications.

CustomerApp must not become a duplicate SiteApp.

Do not recreate SiteApp:

- operational trade stages;
- trade sequencing;
- dependency management;
- readiness/sign-off workflows;
- labour planning;
- manufacturing planning;
- internal notes/issues;
- SiteApp administration;
- SiteApp roles/policies;
- direct SiteApp database access.

The approved controlled reuse of generic Wald engine concepts/code is a narrow
exception and does not authorise copying SiteApp operational domain code.

The integration direction remains:

**source → CustomerApp**

There is no spreadsheet or SiteApp write-back unless a future decision
explicitly approves it.

---

## 7. Architecture and Coding Rules

Prefer simple, explicit, auditable implementation.

- Keep controllers thin.
- Authorise, validate, dispatch to domain/application services, return response.
- Put business transitions in Actions/services/domain classes.
- Re-authorise immediately before persistence.
- Hidden buttons are never security.
- Never trust client-supplied organisation/site/plot/request/source IDs.
- Enforce customer and site boundaries server-side.
- Use transactions for multi-record business actions.
- Use locks where concurrent changes can alter an outcome.
- Preserve immutable history and attribution.
- Preserve before/after evidence where required.
- Separate customer-visible explanations from private Fenster/internal reasons.
- Use appropriate foreign keys, indexes and deliberate delete behaviour.
- Avoid N+1 queries.
- Paginate potentially large datasets.

For Portal request/date workflows preserve the canonical locking order where
applicable:

service
→ request
→ negotiation/amendment
→ proposal
→ history

Preserve bounded retry behaviour for recognised transient MySQL concurrency
errors.

Never weaken race/concurrency assertions simply to make tests pass.

---

## 8. Database and Migration Rules

CustomerApp must remain testable on SQLite for normal development and qualified
on MySQL 8.4 for MySQL-specific behaviour.

Use disposable MySQL evidence for:

- locking;
- concurrency;
- trigger behaviour;
- uniqueness races;
- production-like import transactions;
- database-specific constraints.

Never edit a migration that may already have run outside a disposable local
database.

Use a new forward/additive migration.

Do not infer current production migration count from an old report. Inspect the
current repository and production ledger when it matters.

---

## 9. Frontend and UX

Use:

- Blade;
- Tailwind;
- Livewire where server interaction benefits;
- Alpine for small client behaviour.

Do not add a large frontend framework without approval.

Design mobile-first.

Requirements:

- accessible names;
- visible keyboard focus;
- large enough touch targets;
- status communicated by text as well as colour;
- responsive tables/overflow;
- usable navigation at short viewport heights.

The left application sidebar must remain usable when role-specific navigation
exceeds viewport height. Scroll the appropriate navigation region rather than
making controls unreachable.

Do not redesign unrelated screens during a targeted fix.

---

## 10. Authentication and Roles

CustomerApp roles:

- Fenster Office Staff
- Site Manager
- Assistant Site Manager
- Finishing Foreman

These are CustomerApp roles, not SiteApp roles.

The three external roles remain distinct labels but currently share the same
Version 1 Site User permission model unless a newer approved decision changes
that.

### Office Staff

Active Fenster Office Staff:

- are globally scoped in the approved model;
- may have `customer_organisation_id = NULL`;
- receive global access from a valid Office role, not from the null value.

### External Site Users

An external user requires:

- active account;
- valid external Portal role;
- customer organisation;
- authorised site assignments.

External users may access only their assigned scope.

Public self-registration remains disabled unless explicitly approved.

Development role-preview tooling is local/test only and must never grant
production access.

QR codes may identify a site in future but must never authenticate a person.

Use framework-standard:

- password hashing;
- password reset;
- CSRF;
- secure sessions;
- rate limiting.

Never store plaintext passwords.

---

## 11. User / Customer / Site Administration

Office Staff must be able to manage the practical access hierarchy without
developer/database intervention.

Required relationship:

**User → Customer Organisation → Assigned Site(s)**

Office administration should support, as implemented/approved:

- list/search users;
- create user;
- edit user;
- choose Portal role;
- assign customer organisation;
- assign one or more sites under that customer;
- remove site access;
- deactivate/reactivate account.

Reverse administration should also be understandable:

**Customer → Users**

and:

**Site → Assigned Users**

A Customer A user must never be assignable to a Customer B site.

Changing an external user's customer must not leave stale cross-customer site
assignments.

Wald source binding and user assignment are separate concerns:

- Wald binding answers: "Which CustomerApp site does this source site belong to?"
- User assignment answers: "Which Portal users may see this CustomerApp site?"

Wald must not automatically assign users.

---

## 12. Customer → Site → Plot Hierarchy

The product hierarchy is:

**Customer → Site → Plot → Services / Call-offs**

Every projected plot belongs to exactly one CustomerApp site.

Plot source identity is scoped by site.

The same Plot Ref may legitimately exist at multiple different sites.

Never resolve Plot Ref globally across every site.

When Wald has an exact source-site binding, all source plot rows for that
source identity inherit the resolved CustomerApp site.

Office must not have to manually link every imported plot one-by-one.

If the source site is not safely resolved, plot projection blocks.

Site Details should make the relationship obvious by showing the plots that
belong to that site and their appropriate source-managed information.

---

## 13. Customer Services

Customer-facing services are exactly:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

These are independent services, not SiteApp operational stages.

Do not infer dependencies from display order.

Typical customer-facing service presentation includes:

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

Completed plots remain retained and may be hidden by default with a Show
Completed option.

Do not manufacture completion from Portal-requested/agreed dates.

---

## 14. Initial Date Agreement

One customer submission may include multiple plot/service requests.

Each request owns:

- its service;
- requested date;
- agreed date;
- negotiation state;
- decisions;
- history.

Do not allow more than one active request for the same plot/service.

Typical flow:

Site User submits
→ Awaiting Fenster

Office accepts requested date
→ Date Agreed

or:

Office proposes alternative
→ Awaiting Site User

Assigned authorised Site User accepts
→ Date Agreed

or rejects with reason
→ Awaiting Fenster

Negotiation may repeat.

Use **Date Agreed**, not "Approved", for current customer-facing state.

Preserve historic records truthfully.

Withdrawal is allowed only under the currently approved pre-agreement rules.

Do not invent new lead-time, holiday or transition rules.

---

## 15. Amendments

After Date Agreed, an authorised Site User may request a new date under the
current amendment workflow.

The previous agreed date is history, not silently overwritten.

Approved amendment reasons and validation come from `brief.md` /
`DECISIONS.md`.

Office may:

- accept the new requested date;
- propose an alternative.

Assigned Site Users may respond according to current authorisation rules.

Source completion wins over an open amendment.

A later source reversal must not automatically reopen an obsolete negotiation.

Do not invent:

- automatic old-date reinstatement;
- Office-originated amendment behaviour;
- new cutoff rules.

---

## 16. Source Import and Wald Principles

Wald is deterministic spreadsheet intelligence.

Core principle:

**Wald infers structure; the controlled business dictionary defines meaning.**

Do not add:

- external AI/LLM interpretation;
- embeddings;
- third-party semantic spreadsheet services;
- fuzzy business inference.

CustomerApp must remain able to operate without:

- SiteApp API;
- SiteApp database;
- SiteApp filesystem;
- SiteApp runtime;
- SiteApp queue.

The controlled flow is:

private source
→ structural analysis
→ clarification
→ neutral staging
→ exact binding
→ authorised preview
→ explicit commit

Inference must not write directly into final Portal state.

---

## 17. Master Export and Revision Model

RedZebra operates from one master source sheet/export.

The operational expectation is approximately two exports per day:

- MORNING
- AFTERNOON

Date + slot identifies a logical export family, not a permanent "may only ever
upload once" record.

A later upload for the same date + slot is a revision/replacement.

### Failed predecessor

If the previous revision failed:

- allow a replacement without the old hard duplicate error;
- preserve failed history/audit;
- continue with a new revision.

### Non-failed predecessor

If an existing non-failed revision exists:

- warn the Office user;
- require explicit replacement confirmation;
- preserve the old revision/history;
- create a successor/current revision.

### Identical reupload

Avoid creating meaningless identical revisions where the exact same content is
already known. Prefer navigating to the existing import/history where supported.

Replacing an import does not erase committed Portal/source facts.

All normal partial-export/correction rules still apply.

Older uncommitted reviews/previews must become stale when their source revision
is superseded.

---

## 18. CustomerCode Source Identity

Current source identity uses a stable external CustomerCode.

Source workbooks may expose the field as:

- `CustomerCode`
- `CustomerNo`

when explicitly approved by the current dictionary.

CustomerCode is the first source identity key.

Site Name is descriptive evidence, not the durable primary identity.

Required behaviour:

- known CustomerCode → use its exact active binding;
- changed/mistyped Site Name with same code → do not create a duplicate site;
- unknown CustomerCode → require explicit Office binding;
- missing required CustomerCode → block the dependent source unit;
- different codes with same Site Name → do not silently merge;
- one code with evidence of genuinely different sites → block/review.

Do not fuzzy-match Site Name.

---

## 19. Source Site Binding and Plot Identity

A durable source-site binding resolves:

source namespace
+
CustomerCode

to the intended CustomerApp customer/site.

After exact binding:

all plot rows for that source identity inherit the target site.

Plot identity is:

resolved exact site binding
+
normalized Plot Ref

Do not require plot-by-plot manual site linking.

A new Plot Ref may be source-projected beneath the bound site according to the
approved projection rules.

An existing same-site Plot Ref is reused.

A source identity attempting to move established site/plot history to another
site must block rather than silently reassign.

The import preview should make target site and create/reuse behaviour obvious.

---

## 20. Source Row and Visit Identity

Keep three concepts separate:

### Plot

Resolved site binding + normalized Plot Ref.

### Source Row

Source namespace + CallNo.

### Visit

Source Row + recognised non-null Call Type.

CallNo alone does not establish a visit.

A recognised non-null Call Type establishes the source visit/service meaning.

---

## 21. Blank Call Type

A genuinely blank Call Type is valid `null`.

Meaning:

**The plot exists, but no call-off has started.**

A blank type may contribute approved:

- plot facts;
- product facts.

It must not:

- create a visit;
- create a customer-facing service request;
- create/reverse completion;
- create Requested Date;
- create Date Agreed;
- create a proposal/amendment date;
- invent a completion date.

A nonblank unrecognised Call Type remains unknown and blocks its dependent
projection.

In a partial export, a later blank value does not automatically erase an
already established visit.

---

## 22. Call Type and Header Semantics

Use the current approved dictionary.

Known mappings include:

- PC1 → Windows
- CC1 → Cavity Closers
- CM1 → CML-related visit

Do not invent a Snagging mapping.

Workbook-specific exceptions must remain workbook/context/checksum scoped.

Do not turn one workbook correction into global dictionary truth.

Call-reference headers use deterministic normalisation around the exact
semantic token combinations:

- `call` + `no`
- `call` + `number`

Case, punctuation, spacing, underscore, hyphen, concatenation and order may be
normalised where approved.

Do not use fuzzy matching.

Extra semantic words must prevent automatic recognition.

---

## 23. Composite Workbook Structure

Wald may compose one logical table from physically separated compatible
fragments when evidence clearly supports that interpretation.

Examples may include:

`C2:E2+I2:AJ2`

for a logical composite header.

Composition requires compatible evidence such as:

- same worksheet;
- aligned row boundaries;
- blank/formatting-only spacer regions;
- complementary header/data roles;
- no stronger evidence of two separate tables.

If more than one plausible composition remains:

ask for clarification.

Never silently join unrelated tables.

Always preserve original:

- worksheet;
- cell coordinate;
- raw value;
- fragment identity;
- logical row association.

Unlabelled populated columns remain private unmapped evidence.

---

## 24. Product Facts

Source product facts are exact evidence.

Rules:

- unrepresented/missing → no assertion;
- explicit zero → exact zero;
- explicit positive valid quantity → exact quantity;
- invalid value → block dependent projection;
- matching explicit values across rows → agreement;
- unrepresented + explicit → retain explicit fact;
- conflicting explicit quantities → block selected site unit.

Never use:

- first-row-wins;
- last-row-wins;
- minimum;
- maximum;
- averaging.

Do not infer product quantities from Call Type.

Keep BF separately identifiable for approved lead-time behaviour.

Do not invent new customer product totals or labels without an approved
decision.

---

## 25. Partial Export Safety

Default source scope is:

`PARTIAL_FILTERED_EXPORT`

Therefore:

**absence is not evidence of deletion.**

Missing rows do not imply deletion.

Missing product values do not imply zero.

Missing visits do not imply reversal.

Missing sites do not imply removal.

A stronger complete-snapshot contract must be explicitly approved before
absence-based reconciliation is allowed.

---

## 26. Supervised Wald Pilot

Current Wald work is intentionally bounded unless a later decision supersedes
it.

Expected safe pilot model:

- Office-only;
- private upload;
- manual review;
- exactly bound source site;
- one selected site per review/commit;
- non-mutating preview;
- explicit approval;
- atomic commit;
- idempotent receipt/history;
- no customer access to source evidence.

Do not introduce automatically:

- all-site commits;
- unattended sync;
- scheduled imports;
- fuzzy matching;
- automatic conflict repair;
- spreadsheet writeback;
- source API dependency;
- automatic purge.

Full multi-site orchestration remains separate work unless explicitly approved.

---

## 27. Wald Environment Gate

The production emergency gate is:

`WALD_IMPORT_AVAILABLE`

This is security-sensitive.

It must fail closed.

Only the exact case-insensitive word:

`true`

is approved to enable the environment layer.

Do not reintroduce generic truthy boolean casting.

Malformed values, numeric values, `yes`, `on`, padded text and arbitrary
non-empty strings must not enable Wald.

Effective pilot access also requires:

- audited application setting enabled;
- current valid Office Staff authority.

Navigation visibility alone is not security.

---

## 28. Wald Commit Safety

One reviewed selected-site unit is one atomic commit.

Preserve:

- top-level commit journal ownership;
- transaction boundary;
- stale-preview checks;
- exact binding;
- source ordering;
- idempotency;
- immutable attempts/outcomes;
- receipts;
- bounded concurrency retry;
- Portal-state protection.

Do not recreate the historical double-journal defect.

There must be one authoritative top-level journal/transaction ownership path.

Never disable a safety guard just to make a smoke test pass.

---

## 29. Portal-Owned State Protection

Source import must not overwrite customer-owned workflow such as:

- Requested Date;
- Date Agreed;
- alternatives;
- customer responses;
- amendments;
- Portal history.

Operational source dates are not automatically customer dates.

Source completion may update approved source-completion facts according to the
current dictionary, but completion dates must not be invented.

---

## 30. Notifications and Queues

Notifications derive from committed domain truth.

Prefer dispatch after successful commit.

Re-authorise recipients and destinations.

Reading/dismissing a notification must not alter business history.

Completion currently sends no notification unless a newer approved decision
changes that.

Do not claim a persistent queue worker, provider or scheduled Wald process
exists without evidence.

Queue-dependent work must document:

- retries;
- idempotency;
- failures;
- intended production worker model.

---

## 31. Required Verification

Run focused tests first.

Ordinary executable changes normally require:

```text
php artisan test
vendor\bin\pint --test
composer validate --strict
composer audit
npm run build
npm audit --omit=dev
git diff --check
```

Use tests proportionate to the task.

Security/authorisation work requires both allowed and denied paths.

Tenant work requires cross-customer/site denial tests.

Import work requires success, blocker, stale-preview and rollback paths.

Concurrency/locking/database-specific work requires disposable MySQL 8.4
evidence.

Do not weaken tests merely to produce green output.

Never report a command as passing unless it actually ran successfully.

Documentation-only work does not require the full application suite unless it
changes executable artefacts, but it still requires contradiction/diff/path
review and whitespace validation.

---

## 32. Documentation Discipline

Keep `brief.md` as the current product contract.

Do not turn it into a deployment log.

Use:

- `DECISIONS.md` for durable decisions;
- `current_sprint.md` for current delivery work;
- `HANDOVER.md` for current operational handover;
- `ROADMAP.md` for delivery order;
- release/QA reports for historical evidence.

Do not duplicate stale truth beneath newer truth.

Use absolute dates and exact SHAs/deployment identifiers in reports where
known.

Record unresolved business rules explicitly.

Do not invent answers.

---

## 33. Task Heartbeat

For substantial tasks, provide concise progress updates at meaningful
checkpoints, for example:

- baseline inspected;
- root cause identified;
- implementation complete;
- focused tests running;
- full regression running;
- commit created;
- push/deployment starting;
- deployment verified.

Do not send repetitive updates that add no information.

---

## 34. Completion Reports

Every substantial task report should state:

### Completed

What was actually achieved.

### Files changed

Exact task-related files.

### Tests / checks

Commands and exact results.

### Migration / database impact

What changed or explicitly did not.

### Git state

Branch, commit SHA and whether anything was pushed.

### Production impact

Deployed / not deployed, with evidence where relevant.

### Notes / blockers

Unresolved decisions, preserved unrelated changes, manual actions and next
step.

Never use "done" to mean merely "implemented locally" when deployment was part
of the user's goal.

---

## 35. Final Engineering Principle

Prefer changes that are:

- simple;
- secure;
- deterministic;
- auditable;
- maintainable;
- understandable to Fenster staff.

CustomerApp should make the real business relationship obvious:

**CustomerCode → CustomerApp Site → Plots → Assigned Users → Customer-safe
services/history**

without drifting into SiteApp's operational domain.
