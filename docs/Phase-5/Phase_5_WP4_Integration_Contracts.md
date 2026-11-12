# Phase 5 WP4 — Customer statements and release handover

12 November 2026 (Africa/Lagos). Implements the [approved WP4 plan](Phase_5_Implementation_Plan.md) and [binding WP1 statement contract §9](Phase_5_WP1_Integration_Contracts.md), following [WP3 Owner acceptance](../acceptance/2026-11-12-phase-05-wp3-owner-acceptance.md). WP4 and the whole phase are now Owner-accepted and closed; [dated decision](../acceptance/2026-11-12-phase-05-owner-closure.md). Historical delivery-workspace limitations remain distinct from that decision. No new financial writer, schema, library, reporting/export product, offline transport or Phase 6 feature.

## Sources and responsibility

| Source | Responsibility |
|---|---|
| `CustomerDebt/Infrastructure/StatementEventQuery` | Scoped canonical UNION read model; every debt effect once, zero-debt Return context retained |
| `CustomerDebt/Application/StatementInput` | Closed date/page/cursor query input, Shop-local midnight boundaries |
| `CustomerDebt/Application/StatementCursor` | Purpose-derived AES-256-GCM authenticated opaque page anchor; fail closed without deployment secret |
| `CustomerDebt/Infrastructure/StatementQuery` | Current view authorization, one read-only snapshot, version/anchor checks, exact totals/running debt and safe event DTOs |
| `Api/DebtController::statement`, `Config/Routes.php` | One authenticated GET endpoint, existing Shop/session resolution and response conventions |
| `DebtPaymentRepository::verifiedAllocations` | Shared receipt/statement allocation evidence/hash/sum check; normal receipt behavior retained |
| `customer-statement-panel.js` | Filters, contextual read-only paging, refresh/recovery, accessible loading/errors, page/full-range print preparation |
| `customer-statement-document.js` | One screen/customer-print representation; readable direction, timestamps, links and allocation explanations |
| `customer-debt-panel.js`, `_trading.scss` | Existing Customers contextual route and responsive/print styling; no new navigation destination |

The statement GET emits no domain, Audit, sync, receipt, statement cache or cursor-table writes. Signed/opaque cursors are not Phase 10 transport. Existing money/correction participants and transaction rules remain WP2/WP3-owned.

## API

`GET /api/v1/shop/customers/{public_uuid}/statement`

Requires `customer.view` **and** `debt.view`, established Tenant/Shop membership and the existing AccessGate. READ_ONLY may read; BLOCKED/CLOSED/inactive/foreign scope and Platform Admin without Shop membership are denied by existing context/access checks. `debt.manage`, collect, reverse, adjust or sale.view alone grants no statement authority. All queries use current-Shop debt for a Tenant-owned Customer, including inactive Customers with legitimate history. A cursor is never authentication or authorization.

| Query | Contract |
|---|---|
| `from_date` | Optional strict `YYYY-MM-DD`, inclusive Shop calendar date |
| `to_date` | Optional strict date, inclusive; SQL upper bound is next Shop calendar midnight, exclusive |
| `per_page` | Integer1–50, default20; UI offers20/50 |
| `cursor` | Optional server-produced opaque page/checkpoint token, max8192 characters; never accept a client running total |

Empty dates mean an unbounded side. Reject impossible dates, reversed ranges, arrays, unknown keys and invalid page size with422 and field guidance. Valid date years1000–9999, except9999-12-31 (next midnight must remain representable). Boundaries are converted from the Shop timezone to UTC; calendar-day addition handles23/25-hour DST days rather than assuming86400 seconds. Dates filter **accepted posting time**, not backdated occurrence. Display both separately in Shop time.

DTO: Customer `{public_id,display_name,status}`, Shop `{public_id,name,timezone}`, applied filters, `as_of`, range summary `{opening_amount,increase_amount,decrease_amount,closing_amount,current_amount}`, event items, and pagination `{per_page,total,from_record,to_record,page_start_amount,page_end_amount,page_cursor,next_cursor}`. Money is exact decimal strings; no JS/PHP monetary float. Internal `source_id`, event surrogate key/rank, Tenant/Shop numeric IDs, watermark, command/allocation hashes, secret, actor-private details, Customer phone/notes, bank references, Audit reasons, Inventory costs/profit are omitted. The encrypted cursor does not expose its internal fields.

## Event model and totals

| Source | Effect on amount still to pay | Links/context |
|---|---|---|
| Original credit bill | `+original_amount`, once | Bill and original Sale |
| Sales-owned Return/cancellation/Return inverse | Sum related signed adjustments, once per commercial source/bill | Commercial source and original Sale; Cash/Transfer refund or money received back shown separately, never added again to debt |
| Non-Sales factual bill correction or inverse | Its signed delta, once | Bill-change detail and original source |
| Normal standalone payment | `−payment.amount`, once | Receipt, all ordered verified allocations and any correction links |
| Linked payment inverse | `+payment.amount`, once | Original receipt, inverse, corrected replacement where present |

Payment allocations are explanation, not another debt effect. Original and inverse both remain; do not exclude original and then apply inverse again. Fully repaid credit-bill Returns with no debt adjustment still appear as zero-effect context. Their refund amount is separate from the debt column. Fully paid Sales with no original Receivable are outside this debt statement, not invented credit events. Draft Sales and unaccepted requests never enter the statement. Original snapshots remain untouched.

