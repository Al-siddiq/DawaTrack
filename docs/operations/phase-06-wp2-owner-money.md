# Phase 6 WP2 — Owner money operations and release

13 November 2026 (Africa/Lagos). WP2 delivered for manual acceptance only. [Manual guide](../manual-verification-phase-6-wp2.md) · [contracts](../Phase-6/Phase_6_WP2_Integration_Contracts.md).

Owner took money means genuine Owner removal; Add money to shop means genuinely new funds supplied by the Owner. Choose Cash for physical notes/coins; choose Transfer for non-cash funds. Transfer is recorded evidence, not a bank integration or physical Cash. Neither is a sale, debt receipt, stock adjustment, or revenue calculation. Explain why money moved and keep any reference.

Starting cash establishes the actual baseline. Owner-added Cash is a later signed movement and requires the finalized baseline; it cannot create, replace or rewrite Starting cash. Transfer needs no Cash baseline. Finish any active cash count before Cash movements. WP4 will compose expected Cash; WP2 does not expose or claim a computed shop balance or enforce a new overdraft rule.

Use only the active Shop Owner membership. Granting Staff every financial permission does not grant these commands. To recover an interrupted save, check its saved public reference and resume the same action/UUID explicitly if necessary. No new reference to bypass conflict, automatic repost or offline queue. The safe session marker adds an action tag to the UUID; no monetary body/secret is retained. Role/grant/READ_ONLY changes are rechecked at save.

Release the reviewed source and generated assets together. Verify the WP2 404-file artifact inventory and existing migration status; WP2 adds no migration or package dependency. Preserve all accepted financial/Audit/change evidence. Do not remove the financial_movements table, run migrate:fresh, edit protected records or deploy old code that ignores financial Cash evidence. Follow [WP1 safe release/recovery](phase-06-wp1-release.md) for partial schema and data-compatible rollback. No production merge, deployment or migration is performed by this delivery.
