# Navigation and permission reference

The [29-entry register](navigation-map.csv) retains original mappings. Current authority source is `app/Controllers/Api/ShopController.php`, not a display label or permission-enum name.

| Current capability | Existing authority |
|---|---|
| manage_staff / manage_shop_settings / manage_branding / manage_domains | PRIMARY_OWNER |
| view_subscription / manage_opening_cash / view_audit | PRIMARY_OWNER |
| view_customers | customer.view |
| view_sale_drafts | sale.view |
| view_inventory | inventory.view |
| manage_products / adjust_inventory / manage_intakes / reverse_inventory | product.manage / inventory.adjust / inventory.intake / inventory.reverse |

Product/customer editing, price override, cost/profit viewing, returns and sale reversal retain action-specific grants. `staff.manage` existing in an enum does not broaden the Owner-only controller. Current `intakes` read navigation uses `view_inventory`; writing requires `manage_intakes`. The original catalogue's Add stock shorthand is not a read gate.

Implemented C registry: ID, approved label/copy key, semantic icon, parent, bottom group, actual capability, actual access policy, current target. One registry drives sidebar, bottom bar, drawer and current-page heading. No Vue Router. Preserve sale-draft/sale/history hashes, records, refresh and Back/Forward behavior.

Phone: Home / Sell / Stock / Customers / Menu. Omit unauthorized destinations; never show a dead target. Sales/returns/settings use Menu as current group. Stock deliveries/starting stock stay under Stock. Desktop: work destinations plus authorized Owner links; no duplicated bottom bar. Admin: Shops, Add shop, Plans, Web addresses, Activity under actual platform grants. Plans is existing subscription operations, not a new feature. Customers are records, not an unsupported portal.

Current BLOCKED/CLOSED navigation in Shop limits to home/activity; preserve actual policy when C integrates it. Other server access modes and refreshed denials must be handled explicitly. Navigation visibility never replaces backend authorization or permits fetching hidden customer/cost data.

C now integrates the registry into real Shop/Admin shells. Every current destination remains reachable with its actual authority; use workspace-shells.md and the C checklist. Full task-body migration/copy remains UI-3. Design samples never override server rules.

The server embeds Admin permission hints using the same Shield user.can() checks as current platform filters. Client metadata is display filtering only; all APIs/MFA still revalidate. Shop context refresh on returning focus/visibility can remove an unavailable current page without broadening any access mode.

## Phase 5 WP3 contextual routes

Within Customers, `#customers/debt/{customer}/payment/{payment}/reverse` and `/correct` require reverse; corrected replacement additionally record, and allocate for selected bills. `#customers/debt/{customer}/bill/{bill}/adjust` requires adjust; `/changes` is read-only history. `#customers/debt/{customer}/adjustment/{change}` reads a saved change; `/reverse` requires adjust and an eligible non-Sales original. Every path independently requires customer.view + debt.view on server. No new sidebar/bottom item or hidden blanket debt.manage authority. READ_ONLY/offline disables saves; saved status reads remain available when authorized and online. Shared shell/navigation guard owns unknown-result and unsaved-form transitions.

## Phase 5 WP4 contextual statement

`#customers/debt/{customer}/statement` is within Customers; View customer statement appears on Customer bills and bill detail. customer.view + debt.view independently enforced on server. Read-only date/page/Print utilities, no new bottom/sidebar item, no collect/reverse/adjust authority. Global money-recovery navigation guards continue to block leaving unresolved commands; statement reads create no pending-money marker. Page tokens are not stored in URLs/localStorage/sessionStorage or used as authentication.

## Phase 6 WP1 — 13 November 2026

Phase 6 WP1: Money movements is a single sidebar/Menu Work destination, shown by view_financial_movements derived from financial.view. Expense entry additionally requires record_expenses, financial.record and FULL current access. Hash routes share that one destination; direct hashes never confer authority. READ_ONLY retains authorized history/detail; unknown-save status lookup remains read-only. Four/five existing bottom destinations are unchanged. Correct/reverse permissions register for later packages without buttons or endpoints.
