# CUSTOMER-WALD03 — Business Dictionary + Semantic Adapter

Date: 8 September 2026. Owner: CustomerApp Wald Architecture / Integration.
Status: Explicitly approved for implementation on 8 September 2026. Feature branch starts
exactly at `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`; documentation carried forward from
reviewed scope commit `76ed196cd19f2d207f2a5d50006041f1b3d8b814` as metadata only.
Implemented on `feature/customer-wald03-business-dictionary` at candidate `1fee57d`;
dedicated QA and accepted output SHA pending. Evidence:
`documentation/wald/customer-wald03-business-dictionary-2026-09-08.md`.

Implementation clarification: absent/blank quantity is zero within this supplied input only;
it does not authorize projection zeroing. Quantities are non-negative finite decimals with up
to three fractional digits (consistent with existing source quantity precision), bounded at
999,999,999.999 per value and total. Plain numeric strings are supported; exponents, grouping,
booleans, negative values and excess precision are invalid. Fixed-point arithmetic avoids
float roll-up drift. Labels are part of dictionary identity and require a version change.

## 1. Accepted input baseline and authority

CUSTOMER-WALD03 may start only from the accepted corrected CUSTOMER-WALD02 baseline:

- Git SHA: `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`;
- branch at acceptance: `qa/customer-wald02-2026-09-08`;
- reader: `wald-0.2.1`;
- XLSX and CSV adapter versions: `2`;
- structure: `wald.structure.v1.1`;
- reasoning: `wald-0.3.0`;
- rules: `wald.generic-rules.v1`;
- confidence: `wald.confidence.v1`.

The original `9980354d28bfe1ca7986e10a529ab073d95d0b91` candidate is superseded and must
not be used as the WALD03 input. The five QA reader corrections and their regressions are part
of the frozen core contract.

Authority is DEC-039, DEC-040, DEC-042, DEC-043, DEC-044, `brief.md`,
`documentation/siteapp-import-data-dictionary.md`, the WALD02 work package and
`documentation/wald/customer-wald02-qa-2026-09-08.md`.

## 2. Objective

Add a CustomerApp-owned, pure business-dictionary adapter around the generic Wald core. It
will take resolved Wald structural observations/candidates and interpret values using only the
approved CustomerApp source dictionary.

```text
Workbook
  → frozen generic Wald observation/reasoning
  → generic structural candidates and evidence
  → CustomerApp semantic adapter
  → confirmed / unknown / ambiguous / invalid / ignored semantic results
```

This phase produces typed semantic facts and clarification requirements. It does not stage,
persist, review or commit an import.

## 3. Core boundary

`App\Wald` remains generic. WALD03 must compose around its public contracts and must not add
CustomerApp source codes, product meanings, services, dates, workflow rules or dictionary
lookups to the core.

Proposed ownership boundary:

```text
App\Wald                              frozen generic core
App\SourceImport\Semantics\Contracts dictionary/adapter interfaces
App\SourceImport\Semantics\Data      immutable semantic results
App\SourceImport\Semantics\Enums     semantic classifications
App\SourceImport\Semantics\Dictionary approved CustomerApp definitions
App\SourceImport\Semantics            adapter/composition service
```

Any required `App\Wald` modification must stop the package and receive separate architecture
approval. A generic defect discovered by a synthetic fixture is handled as its own core change,
with versioning, QA and divergence evidence; it is never disguised as dictionary work.

## 4. Allowed dependencies

WALD03 may depend on:

- accepted `App\Wald` observation/reasoning contracts at the frozen SHA;
- PHP language/standard-library types;
- CustomerApp-owned immutable DTOs, enums and interfaces created inside the semantic boundary;
- the controlled definitions in `documentation/siteapp-import-data-dictionary.md`.

It must remain constructible in a plain PHP test without Laravel bootstrapping. Do not depend on
Eloquent, database connections, facades, `config()`, `app()`, authentication, Portal models or
`CallOffServiceType`. Emit stable semantic identifiers; later integration maps them to Portal
domain types at its authorised boundary.

No new Composer/npm dependency, external API, AI/LLM, embedding, network call, queue or file
persistence is permitted.

## 5. Business dictionary interface

Introduce a repository-consistent equivalent of `SourceBusinessDictionary`. It must expose an
immutable `DictionaryIdentity` and explicit lookup operations for:

- call types;
- completion flags;
- product codes;
- field/header treatments;
- safe default export scope.

Lookups accept raw values and return typed dictionary results. Normalisation is limited to safe
lookup preparation such as trimming and case-folding confirmed codes/Yes-No variants. It never
replaces the raw value and never performs fuzzy business mapping.

