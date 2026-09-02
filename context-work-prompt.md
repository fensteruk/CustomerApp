# Customer Portal — Management Requirements Consolidation Prompt

Update the Customer Portal project documentation following a detailed management requirements session held on 20 August 2026.

Project:

`C:\Users\JoshO\Documents\CustomerApp`

This is a **DOCUMENTATION-ONLY** task.

Do not change production application code. Do not create migrations. Do not modify routes, controllers, models, views, JavaScript, CSS or tests. Do not implement any requirements yet.

The objective is to preserve today's confirmed management decisions accurately, correct misunderstandings in the existing project brief, and ensure future agents are required to read the new context document.

## Before making changes

Read the current project documentation in this order:

1. `AGENTS.md`
2. `brief.md`
3. `DECISIONS.md`
4. `current_sprint.md`
5. `ROADMAP.md`
6. `HANDOVER.md`
7. `documentation/call-off-domain.md`
8. `documentation/notification-domain.md`
9. `documentation/full-site-audit-2026-08-19.md`
10. `documentation/sprint-2a-company-test-readiness.md`
11. `documentation/sprint-2a-company-test-readiness-qa-report-2026-08-19.md`

Understand the existing implementation and previous decisions. Treat the requirements below as the latest authoritative management clarification where they conflict with older assumptions.

## Create the authoritative context file

Create:

`documentation/management-requirements-2026-08-20.md`

Use this title:

`# Customer Portal — Management Requirements`

`_Last Updated: 20 August 2026_`

Explain at the top that this document records requirements confirmed directly with Fenster management; supersedes earlier assumptions where explicitly contradictory; does not mean every requirement is currently implemented; and requires future planning and implementation agents to distinguish **Confirmed requirement**, **Current implementation**, and **Future implementation**.

Record that SiteApp remains the operational source of truth and the Customer Portal remains a separate customer-facing communication/request system.

## Confirmed requirements

### 1. Core purpose

The Customer Portal is the customer-facing communication and call-off system. SiteApp/Excel remains the source of operational plot, product and completion information. The Portal displays authorised site/plot information, accepts customer call-offs, manages date agreement, manages customer-requested amendments, displays progress/completion, maintains customer-safe history, and provides notifications and agreed-work calendars. It must not become Fenster's operational manufacturing or planning system.

### 2. Roles and permissions

External role names remain **Site Manager**, **Assistant Site Manager**, and **Finishing Foreman**. They remain distinct job titles for display, identity, reporting and history, but technically form one Site User permission group with the same Portal permissions.

External Site Users are assigned to sites, not individual plots; may be assigned to one or multiple sites; can see all plots belonging to assigned sites; can create/manage authorised call-offs for assigned sites; and cannot access unassigned sites.

Fenster staff can see all customers, sites, plots and customer call-offs; manage customer accounts; assign/remove users from sites; and share the same internal permission level for this version. The previous assumption that Fenster Office Staff are restricted to assigned sites is superseded. No plot-level user assignments are required.

Fenster staff create and manage customer/site-user accounts. Customers do not self-register or administer users. When a site user leaves, Fenster deactivates the account while historical activity remains and the user's name/role remains visible in history.

### 3. Authentication

The initial live version uses username/email plus password, with secure authentication and sessions and no public registration. MFA and SSO remain future enhancements.

### 4. Customer and site structure

One customer/company may have multiple developments/sites. A Site User may be assigned to one or multiple sites belonging to that customer and sees all plots on assigned sites. Fenster staff see all customers and sites.

QR codes, when introduced, identify a site rather than a user. An authorised logged-in user goes directly to the site's dashboard; an unauthenticated user logs in and then continues to that site; an unauthorised user is denied safely. The QR code never grants permission. Manual site selection remains available for assigned sites.

### 5. Plot-centric site overview

The main Site Dashboard should evolve into a plot-centric overview similar to the management prototype. Each row represents one plot. Required service columns, in this exact order, are:

1. Cavity Closers
2. Windows
3. Snagging
4. CML

Also include Plot Reference, Overall Status and appropriate actions.

Each service cell has four principal customer-facing states: **Nothing / Not Called Off**, **Called Off — Awaiting Date**, **Date Agreed — show agreed date**, and **Completed — show actual Completed Date**.

Snagging is a full customer-callable service and follows the same core date-request/date-agreement process as the other three services.

### 6. Fully completed plots

A plot is Fully Completed only when all four services are Completed. Highlight the entire plot row, keep it available indefinitely, hide fully completed plots by default, and provide Show Completed/filter functionality. Never automatically archive or delete completed plots.

