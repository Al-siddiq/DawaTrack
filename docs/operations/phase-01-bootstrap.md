# Phase 1 bootstrap

1. Configure MySQL 8.4 and environment values, including `DAWATRACK_PLATFORM_DOMAIN`.
2. Run Shield's pinned migrations first: `php spark migrate --all`.
3. Run DawaTrack migrations: `php spark migrate`.
4. Run controlled bootstrap data: `php spark db:seed PhaseOneSeeder` once per environment.
5. Create the first Platform Administrator through a controlled Shield CLI/bootstrap procedure, assign only the `platform_admin` group, verify the account email, and invalidate the bootstrap credential. The first login completes Shield's native email second factor; DawaTrack then establishes the 15-minute recent-MFA session state.
6. Restrict the runtime DB account from UPDATE/DELETE on `audit_events` after migrations.

The seeder is repeat-safe for plan codes and the active Sync Generation. Trial prices are zero; Starter/Growth commercial prices must be explicitly configured before paid entitlement creation.

Never create a synthetic Tenant membership for a Platform Administrator. MFA reset/recovery is an operator-controlled identity recovery: clear active sessions, confirm control of the verified Admin email, issue the normal Shield recovery flow, and require a fresh password plus email factor. No MFA code, magic link or session value belongs in tickets, logs or Audit metadata.
