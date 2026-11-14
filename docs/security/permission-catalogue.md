# Permission catalogue v1

Staff have no authority unless an active grant exists. Unknown codes fail closed. Primary Owners derive ordinary Tenant authority from role.

`inventory.view`, `inventory.adjust`, `sale.record`, `sale.reverse`, `debt.manage`, `debt.payment.record`, `reconciliation.view`, `reconciliation.count`, `reconciliation.review`, `reconciliation.finalize`, `reconciliation.adjust`, `report.view`, `staff.manage`.

Reversal, review, finalization and adjustment permissions remain separate. Every grant/revocation is Tenant-scoped and audited; revocation requires a reason and retains its history.

The Owner interface obtains the catalogue, active grants and complete grant/revoke history from the server whenever a Staff permission panel opens. Codes are grouped by their namespace and may be searched. Existing grants are never inferred from local checkbox state. Repeated grant/revoke requests return conflict and write no false state-change Audit event. Grant targets must be active same-Tenant Staff; revoked history remains visible for deactivated Staff.

## WP4 Platform operations

| Permission | Scope |
|---|---|
| `platform.subscriptions` | Create immutable paid/renewal/transition/Trial-extension entitlement evidence and events |
| `platform.tenants` | Suspend/reactivate/close Tenants and deactivate/reactivate Shops |

Both permissions are assigned only to `platform_admin`; their routes also require recent MFA. They do not establish Tenant membership or impersonation.

## Phase 2 Audit permissions

| Permission | Scope |
|---|---|
| `platform.audit` | Read platform-scoped Audit evidence; requires `platform_admin` and recent MFA |
| `platform.audit_support` | Establish a reasoned, explicit Tenant/Shop diagnostic Audit scope; requires `platform_admin`, recent MFA, and successful SUPPORT evidence |

Primary Owner activity history is role-authorized through `ShopContext`; Staff have no Audit-history permission by default. Platform support access is **Platform-authorized diagnostic reading, not Tenant membership or impersonation**.

## Phase 3 registry

`product.manage` authorizes Product create/edit/deactivate. `inventory.view` authorizes Product and Stock Position reads. `inventory.adjust` authorizes low-stock threshold changes. The approved future codes `inventory.intake` and `inventory.reverse` are registered fail-closed but have no WP1 route or UI. Owner authority remains role-derived; Staff requires an active explicit grant.

## Phase 3 WP2

`inventory.intake` now authorizes Draft create/edit/item removal/discard and Opening/Receipt post. It is independent of `product.manage` and `inventory.adjust`. `inventory.view` authorizes Intake and movement evidence reads.

## Phase 3 WP3

`inventory.adjust` authorizes damaged, expired, Owner stock use, missing/loss and signed Correction. `inventory.reverse` independently authorizes Movement/Intake reversal and sensitive replacement linkage. Inactive Products remain eligible for these accountability commands. Every command requires FULL access and a reason.

Phase 3 closure review confirms no implication between `product.manage`, `inventory.adjust`, `inventory.intake`, and `inventory.reverse` for Staff. `inventory.view` controls Product/stock/Intake/movement reads. The read-only `inventory:diagnose` command is an operator CLI, not a Shop permission or route.


## Phase 4 WP1 Opening Cash authority

Only an ACTIVE PRIMARY_OWNER can view/control Opening Cash; Staff reconciliation grants do not authorize first-baseline setup. Mutations require FULL access and active membership rechecked under lock. READ_ONLY permits Owner reads; BLOCKED/CLOSED deny. Later Customer/Sale/Return permissions will be registered in their owning package. MFA freshness now derives only from a successfully completed Shield Email2FA verify action, never redirect generation.

## Phase 4 WP2

New closed-registry codes: `customer.view`, `customer.manage`, `customer.private`, `sale.view`, `sale.price_override`; reuse `sale.record`. Customer view + manage authorize lifecycle writes; private permission additionally controls phone/notes reading, phone search and writing. Omitted private edits preserve stored fields. Sale view + record authorize draft mutation and minimal active identity/Product lookup; price override requires its independent grant. Quick-create additionally requires Customer view + manage. Owners derive known-code permissions; Staff require each active grant. `debt.manage` is not an alternate authority. No COGS/profit field is exposed from drafts. See [exact matrix](../modules/phase-04-wp2.md); no Sale post/return/reverse/collection workflow is introduced here.

## Phase 4 WP3

`sale.record` now authorizes ordinary forward posting when combined with `sale.view`; both are checked inside the owning transaction. New `sale.profit.view` independently permits historical Cost basis/COGS/gross-profit receipt fields, in addition to `sale.view`. Without it cost fields are excluded from Sale queries/responses and masked in the UI. Owner derives known-code authority; Staff grants remain separate from `sale.price_override`, Customer/private, Inventory, reversal and debt permissions. No Return/reversal authority is activated by WP3. See [WP3 matrix](../modules/phase-04-wp3.md).


## WP4-B — 2026-11-05

`sale.return` independently grants Return create/update/preview/post/discard and estimate/eligibility together with `sale.view`. Owner derives known-code authority; Staff requires the explicit grant. `sale.reverse` alone does not authorize Return posting. `sale.profit.view` separately gates all cost/COGS/margin fields. FULL active authority is rechecked inside mutations.


