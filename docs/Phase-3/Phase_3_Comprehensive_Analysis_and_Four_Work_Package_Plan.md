# Phase 3 — Comprehensive Analysis and Four-Work-Package Delivery Plan

**Prepared:** 2026-10-06  
**Status:** implementation plan only; no Phase 3 application or schema implementation is included  
**Authority used:** Business & Product Blueprint → Technical Blueprint → Database / Schema Design v1.1 → Strategic Phased Implementation Roadmap v1.1 → current repository

## A. Executive assessment

DawaTrack is architecturally ready to begin Phase 3, subject to four **pre-implementation corrections/gates** that should be completed in Work Package (WP) 1 rather than by inventing a parallel foundation:

1. Extend the existing closed permission, Audit action/resource, and `ChangeSequenceService` resource registries before the first Product write. The present registries intentionally reject the Phase 3 vocabulary.
2. Produce the five v1.1 tables as ordered CI4 migrations **and** matching raw SQL. No Phase 3 schema exists in the repository today.
3. Decide and document the exact Intake correction aggregate described under WP3. The frozen schema supports reversal/replacement links, but the prose does not determine whether an Intake reversal is represented by a linked compensating `OPENING`/`RECEIPT` Intake or by an explicit reversal-shaped Intake whose public `intake_kind` still has to satisfy the two-value check. The recommended solution is a linked compensating Intake created only by the reversal service, with the original kind, opposite `REVERSAL` movements, and an optional separately linked replacement; ordinary draft UI may never create negative Intake items.
4. Add a dated Phase 2 runtime-verification addendum. The owner reports Phase 2 delivered and tested, while the checked-in 2026-10-04/05 delivery reports and manual records still say Composer, MySQL, HTTP and browser acceptance was blocked. That is documentation debt, not evidence that the current owner statement is false. Do not rewrite the historical reports; append the environment, commands, results and evidence references that established later acceptance.

No prerequisite calls for a new framework, event bus, generic repository layer, generic idempotency system, or offline subsystem. The current modular monolith, server session, Shop context, typed Audit boundary and internal change-sequence boundary are suitable.

**Readiness conclusion:** ready to start WP1 after review of the three implementation decisions in this plan. Phase 4 must remain blocked until all four packages pass their runtime, concurrency, rollback, isolation and responsive acceptance gates.

## B. Current codebase / database state

### B.1 Application and frontend architecture actually present

* The application is a CodeIgniter 4.7 modular monolith on PHP 8.2 with Shield 1.4.1. Explicit routes live in `app/Config/Routes.php`; auto-routing is disabled. API controllers map transport and services own transactions/business rules.
* Business boundaries currently exist under `app/Modules/{Access,Audit,BrandingDomain,Identity,Subscription,Sync,TenantShop}`. There is no Product Catalog or Inventory module yet and no generic repository base to imitate.
* API responses use `ApiResponse`: `{success,data,meta.request_id,error}` with stable codes and field-detail arrays. `ApiException` is the safe domain-to-HTTP carrier. Future collection pagination is documented as `meta.pagination` with capped `page`, `per_page`, `total`, and `total_pages`; Audit alone uses a keyset cursor.
* Authentication is Shield session based. Global CSRF protects writes. `AuthenticatedFilter`, session-security, request-correlation and security-header filters are already applied. Platform Admin has a distinct group/MFA/filter boundary and must not be used for normal Shop inventory.
* The Shop UI is a Vue 3 progressive mount inside the server-rendered shell, using self-hosted Vue, compiled Bootstrap/Sass, native ES modules and native Fetch. Editable sources are in `resources`; matching generated assets are checked into `public/assets`. There is no bundler and Dexie is present but deliberately unused.
* `resources/js/shop/app.js` owns page state/composition, `render.js` supplies the current render tree, `phase-one.js` wraps APIs, and `http-client.js` provides credentials, CSRF refresh, canonical request IDs, timeout/network normalization and session/MFA redirects. `useReasonDialog` supplies focus entry/return, focus trapping, Escape behavior, pending-state protection and retained reason on recoverable failure. Audit uses responsive cards rather than compressed tables.
* The existing shell already distinguishes `FULL`, `READ_ONLY`, `BLOCKED` and `CLOSED`, hides unavailable navigation, disables mutation in read-only mode, and exposes loading/error/empty or focused workflow states. Phase 3 should add modules/composables/components rather than grow one monolithic controller/render file indefinitely, while retaining this shell and API client.

### B.2 Identity, tenancy, authorization and lifecycle

* `ShopContext::resolve()` derives the Shop from a verified active hostname and derives Tenant membership from the authenticated user. A request Tenant/Shop UUID is never authority. Missing/foreign context is non-enumerating `404`.
* `AccessGate::evaluate()` returns `CLOSED`, `BLOCKED`, `READ_ONLY` or `FULL`: closed Tenant; suspended Tenant/inactive Shop/incomplete onboarding; expired/no current subscription; or active entitlement respectively. `mutableContext()` currently rejects every non-`FULL` write with `403`.
* `PermissionService::allows()` validates against `PermissionCode`, grants the Primary Owner ordinary Tenant authority by role, and requires an active explicit Staff grant otherwise. Unknown permissions fail closed.
* The current enum already contains `inventory.view` and `inventory.adjust`; it does **not** yet contain separate catalog-management, Intake or Inventory reversal permissions even though the Phase 3 roadmap requires those separations.
* Tenant and Shop lifecycle, subscription, permission and membership checks therefore belong ahead of every Inventory service call. Controllers must never accept internal `tenant_id`, `shop_id`, actor ID or membership ID.

### B.3 Audit foundation inherited

* `AuditService::append(AuditAppendCommand)` is the only writer. The immutable command carries an explicit `AuditActor`, allow-listed action/category/resource, public resource UUID, reason, safe metadata, optional causal Audit ID and bounded occurrence/operation context.
* `AuditAuthorityValidator` revalidates active Tenant membership and exact Shop/Tenant scope. `AuditCausalLinkValidator` prevents cross-scope causal links. `AuditMetadataPolicy` limits type, depth, entries, strings and bytes and rejects credential/request-body/binary-like keys. Unsafe Audit data aborts the owner transaction.
* `AuditReasonPolicy` presently requires a reason for every action except three mutable branding/profile actions. Phase 3 must deliberately classify its new actions rather than bypass this policy.
* Audit correlation is the canonical request UUID from `RequestContext`; supplied occurrence may be contextual but is bounded and does not become authorization or ordering evidence. `actor_device_id` remains null.
* Owner and Platform Audit queries are immutable/redacted and scoped. Inventory must extend the same action/resource vocabulary; it must not create a new audit table, logger or writer.
* Posted evidence has no generic update/delete path. Runtime DB grants are intended to deny Audit UPDATE/DELETE.

### B.4 Phase 2 change-sequence and transaction foundation

* `sync_generations` was created in the Phase 1 platform-root migration and seeded with one active generation. `sync_changes` is the sole Phase 2 schema addition.
* `ChangeSequenceService::publish()` requires an already active transaction, validates an allow-listed resource/public UUID and same-Tenant Shop, locks the sole active generation `FOR UPDATE`, inserts ordered `UPSERT`/`DEACTIVATE` notifications, and uses the request correlation. It stores no payload and exposes no pull endpoint.
* Required lock order is domain/advisory/row locks → business validation/mutation → Audit → active generation lock/change rows → commit. No domain lock may be acquired after change publication begins.
* Existing services instantiate Audit and Change Sequence with the same CI database connection. Failure rolls the entire mutation back. Phase 3 must do the same, ideally via constructor injection so failure tests can substitute a throwing publisher/auditor.
* `RESOURCE_TYPES` currently ends at Phase 1 resources. It must be extended in reviewed increments for `product`, `stock_position`, `stock_intake`, and `stock_movement`.
* There is no device pull, cursor, receipt, browser outbox, durable request replay or event sourcing; none belongs in Phase 3.

### B.5 Database and migration state

