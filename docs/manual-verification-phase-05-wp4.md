# Phase 5 WP4 — native statement and integrated acceptance

**Closure update, 12 November 2026:** [Owner accepted WP4 and the whole phase](acceptance/2026-11-12-phase-05-owner-closure.md). Phase 5 is closed. Earlier Pending decision language below describes delivery time; individual native results/runtime details not supplied remain unreported. Integration/deployment is separate.


Candidate: `codex/phase-5-wp4-statements-closure-20261112`, based on accepted WP3 `9e81e769af4b588858de8f522a0ef62022331e11`. Record actual `git rev-parse HEAD`, environment, role/grants, Shop/timezone and device/browser in [WP4 results](Phase-5/Phase_5_WP4_Manual_Results.csv). **Owner acceptance is recorded; individual P5-37–P5-48 outcomes were not separately supplied.** [Full contracts](Phase-5/Phase_5_WP4_Integration_Contracts.md) · [operations](operations/phase-05-statements-release.md) · [integrated48-case register](Phase-5/Phase_5_Integrated_Manual_Results.csv).

Use a backed-up isolated XAMPP/development copy and synthetic data. Do not reset production, insert/edit/delete protected financial evidence or restore an old DB over later accepted money. WP1/WP2/WP3 acceptance is recorded; individual outcomes not supplied are not invented. Reuse unaffected accepted observations; supplement affected integrations.

## Setup

```bash
git fetch origin
git switch codex/phase-5-wp4-statements-closure-20261112
git status --short
git rev-parse HEAD
composer install
npm ci
npm run build
php spark migrate:status
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP4_Artifact_Manifest.json
```

No WP4 migration/dependency. If WP1 tables are missing, follow its documented App migration sequence once on the positively identified development DB. Never migrate:refresh/rollback to test financial history. Windows CRLF-only mismatch: repeat inventory check with `--fix-line-endings`, then repeat plain check; do not regenerate the inventory to conceal genuine edits.

Statement pages require a persistent strong `encryption.key` (at least32 characters) and OpenSSL. Keep an already configured strong key. If absent, use the [private key generation/setup instructions](operations/phase-05-statements-release.md); no actual secret belongs in Git/screenshots/logs. Missing/short key must show a clear setup error, not unprotected page links. Changing the deployment's existing encryption seed can affect other encrypted data, so use a disposable configuration for rotation checks.

Close old trading tabs, hard reload normal Shop URL, sign in as Owner: **Customers → Money customers owe → View bills → View customer statement**. Bill details also offer the action. The statement is read-only; READ_ONLY users with both view grants can read/print. View-only Staff must not gain collection/correction/Sale authority. Check page range, saved/happened dates, signs, carry-in, running totals and whole-range closing before printing.

## Fixtures through normal commands

Use fresh records for independent cases; retain handwritten IDs, signed effects and expected totals. No direct ledger inserts.

1. F1/F2/F5 and the WP3 collected-return fixtures remain authoritative. Reuse their accepted evidence where unchanged.
2. F7 mixed example: Customer bills1000,600,400 → total2000. Collect1200 oldest-first →800. Factual decrease50 on billB →750. Return100 of goods from the fully paid billA → debt change0/refund100. Undo that eligible Return → debt change0/refund capacity restored. Atomically correct1200 to900 → old payment inverse+1200, replacement−900, total1050. Undo decrease50 →1100. Return100 on billC →1000; undo eligible Return →1100. Sales/Inventory/Cash guards still apply. Running list has12 debt/context events: three credits, normal payment, factual decrease, zero-debt Return, its zero-debt undo, payment undo/replacement, factual inverse, debt-reducing Return and its inverse. The definite numerical end is1100.
3. For three full UI pages, use existing eligible synthetic history or create a fresh bill100 and44 small collections of1 for that Customer. That gives45 events and debt56; each physical synthetic collection is independently reviewed/saved through normal commands. Use20 records/page. Do not reuse these events as real money or bypass normal validation.
4. Prepare inactive/no-debt Customer, second Tenant/Shop, view-only/old broad grant/collector/reverser Staff, READ_ONLY/BLOCKED and Admin without Shop membership. Device timezone differs from Shop. Use long labels and a narrow phone viewport.

