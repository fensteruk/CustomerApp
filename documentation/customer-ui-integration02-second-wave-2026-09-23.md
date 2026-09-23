# CustomerApp second-wave UI and amendment integration

Date: 23 September 2026. This is a local integration candidate, not a production release.

## Ancestry and scope

The clean `codex/customer-ui-integration02` worktree began at first-wave baseline
`f94a750d820d5a635d6e4fcac87ef3bd02079fc2`. Accepted branch tips were merged
without Git conflicts in the prescribed order: Plot/live amendments
`f512199dd1505b831737e60fade1a59594076aa6`, Office Amendments
`9a815d9193141311424b2c2c6ce726bb6b4be40e`, Wald reconciliation
`9efb337b9126c5e9a5e2a4dce94893aa8a7307b1`, and Site workspace
`16edc4b5bca5ff2f293053954da5a432c8f615d9`.

The original checkout's three unrelated Cavity Closer edits remain untouched.
`origin/main` was `04840b6b5964c59ef13a2be71eb236c2fe496110` after fetch.
No migration or lead-time branch commit was included. Nothing was pushed or deployed.

## Integration corrections

- Office queue latest-cycle selection now follows the Backend's highest-ID,
  non-withdrawn, dated amendment semantics. It shows the effective requested date
  from `CallOffRequest::effectiveRequestedDate()`. Immutable amendment events
  supply the previous requested value and early-date reason without loading
  unbounded histories into every card.
- Dashboard counts one current actionable request, reads its canonical latest
  amendment, and orders cards by amendment recency rather than request age.
- Site presentation eagerly loads `latestEffectiveAmendment` and calls the
  canonical effective-date resolver. It no longer passes a filtered,
  incompletely loaded `dateNegotiations` relation to that resolver.
- The reconciliation set-based SQL read adapter has the same latest-valid-ID
  criteria as the canonical relation and retains exact plot ID and service
  linkage. It is not a second mutable date workflow.

The Backend has no audited “Updated in RedZebra” action; the Office workspace
remains review-only. CustomerApp does not write back to RedZebra. Automatic
source-date confirmation remains deferred for Cavity Closers, Windows,
Snagging and CML pending approved field/Call Type semantics. The supplied
Amendments mockup's sync badges/action are illustrative, not implemented
truth. The supplied reconciliation mockup path was not present on this machine.

## Verification and remaining qualification

The exact 1 Oct → 3 Oct → 5 Oct scenario is automated using 2029, when all
three are weekdays. It checks one active request, retained immutable history,
the effective date, one Dashboard attention item, latest Office comparison,
and Plot display. Existing tests cover Awaiting Fenster amendment, Date Agreed,
early reason validation, stale UUID/revision rejection, role/site boundaries,
source completion, and reconciliation linkage. The focused combined suite
passed 61 tests and 503 assertions before the final minor ordering regression
test was added; final gate totals are reported in the task response.

The local MySQL-specific rerun and actual browser viewport exercise were not
completed in this worktree. Prior branch MySQL results are historical evidence,
not a substitute for a combined rerun. Docker access was denied and no
disposable MySQL instance was positively identified; no unknown database was
used. CSS/markup review found responsive grids, labelled filters, visible focus,
text status, and touch-sized controls, but does not certify the four requested
browser viewports or keyboard interaction. These are release-review checks.
