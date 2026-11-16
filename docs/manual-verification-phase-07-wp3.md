# Phase 7 WP3 — Owner manual verification (P7-25–P7-36)

**16 November 2026 (Africa/Lagos). WP2 Owner-accepted. WP3 implemented for native review; not accepted, merged or deployed.** Use an isolated backed-up native PHP/MySQL8.4 environment and synthetic Shops. Never edit/delete protected business/Audit rows or fault production to produce a result.

## Setup and candidate integrity

Run from the DawaTrack checkout:

    git fetch origin
    git switch codex/phase-7-wp3-stock-full-20261116
    git pull --ff-only
    composer install
    npm ci
    npm run build
    npm run ui:release:check -- --output=docs/Phase-7/Phase_7_WP3_Plain_English_Artifact_Manifest.json
    php spark migrate:status

Record exact commit, inventory hash, PHP/Composer/MySQL/browser versions, Shop hostname/timezone and actors/grants. Do not regenerate inventory to hide edits. Only exact CRLF-only changes can use the established --fix-line-endings recovery followed by a plain check.

WP1 Device and WP2 evidence migrations must already exist. Inspect the single new `2026-11-16-100000_AddPhaseSevenStockVarianceForeignKey` migration and its SQL twin. On the backed-up isolated copy:

    php spark migrate --namespace App
    php spark migrate:status

For an empty installation retain Settings → Shield → App ordering. The new migration preflights existing links, adds the scoped variance candidate key, then deferred Stock Movement FK. Use migration OR raw SQL on a database, never both; SQL parity rehearsal needs a separate copy. MySQL DDL may partially commit, so inspect before retry. Down is refused after linked movement evidence; use compatible code/assets rollback. Preserve private encryption.key (at least32 bytes)/OpenSSL and real HTTPS host-scoped Device cookies. Never capture credentials/receipts in evidence.

Open the configured Shop address and normal Menu → Stock control / Full control; not a reference gallery. Existing Cash control remains available.

For the shared control-page error follow-up and the deployed evidence still needed, see [investigation](Phase-7/Phase_7_WP3_Control_Error_Fix.md). The original WP3 and first fix inventories are historical; the command above checks the current plain-English candidate.

The menu now says **Cash check**, **Stock check** and **Cash and stock check**. Save each count, then use **Confirm this count** (or **Confirm all item counts** / **Confirm cash and item counts**). **Compare the numbers** opens the next step. **Finish check** keeps the final result; **Cancel check** stops the attempt but keeps its saved entries. **Check what was saved** loads the saved result without sending it again. These labels replace the old UI terms in the steps below; the API and case numbers stay the same. See [what changed](Phase-7/Phase_7_WP3_Plain_English.md).

## Test fixtures

Use Shop A and foreign Shop B; actors: Owner, Counter (view/count/commit), Reviewer/finalizer (view/review/finalize), note reviewer (review/note), adjustment reviewer (review/adjust), and transaction Staff with the necessary owning grants. Verify FULL versus read-only/expired access separately. Enroll actual browsers A/B as ACTIVE Devices; acknowledge from each actual installation or expressly exclude as Owner. Do not seed sessions/acks/Stock positions by SQL.

F3 via approved Intake/Sales/manual workflows: Product P expected **51.0000**, average cost **100.000000**, actual physical **48.0000**, variance **−3.0000**. After this control finalizes, post Sale−2 so current expected49.0000, then apply the reviewed variance−3: expected result **46.0000**, never48.0000. Frozen51/48/−3 remain unchanged. Keep all UUIDs and server times. Add inactive, zero expected, negative expected, fractional, VALID/RETAINED/UNESTABLISHED Products via legitimate workflows; physical counts remain nonnegative. Use fresh Shops for independent cases so fixtures do not contaminate each other.

For Full, finalize Starting cash and a later standalone Cash control; independently finalize a Stock control with a different cutoff. Keep both predecessor references. Prepare a second browser/request runner for controlled races, using authenticated same-origin/session/CSRF rules and original UUIDs. Capture sanitized responses without credentials.

## P7-25 — Stock dataset and first period

1. In a fresh Shop with prepared positions, start Stock control; record server cutoff, generation and watermark. Reject a request that supplies a client cutoff, scope or expected values.
2. Inspect saved rows/read-only evidence with authorized native diagnostics: all current positions must be frozen, including inactive, zero and negative quantities. Record Product names/units, expected cost/state/version.
3. Create a new Product or rename/deactivate an existing Product through allowed master workflows after start. Refresh count search/history, then abort; start/finalize the next legitimate control.

**Expected:** First Stock period begins Shop.created_at; later period begins the latest finalized Stock/Full cutoff. Aborted attempts do not advance it. Later master changes do not add/remove/rename frozen lines or change cost snapshots.

## P7-26 — Exact quantities and costs

