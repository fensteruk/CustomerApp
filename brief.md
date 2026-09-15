# Fenster Customer Portal — Current Product and Integration Brief

Last updated: 15 September 2026.
Authority: CUSTOMER-WALD-PILOT01/02, CUSTOMER-WALD-CUSTAPP2-01, DEC-064, current management product decisions and the
preserved decision/history records. This is one current contract, not a second brief
appended below superseded requirements.

## Release truth

Production serves corrected commit-boundary release
`89768986a1a32d4258b5deebdf3012584acd6a6c`. `WALD_IMPORT_AVAILABLE=false`, so effective
production pilot availability is OFF. The unexplained environment-gate change is under a separate
Integration/DevOps investigation. No current source-compatibility work may alter that state.

CUSTOMER-WALD-CUSTAPP2-01 is implemented only on a non-deploying feature branch. The approved
private workbook's aggregate structure qualifies locally on SQLite and MySQL, except one selected
site correctly blocks on conflicting explicit product evidence. This is PARTIAL qualification,
not a production, release or source-data-correction claim.

The synthetic Import Studio is a local/testing-only, default-off, precomputed demonstration.
Its routes are absent in production even if the flag is set, it writes nothing and it has no
upload, binding, Wald invocation or commit endpoint. Production navigation contains only real
destinations: Review Requests, Customers and Notifications for authorised Office Staff.

WALD02–05 form the accepted backend. When explicitly re-enabled after an approved corrected
release and fresh recovery point, the temporary live pilot allows authorised Office Staff to
upload one private workbook, choose exactly one detected source site, bind it exactly, review,
preview and atomically commit that site as partial-only. Effective access requires the
environment kill switch and audited application setting; both default off. It has no RedZebra
API, automatic purge/splitting, customer access, SiteApp dependency or writeback. Full WALD06
orchestration remains paused.

## Product boundary

CustomerApp is a separate customer-facing communication and request application. It
shows authorised sites/plots/services, collects requested dates, supports agreement and
amendment, and preserves customer-safe progress/history. SiteApp owns operational work.

Do not reproduce SiteApp stages, dependencies, readiness, labour/manufacturing planning,
trade management, internal statuses, administration or direct database/runtime access.
The direction remains source → Portal only. There is no spreadsheet/SiteApp writeback.

## Access

Four roles remain distinct: Site Manager, Assistant Site Manager, Finishing Foreman and
Fenster Office Staff. The three external roles have identical Version 1 capabilities,
require active accounts, a customer organisation and assigned sites, and may see all
authorised requests for the active site, not only their own submissions.

Active Office Staff are globally scoped by role and may have a null customer organisation.
Null organisation alone never grants access. Office may review but cannot initiate customer
amendments. Customers cannot self-register or administer users. Normal framework login,
CSRF and current server-side authorisation remain mandatory. Development role preview is
local/testing only and cannot grant production access. Future QR identifies a site, not a user.

## Plots and services

The exact order is Cavity Closers, Windows, Snagging, CML. Services are independent,
not operational stages. CML's full customer-facing expansion remains unconfirmed.

The dashboard is plot-centric, authorised, searchable/filterable and paginated. Completed
plots remain retained and are hidden by default with Show Completed available. Plot Details
shows the correct site/plot, non-zero authorised products, four services and safe history.

Service presentation: Nothing Called Off; Called Off — Awaiting Date; Date Agreed;
On Hold — Date Change Requested; Completed. Completed dates are shown only when supported
by actual source evidence. No Portal requested/agreed/proposed date manufactures completion.

Overall presentation: Nothing Called Off, Call-Offs In Progress, Dates Agreed,
Partially Completed, Fully Completed. An open amendment contributes to Call-Offs In Progress.
Partial/full source completion retains presentation precedence. There is no separate overall
Amendment In Progress label.

## Call-offs, dates and initial agreement

One submission batch belongs to one active site and may contain multiple plots/services,
with different requested dates per service. Each request owns its date/state/history.
Ineligible combinations remain explainable; only eligible authorised combinations persist.
No more than one active request per plot/service. Diverged decisions cannot be overwritten
by a later batch action.

The normal customer window is four weeks, or five weeks for exact positive BF on that plot;
the underlying three/four-week minimum includes a one-week customer buffer. Normal dates
are future weekdays and at most six months ahead. UK holiday exclusion is an approved target,
but no provider/dataset is approved or claimed live; current behaviour is weekday-only.

Request Earlier Date requires a reason and prominent flag. Office acceptance inside normal
lead time requires explicit acknowledgement. Date decisions revalidate current eligibility.

Site request → Awaiting Fenster → Office accepts requested date → Date Agreed.
Alternatively Office proposes → Awaiting Site User → authorised Site User accepts → Date Agreed,
or rejects with mandatory reason → Awaiting Fenster. Negotiation may repeat. Any currently
authorised assigned Site User may respond; actual responder attribution remains truthful.
Use Date Agreed, not Approved. Preserve legacy Approved/Rejected records and their history.

Withdrawal is available before Date Agreed, not after. Eligible withdrawal/trash/restoration
retains the existing five-second actor-bound Undo and seven-day customer Trash window.
Expiry hides customer Trash, not audit history. Bulk undo/restoration remains atomic.

## Sprint 3F amendments included in RC1

After Date Agreed, a Site User may request a new date on the existing request, using a distinct
amendment cycle in the existing negotiation engine. The previous agreed date remains stored
and visible as history, not a current confirmed date while On Hold. The original requested
date is never overwritten. No fixed amendment cutoff is introduced.

Approved reasons: Site Not Ready; Programme Change; Access Issue; Customer Requested Change;
Materials / Availability; Weather; Other. Other requires Additional information; other
explanations are optional. Maximum 2,000 characters, validated and trimmed at the domain boundary.
Persist stable code, label and actual requester snapshots; display friendly labels, not codes.

