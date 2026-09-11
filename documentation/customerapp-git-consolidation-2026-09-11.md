# CUSTOMER-GIT-CLEANUP01 — Git consolidation record

Date: 11 September 2026

## Result

Local consolidation is substantially complete. The accepted executable local
`main` checkpoint is `93737df1e8dae36b9d79b6b641e30b6c9af51908`; this record adds only
documentation above it. `origin/main` remains
`e757bb651f9aa95d67b808a97433d27bc29d03c3`. No push to `main`, deployment,
production access, merge or executable/schema change occurred.

The canonical unreleased Wald line is `feature/customer-wald-next` at
`c9fe0620069a10fe07050e4ec2f30043a9a9a1ec`. It contains accepted WALD02
`4aa5ffb`, WALD03 `a80ce7d`, WALD04 `0e83eb2`, corrected WALD05 executable
`dbd17c6` and the accepted WALD05 QA handoff `c9fe062`. It does not contain the
superseded manual-import lineage rooted at `04b560f`.

CUSTOMER-GIT-CLEANUP02 subsequently published the canonical Wald branch and all
12 preservation tags after exact target/ancestry verification. Ten preserved
non-main remote branches were deleted by ordinary remote branch deletion.
`origin/main` remained unchanged throughout.

## Branch policy

- `main` is the accepted production/release product line. A local commit is not
  production truth until a successful production deployment is evidenced.
- Feature branches are short-lived and removed after accepted outcomes are
  consolidated.
- QA and release branches are removed after acceptance and preservation.
- Accepted unreleased Wald work lives only on `feature/customer-wald-next`.
- Historical milestones use annotated tags rather than permanent branches.
- Pushing `main` remains a separate production action because Forge Push to
  Deploy is enabled.

## Initial local branch classification

`In LM` and `In OM` mean the initial tip was an ancestor of prepared local
`main` and `origin/main`. `Ahead` is the number of commits reachable from that
branch but not from prepared local `main` at audit time.

