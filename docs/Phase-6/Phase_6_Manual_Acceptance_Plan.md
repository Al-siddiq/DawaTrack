# Phase 6 — planned manual acceptance

**Date:** 12 November 2026 (Africa/Lagos)  
**Status:** Planned only — not executed; no Pass results recorded.  
**Controlling plan:** [Phase 6 implementation plan](Phase_6_Implementation_Plan.md).

This plan is the minimum phase-level coverage. Each implementation package must add any newly discovered cases and provide a commit-specific result register. A package is not accepted because a build or static check passes.

## 1. Safe setup and evidence rules

Use an isolated development/XAMPP installation with a positively identified database, a backup and synthetic Tenant/Shop data. Apply each documented migration once. Verify the PHP migration and equivalent raw MySQL 8.4 SQL. Never inject failures, manipulate financial rows or run destructive rollback against production.

For every result record: case ID, reviewed commit, environment/database version, Tenant/Shop, role and grants, fixture IDs, steps actually performed, expected and observed values, Pass/Fail/Blocked/Not run, optional screenshot/request/evidence reference, defect, reviewer and Shop-local date. Do not infer a case result from a previous package's acceptance.

Use separate browser profiles/sessions for Owner and Staff authorization. Create records through accepted application commands; do not seed posted financial movements by direct SQL. Reset scenarios by using fresh synthetic Shops, not by deleting protected history.

## 2. Roles and access profiles

- Active Primary Owner with full access.
- Staff with financial.view only.
- Staff with financial.view + financial.record.
- Staff with financial.view + financial.reverse.
- Staff with financial.view + financial.correct but without financial.reverse.
- Staff with financial.view + financial.correct + financial.reverse.
- Staff with financial.record but no financial.view.
- Staff with only legacy debt.manage or unrelated Inventory/Reconciliation grants.
- Inactive Staff/membership; READ_ONLY subscription; BLOCKED/SUSPENDED Shop; CLOSED Tenant in a disposable fixture.
- Second Tenant/Shop with independent movements.
- Platform Admin without Shop membership.

Owner-only tests must prove that even a Staff member holding every Financial Movement grant cannot create OWNER_WITHDRAWAL or OWNER_INTRODUCTION.

## 3. Deterministic synthetic fixtures

Create all financial and commercial evidence through accepted UI/API commands.

| Fixture | Setup | Expected result |
|---|---|---|
| F1 — mixed Cash sources | Finalized Opening Cash ₦20,000; Cash Sale receipts ₦12,000; Cash Sale refund ₦2,000; Cash Debt receipt ₦3,000; Cash Expense ₦1,500; Owner Cash Withdrawal ₦2,000; Owner Cash Introduction ₦5,000; linked Cash Financial Correction +₦500 | Expected Cash = ₦35,000. Each component traces to its source. |
| F2 — Transfer-only movement | In a fresh Shop with no Opening Cash, record Transfer Expense ₦4,000 and Owner Transfer Introduction ₦6,000 | Both records are valid for authorized users; neither changes physical Cash or requires Opening Cash. |
| F3 — protected correction | Cash Expense ₦1,000; reverse and replace with a linked FINANCIAL_CORRECTION of -₦800 | Original remains -₦1,000, inverse +₦1,000 and replacement -₦800; net Cash effect is -₦800. |
| F4 — failed correction | Prepare a valid correction, then revoke permission or cause a documented development-only failure between inverse and replacement inserts | Neither half, Audit event nor change row commits; original remains effective. |
| F5 — reversal | Transfer Expense ₦250 with no Cash baseline, then reverse it | Exact +₦250 Transfer inverse; no Cash requirement or Cash effect. |
| F6 — boundary and occurrence | Opening baseline at t0; record an expense with an earlier occurred_at but posted after t0; query cutoff before and after posted_at | The event is included only in periods whose posted_at boundary includes it; occurred_at does not rewrite a closed period. |

For F1, use an explicit hand-calculation sheet. The Transfer movement in F2 must not enter the F1 Cash total. A zero Opening Cash amount is legitimate where a fresh Shop requires an Opening baseline.

