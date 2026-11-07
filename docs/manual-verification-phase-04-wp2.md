# Phase 4 WP2 owner manual verification

Status: **UNEXECUTED / acceptance pending**. Mark each case PASS / FAIL / BLOCKED with observed result. Record tested commit, PHP/MySQL versions, verified hostname and device/browser. The implementer's source/build checks do not prefill owner results.

## Setup

Use backed-up disposable MySQL 8.4 development data with accepted WP1, two Tenants/Shops A/B and their Owners, and independent Staff grants. Apply the seven WP2 App migrations once (not raw SQL too), verify status/SHOW CREATE TABLE against paired SQL, then repeat migrate. Build frontend assets. Starting fresh follows Settings → Shield → App order. Create active Products available in A with prices 100.00 and 250.00, one inactive Product and a Customer in B. Take before snapshots/counts of positions/movements, payments, Receivables, adjustments, reconciliation rows, Audit and sync changes. Use separate browser profiles for races, never shared credentials/sessions.

```bash
git fetch origin
git switch codex/phase-4-wp2-customers-drafts-20261103
composer install
npm ci
npm run build
php spark migrate:status
php spark migrate --namespace App
php spark migrate:status
php tests/contracts/wp1.php
php tests/contracts/wp2.php
```

Sign in as Owner A. Open Customers and Sale drafts. Contract scripts are optional engineering support, not substitutes for manual acceptance.

## Owner checklist

