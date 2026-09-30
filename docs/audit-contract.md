# Audit vocabulary and context contract

Phase 0 defines vocabulary only; persistence begins in Phase 1 before sensitive mutations.

An audit event will identify a stable action code, occurred/recorded time, actor type and actor identifier, authority context, tenant/shop scope when applicable, subject type and identifier, correlation ID, optional device/operation context, required reason where policy demands it, outcome, and safely redacted before/after evidence. Audit records are immutable evidence and participate in the owning transaction. Platform Admin authority is distinct from tenant membership. Secrets, tokens, credential material, and unrestricted request bodies are never audit payloads.

WP2 identity mutations treat Audit failure as transaction failure. Atomic onboarding records distinct `owner.created` and `tenant.onboarded` evidence. Staff create/status/credential actions, permission grant/revoke and Shop profile changes include the server-derived Tenant/Shop, actor membership, target public identity, required reason and bounded safe change metadata. Permission Audit includes Shop authority context; repeated no-op mutations return conflict and do not invent evidence.

## Central Phase 1 metadata safety and bounds

Every `AuditService::record()` call now passes metadata through one recursive policy. Forbidden nested keys covering passwords/hashes, MFA codes/secrets, recovery secrets, magic links, raw verification tokens, TLS keys/secrets, cookies, session/auth/bearer tokens, API secrets, and raw binary/image bytes cause the owning operation to fail before persistence. Metadata is limited to four nested levels, 40 entries per array, scalar/array values, and 500 characters per string. Reasons are trimmed and limited to 500 characters.

Normal Phase 1 sensitive mutations write business state and required Audit inside the same transaction. Audit insertion/policy failure therefore rolls back the mutation. The only bootstrap exception remains MFA session establishment before the Phase 1 Audit table exists; it is explicitly guarded by a table-existence check and does not apply after normal schema installation.

The application contains no Audit update/delete endpoint or service method. Production append-only protection is completed operationally by granting the runtime database principal `SELECT, INSERT` but not `UPDATE`/`DELETE` on `audit_events`; see `docs/operations/phase-01-subscriptions-and-lifecycle.md` for exact owner commands.
