# Phase 2 server change-sequence boundary

`sync_changes` is compact notification/index evidence. It stores generation, scope, resource identity, `UPSERT`/`DEACTIVATE`, server time, and optional operation/correlation causality. It stores no business payload, actor, Device, response, cursor, receipt, or replay outcome.

## Normative publication lock order

Every publisher uses its owning business transaction and the same database connection:

1. obtain all domain/advisory/row locks;
2. mutate and validate the business state;
3. append required Audit;
4. lock the sole active `sync_generations` row with `FOR UPDATE`;
5. acquire no later domain lock;
6. insert all sibling change rows;
7. commit or roll back, releasing the generation lock.

`AUTO_INCREMENT` allocates unique numbers but does not guarantee commit order. The active-generation lock serializes publication: a later publisher cannot allocate until the earlier holder commits or rolls back. Rollback gaps are harmless.

## Watermark

After obtaining its domain locks, the caller locks the active generation and uses a current locking read:

```sql
SELECT change_sequence
FROM sync_changes
WHERE sync_generation_id = ?
ORDER BY change_sequence DESC
LIMIT 1
FOR UPDATE;
```

The result is `(generation_uuid, change_sequence)`, with sequence zero for an empty generation. The caller holds the generation lock until the watermark-consuming business evidence commits. This is not a Device cursor, acknowledgement, receipt, or pull API.

## Generation transitions

Initial seeding and any future activation/retirement lock the active-generation selection inside their transaction. No Phase 2 generation-management UI or endpoint exists.
