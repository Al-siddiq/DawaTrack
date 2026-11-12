# Protected resource API convention

Drafts may use explicitly approved edit/discard operations. Mutable master/configuration resources use focused owning-service updates and Audit important changes.

Posted or finalized evidence never receives generic CRUD mutation routes. A future correction is an action-oriented endpoint owned by the relevant domain, for example `POST /.../{public_id}/reverse`. The service must authorize, lock, validate, append domain compensation, attach the approved source/reversal relationship, append causal Audit, and commit atomically. The original is never overwritten or deleted.

Route shape does not supply authority. Tenant scope is server-derived, Platform authority stays separate, and resource UUIDs are not authorization.

Repeated commands return existing domain-specific `409` codes or the established `CONFLICT` envelope. DawaTrack does not promise generic response replay before Phase 10 and does not accept an unapproved generic `idempotency_key`.

## Phase 4 WP2

[Customer and Sale draft contract](../modules/phase-04-wp2.md): Customer masters have focused create/update/deactivate commands, safe private-field masking and atomic Audit/change publication. Every draft write locks the normal Sale parent, proves DRAFT and compares the expected version. Draft removal/discard cannot modify POSTED evidence. Payment preview is read-only/transient; actual Sale posting/payments/credit remain WP3. No generic posted Sales CRUD or payment/Receivable writes exist.

## Phase 4 WP3

POST `/api/v1/shop/sales/{uuid}/post` is the explicit ordinary forward DRAFT→POSTED command. GET the same Sale resource for authoritative state/receipt after an uncertain result; existing POSTED retries produce no duplicate effects. No generic posted PATCH/PUT/DELETE is exposed. Actual payments are immutable receipt children; original credit is read through `/sale-credit/{uuid}`. Direct Inventory manual reversal cannot independently compensate Sale/Return stock. Full API, scope/permission and error/retry semantics: [WP3 contract](../modules/phase-04-wp3.md).


## WP4-B — 2026-11-05

WP4-B adds authenticated Return draft/list/detail/eligibility/estimate/preview/post/discard routes with current scope and CSRF. See [route/body/error contract](../modules/phase-04-wp4-b.md). No reversal/replacement command is exposed in B.


## WP4-C — 2026-11-06

WP4-C adds five authenticated correction preview/post/replacement/state/compensation-detail routes. Both POST kinds require sale.view + sale.reverse; ordinary replacement posting keeps independent sale.record or sale.return authority. Explicit compensation reads avoid masquerading as new Sale credit. See [C contract](../modules/phase-04-wp4-c.md).


## WP4-D — 2026-11-06

GET /api/v1/shop/sales supports ALL/SALE/RETURN/COMPENSATION/DRAFT_RETURN record filters; GET /sales/{uuid} normalizes supported Sale/Return/compensation/draft detail. Data list + meta.pagination remains stable. B/C explicit commands and reads remain supported. Original Return choices require sale.return; safe readers receive saved lines only. See [D read contract](../modules/phase-04-wp4-d.md).

## UI2-C context presentation extension

The existing authenticated/scoped `GET /api/v1/shop/context` response additionally includes `current_user: { display_name: string }`. This is the currently authenticated user’s display name only, for refreshed workspace identity presentation. Existing role/capability/access fields and all service authorization are unchanged. No password, token, email, new user lookup or new mutation endpoint is exposed. Clients may continue ignoring this additive field.


## Phase 5 WP1 debt reads

Authenticated current-Shop `GET /api/v1/shop/debts`, `GET /api/v1/shop/customers/{uuid}/debt` and `GET /api/v1/shop/debt-bills/{uuid}` require both customer.view and debt.view, independently of legacy debt.manage. Standard private/no-store envelope and current scope/access checks apply. No WP1 debt mutation route exists. See [request/response/error contract](../Phase-5/Phase_5_WP1_Integration_Contracts.md#4-implemented-read-api).


## Phase 5 WP2 payment API

POST /api/v1/shop/debt-payments/preview and /debt-payments require customer.view + debt.view + debt.payment.record (plus debt.payment.allocate for explicit mode). GET /debt-payments/{uuid} and /customers/{uuid}/debt-payments require both view grants. Authenticated hostname/membership/CSRF and private/no-store conventions remain. No undo/adjust/statement writer is exposed. [Payloads, errors and recovery](../Phase-5/Phase_5_WP2_Integration_Contracts.md).

## Phase 5 WP3 protected correction resources

See [method/body/authority table](../Phase-5/Phase_5_WP3_Integration_Contracts.md). Ten new routes under `/api/v1/shop`: payment reversal-preview/reverse/correction-preview/correct; bill adjustment-preview/adjustments POST; adjustment reversal-preview/reverse; adjustment GET and bill adjustments GET. Read grants are customer.view + debt.view. Reverse requires debt.payment.reverse; atomic replacement also debt.payment.record and explicit allocation grant independently; bill adjustment/inverse requires debt.adjust. FULL/membership/CSRF apply to writes, including previews. No blanket debt.manage bypass. Strict UUID/reason/decimal/state fingerprint;409 for stale/conflict/dependency,422 invalid input,403 denied,404 absent scoped status. No new generic mutation/statement endpoint. Unknown outcome status reads remain available to authorized READ_ONLY users.

## Phase 5 WP4 statement resource

GET `/api/v1/shop/customers/{uuid}/statement`: customer.view + debt.view/current Shop context. Optional Shop-calendar from_date/to_date, per_page1–50(default20), opaque cursor. READ_ONLY reads; no POST or implicit sale/private/correction grant. Fresh read-only snapshot; opening/range/current totals and exact running page values. Authenticated encrypted page/checkpoint tokens bind scope/user/range/size/timezone/version; changes/expiry return STATEMENT_REFRESH_REQUIRED409, invalid/tampered token422, missing private key/OpenSSL STATEMENT_SETUP_REQUIRED503. Cursor grants no authority. No internal IDs/cost/private notes/Audit hashes/reasons/secret in public DTO. [Complete body/ordering/secret/privacy contract](../Phase-5/Phase_5_WP4_Integration_Contracts.md).
