# Permission catalogue v1

Staff have no authority unless an active grant exists. Unknown codes fail closed. Primary Owners derive ordinary Tenant authority from role.

`inventory.view`, `inventory.adjust`, `sale.record`, `sale.reverse`, `debt.manage`, `debt.payment.record`, `reconciliation.view`, `reconciliation.count`, `reconciliation.review`, `reconciliation.finalize`, `reconciliation.adjust`, `report.view`, `staff.manage`.

Reversal, review, finalization and adjustment permissions remain separate. Every grant/revocation is Tenant-scoped and audited; revocation requires a reason and retains its history.
