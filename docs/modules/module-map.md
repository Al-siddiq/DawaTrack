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
