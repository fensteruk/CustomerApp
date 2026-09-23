# CustomerApp Final Wald UX Report

23 September 2026. Feature branch only; no push or deployment.

## Overall result

READY_FOR_INTEGRATION. Both sequential UI tasks are implemented and qualified.

## Base and Git scope

- Exact base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.
- Branch: `codex/customer-ui-final03` in an isolated worktree.
- Task A: `74872f5f7cd55eaa7737812bd9dfea50d8fea981`.
- Task B: the separate commit containing this report, directly after Task A.
- The original main checkout and its three unrelated Cavity Closers changes were preserved.
- No merge, push, release, production access or deployment was performed.

## TASK A — Import Detail / Review

### Workflow

Upload → Link site → Analyse → Review → Approve → Apply → Applied is derived from
actual review state. Whole-workbook counts remain distinct from the selected site.
The page identifies the exact target customer, site and CustomerCode.

### Clarifications

Plain-language questions retain the existing exact candidate IDs, reasons, sequence,
context and command payloads. Original evidence is available in a disclosure. No
clarification semantics or dictionary decisions changed.

### Preview

Full verified selected-site stage totals show plots, included/ignored rows, CU4 and
blocked rows. The preview shows target site and plot create/reuse results. Source
products, services, completion and operational date evidence remain available behind
disclosures. Missing facts are not invented. The evidence list explicitly shows its
first-100-row limit; totals cover the bounded complete stage (maximum 500 rows).

### Approve / Apply

The reviewed site and proposed plot counts are stated before approval and Apply.
Approval remains separate from writing data. Existing POST routes, CSRF, command IDs,
preview identity/hash, required Apply confirmation and server guards are preserved.

### Persistent actions

The action bar remains sticky within each selected-site review. Native invalid Apply
confirmation scrolls the checkbox clear of the bar. Re-analysis recovery also has a
persistent link where the stored analysis is eligible.

### Responsive / accessibility

Uploaded, review, approve, apply, blocker, expired, stale and clarification states were
checked at 1366×768, 768×1024, 390×844 and 320×740 (32 combinations). No horizontal
overflow. Desktop/mobile screenshots were inspected. Keyboard focus is visible;
forms retain labelled controls, required choices and semantic disclosures.

### Tests and commit

Before the Task A commit: four new tests passed (41 assertions); WaldPilot01 and
WaldReconciliation had 60 passed / 5 skipped (357 assertions); MySQL 8.4 ran all four
new tests successfully. Build, formatting and whitespace checks passed. See the
separate Task A checkpoint report for the exact pre-Task-B state.

## TASK B — History / Results

### Applied result

A prominent receipt panel shows site, plots created/reused, rows applied/ignored,
timestamp and actor, with View site plots and View import history links. All counts
come from the verified immutable receipt. An expired historical preview cannot move
an applied result back to Review. Retained previews use historical wording.

### Failed state

Upload and selected-site failure messages explain the safe stop and whether anything
was applied. Eligible selected-site failures offer re-analysis of the existing file;
early upload failures guide source correction and a fresh export. Missing/unverified
stored files leave retained evidence readable but hide actions requiring those bytes.
Failure references remain secondary. No partial-site success is implied.

### Superseded / stale

Superseded uploads identify and link the current revision. Earlier applied receipts
remain visible and are explicitly historical. Stale analysis and expired preview are
separate states with the appropriate existing recovery path. Historical approvals
are not presented as permission to apply uncommitted work.

### Re-analysis

The form explains that the same private workbook is used, previous analysis is kept,
and the new analysis needs fresh review/approval. The real action test confirms new
context/stage, unchanged workbook hash, retained old stage and no plot writes.
Current, previous and retained final analysis generations are labelled separately.

### Import history

Detail adds uploader/time, export date/slot/revision, replacement reason, applied-site
count and revision links. Older-history rows add export identity and receipt/selection
counts. Site history exposes plot receipt totals. Existing pagination is unchanged:
three recent imports, ten older imports per page, and twenty per site-history page.
The existing maximum twenty analysis generations remains unchanged. No full-history
query was added. Private workbook availability is checked once per detail request.

### Responsive / accessibility

Applied, site history, superseded, failed upload, failed analysis, re-analysed,
unavailable workbook and paginated history were checked at all four required sizes
(32 additional combinations). No horizontal overflow. Mobile/desktop receipt,
recovery and history screenshots were inspected. Keyboard expansion of receipt
evidence, visible focus and mobile recovery navigation were verified. Browser size
override was reset afterwards.

### Qualification

| Check | Result |
| --- | --- |
| New FinalImportDetailUxTest, SQLite | 9 passed, 109 assertions |
| WaldPilot01 + WaldReconciliation + WaldSource02 | 82 passed, 5 skipped; 596 assertions |
| New FinalImportDetailUxTest, disposable MySQL 8.4 | 9 passed, 109 assertions |
| Full `php artisan test --compact` | 1,853 passed, 89 skipped; 10,070 assertions; 1,942 tests |
| `php vendor/bin/pint --test` | Passed |
| `composer validate --strict` | Passed |
| `composer audit` | No security vulnerability advisories |
| `npm run build` | Passed |
| `npm audit --omit=dev` | Zero vulnerabilities |
| `git diff --check` | Passed |

Browser pages use fictional static captures of server-rendered views; HTTP tests
exercise actual authorised actions and database state. The large master summary and
clarification layout test supplies explicit view fixtures. No real customer workbook
was uploaded or committed. Static preview form links are not a running backend.

An existing failed-revision label was retained after regression detected its removal.
The MySQL receipt assertion compares complete canonical JSON, preserving types and
values while allowing MySQL's JSON key ordering. No safety assertion was weakened.

## Files changed across both commits

- `app/Http/Controllers/OfficePilotImportController.php` — authorised read presentation only.
- `app/View/Presenters/ImportReviewPresentation.php` — display summaries, labels and actions.
- `resources/views/office/pilot-import/show.blade.php`
- `resources/views/office/pilot-import/detail-styles.blade.php`
- `resources/views/office/pilot-import/site-review.blade.php`
- `resources/views/office/pilot-import/source-sites.blade.php`
- `resources/views/office/pilot-import/reanalysis.blade.php`
- `resources/views/office/pilot-import/applied-result.blade.php`
- `resources/views/office/pilot-import/lifecycle.blade.php`
- `resources/views/office/pilot-import/index.blade.php`
- `resources/views/office/sites/imports.blade.php`
- `tests/Feature/WaldPilot01/FinalImportDetailUxTest.php`
- `documentation/customer-ui-final03-task-a-2026-09-23.md`
- `documentation/customer-ui-final03-2026-09-23.md`

## Wald business logic changes

NONE. Controller changes obtain verified existing evidence and presentation metadata.
All mutations still use the existing domain actions. Private upload, binding,
dictionary, exclusions, provenance, clarification, preview, approval, atomic one-site
commit, receipts, idempotency, replacement and staleness rules remain unchanged.
Automatic RedZebra date reconciliation was not expanded. Global shell, routes,
dependency lockfiles and source engine files were not changed.

## Migrations / database impact

NONE. No schema or production data change. Tests used disposable local databases.

## Push

NO.

## Deployment

NO. This is an integration candidate, not production evidence.
