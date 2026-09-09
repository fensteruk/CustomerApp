# CUSTOMER-WALD05 Dedicated Backend QA Report

Date: 9 September 2026. Owner: CustomerApp QA / Testing.

## W5Q-03 / W5Q-04 correction and fresh requalification — 9 September 2026

**PASS — corrected bounded WALD05 backend is eligible to freeze.** Fresh acceptance against
`dbd17c68a04c028418e2d8a08fc43312aae5fe3b` closes W5Q-03 and W5Q-04 with no remaining
bounded backend blockers. This section supersedes the original gate for this candidate only;
the original FAIL evidence below remains unchanged. This is not full UI, pilot or release approval.

### Corrected candidate and bounded authority

- Starting QA handoff: `324cacfd53cf031b284464fb8ca97b20ff1a6598`.
- Corrected executable/test candidate: `dbd17c68a04c028418e2d8a08fc43312aae5fe3b`.
- Branch: `qa/customer-wald05-backend-2026-09-09`; local only.
- User explicitly authorised W5Q-03 and W5Q-04 corrections and fresh acceptance, not WALD06,
  multi-site design, dependency remediation, main, push, production or deployment.
- W5Q-01/02/05 remain preserved. No generic Wald, dictionary, existing projection guard,
  route, UI, dependency or historical migration was changed.
- Backend application identity advances to v3. Projection and transient digest remain v2.
  Historical staged application-v2 manifests cannot silently commit under new behavior.

### W5Q-03: durable intent and immutable outcome

`CommitAttemptJournal` surrounds the existing `ImportStore` business operation. Two new private
tables retain a bounded immutable intent and at most one immutable terminal outcome for an
actor/command UUID. The forward migration adds restrictive ownership references, a composite
preview/run foreign key and four unconditional UPDATE/DELETE guards. No existing data is rewritten.

1. A short transaction registers intent before business execution, using current stored authority,
   a scoped run and a hash of the complete command payload. An identical command reuses the intent;
   changed payload under that token conflicts. Unknown targets cannot create orphan journal entries.
2. Execution obtains current actor/role/owner/site locks, then the attempt lock, then enters the
   unchanged import operation. Its run/stream/knowledge/binding/Portal aggregate lock order remains.
3. Business work has a savepoint inside the audit execution transaction. Success effects, existing
   success command audit, business receipt and SUCCEEDED outcome commit together. A semantic refusal
   or injected exception rolls back the business savepoint; its terminal audit outcome then commits.
4. Recognised MySQL concurrency errors escape to the bounded whole-transaction retry (three total
   attempts). Exhaustion records FAILED after rollback. Validation/stale/authority failures are not
   retried as transient errors. No per-row commit, queue, mail or legacy-importer fallback exists.
5. A crash or unavailable outcome store can leave an intent with **no terminal outcome**. This is
   incomplete evidence, never fabricated success/failure. A matching command may resume; success
   receipts and terminal outcomes prevent duplicate effects. If the database cannot register intent,
   business execution never starts. Persistence availability cannot be guaranteed by an audit table.

Runtime commit callers must enter without an ambient transaction; otherwise they are refused.
Only the framework's testing environment permits its enclosing rollback-isolation transaction.
Real MySQL worker and standalone upgrade calls exercise the actual top-level boundary.

Outcomes are SUCCEEDED, FAILED, REFUSED, STALE or CONFLICTED; these are audit classifications,
not new import-run product states. Same-key terminal failure remains terminal: retrying corrected
conditions requires an explicit new command, preserving the old historical outcome. Successful
same-key replay reauthorises access and returns the existing result. New-key receipt replay is
attributed separately without repeating projection effects.

The intent retains run/preview/stage references, actor ID and stored role/active snapshot, source
date/slot, workbook hash, binding/version references, profile identities, component pins, knowledge
hash and command identity. It does not retain workbook rows, display filename, stack traces,
credentials or raw exception messages. Metadata is bounded at 64 KiB; source row inspection is
bounded by the existing 500-row unit. An authorised paginated audit query caps each page at 100.

Authority loss is audited only for a known non-preview uploader/reviewer on the exact scoped run.
Unrelated external probes receive the normal generic denial and no linked record or target data.
This is not a new general security-event system. Audit access remains current Office/private.
Existing run holds and success-receipt retention references remain protected; no disposal scheduler
or new failed-attempt deletion period is invented. Future disposal must respect these dependencies.

Positive tests cover expiry, revoked binding/profile, projection epoch, historical dictionary/reader
pins, inactive/role-changed actors, ordering/duplicate/identity review blockers, rollback after
observations/products/receipts/required audit, exact replay, command collision, private reads,
direct SQL/ORM immutability and outcome-store recovery. Historical-pin cases construct new immutable
fixture rows; they do not disable guards or mutate executable constants during acceptance.

### W5Q-04: operation-local batch eligibility

