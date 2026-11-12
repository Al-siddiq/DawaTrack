# Phase 5 WP2 — receive and allocate customer payments

**Date:** 12 November 2026 (Africa/Lagos). **Branch:** `codex/phase-5-wp2-debt-collection-20261112`. **Decision:** implementation delivered for Owner manual verification; WP2 not yet accepted. WP3/WP4 are not implemented by this package.

## Delivered result

An authorized user can receive one Cash or Transfer payment, review automatic oldest-first or independently permitted selected-bill allocations, save once, inspect/print its receipt and reopen paginated history. Exact live Customer/bill balances refresh from the common projection. A payment spanning bills is one collection, not another Sale/revenue/profit or inventory movement. Inactive Customers may repay existing debt. Cash requires finalized Starting cash/no blocking active Cash scope; Transfer does not.

The server owns normalization, current authority, canonical locks, exact allocation sums, review fingerprint and one transaction for payment/all allocations/Audit/change evidence. Stable public UUIDs and persisted command/allocation evidence make equivalent retry repeat-safe and reject changed-command reuse. Source/public parity and a distinct package inventory are delivered; the accepted WP1/UI4 manifests remain intact. No migration or dependency is added.

The UI keeps existing Customers navigation, plain English, local icons, shared fields/cards/loading/confirmation/error/print patterns and selected brand touches. Explicit bill pages retain selection and exact kobo remainder; server review is the money authority. Uncertain results retain the same reference across a tab reload using a minimal scoped ID marker, never an outbox or automatic POST. A404 does not authorize creating another UUID. Receipt distinguishes the saved before/after totals from a later live balance and omits internal/private print sections.

## Acceptance and branch foundation

[WP1 Owner acceptance](../acceptance/2026-11-12-phase-05-wp1-owner-acceptance.md) records the exact confirmation and WP2 authorization. No individual WP1 case results were invented. At WP2 start PR #39 remained open; this branch retains accepted WP1 head `cca53e90d68ba092d78c2f35fcf94fa5240ad317` and its prerequisite plan, then adds WP2. The new PR targets **main directly**. No merge/deployment is assumed or performed.

Prior whole UI acceptance remains closed. WP2's native results remain Pending until Owner verification. Keep the planned package progression; do not start WP3 on the strength of a source build.

## Implementation reference

- [WP2 API/domain/lock/financial/privacy/recovery contracts](Phase_5_WP2_Integration_Contracts.md).
- [Windows-compatible native manual guide, fixtures and P5-11–P5-23](../manual-verification-phase-05-wp2.md).
- [Manual result register](Phase_5_WP2_Manual_Results.csv).
- [Operations/release and safe recovery](../operations/phase-05-debt-collection.md).
- [Machine-readable engineering evidence](Phase_5_WP2_Engineering_Evidence.json).

CustomerDebt now owns DebtPaymentInput/Service, DebtAmount/AllocationPlanner and PaymentRepository/Query, with a thin DebtPaymentController. Existing CommercialAccess/Reconciliation/Audit/ChangeSequenceService retain their authority/transaction ownership. Only normal recorded/allocated Audit actions are activated. No general adjustment, reverse/correct or statement endpoint is registered. Existing COLLECTION_DEPENDENCY guard remains until WP3, with plain-language UI guidance; do not remove evidence to bypass it.

The closed permission matrix is customer.view + debt.view for receipts/history, additionally debt.payment.record to preview/post and debt.payment.allocate for explicit selection. Customer.private, legacy debt.manage and reversal/adjustment grants do not imply these rights. Owner derives known-code authority; Staff grants stay separate. Current access, hostname/membership, CSRF and private/no-store boundaries remain on direct HTTP.

## Engineering evidence actually obtained

- Production build succeeds with existing locked dependencies. Local icon subset unchanged:34 symbols,17,008 raw/5,102 gzip bytes.
- All244 App PHP files pass PHP8.3 **grammar parsing**, not native PHP execution.
- All44 published JS modules parse and127 local import/re-export edges resolve. Two existing WP1 migration/raw-SQL pairs still match; no WP2 migration exists.
- Scratch mocked Vue host review:12 render variants,11 workflow assertions, no Vue warnings. Real client handlers exercise single-save, committed lost reply/reload/status, absent-at-read/same-ID continuation, stale review/input retention, explicit exact remainder, denial, READ_ONLY, offline, empty history, user-marker separation and actual Print clone filtering/cleanup. Responses are mocks, not HTTP/database proof.
- Existing WP1 debt UI host review still passes10 variants (8 GETs/no mutations).
- Actual receipt/allocation SQL runs on SQLite integer fixtures for four read-shape/scope/balance scenarios, alongside the existing nine signed projection scenarios. This is not MySQL/DECIMAL/transaction execution or PHP posting.
- The376-file package inventory is generated/checked separately; its content hash is recorded in `Phase_5_WP2_Artifact_Manifest.json`. A clean archive without Git metadata passes. Simulation converted367 text files to CRLF; explicit exact-byte repair restored all376 recorded files and normal checking passed. A mixed real content change was rejected without repair. This is Linux simulation, separate from the Owner’s earlier WP1 Windows evidence; no manifest regeneration is used to approve differences.
- Documentation/coverage/whitespace checks pass;88 new wording entries extend the catalogue to688 without replacing prior IDs. No automated testing framework or committed synthetic ledger/fault hook is added.

**Native not executed here:** PHP runtime; MySQL posting/constraint/DECIMAL/lock/concurrency/rollback; authenticated HTTP/permission/CSRF; actual Windows/phone/browser/keyboard/assistive technology; real Print preview; deployment/rollback. PHP/MySQL executables are unavailable in this workspace. Do not equate grammar, mock handlers or SQLite structure with native acceptance. The guide supplies isolated native fault/lost-reply procedures, not pre-filled Pass results.

## Manual gate and known package boundary

Complete P5-11–P5-23. Use fresh ₦2,000 A1000/B600/C400 credit bills; automatic collection1200 leaves800; independent explicit300 split B100/C200 leaves1700. Check actual evidence, original Sale/stock invariance, current rights after preview, two-session conflicts, lost replies, reload, printing and whole-transaction rollback.

Normal collected-debt Return/cancellation remains guarded as explicitly approved for this intermediate package. WP3 supplies payment undo/correction, legitimate adjustment/inverse, repayment-aware refund capacity and diagnostic integration; WP4 supplies statements/phase closure. No Phase 5 requirement is silently dropped or moved to Phase 6. Explicit WP2 acceptance is the next gate.