## WP4-C — 2026-11-06

sale.reverse + sale.view authorize correction preview/post and replacement linkage. sale.return alone cannot reverse; sale.reverse alone cannot post replacement Sale/Return. sale.profit.view still separately gates compensation historical costs/COGS/signed margin. Current FULL/membership/host scope and Cash authority are enforced at confirmation.


## WP4-D — 2026-11-06

Integrated UI controls follow sale.return/sale.reverse/sale.record independently, and cost rendering/printing also requires sale.profit.view. Backend authority remains decisive. Read-only/offline/pending/unknown-outcome controls prevent client commands, without granting offline posting. Customer-private fields are not added to commercial history.


## Phase 5 WP1 — read-only debt foundation

`debt.view` + `customer.view` authorize current-Shop customer/bill balance GETs. `debt.manage` remains recognized for compatibility but grants no shortcut. New `debt.payment.allocate`, `debt.adjust`, `debt.payment.reverse` are independently registered for their future owning commands; WP1 has no collection/adjust/reverse routes. Existing `debt.payment.record` is not sufficient to read or explicitly allocate on its own. Staff grants are never expanded automatically. Owner derives known-code authority. Debt output contains no private Customer phone/notes/cost/profit; existing private-detail policies remain separate. READ_ONLY reads remain allowed, BLOCKED/CLOSED and inactive membership/host scope deny. [Exact matrix and package boundaries](../Phase-5/Phase_5_WP1_Integration_Contracts.md).


## Phase 5 WP2 — collection and receipts

customer.view + debt.view authorize scoped receipt/status/history reads. Add debt.payment.record for ordinary preview/post and debt.payment.allocate for EXPLICIT selection. All are rechecked through the owning current-FULL transaction; READ_ONLY reads remain available. debt.manage/customer.private/adjust/reverse do not imply collection. Shop context exposes matching collect_debt_payments/allocate_debt_payments hints; backend authority remains decisive. New recovery_scope is an opaque user/Shop browser-marker namespace, never a token or permission. [WP2 exact contract](../Phase-5/Phase_5_WP2_Integration_Contracts.md).

## Phase 5 WP3 activation

Registered debt.payment.reverse and debt.adjust now own explicit reviewed commands; existing Staff grants are not expanded. Every debt resource also requires customer.view + debt.view. Atomic corrected replacement independently needs debt.payment.record and, for EXPLICIT, debt.payment.allocate. A reverse-only Staff may undo but cannot save a corrected replacement; adjust-only Staff cannot undo a payment or Sales-owned Return. Existing sale.return/sale.reverse authority remains independent. No debt.manage shortcut. Mutation authority/entitlement/membership rechecked inside owning transaction; Cash guard covers original or replacement Cash effect. Correction reason on payment DTO is limited to reverse-authorized users and hidden on Print; adjustment reason is authorized debt context. No Customer private notes/phone, raw Audit hashes, cost/profit or credential storage is added.

## Phase 5 WP4 statement reads

No new grant. Both customer.view and debt.view required on every statement page, regardless of cursor. READ_ONLY may read; current Shop membership/AccessGate still governs BLOCKED/CLOSED/foreign/Admin context. sale.view only controls contextual Sale links and its separate endpoint; no correction authority is inferred. Current user/membership additionally binds the token; page state is AES-256-GCM opaque/authenticated with purpose-separated deployment encryption.key, never a browser credential or default source secret. Statement DTO excludes Customer private fields/Audit reasons/financial cost and command hashes; private live total hidden in both Print paths.

## Phase 6 WP1 — 13 November 2026

Phase 6 WP1 registers financial.view (read Money movements), financial.record (save expenses; also needs view), financial.correct and financial.reverse (reserved independently for WP3, no routes yet). Staff management uses the Money movements group and plain labels; existing Staff grants are not expanded. Primary Owner follows existing membership authority; a Platform Admin role alone is not Shop access. Record requires FULL active commercial access; authorized reads remain subject to existing READ_ONLY policy. No legacy debt.manage fallback.


## Phase 6 WP2 — 13 November 2026

Owner withdrawals/introduction require financial.view + financial.record AND a current ACTIVE PRIMARY_OWNER membership, selected by current tenant/membership/user inside the owning Tenant/Shop/membership lock boundary. Staff with every financial grant are still denied. No new grant is added. Browser role/capability is presentation only. READ_ONLY prevents commands; authorized financial.view reads permit status recovery. Correct/reverse remain independently reserved for WP3.


## Phase 6 WP3 — independent correction authority

financial.reverse authorizes a standalone reversal and is required for every correction. financial.correct is independent and additionally required to create a corrected replacement. Neither permission implies the other or financial.record; debt.manage, reconciliation and inventory grants do not substitute. Owner authority remains role-derived. Staff require explicit active grants. Every preview and confirmation rechecks current active membership, FULL access, Tenant/Shop state and grants under the owning lock boundary. READ_ONLY permits authorized reads only; BLOCKED/CLOSED, inactive membership and foreign scope are denied. No broad financial.manage permission is added.
