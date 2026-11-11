# Owner, Admin and Auth developer reference

UI3-B, 11 November 2026. Implemented for review; acceptance pending. Depends on UI3-A PR #35 and its integrated UI2 foundation.

## Module and state ownership

| Source | Responsibility |
|---|---|
| `components/owner-panel.js` | Staff/details/appearance/web-address/plan task bodies; shared fields, catalogue, disclosures and secret-output guard |
| `shop/app.js` / `shop/template.html` | Existing API commands, role/capability gates and reason/reset dialog remain owners; reactive `ownerTools` presents the existing refs/functions to OwnerPanel |
| `components/management-ui.js` | Display labels only, including all current permission codes; re-exports shared facts/select/error/local-decision helpers from UI3-A |
| `components/opening-cash-panel.js` | Existing exact string normalization and original attempt/status transitions; final/abort decisions; unknown/read-only/offline states |
| `components/audit-panel.js` | Existing Owner/platform/support contracts, target/reason/access_id, cursor and immutable detail evidence |
| `admin/app.js` | Exported AdminApp, existing operation payloads/grants, onboarding review and secret cleanup; shared workspace navigation |
| `auth/entry.js` | Progressive enhancement over native forms: submit lock, restored busy buttons, show/hide/remask password and server-error links |
| `app/Views/auth/*` / `app/Config/Auth.php` | Native view-only forms and existing Auth routes; three local Shield screen mappings |
| `resources/css/ui/_management.scss` | Scoped compact management forms, permission lists, secret output, Activity metadata and native Auth utilities |
| `app/Views/errors/html/{error_400,error_404,production}.php` | Minimal service-independent recoverable error presentation; no raw exception detail in ordinary production UI |

Both ShopApp and AdminApp expose their component object for inspection and mount normally when their real mount element exists. The production runtime-only Vue build still uses the generated Shop renderer and `h()` Admin/components. Do not add runtime template strings, untrusted inline styles or external icon requests. Rebuild with the normal Node pipeline after source/template/Sass edits; commit generated copies.

## Owner contracts

`ownerTools` is a reactive presentation adapter over existing state and callbacks, not another store or API layer. Staff, settings, branding, assets, domains, subscription, field errors and read/loading/busy state retain their original command ownership. File selection passes the selected File to the existing upload command; the input ref stays in the component. `FormData` keys remain `asset_type` and `asset`. No credentials, uploaded file data or one-time setup values are copied to storage/logs.

OwnerPanel is mounted only for current authorized navigation destinations; server capabilities and current access policy remain authoritative. Changes are disabled while pending/read loading, read-only or offline. Reload is permitted for read-only users and uses the shared dirty/unknown guard. Common safe saved notices may dismiss using the accepted shared timer; instructions, one-time values, errors and unknown outcomes remain persistent.

Staff status/grant/reset commands still require their original reason and use the existing dialog. Reset output is selectable, shown once, excluded from print and cleared on dialog close. Creating Staff clears a failed/submitted password as before; navigation/unmount also clears an entered password. Labels never change permission codes, the grant API or catalogue membership. Future-reserved grant entries remain catalogue entries; they do not add future task destinations.

A DNS setup value is protected by a leave warning. Explicit acknowledgment removes the displayed value. Replacing the value warns that the old one no longer works. Leaving after an explicit discard clears it. Exact DNS type/name/value and dimensions remain technical setup facts because the operator must copy them accurately; routine task labels use plain English. The platform address fallback and Admin responsibility for TLS/activation remain.

## Starting cash contract

Keep the original `canonicalCash` decimal-string normalizer and command payloads. `attemptId` is generated once per attempt and reused; abort may create the next attempt ID only through the existing path. COUNTING → COUNTS_COMMITTED → REVIEW → FINALIZED remains the server state machine, with the original count guard before commit. Finalize/abort ask for an explicit local decision before the original command runs. Abort still requires a reason and retains evidence.

Unknown replies never create a new attempt. Existing refresh reads the current authoritative state; if uncertain status cannot be read, the hard navigation/write guard remains until Check saved status succeeds. Native count/history/cutoff/generation fields are preserved under support details. Starting cash is not a daily or resettable balance. An automatic status refresh after an error can hydrate the confirmed saved count, matching the existing workflow; do not describe an unconfirmed reply as a confirmed failure.

