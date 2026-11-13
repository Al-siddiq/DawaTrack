# Phase 6 WP2 — Owner withdrawals and money added

13 November 2026 (Africa/Lagos). WP1 Owner-accepted; WP2 implemented for manual review. This extends the [approved plan](Phase_6_Implementation_Plan.md) and [WP1 foundation](Phase_6_WP1_Integration_Contracts.md). No migration or dependency is added; all financial_movements schema/SQL and prior artifact manifests remain unchanged.

## Explicit commands and authority

| POST path under /api/v1/shop/financial-movements | Operation | Stored type/effect |
|---|---|---|
| /owner-withdrawals/preview | Review Owner money taken out | OWNER_WITHDRAWAL, negative magnitude |
| /owner-withdrawals | Confirm Owner money taken out | Same negative effect |
| /owner-introductions/preview | Review Owner money added | OWNER_INTRODUCTION, positive magnitude |
| /owner-introductions | Confirm Owner money added | Same positive effect |

Existing expenses/preview, expenses, history and UUID detail routes remain. No arbitrary type parameter, generic movement writer, correction/reversal endpoint or permission alias is introduced. Command fields and confirmation fingerprint match WP1: UUID, CASH/TRANSFER, positive decimal-string amount, required explanation up to300, optional reference up to120, optional UTC DATETIME6 occurrence; post additionally review_fingerprint. Unknown type/sign/scope/actor/link fields are rejected. Public amounts are strings, never JSON numbers or floats. Null occurrence means time first saved; explicit occurrence uses the existing bounded Audit policy. Actor, scope, posted time and sign remain server-owned.

Both Owner commands require financial.view + financial.record, active FULL access and **current ACTIVE PRIMARY_OWNER membership**. CommercialAccess owns Tenant → Shop → active membership locks and current permission/access rechecks. Inside that same transaction callback, before replay and new validation, MovementRecordingService explicitly reads that membership by tenant/membership/user/role/status FOR UPDATE. It never trusts a stale ShopContext role, browser isOwner flag or holding every financial grant. Preview and confirmation independently perform this check. Equivalent retries still require current authority; lost write authority can recover through the existing scoped GET where view remains authorized.

Cash uses the existing finalized Starting cash and Cash/Full control guard; Transfer skips only Cash-specific eligibility and has zero physical_cash_effect. All other authority/schema/evidence checks apply to both channels. Introduction is a genuine later movement, not a Starting cash substitute, sale, revenue, debt receipt or inventory action. No invented balance/overdraft rule or WP4 expected-Cash engine is added.

## Source ownership and atomicity

ExpenseService retains its accepted public methods and delegates to explicit Expense methods in MovementRecordingService. OwnerMovementService exposes explicit withdrawal/introduction preview/post methods. The common engine's generic type-bearing preview/post functions are private; only six fixed action methods choose types. It shares normalization, schema readiness, repository, posting participant, Cash guard, Audit and change sequencing. ExpenseInput keeps its historical name as the shared closed-body normalizer, with action-sensitive fingerprints. The SHOP_EXPENSE fingerprint prefix remains exactly expense-v1 for compatibility with accepted rows and in-flight reviewed commands; Owner prefixes are OWNER_WITHDRAWAL-v1 / OWNER_INTRODUCTION-v1. Cross-action UUID reuse conflicts even if other fields match.

The same immutable movement, required financial_movement.recorded FINANCIAL Audit (explanation as reason, exact type/channel/delta/hash metadata), and financial_movement change commit together. Audit precedes change publication; generation publication remains last. History/detail verify recorded evidence and display a truthful generic Money record saved Activity label, specific movement type, amount/channel/actor/explanation/times and same public reference. No independent owner posting table or mutable balance exists. Ordinary records have null reversal/replacement links; causal undo/replacement is WP3.

## Workspace and interrupted saves

One existing Money movements destination gains Owner took money and Add money to shop actions; no extra bottom tab. Plain Money wording supports Cash and Transfer without implying a Transfer is physical cash. Direct Owner hashes /withdrawal and /introduction are denied in UI for Staff; backend remains authoritative. The existing /expense and /record/{UUID} hashes continue. Preview, confirmation modal, busy states, icons, focus, validation and navigation protection reuse the accepted shell.

The existing per-host/user/Shop session marker key is retained for backward recovery. The marker now holds only `{movement: UUID, action: expense|withdrawal|introduction}`; it contains no amount, explanation, reference, body or credentials. Older UUID-only markers are interpreted as expense. Valid action tags are checked as own registry properties. Pending Owner recovery redirects to its original action, never another sign/action. A 404 is inconclusive during an in-flight request; explicit continuation retains UUID and action and requires re-entry/review of the same content. No automatic repost/offline queue. Permission/role loss still permits authorized GET status lookup, not a new Owner write. A route/role change while a confirmation is open invalidates that confirmation before POST.

Native manual acceptance P6-13–P6-18 and affected WP1 regression checks remain pending. Parser/source/SQLite/custom Vue host probes are not native PHP/MySQL/browser evidence. No production merge, deployment or database change is performed.
