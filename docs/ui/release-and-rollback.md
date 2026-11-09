# Build, release and rollback

## UI2-A build

```bash
npm ci
npm run build
npm run ui:reference
```

The main build compiles existing Sass, generates allowlisted icons, recompiles the existing Shop template and copies application JS/vendor assets with Node. No shell-only dependency is introduced. Exact Bootstrap Icons version is 1.13.2. `npm ci` uses the lockfile; first dependency download requires internet. Deployment/runtime uses local assets. Sass Bootstrap `@import` warnings predate this package and do not stop the build.

Commit source plus generated CSS, icon sprite/manifest/licence, JS/PHP catalogues, public JS copy and reference HTML/SVGs. UI2-A introduces no migration and does not alter authentication/API/financial behavior. PHP helper is not auto-invoked by existing pages.

## Production deployment obligations

Ship markup, CSS, JS, catalogue and fingerprinted sprites as one compatible release. Do not upload only the icon helper without its referenced sprite. Inspect `manifest.json`; build rejects a mismatched version, missing symbol or exceeded budget. Keep required prior sprites for older tabs and rollback; retain compatible release directories/archives per existing deployment process. Do not prune every old sprite at build time.

No Service Worker change or sensitive response caching is introduced. Verify the actual hosting cache/CSP after adoption in B/C. A hard reload is a troubleshooting step, not a cache-invalidation design. The loopback reference server is for developer/manual review, not production.

## Rollback

Restore the complete prior compatible source/generated asset release. UI2-A needs no data rollback. Never reverse customer sales as a visual rollback. For later packages, preserve operation identities and long-lived tabs; recheck sign-in/context, navigation, saved baskets and receipts after rollback. Record actual commits/environment and evidence before claiming a rehearsal passed.

## UI2-B release boundary

Build the B branch including its A dependency; do not deploy B JS/template changes against A-only public assets. Commercial helper adapters, new UI modules, compiled Staff renderer, CSS and reference must ship coherently. No migration is needed. Roll back to the complete previous compatible code/assets; preserve saved business records and identities. Before accepting B, verify test Staff dialog/credentials, customer list/form/privacy and existing sale outcome recovery. Native app and UI2-C navigation acceptance remain separate.
