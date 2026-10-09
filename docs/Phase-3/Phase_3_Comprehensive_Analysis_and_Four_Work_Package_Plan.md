# Phase 3 — Comprehensive Analysis and Four-Work-Package Delivery Plan

**Prepared:** 2026-10-06
**Status:** revised planning baseline; implementation must not begin until this document is reviewed and approved
**Authority:** Business & Product Blueprint → Technical Blueprint → Database / Schema Design v1.1 → Strategic Phased Implementation Roadmap v1.1 → Phase 1/2 records → current repository → supplied database evidence

## A. Executive Assessment

DawaTrack is structurally ready for Phase 3. The current CodeIgniter modular monolith, authenticated `ShopContext`, access modes, deny-by-default permission service, typed immutable Audit boundary, and Phase 2 commit-ordered change publisher are the correct foundations. Phase 3 must extend them, not create Inventory-specific substitutes.

The approved delivery architecture remains exactly four vertical packages:

1. **WP1 — Catalog and Stock Projection Foundation**
2. **WP2 — Opening Stock, Receipt Intake and WAC Posting**
3. **WP3 — Accountable Non-Sale Movements and Append-and-Correct**
4. **WP4 — Integrated Inventory Experience and Phase 3 Closure**

### Approval gates and resolved senior-review rules

The following rules are now frozen for implementation:

* Acceptance uses documented **manual verification**: controlled CLI, SQL, MySQL two-session, HTTP/browser, failure-injection, responsive, keyboard and code-review procedures. Phase 3 introduces no automated test framework or suite.
* Product `name` and `current_selling_price` remain mutable and audited. `unit_name` is mutable only until the first committed Stock Movement for that Product in any Shop; thereafter it is immutable. A changed base unit requires deactivating/preserving the old Product and creating a new Product.
* `OPENING` eligibility is per Product/Shop position. It requires a locked, truly unestablished position: `last_movement_id IS NULL`, `cost_basis_state = UNESTABLISHED`, and no committed movement for that position. A different unestablished Product may receive Opening later.
* One Intake may contain multiple lines for the same Product. The service locks each unique position once in ascending internal Product ID, then applies that Product's lines in ascending Intake `line_number`, with one movement per item.
* Ordinary Draft Intake create/edit/item removal/discard emits neither permanent Audit nor `sync_changes`. Posting and correction/reversal are authoritative and emit both.
* A posted Intake reversal uses a new posted reversal header of the **same** `intake_kind`, linked by `reversal_of_intake_id`, with **no Intake Items**. Its compensating movements use `source_kind=REVERSAL` and each links its original movement. The header and movements share workflow correlation. No third kind or negative Intake Item is invented.
* An Intake can be directly reversed only when its movements are still the latest contiguous movement chain for every affected locked position. Then inverse application in reverse original application order can safely restore the verified prior cost state. If later movement exists, direct reversal is rejected and the user must use reasoned correction and, when needed, a replacement Receipt.
* Positive `CORRECTION` changes quantity but retains an existing cost basis and performs no Intake WAC. With no basis ever established, it leaves `UNESTABLISHED`; only a cost-bearing Intake establishes/resets acquisition basis.
* Inactive Products reject new Opening, Receipt and later Sale selection, but remain readable and accept authorized damaged, expired, withdrawal, loss, correction and reversal activity so physical stock remains accountable.
* Drift checking is read-only operator tooling/runbook procedure, never a Shop repair feature or second source of truth.
* Every visible surface is complete, authorized, useful and available with equivalent capability on phone and desktop. No placeholder, dead control, future-phase navigation, half-styled flow or desktop-only capability is acceptable.
* Backend and frontend delivery must remain focused and maintainable. Existing large Shop files must not become Phase 3 monoliths.

### Prerequisites

Before WP1 closes:

1. add the Phase 3 permission/Audit/change vocabularies required by WP1;
2. add the ordered v1.1 migrations and paired SQL only as each package owns them;
3. complete a dated, append-only Phase 2 runtime-verification addendum recording the later environment and evidence without rewriting the historically blocked reports; and
4. preflight the actual implementation database rather than relying permanently on a supplied snapshot.

**Readiness conclusion:** WP1 may begin after this plan is approved. Phase 4 Sales may not begin until WP4 proves the complete stock truth boundary under concurrency, rollback, isolation, lifecycle, responsive and maintainability review.

## B. Current Codebase / Database State

### B.1 Application architecture and conventions

* The project is a PHP 8.2 / CodeIgniter 4.7 modular monolith using Shield 1.4.1. Explicit routes are in `app/Config/Routes.php`; auto-routing is disabled.
* Current module boundaries are `Access`, `Audit`, `BrandingDomain`, `Identity`, `Subscription`, `Sync` and `TenantShop`. Controllers map transport; owning services contain business transactions. Product Catalog and Inventory modules do not yet exist.
* `ApiResponse` returns the common `{success,data,meta.request_id,error}` envelope. `ApiException` carries safe stable error codes and field details. Ordinary future collections use capped pagination; Audit alone uses opaque keyset cursors.
* Global request correlation, CSRF, session security, authentication and response-security filters already exist. Platform Administration is a separate Shield group/MFA/filter authority and is not a shortcut into Shop Inventory.
* Migrations use explicit MySQL SQL when Forge cannot preserve checks, collations or composite FKs. Conventions are `BIGINT UNSIGNED`, UUIDv4 `CHAR(36)` with ASCII binary collation, UTC `DATETIME(6)`, InnoDB, `utf8mb4_unicode_ci`, deterministic `fk_/uq_/ix_/ck_` names and `RESTRICT` for history.

### B.2 Shop frontend actually present

* The Shop is a Vue 3 Composition API mount in the server-rendered shell, with native ES modules, native Fetch, self-hosted Vue and compiled Bootstrap/Sass. Editable sources are under `resources`; generated deployable assets are under `public/assets`.
* `resources/js/api/http-client.js` already centralizes credentials, CSRF refresh, request IDs, timeout/network errors and session/MFA transitions. `useReasonDialog` supplies focus entry/return, trap, Escape and pending behavior. Audit uses responsive cards rather than squeezed tables.
* `resources/js/shop/app.js` and `render.js` are already substantial. Phase 3 must add focused Product/Inventory API modules, composables and components rather than jam new state, markup and business branching into those files.
* Existing presentation distinguishes `FULL`, `READ_ONLY`, `BLOCKED` and `CLOSED`, with loading/error/empty feedback. Phase 3 extends those patterns consistently.

### B.3 Identity, tenancy, authorization and access modes

* `ShopContext::resolve()` derives Tenant/Shop from the authenticated user and verified hostname. Request-supplied scope never grants authority; unavailable/foreign context is non-enumerating.
* `AccessGate::evaluate()` produces `FULL`, `READ_ONLY`, `BLOCKED` or `CLOSED`. Existing `mutableContext()` rejects every non-`FULL` write.
* `PermissionService::allows()` validates the closed `PermissionCode` enum, gives the Primary Owner normal Tenant authority by role, and requires active explicit Staff grants otherwise. Unknown codes fail closed.
* Existing codes include `inventory.view` and `inventory.adjust`; Phase 3 needs `product.manage`, `inventory.intake` and `inventory.reverse` to satisfy the approved separation.

### B.4 Audit boundary

* `AuditService::append(AuditAppendCommand)` is the only writer. Typed commands carry explicit actor authority, allow-listed action/category/resource, permanent public resource UUID, policy-controlled reason, bounded metadata, optional causal Audit ID and bounded occurrence/operation context.
* Authority and causal validators enforce active membership and identical Tenant/Shop scope. Metadata rejects secrets, request dumps and oversized/unsafe structures. Invalid Audit aborts the owning transaction.
* Request correlation comes from `RequestContext`; Device attribution remains null. Owner/Platform Audit reading is immutable and redacted. Inventory must extend this vocabulary, not create another Audit mechanism.
* Draft/uncommitted, mutable master/configuration and posted/finalized evidence are distinct. Ordinary Draft Intake editing is not permanent accountability evidence.

### B.5 Phase 2 change sequence and transaction order

* `sync_generations` has one active generation; `sync_changes` is the Phase 2 notification/index table.
* `ChangeSequenceService::publish()` requires an active caller transaction, validates allow-listed resource/public UUID and scope, locks the active generation, then publishes ordered `UPSERT`/`DEACTIVATE` notifications with correlation. It contains no payload and exposes no transport.
* Required order is all domain locks → validate/mutate business state → Audit → active-generation lock and changes → commit. No domain lock is acquired after publication begins.
* Current resource vocabulary ends at Phase 1 resources. Phase 3 will add `product`, `stock_position`, `stock_intake` and `stock_movement` only when their first authoritative writer arrives.
* There is no pull, cursor, Device, receipt, outbox, generic replay or event sourcing, and Phase 3 adds none.

