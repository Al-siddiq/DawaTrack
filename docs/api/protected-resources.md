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
