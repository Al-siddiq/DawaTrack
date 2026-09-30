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
