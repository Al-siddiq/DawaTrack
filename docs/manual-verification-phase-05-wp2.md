# Phase 5 WP2 — receive and allocate customer payments

12 November 2026 (Africa/Lagos). WP1 is accepted. **WP2 is delivered for your manual verification; not yet accepted.** No correction/undo/statement workflow is included yet.

## Update and open the real application

Use your backed-up development database and normal configured Shop address, not a reference gallery. Avoid real business money while verifying synthetic scenarios.

```bash
git fetch origin
git switch codex/phase-5-wp2-debt-collection-20261112
composer install
npm ci
npm run build
php spark migrate:status
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP2_Artifact_Manifest.json
```

WP2 adds **no migration**. Both WP1 debt-payment migrations must already be applied; if missing, apply `php spark migrate --namespace App` once on the identified development copy and check status again. Do not run raw SQL in addition. Windows Command Prompt is supported. If the inventory explicitly reports LINE_ENDINGS_ONLY, run the same check with `--fix-line-endings`, then repeat normal checking. Do not regenerate the inventory to approve unknown content.

Hard reload/close old trading tabs so backend and assets belong to the same release. Sign in as Owner: **Customers → Money customers owe → View bills → Receive payment**. **View customer payments** opens paginated history. No extra bottom-navigation destination is added.

## First normal payment

1. Use a fresh test Customer and three all-credit Sales A ₦1,000, B ₦600, C ₦400, accepted in that order. The total must be ₦2,000. Do not reuse a fixture whose bill A was already returned during WP1.
2. Finalize Starting cash for a Cash collection (zero is allowed).
3. Choose Receive payment, amount **1200.00**, Cash, **Pay the oldest bills first**.
4. Select Review payment. Expect A receives ₦1,000, B ₦200, C untouched; Customer total changes from ₦2,000 to ₦800.
5. Select Save payment once. The saved receipt must show Cash ₦1,200, both allocations, total before ₦2,000/after ₦800 and a separately labelled live balance.
6. Open history, reopen/print the receipt and refresh Customer bills. A is Paid; B is Partly paid with ₦400; C is Unpaid with ₦400. No extra Sale/revenue/profit/stock movement is created.

For explicit selection use **fresh independent** A/B/C bills, receive ₦300 assigned B ₦100 and C ₦200. A stays ₦1,000; B ₦500; C ₦200; Customer total ₦1,700. Assign nothing to A. The form shows the exact amount left to assign (or how much is over-assigned); the server checks it again at review/confirmation.

Two channels require two separate payment records. Transfer-only collection does not require Starting cash. An inactive Customer can repay an existing bill without being reactivated or given new credit.

## Staff access

Owner → Staff grants independently:

- customer.view + debt.view: balances, history and receipts only.
- Add debt.payment.record: receive payment using oldest bills first.
- Add debt.payment.allocate: additionally choose specific bills.

customer.private does not grant collection; debt.manage is not a substitute; adjustment/reversal grants alone do not receive payments. READ_ONLY cannot preview/save; authorized history remains available. BLOCKED/CLOSED/inactive membership and wrong Tenant/Shop deny per existing policy. Platform Admin gets no ordinary Shop collection authority.

## Thirteen native cases

| Case | Manual action | Expected result |
|---|---|---|
| P5-11 | Fresh fixture; receive ₦1,200 Cash with finalized Starting cash | One collection, allocations A1000/B200, balance800; receipt/history/current bills agree. |
| P5-12 | Fresh fixture; receive Transfer without Starting cash; include/omit bounded bank reference | Transfer succeeds with no physical Cash effect. Reference retained when supplied. No Cash-only prerequisite. |
| P5-13 | Partially pay a bill, then fully settle remaining debt; inspect original Sale receipts and stock | Derived partly-paid/paid/overdue states and exact values. Collection creates no new Sale, revenue, profit or inventory movement. Earlier payment receipt retains its recorded before/after even when current balance later changes. |
| P5-14 | Explicit ₦300 split B100/C200; load more bill pages when needed | Correct selected bills only, remaining1700, running remainder exactly0; per-command maximum200 bills without silent truncation. |
| P5-15 | Sum mismatch, duplicate bill, another Customer/Shop/Tenant bill ID, settled/stale selection | Clear rejection/review correction; no partial row set or success Audit/change. Use direct authenticated JSON for duplicate/foreign IDs the UI cannot select. |
| P5-16 | Excess/no-balance/zero/negative/comma/float/scientific/overprecision/boundary amount | Exact decimal-string validation and range/no-advance rules. No wallet or spare balance is created. Inputs remain available for correction. |
| P5-17 | Try Cash with no baseline or blocking active Cash control; compare Transfer | Cash blocked with Starting cash/count guidance; eligible Transfer succeeds. Do not fabricate an active Reconciliation just to mark a case passed; it is a future valid integration state or requires a safely controlled developer fixture. |
| P5-18 | Two browser sessions each preview ₦400 against one fresh ₦500 bill before either saves; confirm both | At most one accepts400. The other gets refresh/review/excess guidance against remaining100, never silently records100 or another400. Also check a concurrent eligible credit Return versus collection; only valid serialized effects survive. A Return after effective collection remains deliberately blocked until WP3. |
| P5-19 | Double-activate Save; repeat exact accepted POST/UUID; then same UUID with different amount/channel/mode/selection/note | One business effect and success evidence set only. Equivalent retry recovers the receipt even though its original preview is old. Different command conflicts. |
| P5-20 | Exercise not-sent and committed-but-lost-reply scenarios below; reload tab with unknown result; switch accounts | Same UUID retained; only IDs in namespaced tab marker, no automatic POST/outbox. Saved-status GET recovers; a404 is not conclusive failure. Continue only with same reference. Another account's marker is not resumed. |
| P5-21 | Developer performs the isolated three-stage temporary fault checks below | Every failed transaction leaves no payment/allocation/success Audit/change residue. Restore source and validate package inventory before reusing same UUID. |
| P5-22 | Revoke collect/allocate after preview, expire access/session, direct-post with missing grants; compare view-only/READ_ONLY | Confirmation rechecks current authority. All grants stay independent. Unknown outcome/status remains safe if access disappears. |
| P5-23 | Phone/desktop/keyboard/long labels; loading/errors/offline; receipt/history paging; app Print and browser Print | Readable shared UI, no double save, clear correction guidance and no queued money. Private/internal/current-balance sections absent from customer print; saved amount/reference/allocations remain. No private phone/notes/cost/profit in JSON/receipt. |

