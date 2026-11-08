# UI2-A delivery — Baseline, tokens and local icons

**Date:** 8 November 2026. **Branch:** `codex/ui2-a-tokens-icons-20261108`. **Base:** `09789e8aab48b5d62b546ecf538e491867b5fedf`.

**State:** implemented for manual review; not accepted on the Owner's behalf. UI2-B, UI2-C, UI-3 and final programme verification remain planned.

## Delivered

- Exact Bootstrap Icons 1.13.2 dependency/lockfile, 34-symbol allowlist, retained MIT licence, deterministic local SVG generator and fingerprinted output.
- Shared runtime-only Vue icon component/render helper and opt-in escaped PHP helper, using the same generated semantic map.
- Opt-in design tokens and presentation styles; current production pages are not switched to the new layout in this package.
- 18 enhanced screen specimens with meaningful navigation/action icons. Local reference includes visual examples for loading, validation, modal/sheet, accordion, dropdown and alert states, plus a real Vue/local-sprite icon catalogue.
- Cross-platform `ui:reference` and `ui:preview` commands. Loopback-only documentation server, strict CSP, no API/data connection and narrow static path policy.
- Approved strategy and developer reference, 46 requirement records, 29 navigation records, 96 wording records and 35 specimen/task-family inventory rows. Copy/task migrations are explicitly pending, not counted as completed production adoption.
- Step-by-step UI2-A manual checklist and release/rollback guidance.

## Engineering evidence

| Check | Actual result |
|---|---|
| Clean `npm ci` | Passed on Linux; exact bootstrap-icons 1.13.2 installed |
| `npm run build` | Passed; existing Shop template compiled; `Frontend assets built.` |
| `npm run ui:reference` | Passed; 18 enhanced specimens generated |
| JavaScript syntax checks | Passed for icon build/reference/server, icon component and reference interactions |
| Icon generation | Same fingerprint across repeated builds; 34 symbols; 17,008 raw bytes, 5,102 gzip bytes, below 32 KiB / 8 KiB budget |
| Static color measurements | Six documented baseline text/control pairs meet the stated 4.5:1 or 3:1 thresholds; dynamic branding/focus contexts still need review |
| Vector visual inspection | All 18 enhanced SVGs rendered with Sharp and inspected together; no observed clipping/covered labels in those specimens |
| Local HTTP smoke inspection | Reference, real local sprite, Vue icon module and runtime returned HTTP 200 under strict reference CSP; traversal attempt to package.json rejected with 403 |
| Native browser interaction / CSP execution | Not verified here: Chromium startup blocked by environment socket restriction. HTTP delivery and vector rendering do not establish interaction accessibility |
| PHP helper lint/runtime | Not run: PHP executable unavailable in this environment. Validate in XAMPP before adopting the helper in live views |
| Native Windows build | Pending Owner/developer Windows environment |
| Actual application integration/business acceptance | Pending later packages and their manual gates; no migrations or business logic changed |

No automated test suite/framework was introduced. Build validation and targeted integrity/syntax/HTTP inspections are engineering evidence, not substituted manual acceptance.

## Review on Windows

Use [manual-verification.md](manual-verification.md). Build, run the reference, inspect the 18 screens, both icon catalogues and the interaction examples. The application itself is expected to retain its existing screens at this stage. Record actual results and acceptance separately; no acceptance record has been created prematurely.

## Known limits and next objective

The PHP helper and application CSP/browser compatibility require native verification before call-site adoption. Interaction samples do not yet implement reusable production dialog/loading components or real save recovery. These are documented UI2-B obligations. Responsive application shell integration follows in UI2-C, then all task bodies in UI-3.

Next implementation objective after this package review: **UI2-B — Shared interaction components and state behavior**.
