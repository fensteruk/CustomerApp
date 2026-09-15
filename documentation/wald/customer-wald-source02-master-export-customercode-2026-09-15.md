# CustomerApp Wald Master Export + CustomerCode Implementation Report

Date: 15 September 2026

Work package: `CUSTOMER-WALD-SOURCE02`

Decision: DEC-066

State: Feature branch only; production unchanged and Wald OFF.

## Result

The implementation replaces same-slot hard blocking with retained revisions and makes exact
CustomerCode the authoritative new master-export source-site key. It changes neither the one-site
atomic commit unit nor partial-export meaning.

## CustomerCode and binding

Exact `CustomerNo` and `CustomerCode` headers map to `source_customer_code`. Discovery groups by
source namespace + exact code; Site Name is retained separately for display and provenance. A
known code keeps its active binding when names change. An unknown code remains unbound until an
Office user explicitly drafts and activates a binding to an existing active Portal site. Missing
code blocks new master-export intake. Different codes with equal names remain distinct.

One code with multiple observed names remains one identity and displays a name-variation warning.
Names alone do not prove separate sites; independent evidence that one code represents distinct
sites must be resolved before commit and must never cause an automatic split.

Two exact historical workbook hashes retain the earlier Site Name/source-ID interpretation. This
bounded compatibility keeps accepted history readable without inventing CustomerCodes or allowing
new code-less master exports.

## Same-slot revisions

The server resolves and locks the latest upload for a stream/date/slot. Identical workbook hashes
return a typed conflict linked to the existing import and create no revision. A different workbook
after `FAILED` creates the next revision automatically. Any other state requires exact confirmation
and the current predecessor UUID. Forged or stale predecessor IDs fail before persistence.

Creating a successor marks the predecessor upload superseded, increments stream epoch, supersedes
uncommitted predecessor selections/runs and records predecessor/successor revision, state, actor,
timestamp, workbook hash, confirmation requirement/receipt and reason in immutable audit. Current
cards show only the latest slot revision; detail pages link retained revision history.

Committed predecessor runs and receipts are retained. A selected successor unit points to the
matching predecessor run for that Portal site, so existing reviewed correction rules apply. A
replacement never reverses absent records or zeros unrepresented products.

## Private workbook qualification

The private workbook remained local and unmodified. Its expected SHA-256 matched. CustomerApp
detected one composite logical table, 33 included records, no excluded records, nine distinct
CustomerCodes and nine source identities; every code had one observed Site Name. Blank Call Type
remains valid under the previously approved CUSTAPP2 rule.

## Schema and upgrade

No migration is required. Deployed additive schema already has upload revision, predecessor,
replacement reason/state/epoch/history and generic source binding kind/identity fields. Production
upgrade is application-code only, but still requires the existing backup, maintenance/recovery,
configuration and fictional-smoke controls before any separate enablement decision.

## Verification

- Focused SOURCE02 final-state gate: 8 passed, 66 assertions. The broader Wald/pilot/CUSTAPP2/
  backend checkpoint passed 747 tests, 25 environment-gated skips and 3,536 assertions before the
  final presentation-only dialog/history-label refinement.
- New private master-export qualification: 1 passed, 9 assertions; exact SHA matched.
- Disposable MySQL 8.4.11: all 17 migrations passed. Replacement/binding/production-boundary
  gate: 48 passed, one intentionally non-MySQL skip, 172 assertions. Additional filtered private
  qualification/identity gate: 6 passed, 48 assertions.
- Full SQLite regression against the final executable state: 1,561 passed, 80 environment-gated
  skips, 8,052 assertions. The skipped private-master test passed separately with its approved
  local environment path (1 test / 9 assertions).
- Pint passed. Composer validation was valid and Composer audit found no advisories.
- Vite 8.1.4 production build passed after rerunning outside the Windows process sandbox that
  blocked Vite child-process creation. `npm audit --omit=dev` found zero vulnerabilities.
- `git diff --check` passed.

## Production impact

None. No production access, database operation, deployment, `main` push, source workbook commit,
automatic processing or Wald enablement occurred.