1. Count fractional quantities, explicit0, positive expected and negative expected fixtures. Reload saved observations and check zero stays counted rather than missing.
2. Try negative actual, excess precision, exponent notation, malformed value and overflow. Use two sessions to save conflicting quantities with the original row revision.
3. Commit legitimately and inspect exact expected/actual/variance and six-decimal cost with VALID/RETAINED/UNESTABLISHED provenance.

**Expected:** Exact actual−expected arithmetic and original cost/state match independent calculations; rejected/stale writes do not overwrite saved observations. No float rounding or inferred zero.

## P7-27 — Stock and Full complete lifecycle

1. Run Stock start/save/Device readiness/commit/review/explain/finalize; repeat with Full using independent prior Cash and Stock controls.
2. In Full leave one Stock line uncounted, then omit Cash, then use an old whole-set commit revision. Each commit must refuse without partially locking observations.
3. Complete both scopes and finalize. Inspect independent predecessors/period starts, common cutoff, all committed timestamps, immutable nonzero variances and next control baselines. Separately abort COUNTING/committed/REVIEW attempts with reasons and try illegal transitions.

**Expected:** Full commits/finalizes both scopes atomically. No expected values before commitment, no edit afterward, no automatic Stock/Cash movement. Abort retains drafts and advances neither chain; finalization advances applicable chains only.

## P7-28 — Every Stock writer composition

1. During active Stock and then Full, attempt Intake/replacement post, manual movement/correction, movement/Intake reversal, Sale/replacement post, Return and compensation/reversal.
2. Include full-credit and Transfer-only Sales and mixed Cash compositions. Try a new reconciliation adjustment from another source. Compare source/position/Audit/publication counts before/after refusals.
3. Resolve the control and repeat legitimate operations. Check effect-free drafts/threshold/master preparation where the actor is allowed to read them.

**Expected:** Every quantity/cost-affecting operation is blocked before effect during overlapping control, including credit/Transfer Sales. No partial event/Audit/publication or bypass; allowed unrelated preparation remains effect-free.

## P7-29 — Overlapping start and writer races

1. Use two authenticated native sessions to race STOCK/STOCK, CASH/CASH, FULL/STOCK and FULL/CASH starts from an initially empty active-session range; preserve both UUIDs.
2. Race start against Sale/Intake/manual/reversal and against adjustment posting, using controlled native lock barriers in an isolated copy.
3. Inspect winning cutoff snapshots and losing responses plus all source/Audit/publication rows; repeat reversed scheduling.

**Expected:** At most one overlapping control starts; the writer is either wholly accepted before cutoff and frozen or refused after start. No slipped effect, partial snapshot, deadlock-dependent corruption or lock-order inversion.

## P7-30 — Disjoint and unrelated activity

1. Start CASH and STOCK independently and confirm coexistence, then try FULL. Use different responsible/transaction users to distinguish read blindness from writer protection.
2. Try non-Stock Transfer expense/debt collection and effect-free drafts; then attempt Cash transaction while CASH active and Stock movement while STOCK active.
3. Finalize/abort each scope independently and inspect released protection without changing the other control.

**Expected:** CASH/STOCK coexist; FULL overlaps both. Each effect is guarded by its actual scopes; unrelated Transfer operations remain permitted with grants. Release is per control, not a global unlock.

## P7-31 — Blind search, progress and recovery

1. As Counter/Owner/starter, search and paginate frozen Products, enter quantities on multiple pages and check saved progress. Keep one unsaved quantity while searching and save another Product.
2. Inspect raw detail/count/readiness/history/Staff/notes/source APIs, DOM and storage across another session for the same actor. Try prior controls and source history/Print routes with independent grants.
3. Lose a count reply, use explicit saved-status GET and retain visible input; verify no reconnect POST. Commit all lines and inspect reviewer versus Counter DTOs. For Full, retain unsaved Cash while saving a Stock row.

**Expected:** Blind DTOs expose identity/unit, actual/revision/progress only. No expected/cost/variance/explanation via historical/source reads or storage. No missing auto-zero. Local drafts and stale revisions survive refresh; committed observations are read-only; Full Cash draft is preserved across Stock saves.

## P7-32 — Variance and scoped FK/history integrity

1. Inspect native SHOW CREATE TABLE for the scoped Stock Movement variance FK and referenced candidate key; compare migration/SQL in separate rehearsal copies.
2. Finalize shortage/surplus/balanced lines; inspect only nonzero normalized rows with exactly one matching Cash or Stock count source. Try foreign Shop/source UUID and nonexistent Product through APIs.
3. Inspect bounded history/count/notes endpoints and absence of update/delete routes. On a disposable schema constraint rehearsal only, demonstrate rejected orphan/cross-Shop FK and malformed variance source rows; do not change real evidence.

**Expected:** Scope/source checks, FK RESTRICT and one-source CHECKs hold natively. Balanced lines create no zero variance/movement. Immutable final evidence and bounded response shapes remain intact.

## P7-33 — Staff context and later notes