The semantic adapter consumes a structurally resolved Wald candidate plus its source evidence.
It must refuse dependent semantic selection while Wald still reports competing structural
candidates.

## 6. Semantic result contract

Use orthogonal state instead of forcing several meanings into one enum:

### Semantic classification

- `CONFIRMED` — exact approved dictionary meaning;
- `UNKNOWN` — no approved definition;
- `AMBIGUOUS` — structural or semantic alternatives remain unresolved;
- `INVALID` — explicitly known invalid value, such as literal `CC!`;
- `IGNORED` — recognised field/value deliberately excluded from semantic output.

### Resolution requirement

- `RESOLVED`;
- `REQUIRES_CONFIRMATION`;
- `BLOCKED`.

An immutable semantic result should contain:

- concept/role being interpreted;
- exact raw source value and source coordinate/provenance;
- safe normalized lookup value, when one was used;
- semantic classification and resolution requirement;
- matched dictionary entry, or `null`;
- optional suggestion with a reason code, never an applied replacement;
- structured reason/evidence;
- inherited Wald evidence and confidence where applicable, explicitly not probability;
- dictionary identity/version/fingerprint;
- accepted Wald reader/core identity;
- readiness of this result for later neutral staging.

`INVALID + REQUIRES_CONFIRMATION` and `UNKNOWN + REQUIRES_CONFIRMATION` are valid combined
states. `AMBIGUOUS + BLOCKED` is required when the structural role itself is unresolved.
Raw evidence is never discarded.

## 7. Call-type dictionary

| Source value | Meaning | Semantic service identifier | Result |
|---|---|---|---|
| `PC1` | Plot Install | `windows` | `CONFIRMED / RESOLVED` |
| `CC1` | Cavity Closer 1 | `cavity_closers` | `CONFIRMED / RESOLVED` |
| `CM1` | Revisit 1 | `cml` | `CONFIRMED / RESOLVED`; CML-related revisit |
| `CM2` | Revisit 2 | `cml` | `CONFIRMED / RESOLVED`; CML-related revisit |
| `CML` | CML Call Off | `cml` | `CONFIRMED / RESOLVED` |
| `CC!` | Invalid/likely typo evidence | none | `INVALID / REQUIRES_CONFIRMATION`; may suggest `CC1` |

Any other non-blank code, including `ZZ9`, is `UNKNOWN / REQUIRES_CONFIRMATION`. Do not fuzzy
map it. No Snagging source code is defined or inferred. CM1 and CM2 do not create additional
Portal services.

## 8. Completion dictionary and composition rule

Case-insensitive sensible `Yes`/`No` variants are confirmed Boolean source facts for the
specific source call-off part. Preserve the raw value. Blank or malformed values are unknown;
none creates a date.

Completion is independent evidence but cannot establish service identity. A row with `ZZ9` and
`complete = Yes` may expose a confirmed raw completion flag, but the row remains blocked from
service-level staging because the call type is unknown. WALD03 must not execute CustomerApp's
source-completion precedence transition.

## 9. Product dictionary

| Code | Confirmed meaning | Group/result |
|---|---|---|
| `VS` | Vertical Slider | `WINDOWS / CONFIRMED` |
| `TT` | Tilt and Turn | `WINDOWS / CONFIRMED` |
| `BAY` | Bay Window | `WINDOWS / CONFIRMED` |
| `ALI` | Aluminium Windows | `WINDOWS / CONFIRMED` |
| `AOV` | Automatic Opening Vent Window | `WINDOWS / CONFIRMED` |
| `FI` | Fire Window | `WINDOWS / CONFIRMED` |
| `PSU` | PVC Door Utility | `DOORS / CONFIRMED` |
| `PSG` | PVC Door Garage | `DOORS / CONFIRMED` |
| `CDF` | Composite Door Front | `DOORS / CONFIRMED` |
| `CDU` | Composite Door Utility | `DOORS / CONFIRMED` |
| `CDG` | Composite Door Garage | `DOORS / CONFIRMED` |
| `PSP` | PVC Sliding Patio | `DOORS / CONFIRMED` |
| `BF` | Bifold | `DOORS / CONFIRMED`; separate BF fact also retained |

Total Windows is exactly `VS + TT + BAY + ALI + AOV + FI`. Total Doors is exactly
`PSU + PSG + CDF + CDU + CDG + PSP + BF`.

`CAS`, `FLU`, `PFD`, `GLS`, `WP` and `MISC` are `IGNORED / RESOLVED`: retain their
raw/private evidence but exclude them from customer totals. An unknown product code is
`UNKNOWN / REQUIRES_CONFIRMATION` and must not be silently added to a total.

