# UI-1 design specification

## Decision and scope

Use one design system and one Shop layout for Owner and Staff. Show the tasks permitted for the signed-in person. Keep the Admin workspace and session separate, while reusing typography, controls and error patterns. Do not create a Customer workspace: Customers are records in the current system, not an implemented login category.

This combines the consistency and lower maintenance cost of a shared template with the separation required for Admin. Four independently styled templates would duplicate components, cause wording drift and make Staff training harder. A single undifferentiated workspace would obscure the platform/Shop boundary.

Baseline reviewed: main commit `09789e8aab48b5d62b546ecf538e491867b5fedf`. The Owner's Phase 4 acceptance in the conversation is retained. This deliverable covers the approved design direction before the UI-2 shell implementation. It does not add Phase 5 collections/statements, reports, daily closing, wallets, interest, public customer accounts or offline posting.

## Navigation map

Phone: **Home / Sell / Stock / Customers / Menu**. Five labeled touch targets stay above the device safe area. Hide unauthorized destinations and distribute remaining items evenly; do not show dead controls. Sales, returns, account and Owner settings are in Menu. Keep Menu active while viewing Sales or returns. Stock deliveries and starting stock live inside Stock. Selling keeps its position throughout products, payment and review.

Desktop: persistent Shop logo/name and sidebar; current user/account controls at the bottom. Work links: Home, Sell, Stock, Customers, Sales. Owner links: Staff, Shop settings, Activity. Shop settings groups shop details, starting cash, appearance, web address and plan; these retain their individual current permission gates. There is no duplicate desktop bottom bar. Intermediate/tablet widths use the drawer when sidebar width would squeeze the task area.

Header: shop identity and current page/task. Add Back/Close only where their meaning is clear. A drawer on phones opens from Menu and restores focus to its trigger when closed. It contains the same allowed destinations; no separate navigation truth. Use a current-page heading and selected navigation state, not color alone. A breadcrumb is useful for Sales → Sale details → Return, but not required on every shallow page.

Sell opens a clearly identified unfinished sale or offers a list of saved baskets and New sale. Never silently choose among multiple baskets. Save-for-later and page navigation preserve saved identity. Browser Back must not lose edits without an explanation. The current online requirement is visible whenever it blocks an action.

Admin: Shops, Add shop, Plans, Web addresses, Activity. Plans is a view over existing subscription operations, not a new plan-catalogue service. Owner information stays inside existing shop/onboarding context; there is no invented Owners management root. Admin cannot operate a shop by changing a visible role selector.

See `navigation-map.csv` for all 29 destination/action mappings. Screen examples are selected states, not a promise that every permission combination has the same controls.

## Permissions and authority

| Task | Visibility/action rule |
|---|---|
| Home | Current authenticated Shop member; shortcuts inherit destination gates |
| Stock | `inventory.view`; product edits, intakes, adjustments and reversals require their current separate grants |
| Customers | `customer.view`; edits require `customer.manage`; private fields require `customer.private` |
| Sell / Sales | `sale.view`; saving requires `sale.record`; price changes require `sale.price_override` |
| Cost and profit | `sale.profit.view`; never leak hidden costs through receipts, tooltips or summaries |
| Return / cancel saved sale | Existing `sale.return` / `sale.reverse` authority and server conditions |
| Starting cash, Staff, shop details, appearance, domains, plan, activity | Current controller Owner-only gates remain authoritative; do not broaden them from enum names |
| Admin | Existing platform permission and separate Admin session/MFA |

A read-only user can view allowed records but cannot make writes. Do not show a disabled main action with no explanation. Where lack of setup or plan status blocks an otherwise authorized task, explain the specific next step. Existing server responses remain authoritative if permissions change while a screen is open.

## Content and wording

Use short, direct English and the name of the thing the user sees. “Save sale” is the permanent action, “Review sale” is preparation, “Save for later” keeps an unfinished basket. “Still owed” is a result of the sale total minus valid recorded payments, not a third payment method. “Paid by transfer” does not imply a bank integration or verification service.

