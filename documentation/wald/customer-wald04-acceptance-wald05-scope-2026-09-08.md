# CUSTOMER-WALD04 Acceptance + WALD05 Scope Report

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Outcome: **WALD04 corrected output accepted; WALD05 scoped only — decisions and implementation approval required.**

## Completed

Recorded management acceptance of the corrected WALD04 dedicated-QA output, preserved all QA
corrections, inspected current and historical import boundaries, and created the proposed WALD05
integration work package. No WALD05 runtime implementation was performed.

Documentation branch: `codex/docs-customer-wald05-scope-2026-09-08`, created directly from the
accepted QA output. Initial checkout was the QA branch at that exact SHA. The only pre-existing
workspace changes were the known unrelated Sprint 3E whitespace edit, unopened workbook and
`output/` directory; all were preserved and excluded.

## Frozen WALD04 baseline

| Item | Accepted value |
|---|---|
| WALD04 output / immutable WALD05 input | `0e83eb2896e7c5144bc38c1be9713f3d205d93b8` |
| QA branch | `qa/customer-wald04-2026-09-08` |
| Corrected executable/test revision | `9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5` |
| Original implementation candidate — not accepted output | `2c7d0154e51a35b165c7e93f7dd256e2cf0f030f` |
| Accepted WALD03 input | `a80ce7d14206cf3f3a9343448d406f01ae927b88` |
| Generic Wald tree | `30d1fc65e575242004eb335ad46a4d8ec920127a` |
| CustomerApp semantic tree | `9cc8df4bc50a888ec49e6a93dddaba180a2d0d01` |
| WALD04 knowledge tree | `43135838725e11f6af1b614115f642fb800a2953` |
| Dictionary | `customerapp.source-dictionary.v1` |
| Dictionary fingerprint | `18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357` |
| Reader / adapters | `wald-0.2.1` / XLSX 2, CSV 2 |

The executable and documentation-inclusive snapshots have identical application, migration,
test, test-configuration and verification-script contents. The final snapshot adds the QA report
and current status documentation. DEC-047 records acceptance and scope-only authority.

## Accepted corrections and QA evidence

[Dedicated QA report](customer-wald04-qa-2026-09-08.md) remains the detailed evidence.

- W4Q-01 P1: a forward migration replaces collation-sensitive MySQL protected-field trigger
  comparisons with null-safe binary comparisons. Historical migrations remain unchanged.
- W4Q-02 P2: profile/provenance matching and retention use bounded batch/EXISTS/MAX queries,
  with no per-candidate database loop.
- W4Q-03 P3: UUID fixture emails prevent duplicate Faker collisions in extended races.
- W4Q-04 P3: PHPUnit receives a test-only 512 MiB allowance; production/Wald limits remain fixed.

Accepted results:

| Gate | QA result |
|---|---|
| Focused WALD04 SQLite | 187 passed; 11 MySQL-only skips; 406 assertions |
| Combined Wald | 865 passed; 11 MySQL-only skips; 4,218 assertions |
| Full CustomerApp | 1,084 passed; 26 environment skips; 5,406 assertions |
| Disposable MySQL 8.4.11 | 198 tests / 862 assertions covered, no accepted-gate skip |
| Real concurrency | Seven scenarios × 20 iterations = 140 groups / 280 workers |

The MySQL gate included additive upgrade, empty rollback/reapply, populated rollback refusal,
binary trigger attacks, retries and lock races. The task-only server was stopped. These are
accepted attributed QA results, not rerun by this documentation task.

## WALD05 scope

[Work package](../work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md) proposes:

- CustomerApp-owned private XLSX/CSV artifacts, operation IDs, durable queue leases, fencing,
  resumption and recovery independent of SiteApp;
- composition of frozen Wald, controlled semantics and WALD04 one-time/profile knowledge;
- explicit namespace/family, immutable revision, source-site binding and export coverage;
- immutable typed neutral staging with raw/canonical evidence, physical lineage and exact
  fixed-point quantities;
- Office-only clarification, scope/revision confirmation, current-state diff and review;
- a separately authorised, idempotent, current-state-revalidated Portal commit with immutable
  receipts, issues and before/after history;
- strict partial-export preservation, source-completion precedence and Portal date protection;
- queued failure/recovery, security, parity, accessibility, scale and real MySQL evidence;
- a default-off boundary. WALD06 owns supervised pilot/hardening and production readiness.

Wald still infers only structure. Site/customer selection, revision order, completeness, domain
meaning and commit permission remain CustomerApp controls. A profile receipt is never a final
commit grant.

## Current importer reconciliation

Read-only inspection confirmed that the accepted checkout's current projection importer cannot
serve directly as the new Wald commit path:

- `SourceCallTypeMapper` still maps literal CC! and speculative legacy codes/stages contrary to
  the controlled dictionary;
- `SourceRecord` models legacy job-stage/completed-date inputs and float-like quantities rather
  than the frozen raw/canonical semantic result;