Roll-ups may be calculated only from exact confirmed product codes with validated finite,
non-negative quantities inside the interpreted input. Absence is not zero outside confirmed
coverage.

## 10. BF semantic boundary

An exact `BF` code with a numeric quantity greater than zero emits a `bf_present = true`
semantic fact. Exact zero emits `false` for the represented value. A lookalike code, aggregate
Doors value, label similarity or absent column does not establish BF.

WALD03 does not calculate four/five-week dates, weekdays, holidays or eligibility. Lead-time
policy remains downstream CustomerApp workflow logic.

## 11. Field dictionary

| Field | WALD03 treatment |
|---|---|
| `Items Ordered Status` | `IGNORED`; must not drive any semantic fact. |
| `Site Value` | `IGNORED`; exclude from customer output/model. |
| `Plot To Be Installed` | Confirmed PC1 operational arrival/installation-date role only. |
| `Site Name` | Transitional site clue only; no match, creation or authorisation. |
| Permanent source Site ID/reference | Preferred durable source-identity role when supplied. |

`Plot To Be Installed` must never become Requested Date, Date Agreed, alternative proposal or
completion date. WALD03 preserves date-shaped/raw evidence but does not normalize it into Portal
workflow state.

## 12. Export scope

`PARTIAL_FILTERED_EXPORT` is the unconditional default. Workbook filename, size, rows, sheets,
sites or familiar structure cannot promote scope.

The semantic contract may carry a separately supplied explicit scope assertion, but WALD03 does
not authenticate or approve it. A stronger `SITE_COMPLETE_SNAPSHOT` or
`GLOBAL_COMPLETE_SNAPSHOT` assertion remains `REQUIRES_CONFIRMATION` until a later authorised
review boundary validates its owner, source revision and coverage. No absence/deletion meaning
is applied in this phase.

## 13. Clarification behaviour

- Structural ambiguity is resolved before dependent business interpretation.
- If `House No.` and `Sales Plot` remain competing Plot candidates, return
  `AMBIGUOUS / BLOCKED` with both candidates/evidence; do not choose one.
- Literal `CC!` returns the raw invalid value, possible `CC1` suggestion, reason
  `LIKELY_TYPO`, and a required confirmation.
- Unknown required call/product values return `UNKNOWN / REQUIRES_CONFIRMATION`.
- Confirmation is represented as a typed request/result only. WALD03 has no UI, answer store,
  learned profile, activation, authorisation or persistence.
- A clarification answer changes semantic interpretation only; it is never permission to stage
  or commit Portal records.

## 14. Dictionary versioning

Initial identity: `customerapp.source-dictionary.v1`.

The implementation must derive a stable SHA-256 fingerprint from canonical ordered dictionary
definitions and include both identifier and fingerprint in every semantic result. It also
records Wald reader `wald-0.2.1` and the accepted core SHA.

Any added/removed code, changed meaning, roll-up, treatment, suggestion or safe-normalisation
rule requires an explicit dictionary version change and regression review. Old results retain
their original identity/snapshot; later persistence work must never silently reinterpret them
under a new dictionary.

Do not reuse the non-main import line's numeric `semantic_version = 3` as if it identified this
new contract. That version belongs to a different config-driven pipeline.

## 15. Existing non-main CustomerApp import classification

The chain `04b560f` → `a013ed1` → `1e8c22b` remains read-only evidence and must not be merged.

| Material | Classification | WALD03 treatment |
|---|---|---|
| Confirmed definitions in `config/siteapp_import.php` | `REIMPLEMENT` | Re-express from the authoritative data dictionary in a pure immutable dictionary; do not copy Laravel config coupling or numeric version identity. |
| `SiteAppImportDataDictionary` | `REIMPLEMENT` | Retain useful lookup/roll-up intent, but replace `config()`, collections and Portal enum/model coupling with pure typed contracts. |
| `SourceCallTypeMapper` | `SUPERSEDED` | Semantic adapter replaces direct Portal-enum mapping; reject its historical Job Stage completion rules. |
| Alias ideas in `config/manual_source_import.php` | `REIMPLEMENT` selectively | Use only approved field terminology as dictionary hints after Wald structural evidence; do not restore a fixed workbook contract. |
| `ManualSourceWorkbookContract` and environment-defined worksheet/header route | `SUPERSEDED` | Wald owns structural inference; no fixed direct-reader architecture in WALD03. |
| `SpreadsheetStructureInterpreter`, mapping/profile services and direct XLSX pipeline | `REFERENCE_ONLY` | Regression/terminology evidence only; later parity decision remains for WALD04/05. |
| `SiteAppImportSemanticCorrectionTest` dictionary scenarios | `REUSE` | Recreate the safe semantic cases as pure WALD03 tests without DB, service container or Portal workflow assertions. |
| `SourceImportScope` concepts and partial-default tests | `REIMPLEMENT` | Pure semantic scope result; authorisation/confirmation remains downstream. |
| Source records, site binding, previews, projection importer and Office UI | `REFERENCE_ONLY` | WALD04/05 integration evidence; prohibited from WALD03 runtime. |

