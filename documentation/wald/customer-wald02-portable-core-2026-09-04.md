# CUSTOMER-WALD02 Portable Core + Corpus Report

Date: 4 September 2026. Owner: CustomerApp Wald Architecture / Integration.

## Overall Result

CUSTOMER-WALD02 is implemented on a non-deploying CustomerApp feature branch and is ready
for dedicated QA. The result is a standalone, deterministic workbook observation and generic
reasoning module plus a safe synthetic corpus. It is not wired to routes, uploads, queues,
databases, Portal models, business dictionaries or production.

## Branch/SHAs

- Branch: `feature/customer-wald02-portable-core`.
- Documentation baseline: `e84999cf66fc90aac3007842a538672b018b3e03`.
- Work-package commit: `243beb8`.
- Portable-core commit: `d1c130a`.
- Synthetic-corpus and boundary-test commit: `5ddaa26`.
- Final evidence/documentation commit: recorded by the branch history containing this report.
- Nothing was merged to `main`, pushed, tagged or deployed.

## Approved Source Baseline

- SiteApp manifest: `C:\Users\JoshO\Documents\SiteApp\documentation\wald\siteapp-wald-source-baseline-2026-09-04.md`.
- Approved manifest-content digest: `76bc079e1e48c79233242734d2597a3c8316408d4bc1d7e388238683131a002a`.
- Manifest file SHA-256: `d47cc0f4f11c217b4dce7497e7dad74a874510d4d77de273a772bc377106c392`.
- SiteApp HEAD at capture: `4e2955b489e386a2a5b664bd88e72d9b5793ead9`.
- Inventory: 162 checksum-recorded files.

The per-file manifest is controlling because the approved Wald source was not contained in
the recorded SiteApp Git commit.

## Source Integrity

Before adoption, all 162 manifest entries were rehashed at their approved SiteApp paths.
Result: `162/162` present and matching, zero mismatches. Rebuilding the sorted LF-joined
`path|bytes|sha256` inventory produced the approved digest exactly. SiteApp was read only;
no source file or manifest was changed.

The 57 adopted baseline files are itemised below. For all entries, adoption classification is
`ADOPT_AS_IS`, the only intentional edit is the namespace/path relocation described under
Portability Changes, and equivalence is `IDENTICAL` after reversing that relocation.

