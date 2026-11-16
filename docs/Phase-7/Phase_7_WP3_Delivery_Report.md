# Phase 7 WP3 — delivery report

**16 November 2026 (Africa/Lagos). WP2 Owner-accepted; WP3 implemented for native manual review. WP4 not authorized.** [Acceptance source](../acceptance/2026-11-16-phase-07-wp2-owner-acceptance.md). No individual native outcome is inferred from package acceptance.

## Delivered behavior

- Shared Cash/Stock/Full lifecycle with stable Cash API wrappers, server-selected scope, independent Cash/Stock predecessors and common Full cutoff. Full commits and finalizes observations atomically; reasoned abort retains evidence without advancing chains.
- Frozen complete Stock-position membership, Product identity/unit, exact quantity/average cost and provenance state/version; immutable per-Product causal Audit plus count/digest integrity. Inactive, zero and negative expected Stock included; later master changes do not rewrite the snapshot.
- Exact four-decimal nonnegative actual observations, explicit zero, stale-row and whole-set revisions, blind paginated search/progress, post-commit authorized review/explanations, immutable normalized variances and scoped deferred Stock Movement FK.
- Actual observer/committer separation, historical Staff context and full-length append-only source-specific notes for Stock and Full Cash/Stock variances.
- Inventory-owned reasoned preview/confirm adjustment against current Stock, preserving later movements/current cost rules. One effect per latest eligible finalized Stock variance; exact original-ID replay, stale-preview protection, protected generic reversal, no automatic alignment or Cash balancing.
- Typed deduplicated late-source Audit associations and control refresh publication in the owning source transaction before generation locking. Original occurrence/posting times and old snapshots/baselines remain unchanged.
- Existing Shop Menu integration for separate keyed Cash/Stock/Full pages, predecessor navigation, paginated counts/history/readiness/Staff/notes/late evidence, per-line draft retention and source-linked adjustment recovery. Cash recovery marker remains backward compatible. Unknown adjustments retain IDs/action only and never reconnect/repost automatically.
- One paired forward FK migration with preflight, MySQL8.4 scoped candidate key and guarded down. No new table/column/dependency/setting/secret or implicit Staff grant.

## Manual review and engineering evidence

[Phase-style native guide P7-25–P7-36](../manual-verification-phase-07-wp3.md) follows the earlier phase format: checkout/build/inventory/migration setup; actors/Device/Stock/Full fixtures; individual steps and expected results; controlled fault/lost-reply procedures; results and package boundary. [12-case register](Phase_7_WP3_Manual_Results.csv) remains **Not run**. F3 target: frozen51/actual48/variance−3, later Sale−2 produces49, separate adjustment−3 produces46; original evidence never changes.

| Engineering observation | Result and limit |
|---|---|
| Frontend build | Passed using locked dependencies; existing Sass deprecation warnings; no dependency changes |
| PHP grammar | All303 App PHP files parsed with PHP8.3 grammar; not native PHP execution |
| Exact Stock arithmetic | Actual StockCountAmount PHP8.3 WebAssembly execution passed9 exact observations (zero/fraction/sign/F3/cancellation/max range),5 rejected formats and overflow rejection; not native service/MySQL execution |
| Published JavaScript | All51 public modules syntax checked; source/public graph/parity verified by inventory |
| Stock/Full UI scratch probe | Actual Vue component with mocked API/dialog methods: Stock blind render/explicit zero/progress; frozen cost/shortage review; selected-variance note;49→46 adjustment preview; lost-reply IDs-only marker and GET recovery without repost; Full unsaved Cash retained across Stock save/commit disabled; offline clearing passed |
| Cash and Device UI regression | Existing mocked Vue host probes passed; actual cookies/CSRF/HTTP/Device readiness and browser interaction not executed |
| New migration twin | Two DDL statements compared exactly; native MySQL FK/CHECK/DDL execution not performed |
| Earlier evidence migrations | Six WP2 pairs/seven DDL statements still identical; six actual pinned-membership query observations on scratch SQLite passed after shared repository move |
| Late-boundary query | Eight observations of the actual selector on scratch SQLite passed: scope/newest/Full, exact occurrence boundary, posting exclusion, Opening predecessor, foreign Shop/Tenant; not MySQL locks or PHP source transaction execution |
| Inventory |449 files; content SHA-256 `d7c2389ac49356426b4afc28c5aad8563f691479a7656e1124ea94114035114a`; manifest-file SHA-256 `5e05d0790466c9cbae4083cf001ca2e1fd5de244d808891112e37eff10404dd1` |
| Documentation / traceability | Relative links/CSV shape;32 original requirement IDs;48 original integrated case IDs;12 WP3 case IDs and no inferred native Pass; whitespace checked |
| Native gates | Native PHP/Composer/MySQL unavailable here. Native lifecycle/arithmetic/FKs, source/adjustment races, rollback, generation recovery, real HTTP/cookies/OpenSSL readiness, responsive/accessibility/performance and backup/restore remain open |

No automated framework/suite or production dependency was added. Scratch engineering tools remain outside the repository. Static/mocked observations do not establish native acceptance. The source review preserved Cash recovery storage compatibility, strengthened root scope/cutoff/generation validation, retained Full unsaved Cash across Stock saves, used existing semantic icons and added an explicit protected-reconciliation-reversal response.

## Source, schema and handover

[Contract](Phase_7_WP3_Integration_Contracts.md) · [Source effect matrix](Phase_7_WP3_Source_Effect_Matrix.csv) · [Operations](../operations/phase-07-wp3-stock-full.md) · [Defect/gap disposition](Phase_7_WP3_Defect_Disposition.csv) · [Exact inventory](Phase_7_WP3_Artifact_Manifest.json).

Branch `codex/phase-7-wp3-stock-full-20261116` starts at accepted WP2 `3d13eaa362f3ab66040c58b1eed52f08452c8927` and includes accepted WP1/planning prerequisites. Observed main is still `42664001a18c9678022d85fd85e7be33b7640e3f` (Phase6 closure). Draft PR targets main cumulatively; inspect stack/ancestry and duplicate squash snapshots if integrating prior PRs separately. Historical inventories/results remain preserved. The new FK migration requires all WP1/WP2 schema prerequisites and a backed-up native rehearsal, using migration OR SQL per database. Down refuses linked movement evidence; preserve it through compatible code/assets rollback.

**Next gate:** Owner native WP3 verification/acceptance. WP4 integrated phase closure remains a separate authorization. This delivery performs no main merge, migration, configuration/credential change, business mutation or production deployment.
