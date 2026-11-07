# Phase 4 final integrated manual verification — WP4-D

Status updated 2026-11-07: **owner reports completing manual review/testing and accepting the integrated delivery; Phase 4 is CLOSED.** See [acceptance/provenance](acceptance/2026-11-07-phase-04-integrated-owner-acceptance.md). The steps below remain a reusable regression checklist. Owner acceptance is recorded overall; individual case/environment logs were not supplied. Historical assistant execution limits are preserved and are not converted into independent native PASS results.

## Checkout and preparation (Windows Command Prompt)

Use a development checkout/database backup, not live business data. Record branch/commit, PHP version, actual database vendor/version, browser and actor grants. Supported schema target is MySQL 8.4; XAMPP alone does not identify its database vendor or establish compatibility. Resolve any vendor mismatch explicitly.

```bat
git fetch origin
git switch codex/phase-4-wp4-d-integration-20261106
composer install
npm ci
npm run build
php spark migrate:status
php spark migrate --namespace App
php tests/contracts/wp1.php
php tests/contracts/wp2.php
php tests/contracts/wp3.php
php tests/contracts/wp3-posting.php
php tests/contracts/wp4-b.php
php tests/contracts/wp4-b-posting.php
php tests/contracts/wp4-c.php
php tests/contracts/wp4-c-posting.php
php tests/contracts/wp4-d.php
```

Expect `Frontend assets built.`; hard reload. The contract scripts identify their own limits. Apply migrations once through Spark, not also raw SQL. New migration is `2026-11-05-100000`. Read the delivery migration/downgrade restrictions before testing rollback on a disposable clone.

## First Return walkthrough (now in the operator UI)

1. Sign in as Owner in the intended Shop. Finalize Opening Cash if actual Cash will be refunded.
2. Record and confirm an original Sale; verify its receipt in Sale history. A draft is not a posted Sale.
3. Open that original posted receipt and choose **Return Products**. Select original-line quantities, save them, then calculate settlement.
4. Check the displayed Return value, existing debt reduction and required refund. Enter actual Cash/Transfer refund amounts whose sum equals required refund. A debt-only Return uses zero money fields.
5. Enter a clear reason, review, read the authoritative summary, tick confirmation and post once. Inspect the linked Return receipt, stock evidence and original debt.
6. For correction, use the posted ordinary receipt's reversal preview, reason and confirmation. A dependency error means later effective evidence prevents direct reversal. After successful reversal, prepare a replacement draft if needed; review and post it separately.
7. If a response is lost, retrieve the same operation/source receipt. Do not create a second operation to guess whether the first committed.

## Verification matrix

