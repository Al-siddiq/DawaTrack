# Module ownership and dependency direction

- `Controllers/Web` owns server-rendered entry shells and web authentication transitions.
- `Controllers/Api` owns transport mapping only; it does not own business rules or Audit construction.
- `Libraries/Http` owns canonical request correlation and API response contracts.
- `Filters` owns cross-cutting authentication, Platform authority, request context, throttling, session, CSRF, and response-security boundaries.
- `Modules/TenantShop` owns Tenant/Shop context, onboarding, settings, and lifecycle.
- `Modules/Identity` owns memberships, Staff lifecycle, and grants.
- `Modules/BrandingDomain` owns branding, assets, hostname, and domain lifecycle.
- `Modules/Subscription` owns immutable entitlement periods/events and their operational queries.
- `Modules/Audit` owns typed immutable Audit commands, authority/scope validation, vocabulary, reason/metadata/causal policy, append, and safe output representation.
- `Modules/Sync` owns internal commit-ordered change publication and generation-bound watermark acquisition. It does not own business authorization or transport.
- `resources` is editable frontend source; `public/assets` is generated browser output.

Controllers call owning services. Business modules may use the cross-cutting Audit contract, but Audit does not mutate their records. Platform authority and Tenant membership remain separate. No generic repository or controller may bypass established scope.

Phase 2 WP1 adds no Audit query route/UI and no change sequence. Those remain later authorized work.

Phase 2 WP3 extends `Modules/Audit` with focused query parsing, scope-aware keyset queries, safe detail/relationship presentation, and reasoned support-access evidence. `AuditController` maps transport only. Primary Owner authority remains in `ShopContext`; Platform permissions/MFA remain in `PlatformAdminFilter`. Audit reads never call `Modules/Sync` and never emit `sync_changes`.
- `Modules/ProductCatalog` owns Product normalization, validation, lifecycle transactions and scoped Product queries.
- `Modules/Inventory` owns the protected Stock Position threshold command in WP1. Quantity/cost evidence remains deferred to WP2/WP3.
- Phase 3 WP2 extends `Modules/Inventory` with focused Draft, exact-decimal posting and scoped Intake/history query responsibilities. It exposes no WP3 manual/reversal command.
- Phase 3 WP3 adds focused manual movement, Movement reversal, current-suffix Intake reversal, cost-state reconstruction and replacement-link services within `Modules/Inventory`; no alternate ledger or WAC engine exists.

Phase 3 WP4 closes Product Catalog + Inventory as one Shop capability. `InventoryDiagnostics`/`inventory:diagnose` is operator-only, read-only and has no HTTP or Shop UI surface.


## Phase 4 WP1

`Modules/Reconciliation` owns Opening Cash commands/query and the caller-transaction CashControlGuard. `Modules/Sales/Application/CommercialDecimal` supplies only exact calculation primitives; no Sales posting is implemented. OpeningCashController maps transport; OpeningCashPanel is a runtime-rendered Vue component. See Phase-4 integration contracts and the Owner manual verification checklist.

## Phase 4 WP2

CustomerDebt implements Tenant Customer lifecycle/safe queries; Sales implements strict commercial input and current-Shop normal Sale drafts/estimates. Shared CommercialAccess enforces scope authority, FULL mutation access and root lock ordering. Controllers expose only Customer and draft/lookup/preview commands; Inventory remains authoritative for future posted stock/cost effects. See [WP2 contract](phase-04-wp2.md), [delivery](../Phase-4/Phase_4_Work_Package_2_Delivery_Report.md), and [manual acceptance](../manual-verification-phase-04-wp2.md). WP1 is owner-accepted; WP2 acceptance is pending; Phase 4 remains incomplete.

## Phase 4 WP3

Sales owns forward posting, exact money composition, state/receipt/history queries and confirmation/recovery UI. Inventory's `SaleStockParticipant` owns Sale stock/cost/projection effects inside the caller's prelocked transaction. Historical cost state is reconstructed from existing immutable movement evidence; unknown bases are labeled provisional, with independent `sale.profit.view` masking. CustomerDebt schema holds the one optional Sale Receivable; collection remains Phase 5. WP3 adds no schema and activates no Return/reversal workflow. See [contract](phase-04-wp3.md), [delivery](../Phase-4/Phase_4_Work_Package_3_Delivery_Report.md) and [manual verification](../manual-verification-phase-04-wp3.md). WP2 is owner-accepted; WP3 acceptance and Phase 4 closure remain pending.

## Phase 4 WP4 readiness

WP3 owner acceptance was recorded on 2026-11-04. Sales now also exposes operator-only `sales:diagnose` with a scoped, owned read-only snapshot and bounded normal-evidence invariant samples. The explicit compensation-header/Return policy decision is prepared for review; dependent financial writers/UI remain unimplemented. This is not WP4 or Phase 4 completion. See [readiness](../Phase-4/Phase_4_WP4_Implementation_Readiness_and_Decision.md) and [proposed contract](../Phase-4/Phase_4_WP4_Proposed_Return_and_Compensation_Contract.md).


## WP4-B — 2026-11-05

Sales now owns original-linked Return draft/settlement/post orchestration; Inventory owns incoming Return stock/cost/provenance. See [WP4-B](phase-04-wp4-b.md). Correction writers and operator UI remain later increments.


