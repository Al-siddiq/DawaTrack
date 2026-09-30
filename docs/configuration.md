# DawaTrack configuration

## Local setup

1. Install PHP 8.2+ extensions required by CodeIgniter and MySQLi.
2. Run `composer install --no-dev` for a production install (omit `--no-dev` locally if framework tooling is needed).
3. Copy the tracked `env` template to `.env`; never commit `.env`.
4. Set a unique `DAWATRACK_DEPLOYMENT_ID`, application URL, and least-privilege database credentials.
5. Run `npm ci && npm run build`, then deploy only the generated `public/assets` files with the application.
6. Run `php spark serve` for local manual verification.

Staging and production must set `CI_ENVIRONMENT = production`, an HTTPS `app.baseURL`, and `app.forceGlobalSecureRequests = true`. Secrets belong only in the runtime environment or deployment secret store. Debug output must not be enabled outside development.

## Database baseline

MySQL 8.4 LTS is required. Provision the application account only for the selected DawaTrack database. Configure the server/session for `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`, UTC (`+00:00`), InnoDB, `utf8mb4`, and `utf8mb4_unicode_ci`. UUIDs and normalized hostnames deliberately retain `ascii_bin` comparison.

Readiness requires `DAWATRACK_DEPLOYMENT_ID`; `/api/v1/health/ready` returns a safe 503 envelope if it is absent. Liveness never reveals configuration or secrets.

## Phase 1

Set `DAWATRACK_PLATFORM_DOMAIN` to the canonical platform hostname suffix. Configure Shield session authentication, generic login/recovery errors, CSRF, password policy, login/recovery rate limits, and the `platform_admin` group. Production Platform Administrators must complete Shield email MFA; the Admin boundary rejects sessions without a recent MFA verification. Branding bytes are stored below `writable/uploads/branding` and must be served only through an authorized, non-executable delivery endpoint.

`vendor/` and `node_modules/` are disposable, ignored dependency outputs. Production serves the Vue runtime, compiled Bootstrap CSS and application modules copied into `public/assets` by `npm run build`; it never serves files from `node_modules` and uses no public CDN.

## Authentication and sessions

Public registration is disabled. Accounts are created only through controlled Platform Admin and Owner workflows. Shield accepts email credentials, enforces a 12-character minimum plus its composition, personal-information and dictionary validators, and uses time-limited magic-link recovery with generic non-enumerating presentation.

Shield's native email second-factor action is mandatory at login. A Platform Administrator additionally requires the `platform_admin` group. After the native Shield factor completes, the server binds a recent-MFA timestamp to that Admin user and session for 15 minutes. When it expires, the Admin is signed out and must complete password and email-factor authentication again. Logout and step-up restart clear recent-MFA state.

The server session has a two-hour configured idle lifetime and an eight-hour application-enforced absolute lifetime. Session identifiers rotate every five minutes with the previous data destroyed. Production cookies are Secure, HTTP-only and SameSite Strict. Absolute expiry returns JSON `SESSION_EXPIRED` to APIs and the branded expired-session route to browsers.

All session-authenticated writes use the session CSRF filter. Server-rendered forms include `csrf_field()`. The Fetch client sends the configured CSRF header and adopts the refreshed response token. Authentication, recovery, MFA and Platform Admin writes use the shared sensitive throttling filter.

## Phase 1 WP4 operations

The controlled `platform_admin` group receives the focused `platform.subscriptions` and `platform.tenants` permissions. All `/api/v1/platform/operations/*` writes remain behind the established Platform Admin identity, recent-MFA, CSRF, and rate-limit filters. No subscription processor, queue, Redis, or worker configuration is required. Schedule expiry evidence, if desired, through cron invoking `php spark subscriptions:record-expiries <platform-admin-user-id>` under a controlled operational identity.
