# Office Staff Organisation Model — Production Release Candidate

_Prepared: 27 August 2026_

## Release Identity

- Verified `origin/main` baseline: `9111d76ff05d702d68afd884ee8e42bc8e50c8e3`.
- Release branch: `release/office-staff-organisation-fix-2026-08-27`.
- Source correction and OSO-QA-01 fix: `ccb049d6006bd3a4029df1fe95fd9237cca0e0c9`.
- Transplanted runtime/test commit: `bd90cb8` (`fix: secure organization-free Office Staff access`).
- Source QA report: `27936f15d1bef756225dedfff1dd23e659ba5b5a`.
- Transplanted QA-report commit: `912da58` (`docs: record Office Staff organisation QA`).
- Verified release content before this release record: `912da58`.
- Final release branch SHA is the commit containing this record and is verified against the
  remote branch after push; it is reported in the release-preparation result because a Git
  commit cannot embed its own SHA.

The QA branch was not merged. The two approved commits were transplanted onto a clean branch
created directly from the verified production/main baseline, excluding its older Sprint 3E
release-gate ancestry.

## Included Files

- `app/Models/User.php`
- `app/Actions/CallOff/DetermineCallOffEligibilityAction.php`
- `database/factories/UserFactory.php`
- `tests/Feature/OfficeStaffOrganisationModelQaTest.php`
- `tests/Feature/SecureAccessFoundationTest.php`
- `tests/Feature/Sprint3aTargetDomainTest.php`
- `tests/Feature/OfficeStaffReviewRequestsTest.php`
- `tests/Feature/PortalNotificationsTest.php`
- `DECISIONS.md`
- `documentation/office-staff-organisation-model-2026-08-27.md`
- `documentation/office-staff-organisation-model-qa-2026-08-27.md`
- this release record

No Sprint 3F, workflow, migration, credential, backup, dump or unrelated Sprint report is
included.

## Access Model

- Active Fenster Office Staff may have `customer_organisation_id = NULL` and receive global
  Office authority from the recognised Office role.
- Historical Office Staff rows with an organisation remain valid and global.
- Site Manager, Assistant Site Manager and Finishing Foreman profiles still require a real
  customer organisation and current site assignment for site-scoped access.
- A null organisation never grants authority by itself.
- Inactive and unknown-role users fail closed.
- Projected-plot access evaluates valid global Office authority before applying the external
  customer/site containment branch.

## OSO-QA-01

The P1 correction is in source commit `ccb049d6006bd3a4029df1fe95fd9237cca0e0c9`
and release commit `bd90cb8`. `User::currentPortalRoleIdentifier()` rejects a loaded
`portalRole` relationship when its primary key does not equal the user's current
`portal_role_id`.

The reconstructed release proves that:

- Office Staff changed to Site User immediately lose Office authority;
- Site User changed to Office Staff does not gain authority through a stale Site User
  relationship before refresh; and
- an explicitly mismatched cached relationship fails closed.

No stale relationship can preserve or manufacture global Office access.

## Verification

- Focused Office organisation, authentication, review, notification and Sprint 3E suite:
  **111 tests, 543 assertions, 0 failures**.
- Full Pest suite: **219 passed, 15 skipped, 1,188 assertions, 0 failures**. The skipped tests
  are the existing environment-gated MySQL tests.
- Disposable SQLite `migrate:fresh --seed`: passed; all 11 migrations ran.
- Pint: passed.
- `composer validate --no-check-publish`: passed.
- `composer audit`: passed with no known PHP security advisories.
- Vite 8.1.4 production build: passed.
- `git diff --check`: passed.
- Locked frontend install reported the pre-existing npm advisory baseline of one moderate
  and one high advisory; this release changes neither `package.json` nor `package-lock.json`.

No additional MySQL gate is required: the release has no migration, schema, index, foreign
key, locking, transaction or database-engine-specific query change.

## Migration and Production Impact

`git diff origin/main...HEAD -- database/migrations` is empty. Production already has 11
applied migrations, so the expected Forge migration result is `Nothing to migrate`.

The runtime impact is limited to role/profile validation and projected-plot authorisation.
It enables a legitimate Office Staff account with no fake customer organisation while
preserving external customer and site isolation.

## Approved Rollout Sequence

1. Obtain explicit merge and deployment approval for this exact remote branch SHA.
2. Merge only this branch into current `main`; stop if `main` changes unexpectedly.
3. Deploy through the existing Forge zero-downtime process.
4. Verify the active release SHA, production environment, maintenance state and expected
   `Nothing to migrate` result.
5. Run proportional unauthenticated and log checks.
6. Only then provision `np@fensteruk.net` as active Fenster Office Staff with a null customer
   organisation, using the approved secure password-reset path.
7. The user authenticates personally before the read-only production smoke test resumes.

No merge, deployment, Forge change or production-data change was performed while preparing
this release candidate.
