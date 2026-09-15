# WP-CUSTOMER-WALD-SOURCE02 — Master Export Revisions + CustomerCode

Status: Merged into local `main`; controlled push approved under DEC-067; deployment not yet evidenced.

Date: 15 September 2026

Authority: DEC-066 and the approved CUSTOMER-WALD-SOURCE02 instruction.

## Objective

Represent RedZebra's twice-daily master export truthfully: date/slot is a logical revision family,
and CustomerCode is the stable source-site identity. Retain Office-only supervised one-site review,
atomic commit, exact bindings, immutable evidence and partial-export protection.

## In scope

- exact `CustomerNo` and `CustomerCode` dictionary roles;
- source namespace + CustomerCode bindings, with Site Name as descriptive evidence;
- missing/unknown/changed-name handling without fuzzy matching or site creation;
- same-slot successor revisions, failed quick replacement and non-failed confirmation;
- identical-hash suppression, predecessor audit and current/history presentation;
- stale uncommitted predecessor reviews and explicit correction lineage for committed units;
- SQLite, private-workbook and disposable MySQL qualification;
- decision, brief, sprint, roadmap, handover, integration and divergence documentation.

## Out of scope

- production deployment, Wald enablement, real customer import or automatic processing;
- full WALD06 multi-site orchestration or automatic site creation;
- source writeback, SiteApp change/backport, RedZebra API integration or source repair;
- absence-based deletion/reversal/zero and changes to customer-owned Portal dates/history.

## Domain rules

1. The current record is the highest revision for one source stream + export date/slot.
2. Same bytes return the current import. Different bytes create a successor.
3. A failed current predecessor needs no replacement confirmation. Every other predecessor needs
   exact confirmation and an exact current predecessor UUID; the server resolves both under lock.
4. Supersession retains every upload, workbook, event, review and receipt. Uncommitted predecessor
   selections/runs become `SUPERSEDED`; stream epoch also invalidates retained previews.
5. Committed predecessor runs remain until reviewed successor commit. The successor's site run
   records explicit predecessor lineage; existing correction checks decide whether facts may change.
6. CustomerCode is trimmed exact source text, not fuzzy/case-normalised matching. Site Name changes
   do not change identity. Multiple names under one code are retained and warned; distinct-site
   evidence for one code blocks rather than splits. Different codes never merge by name.
7. Unknown code means no active binding and requires explicit Office action. Missing code blocks a
   new master export. Exact legacy workbook hashes preserve old identity only for readability and
   accepted regression; no historical code is synthesized.

## Data and security

Existing `wald_pilot_uploads` revision/predecessor columns, immutable pilot events and generic
`wald_source_bindings` identity kind/value columns are sufficient. No migration is needed. The
replacement transaction locks the stream, checks the latest revision/hash/confirmation, inserts
the successor, supersedes eligible predecessor work and records audit. Current active non-preview
Fenster Office Staff policy remains mandatory at the controller and transition boundaries.

## Acceptance gates

- exact header and identity tests, changed/missing/unknown/duplicate-name cases;
- failed, confirmed, cancelled/missing-confirmation, forged and identical replacement cases;
- stale preview, committed correction lineage and partial-absence preservation;
- external/inactive authorization and cross-owner binding tests;
- exact private-workbook SHA/structure qualification;
- full SQLite regression and disposable MySQL 8.4.11 replacement/identity evidence;
- Pint, Composer validation/audit, frontend build, production dependency audit and diff checks.

## Release boundary

The completed branch is evidence for controlled release review only. It does not authorise a
`main` push, Forge action, database operation, production smoke/import or Wald enablement.
