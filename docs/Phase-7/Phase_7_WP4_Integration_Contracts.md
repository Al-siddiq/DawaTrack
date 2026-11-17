# Phase 7 final integration contract

17 November 2026 (Africa/Lagos). WP3 accepted; WP4 candidate prepared for review. This extends the accepted [WP1](Phase_7_WP1_Integration_Contracts.md), [WP2](Phase_7_WP2_Integration_Contracts.md) and [WP3](Phase_7_WP3_Integration_Contracts.md) contracts. Their business rules remain authoritative.

## Ownership and effects

Reconciliation owns the check lifecycle, captured evidence, difference records, staff context and notes. Inventory owns changes to current stock. Sales, customer payments and financial movements own their posted sources. Device owns installation identity and credential validation. Sync owns publication and active generation. Audit evidence stays append-only. See the [final source matrix](Phase_7_WP4_Source_Effect_Matrix.csv).

Root locking remains Tenant → Shop → current membership before source/product locks; generation publication remains last. Disjoint Cash/Stock checks may coexist; Full blocks both. Start captures one coherent cutoff and relevant Device set. Unconfirmed replies retain original IDs; a missing read does not prove a failed write. No automatic retry, cash balancing, automatic stock adjustment or durable offline workflow is introduced.

## Bounded Stock reads

`StockCountEvidence.progress` returns scalar total/count-completed values after validating the start manifest and row/proof totals. Detail no longer loads every Product/count/proof. `page` validates page1+, per_page1–50 and search≤100 characters, limits count rows in SQL, then loads only their causal per-Product proof (at most page-size+1). Finalized variance/adjustment lookup is also limited to page IDs and page-size+1. Search uses captured names/units, including literal percent/underscore; later Product renaming does not change the captured list. Fixed ASCII identity joins and parameter binding remain scoped to Tenant/Shop/session. Search case matching follows the native database collation; native non-ASCII behavior and plans require review.

`one` validates one Product proof for saving an observation or explanation, with count/root totals checked. Unrelated proof corruption may therefore surface only when its page is read or the full count is confirmed. Commit, finalize and stock-adjustment paths retain whole-set immutable digest validation. No expected value is returned to a blind counter. Bounded returned rows/round trips do **not** imply constant database work: aggregate scans, JSON proof predicates, search and OFFSET can grow; native EXPLAIN/timing is a release gate. Large start/commit/finalize operations necessarily inspect the complete captured set.

## Stock/Full count revision v2

Endpoints still accept `expected_commit_revision`, an opaque hash, with the same stale409 behavior. The server now derives it from control identity/scope, latest immutable count-write Audit id, total/completed count, and Full actual-cash/revision. All API count writes serialize under the owning check lock and append the existing Audit event; no-op retries append nothing. Same-number writes with a new audit id invalidate stale tokens even if timestamps tie. Unrelated Device/notes/publications do not invalidate it. No expected quantity, cost or expected digest enters this token, avoiding a guessable blind-value oracle. It is a concurrency token, not authorization or a database-tamper signature. Full evidence integrity is checked separately inside commit.

A page open before upgrade may hold a v1 token. A409 requires a fresh read and a check of the saved counts before an explicit retry; never silently resubmit. No migration or persisted token change is needed. The legacy helper remains for source compatibility; active endpoints issue v2. Compatible code rollback to accepted WP3 can issue v1 again after reload.

## Privacy and publication

Control detail omits cancel-reason free text for a blind reader or someone without review permission, including historical cancelled checks. Device exclusion reasons are omitted until an authorized, non-blind review after count confirmation, or for a cancelled check. Internal reason records remain intact. Identity/status/readiness totals remain usable. Existing source-route blind restrictions and review-only notes/staff/late evidence remain in force; inspect every route in native acceptance, including print/cache and historical records.

Late-source association still runs in the source transaction before generation locking and keeps each typed source association. Multiple sources targeting the same Full check now append only one check-refresh UPSERT per publication batch, including when an UPSERT is already present. This does not suppress distinct source changes or evidence. Native rollback/race checks remain required.

## UI and recovery

Successful command detail is reused once, reducing a duplicate detail GET; explicit “Check what was saved” always reads again. Stale replies are ignored before secondary requests. After generation change, count/save/confirm/review/readiness progress stops and cancellation remains available with a reason. Server enforcement remains authoritative. A confirmed/recovered cancellation drops unsaved item drafts; already saved counts remain evidence and never become a new baseline. Unknown cancellation is cleared only after reading the same check as cancelled.

The shared native dialog now wraps Tab and Shift+Tab within visible, enabled controls, keeps Escape handling and returns focus to the opener. It preserves caller key handlers. Plain shop text is governed by [the writing guide](../ui/plain-english.md); technical names remain in the API and developer documentation only.

## Schema, configuration and access

No WP4 migration, dependency, environment setting, credential change or new grant. All nine Phase 7 migrations are prerequisites. Keep the existing private encryption key and OpenSSL available; never rotate a working key as a troubleshooting shortcut. Do not expand Staff grants. READ_ONLY may inspect allowed reads but all mutations stay denied. Device enrollment/revocation remains Owner-only; a user session is not an installation credential. For exact route/grant/input/error details use the linked earlier contracts and [operations](../operations/phase-07-final-handover.md).
