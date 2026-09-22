# CustomerApp Wald Existing-Import Re-analysis Report

Date: 22 September 2026
Status: **READY_FOR_RELEASE** as a feature-branch candidate. No production action was taken.

## Baseline and root cause

The branch started from `origin/main` at `cfa3d093d4253416a84d855708a6fcebad7e3d25` with a clean worktree. The current analysis action reused the existing open knowledge context. An older Office choice of descriptive `Plot Ref` therefore survived an analysis retry, even after the approved automatic `Plot number` rule was available. The identical-upload guard correctly prevented uploading the same bytes as another revision.

The private upload, checksum, date/slot and revision are source evidence. Knowledge contexts, clarification answers, staged rows, previews and approvals are derived interpretations. The latter are retained rather than edited or deleted.

## Re-analysis model

An active Office user confirms **Re-analyse import** on an uncommitted selected-site run. Wald verifies the original private workbook checksum and inspects it outside write locks. In one audited transaction it checks current upload/selection scope and the absence of a commit receipt, creates a successor knowledge context, marks the predecessor context superseded, and clears only the run's current stage and preview pointers. The old answers, stage rows and previews remain immutable. Current automatic clarification and staging then run against the successor context; a new non-mutating preview must be created explicitly. A failed fresh analysis remains safely uncommitted.

The command receipt and pilot audit record the initiating Office actor, timestamp, source hash, old/new context and analysis identities, old/new knowledge pins, and the superseded stage/preview identifiers. Analysis history shows current versus superseded generations and manual versus automatic choice counts. The new automatic choice is attributed to `wald_automatic`, not to the earlier Office reviewer.

The backend staging identity advances from `pilot-v3` to `pilot-v4`. Older stages fail the server-side dependency check for preview, approval and commit. A direct normal analysis request cannot re-stage an already staged pilot context; the confirmed re-analysis path is required. A committed run is rejected and must use the separate correction/replacement workflow. The identical-upload guard is unchanged.

## Local qualification

The checksum-pinned small workbook contains 16 source rows: 15 included and one `CU4` excluded. In the regression and local browser fixture, generation 1 selected descriptive `Plot Ref` (for example, `Vistry – Countryside 2D – Plot 673`). Generation 2 automatically selected `Plot number`, staged numeric identities including `673`, and retained generation 1's evidence. The new one-site preview was blocker-free for CustomerCode `FNA2563`, exactly bound to the local QA site, with 15 create targets. No preview was approved or applied in the browser walkthrough. Old preview commit was refused in the regression; projected plots and receipts remained zero.

The local browser showed **Analysis 1 · Superseded · 1 Office choice, 12 automatic choices** and **Analysis 2 · Current · 0 Office choices, 13 automatic choices**. The Office confirmation control, numerical staged rows, fresh preview and site targets were visible. The production import was not opened for mutation.

## Checks

- Focused SQLite after expanded checks: 3 tests passed, 88 assertions (the new re-analysis test alone: 1 test, 58 assertions). The final full run includes the final code and tests.
- Final `php artisan test`: 1,749 tests, 1,668 passed, 81 expected skips, 8,867 assertions.
- Disposable MySQL 8.4.11: final expanded re-analysis regression 1 passed, 58 assertions. A separate fresh-database run of re-analysis plus the existing production-like commit boundary passed 2 tests, 59 assertions. A stale success-message assertion in that existing boundary test was updated to the current UI copy.
- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: valid; `composer audit --no-interaction`: no advisories.
- `npm run build`: passed; `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed.

The tests include duplicate-upload refusal, old-version staging refusal, confirmation validation, old preview refusal, committed-run refusal, and denial for all three external roles, inactive Office, and a stale Office role.

## Migration, production and release

No migration or database schema change is required. No code was pushed, deployed or run against production; Nick's current import was not re-analysed, approved or applied. After controlled release and deployment verification, Office can open that existing revision, confirm **Re-analyse import**, inspect the new numeric staging, create a fresh preview, then separately decide whether to approve and apply it. Do not approve or apply the old descriptive preview.

The feature branch is `codex/wald-existing-import-reanalysis-2026-09-22`. The final commit SHA is recorded in the completion response.
