# Phase 5 WP1 — integration contracts

> **12 November 2026 update:** WP1 is Owner-accepted. The collection contract specified below is now delivered by [WP2](Phase_5_WP2_Integration_Contracts.md) for its separate manual gate. Historical 'future WP2/not implemented' descriptions below record WP1's delivery boundary; WP3/WP4 remain unimplemented.

11 November 2026 (Africa/Lagos). **Plan approved by Owner; WP1 implemented for manual verification.** Only the three read routes below, schema, permissions and internal participants are implemented now. Later-package commands in this document are binding integration specifications, **not available endpoints**. See [plan](Phase_5_Implementation_Plan.md) and [approval](../acceptance/2026-11-11-phase-05-plan-owner-approval.md).

## 1. Ownership and implemented boundaries

CustomerDebt owns live Receivable balances and the new payment/allocation evidence. Customer identity remains Tenant-owned; bills, money, queries and future mutations use server-resolved current Shop scope. No Customer Shop column, stored balance/status, advance wallet or duplicate Receivable Adjustment table is added.

- `ReceivableBalanceQuery::projection()` is the common internal SQL projection, with explicit ShopContext and captured Shop calendar date. It neither authorizes nor commits. Future callers must establish scope, schema readiness and their owning transaction first.
- `DebtQuery` authorizes `customer.view` AND `debt.view`, then checks `DebtSchema`. Its count, totals and page run inside `DebtReadSnapshot` (one REPEATABLE READ, READ ONLY transaction). The participant reuses an existing transaction without committing it. No Audit/change row is created by these reads.
- `ReceivableAdjustmentParticipant::append()` owns the one adjustment insertion path. Caller supplies its same-scope locked Receivable, owning transaction, legitimate source/type/sign/reason, timestamp/correlation and reversal relationship. It performs bounded decimal/sign/type validation and inserts; it never opens/commits a transaction or performs independent Audit/publication. The owning command must validate balance/dependencies and exact inverse amounts before calling it. It is not a public adjustment authorization service.
- `CommercialEvents::adjustment()` delegates to this participant with its unchanged signature. Existing Sale/Return transaction, locks, typed Audit and publication remain authoritative. Zero delta stays a no-op. Existing `CommercialEvidence::credit()` collection guard and original-receipt refund capacity remain deliberately unchanged until WP3.

## 2. Schema and rollout

Parent-first migrations and paired complete SQL:

1. `2026-11-11-190000_CreatePhaseFiveDebtPayments`; `docs/database/sql/phase-05/20261111190000_debt_payments.sql`.
2. `2026-11-11-190100_CreatePhaseFiveDebtPaymentAllocations`; `docs/database/sql/phase-05/20261111190100_debt_payment_allocations.sql`.

These add only `debt_payments` and `debt_payment_allocations`. All v1.1 columns, positive DECIMAL(19,2) amounts, unique public/reversal/payment-bill identities, allocation order/indexes, scoped FKs and Shield `INT UNSIGNED` actor references are retained. Scoped Shop parent FK also protects standalone payment scope. Existing Receivables/adjustments/Sales are not altered. Same-Customer allocation/reversal, allocation sum, equal inverse, no reversal chain/self-reversal and no excess are cross-row **service** invariants; row CHECKs cannot prove them. No CHECK references AUTO_INCREMENT `id` because MySQL does not allow that combination.

No payment status/draft column or command-hash table is invented. Payment rows are accepted immutable evidence once their owning transaction commits. Amounts stay positive; reversal link determines inverse allocation/Cash effect.

`DebtSchema` is one explicit gate: both tables must exist. No missing-table fallback reports zero payments. A partial/missing deployment returns DEBT_SETUP_REQUIRED after authorization, with no financial response. Apply both migrations while the application is in a controlled update window; then deploy matching source/assets and verify reads. Existing Phase 4 commands do not start using a new payment writer in WP1. Do not route traffic to new collection code before its schema is ready in a later package.

DDL implicitly commits. Back up and identify the target first. Do not forge migration history after a partial failure. On a populated development copy, inspect any created table before retry; recover the copy from backup if necessary. The down methods refuse populated tables. Reverse allocations before payments only on a disposable empty-new-table copy. Do not drop financial evidence or rewind production migrations to roll back code. A runtime role that only has per-table grants needs SELECT on the new tables for WP1; later writers require INSERT, with immutable evidence protected from ordinary UPDATE/DELETE. Use a separate privileged migration account.

## 3. Balance and state contract

For each bill:

- `adjustment_delta = SUM(receivable_adjustments.amount_delta)`, including original and opposite reversing deltas exactly once.
- `adjusted_amount = original_amount + adjustment_delta`.
- `collected_amount = SUM(normal payment allocations) - SUM(reversing payment allocations)`.
- `outstanding_amount = adjusted_amount - collected_amount`.

