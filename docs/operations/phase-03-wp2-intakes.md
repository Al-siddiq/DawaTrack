# Phase 3 WP2 operations

Apply WP1 then WP2 migrations in register order. Inspect the two deferred future FKs and final scoped pointer FK. Posting lock order is header → Products ascending → positions ascending → evidence/state → Audit → generation/change rows. Diagnose a failed post by its request correlation; never manually mark a Draft posted or overwrite a position. Use the owner checklist for two-session blocking and rollback verification.
