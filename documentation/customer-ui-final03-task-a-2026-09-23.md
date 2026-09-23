# CUSTOMER-UI-FINAL03 — Task A: Import Detail / Review

23 September 2026. Feature branch only; no push or deployment.

Base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.
Branch: `codex/customer-ui-final03`, clean isolated worktree.

Implemented a scoped Blade redesign of the individual import review. Whole-workbook
cards are separate from selected-site progress. Each site shows Upload, Link site,
Analyse, Review, Approve, Apply and Applied, with the current step derived from real
run state. Source questions retain exact candidates and command payloads. Plot targets,
products, service/completion facts and original evidence remain accessible in disclosures.
Approval and Apply explain the exact site and create/reuse totals; the sticky submit
uses the existing POST form, preview hash, CSRF, command UUID and required confirmation.
Native invalid Apply confirmation is scrolled into view above the sticky bar.

Read presentation now obtains the complete, integrity-checked existing stage (maximum
500 selected-site rows) through BackendStore, derives accurate included/excluded/CU4/
plot totals, and displays the first 100 evidence rows with an explicit limit. The audit
policy and scoped run resolution remain enforced. Preview expiry and existing component
pins drive presentation warnings; authoritative transition checks are unchanged. No new
business meaning, transition, source reconciliation, migration or persistent record.

Qualification before the separate Task A commit:

- New focused tests: 4 passed, 41 assertions.
- WaldPilot01 + WaldReconciliation: 60 passed, 5 skipped, 357 assertions (65 tests).
- Disposable MySQL 8.4: new focused tests 4 passed, 41 assertions.
- Build passed; dirty Pint fixed formatting; whitespace check passed.
- Browser checked uploaded, review, approve, apply, blocker, expired, stale and clarification
  fixtures at 1366x768, 768x1024, 390x844 and 320x740: no horizontal overflow in 32 states.
  Desktop and mobile screenshots inspected; exact POST payload and required Apply checkbox
  covered in HTTP/DOM tests. Browser validation kept Apply on the fixture page and focused
  the checkbox; its final top was 360px while the sticky bar began at 480px (320x740).
- The browser fixture uses fictional data and static HTML; HTTP tests exercise real actions.
- Full application/dependency qualification follows Task B as required for the combined result.

Files: OfficePilotImportController read presentation, ImportReviewPresentation, scoped
pilot-import show/detail-styles/site-review/source-sites/reanalysis/applied-result partials,
FinalImportDetailUxTest, and this report. Existing source-site, re-analysis and receipt
content was extracted without domain changes for subsequent Task B polishing.

Task B has not started at this checkpoint. Existing shared-checkout changes were preserved.
