# CustomerApp Documentation Contradiction Register — 4 September 2026

**Purpose:** Preserve historical evidence while preventing superseded statements from becoming
current implementation instructions.

## Authority Used for This Reset

1. New explicit user/management instruction.
2. Newer applicable numbered entries in `DECISIONS.md`.
3. The rebuilt `brief.md` current product contract.
4. Successfully QA/release-approved behaviour and verified deployment evidence.
5. Current repository, test and migration evidence.
6. Current sprint/work-package contracts within their stated scope.
7. Older brief sections, sprint reports and release records as historical evidence only.

## Critical

| ID | Conflict | Resolution / required action |
|---|---|---|
| CR-001 | The last explicit successful production record is Sprint 3E at `9111d76ff05d702d68afd884ee8e42bc8e50c8e3` / Forge deployment `76326195`, while `origin/main` is now `0873bac79edf578e9f4a9417e3cafae34e8aa925`. Repository documentation inspected during this reset does not prove that the later SHA deployed successfully. | Treat `9111d76` as the last evidenced deployment and `0873bac` as current `origin/main`. Verify Forge before any production claim or deployment decision. Do not infer deployment from a push or branch position. |

## Stale Documentation — Corrected by This Reset

| ID | Stale statement or pattern | Current resolution |
|---|---|---|
| SR-001 | The old `brief.md` accumulated later sections beneath earlier contradictory rules. | `brief.md` has been rebuilt as one current contract; history remains in the decision ledger and reports. |
| SR-002 | The old `AGENTS.md` said Office Staff were site-assigned and treated a null organisation ambiguously. | Valid active Office Staff are globally scoped and may have no customer organisation. Null organisation alone never grants access. |
| SR-003 | The old `AGENTS.md` and early brief listed only Cavity Closers, Windows and CML. | The current service set is Cavity Closers, Windows, Snagging and CML. No Snagging source code is invented. |
| SR-004 | Early workflow documentation used Submitted → Approved/Rejected as the target customer lifecycle. | The current target is requested-date negotiation ending in Date Agreed. Legacy records may remain for compatibility. Rejecting an alternative is not rejecting the call-off. |
| SR-005 | Early batch rules required one service and one date per batch. | One submission batch may contain multiple plot/service requests and dates; each request owns its individual values and history. |
| SR-006 | Earlier completion text depended on speculative job stages, completion dates and codes. | `complete = Yes` completes the represented source part; no date is invented. Older speculative fields/codes are non-authoritative. |
| SR-007 | Earlier product/lead-time text omitted Snagging and/or the exact BF effect. | Customer roll-ups and exact BF handling are defined in `documentation/siteapp-import-data-dictionary.md`; normal earliest request is four weeks, or five with positive BF. |
| SR-008 | Earlier source assumptions could treat workbook absence as deletion or complete site coverage. | Every workbook defaults to partial/filtered scope. Stronger snapshot authority must be explicit. |
| SR-009 | Older integration language assumed CustomerApp had to wait for a SiteApp API/export path. | Initial rollout uses CustomerApp's standalone private Wald path and must work without SiteApp runtime access. Future SiteApp integration remains separately controlled. |
| SR-010 | Source semantics were referenced from a feature branch document not present in the governing checkout. | A current authoritative dictionary now exists at `documentation/siteapp-import-data-dictionary.md`. |
| SR-011 | Some current-status sections described Sprint 3F amendment reasons and aggregate state as unresolved. | The feature branch confirms the seven reasons and `On Hold — Date Change Requested`, with existing overall `Call-Offs In Progress`; the feature remains non-main and non-deployed. |

## Historical Only — Retained, Not Current Instruction

| ID | Historical material | How to use it |
|---|---|---|
| HR-001 | Earlier numbered decisions before the date-negotiation and standalone-Wald decisions, including the direct Approved/Rejected lifecycle and assigned-site Office scope. | Retain for traceability. Apply later explicit decisions where contradictory. Do not delete or silently rewrite history. |
| HR-002 | Older Sprint 1–2 sections and version checklists in `ROADMAP.md`, `current_sprint.md` and `HANDOVER.md`. | Evidence of delivery sequence only. Read the current status block at the top first. |
| HR-003 | Pre-deployment Sprint 3E QA/release-candidate reports that correctly said “not deployed” at the time. | Time-bounded evidence. The later successful deployment record supersedes them for Sprint 3E production status. |
| HR-004 | `context-work-prompt.md` management-session assumptions about source codes, completion, products or amendments. | Dated discovery input. Use the current brief, decisions and data dictionary for confirmed truth. |
| HR-005 | The manual source-import branch and its reports. | Non-main technical evidence for Wald comparison. It is not deployed, the final architecture or authority for product semantics. |
| HR-006 | SiteApp WALD01–07 code and reports. | Candidate source for a controlled generic-engine fork only after an immutable baseline manifest is approved. Never treat it as CustomerApp production state. |

## Needs Decision

| ID | Open decision | Why it blocks or constrains work |
|---|---|---|
| ND-001 | Exact customer-facing expansion of CML and any customer certificate/document access. | Wording and future content cannot be invented. |
| ND-002 | UK bank-holiday provider/dataset, update owner and failure behaviour. | The target excludes bank holidays; the last evidenced release is weekday-only. |
| ND-003 | Source owner/operator, immutable revision identity, stale ordering and duplicate/multi-row `Call No.` semantics. | Required for safe repeat imports and conflict handling. |
| ND-004 | Which Portal roles may upload, analyse, answer clarifications, approve dictionary knowledge, review and commit imports. | Global Office review scope is not automatically an import-administration grant. |
| ND-005 | Retention for raw workbooks, neutral staging, clarification answers, learned profiles and audit evidence. | Required for privacy, storage and audit design. |
| ND-006 | Import commit atomicity, recovery/rollback and partial-failure presentation. | Required before a controlled production commit path. |
| ND-007 | Customer-safe projection of any source fields beyond the current service, status and product allowlist. | Prevents accidental disclosure or operational-domain leakage. |
| ND-008 | Amendment failure/cancellation and explicit reinstatement of a previous agreed date. | Sprint 3F must not auto-restore or invent a terminal outcome. |
| ND-009 | Scheduled source-sync mechanism, credentials, cadence and alert ownership. | Manual import should be proven before operational automation. |
| ND-010 | Email provider/event mapping, reminders and persistent queue-worker rollout. | Email and reliable async delivery cannot be claimed or released without this operating contract. |

## Verification Gaps (Not Business Decisions)

| ID | Gap | Required verification |
|---|---|---|
| VG-001 | Whether `0873bac` is the active production SHA. | Inspect the current Forge deployment/release record before deployment-related work. |
| VG-002 | Authenticated Sprint 3E production behaviour. | Run an approved, read-only/non-destructive smoke test with a designated safe account/session. |
| VG-003 | Current production queue and dependency-advisory state after the last evidenced release. | Recheck production configuration and current lockfile audits in a separately approved operational task. |
| VG-004 | Sprint 3F release readiness. | Dedicated QA, security audit reconciliation and explicit release approval are still required. |

## Closed Semantic Questions

The 4 September source reconciliation closed the business meanings for PC1, CC1, CM1, CM2,
CML, literal `CC!`, `complete`, Windows/Doors roll-ups, exact BF, excluded products, ignored
fields, PC1 operational date, transitional Site Name and filtered-export scope. Do not reopen
these by inference; change them only through an explicit new decision.
