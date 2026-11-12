# Developer maintenance handover

> **Current status, 11 November 2026:** Owner accepted the UI programme and reported merge/rollout complete. [Final decision](acceptance/2026-11-11-ui-programme-owner-closure.md) and [closure ledger](programme-closure.md) supersede pre-review Pending wording below. Historical engineering limits and unreported individual results remain unchanged.

The UI uses existing Vue 3 JavaScript modules with precompiled Shop rendering and `h()` Admin/components. Native Auth remains PHP forms with a small local enhancement module. Bootstrap is CSS only; no competing Bootstrap DOM controller, Router/Pinia or runtime-template compiler is introduced. Icons are the pinned, locally served Bootstrap Icons subset.

## Start with these references

| Question | Reference/source |
|---|---|
| What was agreed and what closes the programme? | strategic-plan.md; programme-closure.md |
| What is current, accepted and still unverified? | UI4-A-delivery.md; acceptance records; verification/ui4-a-manual-results.csv |
| Where do I change navigation/access hints? | shared/workspace-registry.js; workspace-shells.md; navigation-map.csv |
| Where do I change fields, feedback, overlays or tasks? | components/ui; components.md; interaction-states.md |
| Where do I change trading tasks? | trading-screens.md; products/intakes/customer/sale/return/receipt components |
| Where do I change Owner/Admin/Auth? | owner-admin-auth.md; owner-panel.js; admin/app.js; app/Views/auth |
| Where do I change tokens/brand/icon selection? | design-tokens.md; brand-refinement.md; icons.md; resources/css/ui; resources/ui/icons.json |
| How do I build and verify a release's content? | release-handover.md; scripts/build-ui-release-manifest.mjs |
| What are the native fixtures and final gates? | manual-verification-ui4-a.md; verification/ui4-a-fixtures.md |

Paths under shared/components above are under `resources/js`; the generated public copies are under `public/assets/js`. Update source and regenerate output; never hand-edit the generated Shop render, copied JS/CSS, icon sprite/catalogues or release checksum inventory to mask a discrepancy.

## Routine change workflow

1. Read the existing task's API/service/permission contract and its source reference. Preserve UUID/version/fingerprint identities, server-authoritative decimal strings and actual role/access gates. A display label must not become an API enum.
2. Make the smallest complete change in its owner. Use existing UI components and plain English. Error/unknown/one-time outcomes must remain available; only specifically safe successes may dismiss.
3. Rebuild with locked dependencies and `npm run build`; use `npm run ui:reference` when reference material changes. Inspect generated/source parity and the affected source/native path. Do not add a new test framework or replace Owner manual acceptance with diagnostic scripts.
4. Update wording, navigation/inventory/requirements where affected. Keep original COPY-001–096 IDs and explicit future-feature dispositions. Record design differences and defects instead of hiding an agreed requirement under cosmetic work.
5. Run `npm run ui:release` only when deliberately recording a reviewed content change. Run `ui:release:check` to verify an expected inventory. Stage new required assets first. Record native results for the affected path; reuse unaffected valid evidence.
6. Open the PR against the actual integration target. After merging dependencies, inspect main's tree; a closed stacked PR alone does not establish integration. Ship compatible PHP/JS/CSS/icons/licences together and retain the prior complete release.

## UI4 corrections to preserve

Receipt privacy applies to both the filtered clone and browser-native Print; internal `.receipt-private` / `.receipt-stock-evidence` nodes must never enter a customer copy. Error-summary correction links open native details, dispatch the owned `dt:reveal-field` event to shared accordions, wait for rendering and then focus. Do not reveal unrelated controls or bypass an active security/write guard.

Legacy unused Auth placeholder modules and the compiler-enabled Vue vendor file are removed. `build-frontend-assets.mjs` copies the runtime Vue/Dexie assets plus Vue, Dexie and Bootstrap licence notices. Do not accidentally reintroduce the placeholder or load two Vue runtimes. The local component gallery remains a reference with fixtures, not a live Shop.

Raw-byte manifests intentionally identify one content set and do not certify PHP vendor/environment/uploads/database or a production deployment. `.gitattributes` and clean archives support stable text bytes; never destroy a developer's local edits to fix a checksum mismatch. Actual origin/CDN cache/CSP behavior is still an operational/native check.


