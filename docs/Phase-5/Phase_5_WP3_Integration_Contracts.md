# Phase 5 WP3 — correction and collected-return contracts

12 November 2026 (Africa/Lagos). WP2 is Owner-accepted; WP3 is delivered for separate native manual acceptance. Read with the [approved plan](Phase_5_Implementation_Plan.md), [WP1 binding contract §§8–9](Phase_5_WP1_Integration_Contracts.md), foundation blueprint/schema and [manual guide](../manual-verification-phase-05-wp3.md). No schema semantic change or new dependency. No statements: WP4 remains unimplemented.

## API and authority

All paths below have prefix `/api/v1/shop`. Existing authenticated commercial controller resolves active Tenant/Shop membership and CSRF. UUID route matching confers no authority. All reads require `customer.view` + `debt.view`; mutations repeat current independent grants, active membership and FULL access under `CommercialAccess`'s owning transaction. `debt.manage` does not substitute. READ_ONLY permits reads, never previews/posts. All scope and same-Customer checks are server-owned.

| Method and suffix | Purpose | Additional mutation permission |
|---|---|---|
| POST `/debt-payments/{source}/reversal-preview`, `/reverse` | Preview/append exact standalone inverse | `debt.payment.reverse` |
| POST `/debt-payments/{source}/correction-preview`, `/correct` | Preview/append inverse and replacement atomically | reverse + `debt.payment.record`; `debt.payment.allocate` for EXPLICIT replacement |
| POST `/debt-bills/{bill}/adjustment-preview`, `/adjustments` | Factual signed bill correction | `debt.adjust` |
| POST `/debt-adjustments/{source}/reversal-preview`, `/reverse` | Exact inverse of eligible non-Sales correction | `debt.adjust` |
| GET `/debt-payments/{operation}` | Existing authorized receipt/status; now includes inverse/replacement links | None |
| GET `/debt-adjustments/{operation}` | Authorized change receipt/status | None |
| GET `/debt-bills/{bill}/adjustments?page=1` | Posting-order change history, pages of20 | None |

Standalone undo/adjustment inverse body: `public_id`, mandatory `reason` (1–500 trimmed characters), `occurred_at` (existing UTC DATETIME6 syntax), plus `review_fingerprint` on final POST. Source UUID is in the route. A new identity must differ from its source. Unknown fields fail closed.

Atomic replacement body: `reversal_public_id`, distinct `replacement_public_id`, mandatory `reason`, `occurred_at`, and `replacement` containing `customer_public_id`, `channel`, `amount`, optional `external_reference`, `occurred_at`, `allocation_mode`, `allocations`. Replacement uses the exact WP2 normalizer: positive DECIMAL(19,2), one channel, 200 unique selected bills at most, full exact allocation. It must belong to the same Customer. For wrong-Customer money, independently undo the original then record a new payment for the correct Customer; each result is separate. The replacement inherits the correction reason; clients cannot inject another replacement UUID/reason. Final body adds `review_fingerprint`.

Factual adjustment body adds `adjustment_type` (`CORRECTION_INCREASE` or `CORRECTION_DECREASE`) and signed `amount_delta` (positive for increase, negative for decrease), with one DECIMAL(19,2) bound. This changes an established factual obligation by a documented amount; it is not “set balance”, waiver, write-off, discount, interest, revenue, money or stock. A decrease cannot exceed outstanding. Reversal has exactly the opposite delta, same bill, one direct inverse, no chain. Sales-owned changes (`sale_id` not null, especially RETURN_REDUCTION) must use the original commercial command. Cancelled original Sales cannot have their debt resurrected through this endpoint.

## Ownership, locks, review and atomicity

`DebtCorrectionService` owns authorization, transaction, original evidence/dependencies, mandatory reason, Audit and last change publication. `DebtPaymentPostingParticipant` now owns the shared positive payment/allocation append path; WP2 and WP3 reuse it. It never authorizes/commits/publishes independently. `ReceivableAdjustmentParticipant` retains Sales-compatible behavior and accepts an optional owning-command public UUID. Normal payment schema is unchanged.

Order: Tenant → Shop → membership/current authority → schema/replay → Cash session guard when either affected payment is Cash → original commercial roots → Customer → all Customer Receivables in `(posted_at,id)` order → payment/adjustment evidence → Audit → sync generation last → commit. Tenant/Shop locks shared with Sales serialize Return/payment/correction writers before narrower domain locks. Readers use a consistent snapshot; command reads participate in the existing transaction. Preview owns a transaction for fresh locks/permission/Cash checks but appends no financial rows/Audit/changes. No nested owning mutation. SQL/lock/commit failure propagates and rolls the entire command back.

The preview hash covers canonical input, scoped source evidence, ordered bill state and relevant per-channel capacity. Final POST recomputes everything. A stale hash returns409; the user must review again. No silent amount, channel, allocation, reason or identity rewrite. The inverse restores **the original allocations in their original order and amounts**, not a new oldest-first plan. Normal positive rows minus positive linked inverse rows are counted once. No original row is updated or deleted.

