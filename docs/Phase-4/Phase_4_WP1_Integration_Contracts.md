# Phase 4 WP1 integration contracts

Date: 2026-11-02. Scope: implemented Opening Cash plus contracts for subsequent packages. The approved four-package plan remains authoritative; this file does not implement Sales or Returns.

## Implemented Opening Cash boundary

Only the current ACTIVE PRIMARY_OWNER may access these APIs. Reconciliation grants do not grant Staff authority to create a Shop's first Cash baseline. ShopContext is server-derived. Owner READ_ONLY may view; every mutation requires FULL access. BLOCKED/CLOSED deny these resources. Both controller and mutation service enforce their boundary; service rechecks Owner membership under lock.

Lifecycle: COUNTING (enter/save one amount) → COUNTS_COMMITTED (explicit physical confirmation) → REVIEW → FINALIZED. Any nonterminal attempt may be ABORTED with a required reason; observations remain. No delete/update endpoint exists for terminal evidence. Abort permits a fresh UUID attempt only if no Cash baseline has been finalized. A standard/full Cash predecessor also prevents another Opening.

The two full v1.1 tables include future standard/stock period fields, component snapshots and predecessor links; only OPENING/CASH is exposed. The start timestamp/cutoff is provisional while COUNTING. Explicit confirmation binds counts_committed_at, cash_period_starts_at and cutoff_at to the same UTC instant and captures the active generation/current sequence. Review/finalization preserve that cutoff. Generation change after confirmation rejects review/finalize; abort and reconfirm a fresh attempt. A generation change before confirmation is handled by rebinding at confirmation. The cutoff precedes the confirmation's own change notification. Finalization never recalculates the watermark.

Exactly one CASH count exists. Actual = expected = opening baseline, all activity components and variance zero. Zero is legitimate. Cash inputs are decimal JSON strings, max DECIMAL(19,2); binary JSON floats, scientific notation, negative and overprecision inputs are rejected. CashAmount performs string normalization; no monetary float arithmetic occurs.

## API

| Method | Route below /api/v1/shop | Effect |
|---|---|---|
| GET | /opening-cash | Safe active/baseline/latest-50 history representation |
| POST | /opening-cash | Start with required permanent public_id UUIDv4 |
| POST | /opening-cash/{uuid}/count | Save JSON amount string while COUNTING |
| POST | /opening-cash/{uuid}/commit-count | Explicitly confirm saved amount and bind cutoff |
| POST | /opening-cash/{uuid}/review | Move committed evidence to REVIEW |
| POST | /opening-cash/{uuid}/finalize | Finalize REVIEW evidence |
| POST | /opening-cash/{uuid}/abort | Retain abort with JSON reason |

Every successful response returns authoritative state in the standard envelope. Start with the same scoped UUID is repeat-safe. Repeating finalization returns state without another event. Other stale transitions return OPENING_STATE_CONFLICT; unchanged count returns OPENING_UNCHANGED. No generic operation replay or durable receipt exists. After a timeout, retrieve state before retrying. The interface retains the attempt UUID and refreshes state on uncertain outcomes. Responses are private/no-store; offline actions are disabled and never queued. API headers/errors and CSRF follow existing conventions. UTC timestamps are explicitly labeled in the receipt.

Error codes include OPENING_NOT_FOUND, OPENING_COUNT_REQUIRED, OPENING_ALREADY_FINALIZED, CASH_CONTROL_ACTIVE, OPENING_CASH_REQUIRED, OPENING_GENERATION_CHANGED, OPENING_STATE_CONFLICT and VALIDATION_FAILED. Wrong-scope IDs return scoped not-found, not foreign evidence.

## Transactions and lock hierarchy

Opening commands own one transaction: Tenant root → Shop → current Owner membership → session/count rows and Cash predecessor/active checks → mutations → Audit → active generation/watermark → notification inserts → commit. No new domain lock may be acquired after generation locking. bindWatermark updates only a session row already held by the transaction. Any exception rolls back the entire command. MySQL migration DDL itself is not transactionally reversible.

CashControlGuard has no independent commit. Later cash-affecting posting calls assertCanAffectCash(context) inside its owning transaction BEFORE Sale/Product/Position/Receivable locks. It locks/checks the Tenant/Shop, requires a finalized Cash control and denies an active overlapping Cash scope. No guard call is required for pure Transfer/full-credit effects. Do not confuse “Cash baseline exists” with permission, entitlement or reconciliation readiness; callers must enforce their own authority and domain invariants.

Later global order: Tenant/Shop/Cash scope → original Sale/Return and line rows in stable IDs → Products/positions ascending Product ID → Receivables in stable age/ID order → Audit → active generation → changes → caller commit. WP3/WP4 must align all participating writers to that order. Retry only the entire safe transaction on bounded deadlock; never partially commit. Stock-only future scope guards remain Phase 7, not silently implied by this Cash implementation.

