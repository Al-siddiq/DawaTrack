# Phase 8 plan acceptance and WP1 commencement

**Recorded:** 25 November 2026 (Africa/Lagos).  
**Decision:** ACCEPTED — Phase 8 implementation plan.  
**Instruction:** Commence Phase 8 WP1.

The Owner confirmed:

> Plan reviewed and accepted. proceed with implementation as planned starting with phase 8 WP1.

The Owner additionally directed that Shop dashboard/report screens be understandable to a person with primary-school education, with no heavy financial vocabulary. This is a mandatory Shop UI acceptance requirement and is reflected in the Phase 8 plain-English policy and WP1 screen labels/explanations.

## Authorized work

- The approved three-package [Phase 8 implementation plan](../Phase-8/Phase_8_Implementation_Plan.md) is accepted.
- WP1 — Sales, Payment Mix and Gross Profit Reports is authorized to proceed.
- WP1 remains subject to its own native verification, manual P8-01–P8-12 checks and explicit Owner acceptance before WP2 begins.
- WP2 and WP3 are not authorized to start in parallel or before WP1 acceptance.

## Implementation boundaries

- Follow the existing Business/Product, Technical and Database Schema foundation documents and the approved Phase 8 roadmap.
- Preserve backend/frontend/module ownership, closed permission registry, Tenant/Shop scope, Owner/Staff roles, existing UI conventions and delivery-report patterns.
- Use the existing `report.view`, `sale.view` and `sale.profit.view` grants independently; do not add a new role or broaden Staff grants.
- Use plain everyday language on Shop screens while preserving exact backend concepts and calculations.
- Do not add a reporting warehouse, duplicate transactional truth, new schema/migration/index, dependency, export or offline report persistence unless the approved plan's explicit conditions are met and separately authorized.
- No production deployment, live migration or production data change is authorized by this implementation instruction.

## WP1 delivery status

The existing WP1 candidate branch is `codex/phase-08-wp1-sales-reports`; its draft [PR #55](https://github.com/Al-siddiq/DawaTrack/pull/55) contains the implementation, integration contract, source-level guard and manual verification guide. Native PHP/CodeIgniter/MySQL/browser verification and Owner package acceptance remain outstanding. The branch must not be treated as merged or production-ready until those gates are complete.
