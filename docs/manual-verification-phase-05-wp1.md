# Phase 5 WP1 — manual verification and handover

> **Current decision, 12 November 2026:** Owner accepted WP1 and authorized WP2. [Acceptance](acceptance/2026-11-12-phase-05-wp1-owner-acceptance.md) supersedes this guide's delivery-time Pending wording; detailed individual case observations remain unreported.

11 November 2026 (Africa/Lagos). **Implementation delivered for your verification; not yet Owner-accepted.** This package adds live read-only customer balances, schema and integration contracts. It does not let you receive/undo payments or correct bill amounts yet.

## 1. Update your development checkout

Use a development database/copy with a backup. Do not test failures or destructive rollback on your live business database.

```bash
git fetch origin
git switch codex/phase-5-wp1-debt-foundation-20261111
composer install
npm ci
npm run build
php spark migrate:status
php spark migrate --namespace App
php spark migrate:status
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP1_Artifact_Manifest.json
```

These commands work in Windows Command Prompt; no `sh` is required. The new App migrations are `2026-11-11-190000` and `2026-11-11-190100`. Both new tables must be present. Do not run paired raw SQL as well as migrations. A successful build ends `Frontend assets built.`; historical Sass/Bootstrap deprecation warnings alone are not a failure. If you use an archive, use the reviewed clean source archive to preserve canonical line endings and follow the existing release guide for Composer/environment/assets.

### If a clean Windows checkout fails the inventory check

Pull the WP1 follow-up, then run the same check with `--fix-line-endings`. It restores only CRLF files whose LF bytes exactly match the recorded hash; real content changes still fail without rewriting any files. Do not regenerate the inventory to make this error disappear.

```bash
git pull --ff-only
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP1_Artifact_Manifest.json --fix-line-endings
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP1_Artifact_Manifest.json
```

See [confirmed Windows byte diagnosis and repair](Phase-5/Phase_5_WP1_Windows_Inventory_Followup.md). No Node upgrade, database reset or extra migration is needed for this line-ending issue.

Use your normal configured Shop address, sign in as Owner and hard reload. Choose **Customers → Money customers owe**. The bottom navigation remains unchanged. From a selected Customer record, the same button opens that person's bills. Staff needs **View customer records** plus **View money customers owe**, granted by Owner in Staff settings. Merely granting the legacy debt permission is insufficient.

A setup message instead of balances means the application update is incomplete. Check migration status and both new tables; do not read the message as a zero balance. Existing Customer records, Sales/Returns and original receipts must still work after migration.

## 2. Create a small fixture using the existing app

Use a new Customer called **WP1 balance review**, and a test Product with enough stock (for example 100 units, cost ₦60, selling price ₦100). Use accepted Stock/Sale screens, not direct inserts into protected evidence. A finalized zero Starting cash baseline is permitted but all-credit Sales themselves have no Cash receipt.

Create three separate all-credit Sales for this Customer, in this order:

| Bill | Quantity × unit price | Credit | Due date |
|---|---:|---:|---|
| A | 10 × ₦100 | ₦1,000 | Yesterday according to the Shop date |
| B | 6 × ₦100 | ₦600 | Today according to the Shop date |
| C | 4 × ₦100 | ₦400 | Leave empty |

Save each posted Sale reference. Initial debt is exactly **₦2,000** and stock is 80 if it started at100. Only A is overdue. A balance screen shows the Shop date used; changing device timezone must not change which bill is overdue.

Also create a Customer with no credit Sales. Keep a separate inactive test Customer with existing credit by making them inactive **after** recording a legitimate credit Sale. Duplicate display names or shared/missing phone numbers are allowed.

## 3. Run these ten checks