### 7. Overall plot status

Use: **Nothing Called Off**, **Call-Offs In Progress**, **Dates Agreed**, **Partially Completed**, and **Fully Completed**.

- Nothing Called Off: nothing has been called off.
- Call-Offs In Progress: one or more active call-offs remain unresolved/awaiting date, unless the service is already Completed.
- Dates Agreed: at least one service is Date Agreed or Completed and no currently called-off service remains Awaiting Date; services not yet called off do not prevent this status.
- Partially Completed: at least one service is Completed but not all four are; completion takes precedence over unresolved call-offs for the overall label.
- Fully Completed: all four services are Completed.

### 8. Status colours and accessibility

Use a consistent visual status system for Not Called Off, Called Off — Awaiting Date, Date Agreed and Completed. Use colour, text and icons/labels where appropriate; colour must never be the only indicator. Fully Completed additionally highlights the whole plot row.

The calendar is an exception: entries use service-specific colours for Cavity Closers, Windows, Snagging and CML. Hover/focus explains the service/status and colour meaning. On mobile, the first tap opens the information summary and **View Details** opens the full record.

### 9. Service order

Display/physical work sequence is **Cavity Closers → Windows → Snagging → CML**. This is not a call-off dependency. Customers may call off any service at any time subject to eligibility/date rules. Do not implement workflow-stage dependencies between the services.

### 10. Starting call-offs

Each plot row has a Call Off action. Users can select multiple plots directly from the overview and choose Call Off Selected. Keep a separate New Call Off page as an alternative route. Both entry points use the same business rules.

### 11. Multi-plot and multi-service call-offs

The earlier Version 1 assumption of one service and one date per batch is superseded. Users can select one or multiple plots, one or multiple services, and enter a different requested date for each selected service. By default each selected service applies to every selected plot; users can untick individual plot/service combinations before submission.

Already called-off or ineligible combinations remain visible but disabled with an explanation. Never silently remove unavailable combinations.

### 12. Products and quantities

Product information comes automatically from Excel/SiteApp. Customers do not enter or edit quantities. Following the confirmed 2 September 2026 source-dictionary correction, customer-facing output contains only non-zero Total Windows and Total Doors from the approved product groups. Individual codes remain Office/audit detail; excluded codes such as CAS, PFD and MISC are not customer product types. The current dictionary is `documentation/siteapp-import-data-dictionary.md`.

Do not clutter the main overview with products. Show them on Plot Details and during relevant call-off selection/review.

### 13. Plot Details

Provide a dedicated Plot Details page. Prominently show Overall Status, then non-zero product quantities and the four service statuses. Keep it relatively simple. Opening a service shows full customer-safe history, dates, attachments and relevant actions.

### 14. Lead times and dates

Normal minimum manufacturing/service lead time is three weeks for standard products and four weeks only where exact source code `BF` has a positive quantity. The customer-facing normal request window includes an additional one-week buffer: the earliest normal date is four weeks normally and five weeks only for positive `BF`. Other door codes and Total Doors do not trigger the BF rule.

Calculate lead time per plot/service. In bulk call-offs, do not force every plot to use the longest lead time; calculate independently and show the earliest normal date for each plot/service.

Normal requested dates are Monday–Friday only, exclude UK bank holidays, and may be no more than six months ahead. The normal date picker prevents dates before the calculated earliest date.

Provide **Request Earlier Date** as an explicit exception route with a mandatory reason/message, prominent flagging to Fenster, audit history, and Fenster actions **Accept Date** or **Propose Alternative Date**. Accepting an inside-lead-time date requires additional confirmation acknowledging the exception.

### 15. Date review and negotiation

After a customer submits a requested date, Fenster can **Accept Date** or **Propose Alternative Date**. Acceptance immediately becomes **Date Agreed** and needs no further customer confirmation.

If an alternative is proposed, the customer explicitly **Accepts alternative** or **Rejects alternative with a mandatory reason**. Acceptance makes the date Date Agreed. Rejection leaves the request alive and allows another alternative; negotiation continues until a date is agreed or the request is withdrawn. A rejected proposed date is not a new rejected call-off or resubmission.

The original submitter is the primary action recipient, but any currently authorised Site User assigned to the site may respond. History records the actual responder.

Use **Date Agreed**, not Approved, as the final customer-facing state. Review/migrate existing Approved terminology accordingly.

### 16. Withdrawal

Customers can withdraw a call-off at any point before Date Agreed, including while waiting to respond to a Fenster alternative date. Once Date Agreed, withdrawal is unavailable and changes use the Amendment process. Existing Undo/Trash history requirements remain unless explicitly superseded.

