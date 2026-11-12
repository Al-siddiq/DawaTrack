# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 11 November 2026

The Owner has reported manual acceptance and merge of Phase 4, and has now **reviewed, accepted, merged and rolled out the UI programme**. UI4-A PR #37 is verified merged into main as `b41425f905100a728a4c6167d5df936702a8502a`. The [UI final acceptance](docs/ui/acceptance/2026-11-11-ui-programme-owner-closure.md) records the decision and reported rollout without inventing per-case or hosting observations. See the [current closure ledger](docs/ui/programme-closure.md).

The implemented foundation covers identity, tenancy, subscriptions, permissions, Audit/change sequence, Products/Inventory, Opening Cash, Customers, Sales/payments/credit, Returns and linked commercial corrections. Debt collection, Financial Movements, full Reconciliation, reporting, registered devices and full offline/sync transport remain later-phase work. The existing service worker does not provide offline business operation.

**Next: Phase 5 — Debt Collection and Customer Statements.** The [four-package implementation plan](docs/Phase-5/Phase_5_Implementation_Plan.md), [requirements matrix](docs/Phase-5/Phase_5_Requirements_Matrix.csv) and [manual acceptance plan](docs/Phase-5/Phase_5_Manual_Acceptance_Plan.md) are ready for Owner approval. This documentation change does not implement Phase 5 or change the database.

## Start locally

```bash
cp env .env
composer install
npm ci
npm run build
php spark serve
```

Configure a development environment before first use; never overwrite an existing live `.env`. Apply native database setup/migrations according to the [configuration](docs/configuration.md) and [migration register](docs/database/migration-register.md), not by guessing migration batches.

Open `/`, `/auth`, `/platform-admin`, `/api/v1/health/live`, and `/api/v1/health/ready` using the configured host/tenant domains. See [API conventions](docs/api/conventions.md), [module ownership](docs/modules/module-map.md) and [security foundation](docs/security/foundation.md).

Frontend source lives in `resources/`. `npm run build` compiles CSS and copies pinned runtime dependencies and application ES modules into `public/assets`; it does not bundle JavaScript. Node asset scripts work on Windows Command Prompt without a Unix shell. Bootstrap/Sass deprecation warnings are non-fatal; successful asset generation ends with `Frontend assets built.`

## Developer and delivery references

| Reference | Purpose |
|---|---|
| [Strategic phased roadmap](docs/DawaTrack_Strategic_Phased_Implementation_Roadmap.md) | Approved engineering order, full scope and boundaries |
| [Technical Blueprint](docs/DawaTrack_Technical_Blueprint.md) | Business-service, security and architecture rules |
| [Database schema v1.1](docs/DawaTrack_Database_Schema_Design.md) | Tables, relationships and immutable evidence |
| [UI reference](docs/ui/README.md) | Navigation, plain-English wording, components, icons and maintenance |
| [UI developer handover](docs/ui/developer-handover.md) | Where and how to maintain accepted UI behaviour |
| [Release and rollback guide](docs/ui/release-handover.md) | Coherent releases, asset integrity and operational recovery |
| [Phase 4 plan](docs/Phase-4/Phase_4_Comprehensive_Analysis_and_Four_Work_Package_Plan.md) | Historical package scope, contracts and acceptance approach |
| [Phase 4 operations](docs/operations/phase-04-sales.md) | Sale/Return/correction operational contracts |
| [Phase 5 plan](docs/Phase-5/Phase_5_Implementation_Plan.md) | Proposed next four work packages and manual gates |

Historical delivery reports describe their state when authored. Later explicit Owner acceptance supersedes earlier Pending decision wording; it does not manufacture historical per-case evidence. Delivery remains package-by-package, with Owner manual acceptance before progression. Engineering checks support that process rather than replacing it.
