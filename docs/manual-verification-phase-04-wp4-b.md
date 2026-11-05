# WP4-B manual/native verification

Status: **NOT EXECUTED — owner acceptance pending**. Record actual PASS/FAIL/BLOCKED, fixture, expected/actual result, actor grants, database version and commit. This is the backend/API Return increment. The operator Return screen and combined history/receipt UI belong to WP4-D. Do not expect a new Return button in this checkout.

## Checkout

Use a disposable development database clone/backup. Supported target is MySQL 8.4; XAMPP alone does not identify its database vendor/version. Record `SELECT VERSION()` and resolve a vendor mismatch before claiming schema acceptance.

```bat
git fetch origin
git switch codex/phase-4-wp4-b-returns-20261105
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
```

There is no new B migration. Apply WP4-A's `2026-11-05-100000_AmendPhaseFourSaleCompensationComposition` through Spark once; do not also execute raw SQL. Inspect migration status and SHOW CREATE TABLE sales. Retain A's preflight/downgrade protection and pending native verification. Contract scripts use source/doubles, not MySQL statements or HTTP.

## First Return through the authenticated API

1. Sign in as Owner in the development Shop. Record a test original Sale and confirm it; a Sale draft cannot be returned. Finalize Opening Cash before any actual cash refund. Copy the original posted Sale UUID from its receipt/API.
2. Open browser Developer Tools → Console on the same signed-in DawaTrack Shop page. Run the following **once**, replacing ORIGINAL_POSTED_SALE_UUID. These helpers use the application's existing session and CSRF client; no password/token is pasted or logged.

```javascript
window.wp4b = {
  api: await import('/assets/js/components/commercial-ui.js'),
  original: 'ORIGINAL_POSTED_SALE_UUID',
  returnId: crypto.randomUUID()
};
console.log('Keep this Return identity for recovery:', wp4b.returnId);
wp4b.eligible = await wp4b.api.get(`/api/v1/shop/sales/${wp4b.original}/returnable-lines`);
console.table(wp4b.eligible.items);
wp4b.record = await wp4b.api.send(`/api/v1/shop/sales/${wp4b.original}/returns`, 'POST', {public_id: wp4b.returnId});
```

3. Choose an original line UUID from the table and a positive quantity no greater than remaining_quantity. Replace ORIGINAL_LINE_UUID and the example quantity below. Each selected original line appears once; add more objects if needed. Prices and costs are derived by the server.

```javascript
wp4b.record = await wp4b.api.send(`/api/v1/shop/returns/${wp4b.returnId}`, 'PUT', {
  expected_updated_at: wp4b.record.updated_at,
  items: [{public_id: crypto.randomUUID(), original_sale_item_public_id: 'ORIGINAL_LINE_UUID', quantity: '1.0000'}]
});
wp4b.estimate = await wp4b.api.get(`/api/v1/shop/returns/${wp4b.returnId}/estimate`);
console.table(wp4b.estimate);
```

4. Read debt_reduction, refund_amount, cash_capacity and transfer_capacity. Choose actual cash_amount and transfer_amount adding to refund_amount within the respective capacities. The `0.00` values below are appropriate **only for a debt-only/zero-value Return**. Change them for a real refund, then review.

```javascript
wp4b.settlement = {expected_updated_at: wp4b.record.updated_at, cash_amount: '0.00', transfer_amount: '0.00'};
wp4b.review = await wp4b.api.send(`/api/v1/shop/returns/${wp4b.returnId}/preview`, 'POST', wp4b.settlement);
console.table(wp4b.review);
```

5. Check the reviewed value, debt reduction and actual refund before explicitly executing this financial mutation on test data:

```javascript
wp4b.receipt = await wp4b.api.send(`/api/v1/shop/returns/${wp4b.returnId}/post`, 'POST', {
  ...wp4b.settlement,
  review_fingerprint: wp4b.review.review_fingerprint,
  reason: 'Development verification: customer returned selected Products.'
});
console.log(wp4b.receipt);
```

6. Inspect the immutable Return, original line/movement UUIDs, actual REFUND children and RETURN_REDUCTION on the original Receivable. For authorized cost viewers, verify original COGS/provenance and signed margin effect. Verify stock through Products & stock and native evidence queries.
7. On timeout or response loss, **retain the same Return UUID** and retrieve `await wp4b.api.get('/api/v1/shop/returns/' + wp4b.returnId)`. POSTED means committed: do not create another Return. For DRAFT, reload its version and calculate/review again before an intentional retry. After a page reload, recover with the saved UUID; do not rerun initialization to guess the outcome.
8. Test listing using GET `/api/v1/shop/returns?status=DRAFT` or `?status=POSTED&original_sale_public_id=UUID`. Draft discard uses DELETE `/api/v1/shop/returns/UUID` with current expected_updated_at and a reason. Posted records reject update/discard.

