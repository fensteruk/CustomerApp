# CUSTOMER-WALD04 Dedicated QA Report

Date: 8 September 2026. QA branch: `qa/customer-wald04-2026-09-08`.
Scope: the approved DEC-046 / CUSTOMER-WALD04 backend contract, not WALD05 or release approval.

## Overall Result

PASS after two runtime corrections and two test-harness corrections. The corrected QA output
may be frozen for management acceptance; this is not production release approval.

## Candidate

Original: `2c7d0154e51a35b165c7e93f7dd256e2cf0f030f`, from
`feature/customer-wald04-knowledge-profiles`. Corrected executable SHA:
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`.
The final documentation-inclusive output SHA is reported in the task handoff.
All work is feature-branch only, not on `main`, not deployed and not release-approved.

## WALD03 Baseline Integrity

PASS. The candidate's 51-file committed change set was reviewed against accepted baseline
`a80ce7d14206cf3f3a9343448d406f01ae927b88`. No changes to `app/Wald`,
`app/SourceImport/Semantics`, their accepted identities, routes, views or dependency lockfiles.
The plain-PHP WALD03 replay still produces
`e89609be49f056a7f9a17672da3860ae0319657ba7634725ea9c16ae17db8c5f` for all nine
timezone/precision combinations, including numeric locales C, de-DE and fr-FR. No Composer,
Laravel, database driver or forbidden class is loaded by that replay.

## Schema / Migrations

Eight knowledge tables remain separate from Portal request/source tables. Composite site-owner,
active-version and receipt-version foreign keys, restrictive actor/provenance relationships and
sequence/version/command uniqueness were inspected. Names meet MySQL's 64-character bound.
There are no SQL CHECK constraints claiming to implement the business lifecycle: authorisation,
state transitions and hashes are service invariants; immutable truth has database triggers.

QA added `2026_09_08_000010_harden_wald_evidence_comparisons.php`; the original WALD04 migration
and all 11 baseline migrations are unchanged. Independent MySQL rehearsal passed baseline
installation, additive upgrade preserving two database-snapshotted synthetic owner/site rows,
empty rollback and reapply. Thirteen migrations total: 11 baseline plus two additive.

## Immutability Triggers

PASS after W4Q-01. Sixteen triggers protect immutable answers, versions, events and receipts,
and protected context/question/profile/evidence columns and deletes. The original MySQL guards
used collation-sensitive equality: nine new attacks successfully changed case, accents or
trailing spaces without rejection. The forward migration uses null-safe binary comparisons.
The identical attacks now fail at the database boundary and leave the values unchanged.
SQLite's existing exact comparison remains unchanged. Mutable hold/lifecycle metadata still works.

## Rollback Safety

PASS. Both Wald migrations refuse populated rollback before DDL for context-only, active,
revoked and receipt-bearing stores. Schema/triggers remain intact. Empty rollback/reapply passed
on disposable MySQL, preserving pre-existing synthetic Portal rows. The hardening migration also
refuses to weaken guards on populated stores. MySQL trigger replacement is DDL, not a transactional
deployment promise: any future approved deployment must use its maintenance/recovery procedure.

## Tenant Isolation

PASS. Fifty-two independent action/read attacks cover organisation, site, namespace and family
boundaries across successor registration, answer, draft, activation, revocation, reuse, close,
hold, questions, reviewed answers, effective state, receipts and disposal eligibility. Foreign
UUIDs fail resolution; the other scope's audit list stays empty. Null-organisation Office users
can act on a valid explicit owner/site scope, never null/global learned knowledge.

## Authorisation

PASS. All three external roles are denied all nine knowledge capabilities. Real, active,
non-preview Office identity is reloaded from storage. Inactive, preview, missing/stale role
and mismatched stored site ownership fail closed. Every mutation passes through the common
authorised transaction boundary and rechecks authority immediately before its audit insert.
There are no new HTTP entry points or customer-facing knowledge endpoints.

## Permission Separation

PASS. Answer, draft, activate, reuse, revoke, audit, evidence and retention are distinct operation
capabilities. V1 deliberately grants these to the same approved Office role; it is not a new
per-user capability administration system. Unsupported capabilities fail closed. Same-actor
activation is permitted by G02, not a missing four-eyes requirement.

## Knowledge Types

PASS. Code-owned dictionary truth, occurrence-only human correction and explicitly activated
structural profile choice remain distinct. Stored selectors do not invent services, source
bindings, call types, quantities, completion vocabulary or Portal request authority.

## One-Time vs Reusable

PASS. Answering alone creates no profile. Saving a draft is separately audited and cannot make
it active. Reuse requires explicit activation and fresh compatibility. Semantic answers cannot
be promoted into reusable structural profiles.

## Activation

PASS. Exact definition hash, version, expected epoch, current pins and unsuperseded answer
provenance are checked. Activation/reapproval is explicit and advances the root epoch.
Revoked versions require a reviewed successor; no silent reactivation of the revoked version.

## Lifecycle / Versions

PASS. Successor answers and profile versions append new records, retaining predecessor links.
Activation changes only the selected root version/epoch. Corrected-away answers and superseded
contexts invalidate reuse; old receipts and history remain truthful records of their original use.

## Reapproval / Retention

PASS. Reapproval becomes due at the exact 12-month boundary without rewriting active history.
The context/receipt 24-hour boundary and 24-month retained-history boundary were tested exactly.
Metadata policies preserve workbook terminal +30 days, observations +7 days, preview validity
+24 hours and preview payload +7 days. Those artifact classes are policy contracts, not evidence
of a WALD05 upload lifecycle. Active/draft provenance conservatively blocks disposal eligibility.
No purge, scheduler, tombstone writer or automatic production disposal was introduced.

## Holds

PASS. Audited placement and removal require a reason and expected metadata epoch. An expired
unheld context becomes age-eligible; placing a hold blocks it; releasing the hold recalculates
eligibility. A context hold protects linked evidence. Active dependencies remain protected.
Automatic disposal stays false regardless of age. G09's named owner is still a future disposal gate.

## Revocation

PASS. Authorised Office actor, valid scope, expected epoch and bounded nonblank reason are
required. Revocation retains versions, audit, evidence and prior receipts while preventing future
eligibility. Repeating a stale competing command does not overwrite the first result.

## Reuse Receipts

PASS. Receipts retain profile/version/epoch, context/evidence hash, current pins, applied flag,
selection and reason. A revoked, changed, expired or newly ambiguous dependency invalidates
future eligibility without rewriting the original receipt. Eligibility reads are advisory;
WALD05 must design its own commit-time locking and cannot treat a receipt as import permission.

## Profile Compatibility

PASS for the implemented bounded selector contract. Exact structure can reuse; column reorder,
recognised role aliases and competing structure require review; missing critical roles,
unknown product substitution and unsafe current value shapes cannot apply. Duplicate selectors,
multiple tables, hidden/warning-bearing structure and fresh ambiguity do not get a first/score winner.
Filename is absent from reusable identity; absolute row movement preserves the descriptor.
Not every conceivable workbook layout has been exhaustively enumerated.

## Fresh Evidence Precedence

PASS. Current ambiguity, unsafe types/formula/error/negative/empty/date-percent evidence,
explicit current answers and competing active profiles veto remembered choices. Historical
confidence does not replace current reasoning. Reviewed results remain separate envelopes with
`ready_for_staging=false`; the original blocked interpretation and raw occurrence remain intact.

## Dictionary Authority

PASS. `customerapp.source-dictionary.v1` remains pinned to
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.
Profiles cannot redirect known PC1 meaning to Cavity Closers or install an unknown product/code.
There is no arbitrary role/business-alias editor, dictionary writer or legacy-profile import.

## Version Compatibility

PASS. Unsupported dictionary, core/confidence, reader/adapter, schema, selector, matcher,
semantic executable, accepted baseline and policy pins fail closed. Same-version fingerprint
change is incompatible, not an accepted new meaning. Corrected WALD03 executable `f4fda0f`
is allowlisted; original `574f196` is not. No label-only blanket compatibility was added.

## CC! / ZZ9 Learning Safety

PASS. CC! requires an explicit matching structural answer before an occurrence-only CC1
correction; raw CC! and original blocked evidence remain. The next occurrence asks again.
ZZ9 stays unresolved and cannot become Windows or a global alias. Dictionary identity is unchanged.

## Evidence / Privacy

PASS. Private models expose UUID only by default. The trusted snapshot recomputes reasoning;
it is not an HTTP deserializer. Evidence bounds remain 64 KiB/question, 50 candidates,
2,000-character reasons and 256 KiB aggregate/profile envelopes. Private source labels/locators
are not customer output. Synthetic instruction-like/script/formula text remains inert data.
No production account, customer workbook, SiteApp connection or external interpretation service was used.

## IDOR / Mass Assignment

PASS. Foreign UUID and integer substitute checks fail. Guarded models reject mass-assigned
actor/site/payload authority; forced fixture attributes still serialize only UUID. Internal
ORM helpers are not public authorisation endpoints; future HTTP integration must pass the
authenticated actor and must not deserialize an arbitrary trusted analysis object.

## Audit / Atomicity

PASS. Injected audit failure in all eight mutation paths rolls back exact snapshots of all eight
knowledge tables, including lifecycle metadata and evidence. Actor, operation, before/after,
reason references, policy and command identities remain attributable. Audit is paginated to 50.

## Idempotency

PASS. Exact actor/command/payload replay returns the existing result with no duplicate history;
changed payload/scope/action conflicts. Same command replay still checks current Office access.
Profile-root replay is not an assertion of renewed eligibility: subsequent state may have changed.

## Concurrency

PASS: 20 iterations each of competing answers,
activation/activation, activation/revocation, revocation/reuse, draft-version/reuse,
competing immutable-version activation, and hold/reuse: 140 groups / 280 barrier-synchronised workers.
Assertions require one winning transition where epochs compete, truthful prior receipts and
ineligible stale dependencies; holds must survive concurrent reuse.

## Lock Order / Retry Behaviour

Actor → owner organisation → profile roots in ascending ID order → context → clarification →
append-only history is preserved for the relevant operations. Analysis is outside write locks;
the organisation anchor serializes same-owner operations. Framework transactions cap retries
at three for recognized concurrency errors, not arbitrary validation/authorisation failures.
Additional MySQL server-signalled 1213/40001 and 1205/HY000 fault tests verify successful third
attempt, exhausted third attempt and single-attempt non-transient 1644/45000 failure without
duplicate context/audit. These injected errors are not claimed as naturally occurring deadlocks.

## MySQL Evidence

PASS: 198 tests / 862 assertions covered without skips across the final non-race gate
(191 tests / 422 assertions) and seven race scenarios (440 assertions). The preceding complete
run passed all 196 then-present tests / 860 assertions in 285.524 seconds. Final review preserved
the original fail-closed exception for malformed active-version provenance during batched reads;
two explicit cases were added and the entire non-race gate reran (191 passes in 78.057 seconds).
The seven unchanged concurrency scenarios are not double-counted with that rerun.

Dedicated Oracle-signed MySQL 8.4.11 instance bound only to `127.0.0.1:33485`,
mysqlx disabled, new task-only data directory `storage/app/wald04-qa-20260908/data`.
Schemas: `customerapp_wald04_qa` and `customerapp_wald04_qa_upgrade`; no `.env` changes.
Eight tables, 16 triggers, four named composite relationships and zero overlong constraints verified.
Generated JUnit and server data remain ignored local evidence, not committed source/customer data.
The task-only server was shut down after verification; no persistent worker/service was installed.

MySQL commands used the explicit testing environment, loopback host/port, disposable schema,
root test account, empty test password and empty DB_URL, never `.env` changes:

```text
php -d memory_limit=512M vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald04 tests/Unit/Wald04 --compact --log-junit=storage/app/wald04-qa-20260908/mysql-final-junit.xml
php vendor/pestphp/pest/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Wald04/DedicatedQaTest.php tests/Feature/Wald04/GovernanceTest.php tests/Feature/Wald04/KnowledgeTest.php tests/Feature/Wald04/SemanticsAndIntegrityTest.php tests/Feature/Wald04/MysqlRetryQaTest.php tests/Unit/Wald04 --compact --log-junit=storage/app/wald04-qa-20260908/mysql-final-nonrace-junit.xml
php scripts/verify-wald04-qa-upgrade.php
php scripts/verify-wald04-mysql-schema.php
```

The final two scripts used `customerapp_wald04_qa_upgrade`. Both passed; the upgrade script
requires an empty, exactly named schema and refuses to reuse populated databases.

## SQLite / MySQL Coverage

SQLite supplies the ordinary lifecycle, security, semantic and regression gate. Database-specific
collation protection, real process races, server error retry behaviour and upgrade/rollback are
separately exercised on MySQL 8.4. No SQLite test is represented as lock-contention proof.

## Performance / Query Safety

PASS after W4Q-02. With one versus 12 active profiles, reuse previously grew from 23 to 67 SQL
queries and receipt checks from 18 to 59. Active versions and current provenance now use bounded
batch queries, with no per-candidate database loop; growth is at most two queries in regression.
Retention uses scoped EXISTS/MAX rather than hydrating unbounded profile histories. The existing
50-profile candidate budget refuses overflow rather than truncating competitors. It is conservative
and can require a narrower family as history grows; it is not a production load/SLA benchmark.

## Defects

| ID | Severity | Reproduction and correction |
| --- | --- | --- |
| W4Q-01 | P1 | MySQL collation-equivalent edits bypassed protected-field triggers. Nine attacks failed before the forward binary-comparison migration and pass after it. Historical migrations unchanged. |
| W4Q-02 | P2 | Reuse/receipt N+1 queries and unbounded retained-profile hydration. Measured 1→12 profile query growth; independent retention query-shape test failed before correction. Fixed by scoped batch provenance and EXISTS/MAX. |
| W4Q-03 | P3 | Extended MySQL races hit duplicate Faker account email `1062` and stopped one scenario. Test fixtures now use UUID addresses; race assertions and iteration counts are not weakened. |
| W4Q-04 | P3 | The enlarged full suite exceeded the local 128 MiB PHP process allowance (Wald correctly clamped its guard to 96 MiB). Both PHPUnit configurations now allocate 512 MiB to the test harness only. Production configuration, the frozen 384 MiB maximum, and explicit low-memory guard tests are unchanged. |

The first full MySQL attempt had 181 passes and one fixture error; it is not accepted race-gate
evidence. A QA upgrade-script comparison initially compared factory raw types with DB raw types;
it was corrected to compare DB snapshots, and the isolated synthetic upgrade schema was recreated.
An initial combined command referenced nonexistent `tests/Feature/Wald03`; no tests ran from it.
These failed attempts are recorded separately from final successful checks.
The first standard full-suite attempt at the inherited 128 MiB allowance had 1,007 passes,
26 skips, four failures and 65 resource-limit errors. The same 1,102-test suite then passed
with a 512 MiB test-process allowance: 1,076 passes / 26 skips / 5,392 assertions.
Six final SQL-tampering tests were subsequently added; final totals below supersede that run.

## Focused WALD04 Tests

`php artisan test tests/Feature/Wald04 tests/Unit/Wald04 --compact`:
198 tests, 187 passed, 11 MySQL-only skips, 406 assertions; exit 0 (32.145 seconds).

## Combined Wald Tests

`php artisan test tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04 --compact`:
876 tests / 865 passed / 11 MySQL skips / 4,218 assertions; exit 0 (50.325 seconds).

## Full CustomerApp Regression

`php artisan test --compact`: 1,110 tests / 1,084 passed / 26 environment skips /
5,406 assertions; exit 0 (69.446 seconds). The 26 skips are 15 inherited environment gates
plus 11 Wald MySQL-only tests, all 11 separately executed on disposable MySQL.

## Composer Audit

`composer audit`: exit 1, the same eight inherited advisories across Filament, CommonMark and
Livewire: five high, two medium, one low. No package/lockfile changes or security-branch merge.
The separate remediation commit `5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not imported.
This is not a clean security audit or production-release approval. `composer validate --strict`
passed; Composer reported its usual unwritable-cache warning and completed without that cache.