### 17. Amendments

After Date Agreed, customers can request a change by supplying a new requested date, a predefined amendment reason, and an optional explanation. The exact predefined reason list remains to be finalised. There is no fixed amendment cutoff; amendments may be requested close to the agreed date.

Fenster can Accept amended date or Propose Alternative Date, using the same negotiation process as the original call-off. Fenster cannot initiate a Portal change to a Date Agreed date; Fenster-originated changes are handled outside the Portal.

When an amendment is submitted, the existing Date Agreed becomes **On Hold** rather than being erased. If a new date is agreed it becomes Date Agreed and the previous date remains in history. If agreement fails, Fenster staff decide whether the previous On Hold date can be reinstated; never reinstate automatically.

An amendment submitted within three working days of the current Date Agreed is flagged **Urgent / Late Amendment**. This is a warning/priority indicator, not a prohibition.

### 18. Completion authority and mappings

Completion comes from Excel/SiteApp only. Fenster staff cannot manually mark a service Completed in the Portal. Normally use Job Stage plus Completed Date. Confirmed mappings:

- Cavity Closers: `CC08`
- Windows / Plot Calloff Installation: `CA02` or `CA03`
- Snagging: `SN05`
- CML: `CML4`

A new source column is `Completed Date`. Display **Completed — [Completed Date]**. Never use requested, agreed or planned dates as completion dates. If Completed Date is present but the expected stage code is missing, treat the service as Completed; Completed Date is strong evidence of actual completion.

If Excel/SiteApp reports Completed while negotiation or an amendment is open, Completed takes priority, close the outstanding negotiation/amendment, retain its history, and show Completed with the actual date. No notification/email is required merely because a service becomes Completed; dashboard updates are sufficient.

If source data reverses a previously Completed service, follow the source, remove the current Completed state, retain previous history, and show the reversal to customers with date/time.

### 19. Excel/SiteApp integration

Initial integration is read-only from the Portal: **Excel/SiteApp → Customer Portal**. The Portal does not write agreed dates to Excel/SiteApp.

The Portal owns customer requested dates, negotiation, Date Agreed, amendments and Portal communication/history. Excel/SiteApp owns product quantities, operational completion and source identifiers.

`Call No.` is a permanent unique identifier, never reused or changed, and safe as an external source reference. The corrected call-type dictionary is `PC1` = Plot Install, `CC1` = Cavity Closer 1, `CM1` = Revisit 1, `CM2` = Revisit 2 and `CML` = CML Call Off. PC1, CC1 and CML currently map to Windows, Cavity Closers and CML respectively. CM1 and CM2 have no confirmed four-service Portal mapping and require reconciliation. `CC!` is invalid (a Shift+1 typo for CC1) and must never be silently mapped.

The meanings/final uses of `complete`, `Items Ordered Status`, `Plot To Be Installed`, `Site Value`, Site Name durability and normal export scope remain unresolved. `complete` does not currently prove completion. Plot To Be Installed is operational context only and never becomes a requested, proposed, agreed or completion date. Site Value is commercial and excluded from customer output. Do not confuse operational statuses/codes with Portal request statuses.

Refresh approximately every one to two hours, not necessarily in real time. If sync fails or is delayed, show the last successfully synchronised data and visibly display **Last updated: [date/time]**. If a known Call No. disappears, retain last known data and Portal history, flag it internally to Fenster staff, and do not silently delete or hide it. Ownership of the process that updates the Excel/source data remains TBC.

### 20. CML

Use **CML** on the main overview. When opened, provide its full meaning/explanation. The exact full customer-facing expansion remains TBC. Customers cannot download the actual CML certificate from the Portal; certificate distribution remains outside the Portal.

### 21. History and timeline

Customers can view full customer-safe history for each plot/service: original requested date, alternatives, customer responses, customer-visible reasons/messages, Date Agreed, amendments, previous agreed dates, withdrawal/resubmission where relevant, completion/reversal events, and attachments associated with customer actions.

Every event shows exact date, exact time, actual person's name and actual person's role. Fenster staff see everything customers see plus internal notes/reasons and appropriate audit information. Never expose private internal Fenster information to Site Users.

### 22. Structured communication

Do not add a general chat or conversation thread. Communication remains structured around actions such as call-off request, alternative date, accept/reject alternative, Request Earlier Date and amendment, keeping the audit trail structured.

### 23. Attachments

