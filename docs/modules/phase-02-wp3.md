# Phase 2 WP3 — scoped Audit experience

WP3 provides complete read-only Audit experiences for Primary Owners and Platform Administrators. `AuditQuery` validates bounded filters, `AuditCursor` protects stable keyset continuation, `AuditQueryService` enforces scope on list/detail/related reads and maps through the WP1 redaction boundary, and `SupportAuditAccessService` establishes evidenced diagnostic access.

Owner scope is derived from `ShopContext` and includes the current Shop plus Tenant-wide evidence. Normal Platform scope contains only events without Tenant scope. Tenant diagnostic access is a distinct reasoned workflow and never grants Tenant membership or impersonation. Every continuation/detail request revalidates the support evidence, operator, target, and 15-minute lifetime.

The reusable `AuditPanel` renders chronological cards rather than a squeezed table, bounded filters, load-more pagination, safe detail, causal/correlation context, empty/loading/error states, and a conspicuous support banner. It adds no mutation, export, sync, Device, or correction controls.

No DDL was needed. Audit reads emit neither Audit nor change evidence; support entry emits SUPPORT Audit only. Existing WP1/WP2 runtime-verification debt is preserved in the Phase 2 closure report.