## Native acceptance matrix

Every row starts **NOT EXECUTED**; engineering script output does not change that status.

| # | Scenario | Required observation | Result |
|---|---|---|---|
| 1 | Native schema and routes | Record PHP/MySQL vendor/version; ordered A migration succeeds, repeat no-op; routes resolve, CSRF/session and no-store/error envelope work | NOT EXECUTED |
| 2 | Draft lifecycle | Create same UUID twice → same record; save/reload/version; empty draft cannot preview/post; discard reasoned and effect-free; posted edit/delete denied | NOT EXECUTED |
| 3 | All original payment compositions | Independent Cash, Transfer, mixed, credit, Cash+credit, Transfer+credit, Cash+Transfer+credit originals; partial then full Returns reconcile exactly | NOT EXECUTED |
| 4 | Debt-first examples | Original 20,000 / Cash 5,000 / Transfer 5,000 / credit 10,000: independent Return 4,000 reduces same debt to 6,000, no refund; independent Return 12,000 reduces debt 10,000 and refunds 2,000 | NOT EXECUTED |
| 5 | Quantity/value/COGS | Fractional/repeated Product lines, rounding-sensitive fractions, final full exact original cents; over-return, duplicate/wrong original lines and invalid decimals denied | NOT EXECUTED |
| 6 | Historical attribution | Current price changes/inactive Product or Customer do not alter Return original identity/price/cost; no client-supplied price/cost accepted | NOT EXECUTED |
| 7 | Refund caps and zero value | Per-channel refund never exceeds effective original receipt; split sum exact; zero-valued Return still restores selected stock but creates no fake payment/adjustment | NOT EXECUTED |
| 8 | Stock arithmetic/provenance | Weighted incoming average at original cost; unknown mix stays provisional through later positive-stock Intake; depletion + known fresh basis recovers; earlier receipts retain historical classification | NOT EXECUTED |
| 9 | Cash/access | Cash refund without baseline denied with no effects; pure Transfer/debt-only works without Cash baseline; READ_ONLY/BLOCKED/CLOSED/revoked membership enforced | NOT EXECUTED |
| 10 | Permissions/privacy | sale.return required independently; sale.reverse alone insufficient; without sale.profit.view no cost/COGS/margin in draft/receipt/API; no raw Customer contacts or foreign scope data | NOT EXECUTED |
| 11 | Preview invalidation | Other session consumes quantity/debt/channel capacity or edits same draft; old version/review rejected or new invalid settlement reported; no partial effects | NOT EXECUTED |
| 12 | Real concurrency | Competing Returns/root Sale, same UUID, different Product orders and Intake concurrency serialize safely; no excess quantities/refunds, duplicate stock/money/debt or partial deadlock result | NOT EXECUTED |
| 13 | Fault rollback | Disposable clone: inject failures at stock movement/position/item/header/payment/adjustment/Audit/change publication/commit; all effects absent on failure; clean retry succeeds | NOT EXECUTED |
| 14 | Uncertain response | Lose post response after commit, retrieve same Return identity; POSTED unchanged on retry; uncommitted DRAFT recovered with new review, no blind new UUID | NOT EXECUTED |
| 15 | Evidence and refresh | One reasoned aggregate Audit, same original Receivable, NULL Return snapshots, movement/source links; atomic original/Return/movement/position/Receivable/Customer change publication; no draft financial refresh | NOT EXECUTED |
| 16 | Integrated regressions | Accepted WP1–WP3 Cash/Customer/draft/posting flows plus Product/Intake/manual reversal, original/subsequent Sale cost provenance, scope/privacy and active change generation still work | NOT EXECUTED |

Run existing scoped `sales:diagnose` and `inventory:diagnose` following their CLI help. Read-only anomaly samples support investigation; zero findings do not certify the native checks. Complete compensation-aware diagnostics/UI and integrated Phase 4 closure remain WP4-D. Record observed defects and rerun affected checks after fixes. WP4-B and Phase 4 acceptance are not inferred from contract approval.
