# Phase 7 WP4 — Owner manual verification (P7-37–P7-48)

**17 November 2026 (Africa/Lagos). WP3 accepted; WP4 candidate for review. Release HOLD until native evidence and whole-phase acceptance.** Use isolated backed-up synthetic shops on supported native PHP/MySQL. Never inject faults or edit protected evidence in production.

## Setup and candidate integrity

From the project folder:

    git fetch origin
    git switch codex/phase-7-wp4-final-handover-20261117
    git pull --ff-only
    git rev-parse HEAD
    composer install
    npm ci
    npm run build
    npm run ui:release:check -- --output=docs/Phase-7/Phase_7_WP4_Artifact_Manifest.json
    php spark migrate:status

Record exact commit, manifest-file SHA-256/content digest, PHP/Composer/database/browser/OS/server versions, SQL mode, storage engine, hostname, UTC and Nigeria display time. In Windows XAMPP use the installed PHP executable if `php` is unavailable. `SELECT VERSION()` identifies the actual database; XAMPP is not proof of MySQL8.4. Do not regenerate the inventory to hide changed files; only documented exact CRLF repair is permitted.

WP4 adds no migration. All nine Phase7 migrations are prerequisites; inspect status and native objects. On an approved backed-up copy only, apply missing migrations using `php spark migrate --namespace App`. Empty installation order remains Settings → Shield → App. Compare approved SQL twins on a different disposable copy, never both methods on one database. Preserve the existing private key/OpenSSL and host-scoped Device credentials. Do not publish secrets. Read [final operations](operations/phase-07-final-handover.md) before migration/restore/rollback.

## Actors and fixtures

Use Owner, separately granted counter/reviewer/finalizer, Staff without those grants, READ_ONLY and inactive/suspended members; two shops in one tenant and a second tenant; two real browser installations A/B with distinct verified Device cookies. Record public fixture UUIDs, role/grants, Device identities, active generation, times, request IDs and source IDs without credentials. Repeat scope-sensitive cases for Cash, Stock and Full, with disjoint Cash/Stock active together.