### B.6 Database evidence and actual Phase 3 state

No current SQL dump is checked into the repository. A separately supplied **October 6 DawaTrack database dump** was reviewed and is consistent with the expected pre-Phase-3 state: the Phase 1/2 foundation exists and the five Phase 3 domain tables do not yet exist.

The repository itself contains six ordered Phase 1 migrations and paired SQL files plus the Phase 2 `sync_changes` migration/SQL. It contains no `products`, `stock_positions`, `stock_intakes`, `stock_intake_items` or `stock_movements` migration/table artifact.

The supplied dump is review evidence, not a permanent substitute for implementation-time inspection. Before WP1 changes, record sanitized output from:

* `php spark migrate:status`;
* MySQL server/version/session-mode/time-zone inspection;
* table inventory and migration records;
* `SHOW CREATE TABLE` for foundation parents and, after each package, new tables;
* active generation and required composite parent-key inspection.

### B.7 Phase 2 verification record discrepancy

The owner reports Phase 2 delivered and tested. Checked-in reports dated October 4–5 accurately say their original environment lacked Composer/MySQL/browser runtime and marked those checks blocked. Preserve both facts through a dated append-only addendum:

```yaml
original delivery environment: blocked
later validation environment: executed result
```

The addendum records environment, PHP/CI4/Shield/MySQL/browser versions, exact procedures/commands, MySQL schema and two-session concurrency checks, HTTP/browser checks, controlled failure injection, results and evidence references. It must be complete no later than WP1 closure and is an explicit Phase 3 acceptance dependency.

## C. Phase 3 Architectural Interpretation

Phase 3 establishes trustworthy stock truth before Sales can depend on it.

### C.1 Product and projection

* `products` is a Tenant master. Identical and similar normalized names are legal; normalization supports search/warning only.
* Name and current selling price may change through audited commands. Base unit defines the meaning of every historical quantity; after the first committed movement anywhere for the Product, `unit_name` cannot change.
* `stock_positions` is current Shop/Product projection and lock target, not an editable balance. Threshold is its only independent mutable business field. Quantity, average cost, cost state, last movement and version change only with movement evidence.
* Low stock is derived as `expected_quantity <= low_stock_threshold`; negative stock is permitted and visibly exceptional. There is no low-stock alert ledger.

### C.2 Intake and movement evidence

* Opening Stock is an `OPENING` Intake and establishes a Product/Shop position only before any movement history. It is not a current-balance setter.
* Later genuine acquisition is `RECEIPT`. Draft headers/items may change until post. Posted header/items/movements are immutable.
* Multiple lines for one Product are legal cost batches. Locks operate on unique Products; evidence remains one item and one movement per line.
* Phase 3 exposes exactly `OPENING`, `STOCK_INTAKE`, `DAMAGED`, `EXPIRED`, `OWNER_WITHDRAWAL`, `MISSING_LOSS`, `CORRECTION`, `REVERSAL`. Reserved later values create no command or UI.
* Every projection quantity change and its movement commit together. No generic balance, cost or posted-evidence update/delete API exists.

### C.3 Decimal and WAC invariant

Authoritative values are canonical decimal strings and MySQL fixed-precision operations: quantity `DECIMAL(19,4)`, unit/average cost `DECIMAL(19,6)`, extended amount `DECIMAL(19,2)`, intermediate arithmetic `DECIMAL(38,12)`, with explicit half-up rounding. PHP or JavaScript floating point never determines stored results.

A focused Inventory decimal repository should use explicit MySQL `CAST`/`ROUND` on the same transaction connection. This avoids adding a dependency and matches the MySQL 8.4 canonical store. Deterministic manual vectors verify every boundary; no automated test suite is introduced.

### C.4 Locking and transaction invariant

Every posting or movement transaction:

1. begins and locks the aggregate/original row;
2. resolves all Products inside server-derived Tenant/Shop scope;
3. sorts unique internal Product IDs ascending;
4. safely materializes missing zero positions without changing quantity/cost;
5. locks each position once in ascending Product ID;
6. validates lifecycle/history/decimal/source rules under lock;
7. for repeated Product lines, applies lines in ascending `line_number`;
8. inserts one movement per effect and updates position/last movement/version;
9. makes the lifecycle/link transition;
10. appends typed Audit when the operation is authoritative;
11. publishes required changes after all domain locks;
12. commits or rolls everything back.

Browser order never controls lock order. A bounded deadlock retry may restart the whole stable-UUID command, but no partial catch/commit is allowed.

### C.5 Duplicate safety before Phase 10

Permanent public UUIDs, unique indexes, aggregate/original row locks, conditional lifecycle transitions, deterministic position locks and the unique direct-reversal link provide domain-specific duplicate safety. Repeat posting returns a specific conflict without another effect; competing reversal loses at the unique guard. UI pending state is only ergonomics. There is no generic idempotency key, durable response replay, `DUPLICATE_OPERATION` or `sync_receipts`.

### C.6 End-to-end and product-quality invariant

A capability is delivered only when Database → service/repository → authorization → Audit → required `sync_changes` → API → Shop capability/navigation → complete responsive frontend → universal states → manual verification → documentation is connected. A route without usable UI, UI without a working authorized command, desktop-only action, placeholder card, mock metric or future-phase link fails acceptance.

## D. Senior Review Decisions Incorporated

| Review issue | Frozen rule | Downstream dependency |
|---|---|---|
| Verification approach | Manual documented CLI/SQL/two-session/HTTP/browser/failure procedures; no new automated framework/suite | Every WP delivery and closure report |
| Base-unit safety | Unit editable only before Product has any movement; query relational movement evidence; no schema column | Phase 4 quantity meaning and Sale unit |
| Dump wording | Repository has no dump; separately supplied Oct 6 dump confirms pre-Phase-3 state; live preflight still required | Migration safety |
| Phase 2 history | Append later runtime evidence; never rewrite originally blocked status | WP1 closure/Phase 3 acceptance |
| Opening eligibility | Per locked position, no prior movement/pointer and unestablished state; staged Products allowed | Intake post concurrency |
| Duplicate Product lines | Legal; lock unique position once, line order ascending, one movement/item | Deterministic WAC |
| Draft evidence | No Audit/change for ordinary Draft CRUD/discard; posting starts authoritative evidence | Phase 10 may reconsider Draft synchronization |
| Intake reversal shape | Same-kind, posted, zero-item reversal header + linked reversal movements/correlation | Queries, Phase 4, future sync |
| Intake reversal costing | Direct only while original chain is current; reverse chain in reverse order; otherwise reject for correction/replacement | Current WAC and future COGS |
| Positive correction | Retain existing basis; remain unestablished if none; never perform acquisition WAC | Phase 4 Sale costing |
| Inactive Product | Blocks new acquisition/Sale selection; allows accountable cleanup/correction/reversal and history | Truthful stock cleanup |
| Drift diagnostics | Internal/operator, read-only, non-repairing | Operations only, no Shop feature |
| UI/code quality | Complete coherent surfaces, strict phone/desktop parity, focused files/classes, no dead UI | All WPs and WP4 closure |

There is no remaining semantic decision required before WP1–WP3. Any proposed deviation requires architectural review because Phase 4 and future sync consume these contracts.

## E. Four-Work-Package Summary Matrix

| WP | Title | Main capability | Schema | Dependency | Exit result |
|---:|---|---|---|---|---|
| 1 | Catalog and Stock Projection Foundation | Product lifecycle/search and protected stock/threshold visibility | `products`, `stock_positions` base | Phase 1 context/access; Phase 2 Audit/change | Complete responsive catalog and stock summary with immutable-after-history unit rule |
| 2 | Opening Stock, Receipt Intake and WAC Posting | Draft builder and atomic authoritative acquisition posting | `stock_intakes`, `stock_intake_items`, `stock_movements`, late position pointer FK | WP1 products/positions | Opening/Receipts create deterministic movement evidence and WAC |
| 3 | Accountable Non-Sale Movements and Append-and-Correct | Manual accountable changes and exact protected reversal/correction | no new table | WP2 ledger/locking/cost state | All approved non-sale effects are reasoned, linked and immutable |
| 4 | Integrated Inventory Experience and Phase 3 Closure | Coherent production UX, operator diagnostics and complete acceptance | no planned feature schema | WPs 1–3 | Phase 3 is proven end-to-end and safe for Phase 4 |