1. Select on-duty actors with known times and leave unknown times blank. Save observations as one Counter and commit as another authorized actor; review/finalize with distinct identities.
2. Inspect historical ON_DUTY/COUNTER/REVIEWER/FINALIZER context after account rename/deactivation. Confirm counter and committer are not conflated.
3. Append a typed2,000-character later finding to a specific Stock variance and, in Full, its Cash variance. Retry same note UUID, change its body, try foreign variance/update/delete and ordinary Staff.

**Expected:** Immutable responsibility identities/windows remain contextual, not proof of guilt. Authorized notes append once, retain full permitted text and do not exceed bounded Audit metadata; original count/variance/explanation stays unchanged. Changed/foreign/unauthorized writes refuse.

## P7-34 — Stock adjustment after later Sale

1. Finalize F3 at expected51.0000/actual48.0000/variance−3.0000 with explanation. Verify no automatic movement; then post Sale−2 to current49.0000.
2. Choose Review Stock adjustment with reason; inspect delta−3/current49/after46, cost state, original movement UUID and source UUID. Post a legitimate intervening Stock operation after preview and verify stale preview refuses; refresh the preview.
3. In a fresh exact F3 run confirm once at49→46. Inspect immutable movement, variance/source FK, current version/pointer, reason/Audit/publication and frozen count/variance. Repeat cost-state edge fixtures separately.

**Expected:** Adjustment applies frozen delta to current position once:49−3=46. Later Sale remains included; no overwrite to old actual48. Current average cost is retained and non-acquisition cost-state rules hold. Old evidence and Cash remain unchanged.

## P7-35 — Adjustment concurrency, recovery and protection

1. Race two native requests for the same variance with same and different movement UUIDs; lose the accepted reply and use Check saved status. Inspect one effective linked movement/position update.
2. Retry exact original movement/variance/reason, then changed details; attempt a second new UUID. Finalize a newer Stock/Full control and try an older unadjusted variance, then recover an already accepted exact old command.
3. Try missing grants/read-only access, foreign/unfinalized/Cash/zero source, active Stock control, generic manual reversal/correction and direct changed identity. Reload the browser during an unknown reply and explicitly recover with original IDs/re-entered reason.

**Expected:** One effective delta; exact accepted UUID recovers even after a newer control. Different second effect, changed-body conflict, superseded unadjusted source and protected generic reversal refuse. Unknown retains IDs/action only and never automatic POST/new identity;404 absence is inconclusive.

## P7-36 — Late source evidence and no Cash adjustment

1. Finalize controls, then accept legitimate backdated Stock Intake/manual/Sale/Return/reversal plus Cash Sale/refund/Debt/Financial operations whose occurred_at is at/before latest finalized affected cutoff but posted_at is later.
2. Inspect each committed source occurrence/posting, newest affected finalized control association and its refresh publication. Repeat source status/publication path without creating another effect. Test exact-cutoff occurrence, later occurrence and pure Transfer/credit-only Cash exclusion.
3. Inspect authorized paginated late-operation list after commitment; during relevant blind counting try that list and Audit/source bypass. Compare earlier frozen snapshots/baselines and later posted_at composition.

**Expected:** One typed SYSTEM link per source-kind/UUID/scope to newest finalized affected boundary; Full may receive independent Cash/Stock links. Source effects stay in posting period. Original timestamps/old snapshots/variance/baselines never rewrite; no balancing Cash movement is created. Pure Transfer is excluded from Cash; credit Sale still has Stock evidence.

## Controlled rollback and lost-response checks

Use only an isolated synthetic database and a disposable uncommitted fault-instrumentation checkout. Restore original files before inventory/acceptance. Never manufacture a Pass by modifying posted data.

For Full start, commit and finalization, inject a throw after the first scope write, after Stock row writes, after Audit and before publication/commit. For adjustment, inject after movement insertion, after position update, after Audit and during publication. For late association, fail after source write/association but before generation publication. Each failure must roll back all rows, projection, Audit and sync changes together; prior evidence stays byte-for-byte. Native DDL is separate and may implicitly commit.

Distinguish request not sent from committed-but-reply-lost using a controlled response proxy/barrier, not just an offline icon. Keep the original command UUID/body in a private ephemeral operator record, never in committed docs. A blocked unsent command has no server effect; an accepted lost reply has exactly one effect, recovered by explicit scoped GET or exact original-ID repeat. An absence/404 while a request can still finish proves nothing. Unknown adjustment must not mint another movement UUID. Revoke grants or change generation between load/save/review to verify current authority/final lock checks and safe abort behavior; never alter a production generation for a test.

## Record results and package boundary

Use [WP3 results](Phase-7/Phase_7_WP3_Manual_Results.csv) for P7-25–P7-36. Record observed values, exact commit/environment, sanitized evidence, reviewer and timestamp; use Pass only for a case actually executed natively. Engineering static/mocked observations are separate. Existing integrated48 identities and prior package registers remain intact. PHP/MySQL/real HTTP/Device/concurrency/rollback/accessibility/restore cases were not executed in the implementation workspace.

Return the WP3 package decision with any defects. WP4 remains a separate gate; this handover does not merge, migrate, deploy or close Phase7.
