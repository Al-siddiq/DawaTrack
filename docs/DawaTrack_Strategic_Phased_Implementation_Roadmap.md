# DawaTrack — Strategic Phased Implementation Roadmap

**Status:** Proposed authoritative implementation execution plan, awaiting approval  
**Prepared from:** Business & Product Blueprint; Technical Blueprint v1.0; Database / Schema Design v1.1  
**Target outcome:** A complete, production-ready DawaTrack Phase 1 application suitable for real pilot shops

---

## 1. Authority, interpretation and scope

This roadmap implements the three approved foundation documents together. The Business & Product Blueprint defines the outcome and user experience; the Technical Blueprint defines the modular-monolith, security, offline and operational architecture; Database / Schema Design v1.1 is authoritative for persistence, constraints, transaction boundaries and concurrency. Database v1.1 explicitly resolves the Technical Blueprint's earlier custom-domain deferral: verified custom domains and complete Shop white-labeling are mandatory Phase 1 work. No other implementation-blocking contradiction was found.

The approved foundation is fixed: latest stable PHP/CodeIgniter 4 at implementation start, CodeIgniter Shield, MySQL 8.4 LTS/InnoDB, one modular-monolith codebase, Vue 3 Composition API with normal `setup()`, ES6+ JavaScript, native Fetch through one API layer, statically compiled Tailwind CSS, Dexie/IndexedDB, a manually controlled Service Worker, and REST-style JSON over HTTPS. There is no TypeScript, JavaScript application build system, Vue Router, Pinia, GraphQL, microservice, raw-table replication, or Docker/Kubernetes-first redesign.

### 1.1 Delivery principles

1. **Vertical slices, not disconnected screens.** Each business slice includes schema, scoped data access, service/invariants, authorization, API, integrated responsive UI, audit, synchronization where applicable, errors and manual verification.
2. **Server authority.** MySQL is canonical. Every online or synchronized write passes through the same explicit business service. Client-supplied Tenant/Shop IDs and hostnames select context but never grant access.
3. **Append and correct.** Posted/finalized evidence is not silently edited or deleted. Reversals, corrections and replacements are explicit, linked, reasoned and audited.
4. **Offline awareness from the first transaction design.** Public UUIDv4 resource identity, stable operation identity, occurrence time, dependency references and idempotent services are designed before transactional modules, even though full sync transport arrives later.
5. **Golden responsive rule.** Anything available on desktop is equally usable on tablet and an affordable Android phone. Tables become purposeful cards/lists or focused drill-downs—not routine horizontal scrolling. Accounting terms are translated into plain English familiar to an ordinary Nigerian shop operator; technical terms may appear only in help/support details.
6. **No automated testing.** Approval is exclusively through the detailed manual checklists in this roadmap. No PHPUnit, Pest, Jest, Vitest, Cypress, Playwright or other automated test suites are to be introduced.
7. **No placeholders.** A visible route, button or control must be functional and authorized; unavailable later work stays out of navigation. No mock production values or knowingly incomplete flows are accepted.

### 1.2 Mandatory engineering standard for every phase

Use strict typing where compatible, typed PHP properties/parameters/returns, focused classes, meaningful names, explicit readable control flow, one statement per line and generous whitespace. Controllers normally receive/validate/authorize, call one business service and format a response. Business rules belong in focused services such as `postSale()`, `recordDebtPayment()`, `postStockIntake()`, `finalizeReconciliation()` and `reverseSale()`, never duplicated across controllers or screens. Repositories/query objects always require established scope. Avoid large controllers, multipurpose service methods, deep ternaries, compressed `try/catch`, clever one-liners and copy/pasted rules.

Important public methods receive concise PHPDoc explaining purpose, scope/security and transaction invariants—not obvious syntax. Vue components and composables remain focused; shared presentation and API behavior is extracted rather than copied. All errors have a stable machine code, plain-English message and correlation ID where support investigation is appropriate. Money uses decimal strings end-to-end; dates/times follow the approved UTC/Lagos rules.

### 1.3 Database and documentation delivery convention

* CI migrations: `app/Database/Migrations/YYYY-MM-DD-HHMMSS_<Action><Subject>.php`, ordered by actual FK dependency. Shield's pinned vendor migrations run first and are not copied.
* Matching SQL: `docs/database/sql/phase-XX/YYYYMMDD_HHMMSS_<snake_case>.sql`. Every new CI migration must have an equivalent rerunnable-by-procedure raw MySQL 8.4 script with matching DDL, indexes, checks, FKs, generated guards and comments. Rollback/irreversibility notes accompany both.
* Phase records: update `docs/database/migration-register.md`, `docs/database/schema-notes.md`, affected `docs/modules/*.md`, `docs/api/conventions.md`, `docs/security/permission-catalogue.md`, `docs/sync/protocol.md`, `docs/operations/runbook.md` and `docs/configuration.md` as applicable.
* Each migration is rehearsed manually on an empty database and a representative prior-phase database; DDL precedes code that needs it. Data backfills precede `NOT NULL`/FK enforcement. Late/circular FKs are added only after both parent tables exist and are recorded in the migration register.
* No schema change may casually diverge from v1.1. A genuine blocking discrepancy must be documented and submitted for approval before implementation.

### 1.4 Universal UI and phase gate

Every user-facing slice supplies loading, success, empty, validation-error, server-error, permission-denied, offline, pending-sync, Needs-Attention/conflict, expired/read-only and suspended states wherever relevant. Forms retain entered data after recoverable failure. Sensitive actions explain consequences and require confirmation/reason. Semantic HTML, associated labels, logical focus, keyboard operation where practical, readable type/spacing, high contrast and comfortable touch targets are mandatory.

A phase is complete only when its database + business rules + backend authorization + API + frontend + responsive design + validation + audit + required offline behavior + error handling + documentation + manual verification work end-to-end.

---

## Phase 0 — Controlled Project and Application Foundation

### Objective
Establish a maintainable CI4/Vue/PWA skeleton, module conventions and environment discipline without pretending that business functions exist.

### Why It Comes Here
Every later slice needs consistent bootstrapping, response/error conventions, static assets, configuration and deployment-safe structure.

### Scope
Pin compatible stable releases; define modular namespaces and dependency direction; create Shop, Authentication and Platform Admin application shells; establish environment configuration, logging, coding conventions, route naming, JSON envelope/pagination/error conventions and documentation directories. Self-host Vue/Dexie/runtime assets. Establish Tailwind's separate CSS compilation procedure without adding a JavaScript application bundler.

### Database Work
Configure MySQL 8.4, InnoDB, strict SQL mode, `utf8mb4_0900_ai_ci`, UTC connections and least-privilege development credentials. Create no DawaTrack domain tables.

### Backend Work
CI4 bootstrap; module boundaries; thin-controller base conventions; request correlation IDs; centralized production-safe exception mapping; health/readiness endpoints that reveal no secrets; configuration validation; structured application/security/job logs; REST content negotiation and pagination contracts.

### Frontend/PWA Work
Server-served entry documents with Vue `setup()` mounts; no Vue Router (server routes/history links select application pages); centralized Fetch wrapper with credentials, CSRF refresh, timeout/cancellation, normalized errors and correlation IDs; accessible shell primitives, notices, skeletons, empty/error panels, confirmation/dialog and responsive navigation patterns. Provide a minimal manifest/service-worker registration boundary but do not claim offline business use.

### Security Requirements
Environment-only secrets, debug disabled outside development, safe headers baseline, escaped server output, CSP design compatible with self-hosted scripts, secure production cookie defaults and dependency/version register. HTTPS is mandatory in staging/production.

### Offline/Sync Requirements
**Architectural preparation.** Reserve versioned Service Worker, IndexedDB and sync protocol boundaries; do not cache authenticated data or enqueue operations yet.

### Audit Requirements
Define the audit vocabulary/envelope and actor/context contract; persistence begins in Phase 2.

### Responsive/Mobile Requirements
Demonstrate shell navigation, dialogs, notices, forms and feedback at phone/tablet/desktop widths with 44px-class touch comfort, visible focus and no essential hover-only behavior.

### Integration Dependencies
Approved documents only.

### Deliverables
Runnable empty application areas, conventions, environment guide, module map, API/error standard, static CSS procedure and migration/SQL register structure.

### Manual Verification Checklist
* Start development with valid configuration; confirm Shop/Admin/Authentication shells and health endpoints load.
* Remove a required setting; confirm startup fails clearly without exposing a secret.
* Trigger 404, validation-shaped and unexpected errors; confirm consistent safe responses and correlation logging.
* Inspect phone, tablet and desktop shells; use keyboard-only navigation and screen zoom; confirm focus and controls remain usable.
* Confirm production mode hides stack traces, all runtime assets are local, and no dead feature links/buttons appear.