## F. Detailed WP1 — Catalog and Stock Projection Foundation

### Objective and ordering

Deliver a usable Product catalog and Shop stock-summary foundation before evidence references Products. Users can create/search/detail/edit/deactivate Products, change thresholds, and see low/negative state. Nothing can set balance or cost.

### Scope

* Product create, paginated search/list, similar-name advisory, detail, allowed edit and deactivation.
* Duplicate names/normalized names accepted as distinct Products.
* One base unit and current selling price.
* Unit editable before any committed Product movement; permanently immutable afterward across all Shops.
* Position materialized atomically for the current one-Shop Tenant, with safe lazy-create support for operational resilience.
* Stock summary/detail, threshold edit and derived low/negative views.
* Minimum coherent surfaces: Product/Stock list with search and filters; create/edit form; Product detail combining Product information, Stock left, threshold and current cost-state summary. No history/action advertised before WP2/WP3 exists.

### Explicit non-scope

No Intake, movement, WAC mutation, correction, reversal, Supplier/barcode/package conversion, Sales or offline mutation. A zero position is not Opening Stock.

### Existing code it builds upon

`ShopContext`, `AccessGate`, `PermissionService`, `PermissionCode`, typed Audit services, `ChangeSequenceService`, `ApiResponse`/`ApiException`, `RequestContext`, explicit routes, CSRF/session filters, Shop shell, HTTP client, feedback/dialog patterns and Bootstrap/Sass build.

### Database work

1. Paired migration/SQL: `products` exactly v1.1—public UUID, Tenant, name/normalized name/unit/current price/status/provenance/timestamps; checks; `UNIQUE(public_id)`, non-unique normalized index, status/name index, `UNIQUE(tenant_id,id)`, RESTRICT parents.
2. Paired migration/SQL: `stock_positions` without circular pointer FK—same-scope unique, signed expected quantity, nonnegative threshold/cost, cost-state check, nullable pointer, unsigned version, expected-quantity index, composite Shop/Product FKs.
3. Register both and document rollback dependency. Do not add a unit-lock flag: movement existence is authoritative relational evidence.
4. Product creation atomically inserts the current-Shop zero/unestablished position so threshold and summary are immediately useful. Safe lazy materialization must be insert-if-absent/no-value-change then lock/read.

### Backend/domain work

* Create focused `ProductCatalog` commands, validator/normalizer, service and scoped query object; focused Inventory position query/threshold service. No generic immutable-record save/delete.
* Product edit locks scoped Product. Name/price validation is ordinary mutable-master logic. A requested unit change queries `stock_movements` joined/scoped to Product under the Product lock; if any committed row exists, return `PRODUCT_UNIT_IMMUTABLE` (`409`) with a safe instruction to create a new Product. Name/price may still change in the same UI after the unit field is restored; never partially accept a failed command.
* Concurrency rule: Product lock serializes unit edit against first posting. Posting must lock/read Product after Draft aggregate and before position application; a unit edit either commits before first movement or observes movement afterward—never races into historical reinterpretation.
* Deactivation is `ACTIVE→INACTIVE`, reasoned, preserves position/history and rejects repeat. It does not imply disposal of remaining stock.
* Threshold command accepts only threshold, increments position version, and cannot touch quantity/cost/pointer.
* Search is Tenant scoped and normalized; similarity never blocks/merges.

### Authorization and security

* `inventory.view`: Product/position/low/negative reads.
* `product.manage`: create/edit/deactivate.
* `inventory.adjust`: threshold update.
* Owner is allowed by role; Staff requires exact active grant. Every write requires `FULL`; READ_ONLY retains reads; BLOCKED/CLOSED has no normal Shop operation.
* Context/actor come only from session/hostname. Foreign UUIDs return non-enumerating not-found. CSRF and server validation apply.

### API work

Thin Product collection/detail/create, explicit edit/deactivate, Inventory summary/low filter and explicit threshold command. External identifiers are UUIDs. Product edit accepts mutable fields but returns `PRODUCT_UNIT_IMMUTABLE` when unit evidence exists. There is no generic delete, position create, quantity/cost patch or arbitrary field patch.

### Audit work

Add `product.created`, `product.updated`, `product.deactivated`, `stock.threshold_updated`; resources `product`, `stock_position`, category `INVENTORY`. Create/update/threshold reason is optional; deactivation reason required. Metadata contains bounded field names and scalar before/after. Successful pre-history unit change is audited; rejected unit change writes no false Audit.

### `sync_changes` work

Allow `product`, `stock_position`. Product create publishes Product and initial position `UPSERT`; edit publishes Product `UPSERT`; deactivate Product `DEACTIVATE`; threshold publishes position `UPSERT`. Same transaction/correlation, after domain locks.

### Frontend work and parity

* Focused Product API module, catalog composable and Product/stock components; do not expand `shop/app.js`/`render.js` into a monolith.
* Desktop denser list/grid and mobile stacked cards expose identical create/search/filter/detail/edit/deactivate/threshold capabilities.
* Edit UI allows unit before evidence. After movement exists it renders unit read-only with plain explanation; server remains authoritative against stale clients.
* Deactivation explains history and cleanup remain available later; WP1 shows no dead cleanup button.
* Similar-name advisory is nonblocking. Forms preserve values after recoverable errors.
* Apply established branded form, button, spacing, validation, dialog and card styles—no raw/default controls or one-off inconsistent UI.
* Handle loading, empty, success, validation, conflict, permission, READ_ONLY, BLOCKED/CLOSED, network, timeout, server and pending states. Offline says connection required and does not claim queueing.

### Documentation

Migration register/schema notes; Product/Inventory/module map; API/protected-resource docs; permission catalogue; Audit/change vocabulary/publication docs; configuration only if actually changed; operations/manual verification; WP1 delivery report; Phase 2 runtime addendum.

### Manual verification

1. Preflight live DB and supplied-dump assumptions; apply/rollback/reapply paired migrations on disposable MySQL 8.4; compare `SHOW CREATE TABLE`.
2. Create two identically named Products; search normalized/similar values; paginate/status-filter.
3. Create Product and change unit before any movement fixture: allowed and audited/published.
4. Establish first committed movement through controlled WP2-ready fixture/procedure; attempt unit change: `PRODUCT_UNIT_IMMUTABLE`, no row/Audit/change. Name and price remain editable with permission.
5. Race first movement procedure against unit edit in two MySQL/HTTP sessions; prove serialization and one coherent unit meaning.
6. Deactivate with stock fixture; verify Product/history/position remain readable and status does not zero stock.
7. Threshold equality/below/above; verify version increments and quantity/cost/pointer do not change.
8. Prove no generic balance/cost/delete route or repository operation.
9. Force Audit insertion failure and change-publication failure using controlled verification hooks in a non-production environment; confirm full rollback; remove/disable hooks after verification.
10. Foreign UUID/hostname/scope, Owner, each Staff grant combination, unknown code, deactivated member and all access modes.
11. 320/360/390px, tablet, desktop, keyboard and 200% zoom; verify exact capability parity, focus, error association and no dead surface.
12. Code review: focused classes/components, readable typed methods, no copied authorization/validation, no giant controller/render additions, no unused/debug code.

### Definition of Done

Schema/runtime evidence passes; complete catalog/summary works end-to-end; duplicate names work; unit becomes immutable exactly at first movement while name/price remain mutable; access/isolation/Audit/changes are atomic; no balance setter/dead UI exists; phone and desktop capabilities match; code quality review passes; Phase 2 runtime addendum is checked in.

### Handoff

WP2 receives stable scoped Products, zero positions, Product locking/unit invariant, position locking/materialization, decimal input conventions, permissions and vocabularies. It must lock/read active Product and cannot bypass unit/history rules.

## G. Detailed WP2 — Opening Stock, Receipt Intake and WAC Posting

### Objective and ordering

Deliver Draft → Posted Opening/Receipt Intake end-to-end, introducing the immutable ledger and highest-risk decimal/concurrency primitive only after Product/position scope is stable.

### Scope

* Server-saved Draft `OPENING`/`RECEIPT` headers and positive items; create/edit/remove/discard while Draft.
* Phone/desktop multi-item builder, review and explicit post.
* Multiple lines for the same Product at different costs.
* Per-position Opening eligibility and staged onboarding.
* `OPENING`/`STOCK_INTAKE` movements, line amount snapshots, WAC, position/version/pointer and authoritative history.
* Posted evidence immutable. WP3 owns reversal controls.

### Explicit non-scope

Manual movement/correction/reversal, Supplier/PO/payable, Sales/Return/Reconciliation sources, Draft synchronization/offline queue, balance overwrite.