No existing non-main source file is approved for wholesale copy, cherry-pick or deletion.

## 16. Synthetic test matrix

All tests use generated/literal fictional values only and run without a database.

| Area | Required cases |
|---|---|
| Call types | PC1, CC1, CM1, CM2, CML exact/case/trim lookup; CM1/CM2 remain CML revisits; no Snagging code. |
| Invalid/unknown | CC! raw preservation, likely-typo suggestion and confirmation; ZZ9 unknown; no fuzzy mapping. |
| Completion | sensible Yes/No variants; blank/malformed; no date creation; Yes plus unknown call type remains blocked. |
| Windows | VS, TT, BAY, ALI, AOV, FI meanings and exact roll-up. |
| Doors | PSU, PSG, CDF, CDU, CDG, PSP, BF meanings and exact roll-up. |
| Excluded/unknown products | CAS, FLU, PFD, GLS, WP, MISC ignored; unknown product blocks; raw evidence retained. |
| BF | positive, zero, negative/invalid, absent and lookalike; fact only, no lead-time calculation. |
| Fields/dates | operational date role; Items Ordered Status/Site Value ignored; no Requested/Agreed/proposal/completion date. |
| Structural ambiguity | House No./Sales Plot remains blocked with both Wald candidates/evidence. |
| Scope | absent assertion defaults partial; workbook cannot promote; stronger explicit assertion requires confirmation. |
| Result contract | classification/resolution combinations, provenance, raw/normalized values, evidence and readiness. |
| Versioning | exact version/fingerprint/core identity; definition mutation changes fingerprint; old result identity stable. |
| Determinism | repeated in-process and fresh-process results serialize identically. |
| Boundary | no models, DB, auth, container, config, queue, network, external AI or `App\Wald` business-code changes. |

Run focused tests first, then the full CustomerApp suite, Pint, Composer validation/audit,
Vite build and diff checks. Composer advisories are reported accurately, not remediated inside
WALD03 without separate authority.

## 17. Explicit non-goals

WALD03 does not implement:

- any modification to the accepted Wald reader/reasoning core for business meaning;
- database schema, Eloquent model, profile/dictionary/answer persistence or learned knowledge;
- import run, source registration, upload, queue, scheduler or filesystem storage;
- site binding, tenancy, authentication, permissions or Office review UI;
- neutral staging, projection writes, reconciliation, transaction or commit;
- Date Agreed, requested/proposed dates, negotiation, amendments or notifications;
- source-completion precedence transition or reversal behaviour;
- lead-time/holiday calculation;
- stronger-snapshot authorisation or deletion/zeroing from absence;
- SiteApp access/write-back, external AI/network calls, package changes or deployment;
- production/customer workbooks or non-synthetic fixtures.

## 18. Delivery and rollback boundary

The explicit implementation approval was executed on a non-deploying feature branch from the
exact accepted WALD02 SHA, with separate contracts/dictionary, adapter, tests and evidence commits.

Because this phase is pure and unreferenced by production paths, rollback before integration is
removal of only the new semantic module, tests and WALD03 documentation. There must be no data,
migration, route, configuration or deployment rollback.

## 19. WALD04 entry criteria

Before CUSTOMER-WALD04 may begin:

1. WALD03 has explicit implementation approval, is implemented from exact
   `4aa5ffb5a00527662ddfe66673edbfb18af9f0db`, and passes dedicated QA.
2. Its accepted Git SHA, dictionary identity/fingerprint and Wald identity are frozen.
3. Every controlled call type, completion value, product, BF case, field and scope rule passes
   the synthetic matrix, including negative/ambiguity/determinism cases.
4. `App\Wald` remains generic; any core change has separate version/divergence/QA evidence.
5. The semantic result and clarification-request contracts are stable and preserve raw evidence.
6. No database, Portal workflow, SiteApp or external-service coupling is present.
7. Product approves ownership, tenancy/scope, activation, review and mutation permissions for
   learned mappings/profiles/clarification answers.
8. Product approves retention and audit requirements for dictionary snapshots, answers and
   profile history.
9. The non-main parallel profile route has a recorded adapt/supersede/defer decision; it is not
   silently merged or deleted.
10. The eight inherited Composer advisories are carried as a separate combined-release gate.

WALD03 completion does not itself authorise WALD04, import integration, `main` or production.
