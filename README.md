# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for Nigerian chemist/PPMV operations, with tenant isolation, staff permissions, accountable stock and commercial evidence, and a responsive white-label Shop/Admin/Auth interface.

## Current position — 24 November 2026

**Phase 7 is CLOSED by explicit Owner whole-phase acceptance.** The Owner confirms that Phase 7 was reviewed, tested and verified. The accepted programme includes WP1–WP4; WP4 PR #53 is merged to main at `98cb8d4ee99c8668a18aa92993b7828dc982902f`. See the [dated Owner acceptance](docs/acceptance/2026-11-24-phase-07-owner-closure.md), [Phase 7 closure report](docs/Phase-7/Phase_7_Closure_Report.md), [closure checklist](docs/Phase-7/Phase_7_Closure_Checklist.md) and [final operations handover](docs/operations/phase-07-final-handover.md). Production deployment remains a separate operational decision.

**Phase 8 — Authoritative Reporting and Operational Dashboard:** the [three-package implementation plan](docs/Phase-8/Phase_8_Implementation_Plan.md) was accepted by the Owner on 24 November 2026. **WP1 — Sales, Payment Mix and Gross Profit Reports is underway.** [WP1 integration contract](docs/Phase-8/Phase_8_WP1_Integration_Contracts.md) · [WP1 delivery report](docs/Phase-8/Phase_8_WP1_Delivery_Report.md) · [Native manual guide](docs/manual-verification-phase-08-wp1.md). Native database/browser verification and WP1 Owner acceptance remain pending; WP2 has not started.

Historical Phase 5 and Phase 6 package/acceptance records remain linked in their respective sections below. Historical delivery reports and manual registers are not rewritten by the Phase 7 closure record.

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

## Phase 7 — Reconciliation Control Engine (closed)

Phase 7 was reviewed, tested, verified and accepted by the Owner on 24 November 2026. [Owner acceptance](docs/acceptance/2026-11-24-phase-07-owner-closure.md) · [Closure report](docs/Phase-7/Phase_7_Closure_Report.md) · [WP4 PR #53](https://github.com/Al-siddiq/DawaTrack/pull/53) · [Final operations handover](docs/operations/phase-07-final-handover.md). Historical requirement/case registers remain intact. Production deployment is separate.

## Phase 8 — Authoritative Reporting and Operational Dashboard

The Owner accepted the three-package [implementation plan](docs/Phase-8/Phase_8_Implementation_Plan.md) on 24 November 2026. WP1 Sales reports are underway. [Integration contract](docs/Phase-8/Phase_8_WP1_Integration_Contracts.md) · [Delivery report](docs/Phase-8/Phase_8_WP1_Delivery_Report.md) · [Manual guide](docs/manual-verification-phase-08-wp1.md) · [Manual result register](docs/Phase-8/Phase_8_WP1_Manual_Results.csv). Native verification and Owner acceptance remain pending. WP2/WP3 have not started.

