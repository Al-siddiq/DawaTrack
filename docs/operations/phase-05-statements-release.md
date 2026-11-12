# Phase 5 WP4 — statement operations and release handover

Use [contracts](../Phase-5/Phase_5_WP4_Integration_Contracts.md), [native guide](../manual-verification-phase-05-wp4.md), [closure checklist](../Phase-5/Phase_5_Closure_Checklist.md) and [release decision](../Phase-5/Phase_5_Release_Decision.csv). WP1–WP4 and the whole phase are [Owner-accepted and closed](../acceptance/2026-11-12-phase-05-owner-closure.md). Actual integration/deployment remains separate; prior native execution details not supplied are not invented.

## Deployment prerequisites

WP1 payment/allocation tables, existing Phase4 Receivables/adjustments/Sales, Audit and active sync generation/change tables must exist. No WP4 migration or library. Statement runtime uses SELECT only; existing collection/correction runtime rights stay unchanged. Do not grant UPDATE/DELETE on financial evidence to make statement reads work. Use a coherent reviewed backend/source-generated CSS/JS/icon set, close old trading tabs, hard reload and verify exact branch/commit before manual review.

Statement page tokens require a persistent strong `encryption.key` of at least32 characters and OpenSSL AES-256-GCM support. Preserve an already configured strong deployment key; do not rotate it just for WP4. If no key exists, generate a local random key on the target administrator's machine, copy it directly to that deployment's private `.env`, and keep it out of Git, screenshots, issue bodies and shared logs:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Set `encryption.key = 'YOUR_LOCALLY_GENERATED_VALUE'` privately. The example placeholder is not a key. No actual key is supplied in this repository. Use the same key across application instances, preserve it through releases, and follow the deployment's secret backup/rotation process. Changing a shared encryption seed may affect other encrypted application data; review that deployment before rotating. A missing key produces a setup503; there is no insecure fallback. Token rotation/expiry only requires new statement pages, never edits to financial records.

The raw-byte inventory excludes real environment/vendor/uploads/database; a matching manifest does not verify secret configuration, native DB state or production rollout. Check the WP4 package inventory, not a historical UI4/WP1/WP2/WP3 inventory. If exact Windows CRLF conversion is identified, use `--fix-line-endings` and repeat the plain check; never regenerate to hide real differences.

## Statement support

Dates filter accepted posting time in Shop timezone. “Happened” can be earlier and does not move a record to a different posting period. Range closing can differ from current all-date debt. The private current total is deliberately omitted from customer print. A zero-change Return can still refund money; inspect its separately labelled money context, not another debt reduction.

On `STATEMENT_REFRESH_REQUIRED409`, discard all old page tokens and use Refresh statement from the beginning. Earlier printed pages are historical copies marked with preparation time; do not stitch them into a newly refreshed run. Tampered/malformed/wrong-key links fail422 and should be refreshed. Reauthorize normally after access/session changes; a token never grants view authority. Do not share page URLs/tokens between accounts/Shops or use them as credentials. No statement amounts or bodies are persisted in sessionStorage/localStorage.

Full-range print validates each page under one version, prepares at most2000 records in memory and prints only after the complete set is ready. If too large, shorten the date range or print individually labelled pages. Cancel is read-only and leaves no draft/report. On failure no partial full-range print occurs. Native Ctrl+P/current-page copy has its own page label. Verify both desktop and mobile/browser print paths on the actual target.

## Integrated native verification and release decision

Record P5-37–P5-48 against actual candidate SHA/device/Shop/timezone and supplement prior accepted package evidence only where affected. The integrated48-row register distinguishes Owner package acceptance from independently supplied per-case observations. Keep actual defects, native environment blockers and declared operational limits separate; do not mark a blocked concurrency/security/Print check Pass from a build. Review the historical foundation finding record through its existing disposition evidence, not a new blanket certification.

Run read-only `php spark sales:diagnose TENANT_PUBLIC_UUID SHOP_PUBLIC_UUID` before/after the integrated journey. Record the exact reviewed and deployed commits separately. Only explicit Owner whole-phase acceptance closes Phase5; deployment still needs its own operational decision. This package performs no merge, production mutation or rollout.

## Rollback and recovery

Back up the current database and complete coherent release before deployment. WP4 itself adds no DB effects/schema, so a compatible complete accepted **WP3** backend/assets release can remove statement UI without undoing accepted money. Keep the secret secure and intact. Never roll back to pre-collection/repayment-aware-return code against Phase5 evidence; never drop payment/adjustment tables, erase allocations/corrections, regenerate balances, restore an old production DB over later accepted events or change protected rows to fix a statement. If authoritative evidence disagrees, restrict affected operations appropriately and investigate read-only diagnostics/source identities; apply a controlled forward fix rather than a fabricated balancing event. Preserve all native observations and Owner decisions.
