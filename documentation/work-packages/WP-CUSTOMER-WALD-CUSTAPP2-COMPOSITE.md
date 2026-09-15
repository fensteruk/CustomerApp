# WP-CUSTOMER-WALD-CUSTAPP2-COMPOSITE

Status: **Implemented with PARTIAL private-workbook qualification on a non-deploying feature
branch.**

Date: 15 September 2026

## Objective

Add bounded support for CUSTAPP2-style RedZebra workbooks while preserving WALD02–05 and weekend
pilot safety. This package owns composite-table recognition, deterministic CallNo header
normalization, blank Call Type semantics, distinct Plot/Source Row/Visit identities, safe
plot/product projection and local SQLite/MySQL qualification.

## Approved domain contract

| Domain identity | Definition |
|---|---|
| Plot | Exact active source-site binding + normalized Plot Ref |
| Source Row | Source namespace + CallNo |
| Visit | Source Row + recognized non-null Call Type |

A blank Call Type means the plot exists but no call-off visit has started. It may contribute
valid plot/product facts. It cannot create a visit, service, request, completion transition or
Portal-owned date. A later blank observation cannot erase an established visit under
`PARTIAL_FILTERED_EXPORT`; a recognized type change for the same Source Row blocks.

Product consolidation is deterministic per plot. Unrepresented is no assertion, explicit zero
is exact, agreeing values consolidate, and conflicting explicit values block the whole selected
site unit. There is no first/last/min/max/average winner.

## Structural contract

The approved private artifact has SHA-256
`a9a5f2214d3b687af9043bb0f5232d5d105bb0e9c677c1e82e0b1e0e82b285df` and logical table
`C2:E2+I2:AJ2` / `C4:E36+I4:AJ36` on `Sheet2`. Composite fragments require aligned records,
compatible bounds, complementary roles and no stronger independent-table interpretation.
Ambiguity requires clarification. Physical coordinates and unmapped evidence remain private.

Call-reference headers resolve only from exact normalized token sets `{call,no}` or
`{call,number}`. Token order may vary; punctuation, spaces, underscore, hyphen, case and
concatenation are structural differences. Fuzzy matching is forbidden.

## Scope boundaries

- One explicitly selected, exactly bound source site per review/commit.
- PC1, CC1 and CM1 only for the CUSTAPP2 composite profile.
- No inherited checksum-specific `CC!` or CM2 exception.
- Partial export only: absence is not deletion, reversal or zero.
- No automatic all-site commit, WALD06 orchestration, RedZebra API, writeback, SiteApp runtime,
  UI redesign, production activation, main push or deployment.

## Persistence

Additive migration `2026_09_15_000016_add_wald_source_row_and_plot_identity.php` adds durable
source-row identity/history, Visit attachment/type and unique site/plot identity. It backfills
legacy Wald visits and adds database guards. Deployed migrations remain unchanged.

## Acceptance

Synthetic structure, semantics, identity, transition, product, provenance, security, atomicity,
idempotency, stale-review and cross-site tests must pass on SQLite. Clean migration, private
workbook qualification and existing concurrency gates must pass on disposable MySQL 8.4.11.
The existing weekend-pilot workbook must remain compatible.

Private CUSTAPP2 acceptance is **PARTIAL**: eight sites commit locally; one seven-row selected
site blocks due to one plot with two conflicting product assertions. That is correct fail-closed
behaviour, not permission to repair source data or choose a value. See
`documentation/wald/customer-wald-custapp2-composite-import-2026-09-15.md`.

## Release boundary

The branch is eligible for dedicated review only after final checks and commit. It is not release
approval. Production remains on `89768986a1a32d4258b5deebdf3012584acd6a6c` with Wald
effectively OFF through `WALD_IMPORT_AVAILABLE=false`. Production gate investigation and any
future activation are separate work.