* Current application migrations comprise six ordered Phase 1 migrations (platform roots; Tenant/Shop; memberships; branding/domains; subscriptions; Audit) and one Phase 2 `sync_changes` migration. Each has a matching raw SQL file and register entry.
* Migrations execute explicit MySQL SQL because Forge cannot faithfully express all checks/collations/composite constraints. Tables are InnoDB/`utf8mb4_unicode_ci`; UUID/correlation columns are `CHAR(36)` ASCII `ascii_bin`; application keys are `BIGINT UNSIGNED`; timestamps are UTC `DATETIME(6)`.
* Constraint names use `fk_`, `uq_`, `ix_`, `ck_`; composite parent uniques permit same-Tenant and same-Shop FKs; `RESTRICT` preserves history. Normal migrations intentionally avoid `IF NOT EXISTS`, and raw SQL notes warn that MySQL DDL can partially persist after an unrecorded failure.
* No `products`, `stock_positions`, `stock_intakes`, `stock_intake_items`, or `stock_movements` table/migration/raw SQL exists. No checked-in current SQL dump exists either; `builds` is a regular text artifact, not a dump. Consequently the repository proves intended schema history, not the owner database's live `SHOW CREATE TABLE` state.
* `audit_events.actor_device_id` is the documented late FK to Phase 7. Phase 3 has its own late-FK requirements: `stock_movements.sale_item_id` and `reconciliation_variance_id` columns/indexes/check-compatible nullability are created now, but only the `stock_intake_item_id` and self-reversal source FKs can be installed now. The deferred FK constraints are recorded and added parent-first by Phase 4 and Phase 7 respectively.
* `stock_positions.last_movement_id` creates a same-phase cycle. Create positions without that FK, create movements, then add `fk_stock_positions_last_movement` in a later Phase 3 migration. The authoritative movement-to-position FK remains present on movements.

### B.6 Protected command and duplicate-safety conventions

Before Phase 10, retry safety is domain specific:

* caller/server permanent UUIDv4 plus unique constraints for creates;
* row/advisory locks and conditional lifecycle transitions for state changes;
* unique reversal links for one direct reversal;
* deterministic position locking for multi-product effects;
* specific `409` conflicts and no false Audit/change publication;
* browser `busy/pending` controls as ergonomics only.

Phase 3 must not add `idempotency_key`, `DUPLICATE_OPERATION`, a request-results table or `sync_receipts`. A repeated post locks the Intake and sees `POSTED`; it returns `INTAKE_ALREADY_POSTED` without another effect. A repeated reversal locks the original and loses against the unique reversal guard. A repeated create public UUID returns a resource-specific conflict; it does not replay an old response.

## C. Phase 3 architectural interpretation

Phase 3 establishes stock truth on which Sales may later depend:

* `products` is the mutable Tenant catalog. Duplicate normalized names are legal; normalization powers search/warning only. One base unit and current selling price are current master data.
* `stock_positions` is a Shop/Product projection and lock target, not an independently editable balance. Threshold is the sole directly mutable position field; quantity, average cost, basis state, last movement and version change only through an Inventory evidence transaction.
* `stock_movements` is insert-only accountable evidence. Reconstructing signed movement deltas must explain `expected_quantity`; before/after and cost snapshots diagnose the exact transition.
* `stock_intakes`/items preserve Draft → Posted evidence. Opening Stock is an `OPENING` Intake, never a balance setter. Receipt is `RECEIPT`. Posted headers/items cannot be edited/deleted.
* Movement types exposed in this phase are exactly `OPENING`, `STOCK_INTAKE`, `DAMAGED`, `EXPIRED`, `OWNER_WITHDRAWAL`, `MISSING_LOSS`, `CORRECTION`, `REVERSAL`. Later schema constants may reserve `SALE`, `CUSTOMER_RETURN`, `RECONCILIATION_ADJUSTMENT`, but Phase 3 has no command or UI for them.
* Low stock is the query predicate `expected_quantity <= low_stock_threshold`; negative is allowed and highlighted. There is no alert ledger.
* Arithmetic uses canonical decimal strings and database-safe fixed-precision operations. Quantity is `(19,4)`, unit/average cost `(19,6)`, line amount `(19,2)`, and WAC intermediates `(38,12)`. PHP/JS floats may format display only, never decide authoritative stored values.

### C.1 Required WAC transition function

For a locked position `(Q, A, state)` and positive Intake `(I, C)`:

1. If `Q > 0`, calculate `newA = round6(((decimal38(Q) * A) + (decimal38(I) * C)) / (Q + I))`; state becomes `VALID`.
2. If `Q <= 0` and `Q + I <= 0`, set quantity to `Q + I`, retain `A`, and retain `UNESTABLISHED` only if no valid basis has ever existed; otherwise use `RETAINED`.
3. If `Q <= 0` and `Q + I > 0`, reset `newA = C` (not a negative-inventory blend) and set `VALID`.
4. Outgoing movements snapshot and retain `A`; they never recalculate it. Crossing zero on an outgoing movement makes a formerly valid basis `RETAINED`.
5. Corrections/reversals use explicit direction and the approved cost snapshot needed to compensate evidence. Reversing an incoming movement must not naively restore the old average if later movements exist; the service applies a compensating delta at the current locked projection, retains accountable snapshots, and follows the same current-state basis rules. Historical movement snapshots never change.

**Implementation decision required — decimal engine.** Options are (a) SQL expressions with explicit `CAST(... AS DECIMAL(38,12))`/`ROUND`, or (b) a pinned arbitrary-precision PHP library. The repository has no decimal library and dependencies are intentionally small. Recommend a focused Inventory repository SQL calculator executed on the same MySQL 8.4 connection, with all input/output represented as canonical decimal strings and golden-vector integration tests. Do not use PHP `float`, JS `Number`, or implicit MySQL double coercion.

### C.2 Atomicity and lock order

Every posting/movement transaction must:

1. start the transaction and lock its aggregate/original record;
2. resolve and validate all Product IDs in server-derived scope;
3. sort unique internal Product IDs ascending;
4. materialize any absent positions safely (`INSERT ... ON DUPLICATE KEY` or equivalent), then select all positions `FOR UPDATE` in that sorted order;
5. validate lifecycle, state, decimal bounds and source combinations again under lock;
6. insert immutable movement(s), update projection(s), set each `last_movement_id`, increment each `version` once per applied movement, and perform the aggregate transition;
7. append Audit;
8. publish all sibling changes after every domain lock has been acquired;
9. commit, with no catch that partially commits.

For simultaneous creation of a missing position, the unique `(tenant_id,shop_id,product_id)` is the arbiter. The chosen upsert must not alter quantity/cost and must then be locked/read. Two multi-product Intakes arriving in opposite browser order therefore acquire the same server order.

## D. Findings, risks and documentation discrepancies

| Finding/risk | Consequence | Required treatment |
|---|---|---|
| No Phase 3 code/schema exists | Phase 3 begins cleanly but cannot rely on placeholder models | Build only the five frozen tables/modules in four vertical packages |
| Phase 2 reports say runtime blocked; owner says now tested | Repository verification record is stale | Add a dated, append-only acceptance addendum with evidence; do not claim unobserved results |
| No current SQL dump is checked in | Cannot confirm live production migration state | Preflight with `php spark migrate:status` and MySQL `SHOW CREATE TABLE`; store sanitized evidence, not customer data/secrets |
| Permission registry lacks three required separations | Reusing only `inventory.adjust` would over-authorize | Add `product.manage`, `inventory.intake`, `inventory.reverse`; retain `inventory.view`/`inventory.adjust` |
| Audit/change vocabularies reject Inventory | First write would roll back | Extend closed registries alongside each first writer and document them |
| Circular last-movement FK | Wrong migration order fails | Add it after movement table |
| Later source parents do not exist | Adding those FKs now is impossible; omitting columns weakens compatibility | Create nullable columns/indexes/check logic now; defer only two named constraints and register owners |
| WAC under negative stock is easy to misread | Wrong COGS basis in Phase 4 | One tested decimal transition service; golden vectors and two-connection tests |
| Reversing old incoming evidence after intervening activity | Restoring old projection snapshots would corrupt current truth | Compensate at current locked position; never restore stale `quantity_before`/average wholesale |
| Intake post retry/races | Duplicate movements/WAC | Lock header; `DRAFT` conditional transition; permanent IDs; unique source relationship; specific conflict |
| Reversal race | Double compensation | Lock original, `UNIQUE(reversal_of_movement_id)`, translate duplicate key to conflict |
| Audit/change failure | Partial evidence | Same connection/transaction, injectable failure tests at both append boundaries |
| Foreign UUID substitution | Cross-Tenant disclosure/effect | Scope every lookup by context; composite FKs; non-enumerating not-found |
| Read-only holes | Expired shop could still mutate through a new route | All write controllers use the centralized access gate before permissions/services; reads remain available only in READ_ONLY |
| UI source/generated duplication | Browser build could diverge | Change `resources`, run template compiler/build, review generated `public/assets` |
| Large current Shop files | Inventory additions could worsen maintainability | Add Inventory API module/composables/components while integrating navigation into the existing shell |
| Scope creep through reserved movement/source values | Premature Sales/Reconciliation workflows | Schema compatibility only; no later-phase routes, services, controls or navigation |