Use the accepted [F1–F8 fixtures](Phase-7/Phase_7_Implementation_Plan.md#7-deterministic-acceptance-fixtures): F1 expected35000/actual34500; F2 corrections net−800; F3 captured51/actual48/difference−3, later Sale to49 then linked adjustment to46; F4 independent predecessors; F5 two pinned Devices; F6 time boundaries/backdating; F7 cancellation/failure/lost replies; F8 zero/fraction/negative expected/cost states. Use approved UI/services, never direct business-row edits. Earlier [WP1](manual-verification-phase-07-wp1.md), [WP2](manual-verification-phase-07-wp2.md) and [WP3](manual-verification-phase-07-wp3.md) guides supply setup and all first36 case details.

## P7-37 — Integrated native schema rehearsal

**Steps:** Restore an empty-install copy and a Phase6 copy. Record versions/status and backups. Apply the approved ordered prerequisites, inspect tables/columns/keys/FKs/CHECKs and existing evidence. On separate copies apply SQL twins and compare actual schema. Simulate a controlled partial-DDL stop on a disposable copy and document inspection/recovery. Attempt invalid foreign-tenant links and orphan Device/variance links through the approved rehearsal mechanism.

**Expected:** Equivalent supported schema, all nine tracked migrations, existing evidence retained; invalid links refused. No blind retry/down/delete of protected rows. Record native output and recovery, not merely SQL text equality.

## P7-38 — Required write failure rollback

**Steps:** On a disposable native copy use a temporary, reviewed fault hook or narrowly scoped permission denial that fails the required domain, Audit, change insertion and transaction commit, separately. Record the exact injected boundary and remove it after each run. Cover start, actual save, readiness ack/exclusion, Full confirm/finalize, notes, cancellation and linked adjustment. Read source/evidence/publication before/after and retry the original ID after restoring the service.

**Expected:** No partial Full evidence, orphan row/Audit/variance/note/movement/ack, duplicated effect or published partial aggregate. An unknown commit reply requires saved-status recovery, not an assumption of rollback. Fault setup and result must be observed natively; scratch mocks do not pass this case.

## P7-39 — Simultaneous commands and missing replies

**Steps:** In two authenticated native sessions coordinate save vs confirm, confirm vs confirm, finish vs cancel, duplicate note and duplicate adjustment. Pause one transaction at the reviewed service boundary while the other runs; record lock waits/outcomes. Repeat all scopes. Drop the response after a successful write using a controlled proxy, then read and retry the same ID deliberately.

**Expected:** One valid transition/effect; stale revision refused; no double baseline advance or second adjustment. Full cash/items commit together. Check/notes/adjustment identities survive a missing reply.404 is not proof an earlier request failed.

## P7-40 — Source writes at the count boundary

**Steps:** Use the [source matrix](Phase-7/Phase_7_WP4_Source_Effect_Matrix.csv) to cover Intake, movement/correction/reversal, Sale/Return/replacement/compensation, Debt receipt/correction/reversal, financial movements/corrections/reversals, Opening Cash and stock adjustment. Coordinate each affected writer before/after start barrier and generation publication lock; include Cash/Transfer compositions and disjoint checks. Capture timestamps and sequence evidence.

**Expected:** A coherent captured quantity/cost/cash result and watermark; affected writes pause/refuse while checks overlap; unrelated effect paths remain usable. No inconsistent cutoff or deadlock escape that skips guards. Record actual native lock/transaction evidence.

## P7-41 — Generation change and later-added records

**Steps:** On the isolated copy retire the active generation using the approved admin path during counting and review. Try counts, confirm, explanations, Device readiness and finish; give a reason and cancel, then start anew. Exercise F6 exact exclusive start/inclusive cutoff and backdated source posted later, including a Full source with cash and stock effects.

**Expected:** Old check cannot advance; reasoned cancellation is available. UI says what to do without technical terms. Old snapshots/predecessors/baselines remain intact. Each late source has one typed causal association, original times unchanged, and only one check-refresh per batch per affected check. Next appropriate period includes the genuine later posting; no synthetic balancing.

## P7-42 — Blind reads and private details

**Steps:** While the counter has an active blind check, inspect every related current/historical detail, list, source, print, cache and API route with browser Network, including cancelled checks. Put recognizable expected-value text in a historical cancellation and Device exclusion reason through the proper service. Inspect requests as blind Owner/counter, non-reviewer, authorized reviewer after confirmation and foreign Shop/Tenant. Include Stock page search, revision token and every source route that reveals totals, costs or notes.

**Expected:** No expected quantity/cost/cash, difference, reason text, revealing Staff/notes or source totals bypass the policy. Cancel reasons are omitted for blind/non-review readers; exclusion reasons wait for authorized non-blind review or a cancelled record. Internal reason evidence is preserved. No credential/secret/internal database IDs or foreign enumeration. Count token does not encode expected values/digest. A reviewer sees only allowed post-confirmation data.

## P7-43 — Real access and installation proof

**Steps:** Exercise unauthenticated/session expiry, real CSRF failures, wrong hostname, MFA boundaries where enabled, READ_ONLY, suspended shop/tenant/member, inactive member and revoked grant mid-session. Try Staff administration, Device UUID spoofing, copied/invalid/missing credential, revoked Device and foreign rebind. Inspect real HTTPS cookie flags/origin and recovery.

**Expected:** Each current server grant, tenant/shop/member and Device boundary is enforced; reads do not imply mutation permission. No automatic extra Staff grant. Ordinary login is not installation proof. Safe errors and request IDs without secrets; no unauthorized business/Audit/publication writes.

## P7-44 — Phone, keyboard and plain words

**Steps:** Complete all scopes through start/count/confirm/review/finish/cancel/notes/adjustment and Device setup on physical phone, tablet and desktop. Use320/390/768/1366 widths,200% zoom, keyboard only, screen reader and phone number keyboard. Tab/Shift+Tab through each dialog including first/last/no-enabled-control states; Escape and return focus; long names/notes, errors, pagination and readonly states. Ask a trader to explain the page and next action in their own words.

**Expected:** No hidden required controls or sideways scrolling; persistent navigation does not prevent reaching controls. Focus stays in dialogs and returns to the opener; labels/errors announced. “Less/more” meaning is written, not color alone. No heavy financial/developer wording. Headless synthetic-browser screenshots are supporting engineering evidence only; physical usability and comprehension are still to be recorded.

## P7-45 — Offline, stale and unknown saves

**Steps:** Disconnect/reconnect/restart at every stage; save an item while a different item and Full Cash have unsaved entries. Cause stale revisions with a second session; keep a v1 page open during WP4 upgrade. Lose start/count/confirm/cancel/note/adjustment replies, retain same IDs and check saved status. Cancel after a generation change, including recovery of a lost cancellation reply.

**Expected:** Saved work survives; unrelated drafts stay until discarded or confirmed cancellation. Old token gets409 then fresh read/check and explicit retry. Confirmed/recovered cancellation drops unsaved item drafts but retains saved evidence. No automatic repost, duplicate effect, offline finish or claim of durable unsaved work. Explicit status check is always fresh GET and cannot clear a different pending command.

## P7-46 — Shop day and large item list

**Steps:** Run Intake → Sale/Return → Debt payment → expense/correction → Cash/Stock/Full → notes → F3 adjustment → Audit/source links. On a large synthetic shop (record size; include at least137 and a representative production-size list), inspect first/middle/last/empty Stock pages, exact-zero progress, renamed/inactive Products, literal percent/underscore search and non-ASCII names. Capture native query count, returned rows, EXPLAIN, scanned rows, peak memory and latency; compare before/after. Read detail and save a single item separately from whole-count confirmation.

**Expected:** F1/F2/F3 exact values; later Sale retained and single adjustment49→46. Page≤50 count rows; proof lookup≤page-size+1 and page-only variance links; no per-item query loop or full PHP materialization during detail/page/save. Captured names remain searchable. Whole confirm/finish/adjust still verify full immutable evidence. JSON search/aggregate/OFFSET scans are measured and acceptable on native indexes; bounded returned rows alone do not pass performance. One successful command avoids redundant detail GET; no stale request storm.

## P7-47 — Exact release and compatible recovery

**Steps:** Verify inventory against exact reviewed commit; compare migration status and paired SQL. Perform and document isolated backup restore of database/config/code/assets; compare IDs, final evidence, later sources, Audit/publication and generation. Rehearse code/assets rollback to accepted WP3 with an active check, finished Full and linked adjustment; reload pages and check original command IDs.

**Expected:** Exact build/inventory, working restore and preserved private key. Compatible WP3 reads/guards/evidence retained; no Phase6 fallback after Phase7 activity, destructive down/delete, automatic repost or silent loss of activity after backup. Record operator, times and protected backup/hash location, never secrets.

## P7-48 — Whole-phase decision

**Steps:** Review all32 requirements,48 original cases, source matrix and open gaps. Enter each observed result/evidence/reviewer/time and repeat fixed failures. Fill the [release decision](Phase-7/Phase_7_Release_Decision.md) with the exact reviewed commit/inventory, versions, restore outcome and unresolved issues. Request explicit whole-phase Owner acceptance only after all gates are supported.

**Expected:** No unresolved high/critical release blocker; all roadmap criteria supported by observed evidence and accepted disposition. WP4 review, whole-phase acceptance, merge and deployment are distinct decisions. Phase8 requires a separate plan/authorization.

## Record the result

Update [WP4 twelve cases](Phase-7/Phase_7_WP4_Manual_Results.csv) and [integrated48 cases](Phase-7/Phase_7_WP4_Integrated_Manual_Results.csv); preserve earlier package registers. Use Pass/Fail/Blocked/Not run honestly. Include expected/actual values, exact commit/inventory, native environment, fixture/actor/Device IDs, sanitized evidence, reviewer and Africa/Lagos time. The broad Owner report “Everything now works” accepts WP3 but supplies none of these individual native observations.