### Definition of Done / Acceptance Criteria
The skeleton runs without mock business data; conventions are documented; errors and responsive primitives work; later modules have clear ownership boundaries; no prohibited frontend build architecture exists.

### Explicitly Not Included
Authentication configuration, domain tables, operational PWA cache, business workflows or automated tests.

---

## Phase 1 — Identity, Tenancy, Subscription Gate and White-Label Onboarding

### Objective
Deliver secure tenant creation, branded Shop entry and authenticated Owner/Staff access as the foundation inherited by every later module.

### Why It Comes Here
No business row or screen is safe before identity, Tenant/Shop scope, status, entitlement and deny-by-default permissions are established. White-labeling and custom domains are Phase 1 product capabilities, not finishing work.

### Scope
Shield username/password sessions and recovery; separate Shop and Platform Admin session/route boundaries; Platform Admin MFA; Tenant/Shop onboarding; one-Shop Phase 1 invariant; memberships and granular grants; Primary Owner and Staff lifecycle; Trial/Starter/Growth access evaluation and staff limits; operational Shop profile; complete branding/assets; platform subdomain and verified custom-domain lifecycle; branded login, title, favicon and dynamic manifest.

### Database Work
Run pinned Shield migrations, then introduce in FK order: `tenants`, `shops`, `shop_branding_profiles`, `shop_brand_assets`, `shop_domains`, `tenant_memberships`, `membership_permission_grants`, `subscription_plans`, `subscriptions`, `subscription_events`, plus documented generated guards/composite uniques/checks from v1.1. Seed only approved plan definitions and versioned permission codes through controlled seed procedures. Tenant onboarding atomically creates Tenant, Shop/profile, branding defaults/assets metadata, platform subdomain, Owner membership, Trial and later Phase 2 audit/change hooks; until Phase 2, onboarding is restricted to a controlled pre-pilot path and records an operational log.

### Backend Work
Identity, TenantShop, BrandingDomain and Subscription modules; hostname normalization/resolution; tenant context middleware; membership/status/permission/entitlement policies; password recovery/invalidation; Owner staff creation/deactivation/grant/revoke; Admin MFA and onboarding services; immutable entitlement period evaluation using server time; read-only/blocked response policy. Validate image bytes by decoded content and MIME allow-list, limits/dimensions, optimize safely, generate opaque names, store outside executable paths, disable script execution, version replacements and serve safely. Domain ownership challenge, verification, primary switching and certificate-status coordination use explicit services/scheduled checks.

### Frontend/PWA Work
Branded sign-in/recovery; first-use/onboarding state; Owner staff and permission management; Shop operational settings; branding editor/preview/upload; domain status/instructions/primary selection; subscription/status view. Apply colors through constrained CSS variables and active assets to all current shells, manifest fields, title and icons. Present plan/access restrictions plainly (for example, “Your plan has ended. You can view records but cannot record a new sale.”), with full loading/empty/error/denied/read-only/suspended states.

### Security Requirements
Shield password hashing; CSRF on session writes; secure `HttpOnly`, `SameSite` cookies; session rotation/invalidation; login/recovery rate limits; generic credential errors; mandatory Admin MFA; no public self-registration; backend authorization on every action. Hostname is never authority. Validate same-Tenant relationships. Hash domain tokens, never store TLS private keys in MySQL, escape branding copy, sanitize filenames, and prevent executable uploads. Platform Admin has no automatic Tenant membership.

### Offline/Sync Requirements
**Architectural preparation.** Branding/configuration resources expose stable UUIDs and version numbers; manifest/asset URLs are versionable. Offline authentication/provisioning and caching wait for Phase 10.

### Audit Requirements
Define pending Phase 2 integration points for login/security, onboarding, owner reassignment, membership/status/grants, entitlement, branding/assets/domain and Tenant/Shop status actions. Before Phase 2, only controlled pilot setup is allowed; the phase is not released externally.

### Responsive/Mobile Requirements
Phone layouts use stacked forms, searchable permission groups and card-based staff/domain lists; crop/preview and file errors work without precision dragging. Branded login/background remains readable on low-resolution screens. Every Admin/Owner action has mobile parity.

### Integration Dependencies
Phase 0.

### Deliverables
End-to-end branded tenant onboarding and secure access; permission catalogue; domain/asset safety notes; subscription access-mode contract; no generic DawaTrack branding on routine Shop surfaces.

### Manual Verification Checklist
* Onboard a Trial Tenant; verify exactly one Shop, active Owner, default brand, platform subdomain and half-open entitlement dates.
* Sign in/recover password/sign out as Owner and Staff; invalidate sessions after deactivation/password recovery; verify Admin MFA and distinct Admin/Shop sessions.
* Grant/revoke each representative permission; confirm Staff is denied by default and backend denial persists when a hidden control is called directly.
* Alter Tenant/Shop/member UUIDs and Host headers; confirm no cross-Tenant record or action is exposed.
* Upload valid assets and attempt fake extensions, malformed images, oversize/wrong dimensions/script payloads; verify safe rejection and versioned replacement.
* Verify custom domain challenge cannot activate before ownership/TLS readiness; switch primary domain and confirm canonical branded presentation without changing authority.
* Exercise Trial, active, expired/read-only, suspended, reactivated and closed states; verify immediate downgrade is refused above the Staff limit and no Staff is auto-deactivated.
* Complete all flows on phone/tablet/desktop, including permission editing, upload and domain instructions; confirm plain language and retained form input.

### Definition of Done / Acceptance Criteria
Tenant context is server-derived; all membership/status/entitlement gates work; Shop/Admin boundaries and MFA hold; complete brand/domain workflows are usable on mobile and desktop; uploads are safe; every migration has matching SQL/documentation; Phase 2 audit hookup points are explicit.

### Explicitly Not Included
Operational catalog/transactions, offline login/sync, automatic card billing, messaging credits or multi-branch behavior.

---

## Phase 2 — Audit, Idempotency and Protected-Transaction Foundation

### Objective
Make accountability, immutable evidence, causal correction and retry safety reusable before operational transactions begin.

### Why It Comes Here
Audit cannot be retrofitted credibly. Sales, inventory, debt, reconciliation, subscription and sync must share one atomic accountability model.

### Scope
Audit persistence/querying; actor/support/device/correlation context; reason and before/after conventions; protected-record lifecycle; online idempotency contract; explicit reversal/correction policy; integrate all Phase 1 sensitive events.

### Database Work
Introduce `audit_events` with v1.1 tenant/shop/actor/action/subject/correlation metadata, append-only protections and indexes. Add only approved Phase 1 columns/constraints already specified by v1.1—no generic event-store redesign.

### Backend Work
Audit service called inside owning business transactions; scoped Audit repository; immutable event DTOs; sensitive-field redaction; reason validators; idempotency-key handling per Tenant/operation family; protected-resource route conventions (no delete/update); support-context attribution. Backfill/emit controlled genesis events for Phase 1 setup as the approved pre-pilot transition, then make atomic audit mandatory.

### Frontend/PWA Work
Owner activity history and Platform Audit foundation with filters, pagination, clear event summaries and drill-down; shared reason dialog and correction/reversal presentation. Raw secrets/hashes never render.

### Security Requirements
Tenant-scoped audit visibility, separate Platform Admin policies, output escaping/redaction, append-only DB account permissions where operationally feasible, rate-limited export/query and correlation logging.

### Offline/Sync Requirements
**Architectural preparation.** Audit accepts future device, operation and occurrence context; offline writes do not yet run.

### Audit Requirements
Audit itself is append-only. Immediately integrate Phase 1 security/Admin, Tenant/Shop, Owner, membership/grant, subscription, branding/domain/asset and support actions.

### Responsive/Mobile Requirements
Audit tables become chronological cards on phones with actor, action, time and subject visible before expansion. Reason dialogs are keyboard/touch accessible.

### Integration Dependencies
Phases 0–1.

### Deliverables
Atomic audit facility, protected-transaction standard, idempotency/error contract and fully audited Phase 1 actions.

### Manual Verification Checklist
* Perform each Phase 1 sensitive action and confirm actor, scope, reason, subject and correlation are correct.
* Force a business failure; confirm neither its state nor audit event partially commits.
* Repeat an idempotency key; confirm one effect and stable response; reuse it for a different payload and confirm rejection.
* Attempt audit update/delete and cross-Tenant query; confirm denial.
* Verify redaction, pagination and phone/desktop readability; confirm support access has explicit reason and attribution.

