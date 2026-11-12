# Phase 5 WP3 operational handover

Use [contracts](../Phase-5/Phase_5_WP3_Integration_Contracts.md), [manual guide](../manual-verification-phase-05-wp3.md), [delivery report](../Phase-5/Phase_5_WP3_Delivery_Report.md). WP2 accepted; WP3 native acceptance pending.

Deploy a coherent reviewed backend/source-generated asset set, with WP1 tables already migrated. No new DDL or dependency. Existing runtime SELECT/INSERT rights now also cover `receivable_adjustments` (and existing Audit/change tables); keep ordinary UPDATE/DELETE on immutable financial evidence unavailable. Close older trading tabs; hard reload. Do not run an older server that ignores collection/refund dependencies against new accepted records. No merge, deployment or production data repair has been performed by this package.

Check exactly the WP3 inventory; do not regenerate it to hide a difference:

```bash
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP3_Artifact_Manifest.json
```

If Windows checkout conversion is specifically reported, add `--fix-line-endings`, then repeat the plain check. Repair only succeeds for exact recorded LF bytes; genuine edits remain a conflict. Prior UI4/WP1/WP2 manifests are historical.

For unknown correction outcome, keep inverse and replacement UUIDs and the original reference. Check GET `/api/v1/shop/debt-payments/{inverse}`; its replacement link proves the pair's accepted identity. For bill corrections use GET `/api/v1/shop/debt-adjustments/{operation}`. No automatic repost; a404 alone does not cancel in-flight work. Continue only with original identities and same details. Do not replace UUIDs, clear browser storage, delete allocations or change protected rows. If session/grants expired, restore proper read authority or ask an authorized Owner to inspect the IDs in the same Shop. Never record the same physical money twice to “fix” an uncertain display.

A payment that has funded a refund cannot be undone while that refund remains effective. Open its linked Sale, inspect the Return and exact reversal eligibility. Undo the eligible Return first, then re-review the collection. Inventory changes may make exact Return reversal unsafe: stop and investigate the actual later stock chain; do not bypass that guard. Where a direct original Sale cancellation is requested, all effective collections/bill changes and Returns must be safely undone first. An atomic corrected replacement does not bypass dependencies just because its net amount would be similar.

Read-only diagnostics: `php spark sales:diagnose TENANT_PUBLIC_UUID SHOP_PUBLIC_UUID` (confirm command help for the installed release). Use positively identified Tenant/Shop public UUIDs; JSON findings never authorize repair. Archive affected public references/request IDs and investigate with approved role. Do not log credentials, full request bodies or customer-sensitive notes. Limits are100 findings/check; a clean diagnostic is not a substitute for native concurrency/rollback acceptance.

Back up database and complete release before rollout. Roll back code only to a data-compatible release; do not drop WP1 tables, delete corrections, restore an older production DB over accepted events or resume commercial correction with the old allocation-blind code. Prefer a controlled read-only state and forward fix when effects exist. WP4 statements remain unavailable.
