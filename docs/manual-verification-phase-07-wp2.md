# Phase 7 WP2 — Owner manual verification (P7-13–P7-24)

**16 November 2026 (Africa/Lagos). WP1 is Owner-accepted. WP2 is implemented for your manual verification; not yet accepted, merged or deployed.** Use the real application in an isolated backed-up XAMPP/native development database with synthetic Shops/users. Never inject faults, edit/delete business or Audit rows, or change production grants just to run a case.

## Setup and candidate integrity

Run these commands from the DawaTrack checkout:

    git fetch origin
    git switch codex/phase-7-wp2-cash-reconciliation-20261115
    git pull --ff-only
    composer install
    npm ci
    npm run build
    npm run ui:release:check -- --output=docs/Phase-7/Phase_7_WP2_Artifact_Manifest.json
    php spark migrate:status

Record the exact candidate commit and actual PHP/Composer/MySQL/browser versions, Shop hostname/timezone, actor/grants and artifact hash. The inventory was generated from the built candidate and committed; the check must pass. Do not regenerate it to hide real edits. If only CRLF conversion is reported, follow the established --fix-line-endings procedure, then repeat the plain check.

WP1's two Device migrations must already be applied. In a backed-up isolated copy, inspect the six new WP2 migrations (100200–100700), then apply the App namespace using the established migration procedure:

    php spark migrate --namespace App
    php spark migrate:status

An empty installation first needs the documented Settings → Shield → App package ordering. WP2 adds supporting scoped candidate keys followed by Stock-count schema prerequisite, variance, note, Staff-context and Device-acknowledgement tables. Stock/Full workflows are not activated. Use matching SQL twins only on a separate rehearsal database, never in addition to migrations on the same database. MySQL DDL may partially commit: inspect failures before any retry; never forge migration history or delete protected evidence.

Keep the existing private deployment encryption.key configured with at least32 bytes and OpenSSL available. The readiness receipt uses purpose-separated encryption; no default key is invented or committed. Never paste the key, Device cookie or readiness receipt into screenshots/support records. Verify the real HTTPS cookie behavior and same-origin session/CSRF policy.

Open the normal configured Shop address, sign in and use **Menu → Cash control**. Use **Shop devices** to enroll each physical browser/origin through the Owner. This is the real application, not a reference gallery.

## Test fixtures

Prepare two synthetic Shops with Stock and finalized Starting cash through approved workflows. Register browser A and browser B as separate ACTIVE Devices in Shop A; do not fake acknowledgement rows or create a standard session by SQL.

Prepare these actors:

- Owner with FULL access.
- Counter: reconciliation.view + reconciliation.count + reconciliation.commit; no review/finalize/Device-exclusion authority.
- Reviewer/finalizer: reconciliation.view + reconciliation.review + reconciliation.finalize; add abort/note separately for those cases.
- Ordinary transaction Staff with appropriate financial/Sales/Debt grants but no count responsibility, to verify unrelated Transfer-only activity while a counter's revealing source screens are restricted.

For F1, create all events **before** starting standard Cash control:

1. Before Starting cash, record a Transfer Owner Introduction of ₦1,000.00.
2. Finalize Starting cash at ₦20,000.00.
3. Correct the Transfer introduction into a CASH replacement of +₦500.00. The inverse remains Transfer and is excluded from physical Cash.
4. Record a Cash Sale of six units at ₦2,000.00 each (₦12,000.00). Return one unit with a Cash refund of ₦2,000.00.
5. Create an eligible credit Sale and collect ₦3,000.00 in Cash against its debt. Credit itself adds no physical Cash.
6. Record Owner Cash Introduction ₦5,000.00, Cash expense ₦1,500.00 and Owner Cash withdrawal ₦2,000.00.

Separate hand calculation:

    20,000 + 12,000 - 2,000 + 3,000 + 5,000 - 1,500 - 2,000 + 500 = 35,000

Use physical actual ₦34,500.00: variance =34,500−35,000 = **−₦500.00 shortage**. Keep fixture/public UUIDs and server timestamps. Use fresh Shops for destructive error-path comparisons, zero-count/baseline tests or F2 correction tests so fixtures do not contaminate each other. Record stock Intake through approved UI; no direct balance/Audit edits.

## P7-13 — standard Cash start and authoritative cutoff

1. In Shop A, choose Cash control. Optionally find/select on-duty members and enter only known duty times/context; unknown times stay empty.
2. Start once and confirm the stated effect: physical-Cash operations pause until finalization/abort.
3. Verify one STANDARD/CASH COUNTING control with a permanent public UUID, the latest finalized Cash predecessor and its **actual** baseline. Its period begins at that predecessor's cutoff.
4. Inspect authoritative saved generation, fixed server cutoff and watermark; the browser cannot select or post its own scope, baseline, expected amount, generation, actor or cutoff.
5. Retry the same start reference/body. One control, snapshot and pinned-set evidence remain. Change the on-duty context with the same reference: conflict, no second control.
6. Try starting without finalized Starting cash, against foreign context, or while another Cash control is active. It must reject safely without partial evidence.

