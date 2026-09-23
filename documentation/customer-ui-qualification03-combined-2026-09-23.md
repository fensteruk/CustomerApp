# CustomerApp combined release qualification — 23 September 2026

## Scope and result

The clean qualification worktree was
`C:\Users\madas\OneDrive\Documents\customerapp\second-wave-integration-20260923`,
branch `codex/customer-ui-integration02`, at candidate
`62945c1326696efd87efaf7536d05078870863e6`. This report qualifies that
candidate plus the separately committed, browser-proven Plot-label correction
`2ca05f5b6e67964c9c171468e66542b01d10bdc2`. Result:
**READY_FOR_RELEASE_REVIEW**, not production approval. Nothing was pushed or
deployed. The original checkout and its unrelated changes were not touched.

## Real-browser qualification

The local app ran against fictional data in the disposable MySQL database.
The actual in-app browser exercised 1366 × 768, 768 × 1024, 390 × 844, and
320 × 740 for Office Dashboard, Customers, Users, Imports, Office Amendments,
Master Import Reconciliation, Review Requests and Office Site workspace, plus
Site User dashboard, Plot workspace and amendment form. At every measured
page/size there was no document horizontal overflow and no visible active
control outside the viewport. At tablet size, offscreen Office sidebar
controls were correctly inert and `aria-hidden` while closed. Mobile Menu
opened, Escape closed it and returned focus. Observed browser errors/warnings:
none. Visual screenshots included desktop, tablet and both phone widths.

- Dashboard showed one current amendment despite earlier cycles, truthful
  import/call-off counts, working section links and responsive cards.
- Customers showed three customers, sites/plots totals, search and clear,
  whole-card navigation, Add customer, and Add site for an empty customer;
  card CSS uses three/two/one columns at desktop/tablet/mobile.
- Users showed Global Office access, an assigned Site User's customer and
  sites, an unassigned-user warning with Assign site, Manage user and search.
- Imports showed the latest three separately from older history; page two
  navigation exposed the final three of 13 older fictional uploads. Ready
  and Failed statuses were textual; Upload was explicitly not Apply. The
  fictional upload stage had no analysed row/site/plot counts, and the UI
  truthfully said they were not yet known; an analysed selected-site preview
  was not created during this browser pass.
- Site and Plot views showed customer/site identity, six plot cards,
  attention/upcoming activity, search, whole-card navigation, Windows/Doors
  quantities, four service cards, source detail behind disclosure and
  retained amendment history.
- The browser's real fictional amendment history used 1 → 5 → 7 October
  2026 (3 October 2026 falls on a Saturday). It showed 7 October as the
  current effective date on Plot, one Dashboard item, latest 7 October in
  Office Amendments and reconciliation, and earlier dates in history.
  The exact 1 → 3 → 5 October scenario is automated in 2029, when all dates
  are weekdays. The form exposed an early-date reason when an early date was
  selected and native required validation blocked review without it. The
  review step was inspected without submitting another persistent change.
  No RedZebra writeback was offered.
- Office Amendments showed latest-vs-previous dates, requester/time and
  timeline, filters and a tested zero-result state. Browser fixture had one
  queue item; multi-item ordering, early reason and pagination were covered
  by the focused automated test.
- Reconciliation used the corrected
  `C:\Users\madas\Downloads\importreconcilliation.png` reference, showed
  exact source-site context, latest Portal value and previous history, and
  consistently said `Not compared`/pending rather than claiming a sync or
  conflict. The narrow table stacked cleanly. A repeated `Plot Plot 001`
  browser label was corrected and rechecked; a regression test was added.
- Site User requests to both Office-only amendment and reconciliation pages
  returned 403. Browser accessibility checks covered named controls, text
  statuses, mobile focus/menu, native form validation, pagination and
  disclosure. This is not a formal accessibility certification.

## Disposable MySQL 8.4 qualification

Docker image and running server reported **MySQL 8.4.11**. The isolated
container was loopback-only (`127.0.0.1:65178`); all data was synthetic.
A first clean migration run reached a Wald trigger and was rejected by the
image's binary-log trust setting, not an application migration defect. Only
inside this disposable container, `log_bin_trust_function_creators` was
enabled, the test database was recreated, and all **20 migrations** then ran
from clean successfully. `migrate:status` showed no pending migrations.
No UI/amendment migration was introduced and no lead-time migration was merged.

Focused MySQL suite: **62 passed, 505 assertions**, covering the amendment,
Office queue, Dashboard, Site and reconciliation workspaces. The dedicated
reconciliation suite after the label correction: **18 passed, 111 assertions**.
The real-MySQL process concurrency subset: **6 passed, 330 assertions**,
including simultaneous amendments, completion races and five repeated
Office accept/propose races per ordering; stale losing decisions were rejected.
Existing focused tests cover effective date, append-only cycles, UUID guard,
authorization/site scope, source-completion precedence, queue ordering and
pagination, exact plot IDs with identical references at different sites,
CustomerCode binding, service separation and pending source truth.
Queue and reconciliation tests include bounded-query assertions and pagination;
no N+1 or unbounded history regression was detected within those fixtures.

## Final regression and release boundary

After the correction, the full SQLite suite passed: **1,844 passed, 89
skipped, 9,961 assertions**. Pint test, strict Composer validation, Composer
audit (no advisories), Vite production build, npm production-dependency audit
(zero vulnerabilities), and `git diff --check` passed. TLS verification was
not disabled. The lead-time commits `7ada4ff` and `e5fe235` are outside this
candidate. No production database, Forge deployment, or `main` push was used.

This is a release-review handoff. The review should decide whether the
existing automated coverage is sufficient for the browser-fixture limits
above; no known code defect remains and no further implementation is proposed
as part of this qualification.
