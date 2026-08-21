# Sprint 3B Source QA Report

_21 August 2026_

## Result

**Pass — SQLite source-projection, import and database QA gate.** Sprint 3C may begin as
the plot-centric dashboard only. It must not activate a real source transport, scheduler or
production source import.

## Findings

### P0

None open.

### P1 resolved during this gate

- A Call No. could have been rebound to a different service or a second Call No. could have
  overwritten an already bound plot/service. Both now reject as association anomalies.
- A corrected Completed Date updated the projection without an audit event. It now creates
  exactly one `completion_date_updated` source event.

### P2 resolved during this gate

- Blank Call No./plot identity and malformed product quantities are rejected before any
  ambiguous projection is written.
- Identical imports no longer count observation timestamps as source updates; first source
  bindings count as created projections.
- Restored Call Nos. resolve their existing missing-source issue instead of leaving it
  active indefinitely.
- Known source timestamps are retained when a later payload has none, and a plot retains
  the newest source timestamp across its services.
- Unexpected failures now mark the import run failed and log only safe metadata.

### P3 / deferred

- There is intentionally no source transport, parser, scheduler, credentials, UI or public
  import endpoint. These remain outside Sprint 3B.

## Audit results

- **Source identity:** Call No. is unique, normalised for whitespace, reused idempotently
  and never silently moves site, plot or service. Duplicate/blank/malformed records create
  durable reconciliation evidence.
- **Four-service projection:** PC1, CC!, CM1 and CM2 map only to Windows, Cavity Closers,
  Snagging and CML respectively. Each plot safely has four independent service rows.
- **Products:** exact codes and zero quantities are retained. Positive BF affects lead-time
  input; zero BF does not. Repeated imports do not grow product rows; missing product codes
  are set to zero under the authoritative snapshot contract.
- **Completion and reversal:** all agreed stage mappings plus Completed Date are supported.
  Completion closes open negotiations, completes active requests, clears conflicts and
  emits no Portal notification. Reversal preserves the completed request, keeps a newer
  active request authoritative, raises an unsafe-reversal issue and never creates a second
  active conflict key.
- **Missing source:** absent Call Nos. remain projected with last-known data and an
  idempotent issue. Returning records reuse the same projection and resolve the missing
  issue.
- **Error isolation:** malformed logical rows roll back independently; valid rows in the
  same snapshot continue. Unexpected infrastructure errors fail the run safely.
- **Transport independence/security:** no XLSX/CSV dependency, filesystem path, HTTP upload,
  import route, source-field form or manual completion path exists. Site Users cannot mutate
  source-authoritative fields.
- **Legacy Portal regression:** the complete Pest suite passed; legacy authentication,
  selection, call-off, review, lifecycle, notifications and resubmission tests remain green.

## Migration and performance

- Disposable SQLite fresh migration/seed, Sprint 3B rollback and reapply passed.
- A non-empty pre-3B rehearsal preserved an existing source import run, projected service
  identity and product while adding Sprint 3B tables.
- A 100-plot/four-service (400-record) snapshot imported in approximately 4.6 seconds in
  the targeted SQLite test run. This is a sanity check, not a production benchmark.

## MySQL result and limitations

No safe disposable MySQL/MariaDB target, client, container runtime or authorised
non-production database was available. No Forge or production system was contacted.

SQLite does not prove MySQL nullable-unique behaviour, foreign-key/DDL alterations,
collation/index lengths, row locks or races. Before live source activation, use a disposable
MySQL-compatible database to run fresh/seed, rollback/reapply, non-empty rehearsal and
controlled duplicate Call No., concurrent import and completion-reversal races.

MySQL absence does **not** block Sprint 3C dashboard development: Sprint 3C remains local,
read-only presentation over the tested projection contract. It **does** block production or
live source activation, and must remain a release gate.

## Tests and commands

- `php artisan test tests/Feature/Sprint3aTargetDomainTest.php` — passed: 17 tests, 31 assertions.
- `php artisan test tests/Feature/Sprint3bSourceProjectionImportTest.php` — passed: 15 tests, 71 assertions.
- `php artisan test` — passed: 150 tests, 732 assertions.
- Disposable SQLite `migrate:fresh --seed`, rollback/reapply and non-empty rehearsal — passed.
- `vendor\\bin\\pint --test` — passed.
- `git diff --check` — passed.
- `npm run build` — passed outside the sandbox after its local process-spawn restriction.

## Files changed by QA

- `app/Services/SourceProjectionImportService.php`
- `app/Services/SourceProjectionIssueService.php`
- `tests/Feature/Sprint3bSourceProjectionImportTest.php`
- `documentation/source-integration-contract.md`
- `documentation/sprint-3b-source-qa-report-2026-08-21.md`
- `current_sprint.md`
- `ROADMAP.md`
- `HANDOVER.md`

## Remaining blockers/TBCs

- Safe disposable MySQL/MariaDB environment and concurrency rehearsal.
- Real source transport, owner, credentials, scheduling and reconciliation operations.
- Approved UK bank-holiday provider, CML expansion, amendment reasons and long-term retention.

Safe to begin Sprint 3C