### Definition of Done / Acceptance Criteria
Every sensitive existing action is audited atomically; evidence cannot be silently changed; repeat requests cannot duplicate effects; audit views are scoped and useful.

### Explicitly Not Included
Operational ledgers, full sync receipts/change feed, or general-purpose event sourcing.

---

## Phase 3 — Product Catalog and Inventory Ledger

### Objective
Deliver trustworthy products, stock intake/current expectation and all approved non-sale stock movements with Moving Weighted Average Cost (WAC).

### Why It Comes Here
Sales require products, expected quantities and cost snapshots. The accountable movement ledger must precede deduction and reconciliation.

### Scope
Product create/edit/deactivate; shop/product low-stock threshold; opening stock; posted multi-item intake; stock position; damaged, expired, Owner internal use/withdrawal, acknowledged missing/loss and correction/reversal operations; low-stock list; negative-stock behavior; zero/negative cost-basis handling. Expected quantity is never arbitrarily edited.

### Database Work
Introduce `products`, `stock_positions`, `stock_intakes`, `stock_intake_items`, `stock_movements` in dependency order with public UUIDs, composite same-scope FKs, source checks, decimals/indexes and unique reversal guards. Product name is not unique. Stock position is a lockable projection; movement rows are evidence.

### Backend Work
Catalog and Inventory services/repositories/policies/validators/APIs. Opening/intake transactions lock positions in ascending Product ID; validate totals/items; calculate WAC using `DECIMAL(38,12)` intermediate and store six-decimal average; post movements and update projection atomically. Follow v1.1 negative-to-positive cost reset and zero/negative basis rules. Corrections/reversals compensate rather than mutate. Paginated search and low-stock queries use documented indexes.

### Frontend/PWA Work
Product list/cards, search, detail/edit/deactivate, low-stock threshold; opening-stock and intake item builder; inventory summary/history; dedicated reasoned movement/correction/reversal flows. Show expected stock, not an editable balance. Use Naira and simple terms (“Cost price”, “Stock left”, “Why did stock leave?”), explicit negative-stock warnings and all universal states.

### Security Requirements
Separate view/manage/intake/adjust/reverse permissions; Owner-only or expressly granted sensitive loss/correction actions; same-Tenant Product enforcement; decimal/UUID/server validation; parameterized queries; CSRF and duplicate-submit controls.

### Offline/Sync Requirements
**Architectural preparation.** All resources and operations use permanent UUIDs, stable operation shapes, occurrence time and dependency references. Full local operation begins Phase 10.

### Audit Requirements
Product lifecycle, threshold changes, each posting, movement, reversal/correction and reason; position changes are explained by movement/audit, never audited as arbitrary edits.

### Responsive/Mobile Requirements
Phone product/inventory history uses cards and filters; multi-item intake has large search/add/quantity/cost controls, sticky review/post action and no wide-grid dependency. Desktop supports efficient keyboard entry without changing rules.

### Integration Dependencies
Tenant/scope/permission/subscription (Phase 1), audit/idempotency (Phase 2).

### Deliverables
End-to-end catalog and inventory ledger, correct current projections/WAC/low-stock, responsive UI and module/schema documentation.

### Manual Verification Checklist
* Create similar/identically named products; edit/deactivate and verify history remains accessible.
* Post opening stock and multi-line intakes; hand-calculate WAC/rounding and compare. Exercise zero stock, negative stock, and negative-to-positive transition.
* Record every approved non-sale movement; verify expected stock changes only through ledger entries.
* Reverse/correct a posting with reason; confirm compensating evidence, causal links, one-reversal guard and audit.
* Submit duplicate/retried requests and concurrent intakes in two sessions; verify one effect per request and no lost quantity/WAC update.
* Change Product/Tenant/Shop UUIDs and permissions; verify denial/isolation.
* Verify search/pagination/low-stock and all empty/error/read-only/suspended states on phone and desktop.

### Definition of Done / Acceptance Criteria
Inventory is reconstructable from movements; projection and WAC follow v1.1 under concurrency; no arbitrary balance endpoint exists; every operation is authorized, audited, idempotent, responsive and documented.

### Explicitly Not Included
Suppliers, purchase orders, payables, barcode scanning, complex units, sales deductions, returns or reconciliation adjustments (their integration comes in later owning phases).

---

## Phase 4 — Customers, Sales, Payments, Credit and Returns

### Objective
Deliver complete posted sales from item entry through stock, payment, credit, profit snapshots and customer returns.

### Why It Comes Here
Sales consume the Phase 3 ledger/cost projection. Customer identity must exist for credit, and sale effects must be atomic before repayment/reporting.

### Scope
Customer management; multi-item sales; Cash, Transfer, mixed, full-credit and part-credit composition; mandatory Customer for credit; optional due date; historical selling-price/cost/COGS snapshots; derived gross profit; linked Customer Return with stock increase, Cash/Transfer refund or credit Receivable reduction; protected reversal/correction. Credit is unpaid value, never a payment row.

### Database Work
Introduce `customers`, then `sales`, `sale_items`, `sale_payments`, `receivables`, and the required return/original/reversal relationships exactly as v1.1. Use checks ensuring item/header/payment/credit composition and one Receivable per credit sale; preserve explicit Cash/Transfer rows only. Before enabling any Cash effect, introduce the bounded opening-control subset of `reconciliation_sessions` and `reconciliation_financial_counts` (including their complete v1.1 columns and constraints); Phase 7 adds the remaining Reconciliation tables and activates the broader lifecycle rather than replacing these tables.

### Backend Work
Customer and Sales modules; `postSale()` locks Product positions in sorted order, requires finalized Opening Cash control before any Cash effect (the minimal opening-control capability is established here using Phase 7 schema/service preparation), snapshots prices/cost, posts sale/movements/payments/Receivable/audit atomically. Return service locks original evidence/position/Receivable, prevents over-return and composes refund/debt reduction. Calculate gross profit from snapshots; never label it net profit.

### Frontend/PWA Work
Fast searchable sale basket, quantity/price/payment split, Customer selection/quick create for credit, due date, clear amount due/paid/left to pay, review/confirm/receipt and sale history/detail/return/reversal. Prevent double taps while retaining retry identity. Explain “Gross profit (before expenses)” in plain language.

### Security Requirements
Granular sale/customer/return/reverse permissions; server recomputes totals and outstanding; same-Shop Product/Customer checks; protect posted evidence; validate payment composition, prices/quantities and return limits; mask Customer details according to permission.

### Offline/Sync Requirements
**Architectural preparation.** Sale/Customer/Return commands carry UUIDs, operation IDs, causal sequence-ready metadata and dependencies. Do not falsely advertise offline execution until Phase 10.

### Audit Requirements
Customer lifecycle and every posted sale, payment composition, credit creation, return/refund/debt reduction, correction/reversal and reason.

### Responsive/Mobile Requirements
Sale entry is phone-first: quick search, large add/quantity controls, collapsible basket and sticky total/checkout; mixed payment is a simple two-amount form. Sale detail becomes labeled sections/cards rather than a squeezed table.

### Integration Dependencies
Phases 1–3 and Phase 7's opening-Cash prerequisite delivered as an early bounded sub-slice before Cash posting.

### Deliverables
End-to-end sales/returns with correct stock, payment, Receivable and gross-profit evidence; responsive cashier workflow and API/module docs.

### Manual Verification Checklist
* Post single/multiple item Cash, Transfer, mixed, full-credit and partial-credit sales; confirm stock, snapshots, payment rows and Receivable amounts.
* Verify Cash sale is refused until Opening Cash is finalized; finalize it and retry.
* Attempt credit without Customer, mismatched totals, invalid due date/quantity/price and cross-Tenant IDs; confirm clear rejection with preserved basket.
* Return part/all of Cash, Transfer and credit items; verify refund or Receivable reduction and stock increase; reject over-return.
* Reverse/correct once; verify linked compensation, immutable original and accurate audit.
* Race two sales against limited/negative stock and repeat a submission; verify locking, approved negative behavior and no duplicate effect.
* Complete cashier/history/return on affordable-phone dimensions and desktop, including denied/offline/read-only/server-error states.

### Definition of Done / Acceptance Criteria
All payment/credit/return variants produce atomic, immutable, correctly costed evidence and usable UI; credit is never a fake payment; no sale control is decorative.

### Explicitly Not Included
Debt collection/allocation, expenses, net profit, bank reconciliation or complete offline execution.

---

## Phase 5 — Debt Collection and Customer Statements

### Objective
Complete the approved customer-debt lifecycle with accountable allocation and correction.

### Why It Comes Here
Repayment requires Receivables produced by sales; statements require both sales and return adjustments.

