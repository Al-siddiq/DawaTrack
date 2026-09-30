# Phase 1 Recovery & Completion — WP3 Text-Only Reimplementation Report

## A. WP3 Reimplemented

WP3 is implemented end-to-end for branding profiles, five runtime-upload asset classes, runtime presentation, the canonical dynamic manifest, custom-domain challenge and verification, Platform Admin TLS/activation operations, primary switching, deactivation, platform fallback, authorization, Audit, and responsive source UI. WP4 was not started.

## B. Zero-Binary Compliance

- Number of new image files: `0`.
- Number of generated image files: `0`.
- Number of new binary files: `0`.
- No screenshot, fixture, embedded image, SVG, font, archive, executable, or encoded binary content was created.

## C. Binary/Image Files Removed

`public/manifest.webmanifest` was removed because the web server could otherwise bypass the canonical dynamic PHP route. It was text, not binary. No WP3-generated binary was present at task start, and therefore no binary path required removal. In particular, `public/assets/icons/pwa-192.png` and `public/assets/icons/pwa-512.png` did not exist and were not created.

## D. WP1/WP2 Preserved

The existing Shield, Email2FA/recent-MFA, CSRF, throttling, session, API-envelope, onboarding, Tenant-context, membership, Staff-cap, credential recovery, permission, access-mode, Shop-setting, Bootstrap/Sass, Vue 3 and native Fetch foundations remain in place. The schema and its `utf8mb4_unicode_ci` defaults and intentional `ascii_bin` normalized identifiers were not changed.

## E. Branding Profile

The profile service trims inputs, normalizes blank optional text to `NULL`, enforces schema lengths and required fields, normalizes five colours to uppercase `#RRGGBB`, and blocks primary/background combinations below WCAG 4.5:1. It reports field-specific errors, rejects no-op updates, increments the configuration version once, and writes bounded before/after Audit metadata in the owning transaction.

## F. Branding Editor

The Owner editor covers all approved text and colour fields, receives server field errors, refreshes authoritative profile/asset data after success, and uses a CSS/text preview with initials. It contains no generated image preview; it displays only a real, already-uploaded runtime asset through the controlled endpoint.

## G. Runtime Asset Upload Infrastructure

`LOGO`, `FAVICON`, `LOGIN_BACKGROUND`, `PWA_ICON_192`, and `PWA_ICON_512` accept real user PNG/JPEG uploads. The server inspects MIME independently, decodes the image, applies decoded-pixel and type constraints, re-encodes through GD (stripping source metadata), hashes normalized bytes, uses opaque private names and permissions, and records active metadata and Audit. Delivery is Tenant-scoped for authenticated previews and active-host/scoped for immutable public presentation, with `nosniff` and controlled content types.

## H. Asset Validation Rules

| Runtime type | Bytes | Dimensions/aspect |
|---|---:|---|
| Logo | 2 MiB | 120×40 minimum; 2400×1200 maximum; ratio 0.2–8.0 |
| Favicon | 512 KiB | square, 32–512 px |
| Login background | 5 MiB | 800×450 minimum; 5000×5000 maximum; ratio 0.8–3.0 |
| PWA 192 | 1 MiB | exactly 192×192 |
| PWA 512 | 2 MiB | exactly 512×512 |

All types are capped at 16 million decoded pixels and restricted to server-confirmed PNG/JPEG. Animated formats are not accepted.

## I. File/Database Compensation

A real upload is normalized into a private temporary file before metadata mutation. The transaction deactivates old metadata, inserts the replacement, and appends Audit. Only after commit is the file atomically renamed. Validation/transaction failures remove temporary output. A finalization failure runs compensation that removes replacement metadata, reactivates the prior metadata, and removes new filesystem material; the previous physical asset is never overwritten.

## J. Runtime Branding Propagation

The resolver supplies content-hash-versioned URLs. Shell/auth layouts apply names, title, favicon, logo, CSS colours, and a real login-background URL when present. Missing logo/background/favicon use initials/text/CSS or omit the reference; no image is fabricated.

## K. Dynamic PWA Manifest

`/manifest.webmanifest` is the one canonical manifest and emits id, names, description, start URL, scope, display and colours. Shop PWA assets win. Otherwise, each manual platform fallback is included only if its physical file exists. With neither source, the valid manifest omits `icons`. Its short cache policy permits configuration changes to appear, while uploaded assets retain content-hash immutable URLs.

## L. Hostname / IDN Policy

`HostnamePolicy` trims the single terminal dot, converts Unicode using UTS #46 `intl` IDN conversion, lowercases canonical ASCII, validates total/label syntax and rejects schemes, credentials, ports, paths, IPs, wildcards, internal/reserved labels, platform apex and platform suffixes. Database uniqueness applies to this canonical value.

## M. DNS Challenge / Verification

Requests create a cryptographically random 256-bit value and persist only SHA-256. The one-time response explicitly returns TXT type, `_dawatrack-verification.<hostname>` name and value. Rotation invalidates the old digest and returns a new value once. Owner checks and the Spark command use the same verification service. DNS lookup errors are retryable; mismatches remain pending; successful verification and Audit are atomic. Raw values never enter Audit.

## N. TLS Workflow

The Platform Admin API/UI can mark verified custom domains TLS ready or failed. The route requires Shield Platform Admin identity, `platform.domains`, recent MFA through the established filter, CSRF, throttling policy inherited by platform writes, and a reason. No certificate or private key is stored.

## O. Activation / Primary / Deactivation / Fallback

Activation requires verified ownership and active TLS. Primary selection requires an active domain and transactionally demotes the old primary. A custom primary must first be switched away before deactivation. The active platform domain can explicitly become primary again. Each transition is reasoned and audited; normal operations require no database editing.

