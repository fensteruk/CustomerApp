# Fenster Customer Portal — Agent Instructions

## 1. Read Before Every Task

Read these files in order before acting:

1. `AGENTS.md`
2. `brief.md`
3. `DECISIONS.md`
4. `current_sprint.md`
5. `ROADMAP.md`
6. `HANDOVER.md`, when present
7. the task-specific contract, schema, integration or work-package documents

For spreadsheet/source work, also read:

- `documentation/source-integration-contract.md`
- `documentation/siteapp-import-data-dictionary.md`

For standalone Wald work, also read:

- `documentation/work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md`
- `documentation/wald-divergence-register.md`
- the approved work package for the current Wald phase

If a newer user instruction, the repository and the documents disagree, stop before any
destructive, production or wide-ranging change. Record the conflict and use the newest
explicitly approved decision. Historical documents are evidence, not current instructions.

## 2. Sources of Truth

Resolve conflicts in this order:

1. the latest explicit approved user/management decision;
2. the newest applicable authoritative entry in `DECISIONS.md`;
3. the current `brief.md` product and scope contract;
4. successfully QA/release-approved behaviour and verified deployment evidence;
5. current code, tests and migration evidence;
6. older briefs, roadmaps, handovers and reports.

`DECISIONS.md` is append-only: newer numbered decisions supersede contradictory older entries,
but the historical record is not rewritten. `current_sprint.md` identifies the active delivery
milestone, while `ROADMAP.md` records delivery order rather than proof of implementation or
deployment. Task-specific contracts define only their bounded domain.

Always distinguish these states in reports and documentation:

- **Deployed:** explicitly evidenced as successfully released to production.
- **On `main`:** merged to the production branch, but not proof that deployment succeeded.
- **Feature branch only:** committed outside `main`; not deployed or release-approved.
- **Planned:** approved intent with no implementation claim.

Do not promote a branch report, release candidate or roadmap checkbox into production truth.

## 3. Product Boundary

CustomerApp is a separate customer-facing communication and request application. SiteApp
remains Fenster's internal operational system.

CustomerApp may display authorised source information, show outstanding plots, collect
date requests and amendments, receive decisions and show customer-facing progress. It
must never become an operational management system.

Do not introduce or recreate SiteApp:

- workflow stages, trade sequencing, dependencies or sign-offs;
- readiness/build verification, labour or manufacturing planning;
- roles, policies, administration or Filament resources;
- internal notes, queries, templates, issues or operational statuses;
- database models, tables, services or direct database access.

The approved controlled fork of generic SiteApp Wald engine code is the sole narrow reuse
exception. It does not authorise copying SiteApp operational domain code.

## 4. Workspace and Git Safety

- Work only inside `C:\Users\JoshO\Documents\CustomerApp` unless the user explicitly
  authorises a separate SiteApp task.
- Inspect `git status`, current branch and relevant diffs before and after work.
- Preserve user changes and avoid unrelated refactors.
- Use a named, non-deploying feature/documentation branch for work unless the user gives a
  different instruction.
- Never merge or push to `main`, create a production tag or deploy without explicit approval.
- A push to `main` can trigger Laravel Forge Quick Deploy. Treat it as a production action.
- Never force-push, reset, clean, discard changes or rewrite shared history without explicit
  approval.
- Stage and commit only task-related files. Never commit `.env`, secrets, customer data,
  source workbooks, generated output or local database files.
- Do not upgrade packages or alter lockfiles unless that is the approved task.

Production is `https://fenstercustomer.on-forge.com`. Do not use production accounts,
queues, storage, database or customer records for experiments. Production smoke tests must
use an approved safe account and remain non-destructive unless separately authorised.

- Never run seeders, destructive DDL, `migrate:fresh`, database resets or ad-hoc repair
  statements against production.
- Do not test real customer workflows without an explicitly approved account, record scope
  and cleanup/recovery plan.
- Require an approved backup/recovery point before a production database deployment.
- Never bypass authentication or expose credentials, environment values or provider output.

## 5. Local Environment

Development uses Windows and Laravel Herd. Before application work, verify these commands
are available:

```text
php -v
composer --version
node -v
npm -v
git --version
```

Use versions locked by `composer.lock` and `package-lock.json`. The documented foundation is
Laravel 13, PHP 8.4 locally, Blade, Livewire 4, Tailwind 3, Alpine, Vite 8, Pest 4 and
Filament 5 installed without an assumed panel. SQLite is used locally; production is
MySQL-compatible. Do not assume a package or panel is required merely because it is installed.

If a required command is missing, resolve the environment before changing application code.

## 6. Architecture and Implementation Rules

- Keep controllers thin: authorise, validate, dispatch and return a response.
- Put business transitions in focused Actions, services or domain classes.
- Authorise immediately before persistence; hidden controls are never security.
- Enforce organisation and site boundaries on every protected query and action.
- Do not trust client-mutated organisation, site, plot, request or source identifiers.
- Use transactions for multi-record actions and locking where concurrency can change an
  outcome.
- Preserve the canonical aggregate lock order where applicable: service → request →
  negotiation/amendment → proposal → history.
- Preserve bounded retry for transient MySQL concurrency failures and do not weaken race
  assertions merely to make tests pass.
- Preserve immutable history, attribution, timestamps and before/after values.
- Separate customer-visible messages from private Fenster reasons.
- Use structured validation, deliberate foreign keys/delete behaviour and appropriate indexes.
- Remain compatible with SQLite for ordinary development and MySQL 8.4 for production
  semantics. Concurrency and lock-sensitive work needs disposable MySQL evidence.
