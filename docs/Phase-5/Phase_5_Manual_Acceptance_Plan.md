# Phase 5 — planned manual acceptance

**Current programme status — 12 November 2026:** [Owner whole-phase acceptance is recorded](../acceptance/2026-11-12-phase-05-owner-closure.md); Phase 5 is closed. The planned checks below and historical delivery-workspace execution limits remain evidence references, not a reason to relabel missing individual observations Pass.


11 November 2026 (Africa/Lagos). **Not executed; no Pass results recorded.** This is the minimum phase-level coverage. Each package must provide runnable, commit-specific steps and actual result rows for its cases; further cases discovered during implementation are added rather than substituted for these. Follow the [implementation plan](Phase_5_Implementation_Plan.md).

## Safe setup and evidence

Use an isolated development/XAMPP installation with synthetic data, a backup and positively identified database. Apply the package's documented migrations once; check migration status and paired SQL against native MySQL constraints. Never run a destructive rollback against production or restore an old database over real business events. Use separate browser profiles/devices for permission and concurrency checks.

For every result record: case ID, reviewed commit, environment, role/grants, fixture IDs, steps actually taken, expected/observed values, Pass/Fail/Blocked, optional screenshot/request/evidence reference, defect, reviewer and Shop-local date. Do not fill a case Pass because a build succeeds or a previous package was accepted. Reuse unchanged valid observations and explicitly identify their source. Missing evidence stays unreported; an Owner package decision is recorded separately.

### Roles and access

- Active primary Owner; active Staff with `customer.view` + `debt.view` only.
- Collector: view grants + `debt.payment.record`; explicit allocator adds `debt.payment.allocate`.
- Corrector: view + `debt.adjust`; reverser: view + `debt.payment.reverse`. Replacement needs collection authority separately. Restricted Staff with only the old `debt.manage` must not obtain all these actions.
- Return operator with existing `sale.view` + `sale.return`; separate `sale.reverse` and profit permissions as needed for commercial correction. No implied general debt-correction power.
- Second Tenant and its own Customer/bills; inactive Staff; READ_ONLY and BLOCKED environments; Platform Admin without Shop membership. CLOSED is permanent: use disposable synthetic tenants only.

### Deterministic fixtures

Create through accepted application commands; do not write synthetic ledger rows directly. Keep each scenario independent/reset by creating fresh records, not deleting financial history.

| Fixture | Setup | Exact expected result |
|---|---|---|
| F1 — oldest bills | One Customer, bills A ₦1,000, B ₦600, C ₦400; distinct accepted posting order A→B→C, no initial receipts; due dates yesterday, today, absent | Total ₦2,000. A overdue only; B due today, C no due date. |
| F2 — partial automatic | Fresh F1, receive ₦1,200 | A allocation ₦1,000, B ₦200, C ₦0; A paid, B still ₦400, C ₦400; total ₦800. No revenue/stock change. |
| F3 — explicit | Fresh F1, receive ₦300 assigned B ₦100, C ₦200 | A ₦1,000, B ₦500, C ₦200; total ₦1,700. A remains untouched. |
| F4 — collected return | Sale 10 units at ₦100, all credit; available product stock/cost known. Cash collection ₦600 | Debt ₦400. Return 5 units worth ₦500: debt reduction ₦400, Cash refund ₦100, debt zero; stock increases by 5 at approved historic return cost. Remaining attributable paid capacity ₦500. |
| F5 — correction | Fresh credit bill ₦1,000, receive ₦200, factual correction decrease ₦50 with valid reason | Original credit remains ₦1,000; adjusted obligation ₦950, still to pay ₦750. Undo that adjustment restores ₦800; no Cash receipt is invented. |
| F6 — race | Fresh bill ₦500, two separately authorized sessions each prepare payment ₦400 before either confirms | At most one ₦400 succeeds; other must review the new ₦100 balance, not silently save ₦100 or another ₦400. |
| F7 — pagination | Enough synthetic events for at least three pages; mix credit, collection across bills, return, reversal and factual adjustment; retain a handwritten event/sign list | Opening + signed event changes = closing. Every page's running balances agree with the full list and current balance query. |

For Cash cases finalize a legitimate Opening Cash (zero is allowed). Repeat eligible Transfer-only cases without a baseline in a fresh Shop. Use Shop `Africa/Lagos` and a device in another timezone to verify due-date derivation. Use very long Customer/product/reference labels, duplicate/missing phone numbers, an inactive Customer with existing debt, no-debt Customer and permission-limited profiles.

## WP1 — foundation and live balances