External Site Users can upload photos and documents. Fenster staff can view them but cannot upload through this workflow. Optional attachments are allowed on customer actions containing a reason/message, including New Call Off, Reject Alternative Date, Request Earlier Date and Amendment Request.

All Site Users assigned to the site and all Fenster staff can view customer attachments. Attachments belong to the relevant action/history event. Do not create a general-purpose file repository.

### 24. Notifications and reminders

In-app notifications are comprehensive for meaningful workflow changes, including call-off submitted, requested date accepted/Date Agreed, alternative proposed, alternative accepted, alternative rejected, amendment requested, amendment accepted, amendment alternative proposed, and other meaningful action-required changes. Completion is explicitly excluded from notifications.

Email is reserved for important/action-required events and must not duplicate every in-app notification. The original submitter receives the primary alternative-date action notification/email; all assigned Site Users see updated status in the Portal. All Fenster staff receive relevant customer-action in-app notifications; email remains limited to important/action-required events.

Notifications must identify plot, service, action and required next step, and link to the relevant record. Preserve history even if a notification is read or dismissed. Include sensible reminders for outstanding customer or Fenster actions, but do not create reminder noise for Completed services.

### 25. Calendar and schedules

Provide an agreed-work calendar showing Date Agreed work, with plot, service and agreed date. Use service-specific calendar colours for Cavity Closers, Windows, Snagging and CML; retain text/icon labels and accessible hover/focus/tap summaries. Mobile first tap opens the summary and View Details opens the full call-off.

Provide calendar filtering by site, plot, service and date where appropriate. Calendar/PDF schedule output is a customer-facing convenience and must use Portal Date Agreed data, not placeholder or operational planned dates. PDF schedules should clearly identify site, plot, service, agreed date and relevant status.

### 26. Eligibility and source-driven behaviour

Eligibility is based on source product/plot data and Portal business rules. Customers do not edit operational data. A service may be called off independently of physical service order, subject to its own eligibility and lead time. Existing, completed, ineligible and unresolved combinations must remain explainable in the UI.

### 27. Company testing and priority

The first proper company test uses a completely fictional test customer/site with dummy plots so testers can submit, accept, reject, amend, withdraw and experiment without affecting operations. Include scenarios for BF products, different lead times, completed plots and alternative-date negotiation.

Use a small controlled group: at least one Fenster staff member and two or three people representing the external site roles.

The test succeeds only when testers can complete main workflows without developer assistance; terminology and workflow make sense; no serious bugs or data-integrity problems are found; and management is happy with overall appearance and presentation.

After testing, collect all feedback before changing the app. Classify it as bugs, important usability improvements, genuine new requirements, or personal preferences, then agree priorities with management rather than reacting to individual comments immediately.

The overall priority confirmed for the next phase is to implement all of today's confirmed requirements before company testing, subject to the documentation gap analysis and controlled sprint plan.

## Documentation updates

1. Create `documentation/management-requirements-2026-08-20.md` containing the authoritative record above.
2. Update `brief.md` to correct misunderstandings and reflect the clarified requirements, especially the plot-centric dashboard, four-service order, independent call-offs, multi-plot/multi-service dates, product-driven lead times, Date Agreed/On Hold terminology, completion authority, read-only Excel/SiteApp integration, revised permissions, attachments, notifications, calendar/PDF schedules and company-testing approach.
3. Update `AGENTS.md` with a clear mandatory instruction that every future planning, implementation, QA, review or deployment agent must read `documentation/management-requirements-2026-08-20.md` before making decisions or changes affecting the Customer Portal. State that it is authoritative where it conflicts with older assumptions, while agents must still distinguish confirmed requirements from current implementation and future work.
4. Update `DECISIONS.md`, `ROADMAP.md`, `current_sprint.md` and relevant domain documentation only as needed to remove contradictions and link to the new authoritative context. Do not silently rewrite historical records; preserve decision history and identify superseded assumptions.
5. Record unresolved items explicitly as TBC rather than inventing decisions: exact CML expansion, amendment-reason list, Excel/source update ownership and any other item marked TBC above.

## Verification and final response

Before finishing, inspect the diff and verify that only documentation files changed. Confirm that no production code, migrations, routes, controllers, models, views, JavaScript, CSS or tests were modified. Check that all four services, statuses, date rules, negotiation, amendment/On Hold behaviour, completion mappings, source-sync rules, permissions, attachments, notifications, calendar/PDF requirements and company-test decisions are represented.

In the final response, list the documentation files changed, confirm that the task was documentation-only, identify any TBC items retained, and summarise any contradictions that were explicitly resolved.
