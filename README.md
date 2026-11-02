# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for secure Nigerian chemist/PPMV operations. The current delivery includes the Phase 1 identity/tenancy/SaaS foundation, Phase 2 Audit/change-sequence foundation, and Phase 3 Product Catalog and Inventory ledger. Owner manual acceptance was recorded on 2026-11-02; see the [foundation acceptance record](docs/acceptance/2026-11-02-foundation-owner-acceptance.md) for scope, provenance, open engineering findings and the next-phase boundary.

## Start locally

```bash
cp env .env
composer install
npm ci
npm run build
php spark serve
```

Open `/`, `/auth`, `/platform-admin`, `/api/v1/health/live`, and `/api/v1/health/ready`. See [configuration](docs/configuration.md), [API conventions](docs/api/conventions.md), [module ownership](docs/modules/module-map.md), and [security foundation](docs/security/foundation.md).

Frontend code is authored in `resources/`. `npm run build` compiles Bootstrap/Sass to static CSS and copies pinned Vue/Dexie plus application ES modules into `public/assets`; it does not bundle JavaScript.

## Phase 1 operational scope

Phase 1 implementation includes secure Platform Admin authentication/MFA, atomic Tenant onboarding, Owner/Staff identity and permissions, Shop settings, white-label assets and domains, immutable Trial/Starter/Growth entitlement operations, Tenant/Shop lifecycle controls, effective FULL/READ_ONLY/BLOCKED/CLOSED access, and atomic append-only Audit evidence. Platform subscription and lifecycle procedures are documented in `docs/operations/phase-01-subscriptions-and-lifecycle.md`; consolidated manual acceptance is in `docs/manual-verification-phase-01.md`.

## Current implementation and acceptance

Phase 2 provides typed Audit, Owner/Platform Audit queries, reasoned support access, and internal commit-ordered change publication/watermarks. Phase 3 provides Products, protected Stock Positions, Opening/Receipt Intakes, immutable Movements, costing, corrections, reversals/replacements and read-only diagnostics.

The owner reports thorough manual verification of the current system against the blueprint and implementation roadmap. This is recorded owner acceptance, not a replacement for historical test evidence or a declaration that the separate engineering findings have been fixed. Future phases use owner manual acceptance supported by engineering checks.

The next implementation boundary is Phase 4A Opening Cash control before cash-affecting Sales. Sales, Customers, Debt, Financial Movements, full Reconciliation, reporting, registered devices and offline/sync transport remain unimplemented. The existing service worker does not provide offline business operation.


## Phase 4 delivery planning

The [Phase 4 comprehensive four-work-package plan](docs/Phase-4/Phase_4_Comprehensive_Analysis_and_Four_Work_Package_Plan.md) proposes sequential delivery of Opening Cash, Customers/Sale drafts, Sale posting/payments/credit, and Returns/corrections with integrated closure. It includes requirement coverage and owner manual acceptance gates. The owner approved this execution plan on 2026-11-02. WP1 now implements Opening Cash and integration contracts; see [WP1 delivery](docs/Phase-4/Phase_4_Work_Package_1_Delivery_Report.md) and [manual verification](docs/manual-verification-phase-04-wp1.md). Owner WP1 acceptance remains pending; Sales is not implemented.
