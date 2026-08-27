# Office Staff Organisation Model — 27 August 2026

## Problem

Production provisioning for a Fenster Office Staff account was blocked because application
profile validation required every user to have a `customer_organisation_id`. Creating a
fake customer organisation would have hidden the defect and contradicted the global Office
Staff access model. No production account or organisation was created.

## Architecture evidence

- The model and table are explicitly named `CustomerOrganisation` and
  `customer_organisations`; each `Site` must belong to one customer organisation.
- The organisation entity has no type/kind discriminator and no supported concept of an
  internal organisation. Adding Fenster would therefore make it indistinguishable from a
  customer and eligible to appear in customer/site data paths.
- The initial access migration already made `users.customer_organisation_id` nullable.
  The database does not require an organisation for every user.
- The management contract and target-domain documentation define Office Staff as global,
  role-authorised internal users. Site assignment and customer tenancy remain external
  Site User boundaries.
- Review queries, Office notification recipients and Office actions are globally scoped by
  the `fenster_office_staff` role; they do not require a customer organisation.

The blocker was therefore application-level historical technical debt in
`User::hasCompletePortalProfile()`, not a missing database feature.

## Decision

Use Option A: Fenster Office Staff do not require a customer organisation.

The complete-profile rule is now role-specific:

- active Fenster Office Staff require a valid Portal role and may have
  `customer_organisation_id = NULL`;
- active Site Manager, Assistant Site Manager and Finishing Foreman users require a valid
  Portal role and a real customer organisation;
- unknown roles, missing roles and inactive users remain incomplete and cannot use the
  authenticated Portal.

Existing Office Staff rows that currently reference a customer organisation remain valid.
No historical relationship is rewritten by this correction.

## Rejected alternative

Option B, creating a Fenster organisation, is not supported by the current domain. There
is no generic organisation abstraction or internal/customer classification. Introducing a
Fenster row would make an internal entity look like a customer without a confirmed need for
internal organisation ownership, reporting or hierarchy.

## Implementation

- Made complete-profile validation explicitly role-aware.
- Corrected projected-plot view authorisation so the global Office role is evaluated before
  external customer/site containment.
- Updated the user factory so new Office Staff fixtures default to no customer organisation.
- Retained customer/site checks in `User::canAccessSite()` and all external-site paths.

No migration was added. The production schema already has the correct nullable foreign key,
and all customer sites continue to require a customer organisation.

## Security and provisioning evidence

Focused tests prove:

- a nullable-organisation Office Staff account is password-hashed, authenticates and routes
  to the global Review Requests dashboard;
- it can see sites and review records across customer organisations, use global account/site
  management gates and receive Office notifications;
- direct projected-plot authorisation is global for Office Staff;
- every external role fails complete-profile and authentication checks without a customer
  organisation;
- existing Office Staff with a historical customer relationship remain compatible;
- external assigned-site and cross-customer isolation remains unchanged.

## Production rollout

Before creating Nick Powder's production account:

1. QA and release this application correction through the normal non-production gates.
2. Deploy the verified release; no database migration is required.
3. Create the account with the Fenster Office Staff role, active status, a hashed password
   workflow and `customer_organisation_id = NULL`.
4. Perform the previously planned read-only authenticated production smoke test.

Do not create a production customer organisation for Fenster and do not create the account
before the corrected application is deployed.

## Verification

Verification completed on 27 August 2026:

- focused authentication, global Office access, direct authorisation, review and
  notification tests: 69 passed, 269 assertions;
- clean local SQLite migration and seed: passed, with no pending migration afterward;
- full Pest suite: 203 passed, 15 skipped, 1,049 assertions;
- Pint, Composer validation, Composer security audit, frontend production build and
  `git diff --check`: passed;
- Composer reported no known security vulnerability advisories.

No production environment, production account or production organisation was changed.
