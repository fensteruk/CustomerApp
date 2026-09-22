# CustomerApp Final Real-Source Call-Type Qualification Report

Date: 22 September 2026. Base feature-branch SHA:
`a0077b81eeb565d9cf245a641011430820f7cf4b`.

## Result and source contract

DEC-071 currently excludes `CU0`, `CU1`, `CU3`, `P04` and `zzz` in addition
to the previously approved DEC-069/070 codes. `CU0`/`CU1`/`CU3` are Customer
Care-related. `zzz` has no required CustomerApp meaning at present. **P04 is
temporarily excluded**; its current source description is “Plot Installation -
2nd Visit (use TEAM)”. Management may decide a later CustomerApp meaning.
It is not mapped to Windows now.

These exact-code rows retain private raw evidence and exclusion provenance.
They make no CustomerApp plot, product, visit, service, completion, Portal
date/workflow or notification assertion and do not block a valid selected-site
review merely by existing. A different unapproved nonblank code still blocks.
The dictionary advances to v7, so older knowledge/reviews become stale.

## Real-source aggregate

Both supplied workbooks were read through the local Office pilot upload path.
Explicit test-only site bindings and reviewed structural headers permitted
non-mutating previews. No real-source preview was approved or committed.

| Source | Total rows | Included | Excluded | Unresolved Call Type rows |
|---|---:|---:|---:|---:|
| Small `.xlsx` | 16 | 15 | 1 (`CU4`) | 0 |
| Genuine full `.xls` | 4,358 | 2,567 | 1,791 | **0** |

The full-export excluded codes observed were `CU0` 20, `CU1` 49, `CU3` 167,
`CU4` 939, `P04` 226, `P06` 153, `P08` 207, `T07` 1, `T09` 3,
`XX1` 18, `Z05` 1 and raw `zzz` 7. No other unknown Call Type was observed.

Across 145 source identity units, 59 contain supported rows and are selectable
in pilot discovery; 86 contain excluded rows only and have no selectable
CustomerApp site unit. **Zero** units have a Call Type semantic blocker.
The small workbook and a representative full-export supported unit both reached
selected-site previews with zero staged and preview blockers. This qualifies
Call Type semantics for these file versions; it does not establish that every
site unit is free of unrelated product, identity or source-evidence conflicts.

## Verification and release boundary

Focused dictionary/import tests cover all five codes, the existing CU4 rule,
unchanged supported mappings, private provenance, non-projection and a genuinely
unknown CU2 blocker. The real-file qualification passed independently (one test,
10 assertions) and its temporary scripts were removed before the full suite.
The complete local SQLite suite passed **1,663 tests, 81 skipped, 8,765 assertions**.
A subsequently added direct v6-to-v7 stale-knowledge assertion passed with the
focused dictionary suite (334 tests, 1,491 assertions). Disposable MySQL 8.4
applied the existing 21 migrations and passed the exclusion/unknown-code tests
(3 tests, 34 assertions); its container was removed. Pint, frontend build,
production npm audit (zero vulnerabilities) and whitespace checks passed.
Composer validation and fresh advisory audit could not run because the
Composer command is unavailable locally.

No migration or dependency changed. Production PHP extension and writable
temporary-directory compatibility, a fresh Composer advisory result and the
DEC-068/current controlled release review remain required. This local feature
branch has not been pushed or deployed. No production import is authorised.
