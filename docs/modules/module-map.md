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
