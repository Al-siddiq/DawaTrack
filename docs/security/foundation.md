# Security foundation

DawaTrack uses local scripts/styles and a restrictive CSP without inline runtime code. Baseline headers deny framing and object embedding, restrict browser capabilities, prevent MIME sniffing, and add HSTS in production. Server-rendered values use CodeIgniter escaping. Cookies are HTTP-only, secure in production, and SameSite Strict.

Production uses HTTPS, `CI_ENVIRONMENT=production`, disabled debug output, environment-only secrets, and a non-root least-privilege MySQL account. Logs may record request IDs, safe routes, status, and security event codes; they must not record credentials, tokens, cookies, customer health details, request bodies, or other secrets.

Public registration is disabled. Shield owns password hashing, magic-link recovery, login actions, sessions and groups. DawaTrack uses the explicit `platform_admin` group; ordinary Tenant membership never grants platform authority. Shield's native email second factor runs at login, while sensitive Admin routes also require a server-established factor timestamp no older than 15 minutes.

The global session CSRF filter protects form, JSON and multipart mutations. Browser navigation redirects to same-origin branded authentication; API requests receive JSON authentication/authorization outcomes rather than an HTML login redirect. Shared throttling protects login, recovery, MFA and Platform Admin mutation routes and is the policy reused by later credential/upload/domain work.

Successful high-value Admin MFA establishment and clearing are written to Audit when the Phase 1 Audit table is available. Passwords, hashes, MFA codes, magic links, recovery material, cookies, session identifiers and authentication tokens are never Audit metadata or log context.
