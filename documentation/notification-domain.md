# Sprint 1E — Notification Domain

> **Historical baseline with later extensions.** This contract preserves the original Sprint 1E
> and Sprint 3E notification design, including statements that amendments were then out of scope.
> It is not the current RC1 amendment/recovery release contract. For RC1 use `brief.md`, the newest
> applicable entries in `DECISIONS.md`, `documentation/customer-release02-rc1-build-2026-09-09.md`
> and `documentation/customer-release03a-rc1-recovery-strategy-2026-09-09.md`. The older wording
> below remains evidence of its historical phase and must not override newer decisions.

**Status:** Implementation contract

**Scope:** Version 1 in-app notifications for the Customer Portal

This document defines the notification domain for Sprint 1E and its Sprint 3E negotiation
extension. It builds on the confirmed
Customer Portal roles, assigned-site authorisation and call-off lifecycle. It does not
introduce SiteApp workflow, operational statuses, integration events or internal Fenster
records.

## 1. Sprint 1E boundary

Sprint 1E covers the in-app notification centre for these confirmed call-off events:

- a call-off is successfully submitted;
- a call-off is successfully approved by Fenster Office Staff;
- a call-off is successfully rejected by Fenster Office Staff.

The following are not notification triggers in Sprint 1E or Sprint 3E:

- withdrawal;
- Trash, restoration or quick Undo;
- amendments or revisions;
- later customer-facing progress statuses;
- QR-code activity;
- SiteApp synchronisation or SiteApp operational events;
- email delivery;
- push delivery.

Notifications must describe Customer Portal requests and decisions only. They must never
expose SiteApp workflow, internal statuses, scheduling, manufacturing, labour, readiness,
verification or internal audit data.

## 2. Notification types

The notification type is a stable portal identifier, not a copy of a SiteApp event name.
The initial types are:

| Type | Customer-facing meaning | Request state at trigger |
|---|---|---|
| `call_off_submitted` | A call-off request has been submitted for review. | `submitted` |
| `call_off_approved` | Fenster Office Staff have approved the call-off request. | `approved` |
| `call_off_rejected` | Fenster Office Staff have rejected the call-off request. | `rejected` |
| `call_off_date_agreed` | Fenster Office Staff agreed the requested date. | `date_agreed` |
| `call_off_alternative_proposed` | Fenster proposed a customer-visible alternative date. | `awaiting_site_user` |
| `call_off_alternative_accepted` | An authorised Site User accepted an alternative date. | `date_agreed` |
| `call_off_alternative_rejected` | An authorised Site User rejected an alternative date. | `awaiting_fenster` |

Each notification must identify the affected Customer Portal call-off request by its
public UUID. It may include the related batch UUID, site name, customer-facing plot
reference, service type, requested date, current portal status and customer-visible
response. Integer database IDs, `internal_reason`, private Office Staff notes and raw
integration payloads must not be included.

The batch remains the grouping for a multi-plot submission, but each individual request
owns its status, decision and history. A notification therefore refers to one individual
request. A batch submission may create several request notifications; the notification
centre may group them visually by batch without changing their independent audit meaning.

## 3. Triggering domain events

Notifications are created only after the corresponding audited domain action has
successfully persisted its state transition:

| Domain event | Producing action | Notification type |
|---|---|---|
| Call-off submitted | `SubmitCallOffBatchAction` | `call_off_submitted` |
| Call-off approved | `ApproveCallOffRequestAction` | `call_off_approved` |
| Call-off rejected | `RejectCallOffRequestAction` | `call_off_rejected` |
| Requested date agreed | `AgreeRequestedCallOffDateAction` | `call_off_date_agreed` |
| Alternative date proposed | `ProposeAlternativeCallOffDateAction` | `call_off_alternative_proposed` |
| Alternative date accepted | `AcceptAlternativeCallOffDateAction` | `call_off_alternative_accepted` |
| Alternative date rejected | `RejectAlternativeCallOffDateAction` | `call_off_alternative_rejected` |

The event represents a successful Customer Portal transition. Validation failures,
authorisation failures, stale decisions, duplicate conflicts and transaction rollbacks
must not create notifications.

Notification creation and request-state persistence must be coordinated so a delivery
failure cannot roll back or corrupt the call-off transition. The implementation may use
Laravel's notification/event facilities and queued delivery, but the contract does not
require a particular queue or transport.

## 4. Recipients and authorisation

Recipients are resolved from the Customer Portal's server-side role and site assignments.
A notification may only be delivered to an active authenticated portal user who is
authorised to see the affected request at the time the recipient is resolved.

The minimum Version 1 recipient mapping is:

| Event | Recipient |
|---|---|
| Submitted | The site user who submitted the batch, and active Fenster Office Staff with global Portal review scope. |
| Approved | The site user who submitted the individual request. |
| Rejected | The site user who submitted the individual request. |
| Requested date agreed | The site user who submitted the individual request. |
| Alternative date proposed | The site user who submitted the individual request. |
| Alternative date accepted | Active Fenster Office Staff with global Portal review scope. |
| Alternative date rejected | Active Fenster Office Staff with global Portal review scope. |

The three site roles remain distinct but have identical Version 1 permissions. A site
role's shared visibility of all requests for its active assigned site does not, by itself,
make every site user a notification recipient. No broadcast to all site users or to all
Office Staff is assumed.

The acting Office Staff member does not receive a duplicate decision notification solely
because they approved or rejected a request. If that user is also the submitting user,
the normal submitting-user rule applies.

Recipient resolution must enforce:

- active account status;
- customer organisation boundary;
- assigned-site scope;
- the request's current portal visibility rules;
- no reliance on client-provided user, site or request IDs.