## WP4-C — 2026-11-06

CommercialReversalService coordinates ordinary Sale/Return compensation and linked replacement; Inventory exclusively restores exact eligible before-state. CommercialCorrectionQuery supplies explicit safe compensation graph and recovery reads. See [WP4-C](phase-04-wp4-c.md).


## WP4-D — 2026-11-06

The operator UI now composes B Return and C correction commands through unified Sale/Return/compensation reads. Sales/Inventory write ownership is unchanged. [D integration contract](phase-04-wp4-d.md) and [final native checklist](../manual-verification-phase-04-wp4-d.md) describe the delivered surface and acceptance boundary.


## Phase 5 WP1 — live debt reads and shared adjustment participant

CustomerDebt now owns `DebtQuery`, `ReceivableBalanceQuery`, `DebtSchema`, `DebtReadSnapshot` and `ReceivableAdjustmentParticipant`. The query projects signed adjustment/allocation evidence into live current-Shop balances without stored balance/status. The snapshot covers counts/totals/pages; missing tables fail explicitly. CommercialEvents delegates existing adjustment insertion to the internal participant; Sales keeps its owning transaction, source validation, Audit and changes. WP1 routes are GET-only; collection/correction/statement writers remain later packages. See [contracts](../Phase-5/Phase_5_WP1_Integration_Contracts.md) and [manual verification](../manual-verification-phase-05-wp1.md).


## Phase 5 WP2 — collect and allocate

CustomerDebt owns exact aggregate DebtAmount, pure DebtAllocationPlanner, DebtPaymentInput/Repository/Service/Query and its controller. Existing CommercialAccess owns transaction/authority serialization; Reconciliation owns Cash eligibility; Audit and change publication remain atomic participants. No Inventory/Sale evidence is rewritten. Browser debt-payment-panel/receipt extend the existing Customer task without an outbox. [WP2 contracts](../Phase-5/Phase_5_WP2_Integration_Contracts.md), [operations](../operations/phase-05-debt-collection.md) and [manual guide](../manual-verification-phase-05-wp2.md).

## Phase 5 WP3 ownership

`CustomerDebt/Application/DebtCorrectionInput` closes/normalizes mutation payloads; `DebtCorrectionService` owns reversal/replacement/adjustment locks, review, replay, Audit and last publication. `DebtPaymentPostingParticipant` is shared with normal WP2 collection. `ReceivableAdjustmentParticipant` retains Sales callers and optional explicit operation UUID. `Infrastructure/DebtSettlementQuery` owns original-Sale/channel attributable capacity, reused by Sales and correction dependency checks. `DebtPaymentQuery` extends linked receipts; adjustment detail/history are authorized snapshots in correction service. `Api/DebtCorrectionController` owns ten explicit command/status/history endpoints.

Sales `CommercialEvidence` now reads allocation-aware live balances; `ReturnService` keeps debt-first settlement and exact stock cost; `CommercialReversalService` preserves exact compensation with real dependency checks. `CommercialDiagnostics` includes live allocation/inverse evidence. Frontend `debt-correction-panel.js` owns reviewed forms, contextual history and minimal-ID recovery. [Complete contract/source map](../Phase-5/Phase_5_WP3_Integration_Contracts.md). WP4 statements remain separate.

## Phase 5 WP4 read ownership

CustomerDebt StatementEventQuery derives scoped credit/commercial adjustment/generic adjustment/payment events. StatementInput owns Shop-date ranges; StatementCursor seals encrypted versioned page state using the existing private deployment encryption seed. StatementQuery owns one read-only snapshot, generation/relevant-change binding, exact totals/carry and safe DTOs. DebtController adds only GET customers/{uuid}/statement. DebtPaymentRepository::verifiedAllocations is shared by receipts/statements; payment writers unchanged. customer-statement-panel/document own contextual Customers paging and complete labelled print preparation. [Detailed contracts](../Phase-5/Phase_5_WP4_Integration_Contracts.md). No statement persistence or Audit/change writes.

WP4 also fixes shared `resources/js/api/http-client.js` signal handling: caller abort and existing request deadline both reach fetch; listeners removed afterwards. No financial request is automatically retried.

## Phase 5 closure status

The complete WP1–WP4 module delivery is [Owner-accepted and closed](../acceptance/2026-11-12-phase-05-owner-closure.md) on 12 November 2026, against application candidate `970ad2e39fdc7ae2a1201c985bc8d54d6952152e`. No module code changes accompany closure. Main integration/deployment and Phase6 planning remain separately governed; preserve immutable evidence and exact accepted inventory.

## Phase 6 WP1 — 13 November 2026

FinancialMovement owns Phase 6 financial_movements and the WP1 SHOP_EXPENSE writer. ExpenseInput/MovementAmount normalize exact unsigned magnitude strings; ExpenseService owns CommercialAccess/Audit/change transaction orchestration; FinancialMovementPostingParticipant inserts server-scoped evidence; FinancialMovementSchema gates native structure; repository/query provide scoped history/detail. Query reuses CustomerDebt's generic DebtReadSnapshot repeatable-read primitive; no Debt business writer is invoked. Controller: Api/FinancialMovementController. UI: components/financial-movements-panel.js. Later movement writers and expected-Cash engine are not delivered. See [contracts](../Phase-6/Phase_6_WP1_Integration_Contracts.md).
