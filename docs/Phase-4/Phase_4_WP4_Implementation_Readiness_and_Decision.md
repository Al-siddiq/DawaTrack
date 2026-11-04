# WP4 implementation readiness and decision — 2026-11-04

Status: **WP4 started; dependent Return/compensation implementation awaiting an explicit financial/schema decision.** This is not a completed WP4 delivery or Phase 4 closure report.

Baseline: accepted latest delivered WP3/Windows fix `404bd3bb410b6dd80d4e0bd6ad30a4deea44722b`, branch `codex/wp3-windows-build-fix-20261103`. WP3 acceptance is recorded in `docs/acceptance/2026-11-04-phase-04-wp3-owner-acceptance.md`. Work branch: `codex/phase-4-wp4-contract-review-20261104`.

The approved Phase 4 plan §10 requires a precise owner decision before a blueprint/schema amendment is implemented. WP1's pending compensation entry explicitly says: “Before WP4, obtain an explicit approved interpretation/amendment for reversal-linked headers, payment directions and REVERSAL adjustments.” General authorization to start WP4 does not define those previously unresolved financial meanings.

A concrete proposal is ready in `Phase_4_WP4_Proposed_Return_and_Compensation_Contract.md`. It supplies numeric debt-first refund examples, historical cost/unknown provenance, the exact compensation graph and proposed CHECK text, eligibility/dependency constraints, replacement, permission and implementation/acceptance boundaries. No new business rule is silently applied. The review SQL is not an executable migration and must not be applied before the decision.

## Independent implemented work

Added operator CLI `sales:diagnose <tenant-public-id> <shop-public-id>` and CommercialDiagnostics. It resolves one explicit scope, executes SELECT/CTE evidence queries within its own REPEATABLE READ, READ ONLY transaction, samples at most 100 anomalies per category and performs no repair/Audit/change publication. It checks normal forward line totals/arithmetic, original payment/credit identity, payment/Receivable scope, negative adjusted obligations, normal Return settlement/source and effective quantity/value/COGS caps. It does not certify pending compensation-header rules. It is an operator command, not a Shop HTTP permission bypass.

Use accepted existing UUIDs with `php spark sales:diagnose TENANT_UUID SHOP_UUID`; use `php spark help sales:diagnose` for syntax. Run alongside `inventory:diagnose` for position/movement checks. Native MySQL execution remains development-checkout verification. Phase 5 collection/allocation tables are not consulted; adjusted-obligation output is not a future collected-balance claim.

No schema migration, refund/Return/reversal write endpoint, new financial row or additional business table is introduced by this readiness change. The full approved WP4 scope remains required after the decision. Known Sale-screen usability improvements remain integrated closure work rather than being counted as completed by WP3 acceptance.

## Validation and manual verification

Engineering checks: PHP syntax checking and source review for explicit scope, bounded read queries and owned read-only transaction; git diff whitespace check. Native CLI/MySQL behavior and deliberate corruption detection are not reported PASS.

On a disposable development clone: verify valid UUID/Shop resolution; reject malformed/wrong-scope UUIDs; compare counts for intact posted Sales against expected zero samples; introduce one clearly identified line-total/payment/credit/source inconsistency using operator-controlled fixtures and verify the relevant category; confirm transaction read-only behavior and no business/Audit/change row alteration; roll back fixture transactions/rebuild the clone afterward. Do not edit production evidence or treat sampled zero findings as full phase acceptance.

Next step: owner approves or adjusts the concrete contract. Then implement the complete dependent WP4 migration/backend/UI/evidence/diagnostics/manual package; run appropriate engineering checks and request integrated manual acceptance only after delivery.
