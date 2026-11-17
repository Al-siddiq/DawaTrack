# Phase 7 — native manual acceptance plan

**Status:** Proposed, 15 November 2026 (Africa/Lagos). All cases initially Not run. No automated test suite is introduced.

Use backed-up isolated synthetic Shops, real approved UI/services and native MySQL 8.4/HTTP/browser sessions. Never seed or repair posted evidence by SQL. Capture exact commit/inventory, versions, Shop timezone, actor/grants, Device/source UUIDs, expected and observed results, evidence reference, reviewer/time. A package-level acceptance is distinct from individually observed outcomes.

Fixtures F1–F8 and policy decisions are defined in [the implementation plan](Phase_7_Implementation_Plan.md). Each expected outcome below is a required observation, not a claim of success.

## P7-01 — Device migration and native upgrade (WP1)

Apply Device schema and Audit FK in empty and Phase 6 copies; inspect types/indexes/FKs; reject orphan Device actor and partial setup; preserve old evidence.

## P7-02 — Enrollment credential and scope (WP1)

Enroll/recover own installation through authenticated Owner workflow; retry same identity; deny foreign Tenant/Shop rebind, spoofed UUID/credential and Staff administration; inspect safe DTO/cookie behavior.

## P7-03 — Status and rename (WP1)

Inspect recognizable online status/last-seen; reasoned rename/status behavior; unrelated Device and internal credentials never leak.

## P7-04 — Revocation and session independence (WP1)

Revoke installation with reason; old proof rejected; ordinary user session does not become Device authority; lost credential requires explicit recovery.

## P7-05 — Audit actor and publication contract (WP1)

Verify scoped validated Device context, action/resource vocabulary and bounded redacted metadata; inject failure and verify domain/Audit/change rollback.

## P7-06 — Frozen-set evidence contract (WP1)

Demonstrate complete per-Device start-association representation with count/digest; no oversized array or fake ACK; none/PENDING/REVOKED semantics documented.

## P7-07 — Permission matrix (WP1)

Independent grants registered correctly; Owner exclusion restriction; no Staff grant expansion or financial/inventory shortcut; revoke grant during operation.

## P7-08 — No offline transport (WP1)

Inspect routes/storage/SW: no provision/PIN/outbox/cursor/receipt/pull transport or false sync timestamps; credentials absent from URL/browser script storage/logs.

## P7-09 — Existing Opening and Cash regression (WP1)

Retain Owner Opening save/confirm/review/finalize/abort, baseline and generation rules; Cash Sale/Debt/movement denied before Opening; Transfer allowed.

## P7-10 — Writer root/guard integration (WP1)

Trace every quantity/cost/Cash source path and lock order, including pure-credit Sale and replacement/compensation; no late domain lock after generation.

## P7-11 — Device UI parity and recovery (WP1)

Complete enrollment/status/revoke on phone/tablet/desktop and keyboard; exercise denied/read-only/offline/error/unknown response and current-installation status.

## P7-12 — WP1 build and handover (WP1)

Locked build, syntax/paired SQL review, exact inventory and documentation pass; record actual versions, restore evidence and Owner package decision separately.

## P7-13 — Standard Cash start and cutoff (WP2)

Start from valid finalized Opening/prior Cash; select server time/baseline; freeze exact generation/watermark; deny client-selected baseline/cutoff and foreign predecessor.

## P7-14 — F1 composition and variance (WP2)

Create all F1 sources through workflows; expected 35000.00, actual 34500.00, variance -500.00; exact components/counts and safe source attribution.

## P7-15 — F2 channel and inverse composition (WP2)

Verify Cash/Transfer/Credit exclusions, debt inverse and replacement, financial cross-channel correction; expense original+inverse+replacement nets -800.00 once.

## P7-16 — Blind Cash response policy (WP2)

Inspect count/detail/source reads/errors/Print/DOM/cache before commit from same and other counting sessions; no expected/components/variance leak.

## P7-17 — Count save zero stale-write and atomic commit (WP2)

Save/reload 0.00 explicitly; null is incomplete; reject negative/excess precision/stale save; commit once; later count edits denied and preserved input on failure.

## P7-18 — Cash baseline rollover (WP2)

Finalize shortage/surplus/zero controls; next baseline equals prior actual; later +1000 from 34500 gives 35500; no balancing Financial Movement.

## P7-19 — Relevant Devices and unavailable device (WP2)

Pin all ACTIVE devices at start; ack actual target online; unavailable device blocks commit/finalize; no timeout/new enrollment silently changes set.

## P7-20 — Owner exclusion and revocation (WP2)

Deny Staff/no-reason exclusion, foreign/future/old-generation ack and revoked proof; retained pinned obligation requires explicit reasoned resolution; acknowledgements immutable.

## P7-21 — Cash review Staff context and finalization (WP2)

Reveal only after commit and review authority; verify actual-minus-expected signs, required nonzero explanations, roles and immutable final normalized variance.

## P7-22 — Cash abort and illegal transitions (WP2)

Abort COUNTING/committed/review attempts as allowed with reason; retain observations, do not advance baseline; deny transition skips/finalized edits.

## P7-23 — Unknown outcome and replay (WP2)

Lose save/commit/finalize reply; scoped status lookup and same-ID repeat recover one effect; foreign UUID/changed body conflict and404 inconclusive handling.

