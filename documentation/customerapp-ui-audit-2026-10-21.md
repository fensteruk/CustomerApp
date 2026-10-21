# CustomerApp UI Audit — 21 October 2026

Status: **audit report (read-only audit). The follow-up fixes in section E are on branch `codex/customerapp-plot-list-table-default`.**

## Scope and method

- **Code audited:** `origin/main` at `8629815c1f146cfe41c9364ebafcba28c44a7648`. This is the code production serves: UX02 Table/Cards and UX03 Search & Filters.
  - The code was exported with `git archive` into a session scratch folder.
  - No branch was switched in the shared checkout.
  - The SEC01 branch, the `.release-worktree/*` worktrees and the docs branch were not modified.
- **Runtime:** local only.
  - Herd PHP 8.4, `php -S 127.0.0.1:18431`.
  - SQLite, seeded with `DatabaseSeeder` plus a fictional extra seed: a second customer "Northgate Homes (Fictional)", 46 plots on one site, source-completed services, product rows, an inactive user, an Office user with `customer_organisation_id = NULL`, and a long site name.
  - `WALD_IMPORT_AVAILABLE=true` and the pilot setting were switched on **in the local copy only**, so the import empty state could be viewed.
- **Not touched:** production. It was not used, even though a production session was signed in in the browser; the user confirmed "local only". Also untouched: real customer data, private Wald workbooks, and the live Wald import.
- **Roles exercised in the browser:**
  - Site Manager (preview user)
  - Assistant Site Manager (preview user)
  - Finishing Foreman (preview user)
  - Northgate Site Manager (non-preview; used to produce notifications)
  - Fenster Office Staff (non-preview, null organisation)
- **Viewports:** 320, 390, 768 and 1280 px wide. Short heights 420, 480 and 568 px were used for the sidebar and modal.
- **How screens were checked:**
  - Screenshots in the built-in browser.
  - A scripted DOM check on every screen. It looked for horizontal page overflow, interactive elements with no accessible name, target sizes, and leaked source or jargon terms.
  - Code reading for semantics.
- **Flows exercised end-to-end:**
  - Login error
  - Forgot-password, success and failure
  - Site selection, including a tampered site switch
  - Plot listing in Table and Cards
  - Search & Filters
  - Plot detail
  - Call-off submission, including the validation error and the confirmation modal at 320×568
  - Office accept-requested-date
  - Office propose-alternative, with a marked internal reason
  - Site-user reject validation, then accept
  - Amendment (date change) request and review
  - Office Amendments workspace
  - Notification bell and notification centre
  - Settings: Wald pilot toggle
  - Imports empty state
  - Cross-customer URL probes
- **Not exercised with data:** the Wald import review screens (upload → structure → hierarchy → CustomerCode → unknown rows → preview → commit), and Reconciliation. Running them needs an analysed workbook, and no private workbook was used. These screens were reviewed from code only (see O-xx findings).

Evidence notes:

- `file:line` paths are relative to the repository root and refer to `8629815`.
- "Browser" evidence means it was seen in the local app at the stated viewport.
- The screenshots are kept in the audit session, not in the repository.

---

## A. Authorisation / data-exposure observations (reported separately)

The audit found **no case where an external user can see Office-only source evidence**. It also found no route protected only by a hidden button. Both were verified in the browser and in code:

- **External users see no source evidence.**
  - Plots with source CallNo, Call Type, job stage and `local_preview` identifiers were opened as Site Manager, Assistant Site Manager and Finishing Foreman. None of those values appeared, even with every `<details>` element expanded.
  - The source blocks are gated server-side by `$isOffice` (`resources/views/site-workspace/cards.blade.php:5`, `table.blade.php:19,53`).
- **Office-only reasons stay private.** The Office internal reason `INTERNAL-AUDIT-MARKER…` never appeared on the customer request page. Only the customer-facing message was shown.
- **Cross-customer access is refused.** The Northgate user got:
  - **404** for a Meadow View plot (`PlotDetailsController.php:24-29`) and for a Meadow View call-off;
  - **403** for a tampered `POST /sites/active` with another customer's `site_id`;
  - **403** for `/portal/review-requests`, `/portal/office/workspace/customers` and `/portal/office/workspace/imports`.
