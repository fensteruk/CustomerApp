# Main branch reconciliation — 25 August 2026

## Scope and starting state

This is a Git/release-management reconciliation only. No product feature, migration,
production database, Forge configuration or deployment action was performed.

| Item | Verified value |
|---|---|
| Starting local `main` | `f801c91113bd13656c6cbffdd3d82c12d4a95846` |
| Starting `origin/main` | `f801c91113bd13656c6cbffdd3d82c12d4a95846` |
| Production served commit | `f801c91113bd13656c6cbffdd3d82c12d4a95846` |
| Production release | Forge release `75938097` |
| Reconciliation branch | `reconcile/main-2026-08-25` |

Remote references were refreshed with `git fetch --all --prune` before the branch was
created from the verified current `main`.

## Branches and ancestry inspected

| Branch | HEAD | Relationship to starting `main` | Classification |
|---|---|---|---|
| `main` / `origin/main` | `f801c91113bd13656c6cbffdd3d82c12d4a95846` | baseline and deployed | A |
| `release/production-migration-repair-2026-08-21` | `e91161b3ecfaf0d1a46407cea2d46c3c04463900` | one documentation commit ahead | D |
| `migration-000002-repair-candidate` | `064bf43347a2e7958e8a33a653eca7ec2f07226e` | repair commits already represented on `main`; branch also has rehearsal documentation | E for duplicate repair code |
| `release-candidate/sprint-3d` | `47e8ccc2f260e38696787bee773d8b7700d74dd7` | one later Sprint 3E implementation commit ahead of the Sprint 3D QA checkpoint | C |

Git ancestry checks confirm that the Sprint 3A–3D checkpoint `10be06f`, Sprint 3D QA
correction `34531d2`, Sprint 3D QA checkpoint `8853b15`, and the deployed repair commits
through `1fe5293` are ancestors of `main`. Sprint 3E commit `47e8ccc` is not an ancestor
of `main`.

## Approval register

| Category | Commit/change-set | Evidence | Action |
|---|---|---|---|
| A — production-approved | Sprint 3A–3D checkpoint and Sprint 3D QA corrections | dedicated Sprint 3D QA report; deployed as part of `f801c91` | retained on baseline |
| A — production-approved | MySQL migration repairs (`d5f5592` through `1fe5293`) | restored-partial, clean and normal-upgrade MySQL rehearsal; Forge deployment `75938097` | retained on baseline |
| C — awaiting required QA | Sprint 3E, `47e8ccc` | `documentation/sprint-3e-date-negotiation-report.md` says ready for dedicated QA; no dedicated PASS report exists | excluded |
| D — current documentation | `e91161b` controlled production-deployment record | records the successful actual deployment and does not change runtime code | cherry-picked |
| D — current documentation | operational status corrections in `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md` | corrects the historic claim that Sprint 3E had not begun while preserving that history | added |
| E — duplicate/superseded | older repair-candidate commits | equivalent repaired migration blobs are already on `main` | not replayed |

No QA approval was inferred from the Sprint 3E test results alone.

## Sprint 3E decision

Sprint 3E has backend/UI implementation, tests and an implementation report on
`release-candidate/sprint-3d`; it is committed and pushed. Its report explicitly classifies
the work as ready for dedicated QA, not QA-approved. There is no separate dedicated QA PASS
record in the repository. The entire Sprint 3E commit is therefore excluded; no partial
subset was merged.

## Migration-repair protection

The following production-safe migration blobs are identical on reconciliation baseline,
the production-repair branch and the original repair-candidate branch:

- `2026_08_05_000002_create_call_off_domain_tables.php` — `6b21361be77eb821db40333c7b161905a8cb2e0a6`;
- `2026_08_07_000003_create_portal_notifications_table.php` — `c00327aa9398cb44f9c24cda5af987e61ed1cc0a`;
- `2026_08_20_000005_add_target_call_off_domain_foundation.php` — `7f5f2ce2c9f44747a72b6910832c5a43ec3da0a6`;
- `2026_08_20_000007_add_source_projection_import_contract.php` — `144929c371e513f9e69de332ad3d6d943cceba8d`.

There is no migration change on this reconciliation branch. The prior MySQL proof remains
applicable to the exact same migration/application code: clean install, normal base-plus-
000001 upgrade and restored production partial-state repair all passed, including the
ledger, foreign-key and named-index assertions.

A fresh disposable MySQL re-run was not possible: the previous `customerapp_releaseproof_*`
databases and rehearsal directories are no longer present. This is recorded as unavailable,
not represented as a fresh MySQL pass.

## Verification

| Check | Result |
|---|---|
| Local SQLite `migrate:fresh --seed` | passed |
| Disposable SQLite migrate/reset/reapply verifier | passed |
| Local migration status | all 11 migrations ran, including 000002–000008 |
| Full Pest suite | 171 tests, 884 assertions passed |
| Focused Sprint 3A–3D suite | 59 tests, 310 assertions passed |
| Composer validation | passed |
| Composer audit | no advisories |
| `npm ci` | passed |
| Production asset build | passed outside the Windows sandbox; sandbox attempt hit known `EPERM` spawn restriction |
| Production npm audit | no advisories with `--omit=dev` |
| Full npm audit | 1 high and 1 moderate development-tooling advisory; no automatic fix applied |
| Laravel cache/deployment compatibility | optimise clear, config cache, route cache, view cache and route list passed; local caches cleared afterwards |
| Pint, whitespace and conflict markers | passed; no unresolved markers |

## Documentation reconciliation

`current_sprint.md`, `ROADMAP.md` and `HANDOVER.md` now state the current release truth:
Sprint 3D is deployed with the migration repair, while Sprint 3E exists only on an
unapproved side branch. Historic sprint/QA records were not rewritten. `DECISIONS.md` was
reviewed but did not require a new product or release-management decision.

## Delta and Forge implication

The reconciliation branch adds only deployment/release documentation and the operational
status corrections above. It adds no application code, migrations, dependencies, routes,
assets or configuration. There were no merge conflicts.

Forge is configured to deploy `fensteruk/CustomerApp` branch `main`; deployment `75938097`
is recorded as a push-to-deploy deployment. Consequently, pushing a merge to `main` must be
treated as a production deployment event. This reconciliation branch must be pushed and
reviewed before any explicit `main` merge/push approval.

## Recommended next action

Push `reconcile/main-2026-08-25` only, verify its remote SHA, obtain review/approval, then
perform no further action until explicit approval is given for a controlled merge/push to
`main`.
