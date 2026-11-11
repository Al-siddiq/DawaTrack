# Safe fixtures and difficult manual cases

Use a dedicated non-production Shop and a working test mail setup. Do not alter live plans, close a real account, inject SQL, change production credentials or capture real customer payments for UI verification. Existing domain/manual checklists remain authoritative; these examples are safe starting points, not replacement business rules.

## Accounts and access

1. Use an authorized test Admin to add a disposable business/shop through `/platform-admin`. Complete its native email security check. Sign in to the new Shop as its Owner and complete mandatory password change.
2. In Staff, add separate active test users. Reader: inventory.view, customer.view, sale.view only. Cashier: sale.view + sale.record, with price override/private customer/profit absent. Returns operator: the supported sale.view/sale.return/sale.reverse grants. Add other supported grants only for the case being exercised. Do not infer authority from a visible button; the server is authoritative.
3. Use separate browser profiles/incognito sessions for simultaneous roles. Prepare another disposable Shop and a saved sale there. In the first Shop, open `#sale/<other-shop-sale-reference>`; expect denial/not-found without foreign data, never an approved cross-shop view.
4. For READ_ONLY/BLOCKED/CLOSED cases, use supported Admin account/plan operations on disposable fixtures. CLOSED is permanent: create a separate disposable account rather than expecting to reopen it. Do not mark a state Not applicable merely because setup is inconvenient.

## Deterministic commercial example

Use a test product `UI4 Demo Tablet`, unit `tablet`, price ₦100.00. Record starting stock of 20 at ₦60.00 per unit through the existing stock flow. Record/finalize starting cash once; zero is permitted. Add a named test customer. No real money should change hands.

- Sell 4 tablets: total ₦400.00; cash ₦100.00, transfer ₦200.00, unpaid ₦100.00. Expected recorded stock after the sale: 16. Review must show the authoritative exact amounts before confirming.
- Return 1 tablet: value ₦100.00. It first reduces that sale's remaining unpaid amount; refund is zero. Recorded stock becomes 17.
- Return another 1 tablet: with unpaid amount now reduced, the review should require ₦100.00 refund subject to original channel capacity. Use cash ₦100.00 if the server review permits it. Recorded stock becomes 18. Do not override a different server result; record it as a defect/investigation.
- The original receipt still describes the originally unpaid amount, not a fabricated current customer balance. Return/cancellation evidence describes subsequent changes. Collections/statements are not added by this UI.
- Trying to cancel a sale with effective returns should exercise its existing dependency denial. Use a fresh independent sale/product with no later stock activity to exercise an eligible cancellation and replacement; do not assume the first example remains reversible after dependent activity.

Also prepare an inactive product/customer, an empty list, several unfinished baskets, long names and a product sold before its cost is established. The latter must show provisional profit only to authorized users. Keep exact IDs/time/amount evidence; do not aggregate quantities with different units.

## Lost response: confirmed NOT saved

Use browser DevTools Request blocking in the test Shop. Prepare a saved unfinished sale and note its reference. Block only its exact POST URL `/api/v1/shop/sales/<reference>/post` before confirming. The browser cannot deliver that write. Remove the block, then use Check saved status. Expect the original draft, not a new UUID or an automatic POST. Do not use this case as proof of a transaction that committed.

## Lost response: server committed, reply not received

This needs a controlled development debugger/proxy, not a production code patch. With a PHP debugger in the test checkout, break on the `return $this->ok(...)` statement immediately **after** `SalePostingService->post(...)` returns in `app/Controllers/Api/SaleController.php::post`. This point is after the service has returned from its transaction. Do not break inside the transaction and claim it committed.

Confirm the test sale, pause at that return, and allow the browser's current request timeout to elapse. Record the persistent unknown state. Resume the debugger so the server can finish; then select Check saved status. Expect GET of the original sale reference and the saved receipt, one effective sale/stock/payment set, no automatically generated new operation. Inspect existing authorized history/diagnostics as the Phase 4 checklist requires. Remove breakpoints afterward.

For returns use the equivalent point after the returns service returns in `app/Controllers/Api/ReturnController.php::post`. For starting cash, the existing same-state refresh reads the attempt/baseline. A request abort alone is never proof the server rolled back. If no debugger/proxy is available, mark this specific case Pending with the missing environment, not Pass or Not applicable. UI3-A's mocked-handler checks are supplementary only.

## Access transition, stale preview and overlays

Use two profiles: Owner changes a Staff grant or Admin changes test access while the Staff tab stays open; return focus/reload and exercise a supported operation. For stale review, save/change the same draft through a second permitted session before final confirmation in the first; expect the existing version/fingerprint guard and retained editable context. Close any active dialog before navigating; test the error-summary reveal with both native details and a shared accordion. No nested modal should be required.

## Evidence hygiene

Record browser/OS/device, candidate commit, entry URL, role/grants, fixture references, expected/actual state and defect. Redact passwords/email codes/private phone/notes/DNS setup values. Reference valid previous observations for unchanged paths instead of rerunning everything. Keep real identities, invoices and live accounts out of the test pack.
