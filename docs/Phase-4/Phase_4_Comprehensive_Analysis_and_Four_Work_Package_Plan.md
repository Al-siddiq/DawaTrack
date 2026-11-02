# Phase 4 comprehensive delivery plan

Date: 2026-11-02 (Africa/Lagos)
Status: Proposed execution breakdown for owner review; no Phase 4 implementation or acceptance is claimed.
Repository baseline: `85a1e0fb26781eb1ef7bf6066fa03c5c1555ed01`.
Phase: Customers, Sales, Payments, Credit and Returns, including mandatory Phase 4A Opening Cash.
Delivery structure: four sequential, individually reviewable and manually acceptable work packages.

## 1. Purpose and authority

Deliver the whole approved Phase 4 without reducing its scope or hiding unfinished work behind a single large delivery. Four packages are the minimum reasonable split for this codebase: Opening Cash is an independent prerequisite; Customer/draft work is a usable preparation slice; Sale posting is a substantial atomic commercial boundary; Returns and compensation need that posted evidence. Splitting every channel, layer or screen separately would fragment shared invariants. Combining these four boundaries would make acceptance and financial correctness harder to inspect.

The authoritative sources are:

- [Strategic Roadmap v1.1, Phase 4 and Phase 4A](../DawaTrack_Strategic_Phased_Implementation_Roadmap.md#phase-4--customers-sales-payments-credit-and-returns).
- [Database / Schema Design v1.1](../DawaTrack_Database_Schema_Design.md), sections 3, 7–10, 12, 15–16, 19 and 21–26.
- [Technical Blueprint](../DawaTrack_Technical_Blueprint.md), especially tenancy/authority, Sales/Payments/Profit, inventory, reconciliation, Audit and offline boundaries.
- [Phase 3 closure and Inventory integration contract](../Phase-3/Phase_3_Closure_Report.md).
- [API conventions](../api/conventions.md), [Audit contract](../audit-contract.md), [change-sequence contract](../sync/phase-02-change-sequence.md), [migration register](../database/migration-register.md).
- [Recorded owner acceptance of Phases 1–3](../acceptance/2026-11-02-foundation-owner-acceptance.md).

The roadmap controls delivery timing and explicit deferrals. Database v1.1 controls persistence and cross-record invariants. Existing implementation constrains integration, but does not override the approved business rules. A discovered conflict is documented and resolved before dependent code; it is never silently treated as permission to reduce scope. The original Business & Product Blueprint's broader commercial “Phase 1 MVP” is not the same numbering as the engineering roadmap's Phase 1.

## 2. Entry baseline and limited stabilization

The owner has accepted the current Phases 1–3 foundation through manual use. Preserve that acceptance; do not demand wholesale repetition of previous phases.

Before authoritative Sale/Return mutations are delivered, recheck and resolve the high-priority baseline findings: MFA freshness, draft Intake/posting locking, and the Staff reset/Audit metadata conflict. WP1 records disposition, changes and focused owner verification. Other open findings remain explicitly tracked and are assigned a disposition; a finding affecting the safety of a package must be addressed before that package is accepted. Do not describe documentation acceptance as a code fix.

This is bounded enabling work inside WP1, not an extra feature phase or a license to redesign unrelated modules. Use a development database and synthetic data. No production migration, data repair or deployment is authorized by this plan.

## 3. End-of-phase outcome

An authorized user can establish Opening Cash, manage Customers, prepare and post multi-item Sales with Cash/Transfer/credit composition, inspect receipts/history and historical gross profit, return eligible goods with correct stock and refund/debt effects, and make controlled linked reversals/corrections. All authoritative effects preserve scope, original evidence, exact decimals, transaction atomicity, Audit and change publication.

Cash-affecting operations cannot be accepted before the Shop's finalized Opening Cash control. Credit is unpaid commercial value, never a payment row. Phase 4 is online-only; network loss does not imply a completed transaction or offline queue.

## 4. Package sequence and acceptance

| Package | Deliverable | Depends on | Owner can verify |
|---|---|---|---|
| WP1 | Opening Cash control, shared contracts and enabling stabilization | Accepted foundation | Start, enter, review, finalize and inspect one Cash baseline |
| WP2 | Customers and usable Sale drafts, full commercial schema | WP1 contracts/schema | Customer lifecycle, searchable basket, payment/credit preparation and draft recovery |
| WP3 | Atomic Sale posting, payments, Receivables, Inventory effects, receipts/history | WP1 and WP2 accepted | Every payment/credit variant and corresponding immutable evidence |
| WP4 | Returns, reversals/corrections/replacement, integrated Phase 4 closure | WP3 accepted | Full corrective workflows and whole-phase manual acceptance |

Deliver sequentially. Every package includes its own backend, API, permission-aware responsive interface, migration/raw-SQL work where required, documentation and manual checks. WP4 does not serve as a dumping ground for missing WP1–WP3 screens, permissions or evidence.

## 5. WP1 — Opening Cash control and integration contracts

### Objective and scope

Complete the mandatory Phase 4A prerequisite as a real online Owner workflow. Deliver authorization, start, one physical Cash amount, review/commit/finalize, retained abort where applicable, finalized receipt/detail and clear current-baseline status. Zero is a valid baseline; negative physical cash is rejected. No denomination breakdown, Transfer count, standard Cash/Stock/Full reconciliation or Device readiness workflow is added.

Introduce complete v1.1 definitions for `reconciliation_sessions` and `reconciliation_financial_counts`, including columns needed by later phases. Exposing only OPENING/CASH now must not produce a reduced schema that Phase 7 must replace. Add existing-parent/self FKs in dependency-safe order; no placeholders for nonexistent future tables.

Implement lifecycle using the approved COUNTING, COUNTS_COMMITTED, REVIEW, FINALIZED and ABORTED vocabulary as applicable. Finalized and aborted observations remain protected. A new Opening is allowed after an aborted attempt only when no finalized Cash predecessor exists; overlapping active Cash controls and second finalized openings are rejected under the Shop lock.

For OPENING: exactly one CASH row; entered baseline = expected = actual; variance and all activity components = zero; no previous Cash session; Cash period start and cutoff are the confirmation instant. Obtain the generation-bound watermark at the authoritative confirmation boundary and preserve it through finalization; document how any intervening generation transition is rejected/revalidated. Never recalculate a finalized cutoff later. Cash operations remain blocked until finalization.

### Shared engineering contracts

- Define a reusable Reconciliation-owned Cash baseline/active-scope guard. Sales calls it; later Debt/Financial Movement modules reuse it. Sales does not own Cash baseline data.
- Establish a compatible lock hierarchy for Shop/scope guards, original commercial evidence, Products/positions, Receivables and publication. Read/check opening or active scope before domain mutation; lock the active Sync Generation only after all domain locks and required Audit. No domain lock is acquired afterwards.
- Define focused Inventory-owned Sale/Return/compensation operations participating in the caller's single connection/transaction. No service commits its caller's transaction.
- Define exact-decimal commercial calculation, UUID/retry identity, error-envelope, permission, Audit-action/resource and change-resource contracts used by WP2–WP4.
- Resolve the policy register in section 10 before dependent behavior is implemented.
- Record limited stabilization dispositions and focused re-verification.

### UI and security

Owner receives one plainly labeled physical Cash amount, review/confirm, immutable receipt and explanation of why Cash sales are unavailable until finalized. Staff authority is explicitly denied unless an approved rule grants it; do not equate a generic reconciliation permission with permission to replace the Owner's first Cash control. Retain permission checks in services and direct APIs, not only navigation.

Use server-derived ShopContext, AccessGate, CSRF and existing session/MFA boundaries. READ_ONLY disallows start/count/finalize mutations; BLOCKED/CLOSED deny normal access according to the established policy. View history only under the documented read policy.

### Audit/change and acceptance

Audit authoritative creation/count/finalization/abort with actor, correlation and required reason policy. Publish approved session changes atomically. Extend closed registries explicitly; no free-form resource names.

Owner checks: no baseline versus finalized baseline; zero/positive/invalid amounts; confirm once; duplicate finalization; refresh/reopen; parallel start/finalize; wrong Shop IDs; Staff denial; expired entitlement/session; abort/restart; rollback on failed Audit/change; preserved cutoff/generation; phone/desktop/keyboard states. No denomination or offline workflow exists.

Exit: a protected, correctly scoped Opening Cash baseline can be established and inspected; guard and transaction contracts are documented; dependent policy decisions and high-priority foundation fixes have dispositions sufficient to proceed.

## 6. WP2 — Customers and sale preparation

### Objective and schema

Deliver Customer management and a functional online Sale draft workflow without creating stock, money or debt effects.

Create `customers`, `sales`, `sale_items`, `sale_payments`, `receivables` and `receivable_adjustments` with complete v1.1 definitions. Create base parents first; add mutual/self/deferred FKs after their parents exist, including `stock_movements.sale_item_id` to the scoped Sale Item key. Preserve existing Inventory evidence and exact Shield user FK types. Include named indexes, unique constraints, UUID/type/sign/lifecycle checks and paired raw SQL; record ordering in the migration register. Cross-row totals require service validation, not fictitious aggregate CHECK constraints.

Customer is Tenant-owned, not Shop-owned: do not invent a Customer.shop_id column. Resolve it inside the authenticated Tenant; commercial Sales/Receivables remain in the current Shop. Duplicate or absent phone numbers are legitimate. Customer balance is derived, never manually editable.

### Functional delivery

- Create, search, paginate, view, edit and deactivate Customers while retaining historical references.
- Mask contact/notes according to permissions. Customer quick-create/select works inside cashier preparation.
- Prepare a multi-item Sale basket with Product search, base-unit quantities, permitted unit selling price, line totals and stable line order.
- Draft create/read/edit/remove/discard, Customer selection, optional due date and Cash/Transfer amount preparation; show amount due, paid and credit remainder clearly.
- Drafts survive a deliberate page refresh through the approved online draft flow; they are not an IndexedDB outbox.
- Revalidate active Products/Customers at authoritative posting later. Historical references to inactive records remain readable under permission.
- Identify invalid quantity/price/date, excessive values and invalid payment composition without losing the usable basket.
- Lock draft headers and recheck DRAFT inside every mutation transaction, using the same protocol posting will use. No stale draft edit can change posted evidence.
- Use permanent Sale public identity for the future post/retry path. Any transient payment-entry storage must have a declared contract; do not persist authoritative sale_payments before posting or silently add columns/tables.
- Final posting is not exposed as a working button in this package; clearly distinguish review estimates from posted price/cost evidence.

### Permissions, evidence and owner acceptance

Reuse `sale.record` and extend the closed registry with narrowly justified Customer/read permissions where needed; publish the exact Owner/Staff matrix. Customer management must not grant Sale reversal, repayment or unrestricted debt adjustment.

Audit Customer lifecycle and publish Customer changes in their transaction. Explicitly document the draft-evidence policy: no inventory/money/Receivable/change effect; audit material draft activity only according to the approved contract. Do not log raw contact payloads unnecessarily.

Owner checks: Customer CRUD/deactivation, duplicate/missing phone, quick-create, scope and masking, basket edits/discard/refresh, mixed/credit previews, optional due date, inactive records, forged totals/IDs, draft race protection and denied/read-only/offline states.

Exit: Customer and draft preparation work on phone and desktop; the commercial schema and late FKs exist; no posted business effects can occur yet.

## 7. WP3 — Sale posting, payment composition and credit

### Objective

Deliver the complete forward commercial transaction and its receipt/history. Support Cash-only, Transfer-only, Cash+Transfer, full credit, Cash+credit, Transfer+credit and Cash+Transfer+credit, for single/multiple Products and fractional quantities.

### Authoritative posting

1. Derive scope and recheck identity, permissions, lifecycle and entitlement. Lock/revalidate the Draft; posted state cannot be reopened for editing.
2. Enforce finalized Opening Cash and active reconciliation-scope rules for any Cash effect. Do not unnecessarily require a Cash baseline for a pure Transfer or full-credit Sale with no Cash effect.
3. Validate scoped active Product/Customer references and permitted price/quantity/payment/date inputs. Credit requires an identified Customer; due date remains optional.
4. Lock all Products/positions in ascending Product ID and apply repeated Product lines deterministically. Use the Inventory-owned operation on the same connection and transaction.
5. Recompute line amounts and COGS using exact decimals; snapshot current permitted selling prices and applicable historical cost basis. Normal outgoing Sales do not recalculate WAC. Preserve VALID/RETAINED/UNESTABLISHED semantics under the resolved cost policy.
6. Create SALE movements linked to Sale Items, update positions/pointers/versions and preserve negative stock as valid visible state.
7. Insert actual positive CASH/TRANSFER RECEIPT rows. Payment total + credit = Sale total. Reject overpayment; no wallet/change-balance subsystem is added.
8. Persist SALE received/credit snapshots and exactly one positive Receivable when credit exists; no Receivable for a fully paid Sale. Due date uses local business-date semantics. No interest, penalty or fake CREDIT payment row.
9. Atomically post header/items, payments, Receivable, movements/positions, Audit and changes; rollback all on any failure.

### Queries and cashier experience

Deliver fast searchable cashier, large quantity controls, collapsible basket, sticky totals/checkout and simple two-amount mixed payment input. Review/confirm, pending/double-tap protection, successful receipt, paginated Sale history, immutable detail, actor/Customer context and links to stock evidence are usable on phone and desktop.

Display historical line/header gross profit as net commercial Sale amount minus historical COGS, labeled “Gross profit (before expenses)”. This is Sale evidence presentation, not Phase 8 aggregate analytics or net-profit reporting. Credit revenue/COGS is recognized at Sale posting, not on future collection.

On timeout/unknown outcome, preserve the Sale UUID, query its authoritative state and offer safe continuation. Do not generate a new Sale automatically, retry non-idempotent writes blindly or promise durable generic replay. Server locking/unique identity/transition checks prevent a second post; a repeat returns existing state or the documented conflict with a retrieval path.

### Evidence and manual acceptance

Extend Audit vocabulary and change resources for Sale, Customer/Receivable as required, immutable payments and stock effects; publish only approved resource identities. Extend Inventory diagnostics to recognize valid SALE/CUSTOMER_RETURN/reversal evidence rather than reporting every new type as a Phase 3 anomaly.

Owner checks every payment combination, Customerless paid sale, credit without Customer denial, supplied versus recomputed totals, price/cost snapshots after Product changes, due date/local dates, negative stock and retained cost, repeated Products, two concurrent sales, duplicate posting, response loss, stale draft editing, rollback injection, wrong scope, permission separation and responsive receipts/history.

Exit: all forward Sale variants produce correct atomic evidence; originals cannot be edited/deleted; no debt repayment or Return endpoint is falsely presented as delivered.

## 8. WP4 — Returns, protected correction and complete Phase 4 closure

### Linked Return workflow

Deliver partial/full Return from an original posted Sale, original-line selection, eligible remaining quantities, outcome preview/reason, confirmation, receipt and linked history. Use sales.kind=RETURN and original_sale_id/original_sale_item_id, not an alternate return ledger.

Lock original Sale/items, relevant positions and Receivable in the agreed hierarchy. Recompute effective returned quantities including compensations; concurrent Returns cannot exceed original net eligible quantities. Original Product identity and base unit are retained; deactivation does not erase eligibility to correct prior evidence.

Snapshot the approved historical commercial/cost basis and post CUSTOMER_RETURN stock increases through Inventory ownership. Never quietly price a Return using today's Product price, invent a second WAC engine or rewrite old COGS. Section 10's return-cost policy must be explicit.

Return header received/credit-created snapshots are NULL. Actual refunds use positive CASH/TRANSFER REFUND rows; credit reductions use negative RETURN_REDUCTION adjustments linked to the Return Sale and original Receivable. Their total equals Return commercial value. Recompute outstanding under lock and prevent debt below zero. Apply the approved allocation rule for partial-credit/previously compensated cases. Credit reduction is not money received; no fake debt payment is created.

Any Cash refund also requires Opening Cash and the same active-scope protection. Enforce cumulative refund/adjustment and quantity limits and same original Customer/scope. Preserve original Sale, payment, Receivable and stock evidence.

### Reversal, correction and replacement

Deliver distinct, permission-protected, reasoned correction commands for posted Sale/Return evidence. Define eligible cases and linked compensation graphs before implementation. One direct reversal per original; reversal-of-reversal handling must be explicit. A correction is compensation plus linked replacement where applicable, never UPDATE/DELETE of posted contents.

Compensate stock, actual money and Receivable effects together; preserve historic snapshots. Prevent reversal of an original whose effective Returns or other dependent evidence would become inconsistent. Either reject safely with an actionable explanation or follow an explicitly approved complete compensation sequence; do not guess, delete child history or introduce Phase 5 collection features.

Reversal of a Return compensates its stock/refund/RETURN_REDUCTION effects through approved links. Do not classify that simply as a new purchase receipt. Expose which effects are reversed, remaining eligibility and any replacement link. Draft replacement has no authoritative effect until posted.

### Whole-phase closure

Complete Return/reversal UI with phone/desktop parity, scope/permission masking, accessible confirmation, empty/error/expired-session/read-only/offline/pending states and prevention of duplicate interaction. Add read-only invariant diagnostics for commercial totals, payment/credit composition, Return limits, orphan scope, adjustment bounds and stock evidence, without automatic repair.

Execute the complete acceptance matrix below, plus regression checks on the affected Phases 1–3 workflows. Document applied migration order, observed results, unresolved issues and owner decision. Update module/API/security/permission/Audit/change/migration/runbook/manual-verification documentation and final Phase 4 closure status. Historical reports remain unchanged.

Exit: every Phase 4 requirement is delivered and manually accepted, or the phase stays explicitly incomplete. No unfinished Return/correction, payment variant, UI, permission, rollback or concurrency requirement is deferred to Phase 5.

## 9. Requirement coverage and owner verification matrix

Each listed check records PASS/FAIL/BLOCKED with tested commit, environment, observed result and optional evidence. Owner manual verification is the acceptance gate. This plan does not claim any execution results.

| ID | Required capability / manual check | Delivery owner |
|---|---|---|
| P4-01 | Full v1.1 Opening Cash schema and existing/self FK ordering | WP1 |
| P4-02 | One physical Cash amount; zero/positive accepted, invalid rejected; no denominations | WP1 |
| P4-03 | Opening start/count/review/finalize, abort/restart and immutable receipt | WP1 |
| P4-04 | One active scope/first finalized baseline, concurrent start/finalize, generation/watermark | WP1 |
| P4-05 | Cash sale/refund rejected before baseline; pure Transfer/credit not misclassified | WP3–WP4, WP1 guard |
| P4-06 | Complete six commercial tables and late Stock Movement/Sale Item FK | WP2 |
| P4-07 | Customer create/search/detail/edit/deactivate; shared/missing phone | WP2 |
| P4-08 | Tenant-scoped Customer identity, privacy/masking, cashier quick-create | WP2 |
| P4-09 | Multi-item/fractional basket; draft CRUD/discard/refresh and safe draft/post locking | WP2–WP3 |
| P4-10 | Cash-only, Transfer-only and Cash+Transfer Sales | WP3 |
| P4-11 | Full credit and all three partial-credit payment combinations | WP3 |
| P4-12 | Customer required for credit; optional valid local due date; no fake payment/interest | WP3 |
| P4-13 | Server totals, exact decimal rounding, overflow, invalid price/quantity/composition | WP3 |
| P4-14 | Historical price/cost/COGS and gross profit remain correct after Product changes | WP3–WP4 |
| P4-15 | Negative stock allowed/visible; cost state preserved; repeated Product lines | WP3 |
| P4-16 | Sorted locks, concurrent sales/Intake, no lost movement/position update | WP3 |
| P4-17 | Double tap, repeat post, timeout/response loss, stable identity/retrieval | WP3–WP4 |
| P4-18 | Receipt, paginated history/detail, actor and stock evidence links | WP3–WP4 |
| P4-19 | Partial/full Cash/Transfer Return with correct refund and stock increase | WP4 |
| P4-20 | Full/partial-credit Return, adjustment/refund split and nonnegative outstanding | WP4 |
| P4-21 | Cumulative/concurrent over-return and over-refund denial; inactive historical Product | WP4 |
| P4-22 | Return totals = refunds + debt reduction; RETURN snapshots NULL | WP4 |
| P4-23 | Sale and Return reversals/corrections/replacement; original immutable; duplicate denied | WP4 |
| P4-24 | Dependency conflicts reject safely; compensation effects and profit signs correct | WP4 |
| P4-25 | Audit actor/reason/metadata/correlation and atomic changes for every authoritative command | Every WP |
| P4-26 | Failure of Audit/change/stock/payment/Receivable write leaves no partial authoritative result | Every mutation WP |
| P4-27 | Owner/Staff granular view/create/return/reverse authority; direct API denial | Every WP |
| P4-28 | Cross-Tenant/Shop IDs, deactivated membership, READ_ONLY/BLOCKED/CLOSED denial | Every WP |
| P4-29 | Phone/desktop cashier and corrections; keyboard/focus/zoom, loading/error/empty states | Every WP |
| P4-30 | Offline clearly unavailable; no queued success or generic replay claim | Every WP |
| P4-31 | Fresh ordered migrations, repeat no-op, paired SQL and non-destructive upgrade | Every schema WP |
| P4-32 | Existing Product/Intake/reversal/permissions/Audit flows remain intact | Every WP; integrated WP4 |
| P4-33 | Documentation, diagnostics, itemized delivery evidence and owner final acceptance | Every WP; closure WP4 |

## 10. Explicit decision register and interpretation boundaries

These decisions must be recorded in WP1 contracts and resolved before their dependent code. Recommended directions below are engineering proposals, not changes to the approved documents or assumed owner approvals.

| Decision | Source constraint | Required resolution |
|---|---|---|
| Return cost basis and current average-cost effect | Historical Sale COGS must remain meaningful; stock increase must use Inventory ownership | Specify original-sale cost attribution, return MWA/cost-state behavior, negative/nonpositive transitions and reversal behavior with numeric examples; do not assume purchase-Intake arithmetic is automatically correct |
| Partial-credit Return allocation | Refund + debt reduction equals Return total; outstanding cannot go negative | Decide debt-reduction/refund ordering, eligible channels and cumulative limits, including previously returned/reversed evidence and future collected-debt compatibility |
| Compensation graph | Posted evidence insert-only with explicit Sale/payment/adjustment links | Specify kind, direction/sign, original/reversal/replacement links and derived totals for Sale reversal and Return reversal; no ad hoc extra ledger |
| Price authority | Snapshot selling price, validate permissions | Decide whether cashier can override current price and under which permission; recompute all totals server-side |
| Draft payment preparation | sale_payments are authoritative insert-only evidence | Specify preview/transient storage and reload semantics without unauthorized schema additions |
| Unestablished cost | Existing Inventory permits UNESTABLISHED stock and negative quantity | Specify honest cost-basis display and permitted posting behavior; never label unknown basis as verified profit |
| Draft audit policy | Drafts may be discarded; material actions may require Audit | Define materiality and exact vocabulary; never publish a draft as posted business evidence |
| Granular permissions | Roadmap requires Customer/Sale/Return/reverse separation | Finalize missing view/manage/return registry codes, Owner/Staff matrix and Customer/COGS visibility without a blanket debt-management bypass |
| Scope wording | Customer and Product are Tenant-owned; sales/positions are Shop-scoped | Implement current-Shop availability and Tenant-owned reference validation; do not add invented Shop ownership to Customer |
| Pre-Phase-10 retry semantics | Roadmap reserves durable receipts/replay for Phase 10 | Use permanent draft/Sale UUIDs, locks/constraints and authoritative retrieval; carry operation identity/causality only through approved contracts |
| Opening lifecycle and confirmation instant | OPENING is first Cash control with confirmation cutoff | State when confirmation occurs, how review/finalization retains it, how generation changes are handled and how aborted attempts retain evidence |

If a decision requires a blueprint/schema amendment rather than ordinary implementation detail, present the precise amendment for owner decision before dependent implementation. Do not quietly narrow Returns to “fully paid only” or remove credit/compensation cases to avoid a decision.

## 11. Database, API and module boundaries

Eight new tables belong to Phase 4: two Reconciliation tables in WP1, six commercial tables in WP2. Do not create debt_payments, debt_payment_allocations, financial_movements, general reconciliation counts/variance/device tables or Sync Receipt tables early. Future-parent FKs remain deferred and registered. Complete definitions do not mean exposing later-phase workflows.

Suggested ownership follows existing style: Reconciliation Application services/queries; CustomerDebt Application services/queries for Customer and Receivable semantics; Sales Application draft/post/return/compensation services and read queries; Inventory-owned stock effects. Final names follow repository conventions. Avoid generic “manager” classes, a second costing engine, direct controller SQL business mutations or internal service commits.

Expose explicit versioned Customer, Sale draft/post/history, original-linked Return and compensation routes under the established Shop API; exact paths and error codes are documented per package. No generic posted-record PATCH/DELETE. Public UUIDs, bounded pagination, safe response mappers, same-origin CSRF and safe errors follow existing conventions.

Publish an explicit resource/change matrix for Customer, Sale, Receivable, approved adjustments/payment evidence, logical Shop/Product position, Movement and Opening session. Do not add arbitrary resources or assume every table needs a new public resource; ensure every client-visible authoritative effect has an agreed refresh path. Audit never substitutes for the business FK graph.

## 12. Delivery contract for every package

A package is a complete scoped delivery, not a promise that backend/UI/evidence will be finished later. Deliver:

1. Scope and requirement IDs covered; code baseline and resulting commit.
2. Files changed, migrations/paired SQL and precise apply/rollback guidance for development.
3. Working backend/API and responsive UI for that package.
4. Permission matrix, safe response/errors, Audit and change contracts.
5. Exact financial/stock examples where relevant; no floating-point persisted money.
6. Manual checklist with setup, actions, expected results and cleanup; two-session steps for races.
7. Executed engineering checks reported accurately; no blocked check relabeled PASS.
8. Owner PASS/FAIL/BLOCKED results and acceptance recorded after the owner reports them.
9. Known issues and remaining scope explicitly assigned to the next planned package.

Appropriate syntax/build/static checks support manual acceptance. Targeted automated checks may support implementation where useful, but do not replace the owner's chosen acceptance process or make a new broad test framework a hidden project prerequisite.

## 13. Worked acceptance examples

All amounts are illustrative test fixtures, not production data.

- Sale total NGN 20,000: CASH 5,000 + TRANSFER 5,000 + credit 10,000. Persist two Receipt rows totaling 10,000 and one Receivable for 10,000. No CREDIT payment row. Customer required.
- Full credit NGN 8,000: no payment row; one Customer-linked Receivable for 8,000; stock and COGS recognized immediately.
- Quantity 3 at price NGN 100, cost NGN 60: line sales 300, COGS 180, gross profit 120. Later Product price/cost edits do not change those snapshots.
- Return commercial value NGN 2,000: refunds plus RETURN_REDUCTION must equal 2,000. Allocation is tested against the resolved partial-credit policy; never subtract 2,000 from both money and debt.
- Two requests to post the same Sale UUID: at most one committed set of Sale/payment/Receivable/stock/Audit/change effects.
- Force change publication failure: no Sale, cash receipt, debt or position effect is left partially committed.
- Original Sale already partially returned: a new Return/reversal must compute effective eligibility and cannot silently compensate the full original a second time.

## 14. Explicit exclusions and phase completion

Phase 5 owns collections/allocations and Customer statements; Phase 6 owns non-sale Financial Movements; Phase 7 expands full Reconciliation; Phase 8 owns aggregate reporting; Phase 10 owns registered-device offline operation, outbox, push/pull/cursors and durable receipts/replay. No interest, penalties, wallet/advance balances, bank reconciliation, accounting/general ledger, barcode/conversion, Supplier/PO or multi-branch feature is introduced.

Customer identity, creation of Sale-linked credit, Return debt reduction and historical Sale gross-profit detail are Phase 4 responsibilities and cannot be postponed under those exclusions.

Phase 4 completion requires all four package exit gates, all P4 requirements accounted for, resolved policy decisions, no unresolved critical/high defect affecting delivered workflows, and the owner's integrated manual acceptance. If an essential criterion is FAIL/BLOCKED, report the phase as incomplete; never reduce scope to claim closure. The next phase then builds on this complete, protected commercial foundation.
