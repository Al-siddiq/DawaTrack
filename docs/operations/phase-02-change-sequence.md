# Phase 2 change-sequence operations

Apply the Phase 1 schema and seed first, then migration `2026-10-04-000000 CreatePhaseTwoSyncChanges`. Inspect `SHOW CREATE TABLE sync_changes` against the paired phase-02 SQL file.

Exactly one active generation is required. Diagnose with:

```sql
SELECT id, generation_uuid, status, activated_at FROM sync_generations ORDER BY id;
SELECT sync_generation_id, COUNT(*), MIN(change_sequence), MAX(change_sequence)
FROM sync_changes GROUP BY sync_generation_id;
```

Never repair generation/change state by editing sequence rows. A missing/multiple active generation causes `SYNC_GENERATION_UNAVAILABLE` and rolls back the owner transaction. Generation transition procedures must lock the existing active selection with `FOR UPDATE`, change the epoch in one transaction, and use the same boundary as publishers and watermark readers.

The runtime account needs `SELECT, INSERT` on `sync_changes`; it does not need generic update/delete for normal publication. Backups include generations, changes, Audit, and business rows together.
