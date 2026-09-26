# Phase 1 bootstrap

1. Configure MySQL 8.4 and environment values, including `DAWATRACK_PLATFORM_DOMAIN`.
2. Run Shield's pinned migrations first: `php spark migrate --all`.
3. Run DawaTrack migrations: `php spark migrate`.
4. Run controlled bootstrap data: `php spark db:seed PhaseOneSeeder` once per environment.
5. Create the first Platform Administrator through the controlled Shield CLI procedure, assign only the `platform_admin` group, enroll MFA, then invalidate the bootstrap credential.
6. Restrict the runtime DB account from UPDATE/DELETE on `audit_events` after migrations.

The seeder is repeat-safe for plan codes and the active Sync Generation. Trial prices are zero; Starter/Growth commercial prices must be explicitly configured before paid entitlement creation.
