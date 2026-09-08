# CUSTOMER-WALD04 Knowledge Profiles Implementation Report

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **READY FOR DEDICATED QA — feature branch only.** Dedicated QA has not occurred.

## Authority and immutable inputs

Latest explicit management approval is recorded in DEC-046. It supersedes the earlier
scope-only approval gates, not the accepted Wald core or controlled business dictionary.
The branch is `feature/customer-wald04-knowledge-profiles`, created directly from accepted
WALD03 `a80ce7d14206cf3f3a9343448d406f01ae927b88`.

| Identity | Value |
|---|---|
| Documentation reset baseline | `e84999cf66fc90aac3007842a538672b018b3e03` |
| Corrected executable input | `f4fda0f069bd5106a125b42615ca212294a9dfad` |
| Accepted generic core | `4aa5ffb5a00527662ddfe66673edbfb18af9f0db` |
| Generic core tree | `30d1fc65e575242004eb335ad46a4d8ec920127a` |
| Reader / adapters | `wald-0.2.1` / XLSX 2, CSV 2 |
| Dictionary | `customerapp.source-dictionary.v1` |
| Dictionary fingerprint | `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` |
| Approval/scope commit | `9c894468ebd7352ef3f002c5aa9c1470511bb478` |
| Runtime/migration commit | `5ab218e0a6659cd067b4a9fbade5a406b0579be7` |
| Tests/schema verification commit | `f0f97212b8b1be763b953414cfe65c8e3e3352e7` |
| Unchanged main and origin/main | `0873bac79edf578e9f4a9417e3cafae34e8aa925` |

Approved scope documents were carried from `03098020afeace8616db60ea57753b1f42013eb8`;
that documentation branch was not merged and supplies no additional executable baseline.
The final documentation-only commit containing this report is identified in the task handoff.

## Governance implemented

| Decision | Implementation and boundary |
|---|---|
| G01 | Active, non-preview Office only for all knowledge mutations, evidence and audit. The three customer site roles are denied. Null-organisation Office is valid; every record still has explicit organisation/site scope. |
| G02 | Answer, save draft and activate are independent audited commands. No automatic learning. Same authorised actor may perform all three. |
| G03 | Required organisation, site, source namespace and workbook family. No global or organisation-wide reusable scope. |
| G04 | Metadata policy: workbook 30 days after terminal processing, observations 7 days, preview validity 24 hours, bulky preview payload 7 days. Processing/upload orchestration and cleanup remain WALD05. |
| G05 | Active profile reapproval due after 12 months; expiry makes it ineligible/STALE, not deleted. Retired/revoked history retained at least 24 months, extended by dependencies and holds. |
| G06 | Current authorised Office may revoke with a reason; epoch changes invalidate earlier receipts and future reuse stops. History is preserved. |
| G07 | Source-site binding ownership is approved; its separate lifecycle/runtime is deferred to WALD05. Knowledge scope is not a binding or a tenancy inference. |
| G08 | Reusable knowledge is structural only. CC! may receive an explicit occurrence-specific CC1 confirmation; ZZ9 remains unresolved and cannot define a service or dictionary meaning. |
| G09 | Audited holds, provenance and eligibility metadata exist. No deletion action, cleanup scheduler or unattended disposal. Named Fenster data-owner nomination gates future unattended production disposal, not WALD04 implementation. |

## Persistence and lifecycle

One new additive migration: `database/migrations/2026_09_08_000009_create_wald_knowledge.php`.
It adds eight tables, an ownership composite index on existing `sites`, and 16 immutability
triggers. No existing migration is edited. Populated rollback refuses before destructive DDL.

| Table | Responsibility |
|---|---|
| `wald_knowledge_contexts` | Server-registered scoped evidence generation, immutable hashes/pins, OPEN/CLOSED/SUPERSEDED coordination and expiry. Not an import run. |
| `wald_knowledge_evidence` | Bounded private, hash-pinned payload; hold and retention metadata. |
| `wald_clarifications` | Immutable question identity/evidence, mutable optimistic sequence/state only. |
| `wald_clarification_answers` | Immutable sequenced answer/correction, exact candidate, reason and predecessor. |
| `wald_profiles` | Scoped structural root, lifecycle, epoch, active-version pointer and review/retirement dates. |
| `wald_profile_versions` | Immutable reviewed selector/descriptor, canonical hash, originating answer and full compatibility pins. |
| `wald_knowledge_events` | Append-only actor, policy, command/idempotency identity, reason and before/after references. |
| `wald_profile_uses` | Immutable considered-use receipt with version/epoch, current evidence, result and reason. |

