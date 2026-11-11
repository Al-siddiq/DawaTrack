# Developer maintenance handover

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
