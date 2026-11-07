# WP4-B delivery — Return drafts, previews and posting

Date: 2026-11-05. Status: **Implemented for native verification; not owner-accepted.** Base: WP4-A PR #22 at `c5ec4aabe1d4fb9642abb0992ccd12df70463aa3`. Branch: `codex/phase-4-wp4-b-returns-20261105`. No merge, deployment or database migration is performed.

The owner approved proceeding with WP4-B after the WP4-A increment. The approved financial/schema contract is unchanged. This delivery supplies a usable authenticated API increment; it does not declare the browser workflow, compensation commands or complete Phase 4 delivered.

## Coverage

| Capability | Delivered evidence |
|---|---|
| Original-linked partial/full/fractional Return drafts | ReturnService create/update/discard; original line lookup, versioned safe read/list API |
| Original-price/cost quantity/value/COGS caps | CommercialEvidence allocation and effective prior Return reads; final full exact-kobo consumption |
| Debt-first settlement and refunds | Existing original Receivable RETURN_REDUCTION; actual Cash/Transfer REFUND rows, effective channel caps, no fake CREDIT/new Receivable |
| Inventory restoration | Inventory-owned incoming participant and shared immutable cost history; weighted arithmetic, uncertainty propagation, inactive historical identity and precision guard |
| Preview/post safety | Explicit reviewed split/fingerprint, recomputation under ordered locks, permanent UUID read-back/retry, no draft reservation |
| Mandatory integration | Independent sale.return, cost masking, current scope/access authority, Cash guard, reasoned aggregate Audit and atomic refresh events |
| Safe evidence retrieval | GET Return draft/receipt, original line links, movement UUIDs, actual money and original-Receivable adjustment identifiers |

CommercialAccess now rejects nested ownership and failed transaction start before mutations; the fault checks include transaction start.

No new schema migration is added in B; use WP4-A's ordered 2026-11-05-100000 migration. The original Sale posting and browser assets remain unchanged. Intake posting/reversal and subsequent Sale historical receipt reads now understand Return-era unknown provenance; manual movement reversal aligns Product/movement lock ordering with the shared history reader.

## Engineering verification

Executed using PHP WebAssembly: changed PHP syntax; WP1's 20 Cash/Audit cases, WP2's 61 validation/SQL-source checks, WP3's 2,031 exact-money/history checks and 101 posting-orchestration checks; WP4-B's 10 pure provenance assertions and 83 Return orchestration assertions with explicit in-memory database/authority/evidence doubles.

The B checks exercise all seven original payment compositions, debt-only and mixed refund effects, cumulative final quantities/kobo, original price after Product changes, inactive historical references, zero value, weighted average, unknown mixing/recovery, version/refund/fingerprint denial, committed retry, authority modes and missing sale.return, cost response masking, draft discard, and injected stock/item/position/payment/adjustment/header/Audit/change/commit failure rollback. Existing permission test doubles gained an optional denied-grant list and transaction-start failure injection so denial paths can be exercised. These are engineering checks, not native financial acceptance.

No MySQL statements, native migration/constraints, authenticated HTTP/CSRF flows, concurrent real transactions or browser operation are executed here. No frontend asset changes or new project dependencies are introduced. The scoped native/manual checklist remains NOT EXECUTED until observations are recorded.

## Remaining staged work

WP4-C: protected Sale/Return compensation and linked replacement. WP4-D: integrated operator Return/correction screens, combined receipt/history navigation, complete compensation-aware diagnostics, layout/accessibility/print checks and whole-phase regressions. Permission/Audit/refresh essentials are included now because Return posting cannot safely defer them. Their correction integration remains later work.

P4 Return-specific rules are implemented in this increment; P4-19–P4-24 and the cross-cutting P4-25–P4-33 cannot be declared finally accepted until C/D and integrated owner verification. Continue using the [approved full plan](Phase_4_Comprehensive_Analysis_and_Four_Work_Package_Plan.md), [approved contract](Phase_4_WP4_Approved_Return_and_Compensation_Contract.md), [B module/API contract](../modules/phase-04-wp4-b.md) and [B manual guide](../manual-verification-phase-04-wp4-b.md).
