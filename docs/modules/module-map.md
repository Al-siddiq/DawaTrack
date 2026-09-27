# Module ownership and dependency direction

Phase 0 exposes only foundation code:

- `Controllers/Web` owns the three server-rendered entry shells.
- `Controllers/Api` owns transport endpoints such as health/readiness.
- `Libraries/Http` owns request context and API response contracts.
- `Filters` owns cross-cutting request correlation and response security headers.
- `resources` is editable frontend source; `public/assets` is generated, browser-served output.

Future business modules will live under their own namespaces and may depend on shared foundation contracts. Shared foundation code must not depend on a business module. Controllers remain transport adapters and must not own business rules. Platform Admin and Shop entry boundaries remain separate. No tenant, identity, or business module exists in Phase 0.
