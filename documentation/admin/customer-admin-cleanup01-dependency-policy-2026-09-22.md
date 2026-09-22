# CUSTOMER-ADMIN-CLEANUP01 dependency and deletion policy

Date: 22 September 2026. Baseline: `cfa3d093d4253416a84d855708a6fcebad7e3d25` (`origin/main`).

This feature branch implements a bounded permanent delete for an Office-selected Customer or Site. There is no reliable persisted demo/test marker. A name containing `TEST` is not treated as proof. Office must review the impact and type `DELETE`. The action is unavailable where retained business or Wald evidence exists.

## Dependency map

| Scope | Dependents | Policy |
| --- | --- | --- |
| Site | Site-user assignments | A: remove for this site only; other assignments and user accounts survive. |
| Site | Projected plots, plot services, plot products | A: delete in child-first order if no retained dependents. |
| Site | Call-off batches, requests, status/operation history, negotiations, proposals, amendments, request-linked notifications | D: block. The batch/request roots retain the full business history. |
| Site/plot service | Source projection events and issues | D: block rather than drop or detach source history. |
| Site | Wald binding versions, including historical/revoked versions | D: block. Their site FK is restrictive and their history is immutable. An active binding therefore cannot target a deleted site. |
| Site | Wald import runs, stages, rows, previews, attempts, outcomes, receipts, observations and source rows/visits | D: block at the site-owned run/source-row/visit roots. Shared stream and pilot upload records are retained. |
| Site | Wald pilot selections; knowledge contexts and profiles with their version/use/evidence history | D: block at the site-owned roots. |
| Customer | Sites | A only when each site passes the same impact policy; delete sites in ID order inside one transaction. |
| Customer | User accounts | C: block until accounts are separately reassigned or handled through the existing user lifecycle. No Office or external account is hard-deleted or silently detached. |
| Customer/Site | Administrative audits | D: preserve. Audits use UUID snapshots rather than a foreign key to the removed record. A new immutable `permanently_deleted` audit records actor, entity name/UUID and removed counts. |
| Global | Legacy source import runs, shared Wald streams/uploads/commands | Retain; never delete a global parent because one site is removed. Site-linked source events or Wald runs block the site instead. |

`B` is represented by the blocker categories on the impact screen. `C` is customer-owned user accounts. `D` is durable business/source history. No migration or FK cascade change is used.

## Transaction and confirmation

The preview hashes the entity UUID/version, exact eligible child IDs, counts and blocker counts with the application key. Final submission re-authorises the current Office account, locks the customer then site rows, recomputes the impact and refuses a stale or newly blocked request. The action deletes assignments, products, services, plots and sites in order. The database transaction rolls back all steps on failure. A repeated submission resolves to 404 after deletion.

## Remaining decision

This implementation does **not** make already imported Wald demo sites removable. Existing immutable binding versions, import runs and source evidence are intentionally retained by current schema and product decisions (notably the six-year committed-import audit policy). Management must define a reliable classification and the precise retention/disposal treatment for that evidence before an exceptional purge mechanism can be designed. Do not bypass triggers, disable foreign keys, or delete a shared master upload to force cleanup. Production data has not been changed.
