# CUSTOMER-WALD05 Final Governance Approval

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: **I01–I10 APPROVED. IMPLEMENTATION NOT AUTHORISED BY THIS DOCUMENTATION TASK.**

Authority: DEC-048 and the explicit management approval dated 8 September 2026. This report
supersedes only the unresolved/recommended approval state in
`customer-wald05-governance-resolution-2026-09-08.md`; that earlier analysis remains historical
evidence. The accepted WALD04 input remains
`0e83eb2896e7c5144bc38c1be9713f3d205d93b8`.

## Final I01–I10 decisions

| ID | Final status | Approved rule |
|---|---|---|
| I01 | APPROVED | Active, non-preview Fenster Office Staff alone may upload, interpret, answer one-time clarification, bind, review, commit, audit and retry. Each command has separate authorisation/audit; the same authorised actor may perform all. External Site Users have no import access. |
| I02 | APPROVED | Source-site bindings use `DRAFT → ACTIVE → SUPERSEDED` or `REVOKED`, with explicit Office activation, one active version per exact source-site identity, mandatory reason for replacement/revocation and immutable version/use history. Prefer permanent RedZebra/SiteApp Site ID; exact Site Name is transitional only; never fuzzy-match. |
| I03 | APPROVED TEMPORARY V1 | Manual Office-declared Export Date plus `MORNING`/`AFTERNOON` orders source observations. Authenticated uploader identity and the exact latest-export confirmation are audited. Slot uniqueness, replay, collision, stale and successor rules apply below. |
| I04 | APPROVED | One Call No. identifies one individual visit/call-off. Every revisit, including CM1 and CM2 visits, receives a new Call No. Workbook duplicates always block; later ordered observations retain the same visit identity. |
| I05 | APPROVED | Only `PARTIAL_FILTERED_EXPORT` can commit in V1. Absence has no effect. Site/global complete scopes are non-committable and cannot be inferred from workbook contents. |
| I06 | APPROVED | A run is review-ready only when every required site, plot, Call No. and service meaning is resolved and no Wald ambiguity, invalid mapping, duplicate, ordering conflict or stale dependency remains. Ignored fields may remain. No partial-row commit. |
| I07 | APPROVED | One reviewed bounded run is one atomic database transaction. Any failure rolls back the whole unit. If size requires smaller work, units are explicit before review; no hidden chunking/per-row application. |
| I08 | APPROVED | Minimal committed import audit metadata and provenance are retained for six years as a company V1 operational policy, not a legal claim. Uploaded workbooks keep separate retention. |
| I09 | APPROVED | Correctness uses durable queue-agnostic analysis/work state and a synchronous transactional commit. Workbooks use generated private, non-public CustomerApp storage keys/paths. Persistent workers remain pilot/release operations. |
| I10 | APPROVED | Reimplement the neutral staging and controlled commit boundary under the accepted semantics. Preserve safe relationships/history additively and reuse safe invariant/test intent only. Do not retain obsolete call mappings, source-wide absence, omitted-column zeroing or per-record commits. |

I11 remains `DEFER_TO_WALD06`: supervised pilot, cutover and any retirement decision stay
default-off and require separate approval.

## Manual source ordering and uploader identity

RedZebra supplies no immutable native export revision for this V1 path. The Office uploader
declares an Export Date and one exact slot: `MORNING` or `AFTERNOON`. A later date is newer; for
the same date, `AFTERNOON` is newer than `MORNING`. Upload, receipt and filesystem timestamps do
not establish freshness.

The system captures the authenticated uploader's account ID and name automatically; neither is
free text. The actor must confirm exactly:

> I confirm this is the latest RedZebra export available for this slot.

That evidence is a staff-declared provenance assertion, not a RedZebra-native revision.

Within a source namespace/workbook family, at most one normal import commits successfully for a
given Export Date/Export Slot. Same slot plus the same canonical workbook/content identity returns
the existing result. Same slot plus different content cannot create another normal import: it is
a conflict and requires an explicit audited correction/replacement successor that supersedes the
effective result while retaining the original receipt/history. An older declared slot cannot
overwrite a newer committed slot. If RedZebra later supplies a true revision/API, a new versioned
ordering mechanism may replace this temporary contract without rewriting history.

## Call No. and update behaviour

One Call No. is one visit/call-off. A revisit is a new visit with a new Call No.; CM1 and CM2
visits therefore have their own distinct Call Nos. Repetition of any Call No. inside a workbook
blocks the reviewed run, even when the duplicate rows are canonically identical. There is no
merge, first-row, last-row or generated subidentity behaviour.

The same Call No. in a later permitted export remains the same visit. Unchanged canonical content
causes no semantic change. Changed content is an update/correction only when I03 ordering permits
it. A site, plot or service identity conflict blocks instead of silently rebinding the visit.

## Product presence semantics

- absent product column: unrepresented; preserve existing data;
- present blank/null cell: preserve the raw blank and apply the supplied-record semantic contract;
- explicit zero: exact zero for that supplied record/column;
- valid positive value: exact approved fixed-point quantity;
- invalid or unknown value: block the reviewed unit, never coerce to zero.

Absence never becomes zero, deletion or completion authority.

## Completion and Portal protection

Source completion may apply only when Call No., site, plot and dictionary service are resolved,
`complete = Yes` is valid, and the whole preview/commit remains current and authorised. It never
invents a completion date. An unknown service cannot complete a Portal service.

The controlled source transition must preserve Requested Date, Date Agreed, alternative
proposals, negotiations, amendments, customer responses, actors and immutable history. Source
completion retains its already approved precedence, closes the applicable current process and
sends no completion notification. Operational dates never become Portal dates. Reversal records
the new source fact and does not automatically reopen an earlier process.

## Preview validity and recovery

A reviewed preview is valid for 24 hours and becomes stale when any of these change: canonical
workbook/content, Export Date/Slot, Wald component versions, dictionary/semantic executable,
profile or clarification receipt, binding/version, scope, projection/current-state revision or
review authority state. Commit fails stale and never regenerates silently.

A failed transaction leaves no partial projection change. A retry may use the same reviewed
preview only while it is valid and the prior result is known failed. An uncertain prior outcome
is resolved through the durable receipt. Replaying an exact successful request returns the
existing result. Corrections use attributed audited successors and ordering; routine manual SQL
repair is not part of the workflow.

## Retention and deferred gates

The six-year policy covers minimal committed audit/provenance only. Existing separate periods for
uploaded workbooks, bulky observations, previews and payloads remain; holds/dependencies extend
eligibility. G09 still lacks a named unattended-disposal owner, so automatic production deletion
remains blocked and no disposal scheduler is authorised.

Persistent production queue/worker supervision, operational rollout, WALD06 pilot/cutover,
retirement of old paths, combined Composer/security release reconciliation and production
deployment remain separate. Remediation commit
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` is not merged by this work.

## Implementation readiness

WALD05 governance is complete and may now receive a separate explicit implementation
instruction. This approval does not itself authorise runtime code, migrations, routes, UI,
queues, storage changes, a branch based on anything other than the accepted WALD04 snapshot,
SiteApp work, `main`, push, release or deployment.
