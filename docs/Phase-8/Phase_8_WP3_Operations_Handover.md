# Phase 8 WP3 — Shop Dashboard Operations Handover

## 1. What the Shop Home dashboard shows

The Shop Home dashboard is a read-only summary of existing DawaTrack reports. It does not post Sales, change Stock, collect Debt, finalize Reconciliation or create new business records.

- **Sales and payments:** figures for the selected Shop-local date range. Sales after returns, Cash/Transfer receipts and refunds, Credit created/adjusted and Gross Profit before expenses are taken from the accepted Sales report.
- **Current Stock:** latest saved Stock Position, low/negative counts and confirmed Stock value. It is not a historical Stock balance for the Sales date range.
- **Current customer balances:** outstanding and overdue Receivables as of the Shop-local date. Collections reduce Debt and do not create new Sales.
- **Finished shop checks:** persisted finalized Cash, Stock and combined checks. Historical finalized results are not rebuilt from current records.

The Sales date range is carried into the full Sales report when the user opens its drill-down. WP2 cards keep their source meanings: current Stock, current Debt and finalized Reconciliation. The Sales range must not be read as a historical filter for those cards.

## 2. Permissions

The dashboard appears only for a current Shop user with `report.view`. Each card also requires the existing source permission set:

- Sales: `report.view`; Gross Profit/COGS additionally requires `sale.profit.view`.
- Stock: `report.view` and `inventory.view`.
- Customer Debt: `report.view`, `customer.view` and `debt.view`.
- Reconciliation: `report.view` and `reconciliation.view`; variance fields additionally require `reconciliation.review` and the existing blind-read policy to allow the scope.

Card visibility is not the security boundary. The source API rechecks current Shop context and permissions on every request. Do not grant Staff extra permissions merely to make a dashboard card appear.

READ_ONLY permits authorized report reads. BLOCKED/CLOSED does not load dashboard report data and remains governed by the shared Shop access message.

## 3. Freshness and partial failure

Each card shows its own last refresh time and Shop timezone. Sales uses the WP1 UTC refresh timestamp formatted into the Shop timezone; WP2 cards use their own source `as_of` marker. The four reads are independent and do not form one atomic database snapshot.

Use **Refresh overview** to reread all authorized sources. Changing the Sales date range and selecting **Update Sales period** reloads only Sales. A card can fail without hiding the other successful cards.

If an online request fails after a previous successful load, the previous in-memory figures may remain visible only with an explicit stale warning. A 401/403 or source conflict clears that card's figures.

When offline, live report figures are cleared. No report data is saved in localStorage, IndexedDB or a service-worker cache. When the connection returns, the dashboard requests the source reports again.

## 4. Request limits and safe recovery

All four report GET routes share the existing per-signed-in-user limit of 20 requests per 60 seconds. If the dashboard receives 429 `RATE_LIMITED`, respect the `Retry-After` response and wait before retrying. Do not refresh repeatedly or bypass the filter.

If a card reports a source setup or evidence error:
1. Note the request ID and the source card name.
2. Retry once after confirming the connection and current Shop access.
3. If the same error persists, preserve the sanitized response and report it to the Shop Owner/technical operator.
4. Do not change source balances, Stock positions or finalized Reconciliation records to make the dashboard appear correct.

Do not share raw Customer data, private notes, credentials, blind-count values or unredacted API responses in support evidence.

## 5. Export and release boundary

Export is not included in this WP3 candidate. No export route, permission, button or generated file exists. A future export requires a separate Owner approval specifying report family, fields, date/row limits, format, audience, authority and Audit requirements.

This handover does not authorize PR merge, main-branch integration, production deployment, backup/restore or operational release. Those remain separate recorded decisions after WP3 acceptance.
