# CUSTOMER-WALD02 Acceptance + WALD03 Scope

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: WALD02 corrected QA baseline accepted; WALD03 scoped only and not authorised for
implementation.

## Accepted WALD02 baseline

The immutable CustomerApp input for WALD03 is
`4aa5ffb5a00527662ddfe66673edbfb18af9f0db`, captured on
`qa/customer-wald02-2026-09-08`. Reader identity is `wald-0.2.1`; both reader adapters are
version `2`.

The original `9980354d28bfe1ca7986e10a529ab073d95d0b91` candidate is superseded. It must
not be treated as the accepted output because QA found and corrected five reader defects.

## Accepted QA evidence

- Focused Wald: 208 passed, 1,262 assertions.
- Full CustomerApp: 427 passed, 15 existing environment-gated skips, 2,450 assertions.
- Proven: deterministic/portable execution, canonical ambiguity, business/date neutrality,
  XLSX/CSV observation, malformed-input safety, resource bounds, clarification contract,
  synthetic-only corpus and no SiteApp/Portal/database/network/external-AI coupling.
- Corrected: malformed XLSX ancestry/relationship/string-table validation, P0 CSV memory
  exhaustion and empty XML structured failure.

The exact evidence remains in `documentation/wald/customer-wald02-qa-2026-09-08.md`; that
historical report and the earlier WALD02 implementation report are not rewritten by acceptance.

## Frozen core boundary

Accepted `App\Wald` remains a generic structural observation/reasoning core. Later business
meaning is composed around it. WALD03 may not encode Fenster terms in the core or regress the
five QA corrections.

## WALD03 scope

`documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md` defines a
pure CustomerApp semantic adapter and versioned controlled dictionary. It specifies call types,
completion, product roll-ups, BF fact, field/date treatment, partial export scope, typed semantic
results, clarification behaviour, non-main reuse classification and a synthetic test matrix.

It explicitly excludes persistence, uploads, profiles, site bindings, review/commit, Portal
workflow, lead-time calculation, dependencies and deployment. No WALD03 runtime code was added
by this acceptance/scoping task.

## Composer security

WALD02 inherits eight advisories affecting Filament, League CommonMark and Livewire. The
separately verified remediation commit
`5e7df0862648fd9c2ac964b31a13ad17df84fd12` is not merged here. Dependency reconciliation
and full retesting remain a separate combined-release requirement.

## Recommendation

Review the WALD03 work package as an architecture contract. If accepted, issue explicit
implementation approval for a new non-deploying feature branch based exactly on the accepted
WALD02 SHA. Do not combine WALD03 implementation with this documentation task.

## Documentation changes

The acceptance/scope work is isolated on `docs/customer-wald03-scope-2026-09-08`, based
directly on the accepted WALD02 SHA. Changed task files:

- `DECISIONS.md`;
- `brief.md`;
- `current_sprint.md`;
- `ROADMAP.md`;
- `HANDOVER.md`;
- `documentation/wald-divergence-register.md`;
- `documentation/work-packages/WP-CUSTOMER-WALD02-PORTABLE-CORE-CORPUS.md`;
- `documentation/work-packages/WP-CUSTOMER-WALD03-BUSINESS-DICTIONARY-ADAPTER.md`;
- this acceptance/scope record.

## Checks and production impact

Documentation path, contradiction, diff-scope and whitespace checks passed. All required
CustomerApp paths and the cross-repository SiteApp source-manifest path exist; the accepted Git
object is a commit and declares reader `wald-0.2.1`. Exactly nine task documentation files are
staged, with zero executable or dependency files. Application tests were not rerun because no
executable artefact changed; accepted QA evidence is reported, not recreated.

No application code, tests, dependencies, migration, database, SiteApp, GitHub Actions, `main`,
production or deployment state changed. The unrelated local Sprint 3E report edit, workbook and
`output/` directory remain unmodified and excluded.