| # | Scenario | Required observation | Result |
|---|---|---|---|
| 1 | Migration | On a disposable supported MySQL 8.4 clone: back up, record SELECT VERSION(), migrate status, apply once, repeat no-op and inspect SHOW CREATE TABLE sales. Verify ordinary Sale composition still rejects invalid totals and both compensation shapes accept only approved NULL snapshots. Exercise preflight refusal with unexpected normal-Sale lineage; test down on unused schema and refusal after used SALE compensation. Never delete evidence to force rollback. | Owner accepted overall; no per-case log supplied |
| 2 | Fully paid Returns | Independently test Cash, Transfer and mixed original receipts. Return a partial quantity then remaining quantity. Verify original price/COGS, incoming stock/average/version, exact remaining quantities and actual refunds within each original channel. Fully returned totals exactly equal original totals. | Owner accepted overall; no per-case log supplied |
| 3 | Credit Returns | Test full credit and Cash+credit, Transfer+credit, Cash+Transfer+credit originals. For 20,000 total / 5,000 Cash / 5,000 Transfer / 10,000 credit, independent Return 4,000 reduces same debt to 6,000 with no refund; independent Return 12,000 reduces debt to zero and refunds only 2,000. No CREDIT payment/new Receivable. | Owner accepted overall; no per-case log supplied |
| 4 | Fractional and original attribution | Use fractional quantities and rounding-sensitive prices/costs, repeated Product lines, zero-price/zero-rounded value lines; change current Product price after Sale. Return attribution stays historical. Reverse an earlier fractional Return and return remaining quantities out of order; full consumption conserves all original sales/COGS kobo. | Owner accepted overall; no per-case log supplied |
| 5 | Cost provenance | Test VALID, RETAINED, UNKNOWN and negative/zero stock histories; return unknown-cost stock into known positive stock and later Intake into mixed positive unknown stock. It remains provisional until depletion and known fresh positive basis. Verify pre-Return historical interpretation, weighted arithmetic, overflow rejection and original-line provisional margin labels. | Owner accepted overall; no per-case log supplied |
| 6 | Inactive history | Deactivate original Product/Customer and process allowed historical Return/reversal. A forward replacement Sale still enforces current active Product/Customer rules. Cross-Tenant/Shop IDs never expose or mutate foreign data. | Owner accepted overall; no per-case log supplied |
| 7 | Cash baseline | Without finalized Opening Cash reject any actual cash refund or reversal cash receipt before writes. Pure Transfer/debt-only effects remain eligible. Finalize baseline and retry with fresh review; verify scope/read-only/closed restrictions. | Owner accepted overall; no per-case log supplied |
| 8 | Return reversal | Reverse a debt-only and mixed-refund Return after preview/reason/checkbox. Stock exactly restores before qty/average/state, payments exactly inverse original refunds, positive REVERSAL restores SAME Receivable. Both compensation snapshots NULL; original evidence unchanged. Same identity retry produces no duplicate. | Owner accepted overall; no per-case log supplied |
| 9 | Sale reversal | Effective Return blocks original Sale reversal. Reverse all eligible Returns, then reverse original Sale if latest-effective suffix is valid. Refund exact original receipts, cancel original credit with CORRECTION_DECREASE, restore stock and append one compensation. Reject second direct reversal and reversal-of-compensation. | Owner accepted overall; no per-case log supplied |
| 10 | Dependencies | Later unreversed Intake/Sale/manual movement/Return prevents direct reversal. Exact later fully reversed pairs may be ignored only if quantity/average/cost-state restore. Verify repeated Product lines and multi-Product eligibility is all-or-nothing; drift or collection-allocation evidence blocks safely. | Owner accepted overall; no per-case log supplied |
| 11 | Replacement | From reversed original Sale prepare one linked replacement draft with current prices; no stock/payment/debt effects until separate normal posting. From reversed Return prepare one root-linked Return replacement, save/review/post normally. Existing source links refresh; second different replacement denied, same identity resolves existing result. | Owner accepted overall; no per-case log supplied |
| 12 | Draft lifecycle | Save/edit/reload Return quantities; discard with reason. Empty/negative/excess/duplicate original lines, stale expected_updated_at, wrong original, manipulated price/cost/channel and extra input are rejected. Drafts reserve no stock/refund/debt capacity. Unsaved quantities block UI review. | Owner accepted overall; no per-case log supplied |
| 13 | Settlement review | Use two sessions: change original debt/refund capacity or eligible stock after review. Confirm rejects stale review or dependency with no partial writes. Reload/review authoritative data. Refund Cash+Transfer must equal required refund; each channel remains within effective original receipt capacity. | Owner accepted overall; no per-case log supplied |
| 14 | Concurrency | Against native DB/HTTP, concurrently post competing Returns, reversal vs Return, reversal vs stock posting, same-UUID retries and distinct replacement requests. Verify root and sorted Product serialization, one direct compensation/replacement, no over-return/over-refund/duplicate evidence/deadlock-induced partial result. Record observed errors and recovery. | Owner accepted overall; no per-case log supplied |
| 15 | Rollback faults | On disposable clone inject failures at each stock movement, position, line/header, payment, adjustment, Audit, change publication and commit boundary. Verify every transaction leaves no partial new financial/stock/Audit/change effect. Then remove injection and verify normal command. | Owner accepted overall; no per-case log supplied |
| 16 | Uncertain network | Interrupt post/reversal response after server commit. UI disables blind repost; retrieve authoritative same operation/source links and open committed receipt. No new command UUID/payment created. Test uncommitted failure recovery separately and reload after session expiry. | Owner accepted overall; no per-case log supplied |
| 17 | Permissions and HTTP | Staff sale.view only cannot mutate; sale.return independent of sale.reverse; reverse/replacement permission separately enforced. Without sale.profit.view inspect receipt/history/API/DOM for cost/COGS/margin leakage. Check FULL/READ_ONLY/BLOCKED/CLOSED, revoked membership, session expiry, CSRF denial, JSON errors/no-store and no raw SQL/private contact leakage. | Owner accepted overall; no per-case log supplied |
| 18 | Refresh and Audit | Each posted mutation has one reasoned aggregate Audit and atomic scoped refresh changes for affected Sale/root/source, movements, positions, same Receivable and Customer context. Drafts/replacement preparation have no financial refresh effects. Inspect compensation indirect movement links and source/replacement receipt navigation. | Owner accepted overall; no per-case log supplied |
| 19 | History and diagnostics | Filter Sales, Returns, compensation and Return drafts; search/date/page and related navigation. Run scoped sales:diagnose and inventory:diagnose on clean fixtures, then disposable corrupt fixtures for category detection. Read-only diagnostics never repair or claim full certification. | Owner accepted overall; no per-case log supplied |
| 20 | Usability/accessibility | Desktop and narrow phone: total no longer overlays fields; Products → basket → payment review → confirm is understandable. Keyboard Tab/Enter, focus after errors, labels, checkbox confirmation, 200% zoom, scrolling, empty/loading/error/offline/read-only states, receipt printing and cost masking. Offline never reports queued business success. | Owner accepted overall; no per-case log supplied |
| 21 | Integrated regressions | Repeat accepted WP1 Opening Cash, WP2 Customer/draft and WP3 all seven payment compositions; price override/Customer privacy, Inventory Product/Intake/reversal, Audit and change generation. Verify original posting remains immutable and ordinary Sale composition unchanged. | Owner accepted overall; no per-case log supplied |