## E. Four-work-package summary matrix

| WP | Title | Main capability | Schema | Key dependency | Exit result |
|---:|---|---|---|---|---|
| 1 | Catalog and Stock Projection Foundation | Searchable Product catalog, Product lifecycle, safe position/threshold reads | `products`, `stock_positions` base | Phase 1 context/access/permissions; Phase 2 Audit/change | Users can manage Products and thresholds and view low/negative stock without any balance-write path |
| 2 | Opening Stock, Receipt Intake and WAC Posting | Draft multi-line Intake and atomic Opening/Receipt posting | `stock_intakes`, `stock_intake_items`, `stock_movements`, late position pointer FK | WP1 products/positions and decimal/locking contracts | Opening and later Intake produce immutable movement evidence and correct WAC/projections atomically |
| 3 | Accountable Non-Sale Movements and Append-and-Correct | Damaged/expired/withdrawal/loss/correction, movement and Intake reversal/replacement | no new table; finalize approved checks/index/guards if migration split requires | WP2 ledger/posting primitive and unique reversal protection | Every approved non-sale change is reasoned, compensating and race-safe; original evidence stays intact |
| 4 | Integrated Inventory Experience and Phase 3 Closure | Complete responsive Inventory workspace, history/diagnostics and release evidence | no feature schema; only evidence-backed corrective index migration if review proves necessary | WPs 1–3 complete | Entire Phase 3 is usable, documented and accepted under concurrency, failure injection, isolation and device-size checks |

## F. Detailed Work Package 1 — Catalog and Stock Projection Foundation

### Objective

Deliver an end-to-end Tenant Product catalog and Shop stock-summary foundation: Products can be created, searched, edited and deactivated; positions exist safely; thresholds can be changed; low and negative stock can be queried and displayed. No user can directly set expected quantity or cost.

### Why this package comes first

Intake items and movements require same-Tenant Product and Shop/Product position parents. Permission/Audit/change vocabularies and query conventions must exist before the first Inventory evidence transaction. This package isolates mutable-master and projection setup risk from WAC/posting risk.

### Scope

* Product create, edit, deactivate, detail, status filter and normalized-name search/similar-name warning.
* Identical names and identical normalized names accepted as separate Products.
* One base `unit_name`; `current_selling_price`; no barcode/code/package conversion.
* Lazy position creation for the current Shop or deterministic creation at Product creation (see decision below).
* Stock summary and Product detail show `expected_quantity`, threshold, average cost/basis state, low/negative flags, last movement reference and version diagnostics where appropriate.
* Threshold update only; derived low-stock list; negative-stock exceptional presentation.

### Explicit non-scope

Opening/Receipt drafting/posting, any movement creation, WAC changes, correction/reversal, Sales/returns/Reconciliation and offline mutation. WP1 does not pretend a newly created Product has opening quantity.

### Existing code it builds upon

`ShopContext`, `AccessGate`, `PermissionService`, `PermissionCode`, `AuditService` and typed Audit contracts, `ChangeSequenceService`, `ApiResponse`/`ApiException`, `RequestContext`, explicit `/api/v1` routing and CSRF/session filters. Frontend extends the existing Shop shell/navigation, `http-client.js`, feedback/error conventions, Bootstrap/Sass source and generated-assets workflow.

### Database work

1. Migration/raw SQL A: create `products` exactly as v1.1: IDs/public UUID, Tenant, name/normalized name/unit/current price/status/provenance/timestamps; UUID/status/price checks; `UNIQUE(public_id)`, non-unique `(tenant_id,normalized_name)`, `(tenant_id,status,name)`, `UNIQUE(tenant_id,id)`; Tenant/User `RESTRICT` FKs.
2. Migration/raw SQL B: create `stock_positions` without the circular last-movement FK: `(tenant,shop,product)` unique, expected `(19,4)` allowing negative, threshold `(19,4)>=0`, average `(19,6)>=0`, basis state check, nullable `last_movement_id`, unsigned version and timestamps; same-scope Shop/Product composite FKs; expected-quantity index.
3. Add exact migration-register/schema-note entries. Pair every PHP migration with semantic raw SQL. Rollback order is positions then products and is allowed only after proving no later dependent tables; never delete operational data as an application behavior.
4. Reserve no new columns beyond v1.1.

**Implementation decision required — position materialization.** Options: create one zero position during Product creation for the sole Shop, or create lazily in Inventory operations/query. Recommend atomic creation with Product for the current Phase 1 Shop because one-Shop is enforced, it gives threshold/low-stock UI immediately, and Product + position + Audit + changes can be tested as one slice. Keep repositories capable of safe lazy creation so future imports/branch evolution do not assume it. This is not multi-branch functionality.

### Backend/domain work

* Add `Modules/ProductCatalog` with immutable create/update commands, validator/normalizer, service and scoped query repository.
* Add `Modules/Inventory` position query and threshold service. Repository methods expose named operations, not generic `save()` for protected fields.
* Normalize case/spacing according to one documented deterministic algorithm; warn on similarity but never reject/merge.
* Validate canonical UUIDv4 public IDs, bounded strings, canonical decimal price/threshold strings and inactive transitions server-side.
* Product create transaction: validate → insert Product and zero position → Audit → Product and position changes → commit.
* Edit Product by scoped public UUID; lock/read current row, reject no-op with a specific `409`, snapshot safe changed field names/before-after values, never rewrite movement history.
* Deactivate only `ACTIVE→INACTIVE`, preserve position/history and use `DEACTIVATE` publication. Decide whether future reactivation is out of frozen scope (recommended: no route).
* Threshold service may update only `low_stock_threshold`, increments position version, audits/publishes position; expected quantity/cost columns are absent from its command shape.
* Queries are Tenant/Shop scoped, parameterized, paginated and index-backed. Product status never hides historical position/movement reads.

### Authorization/security

* `inventory.view`: Product list/detail, position/low-stock/negative-stock views and later movement history.
* new `product.manage`: create/edit/deactivate Product.
* Threshold update: `inventory.adjust` because it is operational Inventory configuration; do not bundle it into catalog price/name authority.
* Primary Owner passes by role; Staff requires each explicit grant. Unknown codes still fail closed.
* `FULL` required for every write; `READ_ONLY` can read; `BLOCKED`/`CLOSED` receives no normal inventory surface. Active membership/context is re-resolved on each request.
* CSRF/session Fetch rules apply. Foreign public UUIDs return non-enumerating not-found. Input Tenant/Shop/actor fields are ignored/rejected, never trusted.
* Product deactivation requires confirmation and a reason; ordinary create/edit/threshold do not need a human reason unless policy review intentionally strengthens them.

### API work

Add thin-controller command/query families consistent with current explicit routes:

* Product collection search/list and create.
* Product detail and explicit update/deactivate actions (no generic delete).
* Inventory summary, low-stock filter/list, Product-position detail.
* Explicit threshold command accepting position/Product public UUID plus threshold only.

Use public UUIDs externally; never expose internal IDs as identifiers. Collection metadata follows documented capped offset pagination. There is deliberately no `PATCH expected_quantity`, cost update, position create/delete or arbitrary field patch.

### Audit work

Extend closed vocabularies with category `INVENTORY` actions:

* `product.created`, `product.updated`, `product.deactivated` → resource `product`;
* `stock.threshold_updated` → resource `stock_position`.

Make create/update/threshold reasons optional in `AuditReasonPolicy`; deactivation requires nonblank reason. Metadata contains only safe field names and minimized scalar before/after values (name/unit/price/status/threshold), never request bodies. Resource public UUID and request correlation are mandatory. All are appended in the mutation transaction.

### `sync_changes` work

Add allow-listed resources `product`, `stock_position`. Publish:

* create: Product `UPSERT` and created position `UPSERT`;
* edit: Product `UPSERT`;
* deactivate: Product `DEACTIVATE` (position remains readable and is not falsely deactivated);
* threshold: position `UPSERT`.

Acquire no domain lock after `publish()`. Add no transport/read endpoint.

### Frontend work

* Add catalog navigation only for viewers; manage controls only for `product.manage` and `FULL`.
* Responsive Product cards/list with search, status, unit, selling price, “Stock left,” “Low stock” and visible negative badge. Avoid routine horizontal scrolling.
* Create/edit form keeps entered values and field errors after `422`, conflict, timeout or network error. Similar-name matches show an advisory, not a blocker.
* Detail screen/card contains threshold editor, cost-basis label and history placeholder **only when WP2 delivers the real history**; WP1 must not render a dead button.
* Deactivation uses an accessible confirmation/reason dialog and explains that history remains.
* Loading, empty, success, server/network unavailable, permission denied, conflict, READ_ONLY and BLOCKED/CLOSED shell states; pending buttons prevent accidental double click but server remains authoritative.
* Verify keyboard operation, focus/error association, touch targets and 200% zoom.

