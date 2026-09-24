# CUSTOMER-WALD-MASTER04 — local qualification

Date: 24 September 2026. Branch: `codex/wald-master04`. Starting SHA:
`1aa052bfe73c456f401917d957b3b49d62298454`. This is a local feature
candidate. It was not pushed, deployed or applied to production data.

## Delivered

Office reviews unresolved composite master source units one CustomerCode at a
time. Fully exact groups skip the wizard. Each displayed group starts with all
rows selected; Office can select all, clear all, choose one exact active
Customer/Site, inspect proposed string plots, and confirm directly into the
next group. Canonical MASTER03 customer/site creation returns to row review.
When a safe target does not yet exist, Office can defer the whole group without
inventing one. Progress counts review groups.

Unticked, malformed and unresolved rows reach the final Unknown /
Unclassified workspace after the normal groups. Office can review one row,
review safe same-code rows together, exclude with a reason, or leave it
unresolved. A missing CustomerCode remains outside projection even after a
target/plot note. Decisions pin the current import revision, manifest hash and
epoch, and preserve actor, time, raw row evidence, group versions and decision
history. The three exact CustomerCodes from DEC-080 retain their separate
Ignored tab. One-site preview and explicit Apply remain mandatory; no
multi-site commit was enabled.

## Real workbook qualification

The genuine `Site App Report (EXCEL)(1).xlsx` and `.xls` files were read into
disposable local MySQL 8.4 only. Each produced the same 4,358 physical records,
2,561 included rows, 1,797 excluded rows and 56 source units. The three-code
Ignored tab accounts for 36 rows, of which six newly change included/excluded
projection compared with the prior rule. No row initially lacked CustomerCode.
In an otherwise empty local dataset, no source units auto-resolved and all 56
needed Office review. With two fictional exact Customer/Site fixtures, one
unit resolved automatically and 55 remained for Office review. These are local
qualification counts, not production mapping counts. No genuine row was
committed to a customer-facing site.

The reviewed plot fallback preserves string values such as `033`, `Com 4` and
`Block A`, and removes an initial `Plot ` token from `Plot 776`. Multiple
unexplained fragments stay unresolved. Known conflicting CustomerCodes
`FNA2439` and `FNA2538` remain separate group decisions.

## Verification

The final full SQLite application suite run passed 1,949 tests with 92 skipped
(2,041 total; 10,737 assertions). A subsequent focused rerun after the final
approval handoff and HTTP defer changes passed 42 tests and 354 assertions.
A disposable MySQL 8.4 schema was rebuilt successfully through migration
`2026_09_24_000021`; seven focused decision, defer, stale and safe-preview
tests passed with 55 assertions. An additional MySQL 8.4 check of canonical
customer/site approval handing off to row confirmation passed one test with
13 assertions. Browser QA at 1366×768, 768×1024,
390×844 and 320×740 found no horizontal page overflow or JavaScript page
errors. Default selection, Clear all, Select all, live plot proposals and
Unknown-last routing were exercised. Pint, Composer strict validation and
audit, Vite build, npm production audit, and `git diff --check` passed.

The new migration adds private review row evidence and append-only decision
and group histories. No existing migration was edited. No production schema
or data was changed.
