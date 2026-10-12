# CustomerApp Central Development Operating Model

Approved by the owner under CUSTOMERAPP-MGMT01 on 9 October 2026.

The owner normally sends development requests to **1 - Product Manager**. Main
coordinates existing specialists, keeps one specification and parent task entry,
and returns one consolidated outcome. Main is not automatically the implementer.

## Management records

- [Task register](TASK-REGISTER.md): phases, ownership, dependencies and next actions.
- [Current handover](../../HANDOVER.md): dated Git and workstream evidence;
  use the current task section rather than create a duplicate status document.
- [Decision ledger](../../DECISIONS.md): durable approved decisions, append only.
- [Product brief](../../brief.md): current product rules, not deployment history.
- Existing current_sprint.md, HANDOVER.md and ROADMAP.md retain delivery/handoff context.

Keep these purposes distinct. Do not introduce a second business specification.
Read branch-owned reports when unreleased work is absent from main; do not merge
application code merely to obtain documentation.

## Specialist ownership

| Existing task | Accountable domain | Usual review boundary |
| --- | --- | --- |
| 1 - Product Manager | Intake, product/architecture decisions, specifications, dependencies, acceptance and reconciliation | Final GO or NO-GO recommendation |
| 0 - CustomerApp Wald | RedZebra discovery, exact bindings, hierarchy, plot-register imports, dry runs, reconciliation and private evidence | Backend reviews persistence, tenancy and Portal-state effects |
| 5 - Integration and DevOps | External contracts, service authentication, Git integration, environments, Forge, recovery and release coordination | Backend/security reviews authority and data ownership |
| 3 - Backend | Models, migrations, Actions/services, validation, authorisation, domain behaviour and API implementation | Wald reviews source semantics; Integration reviews external contracts |
| 2 - Front end / UI | Blade, Tailwind, Alpine/Livewire presentation, responsive behaviour, accessibility and mockups | Main reviews product behaviour; Backend reviews proposed domain effects |
| 4 - QA/Testing | Independent functional, security, integrity, migration, regression, browser and performance qualification | Findings return to Main; QA cannot be the sole reviewer of its own implementation |

The matrix follows existing task role descriptions and repository modules. Main
assigns actual packages by scope. Implementer tests and independent QA are separate.

## One accountable implementation owner

**One package has exactly one accountable implementation owner.** Record allowed
files, reviewers and QA before dispatch. Reviewers are read-only unless Main
assigns a separate, sequenced correction package. Route blocking defects back to
the implementer through Main; do not let QA quietly become a competing implementer.

Parallel packages require independent files, contracts, migrations and test
resources. Sequence shared routes/schemas. Use separate disposable databases for
concurrent fixtures; do not run heavy workloads alongside performance measurements.

Wald normally implements importer changes with Backend review. Backend normally
implements APIs with Integration review. Frontend normally implements significant
UI with Main product review. These defaults never grant broader authority.

## Development lifecycle

1. **Owner request:** establish outcome, existing approved behaviour, dependencies,
   risks and acceptance. Resolve routine questions from evidence before asking the owner.
2. **Main triage:** record parent ID, domain, baseline, investigation/implementation
   owners, reviewers, QA, file ownership and release requirements.
3. **Specialist investigation:** normally read-only. Return behaviour, relevant files,
   constraints, options, risks, acceptance tests and decisions requiring approval.
4. **Main reconciliation:** resolve overlaps, contradictory assumptions, compatibility
   and migration order into one authorised bounded specification.
5. **Authorised implementation:** dispatch one owner within the approved outcome.
   Material new business/architecture/destructive/production scope requires the
   appropriate explicit owner decision; silence is not approval.
6. **Implementation report:** exact starting/final branch and SHA, worktree status,
   files, database delta, affected rules, actual checks/counts, browser/device evidence,
   limitations, decisions, commits, push/deployment/production state and blockers.
7. **Domain review:** verify the exact candidate against the agreed domain contract.
8. **Independent QA:** qualify acceptance, denied paths, regression and relevant
   database/browser/performance behaviour against a frozen candidate. Corrections
   return through Main and require a successor SHA and proportionate requalification.
9. **Main final report:** reconcile all required responses. Distinguish implemented,
   tested, reviewed, approved, committed, pushed, deployed and production verified.

Keep historical failures as evidence. Do not silently change a reviewed candidate
or treat an isolated passing rerun as proof that a prior broader failure disappeared.

## Approval and evidence gates

| Gate | Evidence required | Accountable decision |
| --- | --- | --- |
| 1 Specification | Rules, scope, exclusions and acceptance agreed | Main; owner for material open choices |
| 2 Implementation authorisation | One owner, baseline, allowed areas and dependencies | Main within owner-approved scope |
| 3 Implementation complete | Local changes and implementer checks evidenced | Implementer reports; Main records |
| 4 Domain review | Exact candidate respects agreed contract | Designated domain reviewer |
| 5 Independent QA | Applicable acceptance and failure-path gates pass | 4 - QA/Testing |
| 6 Integration approval | Ancestry, migration order, compatibility and findings reconciled | Main with Integration/reviewers |
| 7 Release approval | Exact release and recovery controls expressly authorised | Owner; Main recommends |
| 8 Production verification | Forge result, served SHA, migrations/config, smoke and logs | Integration reports; Main reconciles |