A request within three working days of the old agreed date, including late requests, is
Urgent / Late Amendment. This is a warning, not a prohibition. The existing weekday/provider
boundary applies. Review/confirmation is server-held, actor/site/state-bound and single-use.

Office accepts the new requested date or proposes an alternative. Site response uses the
same authorised negotiation path. A new agreement becomes current while old agreements,
proposals, reasons and actual actors remain truthful ordered history. Early-date Office
acknowledgement remains required. Stale cycles/proposals cannot act again.

Source completion wins over an open amendment and closes it without completion notification.
A later source reversal does not automatically reopen a closed amendment or obsolete date.
Canonical service → request → cycle → proposal → history locking and the corrected Office
pending-alternative current read remain mandatory.

Failure/cancellation/old-date reinstatement and Fenster-originated Portal date changes remain
deferred, not invented. No current product decision blocks the implemented Sprint 3F loop.

## Source semantics and release boundary

The approved current identity model separates Plot, Source Row and Visit. Plot is exact active
source-site binding plus normalized Plot Ref; Source Row is source namespace plus CallNo; Visit is
Source Row plus a recognized non-null Call Type. A blank Call Type is valid null and may contribute
valid plot/product facts, but it cannot create a visit/service/request, mutate completion or map an
operational date into Portal-owned dates. A later blank in a partial export cannot erase an
established visit. A recognized type change for the same Source Row blocks.

Product facts consolidate per plot only when compatible. Unrepresented makes no assertion,
explicit zero is exact, equal explicit values agree and conflicting explicit values block the
complete selected-site unit. Logical composite tables may join compatible horizontal fragments,
but must retain original cell/fragment provenance and require clarification when competing
compositions remain. CallNo headers use exact normalized `call` + `no` or `call` + `number` tokens,
never fuzzy matching.

The CUSTAPP2 profile recognizes PC1, CC1 and CM1 only. It does not inherit checksum-scoped
corrections from the earlier pilot workbook. One explicitly selected, exactly bound site remains
the review/commit unit; automatic multi-site orchestration remains WALD06 work.

The existing transport-independent Sprint 3B importer remains unchanged in RC1 to preserve
the approved release scope and tested concurrency paths. No upload, new binding, saved profile,
live feed, import activation or semantic migration is included.

Approved future import dictionary (not a claim that RC1's retained legacy mapper implements it):
PC1 = Plot Install = Windows; CC1 = Cavity Closer 1 = Cavity Closers; CM1 = Revisit 1 and
CM2 = Revisit 2 are CML revisits; CML = CML Call Off = CML. Literal CC! is invalid and never
silently auto-corrected. Unknown meanings block the dependent import. No Snagging code is invented.

Approved source completion is complete = Yes for the individual visit, without inventing a date.
Items Ordered Status and Site Value are ignored. Plot To Be Installed is operational PC1 arrival
evidence, never a Portal date. A permanent source Site ID is preferred; exact name is transitional
explicit mapping only. Every export defaults partial/filtered; absence never proves deletion.

Approved future customer totals: Windows = VS + TT + BAY + ALI + AOV + FI;
Doors = PSU + PSG + CDF + CDU + CDG + PSP + BF. CAS/FLU/PFD/GLS/WP/MISC are excluded from those
future totals; exact positive BF remains separately identifiable for lead time. Omitted
columns/rows cannot imply deletion/zero. These source corrections belong to the excluded,
separately approved import programme; do not silently enable the retained legacy importer
against a real workbook or present its historical mappings as current dictionary authority.

## History, notifications and non-goals

Preserve UUIDs, original/current dates, exact actor/role/time and immutable before/after history.
Private Office reasons, source metadata, conflict keys and internal errors never reach customers.
Notifications derive from committed event-time truth, reauthorise recipients/destinations and
remain idempotent. A later completion cannot erase a genuinely committed Date Agreed event.
Completion sends no notification. Reading/dismissing a notification never changes domain history.

This RC adds no email/Resend, persistent worker, scheduler, automatic purge, API/writeback,
real Import Studio, real Wald runtime, calendar/PDF, attachments, reminders, SSO/MFA or
external AI. Customer/site administration is included; plot and assigned-user views remain
read-only. No production queue/provider is assumed.

## QA and recovery

The production base has 12 migrations. This RC retains them byte-for-byte and adds only
`2026_09_09_000014_add_customer_site_administration.php`, producing 13 total migrations and
no Wald migration. The administration migration is additive, backfills active UUID-bearing
customer/site rows and preserves relationships and dependent records. Before traffic is reopened,
recovery may restore both a fresh pre-deployment database snapshot and the previously verified
application release. After traffic is reopened or any Sprint 3F write occurs, old main is not
compatible recovery: keep RC-generation-compatible code and roll forward from the exact
deployed RC or a descendant. A full pre-deployment database restore after reopening is a major
incident/business data-loss decision, not routine rollback.

Require focused/full SQLite, isolated MySQL 8.4 migrations/upgrade/concurrency, strict Composer
validation/audit, Pint and build before candidate freeze. Dedicated release QA then runs against
the exact frozen application SHA, including Office/Site User, mobile/accessibility,
history/notifications, security and repeated source/Office races. The approved production
sequence is backup, capture current release/schema evidence, maintenance mode, deploy exact
approved RC, forward migrate/cache refresh, read-only smoke, then reopen. No main push or
deployment occurs without separate approval. See the
[next-release RC1 build record](documentation/customerapp-next-release-rc1-build-report-2026-09-10.md) and
[RC1 recovery strategy](documentation/customer-release03a-rc1-recovery-strategy-2026-09-09.md).
