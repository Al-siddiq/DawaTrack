# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 12 November 2026

**Phase 5 — Debt Collection and Customer Statements is Owner-accepted and closed.** The Owner explicitly accepted the whole phase, including WP4. [Dated acceptance](docs/acceptance/2026-11-12-phase-05-owner-closure.md) · [Closure report](docs/Phase-5/Phase_5_Closure_Report.md) · [Requirement/source matrix](docs/Phase-5/Phase_5_Requirements_Matrix.csv) · [Integrated evidence](docs/Phase-5/Phase_5_Integrated_Manual_Results.csv).

Accepted application candidate: `970ad2e39fdc7ae2a1201c985bc8d54d6952152e` (PR #42), including WP1–WP4. This adds derived balances, Cash/Transfer repayment/allocation, append-only corrections, repayment-aware Returns and scoped versioned statements/Print to the accepted Phases1–4 foundation and UI programme. Detailed per-case observations/runtime configuration were not separately supplied; acceptance does not manufacture independent native test execution.

PRs #39–#42 were still open/unmerged and main at `b41425f905100a728a4c6167d5df936702a8502a` when closure was checked. Integration and production deployment are separate decisions, not performed by this documentation closure. The earlier UI programme acceptance/rollout remains recorded in its [own closure ledger](docs/ui/programme-closure.md).

The next roadmap boundary is **Phase 6 — Financial Movements and Cash Accountability**, with a separately approved package plan before implementation. **Phase 6 — Financial Movements and Cash Accountability** has a proposed four-package implementation plan, requirements matrix and manual acceptance plan; it is awaiting explicit Owner approval. No Phase 6 implementation is authorized by the planning proposal. Full Reconciliation, reporting, registered devices and full offline/sync transport remain later-phase work; the service worker does not provide offline business operation. [Phase 6 implementation plan](docs/Phase-6/Phase_6_Implementation_Plan.md) · [Manual acceptance plan](docs/Phase-6/Phase_6_Manual_Acceptance_Plan.md) · [Requirements matrix](docs/Phase-6/Phase_6_Requirements_Matrix.csv). [Operational release/key/rollback handover](docs/operations/phase-05-statements-release.md) and [release decision register](docs/Phase-5/Phase_5_Release_Decision.csv) remain current.

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
