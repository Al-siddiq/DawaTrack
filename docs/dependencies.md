# Dependency register

| Dependency | Pinned version | Purpose | Runtime delivery |
|---|---:|---|---|
| PHP | ^8.2 | Server runtime | Host |
| CodeIgniter Framework | 4.7.3 (lock) | Web framework | Composer |
| CodeIgniter Shield | 1.4.1 (lock) | Session authentication, recovery, platform groups and MFA | Composer |
| Vue | 3.5.44 | Progressive shell mounts | Self-hosted static asset |
| Bootstrap | 5.3.8 | Responsive CSS source | Compiled into static CSS |
| Sass | 1.93.2 | CSS compiler only | Development dependency |
| Dexie | 4.2.1 | Reserved future IndexedDB boundary; unused in Phase 0 | Self-hosted static asset |

No browser dependency is loaded from a CDN. No JavaScript application bundler is used.

## WP4 dependency outcome

WP4 added no Composer, npm, queue, broker, billing, image, or binary dependency. It uses the existing CodeIgniter database/CLI facilities, Shield boundary, Vue 3, Bootstrap/Sass and native Fetch. MySQL advisory locks are part of the existing MySQL 8.4 deployment contract.


## WP3 Windows build and dependency follow-up — 2026-11-03

Frontend copying/cleanup now uses `node scripts/build-frontend-assets.mjs` with Node filesystem APIs and the current Node executable for template compilation. No Unix shell, Git Bash, `cp` or `rm` executable is required by npm on Windows. The old `.sh` file is only a compatibility wrapper for Unix callers.

Vue and its matched compiler/runtime family are pinned to 3.5.44. The lock file updates `source-map-js` to patched 1.2.2 within its existing transitive ranges. The targeted updates address [GHSA-g2v6-rqmx-r4w6](https://github.com/advisories/GHSA-g2v6-rqmx-r4w6) and [GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q). The Vue advisory concerns server-side rendering; this application uses PHP views and client Vue mounts rather than Vue SSR. This is a dependency hygiene fix, not a claim of an observed exploit. Current `npm audit` after the update reports zero vulnerabilities; that is a point-in-time result. No major upgrade or `npm audit fix --force` is used.
