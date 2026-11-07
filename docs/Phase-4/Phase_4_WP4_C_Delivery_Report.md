# WP4-C delivery — protected reversals and linked replacement

Date: 2026-11-06. **Implemented for native verification; C and whole-phase acceptance pending.** Base: WP4-B PR #24 `e05b4bcaad29272796ae5bfdbbf69ed9635ca332`. Branch: `codex/phase-4-wp4-c-corrections-20261106`. No merge, deployment or database migration is performed.

Owner instruction: “Confirmed and approved. Kindly proceed to the next implementation according to the plan”. This confirms proceeding from B to C; no unreported native tests are inferred. The full approved WP4 contract is unchanged.

| C requirement | Delivered |
|---|---|
| Sale/Return reversal | Exact inverse settlement for all original Cash/Transfer/full-credit/partial-credit compositions; opposite immutable header, required reason and explicit server preview |
| Existing credit correctness | Sale compensation cancels original available credit; Return compensation restores the same Receivable with linked positive REVERSAL; NULL new-Sale snapshots and no fake CREDIT/new Receivable |
| Physical compensation | Inventory-owned inverse REVERSAL movement graph, immediate source-line lineage, exact before qty/average/state, advancing versions/pointers |
| Dependency protection | Effective Returns block Sale reversal; latest-effective per-Product suffix, exact later-pair proof and position drift checks; source integrity/collection dependency denial |
| Replacement | One direct linked draft, identity retry, current-price ordinary Sale replacement and current-eligible original-linked Return replacement; separate normal posting with original/source refresh |
| Authority and recovery | sale.reverse independent of sale.return/sale.record; scope/FULL/Cash guards; source correction-state and compensation GET; cost response masking |
| Evidence integration | sale.reversed / sale.replacement_prepared Audit, atomic aggregate refresh, normal endpoint exclusions and compensation-aware Inventory movement coverage |

The implementation adds CommercialReversalService, CommercialCorrectionQuery/controller/routes and Inventory reversal/suffix participation. Existing Sale/Return posting recognizes linked replacement sources and refreshes them. Normal Sale queries exclude compensations; their explicit read endpoint prevents interpreting a Return reversal as newly created credit or ordinary Sale stock proof. B draft/posting and accepted WP1–WP3 behavior remain required regressions.

Engineering checks: changed PHP syntax via WebAssembly; WP1 20, WP2 61, WP3 2,031 and 101 existing contract assertions, B 10 provenance and 83 orchestration checks; C 7 pure effective-suffix checks and 171 compensation/replacement orchestration assertions with explicit in-memory database/authority/evidence doubles. C covers both directions/all seven compositions, same original Receivable, exact inverse links, NULL snapshots, repeated Product lines, same-identity retry, later effective evidence/dependency rejection, restored unknown/negative stock state, permissions/masking, replacement price/posting, and injected failures at each financial/stock/Audit/publication/transaction boundary. Source checks and doubles do not execute native SQL or prove isolation.

No native MySQL migration/constraints/concurrency/rollback, authenticated HTTP/CSRF or browser workflows are executed. No new project dependencies or frontend assets are introduced. Native/manual status remains NOT EXECUTED until recorded in [the C checklist](../manual-verification-phase-04-wp4-c.md).

Remaining WP4-D: integrated Return/correction screens, combined receipt/history/navigation/printing, Sale usability fixes, complete compensation-aware commercial diagnostics, browser/accessibility and full owner acceptance matrix. Inventory coverage is adjusted now to prevent false ordinary-item anomalies from the new graph; it does not substitute for D's complete diagnostics/closure. P4-23/P4-24 correction rules are implemented here; final P4-19–P4-33 integrated acceptance remains open. See [C API/module contract](../modules/phase-04-wp4-c.md).
