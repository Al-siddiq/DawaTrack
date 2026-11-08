# Design tokens and adoption

Actual source: `resources/css/ui/_tokens.scss`; opt-in primitives: `_foundation.scss`.

| Token | Value | Role |
|---|---|---|
| `--ui-primary` / `--ui-on-primary` | `#155D46` / white | Primary action and its text |
| `--ui-ink` | `#20342A` | Main text |
| `--ui-muted` | `#5D7066` | Helper text |
| `--ui-background` / `--ui-surface` | `#F3F6F4` / white | Page/card |
| `--ui-border` | `#DCE5DF` | Decorative boundaries only |
| `--ui-control-border` | `#718479` | Form boundaries where identification needs contrast |
| `--ui-selected` | `#E6F1EB` | Active destination background |
| `--ui-danger` / surface | `#A92C35` / `#FFF0EF` | Error/destructive state |
| `--ui-warning` / surface | `#765309` / `#FFF5D9` | Warning |
| `--ui-focus` | `#155D46` | 3px visible focus; evaluate against surrounding surface |
| Space 1/2/3/4/6 | 4/8/12/16/24px at default root | Moderate consistent spacing |
| Body / help / nav | 16/14/12px | Regular readable text; meaningful visible labels |
| Heading | 24px; top heading 28px | Avoid excessive headings/whitespace |
| Control / card radius | 8/12px | Consistent rounding |
| Touch target | 44px minimum | Icon size is not target size |
| Small / action / nav icon | 16/20/24px | Consistent alignment |
| Duration | 160ms; 0 under reduced motion | Restrained transitions |
| Layers sticky/scrim/overlay/alert | 100/200/300/400 | One consistent stacking scale in the future shell |

These variables do not replace legacy `--dt-*` or `--admin-*` variables in A. `.ui-scope` gates typography, controls and surfaces. Existing panels are not redesigned by an unscoped `h1`, `.btn` or body override. `.ui-icon` classes are safe standalone dimensions.

Static reference color pairs have measured contrast in `contrast-measurements.json`. Values are mathematical evidence for these exact pairs, not blanket accessibility acceptance. Decorative border color does not identify an input; use control-border and an appropriate error/focus indicator. Branding/custom imagery and real focus adjacency still need manual review.

UI2-C responsive targets: below 768px compact, 768–1199px drawer/intermediate, from 1200px persistent sidebar if content fits. Keep thresholds centrally defined in the eventual shell; they are not active shell CSS in A. Header/bottom safe areas and keyboard accommodation must derive from real layout height. No desktop duplicate bottom bar.

Keep 44px targets, visible words plus status icons, normal text contrast at least 4.5:1 and large text/non-text control contrast at least 3:1 as applicable. Brand colors must have an accessible fallback. Reduced motion removes spinner rotation and retains readable status. Do not claim verification for dynamic branding until that UI exists and is checked.