Database constraints enforce site/organisation ownership and profile/version relationships.
Database triggers reject edits/deletes of immutable records and protected parent evidence fields.
Coordination fields alone may change on contexts, questions, evidence and profile roots.
Canonical JSON preserves numeric types (including `1.0`) across SQLite/MySQL round trips.

An answer is occurrence-specific unless separately saved as a structural draft and explicitly
activated. Immutable successor versions preserve previous definitions. Activation requires the
expected epoch, exact definition hash, current pins and valid originating review provenance.
Revocation requires a reason; previously revoked versions cannot simply be reactivated.
Annual reapproval is an explicit audited activation, never implicit on reuse.

## Structure, semantics and fresh compatibility

`AnalysisSnapshot` is an internal trusted factory over a fresh Wald `WorkbookProfile`; it
recomputes generic reasoning and verifies the accepted identities. It is not a request-body
deserializer and there is no endpoint accepting caller-supplied analysis or scope grants.
Descriptors preserve table/header structure; physical coordinates remain current evidence,
not portable learned selectors. A profile reviews one structural role selector; independent
roles can have independent profiles. Approved dictionary field/product names and controlled
Plot synonyms are recognised. There is no arbitrary alias editor or unknown-code dictionary.

Compatibility results are EXACT_MATCH, COMPATIBLE_WITH_REVIEW, INCOMPATIBLE or STALE_VERSION.
Reordered headers, optional additions and recognised structural aliases require review.
Missing critical roles, changed quantity code, unknown renames and unsafe observations refuse
reuse. Multiple tables/candidates and competing exact profiles do not choose the first/latest
winner. Exact structural reuse cannot remove current semantic ambiguity or inflate confidence.
Formula/error/date/percent/empty/negative-quantity observations have fresh safety checks.

The full dictionary version/fingerprint, core/reader/adapters, structure/reasoning/rules/confidence,
schema/signature/matcher/selector/policy and corrected executable identities are pinned.
Same dictionary version with a different fingerprint is incompatible; changed tuples are stale.
Documentation-only commits do not falsely change executable identity.

CC! confirmation requires reviewed call-type structure and the exact safe current occurrence.
Original raw evidence and original blocked semantic result remain intact beside the explicit
human selection. Another CC! occurrence asks again. A semantic answer cannot become a reusable
profile. ZZ9 supports an unresolved answer record only, not a new canonical business meaning.
Reviewed interpretations always remain `ready_for_staging = false`: WALD04 does not implement
staging, Portal projection or controlled import commit.

## Authorisation, evidence and precedence

Current user/role/scope are re-read at each mutation. UUID knowledge and mass assignment do not
grant access. Office without an organisation must still select a valid organisation/site;
customer roles, inactive users and preview identities are refused. Queries scope parents before
children. Private evidence and paginated audit require the same Office/scope permission.

Evidence is bounded (256 KiB aggregate, 64 KiB question, 50 candidates) and uses structural
descriptors instead of retained row samples where possible. Required occurrence evidence is
private. No customer history, notification, source filename exposure, generic raw-error log,
external AI, spreadsheet write-back or SiteApp access was introduced.

Fresh explicit answers take precedence over profile reuse. Corrected originating answers,
superseded contexts, changed versions/epochs/pins, expiry and revocation invalidate reuse.
More than one eligible profile for the same role requires clarification. Candidate queries are
scope-first and bounded at 50; overflow refuses rather than silently dropping possible matches.
Receipt revalidation also notices a competing profile activated after the receipt was issued.
Receipts are advisory, never final-import permission; WALD05 must recheck under its own commit locks.

## Atomicity, retention and concurrency

Commands execute mutation, evidence and audit in one transaction, with three bounded transient
retries and actor/command idempotency. Repeated identical commands return the original result;
changed payloads or stale expected sequences/epochs conflict. A short organisation coordination
lock serializes absent-root creation and scoped writes; profile locks precede context locks.
No inference work is performed while holding these write locks.

Audit failure injection proves no partial answer succeeds. Immutable correction history and
profile history remain available. Closed-context evidence retains 24 months, extended by
profile retirement periods and conservative dependencies. Holds protect linked context
provenance. Expired active profiles stop reuse without deleting required evidence.
No raw-payload removal, tombstone erasure, cleanup race or restoration operation is implemented
or claimed; these belong to the separately authorised future disposal workflow.

Real MySQL workers use two Office users, independent processes/connections and a shared start
barrier. Six scenarios repeat ten times each: competing answers, same-version activation,
activation versus revocation, revocation versus reuse, successor draft versus reuse, and
competing-version activation. Outcomes check explicit conflicts, atomic pointers, historical
receipts and fresh receipt invalidation, not timing assumptions or SQLite lock emulation.

## Verification evidence

Environment: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.0.windows.3.
No package versions or lockfiles changed.

