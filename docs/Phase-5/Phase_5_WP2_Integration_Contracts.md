# Phase 5 WP2 — receive and allocate payments

12 November 2026 (Africa/Lagos). WP1 is Owner-accepted; WP2 is implemented for its separate manual acceptance. [Approved plan](Phase_5_Implementation_Plan.md), [WP1 contracts](Phase_5_WP1_Integration_Contracts.md), [WP1 acceptance](../acceptance/2026-11-12-phase-05-wp1-owner-acceptance.md).

## Implemented boundary

One Cash or Transfer collection reduces current-Shop debt without changing original Sales, revenue, cost/profit or stock. Every accepted payment is fully allocated. No wallet, overpayment remainder, interest, advance receipt, correction/reversal writer, general adjustment or statement is introduced. No new migration: the two accepted WP1 tables are prerequisites. Existing allocation-dependent commercial Return/cancellation guard remains until WP3; operators must not erase allocations to bypass it.

## Sources and ownership

| Source under `app/Modules/CustomerDebt` | Responsibility |
|---|---|
| Application/DebtPaymentInput | Closed payload normalization, UUIDs, single-payment DECIMAL(19,2) strings, channel/mode/selection/reason validation, canonical command hash |
| Domain/DebtAmount | Exact unsigned DECIMAL(38,2)-sized aggregate string arithmetic; no machine-sized total or float |
| Domain/DebtAllocationPlanner | Pure oldest-first/explicit allocation, exact sum/no-excess and per-bill before/after calculations |
| Infrastructure/DebtPaymentRepository | Same-scope Customer/payment/allocations/recorded evidence reads; canonical Receivable locks and shared balance projection |
| Application/DebtPaymentService | Owns preview/post orchestration through existing CommercialAccess transaction, live authority, Cash control, replay, fingerprint, inserts, typed Audit and changes |
| Infrastructure/DebtPaymentQuery | Authorized read-only consistent receipt/history snapshots and safe DTOs |

`DebtPaymentController` calls those boundaries using existing authenticated hostname/membership context, CSRF/request-envelope/error policy and private/no-store responses. No client actor/Tenant/Shop authority fields are accepted. Internal numeric IDs, private Customer phone/notes, raw Audit metadata, command hashes, cost/profit and credentials are not returned.

## API

Routes below `/api/v1/shop`:

| Method | Route | Result |
|---|---|---|
| POST | `/debt-payments/preview` | Authoritative normalized command, allocations, before/after totals and fingerprint; no persistent payment/Audit/change effect |
| POST | `/debt-payments` | Confirm reviewed command; atomic acceptance or equivalent replay; returns receipt |
| GET | `/debt-payments/{uuid}` | Current scoped receipt/status; absent-at-read returns DEBT_PAYMENT_NOT_FOUND404 |
| GET | `/customers/{uuid}/debt-payments?page=1&per_page=20` | Customer identity, payment items and pagination (max50, page max100000) |

Example preview input (real public UUIDs and current bounded UTC timestamp required):

```json
{
  "public_id": "11111111-1111-4111-8111-111111111111",
  "customer_public_id": "22222222-2222-4222-8222-222222222222",
  "channel": "CASH",
  "amount": "1200.00",
  "external_reference": null,
  "occurred_at": "2026-11-12 10:00:00.000000",
  "allocation_mode": "OLDEST_FIRST",
  "allocations": [],
  "reason": null
}
```

All amount inputs are positive decimal strings, at most17 whole/two fractional digits. Floats, commas, signs, scientific notation, excessive precision, zero and excess fail. One payment has one channel; two channels are two separate reviewed commands. Reference is nullable/max120 and allowed only for Transfer. Reason is an optional trimmed note/max500 for ordinary collection; no fake reason is generated. Occurrence uses exact UTC DATETIME(6) syntax and the existing approximately-one-year-past/five-minutes-future bound for a new acceptance.

EXPLICIT requires independent allocation authority and 1–200 unique `{receivable_public_id,amount}` entries whose exact sum equals the payment. Bills must belong to this Customer/current Shop and have sufficient current outstanding. Automatic mode requires an empty selection array; it consumes canonical `(posted_at,id)` oldest unpaid bills. If more than200 allocations are needed, DEBT_ALLOCATION_LIMIT422 asks for a smaller payment/selection; no amount is silently truncated. Inactive Customers may repay existing legitimate debt.

Preview returns `already_saved:false`, `public_id`, Customer name, canonical `command`, `allocations` (bill public ID, amount, before/after and due date), Customer balance before/after and `review_fingerprint`. If the same equivalent command is already saved it returns `already_saved:true/public_id` for status recovery. Confirm sends canonical command plus the64-character fingerprint. Changes in relevant bill evidence require new review; a consumed balance can also fail the exact excess/selected-bill checks. No stale payment is resized or reallocated silently.

## Transactions, safety and repeat handling

Both preview and post recheck customer.view + debt.view + debt.payment.record through CommercialAccess under tenant→Shop→membership serialization, adding debt.payment.allocate for explicit mode. READ_ONLY permits authorized receipt/history reads but denies preview/post; BLOCKED/CLOSED, inactive membership and wrong host scope deny. Legacy debt.manage is never a bypass. Shop context exposes matching collect/allocate capabilities and an opaque user/Shop recovery-marker namespace; the namespace is not an authorization token.

For a new collection: establish schema → Cash guard if Cash → same-scope Customer lock → all Customer Receivables locked `(posted_at,id)` → shared allocation-aware balances → exact plan/fingerprint → payment and ordered allocations → persisted SUM recheck → Audit parent and every allocation → one change batch → commit. Transfer never calls the Opening Cash guard. Generation locking/publication happens last; no domain access follows it inside the owning transaction. The outer transaction rolls back all effects on any exception/failed commit. Preview takes the same authorization/lock boundary but inserts nothing. Complete native concurrency/rollback verification remains a manual gate.

