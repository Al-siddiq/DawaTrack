# Phase 4 WP2 implementation contract

CustomerDebt owns Customer lifecycle/query services; Sales owns online draft services/queries and strict input parsing. `CommercialAccess` coordinates current authority and transactions. Controllers parse objects, derive ShopContext and return the standard safe envelope, never perform business SQL. Inventory remains sole owner of movements, positions, cost state and version effects. Existing error/CSRF/session protections apply; private no-store responses are used throughout.

## Permissions

| Operation | Required Staff grants | Data/effect |
|---|---|---|
| Customer list/detail | customer.view | Tenant-scoped name/status/reference/timestamps |
| Customer create/edit/deactivate | customer.view + customer.manage | FULL access; expected version for edits/deactivation; reason on deactivate |
| Customer phone/notes read, search by phone or write | customer.private in addition to applicable view/manage | Without it these fields are not selected or returned; omitted edits preserve them |
| Sale draft list/detail | sale.view | Current Shop; no cost/COGS/private contact data |
| Draft create/edit/items/discard/preview and lookups | sale.view + sale.record | Writes require FULL; UI disables writes/preview in read-only/offline states |
| Selling price different from current Product price | sale.price_override in addition to draft grants | Revalidated server-side, including saved lines at preview |
| Basket quick-create Customer | Draft grants + customer.view + customer.manage | Name-only; save assignment explicitly |

Active Primary Owners derive known-code authority. Staff have no implied grants; `customer.private` alone cannot list Customers. Draft Customer lookup intentionally exposes only active identity/name with sale grants, so cashiers can identify credit Customers without accessing contact/notes or Customer management. No cost/gross-profit authorization is introduced for draft placeholders. READ_ONLY allows safe reads; BLOCKED/CLOSED deny commercial resources. API preview is a read-only calculation authorized by both sale grants, despite using POST; UI conservatively disables it in READ_ONLY. Fresh membership/entitlement is rechecked inside mutation transactions. A grant/revocation applies to following server checks even if the UI is stale.

## API

Routes below `/api/v1/shop`; all require authenticated current Shop context. UUIDs are v4, not internal IDs. Pagination uses `page`/`per_page` bounded to 50. Exact quantities, prices and money are JSON strings, never floats. Inputs use an explicit allowlist and reject tenant/shop, totals, status, posted/cost/payment snapshots or other protected fields.

| Method | Route | Input / result |
|---|---|---|
| GET | /customers | search, status ACTIVE/INACTIVE/ALL; items + meta.pagination/private_visible |
| POST | /customers | public_id, display_name; optional phone/notes with private grant |
| GET | /customers/{uuid} | Safe detail with private_visible |
| PUT | /customers/{uuid} | expected_updated_at; display_name/phone/notes; omitted private fields preserved |
| POST | /customers/{uuid}/deactivate | expected_updated_at, reason |
| GET | /sale-drafts | DRAFT SALE page + meta.pagination |
| POST | /sale-drafts | permanent public_id; optional customer_public_id, occurred_at, device_local_offset_minutes |
| GET | /sale-drafts/{uuid} | Safe saved header/items; estimate_only=true, posting_available=false |
| PUT | /sale-drafts/{uuid} | expected_updated_at; optional customer_public_id (null clears), occurred_at, device_local_offset_minutes |
| POST | /sale-drafts/{uuid}/items | expected_updated_at, permanent public_id, product_public_id, quantity; optional unit_selling_price, integer line_number |
| PUT | /sale-drafts/{uuid}/items/{item} | expected_updated_at, product_public_id, quantity; optional price/line_number; UI preserves line number |
| DELETE | /sale-drafts/{uuid}/items/{item} | JSON expected_updated_at |
| DELETE | /sale-drafts/{uuid} | JSON expected_updated_at, reason; deletes only draft children/header; returns empty data |
| POST | /sale-drafts/{uuid}/preview | expected_updated_at; cash_amount/transfer_amount default zero, optional due_date; exact estimate, credit, version, preparation_persisted=false |
| GET | /sale-draft-lookups/products | search by name; max 50 active available Products, unit/current price only |
| GET | /sale-draft-lookups/customers | search by name; max 50 active Tenant identities, no private details |

`occurred_at` is exact `YYYY-MM-DD HH:mm:ss.ffffff` UTC; device offset is optional integer -840..840. Due date is optional valid `YYYY-MM-DD`, no timezone conversion, year 1000..9999. Quantity allows four decimals and must be positive; prices allow two decimals and zero. Each line uses CommercialDecimal MySQL exact ROUND to kobo, header equals SUM of rounded lines and rejects DECIMAL(19,2) overflow. Draft costs are hidden zeros, not known acquisition cost. Preview does not require Opening Cash because it causes no cash effect; WP3 must enforce the guard when actual CASH is posted.

Error codes include VALIDATION_FAILED (422), DRAFT_STALE (409), CUSTOMER_ID_CONFLICT/CUSTOMER_UNCHANGED/CUSTOMER_INACTIVE (409), CUSTOMER_NOT_FOUND/PRODUCT_NOT_FOUND/SALE_DRAFT_NOT_FOUND/SALE_ITEM_NOT_FOUND (scoped 404), PRODUCT_INACTIVE/SALE_ALREADY_POSTED/COMMERCIAL_CONFLICT (409), SALE_PRICE_PERMISSION_REQUIRED/PERMISSION_DENIED and lifecycle/access denial (403), SALE_DRAFT_EMPTY/PAYMENT_EXCEEDS_TOTAL/CUSTOMER_REQUIRED (422). Unknown failures return safe REQUEST_FAILED; database detail/contact payloads are not logged. After timeout/409 reload authoritative state. Create retry preserves its UUID; no automatic duplicate basket creation occurs.

Customer and draft queries return names as escaped Vue text, never raw markup. Phone normalization strips common separators, accepts optional + and 7–15 digits, preserves legitimate shared/missing numbers. No phone uniqueness or stored balance is introduced. Deactivation does not rewrite draft/history references; future posting must revalidate active references even after successful preview.

## Evidence and later integration

Customer Audit actions `customer.created/updated/deactivated`, category DEBT, resource customer, and Tenant-scoped customer change publication are atomic with the mutation. A Customer aggregate refresh uses the same scoped GET APIs and current privacy permission. No transport/outbox/device feature is enabled by this resource. Draft discard adds SALE-category `sale.draft_discarded`, resource sale, reason, line count and estimated total; it emits no sync change. Drafts have no authoritative payment, Receivable, adjustment, stock, reconciliation or Sales change effects.

For WP3: lock/recheck the same SALE/DRAFT header before posting, revalidate current inputs/authority, replace cost placeholders through Inventory, persist payment/credit/date evidence only within the complete transaction, and publish only after all domain effects/Audit. For WP4: retain the recorded compensation schema decision gate before dependent Return/reversal implementation. Existing Phase 3 diagnostics continue to apply to existing evidence; no new movement is created in WP2.