## Build / Pint

`npm run build`: PASS with Vite 8.1.4. Initial sandbox spawn EPERM was resolved by running the
same local build with child-process permission; no code/dependency workaround and no deployment.
`php vendor/bin/pint --test`: PASS. `git diff --check`: PASS.

## Production Impact

None. No push, merge, tag, Forge deployment, production query/migration, real account workflow,
email delivery, customer workbook access or SiteApp runtime access. `main` and `origin/main`
remain `0873bac79edf578e9f4a9417e3cafae34e8aa925`. Local disposable migrations only.
No browser/mobile/accessibility exercise is claimed: this slice adds no UI/routes/assets.
Future integration still needs its own safe-account browser and accessibility gate.

## Remaining Blockers

No genuine WALD04 blockers remain. G09 named-owner nomination is a future unattended-disposal blocker,
not a WALD04 mechanism blocker. Dependency advisories remain a separate security/release gate.

## Recommendation

Freeze the corrected documentation-inclusive QA output reported in the task handoff, not the
original `2c7d015` candidate. WALD05 remains unstarted and needs its own
approved work package/baseline; successful WALD04 QA does not authorise import integration or release.

## Files changed / handoff notes

Exact task files (18):

- `app/SourceImport/Knowledge/Actions/UseProfile.php`
- `app/SourceImport/Knowledge/KnowledgeQueries.php`
- `app/SourceImport/Knowledge/ProfileProvenance.php`
- `database/migrations/2026_09_08_000010_harden_wald_evidence_comparisons.php`
- `phpunit.xml`
- `phpunit.mysql.xml`
- `scripts/verify-wald04-qa-upgrade.php`
- `tests/Feature/Wald04/DedicatedQaTest.php`
- `tests/Feature/Wald04/MysqlRetryQaTest.php`
- `tests/Feature/Wald04/MysqlConcurrencyTest.php`
- `tests/Support/Wald04ConcurrencyWorker.php`
- `tests/Support/Wald04Fixtures.php`
- `documentation/wald/customer-wald04-qa-2026-09-08.md`
- `documentation/wald-divergence-register.md`
- `brief.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

Unrelated `documentation/sprint-3e-date-negotiation-report.md`, `Copy of siteapp1.xlsx` and
`output/` were preserved and excluded from commits.

CUSTOMER-WALD04 dedicated QA passed — freeze candidate and scope CUSTOMER-WALD05
