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