### Scope
Derived Customer outstanding/partial/settled and overdue states; Cash/Transfer debt collection; oldest-first or explicit allocation; partial payment; Receivable adjustment for approved correction/return; repayment reversal/correction; paginated Customer statement. No interest, penalty, automatic growth, wallet or advance balance.

### Database Work
Introduce `debt_payments`, `debt_payment_allocations`, `receivable_adjustments` with same-scope composite FKs, decimal checks, reversal/correlation links and allocation uniqueness. Receivable status and Customer balance remain derived.

### Backend Work
Debt services lock target Receivables in due/age/ID order, recompute outstanding inside the transaction, reject excess/no-balance amounts, allocate atomically and create audit/change hooks. Explicit allocation must sum to payment. Corrections append linked adjustments/compensation. Statement query derives running debt from authoritative evidence with pagination.

### Frontend/PWA Work
Customer debt dashboard/detail, overdue labels, “Record debt payment”, oldest-first preview, explicit bill selection, payment receipt/history/reversal and plain-language statement showing sale, payment, return and adjustment effects.

### Security Requirements
Separate customer/debt view, collect, explicitly allocate, adjust and reverse permissions; Customer confidentiality; same-Tenant/Shop validation; no client-calculated balances accepted; Cash opening-control check.

### Offline/Sync Requirements
**Architectural preparation.** Stable operations/dependencies and conflict result for a balance consumed by another device; full sync Phase 10.

### Audit Requirements
Payments, channel, every allocation, adjustment, reversal, actor, reason and linked Receivables.

### Responsive/Mobile Requirements
Phone debt lists use Customer cards with amount/due indicator; allocations use selectable cards and running remainder, not dense grids; statements retain readable dates/descriptions/amount direction.

### Integration Dependencies
Phases 1–4.

### Deliverables
End-to-end collection/allocation/correction and statements with derived balances and responsive UX.

### Manual Verification Checklist
* Collect partial/full payments by Cash and Transfer using oldest-first; verify exact allocations/balances.
* Allocate explicitly across Receivables; reject sum mismatch, excess and another Tenant's debt.
* Run simultaneous payments against the same balance; confirm serialization and loser receives a clear retry/Needs-Attention outcome.
* Return credit goods and apply authorized adjustments; verify statement and overdue derivation.
* Reverse/correct repayment; verify append-only links and restored derived balance.
* Verify no wallet/advance/interest is created; test permission, expired/suspended and all responsive states.

### Definition of Done / Acceptance Criteria
Balances are explainable and derived; allocation concurrency is safe; every correction is causal/audited; the complete flow is equally usable on phone and desktop.

### Explicitly Not Included
Interest, penalties, reminders/messaging, wallet, advance receipts or accounts receivable GL.

---

## Phase 6 — Financial Movements and Cash Accountability

### Objective
Record the approved non-sale movements that change expected physical Cash or provide financial evidence.

### Why It Comes Here
Reconciliation needs all sale/debt and non-sale Cash effects defined before expected Cash can be trusted.

### Scope
Shop Expense, Owner Cash Withdrawal, Owner Cash Introduction/Float and Financial Correction; Cash/Transfer classification; protected reversal/correction. This is not a general ledger.

### Database Work
Introduce `financial_movements` with controlled kinds/channels, signed-effect rules, original/reversal causal constraints, UUID/idempotency and same-scope indexes/FKs.

### Backend Work
Explicit financial services rather than CRUD; check finalized Opening Cash for Cash effects; validate sign/channel/reason; post and reverse atomically with audit. Expected-Cash query contract combines approved Cash inflows/outflows without treating Transfer as physical Cash.

### Frontend/PWA Work
Simple “Record expense”, “Owner took cash”, “Add cash/float” and “Correct a money record” flows; history/detail filters and clear Cash versus Transfer labels. Avoid debit/credit jargon.

### Security Requirements
Dedicated record/view/correct/reverse permissions; sensitive Owner actions protected; amount/kind/channel server validation; no update/delete endpoints; CSRF/idempotency/scope enforcement.

### Offline/Sync Requirements
**Architectural preparation.** Commands and expected conflicts are sync-ready; Phase 10 enables approved offline subset.

### Audit Requirements
All postings/reversals/corrections with actor, reason, channel, causal link and scope.

### Responsive/Mobile Requirements
Single-purpose mobile forms, large type selection and confirmation summary; history cards show action, amount, channel, actor and time without horizontal scrolling.

### Integration Dependencies
Phases 1–5 and Opening Cash control.

### Deliverables
End-to-end approved financial movements and authoritative expected-Cash inputs.

### Manual Verification Checklist
* Record each movement by allowed channel; hand-calculate expected Cash and verify Transfer does not change physical Cash.
* Attempt invalid sign/type/channel, no Opening Cash, duplicate and cross-Tenant submissions.
* Reverse/correct with reasons; confirm original immutable, one reversal and correct expected Cash.
* Verify every permission and access mode; complete all flows on phone/desktop with errors and retained input.

### Definition of Done / Acceptance Criteria
Each approved movement works atomically and feeds Cash expectation; no GL concepts or arbitrary balance edits exist; audit/security/responsiveness are complete.

### Explicitly Not Included
General ledger, payroll, accounts payable, bank reconciliation or automatic billing.

---

## Phase 7 — Reconciliation Control Engine

### Objective
Implement the highest-risk accountability workflow: opening Cash plus Cash, Stock and Full Reconciliation with immutable cutoff evidence.

### Why It Comes Here
It depends on complete stock, sales, debt and financial effects. Its device-readiness design must then constrain full synchronization.

### Scope
Opening Cash Control; independent prior Cash/Stock control chains; fixed server cutoff; relevant-device readiness/exclusion; Cash/Stock/Full scopes; blind counts; expected snapshots; committed counts; review; actual-minus-expected variance (negative shortage, positive surplus); Staff context; explanations; finalization/abort; append-only post-finalization notes; stock adjustment; Cash baseline rollover; late-operation classification.

### Database Work
Extend use of the Phase 4-created `reconciliation_sessions` and `reconciliation_financial_counts`; introduce `reconciliation_stock_counts`, `reconciliation_variances`, `reconciliation_variance_notes`, `reconciliation_staff_contexts`, `reconciliation_device_acknowledgements`. Apply scope/status checks, immutable snapshot decimals, independent predecessor FKs, one-active-workflow constraints/service lock, and adjustment causal links. Opening Cash uses a finalized `OPENING` session and physical Cash count.

### Backend Work
Reconciliation services serialize start/finalize on Shop; pin cutoff, generation/watermark and relevant device set; temporarily block operations affecting active scope; snapshot expected stock/cash; never reveal expected values during blind count; commit all observations/variances atomically; review/explain; finalize immutable evidence; post stock adjustment through Inventory (never automatic Cash adjustment); advance Cash baseline. Exclusion requires Owner permission/reason. Late offline operations retain original occurrence but are accepted after cutoff and never rewrite finalized evidence; classify them into later expectations and audit context.

### Frontend/PWA Work
Scope/start/readiness screen; mobile-friendly blind Stock count with progress/search; Cash denomination/total entry where approved; commit confirmation; review cards with shortage/surplus plain labels; explanation, finalize/abort, notes and linked stock-adjustment actions; historical evidence. Counts become read-only after commit as specified.

### Security Requirements
Distinct start/count/commit/review/finalize/abort/exclude-device/adjust/note permissions; server cutoff only; no client expected-value leak; state-transition validation; finalized rows cannot update; reason required for exclusions/abort/adjustment.

### Offline/Sync Requirements
**Architectural preparation plus readiness contract.** Registered-device records arrive Phase 10, but service/API semantics and an interim “no registered devices” mode are final. Full offline reconciliation itself is not enabled: start/finalize require server connectivity; all relevant devices must acknowledge caught-up state or be expressly excluded.

### Audit Requirements
Start, cutoff, readiness/ack/exclusion, counts commit, review explanations, abort/finalize, variance, append-only note, stock adjustment and late-operation association.

### Responsive/Mobile Requirements
Counting is optimized for one-handed phones with large controls, saved progress and clear committed lock; desktop supports efficient keyboard entry. Expected values remain blind on all sizes. Variance color is never the sole signal.

### Integration Dependencies
Phases 1–6; Inventory owns stock adjustment; Audit owns evidence.

### Deliverables
Complete reconciliation engine and history with independently correct Cash/Stock periods, concurrency controls and operator guidance.