## P7-24 — Cash notes and package handover (WP2)

Append typed final-variance note with authorized user; no update/delete; inspect bounded history/no private leaks; UI parity/build/inventory and Owner decision.

## P7-25 — Stock snapshot dataset and first period (WP3)

Freeze all relevant positions including inactive/zero/negative; first period begins Shop creation by approved convention; no later Product changes dataset/cost snapshots.

## P7-26 — F3 quantity cost and zero count (WP3)

Expected51.0000 actual48.0000 variance-3.0000; fractional four-place quantities, counted zero and six-place cost including UNESTABLISHED/RETAINED; no invented cost.

## P7-27 — Stock and Full complete lifecycle (WP3)

Run STOCK and FULL all stages with different prior Cash/Stock cutoffs; one common new cutoff; FULL all-or-nothing count commitment and finalization.

## P7-28 — All Stock writers under control (WP3)

Attempt Intake/replacement/manual/reversals/Sales all payments/Returns/compensation/adjustment during STOCK/FULL; forbidden new effects rejected without partial writes.

## P7-29 — Overlapping starts native race (WP3)

Two sessions race CASH/CASH STOCK/STOCK FULL/any; only legal winner active; predecessor/snapshot/readiness consistent and no deadlock ordering regression.

## P7-30 — Disjoint controls and allowed activity (WP3)

CASH and STOCK may coexist; appropriate unrelated Transfer-only events and effect-free drafts allowed; FULL conflicts with both; finalized/aborted locks release.

## P7-31 — Blind Stock reads and progress (WP3)

Search/paginate/count with no expected/cost/variance in all relevant endpoints and sessions; saved progress accurate; no missing count auto-zero or later count edits.

## P7-32 — Variance/FK/history integrity (WP3)

Inspect exactly-one-source Cash/Stock variance checks and late Stock Movement scoped variance FK; deny foreign/unfinalized source, history edits and unbounded lists.

## P7-33 — Staff context and later notes (WP3)

Freeze ON_DUTY/COUNTER/REVIEWER/FINALIZER identities/windows; later finding appended only; no automatic blame; full approved note length without Audit overflow.

## P7-34 — Stock adjustment and later Sale (WP3)

After F3 finalization post Sale-2, then adjustment-3: current49→46; frozen51/48/-3 unchanged; cost state and reason/Audit/source links correct.

## P7-35 — Adjustment repeat concurrency and authority (WP3)

Two adjust requests/reply loss yield one effective variance delta; same-ID recovery; deny different second adjustment, superseded older-control variance, zero/source misuse, generic reversal and insufficient grants.

## P7-36 — Late event and no Cash adjustment (WP3)

Late posted source joins correct later period and linked evidence without changing earlier cutoff/snapshots; finalization/adjustment creates no Cash balancing event.

## P7-37 — Integrated native schema rehearsal (WP4)

Rehearse all ordered migrations on empty and prior-phase copies and raw SQL equivalence on separate copies; identify real partial-DDL recovery and preserve protected evidence.

## P7-38 — Audit/change write failure rollback (WP4)

Inject each required domain/Audit/publication failure using controlled native setup; verify no orphan snapshots/variances/notes/movement/ack or partial aggregate publication.

## P7-39 — Commit finalize abort concurrency (WP4)

Race save/commit/finalize/abort and response loss for all scopes; one valid transition, no partial FULL evidence/baseline advance and reliable same-ID recovery.

## P7-40 — Cutoff versus in-flight writer (WP4)

Native sessions post every scope writer around start barrier and generation lock; expected snapshot, server time and change watermark describe one coherent state.

## P7-41 — Generation and late-boundary fixture (WP4)

Retire/change generation during count/review; deny completion and safe abort/new attempt; equality/exclusive start/inclusive cutoff and F6 backdating preserve old evidence.

## P7-42 — Cross-route blind/privacy security (WP4)

Inspect network/API/source/detail/history/Print/cache for blind bypass, internal IDs, actor/device credential leakage and foreign-tenant resource enumeration.

## P7-43 — Authentication authorization CSRF access (WP4)

Native HTTP session/CSRF/domain/MFA boundaries as applicable; READ_ONLY reads vs denied commands, suspended/closed/inactive/grant revocation and Staff field restrictions.

## P7-44 — Responsive and accessibility parity (WP4)

Complete all scopes/devices/count/review/notes/adjustment on phone/tablet/desktop, keyboard/zoom; labels/focus/dialogs/touch/virtual keyboard and non-color variance meaning.

## P7-45 — Offline stale unknown-result recovery (WP4)

Disconnect/restart/reconnect at every stage; server-saved progress survives; unsaved input honestly labeled; no automatic repost/offline finalization/durable offline claim.

## P7-46 — Full day integration and bounded queries (WP4)

Intake→Sale→Debt→expense→all controls→notes→Stock adjustment→Audit; inspect bounded pagination/search/query plans, no N+1/load-all/storm or changed frozen dataset.

## P7-47 — Release inventory compatible recovery (WP4)

Build exact candidate, verify inventory and migration status, demonstrate isolated backup restore and compatible code/asset rollback; never down/delete business evidence.

## P7-48 — Whole Phase 7 decision (WP4)

Reconcile32 requirements/48 cases, source/observed evidence and defects; no unresolved critical/high blocker; record Owner decision against commit/inventory and separate production authorization.
