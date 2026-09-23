# CUSTOMER-UI-OVERHAUL07 — Office Amendments Workspace

Date: 23 September 2026.

## Status and baseline

READY_FOR_INTEGRATION for the baseline read-only workspace, with the Backend
integration dependencies below explicitly retained.

Feature branch only: `codex/customer-ui-overhaul07`.
Exact base: `f94a750d820d5a635d6e4fcac87ef3bd02079fc2`.
Built in the separate `amendments-workspace-20260923` worktree.
Not merged, not pushed, not deployed. The original checkout and its unrelated
Cavity Closer edits were not changed.

Visual reference: the supplied `amendmentsview.png`. Implemented its summary,
filter, queue/detail, date comparison and timeline arrangement using existing
Blade and the Portal shell. Unavailable mockup sync/product states were not
invented. The computer-use skill guided local-only browser verification.

## Delivered

- GET `office.workspace.amendments.index` at
  `/portal/office/workspace/amendments`.
- Thin validating controller and Office-authorized, read-only query adapter.
- Newest-first queue: one latest amendment negotiation per request, ordered by
  `opened_at`, then ID. A later closed cycle cannot resurrect an older cycle.
- Stable links use request UUID and always resolve its latest amendment.
  Selected detail is independent of queue filters/pages; it may show a linked
  closed amendment even while the default queue is empty.
- Default queue matches the baseline Office-response model: latest open
  amendment, non-trashed request on Amendment On Hold, no pending Office
  alternative awaiting Site User response. Ordinary call-offs are excluded.
- Honest global metrics: awaiting Office, awaiting Site User and latest closed
  cycles. These are negotiation states, not RedZebra handling/sync states.
- Customer/site/plot/service context, exact prior agreed and latest requested
  dates, recorded requester/time, current request state, reason, early and
  urgent warnings. Missing prior dates say Not recorded, never a guessed value.
- Existing immutable request history, chronological by sequence/ID, including
  earlier cycles. Recorded actor snapshots take precedence over current names.
  Amendment before/after dates, proposals and agreements use their recorded
  values. Private internal reasons are not rendered.
- Status/customer/site/service filters and literal customer/site/plot search.
  Legacy batch service fallback matches existing request presentation.
- Queue: 15 rows/page; timeline: 10 events/page. Customer/site option lists are
  capped at 100 plus a selected value, with a visible search hint. Context is
  eager-loaded and proposal existence queried without loading all proposals.
- Intentional no-attention and filtered-empty states.
- Existing Review request and View site plots links; mobile back-to-queue link.
- Dashboard amendment category, rows/footer and pending-amendment summary now
  link to the workspace. The summary opens All; the category opens attention.
  Added a gated Office sidebar entry. No Dashboard layout or query redesign.

## Authorization and domain impact

Route requires auth, active Portal account and Office customer-administration
view authorization. The query independently uses the fresh
`OfficeAdministrationPolicy` check. External roles, inactive accounts and
preview accounts cannot enter. Office does not require a customer organisation.

No domain actions, policies, models, business rules, migrations, schema,
production data or lockfiles changed. No new write endpoint, acknowledgement
table, dismissal mechanism, automatic synchronization or RedZebra writeback.
The local browser fixture used only a disposable SQLite database and fictional
records. Production was not accessed for this task.

## CUSTOMER-UI-OVERHAUL06 integration seam

The baseline only records post-agreement amendment cycles. Pre-response
Awaiting Fenster amendments belong to OVERHAUL06, not this UI branch.

Adapt `OfficeAmendmentsWorkspaceQuery::latestAmendments()`, `status()` and
`stateLabel()` to the Backend's canonical latest-effective representation
after merging that branch. Preserve one working item per request and stable
request links. The required read contract is:

1. Canonical amendment/revision identity and parent request identity.
2. Previous requested/agreed date **with its meaning**, latest requested date,
   effective amendment time and recorded actor.
3. Current effective amendment state, any pending respondent, recorded
   early/urgent context, and immutable history entries for superseded changes.
4. A stable chronological tie-breaker if revisions can share a timestamp.