- **Wald screens check the gate server-side.** Every pilot-import controller calls `abort_unless(WaldPilotAvailability->enabled(), 404)` plus a policy. The navigation link is not the only protection.

Items that need attention or a decision:

| # | Severity | Item | Evidence | Recommended next step |
|---|---|---|---|---|
| A1 | **High** | **Password reset reveals which email addresses have accounts.** An unknown address gets "We can't find a user with that email address."; a known address gets "We have emailed your password reset link." There is no route-level throttle on `POST /forgot-password`. The broker throttles repeat sends only for existing users. | Browser 320 px, both messages seen. `app/Http/Controllers/Auth/PasswordResetLinkController.php:33-41`, `routes/web.php:49`. The same code is on `codex/customerapp-release-sec01` (checked read-only), so SEC01 does not address it. | Always return the same neutral "If an account exists…" message, and add `throttle` to the route. |
| A2 | Medium (decision) | **Permanent delete and "Purge demo/test data" are available in production to every active Office user.** There is no environment flag. DEC-074 approves the purge flow with safeguards but says "does not authorise a production purge". The UI exposes it regardless. `OfficeAdministrationPolicy` grants every listed ability, including `demo_purge` and `customer_delete`, to any active Office Staff. | `routes/office-workspace.php:76-79,86-89`; `app/Http/Controllers/OfficeDemoPurgeController.php:19,32,45,55`; `app/Policies/OfficeAdministrationPolicy.php` (abilities list lines 19/25, check at 36). | Ask management whether purge and permanent delete should sit behind a fail-closed environment flag or a separate grant in production. |
| A3 | Medium | **Wald review forms come with audit reasons already filled in**, so Office can submit without writing a reason. This weakens the audit trail. | `resources/views/office/pilot-import/source-sites.blade.php:35-36` (`value="Reviewed exact source-site binding."`, `value="Exact source identity reviewed against Portal site."`); `site-review.blade.php:21` (`value="Reviewed against the controlled source dictionary."`). | Leave these empty and required, and use placeholder text instead. |
| A4 | Low | The notification JSON returns the whole model (`route_name`, `request_uuid`, `batch_uuid`, `site_uuid`, …) when the bell needs only a few fields. Today it is scoped to the user's own notifications, so nothing leaks across users. | `app/Http/Controllers/PortalNotificationController.php:134`. | Return an explicit allow-list of fields. |
| A5 | Low | The Office workspace route group has no `throttle:office-administration`. The older `/portal/office` group does. | `routes/office-workspace.php:18` vs `routes/web.php:144`. | Add the throttle to the group. |
| A6 | Low | The user form puts every customer's sites in the HTML and hides the others with Alpine. The server does reject cross-customer sites, which is correct. Without JS, every site appears enabled. | `resources/views/office/users/form.blade.php:29-31`. | Render only the selected customer's sites. |

---

## B. Screen inventory

**Guest**

| Screen | Route | View | Checked in browser |
|---|---|---|---|
| Sign in | `GET /login` | `auth/login.blade.php` | 390, 320; error state |
| Forgot password | `GET /forgot-password` | `auth/forgot-password.blade.php` | 320; success and failure |
| Reset password | `GET /reset-password/{token}` | `auth/reset-password.blade.php` | code only |
| Role preview (local/test only) | `GET /development/role-preview` | `portal/role-preview.blade.php` | not in scope (404 outside local/testing) |

**External Portal (Site Manager, Assistant Site Manager, Finishing Foreman)**

