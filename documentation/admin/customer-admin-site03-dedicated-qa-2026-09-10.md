# CUSTOMER-ADMIN-SITE03 Dedicated QA Report

**Date:** 10 September 2026  
**Executable candidate:** `7cde52471af1da0de97635f7d47eb8db140229d6`  
**Hostile QA tests:** `09fc77054277acaa091d19372920b36a77a1b7bd`  
**Result:** **PASS — accepted for next-release consolidation as a local/test-only demo**

## Isolation and route safety

- `IMPORT_STUDIO_DEMO_ENABLED` defaults to false.
- Demo routes register only when the environment is `local` or `testing` **and** the flag is
  explicitly enabled.
- A separate production boot with the flag deliberately set to true confirmed that the named
  demo route is absent.
- The only two demo routes accept `GET`/`HEAD`. There is no upload, preview mutation, binding,
  staging, projection, commit or receipt endpoint.
- Direct `POST`, `PUT`, `PATCH` and `DELETE` attempts returned method-not-allowed and left customer,
  site, import, binding and administration-audit counts unchanged.
- The implementation contains no network URL, fetch/XHR call, arbitrary file input, multipart
  form, queue dispatch, external AI/LLM call or real Wald service invocation.

## Authority and privacy

- Authenticated active Fenster Office Staff can view the storyboard.
- A controlled local preview Office user may view it but remains forbidden from real Office admin
  writes.
- Inactive Office and unauthenticated requests fail through the normal access boundary.
- Site Manager, Assistant Site Manager and Finishing Foreman receive HTTP 403.
- Site-context entry repeats customer/site containment and uses that context only as an explicit
  launch label. The scenario remains separate, fictional and precomputed.
- No raw workbook, source row, private evidence, filename, storage key, hash, exception or real
  customer data is included.

## Journey and truthful presentation

The browser walked all 12 steps: Select Example, Export Date, Export Slot, Uploader, Latest Export,
Wald Analysis, Detected Site, Source Binding, Detected Records, Clarifications, Preview Changes and
Commit Summary. Every step retained the three safety messages:

- **DEMO ONLY**;
- **SYNTHETIC DATA**;
- **NO DATA WILL BE SAVED**.

The analysis explicitly says it is precomputed and that Wald did not run. The fictional
clarification cannot become a global alias. The multi-site paragraph is labelled as a future
WALD06 concept and does not split anything. The final action is disabled and says **Commit
unavailable in demo**.

## Browser, responsive and accessibility

One local server, disposable SQLite database and browser tab were used sequentially and removed
afterward.

- Desktop **1440 × 900**: entry page, progress navigation, first and final steps rendered without
  document overflow.
- Tablet **768 × 1024**: Detected Records table remained contained in its deliberate horizontal
  scroller; document overflow was false.
- Mobile **390 × 844**: Clarifications and all safety labels remained rendered; document overflow
  was false.
- Each step change moved keyboard focus to its step heading, ending on
  `import-demo-step-12`.
- Progress controls expose current/complete/upcoming text rather than colour alone.
- No browser console warning or error was present.
- No non-logout POST form, file input or enabled commit control was present.

## Automated evidence

- Focused ADMIN-SITE03 feature QA: **10 passed / 57 assertions**.
- Client step state/boundary test: **1 passed / 0 failed**.
- Combined Office admin/demo client tests: **14 passed / 0 failed**.
- Final full CustomerApp suite: **1,489 passed / 77 environment-dependent skips / 7,796
  assertions** (**1,566 total**).
- Vite 8.1.4 production build: passed, 7 modules transformed.
- Pint: passed.
- `composer validate --strict`: passed.
- Composer audit: **no security vulnerability advisories found**.
- npm production audit: **0 vulnerabilities**.
- `git diff --check`: passed.

## Acceptance boundary

ADMIN-SITE03 is accepted only as a local/test product storyboard. It is not a real Import Studio,
does not prove Wald browser integration, and provides no authority for WALD06, a multi-site pilot,
real upload/persistence, production demo exposure or import commit UI.
