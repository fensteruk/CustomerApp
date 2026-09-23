# CUSTOMER-UI-FINAL02 — Future Call-Off Lead Times UI integration contract

Date: 23 September 2026.
Status: planned presentation contract only; no lead-time functionality added.
Qualified base: `1fbb0ff25fd23645bc31007fb6b286924f5bf563`.

## Current integration boundary

At this baseline, the only Office Settings routes are:

- `office.workspace.settings.wald` — GET `/portal/office/workspace/settings/wald`
- `office.workspace.settings.wald.update` — PUT to the same path

The real Settings page is `resources/views/office/settings/wald.blade.php`, inside the existing `x-layouts.portal` shell. It contains current availability, the audited Wald application setting, deployment access information and immutable paginated history. It preserves the exact existing controller, action, Office policy and environment gate. The deployment gate remains read-only here.

There is no configurable call-off lead-time Settings route or domain on the qualified baseline. No live lead-time card, link, edit button, placeholder value, disabled fake form or configuration endpoint is added. Existing fixed lead-time behavior is unchanged.

Commits `7ada4ff`, `e5fe235` and `codex/customer-calloff-history01` remain excluded. They need separate backend qualification and explicit integration before this contract becomes executable UI. This document grants no merge or release authority.

## Page structure after separate qualification

1. Keep the current Settings heading, Office-only context and existing global navigation.
2. Add a page-local section navigation for the real Wald settings and the newly qualified Call-Off Lead Times route. Derive URLs from its verified route names; none are reserved or invented here.
3. Use a dedicated Blade page for Call-Off Lead Times beneath `resources/views/office/settings/`. Reuse the page-local card spacing and text hierarchy from the Wald page. Coordinate any shared navigation/layout/CSS changes with Frontend.
4. Display the four independent service cards in one column on small screens and two columns where there is sufficient width. Do not imply sequencing or dependencies.

| Card heading | Current rule/value | Primary action | Secondary action |
| --- | --- | --- | --- |
| Cavity Closers | Server-provided qualified rule summary | Edit Cavity Closers lead time | View Cavity Closers history |
| Windows | Server-provided qualified rule summary | Edit Windows lead time | View Windows history |
| Snagging | Server-provided qualified rule summary | Edit Snagging lead time | View Snagging history |
| CML | Server-provided qualified rule summary | Edit CML lead time | View CML history |

These are design labels, not claims that an editable value exists for every service today. If the qualified domain declares a service fixed or uneditable, show its authoritative rule explanation and omit editing. Do not invent a value, zero, fallback, bypass, holiday rule, BF condition, range or unit. The backend owns each rule and its editability.

Each card shows the service heading, current saved rule/value with its units and material conditions, and the authoritative last-change attribution when available. An edit opens a focused service form, not a global Save All workflow. Keep saved state visually distinct from unsaved input. Do not copy Wald's enable confirmation or required-reason rule into lead-time forms.

## Data responsibilities (presentation requirements, not a new API)

The separately qualified backend must provide, under its approved contract:

- Stable service identity and the four approved display labels.
- An authoritative current rule summary/value, units, conditions and editability.
- The actual update route, allowed fields, validation messages and version/concurrency mechanism.
- Immutable change events containing old value, new value, actor, timestamp and optional reason.
- A paginated service-scoped history query, with deterministic ordering.
- Historical request rule context from immutable request evidence, including an honest unavailable/legacy state where applicable.

Map those fields to Blade only after checking the qualified implementation. These are semantic requirements; this document does not prescribe database columns, migrations or JSON property names.

## Editing and feedback

- Heading: `Edit <service> lead time`; show the current saved rule beside the editable value.
- Use the backend's approved field type, units, required/optional status and validation limits.
- Reason is optional under this requested design contract. If the separately qualified domain requires it, resolve and record that explicit contract difference before integration.
- Primary action: `Save changes`; secondary: `Cancel`, returning to the service card without saving.
- Provide labeled fields, associated help/error text, a summary alert, visible focus and a success notice after a confirmed successful save.
- Preserve input after validation failure. A stale version must explain that the setting changed and require reloading/reviewing the saved value; never silently overwrite another Office user's change.
- Keep changes independent by service. Editing Windows must not update Cavity Closers, Snagging or CML.

## History and historical requests

Use a paginated list of readable change cards matching the Wald history presentation. Each entry explicitly shows old → new, actor, timestamp with timezone and the optional reason. If no reason exists, display `No reason provided`; do not fabricate attribution or dates. Wrap long content and escape all supplied text.

Existing requests must retain the rule/version/context that applied at their original decision point. Showing or changing the current setting must not recalculate historical eligibility, earliest dates, override reasons or acceptance decisions. Display unknown historic context honestly; do not backfill today's rule as historic truth. Approved reschedule/amendment behavior remains owned by the separately qualified backend contract.

## Authorization and verification before integration

- Office-only GET and mutation paths using current valid server-side authority. Hidden controls never substitute for authorization.
- Preserve domain version checks, audit ownership and transactions; UI must not reproduce them.
- Verify all four independent service cards, valid edits, invalid fields, optional reasons, save feedback, cancel, empty/paginated history and content escaping.
- Verify stale updates, denied external/inactive/preview accounts and no cross-service mutation.
- Prove historic request displays remain unchanged after a setting change and after deployment, using the separately qualified feature's required database evidence.
- Check 320/390 mobile, tablet and desktop widths, keyboard focus, named controls, long rule/reason text, and no page-level horizontal overflow.
- Run the repository's required regression/build/audit gates. Any database-specific evidence belongs to that feature's qualification and cannot be replaced by these UI checks.

No domain changes, migrations, branch merges, push or deployment are part of CUSTOMER-UI-FINAL02.