### Database work

1. `stock_intakes` paired migration/SQL with v1.1 scope/kind/status/occurrence/posting/correction links/correlation/provenance, indexes, checks and unique nullable guards.
2. `stock_intake_items` with positive quantity/nonnegative unit cost/rounded line snapshot/line number, public/scoped uniques and RESTRICT scoped header/Product FKs. No Product uniqueness constraint.
3. `stock_movements` with all v1.1 columns, source/sign checks, indexes, position/Product/item/self-reversal FKs and future nullable indexed `sale_item_id`/`reconciliation_variance_id` columns.
4. Separate paired migration adds `stock_positions.last_movement_id` FK after movements exist.
5. Do not add absent-parent FKs. Register Phase 4 sale-item and Phase 7 reconciliation-variance FK ownership.
6. Use explicit Draft cleanup with RESTRICT. Ordinary posted headers require items; WP3's documented zero-item reversal header is the sole service-level exception.

### Backend/domain work

* Focused Intake commands/DTOs, Draft repository, posting service, movement append repository, decimal calculator and scoped history query. Draft operations never access posted rows.
* Line numbers are unique and stable. Duplicate Product lines are valid. Validate each line independently; do not coalesce evidence.
* Posting locks header and verifies `DRAFT`, loads items ordered by `line_number`, validates active same-Tenant Products, builds unique Product IDs, locks positions once ascending, then groups/applies each Product's lines ascending by line number. Persist one movement per item.
* Opening line under the locked position requires all: pointer null, state `UNESTABLISHED`, and scoped movement nonexistence. Expected quantity should be zero by invariant; any contradiction is `INVENTORY_STATE_CONFLICT`, not overwritten. Reject ineligible line as `OPENING_ALREADY_ESTABLISHED` and roll back the entire multi-line post.
* This permits Product A Opening now and Product B Opening later. Any later acquisition for A is Receipt; mistake correction waits for WP3.
* Receipt rejects inactive Product. Opening also rejects inactive Product.
* Posting computes `line_cost_amount=ROUND(quantity*unit_cost,2)` and WAC with decimal `(38,12)` intermediates. Header becomes POSTED only after every movement/position succeeds.
* Repeated/concurrent post locks the header; one succeeds and later attempt returns `INTAKE_ALREADY_POSTED`. Product/unit edit serialization follows WP1 Product locking.

### Deterministic WAC line application

For each Product position `(Q,A,state)` and each positive line `(I,C)` in ascending `line_number`:

* `Q>0`: `newA=ROUND(((Q*A)+(I*C))/(Q+I),6)`, `VALID`.
* `Q<=0` and `Q+I<=0`: retain `A`; remain `UNESTABLISHED` if no basis ever existed, otherwise `RETAINED`.
* `Q<=0` and `Q+I>0`: reset `newA=C`, `VALID`; negative quantity is excluded from WAC.
* persist before/after snapshots, then feed the after state into the next same-Product line.

Line order therefore intentionally matters around a negative-to-positive crossing and is evidenced by line number and movement sequence.

### Authorization/security

`inventory.intake` owns Draft CRUD and posting; `inventory.view` owns reads. Owner derives authority; Staff needs exact grant. `FULL` for writes, READ_ONLY for history, BLOCKED/CLOSED denied. Posting confirmation explains permanence. Scope/actor and server post time are server-owned; occurred time/offset are context only.

### API work

Thin Intake list/detail, Draft create/header/item update/remove/discard and explicit post commands. Payload uses public IDs and decimal strings. There is no generic status patch. Draft endpoints are online only and make no synchronization promise.

### Audit work

Ordinary Draft create/edit/item removal/discard: **no permanent Audit by default**. No current repository policy requires Draft Audit. Post appends `stock_intake.posted` / `stock_intake` with kind, bounded line count/rounded total/occurrence and affected-resource summary. Movements remain relational evidence; avoid one redundant Audit event per line. Rejected/duplicate post emits none.

### `sync_changes` work

Draft create/edit/remove/discard: **no change publication**. On post, allow/publish `stock_intake UPSERT`, each `stock_movement UPSERT`, and one final `stock_position UPSERT` per affected position. Intake items are part of the posted Intake aggregate, not separate notification resources. Phase 10 decides future Draft synchronization.

### Frontend work and parity

* “Opening stock” and “Receive stock” use the same focused builder with fixed kind and clear eligibility guidance.
* Mobile stacked line cards and desktop keyboard-efficient layout provide identical add/search/edit/remove/reorder/review/save/post capability. Repeated Product selection creates another visible cost-batch line rather than silently merging it.
* Show unit, quantity, Cost price, rounded line amount and deterministic line order. Review makes duplicate batches understandable.
* Opening UI hides/disables already-established Products based on current response, but stale attempts still receive `OPENING_ALREADY_ESTABLISHED` and preserve Draft.
* Pending prevents accidental resubmit; authoritative `409` reloads detail. Offline/network errors state connection required and retain in-memory entry where possible.
* No WP3 reverse button until WP3 is complete.

### Documentation

Migration/late-FK register and schema notes; Inventory lifecycle/source/WAC/locking docs; API/permission/Audit/change docs; posting runbook; manual deterministic vectors and two-session procedures; WP2 delivery report.

### Manual verification

1. Apply/rollback/reapply migrations and inspect exact tables/checks/indexes/FKs/future columns on MySQL 8.4.
2. Create/edit/remove/discard Draft; inspect `audit_events`/`sync_changes` and prove no Draft rows were emitted.
3. Post Draft; confirm only authoritative Audit/change evidence appears and all items become immutable.
4. Post Opening for Product A; second Opening for A rejects. Later Opening for untouched Product B succeeds. Receipt adds later stock to A.
5. Race two Opening Intakes for the same unestablished Product in two sessions; hold A's position/header lock, observe B block, then one succeeds and one gets `OPENING_ALREADY_ESTABLISHED` with no partial effects.
6. Same Product twice at two costs: position locked once, lines applied by ascending line number, one movement/item, manual WAC matches.
7. Repeat duplicate-line vector from negative stock where first line remains non-positive and second crosses positive; verify crossing line cost resets basis. Reverse displayed browser order but retain line numbers and confirm deterministic result.
8. Post single/multi-product Receipt; manually verify line rounding, positive WAC, zero, negative-remains-negative and negative-to-positive cases.
9. Two concurrent Receipts for one Product; then two multi-product Receipts submitted in reverse Product order. Use MySQL Session A/B to observe blocking/serialization; inspect positions, movements, Audit and changes.
10. Repeat post sequentially/concurrently; exactly one effect.
11. Controlled failure at movement insert, each position update, lifecycle update, Audit insertion, change insertion and commit boundary; verify Draft/all projections/evidence roll back.
12. Reject inactive Product, foreign Product/item/Intake, zero/negative/overflow/scale and empty Draft.
13. Owner/Staff/access-mode matrix and historical reads in READ_ONLY.
14. 320/360/390px/tablet/desktop/keyboard/200% zoom parity; recoverable errors preserve fields.
15. Review file/class/component size, duplication, naming, transactions and absence of dead UI/debug code.

### Definition of Done

Draft and posted flows are complete and responsive; Draft activity emits no unintended permanent evidence; Opening eligibility is race-safe and per position; duplicate Product lines produce deterministic one-item/one-movement evidence; all decimal vectors match manual calculations; retries/concurrency/failure injection/isolation pass; posted evidence is immutable; code/documentation quality passes.

### Handoff

WP3 receives a protected Intake aggregate, immutable movement repository, deterministic position application, exact cost snapshots, history queries and sorted-lock transaction pattern. It must use these rather than mutate posted evidence.

## H. Detailed WP3 — Accountable Non-Sale Movements and Append-and-Correct

### Objective and ordering

Deliver damaged, expired, owner withdrawal, missing/loss, signed correction and protected reversal/replacement while preserving current stock truth, original evidence and cost-basis correctness.

### Scope

* Negative `DAMAGED`, `EXPIRED`, `OWNER_WITHDRAWAL`, `MISSING_LOSS`.
* Positive/negative `CORRECTION` with frozen basis behavior.
* Manual-movement reversal.
* Posted Intake direct reversal under strict current-chain eligibility; exact zero-item reversal header model.
* Optional replacement Intake linked to original after reversal, or reasoned correction/replacement path when direct reversal is unsafe.
* Inactive-Product accountability cleanup.

### Explicit non-scope

Sale/Return/Reconciliation movement, physical count, financial cash effect of withdrawal, Supplier/PO, offline dispatch, arbitrary repair.

### Database work

