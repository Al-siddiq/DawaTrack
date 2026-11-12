# Phase 5 WP4 — Customer statements and integrated release handover

**Closure update, 12 November 2026:** [Owner accepted WP4 and the whole phase](../acceptance/2026-11-12-phase-05-owner-closure.md). Phase 5 is closed. Earlier Pending decision language below describes delivery time; individual native results/runtime details not supplied remain unreported. Integration/deployment is separate.


12 November 2026 (Africa/Lagos). **WP4 and the complete Phase 5 programme are now Owner-accepted and closed.** The original engineering observations below retain their execution limits; actual merge/rollout remains separate.

[WP3 acceptance](../acceptance/2026-11-12-phase-05-wp3-owner-acceptance.md) is recorded against accepted source `9e81e769af4b588858de8f522a0ef62022331e11`. New branch `codex/phase-5-wp4-statements-closure-20261112` retains the accepted prerequisites and targets main directly. PR #41 remained open, main at `b41425f905100a728a4c6167d5df936702a8502a`, when progression was checked. Capture final reviewed candidate SHA from PR or `git rev-parse HEAD`; no prerequisite integration/deployment is assumed.

## Delivered

- One scoped read-only Customer statement endpoint with strict Shop-calendar dates, exact opening/range/current totals, carried running debt and linked credit/payment/return/bill-change details.
- Canonical posting order and exact-once effects, including normal/inverse payments, atomic replacement rows, generic adjustments/inverses, grouped commercial changes and zero-debt Return/refund context. Allocations explain payments without another subtraction. All-history sum reconciles with the live Receivable projection.
- Encrypted authenticated page/checkpoint anchors bound to scope/user/filter/size/timezone, generation/relevant changes and fixed30-minute expiry. Previous-first and full Print use the original version; a changed/expired page requires refresh. No mutable statement/cursor table.
- Contextual Customers screen with labelled icons, accessible fields/loading/validation, page/period totals, no stale offline fallback, independent view/Sale link authority, safe labelled page printing and all-or-nothing full-range Print up to2000 records. Larger histories remain fully readable/page-printable; use a shorter range for one full copy. Private current total and sensitive source fields stay off customer print.
- Shared HTTP signal fix: caller-provided cancellation previously bypassed the request deadline. Both now reach fetch; explicit abort/deadline/cleanup and uncertain POST status0/no retry are preserved. Truncated/incomplete2xx replies are treated as uncertain rather than clearing a possibly accepted payment identity. Earlier money/recovery host workflows rechecked.
- Comprehensive API/domain/cursor/configuration/privacy/operations handover;30-row requirement/source/case matrix,48-case integrated result register,12 WP4 native cases, defect/gate disposition, final acceptance template and separate release decision register. Prior acceptance/inventory evidence preserved.

**No new migration, dependency, financial writer, accounting/export product, offline transport or Phase6 implementation.** Existing private deployment `encryption.key` (strong, at least32 characters) and OpenSSL are required for safe statement paging; setup is in the manual/operations guide. No actual key is generated/committed by this package.

## Engineering checks observed

| Check | Observed result and limitation |
|---|---|
| Production build | Passed, `Frontend assets built.`; existing Bootstrap/Sass deprecations.34 bundled icons,17,008 bytes/5,102 gzip unchanged |
| App PHP grammar |253 files parse as PHP8.3 grammar; not native PHP/OpenSSL execution |
| Published modules |47 JS modules parse;143 local import/re-export edges resolve |
| Statement SQL |14 assertion statements, including12 running-prefix iterations, on actual projection/keyset SQL with SQLite integer fixtures: four pages, opening750/changes350/closing1100, same-time ordering, backdated occurrence, zero-debt Return, grouped adjustment, scope/draft exclusion and disjoint keys. Not MySQL/DECIMAL/posting |
| New Vue host probes |16 rendered variants; actual handlers for three-page/previous-first, filter reset, stale/denied/setup/foreign/empty/offline/large states, page/full45-record Print/privacy, native-print detail hooks, cancelled-old/new print race, stale full Print no partial copy, existing Customers route and restricted Sale links; zero Vue warnings. Mocked API/DOM host, not native browser/HTTP |
| Shared HTTP client |Six focused scenario groups against actual client with mocked fetch: supplied-signal timeout, parent/pre-abort, uncertain POST/no retry/mutation cleanup, success listener cleanup; truncated/incomplete2xx remains uncertain |
| Earlier package regression |WP1 ten read variants, WP2 twelve payment/recovery/Print variants, WP3 sixteen correction/recovery/access variants;21 prior settlement/diagnostic SQL assertions plus9 projection/four receipt-allocation scenarios retained |
| Schema parity |Both WP1 migration/raw SQL pairs unchanged/equal; no WP4 DDL |
| Release inventory |391 raw files; `123635cdb74a403ab06f1288f27c828f1ca6d9a6c1b4abab64f193ffdeadfb80`; source/published parity |
| Clean archive / line endings |382 simulated CRLF text files restored to all391 exact bytes; repeated check passes; genuine edits reject before repair. Not native Windows |
| Documentation |30 requirement IDs,29 navigation rows and788 prior wording entries retained;85 new copy entries (873 total);12 WP4 Pending native rows and48 integrated rows distinguish package decisions from actual results |

No automated suite/framework or scratch probe is committed. Native PHP/cursor encryption/expiry/timezone execution, MySQL constraints/DECIMAL/locks/rollback, authenticated HTTP/security/CSRF/session, true concurrency and real device/keyboard/accessibility/Print/deployment were not executed here. These remain target manual observations, not implied Pass results from build/parser/SQLite/Vue checks.

## Handover and next gate

[Native setup/P5-37–P5-48](../manual-verification-phase-05-wp4.md) · [Complete developer/API contract](Phase_5_WP4_Integration_Contracts.md) · [Operations/private-key setup/rollback](../operations/phase-05-statements-release.md) · [Coverage/source matrix](Phase_5_Requirements_Matrix.csv) · [Engineering evidence](Phase_5_WP4_Engineering_Evidence.json) · [Closure checklist](Phase_5_Closure_Checklist.md) · [Defect disposition](Phase_5_Defect_Disposition.csv).

The [integrated48-case register](Phase_5_Integrated_Manual_Results.csv) preserves prior Owner decisions without inventing detailed observations. The [dated Owner acceptance](../acceptance/2026-11-12-phase-05-owner-closure.md) now closes the whole phase; the [template](Phase_5_Final_Acceptance_Template.md) is retained only for a future explicit version/re-acceptance decision. Record actual merge/deployment separately in [release decision](Phase_5_Release_Decision.csv). WP4 code rollback can use a coherent compatible accepted WP3 release; no database/evidence rollback or pre-collection code against later financial evidence.

**Phase 5 is Owner-accepted and closed on 12 November 2026.** The closure does not merge, deploy or begin Phase 6. See the dated decision and closure report for the current integration/evidence distinction.