| Screen | Route | View | Checked in browser |
|---|---|---|---|
| Choose a site | `GET /sites/select` | `portal/site-selection.blade.php` | 320, 390 |
| Site dashboard / Plot listing (Table, Cards, Search & Filters, "Switch site & filters") | `GET /portal/site-dashboard` | `portal/site-dashboard.blade.php`, `site-workspace/*` | 320, 390, 768, 1280 |
| Plot details | `GET /portal/plots/{uuid}` | `portal/plots/show.blade.php` | 390 |
| New call-off, then confirmation modal | `GET /portal/call-offs/new`, `POST …/matrix`, `…/review`, `POST /portal/call-offs` | `portal/call-offs/create`, `matrix`, `confirm` | 390, 320×568 (modal) |
| Call-off request detail (alternative accept/reject, withdraw, history) | `GET /portal/call-offs/{uuid}` | `portal/call-offs/show.blade.php` | 390 |
| Request date change, then review | `GET …/date-change`, `POST …/date-change/review` | `portal/call-offs/amendments/create`, `review` | 390, 320 |
| Resubmit rejected call-off | `GET …/resubmit` | `portal/call-offs/resubmit/*` | code only |
| Withdraw / trash / restore confirmation | `POST /portal/call-offs/lifecycle/confirm` | `portal/call-offs/lifecycle-confirm.blade.php` | code only |
| Trash | `GET /portal/call-offs/trash` | `portal/call-offs/trash.blade.php` | 390 (empty state) |
| Notification bell panel | in the layout | `layouts/partials/notification-bell.blade.php` | 320 (keyboard) |
| Notification centre | `GET /portal/notifications/centre` | `portal/notifications/index.blade.php` | 320 (empty state) |
| Mobile sidebar | in the layout | `layouts/portal.blade.php`, `layouts/partials/portal-sidebar.blade.php` | 320×480 (keyboard, Escape) |

**Office (Fenster Office Staff)**

| Screen | Route | View | Checked in browser |
|---|---|---|---|
| Office dashboard | `GET /dashboard` | `office/dashboard/index.blade.php` | 390, 1280 |
| Review Requests queue and detail | `GET /portal/review-requests[/{uuid}]` | `portal/review-requests/*` | 1280; accept and propose |
| Amendments workspace | `GET /portal/office/workspace/amendments` | `office/amendments/index.blade.php` | 390, 1280 |
| Customers list / new / detail / edit / change status / activity | `/portal/office/workspace/customers…` | `office/customers/*`, `office/form.blade.php` | 390, 1280 |
| Site details (Overview, Plots, Users, Source Binding, Import History, Activity) / new / edit / change status / assign user | `/portal/office/workspace/customers/{c}/sites/{s}…` | `office/sites/*` | 390, 1280 |
| Delete permanently (customer, site) | `…/delete-permanently` | `office/permanent-delete.blade.php` | 390, 1280 (preview only; nothing submitted) |
| Purge demo/test data (customer, site) | `…/purge-demo-data` | `office/demo-purge.blade.php` | 390, 1280 (preview only; nothing submitted) |
| Users list / new / detail / edit / change status | `/portal/office/workspace/users…` | `office/users/*` | 390, 1280 |
| Settings: Wald Import Pilot | `GET /portal/office/workspace/settings/wald` | `office/settings/wald.blade.php` | 390, 1280 |
| Imports (upload, recent, older history) | `GET /portal/office/workspace/imports` | `office/pilot-import/index.blade.php`, `upload-form`, `summary-card` | 390, 1280 (empty state) |
| Import detail and review steps | `/imports/pilot/{upload}…` | `office/pilot-import/show`, `source-sites`, `site-review`, `customer-code-review`, `unknown-rows`, `ignored-rows`, `proposal-approval`, `reanalysis`, `applied-result`, `lifecycle` | code only |
| Paired workbook analyse | `GET /imports/paired-analyse` | `office/pilot-import/paired-analyse.blade.php` | code only |
| Reconciliation | `GET /imports/pilot/{upload}/reconciliation` | `office/reconciliation/show.blade.php` | code only |

What passed (summary):

- **No horizontal page overflow** on any screen at 320, 390, 768 or 1280 px. Wide tables scroll inside their own `role="region" tabindex="0"` container.
- **Mobile sidebar:**
  - Behaves as a dialog with `aria-modal` and a focus trap.
  - Escape closes it and returns focus to "Menu". `aria-expanded` stays in sync.
  - The navigation region scrolls at 420–480 px height, with Log out kept visible (`portal-sidebar.blade.php:20`).