+| Approved SiteApp source | Original SHA-256 | CustomerApp destination | Class | Equivalence |
|---|---|---|---|---|
| `app/Contracts/Wald/CandidateRegion.php` | `84876cdab05070efd65faf9fbdb1ab3c81ee17a29c7b211f73633d89955ade1b` | `app/Wald/Contracts/CandidateRegion.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/CellObservation.php` | `d8282753fee43f95fba3b06740a17a003a24565876e2d5642390a5ba9b952453` | `app/Wald/Contracts/CellObservation.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/Ambiguity.php` | `4b89a77212bb73a3ba1f2e79294b60e9f20885b2288a418c0199d13164df2a12` | `app/Wald/Contracts/Reasoning/Ambiguity.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/ConstraintFinding.php` | `780d47ce019f0365f9e3a0703d11ccaea7cab01efd210ed151fbdbae87c95821` | `app/Wald/Contracts/Reasoning/ConstraintFinding.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/Evidence.php` | `db45dbeb30075e54d3a7c1a7633f097a3d706cc406a2de7a652ec95dfd75525c` | `app/Wald/Contracts/Reasoning/Evidence.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/EvidenceDirection.php` | `0ba256b080d55e28dd6841a709fedec61effa91e5e3a42f9929f03111527046c` | `app/Wald/Contracts/Reasoning/EvidenceDirection.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/EvidenceProvenanceVerifier.php` | `e5cc671fc474ce5bcc89f99e5d0a85df8fc55956b94bfa5bdfff125b048ade12` | `app/Wald/Contracts/Reasoning/EvidenceProvenanceVerifier.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/EvidenceTrust.php` | `394f824f8056ef8461fe9d930308739c2bf5d96f182d50571aa384c2f1b46217` | `app/Wald/Contracts/Reasoning/EvidenceTrust.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/Hypothesis.php` | `6b0208a5dfa286c6fe45832e41a06fef20e53954468dc75550d45ac7fff289b4` | `app/Wald/Contracts/Reasoning/Hypothesis.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/HypothesisDefinition.php` | `c2c40eb0ee2556a10b6f932406c55ef128656f9a749b185929ca38b76a20947d` | `app/Wald/Contracts/Reasoning/HypothesisDefinition.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/InferenceRule.php` | `b9573a567a33afcbb141614eed505bc50ea6852099b737761625829d6e34b86c` | `app/Wald/Contracts/Reasoning/InferenceRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/ReasoningConstraint.php` | `18d62dec6f8208a388dcd06f4fcb48d27c4bec7d0afca7f001b32deff4036e2c` | `app/Wald/Contracts/Reasoning/ReasoningConstraint.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/ReasoningResult.php` | `f01b21ed4ea8c317da99e47de35453a348df513dc2d105999dab660a8195586d` | `app/Wald/Contracts/Reasoning/ReasoningResult.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RiskClass.php` | `c839d7898ea7e2b65868f4f6a7d734e1486aa9733a78b36056008db8623a7a73` | `app/Wald/Contracts/Reasoning/RiskClass.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RuleApplicability.php` | `f981ffb2104fe8e282c587eb416006c45f1320ef51e2d11f31a058f7da21007c` | `app/Wald/Contracts/Reasoning/RuleApplicability.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RuleContext.php` | `7881e3cf67632d6faa7614e36e2abc238b703e3910561bf2128dd13a1bb3a51b` | `app/Wald/Contracts/Reasoning/RuleContext.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RuleContextProvider.php` | `c3645e423829554ebbefc9290a21540106711274b8b2f543015d13da3563d54a` | `app/Wald/Contracts/Reasoning/RuleContextProvider.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RuleDefinition.php` | `9b4fe5a2c6d9b98181d3fa096e3d4b708841ce34e1471b5f267df16b1258d4de` | `app/Wald/Contracts/Reasoning/RuleDefinition.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/Reasoning/RulePhase.php` | `acc0f0f320a297f8c113786e8c75db5a130ae68f383f67f477f48ea1c9d69561` | `app/Wald/Contracts/Reasoning/RulePhase.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/SheetObservation.php` | `f3589e7469d644553da1ddf1c79d1fe917e1b0c7edfb7a307d9b317b8c511a6d` | `app/Wald/Contracts/SheetObservation.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/SourceRange.php` | `fd95d36da573c494a2c216c488f3377c35b2e410f467b76ef87adf94c681a651` | `app/Wald/Contracts/SourceRange.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/WorkbookProfile.php` | `9abab9fc34ec0c3688f1971ad455c2db33066853647acc593014ae534adf5dab` | `app/Wald/Contracts/WorkbookProfile.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Contracts/Wald/WorkbookSource.php` | `9eff30eb1b0a5fcf83d50e0003f8860b3422d7ba384d1ed2e181570bf91b3a00` | `app/Wald/Contracts/WorkbookSource.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/AnalysisBudget.php` | `9bade7ec404a77ec98371177774de1ce72478da8ae7b71bd9789ca89971e08a6` | `app/Wald/Services/AnalysisBudget.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/AnalysisProblem.php` | `f4ad0457290d77d4e1fa7aee42b5f791091c54a47de12d0265481f7036a0d438` | `app/Wald/Services/AnalysisProblem.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/CsvWorkbookSource.php` | `d92326b23c1b1e2977a3774cd0f115ad9c5907bd3448e1bfbbeefa6432bcaf8c` | `app/Wald/Services/CsvWorkbookSource.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/HeaderDetector.php` | `80d9a3601a337e5a66b10124c3bb9fb5c60e876d1c4bb2ac72b82170308cc0f8` | `app/Wald/Services/HeaderDetector.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ConfidencePolicy.php` | `a1ea267cb5268fb80aa4934f627aad15c3f6ef6e6860830948a4afb9a1b23583` | `app/Wald/Services/Reasoning/ConfidencePolicy.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ConfidenceResolver.php` | `6b8662c9e7722ce8db0d385c0ba3cfa51a8a331a35d68f23278786a4f6b217cd` | `app/Wald/Services/Reasoning/ConfidenceResolver.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ConstraintEngine.php` | `f7259afa05aa80a0fa48eb02674ca1cf016098dd83f6e244071987cc8d68125f` | `app/Wald/Services/Reasoning/ConstraintEngine.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/EvidenceScorer.php` | `d10cc6293bafb057beab6da5bdfe3bf23d61ba05c4ce55ef7618db4efb9eee8f` | `app/Wald/Services/Reasoning/EvidenceScorer.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ExclusiveRoleConstraint.php` | `57139427da751839420054181a48da6717d8644d88f60b2b7b3cae27bea296d6` | `app/Wald/Services/Reasoning/ExclusiveRoleConstraint.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ExplanationBuilder.php` | `a00781cfadcd9d21694a070238cb4b000c70629fe6d65dc5505be12addda3a61` | `app/Wald/Services/Reasoning/ExplanationBuilder.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/GenericRuleSettings.php` | `59bf05f18c5cf8ed4d885e05aefee720a80da78b5e5f4d208b0b5a7bfbd9f311` | `app/Wald/Services/Reasoning/GenericRuleSettings.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/HypothesisCatalog.php` | `9a3b0af4223cc2f221fedd5df156872a665937f05e4c54f82070e077a81af199` | `app/Wald/Services/Reasoning/HypothesisCatalog.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ProfileContextFactory.php` | `a4419a14d2ad33e3e178864bbd7db592dd6c2e637ffb796a4e94d1c5eef83a71` | `app/Wald/Services/Reasoning/ProfileContextFactory.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ReasoningEngine.php` | `8e192ff852c48f3d3045c4f0df30a00d29bbf7acc482e3dbebf9ac3f6413ad2f` | `app/Wald/Services/Reasoning/ReasoningEngine.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/ReasoningProblem.php` | `d6ed0bd49a8043b74e7dcf6a4bc67fc211501af86d9456dcce14845b0ca9200c` | `app/Wald/Services/Reasoning/ReasoningProblem.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/RuleRegistry.php` | `f0a2519685b29460dea579a8fb1c5bcf31fb643db8d728e2b66b85626acf0247` | `app/Wald/Services/Reasoning/RuleRegistry.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/Rules/ContextEvidenceRule.php` | `6f7c80252ffa88c6660970ffef0ad1a9bc35590abe2d528eba21517c1c767061` | `app/Wald/Services/Reasoning/Rules/ContextEvidenceRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/Rules/HeaderTokenRule.php` | `c9b8511fffea6fa5505852b7be3b569fb14ac116f9e6654167a68ec34ccb63bb` | `app/Wald/Services/Reasoning/Rules/HeaderTokenRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/Rules/ProfileRule.php` | `8fdf4baacfccd64bdfbd8d36bdf56514ba1e724c44a5f97e5d8dfc1760fdf3c8` | `app/Wald/Services/Reasoning/Rules/ProfileRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/Rules/ShapeContradictionRule.php` | `6a9310f040bc760db625e6616372e1c040134e5090f36d65ad6f80e7c9dfeeb2` | `app/Wald/Services/Reasoning/Rules/ShapeContradictionRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/Rules/ShapeEvidenceRule.php` | `06f72ae9e7b5ffbac65328f8176a61b8fa319ff9ddb83edc5104679795a6f060` | `app/Wald/Services/Reasoning/Rules/ShapeEvidenceRule.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/Reasoning/SourceContextConstraint.php` | `f5e03a97c25741546b41bd3170e73becf63bea6ffcac67728ac527b370a43816` | `app/Wald/Services/Reasoning/SourceContextConstraint.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/RegionDetector.php` | `b7dc51dcb61751b54ad8fbb1e666b09f8db4315c26e1c66f96053df30b2be76e` | `app/Wald/Services/RegionDetector.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/SheetProfiler.php` | `10dccd89a5c562ae48ca6e8612e189550920b2f39b954e5be0021d34fcb26859` | `app/Wald/Services/SheetProfiler.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/StructuralEvidence.php` | `d4e6b28d66c34b5f99517b9a3b8ad0e14a8ce6fe35c9addcee78fd534fb0003e` | `app/Wald/Services/StructuralEvidence.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/ValueProfiler.php` | `2cde4916f754d8b7c8a53c9dcce6e4f8bb3f024f02a9e47fa7b4d98cac36e350` | `app/Wald/Services/ValueProfiler.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/WorkbookProfiler.php` | `3576454cc21939e4d506b9ba3e6833bb78a6833a5ab94c07126c57a3cfefa048` | `app/Wald/Services/WorkbookProfiler.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/WorkbookSourceFactory.php` | `fc381a067a9e0166bcccc871348b9ebc87846025c728f841516a55d96a902853` | `app/Wald/Services/WorkbookSourceFactory.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `app/Services/Wald/XlsxWorkbookSource.php` | `a0fc689234d9c1f04f957338413e9027fcd2e1e3858e336b832593a333d57b79` | `app/Wald/Services/XlsxWorkbookSource.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `tests/Feature/Wald/WaldIntegrationBoundaryTest.php` | `63f103e1c767331c6419dd8858b56a3996dc020d3350717a4f5ab41700fa82e6` | `tests/Feature/Wald/WaldIntegrationBoundaryTest.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `tests/Feature/Wald/WaldReasoningBoundaryTest.php` | `e8d552d43ec4d59991959f323c94fe478245b52f2e5c4d7ba2c03fb4687b982d` | `tests/Feature/Wald/WaldReasoningBoundaryTest.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `tests/Support/WaldFixtures.php` | `394c023bbf752d94ee66bee3b18b2cd199f3b3fd7986c7fec65ccc9f14157630` | `tests/Support/WaldFixtures.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `tests/Unit/Wald/ReasoningEngineTest.php` | `12cd240115737ceed91b0517a43c9628826b30f60cd298f6e84127433b8afe24` | `tests/Unit/Wald/ReasoningEngineTest.php` | `ADOPT_AS_IS` | `IDENTICAL` |
| `tests/Unit/Wald/WorkbookProfilerTest.php` | `8dfe42e084d75306633ff6dc8b6303dc2168ee374026c3e6f9c11314b78700da` | `tests/Unit/Wald/WorkbookProfilerTest.php` | `ADOPT_AS_IS` | `IDENTICAL` |

## Portable Core Adopted

The CustomerApp-owned module contains 52 runtime files:

- six workbook-observation contracts;
- thirteen generic reasoning contracts;
- eleven bounded reader/profiler services;
- twenty-two generic reasoning services, constraints and rules.

It supports bounded XLSX and UTF-8 CSV inspection; workbook, sheet, region, header and value
profiling; structural hypotheses; evidence provenance; risk-sensitive confidence; ties and
ambiguity; targeted clarification requirements; deterministic ordering; and refusal of
unsupported, malformed or over-budget inputs.

The retained upstream version identities are reader `wald-0.2.0`, structure
`wald.structure.v1.1`, reasoning `wald-0.3.0`, rules `wald.generic-rules.v1` and confidence
`wald.confidence.v1`.

## Rejected SiteApp Coupling

No SiteApp semantic dictionary or registry, Trade/WorkflowStage model, Plot-stage
normalisation, staging, Eloquent persistence, policy, controller, route, job, Import Studio
UI, permission, Effects/Project Board integration, operational fixture, production/customer
workbook or retained upload was adopted. Those surfaces remain `REJECT` or
`REFERENCE_ONLY` under the work package.

An automated source scan found zero runtime references to SiteApp, ConstructionImport,
WorkflowStage, Trade, Project Board, Effects, call-off/date semantics, application models or
enums, Laravel database/auth/facade access, the service container, configuration, or named
external-AI providers.

## Portability Changes

Runtime source paths moved from `app/Contracts/Wald` and `app/Services/Wald` into
`app/Wald/Contracts` and `app/Wald/Services`. Corresponding PHP namespaces and imports moved
from `App\Contracts\Wald` / `App\Services\Wald` to `App\Wald\Contracts` /
`App\Wald\Services`. The five adopted baseline test/support files received only the same
namespace/import relocation.

There are no algorithmic, constant, limit, scoring, ordering, evidence, decision or exception
changes. A normalized comparison of all 52 runtime and five adopted test/support files found
zero non-namespace differences. These are non-semantic ownership relocations, not a new Wald
version.

## Synthetic Corpus

The adopted `Tests\Support\WaldFixtures` generator creates fictional XLSX workbooks in
temporary storage. The focused baseline corpus covers ordinary and title headers, moved and
reordered regions/columns, blank and separator rows, multiple sheets, competing tables,
numeric/mixed identifiers, date/quantity-like values, unknown/sparse data, formulas, merged
and hidden cells, external relationships, malformed archives, unsupported formats and every
configured analysis bound. No customer, person, live site or production workbook is included.

The five adopted test/support files preserve the approved profiler/reasoning regression
behaviour. `tests/Unit/Wald/CustomerWaldPortabilityTest.php` adds three CustomerApp-owned
safety cases without changing the core.

## Abraham Wald Ambiguity Test

The canonical generated workbook contains `House No.` and `Sales Plot` columns with equally
plausible identifier-shaped values. Both candidates retain structured supporting evidence,
both result in `clarification_required`, neither becomes a leading candidate, both appear in
the competing-candidate set, and the requested clarification is `choose_candidate`.

An immediate replay produces an exactly identical serialized result. The core does not use
header familiarity to invent which identifier has business authority.

## Determinism/Evidence

The adopted tests verify stable profiles, hypothesis order, scores, evidence coordinates,
provenance, explanations, clarification ordering and result manifests. The CustomerApp test
also verifies exact replay equality and confirms confidence is explicitly not represented as
probability.

Raw unknown business values such as `CC!` and `ZZ9` survive in private observations. The
generic engine may identify date-like or quantity-like structure, but has no dictionary
versions, does not invent `CC1`, Requested Date, Date Agreed or proposal dates, and does not
declare the result ready for staging.

## Resource Safety

Existing `AnalysisBudget` limits bound source bytes, archive entries, decompressed bytes,
worksheets, cells, rows, columns, shared strings and cell/string size. Tests exercise exact
limits and one-over-limit refusal, invalid/malformed XML/archive paths, ZIP traversal,
duplicate entries, compression-ratio limits and unsupported input. Formulas, macros, external
links/connections, hidden content and merged cells are observed or flagged; none is executed.

## External Services

No external API, AI/LLM, embedding service, network lookup, database, queue, authenticated
user, application container or persisted profile is used. No API key or external account is
required. The module runs entirely from supplied workbook bytes and deterministic PHP logic.

## Baseline Equivalence

- Adopted runtime: 52 files; `IDENTICAL` after inverse namespace relocation.
- Adopted baseline test/support: five files; `IDENTICAL` after inverse namespace relocation.
- CustomerApp additions: one test file with three safe synthetic/boundary cases;
  `NOT_APPLICABLE` to upstream file parity.
- Intentional behavioural divergence: none.
- Upstream baseline focused cases after relocation: 119 tests, 344 assertions, passed.
- Focused corpus including CustomerApp additions: 122 tests, 1,040 assertions, passed.

## Existing CustomerApp Import Comparison

The non-main chain `04b560f` → `a013ed1` → `1e8c22b` was inspected read only and was not
merged. Its deterministic bounded XLSX inspection, structural DTOs, header/value profiling,
fingerprints, matching, formula/container checks and synthetic regression ideas are useful
generic evidence. The approved Wald baseline already supplies a broader generic
evidence/rules/ambiguity/bounds core, so no non-main runtime file was needed in `App\Wald`.

Source records, source context/scope, source-site binding, Call No. identity, stale-preview
protection, Portal projection, Office authorisation/review and commit controls remain useful
downstream CustomerApp integration evidence for WALD04/05. The parallel direct XLSX → manual
interpretation/profile → projection route remains a supersession candidate, not approved
runtime architecture and not authorised for deletion.

## Dependencies

No Composer or npm package was added, upgraded or removed; neither lockfile changed. The module
uses the existing PHP runtime and `zip`, `xmlreader` and `simplexml` extensions, all confirmed
available under PHP 8.4.23.

## Tests

- `php artisan test --compact tests/Unit/Wald tests/Feature/Wald` — passed: 122 tests,
  1,040 assertions.
- `php artisan test --compact` — passed: 341 passed, 15 existing environment-gated skips,
  2,228 assertions; 356 tests total.
- Source-manifest revalidation — passed: 162 entries, zero mismatches, exact digest.
- Runtime normalized-equivalence check — passed: 52 files, zero non-namespace differences.
- Adopted test normalized-equivalence check — passed: five files, zero non-namespace
  differences.
- Forbidden-coupling scan — passed: zero hits across 52 runtime files.

## Composer Audit

`composer audit` completed and reported eight advisories affecting three existing packages:
`filament/filament`, `league/commonmark` and `livewire/livewire`. The audit therefore exits
non-zero. CUSTOMER-WALD02 changed no dependency or lockfile, and dependency remediation is a
separate explicitly excluded workstream. The findings must be reconciled before any future
release decision.

## Build/Pint

- `vendor\bin\pint --test` — passed.
- `composer validate --strict` — passed (`composer.json` is valid).
- `npm run build` — passed with Vite 8.1.4 after rerunning outside the Windows process
  sandbox; 5 modules transformed and production assets generated.
- `git diff --check` — passed after the final documentation update.
- Documentation path/contradiction check — passed: all required paths exist, 57 ledger entries
  are present and no current document retains the superseded WALD02-not-started claim.

## Production Impact

None. No route, controller, command, upload flow, queue, scheduler, model, migration, database,
configuration, service provider, navigation, feature flag or production process references the
new module. No SiteApp change, push, merge, tag, deployment, production access or customer-data
operation occurred. Generated build assets remain ignored and are not a release action.

## Remaining Blockers

CUSTOMER-WALD02 requires dedicated QA and an explicit acceptance decision before it can be
treated as an approved foundation. CUSTOMER-WALD03 has not begun. Portal semantics must still
be implemented from the controlled CustomerApp dictionary in a separately scoped package;
they must not be inferred or imported from SiteApp.

Import/knowledge permissions, source owner/revision and stale-ordering rules, multiple-row
`Call No.` meaning, retention, reviewed commit atomicity/recovery, durable Site ID availability,
private storage/worker operation, MySQL integration evidence and dependency-advisory
reconciliation remain later-package or release gates.

## Recommended Next Step

Run dedicated QA against commit `5ddaa26` plus the evidence/documentation commit containing
this report. Review the per-file ledger, focused corpus, canonical ambiguity result, forbidden
coupling scan and full regression result. If accepted, freeze the resulting CustomerApp SHA as
the input baseline for a separately approved CUSTOMER-WALD03 semantics/dictionary package.

Do not integrate, deploy or begin CUSTOMER-WALD03 as part of this package.