### Documentation updates

`database/migration-register.md`, `database/schema-notes.md`, new Product and Inventory module docs, `module-map.md`, API conventions/resource docs, permission catalogue, Audit contract/vocabulary, sync phase-02 resource table, configuration only if a real new setting is introduced (none expected), Inventory runbook, WP1 manual checklist and WP1 delivery report. Add the Phase 2 verification addendum or explicitly carry it as a release gate.

### Manual verification checklist

1. Apply/rollback/reapply A–B on disposable MySQL 8.4; compare `SHOW CREATE TABLE` to both artifacts and v1.1; confirm collations/checks/FKs/indexes.
2. Create two Products with the identical name and normalized name; both succeed with distinct public IDs and positions; advisory appears.
3. Search exact, case/spacing-normalized and similar names; paginate/filter active/inactive; verify query plans use intended indexes.
4. Edit name/unit/current selling price; verify safe Audit and one Product change notification. Reject malformed UUID/decimal/overlong fields/no-op.
5. Deactivate; reject repeat; verify `DEACTIVATE`, required reason, hidden from active entry but detail/position remain readable.
6. Set and change threshold; verify position version increments but quantity/cost/last movement do not change; derive low stock at equality.
7. Display negative quantity from a controlled fixture without clamping; show exceptional state.
8. Attempt any generic quantity/cost patch or delete endpoint; prove none exists/route is 404.
9. Substitute foreign Product, Tenant hostname/Shop, and guessed UUID; verify no read/write leakage or existence disclosure.
10. Test Owner; Staff with only view; only manage; only adjust; no grant; deactivated membership; unknown code.
11. Test `FULL`, expired `READ_ONLY` (reads work/all writes fail), suspended/BLOCKED and CLOSED.
12. Force Audit failure then change insertion/generation failure; verify Product/position/threshold and evidence all roll back.
13. Repeat create UUID/edit/deactivate/threshold submissions and race threshold edits; verify domain-specific conflict/no double evidence.
14. Test phone 320/360/390, tablet, desktop, keyboard and 200% zoom; network/timeout/500/409/422 preserve recoverable form input.

### Definition of Done

Both schema artifacts match v1.1 and pass MySQL runtime checks; catalog and stock summary work end-to-end under actual Owner/Staff/access modes; duplicate names work; low/negative derivation works; every mutation has atomic Audit/change evidence; adversarial scope fails; no balance/cost mutation path exists; responsive/accessibility checks have recorded evidence; docs and generated assets match sources.

### Handoff to WP2

WP2 may assume stable scoped Product lookup, position creation/locking helpers, decimal input validation, `inventory.view`/`product.manage`/`inventory.adjust`, Product/position Audit and change vocabularies, and zero positions for newly created Products. It may not bypass these repositories or context rules.

## G. Detailed Work Package 2 — Opening Stock, Receipt Intake and WAC Posting

### Objective

Deliver usable Draft → Posted multi-item Opening Stock and later Receipt workflows whose single transaction creates immutable movement evidence and correct current quantity/WAC projections.

### Why this package comes here

Opening evidence must exist before manual decreases/corrections can be meaningfully exercised. This package introduces the ledger and highest-risk decimal/concurrency primitive once Product and position scope are stable.

### Scope

* Create/edit/review/discard Draft `OPENING` and `RECEIPT` Intakes; add/edit/remove positive lines while Draft.
* Multi-line builder with Product search, quantity, acquisition/unit cost, reference, notes, occurrence and offset.
* Post single- and multi-item Intake.
* Line-cost snapshots rounded half-up to `(19,2)`.
* `OPENING` and `STOCK_INTAKE` movements, WAC transitions, position version/last movement, Intake and movement history/detail.
* Immutable posted evidence. Correction/reversal controls wait for WP3.

### Explicit non-scope

Manual outgoing/positive corrections, reversal/replacement, Suppliers/PO/payables, Sale/Return/Reconciliation sources, offline submission/receipts, direct stock overwrite.

### Existing code it builds upon

All WP1 Product/Inventory repositories/validators/vocabularies plus current Audit/Change services, `UtcTimestamp`, `Uuid`, API envelope/client, reason/dialog and responsive shell patterns. Follow Phase 2 business → Audit → change ordering and same-connection construction.

### Database work

