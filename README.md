# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 11 November 2026

The Owner has reported manual acceptance and merge of Phase 4, and has now **reviewed, accepted, merged and rolled out the UI programme**. UI4-A PR #37 is verified merged into main as `b41425f905100a728a4c6167d5df936702a8502a`. The [UI final acceptance](docs/ui/acceptance/2026-11-11-ui-programme-owner-closure.md) records the decision and reported rollout without inventing per-case or hosting observations. See the [current closure ledger](docs/ui/programme-closure.md).

The implemented foundation covers identity, tenancy, subscriptions, permissions, Audit/change sequence, Products/Inventory, Opening Cash, Customers, Sales/payments/credit, Returns and linked commercial corrections. WP2 now implements debt collection for manual acceptance. Financial Movements, full Reconciliation, reporting, registered devices and full offline/sync transport remain later-phase work. The existing service worker does not provide offline business operation.

**Current: Phase 5 WP2 — receive and allocate payments, delivered for manual verification after Owner acceptance of WP1.** The [four-package implementation plan](docs/Phase-5/Phase_5_Implementation_Plan.md), [requirements matrix](docs/Phase-5/Phase_5_Requirements_Matrix.csv) and [manual acceptance plan](docs/Phase-5/Phase_5_Manual_Acceptance_Plan.md) are Owner-approved. [WP1 delivery](docs/Phase-5/Phase_5_WP1_Delivery_Report.md), [integration contracts](docs/Phase-5/Phase_5_WP1_Integration_Contracts.md) and [native manual guide](docs/manual-verification-phase-05-wp1.md) describe the implemented read-only balances and two new migrations. [WP1 acceptance](docs/acceptance/2026-11-12-phase-05-wp1-owner-acceptance.md) authorizes WP2. [WP2 delivery](docs/Phase-5/Phase_5_WP2_Delivery_Report.md), [collection contract](docs/Phase-5/Phase_5_WP2_Integration_Contracts.md) and [native manual guide](docs/manual-verification-phase-05-wp2.md) cover Cash/Transfer collection, automatic/explicit allocation, receipts/history and same-ID recovery. WP2 acceptance remains pending; WP3 correction/return integration and WP4 statements have not started.

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

## Phase 5 WP3 — corrections and returns after repayment

WP2 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp2-owner-acceptance.md). WP3 delivers linked payment undo/atomic replacement, reasoned bill corrections, repayment-aware returns and read-only integrity checks. [Delivery](docs/Phase-5/Phase_5_WP3_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP3_Integration_Contracts.md) · [Native manual guide P5-24–P5-36](docs/manual-verification-phase-05-wp3.md) · [Operations](docs/operations/phase-05-debt-corrections.md). No new migration/dependency; WP1 tables required. WP3 native acceptance pending; WP4 statements not started.

## Phase 5 WP4 — Customer statements and integrated handover

WP3 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp3-owner-acceptance.md). WP4 adds scoped versioned Customer statements with Shop-date filters, exact opening/running/closing debt, safe detail links and page/full-range printing. [Delivery](docs/Phase-5/Phase_5_WP4_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP4_Integration_Contracts.md) · [Native P5-37–P5-48](docs/manual-verification-phase-05-wp4.md) · [Closure checklist](docs/Phase-5/Phase_5_Closure_Checklist.md) · [Operations/key setup/rollback](docs/operations/phase-05-statements-release.md). Existing private deployment encryption.key required for page tokens; no new DDL/dependency. Whole-phase acceptance and deployment remain pending, not implied by implementation.
