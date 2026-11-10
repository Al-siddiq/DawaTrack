# UI decisions

| ID | Date | Decision and reason | State |
|---|---|---|---|
| UI-D01 | 2026-11-08 | User approved the strategic plan and asked to implement according to its package sequence | Approved plan; not package acceptance |
| UI-D02 | 2026-11-08 | Use exact Bootstrap Icons 1.13.2, trusted selected SVG symbols, one local catalogue and retained MIT notice | Implemented A |
| UI-D03 | 2026-11-08 | Cap selected sprite at 32 KiB raw / 8 KiB gzip after measuring 17,008 / 5,102 bytes | Engineering budget, recorded for review |
| UI-D04 | 2026-11-08 | Adopt new tokens/styles opt-in; do not silently restyle accepted task screens in A | Implemented A; shell C/task UI-3 |
| UI-D05 | 2026-11-08 | Keep UI2-A examples outside public web root with loopback-only docs server and no API connections | Implemented A |
| UI-D06 | 2026-11-08 | Use content fingerprints and retain old sprites during transition; do not add Service Worker caching | Implemented generator; deployment retention still an operator obligation |
| UI-D07 | 2026-11-08 | Source controller is app/Controllers/Api/ShopController.php; intakes read gate differs from its create grant | Clarification; no authority change |
| UI-D08 | 2026-11-08 | Interaction examples illustrate B contracts, not completed shared behaviors or real saves | Documented explicitly |

Keep a new row for future justified design differences, measured budget/version changes and acceptance. Never mark a manual Owner decision from an engineer's inference.

| UI-D09 | 2026-11-09 | User said “Excellent. Proceed to UI2-B”; authorizes B without inventing native/manual results | B authorized; package acceptance pending |
| UI-D10 | 2026-11-09 | Keep legacy commercial success notices persistent until caller-specific copy/next-step review; only explicit ordinary success auto-dismisses | Implemented compatibility policy |
| UI-D11 | 2026-11-09 | Use native controlled dialogs with one active owner; preserve Staff reason/security behavior and remove the parallel legacy focus trap there | Implemented B; remaining bespoke task overlays tracked for UI-3 |