| Case | Action | Expected result |
|---|---|---|
| P5-01 | Apply both migrations to a populated Phase 4 copy; inspect status, keys and data counts | Existing commercial evidence preserved; payment parent before allocation; equivalent SQL/native constraints and rollback/recovery instructions available. |
| P5-02 | Attempt invalid amount, duplicate UUID/reversal link, duplicate payment/bill allocation and cross-scope FK inserts in disposable transaction | Native schema rejects applicable row invariants; aggregate and same-Customer rules are documented service checks, not claimed aggregate CHECKs. |
| P5-03 | Inspect F1 list/detail and each source Sale | Exact ₦2,000 current balance and original bills; no balance edit or fake payment; pagination totals consistent. |
| P5-04 | Create an ordinary credit Return before any collection; refresh all balance views | Existing adjustment changes live debt once, original receipt snapshot unchanged. |
| P5-05 | Compare yesterday/today/no-date bills with different device timezone | Only positive overdue bills tagged overdue using Shop date; paid bills never overdue. |
| P5-06 | Read no-debt, inactive and duplicate-name Customers; search and paginate | Useful empty state; historical inactive debt remains available; no phone uniqueness or identity confusion. |
| P5-07 | Request another Tenant/Shop's bill/customer IDs through direct API and deep link | Denied without balance/private-field leakage; Tenant-owned identity does not grant cross-Shop debt reading. |
| P5-08 | Test Owner, reader, old debt.manage-only Staff, inactive membership and Admin | Only approved view authority; old broad grant not a shortcut; Admin has no Shop collection authority. |
| P5-09 | Inspect authorized READ_ONLY versus BLOCKED/CLOSED; compare private/no-private responses | Read policy enforced; no hidden phone/notes/cost/profit; no mutation controls enabled in WP1. |
| P5-10 | Use phone, desktop and keyboard; review WP1 lock/sign/API/permission contract | Readable cards/details, error/loading/empty states; all integration decisions resolved before collection implementation acceptance. |

## WP2 — collection and allocation

| Case | Action | Expected result |
|---|---|---|
| P5-11 | Receive F2 Cash with finalized baseline | ₦1,200 saved once; allocations A1000/B200; remaining ₦800; receipt/history links match. |
| P5-12 | Repeat F2 by Transfer in fresh Shop without Opening Cash | Valid Transfer collection succeeds; reference preserved if supplied; no Cash effect/baseline requirement. |
| P5-13 | Pay the complete remaining balance and pay a single bill partially | Paid/partly-paid states derive correctly; no interest or new Sale revenue/profit/stock movement. |
| P5-14 | Receive F3 using explicit allocation | Exact selected bills/amounts; total ₦1,700 remains; unselected A unchanged. |
| P5-15 | Submit sum mismatch, duplicate bill, other Customer/Shop bill or stale/settled bill | Reject with useful correction/review; no partial payment/allocation/Audit success. |
| P5-16 | Submit excess, zero, negative, malformed decimal, float/scientific/overprecision and boundary amount | Validation/range rules enforced with decimal strings; no wallet/remainder/rounding surprise. |
| P5-17 | Attempt Cash with no baseline or blocking active Cash control; compare Transfer | Cash rejected; eligible Transfer unaffected; existing Reconciliation ownership intact. |
| P5-18 | Run F6 in two sessions, then collection versus an eligible concurrent Return | Authoritative serialization; fresh balance checked; no over-allocation or stale automatic reassignment. |
| P5-19 | Double-activate Save, retry same UUID/body, then same UUID/different body | One collection/allocation set and one success Audit/change set; equivalent retry recovers; changed command conflicts. |
| P5-20 | Exercise not-sent and committed-but-lost-response outcomes | Original UUID retained, saved-status query resolves correctly; no second payment identity or misleading failure/success. |
| P5-21 | Force an allocation/Audit/change failure with package's documented development-only mechanism | Whole transaction rolls back, no orphan rows or false saved receipt; retry follows same identity. |
| P5-22 | Revoke collect/allocate authority after preview; expire session/access; try direct API | Confirmation rechecks current authority; collecting and choosing bills stay independent; READ_ONLY denies writes. |
| P5-23 | Collect on phone/desktop, reopen receipt/history, browser Print and app Print, disconnect | Plain review/balances and keyboard flow; private/internal evidence excluded; offline Save unavailable, no queued money operation. |

## WP3 — corrections and return integration

