# Wald master hierarchy qualification — 23 September 2026

Status: local candidate on `codex/wald-master01` from production baseline
`3e1062f145c53aba9d8cc383fdff021cbafb118f`. No push, deployment, production
mutation or master import occurred. The private source workbook is not committed.

## Original XLS, read-only qualification

The recognized source header is `Plot Ref` (currently physical column D).
The original XLS contains 4,358 data rows. The approved Call Type dictionary
excludes 1,791 rows, leaving 2,567 included rows. Under the approved spaced
and compact separator rules, the scoped Northam rule, and string plot identities,
2,531 included rows parse directly. Another 36 included rows need Office
hierarchy confirmation. Of these, 25 distinct values match the exact FNA2664
Little Cotton Farm suggestion; the suggestion remains subject to Office
confirmation. The exact FNA2561 Northam pattern accounts for 97 additional
included rows that now parse automatically.

Directly parsed included rows identify 23 customers, 57 customer/site pairs,
55 CustomerCodes and 1,825 site-scoped plot references. There are 57
CustomerCode/customer/site combinations because two included codes have
conflicting parsed hierarchy: FNA2439 (`Baker` versus `Baker Estates LTD`
at Chudleigh Knighton) and FNA2538 (two distinct Mariners Haven Phase 2
site strings). The parsed site name Chudleigh Knighton appears beneath
more than one parsed customer. These are blockers, not automatic merges.

Across all 4,358 rows before approved Call Type exclusions, 4,244 parse and
114 do not. They identify 35 parsed customers, 134 customer/site pairs, 132
CustomerCodes and 2,615 site-scoped plot references. Four codes have conflicting
parsed combinations before row exclusions; only the two above remain within
included rows. The included malformed rows consist of 31 PC1, 2 CC1 and 3 CM1.

FNA2563 included rows resolve to Vistry → Countryside 2D (241 directly parsed
rows), regardless of the descriptive Sherford Site Name. Barne Barton source
rows under FNA2473 resolve to Lovell → Barne Barton (91 included directly parsed
rows). Production currently holds the Barne Barton site under the demo customer
`Sherford Countryside 2D - Vistry PShips`, which differs from the source
hierarchy. The current production FNA2563 demo ownership also differs from
the parsed Vistry customer. Exact bindings must be reviewed after cleanup;
they must not be silently reassigned.

## Production read-only purge preview

The Office UI displayed 3 customers, 4 sites and 29 plots on 23 September 2026.
The three existing customer purge previews showed:

| Demo customer | Sites | Plots | External users | Requests | Batches | Site assignments | Binding roots | Receipts | Retained master uploads |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Sherford Countryside 2D - Vistry PShips | 2 | 15 | 1 | 1 | 1 | 1 | 2 | 1 | 1 |
| TEST — Acme Developments | 1 | 14 | 1 | 5 | 2 | 0 | 3 | 2 | 4 |
| VISTRY SOUTH WEST | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

The existing purge action refuses Sherford and Acme because they have customer
users, requests and batches. The previews also show site-scoped source rows,
visits, staging, knowledge, history, services and products. Shared master
upload evidence remains retained by the current purge design. UI totals are
not a transactionally consistent database snapshot and do not prove orphan
freedom or complete dependency coverage.

## Reset gate

No production recovery snapshot or approved database recovery point was
available during this local task. The existing purge cannot remove two
customers with their current blockers. Production reset is **prepared only**.
Before any deletion, capture customer/site/plot IDs and names, user/site
assignments, binding versions, dependent row counts and shared upload IDs from
the production database without credentials or passwords; verify a recovery
point; qualify a bounded workflow/user detachment path on disposable MySQL 8.4;
then rerun impact previews and post-purge orphan checks. Stop before importing
the master workbook.
