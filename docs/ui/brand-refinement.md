# UI2-C brand colour refinement

**Date:** 10 November 2026. **Branch:** `codex/ui2-c-brand-refinement-20261110`. **Base:** UI2-C #30, commit `25af0ef5f73c1878a4048bcd74e6d172fcfd5aca`. **Refinement acceptance:** pending. Original UI2-C acceptance is recorded separately.

## Colour contract

`app/Helpers/ui_helper.php::dt_ui_brand_palette(primary, accent)` accepts only six-digit hex colours and returns validated CSS token values. Invalid primary/accent input uses `#155D46` / `#F0A202`. No CSS fragments are accepted. The existing nonce-authorized Shop/Admin/entry and Auth styles emit these tokens; no inline style attributes, third-party assets or new runtime library are required.

| Token | Derivation / use |
|---|---|
| `--ui-brand` | Valid original primary; decorative 3px top edge only, never relied on for readable text |
| `--ui-primary` | Original primary, progressively darkened in 1% RGB steps toward black until white contrast is at least 5.5:1; retains its hue instead of substituting unrelated green for a bright valid brand |
| `--ui-on-primary` | White, unchanged |
| `--ui-primary-hover` | Action colour darkened another 12%; primary hover/pressed |
| `--ui-selected` | 92% white + 8% action colour; active navigation and New sale card |
| `--ui-hover-surface` | 96% white + 4% action colour; restrained hover on neutral destinations |
| `--ui-focus` | Readable action colour; visible 3px outline |
| `--ui-accent` | Valid original accent; narrow decorative divider under Shop/Menu/sign-in identity |
| Success/error/warning | Semantic colours retained; success explicitly independent of brand |

Primary text on the selected surface remains at least 4.5:1 with this conservative action threshold. Essential control boundaries/focus use the readable action colour, never raw pale primary/accent. Brand identity does not depend on a coloured line: name/logo and navigation labels remain. White cards, neutral body text/background and normal headings stay calm. Colour is supported by current-page semantics, labels, icons and visible selected bars.

## Touchpoints

- Existing Vue primary buttons and native/legacy Bootstrap primary buttons share the brand action/hover/pressed palette. Outline actions remain white with brand text/border, with a pale hover/active surface; disabled/busy behaviour is unchanged.
- Sidebar/Menu active destination gets its pale brand tint and a narrow side marker; bottom navigation uses a top marker. No layout movement or animation is added.
- Header/sign-in card has a slim brand edge. Accent is limited to the brand identity divider.
- Home icons use small pale brand tiles; New sale has a modest highlighted card. Other shortcuts remain neutral until hover.
- Existing primary links, input focus and selected checkboxes/radio controls use the action hue. Destructive controls and warning/error/success messages retain their meaning.
- Hover effects run only on hover-capable devices. Existing reduced-motion, loading, focus, touch target and navigation contracts remain.

Source: `resources/css/ui/_brand.scss`, `_tokens.scss`, `_components.scss`, `resources/js/components/workspace-home.js`, `app/Helpers/ui_helper.php`, and both PHP layouts. Commit generated CSS/JS with source. No data model, API, permissions, transaction logic or saved identities change. Admin keeps its existing platform/default identity; it does not take over a Shop brand. Branding edits retain the existing reload behaviour, not a new live-theme setting.

## Focused manual review

```bash
git fetch origin
git switch codex/ui2-c-brand-refinement-20261110
composer install
npm ci
npm run build
```

Open the normal XAMPP Shop URL and hard reload. No migration. The branch includes UI2-A/B/C prerequisites. Use supported branding settings in a test Shop; do not weaken existing server colour validation to try invalid inputs.

| Case | Check | Expected |
|---|---|---|
| BR01 | Default, purple/blue and approved bright brand colours | CTAs, active states and pale tints follow the brand; bright valid colours get readable darker actions |
| BR02 | Owner/Staff desktop, mobile bottom bar and Menu | Selected destination has label/icon plus marker; compact navigation remains reachable |
| BR03 | New sale and remaining Home cards | New sale stands out modestly; other cards/body/heading remain neutral |
| BR04 | Primary/outline/plain/destructive buttons, disabled, busy, hover and pressed | Correct palette and visible labels; danger remains red; no behaviour or layout change |
| BR05 | Tab through fields/links/checkboxes; zoom and touch | Visible focus and selected controls; no lost focus ring, new clipping or hover dependence |
| BR06 | Success, warning, validation errors and uncertainty messages | Semantic colour/wording preserved independent of brand |
| BR07 | Shop login/recovery and platform Admin/MFA | Appropriate Shop/platform identity, readable buttons, no missing CSS/CSP errors |
| BR08 | Save branding and reload; missing logo/long name | Existing branding update works; identity text remains readable |
| BR09 | Print saved receipt and inspect existing sale/return flow | Navigation stays out of print; totals, identities and workflows unchanged |

Build and changed JavaScript syntax passed; three changed PHP files parsed with PHP 8.3 grammar. A calculation model sampled 5,832 RGB colours: action/white contrast remained at least 5.5:1 and the minimum action/selected-surface contrast was 4.781:1. This model does not execute the PHP helper. These are engineering checks. Native PHP/CI4, real browser/CSP/contrast adjacency and visual acceptance remain manual; no browser screenshot or per-case Owner pass is claimed here. C acceptance does not pre-accept this refinement or UI3.