| Case | Action | Expected result |
|---|---|---|
| P5-24 | Undo normal Cash and Transfer collections | Equal-opposite linked evidence restores original allocations/balances once; Cash control applies to Cash compensation; originals unchanged. |
| P5-25 | Duplicate reversal, reverse already reversed/reversal record, or use conflicting UUID | At most one inverse per original; valid retry recovers; invalid chains rejected. |
| P5-26 | Correct wrong amount/channel/bill selection with linked reversal then replacement | Old and new evidence/correlation visible, correct final debt/channel effects; each step's true state shown. |
| P5-27 | Fail linked replacement validation/permission or inject failure between inverse and replacement | Binding WP1 atomic contract: neither half commits; original remains effective. A separate standalone undo and later collection honestly show their independent outcomes. |
| P5-28 | Apply valid factual increase and decrease (F5), then undo eligible adjustment | Signed exact balances, reasons/source links; no money/stock/revenue invented; original rows retained. |
| P5-29 | Attempt decrease below zero, unsupported write-off/interest, no reason or cross-scope inverse | Rejected atomically; cannot directly edit balance or generic-reverse a Sales-owned return adjustment. |
| P5-30 | Execute F4 with partial collection then Return | Debt400 reduction400/refund100/remaining0; stock/cost evidence correct; no COLLECTION_DEPENDENCY blanket block. |
| P5-31 | Fully repay fresh bill, then return eligible goods; test collection spanning two bills | Refund capacity includes only attributable effective collections; no double count across bills/channels; no debt below zero. |
| P5-32 | Undo collection that has funded F4's refund; inspect linked dependencies | Unsafe undo blocked with safe correction order; no duplicate refund; after eligible dependent correction, normal collection reversal can proceed. |
| P5-33 | Reverse eligible Return after collection; try exact cancellation of a collected original Sale | Return inverse restores its precise debt/refund/stock effects when eligible. Sale cancellation rejects unmet dependencies rather than erasing repayments. |
| P5-34 | Race collection/reversal/adjustment/Return confirmations against same bill | Compatible lock order, no silent deadlock effect or negative/excess balance; stale review rejected with recovery guidance. |
| P5-35 | Inject failure during adjustment/compensation/refund/Audit/change in isolated development | No partial stock/money/debt/evidence commit; original same-ID outcome recovered safely. |
| P5-36 | Check independent grants, READ_ONLY, diagnostics and original Phase 4 uncollected-return flows | Correction rights separate; legitimate Phase 5 increases/allocations not flagged as Phase 4 corruption; prior commercial behaviour preserved. |

## WP4 — statements and phase closure

| Case | Action | Expected result |
|---|---|---|
| P5-37 | Compare F7 statement to handwritten signed-event list and authoritative current debt | All source types/reversals included once; opening + changes = closing; links and amount direction understandable. |
| P5-38 | Filter date range excluding earlier sales/payments; check boundary dates and timezone | Correct opening debt before range, in-range effects and closing; defined Shop-local date boundaries converted consistently to UTC. |
| P5-39 | Traverse at least three pages, equal timestamps and backdated occurrence records | Stable accepted order/tie-break; running balance includes unseen preceding pages; no missing/duplicate events. |
| P5-40 | Add relevant collection/Return/correction between statement pages | Cursor/version policy demands refresh or preserves documented consistent anchor; never silently mixes balances. |
| P5-41 | Inspect payment spanning bills and both successful/failed replacement scenarios | One financial payment effect, detailed allocations do not double reduce; links show actual saved/reversed/replacement state. |
| P5-42 | Open another Tenant's statement/payment ID and inspect private fields/Print for restricted Staff | Scoped denials and redaction on APIs, screen, app Print and browser Print; Admin no Shop authority. |
| P5-43 | Read statement on phone/desktop and keyboard; test long/empty/failed/offline responses | Clear dates, descriptions and amount direction; loading/focus/errors accessible; no fake data or offline posting. |
| P5-44 | Complete day journey: credit sale → collection → Return → allowed correction → statement/Audit | Debt, stock and Cash/Transfer effects explainable; no collection revenue/profit inflation or double refund. |
| P5-45 | Inspect native payment/adjustment persistence, causal FKs, Audit correlation and server changes | Immutable linked evidence with all allocations; required success Audit/change atomic; reads emit no business effects. |
| P5-46 | Follow clean build/setup/migration and coherent release inventory instructions on target development environment | Locked local assets, exact source/generated match, no missing modules; migration register and operator recovery accurate. |
| P5-47 | Review defect disposition and complete manual coverage matrix | All applicable cases observed or transparently dispositioned; no hidden blocker/Phase 5 omission moved to Phase 6. |
| P5-48 | Owner reviews whole-phase result and release/rollback handover | Explicit final decision recorded against concrete commit; only then declare Phase 5 closed. Actual deployment decision remains explicit. |

## Lost-response and fault-verification delivery requirement

The WP2/WP3 handover must identify exact route, UUID/status read and safe native mechanism for each scenario. A development debugger break **after the owning transaction commits**, followed by the client's timeout and later status read, can prove a committed lost reply. Blocking a request before it reaches the server proves only not-sent behaviour. Do not confuse a break inside a transaction with an accepted payment, and do not add production delays, disable Audit globally or manually edit protected financial rows. If a required mechanism/environment is unavailable, record Blocked and resolve the affected gate before calling it verified. This plan adds no automated testing framework.