Replace the baseline Previous agreed label/comparison with the correct prior
requested/agreed value and label from that contract. Do not derive the prior
value from the request's mutable current fields. Wire any new immutable event
type/date snapshot through the existing timeline. Requalify Dashboard counts
and pre-response/multi-revision scenarios against the canonical Backend model.

### Manual RedZebra handling dependency

No suitable existing handled/acknowledged action or state was found. Earlier
date acknowledgement is only a lead-time decision, not a RedZebra update.
The workspace explains this and exposes no fake Mark updated button.

The Backend must supply an authorized audited action accepting the **exact
effective amendment revision identity** being handled. Its canonical record
must capture that identity, handling actor and timestamp; distinguish the
handled revision from a newer unhandled amendment; and reject stale handling.
Reauthorization, locking/transaction ownership and idempotency belong in that
domain action, not in this query/UI service. These are required semantics,
not newly introduced schema or claimed existing field names.

Only after that contract exists should this UI expose Mark updated in
RedZebra and handled filters/metrics. Later master-import reconciliation needs
its own verified Backend/Wald evidence; negotiation closure must never be
labelled Synced or RedZebra updated. Existing closed history remains available
under Closed/All, with no new silent dismissal.

## Verification

Focused command:
`php artisan test tests/Feature/OfficeAmendmentsWorkspaceTest.php tests/Feature/OfficeDashboardPresentationTest.php`

Result: 18 passed, 210 assertions.

Full regression: `php artisan test` passed: 1,884 tests total, 1,799 passed,
85 skipped, 9,666 assertions, 219.489 seconds. The desktop crash interrupted
an earlier run; that incomplete run is not counted.

Other completed gates:

- `vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: no security vulnerability advisories.
- `npm run build`: passed.
- `npm audit --omit=dev`: 0 vulnerabilities.
- `git diff --check` and `git diff --cached --check`: passed.

The initial `npm ci` reported four development dependency findings (two
moderate, two high); the required production-only audit is clean. No package
updates or lockfile edits were made. MySQL-specific qualification was not run:
this branch adds portable read queries only, no writes/locking/constraints.
Skipped tests are not represented as passing database-specific evidence.

### Browser evidence

Local synthetic Office session only. Verified:

| Viewport | Result |
| --- | --- |
| 1366 × 768 | Desktop summary/filter/master-detail layout; document width 1351 |
| 768 × 1024 | Stacked queue/detail, readable timeline; document width 753 |
| 390 × 844 | Phone comparison, labelled filters, warnings; document width 375 |
| 320 × 740 | Full-width narrow filters, wrapped context/timeline; document width 305 |

No horizontal document overflow at any required size. The 15px difference is
the browser scrollbar. Inspected screenshots and accessible DOM headings,
labels and timeline. Keyboard Tab showed a solid focus outline; Enter applied
service/search filters and returned the expected one amendment. No-result
filter, clear filters, queue-to-detail and back-to-queue links were exercised.
Anchor scroll offset keeps the detail heading below the fixed header.
No browser warning/error entries were recorded. Preview tab closed and
viewport override reset after QA.

## Files changed

- `app/Http/Controllers/OfficeAmendmentsWorkspaceController.php`
- `app/Services/OfficeAmendmentsWorkspaceQuery.php`
- `resources/views/office/amendments/index.blade.php`
- `resources/views/office/amendments/styles.blade.php`
- `resources/views/layouts/partials/portal-sidebar.blade.php`
- `resources/views/office/dashboard/index.blade.php`
- `routes/office-workspace.php`
- `tests/Feature/OfficeAmendmentsWorkspaceTest.php`
- `tests/Feature/OfficeDashboardPresentationTest.php`
- `documentation/customer-ui-overhaul07-office-amendments-2026-09-23.md`

## Integration recommendation

Integrate this read-only workspace with OVERHAUL06 and its canonical
latest-effective amendment contract. Re-run focused/full gates after resolving
the small shared route/sidebar/Dashboard changes. RedZebra handling remains an
explicit Backend dependency; do not claim the complete manual-handling/import
reconciliation workflow is delivered by this branch.

Push: NO. Deployment: NO.
