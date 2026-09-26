# Phase 1 end-to-end manual verification

Use two Tenants and distinct Owner/Staff accounts. For each workflow inspect the matching `audit_events` row and force one database failure to confirm mutation and Audit roll back together.

## Authentication and boundaries
1. Open `/auth` on each active Shop hostname: confirm resolved name/tagline/theme; an unknown or inactive hostname must show only safe platform presentation.
2. Sign in with valid/invalid credentials, sign out, follow recovery, expire a session, and deactivate a user. Expect generic failure wording, correct Shop redirect, no access after expiry/deactivation, and retained form values where safe.
3. Sign in as Platform Admin, enroll/complete Shield MFA, let the 15-minute recent-MFA window expire, and retry onboarding. Expect `MFA_REQUIRED` until reverified; ordinary Shop users remain denied.

## Platform onboarding UI
1. Complete Details → Review → Confirm on `/platform-admin`; verify exactly one Tenant/Shop/Owner/default brand/platform domain/30-day Trial/subscription event/Audit row.
2. Duplicate hostname/email and force a late transaction failure. Expect useful validation and no partial Tenant records.

## Tenant isolation and access
For every API and visible workflow, substitute IDs and Host headers from the second Tenant. Expect denial or not-found without data disclosure. Exercise FULL, expired READ_ONLY, suspended BLOCKED and CLOSED; mutation endpoints must reject non-FULL access while historical screens remain understandable.

## Staff and permissions
Create Staff through the UI, confirm deny-by-default, grant/revoke each catalog permission, deactivate/reactivate with reasons, recover credentials, and call endpoints directly as unauthorized Staff. Fill Trial to 5, Starter to 2 and Growth to 5 active Staff excluding Owner; the next create/reactivation must be refused without auto-deactivation.

## Shop, branding and assets
Update every Shop field and submit invalid email/timezone/address data. Update every branding field and invalid colours; confirm live preview, incremented configuration version, title/theme propagation and escaped copy. Upload/replace logo, favicon, 192/512 icons and login background; attempt fake extensions, malformed/polyglot/oversize/wrong-dimension files. Expect decoded-image validation, opaque private storage and only active scoped assets served.

## Domains
Confirm platform domain display. Request custom hostname and follow displayed DNS TXT instructions. Run the domain verification Spark command for missing, mismatched and valid DNS; coordinate TLS provider status, then activate/switch primary. Expect no activation before verified ownership plus active TLS, no raw token persisted, and audited lifecycle changes.

## Subscription, responsive and accessibility
Confirm plan/type/start/end/cap/current active Staff/effective mode for Trial, Paid and expired periods. Complete every UI workflow at 320, 360, 390, tablet and desktop widths: no wide-table dependency or lost action. Repeat keyboard-only and 200% zoom; labels, focus, errors and status must remain perceivable.