**Expected:** exact server-selected predecessor/snapshot/cutoff, current authority and one start effect. Stock/Full start controls are absent.

## P7-14 — F1 composition, physical count and shortage

1. Start after all F1 events. As Counter, inspect the page and network: expected/component/variance fields must be absent, not merely hidden visually.
2. Enter "34500.00" and choose Save physical count. Reload and verify the server-saved observation persists without revealing expected values.
3. Resolve the real pinned Device readiness in P7-19/P7-20, then commit the count once. The observation becomes read-only.
4. Sign in as authorized Reviewer, open the committed record and review the frozen components. Compare each component with the separate hand calculation and original source records.
5. Verify expected exactly "35000.00", actual "34500.00", variance "-500.00", labelled **Shortage** without relying on color.

**Expected:** decimal strings and exact signed formula, one saved/committed observation, expected values exposed only after commitment to review-authorized readers. A view-only/counter-only response still has no snapshot.

## P7-15 — F2 correction signs and Transfer/Credit exclusion

1. In a separate ready Shop record Cash expense ₦1,000.00, then correct it into Cash replacement −₦800.00 through the accepted money workflow.
2. Before standard start, confirm source −1,000 + exact inverse +1,000 + replacement −800 = net −800. Start and eventually review the frozen Cash contribution.
3. Repeat cross-channel correction cases: Cash→Transfer and Transfer→Cash. Include legitimate Debt inverse/replacement and Sale refund/compensation through their owning workflows.
4. Add Transfer-only financial events and full-credit Sales; neither is physical Cash. Use a separate appropriately authorized non-counting Staff session to verify permitted Transfer-only events during an active Cash control. Counting actors' revealing source screens remain deliberately blind until commitment.
5. Try a new Cash-affecting event during COUNTING, committed and review states. Shared control protection must reject it without partial domain/Audit/change evidence.

**Expected:** only CASH effects counted once with correct signs; unrelated authorized Transfer behavior remains available subject to role/blind-read policy; no silent balancing movement.

## P7-16 — blind API, predecessor, source and Print checks

- Inspect detail/history/readiness/member/context JSON, DOM and browser Print before commitment. No expected amount, opening baseline, components, variance or expected/cost payload is shipped in hidden fields.
- Try another session for the same Counter/Owner, direct source reads/previews, Audit and the previous finalized Cash control's detail/variance-note routes. A counting actor cannot use those paths to reveal the predecessor baseline/components while the new blind count is active.
- Counter-only and view-only users cannot gain review values by direct URL or client role/grant edits. After commitment, a user still needs current reconciliation.review.
- Use Check a saved transaction reference with an original Sale/Debt payment/Money movement UUID and its separate source-view grants. It shows existence/state only, never amount/body or expected Cash. Missing at that read is not conclusive non-commit. Full source identity/body conflicts still require later authorized detail inspection.
- Abort an uncommitted attempt: its captured expected values must not be exposed as though it had been committed. Remembered data cannot be erased, but new server responses remain controlled.

**Expected:** server-side blind projections and source policy, no cache/Print/session bypass or raw Audit/internal identity/credential leakage.

## P7-17 — zero, stale saves and immutable commitment

1. In a fresh fixture save "0.00" explicitly; distinguish it from null/not-counted. Negative/numeric JSON/comma/scientific/overprecision amounts reject with retained input.
2. Open the same count in two native sessions. Save a new value in A, then submit B with the old expected_updated_at. Different stale input must conflict instead of overwriting A. Refresh and deliberately review the saved observation before another save.
3. Save a physical observation in one user session and commit through a separately authorized user. Historical COUNTER identity must be the observing user; counts_committed_by records the committer separately.
4. Commit only after a saved amount and complete real readiness. Unsaved input must not be implicitly committed. Double-activate/retry: one transition/evidence set.
5. Try changing the amount after commit/review/finalization and direct API transition skips. It must reject without changing observations.

**Expected:** zero is valid, complete commitment is atomic, stale writes are visible conflicts, observer identity is retained and committed counts cannot be edited.

## P7-18 — actual baseline rollover and retained shortage

1. Complete the F1 shortage review, save a meaningful explanation and finalize with actual ₦34,500.00.
2. Inspect that original expected/actual/variance/explanation remains frozen and no Financial Movement was created for −₦500.00.
3. Record one genuine new Cash introduction of ₦1,000.00 after finalization, then start the next Cash control.
4. After a valid blind count/commit, authorized review must show baseline "34500.00" and expected "35500.00", not old expected35,000 plus movement, and not a double-adjusted value.
5. Repeat balanced, surplus and actual-zero cases in separate fixtures. Aborted controls never become predecessors.