The cause was one `KnowledgeQueries::receiptEligible` invocation per applied use. Each invocation
reloaded authority, context, profiles, selected versions and provenance. `ImportKnowledge` now
retains one fresh reuse-authority check and passes its already scoped/locked dependencies to
`ProfileReceiptBatch`. Selected active versions and current provenance are each loaded once;
matching and every receipt check then use bounded in-memory collections, not SQL in loops.

The existing 50-profile/50-use overflow guards remain. Root SQL includes organisation, site,
namespace and family; child queries are constrained by those selected IDs. No global hydration,
cross-operation cache, first-profile winner, competitor truncation or increased budget was added.
Revocation, expiry, epoch/version, current answers/evidence, descriptor compatibility, dictionary/
reader pins, provenance and competing matches remain vetoes. Receipt selection equality is also
checked against the current matching result.

| Applied receipts | Original QA commit SELECTs | Corrected fresh-acceptance SELECTs |
|---|---:|---:|
| 1 | 60 | 70 |
| 7 | 138 | 70 |
| 20 | Not measured | 70 |

The extra fixed audit work explains the higher one-receipt cost. The positive acceptance assertion
requires maximum minus minimum <=2 and maximum <=100; observed fresh-acceptance growth is zero. Existing
7/100/500-row assertions remain <90 reads and <384 MiB, unchanged. Invalid-use batches, actual
competing profile activation and superseded answer provenance refuse. No stale authority is cached.

### Fresh verification

All final application processes started after the corrected commit. Executable files remain fixed
for the entire acceptance run; the earlier mixed-definition race qualification is not reused.
Disposable MySQL 8.4.11 is independently initialised in
`storage/app/wald05-requal-20260909/data`, bound only to 127.0.0.1:33489, X protocol off.
No Windows service, environment file, normal local database or production resource is used.

Preflight only (not final acceptance): focused 215 passed / 23 MySQL skips / 747 assertions;
three later positive tests passed / 18 assertions; MySQL retry/schema 5 passed / 27 assertions;
WALD04 additive upgrade preserved all 34 old tables and refused all three populated rollbacks.
One early test found revoked-binding refusal classified REFUSED; corrected its audit classification
to STALE while preserving the original exception and source-binding behavior.

The corrected candidate was committed at 12:13:21 +01:00, before any final acceptance process.
No executable/test/migration edits occurred during those processes. Final results:

| Gate | Total | Passed | Skipped | Assertions | Failures/errors |
|---|---:|---:|---:|---:|---:|
| Focused WALD05, SQLite | 241 | 218 | 23 | 765 | 0 |
| Combined Wald, SQLite | 1,129 | 1,095 | 34 | 5,005 | 0 |
| Full CustomerApp, SQLite | 1,363 | 1,314 | 49 | 6,193 | 0 |
| Independent WALD05 non-race, MySQL 8.4.11 | 218 | 217 | 1 | 763 | 0 |
| Fresh MySQL races/retry/schema | 23 | 23 | 0 | 1,493 | 0 |

The two disjoint MySQL suites total **240 passes, one SQLite-only skip, 2,256 assertions**
and zero failures/errors. Upgrade scripts and the extra measurement rerun are additional,
not double-counted. All five final JUnit reports contain zero failures and errors.

Fresh concurrency evidence: **320 groups / 640 separate worker processes / zero failures**:

- Eight retained backend scenarios x20 =160 groups: double commit, same Call No. update,
  AM/PM, older/newer, binding change, profile revocation, projection epoch, same-slot correction.
- Four retained foundation scenarios x10 =40 groups: activation/activation, exact command
  replay, draft/draft, activation/revocation.
- Three independent QA scenarios x20 =60 groups: analysis claims, actually applied profile
  receipt against revocation, cross-family visit writers.
- Three new attempt-audit scenarios x20 =60 groups: simultaneous stale commits, concurrent
  identical command and failed-attempt/successful-commit races.

The 18 scenario tests plus four retry cases and one schema case all passed in 1,489.386 seconds.
No iteration count or loser/effect assertion was reduced. The new audit scenarios retained
exactly 100 terminal records: 40 STALE, 51 SUCCEEDED and nine FAILED, with no missing or duplicate
terminal outcomes. Exact-command pairs shared one attempt; distinct commands retained separate
truth. Both real failure and receipt-replay orderings occurred in the failure/success scenario.

SQLite skips include MySQL-specific gates; the MySQL non-race skip is SQLite-specific.
No environment skip is promoted to a pass. Broader non-WALD05 MySQL races are not rerun here.
JUnit evidence is retained locally (ignored, not committed):
`storage/framework/testing/wald05-requal-final-{focused,combined,full,mysql-nonrace,races}.xml`.

Exact acceptance commands, from the repository root:

