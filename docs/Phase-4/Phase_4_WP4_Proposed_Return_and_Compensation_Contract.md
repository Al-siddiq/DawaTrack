# WP4 proposed Return and compensation contract

Date: 2026-11-04. **PROPOSED — owner decision required before dependent implementation.** This is the concrete resolution requested by the approved plan §10 and WP1 integration-contract decision register. It is not a migration, an approved schema change or a delivered Return workflow.

## 1. Why a decision is necessary

The current `sales` CHECK requires every POSTED SALE-shaped record to have received/credit-created snapshots adding to its total. That rule is correct for a new Sale. A reversal of a credit Return restores an **existing** Receivable through a positive REVERSAL adjustment; it creates no new Receivable. Treating restored debt as newly created credit would falsify those snapshots, and pretending restored debt is received money would falsify payments.

Example: original Sale 20,000, cash 5,000 + transfer 5,000 + credit 10,000. A Return worth 4,000 reduces the existing credit to 6,000. Reversing that Return must bring that **same** obligation back to 10,000. No additional debt or invented payment may be created.

Recommendation: distinguish ordinary forward Sale/Return evidence from a linked compensation record using the existing `reversal_of_sale_id`. Preserve normal rules, while reversal-linked records have NULL new-Sale received/credit-created snapshots and describe their actual signed effects through explicit payment/adjustment/stock links.

## 2. Settlement policy — debt reduction first

For each original-linked Return, lock the original commercial aggregate and Receivable. Recompute effective prior Returns/compensations. Outstanding in current Phase 4 is original Receivable amount plus signed adjustments; Phase 5 later adds its effective allocation/collection query contract.

1. Return commercial value comes from original Sale line amounts, never today's Product price.
2. Reduce the legitimate remaining original debt first: `reduction = min(Return value, current original debt outstanding)`.
3. Refund `Return value - reduction` using actual CASH/TRANSFER only.
4. The operator chooses the refund split within remaining original received amounts per channel, after effective prior refunds/compensations. Never refund more than money actually received.
5. Refund plus debt reduction equals Return total. Zero reduction/refund produces no zero-valued adjustment/payment row. No CREDIT payment, advance wallet, write-off, interest or independent repayment is added.
6. Any cash refund or reversal cash receipt uses the existing Opening Cash/active-scope guard. Transfer-only or debt-only effects do not require a Cash baseline.

Examples, each independently starting from the 20,000 Sale above:

| Return value | Debt reduction | Actual refund | Remaining original debt |
|---:|---:|---:|---:|
| 4,000 | 4,000 | 0 | 6,000 |
| 10,000 | 10,000 | 0 | 0 |
| 12,000 | 10,000 | 2,000 | 0 |
| 20,000 | 10,000 | 10,000 | 0 |

The 12,000 Return may refund cash 2,000, transfer 2,000, or an allowed split totaling 2,000. It cannot also refund 12,000 after reducing debt 10,000. Historical inactive Products/Customers remain eligible for Returns/correction against their original evidence; they are not erased or replaced by unrelated identities.

## 3. Value/COGS attribution and Inventory policy

Return Product/unit identity and unit-price/cost attribution come from the original Sale line. Cumulative effective quantities cannot exceed the original line quantity. Allocate original line sales/COGS kobo without exceeding original cents. A final full Return must consume exactly all remaining original line sales/COGS cents.

For a new partial Return, calculate the cumulative proportional target rounded to kobo, subtract the effective amount already returned, and bound the new amount between zero and the remaining original amount. This handles out-of-order compensation without a negative Return line amount. Sales value and COGS allocations are tracked separately. A zero-valued Return of a legitimately zero-priced/rounded line may still restore physical quantity without a fake money entry.

Inventory owns every stock, cost-state, pointer and version effect in the caller's transaction. Normal Return incoming quantity restores the original Sale cost attribution through the existing exact weighted arithmetic:

