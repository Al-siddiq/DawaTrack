# DawaTrack UI developer reference

**Current package: UI3-B — Owner/Admin/Auth screens, implemented for review.** B is stacked on UI3-A #35, which remained open at implementation. Authorization to start B is recorded without inventing UI3-A manual acceptance. UI3-A/B manual acceptance and UI4 final programme verification remain outstanding.

## Review UI3-B in the actual application

Use the normal XAMPP Shop/Admin addresses and [UI3-B manual verification](manual-verification-ui3-b.md). See [delivery](UI3-B-delivery.md) and [Owner/Admin/Auth reference](owner-admin-auth.md). No migration. Merge UI3-A #35 into main first, then retarget the B PR to main; do not merge into an old UI2 feature branch.

## Review UI3-A in the actual application

Follow [UI3-A manual verification](manual-verification-ui3-a.md) using the normal XAMPP Shop address. See [delivery scope and integration](UI3-A-delivery.md) and [trading developer reference](trading-screens.md). No migration is needed. The component gallery below remains a design reference, not the live app or proof of business acceptance.

## Review the delivered foundation

```bash
npm ci
npm run build
npm run ui:reference
npm run ui:preview
```

Open **http://127.0.0.1:4173** on the same computer. This read-only loopback documentation server has no application API or customer data. Stop it with Ctrl+C. Use **Screen designs** to inspect all 18 enhanced desktop/phone specimens, **Interaction examples** for proposed loading/overlay/feedback states, and **Icon catalogue** for all 34 shipped symbols. Interaction examples now mount the actual shared components with local fixtures. Their delayed/sample operations are not financial operations.

UI2-C now changes the actual Shop/Admin/Auth layout and navigation. Open the normal XAMPP development address for C verification. The local reference is still a design/component gallery, not a live business app. UI-3 migrates remaining task bodies and wording.

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
| [Workspace shells](workspace-shells.md) | Current registries, hashes, guards, Home, branding, Admin and Auth contracts |
| [Brand refinement](brand-refinement.md) | Colour contract, implementation and focused manual checks |
| [UI2-C acceptance](acceptance/2026-11-10-ui2-c-owner-acceptance.md) | Owner confirmation of the delivered layout/navigation |
| [UI2-C delivery](UI2-C-delivery.md) | Integrated shell scope and engineering limits |
| [UI2-C checklist](manual-verification-ui2-c.md) | Actual Shop/Admin/Auth manual verification |
| [UI2-B acceptance](acceptance/2026-11-09-ui2-b-owner-acceptance.md) | Owner manual acceptance record |
| [UI2-B checklist](manual-verification-ui2-b.md) | Real component and application integration verification |

## Maintenance rules

Edit source, regenerate output and commit both where the repository tracks generated assets. Do not edit `public/assets` or `icon-catalogue.js` / `UiIconCatalogue.php` directly. Every change to the allowlist must rebuild icons and review examples. Never regenerate the human-maintained requirement/acceptance columns over review results. Update documentation in the same PR as the implementation.

The 18 designs are representative, not a substitute for the full task inventory. The 96 copy entries are not marked migrated merely because their text appears in a reference. The requirements register records package-level C acceptance separately from unreported per-case results and remaining UI3/UI4 work. The Owner's prior Phase 4 acceptance remains intact; new UI acceptance is recorded separately and only after manual confirmation.
