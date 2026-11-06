# WP4-D integrated operator and query contract

Date: 2026-11-06. Based on approved WP4 A/B/C contracts; implementation is ready for owner verification, with native/visual gates still pending.

## Operator flow

Record Sale has ordered Product selection/addition, basket review and payment entry. Customer/time is optional for fully paid Sales; credit requires a named Customer saved before review. Product choice fills price; ordinary override rules remain. Review is not posting. Explicit checkbox confirmation posts once, and the posted view links into Sale history/Returns. Total and checkout sections stay in document flow; only the compact confirmation action is sticky. Cancellation is a separate expandable reasoned action.

In Sale history open an original posted Sale, choose Return Products, save original-line quantities, calculate debt-first settlement, enter the permitted actual refund split/reason, review and confirm. Saved Return drafts can be resumed through the record-type filter. Empty/unsaved selections cannot proceed. Historical prices/inactive references and all B server guards remain authoritative. Same-identity status retrieval resolves uncertain outcomes before further writes. Safe readers without return authority can inspect saved lines without edit capability.

Receipt corrections compensate the complete eligible ordinary operation. Preview states money direction and existing-debt cancel/restore; reason and checkbox are required. Backend dependency errors are shown with source references. A reversed source may prepare one replacement draft; Sale replacement opens the ordinary cashier, Return replacement opens the original-linked Return editor. Both need separate normal confirmation. Compensation has no reverse button. Parent navigation/reload is disabled while child mutations are pending; stale component results do not replace another selected source.

## Unified reads, preserved command contracts

The explicit B Return and C correction endpoints remain supported unchanged; see phase-04-wp4-b.md and phase-04-wp4-c.md. The operator UI normalizes command results through unified GET /api/v1/shop/sales/{uuid}; writes still use the distinct approved B/C command routes, body fields, UUID/version/fingerprint and permissions.

| Read | Behavior |
|---|---|
| GET /api/v1/shop/sales | kind SALE (backward-compatible default), ALL (all posted), RETURN, COMPENSATION, DRAFT_RETURN; bounded 50/page; search by Customer name/reference, UTC posting date filters; response retains data list + meta.pagination |
| GET /api/v1/shop/sales/{uuid} | Existing normal Sale draft representation or unified normal Sale/Return/compensation detail; Return draft attaches original choices only with sale.return |
| Related records | Root original, immediately compensated source, direct reversal and replacement; latest 50 normal related Returns, with additional records accessible through paginated history |
| Costs and profit | sale.profit.view required; no unit cost/COGS/margin in safe item projections without it. Provisional provenance follows original Sale evidence, with signed commercial effect |
| Stock proof | Normal item → own SALE_ITEM movement; compensation item → immediate source item → original movement → linked REVERSAL movement, retaining NULL sale_item_id on inverse |

Reads require sale.view and existing scope/access gate. sale.return and sale.reverse remain independent; current server authority is rechecked at confirmation. Customer name/public identity is permitted commercial context; private contacts are not added. Receipts clearly separate original credit created from later debt reduction/restoration/cancellation. They are not collection statements or net-profit reports.

Browser refresh restores saved quantities/Customer/business time; transient payment/refund preparation, reason and confirmation are reviewed again. Offline has explicit unavailable text and no queued success. Lost responses retain operation identity and disable blind reposting; retrieve actual source/operation state. Newly created Return/replacement read-back does not rely on partial command response shapes. Named record components and record-object hydration prevent prior editor state leaking into another draft.

## Printing and diagnostics

Print creates a temporary isolated clone of the already permission-filtered receipt, opens linked stock evidence, removes controls, invokes printing and cleans up. CSS displays only the clone during printing. Browser/paper validation is still required.

sales:diagnose owns one REPEATABLE READ / READ ONLY snapshot, never repairs/publishes/Audits. Its 18 SQL categories sample at most 100 anomalies each: posted line totals, normal Sale arithmetic/composition, payment/Receivable scope, adjusted-debt lower/upper bounds, normal Return settlement/source/effective quantity/value/COGS limits, compensation header/line/inverse-payment/missing-payment/settlement/adjustment graph, missing inverse lines, effective refund channel caps and replacement graph. A 19th category compares stored cost state with immutable per-Product reconstruction (up to 100 anomalies). Snapshot commit failure rolls back its owned transaction. inventory:diagnose supplies physical movement/position and indirect compensation item coverage. Zero sampled findings are not certification; collection allocations remain outside the Phase 4 balance contract.

No schema migration is introduced by D. Apply A once through Spark; do not also run paired SQL. Respect preserved-evidence downgrade guards. [Integrated verification](../manual-verification-phase-04-wp4-d.md) and [closure status](../Phase-4/Phase_4_Integrated_Closure_Status.md) distinguish engineering checks, blocked live-browser/native gates and final owner decision.