### Manual Verification Checklist
* Finalize Opening Cash; verify Cash operations were blocked beforehand and baseline is retained.
* Run Cash-only, Stock-only and Full sessions; verify independent previous control points and fixed cutoff.
* Confirm blind entry cannot reveal expected values via UI/API; commit counts, verify `Actual - Expected`, shortage/surplus signs and rounding.
* Try illegal transitions, two simultaneous sessions, operations during active scope and edits after finalization; confirm safe rejection.
* Exercise relevant-device ready/not-ready/excluded-with-reason cases; confirm Staff context and immutable acknowledgements.
* Finalize, add later note, create linked Stock adjustment, and verify original evidence is unchanged and no Cash adjustment is automatic.
* Submit a late operation around cutoff; confirm prior result never changes and the operation affects the correct later period.
* Run abort and recovery/error paths and complete counting/review/history on phone/tablet/desktop.

### Definition of Done / Acceptance Criteria
Every lifecycle and scope is correct; evidence is immutable; cutoff/device rules hold; variance formula/signs and baseline rollover are demonstrably correct; all actions are authorized/audited/responsive.

### Explicitly Not Included
Bank reconciliation, offline finalization, silent device exclusion, automatic Cash adjustment or rewriting past sessions.

---

## Phase 8 — Authoritative Reporting and Operational Dashboard

### Objective
Turn authoritative transactions into useful Phase 1 summaries without a second truth.

### Why It Comes Here
Reports must derive from completed operational modules and reconciliation evidence, not speculative/mock data.

### Scope
Daily/monthly/custom-date Sales and Cash/Transfer/Credit mix; Gross Profit (not net); Stock value/levels/low-stock; Customer/debt/overdue; reconciliation/variance; Shop dashboard summaries. Controlled export only where approved.

### Database Work
No duplicate reporting warehouse/tables. Confirm/add only blueprint-approved indexes through paired migrations/SQL after examining query plans. Derived values remain queries from evidence/projections as specified.

### Backend Work
Scoped report query services, server-time/date-boundary handling in Shop timezone, pagination, bounded date ranges, efficient aggregates and permission-specific fields. Cache only safe short-lived computed responses with precise invalidation; database remains truth.

### Frontend/PWA Work
Real dashboard cards/charts only where useful; report filters, summaries, accessible tables/cards and detail links. Clearly explain “Gross profit before expenses”, expected stock and overdue. Provide loading/empty/error/stale/offline/access-mode timestamps.

### Security Requirements
Report/export permissions, Tenant scope, bounded queries/rate limits, formula injection-safe export, no confidential cross-report leakage and no sensitive data in shared caches.

### Offline/Sync Requirements
**Local support deferred to Phase 10.** APIs expose version/change dependencies; cached offline reports will be labeled with last synchronization time and never masquerade as current.

### Audit Requirements
Audit sensitive exports and Platform/Owner access where specified; source transactions retain their own audit.

### Responsive/Mobile Requirements
Summary-first phone views, card/list transformations and focused drill-down; charts have textual equivalents; filters/dialogs are touch friendly and do not require wide tables.

### Integration Dependencies
Phases 1–7.

### Deliverables
Real-data dashboards/reports with documented definitions, performance notes and no duplicated truth.

### Manual Verification Checklist
* Hand-reconcile sample daily/monthly/custom-range totals against sale/payment/debt/movement records and Shop timezone boundaries.
* Verify returns/reversals/credit/expenses affect only appropriate metrics; Gross Profit remains clearly distinct from net profit.
* Compare stock/low-stock, Customer balance/overdue and reconciliation variance summaries to source detail.
* Exercise large paginated histories and bounded filters; verify no load-all behavior or repeated request storm.
* Test permissions, cross-Tenant IDs, exports and expired/suspended states.
* Verify phone/tablet/desktop presentations, empty/error/offline-stale labeling and accessible chart alternatives.

### Definition of Done / Acceptance Criteria
All approved reports trace to authoritative data, definitions reconcile manually, queries are indexed/paginated and every screen is responsive and honest about freshness.

### Explicitly Not Included
Net-profit accounting, data warehouse, advanced analytics, forecasts or demand prediction.

---

## Phase 9 — Platform Administration and SaaS Operations

### Objective
Complete the separate Admin experience needed to operate real tenants safely.

### Why It Comes Here
Core tenant workflows now exist, so Admin support can diagnose them without gaining covert mutation powers. Foundation Admin onboarding/subscription/domain actions from Phase 1 are consolidated here.

### Scope
Platform dashboard; Tenant search/detail; Shop registration/onboarding progress; branding/domain management; Primary Owner create/recovery/reassignment; entitlement renewal/upgrade/downgrade; suspension/reactivation/closure; device and sync-health entry points; explicit reasoned support context, read-only/diagnostic by default; Platform Audit.

### Database Work
No separate Admin business tables are invented: use Tenant/Shop/identity/subscription/domain/device/audit tables. Add only approved indexes if measured/manual query review requires them.

### Backend Work
Dedicated Admin controllers/services/policies, never ordinary tenant service bypass. Owner reassignment is serialized; subscription changes append period/event and never mutate history; immediate downgrade checks active Staff. Support context has reason, expiry, narrow scope and visible attribution; exceptional action uses the owning service and remains audited.

### Frontend/PWA Work
Distinct Admin shell/branding and MFA step-up; real platform metrics; searchable Tenant cards/tables; guided onboarding; subscription timeline; suspension/reactivation/closure; domain/certificate and device/sync status; support-context banner; Platform Audit.

### Security Requirements
Mandatory MFA and least-privilege Admin permissions; route/session separation; step-up for sensitive actions; no silent impersonation; no universal Tenant membership; reasons/confirmations; rate limits; session invalidation; safe support-data rendering.

### Offline/Sync Requirements
**None for Admin operation.** Admin requires online connectivity. It can inspect/revoke devices and initiate controlled recovery once Phase 10 exists.

### Audit Requirements
Every Admin security, onboarding, Owner, plan/entitlement, status, domain, device, recovery and support-context action; audit queries themselves where sensitive.

### Responsive/Mobile Requirements
Appropriate Admin actions retain phone parity via cards/step flows, though large-screen efficiency remains. No critical recovery/suspension action is desktop-only.

### Integration Dependencies
Phases 1–8; Phase 10 completes device/sync views.

### Deliverables
Complete auditable SaaS operations console and support procedures.

### Manual Verification Checklist
* Onboard and search Tenants; verify real dashboard counts and one-Shop invariant.
* Recover/reassign Owner; verify former access/session handling and one active Primary Owner.
* Renew/upgrade/downgrade/expire; verify immutable periods, events, Staff-limit refusal and read-only behavior.
* Suspend/reactivate/close and confirm Shop sessions/actions respond correctly.
* Enter support context with reason; verify visible banner, expiry, read-only default and complete audit; confirm no impersonation path.
* Test Admin permissions/MFA/session boundary/cross-context attempts and phone/desktop workflows.

### Definition of Done / Acceptance Criteria
Platform operators can manage the Phase 1 lifecycle without direct DB edits or hidden Tenant authority; every sensitive action is secure, reasoned and audited.

### Explicitly Not Included
Automated card billing, messaging credits, business-record editing, hidden impersonation or multi-branch management.

---

## Phase 10 — Offline PWA, Devices and Operation Synchronization

### Objective
Enable safe, visible multi-device offline operation for the approved essential workflows without compromising server business rules or reconciliation evidence.

### Why It Comes Here
Transaction commands, identities, conflicts and reconciliation readiness are already stable. Implementing transport now connects to authoritative services rather than inventing parallel client logic.

### Scope
Installable branded PWA; manual Service Worker; Dexie versioned schema; cached Server Base State plus Local Unsynchronized Overlay; outbox; UUID resources/operation IDs/dependencies/causal client sequence; device registration/revocation; offline-user provision and PIN; approximately 72-hour authorization lease; push/pull; receipts/idempotency; server change sequence/cursor; generation/recovery epoch; conflict/Needs Attention; reconnection; relevant-device readiness; late reconciliation operations; branding sync.

### Database Work
Introduce `registered_devices`, `offline_user_provisions`, `sync_generations`, `sync_receipts`, `sync_changes`, `device_sync_cursors` in dependency order. Enforce unique device/operation/sequence/cursor/generation rules, hashes rather than plaintext PIN material, terminal receipt semantics and same-scope FKs. IndexedDB-only stores (base entities, overlay, outbox, local conflicts/settings) are documented, not added to MySQL.

### Backend Work
Device/provision services; secure PIN verifier/lease policy; versioned sync protocol; push validates provision, generation, monotonic causal sequence and dependencies, attempts unique receipt, dispatches the same owning service, and commits effects + changes + audit + ACCEPTED receipt atomically. Deterministic rejection commits terminal receipt; transient failure rolls back for retry. Pull is ordered by server change sequence; cursor advances only after local apply. Snapshot/bootstrap and generation recovery never erase pending outbox. Revocation blocks future push/lease renewal.