```powershell
php artisan test tests/Feature/Wald05 tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-requal-final-focused.xml
php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 tests/Unit/Wald05 tests/Feature/Wald05 --compact --log-junit=storage/framework/testing/wald05-requal-final-combined.xml
php artisan test --compact --log-junit=storage/framework/testing/wald05-requal-final-full.xml
# MySQL processes explicitly use testing, mysql, empty DB_URL, loopback port 33489,
# synthetic database names below, root/empty local-only password, array mail/cache/session.
$qaTests = @(rg --files tests/Feature/Wald05 | Where-Object { $_ -notmatch 'Mysql' })
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml @qaTests tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-requal-final-mysql-nonrace.xml
php artisan migrate --force --no-interaction
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/MysqlBackendQaTest.php tests/Feature/Wald05/MysqlBackendConcurrencyTest.php tests/Feature/Wald05/MysqlFoundationConcurrencyTest.php tests/Feature/Wald05/MysqlAttemptConcurrencyTest.php --compact --log-junit=storage/framework/testing/wald05-requal-final-races.xml
php scripts/verify-wald05-qa-upgrade.php --acceptance
php scripts/verify-wald05-qa-upgrade.php --from-wald05 --acceptance
php vendor/bin/pint --test
composer validate --strict
composer audit --format=json
npm run build
git diff --check
```

The independent non-race schema is `customerapp_wald05_requal_nonrace`; the separate race/retry
schema is `customerapp_wald05_requal_final`. The latter migrated all 16 migrations successfully.
Both standalone upgrade checks completed successfully after the candidate commit:

- `customerapp_wald05_requal_upgrade_final`: WALD04 baseline 13 migrations plus three additive
  migrations; all 34 pre-existing data tables preserved, allowing only the newly added epoch field.
- `customerapp_wald05_requal_upgrade_current_final`: existing WALD05 baseline 15 migrations plus
  the new audit migration; all 45 pre-existing data tables preserved, including existing epochs,
  Portal dates/products/history and synthetic prior binding/staging/knowledge evidence.
- Both: empty rollback/reapply passed; all three WALD05 populated rollbacks refused **before
  any DDL or immutability guard removal**. Existing migrations were not edited.
- Direct fresh-MySQL schema inspection confirmed four new audit guards and five restrictive
  foreign keys. Retry evidence contains four SUCCEEDED outcomes (including explicit new-command
  replays), one FAILED `transient_retries_exhausted` and one FAILED
  `business_transaction_failed`; no terminal audit category contains provider output.

MySQL 7/100/500-row benchmarks retained fixed 64 commit reads without source completion and 66
with completion. Maximum observed test-process memory was 100,663,296 bytes (96 MiB), beneath
the unchanged 384 MiB assertion; largest completion case took 36.332 seconds while parallel QA
was running. Combined SQLite peaks were 111,149,056 bytes (106 MiB). These are synthetic local
budgets, not production latency promises. Profile receipt growth stayed zero at 1/7/20.
An additional fresh MySQL evidence-only rerun of `DedicatedBackendQaTest.php` with
`--filter='receipt-count query slope' --compact` passed one test/four assertions and printed
70/70/70 exactly (30.294 s); it is not double-counted in the disjoint acceptance totals above.

Full Pint and `composer validate --strict` passed. `npm run build` passed (Vite 8.1.4, 7.93 s);
the sandbox initially denied the build subprocess, then the approved local rerun succeeded.
`composer audit --format=json` returned exit 1: eight inherited advisories, five high, two medium,
one low, across Filament, CommonMark and Livewire. A preliminary summary script miscounted JSON
properties; the corrected fresh audit confirmed **eight**, not 72. No manifests/lockfiles or
separate remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` were merged or changed.
These remain a separate security/release gate, not a hidden pass or a new WALD05 defect.

Static review found explicit insert-column allowlists rather than request mass assignment;
audit reads freshly authorise Office scope and resolve the exact scoped run. No new route,
customer serialization, raw-error logging, legacy importer coupling or external-service access
was added. Browser/mobile/accessibility and HTTP upload controls remain untested later UI gates.
All 11 correction PHP files passed `php -l`; all 33 local Markdown links in the eight task
documents resolve. `git diff --check` passed. A direct comparison with handoff `324cacfd...`
confirmed the entire original report body from `## Overall result` onward is unchanged.

After all MySQL gates completed, the exact loopback server/version/port/datadir was reverified
and `mysqladmin --no-defaults --protocol=TCP --host=127.0.0.1 --port=33489 --user=root shutdown`
requested a normal shutdown at 11:39:19 UTC; the log confirms **Shutdown complete** at
11:39:20.876 UTC. The port was no longer listening. Disposable
schemas/logs remain private local evidence; nothing was deleted or applied to the normal local
or production database. This task adds one forward migration, applied only in disposable QA.

The separate multi-site **PILOT_BLOCKER** remains WALD06/pilot design work, not a W5Q-03/W5Q-04 backend
defect. One bound site/table is internally bounded; multiple site commits cannot bypass the
shared date/slot ordering contract. No pilot design or real-workbook import occurred.

