# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for secure Nigerian chemist/PPMV operations. Phase 1 recovery is in progress; the repository includes the runtime, database, Shield authentication, Tenant, Shop, branding, domain and subscription foundations, but Phase 1 is not yet approved as complete.

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

Phase 2 business data, offline/sync transport, Product, Inventory, Sales, Customer, Debt, Financial Movement, Reconciliation, reporting, and devices are not implemented.