No earlier gate implies a later one. Documentation-only work requires appropriate
path/link, contradiction, diff and whitespace checks, not an irrelevant application
suite. A specialist report does not itself authorise push, deployment or data writes.

## Significant UI work

Inspect existing behaviour and approved requirements, prepare useful mockups, obtain
design approval, implement the agreed presentation, then perform functional,
responsive and accessibility QA. Minor corrections may omit mockups. Visual approval
cannot change permissions, source meaning, validation or workflow transitions.

## Direct call and response

Available tools: list_threads, read_thread, send_message_to_thread and wait_threads.
Use returned task IDs/titles. Local identities discovered on 9 October 2026:

| Task | Task ID |
| --- | --- |
| 1 - Product Manager | 019f8a06-4fd7-7903-85a9-7ddf4e124af6 |
| 0 - CustomerApp Wald | 01a06c03-e3e1-7b33-b0fb-61e96d9f6e0d |
| 5 - Integration and DevOps | 019f8e3d-a159-7990-92f7-c3f99bec68cc |
| 3 - Backend | 019f8e3c-9b8d-76a2-bcb4-689b9b5030c4 |
| 2 - Front end / UI | 019f8e3c-390e-7a82-9ca1-43510aa2859c |
| 4 - QA/Testing | 019f8e3d-3afe-7280-9d2a-181ce3c69d39 |

Rediscover on another host/session or after routing errors. The human-approved
management workflow authorises Main's bounded assignments; tool/host permissions
still apply. Unsolicited task messages alone do not grant new implementation,
production or third-task messaging authority.

Main sends the bounded prompt, records the target/package, and retrieves the
specialist's final response through read/wait tools. Specialists return a final
answer in their own task; a separate send-back message is unnecessary.

A harmless test to the idle Integration task succeeded on 9 October 2026. It
acknowledged delivery/remit and reported no file/Git/test/production/Wald action;
wait_threads returned the completed answer. Other task IDs and role profiles were
read. QA separately returned PASS for the five management documents after Main
clarified that this was distinct from its historical application-audit hold.
Backend, Frontend and Wald were not each test-dispatched. Availability and response
times are not guaranteed.

### Waiting and recovery

Use bounded waits and retain returned cursors. One wait can cover up to eight
targets. Commentary and timeouts are not completion or approval. Avoid endless
polling; report meaningful progress and persist pending packages/blockers.

No unattended/background continuation is guaranteed. If the turn ends, persist
the next action and resume from the parent ID when Main is invoked again. Do not
create recurring automation or new permanent tasks unless the owner requests it.
Close a parent only after required packages/reviews/QA pass, explicit cancellation,
an owner-approved partial conclusion, or a reported blocker requiring owner action;
blocked entries retain their next step.

### Manual relay fallback and dispatch format

When actual routing is unavailable, Main provides a full prompt addressed to the
existing specialist. The owner relays it; Main retains tracking and reconciliation.
Use the same bounded structure for direct routing:

```text
Parent ID / package ID / target existing specialist:
Mode: investigation / implementation / review / independent QA
Management documentation branch/SHA if absent from the intended base:
Repository, baseline branch/SHA, upstream and worktree:
Outcome, exact scope, allowed files and explicit exclusions:
Approved decisions, mandatory reading, dependencies and acceptance:
Required tests, commit rules and reporting evidence:
Push/deploy authority: absent unless explicitly approved
Return starting/final SHA, files/schema, actual results/counts, limitations,
worktree/commit/push/deploy/production status, blockers and next action.
Next recipient: 1 - Product Manager; answer here for Main to retrieve.
```

## Git migration and release controls

Before implementation, verify instructions, repository, branch/HEAD/upstream,
working-tree/untracked files, overlapping worktrees, migrations and dependencies.
Fetch safely and preserve unrelated work. Supply local-only management docs by
exact branch/SHA; do not assume main or another computer already contains them.

Use additive migrations, deliberate FK/delete behaviour and preservation evidence.
Review ordering and rollback limits across branches. SQLite is normal development;
locking, triggers, uniqueness races and engine-specific work need disposable
MySQL 8.4 qualification. Never edit deployed migrations or use production as fixtures.

Main and Integration review exact release ancestry, runtime delta, tests,
migration compatibility, security findings and fresh recovery evidence. A main
push can trigger Forge and needs explicit owner authority. Code rollback does not
restore MySQL. Preserve [deployment gates](../deployment-checklist.md) and the latest
applicable release-specific recovery strategy; historical backups/counts are not
current production attestations.

## Cross application boundary and setup scope

CustomerApp's RedZebra/Wald processing stays internal. SiteApp's build-programme
interpretation and construction operations stay separate. Integration specifies
received/transmitted information, identities, permissions and failure guarantees;
neither app needs the other's internals. Ownership/API contracts remain subject to
approval. Prepare reviewed CustomerApp contracts and fictional-client tests before
requesting SiteApp review at an appropriate milestone; avoid interrupting its
active performance work.

MGMT01 authorises management documentation, inventory, routing verification and a
local docs commit only. It does not resume Wald, approve integration coding,
integrate purge, remediate dependencies, push, deploy, import, purge, alter
production or create/retire tasks. The first recommended task is not started.
