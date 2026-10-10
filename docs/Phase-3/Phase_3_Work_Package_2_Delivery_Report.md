# Phase 3 Work Package 2 Delivery Report

## A. Implementation delivery status

**IMPLEMENTATION COMPLETE — OWNER MANUAL ACCEPTANCE PENDING.** WP2 code, schema artifacts, responsive workflows and documentation are delivered. Static/doable checks pass. Runtime MySQL, concurrency, failure-injection, HTTP/browser and device acceptance are explicitly delegated to the project owner and are not claimed here.

## B. Repository state inspected

The accepted WP1 Product/position migrations, Product lock/unit guard, threshold service, permissions, Audit/change registries, explicit API routes, Vue composables/templates and generated-asset workflow were reinspected. WP2 builds on the accepted zero position and Product UUID logical position identity without redesigning WP1.

## C. Delivered scope

Draft Opening/Receipt create/edit/item add-edit-remove/discard; duplicate Product cost batches; review/post; Opening eligibility; exact-decimal line/WAC transitions; immutable movements; atomic positions/pointer/version/post/Audit/changes; Intake list/detail and Product movement history; complete phone/desktop UI. No WP3 action exists.

## D. Changed files and responsibilities

* Four `2026-10-06-0002xx..0005xx` migrations and paired phase-03 SQL: Intakes, items, ledger and pointer FK.
* `InventoryDecimal`: centralized MySQL exact-decimal validation/calculation.
* `IntakeDraftService`: Draft-only online working-state commands without Audit/change.
* `IntakePostingService`: locking, eligibility, WAC, evidence, projection and atomic publication.
* `IntakeQuery`: scoped Intake/detail/movement/Product history.
* `StockIntakeController` and routes: explicit thin command/query HTTP boundary.
* Intake API/composable/template/Sass/generated assets: full responsive workflow.
* Registry/documentation/manual/report files: closed contracts and owner acceptance procedures.

## E. Database delivery

`stock_intakes` contains frozen kinds/status, occurrence/posting/provenance/correction-link columns and scoped self FKs. `stock_intake_items` uses `(19,4)/(19,6)/(19,2)`, positive/nonnegative checks, unique line and no Product uniqueness. `stock_movements` includes complete immutable snapshots/source columns, checks, indexes, current-parent FKs and unique reversal link. Sale/Reconciliation columns are present without absent-parent FKs. Migration 13 adds the scoped position pointer FK.

## F. Backend/domain delivery

Draft commands require `inventory.intake`, only operate on DRAFT, validate scope/active Product/decimals and physically discard only Draft aggregate rows. Posting locks the header, loads ordered items, locks Products and positions deterministically, validates all state, applies one movement/item, updates projection, then posts/Audits/publishes. Posted mutations/deletes have no path.

## G. WAC implementation

MySQL `DECIMAL(38,12)` expressions calculate line multiplication, quantity addition and WAC; final line amount/average are cast to `(19,2)/(19,6)`. Positive Q blends. Non-positive Q that remains non-positive retains average and UNESTABLISHED/RETAINED state. The first line restoring positive resets average to that line cost. Duplicate Product lines feed state sequentially by line number. No PHP/JS floating point is authoritative.

## H. Locking/concurrency design

Order is Intake header → unique Products ascending → positions ascending → per-Product lines ascending. All locks precede Audit/change publication. The Product lock activates the WP1 unit invariant. Opening validation and all WAC calculation occur under position locks.

## I. Duplicate-safety design

A header `FOR UPDATE` and DRAFT check allow exactly one post. Later attempts receive `INTAKE_ALREADY_POSTED` before effects. Unique public/source/line/reversal constraints supply defense in depth; no generic replay infrastructure was introduced.

## J. Authorization/security

Reads use `inventory.view`; every Draft/post write uses independent `inventory.intake`; Owner derives authority and Staff requires grant. Existing AccessGate enforces FULL writes and READ_ONLY reads. Tenant/Shop/actor come from `ShopContext`; every query and FK is scoped; CSRF/session remain global.

## K. Audit

Draft CRUD/discard emits none. Successful post emits one optional-reason `stock_intake.posted` INVENTORY event on `stock_intake`, with bounded kind/count/total/occurrence metadata, inside the posting transaction.

## L. `sync_changes`

Draft emits none. Posting publishes Intake UPSERT, every Movement UPSERT, and one final position UPSERT/Product UUID per affected position, after Audit and in the same transaction. No transport exists.

## M. Frontend delivery

“Opening stock” and “Receive stock” create real Drafts. The builder edits header and separate repeated Product cost-batch cards, reviews immutable evidence, prevents duplicate clicks and displays posted movement before/after detail. Intake list/detail plus Product stock history are working surfaces. No Supplier, Sale, correction or reversal controls appear.

## N. Mobile/desktop parity design

Responsive cards, wrapped forms and full-width phone actions expose the same Draft, line, duplicate-batch, review, post and detail capabilities as desktop. Sticky review actions do not require wide tables. Owner must execute 320/360/390/tablet/desktop/keyboard/zoom checks.

## O. Static/doable verification results

| Check | Result | Evidence/notes |
|---|---|---|
| PHP syntax | PASS | Full application lint |
| JavaScript syntax | PASS | `node --check` editable/generated modules |
| Frontend build | PASS | `npm run build`; upstream Sass warnings only |
| Migration/raw SQL comparison | PASS | DDL bodies compared statically |
| Controlled values/FK/index review | PASS | Frozen kinds/status/source/precision and deferred FKs inspected |
| Route/scope/security review | PASS | Explicit commands, scoped contexts, no generic writes |
| Permission/Audit/change registry review | PASS | Intake permission/action/resources present |
| WP3/WP4 scope scan | PASS | No manual/reversal/correction route or navigation |
| Generated asset consistency | PASS | Asset build recopied source modules/template output |
| Runtime/manual acceptance | OWNER PENDING | See owner checklist |

## P. Owner manual verification checklist

Execute every procedure and evidence expectation in `docs/manual-verification-phase-03-wp2.md`. Static delivery does not assert any runtime PASS.

## Q. Maintainability review

Responsibilities are separated across Draft, Posting, Decimal and Query classes; controller owns transport only. WAC exists in one calculator. Frontend API and Intake state are separate from shell composition. No direct controller SQL, hidden debug hook, TODO business logic or future action was found. Some concise repository/service statements follow the existing compact project style; future modification should preserve responsibility boundaries.

## R. Documentation updated

Migration register, schema notes, API conventions, permission catalogue, Audit contract, sync change contract, module map/WP2 module doc, WP2 operations runbook, owner manual checklist and this report.

## S. Deviations

None from approved WP2 semantics.

## T. Known issues

* **Owner acceptance pending:** runtime migration, exact MySQL arithmetic, races, rollback, isolation and responsive behavior require the owner environment.
* **Non-blocking:** Bootstrap Sass emits upstream deprecation warnings.

## U. WP3 readiness

After owner acceptance, WP3 may assume protected posted Intakes/items/movements, exact decimal transition service, deterministic Product/position locks, one movement per Intake Item, position pointer/version updates, authoritative Intake/movement/position publication, and scoped history. WP3 is not implemented or authorized here.
