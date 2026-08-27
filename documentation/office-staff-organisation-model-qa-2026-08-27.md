# Office Staff Organisation Model QA Report

_27 August 2026_

## Overall Result

**PASS after one P1 security correction.** The Office Staff organisation model is safe to
prepare for production release. No deployment, production account, production data, `main`
merge or `main` push was performed.

## Candidate

- QA branch: `codex/qa-office-staff-organisation-model-2026-08-27`
- Reviewed correction and QA-fix commit: `ccb049d6006bd3a4029df1fe95fd9237cca0e0c9`
- Production/main baseline: `9111d76ff05d702d68afd884ee8e42bc8e50c8e3`
- Candidate parent before the correction: `22f9746e416ca43e88662c90f76ff4080b0e5995`

The QA branch contains earlier non-production Sprint 3E release-gate history and must not
be merged wholesale. Release preparation should start from current `main` and apply only
the reviewed correction commit above. The pre-existing unrelated whitespace edit to
`documentation/sprint-3e-date-negotiation-report.md` was not staged or committed.

## Authentication Matrix

| Profile | Result |
|---|---|
| Active Office Staff, null organisation | PASS — authenticates and routes to Review Requests |
| Active Office Staff, historical organisation | PASS — remains valid and globally authorised |
| Inactive Office Staff, null organisation | PASS — login and protected access denied |
| Site Manager, valid organisation and assigned site | PASS |
| Assistant Site Manager, valid organisation and assigned site | PASS |
| Finishing Foreman, valid organisation and assigned site | PASS |
| Any external role, null organisation | PASS — incomplete profile; login denied |
| External role, valid organisation but no assignment | PASS — login allowed; no site access |
| Unknown role, null organisation | PASS — incomplete profile; login denied |
| Unauthenticated protected request | PASS — redirected to login |

The complete-profile decision is role-specific. A null organisation is never itself an
authority signal.

## Office Staff Global Access

PASS. A null-organisation Office Staff fixture accessed the global Review Requests queue,
request details and projected-plot gate across two unrelated customers and sites. Global
review, account-management and site-assignment gates were allowed by the Office role, not
by a customer relationship. Office agreement of a requested Sprint 3E date also passed.

## External Customer Isolation

PASS. All three external roles were exercised against direct cross-customer plot and
call-off UUIDs. Plot details, customer request details, Office review and management gates
remained denied. Cross-customer site assignments did not confer access because the
customer-organisation check remains mandatory.

## Site Assignment Isolation

PASS. For each external role, an assigned site was allowed while an unassigned site in the
same customer was denied. Users with a valid customer organisation but no site assignment
could authenticate only to the empty assigned-site screen and could not select or access a
site. Null-organisation Office Staff remained global without site assignments.

## Projected Plot Authorization

PASS. `DetermineCallOffEligibilityAction::canViewProjectedPlot()` now evaluates the Office
role before the external tenant/site branch. Office Staff with no organisation are allowed;
external users still require a recognised Site User role, matching customer organisation
and current site assignment. Null-organisation external users, inactive users, wrong-site
users and wrong-customer users were denied. Direct public UUID substitution returned no
target details.

## Sprint 3E Authorization

PASS. Null-organisation Office Staff could propose an alternative and agree a requested
date globally. Assigned Site Users could accept an alternative only for their site.
Wrong-customer and unassigned Site Users could not perform customer actions, and Site Users
could not perform Fenster actions. The Sprint 3E state machine was not changed.

## Notifications

PASS. Active null-organisation Office Staff received global Office notifications and their
safe links resolved to authorised review details. Inactive Office Staff and invalid
null-organisation external profiles did not receive Office notifications. External
notification reads and links retain recipient, role, customer and assignment checks.

## Account Provisioning

PASS for the currently implemented provisioning boundary. A Nick-like local Office Staff
fixture was created active, with the Office role, a null organisation and a framework-hashed
password. It authenticated normally, routed to Review Requests, preserved the unique-email
database control and created no fake customer organisation. External accounts without a
customer organisation failed the profile and login guards.

There is no implemented customer-account administration endpoint or account form in this
candidate, so no nonexistent UI validation contract was invented. The existing
`manage-portal-accounts` and `manage-site-assignments` gates were verified.

## Role Tampering

PASS after OSO-QA-01. Null organisation plus any external role, null organisation plus an
unknown role, inactive Office Staff and external users attempting Office gates were denied.
Office Staff with a historical customer association remained valid. Changing a loaded
Office Staff model to Site Manager while its organisation remained null now invalidates the
profile and all global gates immediately, before a model refresh or later request.

## Historical Compatibility

PASS. Existing Office Staff rows with a valid historical customer organisation continue to
authenticate and receive role-based global authority. No migration, data rewrite or forced
nulling was added.

## Customer List and UI Regression

PASS within the implemented UI. Review Requests and request-detail Blade responses rendered
for null-organisation Office Staff without null-reference errors and showed real customer
and site relationships. Provisioning an Office fixture did not create a customer row, blank
customer entry or internal sentinel customer. No Office account-management screen currently
exists to regress.

## Null Safety

PASS. Application, route and view references to `customer_organisation_id` and
`customerOrganisation` were audited. Authenticated Office paths do not dereference a user
customer relationship. Customer labels are reached through `Site`, whose customer foreign
key remains non-null. Navigation, dashboard routing, review, notification query/link,
history and call-off eligibility paths handled the null Office relationship safely.

## Defects Found

| ID | Severity | Finding | Correction |
|---|---|---|---|
| OSO-QA-01 | P1 | After an already-loaded Office Staff model was changed to a Site User, the cached `portalRole` relationship could retain Office authority in the same request until refresh. | Role checks now fail closed when the loaded role key does not match the current `portal_role_id`. A regression test proves immediate loss of profile validity and global gates. Fixed. |

No P0 defects were found. No P1 defect remains open.

## Full Tests

- Initial implementation-focused baseline: **69 tests, 269 assertions — passed**.
- Dedicated hostile Office Staff suite: **16 tests, 139 assertions — passed** after
  OSO-QA-01 was corrected.
- Combined authentication/domain/review/notification/Sprint 3E focused suite:
  **111 tests, 543 assertions — passed**.
- Full Pest suite after clean migration/seed: **219 passed, 15 skipped, 1,188 assertions,
  0 failures**.

The 15 skipped tests are the existing environment-gated MySQL tests; no skip was added or
weakened for this correction.

## Build / Audit / Pint

- `php artisan migrate:fresh --seed` against confirmed local SQLite: passed; all 11
  migrations subsequently reported as ran.
- `vendor\bin\pint --test`: passed.
- `composer validate --no-check-publish`: passed.
- `composer audit`: passed; no known security advisories.
- `npm run build`: passed. The restricted first attempt hit the known Windows `spawn EPERM`
  limitation; the approved local rerun completed with Vite 8.1.4.
- `git diff --check`: passed.

## MySQL

No targeted MySQL verification was required. This correction adds no migration, schema,
index, foreign key, lock, transaction or database-specific query. The changed runtime
behaviour consists of role/profile decisions and ordering the existing role branch before
external containment. No production database was contacted.

## Release Recommendation

The correction is safe to prepare for production. Create a release branch from current
`main` at `9111d76ff05d702d68afd884ee8e42bc8e50c8e3`, apply only
`ccb049d6006bd3a4029df1fe95fd9237cca0e0c9`, and repeat the proportional release checks.
Do not merge this QA branch wholesale, deploy, or create the production Office Staff
account as part of this QA result.

Office Staff organisation model QA passed — ready for production release preparation
