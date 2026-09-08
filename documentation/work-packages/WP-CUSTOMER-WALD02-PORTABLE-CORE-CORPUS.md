# CUSTOMER-WALD02 — Portable Wald Core + Synthetic Corpus

Date: 4 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: Dedicated local QA passed after reader corrections on 8 September 2026;
management acceptance of the corrected baseline remains. See
`documentation/wald/customer-wald02-qa-2026-09-08.md`. No integration or deployment.

## 1. Objective and authority

Create a CustomerApp-owned, standalone portable Wald core from the approved checksum-frozen
SiteApp baseline. This phase ends at deterministic workbook observation, structural reasoning,
safe synthetic corpus and equivalence evidence. It does not add an import workflow or write to
CustomerApp domain models.

Authority:

- CustomerApp documentation baseline:
  `e84999cf66fc90aac3007842a538672b018b3e03`;
- SiteApp source manifest:
  `documentation/wald/siteapp-wald-source-baseline-2026-09-04.md` in the SiteApp repository;
- manifest content digest:
  `76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`;
- SiteApp HEAD at capture:
  `4e2955b489e386a2a5b664bd88e72d9b5793ead9`;
- manifest inventory: 162 files, revalidated 162/162 before implementation;
- DEC-039–041 and CUSTOMER-WALD01.

The manifest is the only approved SiteApp source baseline for this package. A changed file is
baseline drift and must not be substituted silently.

## 2. Governing boundary

Wald infers structure. Controlled business dictionaries define meaning.

The portable core may observe workbook/sheet/cell structure, detect candidate regions and
headers, profile value shapes, construct structural hypotheses and evidence, score confidence,
preserve ambiguity and explain why clarification is required. It must not define CustomerApp or
SiteApp business meaning.

The portable core must run without a database, authenticated user, Portal model, SiteApp model,
queue, filesystem persistence adapter, network service or external AI.

## 3. Permitted source classifications

Only the following approved manifest surfaces may be adopted:

1. The six root observation contracts under `app/Contracts/Wald`.
2. The generic reasoning contracts under `app/Contracts/Wald/Reasoning`.
3. The eleven root reader/profiler services named by the manifest entry package.
4. The generic reasoning engine under `app/Services/Wald/Reasoning`.
5. The corresponding `SAFE_SYNTHETIC` fixture generator and focused profiler/reasoning tests.
6. Optional synthetic benchmark ideas as reference only.

Every adopted file is classified as one of:

- `ADOPT_AS_IS` — behaviour and implementation are preserved, with destination-path/namespace
  relocation recorded separately;
- `ADOPT_WITH_PORTABILITY_CHANGE` — a narrowly justified change removes source-application
  coupling or supplies a generic boundary;
- `REFERENCE_ONLY` — useful evidence or fixture intent, not copied runtime code;
- `REJECT` — outside the bounded portable core.

## 4. Prohibited source classifications

Do not adopt SiteApp construction dictionaries, semantic registry/grain, Trade or WorkflowStage
models, Plot-stage normalisation, SiteApp staging, Eloquent persistence, policies, routes,
controllers, jobs, Import Studio UI, permissions, operational workflow, Effects, Project Board,
staff-test code, legacy PDF functionality, production/customer workbooks, retained uploads or
unknown-origin fixtures.

All SiteApp semantics, clarification persistence, profile persistence and integration/orchestration
surfaces are deferred. They must not be copied and cleaned up later.

## 5. Destination architecture

The CustomerApp-owned module lives under `App\Wald`:

```text
app/Wald/Contracts
app/Wald/Contracts/Reasoning
app/Wald/Services
app/Wald/Services/Reasoning
```

