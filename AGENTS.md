# Fenster Customer Portal — Agent Instructions

## Mandatory Context

Before work, read in this order:

1. `AGENTS.md`
2. `brief.md`
3. `ROADMAP.md`
4. Any task-specific schema, design, integration or prototype documentation

All work must align with the Customer Portal brief, current roadmap milestone, customer data boundaries and separation from SiteApp.

If documentation conflicts with the repository or a newer user instruction, identify the conflict before destructive or wide-ranging changes.

## Workspace Rules

- Work only inside the Customer Portal project directory: `C:\Users\JoshO\Documents\CustomerApp`.
- Do not modify SiteApp unless explicitly requested as a separate task.
- Preserve user changes and avoid unrelated refactors.
- Prefer small, safe and reviewable changes.
- Do not copy SiteApp models, migrations, services, policies or UI merely because they exist.
- Treat all customer and project data as confidential.

## Critical Product Boundary

The Customer Portal is a separate customer-facing communication and request application.

SiteApp remains Fenster's operational system.

The portal may display authorised information, show outstanding plots, collect requests and amendments, receive decisions and show customer-facing progress.

It must never become an operational management system.

## Prohibited SiteApp Duplication

Do not introduce or recreate:

- workflow stages, trade sequencing or dependencies;
- trade sign-offs or Black Hat approvals;
- readiness or build verification;
- Site Manager, Build Manager, Trade Operative, Team Leader or Office User roles;
- SiteApp workflow policies;
- SiteApp Filament resources or administration;
- SiteApp queries, templates, workflow issues or internal notes;
- trade assignments, labour planning or manufacturing planning;
- SiteApp database tables, domain models, services or internal statuses.

Portal-specific approval screens are allowed only for portal requests.

## Communication Rules

- Report completion, files changed, tests, failures, migrations, blockers and manual actions.
- Do not claim a command, test, build, migration or integration succeeded unless it ran successfully.
- State when work depends on unresolved business rules, credentials or an integration contract.
- Do not invent SiteApp endpoints, fields or status mappings.

Preferred final format:

- Completed
- Files changed
- Tests
- Notes

## Local Development and Stack

Development is on Windows with Laravel Herd.

Use the versions locked in `composer.json`, `package.json` and lockfiles. After project creation, update this section with the exact versions.

Installed foundation:

- PHP 8.4.23
- Composer 2.10.1
- Laravel 13.20.0
- Blade
- Filament 5.6.8 (installed only; no panel assumed)
- Laravel Breeze 2.4.2 (installed only; no authentication scaffolding generated)
- Tailwind CSS 3.4.19
- Alpine.js 3.15.12
- Livewire 4.3.3
- SQLite locally
- MySQL-compatible production database
- Pest 4.7.5 with Pest Laravel Plugin 4.1.0
- Laravel Pint 1.29.3
- Vite 8.1.4

Do not upgrade packages, alter lockfiles, add a large frontend framework, add unnecessary runtime dependencies or assume Filament is required unless explicitly requested.

## Version 1 Priorities

- secure foundation and authentication;
- customer organisation isolation;
- portal-specific roles and permissions;
- authorised developments and outstanding plots;
- date requests for Cavity Closers, Windows and CML;
- portal approval;
- amendments and revision history;
- notifications and customer-facing statuses;
- responsive dashboard;
- safe SiteApp integration boundary;
- automated security and lifecycle tests.

Avoid speculative microservices, AI scheduling, manufacturing calculations, labour planning, generic CRM features, native apps and unrelated SiteApp functionality.

## Request Rules

- Cavity Closers, Windows and CML are independent services.
- Customers may only request dates for authorised outstanding plots.
- Completed plots must reject new requests.
- Prevent more than one active request for the same plot and service.
- Changes create amendments or revisions, not duplicate active requests.
- A requested date is not confirmed until an authorised internal portal user publishes a decision.
- Do not invent lead times or scheduling rules.
- Bulk requests must remain traceable per plot and service.

