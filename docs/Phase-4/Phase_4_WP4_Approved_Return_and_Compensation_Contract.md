# WP4 Return and compensation contract — OWNER APPROVED

Decision date: 2026-11-05. The owner explicitly approved the full proposal for implementation. This is the binding WP4 business/schema contract, not evidence that all workflows are delivered or accepted.

Approved rules:
1. Debt-first settlement: reduce only the remaining balance of the original Receivable by min(Return value, current outstanding obligation); refund only the remainder.
2. Refunds and reversal receipts use positive CASH/TRANSFER rows, limited by original Sale received amounts per channel. No CREDIT payment, wallet, write-off or invented receipt.
3. Return price, Product identity and cost attribution derive from the original Sale line. Cumulative effective returned quantity/value/COGS cannot exceed the original line; final full Return consumes exact remaining cents.
4. Unknown/unestablished cost provenance remains provisional through mixing and historical reconstruction; later known receipts do not relabel earlier evidence.
5. Compensation is immutable and linked: opposite commercial kind, reversal_of_sale_id to the immediate compensated operation, original_sale_id to root Sale, immediate line links, and exact inverse money/adjustment/stock evidence. Both compensation snapshots are NULL. Reversing a credit Return restores the same Receivable via a positive REVERSAL adjustment, never a second Receivable.
6. Safe reversal is rejected when dependent effective Returns or later stock contributions make exact inverse state unsafe. Replacements are new linked drafts with no effect until separately posted.
7. Existing forward Sale/Return rules remain intact. No generic posted-row edit/delete or caller-supplied compensation flags.

See Phase_4_WP4_Proposed_Return_and_Compensation_Contract.md for worked examples, formulae, graph, eligibility rules, permissions and acceptance matrix.

## Implementation sequence
- WP4-A: paired forward CHECK migration, preflight and rollback guard.
- WP4-B: Return draft/preview/post with exact debt-first settlement and Inventory-owned incoming movements.
- WP4-C: linked Sale/Return reversal and replacement, including payment/Receivable/stock compensation.
- WP4-D: permissions, Audit/change publication, receipts/history/UI, diagnostics, regression and owner manual verification.

Migration 2026-11-05-100000 AmendPhaseFourSaleCompensationComposition implements WP4-A only. It changes no rows and must be applied through the normal ordered App migration process. Native MySQL 8.4 migration execution remains to be verified on a disposable development clone before production use. WP4 remains incomplete until all stages and owner integrated acceptance are complete.