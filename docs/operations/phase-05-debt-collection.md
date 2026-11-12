# Phase 5 WP2 operational handover

**Progression update,12 November2026:** [Owner accepted WP2](../acceptance/2026-11-12-phase-05-wp2-owner-acceptance.md) and authorized WP3. Delivery-time pending/boundary statements below are historical. Per-case native outcomes were not supplied. WP3 supersedes the temporary collected-return guard; WP4 remains separate.


Use the [module/API/transaction contract](../Phase-5/Phase_5_WP2_Integration_Contracts.md), [native manual guide](../manual-verification-phase-05-wp2.md) and [delivery report](../Phase-5/Phase_5_WP2_Delivery_Report.md). WP1 is accepted; WP2 still needs its own Owner manual decision.

## Release prerequisites

The approved WP1 payment/allocation tables and permissions must exist. WP2 adds no DDL or PHP/npm dependency. Deploy backend, locked dependencies and source-generated frontend coherently; close older trading tabs and hard reload before collecting. Runtime needs SELECT/INSERT for immutable payment/allocation evidence and existing Audit/change rights. A migration account's destructive authority is not an ordinary runtime permission.

Check the WP2 manifest with:

```bash
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP2_Artifact_Manifest.json
```

If the utility specifically identifies CRLF-only bytes, repeat with `--fix-line-endings` and then normal checking. Do not regenerate the manifest to conceal content differences. Prior WP1/UI4 manifests remain historical records.

## Accepted payment and recovery

A payment's public UUID is permanent. On uncertainty, preserve it and use its status/receipt GET; never create a new UUID as an automatic retry. A404 is absent at read time, not a cancellation of a request still in transit. The same-ID preview/post route either recovers the equivalent saved command or rejects different content. The browser's tab marker holds only Customer/payment IDs, is scoped to host/user/Shop, queues no money and grants no authority.

Receipt/history queries remain authorized in READ_ONLY. A collector whose write privilege expires can still check status if they retain view authority. If all view access disappears, restore legitimate access or ask an authorized Owner to inspect the payment; do not discard markers/evidence or weaken permissions. Each request's existing request/correlation reference supports investigation without logging credentials or request bodies.

## Correction boundary

WP2 does not expose undo, corrected replacement, general bill adjustment or statements. The existing Sales guard rejects collected-debt Return/cancellation until WP3 implements safe settlement/refund dependencies. Do not delete allocations, rewrite original credit or pretend collection is a new Sale to work around it.

## Rollback/incident handling

Code rollback cannot remove accepted payments or database history. Preserve complete code/assets and environment/data boundaries, identify the affected release and stop new writes while investigating. An old client/server release unaware of repayment must not resume affected Return/correction flows; keep incompatible operations disabled and prefer a data-compatible forward fix or controlled read-only recovery. Do not drop the WP1 tables or restore a pre-payment database over later real money. A production switch/rollback requires its own explicit operating decision and evidence; it is not performed by delivering this package.

Use isolated synthetic environments for fault/concurrency/lost-response checks. The manual guide names exact commit boundaries; an exception inside a transaction does not prove a payment was saved, and a lost HTTP reply does not prove it failed.
