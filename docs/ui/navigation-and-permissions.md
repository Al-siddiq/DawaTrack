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

Planned C registry: ID, approved label/copy key, semantic icon, parent, bottom group, actual capability, actual access policy, current target. One registry drives sidebar, bottom bar, drawer and current-page heading. No Vue Router. Preserve sale-draft/sale/history hashes, records, refresh and Back/Forward behavior.

Phone: Home / Sell / Stock / Customers / Menu. Omit unauthorized destinations; never show a dead target. Sales/returns/settings use Menu as current group. Stock deliveries/starting stock stay under Stock. Desktop: work destinations plus authorized Owner links; no duplicated bottom bar. Admin: Shops, Add shop, Plans, Web addresses, Activity under actual platform grants. Plans is existing subscription operations, not a new feature. Customers are records, not an unsupported portal.

Current BLOCKED/CLOSED navigation in Shop limits to home/activity; preserve actual policy when C integrates it. Other server access modes and refreshed denials must be handled explicitly. Navigation visibility never replaces backend authorization or permits fetching hidden customer/cost data.

No production navigation changes in A. Existing routes remain reachable as before. Register status/evidence is intentionally pending C and later migration. Design sample access states illustrate the pattern and cannot override server rules.
