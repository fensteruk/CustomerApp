# CUSTOMER-WALD05 Backend Completion Report

Date: 9 September 2026. Owner: CustomerApp Wald Architecture / Integration.

## Overall result

**READY FOR QA — bounded backend/application layer only.** Final verification is recorded below.
Feature branch only.
This report is not dedicated-QA acceptance, a real-workbook import, WALD06 approval or a release.
The latest instruction, recorded as DEC-053, explicitly excludes the full Office UI from this task.

## Branch / immutable inputs

- Branch: `feature/customer-wald05-import-review-integration`.
- Starting foundation: `d1b5de13b260dde77867fdb8ac73296e49994c14`.
- Reader correction: `e9e1c3a2a3ff79cfe5f097bea143f51195becd55`.
- Governance: `877bd3ff666873a0703c3b7671015ec4cfd2ee52`.
- Accepted WALD04 input: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`.
- Documentation reset: `e84999cf66fc90aac3007842a538672b018b3e03`.
- The implementation commit containing this report is the backend candidate, not the starting
  foundation SHA. Its full SHA is provided in the task handoff and is available from Git history.

All listed inputs were verified in ancestry. Entry had no unrelated runtime modifications.
The existing Sprint 3E report edit, untracked customer workbook and `output/` were preserved.
Generic `app/Wald`, semantic executable/dictionary, dependencies, routes and old importer are
unchanged in this task. The narrow CustomerApp knowledge hashing change is disclosed below.

## Supported application boundary

The default-off application API is `ImportIntake`, `ImportAnalysis`, `ImportClarification`,
`ImportReview` and `ImportRetention`, alongside the accepted `SourceBindingService`.
No HTTP routes, controllers, full Office screens, public downloads, worker, scheduler or feature
enablement were added. Tests explicitly enable the existing test configuration only.

The complete, explicitly reviewed atomic unit supports XLSX or UTF-8 CSV with one visible,
unmerged table/sheet, one Portal organisation/site scope and at most 500 nonempty source rows.
Every included site value requires an exact current binding to that selected scope. Multiple
sheets/sites, unresolved mappings, hidden/merged ambiguity, competing distinct visits for one
plot, resource excess and invalid required facts refuse the whole unit. There is no implicit
partition, per-row commit, latest/sum/any/all visit aggregation or complete-snapshot commit.

The actual twelve-site workbook has NOT been uploaded, staged, reviewed or committed here.
Its bytes remain unchanged, SHA-256
`ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.
Earlier evidence of 45 selected rows is only the prior read-only audit. Do not suggest splitting
that artifact silently: changed bytes do not inherit DEC-050/051 approval, and source-family
slot ordering is shared, not independently reset per site. Broader actual-workbook support needs
explicitly scoped implementation and review; it is not represented by these synthetic results.

## Upload / storage

Private generated UUID keys under CustomerApp `storage/app/private/wald-imports`; original names
are sanitised display-only. Server-side Office identity, scope, MIME/type, accepted reader open,
file size and SHA-256 are captured. The existing 25 MiB reader budget remains. Reopening verifies
stored byte count/hash and rejects links or malformed keys. No filename-derived path/public URL,
formula execution or executable upload. Registration failures remove only the newly generated,
unregistered artifact; registered evidence and original source workbooks are not deleted.

## Import run / ordering

Durable run states are UPLOADED, ANALYSING, NEEDS_CLARIFICATION, REQUIRES_REVIEW, REVIEWED,
READY_TO_COMMIT, COMMITTING, COMMITTED, FAILED and SUPERSEDED. An immutable generation/stage
represents successful analysis. Commands are scoped, authenticated, epoch-checked and audited.
Analysis runs outside long projection write locks, uses a 600-second fenced lease and bounded
attempts, and exposes explicit retry/status APIs. Expired-worker results cannot overwrite a
newer generation. This is queue-agnostic synchronous orchestration, not a production worker.

Export Date followed by MORNING/AFTERNOON is the only ordering authority. Office must confirm
exactly: "I confirm this is the latest RedZebra export available for this slot." The actor is
freshly read from authentication, never supplied as free text. Upload/filesystem time does not
establish freshness. Streams are source namespace plus workbook family.

## Analysis / staging / knowledge / binding

