# Phase 2 WP3 manual verification

Use a production-equivalent MySQL 8.4 database, least-privilege runtime account, two browser sessions, and Chromium responsive/accessibility tooling. Record actual identifiers separately; never copy secrets into this document. Static review is not a substitute for a runtime result.

## WP3 runtime checklist

1. Create representative Phase 1 sensitive events and inspect actor, authority, target scope, reason, subject, occurrence/creation times, canonical correlation, and bounded metadata.
2. As Primary Owner A, list and open current-Shop and Tenant-wide events.
3. Substitute Tenant B event/resource/correlation/cursor identifiers; verify no existence disclosure.
4. As Staff, verify both Owner Audit endpoints and navigation are denied.
5. Exercise normal Platform Audit unauthenticated, wrong group, without permission, with stale MFA, and with correct authority.
6. Attempt Tenant/Shop filters on normal Platform routes; verify they cannot establish support scope.
7. Attempt support access without target, without reason, without permission, and with stale MFA.
8. Establish support access and inspect the SUPPORT event actor, target, reason, canonical correlation, category/action, and minimized metadata.
9. Verify no Tenant membership, Tenant session, Owner identity, or impersonation is created.
10. Force the support Audit insert to fail and verify no target results are returned.
11. Change target/reason and verify new SUPPORT evidence is required.
12. Open a foreign event UUID inside another support scope and verify non-enumerating not-found behavior.
13. Follow caused-by/shared-correlation relationships and verify they never escape the active authority scope.
14. Seed safely recognizable forbidden/sensitive metadata through an isolated verification fixture and verify it is rejected/redacted before output.
15. Seed equal `occurred_at` values and page through them; verify no duplicate or missing event.
16. Test malformed/foreign/expired cursor, page sizes 0 and 51, invalid UUIDs/filter characters, reversed dates, and a range over 366 days.
17. Exhaust Owner and Platform Audit throttle buckets; verify `RATE_LIMITED` and `Retry-After`, including attempts with changing event UUID paths.
18. Attempt POST/PATCH/PUT/DELETE against guessed Audit mutation routes; verify none exists.
19. Compare `sync_changes` count before/after Owner list/detail, Platform list/detail, and support entry; verify no rows are created.
20. Verify routine Audit pagination does not create Audit; support entry creates exactly one SUPPORT event.
21. At 320, 360, and 390 px, tablet, desktop, and 200% zoom, verify chronological cards, wrapped UUIDs, readable banner, no routine horizontal scroll, and 44px-class controls.
22. Complete filter, support, detail, pagination, reset, close, and leave-support flows by keyboard; verify visible focus and associated labels.
23. Exercise initial loading, populated, empty, empty-filtered, validation, server, permission, offline/network, read-only, blocked/closed, expired-session, stale-MFA, rate-limit, unavailable-event, invalid-cursor, missing-reason, invalid-target, support-Audit-failure, and unavailable-related-evidence states.
24. Confirm recoverable errors retain filters, reason, target, and selected scope; leaving support clears client-side target/reason/access state.

## Carried WP1/WP2 runtime debt

Re-run every item in `docs/manual-verification-phase-02-wp1.md` and `docs/manual-verification-phase-02-wp2.md`, especially HTTP/Spark correlation, database-backed authority/causality, `actor_device_id IS NULL`, Audit privilege denial, forced atomic rollback, MySQL schema equivalence, two-connection generation serialization, publisher/watermark mutual blocking, delayed lower-sequence commit, rollback gaps, generation transition serialization, and Audit/change canonical-correlation equality.

## Result vocabulary

For every item record exactly one of:

- **PASS — executed successfully**
- **FAIL — executed and failed**
- **BLOCKED — not executed**, followed by the concrete missing dependency or access.

Phase 2 cannot receive final acceptance while mandatory runtime checks remain blocked.