### Frontend/PWA Work
Dexie migrations; base/overlay selectors; encrypted-at-rest limitations explained (browser storage is not a trusted vault); PIN unlock and lease expiry; outbox dependency scheduler with backoff; push then pull/rebase; stable operation IDs across retry; status banner (offline/pending/syncing/up-to-date/Needs Attention/revoked/lease expired); conflict resolution actions that create authorized corrective/new commands, never edit server evidence; branded cached shell/config. Cache only versioned static assets and safe scoped data; sign-out/account switch prevents scope bleed while preserving protected pending work via explicit recovery path.

### Security Requirements
Device-bound opaque credentials, server-side provision status/expiry, slow PIN hash and attempt throttling, no reusable password in IndexedDB, XSS/CSP hardening, cache partitioning by user/Tenant/Shop, purge rules only after confirmed sync/authorized revocation, server entitlement/status check on sync, and no offline extension beyond approved lease. Offline cannot grant new permissions.

### Offline/Sync Requirements
**Complete synchronization support.** Enable only the blueprint-approved operations: essential Product/Customer reads/creates as specified, sales, approved stock/financial/debt operations and required reference/config state. Reconciliation start/finalize remains online with device readiness. Operation payloads—not tables—sync through normal services. Define compatibility/version negotiation and deterministic Needs-Attention reasons.

### Audit Requirements
Device register/rename/revoke, provision issue/renew/expire, accepted/rejected operation, conflict resolution, generation reset/bootstrap, exclusions and support recovery, with device/operation/sequence context.

### Responsive/Mobile Requirements
Persistent but unobtrusive sync status; phone-first outbox/conflict cards with plain next steps; no technical stack traces or cursor jargon. All offline-enabled desktop actions work equally on phone and vice versa.

### Integration Dependencies
Phases 1–9, especially explicit service commands, Audit and Reconciliation readiness.

### Deliverables
Installable branded offline PWA, documented sync protocol/Dexie schema/device recovery, visible reliable multi-device synchronization and Admin health integration.

### Manual Verification Checklist
* Install from platform and custom domains; cold-start offline after prior bootstrap; verify branded shell/icons/config and allowed reference data.
* Make every approved offline operation, close/reopen browser, and verify base + overlay displays one correct projected result with pending status.
* Reconnect; inspect dependency ordering, push/pull, cursor advancement and final server/local convergence.
* Retry identical operations, interrupt between send/response and resubmit; confirm one business effect and stable receipt.
* Create same Customer/resource UUID and conflicting multi-device debt/stock actions; verify deterministic acceptance or clear Needs Attention, never silent overwrite.
* Test wrong PIN/throttling, 72-hour lease expiry, revoked device, deactivated user, revoked permission, expired plan and suspended Tenant while offline/reconnecting.
* Start reconciliation with ready, pending and unavailable devices; exclude only with Owner reason; submit a late pre-cutoff operation afterward and verify finalized evidence stays fixed.
* Upgrade Dexie/Service Worker with pending outbox; verify operations survive, migrate and sync without clearing site storage.
* Switch user/Tenant, sign out, lose connectivity and simulate storage/quota/API errors; confirm no scope leak/data loss and clear recovery guidance.
* Run all status/conflict/recovery flows on affordable-phone dimensions and desktop.

### Definition of Done / Acceptance Criteria
Offline-approved work survives restart, retries exactly once in business effect, converges across devices, honors current server authority and never rewrites reconciliation; users can understand and resolve every status; normal upgrades preserve pending work.

### Explicitly Not Included
Raw DB replication, offline Admin, offline reconciliation finalization, indefinite authorization, background messaging or native apps.

---

## Phase 11 — Integrated UX, Accessibility and Performance Closure

### Objective
Close cross-module seams and make the complete application coherent, fast and understandable without deferring defects to deployment.

### Why It Comes Here
All features exist end-to-end; now complete journeys, terminology and workload behavior can be validated together without using this phase to finish missing module basics.

### Scope
Navigation/permission map; cross-links among Product, Sale, Customer, movement, reconciliation and audit; universal state consistency; Nigeria-appropriate plain-English content; accessibility; responsive parity; query/payload/image/static-asset performance; no placeholders/dead controls.

### Database Work
Only measured, schema-aligned index refinements with paired SQL; no denormalized reporting truth.

### Backend Work
Profile slow endpoints manually, remove N+1/repeated calls, enforce pagination/select only needed fields, bounded filters and efficient lookups; consistent errors and caching validators; production image variants.

### Frontend/PWA Work
Navigation audit; component/state consistency; request deduplication/cancellation; controlled caching; lean self-hosted libraries; responsive card/list designs; focus restoration, labels/semantics/contrast, keyboard and screen-reader basics; help text for difficult concepts; optimize payloads/assets for low-end phones.

### Security Requirements
Ensure performance changes never weaken scoping/authorization or cache isolation; escape all content and retain CSRF/CSP.

### Offline/Sync Requirements
**Complete support refinement.** Validate every enabled route against cached/reference needs, stale labeling, outbox overlay and storage pressure; no new offline scope.

### Audit Requirements
Confirm navigation hides unauthorized actions only as UX while server remains authoritative; retain audit links across journeys.

### Responsive/Mobile Requirements
Perform a route-by-route parity ledger at representative phone/tablet/laptop/desktop widths. Replace unsuitable tables with cards/compact summaries; verify touch targets, fixed/sticky controls, dialogs, virtual keyboard and portrait/landscape.

### Integration Dependencies
Phases 0–10.

### Deliverables
Closed navigation/state/accessibility/performance issue register; terminology glossary; responsive parity sign-off; no disconnected UI.

### Manual Verification Checklist
* Follow each role's complete navigation; confirm every link/button works and unauthorized/deferred items do not appear.
* Complete end-to-end day journey: login, intake, sale, debt collection, expense, reconciliation, report and audit trace.
* At phone/tablet/laptop/desktop sizes verify every action, card/table alternative, dialog, validation and virtual-keyboard interaction.
* Navigate by keyboard, inspect focus/labels/heading order, zoom text and check meaning without color; verify plain-language comprehension with representative operators.
* Manually inspect network/query logs on long lists/search/sale lookup; verify pagination, small payloads, no request storms and acceptable low-bandwidth behavior.
* Exercise all universal states and recoverable errors; confirm input retention and useful feedback.

### Definition of Done / Acceptance Criteria
No dead UI or missing mobile action remains; all workflows use consistent understandable states; representative data volumes perform reasonably; accessibility and responsive parity ledger is approved.

### Explicitly Not Included
New business capabilities, decorative redesign, advanced caching infrastructure or automated testing.

---

## Phase 12 — Security, Data Protection and Operational Hardening

### Objective
Verify defense in depth and establish recoverable staging operations before production deployment.

### Why It Comes Here
Controls were implemented per feature; complete-system review can now find cross-module gaps and prove operational recovery.

### Scope
Threat/permission/Tenant-isolation review; HTTPS/DNS/cookie/header/CSP/error/secret/DB-account hardening; uploads/domains/devices; retention/export/closure; logs/monitoring; scheduled work; backup and restoration.

### Database Work
Production least-privilege accounts for runtime, migration and backup; encryption/access controls; migration ledger review; integrity queries for Phase 1 one-Shop, scope and ledger invariants. No casual schema redesign.

### Backend Work
Review every route/policy/service/repository; rate limits for login, recovery, sync, uploads, exports and Admin; production errors; log redaction/rotation; scheduled domain/certificate, provision, monitoring and retention tasks. Define export/closure authorization and retention behavior.

### Frontend/PWA Work
Security/session expiry/revocation/recovery UX; safe rendering; CSP-compatible assets; clear maintenance/offline/server-error behavior without leaking details.

### Security Requirements
TLS everywhere; HSTS after domain readiness; secure cookies/session rotation; Shield hashing/Admin MFA; CSRF/XSS/SQL injection defenses; strict validation/output encoding; upload revalidation; domain takeover prevention; secret rotation/storage; least privilege; device/cache controls; audit integrity.

### Offline/Sync Requirements
**Complete support hardening.** Recovery generation procedure, forced bootstrap without deleting pending outbox, revoked credential handling, protocol abuse/rate limits and incident playbook.

### Audit Requirements
Review completeness for security/Admin/subscription/staff/reconciliation/device/support actions; alert on important failures without logging secrets.

### Responsive/Mobile Requirements
All security prompts, expiry/recovery states and incident notices remain usable on phones and accessible.

