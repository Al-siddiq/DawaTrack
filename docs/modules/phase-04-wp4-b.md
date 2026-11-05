# WP4-B — Return drafts, previews and posting

Delivered for native verification on 2026-11-05, based on WP4-A PR #22 (`c5ec4aabe1d4fb9642abb0992ccd12df70463aa3`). The binding rules remain the owner-approved WP4 Return/compensation contract. This increment implements the Return API and its required authority, Inventory, Audit and refresh participants. Correction/replacement commands and operator UI/combined receipts/history/complete diagnostics remain WP4-C/D; WP4 and Phase 4 acceptance are pending.

## API

All paths below `/api/v1/shop`; current Tenant/Shop derives from the authenticated session/host. Existing CSRF, JSON error envelope and private/no-store responses apply. Decimal values are strings. UUIDs are public v4 identities. No caller supplies tenant/shop, price, cost, debt delta, payment direction or compensation flags.

| Method | Path | Request/result |
|---|---|---|
| GET | /sales/{original}/returnable-lines | Original line UUID, Product/unit, original and effective remaining quantity, original unit selling price |
| POST | /sales/{original}/returns | `{public_id}` → normal RETURN DRAFT; same scoped original/UUID resolves existing record |
| GET | /returns | status DRAFT or POSTED, optional original_sale_public_id; page/per_page bounded to 50 |
| GET | /returns/{uuid} | Authoritative draft or posted Return, original link, items, refund/adjustment evidence, version; safe cost projection |
| PUT | /returns/{uuid} | expected_updated_at, items; optional occurred_at (UTC), device_local_offset_minutes. Full replacement of draft items only |
| GET | /returns/{uuid}/estimate | Saved-draft value, original debt reduction, required refund, remaining per-channel receipt capacity; advisory read |
| POST | /returns/{uuid}/preview | expected_updated_at, cash_amount, transfer_amount → authoritative validated settlement and review_fingerprint |
| POST | /returns/{uuid}/post | Preview fields + review_fingerprint + reason → immutable Return; same committed identity creates no second effects |
| DELETE | /returns/{uuid} | expected_updated_at + reason; draft-only discard with Audit and no financial effects |

Each PUT item contains `{public_id, original_sale_item_public_id, quantity}`; original lines must be unique, belong to the original Sale and have positive quantities within effective remaining quantity. Prices/COGS are server-derived. Empty drafts may be saved but cannot be estimated/previewed/posted. No generic posted edit/delete exists. Lists include only normal Return records; existing Sale/draft endpoints keep their previous meanings.

Read requires sale.view. Original-line eligibility and estimate also require sale.return. Create/edit/preview/post/discard require sale.view + the new independent sale.return grant, FULL access and active membership rechecked under transaction locks. Primary Owner derives known-code authority. Existing sale.reverse does not grant Return posting. Cost/COGS/signed margin responses require sale.profit.view; Customer contacts, raw internal IDs and original cost fields are excluded from safe responses without that grant. READ_ONLY allows safe reads; BLOCKED/CLOSED deny.

## Settlement and inventory

Return value and COGS use original Sale line price/cost attribution. Cumulative effective proportional rounding is bounded by remaining original cents; a final full Return consumes exact remaining sales/COGS cents. Effective prior Returns exclude directly compensated ones, preparing the approved read contract for WP4-C without exposing a compensation writer now.

`debt_reduction = min(Return value, remaining original Receivable)`; `refund = value - reduction`. Actual positive CASH/TRANSFER REFUND rows must sum exactly to refund and fit original receipts remaining per channel. Positive debt reduction appends a negative RETURN_REDUCTION against the same original Receivable. No new Receivable, CREDIT payment, wallet or write-off exists. Zero money/debt effects produce no fake rows. Normal Return new-Sale snapshots remain NULL.

Example: original 20,000, Cash 5,000 + Transfer 5,000 + credit 10,000. An independent Return 4,000 reduces debt to 6,000 with no money refund. An independent Return 12,000 reduces debt to zero and refunds 2,000 within the original receipt channels. A Return never invents received money. Future collection allocations block this settlement until the collection module's balance integration exists.

Inventory owns incoming CUSTOMER_RETURN / SALE_ITEM movements, original cost, weighted average, quantity/version/pointer and cost-state changes. Qty 10 at average 80 plus returned qty 2 at original cost 60 yields qty 12 at average 76.666667; attributed COGS remains 120. Original unknown provenance taints mixed positive stock; subsequent positive-stock Intake preserves that uncertainty. Depletion followed by a known fresh incoming basis may establish VALID. Pre-Return historical interpretation is preserved. Shared history is used by subsequent Sale receipt provenance and Intake reversal. Quantity precision overflow is rejected before narrowing. Historical inactive Product/Customer references remain valid; original identity cannot be substituted.

## Atomicity, recovery and evidence

CommercialAccess owns one transaction. Order: Tenant → Shop → active membership/authority → Cash guard for any actual cash refund → root original Sale → target Return → Products and scoped positions sorted by Product ID → original Receivable → financial/stock/header writes → aggregate Audit → active change generation/publication → commit. Preview locks root/target and revalidates without business writes; post recomputes all financial values and eligibility. Drafts reserve no quantity, money or debt. Inventory history uses current reads when proving Intake reversal after Product locks; manual reversal lock ordering is aligned to avoid movement-before-Product inversion.

The fingerprint compares reviewed version/allocated line values/debt/refund capacity/split. It is not authorization or replay storage. Changed quantities, version, settlement or eligibility require reload/review; a validation error can precede the stale-fingerprint error when the reviewed split is no longer valid. GET estimates are advisory and can change between requests. Same committed Return identity is a no-op on post; callers retrieve GET /returns/{uuid} after a lost response, retaining that identity rather than creating a second Return. Reload and review an uncommitted draft before an intentional retry. Read-back failure after commit is also resolved through that GET.

Audit action sale.return_posted records reason, source identity and aggregate effects. Discard uses sale.draft_discarded with kind RETURN. Audit/change/commit failure rolls all business effects back. Refresh publishes the Return and original Sale, movement UUIDs, logical Product Stock Position UUIDs, original Receivable and Customer context. Draft create/update/discard reserve and publish no financial effects. No offline posting/replay transport is added.

Key errors: ORIGINAL_SALE_REQUIRED, ORIGINAL_SALE_REVERSED, RETURN_STATE_CONFLICT, RETURN_NOT_FOUND, RETURN_EMPTY, RETURN_LINE_INVALID, RETURN_QUANTITY_EXCEEDED, RETURN_REFUND_MISMATCH, RETURN_REFUND_CAP_EXCEEDED, COMMERCIAL_REVIEW_STALE, COMMERCIAL_EVIDENCE_INVALID, COLLECTION_DEPENDENCY, STOCK_QUANTITY_OVERFLOW, plus existing scope/access/Cash/version/validation errors. Original evidence is immutable and future compensation remains the correction path.

See [delivery](../Phase-4/Phase_4_WP4_B_Delivery_Report.md) and [manual verification](../manual-verification-phase-04-wp4-b.md). Native MySQL/HTTP/concurrency verification and owner acceptance remain required.
