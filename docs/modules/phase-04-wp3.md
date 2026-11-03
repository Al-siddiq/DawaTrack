# Phase 4 WP3 — authoritative forward Sales

Sales owns posting orchestration, payment composition and receipt/history queries. Inventory owns `SaleStockParticipant`: it requires the caller's transaction and prelocked ordered Product/position rows, writes SALE movements and positions, and returns historical cost snapshots. It never begins/commits a transaction or publishes early. CustomerDebt's existing schema owns Sale-linked Receivables; no collection/write-off workflow is introduced.

## API and authority

Authenticated routes below `/api/v1/shop`; ShopContext derives scope from current membership/hostname. All responses are private/no-store with the established JSON envelope, CSRF/session protections and safe errors.

| Method | Route | Contract |
|---|---|---|
| POST | /sales/{sale_uuid}/post | JSON expected_updated_at, optional cash_amount/transfer_amount strings (omission means zero), optional due_date YYYY-MM-DD/null. Returns the permanent receipt. Explicit null/numeric amounts are invalid. |
| GET | /sales/{sale_uuid} | Authoritative DRAFT state or immutable POSTED receipt; use after response loss and on refresh. Normal forward SALE only. |
| GET | /sales | Posted SALE history: bounded page/per_page (max 50), search Customer name/Sale UUID, optional from/to **UTC posting dates**, newest first. |
| GET | /sale-credit/{receivable_uuid} | Same-Shop original Sale obligation, optional local due date, linked Sale/Customer identities. No editable/current balance or repayment claim. |

| Capability | Staff permission | Boundary |
|---|---|---|
| Draft/receipt/history/associated original credit read | sale.view | Same Shop; Customer identity/name, no phone/notes |
| Forward Sale posting | sale.view + sale.record | FULL access, active membership and current authority rechecked inside transaction |
| Price differing from current Product price | sale.price_override additionally | Revalidated under Product lock; unauthorized stale quote rejected |
| Historical cost, COGS and gross-profit detail | sale.profit.view additionally to sale.view | Otherwise fields are not selected/calculated/returned; UI also masks them |
| Opening Cash | Existing Owner-only WP1 authority | Cashier sale.record does not grant baseline setup |

Primary Owner receives known-code ordinary authority. Staff grants are independent. `customer.manage`, `debt.manage`, `sale.reverse`, `inventory.adjust` and reconciliation grants do not authorize posting or margin access by implication. READ_ONLY permits receipt/history reads but not posting; BLOCKED/CLOSED deny. Private Customer fields remain separately controlled in Customers and never appear in Sale responses. Inventory permissions continue to govern their own existing APIs; stock proof in the Sale receipt exposes quantities/reference, not cost fields without the margin grant.

Errors follow the established envelope: SALE_NOT_FOUND/RECEIVABLE_NOT_FOUND (scoped 404), DRAFT_STALE/SALE_STATE_CONFLICT/PRODUCT_INACTIVE/CUSTOMER_INACTIVE (409), SALE_DRAFT_EMPTY/CUSTOMER_REQUIRED/PAYMENT_EXCEEDS_TOTAL/STOCK_QUANTITY_OVERFLOW/VALIDATION_FAILED (422), SALE_PRICE_PERMISSION_REQUIRED/PERMISSION_DENIED and access-mode denial (403), plus WP1 OPENING_CASH_REQUIRED/CASH_CONTROL_ACTIVE (409). Unexpected database/commit/readback failures return safe REQUEST_FAILED; retrieve the Sale UUID before deciding whether to retry.

## Posting and locking

One CommercialAccess transaction: Tenant → Shop → active membership/authority → Cash baseline/active-scope guard **only if cash > 0** → Sale parent → active assigned Customer → line rows → Products ascending numeric ID → positions ascending Product ID → stock/line/payment/Receivable/header effects → aggregate Audit → active generation/change publication → commit. No participant commits and no domain locks follow generation locking. Current root locking conservatively serializes Sale commands within a Tenant; no high-throughput claim is made.

Posting locks the same parent used by WP2 drafts and rechecks DRAFT and expected_updated_at. Current active Product and available Shop position are required. Any assigned Customer must still be active; positive credit additionally requires a Customer. Recompute every quantity, permitted price and rounded line total server-side. Persisted client/draft totals and zero cost placeholders are never authoritative posting inputs. Original/reversal/replacement-linked rows are excluded from this ordinary forward command; WP4 owns those workflows.

