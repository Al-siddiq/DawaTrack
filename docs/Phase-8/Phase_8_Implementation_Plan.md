# Phase 8 — Authoritative Reporting and Operational Dashboard

**Prepared:** 24 November 2026 (Africa/Lagos)  
**Status:** Owner accepted 25 November 2026. Phase 8 WP1 implementation candidate is in progress; native verification and package acceptance remain pending.  
**Baseline:** `main` at `98cb8d4ee99c8668a18aa92993b7828dc982902f` (Phase 7 WP4 integration, PR #53).  
**Delivery model:** Three sequential, independently reviewable end-to-end work packages. Each package includes its own backend, API, frontend, security, audit, documentation and manual verification. The next package starts only after explicit Owner acceptance of the preceding package.

## 1. Authority and controlling foundation

This plan implements, and does not expand, the existing Phase 8 scope in:

- [Strategic phased implementation roadmap — Phase 8](../DawaTrack_Strategic_Phased_Implementation_Roadmap.md#phase-8--authoritative-reporting-and-operational-dashboard): controlling objective, scope, requirements, exclusions and acceptance criteria.
- [Technical Blueprint](../DawaTrack_Technical_Blueprint.md), especially §§1, 2, 3, 6.8–6.10, 7, 9, 10 and 13: architecture, tenant authority, operational evidence, gross-profit definition, reconciliation, immutability, platform/Shop separation and module ownership.
- [Database Schema Design v1.1](../DawaTrack_Database_Schema_Design.md), especially §§3–4, 7–15, 20–25: authoritative tables, decimal/time rules, tenant relationships, indexes, derived values, transaction boundaries and reporting source rules.
- [Module ownership map](../modules/module-map.md), [HTTP API conventions](../api/conventions.md), [security foundation](../security/foundation.md), [permission catalogue](../security/permission-catalogue.md), [Audit contract](../audit-contract.md), [migration register](../database/migration-register.md), [UI architecture](../ui/architecture.md), [navigation and permissions](../ui/navigation-and-permissions.md), and [plain-English UI policy](../ui/plain-english.md).
- The Phase 7 closure report and Owner acceptance record, which establish the completed Reconciliation prerequisite.

If this plan and a controlling foundation document appear to differ, the foundation document controls. Resolve a genuine ambiguity through an explicit Owner decision before implementing the affected behavior. Do not silently reinterpret schema v1.1 or the roadmap.

## 2. Entry position and objective

Phases 1–7 provide the operational evidence Phase 8 needs: Products and Stock, posted Sales and Returns, payment composition, Customer Receivables and collections, Financial Movements, Audit/change publication, and Cash/Stock/Full Reconciliation.

Phase 8 turns those existing authoritative records and projections into useful Shop summaries. It creates no second transactional truth.

At completion, an authorized Shop user must be able to understand:

1. what the Shop sold over a day, month or selected date range, including Cash, Transfer and Credit composition;
2. the Gross Profit derived from the historical Sale/Return cost snapshots, clearly distinguished from expenses and net profit;
3. current Stock levels, low-stock state and supported Stock value;
4. Customer balances, settlement state and overdue balances;
5. finalized Reconciliation results and variance summaries; and
6. a concise Shop dashboard that links each summary to its authorized source detail and states when the information was last refreshed.

All results must reconcile to existing source records and use the Shop's configured timezone for user-facing date boundaries.

## 3. Fixed scope

### Included

- Daily, monthly and custom-date Sales summaries.
- Cash, Transfer and Credit Sales mix.
- Gross Profit summaries using immutable historical cost evidence; expenses remain separately reported.
- Stock value, Stock levels and derived low-stock summaries.
- Customer balances, debt/receivable and overdue summaries.
- Reconciliation and variance summaries from finalized Reconciliation evidence.
- Shop operational dashboard summaries assembled from the same approved report queries.
- Scoped, permission-aware, bounded and paginated report query services.
- Shop-timezone date-boundary handling, stable ordering and efficient aggregate queries.
- Responsive report filters, summary cards, useful charts where they improve understanding, accessible tables/cards and source-detail links.
- Honest loading, empty, error, stale, offline and access-mode states with freshness timestamps.
- Safe, short-lived computed-response caching only if profiling demonstrates a need and precise invalidation is proven.
- Controlled export only where the Owner approves the exact export scope and authority. Any approved export must be audited as required and protected against spreadsheet formula injection.
- Required report definitions, API/module contracts, manual verification records, performance notes and operational handover.

### Explicitly excluded

- Net-profit accounting or expense-adjusted profit.
- General ledger, chart of accounts, double-entry accounting or journal posting.
- A reporting warehouse, duplicate fact tables, permanent reporting snapshots or a second balance ledger.
- Advanced analytics, forecasts, demand prediction, recommendations or benchmarking.
- New business workflows, new transactional records, or changes to Sales, Inventory, Debt, Financial Movement or Reconciliation business rules.
- Bank reconciliation, bank feeds or settlement verification.
- New multi-branch or cross-Tenant consolidated reporting.
- Offline report persistence or local report caching. Local/offline support is deferred to Phase 10.
- New queue, broker, worker, analytics engine, charting framework, frontend state-management framework, bundler or other technology not already established.
- Automatic data correction, variance balancing or mutation of posted/finalized source evidence.
- Production deployment or changes to production data/secrets; those remain separately authorized operational decisions.

No Phase 8 feature may alter the meaning, amount, status, causal links or historical posting time of existing evidence.

## 4. Authoritative reporting definitions and source rules

The detailed contracts for each report must cite the relevant schema and owning service/query. The following rules are binding; implementation must not substitute convenient but non-authoritative fields.

### 4.1 Periods and date boundaries

- Accept only the documented, bounded date filters for each report. Reject invalid, reversed or excessively wide ranges with the project's standard safe validation envelope.
- Interpret a user's selected Shop calendar dates using the current Shop timezone and convert them to precise UTC instants for database filtering.
- Use server-authoritative `posted_at` for authoritative reporting periods. `occurred_at` may be shown as contextual information where useful, but it must not backdate accepted evidence into a closed period.
- Daily and monthly summaries must use Shop-local calendar boundaries, including month/year transitions and daylight/timezone rules supported by the configured timezone.
- Use inclusive Shop-date selection implemented as a precise half-open UTC interval; document the conversion and avoid double counting at adjacent period boundaries.
- Use deterministic ordering and bounded pagination for detail/history lists. Aggregate endpoints must bound date ranges and result cardinality.

### 4.2 Sales, payment mix and Gross Profit

- Read posted Sales and linked posted Returns/compensations through Sales-owned query/evidence contracts. Drafts are not revenue.
- Include linked compensating effects exactly once. Never count an original and its compensation as if both remained effective.
- Derive Cash and Transfer mix from authoritative Sale payment evidence and its linked refunds/reversals, not from a client-calculated header or a current Cash balance.
- Derive Credit from the Sale's validated Receivable/credit evidence. Credit is an unpaid Sale amount, not a payment row or later debt collection.
- Debt collection reduces an existing Receivable; it is not new Sales revenue and does not create new Gross Profit.
- Calculate Gross Profit as signed net Sale/Return amount less the corresponding historical COGS derived from Sale Item snapshots. Never recalculate historical COGS from today's average cost.
- Clearly label the result **“Gross profit before expenses”** or the approved plain-English equivalent. Do not call it net profit or imply expenses have been deducted.
- Show recorded Financial Movement expenses separately where the approved report/dashboard design includes them. Do not subtract expenses from Gross Profit or create an expense-adjusted/net-profit figure.

### 4.3 Stock value, levels and low Stock

- Read the current Stock Position and Stock Movement evidence through Inventory-owned queries/projections.
- Low-stock state remains derived from expected current quantity compared with the Shop/Product threshold; do not store a second low-stock flag.
- Stock value must use the existing persisted current quantity and average-cost basis, with the Inventory module's established precision and cost-state rules. Do not revalue historical Sales or invent a new costing method.
- Preserve and visibly explain any existing unestablished/retained cost state. Do not present an unsupported precise valuation as authoritative where the cost basis is unknown or retained under existing rules.
- Reconciliation's captured expected Stock and finalized variance are historical cutoff snapshots; they are not interchangeable with today's live Stock Position.

### 4.4 Customer debt and overdue

- Derive outstanding balances from Receivables, effective Receivable Adjustments and effective Debt Payment Allocations, including linked reversals/compensations exactly once.
- Derive outstanding/partially-paid/settled state; do not add a mutable status or balance column.
- Derive overdue only when a positive outstanding balance has an established due date that has passed, evaluated against server time and the Shop timezone.
- Do not treat collection as revenue, create wallet/advance balances, or expose Customer private fields beyond existing permission rules.
- Paginated Customer/debt lists and details must use the existing CustomerDebt query ownership and scope.

### 4.5 Reconciliation and variance

- Summarize only the applicable persisted Reconciliation evidence and use the existing Cash/Stock/Full session scopes and lifecycle.
- Preserve the established variance sign: actual minus expected. Present shortage/surplus in plain language without changing stored signs.
- Keep Cash and Stock variances distinct and identify Full checks without double counting their constituent evidence.
- Do not recompute a finalized historical result from today's Sales, Stock, Debt or Financial Movements.
- Do not create Financial Movements or Stock Adjustments from a report or variance summary.

### 4.6 Shop dashboard

- The dashboard is a presentation of the approved report summaries, not a new source of truth.
- Dashboard cards and any charts must call the same scoped report services/definitions as their corresponding reports.
- Every summary must link to an authorized detail view where one exists.
- A card must not display a figure if the underlying source query is denied, incomplete, stale beyond its stated freshness, or unavailable; show the correct safe state instead.

## 5. Architecture, module ownership and implementation conventions

### Backend

- Controllers remain transport mappers: receive/validate transport input, rely on established filters/context, call one owning service/query and return the canonical API envelope.
- Report calculations belong in focused, read-only query services in the owning business module or a narrowly scoped Reporting module only if the established architecture requires it and the Owner approves the ownership boundary before implementation. Do not create a generic cross-module repository.
- Sales owns Sales/payment/COGS interpretation; Inventory owns Stock projection and valuation interpretation; CustomerDebt owns Receivable/overdue interpretation; Reconciliation owns variance/session interpretation; FinancialMovement owns expense evidence; Audit owns Audit evidence.
- Cross-module reporting composition is read-only and follows module-map dependency direction. No module writes another module's records.
- Use the established Tenant/Shop context, composite ownership checks, current membership, AccessGate and permission services on every request. Client-supplied Tenant/Shop IDs never establish authority.
- Reuse the standard request correlation, response envelopes, CSRF, no-store/security headers, throttling and safe error behavior.
- Use exact decimal strings and MySQL DECIMAL arithmetic/aggregates. Never use PHP or JavaScript binary floating-point arithmetic for money, quantities or cost totals.
- Keep UTC authoritative instants and convert to Shop timezone only for presentation and date-boundary interpretation.
- Use bounded date ranges, server-capped page sizes, stable keyset/offset strategy consistent with the existing endpoint family, and query plans verified against pilot-shaped data. Do not load entire histories into PHP or the browser.

### Database and migrations

- Add no reporting tables, warehouse, duplicate balance columns or materialized fact tables.
- Start with existing schema indexes. Inspect actual query plans and representative cardinality before proposing an index.
- Add only a schema-v1.1/Technical-Blueprint-approved index that is demonstrably required. Every approved DDL change must have the existing paired CodeIgniter migration and SQL twin, deterministic names, register entry, rollback/preflight notes and native MySQL verification.
- Do not add a new index merely because a report is being built. Do not add a redundant left-prefix index.
- Keep MySQL 8.4 LTS, InnoDB, explicit precision, foreign-key and transaction conventions.

### Permissions, privacy and Audit

- Reuse the existing closed-registry `report.view` permission for report reads, subject to the established Primary Owner/Staff grant model, current membership, Tenant/Shop status and AccessGate. Verify the runtime catalogue and Staff UI rather than assuming a browser-side permission.
- Do not silently grant existing Staff new access. Do not infer report access from `sale.view`, `sale.profit.view`, `inventory.view`, `customer.view`, `debt.view`, `financial.view` or reconciliation grants unless the existing approved permission policy explicitly says so.
- Sensitive fields remain governed by their source-domain permission rules. A report must not leak COGS/profit, Customer-private information, Device details, Audit metadata or other restricted fields through aggregates, filters, exports or drill-down links.
- Export authority is separate from ordinary report viewing. Do not introduce an export permission or expose an export route by assumption. If the Owner approves export, define its exact scope and least-privilege permission in the relevant package contract before implementation; existing Staff grants must not be expanded automatically.
- Audit sensitive exports and any Platform/Owner report access where required by the approved Audit vocabulary/policy. Ordinary report reads must not create unnecessary Audit noise.
- Never put sensitive report payloads in shared caches. Cache keys, if approved, must include all authority/scope/filter/version dimensions and invalidation must be precise.

### Frontend/PWA and accessibility

- Use the current Vue 3 Composition API, ES modules, existing Shop app, shared UI primitives, navigation/permission registry, API client, Sass/Bootstrap conventions and plain-English policy.
- Edit `resources/` sources only; regenerate `public/assets/` through the existing build. Do not hand-edit generated files or change the build pipeline.
- Use charts only where they add meaning. Every chart has an equivalent textual summary/table and is understandable without color alone.
- On phones, use summary-first cards/lists and focused drill-down; on larger screens use the existing responsive table/card patterns. Avoid forcing wide tables on small screens.
- Provide loading, empty, safe error, stale/offline and READ_ONLY/BLOCKED/CLOSED states. Clearly display the report's selected period, Shop timezone and last-refreshed time.
- Phase 8 remains online-authoritative. Do not add local report persistence or claim offline freshness. If a previously cached response is presented by an existing approved mechanism, label its last synchronization/refresh time and never present it as current.

## 6. Three-package delivery structure

Three packages are the minimum practical split. WP1 establishes Sales and Gross Profit definitions; WP2 delivers the independent Inventory, Debt and Reconciliation evidence families; WP3 composes the Shop dashboard, conditionally approved exports and complete cross-domain acceptance. Combining these into fewer packages would couple several distinct source owners and make failures difficult to isolate. More packages would add artificial handovers without improving domain ownership.

Every package is vertical and end-to-end: backend queries, API contracts, permissions/security, frontend screens, responsive states, documentation and manual verification are included in that package.

### WP1 — Sales, Payment Mix and Gross Profit Reports

**Outcome:** An authorized Shop user can view reconciled daily, monthly and custom-range Sales summaries, Cash/Transfer/Credit mix and Gross Profit from authoritative posted Sale/Return evidence.

**Backend and data**
- Define and document Sales period, net Sales, payment mix, return/compensation and Gross Profit semantics against schema v1.1 and the Technical Blueprint.
- Implement focused Sales-owned scoped report query services with Shop-timezone boundaries, bounded date ranges, exact DECIMAL/string arithmetic, efficient aggregation and stable pagination for any detail list.
- Include posted Sale and effective linked Return/reversal/compensation effects exactly once; exclude drafts and prevent Credit collection from becoming new Sales.
- Read payment mix from posted Cash/Transfer payment evidence and Credit from validated Receivable evidence. Keep refunds/reversals correctly attributed and signed.
- Calculate Gross Profit from immutable historical Sale Item cost snapshots. Mask profit/COGS wherever the existing `sale.profit.view` authority is absent; report viewing alone must not bypass it.
- Present Financial Movement expenses separately only where the agreed report design includes them; no net-profit calculation.
- Use existing indexes first. Record EXPLAIN/EXPLAIN ANALYZE findings and propose a paired migration/SQL index only if evidence requires an approved index.

**API and security**
- Publish only the report read routes required by the approved API contract under the existing `/api/v1/shop` conventions; route names and payloads are finalized in the WP1 integration contract, not invented in this plan.
- Reuse `report.view`, existing current-Shop authorization and the source-specific profit visibility rule. Enforce scope and date bounds in the server query.
- Return canonical response envelopes, safe validation errors, request IDs, pagination metadata where applicable and no-store behavior.

**Frontend**
- Add Sales summary/report screens to the existing Shop navigation and permission presentation.
- Provide daily/monthly/custom date filters, Sales totals, Cash/Transfer/Credit composition, Gross Profit before expenses, period labels, source detail links, loading/empty/error/stale/access-mode states.
- Use the existing UI primitives and mobile card/list transformation. Any chart must have a textual equivalent.

**Documentation and package verification**
- Deliver the WP1 requirements/source matrix, API and module contracts, report-definition notes, manual result register and delivery report.
- Hand-reconcile ordinary Cash, Transfer, mixed and Credit Sales; partial/full returns; reversals/compensations; credit collection; and a period boundary against source details.
- Verify that current cost changes do not rewrite historical Gross Profit and that unauthorized Staff cannot obtain COGS/profit through API, UI, pagination, filters or detail links.

**WP1 manual cases:** P8-01–P8-12.

**Acceptance:** Sales totals, payment mix and Gross Profit reconcile to authoritative evidence for daily/monthly/custom periods; returns and compensations are counted exactly once; Shop timezone boundaries are correct; permissions and profit masking hold; screens are responsive and freshness is honest.

### WP2 — Stock, Customer Debt and Reconciliation Reports

**Outcome:** An authorized Shop user can inspect authoritative current Stock summaries, Customer/debt/overdue summaries and finalized Reconciliation/variance summaries.

**Backend and data**
- Implement focused Inventory-owned Stock summary/valuation/low-stock queries using current Stock Position and established cost-state semantics.
- Preserve expected quantity, average-cost precision, low-stock threshold derivation and UNESTABLISHED/RETAINED valuation states. Do not invent a new valuation rule.
- Implement CustomerDebt-owned outstanding, settlement and overdue summaries from Receivables, effective adjustments and allocations; derive overdue from positive outstanding plus due date and Shop-local current date.
- Implement Reconciliation-owned summaries from persisted finalized Cash/Stock/Full controls and variance evidence. Preserve actual-minus-expected signs and never reconstruct a finalized result from live records.
- Apply bounded Shop-scoped query filters, deterministic pagination and efficient aggregates. Any drill-down must use the owning module's existing authorized read.
- Examine existing composite indexes and query plans. Only introduce a paired migration/SQL index where the foundation allows it and measured evidence demonstrates the need.

**API and security**
- Define each source report's response fields and scope in its WP2 integration contract. Reuse `report.view` for report entry, while all source-specific field and detail access rules remain enforced.
- Do not leak Customer private data, restricted Stock/cost data, reconciliation blind expected values, non-review reasons or cross-Shop records through summary counts or drill-down links.
- Deny foreign IDs without revealing whether another Tenant/Shop record exists. Preserve READ_ONLY reads and BLOCKED/CLOSED restrictions.

**Frontend**
- Deliver Stock levels/value/low-stock summaries, Customer debt/overdue summaries and Reconciliation/variance summaries in the existing Shop UI.
- Clearly label current Stock versus historical captured Reconciliation Stock.
- Clearly label outstanding, partially paid, settled and overdue states; do not expose private Customer fields without the existing authority.
- Clearly label Cash/Stock variance and shortage/surplus; preserve the recorded sign and link to permitted finalized detail.
- Use accessible responsive cards/lists/tables, date/filter controls where appropriate, loading/empty/error/stale/offline/access-mode states and last-refreshed timestamps.

**Documentation and package verification**
- Deliver the WP2 source-definition matrix, API/module contracts, report definitions, manual result register and delivery report.
- Compare Stock quantity, cost-state, value and low-stock results to Inventory source detail.
- Reconcile Customer balances against original Receivables, payments, allocations, adjustments and compensations; test due-date boundaries in Shop timezone.
- Compare Cash/Stock/Full variance summaries with finalized session details and prove live operations do not mutate historical report results.

**WP2 manual cases:** P8-13–P8-24.

**Acceptance:** Stock, Customer/debt and Reconciliation summaries reconcile to their owning authoritative evidence; derived states and valuation caveats are correct; no cross-domain or cross-Tenant leakage exists; pagination, filters, mobile presentation and freshness behavior pass.

### WP3 — Shop Dashboard, Approved Export and Phase 8 Integration

**Outcome:** The Shop receives a coherent operational dashboard assembled from accepted WP1/WP2 reports, with end-to-end permissions, performance, accessibility and documentation. Controlled export is included only if the Owner has explicitly approved its exact scope and authority.

**Backend and integration**
- Compose dashboard summaries from the same WP1/WP2 report query definitions. Do not create dashboard-specific competing calculations or a second truth.
- Ensure a dashboard card cannot reveal a figure that its viewer cannot access in the corresponding report. Source permissions and masking remain authoritative.
- Establish safe refresh/version/change dependencies in the API contract. Dashboard and report freshness must be explicit; no Phase 10 offline synchronization is implemented.
- Profile representative multi-report dashboard requests, prevent repeated request storms and avoid N+1 query patterns.
- Consider safe short-lived computed-response caching only when measured need is documented. If used, prove precise invalidation on relevant source changes and isolate by Tenant, Shop, user authority, filter and version. Database evidence remains authoritative.
- Review rate limits and bounded filters across all report routes. Confirm all report paths use the correct Shop timezone and source-specific read services.

**Controlled export — conditional scope**
- First obtain an explicit Owner decision identifying the report, fields, date-range limits, format, audience and access authority.
- If not approved, do not implement export routes or UI and record export as not included in this accepted Phase 8 scope.
- If approved, implement only the approved export through the established API/controller/service conventions; enforce the distinct approved export authority, current Tenant/Shop scope, bounded rows/ranges, safe headers/content type and formula-injection protection for spreadsheet-compatible values.
- Audit sensitive exports using the approved Audit vocabulary and metadata policy. Do not log exported contents, Customer private fields or secrets. Do not place exports in shared caches or public paths.
- Exported figures must reconcile to the same report definitions and period boundaries as the on-screen report.

**Frontend**
- Deliver the Shop operational dashboard using only useful cards/charts and WP1/WP2 report services.
- Provide a coherent selected-period/filter model, source detail links, textual chart equivalents and responsive phone/tablet/desktop layouts.
- Present loading, empty, error, stale/offline and READ_ONLY/BLOCKED/CLOSED states honestly. Every figure must expose its selected period, Shop timezone and last-refreshed time.
- If export is approved, add only its approved UI action and safe completion/error feedback. Do not create an export interface for unapproved report families.

**Documentation and package verification**
- Deliver dashboard composition contract, any approved export contract, integrated report definitions, performance/query-plan notes, final source-to-report traceability matrix, P8-25–P8-36 manual result register, final delivery report and operations handover.
- Hand-reconcile the dashboard against WP1/WP2 report detail for the same selected period.
- Verify cross-report permission consistency, cache isolation/invalidation if cache exists, export formula-injection safety if export is approved, no request storm, and stale/offline/access-mode labeling.
- Complete phone, tablet and desktop checks, keyboard/dialog checks where applicable, textual chart alternatives and accessibility review.

**WP3 manual cases:** P8-25–P8-36.

**Acceptance:** Dashboard figures equal the corresponding accepted reports; all report definitions reconcile to source evidence; permissions, privacy, Tenant isolation, bounded query performance and freshness labels hold; every view is responsive and accessible; any export is limited to the exact approved contract and audited safely.

## 7. Cross-package manual acceptance register

The following case identities are reserved for the three-package programme. They are requirements to execute, not pre-recorded Pass results. Each result must retain the repository's existing evidence standard: environment/version, synthetic fixture and Shop, actor/grants, selected period/timezone, expected and actual result, evidence reference, reviewer and Africa/Lagos observation time. A case not actually observed remains Not run or Blocked.

| Case | Required verification |
|---|---|
| P8-01 | Daily Sales summary against posted Sale/Return source detail |
| P8-02 | Monthly Sales summary across month boundary |
| P8-03 | Custom date range and adjacent-period boundary without double count |
| P8-04 | Cash-only Sales composition |
| P8-05 | Transfer-only and mixed Cash/Transfer composition |
| P8-06 | Full/partial Credit composition and Receivable linkage |
| P8-07 | Return/refund and linked compensation counted exactly once |
| P8-08 | Debt collection does not create new Sales revenue |
| P8-09 | Historical COGS/Gross Profit remains based on Sale snapshots |
| P8-10 | Expenses remain separate; no net-profit label or calculation |
| P8-11 | Profit/COGS permission masking through API and UI |
| P8-12 | Large range, pagination, bounds, empty/error and phone/desktop behavior |
| P8-13 | Current Stock levels against Stock Position |
| P8-14 | Low-stock threshold derivation and threshold changes |
| P8-15 | Stock value against quantity and valid cost basis |
| P8-16 | UNESTABLISHED/RETAINED cost state is not presented as false precision |
| P8-17 | Stock intake, Sale, Return and reversal effects reflected once |
| P8-18 | Current Stock distinguished from historical Reconciliation count |
| P8-19 | Customer outstanding balance against Receivable evidence |
| P8-20 | Partial/settled/overdue derivation and due-date boundary |
| P8-21 | Debt payment allocation, adjustment and compensation counted once |
| P8-22 | Customer private fields remain masked without authority |
| P8-23 | Finalized Cash variance summary and sign |
| P8-24 | Finalized Stock/Full variance summary without double counting |
| P8-25 | Dashboard Sales/Gross Profit cards equal WP1 reports |
| P8-26 | Dashboard Stock/debt/reconciliation cards equal WP2 reports |
| P8-27 | Selected period and Shop timezone consistent across cards and drill-down |
| P8-28 | Cross-Tenant and cross-Shop identifiers cannot disclose report data |
| P8-29 | Staff without report.view cannot read report/dashboard APIs or screens |
| P8-30 | READ_ONLY permits authorized reads; BLOCKED/CLOSED restrictions hold |
| P8-31 | Bounded filters, pagination and no load-all/repeated-request storm |
| P8-32 | Query plans and performance against pilot-shaped data |
| P8-33 | Cache disabled or safe isolation/invalidation proven if enabled |
| P8-34 | Export denied/not present unless approved; approved export is bounded and formula-safe |
| P8-35 | Loading/empty/error/stale/offline/access-mode states and freshness labels |
| P8-36 | Phone/tablet/desktop, keyboard and accessible chart/text alternatives |

The numbering is a planning register. During delivery, retain these IDs and add narrowly scoped cases only if a requirement cannot be adequately tested by the reserved case set. Do not delete or silently renumber a case.

## 8. Cross-package Definition of Done

Phase 8 is complete only when all of the following are true:

1. Every approved Phase 8 report and dashboard figure traces to an authoritative source record or projection owned by the correct module.
2. The report definitions, sign rules, date boundaries, inclusion/exclusion behavior and limitations are documented in plain language.
3. Daily, monthly and custom-period totals hand-reconcile to source detail using Shop timezone boundaries.
4. Returns, reversals, compensations, Credit, debt collection, expenses and Reconciliation evidence affect only their correct metrics and are never double counted.
5. Gross Profit uses historical cost snapshots, is labeled before expenses and is never presented as net profit.
6. Stock value and low-stock behavior follow Inventory's current projection and cost-state rules.
7. Customer outstanding/overdue values remain derived from Receivables, adjustments and allocations.
8. Reconciliation summaries preserve finalized snapshots and actual-minus-expected signs.
9. All routes and queries enforce Tenant/Shop scope, current membership, AccessGate and report/source permissions; no confidential cross-report leakage exists.
10. Date ranges, aggregates, pagination and exports (if approved) are bounded. Query plans are reviewed; any added index has measured justification and paired migration/SQL documentation.
11. No duplicate reporting warehouse, tables, balances or transactional truth is introduced.
12. The existing frontend conventions, plain-English policy, responsive layouts, keyboard/accessibility behavior and generated-asset build process are preserved.
13. Loading, empty, error, stale/offline and access-mode states are honest and include period/timezone/freshness context.
14. All 36 reserved manual cases have explicit observed outcomes or a documented Owner-approved disposition; no unrun case is silently marked Pass.
15. Package delivery reports, integration contracts, source matrices, manual results, defect disposition and final operations/release handover are complete.
16. The Owner explicitly accepts each package before the next begins and records whole-phase acceptance against the exact final source candidate/inventory.

## 9. Delivery controls and sequencing

- **WP1 → Owner review/acceptance → WP2 → Owner review/acceptance → WP3 → integrated Owner review/acceptance.** No package is started in parallel with an unaccepted predecessor.
- Each WP starts by re-reading the exact current `main` baseline, relevant source services, routes, permission registry, schema and current generated assets. Do not rely solely on this plan's descriptions.
- Use one focused branch and reviewable PR per package, following the repository's established delivery pattern. Cumulative prerequisites must be included or merged exactly once; compare ancestry/tree before integration to avoid duplicate snapshots.
- Before editing, record the package's source/requirement matrix and API/module/UI contracts. The plan does not authorize speculative endpoint names, migrations or permissions.
- Run the existing locked frontend build and applicable existing tests/contracts. Do not add a test framework, dependency or CI workflow as part of Phase 8.
- Execute the native manual checks in the supported PHP 8.2+, CodeIgniter 4, Shield and MySQL 8.4 LTS environment. Browser/device/accessibility and query-plan evidence must be actual observations, not mocked claims.
- Keep historical delivery reports immutable as historical records. Later acceptance is recorded in a new dated Owner decision/closure record; it does not rewrite old evidence or manufacture case-level results.
- Source integration, database migration, production deployment, backup/restore and operational release remain separate recorded decisions. No production action is implied by package acceptance.

## 10. Planning assumptions and decisions required before implementation

1. Phase 7 is recorded closed by the Owner's explicit confirmation on 24 November 2026. Phase 8 depends on that completed Reconciliation foundation.
2. `report.view` already exists in the permission catalogue. WP1 must verify its actual runtime registration and Shop UI exposure; do not add a broad report-management permission.
3. The roadmap's “controlled export only where approved” is conditional. The Owner must approve exact export scope and authority before WP3 implements it. No export format or additional permission is presumed by this plan.
4. No new Reporting module is presumed. Begin with the existing owning modules and module-map dependency direction. If a genuinely necessary shared composition boundary is discovered, document the alternatives and obtain approval before creating it.
5. No new database table is presumed. Index changes require measured query-plan evidence and paired migration/SQL.
6. No new dependency or frontend architecture is approved.
7. The Phase 8 implementation plan itself is prepared at the Owner's request. Implementation begins only after the Owner accepts this plan and explicitly instructs commencement of WP1.

## 11. Expected final handover

The Phase 8 final delivery must include:

- accepted WP1, WP2 and WP3 delivery reports and integration contracts;
- preserved requirement/source traceability and all 36 manual case identities/results;
- report definitions and data-source ownership notes;
- any measured query-plan/performance notes and paired migration/SQL records, if an index change is approved;
- any approved export permission/contract/Audit evidence, or an explicit record that export was not approved and therefore was not implemented;
- final defect disposition, exact source/artifact inventory and reviewed commit;
- operator guidance for report freshness, permissions, bounded date filters and safe recovery;
- separate integration, release and deployment decisions.

**Phase 8 success means authoritative, understandable, responsive and permission-safe reporting—not a new accounting system, analytics platform or offline reporting engine.**
