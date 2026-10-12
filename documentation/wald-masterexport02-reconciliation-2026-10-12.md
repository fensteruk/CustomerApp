# Master export count reconciliation

Date: 12 October 2026. Parent: CUSTOMERAPP-WALD-MASTEREXPORT02.
Scope: retained MASTEREXPORT01 neutral cells labelled SHA-256
56e64ce1b9ad380cc2109829c78be4bd195d3a497bf13a86cdaed6bdef174f49.
Independent QA matches Wald's exact source membership, states and Plot keys.
This does not qualify the current file hash7301acbc…aa725 or runtime behaviour.

## Canonical totals

| Measure | Prior Wald / QA | Reconciled | Definition and cause |
| --- | --- | --- | --- |
| Source-site units | 85 / 83 | 85 | Exact AA-prefix units, including two unresolved units without parent headers |
| Qualifying units | 55 / 55 | 55 | At least one safely qualifying call under valid exact association |
| Proven-zero units | 3 / 1 | 3 | Whole-unit absence of calls with safe inventory; no unapproved AA-suffix equality test |
| Review units | 27 / 27 | 27 | Includes missing parents and other unresolved evidence; prior matching count masked different membership |
| Eligible distinct Plot candidates | 4969 / 4968 | 4969 | Correct boundary restores valid prior parent2558 without inheriting foreign row2565 |
| Retained no-call-off Plots | 2238 / 2238 | 2238 | All valid base Plots on qualifying exact sites |
| Whole-zero-site Plots excluded | 123 / 6 | 123 | 113+4+6; earlier suffix test improperly removed two zero-site groups from classification |

All5709 valid source-code-scoped Plot keys are independently matched. Inventory
eligibility is not binding/Apply approval; qualifying groups can still have
hierarchy/fact blockers. Source unit counts are not counts of physical Plot groups.

## Boundary and rule evidence

| Source evidence | Correct interpretation | Required regression |
| --- | --- | --- |
| Headerless rows2 and2565 | Retain two exact source units as REVIEW; do not omit or infer a parent |
| Parent2558; children2559–2564; foreign base row2565 | Keep valid earlier Plot; row2565 does not inherit prior parent across authoritative code boundary |
| Parent ranges3–227 /3833–3839 /4392–4402 | Respect113 /4 /6 zero-site Plots; AA suffix is not independently approved Plot authority |
| Call-free conflicting hierarchy unit | REVIEW, not a guessed zero/qualifying site |
| Four excluded category groups/eight physical rows | Private One Off/Trade Supply Only disposition, no facts; adjacent normal groups retained |

Independent QA's prior mixed-AA finding resulted from the same incorrect
row2565 inheritance; canonical missing-parent rows remain explicitly withheld.
Historical totals remain preserved rather than silently relabelled as passing.

## Verification and limits

QA independently reproduced existing normalization and corrected its grouping/
eligibility checker. Canonical comparison reports zero source-membership/state
disagreements and zero Plot-key symmetric difference. Main checked the retained
physical cells at the boundary. Private source manifests are retained untracked
under .cursor/masterexport02-qa; no raw customer rows are committed here.

No application test suite, current-source extraction, database projection,
browser journey or production import passed as part of this reconciliation.
The identified narrow regressions remain required in the later runtime package.