Tests live under `tests/Unit/Wald`, `tests/Feature/Wald` and `tests/Support`. The existing
`App\` and `Tests\` PSR-4 mappings already cover these paths. This is an in-repository module,
not a Composer package.

Source namespaces are relocated from `App\Contracts\Wald` and `App\Services\Wald` to the
owned module. Namespace-only changes are tracked as portability relocation, not semantic
divergence.

## 6. Required capabilities

- bounded XLSX and UTF-8 CSV observation;
- physical cell/sheet/source lineage;
- workbook, sheet, region, header and value profiling;
- deterministic structural evidence and hypothesis scoring;
- risk-sensitive confidence, ties, ambiguity and explanation;
- source/context constraints and deterministic ordering;
- unsupported/malformed input refusal;
- limits for source size, worksheets, cells, rows, columns, strings, archive entries and
  decompressed content;
- formulas, hidden content, merged cells and external relationships treated as evidence only;
- no formula, macro, external link or connection execution.

Observed baseline versions remain:

- reader `wald-0.2.0`;
- structure `wald.structure.v1.1`;
- reasoning `wald-0.3.0`;
- rules `wald.generic-rules.v1`;
- confidence `wald.confidence.v1`.

The QA-corrected candidate versions the reader as `wald-0.2.1` and both reader adapters as
`2`; the other identities above remain unchanged. This is an intentional safe-reader
divergence documented by WD-21–23, not a business-semantic extension.

## 7. Synthetic corpus and canonical ambiguity

Only `SAFE_SYNTHETIC` sources may travel with the fork. The baseline `WaldFixtures` generator
may be namespace-adapted. Additional CustomerApp-owned generated fixtures must remain fictional
and contain no customer/site/person data.

The corpus must cover ordinary headers, title rows, reordered/blank columns, separator rows,
multiple sheets, competing tables, numeric and mixed identifiers, date/quantity-like columns,
unknown columns, sparse/malformed input and ambiguous columns.

The canonical Abraham Wald regression contains competing `House No.` and `Sales Plot` columns.
Both must remain plausible with structured evidence; the result must preserve ambiguity and
require targeted clarification rather than selecting one arbitrarily.

## 8. Equivalence and divergence

For adopted baseline behaviour, record each result as `IDENTICAL`, `INTENTIONALLY_DIVERGED` or
`NOT_APPLICABLE`. Namespace relocation, CustomerApp test paths and additional synthetic safety
tests are expected non-semantic differences. Any behavioural difference needs a failing fixture,
reason, version decision and divergence-register entry.

The non-main CustomerApp chain `04b560f` → `a013ed1` → `1e8c22b` is comparison evidence only.
Generic mechanics and fixtures may inform a focused correction; Portal bindings, scope,
projection, preview/commit and Office UI remain downstream; the direct parallel inference path is
a supersession candidate, not a merge source.

## 9. Dependencies and resource safety

Use existing PHP and framework dependencies. Required runtime extensions are ZIP, XMLReader and
SimpleXML. Do not add or update Composer/npm packages or lockfiles. No external API, AI/ML
service, network lookup or API key is permitted.

## 10. Test and exit requirements

Focused tests must prove:

- approved baseline parity for ported profiler/reasoning cases;
- deterministic replay and evidence stability;
- ambiguity/clarification preservation;
- safe malformed/unsupported input handling and bounded resources;
- zero database queries and no model/auth/container/persistence dependency;
- no SiteApp or Portal business-semantic imports;
- no network/external-AI path.

Then run:

```text
php artisan test
vendor/bin/pint --test
composer validate --strict
composer audit
npm run build
git diff --check
```

No MySQL evidence is required unless schema or persistence is unexpectedly introduced. If that
happens, stop: it is outside CUSTOMER-WALD02.

Exit requires a complete safe synthetic corpus run, explicit equivalence/divergence record,
source-to-destination hash ledger and no application integration.

## 11. Non-goals

This package does not add semantics/dictionaries, persisted clarification/profile memory,
sessions, uploads, routes, Office navigation, source-site bindings, neutral staging, projection
commit, database schema, queue, scheduler, production configuration, deployment or SiteApp
changes. It does not start CUSTOMER-WALD03.

## 12. Rollback and removal boundary

The module is isolated and unreferenced by production application paths. Before later integration,
rollback is removal of only the new `app/Wald` code, Wald tests/support files and WALD02
documentation. No domain records, migrations, configuration, routes or customer data require
reversal. Do not delete or alter the existing CustomerApp importer or the non-main import evidence.