- `SourceProjectionImportService` commits per record, then runs separate product and missing
  passes; that is not whole-reviewed-set atomicity;
- omitted products become zero and absence is compared source-wide, which is unsafe for the
  mandatory `PARTIAL_FILTERED_EXPORT` default;
- source/site/revision identity is insufficient for durable binding and ordering authority.

These files are preserved as historical/domain evidence. WALD05 must define and approve a
neutral/commit adapter before invoking any corrected Portal transition. No current migration or
history is rewritten by this scope.

## Parallel non-main reconciliation

Actual Git evidence from the non-main chain ending `1e8c22b` was retained as follows:

- **REUSE:** validation/content-hash/stale-preview, exact-binding and partial-scope test intent;
- **REIMPLEMENT:** Office/scoped operations, immutable source-site binding lifecycle, private
  upload/status/review under current accepted architecture;
- **SUPERSEDED:** automatic profile save, namespace-only score/first matching, coupled synchronous
  interpretation/commit and numeric legacy semantic version;
- **REFERENCE_ONLY:** manual import UI/result presentation and mixed preview/projection migrations.

No merge, cherry-pick, file copy, legacy profile/binding migration or retirement occurred.

## Decisions required before WALD05 implementation

| ID | Required decision | Recommended V1 |
|---|---|---|
| I01 | Import abilities and actor separation | Active non-preview Office only, distinct capabilities; same actor allowed for supervised V1 review/commit. |
| I02 | Source-site binding | Immutable activate/revoke versions, exact source key to an existing organisation/site; never move a used binding. |
| I03 | Source owner/revision/order | Named owner and issuer-defined stable revision; hash alone is insufficient; stale/unknown order blocks. |
| I04 | Duplicate/multi-row Call No. | Unique Call No.; all duplicates block until a source-grain contract exists. |
| I05 | Complete-snapshot absence | Partial default preserves all omissions; explicit complete scope may mark reviewed missing facts, never silently delete. |
| I06 | Neutral staging/review | Immutable staging/diff generation; all blockers resolved; separate explicit review. |
| I07 | Commit/recovery | Whole reviewed bounded set atomic; exact idempotency/current-state recheck; corrections are successors. |
| I08 | Final audit retention | Six years proposed as operational policy, not legal fact; named data owner/holds/backup expiry required. |
| I09 | Queue/storage owner and limits | CustomerApp-owned private storage/queue, named operator, measured leases/retries/workers/alerts. |
| I10 | Importer disposition | Reimplement bounded neutral/commit integration; reuse tests/invariants; no wholesale merge or direct legacy call. |
| I11 | Pilot/cutover | Default off; WALD06 supervised pilot and separate retirement decision. |

I01–I10 and a separate explicit implementation instruction are entry gates. I11 is a later
pilot decision, though WALD05 must implement a default-off boundary. Recommendations do not
grant permissions, choose a legal retention period or authorise a write path.

## WALD05 safe state

Until those decisions are approved:

- no upload, queue, binding, staging, review or commit runtime;
- no direct call to the current projection importer from Wald/browser input;
- no source absence or missing product changes;
- no production disposal, source scheduling or pilot;
- no adoption of non-main profiles, bindings, migrations or UI;
- no release, main mutation, push, SiteApp action or deployment.

The existing Portal remains functional without a workbook importer. Pausing imports is safer
than bypassing review through console writes or an unapproved feature branch.

## Files changed

Ten exact documentation files:

- `DECISIONS.md` — appended DEC-047 only.
- `brief.md` — accepted baseline and scope-only status.
- `current_sprint.md` — current phase handoff at top.
- `ROADMAP.md` — accepted WALD04 and WALD05 planning gate.
- `HANDOVER.md` — exact baseline, blockers and next action.
- `documentation/wald-divergence-register.md` — WD-42 scoped integration difference.
- `documentation/work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md` — phase status only.
- `documentation/work-packages/WP-CUSTOMER-WALD04-KNOWLEDGE-PROFILES.md` — accepted output status.
- `documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md` — new proposed package.
- `documentation/wald/customer-wald04-acceptance-wald05-scope-2026-09-08.md` — this report.

Historical WALD04 implementation and QA reports are unchanged.

## Checks and production impact

The final task handoff records exact branch/ancestry, runtime diff, path/link, contradiction and
whitespace checks. No application suite, MySQL, build or Composer audit is required for this
documentation-only change; prior QA totals remain attributed rather than presented as fresh runs.

Eight inherited Composer advisories remain a separate security/release gate. Remediation commit
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. No runtime code, migration, route,
view, queue, storage, configuration, dependency, source workbook, customer data, main, push,
production, SiteApp or deployment change occurred.

Recommendation: management should approve or amend I01–I10 and name the applicable owners,
then issue a separate explicit WALD05 implementation instruction. Do not implement now.
