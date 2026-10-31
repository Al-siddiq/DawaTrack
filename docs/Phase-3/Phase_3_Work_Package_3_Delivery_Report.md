# Phase 3 Work Package 3 Delivery Report

## A. Implementation delivery status

**IMPLEMENTATION COMPLETE — OWNER MANUAL ACCEPTANCE PENDING.** WP3 code, integrated responsive workflows, contracts and owner checklist are delivered; all feasible static/build checks pass. Runtime MySQL, HTTP/browser, race/failure and device acceptance remain explicitly for the project owner and are not claimed.

## B. Repository state inspected

Accepted WP1/WP2 schema, Product unit lock, protected positions, Draft/post services, exact-decimal WAC, deterministic posting locks, movement/source constraints, pointer FK, query/UI, permissions, Audit and change registries were inspected. WP3 extends this ledger and does not add an alternate Inventory engine.

## C. Delivered scope

Damaged, expired, Owner stock use, missing/loss, signed Correction, inactive-Product accountability, manual Movement reversal, current-suffix whole-Intake reversal, reasoned replacement Intake linkage, causal history/detail, permissions, Audit, changes and responsive UI. No WP4 or later-phase feature is included.

## D. Changed files and responsibilities

* `InventoryProjection`: exact signed quantity addition and non-acquisition cost-state transitions.
* `ManualMovementService`: approved MANUAL evidence and Correction.
* `MovementReversalService`: one direct manual-evidence reversal.
* `CostStateReconstructor`: derives prior basis state from immutable chain.
* `IntakeReversalService`: whole current-suffix proof and exact inverse.
* `ReplacementIntakeService`: silent, reasoned A→C Receipt/eligible-Opening Draft linkage after reversal.
* `OpeningEligibility`: one shared locked rule for pristine positions and fully reversed initial Opening evidence.
* `IntakePostingService`: makes replacement causality, both Audit events and all authoritative publications atomic with C posting.
* `IntakeQuery`: linked Intake/Movement detail, history and conservative eligibility presentation.
* shared/command controllers and explicit routes: transport/access only.
* focused frontend API/composable plus Inventory action/detail forms.
* module/API/security/Audit/sync/operations/manual/report documentation.

## E. Database/schema impact

No new table or migration. WP3 uses accepted `MANUAL`/`REVERSAL` source shapes, reason fields, immutable snapshots, unique `reversal_of_movement_id`, and Intake `reversal_of_intake_id`/`replacement_for_intake_id`. This is the approved no-DDL outcome.

## F. Manual movement implementation

The service validates allow-listed type/sign, positive magnitude and mandatory reason; locks Product and position; permits inactive Product and negative result; inserts exactly one MANUAL movement; updates quantity/state/pointer/version; then Audit/changes and commit.

## G. Correction cost-basis implementation

Correction accepts ADD/REMOVE but no cost. Average and `unit_cost_basis` snapshot the current average. VALID stays valid while positive and becomes RETAINED at non-positive; RETAINED stays retained; UNESTABLISHED remains unestablished even after a positive Correction. No WAC is performed.

## H. Movement reversal implementation

Only MANUAL originals use the individual command; Intake sources require whole-Intake reversal. The service locks original, rejects reversal-of-reversal/already reversed, derives the exact negated delta, applies current non-acquisition basis rules, creates one linked REVERSAL and relies on both lock/check and unique guard. Original is untouched.

## I. Intake reversal representation

Original A remains POSTED. New B is POSTED, uses A's exact `intake_kind`, has zero items and `reversal_of_intake_id=A.id`. Each R has `source_kind=REVERSAL`, `movement_type=REVERSAL`, only `reversal_of_movement_id=M.id`, negated delta, and the workflow correlation. Causal paths are B→A and R→M→item→A.

## J. Intake reversal eligibility/costing

The service locks A, Products/positions ascending, proves each original set is the current movement suffix, unreversed and snapshot-consistent, and verifies current state before each inverse. It applies same-Product originals descending, restores exact verified original quantity/average/state, updates pointers/versions and rejects any later activity with `INTAKE_REVERSAL_NOT_CURRENT`. Thus 10@10 plus 10@20 reverses back to 10@10 rather than retaining 15.

## K. Replacement workflow

After A has a reversal, an `inventory.reverse` user may create one linked Draft C with a bounded mandatory reason retained in the existing Draft `notes` field. Replacement is normally RECEIPT. OPENING is permitted only where exact reversal restores the Product/Shop position to normal WP2 Opening eligibility, enforced by the same shared locked rule. Replacement Draft creation/edit/discard emits neither permanent Audit nor `sync_changes`. The replacement relationship becomes authoritative and emits `stock_intake.replaced` only when the replacement Intake successfully posts.

## L. Inactive Product handling

Manual accountability and reversal resolve Products regardless of lifecycle status; UI retains the inactive badge/history/actions. WP2 still rejects Opening/Receipt. Nothing implicitly reactivates or removes stock.