| Command/check | Result |
|---|---|
| `php artisan test --compact tests/Unit/Wald04 tests/Feature/Wald04` | PASS, exit 0: 102 total, 96 passed, 6 MySQL skips, 185 assertions. |
| `php artisan test --compact tests/Unit/Wald tests/Feature/Wald tests/Unit/Wald03 tests/Unit/Wald04 tests/Feature/Wald04` | PASS, exit 0: 780 total, 774 passed, 6 MySQL skips, 3,997 assertions. |
| `php artisan test --compact` | PASS, exit 0: 1,014 total, 993 passed, 21 environment skips, 5,185 assertions. Fifteen inherited skips plus six WALD04 MySQL tests. |
| `php -n scripts/verify-wald03-qa.php` | PASS: all nine precision/locale hashes identical, no PDO drivers, no Composer, no forbidden classes; dictionary fingerprint unchanged. |
| `php vendor/bin/pint --test` | PASS, exit 0. |
| `composer validate --strict` | PASS, exit 0. |
| `npm run build` | PASS, exit 0; local build only. |
| `composer audit --format=json` | Completed, exit 1 for eight inherited advisories: five high, two medium, one low; no remediation merged. |
| `git diff --check` | PASS; final staged documentation/path checks recorded at handoff. |

### Disposable MySQL 8.4 gate

Oracle-signed MySQL 8.4.11 ZIP was downloaded from the official CDN into ignored
`storage/app/wald04-mysql-20260908`. A fresh task-only data directory and hidden process listen
on `127.0.0.1:33484`; mysqlx is disabled. No old MySQL data directory, normal local database,
production credentials or production service was used. The insecure-initialized root account
belongs only to this isolated loopback test instance.

Explicit test environment: APP_ENV=testing, DB_CONNECTION=mysql, DB_HOST=127.0.0.1,
DB_PORT=33484, DB_DATABASE=customerapp_wald04_clean, DB_USERNAME=root, empty DB_PASSWORD/DB_URL.
No `.env` was edited. All commands are guarded against nonlocal or non-WALD04 databases.
After verification, this instance was stopped with its own `mysqladmin --no-defaults
--host=127.0.0.1 --port=33484 --user=root shutdown` (exit 0). Its ignored task-only binary,
data directory and test logs remain locally for inspection; no existing data was deleted.

- Clean `php artisan migrate --force`: all 12 migrations passed (11 accepted baseline plus one WALD04).
- Empty WALD04 `php artisan migrate:rollback --step=1 --force`, then migrate: both passed.
- Separate `customerapp_wald04_upgrade`: baseline migration paths selected from accepted Git
  snapshot, then synthetic organisation/site fixtures, then new migration. Both stages passed;
  existing fixture identity/name/site relationship survived. No application seeder was run.
- `php scripts/verify-wald04-mysql-schema.php`: PASS, MySQL 8.4.11, eight tables, 16 triggers,
  four composite relationships checked, zero overlong constraint names.
- `php vendor/bin/pest --configuration=phpunit.mysql.xml --display-warnings --log-junit=storage/logs/wald04-mysql-final.xml tests/Unit/Wald04 tests/Feature/Wald04`:
  PASS, exit 0: 102 passed, 355 assertions, zero skips/failures/errors; 221.388 seconds.
  Includes 60 repeated race groups / 120 independent worker processes.
- Populated rollback protection, immutable direct-SQL rejection, ownership/version constraints,
  JSON numeric/hash fidelity, audit rollback and all six repeated races are tested on MySQL.

An earlier Artisan MySQL invocation reported 101 passing tests but returned exit 1. It was not
accepted as a successful process gate. A direct Pest diagnostic passed 11 tests / 28 assertions,
exit 0. The final complete direct Pest rerun includes the subsequently added version race.
The final complete run returned exit 0 and its JUnit records zero errors/failures/skips;
the earlier wrapper exit anomaly was not reproduced and its cause is not established.
No test assertions were weakened to hide the discrepancy. Fixture-count assertions were scoped
to their owning tenant because committed independent-worker fixtures coexist in the disposable DB.

## Parallel import disposition

Reviewed the non-main chain ending `1e8c22bfd5021b82b9775892ebddb192cb173e16`, including
WorkbookInterpretationProfileService, WorkbookInterpretationProfile and its mixed migration.
The full REUSE / REIMPLEMENT / SUPERSEDED / REFERENCE_ONLY inventory remains in section 16 of
[the approved work package](../work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md).
Namespace-only matching, latest/first winner and automatic reusable save are superseded here.
Stale-preview/idempotency/synthetic-test intent was reused, not its runtime. Binding, preview,
commit and UI implementations remain WALD05 reference only. No merge, cherry-pick, old profile
data migration, SiteApp file copy or adoption of its architecture occurred.

