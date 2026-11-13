# Phase 6 — Financial Movements and Cash Accountability

**Date:** 12 November 2026 (Africa/Lagos)  
**Status:** Proposed implementation plan — awaiting explicit Owner approval  
**Reviewed baseline:** main at `f985b5a88516ecd3861497bae0ee90984dfe47a1` (Phase 5 PR #43 merged)  
**Delivery model:** Four sequential, independently reviewable work packages. Each package must be runnable, documented and manually accepted before the next package begins.

## 1. Authority and entry position

This plan is subordinate to, and must be read with:

- [Strategic phased roadmap — Phase 6](../DawaTrack_Strategic_Phased_Implementation_Roadmap.md#phase-6--financial-movements-and-cash-accountability): controlling Phase 6 scope and exclusions.
- [Technical Blueprint](../DawaTrack_Technical_Blueprint.md), especially §§6.8–6.9, 7, 8.7 and 9: non-sale financial movements, expected Cash, offline boundary and immutable correction.
- [Database Schema Design v1.1](../DawaTrack_Database_Schema_Design.md), especially §11.1, §12.1–12.3 and the transaction/lifecycle rules: authoritative table, signs, relationships and future Reconciliation inputs.
- [Audit contract](../audit-contract.md), [API conventions](../api/conventions.md), [permission catalogue](../security/permission-catalogue.md), [migration register](../database/migration-register.md), [module map](../modules/module-map.md) and [Phase 4 Opening Cash contract](../Phase-4/Phase_4_WP1_Integration_Contracts.md).
- [Phase 5 closure report](../Phase-5/Phase_5_Closure_Report.md) and [whole-phase acceptance](../acceptance/2026-11-12-phase-05-owner-closure.md).

Phase 5 was successfully merged through PR #43. The reviewed main baseline is commit `f985b5a88516ecd3861497bae0ee90984dfe47a1`. This is a source-control fact, not a claim that the application has been deployed or that native production-readiness checks have been performed.

### 1.1 Current implementation findings

The current main branch already provides:

- immutable Audit persistence and the FINANCIAL Audit category;
- canonical request correlation and transaction-participating Audit append;
- internal commit-ordered change publication;
- the existing Shop/Tenant/membership transaction boundary in CommercialAccess;
- Phase 4 Opening Cash and CashControlGuard, including finalized-baseline and active-Cash-control checks;
- accepted Sales, Cash/Transfer payments and refunds, Debt collections, debt corrections and Customer statements.

The current source does **not** yet contain a Financial Movement module, the financial_movements migration/table, financial_movement Audit action/resource vocabulary, financial_movement change-resource registration, or financial movement permissions and Shop UI. The existing ChangeSequenceService does not yet accept financial_movement as a resource. The existing workspace has no Financial Movements task.

These are Phase 6 deliverables, not capabilities to assume already exist. WP1 must verify these findings against its exact implementation base before changing files.

## 2. Objective and outcome

Deliver a complete, auditable Shop workflow for the non-sale financial events that change the business's money position:

1. Shop Expenses;
2. Owner Cash Withdrawals;
3. Owner Cash Introductions / Cash Float;
4. controlled Financial Corrections and reversals.

Every movement must have a permanent public identity, current Tenant/Shop scope, actor, channel, signed exact amount, business explanation, occurrence and server-posting timestamps, and protected causal history where corrected or reversed.

At the end of Phase 6, the Owner must be able to answer:

- What money left or entered the Shop outside Sales and Debt collection?
- Was it Cash or Transfer?
- Who recorded it, when was it recorded, and why?
- Was an earlier record reversed or replaced?
- How does the event contribute to expected physical Cash at a later Reconciliation cutoff?

## 3. Fixed scope and exclusions

### Included

- One authoritative financial_movements table exactly aligned with schema v1.1.
- Shop Expense, Owner Withdrawal, Owner Introduction/Float and Financial Correction.
- CASH and TRANSFER channels.
- Exact signed DECIMAL money effects and server-derived signs.
- Scoped history/detail, explicit command services, permission-aware UI and safe same-ID recovery.
- Protected reversal and corrected replacement; original evidence remains unchanged.
- Atomic Audit and sync_changes publication.
- Opening Cash and active Cash-control enforcement for every operation that changes physical Cash.
- An internal, read-only expected-Cash component/query contract for later Reconciliation.
- Responsive phone/tablet/desktop workflows, documentation, manual acceptance and release/rollback handover.

### Explicitly excluded

- General ledger, chart of accounts, double-entry accounting or journal posting.
- Payroll, salaries, supplier accounts payable, purchase orders or expense budgeting.
- Bank-account reconciliation, bank feeds, payment gateway/card settlement or automated billing.
- Customer wallets, advances, transfers between Shops or multi-branch consolidation.
- Automatic creation of Financial Movements from a Cash variance, missing cash, Sales or Debt.
- Full Cash/Stock/Full Reconciliation, variance review, staff/device readiness or count workflows (Phase 7).
- Reports/dashboard analytics (Phase 8), full offline posting/synchronization (Phase 10), and production deployment.
- New testing frameworks or automated test suites. The repository roadmap requires manual acceptance.

No existing Phase 1–5 financial evidence, migration or accepted business rule may be rewritten to simplify Phase 6.

## 4. Binding domain and accounting rules

### 4.1 Movement types and signed effects

The stored movement_type values remain exactly those in schema v1.1:

| Stored type | Business meaning | amount_delta rule | Authority |
|---|---|---:|---|
| SHOP_EXPENSE | Money spent for the Shop | Negative | Owner or Staff with financial.record |
| OWNER_WITHDRAWAL | Owner takes business money | Negative | Active Primary Owner only |
| OWNER_INTRODUCTION | Owner adds money / float | Positive | Active Primary Owner only |
| FINANCIAL_CORRECTION | Corrected replacement evidence | Either sign, non-zero | financial.correct + reversal authority |
| REVERSAL | Exact inverse of one earlier movement | Exact opposite of original | financial.reverse |

The UI accepts a positive amount string for ordinary Expense, Withdrawal and Introduction forms. The owning service derives the stored sign from the approved movement type. A client must never choose an arbitrary signed amount or submit a trusted movement_type that bypasses the command's permission.

**Correction interpretation proposed for approval:** FINANCIAL_CORRECTION is the replacement half of a protected correction, linked to the original through replacement_for_movement_id. It is not a free-form “set cash balance” or standalone balancing entry. A correction command atomically writes (1) an exact REVERSAL of the original and (2) a signed FINANCIAL_CORRECTION replacement. A genuine event not represented by an earlier record must use its correct business movement type, not a fabricated correction. If the Owner intends a different interpretation, it must be resolved before WP1's binding contract is accepted.

A normal reversal creates one REVERSAL row with reversal_of_movement_id. It has the same Tenant, Shop and channel as the original and the exact opposite amount_delta. One direct reversal is allowed per original. A REVERSAL cannot itself be reversed. A corrected replacement is linked to the original by replacement_for_movement_id. Corrections form a validated linear causal history; parallel replacement branches and cycles are rejected. A later correction must reverse the current effective replacement and link the next replacement to that record. The original and all prior evidence remain immutable.

The v1.1 schema lists a unique reversal link, not a unique replacement link. WP1 must not invent a new schema constraint. Replacement-chain uniqueness/linearity must be enforced under the existing serialized Shop transaction boundary unless a separately approved schema amendment is required.

### 4.2 Channel and physical Cash

- Each movement has exactly one channel: CASH or TRANSFER.
- CASH changes expected physical Cash and requires the existing finalized Opening Cash baseline.
- CASH mutations are rejected while an overlapping Cash/Full Reconciliation control is active.
- TRANSFER does not change physical Cash and must not be blocked merely because Opening Cash is absent or a Cash control is active.
- A correction requires the Cash guard if either its original or replacement side has a CASH effect. This includes Cash-to-Transfer and Transfer-to-Cash corrections.
- Channel is never inferred from an external reference. A transfer reference is optional descriptive evidence, not proof of bank settlement.

Reuse Reconciliation CashControlGuard and the established transaction boundary. Do not create a second Opening Cash mechanism or weaken the existing guard.

### 4.3 Amount, description and time

- All money is a canonical decimal string at the API boundary and DECIMAL(19,2) in MySQL. Reject JSON floats, scientific notation, negative user-entered ordinary amounts, zero, excess precision and out-of-range values. Never use PHP or JavaScript floating-point arithmetic for money.
- description is required and limited to the schema's 300 characters. It is the plain-English business explanation and is also supplied as the required Audit reason; do not add an unapproved reason column.
- external_reference is optional and bounded to 120 characters.
- occurred_at may be supplied as a bounded UTC occurrence time under the existing Audit occurrence policy; otherwise it defaults to server time. posted_at is always server-generated and immutable.
- Date filters and history ordering use accepted posted_at (“Date recorded”) converted with the Shop timezone. occurred_at is displayed separately and never backdates an accepted movement into a closed Reconciliation period.
- The server derives actor, Tenant, Shop, signed amount and posted_at. The client cannot submit authority or scope.

### 4.4 Expected physical Cash

The Phase 6 query contract must preserve the approved component model:

~~~text
Expected physical Cash =
    Opening Cash baseline
  + Cash Sale receipts
  - Cash Sale refunds
  + Cash Debt receipts
  + Cash Owner introductions
  - Cash Shop expenses
  - Cash Owner withdrawals
  + net Cash Financial Corrections
~~~

Transfer events and Credit Sales are excluded from physical Cash. Financial Movement rows contribute only when channel = CASH. Since each reversal is itself an exact signed row, the net effect is computed from the original plus its valid inverse/replacement exactly once; do not exclude the original and then subtract its reversal again.

For component display, a reversal is attributed to the original movement type, while a FINANCIAL_CORRECTION replacement contributes to the correction component. The total signed Cash effect must always reconcile to the sum of all in-scope CASH movement rows. Expense/withdrawal/introduction component values are reported as positive magnitudes in the existing Reconciliation snapshot fields; cash_corrections_net remains signed.

The expected-Cash contract uses server-authoritative UTC period boundaries from Reconciliation: (period_started_at, cutoff_at]. posted_at, not user-editable occurred_at, determines inclusion. A late-posted event is not inserted retrospectively into a previously finalized period. A Cash variance never creates an automatic Financial Movement. A new movement is accepted only for a genuine new event, not to erase a shortage/surplus.

WP4 must provide the internal read-only query contract that composes authoritative Sales, Debt and Financial Movement effects. It is not a new public reconciliation API, a new count screen, a mutable balance, a stored cash ledger, or the full Phase 7 Reconciliation engine.

## 5. Permission and access model

Register these independent codes in the closed PermissionCode/catalogue and expose them to Staff permission management without granting existing Staff new powers:

| Permission | Purpose | Staff boundary |
|---|---|---|
| financial.view | Current-Shop movement list/detail/history | Explicit grant required |
| financial.record | Record an ordinary Shop Expense | Explicit grant required |
| financial.correct | Correct a posted movement using the atomic replacement workflow | Explicit grant required |
| financial.reverse | Reverse one eligible posted movement | Explicit grant required |

Primary Owner derives known-code authority as in the existing permission model. The Owner-only rule for OWNER_WITHDRAWAL and OWNER_INTRODUCTION is an additional role check: a Staff grant can never authorize an Owner action. financial.correct must not imply financial.reverse, and financial.reverse must not imply financial.correct or financial.record. A corrected replacement requires both correction and reversal authority; a standalone reversal requires reversal authority.

Every route and owning service enforces current Shop context, active membership, current grants, Tenant/Shop status and AccessGate. READ_ONLY permits authorized reads but no writes. BLOCKED/CLOSED, inactive membership, foreign scope and Platform Admin without Shop membership are denied. debt.manage, inventory permissions and reconciliation permissions do not grant Financial Movement authority. Do not add a broad financial.manage shortcut.

## 6. Work-package structure

Four packages are the smallest sensible split. They separate the shared schema/posting foundation, the Owner-only movement types, high-risk immutable correction, and the final expected-Cash contract. Every package includes its own backend, API, UI, security, Audit/change publication, documentation and manual checks. WP4 is not a documentation-only cleanup package.

| Package | Complete outcome | Dependency / Owner verification |
|---|---|---|
| **WP1 — Financial Movement foundation and Shop Expenses** | v1.1 schema, shared service/query, permissions and first complete Expense workflow | Accepted Phases 1–5; Owner records and reviews Cash/Transfer expenses and sees protected history |
| **WP2 — Owner Cash Withdrawal and Introduction/Float** | Owner-only withdrawal and introduction workflows using the shared movement foundation | WP1 accepted; Owner can record money taken/added without Staff impersonation or Cash/Transfer confusion |
| **WP3 — Protected reversal and correction** | Exact inverse, atomic corrected replacement, causal history and recovery | WP2 accepted; Owner safely corrects a mistaken amount/channel/description without editing the original |
| **WP4 — Expected Cash integration and phase closure** | Authoritative read-only Cash components, complete history/integration and handover | WP3 accepted; Owner can hand-calculate expected Cash from all approved sources and trace every component |

### WP1 — Financial Movement foundation and Shop Expenses

**Outcome:** a Shop can record an Expense once, with clear Cash/Transfer treatment and immutable evidence.

1. Add the exact v1.1 financial_movements schema in a new parent-first Phase 6 migration and equivalent MySQL 8.4 SQL. Preserve every prior migration. Implement same-scope Tenant/Shop/user/self-reference FKs, unique public UUID, unique reversal link, required indexes and approved type/sign checks. Document cross-row constraints that MySQL CHECK cannot enforce.
2. Add a FinancialMovement module with focused input normalization, exact decimal value object, repository, scoped query, schema-readiness gate and explicit application service. Reuse CommercialAccess for the established owning transaction/authority boundary and CashControlGuard for CASH effects. No controller-owned business logic and no generic CRUD repository.
3. Deliver Expense preview/post, safe same-UUID result retrieval, paginated current-Shop history and detail. A same-UUID equivalent retry returns the existing scoped result; changed content conflicts. No generic idempotency store, outbox or Sync Receipt is introduced before Phase 10.
4. Add financial.view and financial.record. Register future financial.correct and financial.reverse codes without activating their routes before WP3. Existing Staff grants are unchanged.
5. Add the FINANCIAL Audit action/resource vocabulary and financial_movement change-resource registration with the owning writer. Posting, Audit and change publication commit together. GET and preview create no business Audit/change event.
6. Add the Expense task to the existing Shop workspace, not a parallel application shell. Show amount, channel, explanation, optional reference and occurrence date; review before saving; display saved result/history. The UI must explain that Transfer does not affect physical Cash.
7. Add explicit API/module contracts, migration/SQL register entries, setup/rollback guidance and P6-01–P6-12 manual cases.

**Exit gate:** native MySQL migration/schema review, exact Expense signs and channels, Cash guard behavior, permissions, Audit/change atomicity and Owner manual acceptance.

### WP2 — Owner Cash Withdrawal and Introduction/Float

**Outcome:** the Primary Owner can record money taken out of or introduced into the Shop without confusing business Cash with Transfer.

1. Add explicit Owner Withdrawal and Owner Introduction/Float command services. Do not expose an arbitrary movement-type selector or rely on the browser to enforce the Owner-only rule.
2. Require an active PRIMARY_OWNER membership at preview and confirmation. Recheck under the same Tenant → Shop → membership lock boundary. Staff with financial.record, financial.correct or financial.reverse must still be denied these two Owner-only movement types.
3. Accept CASH or TRANSFER. Cash operations use CashControlGuard; Transfer operations do not require Opening Cash and do not affect physical Cash.
4. Use the same schema, exact-decimal, public UUID, Audit, change and history infrastructure from WP1. Use explicit action-specific service methods even if the underlying repository is shared.
5. Extend the existing Money Movements workspace with “Owner took cash” and “Add cash/float” actions, prominent channel labels, confirmation summaries and causal history links.
6. Add operations guidance explaining that Owner Introduction is a genuine later movement, not a substitute for the Opening Cash baseline.

**Exit gate:** Owner-only authorization at service level, Cash/Transfer behavior, signed effects, duplicate safety, auditability and Owner manual acceptance.

### WP3 — Protected reversal and correction

**Outcome:** mistakes are corrected through linked new evidence; no posted movement is edited or deleted.

1. Deliver reversal preview/post for one eligible movement. Lock the original under the established Shop boundary, validate scope and current reversal state, and create one exact-opposite REVERSAL row. Enforce same channel/scope, one direct inverse, no self-link, no reversal-of-reversal and repeat-safe same-identity recovery.
2. Deliver an atomic correction preview/post. The command has distinct permanent UUIDs for the reversal and replacement, the original public UUID, the corrected channel/amount/description and a required explanation. Confirmation recomputes eligibility and validates a review fingerprint under lock.
3. The owning transaction inserts both the exact inverse REVERSAL and the FINANCIAL_CORRECTION replacement linked to the original. Either both rows, both required Audit events and all change rows commit, or none commit. A failed replacement must never leave a misleading half-correction.
4. Recheck financial.view plus financial.reverse for reversal; correction additionally requires financial.correct. The replacement must not silently gain authority from the old record's creator or from debt.manage. Recheck FULL access, active membership and current grants at confirmation.
5. CashControlGuard is required when either side of a correction/reversal changes physical Cash. A Transfer-only inverse/replacement must not be blocked by Opening Cash.
6. Expose a scoped correction-state/history graph showing original, reversal and replacement, actor, explanation, channel, amount and recorded time. No original row is updated/deleted. No raw Audit metadata, internal numeric ID or private support data is exposed.
7. Add dependency/chain validation, operational recovery instructions and P6-21–P6-30 manual cases, including rollback after an injected failure between the two inserts.

**Exit gate:** exact inverse and corrected replacement, immutable history, chain/duplicate protection, permission separation, Cash control and atomic failure/recovery manually accepted.

### WP4 — Expected Cash integration and phase closure

**Outcome:** all approved Cash sources can be reconciled to a deterministic expected amount without implementing the later Reconciliation engine.

1. Implement the internal read-only expected-Cash component contract using the existing Opening Cash baseline, Cash Sale receipts/refunds, Cash Debt receipts and net CASH Financial Movement effects. Reuse source-owned queries/semantics; do not copy or reinterpret Sales/Debt rules in a controller.
2. Accept server-provided Shop scope and UTC period boundaries from Reconciliation. Use posted_at in (period_started_at, cutoff_at]. Return exact decimal strings and a component breakdown that maps to the existing reconciliation_financial_counts fields.
3. Prove Transfer exclusion, signed correction/reversal treatment, no double counting, and correct late-posting behavior. The query is read-only: no snapshot writes, Audit events, automatic balancing rows or variance mutation.
4. Complete scoped history filters and detail/causal navigation across all movement types. Use bounded pagination and Shop-local “Date recorded” filters based on posted_at; display occurred_at separately.
5. Complete responsive loading, empty, validation, permission-denied, READ_ONLY, blocked, offline, uncertain-save and stale-review states. Money posting remains online-only; no operation is queued or replayed offline.
6. Run integrated synthetic journeys across Sale → Cash/Transfer payment/refund → Debt collection → Expense → Owner Withdrawal/Introduction → correction/reversal → expected-Cash component result. Preserve all Phase 4/5 semantics.
7. Deliver final requirement/source/manual matrix, integrated result register, defect disposition, API/module/operations notes, release decision template and rollback handover. Record the accepted source commit and inventory only after actual verification; deployment remains separately authorized.

**Exit gate:** all expected-Cash components reconcile to a hand-calculated ledger, no Phase 6 omission remains, all package gates are accepted and the Owner explicitly accepts the complete Phase 6 programme.

## 7. API and command boundary

The exact route names and DTOs are finalized in WP1's integration contract before the first writer is implemented. The proposed resource shape is:

| Method | Proposed route | Purpose |
|---|---|---|
| GET | /api/v1/shop/financial-movements | Bounded current-Shop history |
| GET | /api/v1/shop/financial-movements/{public_uuid} | Scoped detail and same-ID recovery |
| POST | /api/v1/shop/financial-movements/expenses/preview | Validate and review an Expense command |
| POST | /api/v1/shop/financial-movements/expenses | Post an Expense |
| POST | /api/v1/shop/financial-movements/owner-withdrawals/preview | Review Owner Withdrawal |
| POST | /api/v1/shop/financial-movements/owner-withdrawals | Post Owner Withdrawal |
| POST | /api/v1/shop/financial-movements/owner-introductions/preview | Review Owner Introduction |
| POST | /api/v1/shop/financial-movements/owner-introductions | Post Owner Introduction |
| POST | /api/v1/shop/financial-movements/{uuid}/reversal-preview | Review an eligible inverse |
| POST | /api/v1/shop/financial-movements/{uuid}/reverse | Post an inverse |
| POST | /api/v1/shop/financial-movements/{uuid}/correction-preview | Review atomic inverse + replacement |
| POST | /api/v1/shop/financial-movements/{uuid}/correct | Post atomic correction |

These are explicit commands, not a general CRUD API. There is no PUT/PATCH/DELETE for posted movement evidence. No client-supplied tenant_id, shop_id, actor, posted_at, internal FK, signed amount_delta or reversal state is trusted.

A command uses permanent UUIDv4 public identity, channel, positive decimal-string amount where the form represents a magnitude, description, optional external_reference and optional bounded occurred_at. Correction additionally carries distinct reversal/replacement UUIDs and the authoritative review fingerprint. Exact DTOs, error codes, fingerprint inputs and recovery rules are documented before dependent package work.

Stable conflict/error families should include setup-not-ready, scoped not-found, invalid type/sign/channel, Opening Cash required, active Cash control, already reversed, invalid correction chain, stale review, changed UUID content and permission/access denial. Existing standard validation and response envelopes remain authoritative. No error reveals whether a foreign Tenant's movement UUID exists.

## 8. Transaction, Audit and change-publication rules

All write commands use one owning database connection and one transaction:

1. Normalize/validate the command and canonical UUIDs.
2. Begin through the established CommercialAccess transaction boundary; lock Tenant, Shop and active membership; recheck current permissions and AccessGate.
3. If any original or resulting effect is CASH, invoke CashControlGuard before locking Financial Movement domain rows. Transfer-only operations skip the Cash guard.
4. Lock the original movement/causal chain in a stable deterministic order; validate scope, type, sign, reversal and replacement eligibility; re-evaluate the command under lock.
5. Insert the new movement row(s). Never mutate/delete existing posted rows.
6. Append required typed FINANCIAL Audit evidence using the canonical RequestContext correlation and the required business explanation.
7. Publish deduplicated financial_movement UPSERT change rows through ChangeSequenceService.
8. Commit.

ChangeSequenceService locks the active generation. **No further business/domain locks may be acquired after change publication begins.** If any movement, Audit or change insert fails, the entire transaction rolls back. Preview and GET operations publish nothing. The transaction participant never opens or commits an independent transaction.

Proposed Audit vocabulary, to be added only with the owning writers:

- Category: FINANCIAL (already present).
- Resource: financial_movement.
- Actions: financial_movement.recorded, financial_movement.reversed and financial_movement.corrected.
- All actions require the bounded explanation/reason. A correction writes the reversal and correction Audit evidence in the same transaction and canonical correlation.
- Metadata contains only minimized public causal IDs, type, channel and signed amount; never request-body dumps, credentials, private references or raw internal IDs.

Change publication adds financial_movement to the closed ChangeSequenceService resource registry. Each authoritative row is published once in the same transaction; a correction publishes the reversal and replacement identities together. No GET, preview, print or failed command emits a success change.

## 9. UI and wording requirements

Use the existing Shop shell, workspace registry/navigation, components, local icon vocabulary and visual tokens. Do not introduce a parallel navigation system or a new dependency.

Proposed user-facing labels:

| Internal concept | User-facing wording |
|---|---|
| Financial Movements | Money movements |
| SHOP_EXPENSE | Record expense |
| OWNER_WITHDRAWAL | Owner took cash |
| OWNER_INTRODUCTION | Add cash / float |
| FINANCIAL_CORRECTION | Correct a money record |
| CASH | Cash |
| TRANSFER | Transfer |
| occurred_at | Date the money movement happened |
| posted_at | Date recorded |
| reversal | Reversed record / Undo this record |
| expected physical Cash | Expected cash in the shop |

Avoid debit/credit jargon. Every review/confirmation identifies action, amount, channel, explanation, date and effect on expected Cash. Distinguish Transfer clearly: “Transfer does not change the cash physically in the shop.” Use accessible labels, logical focus, touch-comfortable controls and cards on narrow screens; do not require routine horizontal scrolling.

## 10. Migration, rollout and rollback discipline

- The new financial_movements table is introduced only by a new Phase 6 migration and equivalent complete MySQL 8.4 SQL under docs/database/sql/phase-06/. Never edit an applied Phase 1–5 migration or its paired SQL.
- Use the exact v1.1 column types, controlled values, FKs, indexes and checks. Actor FK must match Shield's actual users.id type (INT UNSIGNED); domain IDs remain BIGINT UNSIGNED. Confirm physical parent definitions on the implementation base.
- Add the migration to docs/database/migration-register.md and document schema readiness. Missing/partial schema fails closed with a clear setup-required response; it must never report an empty history or zero Cash effect.
- Rehearse on an empty database and a representative populated Phase 5 database. Verify migration status, native MySQL 8.4 constraints, equivalent SQL, exact signs and FK enforcement.
- MySQL DDL implicitly commits. A partial failure is not repaired by forging migration history or blindly rerunning. Document the exact inspection/recovery procedure. A down migration may remove only positively identified empty Phase 6 data on a disposable development database; it must refuse populated evidence.
- Production/runtime credentials must not receive UPDATE/DELETE on financial_movements. Use the separate privileged migration account. Never roll back by dropping posted financial history.
- Deployment, secret/environment changes, production data mutation and live rollout are not part of the planning PR or automatically authorized by Owner acceptance.

## 11. Manual acceptance and quality gates

The [manual acceptance plan](Phase_6_Manual_Acceptance_Plan.md) assigns deterministic fixtures and P6-01–P6-40 to the four packages. The [requirements matrix](Phase_6_Requirements_Matrix.csv) maps each approved requirement to its owner package and manual cases.

No PHPUnit, Pest, Jest, Vitest, Cypress, Playwright or other automated test framework/suite is introduced. Engineering checks (syntax, static source review, frontend build, exact SQL comparison and bounded diagnostic scripts where appropriate) support but do not replace native manual acceptance.

Each package handover must identify its exact base/head commit, migration/setup instructions, synthetic fixtures, steps actually performed, expected/observed values, result register, limitations and defects. Native MySQL, transaction rollback, two-session concurrency, real HTTP/session/CSRF, browser/device/keyboard and print behavior must be marked Pass only when actually observed. Missing evidence stays Blocked/Not run; Owner package acceptance is recorded separately and does not manufacture test results.

## 12. Approval and progression

**Proposed sequence:** Owner reviews and approves this plan and its binding correction/permission/expected-Cash decisions. Then WP1 is authorized as a separate implementation package. After each package, pause for Owner review and explicit acceptance before beginning the next package.

This planning change creates no Financial Movement schema, route, permission, UI or business record. It does not implement Phase 6, alter existing evidence, run migrations, deploy, or authorize production operations.

## Delivery progression — 13 November 2026

The Owner approved this plan and authorized WP1 only: [dated approval](../acceptance/2026-11-13-phase-06-plan-owner-approval.md). WP1 is implemented for review, with [integration contracts](Phase_6_WP1_Integration_Contracts.md), [delivery report](Phase_6_WP1_Delivery_Report.md), [native manual guide](../manual-verification-phase-6-wp1.md) and [unexecuted results register](Phase_6_WP1_Manual_Results.csv). This does not pre-accept WP1. WP2–WP4 remain planned and unimplemented. Approval supersedes the proposal's earlier awaiting-approval status; the approved scope above is preserved.
