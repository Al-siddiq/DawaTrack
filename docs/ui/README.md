# DawaTrack UI developer reference

**Current package: UI2-B — implemented for review; Owner acceptance pending.** Plan approved 8 November 2026; the Owner authorized B on 9 November. B depends on A PR #28. UI2-C, task migrations and final programme acceptance remain planned. No existing production page is switched to the new shell in UI2-A.

## Review the delivered foundation

```bash
npm ci
npm run build
npm run ui:reference
npm run ui:preview
```

Open **http://127.0.0.1:4173** on the same computer. This read-only loopback documentation server has no application API or customer data. Stop it with Ctrl+C. Use **Screen designs** to inspect all 18 enhanced desktop/phone specimens, **Interaction examples** for proposed loading/overlay/feedback states, and **Icon catalogue** for all 34 shipped symbols. Interaction examples now mount the actual shared components with local fixtures. Their delayed/sample operations are not financial operations.

The application keeps its current overall layout. B now integrates shared commercial controls, customer list/loading, uncertain-sale status and Staff confirmation; C introduces the new shell and UI-3 migrates remaining task bodies.

## Reference index

| Reference | Contents |
|---|---|
| [Approved strategy](strategic-plan.md) | Scope, six-package programme, contracts, original 29 navigation / 96 wording entries |
| [Architecture](architecture.md) | Existing boundaries, source/generated paths, integration and CSP rules |
| [Tokens](design-tokens.md) | Actual values, contrast results, opt-in adoption and responsive intent |
| [Navigation and permissions](navigation-and-permissions.md) | Current authority and next shell integration |
| [Icons](icons.md) | Pinned dependency, semantic map, local sprite, JS/PHP usage, licence and budget |
| [Components](components.md) | What exists now, interfaces and future implementation boundaries |
| [Interaction states](interaction-states.md) | Loading, validation, outcome unknown, overlays and feedback |
| [Requirements register](requirements-register.csv) | All 46 requirements, implementation/evidence and pending work |
| [Navigation register](navigation-map.csv) | All 29 original navigation/action mappings with implementation tracking |
| [Screen inventory](screen-inventory.csv) | All 18 visual references plus existing task families to migrate |
| [Wording register](wording-catalogue.csv) | All 96 copy IDs, usage constraints and migration status |
| [Manual verification](manual-verification.md) | UI2-A setup, checks, expected results and results template |
| [Release and rollback](release-and-rollback.md) | Cross-platform builds, fingerprinted assets, coherent deployments |
| [Decisions](decisions.md) | Exact decisions and justified mockup clarifications |
| [UI2-A delivery](UI2-A-delivery.md) | Historical foundation evidence |
| [UI2-B delivery](UI2-B-delivery.md) | Shared-component scope, evidence and limits |
| [UI2-B checklist](manual-verification-ui2-b.md) | Real component and application integration verification |

## Maintenance rules

Edit source, regenerate output and commit both where the repository tracks generated assets. Do not edit `public/assets` or `icon-catalogue.js` / `UiIconCatalogue.php` directly. Every change to the allowlist must rebuild icons and review examples. Never regenerate the human-maintained requirement/acceptance columns over review results. Update documentation in the same PR as the implementation.

The 18 designs are representative, not a substitute for the full task inventory. The 96 copy entries are not marked migrated merely because their text appears in a reference. The Owner's prior Phase 4 acceptance remains intact; new UI acceptance is recorded separately and only after manual confirmation.
