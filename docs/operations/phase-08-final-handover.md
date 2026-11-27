# Phase 8 — final operations handover

**Phase state:** Closed and Owner-accepted 27 November 2026.  
**Integrated source:** `main` at `53f92462700d79c97f37e9506762ae07d05849e3`.  
**Production state:** Deployment and operational release are separate and not authorized by phase closure.

## 1. Report families and source ownership

- **Sales and payment mix:** Sales-owned posted Sale/Return/payment/Credit evidence; Gross Profit uses historical Sale Item cost snapshots and is visible only under `sale.profit.view`.
- **Current Stock:** Inventory-owned current Stock Position and cost-state-aware valuation. Current Stock is not the historical Stock count captured by Reconciliation.
- **Customer balances:** CustomerDebt-owned Receivable projection, effective adjustments and allocations. Collections reduce outstanding Debt; they are not new Sales.
- **Finished shop checks:** Reconciliation-owned persisted finalized Cash/Stock/Full evidence. Historical results are not rebuilt from current operations.
- **Shop overview:** read-only composition of the four accepted source report APIs. The greeting and overview are followed by unfinished-sale continuation and the four quick actions under **What would you like to do?**

## 2. Access and privacy

- Report entry uses existing `report.view`; each source API independently rechecks current Shop context and its source permissions.
- Gross Profit/COGS requires `sale.profit.view`.
- Stock, Customer Debt and Reconciliation cards follow their existing source capability and privacy masks.
- Reconciliation variance visibility continues to require the established review authority and blind-read policy.
- Do not grant Staff additional permissions merely to expose a dashboard card.
- Never rely on client-supplied Tenant/Shop identifiers or client capability flags as authorization.

## 3. Period, freshness and interpretation

- Sales date boundaries use the Shop timezone and bounded WP1 date contract.
- Dashboard Sales drill-down carries the applied date range into the detailed Sales report.
- Current Stock, current Customer Debt and latest finalized Reconciliation have different source semantics; they are not historical totals for the selected Sales period.
- Each card has its own source freshness marker. The four report reads are independent, not one atomic database snapshot.
- Gross Profit is before expenses; it is not Net Profit.
- Stock value must retain the source's valuation completeness/cost-state caveats.

## 4. Refresh, request limits and safe recovery

- The dashboard uses existing source APIs, explicit refresh and independent card error/stale states.
- The four report GET paths share the existing per-user report bucket: 20 requests per 60 seconds. Respect 429 `RATE_LIMITED` and `Retry-After`; do not repeatedly refresh or bypass throttling.
- No report polling, localStorage/IndexedDB report persistence or Phase 10 offline synchronization is introduced.
- If a source fails, note its request ID and card name, confirm Shop access/connectivity, retry once and escalate a persistent sanitized error to the technical operator.
- Do not alter posted Sales, Stock Position, Receivables or finalized Reconciliation evidence to make a report appear correct.

## 5. Export and support evidence

Export was not approved and is absent. Do not promise a download or use an unapproved route. A future export requires a separate Owner decision defining report, fields, period/row limits, format, audience, authority and Audit requirements.

Support evidence must redact Customer-private data, blind-count values, credentials, secrets and unredacted response payloads. Use the source report's authorized detail screen for investigation.

## 6. Release boundary and next phase

Phase 8 source integration is complete on main. This handover does not authorize production deployment, production migration, backup/restore, rollback execution or operational release. Follow the repository's existing release handover and record those decisions separately.

Phase 9 is the next roadmap phase. It requires a separate Owner instruction before implementation begins.
