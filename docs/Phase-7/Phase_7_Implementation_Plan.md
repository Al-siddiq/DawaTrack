# Phase 7 — Reconciliation Control Engine: strategic implementation plan

**Date:** 15 November 2026 (Africa/Lagos)
**Status:** OWNER-ACCEPTED — 15 November 2026; WP1 only authorized. See the dated approval record.
**Reviewed baseline:** merged main at `4266400` (closure PR #49), following Phase 6 WP4 integration PR #48 (`1677106`). Application content matches accepted WP4 `9483c57`; closure content matches the previously prepared handover.
**Delivery model:** four sequential, independently deliverable work packages. Each is implemented only after its authorization and accepted before its successor begins.

[Owner plan approval / WP1 authorization](../acceptance/2026-11-15-phase-07-plan-owner-approval.md). WP1 is Owner-accepted; WP2 only is authorized and delivered for native manual review. WP3–WP4 are not authorized. See [WP1 acceptance](../acceptance/2026-11-16-phase-07-wp1-owner-acceptance.md).

## 1. Authority and entry position

This plan implements, without replacing:

- [Strategic roadmap — Phase 7](../DawaTrack_Strategic_Phased_Implementation_Roadmap.md#phase-7--reconciliation-control-engine).
- [Technical Blueprint §7](../DawaTrack_Technical_Blueprint.md#7-reconciliation-engine): lifecycle, blind count, fixed cutoff, variance, Staff accountability and baseline rollover; §8 for later synchronization.
- [Database Schema Design v1.1 §§12, 15–16](../DawaTrack_Database_Schema_Design.md#12-reconciliation-schema): approved tables, decimals, scoped constraints and Device foundations.
- [API conventions](../api/conventions.md), [permission catalogue](../security/permission-catalogue.md), [Audit contract](../audit-contract.md), [publication/watermark lock contract](../sync/phase-02-change-sequence.md), [migration register](../database/migration-register.md), [UI architecture](../ui/architecture.md) and [Phase 6 release handover](../operations/phase-06-final-release.md).

The Owner reports Phase 6 PRs integrated and main deployed. Git fetch confirms merged main; the live deployment, applied migration status and native runtime are not independently observed here. Preserve historical native result gaps rather than converting that report into per-case Pass. Before WP1 changes a development database, record actual baseline commit, migration state, PHP/Composer/MySQL/browser versions and a tested isolated backup/restore. Existing production remains unchanged by planning.

Existing code supplies OpeningCashService, CashControlGuard, ExpectedCashQuery, reconciliation session/financial-count tables, Stock projections/movements, exact-money helpers, owning transactions, immutable Audit and generation-bound change publication. Phase 7 extends these foundations; it does not recreate them.

## 2. Complete outcome and scope

At phase acceptance the Owner/authorized Staff can register/inspect relevant Devices online, start Cash/Stock/Full controls, establish a server-fixed cutoff and independent periods, enter blind physical counts, commit once, review exact shortage/surplus, preserve Staff context/explanations, finalize or abort, inspect immutable history, append later notes and make a separately authorized Inventory-owned Stock adjustment.

The full chain is: start → COUNTING → COUNTS_COMMITTED → REVIEW → FINALIZED; meaningful incomplete controls may become ABORTED. Readiness is a gate/substate of the approved workflow, not an invented persistent lifecycle status. Count drafts are editable only before commitment; final evidence is immutable.

Included: one physical Cash amount; Stock quantities/cost snapshots; Cash component snapshots; Staff responsibility context without guilt inference; relevant-device readiness/exclusion; source-linked Stock adjustments; Cash actual rollover; late-posting evidence; online recovery, responsive UI, documentation and manual acceptance.

Excluded: bank reconciliation, denomination capture, automatic Cash balancing, mutable historical snapshots, silent Device exclusion, new general ledger, reporting/dashboard implementation, offline provisions/PIN/outbox/cursors/Sync Receipts/transport, offline finalization, new automated test suites, production deployment and Phase 8 implementation.

## 3. Binding business and data contracts proposed for acceptance

### 3.1 Periods, snapshots and baseline

- Preserve OPENING as Owner-confirmed CASH baseline; it remains the special non-blind opening confirmation with expected = actual, zero activity/variance and start = cutoff. Standard Cash/Full requires an existing finalized Cash baseline.
- For later Cash, server-select the latest same-Shop finalized CASH/FULL predecessor and use its actual_amount. Never use its expected amount or add a balancing movement for its variance. The new Cash start is that predecessor's cutoff_at.
- Independently select the latest finalized STOCK/FULL predecessor. For the first Stock control, propose Shop created_at as the initial historical period origin, with no invented prior session. The expected quantity remains the authoritative Stock Position at the new cutoff, not the previous physical Stock count. This initial-period convention is an explicit planning decision, not a claimed v1.1 field requirement.
- FULL retains separate Cash/Stock predecessors and starts with one common cutoff. Reject foreign, unfinalized, wrong-scope or otherwise inconsistent predecessors.
- Cash includes only signed CASH effects in `(period_started_at, cutoff_at]` by server posted_at. Transfer and Credit are excluded. Reuse Phase 6 source validation and exact component query; caller baseline/time never come from the browser.
- Snapshot all relevant Shop Stock Positions, including inactive Products with positions, zero/negative expected quantities and unestablished-cost context. Freeze Product membership at start; no later Product creation/deactivation changes the captured set. Preserve quantity four-place and unit-cost six-place precision without inventing known costs.
- Actual Cash and physical Stock counts must be nonnegative exact decimal strings, with zero accepted explicitly and null meaning not yet counted. Variance = actual − expected; negative is shortage, positive surplus. Money has two decimal places. No float arithmetic.

### 3.2 Serialization, cutoff and writer coverage

Use a shared control policy with effect classification CASH/STOCK/both/neither, enforced inside owning service transactions. Obtain roots in the established Tenant → Shop → current membership order before session/source/Product/position locks; acquire multi-Product locks deterministically. Recheck current membership, grants and FULL access within that boundary. Inventory-owned transactions must join the compatible root/guard ordering; never nest a service that owns another commit.

Acquire all required domain locks and consistent current reads before the final generation lock. After relevant writers are excluded by roots/scope policy, establish fresh expected reads in the start transaction (avoid an earlier stale repeatable-read snapshot), select server UTC cutoff and pin current generation plus locking-read change watermark. Capture prior state and expected snapshots coherently; append Audit and publish aggregate changes under the established final-generation-lock contract. Never acquire new business locks after generation acquisition. Already-held rows may be completed under the established watermark contract. A native two-session rehearsal must prove the final implementation's read/lock ordering.

At most one active overlapping control per Shop: CASH conflicts with CASH/FULL; STOCK with STOCK/FULL; FULL with any active scope. Non-overlapping Cash and Stock controls may coexist; do not impose an undocumented single-control limit. This is service-enforced under Shop serialization; do not invent a schema guard that blocks legal disjoint scopes.

Guard every quantity/cost writer: Intake and replacement posting, manual Stock/Correction, movement and Intake reversals, Sale/replacement posting, Return and commercial compensation/reversal, and reconciliation adjustments. Guard Cash-affecting Sales/refunds, Debt receipts/inverses/replacements and Financial Movement source/replacement combinations. A pure-credit Sale is STOCK-affecting; a Transfer-only expense is not physical Cash. Draft preparation/master changes may continue only when they do not change frozen evidence or compromise blind access. Retain status recovery of already committed commands while blocking new effects.

### 3.3 Devices and real online readiness

Propose Owner-only enrollment/rename/revoke with required reason for revoke, while active Staff may inspect/acknowledge their authenticated current installation as allowed. Owner registration supplies no Tenant authority to the Device. Platform Admin has no implicit Tenant membership; Phase 9 support remains separate.

Implement registered_devices exactly to v1.1: stable public/device UUIDs, immutable Tenant/Shop binding, recognizable name, lifecycle, credential_hash and real online timestamps. Issue a high-entropy opaque installation credential and store only its secure verifier server-side; transmit via an appropriately secured host-scoped HttpOnly/SameSite cookie. No credential in localStorage, URL, Audit, DTO/log or committed configuration. Validate user membership and Device binding independently. Each origin/browser installation has its own identity; do not silently copy custom-domain cookies or rebind a device_uuid to another Shop. Offline trust/provision/sync timestamps remain unset until their actual owning workflow exists. Lost credentials require explicit re-enrollment/recovery, not treating a UUID as proof.

At start, pin **all ACTIVE registered Devices for that Shop**, including currently unavailable ones. No last-seen timeout silently removes a Device. PENDING/REVOKED devices cannot supply active readiness. Persist the frozen relevant set without adding an unapproved pending-status column/table: use one typed start Audit association per relevant Device plus a count/digest summary, in the same transaction as the session; existing bounded metadata limits remain intact. Resource/correlation/causality must make membership complete and verifiable without a single unbounded Audit array. Acknowledgement rows hold only actual ACKNOWLEDGED or reasoned EXCLUDED decisions, never fabricated placeholder acknowledgements.

Provide a scoped online readiness read/challenge bound to current user, authenticated Device, control, pinned generation and target watermark. Acknowledgement must prove receipt of that target through server-issued integrity-protected short-lived evidence; never trust a client-asserted future sequence or fake a Phase 10 pull cursor. Store the applicable exact watermark. Readiness means this Phase 7 online state was received, not that an unimplemented offline outbox has been drained.

Every pinned Device must acknowledge or be explicitly excluded by an active Primary Owner with exclusion permission and reason before count commitment/finalization. Revocation after start does not remove the pinned obligation: an unresolved Device requires explicit Owner exclusion; a previously validated ACK remains historical evidence and is not rewritten merely because that Device is later revoked. Revoked Devices cannot submit a new proof. Newly enrolled Devices do not rewrite an existing set or bypass active-scope guards. Acknowledgement/exclusion is insert-once for each relevant Device; equivalent same-decision retries recover it, and conflicting replacement decisions are denied. Any exceptional reconsideration requires abort/new control, not UPDATE of the original decision. On generation change, refuse completion and require a safely aborted/new control; final evidence never migrates generations. No Devices is an explicit recorded empty set, not fabricated readiness.

### 3.4 Blindness, count commitment and review

Blind count DTOs return only task/session identity, Product identification/unit, entered actual and progress. Exclude expected quantities/costs/Cash components/variance, including hidden fields, errors, DOM, Print, caches and recovery markers. Reuse of OpeningCashQuery for standard control is prohibited.

For the period until count commitment, apply a Shop/scope blind-read policy to current source endpoints that could reveal the same expected information (Product/Stock totals/history, Cash aggregates and applicable exports/diagnostics), including other sessions for the counting actor. Mask or temporarily deny revealing reads; navigation hides them as presentation only. This prevents independent grants from bypassing the blind DTO. Ordinary unrelated reads remain available. Previously remembered values cannot be technically erased; do not claim otherwise.

Save online draft counts to the approved count rows with actor/time and stale-write protection; do not invent an extra draft table or rely solely on browser memory. Server acknowledgement is the saved-progress indicator. Network failure retains visible input and requires explicit online recovery; durable offline draft counting remains Phase 10. Serialize saves/commit on the session, compare the approved stored revision/evidence, and refuse stale updates without overwriting another count.

Commit requires every frozen Stock line and the applicable Cash amount; it atomically locks all observations and computes exact variances. FULL has one all-or-nothing commit across both scopes. No partial count lock or implicit uncounted-zero. Review reveals frozen expected/actual/variance to users with review authority after commitment; committed counts remain read-only. Explain nonzero variances before finalization under the proposed review policy; explanations never erase differences. Finalize inserts normalized immutable variance evidence for nonzero differences, freezes explanations and Staff context, advances applicable control chains and releases scope protection. A failure in any required write/Audit/change rolls everything back. Aborted sessions retain drafts, reason and evidence and never advance baselines.

### 3.5 Staff context, notes, adjustments and late events

Capture authorized same-Tenant membership/user identity, on-duty windows where known, and counter/reviewer/finalizer roles. Do not assert exclusive responsibility from a variance. Freeze final context; later findings are appended as notes, not rewritten attribution.

After finalization allow scoped, authorized append-only EXPLANATION / INVESTIGATION_FINDING / REVIEW_NOTE entries of the approved size. Preserve authorship/time; correcting a note adds another note. Do not place a 2,000-character note in a 500-character Audit reason; Audit references its public identity/type and safe bounded summary.

A Stock variance adjustment is a separately reviewed, reasoned Inventory command against a finalized STOCK variance belonging to the latest finalized Stock-containing control. If a newer STOCK/FULL control has finalized, refuse adjustment of the older variance to prevent a prior shortage from being corrected again after a newer physical control; retain its notes/history and use the current control for any newly authorized alignment. Proposed policy: one effective variance-delta adjustment per source variance, serialized under the source/position locks with same-public-UUID recovery and derived completion from causal movements. Apply the frozen signed variance once to the current position, preserving all later movements, average-cost/cost-state rules and the original count. Do not overwrite current Stock with the old actual count. A zero variance creates no zero movement. Correction/reversal of a reconciliation-sourced movement is protected: no generic manual reversal or second adjustment that circumvents the one-effect rule; any future exception requires an explicit owning contract. No new adjustment-status balance/table or automatic Cash movement.

Record late accepted online evidence by posted-time/current-generation rules and append a typed Audit association where a source occurrence predates a finalized affected cutoff. Never change its source occurrence time or update old count/variance rows. Discovered omitted pre-cutoff information is a variance note; only a genuine new physical event is recorded as a new business movement. Phase 10 will reuse this contract for synchronized operations; Phase 7 adds no operation transport or invented receipt store.

## 4. Permission and API/UI contract

Proposed new grants: reconciliation.start, reconciliation.commit, reconciliation.abort, reconciliation.exclude_device, reconciliation.note; preserve existing view/count/review/finalize/adjust. Owner derives known-code grants; Staff must have explicit active grants. Exclusion additionally requires PRIMARY_OWNER. Device administration is Owner-only in this phase; authenticated own-installation status/ack is a narrowly scoped action, not a device-admin bypass. Read routes require view; every command also requires its own grant, relevant count/review authority where necessary and FULL access. Grant combinations and counter/reviewer separation are explicit; no implicit financial/inventory grant substitutes for reconciliation authority.

Use explicit scoped commands under /api/v1/shop: Device registration/status/revocation and reconciliation list/start/detail/readiness/acknowledge/exclude/count/commit/review/explain/finalize/abort/notes/adjustment. Exact route names and DTOs are delivered before activation in each package's integration contract. No generic table CRUD or public expected-Cash endpoint. Use permanent public UUIDs, closed payloads, bounded lists/search, stable errors, no-store private responses, CSRF and request IDs. Unknown replies recover through owning resource status using the same identity; never auto-repost or replace unresolved IDs. Existing READ_ONLY recovery reads remain possible; BLOCKED/CLOSED and foreign scope deny as specified.

Reuse Shop workspace/Menu registry, shared fields/cards/loading/status/confirmation/focus and plain-language shortage/surplus labels; no new shell/bottom-tab redesign. Every visible action works at that package's stage. All screens include applicable loading/empty/error/validation/denied/read-only/offline/pending/stale-conflict/saved states. Phone/tablet/desktop and keyboard parity are package requirements; color alone never conveys variance.

## 5. Database delivery and package allocation

Keep the applied session/financial-count migrations unchanged. New tables retain schema v1.1 columns, types, scoped FKs, uniqueness and CHECKs. Shield user FK widths remain compatible. Only documented necessary forward amendments are permitted; any unapproved schema deviation is raised before implementation.

Parent-first sequence:

1. WP1: registered_devices; deferred audit_events.actor_device_id FK after Device parent.
2. WP2: reconciliation_stock_counts as a **schema prerequisite only** (its parent must exist before the full v1.1 variance table); reconciliation_variances; reconciliation_variance_notes; reconciliation_staff_contexts; reconciliation_device_acknowledgements. No STOCK/FULL workflow is exposed before WP3. This avoids delivering a partial Cash-only variant of the approved variance schema.
3. WP3: deferred scoped stock_movements.reconciliation_variance_id FK after variance parent; other genuinely necessary approved late FKs only.

Every migration has an equivalent MySQL 8.4 raw SQL twin, register entry, preflight and evidence-preserving rollback guidance. Apply migrations OR SQL in a target database, never both. MySQL DDL implicit commits require native empty/upgrade/partial-failure rehearsal. No destructive down against posted evidence; no UPDATE/DELETE grant on finalized Audit/variance/note evidence to simplify recovery.

## 6. Deliverable work packages

### WP1 — online Device foundation and shared control protection

**Entry:** plan accepted and WP1 authorized; confirmed complete Phase 6 baseline and safe native development setup.

Deliver usable Owner enrollment/name/status/revocation and authenticated own-Device status, schema/late Audit FK, validated Device actor context, Audit vocabulary, guarded APIs and integrated responsive UI. Deliver the shared scope/writer/read-policy foundation and compatible root locking across existing Inventory/commercial/Cash paths; preserve current Opening and source workflows. New standard session UI remains hidden until its lifecycle exists. Publish the concrete cutoff/readiness/permission/recovery contracts and source-effect matrix.

**Artifacts:** WP1 delivery report, integration/API/device credential contract, migration/SQL/register updates, permissions/capabilities/UI docs, operational enrollment/recovery guidance, case results P7-01–P7-12 and exact built artifact inventory.

**Exit:** native schema/FK/enrollment/revocation/isolation/security cases plus existing Opening/Stock/Sales/Debt/financial regression pass; global lock/read policy reviewed; no credential leak, no unintended Staff grants or offline claims. Owner WP1 acceptance before WP2.

### WP2 — complete standard Cash reconciliation and readiness

**Entry:** WP1 accepted. Deliver shared evidence schema in full dependency order, concrete fixed-set readiness, server cutoff/generation/watermark and full CASH start/count/commit/review/explain/finalize/abort. Deliver actual-baseline rollover, immutable Cash variance/Staff context, history/detail, append-only notes and same-ID recovery. Freeze snapshots and enforce blind read policy. No Stock/Full button or partial lifecycle is exposed yet.

**Artifacts:** WP2 report, lifecycle/lock/snapshot/readiness/DTO contracts, evidence migration twins/register, complete Cash UI and operations/recovery guide, P7-13–P7-24 results and exact inventory.

**Exit:** hand-calculated Cash/reversal/boundary/baseline targets, no-blind-leak, real Device readiness/exclusion, atomic commitment/finalization and native concurrency/rollback evidence pass; Transfer-only events retain correct allowance. Owner WP2 acceptance before WP3.

### WP3 — complete Stock/Full controls and protected Stock adjustment

**Entry:** WP2 accepted. Deliver frozen Stock dataset/expected cost, blind paginated search/count/progress/saved state, exact quantities, count commitment/review/finalize/abort/history/Staff context/notes for STOCK and FULL. FULL preserves independent predecessors and one common cutoff/atomic commit. Activate all writer/read restrictions. Add deferred variance FK and Inventory-owned reviewed adjustment with current-position preservation, cost provenance, causal evidence and safe recovery. Complete late-online-operation association for all affected source modules.

**Artifacts:** WP3 report, Stock/Full/adjustment/late-event integration contract, FK SQL/migration notes, full integrated UI and operation guide, P7-25–P7-36 results and exact inventory.

**Exit:** all scopes, disjoint/overlapping sessions, all Stock writer compositions, native cutoff races, exact quantities/costs, notes/Staff evidence and one-effect adjustment with subsequent trades pass. No Cash balancing and no mutable final evidence. Owner WP3 acceptance before WP4.

### WP4 — final integration, native acceptance and release handover

**Entry:** WP3 accepted. Close cross-slice defects and complete integrated day journeys, native failure/concurrency/generation/late-event/security checks, every responsive/recovery state, bounded-query review and setup/upgrade/rollback rehearsal. This package validates and finishes integration; it does not defer basic features promised by WP1–WP3.

**Artifacts:** final requirement/source/manual matrix; 48-case integrated results with original package registers retained; defect/gap disposition; full scope/module/API/device/sync/migration/permission/UI documentation; exact release inventory and reviewed commit; release decision template; deployment-compatible operation/backup/restore/rollback handover and closure checklist.

**Exit:** every Phase 7 roadmap criterion has observed evidence and accepted disposition; no unresolved critical/high release blocker; whole-phase Owner acceptance is explicitly recorded against exact commit/inventory. Merge and deployment are separate operational decisions. Phase 8 requires its own plan/authorization.

## 7. Deterministic acceptance fixtures

All fixtures use isolated backed-up synthetic Shops and approved business services/UI, never SQL edits to posted evidence. Record public UUIDs, actors/grants, Device identities, UTC/Lagos dates and actual versions.

- F1 Cash: baseline 20,000 + Sale receipts 12,000 − refunds 2,000 + Debt receipts 3,000 + introductions 5,000 − expenses 1,500 − withdrawals 2,000 + signed corrections 500 = expected 35,000. Actual 34,500 → shortage −500. Next baseline 34,500; later genuine +1,000 → expected 35,500. No balancing movement.
- F2 corrections: expense −1,000 + exact inverse +1,000 + replacement −800 = net −800. Cross-channel originals/inverses/replacements count only CASH effects once.
- F3 Stock: expected 51.0000, actual 48.0000 → −3.0000. After finalization, Sale −2 changes position to 49.0000; later linked −3 adjustment yields 46.0000. The original expected/actual/variance remains 51/48/−3. Retry adds no second effect.
- F4 independent controls: Cash predecessor cutoff t1, Stock predecessor cutoff t2, FULL cutoff t3; respective periods (t1,t3] and (t2,t3], no forced common predecessor.
- F5 blindness/readiness: two active relevant Devices, one unavailable. Commit/finalize refused until acknowledged or Owner-excluded with reason; start set survives revoke/new enrollment; expected data cannot be fetched through blind or source routes.
- F6 cutoff races: source at start boundary excluded and at cutoff included; backdated occurred_at posted after cutoff affects the next applicable period and never rewrites finalized evidence. Race source posting vs start and generation retirement in native sessions.
- F7 abort/atomicity: save counts, inject required Audit/change/commit failure, lose reply, recover same identity; no partial FULL commit, duplicate finalization/adjustment or baseline advance after abort.
- F8 quantities/cost: zero actual, fractional quantities, zero/negative expected Stock, inactive Products and UNESTABLISHED/RETAINED cost states; six-place average cost preserved and no invented acquisition/COGS.

Detailed P7-01–P7-48 checklist: [manual acceptance plan](Phase_7_Manual_Acceptance_Plan.md). Requirements/ownership: [matrix](Phase_7_Requirements_Matrix.csv).

## 8. Universal package gate and release discipline

Each package must be runnable from its reviewed predecessor and contain its schema, business rules, current authorization, API, real integrated UI, Audit/publication, recovery, documentation and native manual evidence. Run locked frontend build, source/public parity, exact manifest check, appropriate syntax/static checks and paired migration/SQL review; none substitutes for native MySQL/HTTP/device acceptance. Do not introduce a testing framework or automated suite.

Result rows include exact commit/inventory, versions, Shop/actor/grants/Device/fixture UUIDs, expected/observed values, evidence, reviewer and Africa/Lagos time. Start as Not run; Blocked and Fail stay explicit. Package acceptance is separate from individual outcomes. Preserve prior phase evidence and inventories. Do not regenerate checksums to hide changed files or CRLF issues.

Create each implementation branch from the actual accepted predecessor; use cumulative integration only with explicit ancestry/tree checks. Avoid repeated squash of stacked prerequisites and avoid merging duplicate snapshots. Build/deploy source and generated assets together. Before authorized production work, verify real backup/restore and compatible previous release; no database rollback that destroys business evidence. Planning adds no migration, dependency, secret or production change.

## 9. Decisions requested with plan acceptance

Approve or amend the four-package sequence and these explicit policy choices: (1) all ACTIVE Devices remain relevant without timeout exclusion, (2) immutable per-Device start Audit membership with real ack rows, (3) secured installation credential independent of user identity, (4) first Stock period origin is Shop created_at, (5) blind-read enforcement across revealing source endpoints, (6) independent CASH/STOCK coexistence with overlapping-scope exclusion, (7) nonzero-variance explanations before finalization, (8) one effective frozen-variance Stock adjustment preserving later activity and no generic reversal, (9) latest-Stock-control eligibility for adjustment to prevent correction across superseded controls, and (10) new independent command grants without expanding existing Staff authority.

Acceptance of this planning package does not imply native case Pass, production authorization or automatic authorization of all later packages. Suggested progression decision: “Phase 7 plan accepted. Proceed with WP1 only.” Each later package requires its predecessor's explicit acceptance.
