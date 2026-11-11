# UI3-A — Trading screen redesign

**Date:** 10 November 2026 (Africa/Lagos). **Branch:** `codex/ui3-a-trading-screens-20261110`. **Base:** main `5ff9ae868b8e14f9be93abaac1ab1dfcad6fa3f6`. **Acceptance:** pending Owner manual verification.

## Integration prerequisite

The Owner reported merging UI2. Inspection found #29–31 merged into `codex/ui2-a-tokens-icons-20261108`, while main contained only the squashed UI2-A #28. The first commit on this delivery explicitly integrates the accepted B/C/brand changes using their exact source diff from UI2-A through refinement `e6059d3dfca2ba8c2ff494da5d25f59e4e48a3ac`. It does not reapply the unrelated reverted Phase 4 acceptance change. Its resulting tree is `99bcc41fcb4dbc1f1f886109072ed059bc96f39b` (the accepted refinement tree). UI3-A is the following change. Review/merge this PR against **main**, not an old UI2 branch. No automatic merge or deployment.

## Delivered coverage

| Task family | Implementation |
|---|---|
| Customers | Search/status/paging, private-detail permission, create/edit/inactive status, linked validation and guarded local exits |
| Products and stock | Dedicated panel, search/status/stock filters/paging, units/prices, create/edit/similar names, alert threshold, stock changes/reasons and original history |
| Deliveries / starting stock | Dedicated panel, new/resume/edit/remove items/order/cost, details, review/explicit confirmation, discard, saved movements, cancellation and replacement |
| Selling | New/resume unfinished sale; customer/time and quick customer; product selection/current price and override gate; quantity/order/edit/remove; responsive basket/payment columns |
| Payment and save | Cash/transfer/unpaid amount/date; customer requirement guidance; authoritative review; permanent confirmation; busy/uncertain/status paths using the same record |
| History / receipts | Existing search/date/type/paging; readable statuses and amounts; internal permission-controlled costs/profit; customer-safe print clone; stock evidence and linked records |
| Returns | Original quantities/limits/prices, save/calculate/refund/review/reason/confirmation/discard; saved result and unknown status handling |
| Cancellation / replacement | Plain English about undoing money/amount owed/stock; reason and review; original history retained; related cancellation/return/replacement links |

Shared implementation uses accepted local icons, fields, buttons, feedback, disclosures, native confirmation dialogs and a stock-record side sheet. A new scoped trading stylesheet replaces the old task layout without migrating Owner/Admin/Auth bodies early. Primary icons are visible alongside words. Common customer/product success messages may dismiss after nine seconds; financial outcomes and instructions remain persistent. No new external dependency, schema migration, endpoint, financial calculation or offline queue.

## Verification evidence and limits

- Frontend build and changed JavaScript syntax inspected; source/public parity checked at delivery.
- Fourteen read-only task variants rendered through Vue's host renderer without Vue warnings, using synthetic local records. This caught and fixed a missing `stateLabel` import before delivery.
- The real sale handlers were exercised with mocked replies: preview, one POST despite repeated activation, 500/uncertain response, same-record GET recovery, unchanged reviewed amount/version strings. This exposed an existing shared `UiStatusPanel` missing `emit` binding; the binding is fixed here so Check saved status works.
- The actual print-clone/filter handler was exercised with a host-node adapter: cost/profit and internal stock nodes removed, receipt reference and total retained.
- No committed automated suite/framework or database tests were added. These checks do not replace the Owner's manual verification or establish real HTTP/DB, native DOM/CSP/dialog/focus/keyboard/visual/print behavior. Native Chromium could not start in this environment during UI2; no browser screenshot or device acceptance is claimed for UI3-A.

Follow [manual verification](manual-verification-ui3-a.md). UI3-B still covers starting cash, Staff, settings, appearance/domains, plan, Activity, Admin/Auth task bodies and copy. UI4-A remains full programme/device/release verification. Existing backend error facts and supported recovery contracts remain authoritative; generic retry, collections and customer statements are not introduced.
