# Opening Cash operations

Apply migrations 2026-11-02-100000 and 100100 after Phase 3 using App namespace migrations; paired SQL is reference, not a second install path. Run `npm ci && npm run build` and deploy generated assets with the matching application version. No new service, worker, credential or environment variable is required.

Owner navigation → Opening Cash → Start → enter/save one amount → Confirm saved amount → Review → Finalize. Use the confirmed physical amount, not a sales estimate or Owner Introduction. Finalized baseline/observations cannot be edited. Retained abort with reason is the restart path before finalization. Later standard Reconciliation uses the same tables; WP1 does not expose it.

After server generation transition, an already-confirmed attempt requires abort/reconfirmation; do not patch generation/cutoff/amount in SQL. After an uncertain HTTP result, reload state; do not fabricate a new transaction. Never “repair” a finalized amount directly. Investigate mismatch with immutable evidence and approved later reconciliation procedures.

Read-only diagnosis: inspect scoped reconciliation_sessions, their single CASH financial-count row, related reconciliation_session Audit actions and sync_changes. Expected=actual=baseline, components=variance=zero, fixed confirmation cutoff, actor/time and generation must agree. Abort evidence remains. No background sync, outbox or offline baseline exists.

Development rollback only: roll back the exact two new App migration batches in reverse order after checking migrate:status; financial counts must drop before sessions. Do not run an unbounded rollback or drop accepted Phase 1–3 tables. Rollback destroys Opening evidence and is not a production recovery technique. For production use a backup/restore plan and an explicitly reviewed deployment, not these development instructions.
