# DawaTrack configuration

## Local setup

1. Install PHP 8.2+ extensions required by CodeIgniter and MySQLi.
2. Run `composer install --no-dev` for a production install (omit `--no-dev` locally if framework tooling is needed).
3. Copy `.env.example` to `.env`; never commit `.env`.
4. Set a unique `DAWATRACK_DEPLOYMENT_ID`, application URL, and least-privilege database credentials.
5. Run `npm ci && npm run build`, then deploy only the generated `public/assets` files with the application.
6. Run `php spark serve` for local manual verification.

Staging and production must set `CI_ENVIRONMENT = production`, an HTTPS `app.baseURL`, and `app.forceGlobalSecureRequests = true`. Secrets belong only in the runtime environment or deployment secret store. Debug output must not be enabled outside development.

## Database baseline

MySQL 8.4 LTS is required. Provision the application account only for the selected DawaTrack database. Configure the server/session for `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`, UTC (`+00:00`), InnoDB, `utf8mb4`, and `utf8mb4_0900_ai_ci`. Phase 0 creates no domain tables.

Readiness requires `DAWATRACK_DEPLOYMENT_ID`; `/api/v1/health/ready` returns a safe 503 envelope if it is absent. Liveness never reveals configuration or secrets.