**Expected:** latest finalized physical actual rolls forward once; old shortage/surplus remains historical; no mutable Cash balance or automatic variance movement.

## P7-19 — pinned Devices and real online acknowledgement

1. Start with browser A and B ACTIVE. Check the pinned names/count and typed per-Device start evidence/count-digest; there are no placeholder ACK rows.
2. In A choose Acknowledge this Device online. Inspect that the server-issued short-lived receipt binds this active cookie-proven Device, current member, control, generation and exact target watermark. Receipt stays in request memory, not browser storage/URLs/Audit.
3. Keep B unavailable. Commit/finalize must remain blocked; a last-seen timeout cannot silently remove it.
4. On B sign into the Shop with reconciliation.view and a valid installation cookie, open the same control and acknowledge. Exactly one immutable ACK per pinned Device; equivalent retry adds none.
5. Register a new Device after start: the original set/count/digest never changes, and the new Device cannot claim membership in that old set. It remains subject to shared scope restrictions.
6. In an explicitly reviewed fixture with no registered Devices, the empty set is recorded as empty; no fabricated acknowledgement is inserted.

**Expected:** actual online receipt/decisions for the immutable relevant set; no fake sync cursor, outbox drain, offline trust or silently omitted Device.

## P7-20 — Owner exclusion, revocation and invalid proof

1. Leave B unresolved. Try exclusion as Staff (even with exclude grant), with missing/blank reason, foreign Device, future target, another user's/Device's receipt, changed target, expired receipt and old generation. All invalid actions reject without evidence.
2. As current Owner, expressly exclude B with a clear reason and confirmation. Inspect one EXCLUDED decision, actor/time/reason and Audit, with null acknowledged sequence.
3. Retry identical exclusion: recover one decision. Change reason or try replacing ACK with EXCLUDED/vice versa: conflict; old decision is immutable. Abort/new control is the explicit reconsideration path.
4. Revoke an unresolved pinned Device after start. Its obligation remains until explicit Owner exclusion; old proof cannot submit a new ACK. If it acknowledged validly before revocation, that historical ACK remains recorded.
5. Change server generation in a controlled recovery rehearsal. Progress/ACK/finalize must fail closed; authorized reasoned abort remains possible and does not advance baseline. Restore normal generation procedure and start a new control.

**Expected:** exclusions require current Primary Owner authority/reason; Device and user proofs are separate; generation changes never rewrite the old frozen target.

## P7-21 — review, explanation, Staff context and finalization

1. As reviewer, open committed F1. No count editing is offered. Open review, save a nonzero-variance explanation and inspect the saved revision/author evidence.
2. Try finalization without explanation or readiness, with insufficient finalize/review grants, and after grant/access changes. It must reject. A saved explanation never changes the numerical variance.
3. Finalize once. Inspect immutable normalized Cash variance, exactly-one source relation, finalized actor/time, count/predecessor/component snapshot and Staff ON_DUTY/COUNTER/REVIEWER/FINALIZER contexts with only genuinely known duty windows.
4. Retry finalization and try every generic edit/delete/amount/explanation transition afterward. No additional variance or success evidence and no mutation of finalized history.
5. Compare phone/tablet/desktop/keyboard presentation, plain shortage/surplus labels and safe identity links. Context records responsibility, never an automatic guilt finding.

**Expected:** one atomic immutable final evidence set, accurate historical roles and exact Cash actual rollover; independently enforced action grants.

## P7-22 — abort, errors and release of control

1. Start separate attempts and abort from COUNTING, COUNTS_COMMITTED and REVIEW using required reason.
2. Confirm saved observations/readiness/context remain as aborted evidence, no finalized variance/baseline advancement occurs, and eligible Cash operations can resume.
3. Repeat identical abort reason and try another reason, finalized abort, transition skips and foreign UUID. Reject invalid paths; do not overwrite the original reason.
4. Try READ_ONLY, suspended/closed Tenant, inactive membership and revoked grants after opening the screen. Native confirmation must recheck current authority.

**Expected:** reasoned preserved abort, no phase/baseline advancement, correct scope unlocking and safe rejected transitions.

## P7-23 — lost reply and same-reference recovery

Use the controlled procedure below for start/count/commit/review/explanation/finalize/abort/ACK/exclusion/note.

