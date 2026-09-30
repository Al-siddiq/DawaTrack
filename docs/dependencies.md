# Dependency register

| Dependency | Pinned version | Purpose | Runtime delivery |
|---|---:|---|---|
| PHP | ^8.2 | Server runtime | Host |
| CodeIgniter Framework | 4.7.3 (lock) | Web framework | Composer |
| CodeIgniter Shield | 1.4.1 (lock) | Session authentication, recovery, platform groups and MFA | Composer |
| Vue | 3.5.22 | Progressive shell mounts | Self-hosted static asset |
| Bootstrap | 5.3.8 | Responsive CSS source | Compiled into static CSS |
| Sass | 1.93.2 | CSS compiler only | Development dependency |
| Dexie | 4.2.1 | Reserved future IndexedDB boundary; unused in Phase 0 | Self-hosted static asset |

No browser dependency is loaded from a CDN. No JavaScript application bundler is used.

## WP4 dependency outcome

WP4 added no Composer, npm, queue, broker, billing, image, or binary dependency. It uses the existing CodeIgniter database/CLI facilities, Shield boundary, Vue 3, Bootstrap/Sass and native Fetch. MySQL advisory locks are part of the existing MySQL 8.4 deployment contract.
