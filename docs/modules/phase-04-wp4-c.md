# WP4-C — protected commercial compensation and replacement

Date: 2026-11-06. Implemented for native verification on WP4-B PR #24, commit `e05b4bcaad29272796ae5bfdbbf69ed9635ca332`. Binding policy: [owner-approved WP4 contract](../Phase-4/Phase_4_WP4_Approved_Return_and_Compensation_Contract.md). This is the C backend/API increment. Operator correction/Return screens, integrated history/printing/accessibility and complete commercial diagnostics remain WP4-D. Neither C delivery nor the earlier owner confirmation establishes native acceptance or Phase 4 closure.

## Authenticated API

All paths below `/api/v1/shop`; existing session/CSRF/error/private-no-store behavior and host-derived Tenant/Shop authority apply. UUIDs are permanent public v4 identities. There is no caller-supplied kind, stock delta, refund amount, credit adjustment or replacement flag.

| Method | Path | Input/result |
|---|---|---|
| POST | /sales/{source}/reversal-preview | Empty object; eligibility, exact money/debt direction and totals, stock line count, review_fingerprint |
| POST | /sales/{source}/reverse | public_id (compensation UUID), reason, review_fingerprint; atomic immutable compensation or same-UUID committed result |
| GET | /sales/{source}/correction-state | Ordinary posted source identity/kind, root original, reversal UUID, linked replacement UUID/kind/status; recovery after uncertain response |
| GET | /commercial-compensations/{uuid} | Distinct compensation evidence: root/source, immediate source lines, inverse stock/payment/adjustment public links, signed cost-permitted margin |
| POST | /sales/{reversed-source}/replacement | public_id (draft UUID), reason; one linked replacement record, kind/status and ordinary draft/receipt representation |

Mutation/preview requires sale.view + sale.reverse, FULL and current active scoped membership. Read-back requires sale.view; historical costs/COGS/margin remain separately sale.profit.view. sale.return does not grant reversal; sale.reverse does not grant ordinary Sale/Return posting. Replacement Sale edit/review/post follows sale.record and price-override rules. Replacement Return edit/preview/post follows sale.return. Read-only modes retain only permitted safe reads. No offline queue or generic replay transport exists.

The comparison fingerprint is not an authentication token. Preview creates no business evidence. Post repeats all proofs under locks and rejects changed review/eligibility. Lost responses are resolved by source correction-state and the known compensation UUID. Same source/UUID retries return existing compensation without repeating effects or applying today's Cash guard; a different UUID cannot create a second direct reversal. Never create a new UUID merely to guess the first outcome.

## Compensation effects and lineage

Only ordinary POSTED Sales/Returns can be directly reversed once. A compensation cannot itself be reversed. It is a new POSTED header of the opposite kind, reversal_of_sale_id → immediate ordinary source, original_sale_id → root original forward Sale. Both compensation kinds have NULL new-Sale received/credit-created snapshots. Original headers/items/payments/adjustments remain unchanged. Source header/line totals, Product/cost/quantity evidence and root/Customer attribution must agree or correction is rejected.

| Source | Compensation kind | Money | Existing Receivable |
|---|---|---|---|
| Original Sale | RETURN | Exact positive CASH/TRANSFER REFUND reversing each original RECEIPT with reversal_of_payment_id | Negative CORRECTION_DECREASE cancels the original available credit; no new Receivable |
| Normal Return | SALE | Exact positive CASH/TRANSFER RECEIPT reversing each original REFUND with reversal_of_payment_id | Positive REVERSAL linked to each RETURN_REDUCTION restores the SAME original Receivable |

Example: original 20,000, cash 5,000 + transfer 5,000 + credit 10,000. A Return 12,000 reduces debt 10,000 and refunds cash 2,000. Its compensation receives exactly cash 2,000 back, restores 10,000 on the original Receivable and removes the returned stock. Compensation total 12,000 describes those inverse effects; it does not represent a new Sale/new debt. Zero-value channels/adjustments create no fake rows. Future collection allocations block correction until the collection module balance contract is integrated.

Compensation item.original_sale_item_id links the immediately compensated line. It has no ordinary SALE_ITEM movement of its own. Physical proof follows immediate source line → its original SALE_ITEM movement → inverse REVERSAL movement through reversal_of_movement_id. That inverse has sale_item_id NULL. Explicit compensation GET and Inventory diagnostics follow this graph; ordinary Sale endpoints exclude compensation headers to avoid presenting them as newly received money/credit.

## Dependency and Inventory ownership

An original Sale with effective Returns is blocked, with a linked Return identity. For every affected Product, source movements must be the latest effective suffix. Only exact later reversed pairs restoring quantity, average and reconstructed cost state can be ignored. Later unreversed activity blocks with up to 20 scoped movement public references. Pointer/quantity/average/cost-state drift also blocks; quantity equality alone never permits reversal. No generic stock button bypasses commercial settlement.

Inventory appends inverse movements in descending source movement order, restoring each original before quantity/average/state. Versions increment, pointers advance to the new evidence. Original costs/COGS are copied, not repriced as an Intake. Unknown/retained/negative-stock provenance follows B's shared historical reconstruction. Multi-Product eligibility and all effects are all-or-nothing.

Order: CommercialAccess owning transaction → Tenant/Shop/current membership/authority → Cash guard for actual cash effect → root original Sale → immediate source → sorted Product/position locks → current stock-history proof → original Receivable/adjustments → inverse evidence → aggregate Audit → active change publication last → commit. Preview does not take the Cash guard; confirmation validates today's Cash baseline/scope before Sale/Product locks. Commit/write/Audit/publication failure rolls back the operation; no participant commits independently.

## Replacement

A reversed ordinary source may prepare one direct linked DRAFT. Same UUID resolves that existing draft/posted record; a different UUID is rejected while the link exists. Compensation is not a replacement source. Preparation has a required reason and Audit but no stock/payment/debt/change-publication effects.

A Sale replacement uses current Product prices and normal draft placeholders; separate ordinary review/post rechecks active Product/Customer, price permissions, Cash and composition. The source must remain a reversed ordinary Sale. A Return replacement remains rooted at the original Sale with original line identities; preparation recomputes current allocation/eligibility, and separate normal B posting rechecks again. If the root Sale was compensated it is denied. Drafts reserve nothing. Posting replacement refreshes its source identity as well as normal aggregate effects.

## Audit, diagnostics and limitations

New aggregate actions: sale.reversed and sale.replacement_prepared, SALE/sale, required reason and scoped source/root identities. Compensation publishes new/source/root Sale UUIDs, inverse Stock Movements, logical Product Stock Positions, original Receivable and Customer context after Audit in the same transaction. Receipts expose only safe public relationships. Cost masking applies equally to compensations.

Inventory diagnostics now separate normal item coverage from indirect compensation coverage. sales:diagnose continues its existing normal Sale/Return checks and explicitly states its current boundary; complete compensation header/payment/adjustment diagnostics remain D. Diagnostic samples are read-only and not certification.

No C schema migration or frontend/dependency change is added. Apply A's ordered 2026-11-05-100000 migration before native C testing; never disable CHECKs/FKs or delete evidence to bypass its used-compensation downgrade refusal. Native MySQL/constraints/concurrency/HTTP and owner manual acceptance remain pending. See [delivery](../Phase-4/Phase_4_WP4_C_Delivery_Report.md) and [manual guide](../manual-verification-phase-04-wp4-c.md).
