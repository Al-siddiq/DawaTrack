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
