# Wald real-source release qualification — 22 September 2026

## Addendum — DEC-069 Customer Care exclusion, 22 September 2026

Management subsequently confirmed that `CU4` means Customer Care and every such row
is irrelevant to CustomerApp. DEC-069 is implemented locally on
`codex/wald-realdata03-optional-completion` as a global exact-code exclusion, with
private provenance and dictionary v5 knowledge staleness. The original findings below
describe the earlier dictionary and are retained as historical evidence.

Both supplied files passed local Office pilot upload/discovery again. The 16-record
small `.xlsx` now has 15 included and one excluded CU4 row. With an explicit test-only
site binding and reviewed structural header answers, its selected-site staging has
zero blockers and the non-mutating preview has no blockers. No preview was approved
and no real data was committed. The original 4,358-record `.xls` is accepted without
conversion, with 3,419 included and 939 CU4 rows excluded. Other unknown codes in
the full file remain unresolved and can block their affected selected-site units.

This addendum does not establish production readiness. The PHP extension/temporary
directory check, fresh Composer advisory result and controlled release review remain.
No code or real import was pushed or deployed for DEC-069.

Final local qualification after the exclusion: full SQLite suite **1,632 passed,
81 skipped, 8,539 assertions**; focused disposable MySQL 8.4 CU4 success/blocker
cases **2 passed, 19 assertions**, after 21 existing migrations applied. The
disposable container was removed. Pint, frontend build, production npm audit
(zero vulnerabilities) and whitespace check passed. The Composer executable is
not available on this machine for a fresh validation or advisory check. There
is no new migration or dependency change.

## Result and Git boundary

**Management mapping decision required; release remains on hold.** No production upload,
binding, import, Wald setting change, push or deployment was made. During qualification the
user separately increased Forge's maximum upload size from 2 MB to 5 MB and maximum PHP
execution time from 30 to 45 seconds; both were verified after page reload. The private source workbooks
were read only in local disposable tests and were not copied into Git. This report contains
aggregate evidence only.

The fresh `origin/main` and local `main` baseline was
`51635964ec0787082953cf6d6f9596570beb8d4d`. The existing clean XLS candidate was
`codex/wald-xls-reader` at `5a3e63a482a9e52c160e9e49d98ef74ddb9c5741`. It contains
the genuine XLS reader commit `dde25a6` and approved DEC-068 `Customer Number` header commit
`16964ff`, equivalent to the source decision commit's final dictionary tree. Its diff from
main has only the expected upload/reader, dependency, dictionary, tests and documentation
changes. Neither fix was reimplemented. The local follow-up branch is
`codex/wald-realdata03-optional-completion`, based on that candidate.

## Real workbook path and structure

Both supplied files passed the application's Office pilot private upload and discovery path
without conversion. Analysis used local/test Office authority, explicit structural answers,
temporary exact source-site bindings and non-mutating previews. No real-source preview was
approved or committed. The full file is a genuine legacy BIFF `.xls` export.
Both analysis runs initially requested structural clarification; the review answered the
observed header candidates rather than silently accepting a mapping.

| Evidence | Small `.xlsx` | Full `.xls` |
| --- | ---: | ---: |
| File size | 10,451 bytes | 1,359,360 bytes |
| Logical table | `A1:R17` | `A1:AB4359` |
| Data records / distinct CallNos | 16 / 16 | 4,358 / 4,358 |
| Duplicate CallNos | 0 | 0 |
| Source CustomerCodes | 1 | 145 |
| Source Site Name strings | 1 | 152 |
| CustomerCode + Plot Ref pairs | 16 | 2,695 |
| Blank Call Types | 0 | 0 |
| Completion column | Absent | Absent |

The small workbook's exact identity header is `Customercode`; the full export's is
`Customer Number`. Both are recognized as the existing `CUSTOMER_CODE` role. The full
export has 2,694 distinct Plot Ref strings globally; one appears at more than one source
site, so global Plot Ref matching would be wrong. The approved exact site binding and
site-scoped plot identity are retained. The full export has 145 source groups, of which
seven have only currently approved Call Types. There were no workbook-level table warnings.

Small-file product headers are CAS, FLU, PFD, PSU, PSG, CDF, CDU and CDG. The full export
also has VS, TT, BAY, GLS, PSP, BF, ALI, AOV, FI, WP and MISC. Both contain populated
date-labelled source columns. Those fields are retained as private evidence; this
qualification does not assign customer date or completion meaning to them. Neither
workbook represents completion values because neither has a `Complete` column.

## Call Type inventory

No nonblank unknown code was given a meaning by this task. Supported meanings are only
those already in the controlled dictionary.

| Code | Small | Full | Current meaning | Blocks selected site? |
| --- | ---: | ---: | --- | --- |
| PC1 | 15 | 1,653 | Windows; supported | No |
| CC1 | 0 | 308 | Cavity Closers; supported | No |
| CM1 | 0 | 601 | CML; supported | No |
| CM2 | 0 | 5 | CML; supported | No |
| CML | 0 | 0 | Approved dictionary code, not observed | No |
| CU0 | 0 | 20 | Unknown; decision required | Yes |
| CU1 | 0 | 49 | Unknown; decision required | Yes |
| CU3 | 0 | 167 | Unknown; decision required | Yes |
| CU4 | 1 | 939 | Unknown; decision required | Yes |
| P04 | 0 | 226 | Unknown; decision required | Yes |
| P06 | 0 | 153 | Unknown; decision required | Yes |
| P08 | 0 | 207 | Unknown; decision required | Yes |
| T07 | 0 | 1 | Unknown; decision required | Yes |
| T09 | 0 | 3 | Unknown; decision required | Yes |
| XX1 | 0 | 18 | Unknown; decision required | Yes |
| Z05 | 0 | 1 | Unknown; decision required | Yes |
| zzz | 0 | 7 | Unknown; decision required | Yes |