## P5-37–P5-48

| Case | Native steps | Expected result |
|---|---|---|
| P5-37 | Open F7, compare each event to handwritten list and live bill detail. Inspect normal/inverse payment allocations and original Sale receipt. | Each debt effect once; allocation lines do not subtract twice. Return/cancellation source groups adjustment evidence. Zero-debt Return still appears; refund context is labelled separately. Opening+added−paid/reduced=closing; all-history closing equals current debt. Original snapshots unchanged. |
| P5-38 | Filter from/to dates excluding earlier records; verify posting timestamps against Shop-local dates, not occurrence dates. Test no-from/no-to/empty range, equal dates, impossible date, reversed range, arrays/unknown keys/page sizes0/51 via Network request. | Correct opening before start and closing through inclusive end.422 field guidance for invalid input; no stale document. Lagos date12Nov boundaries are11Nov23:00Z–12Nov23:00Z. DST example America/New_York1Nov2026 is04:00Z–2Nov05:00Z (25 hours); verify PHP/native boundary calculation in isolated scope, not by changing protected posting times. |
| P5-39 | Traverse45-event fixture20→20→5, then Previous twice. Backdate occurrence via normal payment input while accepting now. Inspect equal-timestamp keys if present; use read-only synthetic SQL worksheet for rare tie boundaries, never edit posted times to manufacture a tie. | Page carries start0/end81, next start81/end61, last start61/end56 for the100-credit/44-payment fixture. Stable posting/rank/key order; no duplicate/missing records. Previous first page uses its version checkpoint. Equal timestamps use CREDIT1/ADJUSTMENT2/PAYMENT3 and disjoint adjustment key parity; SQL-only tie proof is distinguished from actual posting evidence. |
| P5-40 | On page1, use a second authorized session to record collection/Return/adjustment. Request Next, Previous-first checkpoint and full Print from the old view. Test malformed/tampered cursor, another Customer/Shop/user, changed filter/page size, expired30-minute anchor and isolated key/generation rotation. | Changed/expired/binding versions return409 requiring refresh; tampered/wrong-key tokens422. UI clears old monetary content and starts fresh only on Refresh. No mixed-version full print or silent carried total. No cursor-auth bypass. Never rotate a real shared key/generation just to test. |
| P5-41 | View payment spanning A/B, its exact undo and successful atomic replacement; repeat a deliberately failed corrected replacement from WP3. Follow permitted detail links. | One normal payment decrease, one inverse increase, one accepted replacement decrease; allocations explain only. Failed pair contributes no accepted rows. Actual original/inverse/replacement links visible; reasons/metadata/private fields absent from statement DTO. |
| P5-42 | Call statement with foreign Customer UUID and cursor from another Tenant/Shop; use view-only Staff without sale.view/customer.private, old debt.manage only, expired/revoked membership, READ_ONLY, BLOCKED/CLOSED and Admin lacking Shop membership. Print via buttons and Ctrl+P. | Both view grants/current context required on every page. No phone/notes/bank reference/Audit reason/cost/profit/internal IDs/watermark/secret leakage. Sale links hidden without sale.view and API remains independently protected. Current all-date balance omitted on customer print. READ_ONLY reads; blocked context denied. |
| P5-43 | Phone/desktop/keyboard with long names, dates, allocation details, empty Customer/range, failed GET and rapid filter/customer navigation. Disconnect while loading or preparing full print; reconnect. Throttle a supplied-signal read beyond10 seconds: deadline must stop it; uncertain money POST remains status0/same-ID recovery, never automatic retry. A truncated/incomplete2xx response must also retain unknown status; use development response interruption after commit, never create new payment UUID as retry. Cancel then immediately restart print. Print page1 and full45-event range; collapse details before Ctrl+P. | Labelled icons/loading/focus/errors accessible; no stale response replaces a new view. No offline queue. Dirty inputs require Show statement before paging/printing. Page copy says records1–20; full copy says all45, contains the last record across multiple printed pages without clipping, opens explanations and excludes private section. Any incomplete/stale full run prints nothing. Cancelled old attempt cannot clear a newer one. Above2000 full print is disabled with shorter-range/page-copy guidance; no reading truncation. |
| P5-44 | Complete fresh day journey: credit Sale1000 → Cash collection600 → Return500 (reduce400/refund100) → eligible Return undo → collection undo → statement/Audit, then independent Transfer/correction fixture. | Debt1000→400→0→400→1000. Refund capacity600→500→600→0. Correct stock/approved historical cost and real Cash/Transfer evidence; collection creates no second Sale revenue/profit. Statement signs match every actual accepted event. Repeat uncollected Return and exact stock dependency rejection without bypass. |
| P5-45 | Read-only inspect native payment/allocations/adjustment FKs, original/inverse/correlation, per-allocation Audit and final changes from accepted WP2/WP3 commands; run isolated rollback/lost-reply procedures from their guides where affected. Compare before/after statement/Print counts. | Immutable causal history; business/Audit/change writes atomic. Reads/Print/cursors add zero domain/Audit/change rows. Existing MySQL constraints and Cash/stock guards retained. Do not mark native atomicity passed merely because no WP4 writer exists. |
| P5-46 | Follow clean locked setup/build/migrate-status and exact manifest on target; configure private key; inspect three-page endpoint and module imports/native Print. Run diagnostics before/after. | Coherent source/published assets, existing WP1 migrations/SQL intact, no new DDL/dependency. Key setup is operational, not stored in Git. Inventory matches or exact CRLF repair; actual content edits fail. `sales:diagnose` read-only, no false legitimate Phase5 findings. |
| P5-47 | Review30-row requirements/source/case matrix,48-row integrated evidence and defect/gate disposition with actual observations. Review relevant existing foundation finding dispositions rather than assuming they were fixed by acceptance. | Every approved requirement implemented or explicitly unresolved; no hidden Phase5 feature moved to Phase6. Native gaps and defects remain visible until resolved/dispositioned; package acceptance is not invented per-case Pass. |
| P5-48 | Review concrete candidate SHA, native results, defect disposition and release/rollback guide. Complete final acceptance template only when ready. | Explicit Owner whole-phase decision closes Phase5; record actual reviewed commit/environment and any residual decision. Rollout/rollback is separately authorized and recorded. Until then phase status stays awaiting final acceptance; no Phase6 implementation. |

F7 contains12 events: 3 credits +1 payment +1 factual decrease +2 zero-debt commercial rows +2 payment correction rows +1 factual inverse +2 debt-return rows. Compare actual source identities as well as totals. The separate45-event fixture closes at56.

For statement HTTP checks use browser Network “Copy as fetch” in the same authorized development session, keep same endpoint/query structure and change only the tested field/token. Do not paste cookies, keys or customer private data into reports. There is no statement POST. When expiry/fault/security infrastructure is unavailable, record Blocked and describe it; never fake protected rows or weaken access to make a check pass.

Read-only diagnostics:

```bash
php spark sales:diagnose TENANT_PUBLIC_UUID SHOP_PUBLIC_UUID
```

Each category samples up to100 anomalies and repairs nothing. For native rollback/lost-reply evidence use [WP2 guide](manual-verification-phase-05-wp2.md) and [WP3 guide](manual-verification-phase-05-wp3.md): isolated uncommitted exception after effects/Audit/publication, or debugger pause **after commit before HTTP reply**, then same-ID status recovery. Restore exact code, rebuild and verify inventory before accepting. No source parser/Vue mock/SQLite probe substitutes for those outcomes.
