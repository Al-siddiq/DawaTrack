# DawaTrack

DawaTrack is a modular CodeIgniter 4 application for secure Nigerian chemist/PPMV operations. Phase 1 recovery is in progress; the repository includes the runtime, database, Shield authentication, Tenant, Shop, branding, domain and subscription foundations, but Phase 1 is not yet approved as complete.

## Start locally

```bash
cp env .env
composer install
npm ci
npm run build
php spark serve
```

Open `/`, `/auth`, `/platform-admin`, `/api/v1/health/live`, and `/api/v1/health/ready`. See [configuration](docs/configuration.md), [API conventions](docs/api/conventions.md), [module ownership](docs/modules/module-map.md), and [security foundation](docs/security/foundation.md).

Frontend code is authored in `resources/`. `npm run build` compiles Bootstrap/Sass to static CSS and copies pinned Vue/Dexie plus application ES modules into `public/assets`; it does not bundle JavaScript.