No new table. Confirm the existing movement check/service rules:

* `MANUAL`: none of the explicit source FKs; allowed manual type/sign; required reason.
* `INTAKE_ITEM`: exactly Intake item; opening/intake, positive.
* `REVERSAL`: exactly `reversal_of_movement_id`; movement type `REVERSAL`; exact negated original quantity.
* future sources remain unreachable.

Cross-row equality/current-chain eligibility is service-enforced under locks because MySQL CHECK cannot query other rows. Unique `reversal_of_movement_id` remains the race arbiter. Any discovered corrective DDL requires paired migration/SQL and review; no shadow correction table.

### Backend/domain work

* Focused manual, movement-reversal, Intake-reversal and replacement commands/services. Commands accept Product/original public IDs, allowed direction/type, occurrence and reason—not source kind, actor, before/after or internal scope.
* Manual outgoing locks the position, retains average cost and moves a valid basis to `RETAINED` if stock becomes non-positive. Negative stock remains legal.
* Positive/negative Correction changes quantity and retains existing average. Existing `VALID` stays valid while result positive and becomes `RETAINED` when result non-positive; existing `RETAINED` remains retained unless a real Intake later resets it; `UNESTABLISHED` remains unestablished regardless of positive corrected quantity. Correction never performs WAC or invents unit cost. Its `unit_cost_basis` snapshots the current retained/valid basis, or zero only with `UNESTABLISHED` as an explicit “no basis,” never `VALID zero` by accident.
* Inactive Product rejects new Opening/Receipt but accepts these authorized accountability commands and reversals. UI marks it inactive and warns it cannot be received/sold, without hiding stock/history.
* Manual movement reversal locks original and position, rejects reversal of a reversal as a shortcut, rejects already reversed, negates quantity, links original and applies the normal non-acquisition basis-retention rule.
* Intake reversal follows the row model and costing decision below. It is whole-Intake, not partial. A correction handles a line-specific historical problem.
* Replacement is a normal new Draft Intake linked by `replacement_for_intake_id`, then independently validated/posted. Link reason/evidence does not bypass Opening eligibility; normally replacement is `RECEIPT` unless the reversal restored a truly unestablished position and the replacement legitimately re-establishes it.

### Inventory Reversal Costing Decision

#### Manual/non-acquisition movement reversal

* Quantity delta is exact negative of original.
* `unit_cost_basis` snapshots the current position basis (or zero with `UNESTABLISHED`), because this is not acquisition WAC.
* `average_cost_before` is current locked average; `average_cost_after` remains equal.
* State follows current quantity: valid positive basis may become `RETAINED` at non-positive; retained/unestablished is not made valid by a positive reversal.

#### Intake direct reversal eligibility

For every affected position, while all are locked:

1. original Intake is posted and has no prior Intake reversal;
2. every original Intake movement exists and is unreversed;
3. the original movements form the current **latest contiguous suffix** of that position's movement chain, ending at `stock_positions.last_movement_id`;
4. their stored before/after quantity, average and state transitions chain exactly; and
5. no later incoming, outgoing, correction or reversal movement exists.

Repeated Product lines are part of this suffix. If any condition fails, return `INTAKE_REVERSAL_NOT_CURRENT` and explain that later activity prevents safe direct undo; use a reasoned quantity Correction and optional replacement Receipt. Never silently approximate.

#### Eligible Intake reversal application

* Lock the current position; do not trust stale snapshots without the suffix proof.
* Process original movements in reverse application order (per Product descending original movement/line order).
* Each reversal delta is `-original.quantity_delta` and `unit_cost_basis=original.unit_cost_basis`.
* `average_cost_before` is the current value at that inverse step.
* Because the current state is proven to be exactly the original after-state, `average_cost_after` and state may restore the original movement's before-state; verify equality before applying. This is an exact inverse, not blind stale restoration.
* This correctly handles immediate `10@10 + 10@20`: reversing the latest `10@20` restores `10@10`, rather than leaving `10@15`.
* It also safely reverses a just-posted multi-line Intake, including negative-to-positive transitions, back through its evidenced states.
* At zero/negative prior state, the exact original before-state (`UNESTABLISHED` or `RETAINED` and its retained average) is restored.
* If later Receipt or outgoing activity exists, direct reversal is rejected; no layer fiction or economically inconsistent subtraction is attempted.

This conservative rule matches moving average (not FIFO layers), append-and-correct, current-position locking and future historical COGS. Posted Sale Item COGS in Phase 4 will never be rewritten. Phase 4 may cause more old Intake reversals to be ineligible, intentionally directing users to correction/replacement evidence.

### Authorization/security

`inventory.adjust` owns manual types/correction; `inventory.reverse` owns all reversal and replacement linking; `inventory.view` reads. Every manual loss/correction/reversal requires a nonblank bounded reason. Owner derives authority; Staff requires exact grants. `FULL` writes only; READ_ONLY history remains. Inactive status does not override authorized cleanup authority.

### API work

Explicit allow-listed manual movement command, explicit movement reverse, Intake reverse and replacement-link families. Stable conflicts: already reversed, reversal not current, Product/source mismatch. No generic movement create/update/delete and no client-provided evidence snapshots.

### Audit work

Add `stock_movement.recorded`, `stock_movement.corrected`, `stock_movement.reversed`, `stock_intake.reversed`, `stock_intake.replaced`; resources `stock_movement`, `stock_intake`; all require reason. Metadata is bounded to type, decimal quantity, Product/original/reversal/replacement public IDs and before/after summaries. Same-scope causal Audit link is supplemental; domain FKs remain authoritative.

### `sync_changes` work

Manual/correction/reversal publishes new movement and final position `UPSERT`. Intake reversal publishes reversal header, every reversal movement, each final position and original Intake `UPSERT` for its derived effective state. Replacement publishes only on authoritative post under WP2 rules; Draft remains unpublished. Immutable originals are never `DEACTIVATE`d.

### Frontend work and parity

* Product detail/stock workspace adds complete authorized “Record stock leaving,” Correction and Reverse actions only when their backend is delivered.
* Plain choices: Damaged, Expired, Owner use/withdrawal, Missing/lost; ask “Why did stock leave?” and preview resulting Stock left, including negative.
* Inactive Product remains searchable in history/accountability context; Intake action is unavailable with explanation, cleanup/correction/reversal remains available by permission.
* Linked timeline shows original/reversal/replacement, actor, reason, contextual occurrence and before/after without erasing either row.
* If Intake direct reversal is not current, explain safe alternative; do not offer an action that will knowingly fail based on already loaded state, while server rechecks.
* Desktop and phone expose identical actions/history/filter/detail capability using grids versus cards/stacked dialogs. Reuse established reason dialog and styling.
* Preserve form/reason after recoverable failures; conflict reloads authoritative link state.

### Documentation

Inventory correction/reversal/source/cost-state contract; exact row model; API errors; permission/Audit/change docs; schema late-FK notes; operator runbook; manual reversal-cost vectors/two-session procedures; WP3 report.

### Manual verification

1. Record every manual type and both Correction signs; inspect actor/reason/movement/position/Audit/change.
2. Positive Correction with VALID and RETAINED basis retains average; with UNESTABLISHED remains unestablished even positive. Negative Correction crossing zero retains basis state correctly.
3. Create legitimate negative stock; no clamp and visible warning.
4. Deactivate Product with remaining stock: reject Opening/Receipt; allow authorized expired/damaged/loss/withdrawal/correction/reversal; preserve history.
5. Reverse manual movements; original unchanged; second direct reversal sequentially and concurrently rejects.
6. Immediate Intake reversal vector `10@10 + 10@20`; verify reverse delta/cost snapshots and restored `10@10`.
7. Reverse latest duplicate-line Intake in reverse line order; verify exact prior state including negative-to-positive case.
8. Attempt Intake reversal after later Receipt; after later outgoing movement; after Correction; while current state changed around zero/negative. Each rejects `INTAKE_REVERSAL_NOT_CURRENT` with no partial evidence.
9. Follow rejected case with reasoned Correction and optional replacement Receipt; verify transparent chain and sensible retained/forward WAC.
10. Inspect concrete A/B/C row relationships from section Q and derived effective state.
11. Race two Intake reversals and race reversal with a new overlapping Receipt in two sessions; inspect serialization, unique guard, positions, Audit and changes.
12. Controlled failures at movement, position, reversal header/link, Audit, change and commit; full rollback.
13. Foreign IDs/scope, forged source/snapshots/type/sign/actor and future movement types reject.
14. Owner/Staff/access modes; phone widths/tablet/desktop/keyboard/zoom parity and universal states.
15. Maintainability review: no duplicated WAC/reversal logic, giant service/controller/component, compressed conditionals, direct controller SQL or dead UI.