Recommendation: freeze the corrected executable/test SHA as the QA-passed **bounded backend**
baseline, then separately scope multi-site pilot architecture and full Office UI. Keep dependency
security, browser/device/accessibility, operations, cutover and production release gates separate.
No architecture decision is required to close these two corrected backend defects.

### Correction files

- `app/SourceImport/Integration/BackendStore.php`
- `app/SourceImport/Integration/CommitAttemptJournal.php`
- `app/SourceImport/Integration/ImportKnowledge.php`
- `app/SourceImport/Integration/ImportStore.php`
- `app/SourceImport/Integration/ProfileReceiptBatch.php`
- `database/migrations/2026_09_09_000013_create_wald_commit_attempt_audit.php`
- `scripts/verify-wald05-qa-upgrade.php`
- `tests/Feature/Wald05/CorrectionRequalificationTest.php`
- `tests/Feature/Wald05/DedicatedBackendQaTest.php`
- `tests/Feature/Wald05/MysqlAttemptConcurrencyTest.php`
- `tests/Support/Wald05ConcurrencyWorker.php`

Documentation-only handoff files (separate from the fixed executable candidate):

- `documentation/wald/customer-wald05-backend-qa-2026-09-09.md`
- `documentation/source-integration-contract.md`
- `documentation/wald-divergence-register.md`
- `DECISIONS.md` (append-only DEC-054 records the latest explicit user authority)
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

Unrelated local edits to `documentation/sprint-3e-date-negotiation-report.md`, the untracked
`Copy of siteapp1.xlsx` and `output/` are preserved and excluded. No workbook, generated output,
database, credentials or environment file is staged. Main and cached origin/main remain
`0873bac79edf578e9f4a9417e3cafae34e8aa925`; there was no fetch, push or remote action.

## Historical original dedicated gate — FAIL, before W5Q-03/W5Q-04 correction

The remaining sections record the original candidate review, partial corrections and failed
handoff. They are retained evidence, not the fresh corrected candidate's acceptance result.

## Overall result

**FAIL — corrections required. Do not freeze this branch as an accepted WALD05 baseline.**

The bounded backend works through private intake, deterministic analysis, explicit review and
atomic projection commit. Independent QA found and corrected a namespace-wide visit ordering
bypass and a canonical digest mismatch. Two further bounded defects remain open: missing durable
refusal/failure audit and repeated profile-receipt queries under commit locks. Passing regression
tests do not constitute acceptance of those known gaps.

This is feature-branch QA only. No main, push, GitHub Actions, production, customer import, SiteApp,
WALD06, full Office UI or Composer-remediation merge occurred. Browser/mobile/accessibility and
HTTP upload security are later UI gates; no nonexistent endpoint is certified here.

## Candidate and accepted components

- Original branch: `feature/customer-wald05-import-review-integration`.
- Exact original candidate: `1dc6ee6a24026c970687472126dc295fb6f2b3c8`.
- Local QA branch: `qa/customer-wald05-backend-2026-09-09`, created from that exact candidate.
- Foundation: `d1b5de13b260dde77867fdb8ac73296e49994c14`, verified ancestor.
- Accepted WALD04: `0e83eb2896e7c5144bc38c1be9713f3d205d93b8`, verified ancestor.
- QA correction commit: `9f5751f7a6d62b4ae989e34f3894e3b57011a0a8`; it is **not an accepted baseline**.
- Local main and cached origin/main remain `0873bac79edf578e9f4a9417e3cafae34e8aa925`.

Reviewed the complete new integration runtime, both additive migrations, accepted-component delta,
all WALD05 test/helper files and implementation report. No routes, resources, lockfiles, dependency
manifests or GitHub workflows differ between accepted WALD04 and this candidate. The old importer
and mapper remain untouched and are not invoked by the new path.

