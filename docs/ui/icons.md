# Local SVG icons

## Delivered implementation

Bootstrap Icons **1.13.2**, exact devDependency and lockfile, MIT notice shipped as `public/assets/ui/bootstrap-icons-LICENSE.txt`. Selection: 34 semantic symbols in `resources/ui/icons.json`. No CDN, webfont, icon JS runtime or additional animation dependency.

Actual sprite: read `public/assets/ui/manifest.json` for fingerprint, SHA-256, raw bytes, gzip bytes and exact name map. Current measurement: **17,008 bytes raw / 5,102 bytes gzip**. Build budget: **32 KiB raw / 8 KiB gzip** for the selected sprite. Exceeding either fails the build and requires an explicit measured scope/budget decision. These are sprite bytes, not total UI bundle bytes or measured network-compression claims.

The sprite is immutable/content-fingerprinted. Old fingerprinted sprites are not automatically deleted, to avoid breaking long-lived tabs. Deploy/retention policy must prevent unbounded accumulation while keeping needed release assets and rollback copies. Rebuilding the same sources produces the same bytes and fingerprint.

## Build and add an icon

1. Choose one familiar meaning and check the existing semantic name first.
2. Add an exact upstream filename stem in `resources/ui/icons.json`; never a remote URL or raw SVG.
3. Run `npm run build`, then `npm run ui:reference`.
4. Inspect the new symbol and all its labels at action/navigation size; inspect the budget/notice and generated JS/PHP outputs.
5. Update this reference, requirement/caller mapping and manual evidence. Commit source and generated assets together.

The dependency pin and allowlist version must match. The generator rejects missing files, invalid semantic names and unexpected upstream structure. Semantic names are stable application vocabulary; glyph changes do not require rewriting every caller.

## Vue use

```js
import { UiIcon, renderUiIcon } from '../components/ui/icon.js';
const { h } = window.Vue;
// A visible label supplies the button's accessible name.
h('button', { type: 'button', onClick: addStock }, [
    renderUiIcon(h, 'add'),
    'Add stock',
]);
// Or register/use the component in an existing precompiled render/template flow.
h(UiIcon, { name: 'stock', size: 'navigation' });
// Icon-only control: name the button, keep its child icon decorative.
h('button', { type: 'button', 'aria-label': 'Close customer selection' }, [
    h(UiIcon, { name: 'close' }),
]);
```

`UiIcon` props: required `name`; `size` one of `small`, `action`, `navigation` (default action); optional `label` when the SVG itself communicates meaningful content, resulting in `role=img` and an accessible name. Default is decorative `aria-hidden=true`, `focusable=false`. Unknown names/sizes throw immediately rather than silently displaying a broken glyph. Do not feed arbitrary API values directly into icon selection.

No template string or compiler dependency at runtime. Rendering reads the generated `iconSymbols` and `iconSprite` exports. URL is same-origin `/assets/ui/<fingerprint>.svg#ui-<semantic>` in the existing root asset convention. Normal app actions still need their existing event/permission wiring; an icon grants none.

## PHP use

```php
<?php helper('ui'); ?>
<button type="button" class="ui-button">
    <?= dt_ui_icon('add') ?> Add stock
</button>
```

`dt_ui_icon(string $name, string $size = 'action', string $label = ''): string` loads the generated `App\Support\UiIconCatalogue`, validates enum-like inputs, escapes label/URL and uses `dt_url()` for same-origin links. It is opt-in: existing Auth views are not switched in A. Load the helper explicitly in an adopting view/controller.

Keep text on bottom navigation, all sidebar destinations and important actions. Close/back compact controls need clear accessible names. A trash can is for removing an unfinished line, not an unlabeled cancellation of immutable history.

## Failure and offline behavior

Icons need no external internet service once local assets are available, but a first uncached HTTP load still needs the local server. No new Service Worker or business-data caching is introduced. A missing sprite cannot remove the visible action text; log/diagnose its asset release rather than offering a remote fallback. Verify actual production CSP and target browsers in B/C before adoption; inspect local network requests and cache behavior.

Full semantic/upstream mapping: `resources/ui/icons.json` and the Icon catalogue in the local design reference. The 18 enhanced screens use those exact upstream paths inline to keep the design specimens self-contained; application helpers use the local fingerprinted sprite.