| Branch | Tip | Upstream | Merge base with local main | In LM | In OM | Ahead | Class | Preservation / disposition |
|---|---|---|---|---:|---:|---:|---|---|
| `codex/docs-customer-wald04-scope-2026-09-08` | `0309802` | — | `20d5f123` | No | No | 40 | SUPERSEDED | `customerapp-wald04-scope-archive` |
| `codex/docs-customer-wald05-scope-2026-09-08` | `877bd3f` | — | `20d5f123` | No | No | 47 | SUPERSEDED | Canonical Wald / Wald accepted tag |
| `codex/qa-office-staff-organisation-model-2026-08-27` | `27936f1` | — | `20d5f123` | No | No | 25 | SUPERSEDED | Canonical Wald; product outcome also in main |
| `codex/qa-sprint-3e-date-negotiation` | `a38d557` | `origin/codex/qa-sprint-3e-date-negotiation` | `8853b159` | No | No | 2 | ARCHIVE_ONLY | `customerapp-sprint3e-qa-archive` |
| `docs/customer-admin-site01-audit-2026-09-09` | `2c4e870` | — | `20d5f123` | No | No | 58 | SUPERSEDED | Next-release integration archive |
| `docs/customer-admin01-audit-2026-09-09` | `1dc6ee6` | — | `20d5f123` | No | No | 53 | SUPERSEDED | Canonical Wald; retained locally only because its worktree contains an untracked user document |
| `docs/customer-wald03-scope-2026-09-08` | `76ed196` | — | `20d5f123` | No | No | 32 | SUPERSEDED | `customerapp-wald03-scope-archive` |
| `docs/customerapp-project-reset-2026-09-04` | `e84999c` | — | `20d5f123` | No | No | 26 | SUPERSEDED | Canonical Wald / integration archive |
| `feature/admin-site02a-customer-site-backend-security` | `8c7998a` | — | `20d5f123` | No | No | 64 | SUPERSEDED | Next-release integration archive; accepted outcome rebuilt in main |
| `feature/customer-admin-site02` | `13897db` | — | `ac250a8e` | No | No | 4 | SUPERSEDED | Next-release integration archive; accepted outcome rebuilt in main |
| `feature/customer-next-release-master` | `b94a09e` | — | `e757bb65` | No | No | 76 | SUPERSEDED | `customerapp-next-release-integration-archive` |
| `feature/customer-ui-sidebar-workspace` | `307e3fa` | — | `ac250a8e` | No | No | 3 | SUPERSEDED | Next-release integration archive; accepted outcome rebuilt in main |
| `feature/customer-wald02-portable-core` | `9980354` | — | `20d5f123` | No | No | 30 | SUPERSEDED | Canonical Wald |
| `feature/customer-wald03-business-dictionary` | `574f196` | — | `20d5f123` | No | No | 36 | SUPERSEDED | Canonical Wald |
| `feature/customer-wald04-knowledge-profiles` | `2c7d015` | — | `20d5f123` | No | No | 42 | SUPERSEDED | Canonical Wald |
| `feature/customer-wald05-import-review-integration` | `1dc6ee6` | — | `20d5f123` | No | No | 53 | SUPERSEDED | Canonical Wald |
| `feature/deterministic-spreadsheet-interpreter` | `a013ed1` | — | `0873bac7` | No | No | 6 | ARCHIVE_ONLY | `customerapp-manual-import-archive` |
| `feature/manual-source-import-backend` | `04b560f` | `origin/feature/manual-source-import-backend` | `0873bac7` | No | No | 2 | ARCHIVE_ONLY | `customerapp-manual-import-archive` |
| `feature/manual-source-import-ui` | `1e8c22b` | — | `0873bac7` | No | No | 7 | ARCHIVE_ONLY | `customerapp-manual-import-archive` |
| `feature/sprint-3f-date-amendments` | `60aa9e2` | — | `60aa9e2` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `fix/office-queue-card-request-values` | `4c33608` | — | `4c33608` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `hotfix/date-agreed-office-filter-2026-08-27` | `0873bac` | `origin/hotfix/date-agreed-office-filter-2026-08-27` | `0873bac` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `main` | `93737df` | `origin/main` | `93737df` | Yes | No | 0 | ALREADY_IN_MAIN | Retained executable checkpoint; 15 commits ahead of origin before this documentation record |
| `migration-000002-repair-candidate` | `064bf43` | `origin/migration-000002-repair-candidate` | `8853b159` | No | No | 7 | ARCHIVE_ONLY | `customerapp-migration-000002-repair-candidate-archive` |
| `qa/customer-wald02-2026-09-08` | `4aa5ffb` | — | `20d5f123` | No | No | 31 | SUPERSEDED | Canonical Wald |
| `qa/customer-wald03-2026-09-08` | `a80ce7d` | — | `20d5f123` | No | No | 38 | SUPERSEDED | Canonical Wald |
| `qa/customer-wald04-2026-09-08` | `0e83eb2` | — | `20d5f123` | No | No | 44 | SUPERSEDED | Canonical Wald |
| `qa/customer-wald05-backend-2026-09-09` | `c9fe062` | — | `20d5f123` | No | No | 57 | SUPERSEDED | Replaced by canonical Wald branch at the identical tip |
| `qa/customerapp-next-release-admin-demo-2026-09-10` | `de1cfbb` | — | `de1cfbb` | Yes | No | 0 | ALREADY_IN_MAIN | Removed locally |
| `reconcile/main-2026-08-25` | `a43a739` | `origin/reconcile/main-2026-08-25` | `20d5f123` | No | No | 1 | ARCHIVE_ONLY | `customerapp-main-reconciliation-2026-08-25` |
| `release-candidate/sprint-3d` | `47e8ccc` | `origin/release-candidate/sprint-3d` | `8853b159` | No | No | 1 | ARCHIVE_ONLY | `customerapp-sprint3d-release-candidate` |
| `release/customerapp-2026-09-09-rc1` | `2bad42a` | — | `2bad42a` | Yes | Yes | 0 | ALREADY_IN_MAIN | `customerapp-rc1-sprint3f`; removed locally |
| `release/customerapp-2026-09-10-queue-card-patch` | `c4cf408` | — | `c4cf408` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `release/customerapp-next-release-admin-demo-rc1` | `2e58bed` | — | `2e58bed` | Yes | No | 0 | ALREADY_IN_MAIN | Removed locally |
| `release/office-staff-organisation-fix-2026-08-27` | `ea03fd7` | `origin/release/office-staff-organisation-fix-2026-08-27` | `ea03fd7` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `release/production-migration-repair-2026-08-21` | `e91161b` | `origin/release/production-migration-repair-2026-08-21` | `23f29fda` | No | No | 1 | ARCHIVE_ONLY | `customerapp-production-migration-repair-2026-08-21` |
| `release/sprint-3e-date-negotiation-2026-08-25` | `22f9746` | `origin/release/sprint-3e-date-negotiation-2026-08-25` | `20d5f123` | No | No | 23 | SUPERSEDED | Canonical Wald contains this historical line; current product outcome is in main |
| `release/sprint-3e-production-2026-08-27` | `9111d76` | `origin/release/sprint-3e-production-2026-08-27` | `9111d76` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |
| `release/sprint-3e-production-record-2026-08-27` | `5c69475` | — | `9111d76` | No | No | 1 | ARCHIVE_ONLY | Tagged; branch retained because its temp worktree is dirty/missing tracked files |
| `security/composer-advisories-2026-09-03` | `5e7df08` | — | `5e7df08` | Yes | Yes | 0 | ALREADY_IN_MAIN | Removed locally |

