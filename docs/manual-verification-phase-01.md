# Phase 1 end-to-end manual verification

Phase 1 recovery is being delivered in four reviewed work packages. The WP1 checks below are executable after the runtime/database dependencies are available; later sections remain acceptance targets for their authorized packages and must not be interpreted as already complete.

## WP1 runtime and security foundation

1. From a clean dependency state run `composer install --no-dev`, `npm ci`, and `npm run build`; confirm Vue and Dexie are self-hosted below `public/assets/vendor` and no browser request uses a CDN.
2. On disposable MySQL 8.4 run Shield migrations, the Phase 1 migration and `PhaseOneSeeder`; inspect `SHOW CREATE TABLE`, rollback/reapply, and seed twice. Expect InnoDB/`utf8mb4_unicode_ci`, retained identifier `ascii_bin`, all checks/FKs/guards, one active Sync Generation and Trial/Starter/Growth.
3. Confirm `/register` and registration POST are unavailable. Confirm password validation uses the same 12-character Shield policy as controlled account creation.
4. Submit a normal form, JSON mutation and multipart mutation with missing/invalid/valid CSRF. Expect invalid requests rejected and valid requests accepted; confirm the Fetch client receives the refreshed token.
5. Visit a protected browser page while logged out and expect branded authentication. Call a protected API and expect JSON `401`, never login HTML. Attempt external/protocol-relative redirect input and expect a same-origin fallback.
6. Exercise invalid/valid login, logout, generic recovery, expired session and inactive account. Inspect responses and logs for account enumeration or secrets.
7. Sign in with a real `platform_admin`, complete Shield email second factor, access Admin during the 15-minute window, expire it, and complete a new password/factor login. Confirm Owner/Staff identities are denied.
8. Exercise login, recovery and MFA limits until `429`, then confirm the documented retry interval.
9. Inspect WP1 Audit and logs. Expect bounded MFA-established/cleared evidence where applicable and no password, hash, MFA code, magic link, recovery secret, cookie, session ID or raw token.
10. Complete login, recovery, MFA, denied, expired and throttled screens at 320, 360, 390, tablet and desktop widths; repeat keyboard-only and 200% zoom.

Use two Tenants and distinct Owner/Staff accounts. For each workflow inspect the matching `audit_events` row and force one database failure to confirm mutation and Audit roll back together.

## Authentication and boundaries
1. Open `/auth` on each active Shop hostname: confirm resolved name/tagline/theme; an unknown or inactive hostname must show only safe platform presentation.
2. Sign in with valid/invalid credentials, sign out, follow recovery, expire a session, and deactivate a user. Expect generic failure wording, correct Shop redirect, no access after expiry/deactivation, and retained form values where safe.
3. Sign in as Platform Admin, enroll/complete Shield MFA, let the 15-minute recent-MFA window expire, and retry onboarding. Expect `MFA_REQUIRED` until reverified; ordinary Shop users remain denied.

## Platform onboarding UI
1. Complete Details → Review → Confirm on `/platform-admin`; verify exactly one Tenant/Shop/Owner/default brand/platform domain/30-day Trial/subscription event/Audit row.
2. Duplicate hostname/email and force a late transaction failure. Expect useful validation and no partial Tenant records.
3. Force failures after Shield identity, Tenant, Shop, subscription and Audit insertion. Confirm the shared transaction retains none of the Owner/Tenant graph.
4. Hold the onboarding advisory lock from another connection, remove the active Sync Generation, and deactivate the Trial plan in disposable data. Expect safe `409` outcomes and no writes.

## Tenant isolation and access
For every API and visible workflow, substitute IDs and Host headers from the second Tenant. Expect denial or not-found without data disclosure. Exercise FULL, expired READ_ONLY, suspended BLOCKED and CLOSED; mutation endpoints must reject non-FULL access while historical screens remain understandable.

## Staff and permissions
Create Staff through the UI, confirm deny-by-default, grant/revoke each catalog permission, deactivate/reactivate with reasons, recover credentials, and call endpoints directly as unauthorized Staff. Fill Trial to 5, Starter to 2 and Growth to 5 active Staff excluding Owner; the next create/reactivation must be refused without auto-deactivation.

Run two concurrent create requests at the last available slot and two concurrent reactivations. Expect one commit and one `STAFF_CAP_REACHED`; active Staff never exceeds the snapshot. Attempt a second active Tenant membership for the same identity and expect `MEMBERSHIP_CONFLICT`.

After deactivation and credential reset, retry an already-authenticated browser session and remembered login; expect `SESSION_EXPIRED`. Confirm the old password no longer works, the one-time displayed temporary password works only under Shield's forced-reset policy, and the secret is absent from Audit/logs.

Open a Staff permission panel with pre-existing grants. Confirm checked state comes from the server, search/grouping works, grant/revoke refreshes state/history, revoke retains its reason, and repeated or cross-Tenant operations produce conflict/not-found without Audit fiction or foreign disclosure.

## Shop, branding and assets
Update every Shop field and submit invalid email/timezone/address data. Update every branding field and invalid colours; confirm live preview, incremented configuration version, title/theme propagation and escaped copy. Upload/replace logo, favicon, 192/512 icons and login background; attempt fake extensions, malformed/polyglot/oversize/wrong-dimension files. Expect decoded-image validation, opaque private storage and only active scoped assets served.

For Shop settings also test phone punctuation normalization, optional blanks to null, overlong values, whitespace trimming and attempted ID/Tenant/status/currency mutation. Only approved fields may change; Audit must show actual bounded before/after values.

## Domains
Confirm platform domain display. Request custom hostname and follow displayed DNS TXT instructions. Run the domain verification Spark command for missing, mismatched and valid DNS; coordinate TLS provider status, then activate/switch primary. Expect no activation before verified ownership plus active TLS, no raw token persisted, and audited lifecycle changes.

## Subscription, responsive and accessibility
Confirm plan/type/start/end/cap/current active Staff/effective mode for Trial, Paid and expired periods. Complete every UI workflow at 320, 360, 390, tablet and desktop widths: no wide-table dependency or lost action. Repeat keyboard-only and 200% zoom; labels, focus, errors and status must remain perceivable.
