# Phase 7 WP2 — delivery report

**16 November 2026 (Africa/Lagos). Implemented for Owner native manual verification; not Owner-accepted, merged or deployed.** WP1 is [Owner-accepted](../acceptance/2026-11-16-phase-07-wp1-owner-acceptance.md); WP2 only authorized. Native individual outcomes were not supplied with acceptance and are not inferred.

## Delivered behavior

- Real standard CASH start → blind count/save → atomic count commitment → review/explanation → finalization, with preserved reasoned abort.
- Server-selected latest finalized Cash actual as baseline, exact source-owned Cash component snapshot, fixed cutoff and generation-bound authoritative watermark. No client-selected scope/expected/time/baseline and no mutable Cash balance.
- Actual-minus-expected variance with shortage/surplus signs, zero-count semantics, revision conflicts and immutable committed/finalized observations. Prior actual rolls forward once; no automatic Cash/Stock movement.
- Frozen set of all ACTIVE same-Shop Devices, typed per-Device causal start Audit and count/digest validation, bounded readiness presentation, real current-cookie target-bound five-minute receipt and actual immutable ACK/Owner exclusion.
- Current member/grants/Owner/FULL checks inside owning root transaction; signed readiness binds member/Device/control/generation/cutoff/exact sequence, never a cursor/outbox-drain claim. Generation mismatch blocks progress; abort remains safe and does not decrypt receipts.
- Historical ON_DUTY/COUNTER/REVIEWER/FINALIZER context, known duty windows only, observing counter distinguished from committer, frozen final evidence and append-only typed variance notes up to2,000 characters.
- Separate blind/review DTOs. Revealing source screens, predecessor financial detail and historical notes cannot bypass active counting blindness. Blind-safe source reference check returns existence/state only with independent source grants.
- Existing Shop Menu integrated Cash workspace with permission-aware actions, source/reference recovery, paging/history/Staff context/notes, same-control/note identities, input retention and no reconnect POST/offline queue.
- Six paired forward migrations: redundant scoped candidate keys needed for MySQL8.4 same-control/source/Device FKs, then five full approved evidence tables. Stock-count schema is prerequisite only; no Stock/Full/adjustment flow is activated.
- WP1 acceptance record, full integration/operations handover, native results/gap register and **phase-style manual verification content** as requested.

## Manual guide format adjustment

[WP2 native guide](../manual-verification-phase-07-wp2.md) follows earlier phase guides: title/status, checkout/build/inventory/migration setup commands, synthetic actor/Device/money fixtures, individual **P7-13–P7-24** steps and expected outcomes, explicit not-sent vs committed-but-lost recovery, controlled native rollback locations, then results/package boundaries. It is not just a link to the strategic acceptance index.

F1 fixture explicitly uses6 units×₦2,000 and a1-unit refund for an exact ₦12,000/₦2,000 composition, plus Cash Debt and financial events. Target expected₦35,000; physical₦34,500 → shortage−₦500; next actual baseline₦34,500 plus genuine₦1,000 → expected₦35,500. These remain native expected targets, not newly observed runtime Pass.

## Engineering observations and limits

| Check | Observed outcome |
|---|---|
| Frontend build | Passed with existing locked dependencies; Node v24.22.1/npm11.9.0; existing Sass deprecation warnings; no project dependency changes |
| PHP grammar | All293 App PHP files parsed with PHP8.3 grammar, not native PHP execution |
| Published JavaScript | All51 public modules syntax-checked; source/public graph/parity verified by release inventory |
| Cash UI scratch host | Actual Vue component with mocked API: blind Counter rendering, lost count reply/same-ID-only marker/no repost, explicit status recovery/input retention, server review/sign rendering,2,000-character note field, lost note identity recovery and offline financial-data clearing passed; dialog methods stubbed, not real browser/HTTP acceptance |
| WP1 UI regression | Device mocked host enrollment/lost-reply/same-marker/Staff own-only/offline clearing passed; information notices now use correct shared component interface |
| Migration twins | Six migration/SQL pairs, seven DDL statements compared exactly; native FK/CHECK/DDL execution not performed |
| Pinned Device SQL shape | Six actual-query observations on private SQLite fixtures: unresolved membership, retained revoked obligation, no later-enrollment inclusion, exact exclusion/actor join, Tenant scope and causal-root separation. Not PHP service/MySQL locks/DECIMAL execution |
| Exact inventory | 439 files; content SHA-256 `b430c602b462563feeeb72cc340c4e9fd25d844d17703747abfab90bfc94ff9a`; manifest-file SHA-256 `a79d3cff49cfc7a125997bf96423abe829d69087c8e7155431c914d814718702` |
| Documentation / traceability | Relative targets/CSV shapes/32 requirements/48 original integrated case identities/12 WP2 identities and no inferred native Pass verified; whitespace check |
| Native runtime / operations | PHP/Composer/MySQL are unavailable in this workspace. Native migrations/constraints/DECIMAL, PHP/OpenSSL proof execution, HTTP/session/CSRF/real cookies, concurrency/rollback/generation recovery, real device/accessibility/performance/backup restore not executed or claimed passed |

No automated test framework/suite or project dependency was added. Scratch tooling/probes remain outside the repository under work. Result rows remain Not run. The UI probe found incompatible shared status-panel props and WP2 corrects Cash and inherited WP1 Device notices to use the proper information-panel interface. Source review additionally closed historical-control/notes as a blind-read bypass and preserved the actual physical observer's Staff role when another user commits.

## Source / schema / release review

[Contract](Phase_7_WP2_Integration_Contracts.md) · [Native guide](../manual-verification-phase-07-wp2.md) · [Results](Phase_7_WP2_Manual_Results.csv) · [Gap disposition](Phase_7_WP2_Defect_Disposition.csv) · [Operations](../operations/phase-07-wp2-cash-control.md) · [Artifact inventory](Phase_7_WP2_Artifact_Manifest.json).

Base is accepted WP1 `676fb09`, including accepted planning prerequisite `bf5a358`; observed main remains `4266400` (Phase6 #48/#49). Branch `codex/phase-7-wp2-cash-reconciliation-20261115` is cumulative and targets main directly. If integrating packages sequentially, recheck comparisons/ancestry after prior merges; do not merge duplicate snapshots or repeat squash without checking the stack. Accepted source is distinct from actual migration/deployment. Prior inventories and historical results remain preserved.

Before use, take an identified isolated backup/restore, inspect existing WP1 migration status and apply only the six new migrations in dependency order with native MySQL8.4. Verify existing private encryption.key/OpenSSL setup for readiness and actual secure Device cookies. Use migration OR raw SQL per database, never both. Check the recorded exact inventory; no regeneration to conceal changed content. Protected evidence/down guards and compatible code/assets recovery are documented.

**Next gate:** Owner native WP2 verification and explicit package acceptance. WP3 Stock/Full/Inventory adjustment/complete late-operation association and WP4 phase closure remain unauthorized/unimplemented. No main merge, database migration, secret/configuration change, financial mutation or production deployment was performed by this delivery.