Do not exclude a reversed original and then apply its inverse again. Original/adjustment/allocation input amounts are DECIMAL(19,2); SQL accumulation remains exact DECIMAL, with money JSON values explicitly mapped to strings. Aggregated Customer totals may exceed a single payment's DECIMAL(19,2) bound and must not pass through PHP/JS float conversion or single-payment normalization. Public output contains no internal numeric IDs, phone/notes, inventory costs or profit.

A bill is SETTLED when outstanding=0, PARTIAL when outstanding>0 and effective collected>0, otherwise OUTSTANDING. A Return can reduce debt without counting as payment. Customer list states use the aggregate similarly; a Customer with no bills has NO_DEBT rather than fabricated payment history. The reader fails closed on negative adjusted/collected/outstanding values or same-scope allocations attributed to a different Customer. It does not replace later writers' complete integrity checks.

`is_overdue = outstanding>0 AND due_date < Shop-local today`. No date means no overdue; due today is not overdue; zero balance is never overdue. Capture one UTC `as_of`, obtain `shops.timezone_name` within the snapshot and derive one `shop_date`. A device clock/timezone cannot establish overdue authority.

Filters: ALL, OWING (all positive), OUTSTANDING (positive/no effective collections), PARTIAL (positive/with effective collections), SETTLED (zero; list requires historical bills), OVERDUE. Customer totals aggregate **all** that Customer's bills before Customer-level filtering; detail totals stay all-bill even when its visible bill list is filtered. List totals aggregate every matching Customer, not only the current page. Name-only search is literal, scope-bound and excludes private phone/notes. Active/inactive/ALL identity filters do not erase historical debt.

## 4. Implemented read API

Prefix `/api/v1/shop`; standard private/no-store envelope and authenticated hostname/membership context. No client Tenant/Shop authority fields accepted.

| GET route | Query | `data` |
|---|---|---|
| `/debts` | `search` max160; `status` default OWING; `customer_status` ACTIVE/INACTIVE/ALL default ALL; `page` default1/max100000; `per_page` default20/max50 | `items`, filtered `summary`, `pagination`, `shop_date`, `as_of` |
| `/customers/{customer_uuid}/debt` | `status` default ALL; page/per_page as above | minimal `customer`, all-bill `summary`, filtered `bills`, `pagination`, `shop_date`, `as_of` |
| `/debt-bills/{receivable_uuid}` | No filters | source Sale/Customer public identities/name, original/change/adjusted/net-collected/outstanding amounts, dates, settlement/overdue and snapshot dates |

Example Customer list item:

```json
{
  "public_id": "11111111-1111-4111-8111-111111111111",
  "display_name": "Example customer",
  "customer_status": "ACTIVE",
  "bill_count": 3,
  "original_amount": "2000.00",
  "adjustment_delta": "0.00",
  "adjusted_amount": "2000.00",
  "collected_amount": "0.00",
  "outstanding_amount": "2000.00",
  "overdue_amount": "1000.00",
  "overdue_bill_count": 1,
  "next_due_date": "2026-11-10",
  "settlement_state": "OUTSTANDING"
}
```

Pagination is `{page,per_page,total,total_pages}`; empty results have total_pages0. Invalid page/filter/text/UUID/protected parameters → VALIDATION_FAILED422; no permission/access → existing403; scoped missing Customer/bill → RESOURCE_NOT_FOUND404; missing schema → DEBT_SETUP_REQUIRED503; contradictory balance evidence → DEBT_EVIDENCE_INVALID409. Reads return no zero placeholder on failure. Internal unexpected errors use the existing safe response/request reference.

## 5. Authority and UI contract

New registered permissions: `debt.view`, `debt.payment.allocate`, `debt.adjust`, `debt.payment.reverse`; existing `debt.payment.record` remains. Only view has WP1 routes. The future command grants are registered for explicit later assignment, not operationalized by a hidden endpoint. `debt.manage` stays recognized without bypassing any action. No existing Staff grant is automatically expanded.

Owner derives known-code authority; Staff requires customer.view + debt.view for these reads. FULL/READ_ONLY read policy applies; BLOCKED/CLOSED and inactive membership/host scope deny. The Shop context exposes `view_debt` as both grants, consistent with the component check. A Sale deep link additionally needs sale.view; exposing its public reference grants no cost/receipt authority. Private details retain customer.private on existing Customer APIs and are not added to debt output at all.

Customers keeps its existing bottom/side destination. 'Money customers owe' opens `#customers/debts`; an existing selected Customer can open `#customers/debt/{uuid}`; bill detail uses `#customers/debt/{customer_uuid}/bill/{bill_uuid}`. Existing workspace navigation handles edits and direct hashes. An unauthorized debt deep link shows an access message and makes no debt request. A bill deep link verifies returned Customer identity matches the route. There is no added bottom destination.