- Unknown outcomes retain only control UUID/action and, for a note, its UUID in the host/user/Shop session marker. No amount/reason/duty body/receipt/cookie is stored there.
- Check saved status performs reads only; reconnect/reload never automatically POSTs. The saved server observation is shown while unsaved input is retained for comparison.
- A404 is only absence at that read, not proof a request cannot later finish. Continue a missing start only with the original reference and re-enter original responsibility context; changed-body replay conflicts.
- A committed but lost count/transition reply returns the same control state; no new UUID/control/variance is substituted.
- A lost note reply preserves its note UUID. Locate the saved note; if still uncertain, explicitly retry that same UUID and original type/text, not a new note reference. Changed details conflict.
- Test storage disabled/corrupt, account/host/Shop switch, lost view grant, browser session timeout and native request cancellation. Keep references; do not clear site storage to dismiss uncertain business work.

**Expected:** one accepted effect per permanent identity/transition, explicit outcome inspection, no silent overwrite or automatic replay.

## P7-24 — later notes, history, UI and package handover

1. On finalized nonzero F1 variance, append EXPLANATION, INVESTIGATION_FINDING and REVIEW_NOTE through authorized review+note grants.
2. Verify full note length up to2,000 characters, plain rendered text, public UUID, author/time and safe bounded Audit reference; no note body is forced into a500-character Audit reason.
3. Double-submit/retry same note UUID/type/text, then change type/text/source with the same UUID. One original note remains; changed identity conflicts. No edit/delete route exists. A balanced control has no Cash variance to annotate.
4. Browse bounded control/readiness/member/context/note pages. Confirm no unbounded response/N+1 request storm, foreign data, raw IDs or stale records after failed/offline refresh.
5. On phone/tablet/desktop and keyboard check focus, labels, confirmation, input retention, loading/empty/error/denied/read-only/offline/unknown states, source-reference check, separate Counter/Reviewer roles and no expected-value leak through Print.
6. Review migration/SQL pairing, artifact inventory, WP1 acceptance record, result register and open verification gaps. Do not infer whole-Phase acceptance or production authorization.

**Expected:** append-only information without rewriting history, complete integrated Cash workflow and honest WP2 evidence handover. Stock/Full/adjustment UI remains absent.

## P7-23 — distinguish not sent from committed but no reply

On the isolated native app, prepare a synthetic command and retain its control/note UUIDs.

**Not sent:** block only the exact command POST in browser request blocking (not its GET status or readiness challenge). Confirm unknown state, remove the block and Check saved status. A404 is inconclusive. Continue with the same reference and original details; do not start another control/note UUID.

**Committed but lost reply:** place a native PHP debugger breakpoint in CashControlController after the service command returns and **before** detail/HTTP response is sent. At this point CommercialAccess has committed. Let the browser timeout, resume the server so session locks can release, then Check saved status. Expect one control/count transition/variance/note/ACK/exclusion and no automatic retry. Pausing inside the service before commit does not prove committed-but-lost behavior.

For note retry, keep its original UUID/type/text. For count conflict, compare the displayed saved server observation with retained input before explicitly saving again with its current revision. Do not call a network timeout a conclusive failure.

## Controlled native rollback checks (developer aid)

No fault endpoint or production toggle is shipped. On a disposable development copy with other operators stopped, temporarily throw a RuntimeException at one location at a time in CashControlService:

1. After snapshot/control/required-set or count/ACK writes, before required Audit.
2. After Audit or normalized variance/Staff-context/note insertion, before finish/publication.
3. Immediately after changes->publish in start/finish, before the owning callback returns and commits.

Exercise real commands and compare scoped control/count/variance/note/context/ACK/Audit/change identities before and after. Each failed transaction leaves no partial success set, orphan acknowledgement, incomplete relevant set or baseline advancement. Restore every temporary line and run build/inventory check before continuing. Never delete protected rows, edit the migration ledger, seed acknowledgements, or run faults in production. Native concurrent save/commit/finalize/abort and source-post/start races also require two real database/browser sessions; parser/SQLite/mock observations do not prove locks.

## Results and package boundary

[WP2 results](Phase-7/Phase_7_WP2_Manual_Results.csv) retain P7-13–P7-24 and start **Not run**. Record actual commit/environment/actor/grants/Device/source UUIDs, expected/observed values, result, evidence, reviewer and Africa/Lagos time. Retain the distinction between package acceptance and individual native observations. P7-06's real readiness and P7-10's Cash-scope runtime portions can now be observed alongside these cases; do not silently relabel earlier records.

Native PHP/MySQL migrations/constraints/DECIMAL/transactions/locks, HTTP/session/CSRF/cookies, generation recovery, backup/restore and real browser/device/accessibility acceptance are Owner gates. WP2 adds no Stock/Full lifecycle, Stock adjustment, offline transport or offline finalization. WP2 must be explicitly accepted before WP3. Merge, migration and production deployment remain separately authorized operations.