## P. Tenant Isolation / Security

Owner operations derive Tenant/Shop/membership from authenticated hostname context and require Primary Owner plus mutable access. Public assets require an active verified hostname and matching active scoped asset. Admin operations derive actor identity server-side. Tokens, bytes, storage keys, MFA/session material and TLS secrets are excluded from Audit.

## Q. Audit Coverage

Atomic actions cover branding update, asset replacement, domain request, challenge rotation, DNS verification, TLS ready/failed, activation, primary switch, deactivation, and platform fallback. Metadata is bounded and contains no raw challenge or image material.

## R. Responsive / Accessibility

Branding, asset, DNS, and Admin operations use labelled forms, wrapping cards/actions, break-safe DNS text, status text, normal Bootstrap controls, focus-visible inherited styling and no wide-table dependency. The source is designed for 320/360/390 px, tablet, desktop, keyboard and 200% zoom. No screenshot was captured; the manual checklist below remains the browser evidence procedure.

## S. Files Created

All are text/source:

- `app/Controllers/Api/PlatformDomainController.php`
- `app/Modules/BrandingDomain/Application/HostnamePolicy.php`
- `public/assets/icons/.gitkeep`
- `docs/Phase_1_Recovery_and_Completion_Work_Package_3_Text_Only_Delivery_Report.md`

## T. Files Modified

All are text/source or generated text CSS/JavaScript:

- `app/Commands/VerifyShopDomains.php`
- `app/Config/AuthGroups.php`
- `app/Config/Routes.php`
- `app/Controllers/Api/AssetController.php`
- `app/Controllers/Api/ShopController.php`
- `app/Controllers/Web/BrandAssetController.php`
- `app/Controllers/Web/ShellController.php`
- `app/Modules/BrandingDomain/Application/BrandingService.php`
- `app/Modules/BrandingDomain/Application/DomainService.php`
- `app/Views/admin/index.php`
- `app/Views/auth/layout.php`
- `app/Views/layouts/shell.php`
- `app/Views/shop/index.php`
- `resources/css/app.scss`
- `resources/js/admin/app.js`
- `resources/js/api/phase-one.js`
- `resources/js/shop/app.js`
- `public/assets/css/app.css`
- `public/assets/js/admin/app.js`
- `public/assets/js/api/phase-one.js`
- `public/assets/js/shop/app.js`

## U. Files Removed

- `public/manifest.webmanifest` (text static manifest superseded by canonical dynamic route).

## V. Binary-File Audit

The changed-path audit uses `git diff --numstat`, NUL-safe path enumeration, prohibited-extension matching, `git diff --check`, and a byte-level NUL scan of every added/modified path. Result: **No binary file remains among files introduced by WP3.** Zero image files and zero binary files were generated or introduced.

## W. Manual Image Requirements

| Asset | Filename | Destination | Dimensions | Format | Purpose | Missing-file behavior |
|---|---|---|---|---|---|---|
| Platform PWA fallback | `pwa-192.png` | `public/assets/icons/` | 192×192 | PNG | Manifest fallback when a Shop has no uploaded 192 icon | Manifest omits this entry |
| Platform PWA fallback | `pwa-512.png` | `public/assets/icons/` | 512×512 | PNG | Manifest fallback when a Shop has no uploaded 512 icon | Manifest omits this entry |

These are the only static images expected by WP3 code and neither was created. Shop logo, favicon, login background and PWA images are optional user uploads at runtime, not manually deployed platform files.

## X. Documentation Updated

This report documents runtime rules, transitions, security, cache/fallback behavior, exact file inventory, manual images, checks and browser procedures.

## Y. Verification Actually Performed

- Read all four authoritative baseline documents before implementation.
- PHP syntax checked every PHP file under `app/`.
- Node syntax checked changed source and published JavaScript.
- Rebuilt Sass and published source modules; build completed with upstream Sass deprecation warnings only.
- Compared source/published WP3 JavaScript copies.
- Ran whitespace/error inspection and prohibited content/extension scans.
- Performed the byte-level changed-file audit without generating fixtures, images or screenshots.

Manual runtime procedure: install Composer dependencies and MySQL, migrate/seed, sign in as Owner, exercise every boundary/invalid branding value and real upload class, force DB/Audit/finalization failures, validate auth/shell/manifest on platform and custom hosts, rotate/check DNS, then sign in as recent-MFA Platform Admin and exercise TLS/activate/primary/fallback/deactivate. Repeat at 320, 360, 390 px, tablet, desktop, keyboard-only and 200% zoom. Inspect Audit after every successful transition and confirm failed transitions write neither state nor false Audit.

## Z. Deviations / Blockers

No WP3 implementation blocker remains. Database, DNS, HTTP, browser, GD behavior and real user-upload execution require an environment with Composer runtime, MySQL, controlled DNS, PHP GD/intl, and a browser. No automated suite or binary fixture was created, as directed.

## AA. WP3 Acceptance Assessment

The approved WP3 scope has corresponding text-only implementation and static verification. Runtime acceptance evidence remains environment-dependent and must be completed using the documented procedure before final Phase 1 approval.

## AB. Remaining WP4 Scope

Subscription renewal/upgrade/downgrade/Trial extension, Tenant suspend/reactivate/close, Shop lifecycle, final Audit closure, final Phase 1 acceptance and Phase 2 remain untouched.

## AC. Git Commit / Change Summary

WP3 adds production branding validation and runtime upload handling, canonical dynamic PWA presentation, hardened host/DNS policy, complete reasoned domain state operations, Owner/Admin responsive UI, private delivery controls, text-only documentation and zero-binary fallback handling. The delivery is committed on the current branch and its pull-request metadata is created after verification.