Reuse shared labeled fields, selects, button icons/loading, facts, cards, information panels, alerts and pagination. No Receive/Undo/Adjust button exists in WP1. Superseded requests abort/are ignored; failed refresh clears old data; offline clears the displayed live balance and asks for connection. No balance is saved in localStorage/IndexedDB or queued as an operation. Native keyboard, responsive layout and request behaviour remain Owner manual gates.

## 6. Binding future collection contract — WP2, not implemented

Proposed commands below must be delivered in WP2 with native verification; no route is registered now.

| Route | Purpose |
|---|---|
| POST `/debt-payments/preview` | Authoritative amount/channel/allocation proposal; does not persist a payment |
| POST `/debt-payments` | Confirm exact reviewed command, atomically accept payment/allocations/Audit/changes |
| GET `/debt-payments/{public_uuid}` | Same-ID outcome/receipt recovery, reauthorized; 404 means absent **at read time**, not proof an in-flight transaction cannot later commit |
| GET `/customers/{uuid}/debt-payments` | Scoped paginated history |

Command fields: permanent `public_id`, `customer_public_id`, `channel` CASH/TRANSFER, positive decimal-string `amount`, nullable bounded `external_reference` max120, fixed UTC `occurred_at` using existing bounded occurrence validation, `allocation_mode` OLDEST_FIRST/EXPLICIT, `allocations` as `{receivable_public_id,amount}` for explicit mode only, optional reason max500. Confirmation also supplies `review_fingerprint`; no client balance/status/actor/scope fields. Preview returns normalized stable command, exact ordered allocations, affected before/after balances and fingerprint. Limit one explicit request to 200 distinct bills; oldest-first over larger debt must return an actionable bounded-selection result or require a smaller payment, never silently truncate the amount.

One payment has one channel; two channels are two visibly separate commands. No remainder, wallet or advance balance. Automatic allocation sorts accepted bill `(posted_at,id)` ascending; explicit amounts sum exactly to payment. All target bills belong to this Customer/current Shop. Recompute under locks; changed reviewed state → DEBT_REVIEW_STALE409 with refresh/review action, not silent reassignment. Excess/no balance → DEBT_AMOUNT_EXCEEDS_BALANCE409; composition mismatch → VALIDATION_FAILED422. Keep old permission/session/access error policy. Cash guard errors remain OPENING_CASH_REQUIRED/CASH_CONTROL_ACTIVE. Authority is customer.view+debt.view+debt.payment.record, adding debt.payment.allocate for explicit selection.

Check existing scoped UUID under the mutation lock before recomputing a previously accepted command. Compare normalized persisted header, Customer/channel/amount/reference/occurrence, exact allocations and bounded reason evidence; equivalent retry returns that record, different content → DEBT_IDENTITY_CONFLICT409. A new UUID is never an automatic timeout retry. Do not require a stale preview fingerprint to replay an already accepted equivalent command. No durable Sync Receipt/outbox exists until Phase 10.

## 7. Lock, Audit and publication traces

The approved clarification uses canonical Receivable locking `(posted_at,id)`, regardless of due-date/explicit display order. Existing tenant→Shop→active-membership serialization precedes domain locks and all current FULL/permission rechecks. Reuse the same DB connection throughout.

- Collection: owning transaction → tenant/Shop/membership and current grants → Cash guard when Cash → resolve Customer/source evidence → candidate Receivables in canonical order → validate/recompute → insert payment/all allocations → required Audit → publish all changes (generation lock) → commit.
- Return versus collection: existing commercial transaction takes the same tenant/Shop boundary before original Sale/lines/Product/position/Receivable locks. Collection cannot hold a Receivable while waiting for that Shop boundary; therefore no opposing lock cycle. WP3 replaces balance/capacity under this existing ordering, not by adding a generation-first read lock.
- Correction: same tenant/Shop/membership/Cash boundary → original commercial records/positions when needed → canonical Receivables → payment/adjustment dependency rows → validate inverse → append compensation → Audit → changes → commit.

Never acquire new domain locks after ChangeSequenceService locks the active generation. Rollback removes payment/allocation/adjustment/stock effects and every associated success Audit/change. Transaction participants never independently commit.

Future closed vocabulary, added with its owning writer (not registered as live WP1 actions): DEBT category; `debt_payment.recorded`, `debt_payment.allocated`, `debt_payment.reversed`, `debt_payment.corrected`, `receivable.adjusted`, `receivable.adjustment_reversed`; resources `debt_payment`, `receivable_adjustment`. Preserve Sales category/actions for Sales-owned returns. Normal collection/allocation actions explicitly join AuditReasonPolicy's optional list in WP2; sensitive reverse/adjust/correct reasons stay required. Do not fabricate user reasons. Per-allocation events or bounded linked evidence must trace every bill/amount/order without exceeding the existing 40-entry/16KiB metadata policy.

