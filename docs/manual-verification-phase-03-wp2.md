# Phase 3 WP2 owner manual verification

Execute on MySQL 8.4 with two Tenants and Owner/Staff accounts. Record date, environment, exact action/SQL, PASS/FAIL/BLOCKED and screenshot/query evidence. A blocked item is not a pass.

| # | Procedure | Expected result/evidence |
|---:|---|---|
| 1 | Apply migrations 10–13 after WP1 | All apply in order; migration status current |
| 2 | `SHOW CREATE TABLE` for all WP2 tables/position | Types, checks, indexes, scoped FKs and pointer match artifacts; future FKs absent |
| 3 | On disposable DB rollback 13→10 and reapply | Dependency-safe clean result |
| 4 | Create Opening and Receipt Drafts; edit header/items; remove item; discard | Draft persistence works; discard removes only Draft/items |
| 5 | Inspect Audit/change after each Draft operation | No Draft Audit or `sync_changes` row |
| 6 | Post Opening for unestablished Product A | One item/movement; position established; atomic evidence |
| 7 | Attempt second Opening for A | `OPENING_ALREADY_ESTABLISHED`; Draft/every state unchanged |
| 8 | Post Opening later for untouched Product B | Allowed independently |
| 9 | Post Receipt after A Opening | `STOCK_INTAKE`, positive stock addition |
| 10 | Add same Product twice at different costs | Two items and two movements, never merged |
| 11 | Use fractional quantity/cost whose product rounds at half-up boundary | Stored `(19,2)` line amount matches manual exact calculation |
| 12 | Positive Q with two incoming prices | Six-decimal WAC matches hand calculation |
| 13 | Intake from Q=0 | Incoming cost establishes VALID basis |
| 14 | Controlled negative Q; Intake remains non-positive | Old basis/state retained; negative excluded |
| 15 | Controlled negative Q; line crosses positive | Average resets to crossing line cost |
| 16 | Duplicate Product lines around crossing | Ascending line order deterministically changes state; one movement/line |
| 17 | Multi-product post in browser order opposite IDs | Locks/processes positions by ascending internal Product ID |
| 18 | Repeat Post sequentially | `INTAKE_ALREADY_POSTED`; no duplicate evidence |
| 19 | Two sessions post Receipts for same Product, hold A lock | B blocks; final quantity/WAC serial and correct |
| 20 | Two multi-product Intakes with reverse client order | No lost update/deadlock; bounded retry if applicable |
| 21 | Two Opening posts race for same Product | One wins; one Opening conflict; no partial loser |
| 22 | Race unit edit with first post, then edit unit afterward | Serialized; after movement `PRODUCT_UNIT_IMMUTABLE` |
| 23 | Deactivate Product between Draft and Post | Post rejects `PRODUCT_INACTIVE`; Draft preserved |
| 24 | Substitute foreign Tenant Product/Intake/item UUIDs | Non-enumerating denial; no state/evidence leak |
| 25 | Owner; Staff view only; Staff intake; unrelated grants | Exact permission behavior; Intake not implied |
| 26 | FULL/READ_ONLY/BLOCKED/CLOSED | FULL writes; READ_ONLY reads/no writes; others no normal workflow |
| 27 | Force movement/position/lifecycle/Audit insertion failure separately | Entire post rolls back |
| 28 | Force generation/change insert failure | Entire business/Audit/change transaction rolls back |
| 29 | Sum ordered movement deltas and inspect pointer/version | Equals position; pointer latest; version increments/movement |
| 30 | 320px phone journey | Complete create/edit/duplicate/review/post/detail; no horizontal dependency |
| 31 | 360px phone journey | Same capability and readable sticky action |
| 32 | 390px phone journey | Same capability |
| 33 | Tablet journey | Full parity |
| 34 | Desktop and keyboard-only journey | Full parity, logical focus/order |
| 35 | 200% zoom | Controls/content remain usable; no hidden action |
| 36 | Trigger 422/409/network/timeout/500 during Draft/Post | Draft/form retained; truthful connection message; no queued claim; no WP3/future UI |
