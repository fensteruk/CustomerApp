# CUSTOMER-WALD05 Source Binding + Import Commit Implementation Report

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **BLOCKED at projection-contract review; runtime implementation has not started.**

## Authority and verified inputs

The latest management instruction explicitly authorises WALD05 implementation and the branch
`feature/customer-wald05-import-review-integration`. It supersedes the previous requirement for
a separate implementation instruction. I01–I10 remain approved; none is reopened by this report.

The branch was created directly from governance commit
`877bd3ff666873a0703c3b7671015ec4cfd2ee52`. Accepted WALD04
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8` is its ancestor. The intervening changes comprise
14 Markdown files only, with no application, migration, test, configuration, dependency or
workflow changes. Corrected WALD04 executable remains
`9284bf55ccd93827a5a2c1fda87e9c3e8dad17c5`.

Verified trees at the implementation starting point:

| Component | Git tree |
|---|---|
| Generic Wald | `30d1fc65e575242004eb335ad46a4d8ec920127a` |
| CustomerApp semantics | `9cc8df4bc50a888ec49e6a93dddaba180a2d0d01` |
| WALD04 knowledge | `43135838725e11f6af1b614115f642fb800a2953` |

Dictionary remains `customerapp.source-dictionary.v1`, fingerprint
`18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357`.

## Newly exposed projection-contract gap

I04 correctly establishes one permanent Call No. per individual visit. Both CM1 and CM2 map to
CML and may identify separate visits for the same plot. That source grain is settled. The
integration still needs the rule converting several distinct visits into one Portal service
state and one plot/product quantity.

The existing schema has one `projected_plot_services` row per `(projected_plot_id,
service_identifier)` and one `source_call_number` on that row. The same migration makes
`(projected_plot_id, product_code)` unique in `projected_plot_products`. See
`database/migrations/2026_08_20_000005_add_target_call_off_domain_foundation.php`, lines 32–61.
These existing constraints are evidence of the old projection shape, not permission to reject
legitimate distinct visits or choose one as business truth.

The approved dictionary's `CustomerAppDictionary::rollup()` explicitly processes one supplied
record. It defines which product codes contribute to Windows/Doors but supplies no cross-visit
aggregation rule. Export Date/Slot orders observations of a visit; two distinct visits in one
export have the same declared slot, so that ordering cannot choose between them.

| Required clarification | Concrete synthetic case | Why an implementation choice changes business meaning |
|---|---|---|
| W5-P01 — service completion and request association | One plot has Call A / CM1 / complete=Yes and Call B / CM2 / complete=No in the same export. | Both visits resolve to CML. The contract does not say whether the Portal's CML is complete, outstanding, or tied to a designated visit; nor which visit may close the active Portal request. This affects eligibility, completion/reversal and negotiation precedence. |
| W5-P02 — product quantity across distinct visits | One plot has two distinct Call Nos. with VS=5 and VS=2, or BF=1 and BF=0, in the same export. | The contract does not say whether quantities are per-visit contributions, repeated plot totals or values owned by a designated visit/call type. Sum, maximum, latest visit or first/last row give different customer totals and potentially different BF lead time. |

The required answer must also cover later partial exports: the approved absence rule preserves
unrepresented visits, so omitting a previously known visit cannot implicitly remove its influence
on an aggregate. No ordering may be inferred from Call No. numeric value, row order, upload time
or CM1/CM2 labels unless that rule is explicitly approved.

This corrects the earlier readiness report's implication that the entire projection contract
was complete. It does not change I03 ordering, I04 visit identity, the dictionary mappings or any
other approved I01–I10 decision. AGENTS.md requires an unresolved business rule to be recorded
rather than invented; the implementation instruction expressly permits escalation of a genuine
integration-contract contradiction. The affected schema/commit implementation is paused here.

## Existing and parallel importer disposition

Read-only inspection confirms the existing importer and the non-main line at `1e8c22b` both
reject a second Call No. targeting an already occupied plot/service. Their product pass merges
maps by plot, allowing later rows to replace earlier quantities. Those behaviours cannot resolve
W5-P01/P02 because they are specifically superseded by the approved adoption boundary.

| Material | Disposition |
|---|---|
| Existing Portal relationships, dates and immutable history | KEEP_AS_IS; evolve additively after the projection rule is confirmed. |
| Source run/issue/event concepts and completion/reversal transition intent | ADAPT behind the new authorised atomic boundary; legacy mappings are not authority. |
| SourceRecord, binding, staging, preview, ordering and commit adapter | REPLACE/REIMPLEMENT under approved contracts and the clarified projection rule. |
| Old importer execution | RETIRE_AFTER_WALD05 only through separately approved WALD06 parity/cutover; retained now. |
| Non-main upload/hash/stale/idempotency tests | REUSE invariant/test intent only. |
| Non-main profile learning, score/first selection, source-wide absence, omitted-product zeroing and per-record commit | SUPERSEDED; no code or data imported. |
| Non-main Office UI | REFERENCE_ONLY. |

## Implementation state

No binding, upload, run, observation, staging, review, receipt or audit table has been added.
No migration has run. No runtime, endpoint, UI, storage configuration, queue, dictionary or
projection code has changed. Accordingly, no I01–I10 feature is claimed implemented by this task.
No atomicity, retry, concurrency, idempotency, performance or MySQL result is claimed.

The explicit implementation approval remains valid once W5-P01/P02 are answered. Work should
resume on this branch from the verified governance baseline plus this entry record; it does not
require another blanket implementation approval.

## Checks and environment

| Check | Result |
|---|---|
| `git merge-base --is-ancestor 0e83eb2896e7c5144bc38c1be9713f3d205d93b8 877bd3ff666873a0703c3b7671015ec4cfd2ee52` | Exit 0; accepted baseline is an ancestor. |
| `git diff --stat 0e83eb2896e7c5144bc38c1be9713f3d205d93b8 877bd3ff666873a0703c3b7671015ec4cfd2ee52` | Fourteen Markdown files only. |
| Path-restricted diff of app/database/tests/config/routes/dependencies/.github over the same range | Empty. |
| `git rev-parse HEAD:app/Wald HEAD:app/SourceImport/Semantics HEAD:app/SourceImport/Knowledge` at branch creation | Matches all three accepted tree identities above. |
| `php -v`; `composer --version`; `node -v`; `npm -v`; `git --version` | Available: PHP 8.4.23, Composer 2.10.1, Node 26.5.0, npm 11.17.0, Git 2.55.0.windows.3. |
| Current model/migration/importer/dictionary and non-main Git-object inspection | Confirms the projection-grain gap and superseded legacy handling described above. |

Application suites, disposable MySQL, Pint, build and Composer audit are not rerun for this
documentation-only entry review. Eight Composer advisories remain inherited reported evidence;
remediation `5e7df0862648fd9c2ac964b31a13ad17df84fd12` was not merged. No fresh clean-security
claim is made. Documentation path/diff/whitespace checks are recorded in the task handoff.

## Files changed and preserved work

Task files: this report; `DECISIONS.md`; `brief.md`; `ROADMAP.md`; `current_sprint.md`; `HANDOVER.md`;
`documentation/wald-divergence-register.md`; and
`documentation/work-packages/WP-CUSTOMER-WALD05-IMPORT-REVIEW-INTEGRATION.md`.

The unrelated two-space change in `documentation/sprint-3e-date-negotiation-report.md`, untracked
`Copy of siteapp1.xlsx` and untracked `output/` remain preserved and excluded. Historical reports
are unchanged. No customer workbook, SiteApp filesystem or production system was accessed.

## Recommendation

Do not start dedicated WALD05 QA yet. Confirm W5-P01 service completion/request association and
W5-P02 product quantity aggregation across distinct visits, then continue the already authorised
implementation. G09 unattended disposal, WALD06 pilot/cutover, persistent production worker
operations, security reconciliation and deployment remain separate later gates.

CUSTOMER-WALD05 blocked — integration contract correction required