## M. Locking/concurrency design

Manual/reversal locks originals where applicable, then Product and position. Multi-Product Intake reversal locks A, unique Products and positions ascending, validates all Products before applying any inverse, then Audit/change. No domain lock follows publication.

## N. Duplicate safety

Unique reversal FK plus original lock prevents double compensation; duplicate Intake reversal/replacement links use accepted unique guards and stable conflicts. Rejected/racing commands publish no false success. No generic replay was added.

## O. Authorization/security

`inventory.adjust` owns manual/Correction; independent `inventory.reverse` owns reversals/replacement; `inventory.view` owns reads. Existing AccessGate requires FULL for mutation and permits READ_ONLY history. `ShopContext` supplies scope/actor; client evidence fields are never accepted.

## P. Audit

Added required-reason actions `stock_movement.recorded`, `.corrected`, `.reversed`, `stock_intake.reversed`, `.replaced`. Replacement Draft operations are silent. Successful C posting emits normal `stock_intake.posted` plus mandatory-reason `stock_intake.replaced` in the same transaction. INVENTORY metadata uses bounded public IDs/type/quantity/snapshots. Domain FKs remain causal authority.

## Q. `sync_changes`

Manual/Correction/Movement reversal publishes movement+position. Intake reversal publishes B, every R, each final position and original A UPSERT. Originals are never deactivated. Replacement Draft create/edit/discard publishes nothing. Successful C posting publishes C, every new Movement, each final position and original A UPSERT atomically.

## R. API delivery

Explicit commands exist for manual evidence, manual Movement reversal, whole Intake reversal and replacement creation plus scoped Movement/linked Intake detail. No generic movement CRUD/source/snapshot/position patch exists.

## S. Frontend delivery

Product detail provides plain-language Damaged/Expired/Owner use/Missing/Correction workflow with reason and current/negative context. Evidence detail exposes causal IDs and eligible manual reversal. Intake detail shows original/reversal/replacement state, safe whole reversal and replacement Draft creation. History preserves originals and reasons; no future controls exist.

## T. Mobile/desktop parity design

Responsive grids collapse to stacked, full-width phone forms/cards/dialogs. Every adjustment, Correction, reversal, replacement and linked-history capability remains available at phone and desktop; owner executes device acceptance.

## U. Static/doable verification

| Check | Result | Evidence/notes |
|---|---|---|
| PHP syntax | PASS | Full `app` lint |
| JavaScript syntax | PASS | Source/generated `node --check` |
| Frontend build | PASS | `npm run build`; upstream Sass warnings only |
| Schema/source/sign review | PASS | No DDL; services match accepted checks |
| Routes/security/permissions | PASS | Explicit scoped commands and separated grants |
| Audit/change vocabularies | PASS | All WP3 actions/resources/publications registered |
| Protected evidence/balance scan | PASS | No generic balance or posted update/delete |
| Future-scope scan | PASS | No Sale/Return/Reconciliation/Supplier/offline/WP4 UI/route |
| Controller SQL scan | PASS | Domain SQL remains in focused services/queries |
| Generated assets | PASS | Source/generated JS consistency and template build |
| Runtime owner acceptance | PENDING | Execute WP3 owner checklist |

## V. Owner manual verification checklist

Execute all 45 procedures in `docs/manual-verification-phase-03-wp3.md`, recording runtime/database/browser evidence and PASS/FAIL/BLOCKED honestly.

## W. Maintainability review

Manual, individual reversal, Intake reversal, state reconstruction, replacement and query responsibilities are separate. Existing exact WAC remains untouched. Controllers coordinate only transport/access. Frontend action state is isolated in a composable/API module. No new dependency, debug hook, TODO business logic or second ledger exists.

## X. Documentation updated

API conventions, Audit contract, sync publication, permissions, schema notes, module map/WP3 module contract, operations runbook, owner checklist and this report.

## Y. Deviations

None. Replacement Draft creation/edit/discard emits neither permanent Audit nor `sync_changes`. The replacement relationship becomes authoritative and emits `stock_intake.replaced` only when the replacement Intake successfully posts. Replacement is normally RECEIPT. OPENING is permitted only where exact reversal restores the Product/Shop position to normal WP2 Opening eligibility.

## Z. Known issues

* **Owner acceptance pending:** runtime arithmetic, chain reconstruction, races, rollback, access/isolation and responsive behavior require owner verification.
* **Non-blocking:** existing Bootstrap Sass emits upstream deprecation warnings.

## AA. WP4 readiness

After owner acceptance, WP4 may assume complete Phase 3 command coverage, immutable causal evidence, exact Correction state rules, race-safe direct reversal guards, current-suffix Intake inverse, replacement Draft linkage, scoped linked history, Audit and authoritative change publication. WP4 is not implemented or authorized here.
