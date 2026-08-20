# Sprint 3A Domain QA Report

_20 August 2026_

## Result

**Pass — SQLite architecture, migration and security gate.** Sprint 3B may begin as the
source-projection/import contract and MySQL rehearsal only. It must not begin dashboard,
submission, negotiation, amendment, attachment, calendar, PDF, reminder or QR work.

## Findings

### Critical findings

None open.

### High findings

None open.

### Resolved during this gate

1. The original target-domain migration fabricated `legacy_mapped_date` negotiations and
   proposals, including an accepted proposal for historic Approved requests. This was
   inaccurate audit history. The migration no longer creates those rows, and corrective
   migration `2026_08_20_000006_remove_synthetic_legacy_date_proposals.php` removes only
   those original generated artefacts from databases that already applied the first
   version.
2. A zero-quantity BF product incorrectly selected the five-week lead time. BF now affects
   lead time only when its quantity is positive.
3. `active_conflict_key` was mass assignable despite being a computed, action-owned value.
   It is no longer mass assignable.

### Medium findings / required Sprint 3B controls

1. SQLite evidence does not prove MySQL DDL, nullable-unique, collation, row-lock or
   concurrent-write behaviour. Rehearse the full non-empty migration/backfill on MySQL,
   then race two submissions/proposal-sequence allocations and a completion-reversal versus
   a new request.
2. `call_off_requests` carries both a plot and a plot-service reference. Future target
   Actions must derive the service row from the authorised plot/service selection and reject
   any mismatch before persistence; a composite database constraint is not yet present.
3. `documentation/management-requirements-2026-08-20.md` is absent. The supplied
   `context-work-prompt.md` and `brief.md` section 17 were used as authority for this QA
   gate, but the canonical management record and its mandatory-agent reference must be
   restored before broader implementation work.

### Low findings

- Existing screens still show the historical Approved/Rejected workflow. This is retained
  legacy behaviour and must be replaced only in its planned target UI sprints.

## Migration safety and legacy rehearsal

The target migration is additive. It preserves legacy business rows, public UUIDs,
histories, Undo/Trash operation snapshots, notifications and rejected-resubmission links.

The disposable SQLite rehearsal created pre-3A Submitted, Approved, Rejected, Withdrawn,
trashed rejected, Undo-operation, notification and rejected-to-resubmitted records before
applying Sprint 3A. Migration rollback and reapplication preserved all 5 requests, 5
histories, 1 operation, 1 operation item, 1 notification and the lineage link. The legacy
Approved request remained `approved` with its original requested date exposed as its legacy
agreed date; it acquired no fictional proposal or acceptance event.

## Domain results

- **Four services:** enum, seed data, filters and target projections support Cavity
  Closers, Windows, Snagging and CML. Legacy three-service records remain valid.
- **Multi-service batch structure:** new request-level service/date fields can represent
  divergent combinations while legacy batch fields remain a dual-read compatibility layer.
- **Negotiation model:** ordered proposal rows, proposal actors/responses and a unique open
  negotiation key support the repeated proposal loop. Sprint 3F must lock the negotiation,
  allow only one awaiting proposal and reject stale proposal responses; this requirement is
  now explicit in the target-domain contract.
- **Date Agreed compatibility:** legacy Approved remains a historical status and is
  interpreted as legacy Date Agreed without rewriting event history.
- **Amendments:** the purpose/status/prior-agreed-date structure can reuse negotiation for
  On Hold amendments; no amendment workflow was implemented.
- **Lead time:** standard is four weeks, positive BF is five weeks, weekends are skipped,
  the six-month maximum is enforced by the service and a replaceable holiday provider is
  injectable. No speculative UK holiday dataset was added.
- **Source completion:** source job-stage, completed-date, observation and import-run
  fields exist. Completion/reversal Actions remain deferred. A reversal must reconcile an
  active conflict before restoring the original request; this is documented explicitly.
- **Conflict key:** Submitted, Approved, Awaiting Fenster, Awaiting Site User, Date Agreed
  and Amendment On Hold are conflict-active; Rejected, Withdrawn and Completed are not.
  The nullable unique key prevents two persisted active requests for one plot/service.
- **Access:** Fenster Office Staff now have global review, decision and notification scope.
  Site Users remain confined to their organisation and assigned sites; direct review,
  account-management and active-site tampering paths remain denied.
- **History and UUIDs:** legacy history remains append-only. New models generate UUIDs
  server-side; the computed conflict key is not fillable.

## Tests and commands

- `php artisan test tests/Feature/Sprint3aTargetDomainTest.php` — passed: 17 tests, 31 assertions.
- Disposable SQLite: `migrate:fresh --seed`, non-empty legacy data insertion, Sprint 3A
  rollback, reapply and corrective migration — passed.
- `php artisan test` — passed: 135 tests, 661 assertions.
- `vendor\\bin\\pint --test` — passed.
- `git diff --check` — passed.
- `npm run build` — passed outside the sandbox after its local process-spawn restriction.

## Remaining TBCs

- Approved UK bank-holiday provider and owner.
- Exact customer-facing expansion of CML.
- Amendment-reason list.
- Source/Excel update ownership, credentials and reconciliation process.
- Long-term retention policy.

## Files changed by QA

- `app/Models/CallOffRequest.php`
- `app/Models/ProjectedPlotProduct.php`
- `database/migrations/2026_08_20_000005_add_target_call_off_domain_foundation.php`
- `database/migrations/2026_08_20_000006_remove_synthetic_legacy_date_proposals.php`
- `tests/Feature/Sprint3aTargetDomainTest.php`
- `documentation/target-domain-v3.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

Safe to begin Sprint 3B
