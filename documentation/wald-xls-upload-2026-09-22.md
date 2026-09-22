# Wald legacy XLS upload candidate — 22 September 2026

## Scope and baseline

Baseline was clean `main` / `origin/main` at
`51635964ec0787082953cf6d6f9596570beb8d4d`. The implementation is local feature branch
`codex/wald-xls-reader`: `dde25a6` accepts genuine BIFF `.xls` workbooks; `16964ff` is a
cherry-pick of the separately approved DEC-068 exact `Customer Number` header change
(`89d861d`). The latter is needed for the supplied export's source identity. Neither commit
has been pushed or deployed from this branch.

## Completed

- Office upload validation, file input, MIME checks and private storage now accept `.xls`.
- An application-side PhpSpreadsheet reader captures physical cells, leading-zero text, raw
  values, formula evidence without evaluation, number formats, merges and visibility. It uses
  existing Wald resource limits and a pre-load estimated-cell limit. Generic Wald engine files
  were not changed.
- The controlled staging and source-discovery path accepts the user's supplied binary XLS
  without conversion. Private local discovery returned 4,358 records in 145 source groups at
  192 MiB observed peak PHP memory. The temporary private copy was deleted after the check;
  the workbook and source rows were not added to Git.
- The exact `Customer Number` header maps to the existing CustomerCode role under DEC-068.
  Unknown codes still need explicit Office binding. The dictionary advances to v4, so older
  analysis knowledge must be re-analysed rather than reused.

This was a read-only local discovery of the real export. It did not resolve structural/source
questions, bind a site, approve a preview, create a receipt, commit Portal facts or modify
production.

## Changed files

- XLS upload and reader: `app/Http/Controllers/OfficePilotImportController.php`,
  `app/SourceImport/Integration/PrivateWorkbookStorage.php`,
  `app/SourceImport/Integration/WorkbookStager.php`,
  `app/SourceImport/Readers/XlsWorkbookSource.php`,
  `resources/views/office/pilot-import/index.blade.php`.
- Runtime dependency: `composer.json`, `composer.lock` (PhpSpreadsheet 5.10 and its required
  packages; no broad dependency upgrade).
- XLS tests: `tests/Feature/WaldPilot01/XlsUploadTest.php`,
  `tests/Unit/Wald/XlsWorkbookSourceTest.php`.
- Exact header implementation, tests and contract documentation: the files in `16964ff`.
- Current handover: `HANDOVER.md`, `current_sprint.md`, `ROADMAP.md`, and this report.

## Verification

- Focused XLS and header regression: 350 passed, 1,497 assertions.
- Full `php artisan test`: 1,628 passed, 81 skipped, 8,500 assertions.
- `php vendor/bin/pint --test`: passed.
- `composer validate --strict`: passed.
- `composer audit`: no security advisories.
- `npm run build`: passed.
- `npm audit --omit=dev`: zero vulnerabilities.
- `git diff --check`: passed before documentation commit; rerun at final review.

The supplied file was confirmed to be binary XLS, not a renamed XLSX. Local discovery used
the normal private upload and staging services. Production PHP extension availability and
memory headroom require confirmation before release; local peak memory was 192 MiB under a
256 MiB test limit. Disposable MySQL 8.4 evidence and production acceptance were not run.

## Impact and release boundary

No migration or database schema change. Existing Portal-owned requests, dates and history
remain protected by the unchanged import/commit flow. The candidate adds a runtime dependency.
The Wald import gate, Office authority, manual selected-site preview and explicit commit still
apply. This branch has no production effect. Do not infer that the failed production revision
has recovered; a controlled re-upload after an approved deployment would create new review
evidence and require Office action. Unknown call types, product conflicts and incomplete source
meaning remain fail-closed under the current dictionary.