The pipeline invokes the accepted reader/profiler/reasoning engine, WALD03 semantic adapter and
WALD04 explicit clarification/profile APIs. Stage rows preserve private physical lineage, raw
call/completion/products/operational date, supplied-column presence, canonical facts, issues,
selection/answer provenance, binding pins and hashes. Formula/error cells block; date-, boolean-
and percentage-typed quantity cells cannot become product counts after header clarification.
Ignored commercial fields and excluded product codes remain outside Portal product projections.

No automatic learning or activation. A reusable profile requires a real current use receipt and
fresh evidence eligibility; one-time semantic corrections remain occurrence-bound. Profile
revocation, changed answers/context and binding supersession invalidate dependent review.
DEC-050's CC! correction/CM2 exclusion and DEC-051's exact Nick TEST exclusion remain tied to the
approved artifact/row. There is no global alias, CM2 ban or test-site detector.

W5-T02: integration exposed `knowledge_payload_limit` while hashing full transient reasoning for
an ordinary seven-row synthetic workbook. `Canonical::evidenceHash` streams the same canonical
bytes under an independent 4 MiB hash-only bound. `AnalysisSnapshot` uses it for profile/reasoning
digests; the backend uses it for bounded staged rowsets. Existing persistent 64 KiB/256 KiB limits,
ordinary `json`/`hash`, matching/activation semantics and generic resource guards are unchanged.
Small-input digest identity and refusal at both retained/transient limits are tested. Backend
identity pins `customerapp.wald-transient-evidence-digest.v1`; accepted profiles/receipts are not
rewritten. Reader stays wald-0.2.2 / XLSX 3 / CSV 2. Dictionary v1 fingerprint remains
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.

## Call No. / preview / stale protection

Any duplicate Call No. within the unit blocks, including identical duplicate evidence. Later
permitted observations retain visit identity; site/plot/service reassignment blocks. Existing
legacy service Call No. associations are checked rather than overwritten.

Preview stores source-derived before/after changes and blockers without projection mutation.
It pins workbook/stage/canonical hashes, export order, current component identities, dictionary,
semantic executable, backend identity, scope, knowledge and binding state, stream epoch,
projection/history digests, reviewer/time and expiry. Preview validity is at most 24 hours.
Approval is separate from preview and commit. Every dependent value is revalidated; stale review
is refused, never silently regenerated. Numeric epochs protect projection ABA changes even if
visible values were restored. Existing Portal requests, negotiations, proposals, batches and
history are included in the current-state snapshot.

## Products / completion / Portal state

Absent columns preserve existing projected products. Present blanks retain raw evidence and
apply the approved supplied-record zero rule; explicit zero is exact zero and valid positive
quantities preserve fixed-point precision. Invalid/unknown quantities block. Only approved
Windows/Doors product codes project; exact BF remains separately stored. No absence pass runs.

Resolved source Yes is authoritative completion for the included visit, with an observation
timestamp but no invented completion date. A genuinely existing source completion date is not
erased by a subsequent Yes; operational dates never supply one. Unknown service plus Yes blocks.

The downstream completion action closes the current request/negotiation and pending proposal
status under the existing precedence contract, clears the active conflict key and appends source
events. It does not rewrite requested/agreed dates, proposal dates/responses/actors, amendment
prior dates, old history or batch decisions. The tests cover an amendment-purpose negotiation
available on this branch, not the separately developed Sprint 3F runtime. Reversal preserves
closed history and never reopens the old request; an unsafe newer active request gets the
existing reconciliation issue. Completion sends no notification.

## Atomicity / receipt / idempotency / correction

The new `ProjectionAdapter` consumes only the guarded review path; it does not call the old
importer/mapper. One reviewed unit uses one transaction for all projections/products/completion,
visits, immutable observations, receipt, stream state, run state and required command audit.
Recognised transient MySQL concurrency failures have the existing bounded three-attempt retry.
Representative failures after observations, product work, completion, receipt/audit and stream
updates prove complete rollback. Successful status is not returned before transaction commit.

Exact successful reviewed replay returns its immutable receipt after fresh scope authorisation,
without duplicate events/projections. Same-slot canonical replay returns the existing result.
Materially different same-slot content needs an explicit Office successor, reason, current
predecessor, reanalysis and new review. Creating a successor invalidates older previews; accepted
predecessor observations/receipts remain immutable. Competing successors do not last-write-win.
PM-before-AM and next-day-before-prior-day regressions refuse regardless of upload time.

