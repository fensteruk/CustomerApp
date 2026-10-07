# CustomerApp Repeated Call Isolation Report

Date: 7 October 2026

Result: **READY_FOR_DRY_RUN** — qualified local feature candidate, not deployed.

Starting SHA: `3b3f1ae00ec3d0d2ba97763505ef8349a9865687`

Branch: `codex/wald-plot-register-integration-2026-10-07`

## Root cause and scope

PilotWorkbookDiscovery detected a repeated nonblank Call inside its physical-row loop
and threw before returning its manifest. This workbook-scoped exception discarded all
in-memory source/inventory evidence. Dry Run never reached resolution, and normal upload
failed before selected-site review. The retained PREREQ01 fixture proves that unrelated
base inventory was hidden by an optional call identity failure.

ScopedCallEvidence now groups the full physical workbook before row/site filtering.
Every repeated group records Call, worksheet/physical rows, CustomerCodes, Plot evidence
and Call Types. No row wins, no identity is extended and no evidence is deduplicated.
Every affected observation, including the first, has a call-dependent marker.

## Projection dependency layers

Physical evidence and controlled hierarchy remain intact. Staged facts retain original
call interpretations and provenance; their safe projection independently withholds Call
Number/type/service/completion for repeated groups. ProjectionSnapshot and ProjectionAdapter
consume that same partition. This creates no ambiguous numbered Source Row or Visit.
One selected-site transaction still owns all safe inventory/products/unambiguous calls.

Quantity conflicts touching repeated evidence withhold the entire Plot/product assertion
across affected and independent observations. No first/base/latest winner is selected;
other codes/Plots stay eligible. Existing values are preserved where withheld. Unrelated
quantity conflicts and all existing hierarchy/binding/staleness guards remain blockers.

## Acceptance evidence

- The original PREREQ01 red test is unchanged and now passes.
- Same site: base Plot 1, safe PC1 Plot 2, repeated-Call Plot 3 produce three Plots,
  one safe Source Row/Visit/service, and zero ambiguous Call 4102 projections.
- Same Plot: base evidence plus repeated Call 4201 retains Plot 776 and independent
  BF quantity. Conflicting VS quantities are withheld without choosing a winner.
- Three sites: clean call, repeated call and clean base inventory all analyse and
  Apply independently under three one-site receipts. Only the clean call creates a Visit.
- A Call shared across two CustomerCodes is withheld on both sites, retaining both Plots.
- Office unticking one duplicate cannot make the remaining observation project a Visit.
- Existing completion and VS quantity 7 survive a later duplicate conflict unchanged.
- Exact-identical and date-variant alternate export repetitions remain withheld with
  all observations retained. The older fatal-error assertion is superseded by DEC-087;
  readable HTTP fatal-error rendering remains covered using a genuinely invalid Call.

## Office experience and dry run

Dry Run completes and reports discovered/base Plots, recognized call rows, repeated
groups and affected rows. Each group shows its physical rows and CustomerCodes. Source
units remain structurally reviewable. Selected-site previews explicitly list withheld
Calls and products; these facts cannot be approved through structural target choice.
Apply can include safe inventory and safe independent calls only. No report grants Apply
approval. Data is never applied during Dry Run.

## Versions and database

Policy v6; discovery v10; backend/projection v6; scoped-call-evidence v1. Old uncommitted
interpretations are stale. Generic Wald, dictionary and Source Row/Visit identity remain
unchanged. No migration. Disposable MySQL 8.4.11 has the existing 26 migrations.

## Verification

Focused SQLite: 56 passed / 570 assertions. Disposable MySQL: 43 passed / six expected
SQLite-only skips / 496 assertions across 49 tests. Updated legacy validation/journal
cases: 47 passed / 230 assertions. Final full suite: 2,114 tests / 2,022 passed /
92 expected skips / 11,485 assertions. Pint, strict Composer validation, build, production npm audit (zero
vulnerabilities) and whitespace validation pass. Composer reports the existing five
advisories: Filament medium PKSA-cnr1-6g7k-bp3d; Laravel low PKSA-d5tc-s1qs-h781;
CommonMark medium PKSA-m2dq-1fhr-29b1 and high PKSA-m4t9-vsgq-8khn; Flysystem low
PKSA-w9tt-7782-78jx. No dependency or lockfile is changed. The disposable MySQL server
was shut down normally and its task-only data/configuration was removed.

## Files and disposition

Changes are the scoped finding/partition helper, discovery/stager, shared projection
consumers, preview payload, CustomerCode review classification, dry-run report, presentation,
compatibility pins and focused tests. DEC-087/source contract/current handover/divergence
register record the approved scope. The exact file inventory is available in this commit.

No push, main merge, deployment, production connection or genuine workbook Apply. Next
qualify the genuine workbook through Analyse only against this exact corrected candidate.