| ID | Actions | Expected result | Result |
|---|---|---|---|
| C1 | Inspect all six new tables, user/scoped/self FKs, unique reversal/line/Sale links and late stock_movements.sale_item FK; repeat migration | Complete approved definitions, no pending migrations, no Phase 5/10 tables | UNEXECUTED |
| C2 | Create Customer without phone; create two Customers sharing +234 (801) 234-5678; search/view/edit names/notes/phone | Valid identities; phone normalized, optional/shared allowed; no stored balance | UNEXECUTED |
| C3 | Submit blank/161-char name, short/invalid phone and oversized notes; send JSON arrays/numbers for text | 422; no partial write, Audit or change | UNEXECUTED |
| C4 | Deactivate with/without reason; reopen inactive record; inspect draft/history references | Reason required, inactive identity retained, no new selection/edit; no hard-delete endpoint | UNEXECUTED |
| C5 | Staff with customer.view only; then add manage; separately add/remove private; inspect network JSON and phone search | View/read vs write separated; phone/notes absent without private, names remain; editing name preserves hidden fields | UNEXECUTED |
| C6 | From basket, quick-create name-only Customer and save draft details | New audited/published Customer assigned only after explicit save; quick-create absent without customer.view + manage | UNEXECUTED |
| D1 | Create draft, note UUID; add both Products, repeated Product lines and qty 1.2345; edit/reorder/remove | Stable saved draft, exact server estimates; positive four-place quantities, unique line order | UNEXECUTED |
| D2 | Choose/clear Customer; set business time; save; refresh browser or return/reopen | Same UUID, persisted basket/Customer/UTC occurrence; visible local-device input matches intended instant | UNEXECUTED |
| D3 | Prepare cash/transfer/due date, refresh; leave unsaved basket/header edits then reload | Preparation and unsaved edits reset, saved basket survives; behavior is labeled | UNEXECUTED |
| D4 | Price 0.10 × qty 0.05 and multiple rounded lines; totals near/exceed supported precision | 0.01 line estimate, sum rounded lines, overflow rejected without changing prior draft; no binary-float artifacts | UNEXECUTED |
| D5 | Submit negative/zero/overprecision/scientific quantity, negative/oversized/floating JSON price, forged totals/status/cost/scope IDs | 422/403/404 as appropriate; usable draft retained; no posted fields/effects | UNEXECUTED |
| D6 | Staff sale.view only; add sale.record; try different price without/with sale.price_override; change Product current price after draft save | Independent grants; current price used without override; preview rejects unauthorized old price; edit/save uses current price | UNEXECUTED |
| D7 | On a 20,000 draft preview cash-only 20,000; transfer-only 20,000; 5,000+15,000; 0+0; 5,000+0; 0+5,000; 5,000+5,000 | Exact paid/credit for every composition; credit requires active Customer; no payment/Receivable rows inserted | UNEXECUTED |
| D8 | Credit with/without Customer; valid optional due date; invalid leap date; due date on fully paid preview; overpay | Customer/date/composition validation; optional local date preserved in estimate only; existing basket unchanged | UNEXECUTED |
| D9 | Deactivate assigned Customer or Product in another session; preview/edit | Inactive Product rejected for add/preview; credit Customer must be active; references readable, removable/reassignable | UNEXECUTED |
| D10 | Discard with blank then valid reason; inspect Audit, rows and stock/payment/debt evidence | Blank rejected; only draft children/header removed; material discard Audit retained, no business/change effects | UNEXECUTED |
| S1 | Owner B/Staff requests A UUIDs; forge Customer/Product/item from B; compare list/lookups/detail | Scoped denial/not-found, no foreign identity/contact data or mutation | UNEXECUTED |
| S2 | Two sessions open same draft/version; both add/edit/remove/header-save/discard; interleave requests | One write then stale/conflict; no duplicate line, lost authoritative update or partial totals; reload resolves | UNEXECUTED |
| S3 | In DB session lock A Sale header FOR UPDATE; submit draft edit, inspect blocking, release; on disposable clone set valid POSTED header then try every draft mutation | Mutations serialize on parent; posted header/items cannot be edited/deleted by draft APIs; no post route exists | UNEXECUTED |
| S4 | Lose create response; retrieve by same UUID; retry same create. Lose line-save response; retry old version, then reload | Same draft; one line effect and stale/conflict on old version; no automatic new identity or blind replay | UNEXECUTED |
| S5 | In disposable environment deny runtime Audit INSERT during Customer edit/discard; separately deny sync_changes INSERT during Customer create/deactivate; restore rights | Entire transaction rolls back; no Customer/draft/Audit partial effect or sequence success; retained inputs | UNEXECUTED |
| S6 | Set READ_ONLY, suspend/close Tenant, deactivate Shop/member, expire login, omit/alter CSRF; call APIs directly too | Correct safe view/denial; no unauthorized changes; UI permissions are not sole enforcement | UNEXECUTED |
| S7 | Disconnect while viewing/editing, double-tap save, trigger request failure | Controls/pending/status clear; no offline queue; input retained, reload guidance on unknown result | UNEXECUTED |
| S8 | Inspect before/after effects for all draft operations and Customer lifecycle | Drafts: no movement/position/payment/Receivable/adjustment/reconciliation/sync effects. Customers: one atomic Audit + Tenant change; reads none; no contacts in Audit | UNEXECUTED |
| S9 | Phone 360px, tablet/desktop, 200% zoom, keyboard-only, screen reader/status inspection | Controls fit/reflow, labels/focus/pending/errors readable, no inaccessible action, cost placeholders absent | UNEXECUTED |
| S10 | Browse accepted Opening Cash, Products/stock/intakes, Staff permissions, activity; rollback all seven WP2 migrations on disposable clone in reverse dependency order, reapply | Existing workflows intact; no old schema edits or FK bypass; reverse/apply documented and successful | UNEXECUTED |

Do not create posted fixtures through user APIs: WP2 intentionally has no posting endpoint. S3's optional controlled SQL fixture must have a valid zero-credit composition and posting provenance, and exists only on a disposable clone. Never alter real evidence. Database lock/failure-injection checks need a separate operator connection; restore all rights and close/rollback test transactions afterward.

## Cleanup and acceptance

Discard only test drafts with explicit reasons; deactivate test Customers/Products rather than deleting referenced history. Retain Audit and accepted Opening Cash. Remove synthetic SQL fixtures only by rebuilding the disposable clone. Do not reset an unidentified database or production environment.

Record: tested SHA ___; environment ___; checklist results ___; defects/blocked cases ___; decision ACCEPT / REJECT / PENDING ___; owner/date ___. WP2 is not accepted until the owner reports that decision. WP3 begins after this package's acceptance, using the same permanent draft identities and lock protocol.
