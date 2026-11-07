# WP4-C manual/native verification

Status: **NOT EXECUTED — owner acceptance pending.** Record PASS/FAIL/BLOCKED, fixtures, expected/actual outcome and evidence. This is the correction backend/API increment; no new reversal or Return screen is claimed until WP4-D. Use development data and a backup/disposable clone.

## Checkout and checks

Record PHP version, actual `SELECT VERSION()`, commit, Shop scope and Staff grants. Supported schema target is MySQL 8.4; XAMPP alone does not establish vendor compatibility. Apply the ordered A migration once through Spark, not also raw SQL. Never disable CHECKs/FKs or delete compensation evidence to force an A downgrade.

```bat
git fetch origin
git switch codex/phase-4-wp4-c-corrections-20261106
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
```

C adds no new migration/dependencies/frontend assets. Expect A's 2026-11-05-100000 migration already applied or applied once. Contract scripts describe their limits; passing doubles does not establish MySQL/HTTP/concurrency acceptance.

## First correction through the authenticated API

On the signed-in development Shop page, use browser Developer Tools → Console and the existing application HTTP helpers. These use the current session/CSRF client; do not paste credentials/tokens. Pick an ordinary posted Sale or Return UUID. Start with an operation having no later dependent stock activity. Finalize Opening Cash before any actual cash effect.

1. Initialize **once**, replacing SOURCE_POSTED_SALE_OR_RETURN_UUID. Keep both command UUIDs for recovery before any mutation.

```javascript
window.wp4c = {
  api: await import('/assets/js/components/commercial-ui.js'),
  source: 'SOURCE_POSTED_SALE_OR_RETURN_UUID',
  compensationId: crypto.randomUUID(),
  replacementId: crypto.randomUUID()
};
console.log({source: wp4c.source, compensationId: wp4c.compensationId, replacementId: wp4c.replacementId});
wp4c.review = await wp4c.api.send(`/api/v1/shop/sales/${wp4c.source}/reversal-preview`, 'POST', {});
console.table(wp4c.review);
```

2. Inspect money_direction (REFUND for original Sale; RECEIPT for Return), Cash/Transfer, debt_direction (CANCEL/RESTORE), debt_amount and stock line count. A dependency error is deliberate protection: use its Return/movement references to investigate. Do not bypass it using manual stock/money edits.
3. Only after reviewing the exact effects, explicitly execute this mutation on test data:

```javascript
wp4c.receipt = await wp4c.api.send(`/api/v1/shop/sales/${wp4c.source}/reverse`, 'POST', {
  public_id: wp4c.compensationId,
  review_fingerprint: wp4c.review.review_fingerprint,
  reason: 'Development verification: compensate the selected operation.'
});
console.log(wp4c.receipt);
```

4. Verify operation_type COMPENSATION, opposite kind, NULL new-Sale snapshots, root and source public links, immediate original line links, inverse movement/payment/adjustment links and the same Receivable. Original evidence must be unchanged. Check stock qty/average/state, advancing version/pointer, Audit and refresh evidence on the native database.
5. If the response is lost, retrieve source state and then the known compensation. Never generate another UUID to guess whether the command committed.

```javascript
wp4c.state = await wp4c.api.get(`/api/v1/shop/sales/${wp4c.source}/correction-state`);
console.log(wp4c.state);
// Run this only when reversal_public_id is non-null:
wp4c.receipt = await wp4c.api.get(`/api/v1/shop/commercial-compensations/${wp4c.state.reversal_public_id}`);
```

If source has no reversal, investigate the failure and preview again before an intentional same-identity retry. A committed same-UUID retry creates no second effects; a different compensation UUID is denied. After a page reload, reconstruct the helper with the saved source/command identities rather than issuing a new operation.
6. From a successfully reversed ordinary source, prepare one linked replacement. This writes a reasoned draft only; it does not redo financial/stock effects.

```javascript
wp4c.replacement = await wp4c.api.send(`/api/v1/shop/sales/${wp4c.source}/replacement`, 'POST', {
  public_id: wp4c.replacementId,
  reason: 'Development verification: prepare a corrected replacement.'
});
console.log(wp4c.replacement);
```

For Sale replacement, refresh Record Sale drafts and open the linked UUID, or use ordinary Sale draft/preview/post APIs. Verify current Product price, normal price permissions and a separate explicit confirmation. For Return replacement, use the [B API walkthrough](manual-verification-phase-04-wp4-b.md) with this existing draft UUID (do not create another Return). Save/review/post separately. Source correction-state recovers a lost replacement response and exposes its kind/status; a posted retry returns the linked record without reposting.

## Native acceptance matrix

