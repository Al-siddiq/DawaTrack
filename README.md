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

Frontend code is authored in `resources/`. `npm run build` compiles Bootstrap/Sass to static CSS and copies pinned Vue/Dexie plus application ES modules into `public/assets`; it does not bundle JavaScript. Asset generation uses Node filesystem APIs and works without a Unix shell on Windows Command Prompt. Bootstrap/Sass deprecation warnings are non-fatal; a successful build ends with `Frontend assets built.`

## Phase 1 operational scope

Phase 1 implementation includes secure Platform Admin authentication/MFA, atomic Tenant onboarding, Owner/Staff identity and permissions, Shop settings, white-label assets and domains, immutable Trial/Starter/Growth entitlement operations, Tenant/Shop lifecycle controls, effective FULL/READ_ONLY/BLOCKED/CLOSED access, and atomic append-only Audit evidence. Platform subscription and lifecycle procedures are documented in `docs/operations/phase-01-subscriptions-and-lifecycle.md`; consolidated manual acceptance is in `docs/manual-verification-phase-01.md`.

## Current implementation and acceptance

Phase 2 provides typed Audit, Owner/Platform Audit queries, reasoned support access, and internal commit-ordered change publication/watermarks. Phase 3 provides Products, protected Stock Positions, Opening/Receipt Intakes, immutable Movements, costing, corrections, reversals/replacements and read-only diagnostics.

The owner reports thorough manual verification of the current system against the blueprint and implementation roadmap. This is recorded owner acceptance, not a replacement for historical test evidence or a declaration that the separate engineering findings have been fixed. Future phases use owner manual acceptance supported by engineering checks.

Phase 4 WP1 Opening Cash is owner-accepted. WP2 Customers, commercial schema and online drafts are owner-accepted. WP3 forward Sale posting/payments/credit, Inventory effects and receipts/history are owner-accepted. WP4 Returns API is authored for native verification; correction/UI increments remain pending. Debt collection, Financial Movements, full Reconciliation, reporting, registered devices and offline/sync transport remain unimplemented. The existing service worker does not provide offline business operation.


## Phase 4 delivery planning

The [Phase 4 comprehensive four-work-package plan](docs/Phase-4/Phase_4_Comprehensive_Analysis_and_Four_Work_Package_Plan.md) proposes sequential delivery of Opening Cash, Customers/Sale drafts, Sale posting/payments/credit, and Returns/corrections with integrated closure. It includes requirement coverage and owner manual acceptance gates. The owner approved this execution plan on 2026-11-02. WP1 now implements Opening Cash and integration contracts; see [WP1 delivery](docs/Phase-4/Phase_4_Work_Package_1_Delivery_Report.md) and [manual verification](docs/manual-verification-phase-04-wp1.md). The owner reported WP1's 15 checklist items working and authorized WP2 on 2026-11-03; see [acceptance](docs/acceptance/2026-11-03-phase-04-wp1-owner-acceptance.md). [WP2 delivery](docs/Phase-4/Phase_4_Work_Package_2_Delivery_Report.md), [API/module contract](docs/modules/phase-04-wp2.md) and [manual verification](docs/manual-verification-phase-04-wp2.md) are available. The owner accepted WP2 and authorized WP3 on 2026-11-03; see [WP2 acceptance](docs/acceptance/2026-11-03-phase-04-wp2-owner-acceptance.md). [WP3 delivery](docs/Phase-4/Phase_4_Work_Package_3_Delivery_Report.md), [API/module contract](docs/modules/phase-04-wp3.md) and [manual verification](docs/manual-verification-phase-04-wp3.md) are available. The owner accepted WP3 on 2026-11-04; see [acceptance](docs/acceptance/2026-11-04-phase-04-wp3-owner-acceptance.md). WP4 has started with [readiness/decision](docs/Phase-4/Phase_4_WP4_Implementation_Readiness_and_Decision.md), a [concrete proposed Return/compensation contract](docs/Phase-4/Phase_4_WP4_Proposed_Return_and_Compensation_Contract.md), and operator-only read-only commercial diagnostics. The owner approved the contract on 2026-11-05; WP4-A schema and WP4-B Return API increments are authored. Protected reversal/replacement API is now authored in WP4-C; integrated operator UI is now implemented in WP4-D; Phase 4 closure is pending. Drafts remain effect-free until explicit posting.


## WP4-B — 2026-11-05

WP4-B Return drafts, previews and posting API is implemented for native verification on the WP4-A branch foundation. See [B delivery](docs/Phase-4/Phase_4_WP4_B_Delivery_Report.md) and [manual/native verification](docs/manual-verification-phase-04-wp4-b.md). WP4-C reversal/replacement API is authored; WP4-D operator UI is implemented; integrated verification/closure remains pending; no Phase 4 acceptance is inferred.


## WP4-C — 2026-11-06

WP4-C protected reversal/replacement API is implemented for native verification on the B branch foundation. [C delivery](docs/Phase-4/Phase_4_WP4_C_Delivery_Report.md) and [manual guide](docs/manual-verification-phase-04-wp4-c.md) describe exact inverse money/stock and same-Receivable restoration. WP4-D integrated implementation is now delivered as a verification candidate; final native/visual owner acceptance remains pending.


## WP4-D — 2026-11-06

WP4-D integrates the Return/correction operator workflow, unified history/receipts, Sale usability fixes and complete commercial diagnostic categories. [D delivery](docs/Phase-4/Phase_4_WP4_D_Delivery_Report.md), [manual verification with P4-01–P4-33 register](docs/manual-verification-phase-04-wp4-d.md) and [closure status](docs/Phase-4/Phase_4_Integrated_Closure_Status.md) are available. Implementation is a closure candidate; native/visual owner verification remains pending and Phase 4 is NOT CLOSED.

## UI refactor developer reference

The approved UI programme and UI2-A foundation are documented in [docs/ui/README.md](docs/ui/README.md). UI2-A supplies local icon assets, opt-in visual tokens and enhanced design examples; it does not replace current task screens. Follow the reference's manual verification steps before recording package acceptance.

UI2-B adds shared interaction components and selected commercial/Customer/Staff integrations. See [component contracts](docs/ui/components.md) and [UI2-B manual checks](docs/ui/manual-verification-ui2-b.md). Full shell integration remains UI2-C.
