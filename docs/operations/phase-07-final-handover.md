# Phase 7 final operation and release handover

17 November 2026 (Africa/Lagos). **Candidate only. Release is on hold until native evidence and whole-phase Owner acceptance are recorded.** No merge, deployment, production mutation, key change or database migration was performed for WP4.

## What to hand over

Use the [WP4 report](../Phase-7/Phase_7_WP4_Delivery_Report.md), [32 requirements](../Phase-7/Phase_7_WP4_Final_Requirements_Matrix.csv), [source effects](../Phase-7/Phase_7_WP4_Source_Effect_Matrix.csv), [48 results](../Phase-7/Phase_7_WP4_Integrated_Manual_Results.csv), [gaps](../Phase-7/Phase_7_WP4_Defect_Disposition.csv), [manual guide](../manual-verification-phase-07-wp4.md), [inventory](../Phase-7/Phase_7_WP4_Artifact_Manifest.json) and [decision record](../Phase-7/Phase_7_Release_Decision.md). Record the reviewed commit from `git rev-parse HEAD` alongside the manifest-file SHA-256 and its content digest; a branch name alone is insufficient.

## Module, API and UI map

- [Device operations](phase-07-wp1-devices.md) and [WP1 contract](../Phase-7/Phase_7_WP1_Integration_Contracts.md): enrollment/status/revoke, verified installation credential, scope, Audit Device FK, source guards and separate command permissions.
- [Cash operations](phase-07-wp2-cash-control.md) and [WP2 contract](../Phase-7/Phase_7_WP2_Integration_Contracts.md): baseline/cutoff, exact expected Cash source composition, pinned readiness, count/confirm/review/finish/cancel, explanations/notes and unknown replies.
- [Stock/Full operations](phase-07-wp3-stock-full.md) and [WP3 contract](../Phase-7/Phase_7_WP3_Integration_Contracts.md): independent predecessors, captured Product/cost evidence, exact quantities, Full atomicity, current-position adjustment, late-source associations and guarded FK.
- [WP4 contract](../Phase-7/Phase_7_WP4_Integration_Contracts.md): page/query bounds, revision v2, reason redaction, one control refresh per batch, generation/recovery/dialog behavior. Existing route names stay `/api/v1/shop/cash-controls`, `/stock-controls`, `/full-controls`; existing Shop menu links remain. No new endpoint or grant.
- [Trader guide](phase-07-shop-checks.md) uses the words shown on screen. [Wording policy](../ui/plain-english.md) applies to all future changes.

## Before a native rehearsal

Use an isolated copy and synthetic shops. Inventory all real versions: `php -v`, `composer --version`, `SELECT VERSION()`, server/OS/browser, UTC and Africa/Lagos display, SQL mode and storage engine. Do not assume XAMPP provides MySQL8.4; record the actual server and resolve compatibility before release. Use the supported native PHP/MySQL versions in the project documentation.

Back up database, full matching code/assets and private configuration; protect the existing encryption key and installed Device/session state. Record backup time, hash, location, access and restore operator without putting secrets in Git, logs or screenshots. Prove an isolated restore works and retained IDs, finalized evidence, source links, Audit/publication sequence and active generation agree. Never restore an old database over later posted shop activity as a routine code rollback.

Check all nine ordered Phase7 migrations. Device parent/FK precede evidence scope keys/counts/variances/notes/staff/acknowledgements; Stock Movement variance FK is last. Empty installs retain Settings → Shield → App ordering. Compare approved SQL twins on a separate disposable copy; use migrations OR raw SQL per database. MySQL DDL may partly commit: inspect actual objects and migration tracking before repair/retry. Do not blindly rerun a partially applied migration or mark it applied manually. WP4 has no additional DDL.

Keep the existing private `encryption.key` (at least32 bytes) and working OpenSSL. New empty installs can use the established private key setup; existing working keys must be preserved. Real HTTPS, correct hostname, secure host-scoped Device cookies, current CSRF and authenticated membership are part of native acceptance. Never paste keys, credential proofs, session cookies or real customer data into results.

## Review and integration

The WP4 branch starts at accepted WP3 `c9d8b996a830ce1ce3248a46d7516d7524e1ea80` and carries WP1–WP3. Observed main at preparation was Phase6 closure `42664001a18c9678022d85fd85e7be33b7640e3f`. Fetch and inspect actual ancestry before merging. A single cumulative reviewed PR may integrate the stack; if earlier packages were independently squashed, compare trees and reconcile duplicate snapshots first. Do not repeatedly squash the same prerequisites. Merge approval and production approval are separate from WP4 review.

## Deployment after separate authorization

Record Owner approval against exact commit/inventory and accepted case evidence. Schedule a short write pause; resolve active checks and unknown writes deliberately, without deleting their evidence. Take and verify the final backup. Deploy matching source and generated assets together, install locked dependencies, verify the WP4 manifest, inspect migration status, apply only approved missing prerequisites and restart the relevant PHP workers/cache. Do not regenerate inventory on the host to make a mismatch pass. Validate login/context, all three check histories, Device status, saved check details, expected permissions, logs and a synthetic approved full-day journey. Record timing and rollback owner. Do not change real shop balances as a smoke test.

## Compatible rollback and restore

Use the complete accepted WP3 code/assets at `c9d8b996a830ce1ce3248a46d7516d7524e1ea80` with its recorded Cash Start inventory, preserving Phase7 schema/data/private configuration. Reload open pages so revision tokens match the running version. Re-read original command IDs; do not auto-repost. Rehearse this on the copy first, including an active check, finalized Full check and linked stock adjustment.

**Phase6 main is not a safe code rollback after Phase7 business activity:** it lacks Phase7 Stock/Full guards and evidence behavior. Do not down/drop Phase7 schema or delete protected evidence to make it fit. If compatible code rollback cannot restore service, pause writes and use a reviewed forward fix. Database restore is disaster recovery requiring a separate plan for all posts after backup; retain the newer database/evidence and reconcile missing activity rather than silently losing it.

## Support

Capture HTTP status, safe error code, support reference and matching `commercial.request_failed` entry for unexpected errors. Setup503 can be a handled response without an exception log. Missing Device/evidence tables: inspect migration status; readiness setup: inspect private key/OpenSSL without displaying it; stale409: read/check saved work and retry only deliberately; unknown response/404: keep original identity and check again. Record exact observed behavior, not a guessed result. Never change posted evidence or clear another command's recovery marker.
