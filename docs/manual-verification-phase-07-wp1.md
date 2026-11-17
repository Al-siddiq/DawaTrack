# Phase 7 WP1 — native manual verification

**Status:** delivered for Owner review; not accepted, merged or deployed. Plan accepted; only WP1 authorized.

Use an isolated backed-up development copy at the reviewed WP1 commit with synthetic Shops/users. Never edit/seed posted financial or Audit evidence directly. Production migration is a separate authorized operation.

## Setup

1. Fetch and checkout codex/phase-7-wp1-devices-control-20261115; record exact HEAD.
2. Install locked Composer and npm dependencies; run `npm run build`.
3. Record PHP/Composer/MySQL/browser versions, Shop host/timezone, current actor/grants and migration status. Native MySQL8.4 is required; SQLite does not prove checks/DECIMAL/locks.
4. Rehearse migration100000 Device table then100100 Audit FK using the existing App migration procedure after accepted Phase6. See database register. Use SQL twins on a separate isolated copy, not in addition to migrations on the same database. Check preflight orphan/cross-scope Audit Device links and never repair them by rewriting Audit.
5. Inspect resulting DDL/constraints, verify schema readiness failure on missing/partial setup, and demonstrate isolated backup restore. MySQL DDL is not transactionally rolled back.
6. Run `npm run ui:release:check -- --output=docs/Phase-7/Phase_7_WP1_Artifact_Manifest.json`. On genuine content differences stop/review; never regenerate the manifest to hide edits. Use documented CRLF-only repair then repeat plain check if applicable.

## P7-01–P7-12

Use the approved [48-case acceptance plan](Phase-7/Phase_7_Manual_Acceptance_Plan.md) for full expected results; complete only WP1 cases now. Record observations in [WP1 results](Phase-7/Phase_7_WP1_Manual_Results.csv). No case is Pass merely because the package was delivered or source parsed.

- P7-01: native empty/upgrade/schema/FK/preflight/partial-DDL and protected rollback.
- P7-02: Owner browser enrollment, permanent marker, actual secure HttpOnly cookie and verifier, same proof replay, foreign/Staff/rebind denial.
- P7-03: bounded list, rename, timestamps and safe DTO; current status independent of another installation's UUID.
- P7-04: revoke with reason; old proof cannot validate; explicit recovery rotates active proof only; revoked entry needs a new Owner enrollment. Verify original history retained.
- P7-05: actual Audit/Device actor authority, domain/Audit/change atomicity and publish-last rollback. Check no raw cookie/verifier/body is logged.
- P7-06: review the frozen-set/typed Audit handoff contract. No ready/not-ready runtime result is claimed; real standard start/ACK is WP2. Mark the unexecutable portion Blocked until WP2, retain contract review evidence separately.
- P7-07: new independent reconciliation codes, Owner administration restriction, no expanded Staff grants, native grant revocation races.
- P7-08: no PIN/provision/outbox/cursor/receipt/transport or fake trust/sync timestamps; offline_*/sync timestamps remain null. Network tab has no automatic POST on reconnect.
- P7-09: existing Opening and Cash/Transfer business regressions through real workflows, including F1/F3 expected-Cash repair through the documented temporary local-only CLI harness (never a public route).
- P7-10: native service lock ordering and existing writes; later standard-scope conflict cases need WP2/3 runtime and remain Blocked here. Do not invent a standard session by editing database rows just to tick a case.
- P7-11: Owner and Staff journeys on phone/tablet/desktop, keyboard/focus, name labels/errors/dialog/reason/read-only/empty/offline/unknown-save/storage-failure; verify no hidden device admin controls for Staff.
- P7-12: locked build/native syntax, exact inventory, source/public parity, SQL equivalence, safe restore and reviewed acceptance record.

## Cookie/unknown response probes

On HTTPS staging inspect __Host-dt_device: Secure/HttpOnly/SameSite=Strict, Path=/, no Domain; secret absent from JSON and JavaScript storage. Enrollment issuance has no last_validated_at until explicit cookie-proven online check. Simulate committed lost enrollment reply: use Check saved status; never silently create new UUIDs. If row saved but cookie was lost, Owner explicitly recovers same marker with reason; do not label404 conclusive failure. Rename/revoke unknown replies require status/history inspection before another command. Reconnect never reposts.

Switch user in the same Shop/browser: cookie remains installation context only, grants derive from new active membership. Switch Shop/host/Tenant: no foreign Device data/proof is trusted; each origin is separately enrolled. Revoke/lose proof and inspect READ_ONLY/BLOCKED/CLOSED and expired-session behavior. Do not use browser localStorage IDs as credentials.

## Release boundary

A new additive Device schema/FK is required before this UI/API can be used. WP1 is not a Phase7 completion or production authorization. Preserve backups, Device/Audit/business rows and compatible full code/assets. Do not down evidence-bearing Device schema or restore older business data to undo a UI release. See [operations](operations/phase-07-wp1-devices.md).
