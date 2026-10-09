# Phase 3 WP1 — Product Catalog and Stock Projection Foundation

Product creation atomically creates one zero Stock Position for the server-resolved current Shop, appends Product Audit, and publishes Product/position changes. Duplicate names are allowed. Normalization trims, collapses whitespace and case-folds only for search/advisory.

Name and selling price are mutable. Unit is mutable only while no `stock_movements` row exists for the Product; the service is forward-compatible by consulting that table when WP2 creates it. Products deactivate without deletion or stock mutation. Threshold is the only independently mutable position field and increments version. Expected quantity, average cost, cost state and last movement have no WP1 mutation command.

UI surfaces are Product/Stock list, create/edit, combined detail/threshold, and reasoned deactivation. No Intake, movement, history or future action is advertised.
