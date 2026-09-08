# CUSTOMER-WALD05 — Confirmed workbook interpretation

Date: 8 September 2026. Authority: explicit user instruction, recorded in DEC-050.

Artifact: `C:/Users/JoshO/Documents/CustomerApp/Copy of siteapp1.xlsx`.
SHA-256: `ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893`.
The hash identifies the approved bytes; the filename does not grant interpretation authority.

| Call Type evidence | Confirmed interpretation for this artifact |
|---|---|
| PC1 | Full call-off example; existing Windows mapping. |
| CC! | Explicitly confirmed typo: interpret every occurrence as CC1 / Cavity Closers while retaining raw CC! and approval provenance. |
| CC1 | Existing Cavity Closers mapping. |
| CM1 | Full call-off example; existing CML mapping. |
| CM2 | Intentionally exclude these records from the proposed customer projection for this workbook; retain private exclusion evidence. |

The user's preceding clarification limits customer display to full call-offs/completions and
excludes past plots and itemised records from that view. This note does not infer a new source
field that identifies past/current records or an ordering from Call No., row position or code.

This instruction is the explicit human confirmation for every CC! Call Type occurrence in the
identified artifact. A future adapter should preserve occurrence-level raw/canonical lineage
linked to that approval without asking the user to approve the same correction cell by cell.
It must not save CC! as a reusable alias or change `customerapp.source-dictionary.v1`.

Ignoring CM2 is an intentional source-selection rule for this artifact, not permission for a
partially failed atomic commit. All included required records must still pass the approved
readiness checks. Exclusion/absence cannot delete, zero, complete or reverse existing facts.
CM2 remains dictionary-valid for other imports. The examples do not exclude CML globally.

The earlier synthetic W5-P01 CM1/CM2 disagreement is outside this workbook's selected scope.
No actual duplicate-visit or conflicting-product condition has been established by this
clarification: only the artifact hash was read during this recording task, not its cells.
The historical entry-review report remains unchanged; its examples must not override this
newer source-selection decision.

## Recording and verification

Changed files: this note, `DECISIONS.md`, and the current WALD05 work package's authority link.
Verified the artifact SHA-256, current feature branch and existing workspace changes. The
workbook itself is unchanged; no runtime, migration, import, deployment or dictionary change.
Whitespace, documentation-link and staged-file checks are required before committing this note.
Application suites are not required for these documentation-only changes.
