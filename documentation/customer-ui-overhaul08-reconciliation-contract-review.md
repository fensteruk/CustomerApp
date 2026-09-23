# CustomerApp Master Import Reconciliation Report

Date: 23 September 2026

## Overall Result

**BLOCKED — source date contract requires confirmation.**

The approved brief defines the comparison outcomes, but does not identify which
RedZebra date field supplies the comparison value or the services to which it
applies. No executable reconciliation behaviour has been introduced. This is a
contract review and integration design, not a completed workspace or engine.

Required management input: the exact RedZebra column name and its applicable
Call Types/services. A question requesting those details is pending in the task.

## Base SHA

`f94a750d820d5a635d6e4fcac87ef3bd02079fc2`

Created clean branch `codex/customer-ui-overhaul08` in the isolated worktree
`C:\Users\madas\OneDrive\Documents\customerapp\reconciliation-workspace-20260923`.
The original checkout was not used for implementation. No `origin/main` update
or concurrent branch was incorporated.

## Visual Reference

The brief's `C:\Users\madas\Downloads\importreconciliation.png` is absent.
Found and inspected the supplied mockup at
`C:\Users\madas\Downloads\importreconcilliation.png` (different spelling).

Its summary cards, exception list, filters and labelled comparison panel fit a
read-only workspace. The illustrative floor/kitchen/roof/garage fields do not
grant source meaning. The mockup's discard/update actions must not become live
request mutations in this preview-only wave. Use RedZebra throughout.

## Master Import Analysis

Existing `PilotWorkbookDiscovery` supports a bounded parent workbook of up to
5,000 source rows, with included/excluded counts and detected source identities.
The selected-site backend remains bounded to 500 rows. These limits are existing
safety boundaries; this task has not changed them.

The dictionary contains no approved source requested-date role. In
`WorkbookStager`, canonical facts contain identity, plot, service, completion and
products. An operational date is retained separately in private provenance.
Parent discovery counts cannot establish analysed plots, date matches, or
evaluated amendment counts. Those figures must remain unavailable until backed
by qualified evidence, never fabricated from row counts or the mockup.

## CustomerCode Routing

The existing route is source namespace + exact CustomerCode -> active binding
version -> customer/site. Plot identity remains bound site + normalized Plot Ref.
Site Name is descriptive. Reconciliation must use these same identities, block
unknown/missing codes, and preserve same-reference plots on different sites.
No routing changes were made.

## Source-Date Contract Blocker

Current explicit evidence:

- `documentation/siteapp-import-data-dictionary.md`, section 5: `Plot To Be
  Installed` means operational PC1 arrival-to-install date, and must not become
  Requested Date, Date Agreed, alternative date or completion date. No other
  source field receives customer meaning without an explicit decision.
- `documentation/source-integration-contract.md`, Canonical source record and
  Mapping and validation: the same PC1-only operational interpretation applies.
- `app/SourceImport/Semantics/Dictionary/CustomerAppDictionary.php`: the role is
  `pc1_operational_install_date`, not a source requested-date role.
- `app/SourceImport/Integration/WorkbookStager.php`: this value is retained as
  `provenance.operational_date`; it is not a canonical requested-date fact.

The latest brief approves comparison without overwriting Portal state. It does
not choose a source field. Comparing an operational date as though it confirmed
a customer's request would assign new business meaning even without a database
write. Therefore no source column has been guessed or repurposed.

Once the field and scope are confirmed, establish its exact parsing, provenance
and row/service applicability through the controlled dictionary. Missing,
invalid, conflicting or unsupported evidence must remain explicit exceptions.
Use retained source evidence for the old baseline; an amendment's
`prior_agreed_date` is Portal history and must not be relabelled as old RedZebra.

## Amendment Resolver

Existing baseline persistence can be reused:

- `call_off_date_negotiations`: amendment purpose, requested date, status,
  requester identity/name, opened time, prior and resulting agreed dates.
- `call_off_status_histories`: append-only amendment and decision events with
  before/after evidence and attribution.
- `CallOffRequest::effectiveRequestedDate()` currently returns the original
  request or batch date; it does not incorporate amendment negotiations.
- `CallOffDateViewService` already distinguishes active amendments, resulting
  agreements and completion-closed processes.

