# Phase 2 Audit access operations

## Authority diagnostics

Owner requests must resolve an active Primary Owner membership through the current verified Shop hostname. Staff denial is expected. Platform routes require the `platform_admin` group, the route-specific `platform.audit` or `platform.audit_support` permission, and a Platform MFA timestamp no older than 15 minutes.

Normal Platform Audit intentionally returns only platform-scoped evidence. Tenant/Shop evidence is available only through the explicit support POST workflow. Support evidence is action `support.audit_accessed`, category `SUPPORT`, resource `support_audit_scope`, and contains a bounded reason and public target identifiers. It emits no `sync_changes` row.

## Incident checks

Use the API response/header request UUID to correlate safe application logs and the Audit event. Do not log filters containing reasons or event metadata. For suspected scope leakage, verify both list and detail with a foreign event/cursor/correlation; responses must be empty, invalid, or not found without disclosing existence. A support evidence failure must prevent all target results.

Audit is append-only. There are no application update/delete routes. Production runtime grants must continue to deny `UPDATE` and `DELETE` on `audit_events`.

## Pagination and throttling

The API orders by occurrence time and internal tie-break ID, but cursors expose only occurrence time and public UUID. A malformed, foreign-scope, or unavailable cursor is rejected. Owner Audit is limited to 60 requests/minute and Platform Audit/support to 30 requests/minute per authenticated identity, with one bucket per surface so changing guessed event UUIDs cannot bypass the limit.
