# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 13 November 2026

**Phase 5 is Owner-accepted and closed**, now integrated into main through PR #43 (`f985b5a88516ecd3861497bae0ee90984dfe47a1`). The historical [closure ledger](docs/acceptance/2026-11-12-phase-05-owner-closure.md) preserves what was known when accepted. UI programme closure remains recorded separately.

**Phase 6 WP1 — Financial Movement foundation and Shop expenses is implemented for manual acceptance.** The Owner approved the [four-package plan](docs/Phase-6/Phase_6_Implementation_Plan.md) and authorized WP1 only. This delivery adds the additive schema, independent permissions, online Cash/Transfer expense review/save, scoped history/detail and same-UUID recovery. [Delivery report](docs/Phase-6/Phase_6_WP1_Delivery_Report.md) · [Developer contracts](docs/Phase-6/Phase_6_WP1_Integration_Contracts.md) · [Manual verification P6-01–P6-12](docs/manual-verification-phase-6-wp1.md) · [Release/rollback handover](docs/operations/phase-06-wp1-release.md).

WP1 native PHP/MySQL/HTTP/concurrency/browser acceptance is pending. WP2 Owner money actions, WP3 corrections and WP4 expected-Cash composition remain planned. Full Reconciliation, reporting, registered devices and offline/sync transport remain later phases. No merge or deployment is performed by this delivery.

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
| [Phase 5 plan](docs/Phase-5/Phase_5_Implementation_Plan.md) | Accepted four-package programme and manual gates; Phase 5 closed |
| [Phase 6 plan](docs/Phase-6/Phase_6_Implementation_Plan.md) | Proposed four-package Financial Movements programme; awaiting Owner approval |

Historical delivery reports describe their state when authored. Later explicit Owner acceptance supersedes earlier Pending decision wording; it does not manufacture historical per-case evidence. Delivery remains package-by-package, with Owner manual acceptance before progression. Engineering checks support that process rather than replacing it.

## Phase 5 WP3 — corrections and returns after repayment

WP2 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp2-owner-acceptance.md). WP3 delivers linked payment undo/atomic replacement, reasoned bill corrections, repayment-aware returns and read-only integrity checks. [Delivery](docs/Phase-5/Phase_5_WP3_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP3_Integration_Contracts.md) · [Native manual guide P5-24–P5-36](docs/manual-verification-phase-05-wp3.md) · [Operations](docs/operations/phase-05-debt-corrections.md). No new migration/dependency; WP1 tables required. WP3 and the complete Phase 5 programme are now Owner-accepted; see the [closure report](docs/Phase-5/Phase_5_Closure_Report.md).

## Phase 5 WP4 — Customer statements and integrated handover

WP3 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp3-owner-acceptance.md). WP4 adds scoped versioned Customer statements with Shop-date filters, exact opening/running/closing debt, safe detail links and page/full-range printing. [Delivery](docs/Phase-5/Phase_5_WP4_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP4_Integration_Contracts.md) · [Native P5-37–P5-48](docs/manual-verification-phase-05-wp4.md) · [Closure checklist](docs/Phase-5/Phase_5_Closure_Checklist.md) · [Operations/key setup/rollback](docs/operations/phase-05-statements-release.md). Existing private deployment encryption.key required for page tokens; no new DDL/dependency. WP4 and the whole phase are Owner-accepted and closed; actual merge/deployment remains separate. See the [dated decision](docs/acceptance/2026-11-12-phase-05-owner-closure.md).
