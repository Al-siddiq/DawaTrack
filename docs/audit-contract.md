# Audit vocabulary and context contract

Phase 0 defines vocabulary only; persistence begins in Phase 1 before sensitive mutations.

An audit event will identify a stable action code, occurred/recorded time, actor type and actor identifier, authority context, tenant/shop scope when applicable, subject type and identifier, correlation ID, optional device/operation context, required reason where policy demands it, outcome, and safely redacted before/after evidence. Audit records are immutable evidence and participate in the owning transaction. Platform Admin authority is distinct from tenant membership. Secrets, tokens, credential material, and unrestricted request bodies are never audit payloads.
