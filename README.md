# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 14 November 2026

**Phase 5 is Owner-accepted and closed**, now integrated into main through PR #43 (`f985b5a88516ecd3861497bae0ee90984dfe47a1`). The historical [closure ledger](docs/acceptance/2026-11-12-phase-05-owner-closure.md) preserves what was known when accepted. UI programme closure remains recorded separately.

**Phase 6 WP1–WP4 are Owner-accepted. Complete closure and final release handover are prepared; whole-phase decision and production release remain pending.** [Closure report](docs/Phase-6/Phase_6_Closure_Report.md) · [WP4 acceptance](docs/acceptance/2026-11-14-phase-06-wp4-owner-acceptance.md) · [Final release handover](docs/operations/phase-06-final-release.md) · [Closure checklist](docs/Phase-6/Phase_6_Closure_Checklist.md). WP2 adds explicit Owner-only Cash/Transfer review/save, truthful signed effects, shared history/detail and action-preserving same-UUID recovery. [Approved plan](docs/Phase-6/Phase_6_Implementation_Plan.md) · [WP1 acceptance](docs/acceptance/2026-11-13-phase-06-wp1-owner-acceptance.md) · [WP2 acceptance](docs/acceptance/2026-11-14-phase-06-wp2-owner-acceptance.md) · [WP3 acceptance](docs/acceptance/2026-11-14-phase-06-wp3-owner-acceptance.md) · [WP4 PR #48](https://github.com/Al-siddiq/DawaTrack/pull/48) · [WP4 delivery](docs/Phase-6/Phase_6_WP4_Delivery_Report.md) · [WP4 contract](docs/Phase-6/Phase_6_WP4_Integration_Contracts.md) · [WP4 manual guide](docs/manual-verification-phase-6-wp4.md) · [WP4 operations](docs/operations/phase-06-expected-cash.md) · [WP4 release decision](docs/Phase-6/Phase_6_Release_Decision_Template.md) · [WP4 rollback](docs/Phase-6/Phase_6_Rollback_Handover.md) · [Final integration matrix](docs/Phase-6/Phase_6_Final_Integration_Matrix.csv) · [WP4 result register](docs/Phase-6/Phase_6_WP4_Manual_Results.csv) · [Defect disposition](docs/Phase-6/Phase_6_Defect_Disposition.csv) · [WP2 delivery](docs/Phase-6/Phase_6_WP2_Delivery_Report.md) · [Developer contracts](docs/Phase-6/Phase_6_WP2_Integration_Contracts.md) · [Manual guide P6-13–P6-18](docs/manual-verification-phase-6-wp2.md) · [Operations](docs/operations/phase-06-wp2-owner-money.md).

WP1's accepted prerequisite is included because PR #45 remains unmerged at implementation start. No new WP2–WP4 migration or dependency. WP2 is Owner-accepted; individual case results were not separately itemized. WP3 was Owner-accepted on 14 November 2026; individual P6-19–P6-30 observations were not separately itemized. WP4 adds an internal read-only expected-Cash component query, signed Sales/Debt/Financial Movement composition, posted_at boundaries and history type filters. WP4 is Owner-accepted; individual P6-31–P6-40 outcomes were not supplied. Whole-phase decision remains pending. Full Reconciliation, reporting, registered devices and offline transport remain later phases. No merge or deployment is performed by this delivery.

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
| [Phase 6 plan](docs/Phase-6/Phase_6_Implementation_Plan.md) | Approved four-package programme; WP1–WP4 accepted; final phase decision pending |

Historical delivery reports describe their state when authored. Later explicit Owner acceptance supersedes earlier Pending decision wording; it does not manufacture historical per-case evidence. Delivery remains package-by-package, with Owner manual acceptance before progression. Engineering checks support that process rather than replacing it.

## Phase 5 WP3 — corrections and returns after repayment

WP2 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp2-owner-acceptance.md). WP3 delivers linked payment undo/atomic replacement, reasoned bill corrections, repayment-aware returns and read-only integrity checks. [Delivery](docs/Phase-5/Phase_5_WP3_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP3_Integration_Contracts.md) · [Native manual guide P5-24–P5-36](docs/manual-verification-phase-05-wp3.md) · [Operations](docs/operations/phase-05-debt-corrections.md). No new migration/dependency; WP1 tables required. WP3 and the complete Phase 5 programme are now Owner-accepted; see the [closure report](docs/Phase-5/Phase_5_Closure_Report.md).