No observed code qualified for a historical or checksum-scoped exception. A genuine blank
Call Type remains valid null/no visit, but no blank occurred in either file.

## First blocker and bounded correction

On the original `5a3e63a` candidate, the small workbook first asks for a structural
choice between `Plot number` and `Plot Ref`. In the local test, the exact `Plot Ref`
header was selected explicitly. Analysis then failed with `required_column_unresolved`
because staging incorrectly required `Complete`, although the approved source contract
marks it optional. This was the first technical compatibility defect after the normal
structural review. The follow-up makes completion optional through staging, snapshot and
projection. An absent value makes no completion assertion and preserves any already
established completion under the partial-export rule. A synthetic two-export regression
checks that the second, no-Complete export commits without a completion reversal.

After that correction, the small file stages all 16 rows. Fifteen are valid under the
current Call Type dictionary; one `CU4` row is marked `UNRESOLVED_CALL_TYPE`. The selected
site preview is reached but has `BLOCKED_STAGED_RECORDS` and cannot be approved or
committed. This is the first remaining **business semantic** blocker. The `CU4` row has
source CustomerCode, Site Name, Plot Ref, CallNo, all eight represented product fields
and populated date-labelled source fields. That context does not establish CU4's meaning.

An unknown code blocks its dependent staged row and then the **entire selected-site
review unit**, because preview requires no blocked rows in that unit. It does not block
other source-site units or the whole workbook. For the full export, a representative
approved-only source group staged two rows with zero blockers. Its non-mutating preview
resolved the exact bound CustomerApp site and showed two site-scoped Plot Refs as `CREATE`.
The small file's exact temporary binding also resolved the target site, but its blocked
preview cannot be approved. No automatic user assignment was made.

**Smallest decision request:** CU4 occurs once in the small workbook and 939 times in
the full export. What does CU4 mean in RedZebra? Other unknown full-export codes will
need separate approved mappings before their selected sites can commit.

## Runtime and release checks

PhpSpreadsheet 5.10 is locked in the candidate. It requires PHP `^8.2` plus ctype,
dom, fileinfo, filter, gd, iconv, libxml, mbstring, simplexml, xml, xmlreader,
xmlwriter, zip and zlib. The Forge UI shows CustomerApp on PHP 8.5 and MySQL 8.4,
with a user-updated 5 MB upload limit, 45-second execution limit, PHP-FPM `memory_limit = 512M`
and `post_max_size = 8M`. The full private file is below the upload limit; local
discovery took approximately 6.8 seconds and peaked at 192 MiB. These settings
appear sufficient, but **production runtime compatibility is not fully established**:
the required extensions and writable PHP temporary directory were not verified.
A read-only Forge command intended to check them remained `Waiting` with no useful
output. The agent made no Forge configuration change; the user changed the upload and execution limits.

Local verification: the complete SQLite application suite passed, 1,629 passed,
81 skipped and 8,509 assertions. Strict Composer validation passed. One Composer
audit invocation returned no advisories from a local cache after Packagist timed out;
a later live retry exited 1 because the security-advisory endpoint timed out. A fresh
Composer advisory gate is therefore still unverified.
Pint and the frontend build passed. The production npm audit found zero
vulnerabilities after using the local Windows trust roots. No migration was added.

Disposable MySQL 8.4.11 migrations applied cleanly (21). The selected-site HTTP
commit boundary passed (1 test, 11 assertions). Upload, source identity, replacement,
composite, environment setting and optional-completion coverage passed (46 passed,
2 skipped, 249 assertions). Staging, safety, rollback, tenant isolation and Portal
protection passed (199 passed, 1 skipped, 711 assertions). The WALD04 race/retry gate
passed (11 tests, 456 assertions). The pilot review group passed (5 passed, 1 skipped,
73 assertions). The earlier broad MySQL run was invalid for many cases because its test
environment lacked `APP_KEY` and used the wrong databases for database-specific gates.
The concurrency gate initially exposed a synthetic fixture defect: copied users retained
duplicate UUIDs and hit the unique constraint before the race. The follow-up gives
second synthetic actors unique UUIDs without changing race assertions. The pilot review
test also assumed JSON object key order, which MySQL does not preserve; its corrected
check still requires the exact four keys. The WALD05 MySQL race gate passed (23 tests,
1,496 assertions). Across the final valid MySQL groups, **285 passed, 4 skipped and
2,996 assertions**. The disposable container was stopped and automatically removed.

## Release recommendation

Keep this branch local pending: (1) an explicit CU4 management mapping decision,
(2) resolution/acceptance of other unknown codes for sites intended for import,
(3) production extension/temp verification and (4) a fresh Composer advisory check.
The MySQL release gates passed. DEC-068 requires a separate release review before
any production push.
No candidate code is deployed, and the previously failed production revision is unchanged.