- **Call-off confirmation modal:**
  - Built on native `<dialog>` with `aria-labelledby`.
  - Focus moves to its title.
  - At 320×568 the body scrolls and Confirm/Cancel stay visible.
- **Focus and status:**
  - The global `:focus-visible` ring is visible; it was seen on Menu, Close and the bell.
  - Status badges always carry text, never colour alone.
- **Status wording is the approved set** in the plot listing: "Called Off — Awaiting Date", "Date Agreed", "On Hold — Date Change Requested", "Completed", plus the overall plot states.

---

## C. Findings

Each finding is listed with its severity, screen, role, viewport and evidence.

### High

None in the UI itself. The only High item is **A1**, in section A.

### Medium

| # | Screen | Role | Viewport | Finding | Evidence | Recommended fix |
|---|---|---|---|---|---|---|
| M1 | Site dashboard — Table view | External, Office | 1280, 768 | The table has a minimum width of 1120 px. With the desktop sidebar the region is only 930 px wide at 1280 (675 px at 768). So the **Actions column ("Call Off") is cut off** and only reachable by scrolling sideways, even on a standard desktop. The plot column is not sticky. | Browser 1280: `tableScroll {sw:1120, cw:930}`. `resources/views/site-workspace/styles.blade.php:35` (`.sw-table{…min-width:1120px…}`). | Make the Plot column and the actions sticky, or bring the table within ~900 px, e.g. by merging the service columns. |
| M2 | Site dashboard — plot count | External, Office | all | The badge "**46 plots shown**" appears while only 15 plots are rendered on the page. | Browser 390: 15 `.sw-card`, pager "« Previous Next »". `resources/views/portal/site-dashboard.blade.php:130` (`{{ $plots->total() }} … shown`); `app/Services/PlotOverviewQueryService.php:97` (`paginate(15)`). | Change it to "Showing 1–15 of 46 plots". |
| M3 | Site dashboard — two filter systems | External | all | There are **two separate filter UIs**: the header "Switch site & filters" (with "Advanced filters" and "Show Completed") and the in-page "Search & Filters". Each has its own Sort. The hint "Use Show Completed in Filters" points at a control that only exists in the other panel. | Browser 320/390. `resources/views/portal/site-dashboard.blade.php:2-90` (page tools), `:131-150` (Search & Filters), `:151` (hint text). | Merge them into one panel (UX03), or reword the hint so it names the actual control. |
| M4 | Customer-facing "Approved" wording | External | all | Current customer-facing text still says "Approved", which brief §12 and AGENTS §14 forbid for current state. Affected: the request history event label, the notification type label and message, and the withdraw confirmation. | `app/Enums/CallOffHistoryEventType.php:31` ("Approved"); `app/Enums/PortalNotificationType.php:20` ("Call-off approved"); `app/Http/Controllers/PortalNotificationController.php:144` ("Fenster approved the … call-off"); `resources/views/portal/call-offs/lifecycle-confirm.blade.php:14` ("Approved call-offs cannot be withdrawn"). | Use "Date Agreed" wording. Keep legacy events labelled as historical only. |
| M5 | Plot details vs listing — same state, different words | External | 390 | The listing shows **"Called Off — Awaiting Date"** for Plot 001 Cavity Closers. Plot details and the request page show **"Awaiting Fenster"**, and the alternative-date state shows "Awaiting Site User" to the site user themselves. The trash and dashboard request lists show raw request statuses ("Submitted", "Rejected", "Amendment On Hold"). | Browser 390 (Plot 001). `app/Services/CallOffDateViewService.php:64` (falls back to `$request->status->label()`); `app/Enums/CallOffRequestStatus.php:20-28`. | Map every customer-facing state to the approved service-presentation set. For the site user's own action, say "Your response needed". |
| M6 | All timestamps | All | all | **Times are shown in UTC with no label.** Customer and Office pages show e.g. "21 Oct 2026, 14:52" while UK time is 15:52 (BST). Only the Office plot card says "UTC". | Browser: notification "14:52" at local 15:52; submitted times "14:42:06". `config/app.php:68` (`'timezone' => 'UTC'`); `resources/views/site-workspace/cards.blade.php:11` (the one place with a "UTC" label). There is no DECISIONS/brief entry on timezone. | Decide a display timezone (Europe/London) and convert when rendering, or label times as UTC. |
| M7 | Missing product facts shown as "Not supplied" | External, Office | all | When no product row exists, the plot card says **Windows "Not supplied" / Doors "Not supplied"**. A customer can read that as "Fenster is not supplying windows". AGENTS §24 says unrepresented means *no assertion*. Plot details shows "—" for the same data, so the two screens also disagree. | Browser 320/390 (card) vs 390 (plot details "—"). `app/Services/SiteWorkspaceQueryService.php:137`; `resources/views/site-workspace/card-content.blade.php:9`. | Use the same neutral "—" / "Not recorded" on both screens. |
| M8 | Form errors | External, Office | all | Errors are not wired to their fields: there is no `aria-invalid` and no `aria-describedby` pointing at the error anywhere. Several flows show only `$errors->first()`. Default Laravel messages name internal fields: "The plots field is required."; and **"The customer response field is required."** under a field labelled "Reason for rejecting this date". | Browser 390 (reject with an empty reason; call-off with no plots, where the error is rendered at the bottom of a ~5,100 px page). `app/Http/Requests/RespondToAlternativeCallOffDateRequest.php:18` (no custom message); `resources/views/portal/call-offs/create.blade.php:7`. | Add `aria-invalid`/`aria-describedby`, use custom messages that match the labels, and put an error summary at the top that links to the fields. |
| M9 | Double-submit on final actions | External, Office | all | Several final submit buttons don't disable or show a busy state while submitting: "Submit amendment", resubmit confirm, the matrix step, user create/edit, assign user, delete/purge, upload/analyse/preview/approve/commit. Only some forms use the `submitting` pattern. Server-side idempotency was not re-verified here. | `resources/views/portal/call-offs/amendments/review.blade.php:20`; `office/form.blade.php:20,35,60` (the shared pattern that exists); the others are listed by the code review. | Use one shared submit-once / `aria-busy` helper on every state-changing form. |
| M10 | Office Site Details — destructive buttons | Office | 390, 1280 | "Delete permanently" and "Purge demo/test data" use the ordinary blue `sw-button`. The Customer page uses `destructive-button` for the same actions. | `resources/views/office/sites/show.blade.php:30,32` vs `office/customers/show.blade.php:80-81`. | Use the destructive style, and keep these apart from everyday actions. |
| M11 | Office Site → Users — Remove Access | Office | all | Remove Access uses the browser's own `confirm('Remove this user’s access to this site?')`. That doesn't name the user, and every other destructive action has its own confirmation page. | `resources/views/office/sites/users.blade.php:9`. | Use a confirmation step that names the user and site, plus sr-only text on the button. |
| M13 | Site dashboard / Office Site Details — default plot view | External, Office | all | **Requirement from Josh (21 Oct 2026): the plot listing should default to Table, not Cards.** Today a site opens in Cards unless it has 100 or more plots. After that, each user's last choice is stored in their browser and reused. No DECISIONS or brief entry records the 100-plot threshold. Meadow View (46 plots) opened in Cards at every width. | Browser 390/1280. `resources/views/site-workspace/plot-list.blade.php:2` (`$plotViewDefault = $siteSummary['total'] >= 100 ? 'table' : 'cards'`), `:25-35` (localStorage key `customerapp:site-plots:view:{user uuid}`). | Default to `table` for every site. Record the decision in `DECISIONS.md`. Fix M1 first or alongside, so the default view isn't cut off at desktop width. Also decide two things: (a) whether a stored "Cards" choice should still win; (b) whether phones (≤390 px, where the 1120 px table scrolls sideways) should also default to Table. |
| M14 | New Call Off — plot list | External | 375–390 | **Reported by Josh (21 Oct 2026).** Opening New Call Off from a plot's "Call Off" button still lists every plot on the site (46 here, 2 per row on desktop, one per row on phones), with only the chosen plot ticked. The user has to scroll past roughly 4,000 px of plots to reach Services and requested dates. | Browser 375 px (`/portal/call-offs/new?plots[0]=…`, page height 5,166 px). `resources/views/portal/call-offs/create.blade.php:12-20`. | When plots arrive pre-selected, show them as a summary and fold the full list behind "Change plots". Use a compact multi-column grid when nothing is pre-selected. |
| M12 | Undo notice | External | all | `role="status"` wraps an `x-text` countdown, so screen readers announce it every second. The five-second Undo window is also short for keyboard and screen-reader users. | `resources/views/portal/call-offs/_undo-notice.blade.php:4,9`. | Move the countdown out of the live region and announce once. Confirm the window length against DECISIONS. |