DEC-052's narrowly namespaced Excel extension correction is the only generic reader delta from
WALD04: reader `wald-0.2.2`, XLSX adapter 3, CSV 2. It does not change structural scoring or source
date-system authority. WALD03 differs only in its approved reader identity pin. Dictionary v1
fingerprint remains `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.
Candidate generic tree `961831e9f727e41e0fe9c7a969376c2af7caad9f` and semantic tree
`40c8255f5951fe51844edd791ee6a7474df1704c` remain unchanged by QA.
WALD04 keeps its authority, provenance and fresh-evidence vetoes. QA changes only its new transient
digest implementation and collision-prone synthetic fixture naming, not persisted knowledge policy.

## Defects and disposition

| ID | Severity | Evidence and effect | Disposition |
|---|---|---|---|
| W5Q-01 | P2 | `evidenceHash(['nested' => [1 => 'one', 0 => 'zero']])` disagreed with `Canonical::hash` because list/object selection occurred before key sorting. | FIXED: re-evaluate list shape after canonical sorting. The original reproducer failed and now passes. Retained JSON ceilings and the 4 MiB transient ceiling remain unchanged. |
| W5Q-02 | P1 | Commit MORNING visit 1001 with VS 2.125; use the same namespace/site/date/slot but another workbook family and VS 99.000. The original candidate overwrote the visit without correction lineage. A new namespace could also take over the already-owned projection using an older date. Both independent reproducers failed. | FIXED: validate namespace-wide visit order and require the latest observation's run as explicit predecessor for changed same-slot facts; reject takeover of an existing Wald-owned service by a different source identity. Batched observation lookup adds one constant SELECT. Older cross-family observations now also refuse during review. |
| W5Q-03 | P2 | Review, expire it, then attempt commit. The exception is safe and all domain state is unchanged, but command count is unchanged and there is no separate durable refusal/attempt journal. Injected failed commits likewise leave no durable failure outcome. | OPEN: add a bounded, attributable attempt/refusal journal outside the all-or-none domain-effects transaction. Keep success receipts immutable and preserve existing rollback assertions. No new business rule is required. |
| W5Q-04 | P2 | Seven identical, valid, explicitly applied profile-use receipts cause 138 commit SELECTs, versus 60 for one receipt on the same seven-row shape. `ImportKnowledge::capture` calls `receiptEligible` independently for every use, repeatedly loading authority, context, profiles, versions and provenance. | OPEN: batch current receipt eligibility per scoped context with bounded queries; preserve each receipt's pins, fresh-answer veto, revocation and competing-profile checks. The original row-only benchmark did not cover this dimension. |
| W5Q-05 | P3 | A longer persistent MySQL race rehearsal failed on duplicate randomly generated organisation name `Heaney Group`, before the race itself. | FIXED: the shared Wald synthetic owner helper now uses UUID-qualified organisation names, as it already does for user email. No application factory or race assertion was weakened. |

W5Q-01/02 advance the backend application/projection/digest identities to v2, so v1 staged
manifests cannot silently commit under corrected behavior. No stored historical evidence is rewritten.
The original two-reproducer run was 0/2 passing; subsequent focused checks pass.
The refusal-audit and receipt-query tests deliberately record the present deficiencies as diagnostic
evidence, not normative acceptance tests. They must be replaced by positive protection assertions
when the corresponding corrections are implemented.

## Schema / migrations

No historical migration was edited and no QA migration was added. The two candidate migrations
add three foundation tables and eight backend tables, six foundation guards, 19 backend guards
and three projection epoch columns. Composite site/organisation ownership, unique stream slot
revision, unique run receipt and visit observation versions are enforced. All immutable tables
reject update/delete; protected identity comparisons are binary on MySQL. Mutable stage/preview
pointers are checked against run ownership at application boundaries rather than relying on UUID
possession; these pointers are not client-writable endpoints.

Independent disposable MySQL 8.4.11 at **127.0.0.1:33488**, X protocol disabled, CustomerApp-owned
data directory `storage/app/wald05-qa-20260909/data`. Official local binary signature was Valid.
No persistent Windows service or environment-file change was made.
After all MySQL checks, the exact server port/bind address/data directory were reverified.
Normal shutdown completed at 10:35:21 UTC on 9 September 2026 and port 33488 no longer listens.
The five disposable synthetic schemas and local evidence are retained; none was dropped/reset.

- Clean schema creation executes through the MySQL feature-test setup.
- Existing backend upgrade script: 14 baseline migrations plus one backend migration; 15 old
  tables preserved; empty rollback/reapply passes; populated backend rollback refuses before DDL.
- Independent QA upgrade script: 13 accepted WALD04 migrations plus both WALD05 migrations;
  all 34 pre-existing tables snapshotted. Existing products, agreed dates, request/history,
  organisation/site and knowledge rows remain unchanged through upgrade and empty two-step
  rollback/reapply. Both populated migration rollbacks refuse without removing guards.
- Binary case/trailing-space identity attacks and MySQL constraint-name length checks pass.

The first independent upgrade attempt referenced a nonexistent service factory and stopped after
baseline setup. The corrected harness uses the actual models and a new empty `_v2` schema;
the first synthetic schema was retained, not reset or reused. The successful script result,
not Laravel's incidental exception-process exit status, is the acceptance evidence.

## Upload / storage / security

Private generated UUID keys live beneath `storage/app/private/wald-imports`. Original filenames
are display data, never path or permission authority. Four additional traversal/script-like
filename cases remain confined; they are not browser-rendering/XSS tests. Authenticated uploader
ID/name come from the stored account, not dirty caller attributes. Exact date/slot confirmation
is mandatory and immutable. Content type, format, size, reader open, content hash and reopen
integrity checks are present; stored symlinks and invalid keys refuse. CSV/XLSX use the accepted
non-executing reader. Formula/error cells block; prompt-like cells are inert data.

All three external roles, inactive Office and persisted preview status are independently tested
against upload, analysis claim, clarification read, review, commit, retry, private details and
retention. Foreign run UUIDs refuse under a different explicitly valid tenant/site scope.
Foundation tests independently cover binding draft/activate/revoke/resolve/history scope and
authority. Null-organisation active Office works only with explicit valid scope. No new durable
impersonation capability exists. No public upload/download or HTTP CSRF/throttling claim is made.

## Ordering / Call No. / correction / recovery

Export Date then MORNING/AFTERNOON is authoritative, never upload/file time. Older day/slot
refusals, same-slot conflict, explicit reasoned successor, immutable predecessor receipt, exact
successful replay and uncertain-client-response recovery are tested. A successful command replay
first reauthorises current access. An uncertain success is simulated by discarding the returned
result and reissuing the same and a new command; this is not a network fault-injection claim.

Duplicate Call Nos. block staging/review, including identical duplicate evidence. Later plot or
service reassignment refuses. Binding scope prevents cross-site reassignment. Distinct CM1/CM2
meaning remains dictionary-owned; a new Call No. is not collapsed into an old visit. The bounded
adapter refuses a competing new visit for an already represented plot/service rather than inventing
a revisit roll-up. W5Q-02 now closes the separate cross-family same-slot bypass.

## Bounded unit / multi-site pilot implication

One explicitly bound site, one visible unmerged sheet/table, maximum 500 nonempty rows.
Independent one-row success and empty/merged/hidden-row/two-visible-sheet/hidden-sheet refusals
pass. Existing 7/100/500 cases pass and 501 refuses before any staged subset/receipt. Every
included source-site identity must bind to the chosen destination; unresolved foreign sites fail.
Wider evidence can hit independent retained-payload or analysis budgets below the row ceiling;
500 rows is a maximum, not a universal workbook-capacity promise.

**PILOT_BLOCKER for the proposed multi-site splitting workflow.** The one-site backend boundary
itself is safe, but a second explicitly reviewed site in the same namespace/family/date/slot
returns `slot_scope_conflict` after the first site commits. Therefore an Office UI cannot simply
split a twelve-site export into twelve same-slot commits under the approved ordering contract.
Do not fabricate per-site family names or change dates/slots to evade that rule. WALD06 needs an
explicit parent-export/unit ordering design or other approved contract change. This is separate
from the two open backend QA defects and does not authorise implementation now.

The actual twelve-site workbook was not uploaded, staged, reviewed or committed. DEC-050/051
synthetic helper tests check exact-artifact approval, raw CC! preservation, scoped CC1 correction,
CM2 exclusion and changed-hash/row refusal. Synthetic end-to-end occurrence clarification proves
an unrelated workbook needs its own answer. No synthetic file is falsely given the real file's
content hash at intake. No global CC! alias, CM2 exclusion or test-site rule exists.

## Partial exports / products / completion / Portal protection

Only PARTIAL_FILTERED_EXPORT commits. Both stronger scopes block. Omitted plot/call/service
facts stay unchanged; absent product columns preserve stored quantities. Present blank is
retained as raw presence and applies the existing supplied-record zero contract; explicit zero,
exact positive and invalid quantity paths remain distinct. The accepted dictionary regression
covers all approved Windows/Doors and excluded product codes. Integration exercises VS/BF,
excluded commercial/product fields, formula/date/percentage/boolean refusal and partial omissions.
Unknown quantities are not coerced to zero. Unknown headings receive no invented product meaning.

Only exact approved positive BF contributes the BF fact; no WALD05 lead-time calculation was added.
Yes/No completion and reversal use approved domain semantics, with no invented completion date.
Operational `Plot To Be Installed` remains private source evidence, not requested/agreed/proposed,
amended or completion date. Unknown service plus Yes blocks.

Preview captures current projections, products, requests, negotiations, proposals, batches and
history without mutating them. Review evidence alone persists. Existing Portal protection tests
seed amendment-purpose negotiation, agreed/requested dates, responses, actors and old history;
completion closes current process/proposals and conflict keys while retaining those facts.
Injected failure after completion rolls all effects back. Reversal never reopens the old request.
No completion notification. This is the amendment-shaped domain available on this branch,
not certification or integration of the separate Sprint 3F implementation.

## Preview / stale-state / lifecycle

Immutable manifests/previews pin workbook, canonical/stage hash, manual order, scope, binding,
knowledge/answers/use receipts, reader/dictionary/executable/backend identities, stream epoch,
projection/history digest, reviewer/time and expiry. Current dependencies are checked on approval
and commit. Source bytes and immutable date/slot fields cannot be edited through application APIs.
Changing a relevant mutable dependency refuses rather than refreshing a reviewed plan.

Controlled-clock tests pass just before preview expiry and refuse exactly at and after expiry.
Run-state matrix refuses UPLOADED/FAILED/SUPERSEDED/COMMITTING/COMMITTED → commit or review
without a valid allowed path; existing committed receipts retain their safe historical replay path.
Analysis uses generation/epoch/600-second lease fencing, explicit recovery and bounded claims.
Failed/expired work cannot jump directly to commit. Browser/persistent-worker recovery remains a
later interface/operations gate, not an implemented queue worker.

## Atomicity / audit / retention

One transaction covers projections, completion, immutable observations, receipt, stream/run state
and the required successful command audit. Injection before/after observation, product,
completion, receipt, source-run, stream and audit writes proves rollback. No per-row transaction
or legacy fallback is called. Existing immutable success replay creates no duplicate business
effects. Source observations and receipt predecessors preserve prior truth.

Metadata implements terminal workbook +30 days, bulky staging +7 days, preview validity 24 hours,
preview payload +7 days and minimal committed audit/observations six years. Explicit run holds
override age eligibility; no purge API, worker or scheduler exists. Named disposal ownership and
dependency-aware actual disposal remain future gates. W5Q-03 means **failure/refusal audit is not
complete**, even though success audit and atomic rollback are sound.
The six-year period is the approved company operational policy, not a legal-compliance claim.

## Lock order / retry / concurrency

Current effective import order: actor → shared role → organisation → shared site → import run →
source stream → scoped profile roots/context/questions → binding roots → plots → services →
products → requests → negotiations → proposals → history → batches → source-call services →
visits → observations/receipt/command. Intake replacement acquires stream before predecessor run;
same-organisation owner serialization protects that path. WALD04 begins with the same actor/owner
locks, then profiles/context. Source completion consumes the already locked Portal aggregate.
The adapter's service/request/negotiation/proposal/history subsequence is preserved. Cross-tenant
source collisions refuse and unique visit identity supplies the final guard.

Race coverage is real separate-process MySQL, not SQLite timing. Existing eight backend scenarios
are increased to 20 iterations each; four foundation scenarios remain ten each. Independent QA
adds 20 each of claim/claim, revocation against an actually applied profile receipt, and conflicting
cross-family visit commits. Exact loser exception/effect counts are asserted; no broad acceptance
of arbitrary exceptions. Tests exercise both possible serialization outcomes but do not claim to
prove the absence of every possible deadlock cycle or every separate Sprint 3F/customer-action race.

Recognized 1213/40001 and 1205/HY000 failures are server-signalled at receipt preparation: two
failures then success, persistent transient exhaustion at three, and nontransient 45000/1644 once.
These verify Laravel's bounded transaction retry path, not naturally occurring deadlock frequency.
An initial synthetic 1205 used an unrealistic message and was not classified by Laravel; the
fixture now uses the actual MySQL timeout wording. Authorization/stale/semantic/ordering conflicts
are not transaction concurrency exceptions and are not automatically retried.

## Verification totals and performance

Candidate-only focused baseline: 114 passed / 12 MySQL skips / 410 assertions.

| Final verification | Total | Passed | Skipped | Assertions |
|---|---:|---:|---:|---:|
| WALD05 focused | 203 | 183 | 20 | 573 |
| Combined Wald | 1,091 | 1,060 | 31 | 4,813 |
| Full CustomerApp | 1,325 | 1,279 | 46 | 6,001 |
| MySQL WALD05 non-race | 183 | 182 | 1 SQLite-only | 571 |
| MySQL race / retry / schema | 20 | 20 | 0 | 939 |

The final MySQL gate passed 260 race groups (520 separate worker processes): 160 existing
backend groups, 40 foundation groups and 60 new QA groups. All five final JUnit files record
zero failures and zero errors. MySQL totals across the two disjoint final suites are
202 passed / one SQLite-only skip / 1,510 assertions. Upgrade-script checks are additional.
Rehearsal qualification: the long-lived race runner started before the final namespace-ownership
guard was added; its already-loaded parent classes were not reloaded. Final focused, combined,
full and MySQL non-race runs cover that guard, but the race totals must not be presented as an
immutable full-SHA acceptance run. The next correction gate must rerun races from its frozen candidate.
No skipped guarantee is called a pass.

Ordinary full-suite MySQL-only skips are exactly:

| File under `tests/Feature` | Skips | Disposition |
|---|---:|---|
| `Sprint3eMysqlConcurrencyTest.php` | 14 | Existing separate release gate; not rerun here. |
| `Sprint3eMysqlSchemaTest.php` | 1 | Existing separate release gate; not rerun here. |
| `Wald04/MysqlConcurrencyTest.php` | 7 | Accepted baseline evidence retained; not counted as this task's MySQL passes. |
| `Wald04/MysqlRetryQaTest.php` | 4 | Accepted baseline evidence retained; WALD05 retry is independently tested. |
| `Wald05/MysqlBackendConcurrencyTest.php` | 8 | Independently exercised on disposable MySQL. |
| `Wald05/MysqlBackendQaTest.php` | 8 | Independent QA retry/schema/race cases on disposable MySQL. |
| `Wald05/MysqlFoundationConcurrencyTest.php` | 4 | Independently exercised on disposable MySQL. |

Pint, all 12 task PHP syntax checks, 31 checked documentation links and whitespace checks pass.

Row-only commit reads are constant at 47 for new incomplete visits and 49 for existing-request
completion across 7/100/500 rows, one more than the original 46/48 because W5Q-02 adds a batched
observation check. Writes scale with required per-visit observations/events. Final disposable
MySQL timings were 1.284/4.188/18.035 seconds incomplete and
1.389/6.106/54.808 seconds completion; process memory reached 96 MiB. These are local diagnostics,
not production SLAs. W5Q-04 is the separate receipt-count slope: 60 → 138 SELECTs for 1 → 7 receipts.

Composer validation passes. Composer audit exits 1 with the same eight advisories: five high,
two medium, one low across Filament/CommonMark/Livewire. No dependency changed and remediation
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. This remains a separate release gate.
Build passes after the known Windows sandbox child-process EPERM and an approved local rerun.
No deployment was triggered by the build.

## Commands and reproducible evidence

All test data is synthetic. Command outputs/JUnit and disposable databases remain ignored/local.
No secret or actual source workbook is committed.

```powershell
php -v
composer --version
node -v
npm -v
git --version
php artisan test tests/Feature/Wald05 tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-focused-v2.xml
php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 tests/Unit/Wald05 tests/Feature/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-combined-v2.xml
php artisan test --compact --log-junit=storage/framework/testing/wald05-qa-final-full.xml
# Process-local only, no .env modification:
$env:APP_ENV='testing'; $env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'
$env:DB_PORT='33488'; $env:DB_DATABASE='customerapp_wald05_qa'
$env:DB_USERNAME='root'; $env:DB_PASSWORD=''; $env:DB_URL=''
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald05/MysqlBackendQaTest.php tests/Feature/Wald05/MysqlBackendConcurrencyTest.php tests/Feature/Wald05/MysqlFoundationConcurrencyTest.php --compact --log-junit=storage/framework/testing/wald05-qa-final-mysql-races.xml
$env:DB_DATABASE='customerapp_wald05_qa_nonrace'
$qaTests = @(rg --files tests/Feature/Wald05 | Where-Object { $_ -notmatch 'Mysql' })
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml @qaTests tests/Unit/Wald05 --compact --log-junit=storage/framework/testing/wald05-qa-final-mysql-nonrace.xml
# Upgrade scripts use their exact separate empty database names, recorded above.
php scripts/verify-wald05-backend-upgrade.php
php scripts/verify-wald05-qa-upgrade.php
php vendor/bin/pint --test
composer validate --strict
composer audit --format=json
npm run build
git diff --check
```

## Existing importer / pilot / recommendation

Retain the old importer but never call it from Wald. Its obsolete stage mapping, source-wide
absence, missing-product zeroing and per-record commits are not the parity oracle. No wholesale
non-main UI/profile merge, retirement or full cross-database legacy comparison was performed.
WALD06 still needs approved parity/cutover, explicit multi-site ordering design, Office UI,
worker/storage/backup/retention operations, real-world corpus, accessibility/device testing and
combined security/release reconciliation. No new source meaning has been invented here.

Complete W5Q-03 and W5Q-04 on a separately reviewed correction candidate, rerun their positive
regressions and the relevant full/MySQL gates, then return for final WALD05 backend acceptance.
Do not freeze either the original candidate or this partially corrected QA branch yet.

## Files changed / production impact

The 18 task files are:

- `app/SourceImport/Integration/BackendStore.php`
- `app/SourceImport/Integration/ImportReview.php`
- `app/SourceImport/Integration/ProjectionSnapshot.php`
- `app/SourceImport/Knowledge/Canonical.php`
- `tests/Feature/Wald05/DedicatedBackendQaTest.php`
- `tests/Feature/Wald05/MysqlBackendQaTest.php`
- `tests/Feature/Wald05/MysqlBackendConcurrencyTest.php`
- `tests/Support/Wald04Fixtures.php`
- `tests/Support/Wald05ConcurrencyWorker.php`
- `tests/Support/Wald05Race.php`
- `tests/Support/Wald05QaProfiles.php`
- `scripts/verify-wald05-qa-upgrade.php`
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`
- `documentation/wald-divergence-register.md`
- `documentation/wald/customer-wald05-backend-qa-2026-09-09.md`

The final local commit identity is recorded in the completion handoff.
The unrelated modified Sprint 3E report, untracked `Copy of siteapp1.xlsx` and `output/` were
preserved and excluded. No migration ran outside the disposable schemas; no normal local
application database or production state changed. No main merge/push, tag or deployment.

CUSTOMER-WALD05 dedicated backend QA failed — corrections required