### Definition of Done

All approved movements work end-to-end; inactive-stock cleanup is truthful; correction cost state is frozen and verified; exact eligible Intake reversal restores its proven prior state; unsafe historical reversal rejects and has a clear correction/replacement workflow; original rows remain immutable; races/failures/isolation/access/UI parity/code quality all pass documented manual review.

### Handoff

WP4 receives complete authoritative command coverage and linked history. It may integrate and verify it, not introduce alternate calculation/repair paths.

## I. Detailed WP4 — Integrated Inventory Experience and Phase 3 Closure

### Objective and ordering

Deliver one coherent production-quality Inventory experience and prove every Phase 3 invariant before Sales consumes it.

### Scope

* Final minimum coherent surfaces: Product/Stock list; Product detail combining master/projection/threshold/actions/history; Opening/Receive builder and review; Intake detail/history; low/negative filtered views. Avoid extra nearly empty pages.
* Complete authorized navigation and universal-state consistency.
* Movement and Intake pagination/filtering, linked evidence and performance review.
* Controlled read-only operator drift diagnostics.
* Complete manual acceptance, migration rehearsal, security/maintainability review, documentation and closure report.

### Explicit non-scope

No new business command, Shop repair API/UI, fake dashboard metric, low-stock alert table, export, analytics, future-phase/offline placeholder or automatic rewrite.

### Database and operational diagnostics

No planned feature schema. Review `SHOW CREATE TABLE`, constraints and representative `EXPLAIN`; add an index only with measured need, paired migration/SQL and write-cost justification.

Provide documented read-only SQL and, only if consistent with existing Spark command conventions, a narrowly scoped operator command to report:

* position quantity versus committed movement sum;
* invalid last movement pointer;
* illegal source/sign combinations;
* posted ordinary Intake item/movement coverage and reversal graph integrity;
* version/chain anomalies.

It performs no update, repair or automatic correction and is unavailable as normal Shop UI/API. Drift triggers investigation and legitimate evidence procedures, not balance rewriting.

### Backend/API/Audit/change review

* Consolidate focused query output and stable ordering/pagination; never calculate authoritative WAC in PHP.
* Re-run every route through exact permission, access, scope, CSRF/session and error mapping review.
* Inspect Owner Audit for correct actor/resource/reason/metadata/correlation/causality and no false conflict events.
* Inspect every authoritative `sync_changes` sibling set/kind/scope/order and Draft silence; no pull/cursor/receipt.
* Verify future Phase 10 can call the same domain services without HTTP-specific business logic; do not implement dispatcher.
* Freeze Phase 4 contract: Sales will call Inventory service for stock effect; it will not update positions directly.

### Integrated frontend and strict parity

* Navigation appears only for meaningful view capability/access. Every visible action is fully working and exactly authorized. No coming-soon route, empty tab, dummy data, future Sale/Reconciliation button or disconnected card.
* Reuse branded Bootstrap shell, spacing, typography, cards, form controls, feedback and dialogs. No default-browser islands, arbitrary duplicate CSS, user-facing DB terms or debug values.
* Phone uses cards/stacked filters/dialogs/sticky actions; desktop may use denser grids/keyboard entry. Both can see, create, edit, search, filter, post, correct, reverse, confirm, review and investigate the same authorized data.
* Every relevant flow defines loading, empty, success, field validation, conflict, permission denied, READ_ONLY, BLOCKED, CLOSED, network unavailable, timeout, server and pending states. No pending-sync/Needs-Attention/queued fiction.

### Professional implementation quality review

Review all changed files for focused responsibility, strict typing where conventional, clear transaction boundaries, meaningful names, concise invariant PHPDoc, scoped explicit repositories/query objects, small transport controllers and reusable frontend state. Reject large unrelated methods/classes, direct SQL in controllers, duplicated authorization/WAC/error logic, deep nesting, compressed one-liners, hidden side effects, generic grab-bag helpers, giant render blocks and unused/debug code. Do not add a state-management/framework dependency.

### Documentation

Finalize migration/schema/late-FK, Product/Inventory/module, API/protected-resource, permission, Audit, sync, configuration/dependency (only if changed), diagnostics/runbook, all four manual records/reports, Phase 2 addendum status and Phase 3 closure report.

### Manual closure verification

1. Execute every requirement in section R with commands/actions, date, environment, PASS/FAIL/BLOCKED and evidence reference.
2. Fresh install, forward migration, rollback decision rehearsal and sanitized `SHOW CREATE TABLE` comparison on MySQL 8.4 strict UTC.
3. Execute deterministic decimal/WAC/correction/reversal vectors in section N by controlled HTTP/SQL observation.
4. Execute two-session Opening/Receipt/reversal races with held locks; inspect final business, Audit and change rows.
5. Execute controlled failure injection at every named transaction stage, then remove/disable verification hooks.
6. Reconstruct test positions from movements and run internal diagnostics; investigate all drift without automatic repair.
7. Cross-Tenant UUID/hostname/request-scope/actor attacks and complete permission/access matrix.
8. Validate every surface at 320/360/390px, tablet, desktop, keyboard and 200% zoom. Capture evidence according to project practice and prove capability parity.
9. Search repository/runtime routes/navigation for forbidden Phase 4+/Phase 10 artifacts and dead/placeholder UI.
10. Conduct structured backend/frontend maintainability review from section T.

### Definition of Done

Every acceptance row passes with recorded manual evidence; five tables match v1.1; no unexplained drift or critical/high issue remains; exact permissions/lifecycle/isolation/atomicity/concurrency pass; complete coherent UI has phone/desktop parity and universal states; no placeholder/future feature exists; code/document/generated assets are maintainable/current; Phase 2 addendum is complete. Otherwise Phase 3 is not closed.

### Phase 4 handoff

Phase 4 receives permanent Product IDs, immutable base-unit meaning, current locked positions, retained/valid/unestablished cost state, insert-only movement evidence, and one Inventory service boundary. It adds the deferred Sale Item FK after its parent and requests Sale deductions from Inventory; it never patches positions or historical costs.

## J. Cross-Package Dependency Map

```text
Phase 1 context/access/permissions + Phase 2 Audit/change
                         ↓
WP1 Product + protected Position + immutable-after-history unit
                         ↓
WP2 Draft Intake → posted Intake Items → Movement ledger → WAC
                         ↓
WP3 Manual evidence + Correction + exact eligible Reversal/Replacement
                         ↓
WP4 coherent UX + operator diagnostics + complete manual closure
                         ↓
Phase 4 Sales calls Inventory service
```

WP1 owns Product/position vocabulary and locks. WP2 owns the only acquisition/WAC and movement-application primitive. WP3 owns non-acquisition and protected inverse rules but reuses that primitive. WP4 composes, diagnoses and proves; it does not repair semantics with alternate logic.

## K. Final Migration Order and Late-FK Strategy

| Order | Migration | Content | Rollback dependency |
|---:|---|---|---|
| 1 | `CreatePhaseThreeProducts` | `products` | after all children removed |
| 2 | `CreatePhaseThreeStockPositions` | positions; pointer column, no circular FK | after movement pointer/children |
| 3 | `CreatePhaseThreeStockIntakes` | headers and correction links | after items/movement sources |
| 4 | `CreatePhaseThreeStockIntakeItems` | items, no Product uniqueness | after movements |
| 5 | `CreatePhaseThreeStockMovements` | ledger, current parents/self FK, future nullable source columns | before items/positions |
| 6 | `AddPhaseThreeStockPositionLastMovementForeignKey` | circular diagnostic pointer | drop first |

Each is a timestamped CI4 migration plus semantically matching phase-03 raw SQL, explicit engine/collation/check/index/FK and register entry. MySQL DDL implicit commits require disposable rollback rehearsal and documented recovery, not forged history.

Deferred constraints—not columns:

* Phase 4 adds scoped Sale Item FK immediately after `sale_items`/parent unique exists.
* Phase 7 adds scoped Reconciliation Variance FK immediately after its parent exists.

Phase 3 services reject future sources. Do not create placeholder parent tables, polymorphic IDs or weaker unscoped FKs.

## L. Permission Matrix