Existing UUID is checked before new balance/Cash eligibility/occurrence-bound calculation. Under the same authorization/Shop serialization, equivalent normalized command returns its saved record without another payment, allocation, Audit or change. A different command conflicts. Immutable parent Audit stores a canonical command fingerprint and allocation fingerprint; replay also compares persisted Customer/channel/amount/reference/occurrence and verifies the actual ordered allocation set against recorded evidence. No extra command-hash table/column is added. Receipt reads verify allocation evidence and exact payment sum, failing closed if inconsistent. Current authority still applies; GET recovery can work with view-only access after collection privilege/entitlement changes.

Every payment and its allocation rows commit together. A concurrent payment or Return takes the same tenant/Shop boundary before Receivable locks, preventing a second command from consuming an old balance. All calculations use SQL DECIMAL or digit-string arithmetic. Customer aggregate debt may exceed a single payment's limit; it is not converted into a single-payment parser or JS Number.

## Audit and changes

Closed vocabularies add DEBT actions `debt_payment.recorded` and `debt_payment.allocated`, resource/change `debt_payment`. Only these normal actions join the optional-reason policy. Reverse/adjust/correct actions are not activated. Parent Audit contains channel, amount, mode, count, command/allocation fingerprints and exact Customer totals. Each allocation has a separately bounded causal child event linking its bill public UUID, amount/order and before/after; no over-sized metadata array is used. Allocation rows remain domain evidence. RequestContext supplies one canonical request correlation for the payment, Audit and change rows.

A normal two-bill payment adds one payment, two allocations, three success Audit events and four changes (payment, Customer context, two Receivables), all atomically. Equivalent retry adds none. Reads/preview emit none. No inventory movement, Sale payment row, Receivable rewrite or new Sale revenue/profit occurs.

## Receipt, history and confidentiality

Receipt contains payment/Customer/Shop identities and names, channel/amount/reference, actor display name, recorded times, ordered allocations, the Customer total before/just after this payment from immutable recorded evidence, and a separately labelled current Customer total derived in the same receipt-read snapshot. A later Sale/payment/Return can change the current total without rewriting the saved receipt's historical before/after values.

History is scoped and paginated in reverse accepted posting order. Sale public links do not grant Sale authority; UI links require sale.view. No private Customer phone/notes, sensitive reason text or raw Audit hashes are printed/exposed by the receipt DTO. Internal current-balance section is hidden both by actual app Print clone filtering and existing browser Print CSS. Native Print preview is still required.

## UI and recovery

Customer bills now offer Receive payment (independent collection authority/FULL/online) and View customer payments. Routes remain inside the existing Customers destination:

- `#customers/debt/{customer_uuid}/receive`
- `#customers/debt/{customer_uuid}/payments`
- `#customers/debt/{customer_uuid}/payment/{payment_uuid}`

Use shared buttons/loading, fields, selects, cards, review and error summary. Explicit selection loads bill pages of50, retains choices and shows exact kobo remainder/over-assigned amount; that is input guidance only, never server balance authority. Every confirm uses the authoritative returned review. Date display uses the device's labelled local time, with server UTC evidence preserved.

During submission, one request guard and existing global mutation/navigation guard block double activation or leaving. On uncertain response (network/timeout/server/auth failure), retain the same UUID, block a new payment and offer Check saved status. `sessionStorage` holds **only payment UUID and Customer UUID**, namespaced by host and opaque current user/Shop recovery scope. No amount, note, bank reference, command body, credential, outbox or automatic replay is stored. Different signed-in users get different namespaces. A disabled-storage warning explains the in-memory limitation and asks the operator to keep the page open.

A fresh page sees a retained marker and checks status; it never automatically posts. A404 means absent at that read, not that a delayed original request cannot arrive. Continue with this same payment reference permits manual reconstruction/review under the original UUID; if the original later committed, server replay/conflict handling prevents a second effect. New details do not get a new reference to bypass an unresolved payment. Known deterministic validation/stale conflicts preserve inputs and require a fresh review. Unauthorized/suspended users retain the marker until access/status can be checked. Offline controls queue nothing. Navigation and sign-out still follow the accepted security-session behaviour; this marker provides no offline authorization.

## Errors and rollout

Validation/selection/format → VALIDATION_FAILED422; no review → DEBT_REVIEW_REQUIRED422; excess → DEBT_AMOUNT_EXCEEDS_BALANCE409; stale selection/fingerprint → DEBT_REVIEW_STALE409; different UUID command → DEBT_IDENTITY_CONFLICT409; excessive allocation count → DEBT_ALLOCATION_LIMIT422; inconsistent evidence → DEBT_EVIDENCE_INVALID409. Existing Cash/access/schema errors retain their codes with plain-English UI guidance.

No migration beyond WP1. Deploy coherent backend and source/generated assets, close older trading tabs before collection and use the WP2 package inventory (do not overwrite WP1/UI4 manifests). Runtime DB role needs SELECT/INSERT on payment/allocation evidence and required existing Audit/change permissions; no ordinary UPDATE/DELETE. Code rollback cannot delete accepted payments, and an older release unaware of collections must not resume commercial correction on affected bills. Use a controlled, data-compatible forward fix/read-only recovery if needed; never restore an old database over later accepted money.

[Native setup/manual acceptance](../manual-verification-phase-05-wp2.md) covers P5-11–P5-23. WP3 owns undo/correction and repayment-aware Returns; WP4 owns statements and phase closure. None is claimed delivered by WP2.