### Low

| # | Screen | Role | Viewport | Finding | Evidence | Recommended fix |
|---|---|---|---|---|---|---|
| L1 | Card view | External | all | Each plot card is one `<a>` that contains a heading, badges, products and four service states, so the link's spoken name is the whole card. | `resources/views/site-workspace/cards.blade.php:18`; `card-content.blade.php:2`. | Make only the plot title the link, or give the link an `aria-label` such as "Open Plot 001". |
| L2 | Table view | External | all | The plot-title link is 18 px tall. The table "Call off" button is 40 px. The checkbox and the link sit in one `<label>`. | Browser 1280 (`A 47x18 Plot 001`, `Call off … :40`). `site-workspace/table.blade.php:27-30`; `styles.blade.php:35` (`min-height:40px`). | Make targets at least 44 px and keep the link out of the checkbox label. |
| L3 | Raw ISO dates | External, Office | 320/390 | Some pages print ISO dates such as "2026-11-25": the amendment review ("New requested date 2026-11-25"), request history ("Original requested date — 2026-11-23") and the Office flash ("…proposed for 2026-11-30."). | Browser. `resources/views/portal/call-offs/amendments/review.blade.php:8`; `portal/call-offs/show.blade.php:108`; `app/Http/Controllers/CallOffDateNegotiationController.php:66`. | Format as `j M Y` everywhere. |
| L4 | Internal and jargon terms shown to customers | External | all | Customers see internal terms: "41 outstanding **projected** plots" (site selection), "RedZebra" (plot details and amendment review), "combinations" (call-off modal), "Source update" / "Source completion recorded". | Browser 320 (site selection), 390. `resources/views/portal/plots/show.blade.php:99`; `portal/call-offs/amendments/review.blade.php:15`. | Use customer language, e.g. "plots", "Fenster's system", "plot/service choices". |
| L5 | Same concept, several names | External | all | The date-change action has four names: "Amend request", "Request Date Change", "Date amendment", "Date change requested". "Call Off", "call-off" and "Call-Offs" are all used. Office Amendments shows "Awaiting Office response" and "Awaiting Fenster" for the same state. | Code-review list; browser 390 (Office Amendments). | Agree one glossary entry for each concept and apply it everywhere. |
| L6 | Success vs error flash styling | External | 390 | "Call-off submitted…" success uses the same amber `admin-notice` style as errors. | Browser 390. `resources/views/portal/site-dashboard.blade.php:101`. | Use green/neutral for success and red for errors. |
| L7 | Error pages | All | all | 403 and 404 pages are the unbranded framework defaults ("403 Forbidden" / "404 Not Found"), with no navigation or way back. | Browser 320 (tampered site switch; cross-customer plot). | Add branded `errors/403` and `errors/404` views with a link to the dashboard. |
| L8 | Single-site users | External | 320 | A user with one assigned site still has to pick it on "Choose your site". | Browser 320 (Northgate). | Select the site automatically when exactly one active site is assigned. |
| L9 | Call-off form | External | 390 | Date fields for services that aren't ticked (Snagging, CML) stay visible and enabled. | Browser 390. `resources/views/portal/call-offs/create.blade.php` (services fieldset). | Disable or hide the date until its service is ticked. |
| L10 | Pagination control | External, Office | 390 | The Laravel default simple pager ("« Previous / Next »") is ~38 px tall, includes `dark:` classes and is styled differently from the rest of the app. | Browser 390 (nav markup). | Publish a branded pagination view with 44 px targets and a page count. |
| L11 | Short-height sidebar affordance | All | 1280×420 | The navigation scrolls correctly (content 524 px in a 229 px area), but nothing shows that more items are below. | Browser 1280×420. `layouts/partials/portal-sidebar.blade.php:20`. | Add a fade or shadow at the overflow edge. |
| L12 | Imports page density | Office | 390 | The history table, badges and actions use 10–11 px text. "Filter history" is 40 px and the "Export details" summary is 30 px tall. The older-imports wrapper uses `overflow:hidden`, so wide tables clip at 1101–1549 px. | Browser 390. `resources/views/office/pilot-import/workspace-styles.blade.php:28-35,300-308,358-387`. | Use at least 12–13 px text and 44 px targets, and `overflow-x:auto` on the wrapper. |
| L13 | Leftover pilot wording | Office | all | Screens still show "WEEKEND PILOT", "— weekend pilot" (sr-only), "Import Studio is being prepared" empty state, raw codes such as "EXACT CUSTOMER NEW SITE", "Source namespace", and hashes or epochs in disclosures. | `office/pilot-import/show.blade.php:6`; `office/sites/show.blade.php:23`; `office/sites/imports.blade.php:4`; `office/pilot-import/source-sites.blade.php:20`; `office/sites/source.blade.php:13`. | Replace them with plain Office wording. |
| L14 | Office dashboard | Office | 1280 | The "Amendments" card is pink/red with a red "0" badge while nothing needs attention. The "Import reviews" card shows a blue "–" badge. | Browser 1280. `office/dashboard/index.blade.php`. | Use neutral styling when the count is 0. |
| L15 | Request history | External | 390 | One proposal shows up as two history entries ("Alternative date proposed" and "Fenster proposed 30 Nov 2026"), each repeating the message. | Browser 390. `resources/views/portal/call-offs/show.blade.php:105-110`. | Collapse them into one customer-facing entry. |
| L16 | Visual consistency | All | all | There are eight page-specific inline `<style>` blocks, each with its own button set (`sw-button`, `am-button`, `imports-primary`, `primary-button`, `od-*`). Focus colours vary (#0369a1, #075bed, #087db2, #087caf). The notification bell emits its own `<style>` on every page. `.status-blue` is referenced but not defined. | `resources/views/site-workspace/styles.blade.php`, `office/amendments/styles.blade.php`, `office/pilot-import/workspace-styles.blade.php`, `layouts/partials/notification-bell.blade.php:2`, `app/Enums/CallOffRequestStatus.php:41` vs `resources/css/app.css:61-65`. | Move shared button, badge and focus tokens into `app.css` components, as a separate design-system task. |
| L17 | Minor ARIA | All | all | `aria-label="Your account"` sits on a plain `div`, and `aria-label` on a `<span>` badge; both are ignored. The new call-off stepper marks the current step by colour only. | `layouts/partials/portal-sidebar.blade.php:129,139`; `portal/call-offs/create.blade.php:9`. | Use `<section aria-label>` or sr-only text, and `aria-current="step"`. |
| L18 | Forgot password | Guest | 320 | After an error the email field is cleared. Sign in keeps it. | Browser 320. | Repopulate the field with `old('email')`. |

---

## D. Top 5 fixes worth doing next

1. **Stop password reset revealing which accounts exist (A1).** Return a neutral message and throttle `POST /forgot-password`. This is small and security-relevant, and it is not covered by SEC01.
2. **Fix the customer-facing status wording (M4 + M5).** Remove "Approved" and show the approved service states ("Called Off — Awaiting Date", "On Hold — Date Change Requested", …) on plot details, request pages, trash and notifications. Today the customer sees different words for the same state depending on the screen.
3. **Make Table the default plot view and usable at desktop width (M13 + M1), and correct the plot count (M2).**
   - Default every site to Table, which is Josh's requirement. Today Table is the default only for sites with 100 or more plots.
   - Use a sticky Plot column and actions, or a narrower table, so "Call Off" isn't cut off at 1280 px.
   - Change the badge to "Showing 1–15 of 46".
4. **Merge the two filter panels on the site dashboard (M3)** so "Show Completed" and Sort exist in one place and the hint is true.
5. **Settle how dates and times are shown (M6 + L3).** Decide UK local time or a "UTC" label, and use `j M Y` for every date, including amendment review, history and flash messages.

Close behind these: accessible form errors (M8), a shared submit-once helper (M9), and the destructive-action styling and confirmations (M10, M11, A2).

---

## E. Follow-up implemented on a feature branch (not merged, not pushed)

Branch `codex/customerapp-plot-list-table-default`, cut from `origin/main` `8629815`, in the worktree `C:\Users\madas\Documents\customerapp\plot-list-table-default-20261021`. It implements:

- **M13:** the default plot view now depends on screen width, as revised by Josh and recorded in DEC-088 (commit `416b3eb`). A view the user has already saved in that browser still wins.
  - Phones and narrow screens below 1024 px open in Cards, because the 1120 px table does not fit a phone.
  - Desktop, from 1024 px up, opens in Table.
- **M1:** the Plot column is pinned on the left, and from 768 px the Actions column is pinned on the right.
- **M14:** dashboard-selected plots are shown as a summary, with the full list behind "Change plots"; the list uses a compact grid when nothing is pre-selected.

- **Plot sort** (commit `956cd14`, decision DEC-089):
  - The options now read "Low to High" / "High to Low" instead of "Reference A–Z" / "Z–A".
  - Plots are ordered by plot number, so "Plot 999" comes before "Plot 1000".
  - New Call Off lists plots in the same order.

The first decision is recorded as DEC-087 on that branch.

Commit `c357174`. Nothing was merged or pushed.

Checks:

- **Full suite:** 2,101 tests; 2,007 passed and 2 failed.
  - Both failures are `tests/Feature/WaldPilot01/WeekendPilotTest.php` "legacy manifest" datasets.
  - Those same 2 tests also fail on unchanged `8629815`, so they predate this change.
  - The remaining tests did not run in this environment (most likely the MySQL-only checks).
- **Focused New Call Off and workspace tests:** 80/80 passed.
- **Tooling:**
  - `pint --test`, `composer validate --strict`, `npm audit --omit=dev` (0 vulnerabilities) and `git diff --check` all passed.
  - `composer audit` reports 6 existing advisories in 5 packages from `main`'s unchanged lockfile: filament, laravel/framework, league/commonmark, league/flysystem, symfony/http-foundation.

Measured in the browser at 375 px:

- **New Call Off from a plot's "Call Off" button:** Services now starts at 655 px instead of about 4,000 px, and the page is 1,986 px tall instead of 5,166.
- **"Request a call-off" with no plots pre-selected:** the page is 3,213 px tall, down from 5,166, but the plot list still has to be scrolled.
- **Site plot table at 1280 px:** "Call Off" is visible without scrolling sideways.

## F. Notes and limitations

- **Wald review screens were not tested with data.** The import review steps and Reconciliation were reviewed from code only (A3, L12, L13). A follow-up should run them on a disposable fictional workbook.
- **Notifications are not generated for preview users.** This is by design (`app/Services/PortalNotificationService.php:40`). The bell and centre were therefore tested with a non-preview fictional user.
- **Finishing Foreman** was checked for navigation, scoping and the trash empty state. It uses the same Version 1 Site User model as the other two external roles.
- **Production was not touched.** Live signed-in checks of UX02/UX03 remain outstanding, as recorded in the release notes. This audit used the same code (`8629815`) locally.
- **Git state:** the audit itself changed no application code. This report is committed on `codex/customerapp-plot-list-table-default` together with the section E follow-up commits, and that branch is pushed to `origin`. Nothing was merged to `main` or deployed. There is no migration or database impact.