1. Migration/raw SQL C: `stock_intakes` with public UUID/scope/kind/status/reference/notes/occurrence/offset/posting/reversal/replacement/correlation/provenance; exact indexes, checks and unique nullable reversal/replacement guards; composite Shop and User FKs and same-scope self links as v1.1 permits.
2. Migration/raw SQL D: `stock_intake_items`, decimal constraints, line order, same-scope Intake/Product FKs, unique line/public IDs and indexes. Use `RESTRICT` plus explicit Draft cleanup (the schema's safer recommended choice), not application cascade over posted evidence.
3. Migration/raw SQL E: `stock_movements` with all v1.1 columns including nullable future `sale_item_id` and `reconciliation_variance_id`; movement/source/sign checks; immutable indexes; same-scope Product/position/Intake-item/self-reversal FKs; `UNIQUE(reversal_of_movement_id)`.
4. Install no FK to absent `sale_items` or `reconciliation_variances`. Record exact deferred names `fk_stock_movements_sale_item_scope` (Phase 4 owner) and `fk_stock_movements_recon_variance_scope` (Phase 7 owner), with prerequisite composite parent uniques. Phase 3 service/check rules reject those sources.
5. Migration/raw SQL F: add the circular `stock_positions.last_movement_id` FK after movements exist.
6. Rollback dependency: drop pointer FK, movements, items, intakes. Product/position parents remain until later dependants are removed. Never promise transactional rollback for MySQL DDL.

### Backend/domain work

* Add typed Intake create/edit/post commands and DTOs. Draft repository exposes explicit item operations and draft-only discard; posted repository exposes read only.
* Validate no duplicate line number and recommend one line per Product per Intake to simplify locking/evidence. If the frozen docs are interpreted to permit duplicate Product lines, coalesce only lock acquisition—not evidence—and define deterministic within-Intake line order. **Recommendation:** reject duplicate Product lines with field error; it prevents ambiguous sequential WAC inside one receipt without weakening schema.
* Posting locks Intake header first, confirms `DRAFT`, reads/validates items and active same-Tenant Products, sorts unique internal Product IDs, locks positions ascending, and recalculates all snapshots inside the transaction.
* Use SQL decimal arithmetic at `(38,12)` and explicit half-up rounding. Never cast authoritative numbers to float.
* Each line inserts one `OPENING` or `STOCK_INTAKE` movement with source `INTAKE_ITEM`, positive delta, exact source item, before/after quantity and average snapshots, cost basis and correlation. Update its position and last movement/version.
* Header status changes to `POSTED` only after all movement/projection work succeeds; store posted actor/time. Append one aggregate Intake-posted Audit event plus minimized counts/amount, then publish header, movements and positions. All items are represented as part of the Intake aggregate notification rather than a separate sync resource.
* Duplicate post: header lock + DRAFT-only transition yields `INTAKE_ALREADY_POSTED` and no second effect. Create/edit use public IDs and scoped line uniqueness. A bounded whole-transaction deadlock retry is allowed only if command public IDs are stable and the retry begins from scratch.
* Opening is accountable Intake, not a separate setter. Whether multiple Opening Intakes are permitted is not explicitly frozen. **Implementation decision required:** recommend allowing accountable later Opening batches only while business policy permits onboarding, rather than a database “one ever” guard; ordinary receipts thereafter should use `RECEIPT`. If product owners require exactly one Shop-wide Opening event, specify that before migration because the schema currently provides no guard.

### Authorization/security

* New `inventory.intake`: Draft creation/edit/discard/post for Opening and Receipt.
* `inventory.view`: Intake/movement/position reads.
* Owner derives authority; Staff requires explicit grant. `product.manage` and `inventory.adjust` do not imply Intake.
* Only `FULL` writes; `READ_ONLY` retains history. Inactive Product cannot be added/posted, but previously posted history remains visible.
* Occurrence time/offset are context, not lock order/entitlement truth. Actor/context always server-derived.
* Posting confirmation explains permanence. A posting reason may be optional; notes/reference are not a fabricated Audit reason.

### API work

* Intake collection/detail, Draft create/update/discard and item add/update/remove.
* Explicit `/post`-style command for lifecycle transition; never generic status patch.
* Position/Product movement history with capped pagination and movement/type/date filters.
* Payload uses public IDs and canonical decimal strings. Stable client-created UUID shapes may be accepted now, but acceptance creates no replay semantics.

### Audit work

Add `stock_intake.created`, `stock_intake.updated`, `stock_intake.discarded`, `stock_intake.posted`; resources `stock_intake` and `stock_movement` (movement rows are normally summarized under posted Intake Audit rather than one Audit per line). Draft discard requires a reason only if product policy treats it as sensitive; recommend optional before evidence exists. Posting metadata: kind, line count, public movement IDs or bounded count (not unbounded arrays), total rounded line cost, occurrence context and resulting position public IDs within metadata bounds. Do not log notes/reference wholesale if not needed.

### `sync_changes` work

Add `stock_intake`, `stock_movement`. Publish Intake `UPSERT` on Draft create/item aggregate change/post; do not publish discarded Draft as authoritative history—if clients can observe drafts in future, use `DEACTIVATE` before deletion. Publish every posted movement `UPSERT` and every affected position `UPSERT`. Multiple lines for one Product still publish deliberate movement rows; deduplicate sibling position change commands to one final state notification per position. Same transaction, same correlation.

### Frontend work

* Separate “Opening stock” and “Receive stock” entry points backed by the same builder/service with fixed kind.
* Phone-first stacked item cards, large Product search/add, unit-aware quantity and “Cost price,” inline line amount, remove/edit, clear total/review, sticky but non-obscuring review/post action. Desktop enables efficient keyboard line entry without a wide-grid dependency.
* Save Draft explicitly; preserve unsaved form values on recoverable errors. Warn when unavailable/offline: Phase 3 cannot queue the operation.
* Review page explains permanent posting. Pending state locks duplicate buttons. On `409 already posted`, reload authoritative detail rather than repost.
* History cards use “Stock came in,” before/after Stock left, cost snapshots, actor/time and reference. Negative and low status remain visible.
* Universal loading/empty/validation/network/server/permission/conflict/READ_ONLY/BLOCKED states and accessible focus/error behavior.

### Documentation updates

Migration register/schema notes/late-FK register; Inventory module lifecycle/WAC/source rules; protected-resource API commands; permission/Audit/change matrices; sync resource representation; Inventory operations runbook including deadlock/rollback diagnostics; manual WP2 vectors; delivery report. Configuration documents MySQL strict mode/time zone already required; add nothing configurable for WAC.

### Manual verification checklist

1. Apply C–F and compare all artifacts/constraints/collations/indexes/FKs; prove future columns exist but future FKs do not; rollback in dependency order.
2. Create/edit/remove/discard Drafts; attempt posted edits/deletes and direct status change; verify rejection.
3. Post Opening Stock; verify positive movement, exact before/after, line snapshot, position, basis `VALID`, version, last movement, actor, Audit and changes.
4. Post single Receipt and multi-item Receipt; hand-calculate line rounding and WAC across multiple incoming costs.
5. Exercise Q=0 unestablished; Q=0 retained; Q<0 remaining non-positive; and Q<0 crossing positive. Verify only crossing positive resets to that incoming cost.
6. Verify outgoing fixtures do not recalculate average and retained-state semantics are ready for WP3.
7. Race two Intakes on one Product; verify serialized sum/WAC. Race multi-product Intakes submitted in reverse browser order; verify ascending locks/no deadlock or bounded safe retry.
8. Retry identical post sequentially and concurrently; one post/effect only, specific conflict, no duplicate evidence.
9. Force failure at each movement insert, each position update, header lifecycle update, Audit append, each change insert and commit; entire logical operation remains absent/unposted with no projection drift.
10. Substitute foreign Product/Intake/item/position UUID and request scope; verify non-enumerating denial and composite FK defense.
11. Test inactive Product between Draft and post, empty Draft, zero/negative quantity, negative cost, overflow/scale, duplicate product line, reordered lines and malformed occurrence.
12. Owner/Staff permission combinations and all access modes; READ_ONLY reads posted/draft history but cannot mutate/post.
13. Phone/tablet/desktop/keyboard/zoom, slow request, offline/network loss, `422`, `409`, `500`; form values survive recoverable errors.
14. Recompute each position from committed movements and compare expected quantity/last movement/version.

### Definition of Done

Opening/Receipt flows function end-to-end; posted evidence is immutable; authoritative decimal vectors match hand calculations; all affected positions and evidence commit or roll back together with Audit/changes; races/retries have one correct effect; scope and access tests pass; no future source is callable; responsive builder/history acceptance is recorded.

### Handoff to WP3

WP3 may assume an insert-only movement repository, one position-application/WAC primitive, deterministic multi-position locking, posted Intake protection, movement history queries, and unique reversal/source structure. It must create compensating evidence through these APIs, not update posted rows.

## H. Detailed Work Package 3 — Accountable Non-Sale Movements and Append-and-Correct

### Objective

Deliver every remaining approved Phase 3 stock movement and a protected correction/reversal model, so all legitimate non-sale gains/losses are accountable and every mistake is corrected without erasure.

### Why this package comes here

It reuses the proven ledger/projection/locking/WAC primitive from WP2. Building reversal first would duplicate or prematurely design the central transaction path.

### Scope

* `DAMAGED`, `EXPIRED`, `OWNER_WITHDRAWAL`, `MISSING_LOSS` negative manual movements.
* Signed positive/negative `CORRECTION` with explicit reason.
* `REVERSAL` that compensates one eligible original movement; reject a second direct reversal.
* Intake-level reversal/correction and optional replacement with causal links, preserving original header/items/movements.
* Current projection, retained basis and history updates; reasoned sensitive confirmations.

### Explicit non-scope

Sale, Customer Return, Reconciliation Adjustment, physical count, financial/cash effect of Owner withdrawal, Supplier/PO and offline dispatch. “Owner withdrawal” moves stock only; it does not create an accounting ledger.

### Existing code it builds upon

WP2's scoped movement insert/application repository, decimal rules, sorted position locking, posted Intake aggregate, history and Audit/change transaction template; `SensitiveReason`, shared reason dialog, `AuditCausalLinkValidator`, permission service and API error conventions.

### Database work

No new table. Verify E's checks admit exactly Phase 3 manual/reversal combinations while rejecting reserved source misuse:

* `MANUAL`: no intake/sale/reconciliation/reversal FK; type is damaged/expired/withdrawal/loss/correction; signed compatibility enforced.
* `REVERSAL`: exactly `reversal_of_movement_id`; movement type `REVERSAL`; quantity is exact negative of original.
* `INTAKE_ITEM`: exactly intake item; type opening/intake and positive.
* `SALE_ITEM`/`RECON_VARIANCE`: nullable columns remain unusable by Phase 3 services; checks may reserve valid future combinations without a route.

Use the unique direct-reversal index and all source indexes already introduced. If MySQL cannot express a cross-row equality in CHECK (it cannot), service validation under original-row lock is authoritative; do not add triggers or shadow tables. Any corrective DDL discovered in review must have paired migration/raw SQL and documented rollback.

### Backend/domain work

* Typed `RecordManualStockMovement`, `ReverseStockMovement`, `ReverseStockIntake` and `CreateReplacementIntake` commands; stable public UUID, Product dependency, signed decimal, occurrence/offset and reason.
* Manual service validates active same-scope Product, permission/type/sign/reason; locks position; creates movement and current snapshots; updates quantity/last movement/version. Outgoing retains average; correction incoming follows the approved current-state basis policy. Because correction is not acquisition evidence, **implementation decision required:** recommend positive `CORRECTION` retain the current basis and set `RETAINED`/`UNESTABLISHED` as appropriate rather than establish/reset WAC; only Intake has acquisition unit cost. If business expects cost-bearing corrections, the approved schema/roadmap must explicitly choose a unit-cost rule before coding.
* Reversal locks original movement then affected position. Reject reversal of a reversal as a direct shortcut unless an explicit correction workflow is selected; reject already directly reversed; copy the negated original quantity, link it, and apply to current projection rather than restore stale before snapshots. Snapshot current before/after and approved cost basis.
* Reversal of manual movement creates one linked movement. Intake reversal locks Intake, all original movements/items and positions in ascending Product ID; creates a linked compensating Intake record/evidence plus one `REVERSAL` movement per original line; links header via `reversal_of_intake_id`. Optional replacement is a new separately validated Draft/Posted Intake linked by `replacement_for_intake_id`, not mutation of the original.
* One aggregate transaction for Intake reversal: eligibility/locks → all compensating movements → positions → reversal link/evidence → causal Audit → changes → commit.
* Unique guards and duplicate-key translation protect races. Do not rely on a pre-check without the index.
* Protected repositories expose no posted/header/item/movement update/delete. Draft cleanup remains explicit and cannot follow a posted link.

### Authorization/security

* `inventory.adjust`: damaged, expired, Owner withdrawal, missing/loss and signed correction. This matches the existing frozen code; UI can further emphasize sensitive types.
* new `inventory.reverse`: any movement/Intake reversal and replacement link. It is not implied by adjust/intake for Staff.
* `inventory.view` for reads. Owner has derived authority; Staff needs exact grants.
* Every manual loss/correction/reversal requires a nonblank ≤500-character reason. Owner withdrawal is not Owner-only: an expressly granted Staff member may record it, preserving the actor. If product policy wants Owner-only, label that as a policy change rather than silently overriding the granular-grant model.
* `FULL` only for commands. Cross-scope and inactive context fail. Inactive Product may still require correction/reversal to preserve truth; recommend allow authorized reversal against historical evidence while blocking new ordinary manual activity, and document the exception.

### API work

* Explicit manual-movement command with allow-listed type; do not expose raw `source_kind`, cost basis, before/after, actor or scope fields.
* Explicit movement reverse command and Intake reverse/correct/replace command families.
* Read detail exposes linked original/reversal/replacement public IDs and effective-state labels.
* No generic movement create/update/delete and no arbitrary correction patch.

### Audit work

Add:

* `stock_movement.recorded` → `stock_movement` for manual types;
* `stock_movement.corrected` → correction movement;
* `stock_movement.reversed` → reversal movement;
* `stock_intake.reversed` and `stock_intake.replaced` → linked `stock_intake`.

All require reasons. Metadata is bounded: type, signed quantity string, Product public ID, original/reversal/replacement public IDs, before/after quantity/cost strings, not notes/request dumps. Use `caused_by_audit_event_id` when a known same-scope original Audit event is available, but never substitute it for movement/Intake FKs. One aggregate Intake-reversal Audit may summarize bounded line count; movement links remain domain evidence.

### `sync_changes` work

Manual/correction/reversal publishes new movement `UPSERT` plus final position `UPSERT`. Intake reversal publishes compensating Intake `UPSERT`, every reversal movement `UPSERT`, affected positions `UPSERT`, and original Intake `UPSERT` if its effective derived state representation changes. Replacement publishes the new Intake aggregate; it never emits `DEACTIVATE` for immutable original evidence. All sibling rows share operation correlation and transaction.

### Frontend work

* Dedicated “Record stock leaving” choices with plain labels: Damaged, Expired, Owner use/withdrawal, Missing/lost; ask “Why did stock leave?” and show resulting Stock left, including negative.
* Correction screen explicitly chooses add/remove, explains that it creates evidence, requires reason and confirmation.
* Movement/Intake detail presents “Reverse this record,” consequences, original/effective status, linked compensating record and optional replacement workflow only to authorized `FULL` users.
* Preserve entered Product/quantity/reason on recoverable failure. Disable repeated submit while pending; on reversal conflict reload links and explain it has already been reversed.
* History filters/types and cards visually distinguish original, reversal and correction without erasing either. Negative warning is prominent, not blocked.
* All universal/access/mobile/keyboard/zoom states follow existing shell/dialog behavior.

### Documentation updates

Inventory module append-and-correct/source/sign/cost rules; protected API resources; permission catalogue; Audit reason/vocabulary/causality; sync publication table; schema notes including deferred FKs; correction/reversal runbook; WP3 adversarial/concurrency checklist and delivery report.

### Manual verification checklist

1. From known stock, record damaged, expired, Owner withdrawal and missing/loss; verify negative deltas, retained average, actor/reason/evidence.
2. Permit a legitimate negative result; confirm no clamp/rejection and clear UI warning.
3. Record positive and negative corrections; validate basis-state policy, exact before/after, reason and history.
4. Reverse each eligible type; delta exactly compensates original; original remains byte-for-byte unchanged; links/effective labels are correct.
5. Submit second direct reversal sequentially and in two concurrent sessions; one wins, other receives specific conflict, no double stock.
6. Reverse an older Intake after later movements; confirm current quantity changes by compensation without copying stale old average/quantity snapshots.
7. Reverse multi-item Intake; verify ascending Product locks, all-or-nothing movements/positions/header link/Audit/changes. Race with Intake on an overlapping Product.
8. Create linked replacement; verify it is independently validated/posted and original/reversal/replacement chain is navigable.
9. Force failure at original validation, movement insert, position update, link insert, Audit, change and commit; no partial compensation.
10. Attempt forged original IDs, cross-Tenant Product/Shop, source kind, before/after/cost/actor, sign/type mismatch and future movement types; reject safely.
11. Attempt update/delete of posted Intake/item/movement; prove no route/repository method and DB protection expectations.
12. Owner; Staff view/adjust/reverse/intake permutations; unknown permission; expired READ_ONLY history with all writes blocked; suspended/closed.
13. Test phone/tablet/desktop/keyboard/200% zoom and every loading/empty/validation/network/server/conflict state; reason/form survives recoverable errors.

### Definition of Done

All and only approved Phase 3 movement commands work; projection changes always have same-transaction movement evidence; reasons/permissions/access/scope are enforced; original posted evidence is immutable; direct reversal uniqueness holds under race; Intake correction is causal and atomic; Audit/change rows are complete; retained/reset basis rules pass; UI is usable at target sizes.

### Handoff to WP4

WP4 may assume complete authoritative command coverage, protected evidence and linked history. It may compose/query/polish these capabilities but must not change stock calculation semantics or add later movement types.

## I. Detailed Work Package 4 — Integrated Inventory Experience and Phase 3 Closure

### Objective

Turn the three proven slices into one coherent, accessible Shop inventory workspace and close Phase 3 with full schema, invariant, runtime, concurrency and documentation evidence suitable for Phase 4 reliance.

### Why this package comes last

Cross-flow navigation, operational diagnostics and exhaustive acceptance require real catalog, posting and correction paths. Delaying this integration avoids placeholder controls and lets closure test the actual final surface.

### Scope

* Final coherent Shop navigation/dashboard for Products, Stock summary, Low stock, Negative stock, Opening/Receive Stock, movement history and authorized manual/correction/reversal actions.
* Query/filter/pagination performance and consistent plain-language cards/details.
* Universal-state consistency, focus/keyboard/zoom/responsive acceptance, failure recovery and duplicate-submit UX.
* Full reconstructability/drift diagnostic and operational runbook (read-only diagnostic; no repair button).
* Phase 3 complete automated/integration/manual verification, security review, migration rehearsal and delivery/closure report.

### Explicit non-scope

No new business command, alert ledger, analytics/forecasting, dashboard charts without approved purpose, export, multi-branch, offline cache/outbox/sync UI, Sales/Customer/Supplier/Reconciliation/finance placeholder, or automated stock repair.

### Existing code it builds upon

WPs 1–3, Shop shell/nav/context capabilities, Bootstrap/Sass, shared HTTP client/reason dialog/status patterns, Audit Panel conventions, build scripts, module/API/security/database/sync/runbook docs and Phase 1/2 manual verification format.

### Database work

No planned feature schema. Run MySQL 8.4 `SHOW CREATE TABLE`, FK/index/check and query-plan review over all five tables. Add an index only if representative runtime evidence proves a frozen query needs it; any such migration/raw SQL is paired and registered, with rollback and write-cost justification. Run a read-only invariant query comparing each position to signed movement sums and validating last-movement/version diagnostics. Do not add cached low-stock, alert or reporting tables.

### Backend/domain work

* Consolidate Inventory query service output so list/detail/history derive flags consistently and never recompute authoritative WAC in PHP.
* Bounded filters and pagination; deterministic ordering with a stable tie-breaker.
* Read-only diagnostic service reports position-vs-ledger drift, invalid last pointer, illegal source combinations and posted Intake coverage without mutating data.
* Full fault-injection suite proves business/Audit/change atomicity. Two-connection suite proves Intake serialization, reversed client ordering and reversal races.
* Verify service contracts are callable by future Phase 10 dispatcher without HTTP/online-specific business logic; do not build dispatcher/receipts.
* Verify Phase 4 can call one Inventory service for sale deductions later; do not add Sale parameters or movement implementation now.

### Authorization/security

Re-audit every route against `inventory.view`, `product.manage`, `inventory.intake`, `inventory.adjust`, `inventory.reverse`; Owner-derived authority; explicit Staff grants; unknown fail-closed; CSRF/session; `FULL` writes, READ_ONLY reads, BLOCKED/CLOSED no operations; foreign UUID non-enumeration and composite DB scope. Sensitive reason text is not placed in URLs/logs and only minimized metadata reaches Audit.

### API work

Freeze and document the Phase 3 API/command contracts, stable error codes and pagination. Remove no required path and expose no placeholder. Verify OpenAPI-like examples if the repo adopts them, but do not introduce a documentation dependency solely for this phase.

### Audit work

Review all Phase 3 events through existing Owner Audit UI: vocabulary, category, actor, scope, resource, reason, occurrence, correlation, metadata redaction and causal link. Prove no false events on validation/no-op/conflict and no missing event on commit. No second Audit mechanism or Inventory-specific log is added.

### `sync_changes` work

Review resource/kind/scope/correlation and sibling publication for every mutation. Prove generation serialization and rollback with current Phase 2 service. Document that `stock_intake` is the aggregate notification for item changes. No pull/cursor/receipt/outbox/UI.

### Frontend work

* Integrate without dead links: navigation shown by view permission and access mode; action buttons shown only by exact capabilities and `FULL`.
* Phone: stacked cards, compact filters, large touch inputs, sticky actions with content clearance, no routine wide tables. Tablet/desktop: denser responsive grids and keyboard-efficient forms.
* Plain language consistently uses “Stock left,” “Cost price,” “Low stock,” “Stock below zero,” “Receive stock,” “Why did stock leave?”; technical source/basis values may appear only as explanatory diagnostics.
* Global and local loading; empty calls to action only when authorized; success/live status; inline validation; server/network unavailable; permission denied; conflict refresh; READ_ONLY banner; suspended/closed state. No offline promise—state clearly that a connection is required and retain in-memory form values.
* Test focus order, modal trap/return, labels/descriptions, visible focus, touch size, color-independent warnings and 200% zoom.

### Documentation updates

Finalize migration register/schema notes/late-FK register, Product/Inventory module docs, API/protected-resource conventions, permission catalogue, Audit contract/vocabulary, sync resource/publication docs, Inventory operational/invariant/failure-recovery runbook, configuration/dependency register only if actually changed, WP1–4 manual records, each delivery report and a Phase 3 closure report. Add Phase 2 runtime addendum with factual evidence or carry an explicit release blocker; never silently overwrite 2026-10-04/05 history.

### Manual verification checklist

Execute and record the complete Phase 3 acceptance matrix below, including:

1. Fresh install, forward migration, rollback decision rehearsal and sanitized schema comparison; application uses MySQL 8.4 strict/UTC.
2. Full Product duplicate/search/edit/deactivate/history and threshold/low-stock paths.
3. Opening, single/multi Receipt, rounding and every WAC boundary vector.
4. Every manual type, positive/negative correction, reversal, second-reversal conflict, Intake reversal/replacement and immutable originals.
5. Sequential retries, same-Product concurrent Intakes, reverse-order multi-product Intakes, reversal race and overlapping post/reversal.
6. Fault injection at movement, position, lifecycle/link, Audit, each change and commit boundaries; verify total rollback.
7. Reconstruct all test positions from movement deltas; compare current quantity/last pointer and investigate any drift without auto-repair.
8. Cross-Tenant Product/Shop/Intake/movement substitution and request-supplied scope/actor manipulation.
9. Owner and granular Staff matrix; READ_ONLY history/all writes denied; suspended/closed no normal operations.
10. Movement history pagination/filtering and Product/low/negative query performance.
11. Real target phone widths, tablet, desktop, keyboard and 200% zoom; screenshots/evidence according to project acceptance practice.
12. Repository search proves absence of Phase 4+ routes/UI/services and Phase 10 transport artifacts.

### Definition of Done

Every row in section N is PASS with evidence or the phase is not closed; all five tables match v1.1; no critical/high issue or unexplained projection drift remains; race/failure/isolation tests pass; exact permissions/access modes pass; final source/generated assets agree; docs/runbooks/delivery reports are current; Phase 2 verification discrepancy is resolved or explicitly blocks release; Phase 4 has a stable Inventory service and trustworthy cost/quantity projection to consume.

### Handoff to Phase 4

Phase 4 can depend on Product public IDs, locked Stock Positions, retained/current WAC, an insert-only movement ledger and the single Inventory transaction service. It must request Sale stock effects from Inventory and add its deferred `sale_item` FK after creating the approved parent; it may not update positions itself.

## J. Cross-package dependency map

```text
Phase 1 ShopContext + AccessGate + PermissionService
        ├── Phase 2 AuditService
        └── Phase 2 ChangeSequenceService
                    │
WP1 ProductCatalog ─┴─ Inventory Position/threshold/query
        │                         │
        └───────────┬─────────────┘
                    ▼
WP2 Intake aggregate + decimal transition + sorted position locks
                    │
                    ▼
WP2 immutable Movement repository / history / projection application
                    │
                    ▼
WP3 manual movement + correction + movement/Intake reversal/replacement
                    │
                    ▼
WP4 integrated UX + diagnostics + acceptance + Phase 4 contract
```

Artifacts later packages may assume:

* WP1: schema A–B, Product scope/normalization, position lock/materialization, permissions, Product/position Audit/change resources.
* WP2: schema C–F, decimal transition vectors, deterministic multi-position lock, Intake lifecycle, immutable movement insert, aggregate publication.
* WP3: complete movement command/reversal rules, protected causal chains and exact authorization.
* WP4: freezes public contracts, proves all invariants and supplies Phase 4 handoff. It does not repair earlier semantics by adding alternate paths.

## K. Recommended migration order and late-FK strategy

| Order | Migration | Contents/dependency | Rollback note |
|---:|---|---|---|
| 1 | `CreatePhaseThreeProducts` | `products`; depends Tenant/User | Drop only after every inventory/later child is removed |
| 2 | `CreatePhaseThreeStockPositions` | positions, Shop/Product scoped FKs; nullable pointer column but no pointer FK | Drop after movement pointer/FK and movement children |
| 3 | `CreatePhaseThreeStockIntakes` | Intake header/self correction columns/guards | Drop after items/movement sources |
| 4 | `CreatePhaseThreeStockIntakeItems` | items, header/Product scoped FKs | Drop after movements |
| 5 | `CreatePhaseThreeStockMovements` | ledger, position/Product/item/self FKs; future source columns/indexes; source/sign checks | Drop before items/positions |
| 6 | `AddPhaseThreeStockPositionLastMovementForeignKey` | circular diagnostic pointer FK after both bases | Drop this FK first |

Each has a timestamped CI4 migration and matching `docs/database/sql/phase-03/*.sql`, explicit UTC setup, InnoDB/charset/collations, deterministic constraint names, and migration-register row. A six-file split is appropriate even though there are five tables because it makes the circular FK dependency honest.

Deferred constraints, not deferred columns:

* Phase 4, immediately after `sale_items` and its same-scope unique parent key: add `(tenant_id,shop_id,sale_item_id)` FK to `stock_movements` using the approved exact scope.
* Phase 7, immediately after `reconciliation_variances` and its parent unique: add the analogous reconciliation-variance FK.
* Until then, Phase 3 commands reject `SALE_ITEM` and `RECON_VARIANCE`; direct DB inserts remain constrained as far as CHECK can express, and least-privilege application access prevents arbitrary writes.

Do not add placeholder parent tables, polymorphic `(type,id)`, weaken scope to an unscoped numeric FK, or remove approved source columns. Validate current live migration state first because no dump is checked in.

## L. Permission matrix

Recommended exact registry additions follow the established `<domain>.<action>` lowercase convention and the roadmap's required separation.

| Operation | Permission | Primary Owner | Staff | FULL | READ_ONLY | BLOCKED/CLOSED |
|---|---|---|---|---|---|---|
| View/search Product, stock, Intake/movement history, low/negative lists | `inventory.view` (existing) | allowed | explicit grant | read | read | deny normal operation |
| Create/edit/deactivate Product | `product.manage` (new) | allowed | explicit grant | write | deny | deny |
| Change low-stock threshold | `inventory.adjust` (existing) | allowed | explicit grant | write | deny | deny |
| Draft/edit/discard/post Opening/Receipt | `inventory.intake` (new) | allowed | explicit grant | write | deny | deny |
| Damaged/expired/withdrawal/missing/correction | `inventory.adjust` (existing) | allowed | explicit grant | write + reason | deny | deny |
| Reverse movement/Intake; link replacement | `inventory.reverse` (new) | allowed | explicit grant | write + reason | deny | deny |
| View Owner Audit evidence | existing Owner-only Audit policy | allowed | denied | read | read | existing Audit policy only; no normal Inventory |

**Implementation decision required — code name for catalog management.** `product.manage` is recommended over `inventory.product.manage`/`catalog.manage`: the existing registry uses one domain and one action (`staff.manage`, `inventory.view`) and Product Catalog is an approved module. Changing the existing `inventory.adjust` would break established grants/docs. Unknown and deprecated spellings must not alias; they fail closed.

Capabilities returned by Shop context should be derived from these permissions and access mode, not hard-coded Owner-only booleans. UI visibility is convenience; services recheck authorization.

## M. Audit and change-publication matrix

All rows require one owning transaction/connection: domain locks and mutation → Audit → changes → commit. “Optional” reason means the Audit policy accepts null; sensitive operations always require it.

| Mutation | Audit action / resource | Reason | Change resource(s) / kind |
|---|---|---|---|
| Product create | `product.created` / `product` | optional | Product `UPSERT`; initial position `UPSERT` |
| Product edit/price/unit | `product.updated` / `product` | optional | Product `UPSERT` |
| Product deactivate | `product.deactivated` / `product` | required | Product `DEACTIVATE` |
| Threshold update | `stock.threshold_updated` / `stock_position` | optional | position `UPSERT` |
| Intake Draft create | `stock_intake.created` / `stock_intake` | optional | Intake aggregate `UPSERT` |
| Draft header/item edit | `stock_intake.updated` / `stock_intake` | optional | Intake aggregate `UPSERT` |
| Draft discard | `stock_intake.discarded` / `stock_intake` | optional (decision may strengthen) | Intake `DEACTIVATE` if previously observable; otherwise no orphan notification |
| Opening post | `stock_intake.posted` / `stock_intake` | optional | Intake `UPSERT`; each movement `UPSERT`; each final position `UPSERT` |
| Receipt post | `stock_intake.posted` / `stock_intake` | optional | same as Opening |
| Damaged | `stock_movement.recorded` / `stock_movement` | required | movement `UPSERT`; position `UPSERT` |
| Expired | same | required | same |
| Owner withdrawal/use | same | required | same |
| Missing/loss | same | required | same |
| Positive/negative correction | `stock_movement.corrected` / `stock_movement` | required | movement `UPSERT`; position `UPSERT` |
| Movement reversal | `stock_movement.reversed` / `stock_movement` | required | reversal movement `UPSERT`; position `UPSERT` |
| Intake reversal | `stock_intake.reversed` / `stock_intake` | required | compensating Intake `UPSERT`; reversal movements `UPSERT`; positions `UPSERT`; original Intake `UPSERT` for derived effective state |
| Replacement Intake link/create/post | `stock_intake.replaced` / `stock_intake` plus normal post event | required for correction link | new Intake `UPSERT`, then normal movement/position publications on post |

Reads, rejected validation, no-op/conflict, search and diagnostics emit neither Audit nor changes. Position changes are not audited as arbitrary edits: movement or threshold action explains them. Correlation is the existing request ID; operation IDs remain optional future-compatible context, not replay keys.

## N. Phase 3 acceptance matrix

| Approved requirement | Owner WP | Verification/exit evidence |
|---|---:|---|
| Product create/edit/deactivate | 1 | API/UI, lifecycle conflicts, Audit/change, history retained |
| Product search/similar and duplicate names | 1 | identical-name and normalized search tests |
| One base unit/current selling price | 1 | schema/form validation and edit test |
| No barcode/Supplier/PO/conversion | 1 + 4 closure | schema/route/UI absence scan |
| Position expected/threshold/average/state/last/version | 1 schema; 2 mutation | schema check; posting snapshots/version/pointer tests |
| No arbitrary quantity/cost CRUD | 1 | route/repository negative tests, closure scan |
| Low-stock derived at `<=` | 1 | equality/below/above query/UI tests |
| Negative stock allowed/visible | 3 | outgoing below zero and UI warning |
| Opening as accountable Intake | 2 | Opening post creates item/movement/position evidence |
| Receipt Draft → Posted | 2 | draft edit then immutable post tests |
| Multi-line builder and atomic post | 2 | UI plus failure injection/all-or-nothing |
| Quantity/cost/line snapshots | 2 | precision/round-half-up vectors |
| `OPENING`/`STOCK_INTAKE` movements | 2 | movement source/sign/snapshot tests |
| Damaged/expired/Owner withdrawal/missing | 3 | one scenario each with reason/actor |
| Positive/negative correction | 3 | signed scenarios/basis policy |
| Reversal, one-direct-reversal guard | 3 | sequential/concurrent second reversal rejection |
| Intake correction/reversal/replacement | 3 | multi-line causal chain, immutable original |
| Posted evidence immutable | 2 + 3 | route/repository/DB privilege checks |
| WAC positive incoming | 2 | multiple-cost hand calculation |
| Outgoing retains average | 3 | each outgoing type snapshot test |
| Zero/negative retained basis | 2 + 3 | boundary golden vectors |
| Negative-to-positive Intake reset | 2 | exact crossing vector |
| `(19,4)/(19,6)/(19,2)/(38,12)` | 2 | schema and golden precision/overflow tests |
| Deterministic ascending Product locks | 2 | reversed-order two-connection test |
| Projection/movement same transaction | 2 + 3 | reconstructability and fault injection |
| Atomic multi-item post incl. Audit/change | 2 | every named failure point rollback |
| Tenant/Shop isolation | 1 foundation; 2–4 regression | foreign substitutions and composite FK tests |
| Permanent UUID/stable commands | 1–3 | canonical UUID and retry tests |
| Domain duplicate safety without Phase 10 | 1–3 | create/post/reversal races and conflicts |
| Permission separation/Owner/Staff | 1 registry; 2–4 flows | complete permission matrix |
| FULL/READ_ONLY/BLOCKED/CLOSED | 1 integration; 2–4 regression | read/write lifecycle matrix |
| Audit typed vocabulary/reason/redaction/causality | 1–3; 4 review | Audit UI/DB evidence and failure rollback |
| `sync_changes` authoritative resources | 1–3; 4 review | scope/kind/order/correlation/rollback tests |
| Late source columns/FKs strategy | 2 | schema artifacts/register and future owner entries |
| Responsive catalog/details/summary | 1 | phone/tablet/desktop/keyboard/zoom |
| Responsive Intake/history | 2 | builder/history device checks |
| Responsive manual/correction/reversal | 3 | dialog/form device checks |
| All universal UI states/form preservation | each slice; 4 closure | network/error/access/conflict matrix |
| Offline-aware, not offline-enabled | 1–4 | stable IDs/shapes plus absence of outbox/transport |
| Ready for Phase 4 Inventory consumption | 4 | frozen service contract and closure gate |

Ownership is unique at the business-capability level; entries showing multiple WPs distinguish introduction from mandatory regression/closure rather than duplicate implementation.

## O. Explicit deferred scope

Phase 3 deliberately provides no Customers; Sales/Sale Items/Sale Payments; Sale movement command/UI; Receivables/debt collection; Customer Returns; expenses/financial movements; physical Reconciliation/counts/variance adjustment; Suppliers; Purchase Orders; payables; general ledger/formal accounting; barcodes; carton/box/strip/tablet conversion; multi-branch workflow; messaging; advanced analytics/forecasting; export; automatic alert ledger; device registration; Dexie inventory cache/outbox; offline Product/Intake commands; push/pull, cursor, acknowledgement, receipt or replay protocol; offline PIN; `sync_receipts`; generic durable idempotency/event sourcing.

The nullable later-source columns and reserved controlled values are compatibility schema only. They justify no Phase 4+ route, service, button, navigation label or mock response.

## P. Recommended implementation and review order

1. **WP1 — Catalog and Stock Projection Foundation.** Review migration fidelity, closed vocabulary/permission changes and absence of balance setters before merging. Resolve Product-position materialization and permission naming.
2. **WP2 — Opening Stock, Receipt Intake and WAC Posting.** Review decimal vectors and transaction/lock order first; require MySQL two-connection and fault-injection evidence before merging. Resolve duplicate Product line and Opening multiplicity policy.
3. **WP3 — Accountable Non-Sale Movements and Append-and-Correct.** Review correction cost policy and Intake compensation model before coding; require reversal race and immutable-history evidence.
4. **WP4 — Integrated Inventory Experience and Phase 3 Closure.** Merge only when every acceptance row is evidenced, documentation/generated assets are current, and the Phase 2 verification-history discrepancy has a factual addendum or is an explicit release blocker.

Each package is a vertical, independently reviewable delivery. Do not begin the next package merely because code exists: its predecessor's Definition of Done, migration state, manual/runtime evidence and documentation must be accepted. Phase 4 begins only after WP4 closure establishes reconstructable, audited stock truth.