Required future change resources: debt_payment, affected receivable identities, Tenant-level customer context; receivable_adjustment identity where exposed by WP3. Extend the closed registry with its writer. Publish deduplicated public identities in one batch after all Audit. Allocations have no public UUID; their payment and Receivable identities are the public links. HTTP Audit correlation remains the canonical RequestContext request ID. Future reconciliation reads signed Cash payment effects; no Phase 7 snapshot or count mutation is implemented now.

## 8. Binding corrections/refund contract — WP3, not implemented

A standalone reversal appends a same-scope, same-Customer/channel/amount payment with `reversal_of_debt_payment_id` plus exact matching reversing allocations. One inverse per original; prohibit self/chain reversals. Require reason + debt.payment.reverse and view grants. It changes no original row.

For a linked corrected replacement use an **atomic** `/debt-payments/{uuid}/correction-preview` and `/correct` command: original UUID, distinct `reversal_public_id` and `replacement_public_id`, required reason, normalized replacement fields and review fingerprint. Both new records share this request's canonical correlation; the inverse links the original. Validate record and allocate permissions separately. Failure rolls both back. A UI that separately undoes and later records a new payment must honestly show each result; do not label an unlinked or failed second step as an atomic corrected replacement. No extra replacement column or forged cross-request correlation is added to v1.1.

Adjustment preview/post uses `/debt-bills/{uuid}/adjustment-preview` and `/adjustments`, plus `/debt-adjustments/{uuid}/reversal-preview` and `/reverse`: new public UUID, approved correction type/signed decimal, reason and authoritative review. Reuse existing table/participant; source Sale is nullable for legitimate non-return correction. Validate exact inverse and no negative outstanding under canonical locks; no general 'set balance', interest, waiver or write-off. Sales-owned RETURN_REDUCTION compensation remains Sales-owned and requires its original commercial command rather than generic adjustment authority. Protected reasons live in adjustment rows or required payment-correction Audit, not invented payment columns.

Replace the current allocation guard, original-receipt-only capacity, original-credit upper ceiling and Phase 4-only diagnostic assumptions together in WP3. Normal Return = debt reduction up to **current original-bill outstanding**, then eligible refund. Channel capacity includes original receipts plus effective same-bill collection allocations minus effective attributable refunds. Do not borrow another bill's share of a multi-bill payment. Compensation signs count once.

Reject collection reversal while an effective dependent refund would duplicate returned money or invalidate current evidence. Show dependencies/safe correction order. Direct exact cancellation of a collected Sale still requires dependencies safely undone; regular eligible returns after collection must work. Keep existing stock quantities, historical cost/profit and Inventory exact-reversal guards. These changes require native collected-return/correction acceptance before phase closure.

## 9. Binding statement contract — implemented in WP4

Current implementation: [WP4 contracts](Phase_5_WP4_Integration_Contracts.md). The following binding scope was established at WP1; it is no longer a future unimplemented feature.

GET `/customers/{uuid}/statement` with optional `from_date`/`to_date` Shop calendar dates, `per_page` max50 and opaque cursor. Scope/customer/view grants are mandatory. Filter accepted posting dates converted to UTC with correct Shop-local boundaries. Display occurrence time separately. Order atomic business events by `(posted_at,event_kind_rank,internal_id)` with fixed rank CREDIT=1, ADJUSTMENT=2, PAYMENT=3; one payment row carries its allocations, and one commercial return event groups related bill adjustments rather than counting total plus allocation twice.

One read-only consistent snapshot computes opening before range, signed in-range effects, per-page carried running balance and closing through range. Cursor binds Tenant/Shop/Customer/filter/order/page-size, last event key, opening/running anchor and generation/latest relevant Shop-or-Tenant change watermark; sign/validate it with server secret, never accept client running balance. On any relevant version change return STATEMENT_REFRESH_REQUIRED409; no mixed-version paging. Conservative invalidation on another Customer's debt change is permitted, silent balance drift is not. No persisted mutable statement balance or Phase 10 cursor table.

## 10. WP1 acceptance boundary

The [WP1 native guide](../manual-verification-phase-05-wp1.md) covers P5-01–P5-10. Partial collection/inverse states are supported in the shared projection and exercised in limited source/host probes; normal native creation of those records belongs to WP2/WP3 and is verified there, never faked by inserting posted ledger rows for Owner acceptance. Native MySQL/PHP/HTTP and real browser/layout checks are not claimed by grammar/build/projection probes. No Phase 5 package is accepted until the Owner explicitly confirms it.