### Integration Dependencies
Phases 0–11; staging environment available.

### Deliverables
Security checklist and permission-route matrix; staging hardening; backup jobs every six hours; approximately 30 daily and 12 weekly recovery points; encrypted copy across the production boundary; backup/job failure alerts; monthly restore calendar/evidence template; documented restore, credential rotation and sync-generation recovery runbooks.

### Manual Verification Checklist
* Attempt horizontal/vertical privilege escalation by changing every representative UUID, route, Host header and payload relationship across two Tenants.
* Verify CSRF, cookie flags, session rotation/invalidation, login/recovery/sync rate limits, MFA and generic errors.
* Repeat malicious input/output/upload/domain cases; inspect CSP/security headers, logs and storage execution controls.
* Use runtime DB credentials to attempt schema/destructive access; confirm least privilege. Verify secrets/errors never appear in UI/logs.
* Run backup; verify encryption/off-boundary copy/retention and failure alert. Restore into isolated staging, reconcile counts/checksums/key workflows and record recovery times.
* Revoke device/user/permission during offline use, rotate sync generation and recover with pending operations intact.
* Verify scheduled-job failure monitoring and phone-accessible security/recovery messages.

### Definition of Done / Acceptance Criteria
Route/policy/scope review has no unresolved critical issue; backups meet frequency/retention/encryption requirements and a restore is proven; monitoring/runbooks and recovery generation work; staging reflects hardened production configuration.

### Explicitly Not Included
Feature expansion, distributed security infrastructure, automated penetration/test suites or production launch.

---

## Phase 13 — Release Engineering and Pilot Production Readiness

### Objective
Deploy safely to production and prove DawaTrack can onboard 3–5 real pilot shops without losing data or requiring storage resets.

### Why It Comes Here
Feature, offline, security and recovery gates must pass before real business records enter production.

### Scope
Staging-to-production release; DNS/TLS for platform/custom domains; migrations and rollback decision points; Service Worker/static/Dexie/API-sync version compatibility; monitoring; smoke verification; pilot onboarding/training/support and release documentation.

### Database Work
Take and verify pre-deployment backup; apply migrations before compatible code activation in documented expand/contract order; retain raw SQL/migration ledger; run post-migration integrity and Tenant-isolation checks; define rollback that never reverses committed business evidence.

### Backend Work
Production configuration/secrets, queues/scheduled commands, log/alert routing, health/readiness, maintenance behavior, domain/TLS automation and release compatibility window for older clients/pending operations.

### Frontend/PWA Work
Version Service Worker/static cache; staged activation/update notice; Dexie upgrade transaction and fallback; dynamic branded manifest/domain validation; ensure old client protocol remains accepted or explicitly migrates after its outbox is safe. Never instruct routine clearing of browser/site storage.

### Security Requirements
Final certificate/HSTS/cookie/MFA/DB/firewall/file-permission/backup-access review; production debug off; Admin bootstrap/recovery secured; pilot accounts use real individual identities.

### Offline/Sync Requirements
**Complete production support.** Release compatibility matrix covers current and immediately previous Service Worker, Dexie and sync protocol; deployment rehearses pending operations before/during/after upgrade and generation recovery.

### Audit Requirements
Audit production Admin/bootstrap/deployment-adjacent configuration and every pilot onboarding/support action; preserve release correlation/version in operational logs.

### Responsive/Mobile Requirements
Final real-device acceptance on representative affordable Android, tablet and desktop browsers across every module and install/update flow.

### Integration Dependencies
All prior phases approved; successful staging restore and hardening.

### Deliverables
Live production environment; monitored backups/jobs/application/sync/domain certificates; deployment/rollback/restore/support/onboarding runbooks; release manifest; pilot-readiness sign-off.

### Manual Verification Checklist
* Rehearse production deployment on staging with pre-backup, ordered migrations, code/static activation, rollback decision and post-checks.
* Upgrade an older installed client containing pending operations; confirm no clear-storage request, successful Dexie/SW upgrade and exactly-once sync.
* Verify platform subdomain and custom-domain DNS/TLS/manifest/icons/title/login/API on fresh and installed clients.
* Execute every role's critical journey using real production-like accounts and two Tenants; verify isolation, permissions, status/subscription and audit.
* Execute Cash/Stock/Full reconciliation with multiple devices and late activity; manually reconcile authoritative results.
* Trigger safe synthetic application/job/backup/sync/certificate failures and confirm actionable alerts/runbooks.
* Restore the pre-release backup in isolation and confirm documented recovery. Verify six-hour/daily/weekly schedules and next monthly restore owner/date.
* Complete real-device responsive/accessibility/offline/reconnect checks and pilot onboarding/training/support handoff.

### Definition of Done / Acceptance Criteria
Production is HTTPS-secured, monitored, backed up and restorable; compatible upgrades preserve pending transactions; custom domains/PWA work; all critical journeys pass on real devices; no critical/high blocker or placeholder remains; operators can onboard and support pilot shops from documented procedures.

### Explicitly Not Included
Post-pilot Phase 2 product features, VPS/microservice redesign, automated billing, messaging, suppliers, multi-branch, native apps or analytics expansion.

---

## 14. Roadmap summary matrix

| Phase | Main goal | Major modules | Key dependencies | End result |
|---:|---|---|---|---|
| 0 | Controlled foundation | Application/API/UI conventions | Approved blueprints | Runnable maintainable skeleton |
| 1 | Secure branded access | Identity, Tenant, Shop, Branding, Domain, Subscription | 0 | Branded tenant onboarding and access gates |
| 2 | Accountability base | Audit, idempotency, correction model | 1 | Atomic traceability for all later writes |
| 3 | Stock truth | Product, Inventory | 1–2 | Movement-ledger stock and WAC |
| 4 | Sell and return | Customer, Sales, Payments, Receivable | 3 | Correct sale/credit/return evidence |
| 5 | Collect debt | Debt allocation and statements | 4 | Explainable Customer balances |
| 6 | Track other money | Financial Movement | 4–5 | Complete expected-Cash inputs |
| 7 | Prove accountability | Reconciliation | 3–6 | Immutable Cash/Stock control evidence |
| 8 | Explain performance | Reporting/Dashboard | 3–7 | Authoritative summaries |
| 9 | Operate SaaS | Platform Administration | 1–8 | Safe Tenant lifecycle/support console |
| 10 | Work without internet | PWA, Devices, Sync | 1–9 | Reliable multi-device offline operation |
| 11 | Close UX seams | All Shop/Admin UI | 0–10 | Responsive, accessible, coherent product |
| 12 | Prove protection/recovery | Security, Backup, Operations | 0–11 | Hardened, restorable staging system |
| 13 | Release pilots | Deployment, Monitoring, Pilot Ops | 0–12 | Production-ready Phase 1 |

---

## 15. Module-to-phase traceability

| Approved module | Foundation/introduced | End-to-end completion | Production verification |
|---|---|---|---|
| Identity & Access | 1; audited in 2 | 1–2 | 11–13 |
| Tenant & Shop | 1 | 1–2 | 9, 12–13 |
| White-Label Branding/Domains | 1 | 1, offline in 10 | 11–13 |
| Product Catalog | 3 | 3, offline in 10 | 11–13 |
| Inventory | 3 | 3; Sale/Return/Reconciliation links 4/7; offline 10 | 11–13 |
| Sales | 4 | 4, offline in 10 | 11–13 |
| Customer & Debt | 4 | 5, offline in 10 | 11–13 |
| Financial Movement | 6 | 6, offline in 10 | 11–13 |
| Reconciliation | Opening prerequisite in 4; full 7 | 7; device readiness/late sync in 10 | 11–13 |
| Reporting | Query contracts earlier | 8; offline freshness in 10 | 11–13 |
| Subscription | 1 | 1 and Admin lifecycle 9; offline enforcement 10 | 12–13 |
| Platform Administration | Foundation actions 1 | 9; device/sync health 10 | 11–13 |
| Audit | Contract 0–1 | 2 and every later phase | 12–13 |
| Offline & Synchronization | Identity/operation preparation 0–9 | 10 | 11–13 |
| Security/Operations | Every phase | 12 | 13 |

---

## 16. Database-table-to-phase traceability

“Use begins” identifies the first business service consuming the table; later integrations remain shown where important. Shield's exact table list is owned by the pinned release, so it is correctly treated as one authoritative vendor migration set rather than redefined here.