| Current position and Return | Result |
|---|---|
| Qty 10, average 80; Return qty 2 at original cost 60 | Qty 12, average 76.666667; Return COGS attribution remains 120 |
| Qty -2, retained average 60; known-cost Return qty 1 at 50 | Qty -1, retained average 60 |
| Qty -2, retained average 60; known-cost Return qty 3 at 50 | Qty 1, average 50, established basis |
| Qty 5 at known average 60; Return qty 2 from original unestablished zero basis | Qty 7, numeric estimated average 42.857143; basis/profit must remain unestablished/provisional |

Unknown-basis incoming stock must not become verified merely because it mixes with known stock. Preserve conservative unknown provenance through subsequent positive-stock acquisitions until that uncertain stock has been depleted and a known incoming basis establishes a new positive position. Earlier historical receipts/Sales must retain their accepted cost classification; the new Return-era rule must be reconstructed from immutable Return/movement lineage, without retroactively relabeling pre-Return evidence. Implementation must align Intake participants and all historical-state readers where they encounter the new Return-era evidence; no duplicate costing engine or mutable profit snapshot is introduced.

## 4. Concrete compensation graph

Every direct compensation has a fresh UUID, immutable actor/reason/time/correlation, opposite commercial kind, `reversal_of_sale_id` pointing to the compensated operation, and `original_sale_id` pointing to the root original forward Sale. Its lines refer through `original_sale_item_id` to the **immediately compensated line**, allowing a chain back to the root original line.

| Operation | Compensation header | Actual money rows | Receivable effects |
|---|---|---|---|
| Reverse ordinary Sale S | kind RETURN; reversal_of=S; original_sale=S | Positive REFUND per original Receipt, exact channel/amount, reversal_of_payment_id=original Receipt | Cancel the eligible original credit with a linked negative CORRECTION_DECREASE; no new Receivable |
| Reverse ordinary Return R against S | kind SALE; reversal_of=R; original_sale=S | Positive RECEIPT per original Refund, exact channel/amount, reversal_of_payment_id=original Refund | Positive REVERSAL of each original negative RETURN_REDUCTION, on the same Receivable and linked by reversal_of_adjustment_id |

Both kinds of compensation have **NULL received_amount_snapshot and credit_created_amount_snapshot**. Ordinary forward Sale snapshots remain required and keep their existing meaning. Ordinary Return snapshots remain NULL. Compensation total is a positive commercial magnitude; its sign/direction derives from the compensated operation, payment direction and signed adjustment. A reversal restores/cancels an existing obligation, never originates a second one.

Stock compensation uses `movement_type=REVERSAL`, `source_kind=REVERSAL`, exact `reversal_of_movement_id`, and opposite original quantity. Existing source-shape constraints retain NULL sale_item_id on such reversal movements. Link compensation line → immediately original line → original movement → reversal movement; diagnostics/receipt queries must follow that graph rather than expecting a new SALE_ITEM movement for compensation lines. Ordinary Returns still create CUSTOMER_RETURN/SALE_ITEM movements linked to their Return lines.

Worked 12,000 Return reversal: R reduced original debt by 10,000 and refunded cash 2,000. Compensation C is SALE-shaped but creates **no new credit**: it records a cash RECEIPT 2,000 reversing R's Refund and a +10,000 REVERSAL adjustment reversing R's reduction on the existing Receivable. Stock reverses R's physical return. C's total is 12,000; both new-Sale snapshots are NULL. Original Sale, Return, payment and adjustment rows are unchanged.

## 5. Exact proposed header CHECK amendment

The eventual implementation would add a new forward migration and equivalent SQL, not edit WP2's applied migration. The following is **review-only SQL**, not an apply instruction:

```sql
ALTER TABLE sales
 DROP CHECK ck_sales_composition,
 ADD CONSTRAINT ck_sales_composition CHECK (
   (
     reversal_of_sale_id IS NOT NULL
     AND status='POSTED'
     AND original_sale_id IS NOT NULL
     AND received_amount_snapshot IS NULL
     AND credit_created_amount_snapshot IS NULL
   )
   OR
   (
     reversal_of_sale_id IS NULL
     AND (
       (
         kind='RETURN'
         AND original_sale_id IS NOT NULL
         AND received_amount_snapshot IS NULL
         AND credit_created_amount_snapshot IS NULL
       )
       OR
       (
         kind='SALE'
         AND original_sale_id IS NULL
         AND (
           (status='DRAFT' AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
           OR
           (
             status='POSTED'
             AND received_amount_snapshot IS NOT NULL
             AND credit_created_amount_snapshot IS NOT NULL
             AND received_amount_snapshot>=0
             AND credit_created_amount_snapshot>=0
             AND received_amount_snapshot+credit_created_amount_snapshot=total_amount
             AND (credit_created_amount_snapshot=0 OR customer_id IS NOT NULL)
           )
         )
       )
     )
   )
 );
```

Other existing kind/status/posting/scope/unique-reversal checks remain. Services/diagnostics additionally prove opposite kind, correct root Sale/Customer, exact immediate-line/source relationships, inverse money/adjustment effects and no new compensation Receivable. SQL CHECK alone cannot inspect child tables. Do not expose a generic header-field edit or allow caller-supplied compensation flags/snapshots.

Before apply, inspect existing rows against the new normal-Sale original_sale_id restriction; unexpected rows block migration and require investigation, never automatic rewriting. Reverse migration must refuse restoration of the old CHECK while new SALE-shaped credit-Return compensations exist. Preserve evidence; do not delete it to downgrade.

## 6. Eligibility, dependency handling and replacement

- Only ordinary original Sales/Returns can be directly reversed, once each; compensation records cannot themselves be reversed. A new justified transaction/replacement is the subsequent correction path.
- An original Sale with any effective active Return cannot be reversed while that Return remains effective. Resolve dependent Returns first; never silently refund the full original twice.
- Direct stock compensation requires the original operation to be the latest **effective** contribution for every affected Product. Later unreversed stock activity blocks direct reversal with linked references/reason. An exact compensated later pair can be ignored only after proving it restored its before-state and the current projection still matches the eligible chain. Quantity equality alone is insufficient.
- Eligible reversal restores the original operation's exact before quantity/average/cost state under Inventory ownership, appending inverse movements and incrementing versions; it never rewinds pointers/versions or treats Return reversal as a purchase.
- These dependency restrictions protect immutable costing. They are explicit safe rejection cases, not restrictions to fully paid Sales or cash-only Returns. No alternate button may bypass the full commercial compensation.
- Replacement is a new linked DRAFT with replacement_for_sale_id=the reversed ordinary operation. SALE replacement follows ordinary forward posting/current permissions; RETURN replacement remains rooted at the original forward Sale and revalidates current return eligibility. Only one direct replacement link is allowed. No stock/money/debt effect until its separate posting confirmation.
- New sale.return is independent of sale.record/sale.reverse; reversal/replacement linkage requires sale.reverse, view authority and a reason. Historical cost visibility remains separately sale.profit.view. Writes require FULL and current scoped authority; read-only/blocked/offline behavior and permanent-UUID retrieval follow existing contracts.

## 7. Required implementation and acceptance after decision

Implement the paired forward CHECK migration/preflight/rollback guard, full Return draft/preview/post/UI/receipt/history, exact settlement caps, Inventory incoming/compensation participation and historical provenance, safe Sale/Return reversal/replacement, closed permissions/Audit/change resources, and complete compensation-aware diagnostics. Preserve all already accepted forward variants and add the known Sale-screen layout/terminology fixes during integrated UI closure.

Native verification includes all paid/full/partial-credit Returns, fractional/cumulative/out-of-order rounding, cash scope, inactive historical identities, concurrent Return limits, duplicated/lost responses, cost-state/negative-stock cases, linked inverse effects, safe dependency rejection, rollback injection, permissions and phone/desktop accessibility. Phase 4 stays incomplete until those workflows and owner integrated acceptance are complete.

Owner decision requested: approve this recommended debt-first settlement, historical-cost/unknown-provenance policy, linked compensation interpretation/CHECK amendment and dependency-protected reversal/replacement contract, or identify a business-rule change. Approval authorizes its dependent WP4 implementation; it is not acceptance of unimplemented functionality.