## Admin contracts

Navigation and action visibility use the existing server-evaluated platform grants. Onboarding retains all original keys: business/shop/contact/address/subdomain/owner/password/reason. Native validity precedes review; the review only renders a hidden-password label. Submission clears the temporary password on success or failure; leaving/unmount clears it too. Completion uses returned identifiers/address, not guessed account or trial data.

Lifecycle commands retain tenant-vs-shop targets and original endpoints. Reasons are mandatory. The dialog explains pause/stop/reopen/permanent close, record retention and current eligibility. Plan commands retain ACTIVATE/RENEW/UPGRADE/DOWNGRADE/EXTEND, STARTER/GROWTH/TRIAL, UTC starts/ends, external reference and reason. Labels are presentation only. Native `datetime-local` strings remain the existing UTC contract; no new local-time conversion is performed. Field errors are scoped to the affected plan form; onboarding error links target its real inputs.

Web-address commands retain tls ready true/false, activate, primary/fallback and deactivate rules. Existing backend checks still own verification/TLS/readiness/primary selection. Admin search filters the already-authorized loaded dataset; it is not a new cross-tenant API. Load and mutation failures show safe messages; no generic automatic retry or idempotency service is introduced. Inspect saved state before repeating an uncertain account/plan operation.

## Activity/support contracts

Owner and platform scopes use their original read APIs. Support access uses `supportAudit` POST with target shop/business/reason first, then returned `access_id` and filters/cursor; detail POST carries only that support ID. The separate support grant controls the entry action, and the server still authorizes every request. Support view is explicitly not impersonation or Shop operating access. Leaving clears support scope/cursor/records; late read responses are disregarded using the current generation/alive check.

All eight original filters remain. Readable labels/summaries accompany exact action/support references under support details. JSON metadata and related events come only from the existing authorized response, rendered as text; no unredacted fetch or HTML injection is added. Detail uses one native sheet, loading/error/empty states and cursor-based Load more. No client retention of a support authorization outside component lifetime.

## Native Auth and error contracts

The three new local email-code views were matched against the pinned source:
- https://github.com/codeigniter4/shield/blob/93c5f1f1481ced93ceb4071ffa3a08a9647a7c8b/src/Views/email_2fa_show.php
- https://github.com/codeigniter4/shield/blob/93c5f1f1481ced93ceb4071ffa3a08a9647a7c8b/src/Views/email_2fa_verify.php
- https://github.com/codeigniter4/shield/blob/93c5f1f1481ced93ceb4071ffa3a08a9647a7c8b/src/Views/email_activate_show.php

Show still posts `email` to `auth-action-handle`; verify/activation still post `token` to `auth-action-verify`; CSRF stays native. Code inputs use text + numeric input mode/pattern so leading zeroes are retained. No new fixed code length/lifetime or server policy is assumed. Vendor email templates/action classes are unchanged. Config changes only those three screen mappings.

Remembering and magic-link availability remain conditional on current settings; recovery messages do not confirm account existence. Forced password change retains current_password/password/password_confirm, required and 12-character attributes and the existing same-origin POST. Browser required validity is enabled as an additional UI aid; backend validation remains authoritative. Step-up still restarts the existing Admin MFA flow and ends the existing session; denial grants no authority. `adminEntryOverride` controls presentation only.

The native enhancement never reads/copies password/token values into JS state. It operates on control types and submit-button markup only. Server summary keys are mapped with `form.elements.namedItem`, associated with existing input IDs, and copied as text into inline errors. Summary receives focus; typing does not refocus it. Password visibility resets on pageshow, as do disabled/busy buttons. Auth notifications are closable, not automatically timed away; verification/security instructions must remain readable. Error views use only static links/local CSS so exception handling does not depend on a database or authenticated layout. HTTP status, Retry-After, logs and development exception/CLI diagnostics remain controller/framework responsibilities.

## Handover boundary

No schema migration, new business endpoint, permission grant, session/CSRF/token algorithm or financial calculation. Build and host/grammar checks are engineering evidence only. Native XAMPP/PHP/Shield/email/CSP/browser/AT/keyboard/device/print and destructive-operation regression remain manual. See the 42-case checklist. UI3-A acceptance is not inferred from authorization to start B. UI4-A remains full programme traceability, native evidence, defect resolution and release/rollback acceptance.
