# Security foundation

DawaTrack uses local scripts/styles and a restrictive CSP without inline runtime code. Baseline headers deny framing and object embedding, restrict browser capabilities, prevent MIME sniffing, and add HSTS in production. Server-rendered values use CodeIgniter escaping. Cookies are HTTP-only, secure in production, and SameSite Strict.

Production uses HTTPS, `CI_ENVIRONMENT=production`, disabled debug output, environment-only secrets, and a non-root least-privilege MySQL account. Logs may record request IDs, safe routes, status, and security event codes; they must not record credentials, tokens, cookies, customer health details, request bodies, or other secrets.