| Operation | Code | Owner | Staff | FULL | READ_ONLY | BLOCKED/CLOSED |
|---|---|---|---|---|---|---|
| Product/stock/Intake/movement/low/negative reads | `inventory.view` | role allows | explicit grant | yes | yes | no normal operation |
| Product create/name-price edit/pre-history unit edit/deactivate | `product.manage` | role allows | explicit grant | yes | no | no |
| Threshold/manual/correction | `inventory.adjust` | role allows | explicit grant | yes; reasons where sensitive | no | no |
| Draft/post Opening/Receipt | `inventory.intake` | role allows | explicit grant | yes | no | no |
| Movement/Intake reversal and replacement linking | `inventory.reverse` | role allows | explicit grant | yes + reason | no | no |
| Owner Audit history | existing Owner policy | yes | no | read | read | existing Audit boundary only |

Codes follow existing `<domain>.<action>` convention. `product.manage` reflects the Product Catalog module; `inventory.adjust` remains compatible with the existing enum. UI capabilities derive from permissions plus access mode, but services always recheck. Unknown aliases fail closed.

## M. Audit and `sync_changes` Matrix

Every “yes” shares the business transaction/connection and request correlation. Reads/rejections/conflicts emit neither.

| Mutation | Audit action/resource | Reason | Change publication |
|---|---|---|---|
| Product create | `product.created` / Product | optional | Product + initial position `UPSERT` |
| Product name/price or eligible unit edit | `product.updated` / Product | optional | Product `UPSERT` |
| Rejected post-history unit edit | none | — | none |
| Product deactivate | `product.deactivated` / Product | required | Product `DEACTIVATE` |
| Threshold update | `stock.threshold_updated` / position | optional | position `UPSERT` |
| Draft Intake create/edit/item remove/discard | **none** | — | **none** |
| Opening/Receipt post | `stock_intake.posted` / Intake | optional | Intake + each movement + final affected positions `UPSERT` |
| Damaged/expired/withdrawal/loss | `stock_movement.recorded` / movement | required | movement + position `UPSERT` |
| Positive/negative Correction | `stock_movement.corrected` / movement | required | movement + position `UPSERT` |
| Manual movement reversal | `stock_movement.reversed` / reversal movement | required | reversal movement + position `UPSERT` |
| Intake reversal | `stock_intake.reversed` / reversal header | required | reversal header/movements/positions + original Intake `UPSERT` |
| Replacement authoritative post/link | `stock_intake.replaced` plus normal posted evidence / Intake | required for correction link | only when posted; new Intake/movements/positions `UPSERT` |

Draft records are online working data, not authoritative stock or future device state in Phase 3. If Phase 10 synchronizes drafts, it must explicitly revise that later boundary.

## N. Inventory WAC / Cost-State Decision Matrix

| Operation/current state | Quantity effect | Average after | State after | Movement `unit_cost_basis` |
|---|---|---|---|---|
| Intake, `Q>0` | `+I` | decimal weighted average | `VALID` | incoming unit cost |
| Intake, `Q<=0`, result `<=0`, prior basis exists | `+I` | retain average | `RETAINED` | incoming unit cost snapshot |
| Intake, `Q<=0`, result `<=0`, no basis ever | `+I` | retain zero/unestablished value | `UNESTABLISHED` | incoming cost snapshot; not yet current valid basis |
| Intake crosses from `Q<=0` to positive | `+I` | reset to crossing line cost | `VALID` | crossing incoming cost |
| Ordinary outgoing remains positive | negative | unchanged | existing `VALID`/state | current basis |
| Ordinary outgoing becomes zero/negative | negative | unchanged | `RETAINED` if basis existed; else `UNESTABLISHED` | current basis/zero if none |
| Positive/negative Correction with basis | signed | unchanged; no WAC | valid if positive/current valid; retained at non-positive; retained stays retained until Intake | current basis |
| Positive Correction without basis | positive | no invented cost | `UNESTABLISHED` | zero explicitly denotes no basis |
| Manual movement reversal | negate original | unchanged current basis | non-acquisition state rule | current basis |
| Eligible Intake reversal | negate each original in reverse chain | exact verified original before average per inverse step | exact verified prior state | original incoming movement basis |
| Intake reversal after any later movement | none; reject | unchanged | unchanged | no movement |

All calculations and comparisons use fixed decimals. One movement row records before/after quantity and average at each duplicate line/inverse step. Phase 4 must refuse to invent meaningful COGS from `UNESTABLISHED`; its approved handling must build on this explicit state.

## O. Product Lifecycle / Unit-Immutability Matrix

| Product state/history | Name | Price | Unit | New Intake | Accountability movement | History |
|---|---|---|---|---|---|---|
| ACTIVE, no movement | editable | editable | editable | Opening/Receipt subject to rules | allowed | readable |
| ACTIVE, movement exists | editable | editable | immutable (`409`) | Receipt; Opening ineligible | allowed | readable |
| INACTIVE, any history | editable only if product policy permits re-labeling; recommend name/price remain service-editable but UI normally limits to context | editable under manage if genuinely needed | immutable once movement exists | reject Opening/Receipt | damaged/expired/withdrawal/loss/correction/reversal allowed | always readable |
| Deactivated duplicate/replacement unit | old Product preserved | historical master remains current record | never reinterpreted | create new Product for new unit | old cleanup remains allowed | linked operationally by user context, no invented schema link |

Movement existence is determined by scoped relational query, not a new column or client flag. Deactivation never changes quantity or erases position.

## P. Intake Lifecycle / Opening Eligibility Matrix

| Situation | Draft | Post result | Permanent Audit/change |
|---|---|---|---|
| Create/edit/remove/discard ordinary Draft | allowed while `DRAFT` | none | none |
| Opening line, locked position has null pointer + UNESTABLISHED + no movement | allowed | post allowed | posting evidence only |
| Opening line, any prior movement/pointer/basis contradiction | may be stale Draft | whole post rejects `OPENING_ALREADY_ESTABLISHED` or state conflict | none |
| Product A established, Product B untouched | B Opening Draft allowed | B posts | B posting evidence |
| Later acquisition after establishment | Receipt Draft | posts under WAC | posting evidence |
| Duplicate Product lines | allowed | same position lock once; ascending line application; movement/item | posting evidence |
| Inactive Product | existing Draft remains viewable | Opening/Receipt rejects | none on rejection |
| Already POSTED | immutable | repeat rejects `INTAKE_ALREADY_POSTED` | no duplicate evidence |
| Direct reversal | not ordinary Draft UI | WP3 zero-item posted reversal header | reversal evidence |
| Replacement | ordinary linked Draft, no Draft evidence | normal post validation | evidence only on authoritative reversal/link/post |

## Q. Intake Reversal Row-Level Model

### Original posted Intake A

Assume `A.intake_kind=RECEIPT`, `A.status=POSTED`:

```text
Header A
  reversal_of_intake_id = NULL
  replacement_for_intake_id = NULL
Item A1: Product P, quantity 20, unit_cost 100, line_number 1
Item A2: Product Q, quantity 5,  unit_cost 250, line_number 2
Movement M1: source_kind=INTAKE_ITEM, stock_intake_item_id=A1,
             reversal_of_movement_id=NULL, quantity_delta=+20
Movement M2: source_kind=INTAKE_ITEM, stock_intake_item_id=A2,
             reversal_of_movement_id=NULL, quantity_delta=+5
```

### Direct reversal B

After locking A, M1/M2 and positions and proving the current-suffix costing rule:

```text
Header B
  intake_kind = RECEIPT                 -- exactly A's kind; no third kind
  status = POSTED
  reversal_of_intake_id = A.id
  replacement_for_intake_id = NULL
  posted actor/time/reason/correlation = reversal workflow evidence
  Intake Items = NONE                   -- avoids fake positive or prohibited negative acquisition rows

Movement R1
  movement_type = REVERSAL
  source_kind = REVERSAL
  reversal_of_movement_id = M1.id
  stock_intake_item_id/sale_item_id/reconciliation_variance_id = NULL
  quantity_delta = -20

Movement R2
  movement_type = REVERSAL
  source_kind = REVERSAL
  reversal_of_movement_id = M2.id
  other source FKs = NULL
  quantity_delta = -5
```

R1/R2 are applied in reverse original application order per Product; independent Products follow ascending locked Product order. B and R rows share one correlation ID. Relational causality is queryable in two joined paths: `B.reversal_of_intake_id→A`, and `R.reversal_of_movement_id→M→A Item`; shared correlation binds the atomic workflow. No direct B-to-R FK exists in frozen v1.1, so correlation plus both required relational paths is the narrow blueprint-consistent representation. Adding fake B items would misstate acquisition; changing schema would exceed frozen scope.

A remains `POSTED`; its effective reversed state is derived from the unique B link and complete R links. It is never rewritten to `REVERSED`. Unique reversal guards prevent a second B/R set.

### Optional replacement C

