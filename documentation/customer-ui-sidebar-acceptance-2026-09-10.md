# CustomerApp Sidebar Acceptance Report

Date: 10 September 2026

This record accepts the dedicated-QA-corrected CustomerApp sidebar code baseline for a
future release. It is an acceptance record only and does not authorise integration or
deployment.

## Accepted SHA

`c80ab5b76a161f340b8786c709b93fecfae47633`

Branch: `feature/customer-ui-sidebar-workspace`

The original implementation candidate
`d76ddbba47ba7b16e6376d2f089ab06b4cc46187` is superseded as an acceptance
candidate. The accepted SHA includes its bounded QA corrections.

## QA Evidence

- Focused: 17 passed / 114 assertions.
- Related UI regression: 121 passed / 748 assertions.
- Full suite: 331 passed / 38 skipped / 2,367 assertions.
- Responsive desktop, tablet and mobile coverage passed.
- Role navigation and 403 containment passed.
- Keyboard, focus and accessibility smoke passed.
- Long-list performance checks passed.
- Composer audit was clean.
- Vite production build passed.

The detailed evidence remains preserved in
[CustomerApp Sidebar Dedicated QA Report](customer-ui-sidebar-dedicated-qa-2026-09-10.md).

## Corrections Included

- UIQ-01 P1 — corrected extreme plot-reference wrapping.
- UIQ-02 P2 — corrected 320px header overflow.
- UIQ-03 P1 — corrected notification/drawer focus interaction.

## Release Disposition

`NEXT_RELEASE`

The accepted sidebar is held for controlled next-release integration. This acceptance does
not merge it with another stream and does not authorise a release or deployment.

## RC1 Impact

None.

RC1 is not reopened or modified. The sidebar is not merged into RC1 or `main` and is not
deployed by this acceptance.

## Next Integration Candidates

- Accepted CustomerApp sidebar redesign.
- ADMIN-SITE02, once QA-approved.
- Possibly ADMIN-SITE03 synthetic import demo, after separate QA.

These candidates remain separate. Their inclusion in a future release requires an explicit
integration instruction and the applicable QA and release gates.
