# CustomerApp Call-Type Exclusion Qualification Report

Date: 22 September 2026. Base: `f24f1000b98549d4d4ea7704b76b2e6fb1c9c8a5` on
`codex/wald-realdata03-optional-completion`. This report describes local feature-branch
qualification, not a production release.

## Overall result

**REMAINING_SEMANTIC_BLOCKERS.** DEC-070's exact codes are excluded and a real clean
selected-site unit reaches preview. Five other observed Call Types remain unknown.

## Controlled exclusions and behaviour

DEC-070: `CM8`, `P02`, `P06`, `P08`, `Q01`, `QU5`, `SS1`, `T03`, `T05`,
`T07`, `T09`, `T11`, `T13`, `T15`, `VC1`, `X10`, `X14`, `X16`, `X50`, `X99`,
`XR1`, `XR2`, `XX1`, `Z05`, `Z09`. DEC-069 separately retains `CU4` Customer Care.

These exact normalized codes retain raw private workbook/staging evidence and
approval provenance. They make no CustomerApp plot, product, visit, service,
completion, customer date or workflow assertion. An excluded row does not itself
block its selected-site review. Existing CallNo uniqueness and unsafe-cell guards
remain. Dictionary identity advances to v6; older semantic reviews become stale.

## Actual source qualification

Both supplied workbooks passed the real local Office pilot upload/discovery path.
Explicit test-only site bindings and reviewed header clarifications allowed
non-mutating previews. No real-source preview was approved or committed.

| Source | Total records | Included | Excluded | Unknown Call Type rows |
|---|---:|---:|---:|---:|
| Small `.xlsx` | 16 | 15 | 1 (`CU4`) | 0 |
| Genuine full `.xls` | 4,358 | 3,036 | 1,322 | 469 |

The 1,322 excluded full-export rows contain: `CU4` 939, `P06` 153,
`P08` 207, `T07` 1, `T09` 3, `XX1` 18 and `Z05` 1. The other confirmed
exclusion codes were not observed in this export. The 469 count represents rows
blocked by unresolved Call Type meaning, not a claim that every other source
validation outcome across all sites has been individually reviewed.

**Remaining unknown Call Types:** `CU0` 20, `CU1` 49, `CU3` 167,
`P04` 226 and `zzz` 7. These remain blocking for their affected selected-site
units. No meaning was inferred for them.

Across 145 source identity units, 41 contain only supported or excluded codes.
Of those, 28 have no included rows and therefore no selectable CustomerApp site
unit. The remaining **13** are selectable and have no Call Type semantic blocker.
The other **104** units contain at least one unresolved code. A representative
selectable clean unit reached a preview with zero staged or preview blockers.
The small workbook also reached a clean preview with one excluded staged row.
Neither exclusion created a blocker. Other validation issues may still require
review for untested site units.

## Verification and release boundary

Focused synthetic tests cover every excluded code, raw private provenance,
product, service, completion, workflow and notification non-projection, selected-site
commit, unchanged approved code mappings, CU4 and unrelated unknown-code refusal.
The real-file local qualification passed independently (one test, 14 assertions);
its temporary scripts were removed before the complete suite. The complete
committed-code SQLite suite passed **1,658 tests, 81 skipped, 8,725 assertions**.
The extra focused projection assertions then passed (3 tests, 34 assertions).
Disposable MySQL 8.4 applied all 21 existing migrations and passed the
selected-site success/blocker tests (3 passed, 30 assertions); its container
was removed. Pint, Vite build, production npm audit (zero vulnerabilities) and
`git diff --check` passed. The local Composer executable is unavailable, so
strict validation and a fresh advisory check remain unverified.

There is no migration or dependency change in DEC-070. Production PHP extension
and temporary-directory compatibility, a fresh Composer advisory check and
DEC-068/current release review remain outstanding. No production upload,
import, push or deployment is authorised by this report.
