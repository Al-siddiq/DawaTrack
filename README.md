# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for secure Nigerian chemist/PPMV operations. The current delivery includes the Phase 1 identity/tenancy/SaaS foundation, Phase 2 Audit/change-sequence foundation, Phase 3 Product Catalog and Inventory ledger, and Phase 4 Opening Cash, Customers, Sales, Payments, Credit and Returns. Owner manual acceptance was recorded on 2026-11-02; see the [foundation acceptance record](docs/acceptance/2026-11-02-foundation-owner-acceptance.md) for scope, provenance, open engineering findings and the next-phase boundary.

## Start locally

```bash
cp env .env
composer install
npm ci
npm run build
php spark serve
```

Open `/`, `/auth`, `/platform-admin`, `/api/v1/health/live`, and `/api/v1/health/ready`. See [configuration](docs/configuration.md), [API conventions](docs/api/conventions.md), [module ownership](docs/modules/module-map.md), and [security foundation](docs/security/foundation.md).

Frontend code is authored in `resources/`. `npm run build` compiles Bootstrap/Sass to static CSS and copies pinned Vue/Dexie plus application ES modules into `public/assets`; it does not bundle JavaScript. Asset generation uses Node filesystem APIs and works without a Unix shell on Windows Command Prompt. Bootstrap/Sass deprecation warnings are non-fatal; a successful build ends with `Frontend assets built.`

## Phase 1 operational scope

Phase 1 implementation includes secure Platform Admin authentication/MFA, atomic Tenant onboarding, Owner/Staff identity and permissions, Shop settings, white-label assets and domains, immutable Trial/Starter/Growth entitlement operations, Tenant/Shop lifecycle controls, effective FULL/READ_ONLY/BLOCKED/CLOSED access, and atomic append-only Audit evidence. Platform subscription and lifecycle procedures are documented in `docs/operations/phase-01-subscriptions-and-lifecycle.md`; consolidated manual acceptance is in `docs/manual-verification-phase-01.md`.

## Current implementation and acceptance

Phase 2 provides typed Audit, Owner/Platform Audit queries, reasoned support access, and internal commit-ordered change publication/watermarks. Phase 3 provides Products, protected Stock Positions, Opening/Receipt Intakes, immutable Movements, costing, corrections, reversals/replacements and read-only diagnostics.

**Phase 4 is owner manually tested, accepted and closed as of 2026-11-07.** It includes Opening Cash, Customers/drafts, Sale posting/payments/credit, Returns/refunds, protected Sale/Return compensation and linked replacement, integrated UI/receipts/history, permissions, Audit/change integration and diagnostics. The owner merged the implementation PR stack into `main`; see the [integrated acceptance record](docs/acceptance/2026-11-07-phase-04-integrated-owner-acceptance.md) and [closure status](docs/Phase-4/Phase_4_Integrated_Closure_Status.md).

Manual acceptance is owner-reported. Historical engineering results retain their stated limits; individual native case logs/environment details were not supplied. The verified merged main baseline is `09789e8aab48b5d62b546ecf538e491867b5fedf`. No production deployment is inferred.

## Phase 4 references and next-phase boundary

The [approved four-work-package plan](docs/Phase-4/Phase_4_Comprehensive_Analysis_and_Four_Work_Package_Plan.md), [approved Return/compensation contract](docs/Phase-4/Phase_4_WP4_Approved_Return_and_Compensation_Contract.md), [WP4-D delivery report](docs/Phase-4/Phase_4_WP4_D_Delivery_Report.md) and [integrated manual/regression checklist](docs/manual-verification-phase-04-wp4-d.md) retain scope and evidence. Earlier delivery reports describe their publication-time status; final acceptance is recorded separately.

Phase 5 Debt Collection and Customer Statements is next in the roadmap. Debt collection, non-sale Financial Movements, full Reconciliation, reporting and registered-device offline/sync transport remain later-phase work. The existing service worker does not provide offline business operation. Future phases continue to use owner manual acceptance supported by engineering checks.