## Executed engineering checks and pending native/visual gates

The D delivery report identifies executed PHP WebAssembly, build, source/asset and mocked DOM checks. It does not claim native MySQL, authenticated HTTP or concurrent transaction execution. Live Chromium could not create its required socket in this workspace; the available browser could not reach the local fixture. Desktop/phone layout, real keyboard/zoom/contrast and printed output remain NOT EXECUTED/BLOCKED until tested in your environment. The optional `tests/contracts/wp4-d-browser.mjs` harness is authored but was not successfully executed here. It requires Chromium plus optional external playwright-core/axe-core tooling, and mocks APIs; it cannot certify database/session behavior.

Run the ordinary application on your configured supported MySQL 8.4 development clone. Record PHP/database vendor/version, branch/commit, Shop, Owner/Staff grants, browser/device, PASS/FAIL/BLOCKED, actual observations and evidence. XAMPP alone does not establish database compatibility. Do not delete immutable evidence or disable constraints to force rollback. Historical A/B/C API walkthroughs remain useful for direct endpoint denial and fault/concurrency checks.

Additional UI checks: start a Sale → select Product → Add to basket → review basket → enter payment → Review Sale → explicit confirmation. Total must not cover any field. Open Sale history/Returns from the posted receipt, create/save/reload a Return, confirm it and follow original/reversal/replacement links. Filter saved Return drafts and resume one; verify paginated results and no stale quantities/review when switching drafts. Simulate response loss for draft creation, Return posting, reversal and replacement, retrieving the same identity before another write. Confirm parent navigation/reload is disabled during child mutation and original evidence is immutable.

For printing, use a cost-authorized and cost-masked account; verify only the selected receipt, its quantities, actual money/debt effects and opened stock proof are printed, without other workspace content or private costs. Test a multi-page receipt and clean return to the application after print cancellation. Check keyboard-only navigation, focus after load/errors, 200% zoom, 390px phone and desktop widths, long Product names/UUIDs, offline/read-only/pending/expired-session/empty/error states. These are acceptance gates, not conclusions from jsdom.

## Integrated requirement/acceptance record

| Requirements | Existing evidence | Current acceptance boundary |
|---|---|---|
| P4-01–P4-04 | WP1 owner acceptance, 2026-11-03 | Retain acceptance; regress Opening Cash in this integrated run |
| P4-05 | Cash guard across WP3/WP4 | WP4 Cash refund/reversal and integrated check pending |
| P4-06–P4-09 | WP2 owner acceptance, 2026-11-03 | Retain acceptance; new CHECK/draft regression pending |
| P4-10–P4-18 | WP3 owner acceptance, 2026-11-04 | Retain acceptance; Return/reversal interactions and cost provenance pending |
| P4-19–P4-24 | WP4 Returns, compensation, replacement implementation | Owner manual acceptance pending |
| P4-25–P4-33 | Cross-cutting authority, evidence, errors, migration, diagnostics/docs | Integrated verification and final Phase 4 owner acceptance pending |

