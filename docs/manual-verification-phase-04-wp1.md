# Phase 4 WP1 owner manual verification

Status update 2026-11-03: owner reported all 15 checklist items working and authorized WP2. See `acceptance/2026-11-03-phase-04-wp1-owner-acceptance.md` for exact provenance and the distinction from the additional S cases. Original execution instructions follow. Use a development MySQL 8.4 database, not production. Record tested commit, PHP/MySQL versions, hostname, role and observed result for each case. All cases begin UNEXECUTED; mark PASS / FAIL / BLOCKED yourself.

## Setup and migrations

Back up the development schema. Install existing dependencies and apply the existing explicit Settings → Shield → App namespace order if starting fresh; see phase-01-bootstrap.md. On an existing Phase 3 database use `php spark migrate:status`, `php spark migrate --namespace App`, then `php spark migrate:status`. Two new migrations must apply in order: 2026-11-02-100000 sessions then 100100 financial counts. Do not run the raw SQL as well as migrations. Verify SHOW CREATE TABLE against paired phase-04 SQL. A repeat App migration has nothing pending. Runtime DB user needs normal scoped table rights and SELECT/INSERT on Audit/changes; migration identity owns DDL.

Use active current-entitlement Shops A and B on verified hostnames, their respective Owners and a Staff account. Use independent browser profiles for separate sessions. No schema reset is needed for most checks: retain aborts; use a separate fresh Shop for finalized-baseline scenarios.

## Owner workflow

| ID | Steps | Expected result |
|---|---|---|
| O1 | Owner A opens Opening Cash | No baseline; Start enabled; no denomination fields |
| O2 | Start; refresh/reopen page | Same active COUNTING attempt and permanent UUID; one start Audit/change |
| O3 | Save 0, then 12500.50 | Exact 0.00 / 12500.50; zero variance; no business activity components |
| O4 | Submit -1, 1.001, 1e4, 1,000, oversized value and JSON float directly | Validation rejection; saved amount/evidence unchanged |
| O5 | Confirm saved amount; inspect DB | COUNTS_COMMITTED; actual=expected=baseline; Cash start=cutoff=confirmation UTC; current generation/watermark fixed |
| O6 | Review then finalize; refresh | FINALIZED receipt, amount/actor/time/reference; protected observations |
| O7 | Repeat finalize POST using same UUID | Same final state; no additional finalization Audit/change |
| O8 | Try count/abort/edit final row through APIs | State conflict; no mutation or false-success evidence |
| O9 | Start another UUID after finalized baseline | OPENING_ALREADY_FINALIZED; no second baseline |
| O10 | On fresh Shop: start/count/confirm; abort with reason | ABORTED preserved with amount, reason, actor/time; fresh UUID may start |
| O11 | Abort without reason; attempt to mutate an aborted record | Rejected; retained original untouched |
| O12 | Two sessions start simultaneously with different UUIDs | One active attempt; other conflict; no duplicate active scope |
| O13 | Two sessions finalize/repeat same attempt; race count/commit | One finalization; stale writes rejected or serialized; confirmation freezes saved observation |
| O14 | After confirmation, transition active generation using controlled development SQL transaction | Review/finalize rejects OPENING_GENERATION_CHANGED; abort/restart/reconfirm works; old cutoff retained |
| O15 | Change generation while COUNTING, before confirmation | Confirmation binds current generation; no false stale-generation success |

## Authority, rollback and interface

| ID | Steps | Expected result |
|---|---|---|
| S1 | Staff opens route directly, even with reconciliation grants | 403; no access or mutation |
| S2 | Owner B requests A's UUID; forge tenant/shop inputs | Scoped 404 or rejection; no foreign data/changes |
| S3 | Expire entitlement after start; try count/confirm/finalize | READ_ONLY permits viewing, denies all mutations |
| S4 | Suspend/close Tenant or deactivate Shop/member | Protected APIs deny; no successful mutation |
| S5 | Expire session; omit CSRF on write | Sign-in/CSRF rejection; no queued or false successful step |
| S6 | Block network before action; lose response after submission; refresh | Offline unavailable; permanent start UUID/state retrieval; no duplicate attempt/finalization |
| S7 | Revoke test DB INSERT on Audit, invoke a valid count; restore permission | Entire count rollback; status/amount/sequence unchanged |
| S8 | Revoke INSERT on sync_changes, invoke confirmation/finalization; restore permission | Business/Audit rollback too; no partial final baseline |
| S9 | Inspect GET responses and history | No internal IDs/secret data; Cache-Control no-store; no GET Audit/change writes; latest 50 labeled |
| S10 | Phone 360px, desktop, 200% zoom, keyboard Tab/Enter | Readable/wrapping evidence, reachable controls, labeled one amount, no horizontal clipping; status/errors announced |
| S11 | Save new amount but do not confirm; refresh | Saved observation shown, still COUNTING; Cash not ready |
| S12 | Review confirmation; attempt a new count | Rejected; abort is the explicit restart path |

## Cash guard integration check (no Sales implemented)

Using a development-only PHP harness, begin a transaction and invoke `CashControlGuard::assertCanAffectCash` with a real server-derived context. Without baseline: OPENING_CASH_REQUIRED. With a counting opening: still denied. With finalized baseline and no active Cash scope: allowed. With an active standard CASH/FULL control fixture in an explicit development transaction: CASH_CONTROL_ACTIVE. Roll back the harness/fixture. Calling outside a transaction throws LogicException. Pure Transfer/full-credit callers will skip this Cash-specific guard in WP3; no fake sale endpoint is added merely to demonstrate it.

## Foundation regressions

- Admin: complete valid email challenge; wait 15 minutes. Visit /login and magic-link form while signed in. Admin access still requires a fresh challenge; GET redirects do not renew freshness. Invalid code never establishes it.
- Owner resets Staff credentials: temporary password returned once; Audit metadata accepted; forced reset is required; old sessions are rejected after successful commit. Force Audit failure on reset: old credentials remain and no cache revocation is performed for a rolled-back reset. Credential-reset response is no-store.
- Two independent development PHP harnesses/connections (not two tabs sharing the same session): BEGIN and lock an Intake header FOR UPDATE in A; invoke item/header edit/discard in B. B waits on the same header. Invoke the real IntakePostingService through the same owning connection in A and commit/release; never fake a posted header with ad hoc SQL; B must re-read POSTED and reject. Repeat with edit completing first: posting uses the committed edit. Existing posted history remains intact.
- Rotate DNS token while old verification is pending: stale verification cannot finalize the new challenge.
- Run `npm ci && npm run build`; edit Shop template and rebuild: generated render and runtime asset refresh together. Rebuild without edits is reproducible.
- Existing Product/Intake posting, manual/reversal, Staff/permissions and Audit navigation still work.

## Owner decision record

Tested commit: ____  Environment: ____  Date: ____
Results / evidence references: ____
Remaining issues: ____
Decision: ACCEPT / REQUEST CORRECTIONS / BLOCKED

Record actual observations; the implementation delivery does not fill in results on the owner's behalf. Later Return/compensation policy decisions are described in Phase_4_WP1_Integration_Contracts.md and must be resolved before dependent packages.