## Phase 5 WP1 maintenance addendum

CustomerPanel delegates debt hashes to `components/customer-debt-panel.js` through existing workspace navigation. The component reads only GET endpoints, aborts/ignores superseded reads and clears data on failed/offline refresh; no balance cache or mutation controller is introduced. Server `DebtQuery` uses one read-only snapshot and exact signed SQL projection. See [WP1 contracts](../Phase-5/Phase_5_WP1_Integration_Contracts.md). The operational inventory script now supports `--output=docs/Phase-5/Phase_5_WP1_Artifact_Manifest.json` so later releases do not overwrite the accepted UI4 artifact record. Pass the same option when checking a package inventory. Existing default remains the historical UI4 inventory.


## Phase 5 WP2 maintenance addendum

CustomerDebtPanel routes receive/history/payment hashes to `components/debt-payment-panel.js`; `debt-payment-receipt.js` owns the filtered print copy and server-sourced historical/current balance labels. Panel uses the shared guarded navigation, no Router or new framework. It retains only pending payment/Customer UUIDs in per-host/user/Shop sessionStorage; never store command bodies, amounts, reasons, bank references or credentials there. Never replace an unresolved UUID with a new one or treat404 as conclusive non-commit. All amount/review/permission checks remain server-owned. [Exact API/lock/recovery/privacy contracts](../Phase-5/Phase_5_WP2_Integration_Contracts.md).

## Phase 5 WP3 maintenance addendum

CustomerDebtPanel delegates contextual payment reverse/correct, bill adjust/changes and adjustment detail/reverse hashes to DebtCorrectionPanel. No extra bottom destination. Forms reuse shared field/error summary, loading button, information panel and confirmation modal. Payment receipt distinguishes inverse amount from money received; links original/undo/replacement. Native Print/app clone hides receipt-private reason/live totals; only reverse-authorized DTO consumers receive payment correction reason. Mandatory financial errors/unknown outcomes persist.

Recovery keys `dt:pending-debt-correction:{host}:{recovery_scope}` store only kind/customer/source/id/replacement IDs; normal payment and correction entry points redirect unresolved work to its owner. No amounts, reasons, references, auth data, automatic retry or offline outbox.404 allows only explicitly chosen same-ID continuation. Keep the hard navigation/reload guard until saved status is established; do not clear a changed-body identity conflict as if it were a failed new payment. [Full API/lock/source/privacy reference](../Phase-5/Phase_5_WP3_Integration_Contracts.md), [native cases](../manual-verification-phase-05-wp3.md). Existing icon set/brand tokens/shell remain; no new dependency.

## Phase 5 WP4 maintenance addendum

CustomerDebtPanel delegates `#customers/debt/{uuid}/statement` to CustomerStatementPanel. Both view grants required; no new shell destination. CustomerStatementDocument owns page/full print labels, dates and signed totals. Tokens are opaque in component memory only; Previous-to-first uses server checkpoint, not a fresh unversioned read. Refresh resets tokens. Changed/offline/failed scope clears previous amounts; superseded reads ignored. Print preparation revalidates all pages, cancels safely and prints nothing incomplete;2000-record full-print bound never truncates paging. Private current total removed from app clone/Ctrl+P, details opened/restored. All money/running values stay server-owned strings. [API/cursor/source/privacy reference](../Phase-5/Phase_5_WP4_Integration_Contracts.md), [manual guide](../manual-verification-phase-05-wp4.md), [final closure gate](../Phase-5/Phase_5_Closure_Checklist.md).

## Phase 5 closure handover

WP1–WP4 and their UI extensions are [Owner-accepted and closed](../acceptance/2026-11-12-phase-05-owner-closure.md) on 12 November 2026. Preserve the accepted runtime/source/Print/recovery contracts and historical engineering evidence; individual observations not supplied are not manufactured. Closure changes documentation only, keeps the391-file application inventory intact, and does not merge/deploy or begin Phase6. See [release/rollback operations](../operations/phase-05-statements-release.md).