Attach defects and rerun affected scenarios after fixes. Contract approval authorizes implementation; it does not accept WP4 delivery. Record final owner acceptance only after explicit confirmation of the delivered package and integrated outcomes. No merge/deployment or Phase 4 closure is inferred.

## Per-requirement integrated result register

Retain prior WP1–WP3 acceptance records; each current integrated rerun below remains pending until observed. Enter tested commit/environment, actual observation, defect reference and evidence alongside the result.

| ID | Approved requirement | Implementation coverage | Integrated result/evidence |
|---|---|---|---|
| P4-01 | Full v1.1 Opening Cash schema and existing/self FK ordering | WP1 accepted; regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-02 | One physical Cash amount; zero/positive accepted, invalid rejected; no denominations | WP1 accepted; regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-03 | Opening start/count/review/finalize, abort/restart and immutable receipt | WP1 accepted; regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-04 | One active scope/first finalized baseline, concurrent start/finalize, generation/watermark | WP1 accepted; regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-05 | Cash sale/refund rejected before baseline; pure Transfer/credit not misclassified | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-06 | Complete six commercial tables and late Stock Movement/Sale Item FK | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-07 | Customer create/search/detail/edit/deactivate; shared/missing phone | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-08 | Tenant-scoped Customer identity, privacy/masking, cashier quick-create | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-09 | Multi-item/fractional basket; draft CRUD/discard/refresh and safe draft/post locking | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-10 | Cash-only, Transfer-only and Cash+Transfer Sales | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-11 | Full credit and all three partial-credit payment combinations | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-12 | Customer required for credit; optional valid local due date; no fake payment/interest | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-13 | Server totals, exact decimal rounding, overflow, invalid price/quantity/composition | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-14 | Historical price/cost/COGS and gross profit remain correct after Product changes | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-15 | Negative stock allowed/visible; cost state preserved; repeated Product lines | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-16 | Sorted locks, concurrent sales/Intake, no lost movement/position update | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-17 | Double tap, repeat post, timeout/response loss, stable identity/retrieval | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-18 | Receipt, paginated history/detail, actor and stock evidence links | WP2/WP3 accepted; D interaction regression required | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-19 | Partial/full Cash/Transfer Return with correct refund and stock increase | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-20 | Full/partial-credit Return, adjustment/refund split and nonnegative outstanding | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-21 | Cumulative/concurrent over-return and over-refund denial; inactive historical Product | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-22 | Return totals = refunds + debt reduction; RETURN snapshots NULL | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-23 | Sale and Return reversals/corrections/replacement; original immutable; duplicate denied | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-24 | Dependency conflicts reject safely; compensation effects and profit signs correct | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-25 | Audit actor/reason/metadata/correlation and atomic changes for every authoritative command | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-26 | Failure of Audit/change/stock/payment/Receivable write leaves no partial authoritative result | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-27 | Owner/Staff granular view/create/return/reverse authority; direct API denial | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-28 | Cross-Tenant/Shop IDs, deactivated membership, READ_ONLY/BLOCKED/CLOSED denial | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-29 | Phone/desktop cashier and corrections; keyboard/focus/zoom, loading/error/empty states | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-30 | Offline clearly unavailable; no queued success or generic replay claim | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-31 | Fresh ordered migrations, repeat no-op, paired SQL and non-destructive upgrade | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-32 | Existing Product/Intake/reversal/permissions/Audit flows remain intact | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |
| P4-33 | Documentation, diagnostics, itemized delivery evidence and owner final acceptance | WP4 A/B/C/D implementation candidate | Owner accepted overall (2026-11-07); no per-case log supplied |

## Final owner decision — 2026-11-07

- Integrated manual review/testing/acceptance: explicitly reported by owner.
- Accepted repository baseline observed on main: `09789e8aab48b5d62b546ecf538e491867b5fedf`.
- Exact test-checkout SHA, environment versions, individual gate results and traces: not separately supplied.
- Assistant native/visual execution: historical limits unchanged; no new independent run claimed.
- Implementation PR stack: merged into main, independently verified.
- Phase 4 closure: CLOSED — OWNER ACCEPTED.

The [acceptance record](acceptance/2026-11-07-phase-04-integrated-owner-acceptance.md) supersedes publication-time pending acceptance. Keep this guide for future regression; its original engineering/blocked-browser narrative describes the assistant's delivery environment. Production deployment and future-phase authorization are not inferred.