## Approval and Amendment Rules

Internal Administrators may confirm, propose a revised date or reject.

- Keep transitions in actions, services or domain classes, not views.
- Authorise immediately before persistence.
- Record user, timestamp and previous values.
- Separate customer-visible notes from private internal reasons.
- Do not calculate operational availability in the portal.
- Amendments preserve prior state and return to review.
- Never silently overwrite dates.
- Block amendments after completion unless an explicit reopen process exists.

## Customer-Facing Status Rules

Initial statuses may include Submitted, Confirmed, In Production, Delivery Made, Installation Planned, Installation Complete, Amendment Requested, Revised Date Proposed and Rejected.

These are portal statuses, not SiteApp workflow statuses.

Centralise labels, icons, colours, ordering and transitions. Do not assume every transition is valid. Do not rely on colour or icons alone.

## Permissions and Authentication

Initial portal roles:

- Customer User
- Customer Administrator
- Internal Administrator

Use policies, gates, middleware, scopes or explicit checks. Hidden buttons are not security.

Enforce customer organisation boundaries on every protected query and action. Customers must never access another customer's data.

Disable public registration unless approved. Use framework password hashing, reset, session, CSRF and rate-limit conventions. Never store plaintext credentials. MFA and SSO remain future features.

## Integration Rules

- SiteApp is authoritative for operational data.
- Do not invent APIs or directly query SiteApp's database without explicit approval.
- Prefer documented APIs, events or synchronisation adapters.
- Store external identifiers separately from local primary keys.
- Make updates idempotent where practical.
- Record failures safely and never show failed submissions as accepted.
- Use least-privilege credentials and never commit secrets.
- Do not expose SiteApp errors or internal paths to customers.

## Laravel, Frontend and Database Rules

- Follow repository conventions and keep controllers thin.
- Use structured validation and server-side authorisation.
- Keep transitions, approvals, revisions, integration and notifications out of Blade views.
- Use transactions for multi-record operations.
- Use Blade and Tailwind first, Livewire for useful server-driven interaction and Alpine.js for lightweight behaviour.
- Do not trust client-mutated identifiers.
- Paginate large datasets and avoid N+1 queries.
- Use mobile-first layouts, large touch targets and accessible states.
- Use migrations, foreign keys, deliberate delete behaviour and suitable indexes.
- Aim for Third Normal Form.
- Separate current state from immutable revision history where needed.
- Remain compatible with SQLite locally and MySQL in production.
- Never store plaintext secrets or tokens.

## Security, Audit and Testing

- Enforce tenant boundaries server-side and protect against insecure direct object references.
- Safely render user and integration content.
- Do not expose traces, credentials, environment values or provider responses.
- Preserve attribution, timestamps and before/after values for requests, decisions, amendments, acknowledgements and customer-facing status changes.
- Do not expose SiteApp internal audit records.
- Use Pest and test authentication, customer isolation, permissions, completed-plot restrictions, duplicate prevention, approvals, amendments, history, statuses, notifications and integration failures.
- Test unauthorised paths as well as successful ones.
- Run relevant tests, Pint and the frontend build when applicable.
- Never weaken tests merely to make work pass.

## Documentation and Git Safety

- Keep `brief.md` as the scope source of truth.
- Keep `ROADMAP.md` aligned with actual progress.
- Record unresolved rules instead of inventing answers.
- Inspect `git status` before and after work.
- Preserve unrelated changes.
- Never reset, clean or force-checkout without permission.
- Never commit `.env`, credentials, customer data or secrets.

## Final Principle

Prefer simple, secure, auditable, maintainable and understandable changes.

The portal should clearly answer:

- Which plots remain outstanding?
- What date did the customer request?
- Has Fenster confirmed, revised or rejected it?
- What changed and when?
- What customer-facing stage is it at?
- What should the customer do next?

Never allow the Customer Portal to drift into SiteApp's internal operational domain.