Integration seam: reconciliation should consume a dedicated effective-date
resolver returning the date, request/amendment/history identities, amendment
time/requester, lifecycle state and resolver version. The parallel Backend
branch's resolver can then replace that adapter without changing classification
or adding another amendment table. Do not silently substitute a proposed or
agreed date for a customer requested date. No resolver was implemented here.

## Match Classification

Approved requirement: old source 1 Oct, Portal amendment 3 Oct, new source 3 Oct
-> **Synced**. This describes agreement of compared evidence, not permission to
change a request or mark an entire workbook applied. Not yet implemented.

## Awaiting RedZebra Classification

Approved requirement: old source 1 Oct, Portal amendment 3 Oct, new source 1 Oct
-> **Waiting for RedZebra**. Preserve the amendment. Not yet implemented.

## Conflict Classification

Approved requirement: old source 1 Oct, Portal amendment 3 Oct, new source 2 Oct
-> **Needs review**. Show all three values; choose neither automatically.
Missing evidence must not be described as this three-value conflict.
Not yet implemented.

## Site-Level Summary

Planned presentation: one summary per exact source binding, with analysed plot
count, evaluated amendments and classification counts. Unbound sites remain
visible blockers. No workspace or summary query has been added.

## Plot-Level Comparison

Planned presentation: customer, site, site-scoped plot, service, old source,
Portal effective amendment, new source, classification and available attribution.
Private lineage belongs in a secondary Office-only disclosure. No comparison
rows have been generated against live or local business data.

## Partial Export Behaviour

Unchanged. Missing rows/dates are unrepresented evidence, never deletion, zero,
reversal, confirmation or permission to erase an amendment. Excluded Call Type
rows make no date or completion assertion.

## Source Completion

Unchanged. Genuine source completion has precedence; a date amendment cannot
reverse it. A completion-closed request must not be reopened after a later source
reversal. The proposed preview must distinguish these exceptions from date sync.

## Wald Safety

Unchanged. No upload, dictionary, exclusion, binding, preview, approval, commit,
staleness, receipt, idempotency, immutable evidence or authorization code changed.
No source file, customer data or production environment was modified.

## Multi-Site Commit

NOT enabled. Multi-site reconciliation will be non-mutating. Each source
application remains subject to the existing selected-site process.

## Evidence Integration

`PilotImportAudit` and immutable `wald_pilot_events` provide an existing audit
architecture to evaluate for attributed comparison snapshots. A snapshot must
identify the import/revision/hash, binding version, old/new source observations,
Portal history, resolver/dictionary versions, classification and comparison time.
Bound payload size and idempotency must be checked before selecting storage.
No competing journal, acknowledgement state or snapshot persistence was created.

## Performance / Query Review

Inspected existing discovery and selected-site paths. The future read model
should batch exact bindings and current relevant amendments, compare bounded
source evidence, and paginate exceptions. It must avoid reopening a workbook or
querying Portal history per row. No new query implementation exists, so no
performance measurement or N+1 qualification is claimed.

## Responsive / Accessibility

Mockup inspected. No reconciliation UI has been implemented or browser-qualified.
Required future sizes remain 1366x768, 768x1024, 390x844 and 320x740, with labelled
stacked date comparisons, keyboard filters, visible focus and textual statuses.

## Migration

NONE. No schema or business data changes.

## Tests

Documentation-only checkpoint: Git baseline/worktree isolation and relevant
contract/code paths inspected. `git diff --cached --check` passed before commit.
Application, MySQL and browser tests were not run; there are no executable changes.
No new tests or passing reconciliation totals are claimed.

## Files Changed

Only `documentation/customer-ui-overhaul08-reconciliation-contract-review.md`.

## Commit / SHA

The documentation checkpoint's exact SHA is recorded in the task response.

## Push

NO.

## Deployment

NO.

## Recommendation

Confirm the source date column and its service scope, then implement the
dictionary-backed adapter, comparison engine and workspace on this same isolated
branch. Coordinate through the effective-date resolver seam, preserving the
parallel Backend branch's ownership of amendment semantics and persistence.

CustomerApp Master Import Reconciliation blocked — source/amendment contract requires resolution