| # | Scenario | Required observation | Result |
|---|---|---|---|
| 1 | Original Sale compositions | Independently reverse Cash, Transfer, mixed paid, full credit and all three partial-credit combinations. Exact inverse payment channels/amounts; credit cancelled once; NULL compensation snapshots; no new Receivable | NOT EXECUTED |
| 2 | Return compositions | Reverse debt-only, paid Cash/Transfer/mixed and debt+refund Returns. Exact refund receipts and linked positive REVERSAL restoring the SAME original Receivable | NOT EXECUTED |
| 3 | Credit example | Original 20,000 / Cash 5,000 / Transfer 5,000 / credit 10,000; Return 12,000 reduces debt 10,000/refunds Cash 2,000; compensation receives Cash 2,000/restores same debt 10,000/removes returned stock | NOT EXECUTED |
| 4 | Immutable graph | Original header/items/payments/adjustments unchanged; fresh opposite posted header, correct root and immediate source; compensation items → source line movement → inverse REVERSAL with sale_item_id NULL | NOT EXECUTED |
| 5 | Effective Returns | Original Sale reversal denied with active Return reference. Reverse eligible Returns in dependency order, then reverse root Sale without duplicate refund/debt effects | NOT EXECUTED |
| 6 | Latest effective suffix | Later unreversed Intake/Sale/manual/Return blocks with scoped evidence references. Exact later reversed pairs accepted only if quantity/average/cost-state restored; pointer/projection drift blocks | NOT EXECUTED |
| 7 | Physical exactness | Repeated Product lines, multiple Products, fractional quantities, retained/unknown/negative stock. Exact before-state restored, versions incremented and pointer advanced; all Products atomic | NOT EXECUTED |
| 8 | Payment/credit integrity | Manipulated source totals/line/cost/customer/directions or unexpected source adjustments block. Future collection allocations block until collection-owned integration. No fake CREDIT/new debt | NOT EXECUTED |
| 9 | Cash scope | Missing Opening Cash or active Cash scope blocks any actual Cash refund/receipt before effects. Pure Transfer/debt-only compensation works without Cash baseline | NOT EXECUTED |
| 10 | Review and duplicates | Change current stock/settlement after preview → stale review or eligibility denial. Same committed UUID returns existing result; different UUID denied; reversal-of-compensation rejected | NOT EXECUTED |
| 11 | Replacement Sale | Reversed source required; current prices, no financial effects on preparation; separate normal posting validates active Product/Customer/Cash/composition/price grants. Source link refreshes | NOT EXECUTED |
| 12 | Replacement Return | Same root/original lines, current allocation revalidated at preparation and post; a compensated root rejects replacement. One direct live link; same-identity retrieval, different identity denied | NOT EXECUTED |
| 13 | Permissions/privacy | sale.return alone cannot reverse; sale.reverse alone cannot post replacement Sale/Return. FULL/current membership required. Without sale.profit.view inspect all correction responses for cost/COGS/margin leakage | NOT EXECUTED |
| 14 | Native HTTP/scope | CSRF/session expiry, malformed/extra input, foreign Tenant/Shop/source UUIDs, no-store and safe errors; READ_ONLY/BLOCKED/CLOSED enforced | NOT EXECUTED |
| 15 | Native concurrency | Concurrent same-source reversals, reverse vs Return post, two replacements, stock posting vs reversal, multi-Product order. One valid outcome/no partial deadlock effects; retain UUID and recover state | NOT EXECUTED |
| 16 | Rollback injection | Disposable clone: fail each header/line/stock/position/payment/adjustment/Audit/change/commit boundary for both directions; no partial effects. Fail replacement preparation too; clean retry works | NOT EXECUTED |
| 17 | Network recovery | Lose response after commit; source correction-state + compensation/replacement retrieval resolves actual result, no blind new UUID or repost. Test uncommitted failure separately | NOT EXECUTED |
| 18 | Audit/refresh | One reasoned aggregate correction Audit; source/root/new Sale, movement, logical position, existing credit/Customer refresh atomic. Replacement draft has Audit but no financial refresh | NOT EXECUTED |
| 19 | Diagnostics/regressions | Scoped inventory:diagnose recognizes indirect compensation item coverage; sales:diagnose retains normal evidence boundary. Repeat WP1–B, Product/Intake/manual reversal, historical cost provenance and change-generation checks | NOT EXECUTED |

Record native findings, fix defects and rerun affected checks. Current diagnostic samples do not certify completion. Full integrated UI, commercial compensation diagnostics, phone/desktop/keyboard/print/accessibility and final P4 requirement matrix remain WP4-D. No native checklist execution or whole-phase acceptance is inferred from approval to proceed.