```text
Header C
  intake_kind = RECEIPT (normally; OPENING only if eligibility is truly restored)
  status = DRAFT then POSTED through normal WP2 service
  reversal_of_intake_id = NULL
  replacement_for_intake_id = A.id
Items C1...Cn = positive corrected acquisition evidence
Movements on post = source_kind INTAKE_ITEM linked to C items
```

C Draft emits no permanent Audit/change. Its reasoned correction link and authoritative post evidence are recorded when posted. C is independently authorized, scoped and WAC-calculated. A, B and C remain navigable and immutable.

## R. Complete Phase 3 Acceptance Matrix

| Requirement | Implementing owner | WP4 closure regression |
|---|---:|---|
| Product create/search/detail/duplicate names | WP1 | integrated journey/parity |
| Mutable name/current selling price | WP1 | permission/Audit regression |
| Unit editable before movement, immutable after | WP1 | race and UI/server regression |
| Deactivate with history/stock preserved | WP1 | inactive cleanup journey |
| Position fields/protection/threshold | WP1 | drift/no-balance-route review |
| Low-stock derivation and negative visibility | WP1/WP3 negative creation | integrated filters/parity |
| Opening per-position first history only | WP2 | staged/race regression |
| Receipt Draft→Posted | WP2 | complete journey |
| Draft Audit/change silence | WP2 | DB evidence review |
| Duplicate Product Intake lines | WP2 | deterministic WAC regression |
| Multi-item atomic post and sorted locks | WP2 | two-session/failure procedures |
| Precision/rounding/positive WAC | WP2 | decision-matrix vectors |
| Zero/negative retained/reset cost state | WP2 | vectors |
| Opening/Intake movements and immutable evidence | WP2 | reconstruction |
| Damaged/expired/withdrawal/missing | WP3 | integrated journey |
| Positive/negative Correction basis | WP3 | cost-state regression |
| Inactive Product accountability movements | WP3 | complete journey |
| Manual reversal/one-direct guard | WP3 | race regression |
| Exact Intake reversal row model | WP3 | A/B/C inspection |
| Reversal costing/current-suffix rejection | WP3 | full vector regression |
| Optional replacement | WP3 | linked journey |
| Tenant isolation/server-derived scope | WP1 foundation; all command owners | adversarial full sweep |
| Exact permissions/Owner/Staff | WP1 registry; each command owner | full matrix |
| FULL/READ_ONLY/BLOCKED/CLOSED | each surface owner | full matrix |
| Typed Audit/reasons/redaction/causality | each mutation owner | Owner Audit inspection |
| Atomic authoritative change publication | each mutation owner | generation/failure inspection |
| Responsive complete UI/universal states | each surface owner | strict parity sweep |
| No placeholders/future phase/offline fiction | each owner | repository/runtime sweep |
| Professional maintainability | each owner | structured final review |
| Internal non-repairing drift diagnostic | WP4 | operator procedure |
| Phase 2 runtime addendum | WP1 closure | Phase 3 closure gate |
| Phase 4 Inventory contract | WP4 | closure sign-off |

No business requirement has two ambiguous implementation owners; WP4 entries are integration/regression gates, not reimplementation.

## S. UI Surface and Mobile-Parity Matrix

| Business surface/capability | Desktop presentation | Mobile equivalent (same capability) |
|---|---|---|
| Product/Stock list | denser responsive grid/list, keyboard search/filter | stacked cards, compact filter controls, same search/filter/results |
| Product create/edit | side-by-side grouped form where space permits | stacked focused form, same fields/rules/actions |
| Product detail | combined master/stock/cost/threshold/history panels | ordered cards/sections, same data/actions |
| Low/negative stock | filter/list with badges | filter/card list with equally prominent warnings |
| Opening/Receipt builder | keyboard-efficient line layout/review | stacked line cards, large search/inputs, sticky review/post |
| Duplicate Product batches | separate ordered rows | separate ordered cards; never silently merged |
| Intake detail/history | timeline/detail panels | stacked timeline/detail cards |
| Manual movement/correction | compact action panel/dialog | full-width form/dialog with same options/preview |
| Reversal/replacement | linked evidence panel and confirmation | linked cards and accessible confirmation, same eligibility/actions |
| Filters/pagination | inline controls/load navigation | wrapped controls/load navigation without horizontal table |
| Universal/access states | banners/inline field feedback/dialogs | identical meanings and recovery; touch/focus optimized |

Every surface is branded and purpose-driven. Technical source/cost-state details appear only in useful detail/diagnostic context; ordinary wording uses “Stock left,” “Cost price,” “Low stock,” “Receive stock,” and “Why did stock leave?” No capability is desktop only.

## T. Code Quality / Maintainability Acceptance Standard

### Backend

Controllers normally parse request, coordinate established authorization, call one focused service and return the standard envelope. They do not own WAC, SQL or lifecycle branching. Services have one domain responsibility; repositories/query objects are explicit and scope every query. Commands/DTOs use typed fields; important public invariants have concise PHPDoc. Transactions and lock order are visible. Validation, authorization, error mapping and decimal arithmetic are not copied across flows.

Reject giant controllers/services, unrelated generic managers/helpers, deep nesting, compressed one-line conditionals, cryptic names, hidden commits/side effects, scattered controller SQL, generic save/delete for evidence, comments masking unreadable code, and speculative abstraction.

### Frontend

Create focused Inventory API modules, composables, Product/Stock/Intake/history/correction/reversal components and genuinely shared formatting/validation/state helpers. Keep understandable state names and shallow render composition. Reuse HTTP/access/feedback/dialog conventions. Do not turn `shop/app.js` or render files into monoliths, duplicate universal-state logic everywhere, or add an unnecessary framework/state dependency.

### Package review

Each delivery report identifies changed-file responsibilities and records a human review for oversized files/methods, duplication, dead/unused code, inconsistent UI, inaccessible controls and phone/desktop mismatch. “Works” is insufficient if maintainability fails. Refactoring required to meet this standard belongs inside the owning WP, not optional cleanup after Phase 3.

## U. Explicit Deferred Scope

No Customers; Sales/Sale Items/Payments; Sale movement; Customer Returns; Receivables/debt; expenses/Financial Movements; physical Reconciliation/variance/adjustment; Suppliers; Purchase Orders/payables; general ledger; barcode; automatic package conversion; multi-branch workflow; messaging; analytics/forecasting; device provisioning; offline PIN; Dexie Inventory cache/outbox; push/pull/cursors/receipts; generic replay/idempotency/event sourcing.

Reserved movement/source values and nullable future FK columns are schema compatibility only. They authorize no route, service, button, card, metric, page, tab or navigation entry.

## V. Phase 3 Closure Gate and Phase 4 Readiness Contract

Implement and review in strict order WP1 → WP2 → WP3 → WP4. A later WP starts only after its predecessor's vertical Definition of Done and manual evidence are accepted. A package is not a staging dump: any visible capability includes schema where applicable, focused domain/repository, authorization, API, Audit/change policy, complete styled responsive UI, universal states, documentation, manual verification and report.

Phase 3 closes only when:

* every section R requirement is PASS with reproducible manual evidence;
* the Phase 2 later-runtime addendum is complete while original blocked history remains intact;
* migrations/schema/late FKs match v1.1 and live inspection;
* projection reconstructability, WAC/cost state, Opening, duplicate lines, correction and reversal rules pass controlled vectors/concurrency/failure procedures;
* no cross-Tenant/access/subscription hole, unexplained drift or critical/high defect remains;
* all PC capabilities have equivalent efficient phone capabilities at required widths;
* no visible button/page/card/action/route/workflow is placeholder or disconnected;
* implementation passes the professional maintainability review; and
* no Phase 4+/Phase 10 functionality leaked into product surfaces.

> Phase 3 is complete only when DawaTrack provides a trustworthy, reconstructable, auditable and responsive Product/Inventory system end-to-end—not merely when its database and backend classes exist.

> Anything an authorized PC user can do or see in Phase 3 must also be available to the same authorized mobile-phone user with equivalent capability, simplicity and efficiency.

> No visible Phase 3 button, page, card, action, route or workflow may exist as a placeholder or disconnected partial implementation.

> All backend and frontend implementation must meet professional maintainability standards: focused classes/components, explicit business rules, readable code, controlled file size, meaningful naming, minimal duplication and no jam-packed developer-unfriendly files.

Only after WP4 closure may Phase 4 create Sale parents/deferred FK and call the same authoritative Inventory service for Sale deductions/cost snapshots. Sales may not write `stock_positions` directly, reinterpret Product units, revise historical WAC or bypass Audit/change atomicity.
