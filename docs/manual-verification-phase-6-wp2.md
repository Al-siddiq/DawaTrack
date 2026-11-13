# Phase 6 WP2 — manual verification

13 November 2026 (Africa/Lagos). **WP2 native cases are Not run in the delivery environment.** Record your actual results in [WP2 manual results](Phase-6/Phase_6_WP2_Manual_Results.csv), with exact commit, runtime/database versions, current Shop, actor/grants, public references, observed values and evidence. The Owner's [WP1 acceptance](acceptance/2026-11-13-phase-06-wp1-owner-acceptance.md) is recorded separately.

## Setup

Use a backed-up isolated XAMPP development database and synthetic Shops, not production fault injection or deletion of protected rows. WP2 includes the accepted WP1 prerequisite because PR #45 was unmerged when work began. No new WP2 migration; apply any unapplied accepted migrations from status only.

```bat
git fetch origin
git switch codex/phase-6-wp2-owner-movements-20261113
git pull --ff-only
composer install
npm ci
npm run build
npm run ui:release:check -- --output=docs/Phase-6/Phase_6_WP2_Artifact_Manifest.json
php spark migrate:status
php spark migrate --namespace App
```

For checkout-only CRLF differences, repeat the inventory command with --fix-line-endings, then without it; never regenerate the delivered inventory to hide a true edit. Hard reload after Frontend assets built. Read [WP1 native setup/constraints](manual-verification-phase-6-wp1.md) and the [WP2 developer contract](Phase-6/Phase_6_WP2_Integration_Contracts.md).

## P6-13 — Owner took Cash

Sign in as the active Owner with FULL access. Start from the accepted F1 fixture: Starting cash ₦20,000 and Expense ₦1,500 (plus any explicitly recorded Sales/Debt fixtures). Menu → Money movements → Owner took money. Enter ₦2,000, Cash, explanation and optional reference/time. Review must say cash goes down by ₦2,000; modal confirmation saves exactly one OWNER_WITHDRAWAL with -2000.00, correct Owner, Shop, times, explanation and null causal links. Detail/history show the correct type, channel and reference. One required FINANCIAL Audit and one change commit. With only Starting cash/Expense/Withdrawal, hand calculation is ₦16,500; no WP4 live expected-Cash total is claimed.

Try without finalized Starting cash and with active Cash/Full control: reject without new movement/success Audit/change. Do not create unsupported Full Reconciliation UI to test its existing guard; use the accepted development fixture/control procedure.

## P6-14 — Owner added Cash

From the same controlled fixture choose Add money to shop and enter ₦5,000 Cash with explanation. Review/detail must say cash goes **up**, store OWNER_INTRODUCTION +5000.00 and one Audit/change. With only the above records hand calculation becomes ₦21,500. Introduction must not change Sales, debt, stock or the immutable Starting cash record. In a fresh Shop without finalized Starting cash, Cash introduction is blocked: record the genuine starting baseline first, not the introduction amount as a fabricated baseline. Check active Cash/Full count rejection too.

## P6-15 — Transfer and exact inputs

In a fresh Shop without Starting cash, save an Owner Transfer withdrawal ₦2,000 and introduction ₦6,000. Expect -2000.00 and +6000.00, Transfer labels, and 0.00 physical_cash_effect for both; no Cash guard. Optional bank references do not choose channels. Owner amounts are positive input; the action chooses sign. Repeat API validation for zero, negative, JSON number/float, exponent, excess precision/range, unknown movement_type/amount_delta/actor/scope/link, blank/overlong explanation/reference, malformed/future dates. Use current authenticated CSRF/session and explicit preview response fingerprint. No invalid request creates evidence. Maximum valid magnitude retains exact decimal-string precision. Review fingerprints from another action or changed body fail for a fresh command.

## P6-16 — Owner-only backend and scope

Use Staff with every financial permission, Staff view+record and legacy debt.manage. Owner buttons must be absent. Direct /withdrawal and /introduction hashes must not enable forms. Call all four Owner API endpoints directly: current Owner membership is mandatory, no movement/Audit/change commits. Client isOwner/role/type fields cannot grant access. Test another Tenant/Shop public UUID, scope/actor tampering, Platform Admin without current Shop membership, inactive member and blocked/closed access. Generic history/detail remains independently gated by financial.view. A Staff viewer may read authorized current-Shop money evidence without receiving Owner write rights.

## P6-17 — fresh authority at confirmation

Prepare each Owner action, then in another authorized session revoke/deactivate the membership, change its role to Staff (even retaining every financial grant), remove required access, or change subscription to READ_ONLY. Confirm using the prior preview: reject without movement/success Audit/change. Preview itself also rejects current Staff. In two native DB sessions verify current Owner selection occurs under the same Tenant/Shop/membership locking boundary, without reverse lock acquisition or partial effects. Role changes in the active UI while a modal is open invalidate that confirmation. Restoring legitimate access may resume the same UUID after status lookup; lost authority must not be worked around with a new identity.

## P6-18 — duplicate, interruption, evidence and regression

For each Owner action/channel: double-click confirm, repeat an identical UUID/body after a time delay, and retry while Cash eligibility changes after a successful commit. There must be one movement, one success Audit and one change. An unchanged omitted occurrence remains equivalent. Same UUID with another action/sign/body/channel/explanation/reference/explicit time conflicts; never create another identity to silence uncertainty. Existing WP1 expense commands/fingerprints and saved history must still replay unchanged.

Simulate lost response after commit and reload: saved-status GET returns the same record without another POST. Simulate inconclusive 404 before a request resolves: explicit continuation keeps both UUID and action; the page must not convert money added into a withdrawal or expense. Inspect sessionStorage: only UUID and action in the host/user/Shop namespace; older UUID-only expense marker still recovers. Test different account/Shop, lost view permission, READ_ONLY, offline/online transitions, unknown 5xx and conflicting content. Neither action queues automatically.

In a disposable runtime inject failures after insert, during required Audit and at change publication inside the owning transaction; all rows/generation must roll back. Restore source and inventory. Native concurrent retries and permission/Cash-control changes must not duplicate or partially save. Review Activity reason/type/channel/amount metadata in authorized evidence tools; reads/preview do not create business success events.

Finally review narrow phone/desktop, keyboard/focus/modal close, button loading/disabled state, explanation correction, date/channel filters, paging, empty/error reads and browser navigation. Confirm Owner controls use the shared shell and no new bottom tab. Recheck expenses, Starting cash, Sales/Returns, Debt payments/corrections/statements, Stock and Staff settings. Individual movement effects are displayed; aggregate expected Cash remains WP4. Undo/correct actions remain absent until WP3.

Record Pass/Fail/Blocked/Not run for each case using observed evidence. Any failed case blocks acceptance. Submit WP2 acceptance after your manual review; WP3 starts only after that decision.
