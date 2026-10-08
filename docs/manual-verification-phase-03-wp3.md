# Phase 3 WP3 owner manual verification

Execute with two Tenants, Owner and separately granted Staff on MySQL 8.4. For every row record setup IDs, HTTP action, expected response, relevant `stock_positions`/`stock_movements`/`stock_intakes`/`audit_events`/`sync_changes` queries, PASS/FAIL/BLOCKED and screenshot/query evidence.

| # | Setup and action | Expected result / evidence |
|---:|---|---|
| 1 | VALID Product; record DAMAGED with reason | Negative delta, current average retained, evidence/Audit/changes atomic |
| 2 | Record EXPIRED | Same invariant; type/reason/actor correct |
| 3 | Record OWNER_WITHDRAWAL | Stock-only effect; no financial row |
| 4 | Record MISSING_LOSS | Accountable negative evidence |
| 5 | Remove more than Stock left | Negative quantity accepted and prominently displayed |
| 6 | Positive Correction from VALID positive state | Quantity increases; average unchanged; VALID retained |
| 7 | Positive Correction from RETAINED state | Average/state retained; no WAC |
| 8 | Positive Correction from UNESTABLISHED | Average zero/no basis and state UNESTABLISHED, not VALID |
| 9 | Negative Correction | Signed delta, reason, no WAC |
| 10 | Correction/outgoing crosses zero | Existing valid basis becomes RETAINED |
| 11 | Deactivate Product with stock; run each WP3 action | Allowed with inactive indicator; no implicit activation |
| 12 | Reverse a manual movement | Exact negated delta; linked immutable REVERSAL; current snapshots used |
| 13 | Repeat reversal | `MOVEMENT_ALREADY_REVERSED`; no duplicate effect/evidence |
| 14 | Two sessions reverse same movement | One commits; unique guard/conflict prevents second |
| 15 | Reverse latest posted Intake immediately | Same-kind zero-item B; one R/original movement; exact prior position |
| 16 | Opening 10@10, Receipt 10@20, reverse latest Receipt | Position returns 10@10, not 10@15 |
| 17 | Latest Intake has duplicate Product lines | Reverse descending application order; exact prior state |
| 18 | Latest Intake crossed negative to positive | Reverse restores exact retained/unestablished prior state |
| 19 | Latest multi-Product Intake | Ascending Product locks; whole reversal all-or-nothing |
| 20 | Try old Intake after later Receipt | `INTAKE_REVERSAL_NOT_CURRENT`; no rows change |
| 21 | Try Intake after later outgoing | Same safe rejection |
| 22 | Try Intake after Correction | Same safe rejection |
| 23 | Inspect error/UI for unsafe direct undo | Explains later activity and Correction/replacement alternative |
| 24 | Use Correction fallback | Transparent new evidence; original unchanged |
| 25 | After eligible reversal create replacement Receipt Draft, edit/post | C links A, Draft unpublished, normal WP2 WAC/post evidence |
| 26 | Hash/compare original headers/items/movements before/after | Original business evidence unchanged |
| 27 | Substitute foreign Product/Movement/Intake UUID | Non-enumerating denial and no cross-scope state |
| 28 | Forge source kind/FKs/before/after/cost/type/sign | Inputs ignored or rejected; server derives evidence |
| 29 | Owner and Staff view/adjust/reverse/intake permutations | Exact grants independent; unknown fails closed |
| 30 | FULL/READ_ONLY/BLOCKED/CLOSED | Only FULL mutates; READ_ONLY history; blocked/closed none |
| 31 | Inspect each action's Audit | Correct action/resource/reason/safe metadata/correlation |
| 32 | Inspect each action's change siblings | Correct Movement/position/Intake UPSERTs; no original DEACTIVATE |
| 33 | Force failure at movement/position/header/Audit/change/commit | Entire logical operation rolls back |
| 34 | Race Intake reversal with new Receipt; reverse Product client order | Deterministic locks; only a valid serialized result commits |
| 35 | Complete every action/history flow at 320px | Full capability; cards/forms/dialogs usable |
| 36 | Repeat at 360px and 390px | Full parity |
| 37 | Tablet | Full parity |
| 38 | Desktop | Full parity and efficient layout |
| 39 | Keyboard-only and 200% zoom | Focus/order/dialog/action remain accessible |
| 40 | Trigger 422/403/409/network/timeout/500 | Product/quantity/reason retained; truthful state; no queued claim |
| 41 | Inspect routes/navigation/UI | No Sale/Return/Reconciliation/Supplier/offline/WP4 placeholder |