Development role preview is not a notification audience. Previewing a role must not
create, redirect or expose real notifications, and preview must remain unavailable in
production.

## 5. Read and unread behaviour

Read state belongs to the recipient, not to the call-off request or batch.

- A newly created notification is unread.
- An unread notification remains unread until that recipient opens it or explicitly marks
  it as read.
- Marking a notification read changes only that recipient's notification state; it does
  not change the call-off status, decision, history or audit records.
- Read state is not shared with other recipients of the same event.
- The notification centre shows an unread count scoped to the signed-in user and current
  customer organisation.
- Notification links use public request or batch UUIDs and must re-authorise access when
  opened; a notification is not an access token.

The Sprint 1E backend provides a tenant-scoped mark-all-read operation for the future
notification UI. It updates only the signed-in recipient's unread records and retains
per-recipient attribution; it never provides cross-user read state. The UI may choose
whether and where to expose that control.

## 6. Notification lifecycle

The logical lifecycle for an in-app notification is:

```text
Created (unread) → Read
       └────────→ Dismissed
Read ───────────→ Dismissed
```

- **Created:** generated from a successful portal domain event and available in the
  recipient's notification centre.
- **Read:** the recipient has opened or explicitly acknowledged the notification.
- **Dismissed:** the recipient has removed it from the default active notification list.

Dismissal is a per-recipient presentation state. It never withdraws, trashes, restores,
rejects, approves, amends or deletes the related call-off. It must not write to call-off
status history. Dismissing an unread notification records it as no longer active for that
recipient; the implementation may also record it as read, but must not alter any other
recipient's state.

There is no notification lifecycle transition for a failed email, push or SiteApp action
in Sprint 1E because those transports and integrations are out of scope.

## 7. Dismissal rules

- A recipient may dismiss only their own notification.
- Dismissal must be server-authorised and tenant-scoped.
- Dismissed notifications are excluded from the default active notification centre and
  unread count.
- Dismissal does not delete the underlying notification record or call-off history.
- No bulk dismissal, permanent user deletion or cross-user dismissal is included in
  Sprint 1E.
- A dismissed notification may remain available in the recipient's notification history
  while it is retained; no automatic restoration or undo behaviour is defined for
  dismissal.

## 8. Retention policy

The seven-day Customer Portal Trash window applies to customer-facing call-off Trash. It
does not delete or expire notification records.

Sprint 1E retains notification records independently of the call-off's current list state:

- a rejected or withdrawn call-off being moved to or expiring from Trash does not remove
  its previously generated submitted or rejected notifications;
- a call-off decision remains represented in notification history even if the request is
  later hidden from customer-facing Trash;
- notification records must not be purged automatically until a long-term retention
  period is explicitly confirmed;
- any future retention job must preserve audit-critical call-off history and must not
  turn notification expiry into call-off deletion.

Long-term retention outside the seven-day Trash visibility period remains an open product
decision. Until it is resolved, Sprint 1E must prefer retaining notifications and must not
silently destroy them.

## 9. Future email mapping

Email is not implemented by Sprint 1E. The future mapping is:

| In-app type | Future email purpose |
|---|---|
| `call_off_submitted` | Submission confirmation and review acknowledgement. |
| `call_off_approved` | Customer-facing approval decision. |
| `call_off_rejected` | Customer-facing rejection decision and response. |

Future email must use the same authorised recipient set as the in-app event unless a new
decision explicitly changes it. Email templates may include `customer_response`, but must
never include `internal_reason` or SiteApp data. Email configuration, consent,
deliverability, retry policy and the required email audience remain unresolved and are
outside Sprint 1E.

Email failure must never roll back or make a successfully persisted call-off transition
appear unsuccessful.

## 10. Future push notification mapping

Push notifications are not implemented by Sprint 1E. The future mapping is one-to-one with
the three in-app types:

- `call_off_submitted` → submission push alert;
- `call_off_approved` → approval push alert;
- `call_off_rejected` → rejection push alert.

Push delivery, device registration, permission prompts, user preferences, digests, retry
behaviour and token retention require a later decision. Push must use the same server-side
recipient and tenant checks as the in-app notification and must never grant access to the
linked request. The roadmap places enhanced push behaviour in a later version; no push
transport or SiteApp event mapping is part of Sprint 1E.

## 11. Implementation acceptance criteria

Sprint 1E is complete when:

1. Successful submission, approval and rejection transitions create the corresponding
   in-app notification types.
2. Failed, unauthorised, stale or rolled-back transitions create no notification.
3. Each notification identifies one individual request with public UUIDs and contains
   only customer-facing portal data.
4. Submission notifications reach the submitting site user and assigned Office Staff;
   approval and rejection notifications reach the submitting site user.
5. Recipient selection enforces active accounts, customer organisation boundaries and
   assigned-site scope server-side.
6. New notifications are unread, and read state is independent for each recipient.
7. Dismissal removes only the recipient's active presentation of the notification and
   never changes call-off state or history.
8. Notifications are not deleted by the seven-day customer-facing Trash expiry rule.
9. Email and push remain future mappings only; no SiteApp integration behaviour is
   introduced.
10. Notification tests cover tenant isolation, revoked assignments, unauthorised UUID
    access, unread/read state, dismissal, failed transitions and recipient scope.

## 12. Explicitly unresolved decisions

The following must be decided before expanding beyond this contract:

- whether additional site users should receive broadcast notifications;
- required email audience, consent and delivery rules;
- push opt-in, device and preference rules;
- long-term notification retention and deletion periods;
- notification rules for amendments and later customer-facing progress statuses;
- grouping, batching or digest presentation for multi-request submissions.

Until those decisions are confirmed, implementation must stay within the three in-app
call-off event types and recipient mapping defined above.