Order is `(posted_at, event_kind_rank, internal_id)`, with CREDIT=1, ADJUSTMENT=2, PAYMENT=3. The ADJUSTMENT rank combines commercial and generic sources: `2*sale.id` for a grouped Sales event; `2*adjustment.id+1` for a generic bill change. SQL DECIMAL(21,0) arithmetic gives disjoint keys without unsigned-BIGINT multiplication overflow or cross-table collisions. These keys stay internal/encrypted. Backdated occurrence does not reorder accepted history.

One REPEATABLE READ READ ONLY transaction derives opening before the lower bound, in-range increases/reductions/count, closing through the upper bound, current authoritative debt and page rows/detail allocations. `opening + increases − decreases = closing`. Page carry is the server-sealed running anchor; each event applies exact string arithmetic, and final page must equal closing. All-history event sum must match the shared live Receivable projection; invalid evidence fails closed with409. Reading does not repair data or manufacture a balancing row.

## Consistent versioned paging and cursor configuration

Use existing deployment `encryption.key`, at least32 characters, with strong random entropy. It is not sent to the browser or committed to source. The cursor class derives a purpose-separated256-bit key with HMAC-SHA256, then uses OpenSSL AES-256-GCM with a fresh12-byte IV,16-byte authentication tag and fixed versioned additional data. GCM authenticates all page state and encrypts numeric keys/balances; there is no unsigned fallback or default secret. Missing/short key or unavailable OpenSSL produces `STATEMENT_SETUP_REQUIRED503` and a clear operator setup instruction. All app instances serving one deployment need the same persistent key; changing it invalidates outstanding pages. Follow [operations](../operations/phase-05-statements-release.md) rather than copying a sample key.

A token binds version, Tenant/Shop/Customer, current user/membership, Shop timezone, normalized range/page size, last accepted ordering key, opening/closing/running anchors, records already seen, initial preparation time, fixed30-minute expiry, active sync generation and latest relevant change sequence. Generation/max sequence are read from the same snapshot as events; sequences stay strings, not lossy JSON numbers. Any current-Shop **or Tenant-level** change conservatively invalidates all affected Shop statements, even if another Customer changed. This is permitted; no silent mixed-version pages. Generation rotation, expiry or binding/version drift requires409 `STATEMENT_REFRESH_REQUIRED`. Malformed/tampered/wrong-key cursor fails422; no decoded state is trusted before authentication.

`page_cursor` seals the current request boundary; first page has last=null/seen0. The UI saves only page tokens in component memory, not statements in browser storage, so Previous to the first page also checks the original version. Fresh Refresh drops the token and starts over. `next_cursor` carries last row and running total, not a client offset. Each next/previous request reauthorizes; no permission is cached inside the token. No snapshot transaction spans HTTP requests.

## UI and printing

Customers → Money customers owe → View bills → **View customer statement**. Bill detail offers the same contextual action. Route `#customers/debt/{customer}/statement`; no extra sidebar/bottom item. Shared shell/icons/selected brand colors, fields, persistent alerts and loading states remain. No financial action is added. Applied dates and Shop timezone appear on document; changing inputs disables paging/printing until Show statement applies them. Aborted/superseded reads cannot replace the current result. Shared http-client now combines caller cancellation with its existing10-second deadline; previously supplying a signal bypassed timeout cancellation. Listener cleanup and uncertain POST status0/no automatic retry are preserved. Unreadable/truncated/incomplete2xx success envelopes also become uncertain status0, not a deterministic failure that could discard a saved operation identity. Denial, invalid result, stale page and offline transitions clear previous amounts and tokens. Reconnection asks for refresh; no fake cached statement or offline queue.

Event cards show saved/happened time, plain-English description, signed debt change, payment method where relevant and server running total. Allocation details explain amounts without double counting. Contextual Sale links require sale.view; bill/payment/change links retain independent server authority. Statement view permissions never grant corrections.

**Print this page** explicitly says which record range it contains and carries its page opening/closing plus whole-date-range totals. **Print full date range** re-reads the first page with its version checkpoint and traverses all next cursors; no print occurs if any page fails, changes, duplicates or is missing. Full print is bounded to2000 records to keep browser preparation usable; above that, choose a shorter range or print separately labelled pages. All records remain available through pagination, not silently truncated. Cancel/unmount/offline abort preparation; late cancellation cannot overwrite a newer attempt. Prepared pages stay in memory only, then are discarded.

Screen and print use the same document component. Filtered clone removes buttons, links and `.receipt-private`; collapsed allocation explanations are opened. Native beforeprint/afterprint opens/restores details and shared print CSS hides private current total on Ctrl+P as well. Statement print CSS replaces the single-receipt absolute layout with normal block flow for multiple pages and removes outside task controls. Every copy labels Customer, Shop, range, preparation time/timezone and scope. The live all-date Customer total is internal and is hidden on customer print; historical range totals and running values remain. No PDF/CSV export subsystem.

## Release boundary

WP1/WP2/WP3 package acceptances stand; per-case outcomes not supplied are not invented. WP4 adds no migrations/dependencies. Existing paired WP1 SQL, Audit vocab/writers and Cash/Inventory guards are unchanged. [Integrated coverage/results](Phase_5_Integrated_Manual_Results.csv), [native WP4 guide](../manual-verification-phase-05-wp4.md), [closure checklist](Phase_5_Closure_Checklist.md), [defect/gate disposition](Phase_5_Defect_Disposition.csv) and [final acceptance template](Phase_5_Final_Acceptance_Template.md) keep implementation, native evidence, Owner whole-phase acceptance and actual rollout distinct. No native PHP/MySQL/HTTP/Print/concurrency, deployment or whole-phase closure is claimed from parser/build/host/SQLite probes.
