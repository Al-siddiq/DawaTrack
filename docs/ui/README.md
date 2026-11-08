# DawaTrack UI developer reference

**Current package: UI2-A — implemented for review; Owner acceptance pending.** Plan approved 8 November 2026. UI2-B, UI2-C, task migrations and final acceptance remain planned. No existing production page is switched to the new shell in UI2-A.

## Review the delivered foundation

```bash
npm ci
npm run build
npm run ui:reference
npm run ui:preview
```

Open **http://127.0.0.1:4173** on the same computer. This read-only loopback documentation server has no application API or customer data. Stop it with Ctrl+C. Use **Screen designs** to inspect all 18 enhanced desktop/phone specimens, **Interaction examples** for proposed loading/overlay/feedback states, and **Icon catalogue** for all 34 shipped symbols. Example state transitions are deliberately local demonstrations, not reusable production components or financial operations.

The application itself still looks as before: UI2-C introduces the new shell, and UI-3 migrates task bodies after UI2-B's reusable components are accepted.

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
| [Delivery report](UI2-A-delivery.md) | Implemented scope, evidence and limitations |

## Maintenance rules

Edit source, regenerate output and commit both where the repository tracks generated assets. Do not edit `public/assets` or `icon-catalogue.js` / `UiIconCatalogue.php` directly. Every change to the allowlist must rebuild icons and review examples. Never regenerate the human-maintained requirement/acceptance columns over review results. Update documentation in the same PR as the implementation.

The 18 designs are representative, not a substitute for the full task inventory. The 96 copy entries are not marked migrated merely because their text appears in a reference. The Owner's prior Phase 4 acceptance remains intact; new UI acceptance is recorded separately and only after manual confirmation.