Receipts retain run/review, actor/reviewer, scope/family/order, binding pins, component identities,
workbook/canonical/review hashes, effect counts/details and numeric projection/visit versions,
predecessor/revision, idempotency identity and commit time. The latest visit pointer may advance;
accepted historical observations cannot be rewritten.

## Audit / retention / security

Metadata covers terminal workbook +30 days, bulky stage +7 days, preview validity 24 hours,
preview payload +7 days and committed minimal audit/observations six years. Audited run holds
override age eligibility; live runs do not become age-eligible. There is no purge API or schedule.
G09 named-owner and future dependency-aware disposal review remain production gates.

Coverage includes external roles/inactive or changed Office authority, scope/IDOR, cross-tenant
bindings, dirty actor attributes, forged confirmation/order/hash/preview/component pins, profile
receipt/activation boundaries, malicious filenames, formula/script/prompt-like source strings,
immutable evidence, partial-only scope and stale dependencies. Public upload/HTTP-specific CSRF,
request throttling, browser output escaping and download policy must be verified when a UI/route
is separately implemented; no endpoint security coverage is claimed for endpoints that do not exist.

## MySQL migration / concurrency evidence

Disposable MySQL 8.4.11, bound only to `127.0.0.1:33487`, MySQL X disabled. Synthetic schemas:
`customerapp_wald05_backend` and `customerapp_wald05_backend_upgrade`. No `.env`, local normal
application database or production database was changed.

One additive forward migration creates eight backend tables, 19 backend integrity/immutability
guards and three projection epoch columns. The 14 pre-existing migrations were not edited.
Upgrade verification preserves 15 populated existing Portal/knowledge/binding tables, exercises
empty rollback/reapply and proves populated rollback refuses before dropping any guard/table.
SQLite fresh-schema/epoch/immutability coverage runs in ordinary tests.

Eight backend race scenarios each repeat five times: double commit, same-call update, AM/PM,
older/newer, binding change, profile revocation, projection epoch change and correction conflict.
The four accepted binding-foundation races repeat ten times each. Combined: 80 meaningful race
groups and 160 separate worker processes/connections. Locks, versions, receipts and final source
order are asserted; broad retry or weakened loser assertions are not used to hide conflicts.

Disposable server shutdown: PASS after verifying the exact port and CustomerApp-owned temporary
data directory. Local synthetic data/log files are retained for audit; no customer data was removed.

## Performance / query safety

Synthetic end-to-end small/medium/maximum units, measured during the final MySQL non-race suite:

| Nonempty rows | Scenario | Seconds (upload through commit) | Commit SELECT queries | Process memory after case |
|---|---|---:|---:|---:|
| 7 | New, incomplete visits | 1.728 | 46 | 70 MiB |
| 100 | New, incomplete visits | 3.517 | 46 | 72 MiB |
| 500 | New, incomplete visits | 12.178 | 46 | 90 MiB |
| 7 | Complete existing active requests | 0.997 | 48 | 90 MiB |
| 100 | Complete existing active requests | 3.610 | 48 | 90 MiB |
| 500 | Complete existing active requests | 26.840 | 48 | 94 MiB |

The run shared the machine with SQLite regression, so these are reproducible diagnostics, not
production latency guarantees. Final SQLite focused completion measurements: 0.634 / 3.746 /
27.914 seconds, 48 SELECTs at each size, maximum 90 MiB process memory. Tests require fewer than 90 commit reads
and less than 384 MiB process memory. Writes scale with required per-visit immutable evidence and
domain events; bulk product/creation operations avoid per-product reads. Completion requests,
batches, services, negotiations and proposals are loaded together, avoiding per-request relation
reads. All 500 completed requests retain their agreed dates. Profile reads stop at
50 supported candidates/receipts and relevant projection/history queries at 10,000 per table.
501 rows refuse, with no receipt and no hidden subset. Wider tables or larger histories may hit
independent retained-payload/analysis limits before the row bound and must still fail closed.

## Tests / exact commands

All data used for this task's execution tests was synthetic. Existing environment-dependent
skips remain skips, not passes. The dedicated MySQL run supplies WALD05 database/race evidence;
it is not a rerun of every unrelated application's MySQL scenario.

