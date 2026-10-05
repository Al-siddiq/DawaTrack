# Security foundation

DawaTrack uses local scripts/styles and a restrictive CSP without inline runtime code. Baseline headers deny framing and object embedding, restrict browser capabilities, prevent MIME sniffing, and add HSTS in production. Server-rendered values use CodeIgniter escaping. Cookies are HTTP-only, secure in production, and SameSite Strict.

Production uses HTTPS, `CI_ENVIRONMENT=production`, disabled debug output, environment-only secrets, and a non-root least-privilege MySQL account. Logs may record request IDs, safe routes, status, and security event codes; they must not record credentials, tokens, cookies, customer health details, request bodies, or other secrets.

Public registration is disabled. Shield owns password hashing, magic-link recovery, login actions, sessions and groups. DawaTrack uses the explicit `platform_admin` group; ordinary Tenant membership never grants platform authority. Shield's native email second factor runs at login, while sensitive Admin routes also require a server-established factor timestamp no older than 15 minutes.

The global session CSRF filter protects form, JSON and multipart mutations. Browser navigation redirects to same-origin branded authentication; API requests receive JSON authentication/authorization outcomes rather than an HTML login redirect. Shared throttling protects login, recovery, MFA and Platform Admin mutation routes and is the policy reused by later credential/upload/domain work.

Successful high-value Admin MFA establishment and clearing are written to Audit when the Phase 1 Audit table is available. Passwords, hashes, MFA codes, magic links, recovery material, cookies, session identifiers and authentication tokens are never Audit metadata or log context.

Phase 2 WP1 centralizes Audit authority validation. Tenant evidence requires a verified active membership matching the actor, Tenant, and Shop. Platform evidence verifies the Shield `platform_admin` group and never creates Tenant membership. System evidence has no user. Device attribution remains untrusted and `NULL` until the approved Registered Device phase.

One lowercase UUIDv4 correlates each request/command across response metadata/header, relevant logs, and Audit. Audit metadata is rejected when it exceeds structural or total-size limits or contains forbidden secret/request-body/binary keys. These failures abort the owning transaction rather than degrading evidence.

Phase 2 WP3 keeps normal Platform Audit and Tenant-support Audit separate. Normal viewing requires `platform.audit` and recent MFA and cannot silently apply Tenant filters. Support viewing requires `platform.audit_support`, an explicit server-resolved Tenant/Shop, a required reason, and committed SUPPORT evidence before results. The continuation is bound to the same administrator and expires after 15 minutes; it creates no Tenant membership, impersonated identity, or durable support session. Audit query output is redacted, scope-limited, bounded, and rate-limited; foreign identifiers remain non-enumerating.