## 4. WP1 — schema, Expense and shared foundation

| Case | Action | Expected result |
|---|---|---|
| P6-01 | Apply the Phase 6 migration to an empty database and a populated Phase 5 copy | Exact v1.1 table created; all Phase 1–5 evidence and row counts preserved; migration order and SQL pair agree. |
| P6-02 | Inspect columns, decimal types, indexes, public UUID, Shield actor FK and same-scope Tenant/Shop/self FKs | Native MySQL constraints match the schema; no guessed actor type or missing scope protection. |
| P6-03 | Attempt invalid type/sign/channel, zero, malformed decimal, float, scientific notation, excess precision and range overflow in a disposable transaction | Invalid rows are rejected; valid values retain exact kobo precision. |
| P6-04 | Request movement history with missing/partial Phase 6 schema | Explicit setup-required failure; no fake empty list or zero movement total. |
| P6-05 | Record F1 Cash Expense after finalized Opening Cash | One negative SHOP_EXPENSE row; expected Cash decreases by exactly ₦1,500. |
| P6-06 | Record F2 Transfer Expense without Opening Cash | Valid Transfer record; no Cash guard and no physical Cash change. |
| P6-07 | Attempt Cash Expense without finalized Opening Cash and while Cash/Full control is active | OPENING_CASH_REQUIRED or CASH_CONTROL_ACTIVE; no movement/Audit/change commits. |
| P6-08 | Submit same public UUID and identical command twice, then same UUID with changed amount/channel/description | Equivalent retry returns the same record; changed content conflicts; no duplicate row or success Audit/change. |
| P6-09 | Try foreign Tenant/Shop movement UUID, altered client scope/actor and Platform Admin without Shop membership | Scoped not-found/denial; no foreign existence or data leakage. |
| P6-10 | Test financial.view, financial.record, no view, READ_ONLY, BLOCKED and inactive membership | Only explicitly authorized reads/writes succeed; no broad legacy permission shortcut. |
| P6-11 | Force a documented development-only Audit or change-publication failure | Expense and all related Audit/change evidence roll back together. |
| P6-12 | Review list/detail, amount direction, explanation, occurrence/recorded times, loading/error/empty states on phone and desktop | History is scoped, understandable, paginated and responsive; GET/preview creates no business Audit/change. |

## 5. WP2 — Owner Withdrawal and Introduction/Float

| Case | Action | Expected result |
|---|---|---|
| P6-13 | Record F1 Owner Cash Withdrawal | One negative OWNER_WITHDRAWAL; Cash guard applies; expected Cash decreases by ₦2,000. |
| P6-14 | Record F1 Owner Cash Introduction | One positive OWNER_INTRODUCTION; Cash guard applies; expected Cash increases by ₦5,000. |
| P6-15 | Record Owner Transfer Withdrawal and Introduction in F2 | Both are classified as Transfer and excluded from physical Cash. |
| P6-16 | Attempt both Owner-only commands as Staff, including Staff with every Financial Movement grant | Denied by the backend service; no row/Audit/change. |
| P6-17 | Revoke Owner membership/permissions or switch Shop to READ_ONLY between review and confirmation | Confirmation rechecks current authority/access and rejects; no stale authorization. |
| P6-18 | Repeat Owner movement UUID, double-activate Save and inspect Activity/history | One immutable movement per accepted command; retry is truthful and causal evidence is visible. |

## 6. WP3 — reversal and correction