| Final gate | Total | Passed | Skipped | Assertions | Result |
|---|---:|---:|---:|---:|---|
| Focused WALD05 | 126 | 114 | 12 MySQL-only | 409 | PASS |
| Combined Wald | 1,014 | 991 | 23 MySQL-only | 4,649 | PASS |
| Full CustomerApp | 1,248 | 1,210 | 38 environment-dependent | 5,838 | PASS |
| MySQL non-race WALD05 | 114 | 113 | 1 SQLite-only | 407 | PASS |
| MySQL backend + foundation races | 12 | 12 | 0 | 246 | PASS |
| MySQL additive upgrade / rollback | 14 baseline + 1 new migration | 15 old tables preserved | — | 8 tables / 19 guards / 3 epochs | PASS |

Commands run from the repository root:

```powershell
php artisan test tests/Feature/Wald05 tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-final-focused.xml
php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 tests/Unit/Wald05 tests/Feature/Wald05 --compact --log-junit=storage/framework/testing/wald05-final-combined.xml
php artisan test --compact --log-junit=storage/framework/testing/wald05-full-final.xml

# Process-local testing variables only; never .env or production credentials.
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='33487'
$env:DB_DATABASE='customerapp_wald05_backend'
$env:DB_USERNAME='root'
$env:DB_PASSWORD=''
$env:DB_URL=''
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/SourceBindingTest.php tests/Feature/Wald05/BackendPipelineTest.php tests/Feature/Wald05/BackendSafetyTest.php tests/Feature/Wald05/BackendSecurityTest.php tests/Feature/Wald05/BackendKnowledgeTest.php tests/Feature/Wald05/BackendPortalProtectionTest.php tests/Feature/Wald05/BackendPerformanceTest.php tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-final-mysql.xml
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/MysqlBackendConcurrencyTest.php tests/Feature/Wald05/MysqlFoundationConcurrencyTest.php --compact --log-junit=storage/framework/testing/wald05-final-races.xml
$env:DB_DATABASE='customerapp_wald05_backend_upgrade'
php scripts/verify-wald05-backend-upgrade.php

php vendor/bin/pint --test
composer validate --strict
composer audit --format=json
npm run build
git diff --check
```

Upgrade script requires its exact empty testing schema and refuses an existing populated schema.
Test XML, synthetic workbook fixtures and disposable databases remain local/ignored, not committed.
Pint and strict Composer validation pass. All 26 task PHP files pass `php -l`; all 37 documented
task paths and 35 checked relative Markdown links exist. Diff/whitespace and boundary checks pass.
Build passes with Vite 8.1.4: an initial sandbox EPERM
blocked a child process, then the approved local build rerun succeeded. No deployment occurred.

Earlier failed attempts are not omitted from the evidence: the transient digest defect above was
corrected; an intentionally ambiguous Plot profile was correctly refused by the accepted fresh-
evidence veto, and the positive reuse test now uses an eligible quantity profile without bypassing
that veto. Test-only exception-class/enum/database-normalisation/canonical-key-order mistakes and a missing model
import were corrected. Additional quantity-type and excluded-product checks pass in the final
suite. Final query review also removed completion-path relation N+1 reads and added small/medium/
maximum active-request completion cases plus failure-after-Portal-completion rollback assertions.
No failing business assertion was removed and no safety guard was relaxed to pass tests.

## Composer security — separate state

`composer audit --format=json` exits 1: eight inherited advisories, three Filament, four
CommonMark and one Livewire (five high, two medium, one low). Cache was unwritable but retrieval
completed without cache. This is NOT a clean security audit. Dependencies/lockfiles remain
unchanged and remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. This remains
an independently owned security/release reconciliation gate, not permission to deploy the backend.

## Old importer disposition / parity gaps / retirement criteria

| Area | Backend disposition | Remaining comparison/cutover condition |
|---|---|---|
| Existing `SourceProjectionImportService` execution | RETAIN, not invoked by Wald; no deletion or route switch. | Independently approved WALD06 cutover, rollback plan and support ownership. |
| Existing stage/call-type mapper | SUPERSEDED only inside the new adapter by approved dictionary facts. | Never treat legacy meanings as a parity oracle or import historical speculative codes. |
| Source-wide absence and omitted-product zeroing | SUPERSEDED in Wald by partial-only/no-absence effects and supplied-column presence. | Explicit negative regressions pass; any stronger-snapshot contract is separately gated. |
| Per-record transactions | SUPERSEDED in Wald by one reviewed-unit transaction. | Dedicated QA validates rollback/races; no hidden per-record fallback. |
| Existing projection relationships, domain events and completion precedence | REUSE/ADAPT downstream; new adapter preserves relationships and existing history. | Synthetic regression passes on this branch. Separate Sprint 3F integration/QA remains outside this task. |
| Non-main manual source import/UI/profile line ending `1e8c22b` | REFERENCE_ONLY; compatible test intent reused, no wholesale merge or old profile migration. | Explicit UI/behaviour parity inventory and approved supersession during future cutover. |
| Real awkward/multi-site workbooks, wider layouts, browser/worker/recovery integration | NOT CLAIMED by this backend candidate. | Dedicated QA may assess refusals; separately scoped support/UI and WALD06 pilot evidence precede retirement. |