## Operational boundary and next gates

Feature branch only: not on main, not QA-accepted, not deployed. No push, tag, Forge action,
production migration, workflow change, UI, import endpoint, SiteApp edit or dependency upgrade.
The separate security remediation at `5e7df0862648fd9c2ac964b31a13ad17df84fd12` remains unmerged.

Dedicated WALD04 QA must independently assess the immutable candidate, security/matching
boundaries, additive migration and MySQL races before management accepts an output SHA.
WALD05 additionally needs an approved bounded integration work package, source-site binding
lifecycle, upload/review/commit permissions, final-import audit/staging retention and atomic
receipt/version revalidation/recovery design. Named data-owner approval remains a gate for
future unattended production disposal. WALD04 does not authorise any of these implementations.

Unrelated pre-existing changes preserved: `documentation/sprint-3e-date-negotiation-report.md`
(trailing whitespace only), untracked `Copy of siteapp1.xlsx`, and untracked `output/`.
None was staged, read as source input, deleted or committed. Test-generated databases,
workbooks, build output and logs are ignored local artifacts, not repository deliverables.

## Exact task file inventory

The inventory below includes the approved scope-document carry-forward and implementation;
it excludes all unrelated user changes. Historical WALD03 implementation and QA reports were
not rewritten. Paths are relative to `C:/Users/JoshO/Documents/CustomerApp`.

- `DECISIONS.md`
- `HANDOVER.md`
- `ROADMAP.md`
- `app/SourceImport/Knowledge/Actions/ActivateProfile.php`
- `app/SourceImport/Knowledge/Actions/AnswerClarification.php`
- `app/SourceImport/Knowledge/Actions/CloseContext.php`
- `app/SourceImport/Knowledge/Actions/RegisterContext.php`
- `app/SourceImport/Knowledge/Actions/RevokeProfile.php`
- `app/SourceImport/Knowledge/Actions/SaveProfileDraft.php`
- `app/SourceImport/Knowledge/Actions/SetRetentionHold.php`
- `app/SourceImport/Knowledge/Actions/UseProfile.php`
- `app/SourceImport/Knowledge/AnalysisSnapshot.php`
- `app/SourceImport/Knowledge/Canonical.php`
- `app/SourceImport/Knowledge/Compatibility.php`
- `app/SourceImport/Knowledge/KnowledgeConflict.php`
- `app/SourceImport/Knowledge/KnowledgeIdentity.php`
- `app/SourceImport/Knowledge/KnowledgePolicy.php`
- `app/SourceImport/Knowledge/KnowledgeQueries.php`
- `app/SourceImport/Knowledge/KnowledgeScope.php`
- `app/SourceImport/Knowledge/KnowledgeStore.php`
- `app/SourceImport/Knowledge/Models/CanonicalJson.php`
- `app/SourceImport/Knowledge/Models/Clarification.php`
- `app/SourceImport/Knowledge/Models/ClarificationAnswer.php`
- `app/SourceImport/Knowledge/Models/KnowledgeContext.php`
- `app/SourceImport/Knowledge/Models/KnowledgeEvent.php`
- `app/SourceImport/Knowledge/Models/KnowledgeEvidence.php`
- `app/SourceImport/Knowledge/Models/KnowledgeProfile.php`
- `app/SourceImport/Knowledge/Models/KnowledgeRecord.php`
- `app/SourceImport/Knowledge/Models/ProfileUse.php`
- `app/SourceImport/Knowledge/Models/ProfileVersion.php`
- `app/SourceImport/Knowledge/ProfileMatcher.php`
- `app/SourceImport/Knowledge/ProfileProvenance.php`
- `app/SourceImport/Knowledge/ProfileState.php`
- `app/SourceImport/Knowledge/RetentionPolicy.php`
- `app/SourceImport/Knowledge/ReviewedInterpretation.php`
- `brief.md`
- `current_sprint.md`
- `database/migrations/2026_09_08_000009_create_wald_knowledge.php`
- `documentation/wald-divergence-register.md`
- `documentation/wald/customer-wald03-acceptance-wald04-scope-2026-09-08.md`
- `documentation/wald/customer-wald04-knowledge-profiles-2026-09-08.md`
- `documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md`
- `documentation/work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md`
- `scripts/verify-wald04-mysql-schema.php`
- `tests/Feature/Wald04/GovernanceTest.php`
- `tests/Feature/Wald04/KnowledgeTest.php`
- `tests/Feature/Wald04/MysqlConcurrencyTest.php`
- `tests/Feature/Wald04/SemanticsAndIntegrityTest.php`
- `tests/Support/Wald04ConcurrencyWorker.php`
- `tests/Support/Wald04Fixtures.php`
- `tests/Unit/Wald04/ContractsTest.php`