CashControlGuard rejects absent finalized Opening Cash or an overlapping active Cash/Full control before commercial/Inventory locks. Transfer-only/full-credit Sale does not call the Cash guard. An existing POSTED retry is recognized under the held Tenant/Shop serialization boundary and returns its existing receipt without another Cash check, payment, stock effect, Receivable, Audit or change. The command still requires current FULL posting authority and syntactically valid inputs. GET state retrieval requires only permitted read access.

Line extension/COGS uses CommercialDecimal's MySQL wide DECIMAL intermediate and half-up rounding to kobo; `SaleMoney` sums rounded money and computes paid/credit/profit through digit-string arithmetic without floats or machine-sized monetary totals. Stock subtraction compares the wide result before narrowing and rejects quantity overflow. Header total and aggregate COGS overflow are rejected. Zero unit price is permitted with applicable price authority; quantities must be positive with up to four places. Repeated Product lines execute in line order after all Product/position locks have been acquired.

Outgoing stock preserves moving average cost. VALID becomes RETAINED at zero/below; RETAINED and UNESTABLISHED remain so under outgoing activity. Negative stock is valid and visible. Each Sale line produces exactly one scoped SALE/SALE_ITEM movement referencing the line, then updates quantity, last pointer and version. Its cost basis and COGS replace the draft placeholders. No alternate costing engine exists.

Only positive CASH/TRANSFER RECEIPT rows are inserted. `paid + credit = total`. Credit creates exactly one positive Receivable for the Sale; fully paid Sales create none. Due date is optional and stores a local calendar DATE without UTC conversion; a due date with zero credit is rejected. No CREDIT payment row, change wallet, interest, repayment, bank reconciliation or Financial Movement is introduced.

## Historical evidence, retries and change resources

Receipt prices/cost/COGS/gross profit come from posted line snapshots. “Gross profit (before expenses)” is Sale detail, not net profit/aggregate reporting. Historical cost state is reconstructed from each Product's immutable movement chain **before that Sale movement**, bounded at the Sale movement ID. UNESTABLISHED cost marks line/header profit provisional; later acquisitions do not turn an old unknown cost into verified profit. Product name/unit displayed are current master labels; the schema snapshots historical amounts, not a separate Product-name copy. Cost-state reconstruction reads that Product's earlier chain; long histories may require future measured optimization without changing evidence.

`GET /sales/{uuid}` is the recovery path. The UI retains UUID, disables further writes after an unknown posting outcome, and offers Check Sale status; it never automatically retries posting or invents a new Sale. Pending protection suppresses double taps. A POSTED refresh shows the receipt. DRAFT refresh restores saved state and clears transient payment preparation. There is no durable generic receipt/replay store or offline queue before Phase 10. Deadlock/lock-timeout errors roll back and return safe failure; retrieve state, then deliberately retry the whole command if still DRAFT. No partial transaction retry is performed.

| Authoritative effect | Audit/change identity | Reader refresh |
|---|---|---|
| Posted Sale, lines and immutable actual Receipt payments | sale.posted Audit (SALE/sale); sale UPSERT with Sale UUID | GET /sales/{uuid}; payments are children, no arbitrary payment resource |
| Created credit obligation | receivable UPSERT with Receivable UUID | GET /sale-credit/{uuid} or embedded original obligation in Sale receipt |
| Customer credit context | Tenant-scoped customer UPSERT | Existing Customer identity read plus linked Sale/Receivable evidence; no stored Customer balance |
| Each linked SALE movement | stock_movement UPSERT with movement UUID | Existing permitted Inventory API or safe stock proof embedded in Sale receipt |
| Position for each distinct Product | stock_position UPSERT with Product UUID | Existing logical Shop/Product position read |

`sale.posted` Audit includes scoped actor, fixed reason, total/paid/credit, line count, total COGS and count of unestablished-cost lines, linked by request correlation. No raw Customer contacts or authentication values are recorded. One aggregate Audit covers the atomic Sale; stock/payment/Receivable FKs provide business linkage. Customer create/deactivate still has its own WP2 Audit semantics. No successful events are emitted for rejected/repeated post commands.

Inventory diagnostics now accept correctly linked SALE and CUSTOMER_RETURN source/sign shapes and inspect commercial item coverage/source integrity, while retaining existing position/chain/pointer/reversal checks. Return/compensation writers are not implemented here. Direct manual-stock reversal of a Sale/Return movement is explicitly denied; future correction must compensate the whole commercial transaction under WP4.
