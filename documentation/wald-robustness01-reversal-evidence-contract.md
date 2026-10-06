# Wald robustness 01 — Import reversal evidence gate

Date: 6 October 2026. Reviewed baseline: `04e48b55c6178f4e2352cd06e7cd657d0b1f081b`.

**TASK C BLOCKED BY EVIDENCE CONTRACT.** No destructive reversal action, route,
preview approval or schema has been added.

## Evidence actually retained

`ImportReview::commit` retains the original immutable receipt, workbook/canonical
hashes, exact scope, binding pins, knowledge/component pins, preview hash,
predecessor receipt and actor/time. `ProjectionAdapter::apply` records source-row
and visit IDs/versions, service IDs/epochs, per-row outcomes and aggregate counts
of created/reused plots. Source-row and visit observations retain supplied facts
and physical provenance.

The retained preview includes site/plot create/reuse candidates, old/new quantities,
completion booleans and hashes of then-current plots/products/services/requests/
negotiations/history. This is useful evidence, but a digest is not a recoverable
before-image. Preview payloads also have a shorter retention policy than commit
receipts and cannot be the only basis for a durable reversal contract.

## Missing ownership contract

- The immutable commit receipt has aggregate created/reused plot counts but no
  explicit per-entity created/reused ownership list and resulting Plot UUIDs.
- It has no durable complete prior product/service state, prior owner/run ID and
  expected post-commit version/fingerprint for every affected entity.
- Source observation history describes supplied source facts, not the whole prior
  projection state, including legacy-import ownership and unrepresented products.
- Binding creation/correction and customer/site structural approval happen before
  source Apply. They are separately audited decisions, not records owned by a site
  import receipt. Reversing an import cannot automatically undo them.
- Source completion may close requests/negotiations and write customer-visible
  history. Source-only reversal must not restore an obsolete negotiation or erase
  committed Portal history. A whole-receipt safe reversal cannot be inferred from
  the current completion boolean alone.
- Original receipts and observations are immutable. The current guarded purge path
  is a hierarchy deletion mechanism, not permission to delete or rewrite an
  individual import's original history.

Accordingly no current record is advertised as `SAFE_TO_REVERSE`. Source completion,
Portal-owned records, later/shared imports and ambiguous ownership must retain their
own explicit categories in a future preview. Deleting a reused plot is forbidden.

## Required forward design

Before implementing C, approve a forward ownership receipt with per-entity UUID/key,
source-owned field set, created/reused flag, prior value/existence/owner/version,
post-commit value/owner/version, and durable complete before/after fingerprints.
Capture it inside the original atomic import transaction, retain it with the
immutable original receipt, and keep original source/audit evidence unchanged.
Existing historical imports must remain unsupported for automated reversal unless
independently complete evidence can be proved; never backfill invented history.

Reversal must then preview the entire selected receipt, block every unsafe category,
pin the impact, lock/recalculate at final confirmation, apply only owned source
facts in one transaction and record a separate immutable reversal receipt. Test
stale previews, later imports, Portal requests/amendments, two concurrent attempts,
forced mid-operation rollback and replay on SQLite and disposable MySQL 8.4.
Partial reversal is not approved here. Reapply needs an explicit audited successor
or separately approved action; the same receipt cannot silently reapply.

## Safe Cascade Purge dependency

`SafeCascadePurgeAction` is absent from the approved `main` baseline. Its inspected
implementation is on the separate branch
`codex/customer-data-safedelete01-2026-10-06`, commit
`1ead0fd3b9c4841d6ece824f837ede9254c06ac8`.
It was read as dependency evidence only and was not merged or copied. Any future
reversal that removes an exclusively import-created hierarchy must deliberately
integrate the accepted purge engine and reuse it. Its final owned-history deletion
must also preserve the original/reversal receipt contract; do not build another
deletion engine.

The audit tests simulate the engine's cleared-root and removed-target states in an
isolated corruption fixture. They are not proof that the separate engine was
integrated or that a production purge ran. Actual engine-integration/reversal races
remain behind the ownership contract and accepted dependency gates.