No branch was classified MERGE_TO_MAIN. No branch had an unresolved code
disposition. The two retained historical branch refs are blocked only by dirty
worktree safety, not by product ambiguity.

## Remote branch disposition

The initial 11 remote branches were reduced to `origin/main` plus the canonical
Wald branch. `origin/main` was not changed.

| Remote branch | Tip | Class | Planned disposition after explicit remote-ref approval |
|---|---|---|---|
| `origin/main` | `e757bb6` | ALREADY_IN_MAIN | Retained permanently; unchanged |
| `origin/codex/qa-sprint-3e-date-negotiation` | `a38d557` | ARCHIVE_ONLY | Deleted after exact tag-target proof |
| `origin/feature/manual-source-import-backend` | `04b560f` | ARCHIVE_ONLY | Deleted after ancestor proof against the manual-import archive tag |
| `origin/hotfix/date-agreed-office-filter-2026-08-27` | `0873bac` | ALREADY_IN_MAIN | Deleted after ancestor proof against `origin/main` |
| `origin/migration-000002-repair-candidate` | `064bf43` | ARCHIVE_ONLY | Deleted after exact tag-target proof |
| `origin/reconcile/main-2026-08-25` | `a43a739` | ARCHIVE_ONLY | Deleted after exact tag-target proof |
| `origin/release-candidate/sprint-3d` | `47e8ccc` | ARCHIVE_ONLY | Deleted after exact tag-target proof |
| `origin/release/office-staff-organisation-fix-2026-08-27` | `ea03fd7` | ALREADY_IN_MAIN | Deleted after ancestor proof against `origin/main` |
| `origin/release/production-migration-repair-2026-08-21` | `e91161b` | ARCHIVE_ONLY | Deleted after exact tag-target proof |
| `origin/release/sprint-3e-date-negotiation-2026-08-25` | `5d3ebce` | SUPERSEDED | Deleted after ancestor proof against canonical Wald |
| `origin/release/sprint-3e-production-2026-08-27` | `9111d76` | ALREADY_IN_MAIN | Deleted after ancestor proof against `origin/main` |
| `origin/feature/customer-wald-next` | `c9fe062` | KEEP_LONG_LIVED | Published and retained as canonical Wald |

## Worktree safety

All clean stale worktrees were removed or their already-missing registrations
were pruned without `--force`, reset or clean. These remain:

- repository root on canonical Wald, retaining the modified Sprint 3E report,
  `Copy of siteapp1.xlsx`, `local logins.docx`, `~$cal logins.docx` and `output/`;
- prepared local-main worktree;
- `customer-admin01-audit`, retaining untracked
  `documentation/admin/customer-account-management-audit-2026-09-09.md`;
- two old Sprint 3E temporary worktrees outside the project. Both currently
  present all tracked files as deleted, so neither was force-removed.

## Accepted product line

Prepared local `main` contains Sprint 3F, the scoped Composer security
remediation, Office queue-card correction, accepted sidebar, customer/site
administration, lifecycle/audit and the dedicated next-release QA correction.
It deliberately excludes WALD02–05 runtime and migrations, the superseded
manual importer/interpreter/UI, WALD06 and production deployment.

## Final local state and verification

- Local branches: 40 initially; four finally retained (`main`, canonical Wald
  and two dirty-worktree historical refs).
- Remote branches: 11 initially; two finally (`main` and canonical Wald).
- Annotated tags: zero initially; 12 created and published.
- Worktrees: 18 initially; five finally retained. Thirteen clean or already
  missing stale worktree registrations were removed/pruned without force.
- Focused admin/sidebar, Sprint 3F and queue-card regression: 166 tests passed,
  1,557 assertions.
- Full SQLite suite: 393 passed, 43 intentional skips, 2,784 assertions.
- `vendor\\bin\\pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: passed with no security advisories; Composer continued
  without its unwritable local cache.
- `npm run build`: passed with Vite 8.1.4 after the sandbox's expected Windows
  child-process restriction required the approved unrestricted rerun.
- `git diff --check`: passed.
- Post-cleanup direct remote-head verification showed exactly `main` and
  `feature/customer-wald-next`; all 12 remote annotated tags peeled to their
  approved commit targets.
- `origin/main` remained
  `e757bb651f9aa95d67b808a97433d27bc29d03c3` before and after cleanup.
- Disposable MySQL was not rerun because the executable and schema tree are
  byte-for-byte unchanged from accepted checkpoint `93737df`; this task added
  documentation and Git refs only.
