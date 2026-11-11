# UI architecture and source ownership

Baseline: main `09789e8aab48b5d62b546ecf538e491867b5fedf`. UI2-A changes presentation infrastructure and documentation only. Existing PHP/API/service/record behavior is retained.

## Existing boundaries

Shop: Owner and Staff share the Vue Composition API application, permissions/capabilities from the server. Admin: separate application and session, existing platform authority. Auth: current server views, Shield email login and configured recovery, forced password change and Admin security check. Customers are records, not a portal.

Retain the runtime-only production Vue build, current API layer, server host context, CSRF/credentials, nonce-authorized branding styles, and existing Service Worker boundaries. Do not introduce Vue Router, Pinia, TypeScript, an app bundler or a DOM-replacement icon runtime. UI2-A's small Node scripts are deterministic asset/documentation build steps, not an app bundler.

## Source / generated / adoption map

| Source | Generated result | Adoption |
|---|---|---|
| `resources/ui/icons.json` + pinned `bootstrap-icons` | `public/assets/ui/icons-<hash>.svg`, licence, manifest, JS/PHP catalogues and inline reference vectors | New `UiIcon` / PHP helper available now; production call sites in B/C/UI-3 |
| `resources/css/ui/_tokens.scss` | Included in `public/assets/css/app.css` by current Sass compilation | Namespaced variables available now, no legacy variable replacement |
| `resources/css/ui/_foundation.scss` | Same stylesheet | `.ui-*` and `.ui-scope` opt-in styles only |
| `resources/js/components/ui/icon.js` | Copied to `public/assets/js/components/ui/icon.js` | `h()`-based; compatible with runtime-only Vue |
| `resources/js/shop/template.html` | `resources/js/shop/render.js`, then public copy | Existing pipeline retained; never hand-edit renderer |
| `docs/ui/UI1/screens/*.svg` + trusted icon vectors | `docs/ui/reference/screens/*.svg`, `index.html` | Updated 18-screen design examples |
| `docs/ui/reference/reference.js` / `.css` | Source files served only by local docs server | Interaction intent; not production component implementations |

`npm run build` invokes Sass, then the cross-platform Node script. That script generates icons before copying JS; stale icon catalogues therefore do not survive a normal build. `npm run ui:reference` regenerates HTML/SVGs from the baseline screen sources and generated icon vectors. The UI reference intentionally stays outside `public/` and is not a new production route.

## Security and data boundaries

Only trusted, pinned upstream SVG paths are included. Build rejects unexpected script/style/event/href structures; application code accepts semantic icon names from an allowlist, never a user-supplied SVG URL. The glyph is decorative by default, with the surrounding text supplying meaning. Do not use `v-html` for user SVG.

The documentation server binds loopback, serves a narrow static allowlist, rejects traversal outside that allowlist, uses `nosniff`, `no-store`, no API connection permission and a strict CSP. It is not a deployment/production HTTP server. Do not copy it into the web root or expose it publicly.

Application CSP is unchanged. External same-origin sprite `<use>` requires browser/CSP verification before production adoption. Keep nonce authorization for branding and no runtime template compilation. If `<use>` is unsupported for a target, use a selected trusted inline vector map rather than loosening CSP or loading a CDN.

## Next integration rules

UI2-B builds focused components around shared behavior; reconcile current `commercial-ui.js`, reason-dialog and screen helpers rather than creating a second uncontrolled system. Vue owns overlay/open/busy state. Bootstrap CSS may style controls; Bootstrap JS must not independently mutate the same DOM. UI2-C adopts shells and registry; UI-3 migrates task screens. Neither stage may move business rules into presentation components or change server authority.

## UI2-B adoption

UI2-B adds `primitives.js`, `overlay.js`, `disclosure.js`, `records.js`, `tasks.js` and a barrel export under the existing UI directory. New CSS classes remain opt-in; the shared button reserves both resting/busy text widths with a CSS grid (no CSP-sensitive inline sizing). Existing commercial helpers now adapt to shared controls; Customers uses the read-only list helper, uncertain sale status uses UiStatusPanel, and Staff confirmation uses UiDialog. The old parallel Staff focus trap is removed. Other bespoke task overlays remain UI-3 obligations. No endpoint, service, schema, permission or exact-money contract is changed.

## UI2-C integration

The real Shop/Admin mounts now use WorkspaceShell. Server layout avoids a duplicate legacy header/footer only for those mounts; Auth remains native server forms. UI navigation state lives in shared/workspace-navigation.js, with one registry in workspace-registry.js. The API wrapper exposes only active-mutation count and a success notification to the originating form for dirty revisions; URL/body/credentials/CSRF/response semantics remain unchanged. Current Home reads and route-specific lazy loading replace eager loading of every Owner section. Numeric layout variables are written through a server-nonced style element; no Service Worker/data cache is changed. PHP view/helper syntax has parser evidence, but native CI4/XAMPP execution remains manual. See workspace-shells.md.
