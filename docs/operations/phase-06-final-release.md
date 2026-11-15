# Phase 6 — final release and support handover

**14 November 2026 (Africa/Lagos).** All packages Owner-accepted; whole-phase release decision pending. This is the complete operational procedure, not a report of production execution.

## Release identity and responsibilities

Application source: `9483c57d2fae3fd0ed59f0403470e678de4429d2`. Closure branch: `codex/phase-6-closure-20261114`. Inventory: [Phase_6_WP4_Artifact_Manifest.json](../Phase-6/Phase_6_WP4_Artifact_Manifest.json), 412 files, content `0f2bb165391d7dc006fe91ac0fad99ca5a396618928ad0898e494d0b9ab4d728`. Record final reviewed closure commit and later integration commit explicitly; both must retain this application inventory unless separately reviewed.

Owner: approve whole-phase scope/evidence/conditions and deployment separately. Technical maintainer: verify commits, exact assets, schema compatibility and defects. Release operator: record environment versions, backup/restore check, maintenance window, deployed/previous artifact IDs, smoke results and time. These are roles to assign, not claims of named staffing or support SLA.

## Integration choice

Observed main: `f985b5a88516ecd3861497bae0ee90984dfe47a1`. PRs #45 (WP1), #46 (WP2), #47 (WP3), #48 (WP4) are all open/unmerged. Git ancestry confirms all three predecessor package heads are ancestors of the accepted WP4 candidate.

Recommended after final review: integrate the cumulative closure branch directly into main once, with a merge commit. It contains WP1–WP4 and the plan prerequisite, so there is no need to merge the predecessors separately. Afterwards mark superseded PRs covered only when the actual main tree/ancestry confirms it. Do not merge duplicate package snapshots again.

Alternative: merge #45 → #46 → #47 → #48 → closure, using merge commits and rechecking each next comparison after every merge. Prior handover's #46-first sequence omitted still-open WP1 #45; this final sequence explicitly includes it. Squashing intermediate stacked PRs rewrites ancestry and can recreate add/add conflicts, as documented during Phase 5. If main moves, inspect the fresh diff and resolve/reverify without overwriting unrelated work. No force push or automatic merge is authorized by this procedure.

## Preflight in isolated staging

1. Checkout the exact reviewed closure commit. Preserve environment, encryption key, uploads and writable data outside the package; keep existing secrets private. Identify the real previous deployed release, not merely a candidate SHA.
2. Install locked dependencies (`composer install` with the project's release settings, `npm ci`), run `npm run build`, and verify the recorded inventory. Never regenerate it to hide differences. The existing inventory supports reviewed CRLF-only repair with `--fix-line-endings` followed by the plain check.
3. Record native PHP/Composer/MySQL/Node/npm versions and required extensions, application connections in UTC, Shop timezone, role/grants and migration status. PHP vendor/environment/database are outside the inventory.
4. Take and identify a backup; demonstrate restore in isolation and retain the restoration evidence. Do not print credentials in release records.
5. Inspect migration status before any DDL. Phase 6's only new migration is WP1 `app/Database/Migrations/2026-11-13-100000_CreatePhaseSixFinancialMovements.php`, with SQL twin `docs/database/sql/phase-06/20261113100000_financial_movements.sql`. Apply missing approved prerequisites once in dependency order through the existing migration procedure; never apply PHP migration and SQL twin twice. WP2–WP4 introduce no migration. Verify native MySQL CHECK/FK enforcement, exact schema and successful status. Partial setup fails closed; inspect it rather than dropping evidence or blindly rerunning CREATE.
6. Run P6-01–P6-40 with synthetic business events through approved workflows. Record exact money, F1/F3, posted-time cutoffs, Transfer/Credit exclusion, scope/privacy, authorization, Audit/change atomicity, concurrency, rollback, same-ID recovery and device/keyboard observations. Expected Cash uses a local-only harness; never add a public route to test it.
7. Update individual registers, defect disposition and [release decision](../Phase-6/Phase_6_Release_Decision_Template.md). Obtain whole-phase decision against commit/inventory and resolve release blockers before a separately authorized production switch.

```bash
npm run ui:release:check -- --output=docs/Phase-6/Phase_6_WP4_Artifact_Manifest.json
php spark migrate:status
```

## Authorized rollout and smoke

Use the established versioned/atomic code-and-assets switch, or established maintenance/quiescence procedure if atomic switching is unavailable. Finish/check in-flight writes first; unknown results retain their UUIDs. Preserve previous compatible code/assets and existing environment/uploads. Stable-path JS/CSS must not be immutable cached; retain fingerprinted sprites for old tabs/rollback. Check real origin/cache/CSP behavior rather than assuming a hard refresh is sufficient.

After authorization and switch, record actual environment, commit, content hash, migration state, operator and Africa/Lagos timestamp. Check sign-in/context, authorized Owner/Staff navigation, scoped movement history/filter/detail, historical receipts and correction links. Use controlled test records for any explicitly approved write smoke; do not generate production balancing entries. Check old and new tabs, errors/CSP, read-only/offline states, exact signed Cash/Transfer labels, and status recovery of an uncertain save without duplication. Run internal expected-Cash smoke through approved local tooling only.

## Support and unresolved writes

Preserve Shop host/context, public UUIDs, source/inverse/replacement IDs, request correlation, posted_at boundaries, baseline origin, deployed commit/inventory and sanitized logs. Escalate financial inconsistency to the Owner and technical maintainer; restrict affected operations while investigating. Never turn missing source evidence into zero.

Use the authenticated owning-module status/history lookup before retrying.404 is inconclusive while an original request might still commit. Continue explicitly with the same reviewed command and same UUID(s); never replace an unresolved identity or replay automatically. Keep mandatory reason and financial data out of recovery storage/logs. Financial writes remain online-only.

## Incident rollback and data preservation

Stop affected financial/accountability workflows and preserve evidence first. Prefer a forward fix. A prior code release is eligible only if it understands the financial evidence already posted, or affected Cash/accountability surfaces are disabled under a reviewed recovery plan. Phase 5/main ignores Phase 6 financial movements and is not a safe automatic Cash-accounting fallback once such rows exist.

Switch complete compatible code/assets using the actual previous release ID, then verify context, read/history, caches and uncertain-ID recovery. Do not assume WP3 candidate `154a2d84b772d594adf8555bee08bc3fe80ca27c` was ever deployed. For a WP4-only defect, a reviewed compatible WP3 release can be considered with expected-Cash use disabled, subject to real deployment evidence.

Never delete/rewrite posted movements, Sales, Debt, Audit/change rows or finalized evidence; never restore an older database to undo a UI release. Do not run migration downs on evidence-bearing databases; the WP1 down method refuses a nonempty financial_movements table. WP4 is read-only and has no snapshot/balance/schema to undo. No operator SQL repairs or automatic variance movements. Re-enable only after the affected native cases and a concrete release decision are recorded.

## Handover completion record

Store actual release/rollback identifiers, environment versions, backup/restore references, result/defect links, decision/authorization, operator/time and post-switch monitoring observations with the inventory. These fields are currently unreported. Phase 7 Reconciliation and Phase 10 offline transport remain separately authorized work.
