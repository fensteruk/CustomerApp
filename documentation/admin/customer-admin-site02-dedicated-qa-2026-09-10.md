# CUSTOMER-ADMIN-SITE02 Dedicated QA Report

**Date:** 10 September 2026  
**Integrated candidate tested:** `9bd782b`  
**Result:** **PASS — accepted for the next-release candidate**

## Scope

This was a separate end-to-end verification pass after backend and prepared-UI integration. It
covered customer/site lifecycle, persisted Office authority, external-role denial, containment,
mass-assignment resistance, immutable audit, read-only plot/source/import presentation,
responsive browser behaviour and real MySQL concurrency. It did not test production and made no
change to `main`.

## Functional and security result

- Active persisted Fenster Office Staff, including NULL-organisation Office, can use the
  administration workspace.
- Inactive, preview, missing-role, stale-role and mismatched-role users fail closed.
- Site Manager, Assistant Site Manager and Finishing Foreman cannot enter admin routes directly.
- Customer create, rename, deactivate and reactivate work through the integrated UI/backend
  contract and append immutable attributable audit.
- Site create without source reference, safe metadata edit, deactivate and reactivate work through
  the integrated contract. Optional location and tenant-scoped name uniqueness are preserved.
- Customer/site parent containment, UUID routing, IDOR denial, forged ownership/actor/lifecycle
  input rejection and stale optimistic versions are covered.
- Inactive customer/site scope is removed from external users without deleting assignments,
  source records or history.
- No plot create, edit or delete route/action exists. Source binding and import history remain
  bounded, read-only and exclude private Wald evidence and internal failures.

## MySQL 8.4 gate

A fresh official MySQL Community Server **8.4.11** ZIP was used for one disposable local instance
on `127.0.0.1:33489`. All **15** repository migrations installed cleanly. The integrated backend,
presentation, security and real two-process concurrency selection passed:

- **46 passed**;
- **1 SQLite-only test skipped as intended**;
- **334 assertions**.

The first cached MySQL folder was incomplete and Windows reported a missing
`libprotobuf-lite.dll`. No database server started from that folder. It was removed, replaced with
the complete official archive, and the gate was rerun successfully. The successful server was
shut down normally and its archive, binaries, database and logs were deleted after evidence was
captured.

## Browser and responsive result

One local server, one disposable SQLite database and one browser tab were used sequentially.
Authenticated Office screens were visually checked at:

- desktop: **1440 × 900**;
- tablet: **768 × 1024**;
- mobile: **390 × 844**.

Customers, customer details, site details, the responsive drawer, section navigation and the
Import Source Data placeholder rendered without horizontal document overflow. The tablet drawer
placed focus on **Close menu** when opened. Labels and non-colour status text were present. The
browser console returned no warnings or errors on the import placeholder. The placeholder had no
file input or import commit control. The browser tab, server and database were removed afterward.

## Automated evidence

- Integrated admin/sidebar selection: **54 passed / 383 assertions**.
- Presentation and real-endpoint integration: **28 passed / 130 assertions**.
- Admin form JavaScript: **13 passed / 0 failed**.
- Full CustomerApp suite: **1,479 passed / 77 environment-dependent skips / 7,745 assertions**
  (**1,556 total**).
- Vite 8.1.4 production build: passed, 6 modules transformed.
- Pint: passed.
- `composer validate --strict`: passed.
- Composer audit: **no security vulnerability advisories found**.
- npm production audit: **0 vulnerabilities**.
- `git diff --check`: passed.

## Defects and corrections

The integration pass found four safe presentation fields that the backend supplied but the
prepared UI did not yet render: source reference/binding state, full binding-version history,
plot status/product totals and correction/import receipt summaries. They were corrected in
`9bd782b` and covered by regression tests. Dedicated QA found no further application defect.

## Acceptance

CUSTOMER-ADMIN-SITE02 is accepted for next-release consolidation. Real import upload, analysis,
binding mutation and commit remain excluded. ADMIN-SITE03 may now add only the separately isolated
local/test synthetic demonstration.
