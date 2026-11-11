# UI3-A manual verification

**Status:** pending Owner verification. **Branch:** `codex/ui3-a-trading-screens-20261110`. **Base main:** `5ff9ae868b8e14f9be93abaac1ab1dfcad6fa3f6`. Includes the accepted UI2-B/C/brand changes missing from main; merge this PR directly into main. No migration.

```bash
git fetch origin
git switch codex/ui3-a-trading-screens-20261110
composer install
npm ci
npm run build
```

Use the normal XAMPP Shop URL, hard reload and sign in. Use test records and both Owner and restricted Staff accounts. Record actual commit/device/browser/expected/actual results; no case below is pre-accepted. For deliberate lost-response/permission/concurrency cases use a controlled test environment.

| Case | Verify | Expected | Owner result |
|---|---|---|---|
| A01 | Native Windows build and Shop entry | Accepted sidebar/header/mobile navigation and brand refinement present; no missing modules/CSP/console errors | Pending |
| A02 | Desktop, 320/360/390px phones, tablet, orientation/keyboard and 200–400% zoom | Readable forms/cards, sensible columns, reachable actions, no routine horizontal task scroll or covered controls | Pending |
| A03 | Keyboard, skip link, fields, accordions, dialogs, stock sheet | Logical focus; real labels; Escape/Cancel safe; one modal at a time; focus returns | Pending |
| A04 | Owner vs Staff permission combinations; read-only/blocked/closed/offline | Actual server gates retained, no unauthorized fields/writes, no offline saved/queued claim | Pending |
| A05 | Customers search/status/paging and failure/empty | Filters retained, results/current states correct, loading/retry errors distinguish no matches | Pending |
| A06 | Add/edit customer with/without private grant | Correct fields; private data not fetched/displayed beyond current authority; no private data overwritten by omission | Pending |
| A07 | Invalid customer fields, server conflict, cancel/back/reload | Linked summary/inline errors, entered values retained, no focus theft while typing; deliberate loss confirmation | Pending |
| A08 | Make customer inactive | Explicit consequence/reason/confirmation; past records retained; no new selection | Pending |
| A09 | Products search/status/low/negative filters and paging | Real list; latest search wins; correct units, quantities and prices | Pending |
| A10 | Product add/edit/similar name and invalid price/unit | Correct identity/unit constraints; useful inline errors; duplicate-name warning; no float amount conversion | Pending |
| A11 | Product details/alert threshold/inactive product | Appropriate grants, low/negative states and history retained; explicit inactive confirmation and reason | Pending |
| A12 | Damaged/expired/owner use/missing/correction add/remove | Existing exact quantity preview and saved server changes; reason and confirmation; no history rewrite | Pending |
| A13 | Stock history/paging and stock-record side sheet | Correct before/after/source/actor/reference; focus/close; unsaved reason kept or explicitly discarded | Pending |
| A14 | Undo eligible/ineligible stock movement | Existing latest-activity eligibility and reason enforced; linked record retained; no nested-modal deadlock | Pending |
| A15 | New starting stock and normal delivery | Correct starting-stock eligibility; reference/notes/time and active-product search; no fake supplier workflow | Pending |
| A16 | Delivery item add/edit/remove/reorder with quantities/costs | Same prices/precision/server facts; explicit remove; unsaved header/item cannot skip review | Pending |
| A17 | Save/resume unfinished delivery | Identity/items/details retained; switching records resets old item editor | Pending |
| A18 | Delivery review/confirm/repeated click/lost response | Explicit checked review; one request; same-ID status check; no duplicate saved stock | Pending |
| A19 | Discard unfinished delivery | Explicit confirmation; stock unchanged | Pending |
| A20 | Saved delivery cancellation/replacement/starting-stock replacement | Eligibility and reasons preserved; original linked records retained; reversal-only Staff grant still works | Pending |
| A21 | New/resume unfinished sale, list/paging/direct hash/back/forward | Correct saved basket identity, no silent new sale from ordinary record open | Pending |
| A22 | Product search/select and price override/no override | Automatic current price; actual unit; current-price changes/inactive choices handled as before | Pending |
| A23 | Add repeated products/edit/remove/order and cancel item | Same server line IDs/order/decimal strings; basket total correct; explicit remove and guarded replace/edit | Pending |
| A24 | Customer/time and quick customer create | Customer optional for full payment; inactive/unpaid selection handled; Save customer/time explicit; device vs UTC time remains labeled | Pending |
| A25 | Cash only, transfer only, mixed, partial and unpaid sale | Server review amounts correct; unpaid requires named customer; optional payment date; no change/advance-wallet logic | Pending |
| A26 | Cash sale before starting cash | Existing prerequisite enforced; plain guidance; Owner-only setup destination | Pending |
| A27 | Validation/excess payment/stale review | Useful summary/inline help; amounts/edits retained; fresh review required; no new calculations | Pending |
| A28 | Confirm/save/double click/lost response/status check | One original identity; permanent receipt only after confirmed result; unknown warning persists and Check saved status works | Pending |
| A29 | Internal Back/Reload/Edit/linked navigation with unsaved values | Keep editing retains input; Leave acts only after dialog closes; unknown/in-flight state cannot be bypassed | Pending |
| A30 | Discard sale | Reason and explicit confirmation; no money/debt/stock created | Pending |
| A31 | Sales history date/search/type/paging incl unfinished returns | Correct server filtering and direct detail links; no machine-enum jargon in labels | Pending |
| A32 | Sale/return/cancellation receipts; Owner/private profit gate | Correct historical product/amount/time/reference evidence; cost-not-recorded profit marked estimate; unauthorized costs absent | Pending |
| A33 | Print/reprint from Owner and restricted Staff, both pilot paper sizes | Customer copy excludes costs/profit/internal stock and controls; quantities/payments/reference retained; readable pages | Pending |
| A34 | Return draft from original sale, quantities incl inactive product/repeated returns | Original prices/items and remaining quantity limits retained; never returns another sale's items | Pending |
| A35 | Return calculation: unpaid-only, refund-only, mixed reduction/refund | Original unpaid amount reduced first; actual server cash/transfer limits; no floating-point recomputation | Pending |
| A36 | Return edit after review/reason/discard/post/unknown | Review invalidated where required; reason and permanent confirmation; values retained; discard causes no financial/stock effects; same-ID recovery | Pending |
| A37 | Cancel sale/return with dependency denial | Money/unpaid/stock changes explained; reason/fingerprint/checkbox; dependency denied safely; no history deletion | Pending |
| A38 | Replacement and original/return/cancellation links | Correct linked identities; no duplicate replacement or cancellation of cancellation; incomplete local edits guarded | Pending |
| A39 | Success timeout, error/unknown persistence, support details | Safe success closes after ~9s and pauses on interaction; critical results/warnings remain; no raw exception/credential disclosure | Pending |
| A40 | Accepted Phase 4 regression + coherent release/rollback | Same stock/payment/debt/audit outcomes; full markup/modules/CSS/icons together; saved baskets/receipts survive UI rollback | Pending |

Report defects and actual outcomes. Original UI2/brand acceptance is recorded separately. UI3-A acceptance and UI3-B/UI4 programme closure will be recorded only after confirmation.