## P5-20 — distinguish not sent from committed but no reply

Use only an isolated synthetic environment. Keep the original public payment UUID from the request or displayed unknown-status reference.

**Not sent:** prepare/review, then block the exact POST `/api/v1/shop/debt-payments` URL in browser request-blocking tools before Save (not `/preview`, not the GET status URL). Save must report unknown, not success. Remove the block; Check saved status may return404. That means absent at that read, not that a delayed request is impossible. Continue with this same payment reference, review and save; do not start a different UUID.

**Committed but lost reply:** with native PHP debugging, place a breakpoint in `DebtPaymentController::post()` on the line after `$id = $this->service()->post(...)` returns, **before** the receipt query/HTTP response. At this point the owning transaction has committed. Let the client timeout, then resume the server request and use Check saved status (do not leave the server paused while status waits for session locks). Expect the same saved receipt, exactly one payment/evidence set and no automatic retry. A breakpoint inside DebtPaymentService before transaction commit does not prove accepted money.

Reload while unknown. The marker stores only payment/Customer UUIDs in host/user/Shop-scoped sessionStorage. A receipt found by GET clears it. If404, continuing the same reference can re-enter the original details and review; existing server identity guards prevent a second effect even if an earlier request later finishes. If storage is disabled, the warning explains that the reference is held only in memory: keep the page open/copy the reference and resolve status before recording another payment. Do not clear browser storage as a way to dismiss an uncertain payment.

## P5-21 — controlled native rollback checks (developer aid)

No fault hook or testing endpoint is shipped. On an isolated development copy with all other operators stopped, a developer can temporarily insert `throw new \RuntimeException('WP2 development rollback check');` at **one** of these source points in DebtPaymentService, exercise a reviewed synthetic Save, then remove the line before the next scenario:

1. After an allocation INSERT (before Audit): payment/allocation writes must roll back.
2. After parent Audit append completes (before allocation child Audit/publication): business and parent success evidence must roll back.
3. Immediately after `$this->changes->publish($commands)` (before returning from the owning callback): payment, allocations, all success Audit and change rows must roll back, including generation-bound publication.

Capture before/after scoped counts/public IDs for debt_payments, debt_payment_allocations, audit_events and sync_changes. Check the failed UUID/status and request correlation using the developer database viewer/HTTP response reference. Do not delete/update protected evidence, weaken Audit permissions, alter the migration ledger or perform these faults on production. Temporary source modifications deliberately fail the inventory; remove them, rerun build/inventory verification and only then retry the same UUID through the normal flow. These are manual fault observations, not an automated suite or claims already executed here.

## Results and package boundary

[WP2 results](Phase-5/Phase_5_WP2_Manual_Results.csv) start Pending for P5-11–P5-23. Record candidate commit, actual environment/device, observations, result and defect/evidence where available. Prior WP1 acceptance is preserved; it does not pre-accept collection. The delivery report distinguishes build/source/mock evidence from native PHP/MySQL/HTTP/concurrency/rollback/browser results.

Normal return/cancellation on a bill with an effective collection remains guarded in WP2; payment undo/correction and repayment-aware Returns are WP3, statements/phase closure WP4. This package must be accepted explicitly before WP3 starts. Merge/deployment remains a separate explicit operational decision.
