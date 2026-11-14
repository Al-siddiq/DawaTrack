# Phase 6 WP1 — movement foundation and Shop Expenses

13 November 2026 (Africa/Lagos). The Owner approved the four-package plan and requested WP1. This contract finalizes the permitted routes/DTOs before the writer. Controlling scope: [Phase6 plan](Phase_6_Implementation_Plan.md), schema v1.1 §11.1, CashControlGuard and existing CommercialAccess/Audit/change conventions. No WP2/WP3 writer or WP4 expected-Cash endpoint is exposed.

## Commands and authority

All routes below start `/api/v1/shop/financial-movements` and reuse authenticated Shop/session context, CSRF and no-store response handling.

| Method/path | Contract | Grants |
|---|---|---|
| GET root | Posting-order current-Shop history, page/per_page(max50); Shop-date from/to and Cash/Transfer filters | financial.view |
| GET `/{uuid}` | Scoped detail/status for a permanent movement UUID;404 means absent only at read time | financial.view |
| POST `/expenses/preview` | Normalize and authoritatively review one Expense; no business Audit/change | financial.view + financial.record; FULL |
| POST `/expenses` | Confirm review, insert Expense/Audit/change atomically or recover equivalent UUID | same |

Closed command body: `public_id` UUIDv4, `channel` CASH/TRANSFER, positive string `amount`, required `description`(1–300 trimmed characters), optional nullable/empty `external_reference`(max120) and optional nullable `occurred_at` (strict UTC DATETIME6). Confirmation additionally requires the server `review_fingerprint`. Reject scope/actor/type/signed delta/causal keys and all unknown fields. Ordinary Expense always derives SHOP_EXPENSE and a negative amount_delta on server. External reference is descriptive only; no bank settlement inference. No PUT/PATCH/DELETE or broad financial.manage grant.

Omitted occurrence defaults to server time on first acceptance. Its absence remains null in the canonical command/hash, so an unchanged retry without occurred_at remains equivalent even after time advances. Preview returns that canonical null; display says the time will be recorded when saved. Explicit occurrence uses the existing bounded Audit policy (366 days past/5 minutes future), validated only for a new effect. Equivalent replay is checked before fresh time/Cash eligibility. Supplying a timestamp after previously omitting it changes the command and conflicts. posted_at/created_at/actor/correlation are always server-owned.

Exact positive DECIMAL(19,2) magnitude: at most17 whole digits and2 decimals, no float/number JSON, sign, zero, commas or exponent. Stored Expense delta is its exact negative. Domain value object owns normalization/sign presentation; no PHP/JS monetary float. No cash balance is stored or manufactured. Transfer has no physical Cash effect and skips the guard even without Starting cash; Cash requires finalized baseline and no active Cash/Full control through existing CashControlGuard.

## Schema and shared ownership

One new parent-first migration/raw MySQL8.4 SQL pair creates financial_movements with exactly v1.1 columns: immutable public UUID/scope/type/channel/signed amount/description/reference, original/replacement links, occurrence/post/actor/correlation/create evidence. User FK INT UNSIGNED matches current Shield-facing migrations; domain keys BIGINT UNSIGNED. Unique public/scope/reversal keys, approved channel/time and type/time indexes; same-scope self FKs, Tenant/Shop/actor FKs, type/sign/nonzero/description/UUID/link-shape CHECKs. No unapproved unique replacement constraint. A future corrected replacement requires serialized service-level linearity; self/cycle/equal inverse/channel/customer-independent source eligibility are cross-row checks, not claimed MySQL CHECK capabilities. CHECKs never reference the AUTO_INCREMENT id.

FinancialMovementSchema fails closed on missing table/columns, incompatible types/nullability, missing required indexes/constraint kinds or incorrect scoped FK mappings; no missing-table zero/empty history. Migration down refuses any populated evidence. No prior migration/SQL pair changes. Native MySQL8.4 constraints/partial-DDL recovery remain manual target checks.

Focused module: ExpenseInput, MovementAmount, FinancialMovementRepository, FinancialMovementSchema, ExpenseService and FinancialMovementQuery. Shared posting participant appends one caller-validated scoped row; no independent transaction/auth/Audit/publication. Future services reuse it while retaining their own explicit command authority, never expose generic movement-type input.

Register financial.view/record plus reserved financial.correct/reverse with no new Staff grants. Only recorded FINANCIAL Audit action/resource and financial_movement change resource are live in WP1; reverse/correct actions wait for owning WP3 writers. Owner-only Withdrawal/Introduction and protected correction interpretation from the approved plan remain binding, not implemented in WP1.

## Transaction, review and recovery

Normalize → CommercialAccess owning transaction → Tenant/Shop/membership and current grants/FULL recheck → schema readiness → existing UUID/equivalence → Cash guard only for Cash → scoped new-row eligibility → verify canonical review → insert movement → required FINANCIAL Audit using description as reason → financial_movement change publication/generation last → commit. No domain reads/locks/writes after publication. Any insert/Audit/publication/commit failure rolls all effects back. Controller returns safe detail after service commit; a lost receipt reply is recovered by GET, not another UUID.

Review fingerprint covers action-version and canonical command. Expenses consume no stored balance: there is no mutable balance to fingerprint and a new independent Expense does not stale another Expense's review. Final confirmation rechecks current role/grants/FULL/Cash controls and UUID eligibility. A changed command/fingerprint yields FINANCIAL_REVIEW_STALE409. Cash guard changes are checked directly, not trusted from preview. Equivalent replay requires matching immutable raw fields plus bounded recorded command fingerprint. Same UUID with changed content returns FINANCIAL_IDENTITY_CONFLICT409. UUID uniqueness plus the serialized Shop boundary prevents simultaneous double acceptance. No generic idempotency/outbox/receipt table.

Detail/history returns only public movement/link IDs, type/channel/amount magnitude and signed delta, description/reference, occurred/posted times, Shop/name/timezone and recorded-by display. Actor numeric ID, Tenant/Shop numeric IDs, raw Audit metadata/hashes, credentials and private support data never leave the server. Statement/customer records, Sales/revenue/profit and Inventory are untouched. Authorized financial.view users may see the movement's own explanation/reference; no extra private-data authority is invented.

UI uses existing Money movements workspace, existing cash icon and shared shell/components/loading/review/confirmation. Cash shows money physically leaving; Transfer explicitly says it does not change physical Cash. History/detail/record-expense hashes are contextual, no extra bottom tab. Minimal per-host/user/Shop tab marker stores only movement UUID. Unknown status blocks leaving/reload, never auto-posts, and manually resumes only the same UUID after a scoped status read.404 is not conclusive cancellation of in-flight work. No amount/description/reference/body/credential in storage. Changed-identity conflicts remain unresolved until the saved record is checked. READ_ONLY/view-only can recover via GET; lost view authority requires legitimate restored access/Owner lookup, not discarding identity.

Operational/native acceptance, schema recovery, exact artifact inventory and P6-01–P6-12 are delivered separately. WP1 is not accepted until the Owner decides; parser/build/host/SQLite evidence never claims native PHP/MySQL/HTTP/concurrency/rollback/layout/accessibility execution. No automated test suite or new dependency.
