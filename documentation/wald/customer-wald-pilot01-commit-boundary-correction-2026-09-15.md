# CUSTOMER-WALD-PILOT01 — Commit Boundary Correction

Date: 15 September 2026.

## Result

**IMPLEMENTED_AND_REQUALIFIED_LOCALLY — PRODUCTION_REMAINS_DISABLED.**

The management-supplied production activation report records that deployed SHA
`8a5a2384dd32c89852172c8dd99ffaba880dc698` passed the controlled pilot through approval,
then safely refused its one fictional site commit with `commit_requires_top_level_boundary`.
The environment hard-off was restored immediately. No receipt, projection or Portal workflow
record changed.

This correction is on local non-deploying branch
`codex/fix-wald-pilot-commit-boundary-2026-09-15`. It is not on `main`, not deployed and not
authority to change either production enable control.

## Cause and bounded correction

`OfficePilotImportController::commit()` entered `CommitAttemptJournal` and invoked
`ImportReview::commit()` inside its callback. `ImportReview` delegates to `ImportStore`, which is
already the domain owner of that journal boundary. Production correctly rejected the nested
entry before any business write.

The controller now retains validation, current-user resolution, authorised pilot selection and
conflict presentation, but delegates directly to `ImportReview::commit()`. `ImportStore` remains
the sole journal owner. No migration, schema, model, policy, guard, transaction, retry,
idempotency, projection, receipt or customer workflow rule changed.

## Regression evidence

The new synthetic regression exercises the authenticated pilot commit route and proves one
attempt, one successful immutable outcome, one receipt and one committed selection. A separate
disposable MySQL 8.4.11 case temporarily runs the application as non-testing for the request, at
transaction level zero, so `CommitAttemptJournal`'s unit-test exception cannot mask nesting.

Before the controller correction, that exact MySQL case failed with the production message:

`The supervised pilot stopped safely: Commit Requires Top Level Boundary.`

After the correction it passed: 1 test, 11 assertions. The wider evidence is:

- focused Wald pilot SQLite: 8 tests, 7 passed, 1 expected MySQL-only skip, 64 assertions;
- full application SQLite: 1,573 tests, 1,495 passed, 78 environment-dependent skips,
  7,853 assertions;
- established disposable MySQL 8.4.11 audit/concurrency gate: 23 tests, 1,495 assertions;
- Pint: pass;
- strict Composer validation: pass;
- Composer security audit: no advisories;
- production frontend build with Vite 8.1.4: pass after rerunning outside the Windows process
  sandbox that initially returned `spawn EPERM`;
- Git whitespace validation: pass.

All test workbook data added by this correction is synthetic. The real RedZebra workbook and the
user's unrelated local files were not added, edited or staged. The two purpose-created disposable
MySQL schemas were removed after the gate and the local MySQL 8.4.11 server was shut down normally.

## Release boundary

Production must remain with `WALD_IMPORT_AVAILABLE=false`. The audited application setting may
remain enabled because the environment hard-off makes effective availability false. The refused
attempt must remain unchanged as production audit evidence.

The remaining production release steps require separate approval: merge and deploy this
correction, verify the served SHA and health, establish a fresh backup, prepare a fresh fictional
one-site preview, then repeat the supervised commit, result-reload idempotency, selected-site
effect and sibling/Portal isolation checks. The two absent external dummy roles also remain a
live-smoke coverage gap. No production action was performed by this correction task.