| Case | Action | Expected result |
|---|---|---|
| P6-19 | Reverse F5 Transfer Expense | Exact opposite REVERSAL, same scope/channel, one inverse; no Opening Cash requirement. |
| P6-20 | Reverse F1 Cash Expense | Exact positive Cash inverse; Cash guard applies and expected Cash is restored by the original amount. |
| P6-21 | Duplicate reversal, reverse an already reversed movement, reverse a REVERSAL or attempt self/foreign link | Rejected or safely recovered by same identity; never more than one direct inverse or a reversal chain. |
| P6-22 | Execute F3 corrected replacement | Original, reversal and replacement remain immutable and linked; final net effect is -₦800. |
| P6-23 | Correct Cash to Transfer and Transfer to Cash | Both sides are evaluated; Cash guard applies whenever either side has a Cash effect; channel totals change correctly. |
| P6-24 | Attempt correction with correct permission but no reversal permission, then reverse permission without correct permission | Independent grants are enforced; neither permission implies the other. |
| P6-25 | Execute F4 with failure after inverse insert but before replacement/Audit/change completion | Whole transaction rolls back; original remains effective; no half-correction or false success receipt. |
| P6-26 | Attempt second parallel replacement, cyclic link, replacement of a reversal, or correction of a stale/non-effective source | Invalid chain rejected under the Shop lock; no branch/cycle or duplicate effective correction. |
| P6-27 | Revoke permission, expire session or switch to READ_ONLY after preview but before confirmation | Current permission, membership and FULL access are rechecked; confirmation is denied. |
| P6-28 | Inspect causal history and Audit for reverse/correct operations | Actor, explanation, channel, original/inverse/replacement identities and shared correlation are traceable; original rows are unchanged. |
| P6-29 | Reverse/correct a movement whose channel is CASH while a Cash control is active | Rejected with the active-control conflict; Transfer-only cases remain unaffected. |
| P6-30 | Verify same-UUID retry after committed-but-lost response and changed-body conflict | Status/detail recovers the original result; no second movement identity or automatic retry. |

## 7. WP4 — expected Cash and integrated closure

| Case | Action | Expected result |
|---|---|---|
| P6-31 | Hand-calculate F1 and compare with the internal expected-Cash component query | Exact ₦35,000; each component and source agrees. |
| P6-32 | Add F2 Transfer movements to the same logical comparison | Transfer does not change expected physical Cash. |
| P6-33 | Compare F3 original + reversal + replacement | Net Financial Movement Cash effect is -₦800, counted once; no double subtraction. |
| P6-34 | Query at t0, before/after posted_at, and with backdated occurred_at (F6) | Strict (period_started_at, cutoff_at] posted-time boundaries; no retrospective change to a finalized period. |
| P6-35 | Integrate Cash Sale receipts/refunds and Cash Debt receipts with Expenses/Owner movements | All approved sources contribute once; Credit and Transfer do not become physical Cash. |
| P6-36 | Reverse/re-correct Owner and Expense movements and inspect component attribution | Reversal is attributed to the original type; replacement correction enters correction net; signed total remains exact. |
| P6-37 | Attempt to create a Financial Movement automatically from a Cash variance or to overwrite expected Cash | No such route/behavior exists; a variance is not an automatic business event. |
| P6-38 | Test scoped filters, pagination, same-UUID detail, privacy and cross-Tenant access | Stable order/totals, bounded reads, no internal IDs or foreign movement leakage. |
| P6-39 | Complete the full workflow on phone, tablet, desktop and keyboard; test offline/timeout/error states | Accessible cards/forms, retained input, honest unknown outcome; no offline financial write or queue. |
| P6-40 | Review all package evidence, source matrix, migration/SQL, defect disposition, operations and rollback handover | All requirements mapped; actual observations distinguished from static checks; Owner makes explicit whole-phase decision against a concrete commit. |

## 8. Lost-response, concurrency and fault-verification requirements

The package handover must identify the exact route, UUID and safe native mechanism for each uncertain-outcome test. A development debugger break after the owning transaction commits, followed by client timeout and same-UUID status/detail retrieval, can prove a committed lost reply. A break before the request reaches the server proves only a not-sent case.

Use two independent sessions for duplicate and reversal/correction races. The Shop serialization boundary must allow at most one direct inverse and one valid linear correction chain. Any losing command receives a stable conflict and safe refresh/review guidance.

Inject failures only on an isolated development database with a documented mechanism. Never disable Audit, edit posted financial rows manually or introduce artificial delays into production. If the required environment/mechanism is unavailable, record Blocked/Not run and resolve the gate before claiming verification.

No automated test framework or suite is introduced.