- Never edit a migration that may have run outside a disposable local database; add a new
  forward migration.
- Use Blade and Tailwind first, Livewire for useful server interaction and Alpine for light
  client behaviour. Do not add a large frontend framework without approval.
- Use mobile-first layouts, accessible names/states, large touch targets and more than colour
  alone to communicate status.
- Avoid N+1 queries and paginate potentially large result sets.

## 7. Authentication and Authorisation

The four CustomerApp roles are Site Manager, Assistant Site Manager, Finishing Foreman and
Fenster Office Staff. They are portal roles, never SiteApp roles.

- The three site roles have identical Version 1 permissions but remain distinct labels.
- External site users require an active account, customer organisation and assigned-site
  scope.
- Active Fenster Office Staff are globally scoped in the current approved model and may
  have no customer organisation. Global access comes from the valid Office Staff role,
  never merely from a null organisation.
- Public registration is disabled unless expressly approved.
- Development role preview is local/test only. It must not exist in production, create
  durable access or bypass normal authentication/authorisation.
- QR codes may identify a site in future but must never authenticate or authorise a user.

Use framework password hashing, password reset, CSRF, secure session and rate-limit
conventions. Never store plaintext credentials.

## 8. Request and Workflow Rules

Do not restate or infer workflow from historical code. Use `brief.md` for the current
customer lifecycle. In particular:

- services are Cavity Closers, Windows, Snagging and CML;
- eligibility is per plot and service;
- prevent more than one active request for the same plot and service;
- each request owns its status, requested/agreed dates, decisions and history;
- customer-owned date negotiation uses Requested Date, alternative proposals and Date Agreed;
- source completion has precedence and cannot be manufactured from Portal dates;
- amendments preserve the old agreed date and history; they never silently overwrite it;
- batch operations must not overwrite individually diverged request decisions;
- portal status must not mirror SiteApp workflow status.

Do not invent lead times, holidays, transition rules, CML wording or source mappings. Follow
the current brief and decision ledger, and record unresolved questions.

## 9. Source Import and Wald Safety

- CustomerApp's initial source route is private workbook upload and controlled review.
- CustomerApp must function without SiteApp API, database, filesystem, queue or runtime access.
- There is no Portal write-back to spreadsheets or SiteApp.
- Wald is deterministic, explainable and rules-based. Do not add external AI, LLMs,
  embeddings or third-party spreadsheet interpretation.
- Structural inference does not define business meaning. Only the approved data dictionary
  and human-confirmed mappings do.
- Use private source → analysis/clarification → neutral staging → authorised Portal review →
  explicit controlled commit. Inference must not write directly into final Portal models.
- Default every export to partial/filtered scope. Absence never proves deletion without an
  explicitly authorised complete snapshot.
- Preserve raw evidence privately and never expose filenames, worksheets, source rows,
  private metadata or internal errors to customers.
- Use stable external identifiers separately from local keys and make imports idempotent.
- Unknown required meanings, unresolved site identity or ambiguous call types block the
  dependent commit.
- Do not begin a Wald phase without its approved baseline manifest and scoped work package.

## 10. Notifications and Queues

- Dispatch workflow notifications from committed domain events, preferably after commit.
- Authorise notification lists and destinations; safe links do not replace route checks.
- Reading or dismissing a notification must not alter domain history.
- Completion currently sends no notification.
- Do not claim email/Resend or a persistent production queue worker exists without evidence.
- Queue-dependent work must document retry, idempotency and failure behaviour and be tested
  with the intended production queue model before release.

## 11. Required Verification

Run checks proportionate to the change and report the exact commands and results. Ordinary
application changes normally require:

```text
php artisan test
vendor\bin\pint --test
composer validate --strict
npm run build
composer audit
git diff --check
```

Also run focused tests first. Security, tenant boundaries, workflows, source imports,
notifications and integration failures require both successful and unauthorised/failure-path
tests. Locking, upsert or database-specific work requires disposable MySQL 8.4 verification.

Documentation-only work does not require the full application suite unless it changes an
executable artefact. It does require link/path checks, contradiction review, diff review and
whitespace validation.

Never weaken tests to make a result pass. Never claim a check, migration, build or deployment
succeeded unless it actually ran successfully.

## 12. Documentation Discipline

- Keep `brief.md` concise and current; do not append a second truth below stale text.
- Append durable decisions to `DECISIONS.md` and identify what they supersede.
- Keep current status at the top of `current_sprint.md`, `ROADMAP.md` and `HANDOVER.md`.
- Leave historical reports intact, but label them historical or superseded where ambiguity
  would otherwise affect current work.
- Use absolute dates and exact branch/SHA/deployment identifiers when known.
- Do not describe feature-branch work as released.
- Record unresolved business rules explicitly rather than inventing answers.

## 13. Completion Report

Every task report must include:

- **Completed** — outcome and scope;
- **Files changed** — exact task files;
- **Tests/checks** — commands and results;
- **Migrations/deployment** — what did or did not occur;
- **Notes** — blockers, unresolved decisions, unrelated changes preserved and manual actions.

Prefer simple, secure, auditable, maintainable and understandable changes. CustomerApp must
answer what is outstanding, what date was requested or agreed, what changed and what the
customer should do next—without drifting into SiteApp's operational domain.
