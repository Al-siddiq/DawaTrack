# Forward Sales operations — Phase 4 WP3

Prerequisite: accepted WP1/WP2 checkout and migrations. WP3 adds no DDL. Build the pinned frontend assets and check migration status. Staff need sale.view + sale.record; margin needs separate sale.profit.view; different prices need sale.price_override. The Owner establishes Opening Cash before any CASH amount is posted. Pure Transfer and credit do not require cash control. Keep a separate migration identity; ordinary runtime permissions cover existing transactional business/Audit/change writes.

## Unknown outcome

Keep the Sale UUID; do not create a replacement or repeatedly tap post. Use Check Sale status or GET `/api/v1/shop/sales/{uuid}`. POSTED means retain/view the permanent receipt. DRAFT means no successful post exists at that read instant; reload and deliberately review before a new attempt. Scoped not-found/authorization/unavailable means investigate hostname, membership, entitlement and actual server state before retrying. A request ID helps correlate logs, but is neither a business identity nor replay guarantee. Changes are not queued offline.

## Integrity and support

Use `php spark help inventory:diagnose` then the documented explicit Tenant/Shop scope. WP3 diagnostics recognize SALE/CUSTOMER_RETURN links/signs and item coverage, alongside quantity, pointer, version and historical chain checks. Diagnostics are read-only, never automatic repair. Correlate Sale UUID → Sale Item → stock movement, actual receipt rows and optional original Receivable with its Audit/change records. Do not repair a mismatch by editing posted rows, inserting fake CREDIT payments, modifying Customer balance or independently reversing a commercial stock movement.

A failed post should leave DRAFT plus prior items/stock/evidence unchanged; verify through a fresh DB/HTTP read rather than a possibly stale screen. Investigate database errors/deadlocks safely using request correlation. No partial effect or partial retry is acceptable. Native failure injection uses a disposable clone/operator connection and restored rights, as detailed in the manual checklist.

Posted financial evidence constrains rollback: preserve database/history and forward-fix or use the future WP4 protected business compensation once implemented. A code downgrade does not authorize schema down migration, manual deletion or FK disablement. WP4 Returns/correction are not available in this delivery. Historical unestablished-cost profit stays provisional after later acquisitions; this is intentional provenance, not an invitation to overwrite costs.

## WP4 independent commercial diagnostics

`php spark sales:diagnose TENANT_PUBLIC_UUID SHOP_PUBLIC_UUID` resolves one explicit Shop/Tenant and runs bounded normal Sale/Return checks in an owned REPEATABLE READ, READ ONLY transaction. No repair, Audit or change publication occurs. It checks line totals/arithmetic, original receipt/credit identity, scope and nonnegative adjusted obligations, plus normal Return source/settlement/effective limits. Existing `inventory:diagnose` remains the stock-projection companion. These are sampled read-only checks, not a certification of pending compensation rules or Phase 4 closure. Phase 5 collection allocations are not included. See the WP4 readiness report for manual checks and the concrete pending owner decision.