| Approved table/set | Migration introduced | Service/business use begins | Major dependent module |
|---|---:|---:|---|
| Shield-owned authentication tables | 1 | 1 | Identity, all actor attribution |
| `tenants` | 1 | 1 | Tenant/access/subscription |
| `shops` | 1 | 1 | All Shop operations |
| `shop_branding_profiles` | 1 | 1 | White-label/PWA |
| `shop_brand_assets` | 1 | 1 | White-label assets |
| `shop_domains` | 1 | 1 | Routing/custom domains |
| `tenant_memberships` | 1 | 1 | Shop authority/Staff context |
| `membership_permission_grants` | 1 | 1 | Granular authorization |
| `subscription_plans` | 1 | 1 | Entitlement/Admin |
| `subscriptions` | 1 | 1 | Entitlement periods |
| `subscription_events` | 1 | 1 | Subscription evidence/Admin |
| `audit_events` | 2 | 2 | Cross-cutting audit |
| `products` | 3 | 3 | Catalog/Inventory/Sales |
| `stock_positions` | 3 | 3 | Inventory/Sales/Reconciliation |
| `stock_intakes` | 3 | 3 | Inventory |
| `stock_intake_items` | 3 | 3 | Inventory/WAC |
| `stock_movements` | 3 | 3 | Inventory/Sales/Returns/Reconciliation |
| `customers` | 4 | 4 | Sales/Debt |
| `sales` | 4 | 4 | Sales/Returns/Reporting |
| `sale_items` | 4 | 4 | Stock/COGS/Returns |
| `sale_payments` | 4 | 4 | Payment/Cash expectation |
| `receivables` | 4 | 4 | Credit/Debt |
| `debt_payments` | 5 | 5 | Debt/Cash expectation |
| `debt_payment_allocations` | 5 | 5 | Debt balances/statements |
| `receivable_adjustments` | 5 | 5 (return hook starts 4 where required) | Debt/Returns |
| `financial_movements` | 6 | 6 | Expenses/Owner Cash/Reconciliation |
| `reconciliation_sessions` | 4 (bounded opening-control subset) | 4; full engine 7 | Reconciliation |
| `reconciliation_stock_counts` | 7 | 7 | Stock Reconciliation |
| `reconciliation_financial_counts` | 4 (bounded opening-control subset) | 4; full engine 7 | Cash Reconciliation |
| `reconciliation_variances` | 7 | 7 | Reconciliation/adjustment/reporting |
| `reconciliation_variance_notes` | 7 | 7 | Append-only explanations |
| `reconciliation_staff_contexts` | 7 | 7 | Staff accountability |
| `reconciliation_device_acknowledgements` | 7 | 7; full device integration 10 | Readiness/sync |
| `registered_devices` | 10 | 10 | Device/sync/reconciliation |
| `offline_user_provisions` | 10 | 10 | Offline authorization |
| `sync_generations` | 10 | 10 | Recovery epoch |
| `sync_receipts` | 10 | 10 | Idempotency/push |
| `sync_changes` | 10 | 10 | Pull/change feed |
| `device_sync_cursors` | 10 | 10 | Pull/device readiness |

Migration ordering follows these dependencies, not the display order alone. The bounded opening-Cash sub-slice deliberately creates its two Reconciliation-owned tables in Phase 4 before the first Cash event and records that ownership/timing in the migration register. Phase 7 then adds the remaining dependent tables and full workflow. This resolves the functional dependency without weakening the rule that Cash cannot begin without a baseline.

---

## 17. Cross-cutting requirement traceability

| Requirement | Established | Completed/verified |
|---|---|---|
| Multi-tenancy / Tenant isolation | 1 context, composite ownership | Every phase; adversarial review 12–13 |
| Granular permissions | 1 catalogue/deny-default | Per-module policies 3–10; matrix 12 |
| White-labeling | 1 full online presentation | Offline 10; release/domain 13 |
| Verified custom domains | 1 lifecycle | TLS/monitoring 12–13 |
| Audit | Contract 0–1, persistence 2 | Integrated 3–10; reviewed 12 |
| Immutability / append-and-correct | 2 | Transactions 3–7; sync 10; review 12 |
| Moving Weighted Average Cost | 3 | Sale snapshots 4; reports/review 8/13 |
| Credit / Debt | Receivable 4 | Collection/statements 5; offline 10 |
| Cash Reconciliation | Opening prerequisite 4, engine 7 | Device/late operations 10; validation 13 |
| Stock Reconciliation | 7 | Device/late operations 10; validation 13 |
| Offline-first operation | Operation-ready design 0–9 | Full PWA/sync 10; release compatibility 13 |
| Multi-device synchronization | Readiness semantics 7 | 10; production validation 13 |
| Idempotency / duplicate prevention | Online contract 2 | Per service 3–7; receipts 10 |
| Responsive parity | Primitives 0, required each phase | Route ledger 11; real devices 13 |
| Accessibility/usability/plain English | 0 and each UI phase | Closure 11; pilots 13 |
| Subscription enforcement | 1 | Admin 9; offline 10; review 12–13 |
| Backups/recovery | Designed from 0 | Implement/prove 12; pre-release/production 13 |
| Security | Every phase | Full review 12; deployment gate 13 |
| Production deployment | Version-aware design 0/10 | 13 |

---

## 18. Release, operations and documentation discipline

### 18.1 Deployment order and compatibility

Every release records DB schema version, backend/API and sync protocol versions, static-cache name, Service Worker version and Dexie version. Use backward-compatible expansion first: backup, additive schema, compatible server, versioned static assets, then client activation; destructive contraction is deferred until supported older clients and outboxes are no longer possible. IndexedDB upgrades are transactional and preserve the outbox. A failed upgrade offers recovery/support—not “clear site data.”

### 18.2 Operational ownership

Assign named owners and alerts for six-hour backup execution, daily/weekly retention, encrypted remote copy, monthly restore rehearsal, domain/TLS expiry, application/error rates, scheduled-job failure and sync rejection/backlog/generation health. Runbooks state detection, triage, containment, restoration, verification and communication steps. Recovery point/time observations are recorded during each restore exercise rather than promised abstractly.

### 18.3 Documentation at each approval gate

Every phase updates its schema/migration register, raw SQL, module contracts/invariants, API/permission catalogue, user-facing terminology, sync protocol or operations/configuration guide as applicable. Documentation is part of Definition of Done and describes actual behavior. Architecture deviations require explicit approval; undocumented drift is not accepted.

---

## 19. Permanently deferred Phase 1 scope

Do not implement multi-branch workflows, Supplier management, Purchase Orders, accounts payable, full general ledger/accounting, payroll, barcode scanning, advanced analytics, demand forecasting, Customer wallet/advance balance, automated subscription card billing, bank reconciliation, native Android/iOS apps, messaging-credit infrastructure or complex package/unit conversion. Full white-labeling and verified custom domains are explicitly **not** deferred.

---

## 20. Phase-by-phase implementation delivery protocol

After this roadmap is approved, each Codex implementation authorization must follow this protocol exactly:

1. Implement only the explicitly authorized phase (including any named bounded prerequisite sub-slice); do not begin the next phase.
2. Complete the phase vertically—schema, service, authorization, API, responsive UI, audit, offline scope, errors and documentation—not merely files or screens.
3. Supply each CI4 migration and its equivalent MySQL 8.4 SQL under the naming convention in §1.3.
4. Update the migration register, schema notes, module/API/permission/sync/operations/configuration documents affected.
5. Provide the phase's manual verification checklist with exact setup, roles, devices and expected results. Do not add automated tests.
6. Report files created/modified and the important implementation decisions/invariants preserved.
7. State any genuine blocker or approved-document deviation explicitly; never silently improvise around it.
8. Stop after the authorized phase and wait for the owner's manual review and explicit approval.

No phase approval may be inferred from a successful migration, endpoint response, rendered page or partial checklist. Approval requires every acceptance criterion and manual scenario for that phase, including mobile/desktop parity, Tenant isolation, failure states and synchronization where applicable.

---

## 21. Final roadmap quality review

This sequence covers every approved Phase 1 module and every v1.1 table; places multi-tenancy, entitlement and authorization before business data; installs Audit before protected operations; makes transaction identities sync-ready before offline transport; supplies Opening Cash before Cash-affecting operations; completes all financial inputs before Reconciliation; and completes Reconciliation semantics before device synchronization. Each user-facing module is a vertical slice and each later integration has an explicit owner.

The roadmap preserves the approved stack and schema, complete white-label/custom-domain scope, append-and-correct evidence, WAC, credit without fake payment, immutable reconciliation cutoff, operation-based sync, responsive parity and manual-only verification. Its final gates prove security, backup restoration, older-client compatibility, production monitoring and real-device pilot readiness. Implementation must stop at each phase boundary for explicit review.