No full cross-database legacy-versus-new importer parity trial was performed. Safe intended
semantics are asserted directly; obsolete behaviours deliberately differ. Do not equate the
passing full regression or prior workbook profiling with full real-import/cutover parity.

## Files changed

All paths below are repository-relative for the committed audit. No customer data, generated
output, dependencies, normal database, `.env`, old migration or unrelated Sprint 3E edit is included.

Application:

- `app/SourceImport/Integration/BackendStore.php`
- `app/SourceImport/Integration/PrivateWorkbookStorage.php`
- `app/SourceImport/Integration/ImportIntake.php`
- `app/SourceImport/Integration/ImportAnalysis.php`
- `app/SourceImport/Integration/ImportKnowledge.php`
- `app/SourceImport/Integration/WorkbookStager.php`
- `app/SourceImport/Integration/ImportClarification.php`
- `app/SourceImport/Integration/ProjectionSnapshot.php`
- `app/SourceImport/Integration/ProjectionAdapter.php`
- `app/SourceImport/Integration/SourceCompletion.php`
- `app/SourceImport/Integration/ImportReview.php`
- `app/SourceImport/Integration/ImportRetention.php`
- `app/SourceImport/Knowledge/Canonical.php`
- `app/SourceImport/Knowledge/AnalysisSnapshot.php`
- `database/migrations/2026_09_09_000012_create_wald_import_backend.php`

Verification:

- `tests/Feature/Wald05/BackendPipelineTest.php`
- `tests/Feature/Wald05/BackendSafetyTest.php`
- `tests/Feature/Wald05/BackendSecurityTest.php`
- `tests/Feature/Wald05/BackendKnowledgeTest.php`
- `tests/Feature/Wald05/BackendPortalProtectionTest.php`
- `tests/Feature/Wald05/BackendPerformanceTest.php`
- `tests/Feature/Wald05/MysqlBackendConcurrencyTest.php`
- `tests/Support/Wald05BackendFixtures.php`
- `tests/Support/Wald05ConcurrencyWorker.php`
- `tests/Support/Wald05Race.php`
- `scripts/verify-wald05-backend-upgrade.php`

Documentation:

- `DECISIONS.md` (append-only DEC-053)
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md`
- `documentation/wald-divergence-register.md`
- `documentation/source-integration-contract.md`
- `documentation/siteapp-import-data-dictionary.md` (delivery status only)
- `documentation/wald/customer-wald05-import-review-integration-2026-09-08.md` (historical label)
- `documentation/wald/customer-wald05-backend-completion-2026-09-09.md` (this report)

## Remaining gates / recommendation

Dedicated backend QA should review the complete candidate, especially the application-only
entry points, W5-T02 hashing boundary, one-site/table resource bounds, immutable migrations,
source completion/Portal history and real MySQL races. The supported synthetic backend contract
has no newly invented business-decision blocker. Historical W5-P01/P02 do not block the earlier
selected sample and are not being resubmitted as blanket questions.

Full WALD05 Office UI, broader actual-workbook support and independent dedicated-QA acceptance
remain before an integrated operational milestone. WALD06 must not start without its separately
approved baseline/work package, supervised corpus/pilot and security, storage, worker, backup,
recovery, device and cutover evidence. G09 blocks unattended production disposal. No main, push,
SiteApp, Sprint 3F, dependency merge, production tag, customer import or deployment occurred.

All recorded backend gates pass except the explicitly separate inherited Composer audit.
Dedicated WALD05 backend QA may begin against the implementation commit containing this report.
This is not full WALD05 acceptance or permission for WALD06, UI rollout, main or deployment.

CUSTOMER-WALD05 backend ready for dedicated QA
