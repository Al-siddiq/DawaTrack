# Phase 2 WP1 manual verification

This checklist records the verification boundary for Typed Audit Integrity and the Protected-Command Contract. It is not an automated test suite.

## Executable static checks

1. Lint every PHP file under `app/`.
2. Compile the Shop Vue template and rebuild checked-in browser assets.
3. Confirm no application call to the removed array-based `AuditService::record()` remains.
4. Confirm no `sync_changes`, Device table/FK, Sync Receipt, idempotency table/key, or `DUPLICATE_OPERATION` implementation was added.
5. Compare the Phase 1 Audit PHP migration and raw SQL table definition; confirm WP1 changed neither artifact.

## Runtime/database checks

Using the setup in `docs/operations/phase-01-bootstrap.md` on MySQL 8.4:

1. Send a valid lowercase UUIDv4 `X-Request-ID`; verify the identical value in the response header, API `meta.request_id`, request logs, and resulting Audit row.
2. Repeat with missing, malformed, non-v4, and uppercase/noncanonical IDs; verify one replacement lowercase UUIDv4 flows through all locations.
3. Run `php spark domains:verify` and `php spark subscriptions:record-expiries <admin-id>`; verify each command prints/logs one correlation and its Audit rows use that value.
4. Attempt forged user, membership, Tenant, Shop, and Platform combinations at controlled service boundaries; expect `AUDIT_CONTEXT_INVALID`, no foreign disclosure, and rollback.
5. Attempt non-null Device attribution; confirm no public typed input exists and every inserted `actor_device_id` is `NULL`.
6. Exercise valid server occurrence, bounded supplied occurrence, future/old invalid occurrence, valid/invalid operation UUID.
7. Exercise forbidden metadata keys at every nesting level, depth five, 41 entries, 501-character value, object/resource value, and JSON over 16,384 bytes; expect `AUDIT_METADATA_REJECTED` and rollback.
8. Link a valid same-scope causal event; then attempt missing, cross-Tenant, cross-Shop, and platform/Tenant-mismatched causes; expect non-enumerating `AUDIT_CAUSE_INVALID`.
9. Force Audit validation/insertion failure after representative onboarding, Staff, permission, settings, branding/domain, subscription, and lifecycle mutation work; confirm no business commit.
10. With the runtime DB principal, prove `SELECT`/`INSERT` succeed as intended and `UPDATE`/`DELETE` on `audit_events` fail.
11. Repeat representative Phase 1 sensitive actions and verify their action, category, resource, actor authority, scope, reason, metadata, correlation, and null Device attribution.
12. Repeat permission, Staff status, lifecycle, domain, and subscription no-op/conflict commands; confirm existing domain-specific `409` outcomes and no false Audit.

## Browser/accessibility checks

Exercise Staff status, permission, and credential-reset reason dialogs at 320, 360, and 390 CSS pixels, tablet, desktop, and 200% zoom. Verify:

- initiating focus is restored;
- initial focus enters the reason field;
- Tab/Shift+Tab remain inside the open dialog;
- Escape closes only when no request is pending;
- label, live error, visible focus, and touch targets remain usable;
- blank/overlong reason is rejected;
- double submit is prevented;
- entered reason survives `409`, `422`, network, timeout, and server failures;
- successful existing workflows remain functional.

## Acceptance record

Record each check as `PASS`, `FAIL`, or `BLOCKED` with command/action, date, environment, and evidence. A blocked check is not a pass. Any critical/high defect prevents WP1 completion.
