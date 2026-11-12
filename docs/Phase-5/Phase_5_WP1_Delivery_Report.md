# Phase 5 WP1 — Debt foundation and live balances

**Delivered:** 11 November 2026 (Africa/Lagos). **Branch:** `codex/phase-5-wp1-debt-foundation-20261111`. **Status:** implementation candidate delivered for Owner manual verification; WP1 is not yet accepted. WP2–WP4 have not started.

## Result

Customers now includes **Money customers owe**, with searchable Customer cards, exact live totals, bill lists/details, unpaid/partly-paid/paid/overdue filters and the source Sale link where permitted. Inactive Customers retain their historical bills. Original Sale credit snapshots are unchanged. The accepted shared navigation/components/icons/brand presentation are reused; no bottom destination, payment action or offline money queue is added.

Two parent-first migrations introduce the full approved collection/allocation tables without altering existing commercial evidence. Independent debt permissions, one explicit schema gate, one consistent read snapshot and the signed balance projection supply the foundation for subsequent collection. Existing CommercialEvents adjustment insertion now delegates to the reusable CustomerDebt transaction participant; the caller's Sales/Return orchestration, Audit, changes and commit are preserved. New down migrations refuse to delete populated financial tables.

The [integration contract](Phase_5_WP1_Integration_Contracts.md) fixes implemented read payloads/errors and specifies future UUID/review/recovery, permission, lock, allocation, compensation, refund, Audit/change and statement contracts. Its later-package routes are specifications only; no such writers are registered now.

## Approval and integration

The Owner explicitly accepted the four-package plan and authorized WP1 only; [approval record](../acceptance/2026-11-11-phase-05-plan-owner-approval.md). At start, planning PR #38 had not merged. This branch retains approved planning commit `a5f625dabf3cf88f6e2b56e654d83bea2fd7f207` on top of main `b41425f905100a728a4c6167d5df936702a8502a`, then adds WP1. The implementation PR targets **main directly** and includes that prerequisite; it does not assume a feature-branch merge has reached main or merge anything automatically.

Plan approval is not WP1 acceptance. Prior Phase 4/UI acceptance remains valid and separate. Existing collection-dependent Return/cancellation guards and original-only refund capacity stay in place until their approved WP3 integration; no hidden partial collection implementation circumvents them.

## Scope and source

| Area | Delivered source |
|---|---|
| Schema | `app/Database/Migrations/2026-11-11-190000_CreatePhaseFiveDebtPayments.php`, `190100_CreatePhaseFiveDebtPaymentAllocations.php`; two paired SQL files under `docs/database/sql/phase-05` |
| Read model | `app/Modules/CustomerDebt/Infrastructure/{ReceivableBalanceQuery,DebtQuery,DebtReadSnapshot,DebtSchema}.php` |
| Shared adjustment insertion | `app/Modules/CustomerDebt/Application/ReceivableAdjustmentParticipant.php`; existing `Sales/Application/CommercialEvents.php` delegate |
| API/security | `app/Controllers/Api/DebtController.php`; Routes; PermissionCode; Shop context `view_debt`; permission labels |
| Native-style UI integration | `resources/js/components/customer-debt-panel.js`, CustomerPanel, workspace registry and compiled Shop template; generated public modules |
| Release inventory | Existing generator accepts a docs-contained `--output=` option; this package has its own 365-file inventory, preserving the accepted UI4 manifest |

## Engineering verification and limits

Actual checks recorded in [engineering evidence](Phase_5_WP1_Engineering_Evidence.json):

- Production frontend build succeeds with existing locked dependencies; 34 icons, 17,008 raw / 5,102 gzip bytes, no new dependency.
- PHP 8.3 **grammar parser** accepts all 237 App PHP files. This is not native PHP execution.
- All 42 published JS modules parse; 118 local import/re-export edges resolve. Source/public module parity is included in the release inventory generator.
- Two migration/raw-SQL pairs match exactly for their up statements. Down methods' protected-data refusal was source-reviewed, not executed against MySQL.
- Ten mocked Vue host render variants exercise list/detail/bill/empty/denied/malformed-link/missing-setup/offline/parent deep-link paths without Vue warnings; eight GET requests, no mutations. This is not a browser/HTTP/permission or accessibility test.
- Nine actual projection sign/date/scoping scenarios run with synthetic **integer** amounts on SQLite: base debt/overdue, spanning allocation, payment inverse, decrease/inverse/increase, foreign-scope exclusion and invalid-balance attribution. This verifies limited query structure/sign handling, **not MySQL DDL/DECIMAL/locks or PHP execution**.
- The package artifact inventory matches SHA-256 `1480203991f226152c45fa2efb9910570be689fe43a587740763f415e2743834` (365 files). The accepted UI4 inventory is unchanged.
- Diff whitespace checks and documentation/case/link checks pass. No automated test suite/framework is added to the repository; engineering probes are scratch-only review aids.

Native PHP and MySQL are not installed/connected here. Authenticated HTTP, native migrations/constraints, down guards, live query plans, concurrent transactions/rollback, actual browser layout/keyboard/assistive technology and production rollout were not executed here. The previously recorded native Chromium environment limitation is not treated as a device pass. These limitations are explicit in the manual handover, with no fabricated results.

## Owner verification and next gate

Start with [WP1 manual guide](../manual-verification-phase-05-wp1.md) and [P5-01–P5-10 results](Phase_5_WP1_Manual_Results.csv). The guide includes Windows-compatible commands, the two migrations, exact ₦2,000 fixture and Return-adjusted expectations, roles, direct scope denials and responsive/error/offline checks.

Native partial collection/inverse states become reachable through actual commands in WP2/WP3. WP1 does not seed fake posted payments for acceptance. Its shared query and display support them now, while subsequent packages retain their actual workflow gates.

No deployment, production migration, merge or acceptance is performed on behalf of the Owner by this handover. After explicit WP1 acceptance, proceed to WP2 only.


## 12 November 2026 — Windows inventory follow-up

Owner testing identified clean Git checkouts with CRLF physical bytes. Four sampled screenshot hashes were reproduced exactly. The verifier now distinguishes CRLF-only from actual content/set differences and offers explicit checksum-constrained `--check --fix-line-endings` repair, preserving the original artifact manifest. See [diagnosis/recovery/evidence](Phase_5_WP1_Windows_Inventory_Followup.md). Simulated CRLF repair and negative content/missing/BOM cases pass within their stated Linux-only limits. No business/schema change or WP1 acceptance is implied.
