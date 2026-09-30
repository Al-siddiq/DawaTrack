# Phase 1 subscription and lifecycle operations

## Authority

These APIs are Platform operations, not Tenant impersonation. The caller must be an authenticated Shield `platform_admin`, possess `platform.subscriptions` or `platform.tenants`, and have recent Email2FA-backed MFA. CSRF and the Platform write throttle apply. Every mutation requires a nonblank reason of at most 500 characters.

## Entitlement model

Subscriptions are immutable half-open periods `[starts_at, ends_at)`. Current means `starts_at <= UTC now AND ends_at > UTC now`; ended periods are history and later periods are scheduled. Events are evidence, never access authority. The service uses a Tenant-specific MySQL advisory lock plus one database transaction for period, event, and Audit.

Supported commands are `ACTIVATE`, `RENEW`, `UPGRADE`, `DOWNGRADE`, and `EXTEND`. Starter/Growth activation is manually confirmed administration and stores only a bounded external reference, not payment-card data. Renewal keeps the prior plan. Upgrade/downgrade compare against the latest scheduled/effective plan. Existing periods are never truncated: a transition must start at or after the last entitlement end. Trial extension must be contiguous and add 1–30 whole days. An overlap returns `SUBSCRIPTION_OVERLAP`.

Downgrade checks current active Staff while holding the subscription lock. Scheduling and immediate attempts both fail with `SUBSCRIPTION_STAFF_CAP_CONFLICT` if active Staff exceeds the target snapshot. No Staff is deactivated automatically. Staff activation uses the existing identity/membership serialization; operators must recheck capacity at the transition boundary as part of the documented scheduled operational procedure.

Expiry remains time-derived. `php spark subscriptions:record-expiries <platform-admin-user-id>` appends a repeat-safe `EXPIRED` event and Audit evidence for ended periods; it does not mutate entitlement periods or access.

## Lifecycle and access

Tenant transitions: `ACTIVE → SUSPENDED`, `SUSPENDED → ACTIVE`, and `ACTIVE|SUSPENDED → CLOSED`. Closed is terminal. Shop transitions are `ACTIVE ↔ INACTIVE`. Nothing is deleted.

The server evaluates access on every request:

1. hostname resolves an active verified Shop candidate;
2. authenticated active membership proves Tenant authority;
3. `CLOSED` Tenant yields `CLOSED`;
4. suspended Tenant or inactive Shop yields `BLOCKED`;
5. active Tenant/Shop without a current entitlement yields `READ_ONLY`;
6. active Tenant/Shop with a current entitlement yields `FULL`;
7. role/permission controls the particular action.

Platform and custom hostnames share the same Tenant/Shop IDs, so changing a hostname cannot bypass lifecycle restrictions.

## Append-only Audit operations

Application code exposes insert-only Audit behavior. The deployment database owner should grant the runtime account only `SELECT, INSERT` on `audit_events` and must not grant `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, or `ALTER` on that table. Example owner procedure (replace identifiers safely):

```sql
REVOKE UPDATE, DELETE ON dawatrack.audit_events FROM 'dawatrack_runtime'@'%';
GRANT SELECT, INSERT ON dawatrack.audit_events TO 'dawatrack_runtime'@'%';
SHOW GRANTS FOR 'dawatrack_runtime'@'%';
```

The runtime account still needs normal least-privilege rights on business tables. Privilege changes belong to deployment operations, not an application trigger or migration.