Correction evaluates the replacement plan against balances after the simulated exact undo, then writes both payments in the same transaction. One canonical request correlation links both; Audit records original/inverse/replacement public IDs. Failure in replacement validation, allocations, Audit, publication or commit leaves neither half accepted. P5-27's earlier two-step expectation is superseded by the binding atomic contract: no partial corrected pair. A deliberately separate standalone undo + later collection remains two honest commands.

Equivalent replay first checks immutable command identity/evidence and returns the saved operation, before fresh debt/Cash/dependency eligibility is reconsidered. Reusing any identity with changed details returns409. Existing unique inverse links enforce one inverse. Inverse-of-inverse and reversal of an already undone original fail. Replacement identity collisions abort, not overwrite.

## Live balances, returns and dependencies

The shared DECIMAL projection remains `original + signed adjustments − effective allocations`. Aggregates use DECIMAL(38,2)/digit strings; no float or original-credit ceiling. Sale credit GET now adds current `adjusted_amount`, `adjustment_delta`, `collected_amount`, `outstanding_amount`, `settlement_state` while retaining original snapshot fields. Historical Sale/payment receipts stay historical.

`DebtSettlementQuery` is the shared original-Sale refund-capacity query. For each channel:

`original Sale receipts + SUM(normal bill allocations − linked inverse bill allocations) − effective normal Return refunds`

Only allocations attributable to that Sale's bill and Customer count. A payment spanning multiple bills never contributes its full header to every Sale. A compensated Return no longer consumes effective refund capacity. Adjustments add no money capacity. Negative derived balances/capacity fail closed.

Normal Return reduces live outstanding first, then refunds the remainder within each attributable channel capacity. F4: original1000, collect600, owe400, return500 → reduction400/refund100/owe0, remaining Cash capacity500. Exact undo of collection600 is blocked because500 cannot cover it. Open the linked original Sale, review and undo the eligible Return first (subject to existing stock/Cash/dependency guards), then review the payment again. The dependency error includes scoped public Sale/bill IDs; the source payment receipt also links each Sale. Never erase allocation or refund records.

Return reversal restores exactly its original debt/refund/stock effects, even when a legitimate non-return increase makes current obligation exceed original credit. Existing exact stock/cost/provenance guards still apply. Original Sale cancellation requires effective collections and adjustments safely undone, no effective Returns, original debt fully available and all previous exact Inventory checks; offsetting increases cannot conceal active collections.

## Audit, publication, privacy and recovery

Activate DEBT actions `debt_payment.reversed`, `debt_payment.corrected`, `receivable.adjusted`, `receivable.adjustment_reversed`; required reasons remain required. Existing normal collection/allocated reasons remain optional. Each payment parent carries bounded command/allocation hashes and historical before/after totals; child allocation Audit traces each bill/order/amount. Correction adds linked summary evidence without placing up to200 allocations into one bounded metadata array. Change types include `receivable_adjustment`; append operation + affected Receivable/Customer changes, and both payment identities for replacement. Publish once, last; duplicates for affected bill/Customer refresh hints are harmless, not business events.

GET/status/history DTOs omit internal numeric IDs, credentials, Customer phone/private notes, Inventory cost/profit and raw Audit metadata/hashes. Authorized adjustment detail exposes the factual reason. Payment correction reason is present only with `debt.payment.reverse` and displayed in `.receipt-private`, removed from app Print clone and browser Print CSS. Public/printed payment receipt retains historical amount/channel/bill and original/inverse/replacement identity links, not internal live balance or reason. No customer portal is introduced.

UI uses existing Customers destination: payment receipt → Undo/Correct; bill → Change bill amount/View bill changes. Shared icons, loading buttons, persistent errors, field correction links and confirmation modal are reused. No financial outcome auto-dismisses. No offline queue. New operation marker stores only `kind,customer,source,id` (+replacement UUID for correction) per host/opaque user-and-Shop scope in tab sessionStorage, never body/money/reason/reference. Normal pending-payment and correction markers redirect to their owning recovery flow. Unknown/save state blocks navigation/reload with existing guard; reload never reposts. GET the operation UUID; correction inverse receipt contains the committed replacement UUID. A404 is absent only at read time; manually continue with the same identities, re-enter and review the same details. A changed-body collision remains unresolved until saved status is checked. If access is lost, restore legitimate read access or have an authorized Owner inspect those IDs; do not clear the marker and start over.

## Diagnostics and boundaries

`sales:diagnose` remains scoped, operator-only, read-only, bounded100 findings per check. Its signed balance/source graph now admits legitimate non-Sales correction/inverse rows and removes the false original-credit ceiling. Refund diagnostics count attributable effective collections. New payment check verifies sums, same-Customer/scope, ordered allocations and exact non-chained inverse; live-balance check includes allocations. Findings require investigation, never automatic repair.

No new DDL, framework, library, automated test suite, balance/status column, wallet, statement, expense or offline writer. WP1 migrations remain prerequisites; SQL twins unchanged. Native PHP/MySQL/HTTP/locks/rollback/Print/phone evidence is not supplied by source parsing, Vue host mocks or SQLite integer probes. See the separate pending native results register and engineering evidence.