Keep essential accounting distinctions even when labels become simpler. A return value, reduction in amount owed and money refunded must be separate rows. “Cancel saved sale” means the existing full compensating reversal, not deleting history; show the reason and exact consequences. A corrected replacement is a separate new sale, never an edit of the saved original. Use specific server reasons instead of a generic “Something went wrong”.

Money uses the existing currency and precision. Quantity uses the product's single base unit and supported precision; do not add cartons-to-packs conversion. Dates show local shop/device meaning explicitly; preserve the API's authoritative timestamps and business-time rules. Do not relabel an initial cash baseline as a daily opening or closing routine. Unknown costs stay clearly provisional where authorized; never turn unknown into zero.

The 96-entry catalogue provides proposed labels, messages and usage constraints. Internal codes remain in the reference catalogue only. Do not put UUIDs, “payload”, “posted”, “orchestration”, “HTTP”, or “idempotency” in routine product instructions. A stable short reference may accompany a failure for support without removing the underlying identity.

Sign-in remains the email/password flow in the current view. “Get help signing in” must expose only the recovery mechanism actually configured; it does not promise an unimplemented password reset. Admin MFA remains mandatory where currently enforced.

## Representative screen inventory

| ID | Intended review |
|---|---|
| desktop-home | Task-based Owner home; starting-cash setup state, saved basket |
| desktop-sell | Product search beside basket; clear next step |
| desktop-stock | Recorded units, prices, delivery/starting-stock entry |
| desktop-admin | Separate platform navigation and Shop access |
| mobile-home | Compact shortcuts; cash already ready |
| mobile-menu | Owner destinations and account |
| mobile-staff | Same shell, example Staff permissions, no Owner settings |
| mobile-sell | Basket with visible total and edit/remove |
| mobile-payment | Cash and transfer amounts; customer required for remaining debt |
| mobile-invalid | Correctable overpayment; basket preserved |
| mobile-return | Returnable quantity and reason |
| mobile-return-review | Exact refund, debt and stock consequences |
| mobile-return-receipt | Saved evidence and print entry |
| mobile-status | Uncertain save; check status before another submission |
| mobile-read-only | Specific plan restriction and valid next step |
| mobile-customers | Customer lookup and permitted details |
| mobile-sale-review | Final payment/debt review and explicit Save sale |
| mobile-sign-in | Branded email sign-in and help |

Selling example: 3 packs at ₦500 plus 3 bottles at ₦2,000 = ₦7,500. ₦4,500 cash leaves ₦3,000 owed by the selected customer. The return example is a separate sale: original total ₦20,000, cash ₦5,000, transfer ₦5,000, debt ₦10,000. Returning ₦12,000 reduces debt by ₦10,000 and returns ₦2,000 cash, subject to existing refund constraints. Example prices are not product defaults. Product selection must use current validated prices and stock rules.

## Layout and component rules

Use a restrained green primary color, neutral backgrounds, normal-weight readable body text, and one strong action per task section. Body text is normally 16 px; helper text 14 px, headings 22–28 px. Bottom labels may be 11–12 px if they remain legible. Use spacing 4/8/12/16/24 px, minimum 44 px touch targets, visible keyboard focus, semantic form labels and text plus icons for status. Do not use bright branding colors for every heading or rely on red/green alone. Approved shop branding fills safe brand regions; accessible action/status colors remain legible.

Phone content scrolls between the header and bottom navigation. Reserve their real heights, including safe areas and any keyboard. Sticky totals belong inside the task frame; they must not cover a field, error or action, as the old estimate banner did. Keyboard focus must be scrolled into view. At wide widths, use two columns only where it helps the current task. Tables become labeled rows/cards on phones. No routine horizontal scrolling; the review catalogue's wide reference table is not a production pattern.

