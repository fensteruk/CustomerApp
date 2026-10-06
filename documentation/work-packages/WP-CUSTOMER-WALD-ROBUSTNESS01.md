# CUSTOMER-WALD-ROBUSTNESS01

Approved 6 October 2026. Base: `04e48b55c6178f4e2352cd06e7cd657d0b1f081b`.
Branch: `codex/wald-robustness01-2026-10-06`. No push or deployment.

Implement sequentially and commit separately:

1. Read-only binding integrity service, Office page and CLI. Verify exact ownership,
   active version/history consistency and current retained source hierarchy. Missing or
   stale evidence must be explicit. No automatic repair or fuzzy matching.
2. First-class Office dry run with no business or binding mutations. Use current
   dictionary, workbook discovery and exact resolver; report proposals, conflicts,
   unknown rows, site-scoped plot candidates and integrity issues. Print-friendly
   report and deterministic content fingerprint. Normal Apply remains separate.
3. Inspect receipt/preview/provenance ownership before reversal. If complete
   before/after ownership is missing, stop destructive implementation and document
   the precise contract and Safe Cascade Purge dependency required.

Qualify focused security, deterministic/no-write and failure cases, current full
suite, formatting, dependencies, assets and disposable MySQL 8.4. Preserve existing
source semantics, partial-export protection, Portal-owned state and shared history.
Do not merge the old WALD06 line or the separate purge branch implicitly.

## Qualification outcome

TASK A READY. TASK B READY. TASK C BLOCKED BY EVIDENCE CONTRACT.
Runtime `f19df5a4d48e96b9a2e776e1cfd038353157e253`: focused 18 / 164,
grouped 85 / 706, disposable MySQL 8.4.11 12 / 149, full 1,984 passed / 92 expected
skips / 11,075 assertions. Viewport/keyboard/print, formatter, build, Composer
validation, production npm audit and diff checks pass. Composer reports five
existing advisories without upgrades. No new migration, push or deployment.
See `../wald-robustness01-report-2026-10-06.md` and the reversal evidence contract.
