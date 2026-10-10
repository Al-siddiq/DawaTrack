# Phase 3 owner closure manual verification

Execute on MySQL 8.4 with sanitized fixtures, two Tenants, an Owner, and Staff memberships with individual grants. Record date/environment, PHP/CI4/Shield/MySQL versions, request IDs, public UUIDs, SQL evidence, screenshots, and PASS/FAIL/BLOCKED for every row. A blocked or failed row is not a pass. Do not use production customer data. Diagnostics are read-only and must never repair state.

| # | Area | Procedure | Expected evidence |
|---:|---|---|---|
| 1 | Schema | Apply all migrations; inspect the five Phase 3 tables and constraints | v1.1 types, checks, scoped FKs and late-FK record match docs |
| 2 | Schema | Roll back/reapply on disposable database | Dependency order succeeds without residual objects |
| 3 | Product | Create Product | Product and zero UNESTABLISHED Position, Audit, Product/Position changes share transaction |
| 4 | Product | Create two identical names | Both succeed with distinct UUIDs |
| 5 | Product | Search whitespace/case variants and similar strengths | Conservative normalization finds matches without merging identities |
| 6 | Product | Edit name and selling price | Both change; Product Audit/change emitted |
| 7 | Product | Change base unit before history | Allowed and audited |
| 8 | Product | Post first movement, then change unit | `PRODUCT_UNIT_IMMUTABLE`; name/price remain independently editable |
| 9 | Product | Deactivate with reason | INACTIVE, Product DEACTIVATE change; stock/history preserved |
| 10 | Product | Inspect inactive Product | Product, stock, history and permitted cleanup remain visible |
| 11 | Projection | Inspect new Position | quantity/threshold/average/version zero, UNESTABLISHED, pointer null |
| 12 | Projection | Set threshold equal to quantity, then above/below | Low stock is true at equality and updates correctly without movement |
| 13 | Projection | Drive stock below zero legitimately | Negative quantity persists and is visibly exceptional in list/detail |
| 14 | Projection | Inspect version/pointer after mutations | Increment/pointer follow committed evidence and threshold policy |
| 15 | Projection | Probe API/routes with arbitrary quantity/cost/pointer update | No generic mutation path exists |
| 16 | Catalog UX | Filter active/inactive/all, low and below-zero; page results | Stable bounded results and correct badges on phone/desktop |
| 17 | Opening | Post first Opening | One movement/item; WAC established; atomic evidence |
| 18 | Opening | Attempt second Opening for same established Product | Stable conflict; Draft retained; no stock/evidence effect |
| 19 | Opening | Open a different unestablished Product later | Staged Opening succeeds |
| 20 | Receipt | Post single-line Receipt | Quantity, WAC, pointer, version, Audit/changes correct |
| 21 | Receipt | Repeat Product on two lines with different costs | Separate items/movements; deterministic line order |
| 22 | Receipt | Verify line amount rounding | DECIMAL multiplication rounded to 2 places |
| 23 | WAC | Hand-check positive Q formula over multiple costs | Stored six-place WAC matches exact-decimal calculation |
| 24 | WAC | Intake at Q=0 with retained/unestablished state | Approved reset/establishment result |
| 25 | WAC | Intake where negative stock remains non-positive | Negative excluded; prior basis/state retained appropriately |
| 26 | WAC | Intake crossing negative to positive | Basis resets to crossing line cost; later same-Product lines blend in line order |
| 27 | Receipt | Post multi-Product Intake with reverse browser order | Positions lock ascending Product ID; all effects commit |
| 28 | Posting | Submit same Draft twice/concurrently | One post only; second `INTAKE_ALREADY_POSTED`; no duplicate evidence |
| 29 | Draft | Create/edit/items/remove/discard ordinary and replacement Drafts | No permanent Audit or `sync_changes` |
| 30 | Manual | Record DAMAGED | Negative MANUAL evidence, retained cost rule, reason/Audit/changes |
| 31 | Manual | Record EXPIRED | Same invariant and correct plain-language history |
| 32 | Manual | Record OWNER_WITHDRAWAL | Stock-only evidence; no financial effect |
| 33 | Manual | Record MISSING_LOSS | Accountable negative evidence and reason |
| 34 | Correction | Add and remove with VALID/RETAINED basis | Quantity changes; no WAC; basis retained |
| 35 | Correction | Positive Correction with UNESTABLISHED basis | Remains UNESTABLISHED; no invented acquisition cost |
| 36 | Inactive | Run authorized damaged/expired/loss/Correction on inactive Product | Allowed; no implicit activation; Intake still rejected |
| 37 | Reversal | Reverse MANUAL movement | Exact opposite delta, current snapshots, immutable linked original |
| 38 | Reversal | Retry and race two reversals | One direct reversal only; stable conflict |
| 39 | Intake reversal | Reverse latest Intake | Same-kind zero-item B and one R per original; original unchanged |
| 40 | Cost inverse | Opening 10@10, Receipt 10@20, reverse Receipt | Returns to 10@10, not 10@15 |
| 41 | Intake reversal | Reverse duplicate-line and multi-Product Intake | Reverse application order; deterministic locks; all-or-nothing |
| 42 | Intake reversal | Add later Receipt/outgoing/Correction then try old reversal | `INTAKE_REVERSAL_NOT_CURRENT`; no partial effect |
| 43 | Fallback | Use reasoned Correction after unsafe direct reversal rejection | New transparent evidence; original retained |
| 44 | Replacement | Create/edit/discard linked Receipt Draft | Reason retained as working state; Audit/change silence |
| 45 | Replacement | Post linked Receipt | `stock_intake.posted` + reasoned `.replaced`, C/movements/positions/original-A changes atomically |
| 46 | Replacement | Reverse initial Opening, confirm zero UNESTABLISHED chain, post replacement Opening | Shared ordinary Opening eligibility succeeds |
| 47 | Replacement | Try Opening replacement with established/intervening history | Shared rule rejects; no bypass/success evidence |
| 48 | History | Page Product history and Intake list beyond 20 rows | Stable order, no duplicates/gaps, all records reachable |
| 49 | History | Inspect A/B/R/C relationships | Originals, reversal, Draft-vs-posted replacement meaning and reasons are understandable |
| 50 | Security | Owner executes every authorized Phase 3 operation | Owner authority follows registry/access mode |
| 51 | Security | Test Staff with each grant alone and combinations | view/manage/adjust/intake/reverse remain independent |
| 52 | Security | Submit unknown permission and use deactivated membership | Fail closed; no operation/evidence |
| 53 | Access | Repeat reads/writes in FULL and READ_ONLY | FULL permits authorized writes; READ_ONLY retains reads and rejects all writes |
| 54 | Access | Repeat in BLOCKED and CLOSED | No normal Shop operation |
| 55 | Isolation | Substitute foreign Product/Intake/Movement UUIDs | Non-enumerating rejection; no cross-Tenant data/effect |
| 56 | Isolation | Forge tenant/shop/actor/membership fields and hostname | Server context wins; invalid host/context fails safely |
| 57 | Evidence | Forge before/after/source/FKs/cost-state fields | Ignored/rejected; server derives immutable evidence |
| 58 | Atomicity | Inject Audit failure for each authoritative family | Domain and change rows fully roll back |
| 59 | Atomicity | Inject change-publication failure | Domain and Audit rows fully roll back |
| 60 | Atomicity | Inject movement insert, position update and Intake lifecycle failures | Entire transaction rolls back; Draft remains valid where applicable |
| 61 | Concurrency | Race two Opening posts | Exactly one valid initial establishment |
| 62 | Concurrency | Race overlapping same-Product Receipts | Serialized WAC and reconstructable final projection |
| 63 | Concurrency | Race reversed-order multi-Product Receipts | No deadlock leak/partial state; valid serialized result |
| 64 | Concurrency | Race Intake reversal against new Receipt/activity | Only a state-valid serialized outcome commits |
| 65 | Audit | Inspect every authoritative mutation and rejected command | Correct actor/action/resource/reason/metadata/correlation; no false success |
| 66 | Changes | Inspect every publication family | Exact Product/Position/Intake/Movement kinds; Draft silence; sibling transaction |
| 67 | Diagnostics | Run `php spark inventory:diagnose <tenant-uuid> <shop-uuid>` | All categories zero in clean fixture; output states read-only |
| 68 | Diagnostics | Create isolated corrupted database copies for quantity/pointer/chain/source/coverage/reversal/scope/version anomalies | Corresponding diagnostic reports each; no data changes |
| 69 | Responsive | Complete Product/list/detail/actions at 320, 360 and 390px | Equal capability; no routine horizontal scrolling |
| 70 | Responsive | Complete Intake/reversal/replacement/history at phone, tablet and desktop | Equal capability and readable long names/reasons/references |
| 71 | Accessibility | Keyboard-only navigation/actions/dialogs | Logical order, visible focus, Escape/close behavior, no trapped operation |
| 72 | Accessibility | Repeat at 200% zoom | No capability or content loss |
| 73 | States | Exercise loading/empty/success/422/403/409/timeout/network/500 | Consistent feedback; recoverable form values retained; no queued claim |
| 74 | Scope | Inspect navigation/routes/UI | No dead control or Sales/Return/Customer/Reconciliation/Supplier/PO/offline placeholder |

Closure requires every mandatory row PASS, no unresolved critical/high defect, and retained evidence references in the owner acceptance record.
