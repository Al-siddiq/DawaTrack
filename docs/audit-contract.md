# Audit vocabulary and context contract

Phase 0 defines vocabulary only; persistence begins in Phase 1 before sensitive mutations.

An audit event will identify a stable action code, occurred/recorded time, actor type and actor identifier, authority context, tenant/shop scope when applicable, subject type and identifier, correlation ID, optional device/operation context, required reason where policy demands it, outcome, and safely redacted before/after evidence. Audit records are immutable evidence and participate in the owning transaction. Platform Admin authority is distinct from tenant membership. Secrets, tokens, credential material, and unrestricted request bodies are never audit payloads.

WP2 identity mutations treat Audit failure as transaction failure. Atomic onboarding records distinct `owner.created` and `tenant.onboarded` evidence. Staff create/status/credential actions, permission grant/revoke and Shop profile changes include the server-derived Tenant/Shop, actor membership, target public identity, required reason and bounded safe change metadata. Permission Audit includes Shop authority context; repeated no-op mutations return conflict and do not invent evidence.
