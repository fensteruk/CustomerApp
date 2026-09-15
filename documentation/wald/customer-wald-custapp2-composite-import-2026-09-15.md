# CustomerApp CUSTAPP2 Composite Import — 15 September 2026

## Status

**PARTIAL.** The bounded compatibility implementation and local SQLite/MySQL qualification are
complete on `codex/wald-custapp2-composite-2026-09-15`. It is feature-branch work only. It is not
on `main`, released, deployed or enabled in production.

The private workbook SHA-256 matched the approved value
`a9a5f2214d3b687af9043bb0f5232d5d105bb0e9c677c1e82e0b1e0e82b285df`. Its workbook data was
not copied into the repository. Aggregate qualification found one real product conflict in one
site/plot selection. The importer correctly blocked that complete site unit rather than choosing
a value. Eight other site selections committed to disposable local databases.

## Implemented contract

- A logical table can be assembled deterministically from compatible horizontal fragments while
  retaining every physical cell coordinate. The approved workbook resolves to header
  `C2:E2+I2:AJ2` and data `C4:E36+I4:AJ36` on `Sheet2`.
- A genuine complete physical table remains preferred. Aligned headerless continuation regions
  remain supported, and competing composite candidates require clarification.
- `Call No.` recognition uses exact semantic tokens: `call` plus `no`, or `call` plus `number`,
  in either order after structural punctuation/spacing/case normalization. There is no fuzzy
  matching.
- A genuinely blank Call Type is valid null. It may establish or update plot/product facts but
  cannot establish a visit/service, create a Portal request, mutate completion or manufacture a
  Portal-owned date.
- Plot identity is active exact source-site binding plus normalized Plot Ref.
- Source Row identity is source namespace plus CallNo.
- Visit identity is Source Row plus a recognized non-null Call Type.
- A recognized visit becoming blank in a later partial export preserves the committed visit.
  A recognized Call Type change for the same Source Row blocks.
- Products are consolidated per resolved plot. Missing/unrepresented evidence makes no
  assertion; explicit zero is exact; identical explicit values agree; missing plus explicit uses
  the explicit fact; conflicting explicit values block the complete selected-site unit.
- Unlabelled populated cells remain private unmapped evidence. Operational dates remain source
  facts and never become requested, agreed, proposed, amendment or invented completion dates.
- Only one explicitly selected, exactly bound source site is reviewed and committed at a time.
  No automatic nine-site operation was added.

## Physical provenance

Staged evidence retains worksheet, original cell coordinate, raw value, logical row and fragment
identity per interpreted value. The canonical composite reference describes the logical view; it
does not replace source coordinates. Tests prove coordinates from both fragments, including
`C4` and `T4`, remain attributable. Raw and unmapped evidence remains private.

## Dictionary and identities

The CustomerApp source dictionary is now `customerapp.source-dictionary.v2`, fingerprint
`ac4fb1ac419aa86aba32c6b8f47e76b014fe2af93d11e1558b15bdf31ca8bc0d`. The CUSTAPP2 composite
profile recognizes only PC1 → Windows, CC1 → Cavity Closers and CM1 → CML. It does not inherit
the earlier workbook's checksum-scoped `CC!` correction or CM2 treatment. Nonblank unknowns
remain blocking.

Backend application/projection identity versions advance so stale profiles/previews cannot be
silently reused under the changed semantics.

## Schema

Additive migration `2026_09_15_000016_add_wald_source_row_and_plot_identity.php`:

- establishes the unique `(site_id, plot_reference)` projected-plot identity after preflight;
- adds `wald_source_rows` and immutable `wald_source_row_observations`;
- attaches nullable `source_row_id` and `call_type` to `wald_source_visits`;
- backfills existing Wald visits/observations;
- adds SQLite/MySQL immutability triggers and refuses destructive populated rollback.

No prior migration was edited. The migration was exercised on clean SQLite and disposable MySQL
8.4.11. No production migration ran.

## Private workbook qualification

Observed aggregate structure matched the approved baseline:

| Measure | Result |
|---|---:|
| Worksheets used | `Sheet2` |
| Source rows | 33 |
| Source sites | 9 |
| Plot identities before binding | 23 |
| Unique populated CallNo values | 33 |
| Duplicate CallNo values | 0 |
| PC1 | 8 |
| CC1 | 10 |
| CM1 | 2 |
| Blank Call Type | 13 |

One selected site contains one plot with conflicting explicit quantities for two products across
three source rows. That seven-row site unit is blocked atomically. The eight compatible site
units produced the same results on SQLite and MySQL:

| Committed aggregate | SQLite | MySQL 8.4.11 |
|---|---:|---:|
| Selected sites committed | 8 | 8 |
| Selected sites blocked | 1 | 1 |
| Source rows | 26 | 26 |
| Projected plots | 20 | 20 |
| Source visits | 13 | 13 |
| PC1 / CC1 / CM1 visits | 5 / 7 / 1 | 5 / 7 / 1 |
| Product projections | 200 | 200 |
| Portal requests | 0 | 0 |
| Portal notifications | 0 | 0 |

## Verification

- Focused CUSTAPP2 structure/integration: 37 tests, 80 assertions.
- WALD03/WALD05 focused regression: 354 tests, 1,473 assertions.
- Private workbook SQLite qualification: 1 test, 31 assertions.
- Private workbook MySQL qualification: 1 test, 31 assertions.
- Existing MySQL commit/race gate: 8 tests, 207 assertions.
- Combined final CUSTAPP2 and corrected regressions: 240 tests, 1,469 assertions.
- Full SQLite application suite: 1,611 tests; 1,532 passed, 79 skipped; 7,953 assertions.
- Laravel Pint passed after one mechanical test import-order correction.
- Strict Composer validation passed; Composer audit reported no security advisories.
- Vite production build passed. Its first sandboxed attempt was blocked by Windows `spawn EPERM`;
  the unchanged build passed when rerun with process-spawn permission.
- `git diff --check` passed.

The disposable MySQL server was shut down and its test data/schema directory was removed after
qualification.

## Security and production impact

Office-only policy, active role checks, exact tenant/site binding, forged selection/identity
refusal, private storage, bounded review, stale-preview protection, atomic commit, idempotency,
cross-site isolation and immutable evidence remain enforced. No customer-facing raw or unmapped
workbook evidence was added.

Production currently serves corrected commit-boundary release
`89768986a1a32d4258b5deebdf3012584acd6a6c`. Wald remains effectively OFF because
`WALD_IMPORT_AVAILABLE=false`; the gate-drift investigation is a separate Integration/DevOps
task. This work did not access or change production, Forge, SiteApp, `main`, the production gate,
customer data or external services.

## Remaining blocker and next step

The implementation itself has no known code blocker for dedicated review. Full CUSTAPP2
qualification is blocked by the one real conflicting plot/product assertion. Obtain corrected
source evidence or an explicit authorised data-resolution decision, then repeat the exact-SHA,
single-site local qualification. Dedicated QA, release approval, backup, migration rehearsal and
supervised production activation remain separate gates.
