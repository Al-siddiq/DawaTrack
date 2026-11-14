# Phase 6 WP1 — delivery report

13 November 2026 (Africa/Lagos). **Implemented for Owner manual acceptance; not accepted, merged or deployed.** Only WP1 is delivered. The approved plan prerequisite from PR #44 is included and the implementation targets main directly, avoiding an unmerged implementation stack.

## Delivered behavior

Money movements appears once in the existing Shop sidebar/Menu. An authorized user enters a positive amount, Cash or Transfer and a short explanation; reviews the effect; confirms; and receives immutable saved detail. History has channel, Shop-date filters and bounded paging. Cash expenses reuse Starting cash/control restrictions; Transfer expenses do not require that Cash baseline and never affect physical Cash. Existing brand, offline bundled icon, shared fields, loading buttons, modal and readable recovery/error patterns are reused.

The new additive financial_movements migration and exact raw MySQL 8.4 twin provide the approved v1.1 schema, scoped foreign keys, signed exact money and constraint/index foundation. Four independent financial permissions are registered, without granting Staff new authority. Only expense posting is active. Server-owned scope/type/sign/actor/time, required FINANCIAL Audit, change publication last and one owning transaction protect the evidence. Equivalent same-UUID retry adds no second success; different content conflicts. A lost response recovers by scoped status read with only a UUID retained in the browser, not a stored expense body or offline queue.

[Developer/integration contracts](Phase_6_WP1_Integration_Contracts.md) document DTOs, source ownership, transaction order, review/duplicate semantics, data exposure and future-package boundaries. API, module, permission, Audit, migration, UI navigation and wording references are updated. A malformed quoted DECIMAL comma in the planning requirements CSV was repaired while preserving all 30 requirement IDs. The navigation map retains all prior destinations and adds one; wording IDs remain stable.

## Engineering verification actually performed

| Check | Observed result | Limitation |
|---|---|---|
| Production asset build | Passed; Frontend assets built | Existing Bootstrap/Sass deprecation notices remain |
| PHP grammar | 262 files parsed using PHP 8.3 grammar | Parser only; no native PHP runtime |
| Published JavaScript/import graph | 48 modules / 149 local edges checked | Not browser execution |
| New migration/raw SQL | Exact parity; 16 columns, index/FK/CHECK/source ownership reviewed; prior migration pairs unchanged | No native MySQL DDL or enforcement |
| Scoped SQL shape | Eight actual repository/history queries on SQLite text-money fixtures passed | No MySQL DECIMAL/locks/posting simulation claim |
| Expense UI | 20 actual Vue host render/workflow variants passed; zero Vue warnings | Mock API/custom host, not a real browser |
| Existing Debt workflows | Payment host probes, 16 correction host scenarios/15 assertions and 16 statement host variants passed | Mock API; no native HTTP or database acceptance |
| Exact release inventory | 402 files; `192afabc44ef5b2956d318bba6988006c6a6f9ecebf012043117a34e069c9adb` | Source inventory, not runtime approval |
| Windows conversion recovery | Archive without Git passed; 393 text files converted to CRLF then restored exactly across 402 inventory entries; repeat check passed; true edit rejected without partial repair | Host simulation, not native Windows |
| Git whitespace | Passed | Source check |

Probes were scratch review aids; no automated test framework/suite or dependency was added to the repository. The runtime environment has no native PHP/MySQL acceptance execution. No native migrations, authenticated HTTP/CSRF, database concurrency/rollback, device layout, keyboard/accessibility or Windows runtime acceptance is claimed.

## Manual handover and next boundary

Follow [P6-01–P6-12](../manual-verification-phase-6-wp1.md) and record observations in [WP1 manual results](Phase_6_WP1_Manual_Results.csv), all initially Not run. Native schema checks must inspect actual CHECK definitions/enforcement in addition to runtime readiness structure. Database DECIMAL coercion is distinct from API lexical decimal validation. Single-movement physical Cash effect is delivered; aggregate expected Cash remains WP4.

The [release/rollback handover](../operations/phase-06-wp1-release.md) preserves all protected evidence. Owner Withdrawal/Introduction (WP2), undo/correction (WP3), expected-Cash composition and phase closure (WP4) remain unimplemented. Proceed to WP2 only after explicit WP1 acceptance. The migration is required before using this package; no production change has been made.
