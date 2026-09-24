# Wald automatic resolution qualification — 24 September 2026

Branch `codex/wald-master02` starts at `main` SHA
`3e1062f145c53aba9d8cc383fdff021cbafb118f` and deliberately cherry-picks
the qualified hierarchy commit `e5d43c64de01b8f7b85a40d553f8a1f87ded74f8`
as `7ae8f9552eb058a72b9fdc9a3d524aa0eb6f34c3`. This is a local candidate;
no push, deployment, production mutation or master import occurred.

## Operational contract

`MasterSourceResolver` groups one result per included source CustomerCode. It
preloads active customers, active sites, binding roots/versions and relevant
site-scoped plots in bounded queries. The seven current composite outcomes are
`EXACT_EXISTING_BINDING`, `EXACT_CUSTOMER_EXACT_SITE`, `EXACT_CUSTOMER_NEW_SITE`,
`NEW_CUSTOMER_AND_SITE`, `BINDING_CONFLICT`, `SOURCE_HIERARCHY_CONFLICT` and
`MALFORMED_HIERARCHY`. Legacy flat uploads retain their prior path. The Office
selected-site action may activate a missing exact binding atomically and audit
its actor, source code, parsed customer/site and matched IDs. It does not create
CustomerApp customers or sites. The one-site preview/commit gate remains.

## Real original XLS qualification

The private original XLS was uploaded only to a disposable SQLite test database,
using the real upload, discovery and summary path. It contains 4,358 data rows:
1,791 approved excluded and 2,567 included. The included rows form 59 distinct
source identities. The controlled clean fixture had an active Vistry customer
with Countryside 2D site and an exact FNA2563 binding, plus an active Lovell
customer with Barne Barton site and no binding. It contained no projected plots.
Against that **specified fixture**, resolution was:

| Outcome | Source identities |
|---|---:|
| Exact existing binding | 1 |
| Exact customer and site, new binding available | 1 |
| Exact customer, new site proposal | 15 |
| New customer and site proposal | 35 |
| Source hierarchy conflict | 2 |
| Malformed hierarchy | 5 |
| Binding conflict | 0 |

The two existing-site identities are automatically matched. Safe units contain
1,776 distinct site-scoped plot create candidates and zero reuse candidates in
this empty-plot fixture. The 7 blocked units are excluded from that candidate
total. These fixture counts must not be represented as production match counts.

FNA2563 resolves Vistry → Countryside 2D with 241 included rows and 162 plot
references, using its exact active binding. FNA2473 resolves Lovell → Barne
Barton with 91 included rows and 72 plots, needing no manual binding draft.
FNA2439 remains blocked because Chudleigh Knighton appears under Baker (21
rows) and Baker Estates LTD (3 rows). FNA2538 remains blocked because
Devonshire Homes appears with `Mariners Haven (Phase 2)` (17 rows) and
`Mariners Haven Phase 2` (2 rows). Neither variant is chosen automatically.

No customer/site creation, multi-site commit, production import or demo reset
was performed by this qualification.

## Verification

The full SQLite application suite passed: 2,018 tests, 1,929 passed, 89 skipped,
10,574 assertions. The selected MySQL 8.4.11 resolution, exact-binding,
controlled-name and existing-draft tests passed: 4 tests, 34 assertions. A
second MySQL run covering composite plot routing, binding conflict and stale
preview passed: 3 tests, 15 assertions. In total, 7 selected MySQL tests
passed with 49 assertions.
`npm run build`, `npm audit --omit=dev` (zero vulnerabilities),
`vendor/bin/pint --test` and `git diff --check` passed. Composer is not
installed in this checkout, so `composer validate --strict` and
`composer audit` could not be run here. MySQL was disposable and held no
production data. The private XLS and its temporary qualification test were
not added to Git.
