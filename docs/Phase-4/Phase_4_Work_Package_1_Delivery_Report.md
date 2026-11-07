# Phase 4 Work Package 1 delivery report

Date: 2026-11-02. Plan: owner-approved four-work-package plan. Baseline: `8fcee20ad1760ec0f81a255ae8ed7831867c84e7`.

Status: IMPLEMENTATION DELIVERED FOR OWNER MANUAL VERIFICATION. This is not owner acceptance, production deployment or Phase 4 completion. Implementation belongs on the WP1 review branch; production/main application behavior must not be changed by applying migrations without review.

## Delivered capability

Owner navigation now provides Opening Cash with one physical amount and explicit COUNTING → COUNTS_COMMITTED → REVIEW → FINALIZED steps. Start UUIDs survive uncertain submission within the page; authoritative state reload is the recovery path. Saving a count and explicitly confirming it are different steps. Unsaved edits cannot be confirmed as if saved. Zero is valid. Finalized and aborted observations have no edit/delete surface. A reasoned retained abort enables another attempt only before any finalized Cash baseline.

The two ordered migrations create full v1.1 reconciliation_sessions and reconciliation_financial_counts definitions with scoped/self FKs, exact amounts, component formulas and lifecycle constraints. Paired raw SQL is included. No Sale/Customer/Device/receipt table or offline transport is added. Initial start metadata is provisional; confirmation binds the fixed UTC Cash start/cutoff, server generation and watermark. Generation changes after confirmation reject review/finalization and require abort/reconfirmation.

CashControlGuard requires the caller's transaction, locks/checks Tenant/Shop access, requires a finalized OPENING/CASH baseline and rejects overlapping active Cash control. It is the future Sales/refund integration point, not a fake Cash-sale endpoint. Pure Transfer/full-credit callers skip this Cash-only guard. CommercialDecimal supplies exact quantity/price/COGS primitives only; no commercial posting is implemented.

OpeningCashService revalidates current Owner membership under lock and commits each command's rows, Audit and change together. Read APIs enforce Owner scope and safe bounded projection/no-store. Staff grants do not grant first Cash-baseline authority. READ_ONLY views but cannot mutate; BLOCKED/CLOSED deny. Runtime-rendered Vue avoids CSP-unsafe template evaluation; mobile wrapping styles, labels, keyboard-native controls, pending/error and offline-no-queue states are included.

## Foundation findings disposition

| Finding | WP1 disposition |
|---|---|
| MFA freshness renewed by redirect | Removed from Auth::loginRedirect; established only after successful native Shield Email2FA verify completion for the pending same Admin identity. GET redirects and invalid challenge responses cannot establish freshness. |
| Intake draft/post race | Every edit/item mutation/discard now uses an owning transaction and the posting header FOR UPDATE lock, rechecking DRAFT after lock acquisition. |
| Staff reset metadata rejection / rollback side effect | Approved access_invalidated metadata replaces forbidden session key; remember/magic-token deletions stay transactional; cache revocation runs only after successful database commit. Response is no-store. |
| DNS rotation/verification race | Final verification compares the exact current challenge hash as well as PENDING status. |
| Platform hostname configuration drift | HostnamePolicy uses DAWATRACK_PLATFORM_DOMAIN consistently. |
| Frontend build gaps | Build compiles Shop template, copies runtime Vue, generates assets and excludes backup files from public output. |
| Session API expiry path | Uses shared RequestPath::isApi normalization. |
| Durable session revocation, generic recovery responses, readiness depth | Remain tracked follow-up; not falsely marked fixed. Cache invalidation can still fail/lose state; full security hardening is not claimed. |
| Pricing/bootstrap documentation disagreement | Existing commercial-policy follow-up, not changed by Opening Cash. |

The owner's accepted Phases 1–3 record remains unchanged. Focused regression checks are included; no whole-phase re-acceptance is demanded.

## Verification executed

| Check | Result / actual boundary |
|---|---|
| Frontend dependency installation | PASS: npm ci from existing lock; no new project dependencies |
| Frontend build | PASS: npm run build, including template compilation/runtime assets; existing Bootstrap/Sass deprecation warnings remain |
| PHP syntax | PASS: 190 application files plus the contract script, using isolated PHP.wasm 8.5.10; this is not a native server test |
| PHP 8.2 source compatibility | PASS: all 190 application PHP files parsed with pinned Composer dev dependency nikic/php-parser targeting PHP 8.2 |
| Framework/Shield class loading | PASS: Composer autoload resolves new controller/service and native Shield-derived MFA action |
| Focused executable contracts | PASS: 20 CashAmount/Audit cases via tests/contracts/wp1.php, including precision bounds, invalid JSON floats and unchanged sensitive-key rejection |
| JavaScript syntax | PASS: source/build modules checked; generated assets validated against source |
| Vue runtime DOM workflow | PASS: isolated DOM harness with mocked API verifies start/count/unsaved-edit guard/confirm/review/final receipt and offline state; five command writes. Does not establish backend or visual browser acceptance |
| Migration/raw-SQL equivalence | PASS: two matching SQL definitions; static inspection only, no MySQL execution |
| Whitespace/diff check | PASS: git diff --check |
| Native PHP/MySQL acceptance | NOT EXECUTED here: no native PHP/MySQL 8.4 server. Isolated PHP dependency install omitted unavailable ext-intl; production still requires native supported extensions. No substitute SQLite acceptance is claimed |
| Browser layout/accessibility acceptance | NOT EXECUTED here: Playwright browser download unavailable. DOM checks do not establish mobile layout/keyboard/zoom acceptance |
| MySQL concurrency, authority, rollback injection, Cash guard and MFA HTTP workflows | PENDING OWNER EXECUTION using the delivered checklist; no fabricated PASS results |

Reproduction on the normal development environment: `npm ci && npm run build`; `php tests/contracts/wp1.php`; PHP lint; apply the two App migrations in the existing explicit bootstrap order; follow the linked manual checklist. The temporary PHP.wasm/DOM tools are verification utilities outside the repository, not new runtime dependencies.

## Delivery files and operator instructions

New backend: Reconciliation CashAmount, CashControlGuard, OpeningCashService, OpeningCashQuery and OpeningCashController; two migrations/paired SQL; CommercialDecimal primitive. Source UI: resources/js/components/opening-cash-panel.js and Shop navigation/composition/template/style changes, with generated assets. Existing registries/routes/security/draft/build integration are updated.

- [Integration and decision contracts](Phase_4_WP1_Integration_Contracts.md).
- [Owner manual verification](../manual-verification-phase-04-wp1.md).
- [Operations and development rollback](../operations/phase-04-opening-cash.md).
- API, permission/security, Audit, change-sequence, module map and migration register now describe WP1.

Do not run both raw SQL and migrations. Financial counts precede sessions on rollback. Never drop accepted Phase 1–3 data, patch finalized Cash amounts, silently switch generation/cutoff or claim existing Sales works: Sales is still absent.

## Acceptance and next-package boundary

Owner decision: PENDING. Record tested commit/environment, checklist results and any requested correction. WP2 remains unimplemented.

The WP1 contract file records routine integration choices and highlights future decisions requiring review. In particular, the existing schema's normal SALE header/new-Receivable rule needs an explicit interpretation/amendment for compensating a credit Return into the existing Receivable. Return settlement/cost proposals also need review before WP4. This delivery does not invent accounting rules or imply owner approval of those proposals. Resolve each source-level ambiguity before its dependent implementation; independent Opening Cash is delivered for manual verification now.