## Evidence and refresh contract

The closed Audit registry includes reconciliation.opening_started/counted/committed/reviewed/finalized/aborted, category RECONCILIATION and resource reconciliation_session. Actor is current tenantUser; fixed reasons explain normal steps, abort requires operator reason. Amount and cutoff metadata are bounded and contain no authentication secrets.

Each successful authoritative transition emits one reconciliation_session UPSERT in the same transaction. Counts are part of that aggregate, not a separate unsupported resource. Rejected commands and exact finalized/start retries emit no false-success event. GET reads emit neither Audit nor changes. Device/outbox/pull/receipt transport remains absent.

## Sales and Inventory contracts for WP2–WP4

CommercialDecimal supplies exact validated quantities, extended price and COGS using MySQL DECIMAL intermediates and rounding to kobo. It rejects a result outside the amount precision rather than allowing silent SELECT cast saturation. Header totals sum posted rounded lines; cumulative return rounding must conserve the original cents.

Inventory retains ownership of quantity/cost/state/pointer/version and Movement rows. The caller supplies scoped Product/source identity and validated commercial direction, not arbitrary snapshots. The Inventory participant requires an existing transaction and prelocked ordered Product/position rows, returns snapshot/effect data, and neither starts/commits a separate transaction nor publishes early. WP3 implements the Sale participant; WP4 implements original-linked Return/compensation. Existing Intake/manual services are not a safe substitute because they own their transactions and/or cost semantics. No Sale route is introduced in WP1.

Permanent draft UUID → posting command → response or retrieval is the pre-Phase-10 repeat-safety contract. Operation UUID and correlation UUID are separate; no request ID becomes authorization, replay protection or a business key. New Sales permissions/resources/actions are explicitly added with their implementing package, not arbitrary strings accepted by a generic controller.

## Decision register for subsequent packages

| Area | Contract / disposition |
|---|---|
| Price override | Default to current Product price; Owner may override; Staff override needs a separate explicit sale.price_override grant. WP2/WP3 add the registry code and UI together. No unprivileged arbitrary price. |
| Customer scope | Tenant-owned Customer; Shop-scoped commercial effects. No invented Customer.shop_id. Shared/missing phone accepted; deactivate without erasing references. |
| Draft payment preparation | UI prepares Cash/Transfer values transiently. Reload restores persisted basket/Customer but payment entries must be re-entered unless existing schema fields legitimately support them. No posted payment rows or new storage column. Label this behavior in WP2. |
| Draft evidence | No authoritative stock/money/debt or sync publication; explicit discard of a material saved Sale draft is audited with a reason. Customer mutations are separately audited/published. |
| Unknown acquisition cost | Do not reject negative sales merely because stock is nonpositive. Snapshot numeric basis as maintained by Inventory; UNESTABLISHED zero basis must be labeled unestablished/provisional, not verified profit. Preserve/reconstruct that original cost state from its movement chain; confirm feasibility before WP3 without silently adding snapshot fields. |
| Return quantity/price/rounding | Use original Product/unit and historical commercial snapshots; cumulative per-line allocation gives a final full Return exactly the original line amounts/COGS, not an extra rounding cent. Never today's selling price. |
| Return stock costing | Recommended: restore at original Sale cost attribution through Inventory-owned incoming weighted arithmetic, with original unknown-basis provenance preserved and explicit VALID/RETAINED/UNESTABLISHED examples. Must be checked against final WP4 compensation graph before implementation; not a new purchase Intake or second WAC engine. |
| Partial-credit Return settlement | Recommended: reduce legitimate remaining original Receivable first, cap at outstanding, refund only the remainder supported by effective original paid value; cumulative refunds/debt reductions cannot exceed eligible value. WP5 collections later extend paid-value eligibility through their owned query contract, not fake Phase 4 payments. Owner reviews this policy before WP4 implementation. |
| Compensation schema clarification | OPEN: sales.kind SALE/RETURN normal header/Receivable rules do not fully define the Sale-shaped compensation of a credit Return (which restores an existing Receivable rather than creates a new one). Before WP4, obtain an explicit approved interpretation/amendment for reversal-linked headers, payment directions and REVERSAL adjustments. Do not improvise a second Receivable, mutate originals, narrow credit Returns, or silently exempt normal sale totals. This does not block independent Opening Cash. |
| Permissions | Opening Cash is Owner-only now. WP2–WP4 introduce granular Customer view/manage, Sale view/record/return/reverse and price override with closed-registry checks and privacy/cost visibility matrix; existing debt.manage is not a blanket authorization. |

These later policy recommendations are recorded for review, not represented as owner-approved schema amendments. Any unresolved source-level decision blocks its dependent implementation, not previously accepted phases. WP1 acceptance does not implicitly approve an undocumented accounting rule.
