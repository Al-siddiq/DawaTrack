# Phase 5 — Debt Collection and Customer Statements

**Date:** 11 November 2026 (Africa/Lagos). **Status:** Owner approved on 11 November 2026; WP1 accepted; WP2 implementation authorized only. See [approval](../acceptance/2026-11-11-phase-05-plan-owner-approval.md) and [WP1 delivery](Phase_5_WP1_Delivery_Report.md). See [WP1 acceptance](../acceptance/2026-11-12-phase-05-wp1-owner-acceptance.md); WP2 manual acceptance is pending. **Reviewed baseline:** main `b41425f905100a728a4c6167d5df936702a8502a` (UI4-A PR #37 merged). **Delivery model:** four sequential work packages, each runnable, documented and manually accepted before the next begins.

## 1. Authority and entry position

Read this plan with:

- [Strategic roadmap, Phase 5](../DawaTrack_Strategic_Phased_Implementation_Roadmap.md#phase-5--debt-collection-and-customer-statements): controlling scope, order and explicit exclusions.
- [Technical Blueprint](../DawaTrack_Technical_Blueprint.md), §§6.4–6.7, 6.10, Audit, tenancy and offline boundaries: legitimate credit, derived balances, oldest-first repayment, no interest/advance balance and accountable corrections.
- [Database Schema Design v1.1](../DawaTrack_Database_Schema_Design.md), §§10.1–10.5, 19 and transaction/lifecycle rules: exact tables, decimal types, causal relationships and immutable evidence.
- [Business & Product Blueprint](../DawaTrack%20Business%20%26%20Product%20Blueprint.docx): simple shop operation, customer credit without interest/penalties, confidentiality and staff accountability. Its commercial MVP numbering is distinct from the engineering roadmap.
- [Audit contract](../audit-contract.md), [change sequence](../sync/phase-02-change-sequence.md), [migration register](../database/migration-register.md), [permission catalogue](../security/permission-catalogue.md) and [UI developer reference](../ui/developer-handover.md).

The Owner previously reported integrated Phase 4 manual acceptance and merge, and has now reported UI4 review, acceptance, merge and rollout. [UI closure](../ui/programme-closure.md) records that decision. Do not require repetition of previously accepted packages. Do recheck affected financial integrations with synthetic data when Phase 5 changes them. No per-case browser, database or deployment observations are invented from the package-level confirmations.

Phase 5 completes collection of existing sale credit. It does not add another Sale, revenue or gross profit when a customer pays. It does not introduce expenses, the full cash-counting engine, reports or offline collection ahead of their owning phases.

## 2. What the current code actually provides

| Existing foundation | Source | Phase 5 consequence |
|---|---|---|
| Tenant-owned Customer identity/private-field policy | `app/Modules/CustomerDebt/{Application/CustomerService,Infrastructure/CustomerQuery}.php` | Reuse; do not add a Customer Shop ID, mutable balance, phone uniqueness or compulsory phone. Debt remains current-Shop scoped. |
| Sale-linked credit creation | `app/Modules/Sales/Application/SalePostingService.php`; `Infrastructure/SaleQuery.php::receivable()` | Original credit and immutable receipt snapshots exist. The current credit read is not a live repayment-aware balance query. |
| Four adjustment types already persisted | `app/Database/Migrations/2026-11-03-100500_CreatePhaseFourReceivableAdjustments.php` | Reuse RETURN_REDUCTION, CORRECTION_INCREASE, CORRECTION_DECREASE and REVERSAL; no replacement table or generic write-off type. |
| Commercial adjustment insertion | `app/Modules/Sales/Application/CommercialEvents.php::adjustment()` | Current writer requires a Sale source and does not supply a general Debt-owned adjustment service. Extract/reuse one transaction-participating writer without changing existing commercial semantics. |
| Deliberate repayment dependency guard | `app/Modules/Sales/Application/CommercialEvidence.php::credit()` | Any allocation currently blocks commercial credit correction; its balance ignores allocations and rejects increases above original credit. WP3 must replace this limited contract safely. |
| Refund capacity from original Sale receipts only | `CommercialEvidence.php::capacity()`; `ReturnService.php::settlement()` | Simply removing the dependency guard would still break returns after repayment. Include attributable effective collection receipts and dependent refunds in the approved capacity calculation. |
| Exact cancellation dependency checks | `CommercialReversalService.php` | Preserve exact-history rules. A collected Sale cannot be cancelled by ignoring its collections; dependencies must be corrected in a safe order. |
| Phase 4-only diagnostics | `app/Modules/Sales/Infrastructure/CommercialDiagnostics.php` | Current adjustment ceiling/type assumptions become too narrow. Update the affected diagnostics in WP3; legitimate Phase 5 corrections must not be reported as corruption. |
| Cash/transaction/access foundation | `Reconciliation/Application/CashControlGuard.php`; `Sales/Application/CommercialAccess.php` | Reuse finalized Opening Cash, active-control and tenant/shop/membership locks. Transfer collection must not require a Cash baseline. |
| Closed permission registry | `app/Enums/PermissionCode.php` | Only `debt.manage` and `debt.payment.record` exist for debt today. Add explicit independent view/allocation/adjust/reverse grants; no authority is implied by a broad old code. |
| Accepted native-style UI and online request recovery | `resources/js/shared/workspace-registry.js`, `workspace-navigation.js`, `api/http-client.js`, `components/ui/` | Extend actual Customers/navigation using existing components, local icons, loading, safe review and same-ID recovery. No new template or parallel navigation system. |
| No collection persistence/workflow | No `debt_payments`/`debt_payment_allocations` migrations, Debt collection routes or collection screens at baseline | These are real Phase 5 work, not features already implemented by the credit schema. |

## 3. Why four packages

| Package | Complete deliverable | Dependency | Owner can use/verify |
|---|---|---|---|
| **WP1 — Debt foundation and live balances** | Approved integration contracts, both collection tables, shared balance reader, debt permissions and read-only customer/bill views | Accepted Phases 1–4/UI | See who owes money, exact bills, partial/settled and overdue states; inspect schema and denial boundaries. |
| **WP2 — Receive and allocate payments** | Cash/Transfer collection, automatic/explicit allocations, review, receipt/history and uncertain-result recovery | WP1 accepted | Receive a customer's payment once, see where it was applied and the new balance. |
| **WP3 — Corrections and return integration** | Payment reversal/replacement, controlled adjustments/reversal, repayment-aware returns/refunds and updated diagnostics | WP2 accepted | Correct mistakes with reasons and linked evidence; process eligible returns after collection safely. |
| **WP4 — Customer statements and phase closure** | Paginated running statement, opening/closing totals, final cross-module verification and operational/developer handover | WP3 accepted | Explain every customer's balance from original sales through payments, returns and corrections; accept the whole phase. |

Four is the smallest reasonable split: shared balance/schema contracts precede money; correction/return dependency work deserves its own financial acceptance gate; full running statements depend on every event type. Each package includes its own backend/API/UI/security/docs/manual checks. WP4 is not reserved for finishing missing WP1–WP3 features.

## 4. Approved financial and integration decisions

The Owner approved these bounded contracts with the plan; implementation must document exact payloads, error codes, indexes and transaction participants in WP1 before dependent writers are delivered. If source analysis proves a schema/business conflict, report it with a proposed resolution before implementing that dependent behaviour.

### Balances and signs

One common CustomerDebt reader computes:

`outstanding = original credit + SUM(signed adjustment deltas) - SUM(effective signed allocations)`.

A normal allocation reduces debt; its linked reversing payment contributes the opposite effect. An adjustment and its inverse contribute their signed deltas once each. Never both exclude an original and apply its inverse a second time. Original credit, allocations and adjustments are immutable. Status, customer totals and overdue are query results, not editable stored fields. Original Sale receipt credit snapshots stay historical; label the live amount separately.

Outstanding may not become negative. Settlement is based on current outstanding: zero = paid; positive and below the current adjusted obligation = partly paid; otherwise unpaid. Show returns/corrections separately so a reduced bill is not described as money received. Overdue requires a positive balance and `due_date < current Shop-local date`; today is not overdue and no due date means no overdue claim. Use Shop `timezone_name`, not the device date. No late fees or debt growth.

### Allocation order and locking

Default allocation uses oldest accepted bill first, stable `(posted_at,id)` ascending as the schema's allocation rule. Backdated occurrence time does not reorder accepted debt. Explicit selection may span bills for this Customer/current Shop only, with positive exact amounts summing to the payment. Reject duplicates, excess, zero/negative/float/overprecision values and empty/no-balance selection.

The roadmap mentions due/age/ID locking while schema §10.4 specifies `(posted_at,id)`. Recommended reconciliation: use **one canonical `(posted_at,id)` database lock order for every touched Receivable**, independent of presentation/due sorting; due date is a visible indicator, not a different deadlock-prone locking order. This clarification is approved with the plan and recorded in the WP1 contract.

Retain the existing tenant → Shop → active-membership guard before domain locks, Cash guard before domain effects, compatible original commercial/Product/position ordering for return paths, then stable Receivable/payment/adjustment locks. Serialize all relevant writers through the existing Shop boundary; permission/access/membership are rechecked inside it. Recompute balances under lock. Audit and domain effects share the transaction; publish server changes last under the existing generation lock, with no later domain-lock acquisition. Document collection-versus-return and correction-versus-collection lock traces. A concurrency loser gets a useful refresh/review result, never a silent reallocation from a stale preview.

### Payment identity, channel and safety

Each collection has a permanent client-generated public UUID. Each record is **one Cash or one Transfer payment**, matching the approved schema; two channels require two clearly separate records, not a hidden mixed payment or wallet. Optional transfer reference is bounded, not a credential or proof the bank settled it. Collection cannot exceed actual outstanding; no unapplied remainder is saved.

The review displays authoritative proposed allocations and a fingerprint of their relevant state. Confirmation repeats validation under lock and rejects changed balances for fresh review. A retry with the same UUID and equivalent command returns the existing scoped result without a second effect; changed content with that UUID conflicts. Authority is still checked on recovery. A timeout means 'We could not confirm whether this payment was saved'. Retain the UUID, query its status, and do not generate a new identity or automatically post again. No generic Sync Receipt, durable outbox or full offline transport is added before Phase 10.

Cash collection and any Cash compensation require finalized Opening Cash and no blocking active Cash control. Transfer-only collection does not. Future reconciliation consumes signed Cash collection/compensation evidence; no new Phase 7 snapshot or cash-count screen is introduced now.

### Correction boundaries

A payment reversal is a new same-Customer, same-scope, same-channel, equal-amount record linked to the original, with matching reversing allocations. It restores debt; originals stay untouched. One direct reversal per original; duplicate requests are repeat-safe; do not create reversal-of-reversal chains as a substitute for a new corrected collection. A corrected replacement has a distinct UUID and visible correlation to the reversal. A rejected replacement must not be presented as saved; explicitly show that the original was already reversed if correction is a two-step workflow. Required reasons and separate permission apply.

Allow only evidence-backed factual debt corrections through existing adjustment types. No arbitrary 'set balance', forgiveness, interest, penalties or unrestricted write-off. Positive/negative corrections show amount, source bill, before/after and reason. Reversal is an equal-opposite linked adjustment. Sales-owned return adjustments are corrected through the Sales return-reversal command, not a second generic debt button. Common adjustment insertion participates in its caller's transaction and never commits independently. Debt is the owner of balance semantics; Sales remains owner of stock/commercial orchestration.

Before a reversal, inspect dependencies, including refunds funded by collections, subsequent adjustments and prior commercial compensation. If undoing a receipt would duplicate money already refunded or invalidate later evidence, reject with the linked records and a safe correction order. Never silently move allocations or invent a customer credit balance. A collected original Sale remains ineligible for exact cancellation until its dependencies are corrected safely; this is a controlled dependency rule, not an excuse to omit normal repayment-aware returns.

### Repayment-aware returns and refund capacity

Normal returns reduce the original bill's current outstanding first, then refund the remaining eligible return value. Calculate per-channel available paid value from original receipts **plus effective collection allocations attributable to that bill**, minus effective attributable refunds. Do not count the entire payment if it paid several bills. Payment/return compensation must affect capacity exactly once. No refund can exceed eligible commercial return value or remaining attributable paid capacity. Keep historical quantity/cost/profit and Inventory reversal constraints intact.

Example: bill ₦1,000; Cash collection ₦600 leaves ₦400. Return worth ₦500 reduces debt by ₦400 and refunds ₦100 from eligible paid capacity, leaving debt ₦0. Reversing that ₦600 collection while its ₦100 refund remains effective must be blocked until the dependent evidence is corrected. An original collection split over bills must not provide refund capacity twice.

### Statements and confidentiality

Statements show accepted event order `(posted_at,id)` with a stable tie-break across event types; display occurrence/business time separately where it differs. Show credit created, collection allocations, returns, factual adjustments and their reversals with clear signed debt effects and links. A collection spanning bills appears with one payment total and drill-down allocations, without counting both total and allocation rows as separate debt reductions.

Date filters use accepted posting time converted to Shop-local dates, labelled “Date recorded”; separately displayed occurrence time never silently changes the historical running order. Group one atomic business event with its supporting allocations so intermediate ledger rows do not appear as independent receipts. A filtered statement includes opening debt before the range, changes in range and closing debt; pagination carries forward the running balance from all preceding accepted events, not just the visible page. Bind cursors to Customer, scope, filters and a consistent balance/version anchor. If relevant evidence changes between pages, require refresh rather than mixing two versions of the balance. Define this contract in WP1; deliver the complete statement in WP4. A read-only repeatable snapshot is not a persisted editable ledger.

Customer identity is Tenant-owned; balances, collections, statements and access are current-Shop scoped. No cross-Shop dashboard is added. Private phone/notes retain `customer.private`; no costs/profit/internal numeric IDs leak through debt pages, receipts, Print, lookup or API errors. Customer is a business record, not a new login/portal role. Platform Admin receives no ordinary Shop collection authority.

## 5. Approved permission separation

| Action | Required Staff grants (Owner derives known-code authority) |
|---|---|
| Debt list/detail, bill balances, payment history/receipt, statement | `customer.view` + **new `debt.view`** |
| Normal automatic allocation preview/post | Above + existing `debt.payment.record` |
| Choose explicit bill allocations | Above + **new `debt.payment.allocate`** |
| Factual adjustment preview/post and its permitted inverse | View + **new `debt.adjust`**; no implied collection authority |
| Payment reversal/correction | View + **new `debt.payment.reverse`**; replacement also needs `debt.payment.record` and explicit-allocation grant when used |
| Read/edit phone and notes | Existing `customer.private`, with existing lifecycle permissions when editing identity |
| Return/reverse Sale or Return | Existing `sale.view` + `sale.return` / `sale.reverse`; common balance service is an internal participant, not a grant of general debt actions |

Keep `debt.manage` registered for compatibility, without a catch-all authorization shortcut. New grants are explicit; do not silently grant existing Staff more powers. READ_ONLY permits authorized reads, not collection/adjust/reverse; BLOCKED/CLOSED and inactive membership deny according to existing access policy. Hide unauthorized actions and also enforce direct HTTP/service denial. Update the server permission catalogue, Shop capabilities and navigation together.

## 6. Work-package delivery contracts

### WP1 — Debt foundation and live balances

**Outcome:** the Owner can inspect live customer debt accurately before money can be collected.

Deliver the exact debt-payment and allocation schema from v1.1 in parent-first migrations with statement-equivalent MySQL SQL, same-scope FKs, correct Shield user type, unique public/reversal/parent-bill keys, positive DECIMAL(19,2) amounts and documented aggregate service checks. Register migration order; preserve existing data. DDL implicit-commit recovery and safe development rollback must be documented. Never drop/recreate an accepted Receivable table to add collection.

Deliver a shared allocation-aware, signed-compensation-aware balance/query contract. Handle the absence/presence of the new tables deliberately during rollout, not with scattered ad-hoc schema checks. Extract the common adjustment participant preserving Sales callers. Define API payloads, public IDs/recovery reads, review versioning, action/change vocabulary, lock traces, correction/refund rules, statement ordering/versioning and permission matrix before WP2. Introduce no public general adjustment writer yet.

Provide customer debt cards, bill detail and unpaid/partly-paid/paid/overdue filters with exact totals and pagination. Extend the existing Customers destination and contextual links rather than crowding the bottom bar. Inactive Customer history stays readable; an existing legitimate debt may be repaid later without reactivating Customer identity or creating another credit Sale. Collection controls are not enabled before WP2.

**Manual gate:** P5-01–P5-10. Deliver `Phase_5_WP1_Integration_Contracts.md`, migration/SQL register updates, module/API notes, manual setup/fixtures and package delivery report. Exit only after Owner accepts correct balances, schema and authority boundaries.

### WP2 — Receive and allocate payments

**Outcome:** an authorized user can receive Cash or Transfer once, see allocation and inspect its receipt/history.

Deliver collection preview/post/status and scoped payment queries; oldest-first/explicit allocation under canonical locks; exact-decimal arithmetic; server revalidation; required Audit for channel, bill allocations and actor; atomic changes for payment, affected Receivables and Customer debt context. Collection changes neither inventory nor original Sale revenue/COGS/profit. Failure of allocation/Audit/change insertion rolls back every effect.

Build the full responsive flow: Customer → 'Receive payment' → amount/channel → automatic preview or authorized 'Choose bills' → review → 'Save payment' → saved receipt/balance. Show a remaining-to-assign amount for explicit selection. Reuse loading buttons, local motion/icon vocabulary, accessible sheets/dialogs, safe alerts, field correction and navigation guards. Receipt/history/status support refresh and same-ID uncertain-result recovery; no offline queue. Printable receipt excludes private/internal fields by default.

**Manual gate:** P5-11–P5-23. Provide a delivery report, API/module/operations notes, exact expected fixtures and checklist. Until WP3, keep the existing explicit guard on collected-debt commercial correction; do not imply the whole phase or return integration is complete. This temporary package boundary is removed/replaced by the approved WP3 rules before phase closure.

### WP3 — Corrections and return integration

**Outcome:** mistakes can be corrected without erasing history or corrupting debt, money or stock.

Deliver payment reversal/replacement and controlled non-return adjustment/inverse workflows, review fingerprints, mandatory reasons, immutable receipts and causal history. Reuse common participants and independent permissions. For any Cash compensation apply Cash control. Recompute dependencies in the authoritative transaction; stale previews cannot authorize unsafe reversals.

Replace the broad COLLECTION_DEPENDENCY guard with actual live outstanding, attributable collection capacity and dependency-aware refund/correction rules. Update `CommercialEvidence`, Return settlement, commercial cancellation eligibility/receipts, live credit read and diagnostics as required. Preserve unchanged historical snapshots and exact Inventory cost/reversal rules. Return/collection races serialize; no negative balances, excess refund or hidden automatic allocation rewrite. Record debt/return correction effects in Audit and change publication with appropriate owning actions.

**Manual gate:** P5-24–P5-36. Include focused Phase 4 regressions rather than rerunning all prior phases. Deliver correction/return contract, operator recovery/dependency guide, read-only diagnostic updates and report. The native collected-return cases must pass before WP3 acceptance.

### WP4 — Customer statements and integrated closure

**Outcome:** a nontechnical Owner can explain every balance and the phase is ready for accepted operational use.

Deliver paginated Customer statement, date filters, opening/closing debt, running balance and linked Sale/payment/return/correction details with consistent versioned pagination. Reuse existing Print conventions and privacy filters for a readable statement; a PDF/CSV export subsystem is not required. Do not turn a statement into a full reporting module. Update plain-English copy, responsive cards/rows, empty/loading/error/read-only/blocked/offline/unknown states and permission-aware task navigation.

Complete integrated manual checks, requirement-to-source-to-case matrix, native migration and concurrency/rollback evidence records, developer/module/API/operations references, coherent source/generated assets and release inventory when affected. Provide delivery report, defect disposition, acceptance template and release/rollback handover. Deployment is a distinct authorized operational action, not implied by passing a build.

**Manual gate:** P5-37–P5-48 plus accepted evidence from preceding packages. No unimplemented collection, correction or return requirement moves to Phase 6 to obtain closure. Explicit Owner whole-phase acceptance closes Phase 5; the next phase then follows the roadmap.

## 7. UI wording and navigation

| Internal concept | User-facing wording |
|---|---|
| Receivables/debt dashboard | Money customers owe |
| Collection | Receive payment |
| Allocation | Bills this payment will cover |
| Oldest-first | Pay the oldest bills first |
| Explicit allocation | Choose bills |
| Outstanding | Still to pay |
| Settled / partial / overdue | Paid / Partly paid / Payment overdue |
| Receivable adjustment | Correct a bill amount |
| Payment reversal | Undo this payment |
| Replacement | Record the correct payment |
| Statement | Customer statement |
| Unknown outcome | We could not confirm whether this payment was saved. Check saved status. |

Use labels with icons, never icon-only financial actions. Always identify Customer, amount, channel, affected bills and resulting balance at review. Explain an input error next to the field with a corrective example. Dangerous confirmations and uncertain outcomes do not auto-dismiss. Supplementary evidence may collapse; essential payment/correction values remain visible. Existing shared shell, tokens, selected brand accents, bundled icons and keyboard/focus contracts remain the standard; no new font/icon dependency or broad UI redesign.

## 8. Coverage and manual acceptance

The [requirements matrix](Phase_5_Requirements_Matrix.csv) assigns all approved scope to a package and native cases. The [manual plan](Phase_5_Manual_Acceptance_Plan.md) supplies P5-01–P5-48 and deterministic fixtures. These are **planned checks, not executed results**.

Every package handover identifies base/head commit, setup/migrations, synthetic fixtures, steps, expected values, result register and known limitations. Owner records observed result, device/browser/environment and defects where available. Package-level acceptance is valid authorization; do not fabricate individual evidence from it. Engineering syntax/build/source checks support but never replace manual acceptance. No automated unit/integration/E2E suite or testing framework is introduced. Use native two-session concurrency and controlled failure verification on an isolated development database with documented safe mechanisms; never inject faults into production.

## 9. Scope exclusions and safeguards

No interest, penalties, late fees, customer wallet, advance/unallocated receipts, reminders/SMS/WhatsApp credits, automatic debt growth, full accounting/general ledger, Customer login/portal, cross-Shop/multi-branch operation, expenses/Owner financial movements (Phase 6), full Reconciliation/devices (Phase 7), analytics/reporting (Phase 8), or offline transport/outbox (Phase 10). Single-channel collection does not add a mixed-channel table. Operational statements are included; a general reporting/export product is not.

No production data repair, destructive rollback, deployment or business mutation is performed by this planning PR. Native migrations/concurrency/HTTP claims require actual observation. Any change of approved schema semantics or refund policy must be visible in the package contract and accepted before its implementation.

## 10. Approval and progression

**Now:** WP1–WP4 and the complete Phase 5 programme are **Owner-accepted and closed on 12 November 2026 (Africa/Lagos)**. See the [dated whole-phase decision](../acceptance/2026-11-12-phase-05-owner-closure.md), [closure report](Phase_5_Closure_Report.md) and updated source/evidence registers. Earlier package delivery-time pending wording is historical; individual native observations not supplied are not fabricated as Pass results.

**Current authorized work:** record closure and preserve the accepted implementation/handover. No Phase 5 implementation package remains awaiting Owner acceptance. Main integration and deployment remain separate; PRs #39–#42 were observed open/unmerged at closure. The next roadmap boundary is Phase 6 — Financial Movements and Cash Accountability, requiring a separately approved package plan before implementation.
