# Wald Office customer/site creation qualification — 24 September 2026

This local candidate continues `codex/wald-master02` from
`6ae164cb148fa188fb8b4d01774ffa8d0992d128`. Resolver
`customerapp.master-source-resolution.v1`, discovery schema v5 and knowledge
policy v2 remain unchanged. No migration, push, deployment or production
master import occurred.

## Scope

Office approves one current exact creation proposal at a time. The action
rechecks the source revision, upload epoch, discovery hash, source hierarchy,
the customer/site names shown to Office, binding and Office authority inside
one transaction. The discovery manifest also pins knowledge policy v2 and
resolver version v1; an older saved proposal without those pins is stale.
It uses the normal CustomerApp customer and site administration actions and
the same exact binding action as existing-site selection. A new customer,
site, binding and their administration/import audit records roll back together
if any step fails. Approval creates no users, assignments, plots, products,
services or customer date requests. A fresh import summary immediately shows
the new exact binding and refreshes sibling proposals.

## Evidence and limits

The genuine private RedZebra XLS was checked in a disposable SQLite test
database. Its source rows were not committed to Git. Test fixture counts are
not production match counts. One-site preview and explicit Apply remain the
only data projection path. `FNA2439` and `FNA2538` remain blocked source
hierarchy conflicts. The Office UI groups new customer, new site, conflict,
clarification and ready units; each structural mutation requires its own
confirmation. There is no approve-all operation.

Verification results are recorded in the MASTER03 task report.

## Real XLS qualification

On the disposable fixture, the original private XLS again produced 4,358
data rows and 2,567 included rows. Seeded `FNA2563` resolved through its
Vistry → Countryside 2D binding. `FNA2473` began as a Lovell → Barne Barton
new-customer-and-site proposal; one Office approval changed it to
`EXACT_EXISTING_BINDING` without creating plots. `FNA2439` and `FNA2538`
remained source hierarchy conflicts before and after approval. A repeat
synthetic export test shows an approved hierarchy resolves automatically
on the next workbook. No original XLS bytes or source rows are in Git.

## Verification

- Final full SQLite suite: 2,029 tests, 1,937 passed, 92 skipped,
  10,634 assertions. The final focused master-export and knowledge-contract
  run passed 64 tests with 340 assertions; the proposal Blade view compiled.
- Disposable MySQL 8.4.11: two real simultaneous Office approval races passed
  (one winner, one stale result for each), 2 tests and 16 assertions. A forced
  binding-write failure rolled back customer, site, binding and approval audit:
  1 test and 6 assertions. Total selected MySQL evidence: 3 tests,
  22 assertions. All three MySQL tests were rerun against the final name
  guard and passed on a fresh disposable database.
- The Office proposal view was rendered with fictional data and checked at
  1366×768, 768×1024, 390×844 and 320×740. All had document width equal to
  viewport width, visible approval controls and keyboard-operable category
  filtering.
- `vendor/bin/pint --test`, Composer `validate --strict`, Composer `audit`,
  `npm run build`, `npm audit --omit=dev` and `git diff --check` passed.

The UI width check used a fictional rendered preview rather than live
production. MySQL used only a named disposable local database. No new
migration was required.
