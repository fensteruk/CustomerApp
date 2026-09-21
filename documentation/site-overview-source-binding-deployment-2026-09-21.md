# Site Overview binding status — production acceptance, 21 September 2026

## 1. Verdict

**SUCCESS — OVERVIEW STATUS VERIFIED**

Completed the explicitly approved exact-candidate deployment and bounded read-only production
smoke. No unrelated fixes, source imports, binding/assignment writes or call-offs were made.

## 2. Revision state

| Evidence | Result |
| --- | --- |
| Previous production / rollback SHA | `0ac7082141156fdff29d296881bbb7d3299e20b5` |
| Approved candidate | `590503b6c80ea186bc6001e43a11ab528298961a` |
| Resulting remote main | `590503b6c80ea186bc6001e43a11ab528298961a` |
| Served SHA, final check | `590503b6c80ea186bc6001e43a11ab528298961a` |
| Forge deployment | `78155443`, push to deploy, main |
| Result / duration | Deployed / 38 seconds |
| Start | 2026-09-21 00:35:50 UTC |
| Migration output / delta | Nothing to migrate / zero |
| Rollback performed | No |

[Forge deployment](https://forge.laravel.com/fensterltd/customerapp/3346456/deployments/78155443).

Before release, fetch and complete diff review verified the exact parent, one candidate
commit, six expected files and clean worktree. Only the Office Overview Blade view changes
runtime behaviour. No migration, dependency/lockfile, configuration, Wald or binding-logic
changes. The guarded exact-SHA fast-forward push preserved unrelated primary-checkout work.
Remote-main confirmation used `git ls-remote origin refs/heads/main`.

Recovery evidence: existing `pre_release_20260920_144011.sql.gz`, 128059 bytes,
modified 2026-09-20 14:40:12 UTC; SHA-256
`128d26168a88b00e692e7f7bf40bdc17a54c98666118df77c89999b08b754906`.
`gzip -t` passed before/after release. This verifies compressed-file integrity, not a restore
rehearsal. No new backup or database restore was performed for this presentation-only release.

## 3. Linked site verification

TEST — Acme Developments → TEST — Willow Park:

- Before release, Overview reproduced the stale unavailable/not-linked presentation.
- After release, both Overview status locations show **Linked**.
- Source Binding remains authoritative and shows three unchanged Active version-1 rows.
- CustomerCode **ACME-WILLOW-E2E** remains present under source `redzebra`.
- Contradiction present: **NO**. Identity/version/timestamp evidence is unchanged.

## 4. Unlinked site verification

Existing site **FNA2563**, under **Sherford Countryside 2D - Vistry PShips**:
Overview **Not linked**; Source Binding **Not linked**, explicitly no active binding.
Agreement: **YES**. This is not the separate bound FNA2563 site under the FNA2563 customer.
No fixture or binding was created for this check.

## 5. Legacy / multiple-binding verification

Willow provides existing safe coverage: its CustomerCode row plus two exact source-site-name
legacy rows, **TEST - Wald Pilot Source** and **TEST WALD GOLIVE 20260915**.
All three remain Active, version 1. Overview shows a single **Linked** state while details
remain on Source Binding. No unexpected behaviour observed. This verifies mixed existing
legacy/multiple rows, not a separate legacy-only production site.

## 6. Security / customer view

Normal user-performed sign-in confirmed **CUSTOMER — Willow Park Site Manager**.
Site selection offered Willow; its normal dashboard showed 14 plots and correct customer/user.
Private CustomerCode and source-binding details were absent from the dashboard, its navigation,
the inspected E2E plot details and the initial New Call Off page.

The reported 403 was at the Office Willow plots URL, not the customer dashboard. This is
expected authorisation: the Site Manager was denied the Office site page and the direct
`/portal/office/workspace/imports` route with 403. Office/Imports navigation was absent.
No permissions, credentials, accounts or assignment changes were necessary.

## 7. Regression smoke

- Willow Source Binding unchanged; CustomerCode still associated correctly.
- **WALD-E2E-01** and **WALD-E2E-02** visible beneath Willow in Office and customer views.
- E2E-01 details opened normally. New Call Off opened; no selection or submission made.
- Office logout followed by Back three times / Forward twice showed only sign-in.
- Site Manager logout followed by Back three times / Forward twice showed only sign-in.
- New regression found: **NO** within this bounded smoke. This is not a full import rerun.

## 8. Production health and checks

| Check | Evidence |
| --- | --- |
| Preflight, 00:33:02 UTC | Forge command `12831927`: old SHA, 21 applied / 0 pending migrations, 0 failed / 0 queued jobs |
| Post-deploy, 00:37:20 UTC | Command `12831956`: exact new SHA, same migration/job counts |
| Final, 01:02:54 UTC | Command `12832121`: exact new SHA, 21 applied / 0 pending, 0 failed / 0 queued |
| Health endpoint | `/up` rendered Application up before and after release; final response rendered in 210 ms |
| Final log check, 01:02:38 UTC | Command `12832119`: readable Laravel log, all checked counts zero |

[Final release-state check](https://forge.laravel.com/fensterltd/customerapp/3346456/commands/12832121)
and [final log summary](https://forge.laravel.com/fensterltd/customerapp/3346456/commands/12832119).

The read-only state command bootstraps Laravel, reads the migrator repository/file list,
counts jobs/failed_jobs, reads `git rev-parse HEAD`, and checks the existing backup via
`gzip -t`, size, timestamp and SHA-256. No database statements write data.
The log summary counts events since 2026-09-20 16:18:20, a window including this deployment:
`production.ERROR`, `PreventAuthenticatedResponseCaching`, `TokenMismatchException`,
`Livewire`, `Undefined array key`, `replacement_predecessor_missing` and `Wald`: all **0**.
No new application/source-binding, Wald or auth/privacy errors were observed. Source Binding
was not a separate log token; the overall production.ERROR count was zero.
Forge's pre-existing generic issues banner remains; this report does not claim its historical
issues were investigated or resolved.

Independent pre-release focused rerun:
`php vendor/pestphp/pest/bin/pest tests/Feature/SiteOverviewSourceBindingTest.php --compact`
passed **9 tests / 69 assertions**, in the isolated hash-matched runtime. Candidate combined
evidence is **108 / 626**. Recorded full-suite evidence remains **1,595 passed, 81 skipped,
one known unrelated weekday-sensitive error, 8,336 assertions**; it is not represented as green.
See the [historical candidate report](site-overview-source-binding-fix-2026-09-21.md)
for exact automated gate commands and four existing development npm advisories.
No additional full suite was run for the deployment-record-only changes.

## 9. UX acceptance

Does Site Overview now truthfully communicate whether the Site is linked?

**YES — VERIFIED LIVE**.

## 10. Remaining UX findings

Kept separate and not fixed by this release:

- Unexplained product codes such as `VS 2` (also observed on E2E-01).
- Mixed import completion wording.
- Filters hiding main navigation.
- Inconsistent View/Edit User behaviour.
- “Projected plots” terminology.

## 11. Recommended next step and handover

Address customer-facing product labels/codes as the next separately approved bounded UX task.
Do not combine the remaining findings into this deployment.

Deployment-record files changed: this report, `current_sprint.md`, `ROADMAP.md`, `HANDOVER.md`.
These documentation changes are on local non-deploying branch
`codex/record-overview-deployment-2026-09-21`; no further push/deployment is authorised or made.
The original six-file candidate remains the exact production revision. Unrelated work in other
checkouts is preserved. Documentation validation uses `git diff --check`, local link/path checks
and diff/contradiction review. Application is left logged out after the privacy check; Forge stays open.