## Phase 5 WP4 — Customer statements and integrated handover

WP3 is [Owner-accepted](docs/acceptance/2026-11-12-phase-05-wp3-owner-acceptance.md). WP4 adds scoped versioned Customer statements with Shop-date filters, exact opening/running/closing debt, safe detail links and page/full-range printing. [Delivery](docs/Phase-5/Phase_5_WP4_Delivery_Report.md) · [Developer/API contract](docs/Phase-5/Phase_5_WP4_Integration_Contracts.md) · [Native P5-37–P5-48](docs/manual-verification-phase-05-wp4.md) · [Closure checklist](docs/Phase-5/Phase_5_Closure_Checklist.md) · [Operations/key setup/rollback](docs/operations/phase-05-statements-release.md). Existing private deployment encryption.key required for page tokens; no new DDL/dependency. WP4 and the whole phase are Owner-accepted and closed; actual merge/deployment remains separate. See the [dated decision](docs/acceptance/2026-11-12-phase-05-owner-closure.md).


## Phase 6 WP3 — protected reversal and correction

WP3 was explicitly Owner-accepted on 14 November 2026 and WP4 was authorized. [Acceptance record](docs/acceptance/2026-11-14-phase-06-wp3-owner-acceptance.md) · [Delivery](docs/Phase-6/Phase_6_WP3_Delivery_Report.md) · [Developer/API contract](docs/Phase-6/Phase_6_WP3_Integration_Contracts.md) · [Manual guide P6-19–P6-30](docs/manual-verification-phase-6-wp3.md) · [Operations](docs/operations/phase-06-wp3-corrections.md). Individual case outcomes were not separately itemized in the acceptance message and remain Not run in the register. PR #47 remains an integration step.


## Phase 6 WP4 — Expected Cash and final integration

WP4 delivers the internal read-only expected-Cash query, signed Sales/Debt/Financial Movement composition, strict posted_at boundaries and the Financial Movement history type filter. It adds no public expected-Cash route, snapshot writer, migration or automatic variance adjustment. [PR #48](https://github.com/Al-siddiq/DawaTrack/pull/48) · [Delivery](docs/Phase-6/Phase_6_WP4_Delivery_Report.md) · [Contract](docs/Phase-6/Phase_6_WP4_Integration_Contracts.md) · [Manual verification P6-31–P6-40](docs/manual-verification-phase-6-wp4.md) · [Final integration matrix](docs/Phase-6/Phase_6_Final_Integration_Matrix.csv) · [Result register](docs/Phase-6/Phase_6_WP4_Manual_Results.csv) · [Defect disposition](docs/Phase-6/Phase_6_Defect_Disposition.csv) · [Release decision](docs/Phase-6/Phase_6_Release_Decision_Template.md) · [Rollback handover](docs/Phase-6/Phase_6_Rollback_Handover.md). WP4 is Owner-accepted; final whole-phase decision and native release evidence remain pending.

## Phase 7 — proposed strategic implementation plan

Phase 6 implementation and closure are merged; the Owner reports main deployed. Phase 7 planning is ready for review, with no Phase 7 code or schema implemented. [Four-package plan](docs/Phase-7/Phase_7_Implementation_Plan.md) · [32-requirement matrix](docs/Phase-7/Phase_7_Requirements_Matrix.csv) · [48-case native acceptance plan](docs/Phase-7/Phase_7_Manual_Acceptance_Plan.md) · [Initial results](docs/Phase-7/Phase_7_Manual_Results.csv) · [Owner review decision](docs/Phase-7/Phase_7_Review_Decision.md). Plan and WP1 authorization remain pending.
