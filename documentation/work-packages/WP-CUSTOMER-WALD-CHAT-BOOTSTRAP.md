# WP-CUSTOMER-WALD — Fresh Chat Bootstrap

**Purpose:** Start a dedicated CustomerApp standalone-Wald task with the correct boundaries.

**Status:** Historical bootstrap. Its CUSTOMER-WALD02 entry gates were subsequently satisfied
by the approved checksum manifest and scoped WALD02 package; see the current handoff/report.

## Read First

Read, in order:

1. `AGENTS.md`
2. `brief.md`
3. `DECISIONS.md`
4. `current_sprint.md`
5. `ROADMAP.md`
6. `HANDOVER.md`
7. `documentation/work-packages/WP-CUSTOMER-WALD01-STANDALONE-WALD-ADOPTION.md`
8. `documentation/wald-divergence-register.md`
9. `documentation/source-integration-contract.md`
10. `documentation/siteapp-import-data-dictionary.md`
11. the newly approved work package for the requested Wald phase

## Ownership

This chat owns only CustomerApp's standalone, deterministic Wald adoption and its explicitly
approved integration phase. It may prepare or implement a bounded Wald work package after all
entry gates are satisfied.

It does not own:

- Sprint 3F date amendments or their QA/release;
- production deployment, Forge configuration or production smoke testing;
- unrelated CustomerApp UI or workflow work;
- SiteApp development, SiteApp deployment or SiteApp operational-domain changes;
- direct SiteApp API/database/filesystem/queue/runtime integration;
- general refactoring of the non-main manual source-import feature.

## Non-Negotiable Boundaries

- CustomerApp must work while SiteApp is unavailable.
- Only the approved generic Wald engine surface may be compared/forked; never copy SiteApp
  operational models, permissions, administration, workflows or commit logic.
- No external AI/LLM, embeddings or third-party spreadsheet interpretation.
- Structural inference never defines business meaning.
- Use private upload → analysis/clarification → neutral staging → authorised review → explicit
  controlled commit.
- Preserve tenant, site, snapshot-scope, source-completion, lead-time, history and
  customer-owned date protections.
- Default imports to partial/filtered scope; absence never proves deletion.
- No spreadsheet or SiteApp write-back.

## Entry Gate

Do not begin CUSTOMER-WALD02 or change production code until all are supplied and approved:

1. the exact immutable SiteApp WALD source baseline and manifest;
2. the scoped CustomerApp work package for the phase;
3. resolved permissions needed by that phase;
4. a non-deploying branch and safe fixture/test plan;
5. confirmation that no production/customer workbook will be used for development.

If a gate is missing, report it and remain audit/planning-only. Do not substitute the manual
import branch or current SiteApp working tree as the approved source baseline.

## Required Reporting

Report the exact source baseline, divergence classification, files changed, focused/full tests,
SQLite and disposable MySQL evidence where applicable, security/audit results, migrations,
unrelated changes preserved, and confirmation that no deployment or SiteApp change occurred.
