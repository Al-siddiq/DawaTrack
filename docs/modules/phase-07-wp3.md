# Phase 7 WP3 module boundary

Shared reconciliation lifecycle: ControlService/ControlRepository/ControlQuery with stable Cash wrappers, server-bound STOCK/FULL controller, frozen StockCountEvidence and exact Inventory StockCountAmount. Inventory ReconciliationAdjustmentService owns separately reviewed current-position variance deltas. LateOperationAssociation runs in source ChangeSequenceService publication before the generation lock and appends immutable Audit/control refresh evidence.

See [WP3 contract](../Phase-7/Phase_7_WP3_Integration_Contracts.md), [source effect matrix](../Phase-7/Phase_7_WP3_Source_Effect_Matrix.csv), [native manual guide](../manual-verification-phase-07-wp3.md) and [operations](../operations/phase-07-wp3-stock-full.md). No offline transport or WP4 closure is activated.