| Utility | Use and behavior |
|---|---|
| Contextual sheet | Product/customer choice and short line edits; preserve parent state; labeled close; return focus |
| Modal | Short final confirmation for cancellation/destructive actions; exact consequences and reason; trap focus, Escape where safe |
| Full page | Sale, payment, return review and lengthy forms; avoid nested modals |
| Accordion | Optional extra details/history; essential totals, errors and next step stay expanded |
| Dropdown | Secondary actions; primary action stays visible; keyboard accessible |
| Information panel | What changes next, blocking setup, refund/debt split and provisional costs |
| Success alert | Close button; dismiss after about 8–10 seconds; pause on hover/focus; saved result remains on page |
| Error/warning | Persistent until fixed or acknowledged; inline field help and linked error summary; retain values |

Dialogs and sheets require a heading, visible Close/Cancel, proper focus management and screen-reader announcements. They must not create additional history entries that lose saved sale identity. Canceling a sheet returns to the unchanged basket. Discarding an entire basket is a separate explicit action with the existing reason requirement.

## Saving and recovery states

Before a permanent action, show total, customer, payment composition, remaining debt and affected stock. Where the existing API previews/staleness contract applies, refresh and require review if facts change. The final save control becomes busy and prevents duplicate taps, but the server owns deduplication. Do not create new record IDs just because a request timed out.

A network failure before preparation shows “Connect to continue”; it never simulates an offline success. An uncertain save is a different persistent state: preserve basket and operation identity, check saved status, then show saved evidence or the server-authorized next step. Browser refresh/back must recover from the same identity. Do not clear a basket merely because the response was lost.

Validation points to the field, repeats the attempted value and gives a specific correction. When several fields fail, show a summary linked to those fields. Invalid input and expired permissions never erase valid edits. For an inactive product/customer or changed stock, explain what changed and let the user amend the basket; do not silently substitute another record.

Receipts show only allowed customer and transaction information. Print hides app navigation and must not expose internal cost/margin data. Reprint and history links must preserve references. Do not claim refund or payment has happened until the server confirms it.

## Foundation alignment and implementation boundary

Primary sources reviewed:

- `docs/DawaTrack_Technical_Blueprint.md` — application boundaries, Vue composition, self-hosted assets and authority.
- `docs/DawaTrack_Strategic_Phased_Implementation_Roadmap.md` — usability, phase scope and manual acceptance.
- `docs/DawaTrack_Database_Schema_Design.md` and Phase 4 contracts/docs — immutable evidence and financial/stock invariants.
- `app/Config/Routes.php`, `app/Controllers/ShopController.php`, current permission enum, auth views and current Vue views — real destinations and gates.

Retain Vue 3 Composition API, precompiled Bootstrap and existing self-hosted asset workflow. This design does not mandate Router, Pinia, TypeScript, a JavaScript bundler or an external UI dependency. It is not a new schema/API contract. Where older blueprint wording differs from implemented accepted behavior (for example email login), this package preserves current behavior and records the difference instead of inventing a new authentication flow.

UI-2 implements layout, permitted navigation and shared components. UI-3 migrates existing task screens and wording without changing financial contracts. UI-4 is the Owner's manual verification and acceptance. A visual design book cannot establish HTTP, concurrency, browser or accessibility acceptance.

## Manual design-review checklist

1. Open `review.html` and inspect all 18 scenes; inspect the PDF at readable zoom.
2. Confirm the Owner can identify shop, current page and next action on every scene.
3. Confirm the same layout makes sense for Staff with fewer grants.
4. Confirm Home/Sell/Stock/Customers/Menu match daily work; validate that Sales belongs in Menu on phones.
5. Confirm Admin looks related but clearly separate; no shop operating controls appear there.
6. Read the 96 wording entries and mark anything a general trader would not understand.
7. Follow basket → payment; check that “Still owed” is understood and a customer is required when appropriate.
8. Follow return quantity → review → receipt; check refund versus debt reduction is obvious.
9. Inspect invalid input and uncertain-save scenes; identify the corrective action without outside instruction.
10. Confirm starting cash is understood as the existing baseline, not a new daily process.
11. Confirm utility rules do not hide essential totals, reasons or errors.
12. Record design changes or acceptance before UI-2. Browser keyboard, zoom, screen-reader, actual phone and integration checks belong to UI-4 after implementation.