| Case | What to do | What should happen |
|---|---|---|
| P5-01 | Apply migrations to your populated development copy; inspect old receipts and new tables | Payment parent before allocations; original Customers/Sales/Returns unaffected; both migration entries recorded. No existing table recreated. |
| P5-02 | Review paired SQL/FKs/CHECKs; use a developer's disposable transaction for invalid rows if testing native constraints | Positive amount, channel, UUID, unique identities/payment-bill keys and same-scope FKs enforced. All invalid-row attempts roll back. Same-Customer/allocation totals/inverse rules are service invariants for later writers, not invented CHECK guarantees. |
| P5-03 | Open Money customers owe; search the fixture Customer; View bills; open A/B/C | Current total ₦2,000. Bills ₦1,000/₦600/₦400, unpaid; original amounts match source receipts. Filter/page totals cover matching records, not only visible page. No balance-edit or payment button. |
| P5-04 | Through the existing Return workflow return 2 units from A, with debt reduction ₦200/no refund; refresh balances and original Sale receipt | A still to pay ₦800; Customer total ₦1,800. Bill changes −₦200, payments ₦0; still 'Unpaid', not 'Partly paid'. Original Sale receipt still shows its historical ₦1,000 credit. Return remaining8 units if desired: A becomes Paid/zero, Customer total ₦1,000. Stock restores correctly. |
| P5-05 | Compare overdue filter before/after Return, B due today and C no date; repeat with device in another timezone | Only positive A is overdue; B today is not; C has no due date; paid A is no longer overdue. Authority follows Shop timezone/date. |
| P5-06 | Select All, Paid and Still to pay; search no-debt and inactive Customers; paginate with enough synthetic records | No-bill Customer is 'No unpaid bills', not fake repayment history. Inactive Customer debt remains visible with clear label. Detail total stays all-bill when only its bill list is filtered. Duplicate names remain separate records. |
| P5-07 | Try another Tenant/Shop's valid Customer/bill public UUID via debt API and deep link; alter Customer portion of a bill link | Other-Tenant identity and foreign-Shop bills return scoped404/denial. A same-Tenant Customer identity can remain visible, but its debt is only for the current Shop (possibly zero). A same-scope bill linked under the wrong Customer is not shown by the UI. |
| P5-08 | Test Owner, view-only Staff, customer.view-only, debt.manage-only, debt.view without customer.view, inactive Staff and Admin without Shop membership | Only approved current authority reads. No new Staff grants automatically appear. Unauthorized deep link gives a useful message and performs no debt fetch; direct HTTP remains denied. |
| P5-09 | Test authorized READ_ONLY versus BLOCKED/CLOSED (disposable tenant for permanent CLOSED); inspect debt JSON and display | READ_ONLY can read, never mutate; other modes denied by existing policy. No internal numeric IDs/phone/notes/cost/profit in debt response. Source Sale link appears only with sale.view. |
| P5-10 | Use phone/desktop, long labels, keyboard/back/deep links, loading/empty/error/refresh/offline; review the integration contracts | Shared look/icons and useful navigation; no overflowing values; focus after load; an old read never replaces newer data. Error/offline clears old live totals and offers safe refresh. No payment is queued. Contracts define locks, signs, future commands and manual boundaries before WP2. |

To reach Paid using WP1 UI, use an eligible full credit Return. Native 'Partly paid' and payment-inverse creation require WP2/WP3 commands, which are not yet exposed. The query/UI logic supports these states and limited engineering probes cover their shape/signs; do not insert fake posted payments to claim native workflow acceptance. They remain explicit later-package checks P5-13/P5-24.

For deeper list/pagination checks, add enough synthetic Customers/bills through accepted workflows. Different request responses each represent one live snapshot; a separately refreshed page may legitimately show newer activity. Full statement versioned paging is WP4.

## 4. API inspection (optional developer aid)

While authenticated on the correct Shop host, inspect these GET responses in browser network tools or by opening their same-origin URL:

- `/api/v1/shop/debts?status=ALL&page=1&per_page=20`
- `/api/v1/shop/customers/ACTUAL-CUSTOMER-UUID/debt?status=ALL`
- `/api/v1/shop/debt-bills/ACTUAL-RECEIVABLE-UUID`

Replace placeholders with actual public IDs from the returned bill list. A Receivable UUID is the bill reference, not the Sale reference. Routes have no POST/PUT/DELETE debt implementation in WP1. Test unknown/protected query fields (for example a client tenant_id) and malformed filters/pages: they must not establish scope or silently alter results.

For P5-02, a developer can inspect `SHOW CREATE TABLE` and `information_schema` on the two new tables. Native invalid-row attempts should target an isolated copy and use transactions that always roll back; never commit a fabricated payment just to create a screen fixture. Native MySQL version/FK/row CHECK behaviour is an actual verification requirement, not satisfied by a PHP grammar parser.

## 5. Results and decision

Record observations in [WP1 result register](Phase-5/Phase_5_WP1_Manual_Results.csv): reviewed commit, environment, device/browser, steps/observations, result, evidence/defect where available. This register starts Pending. Your package-level manual acceptance remains the progression gate; no earlier UI/plan acceptance is treated as acceptance of this new code.

Known engineering limits: native PHP, MySQL and authenticated application/browser verification were unavailable here. Production build, PHP grammar, import/source parity and limited mocked host/projection probes are engineering evidence only. No deployment, native migration, concurrency/rollback or actual device pass is claimed.

After you accept WP1, WP2 implements real collection/allocation. Until then, no WP2–WP4 implementation is authorized by this delivery.
