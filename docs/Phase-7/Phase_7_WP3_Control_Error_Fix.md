# Phase 7 WP3 — shared control-page error investigation

16 November 2026 (Africa/Lagos). Follow-up to WP3 `e84bb31`, prompted by generic failures on Cash, Stock and Full pages plus an uncertain start. **The Owner supplied a handled HTTP503 response with DEVICE_SETUP_REQUIRED.** This confirms the shared DeviceSchema readiness check is refusing control access. The Owner subsequently supplied migration status showing all nine Phase7 App migrations pending (no group/time/batch). The installed schema is still Phase6; this confirms unapplied Phase7 migrations as the cause. Screenshots alone did not identify it. No migration or production change has been performed.

## Confirmed defects and corrections

The shared commercial message helper replaced every5xx response with generic text, including explicit DEVICE_SETUP_REQUIRED, CASH_CONTROL_SETUP_REQUIRED, STOCK_CONTROL_SETUP_REQUIRED and READINESS_SETUP_REQUIRED responses. All three control history APIs use the shared Device/evidence schema checks, so missing common setup can produce the same banner everywhere. The supplied response subsequently confirms DEVICE_SETUP_REQUIRED; the pending Device migrations explain the first failed prerequisite, with all WP2/WP3 schema migrations also pending.

Known503 setup codes now use a narrow allowlist of locally authored operator instructions. Unexpected500/503 codes, server details and arbitrary setup-like codes remain redacted; support references remain visible. No database guard, FK requirement or Device readiness requirement is bypassed.

The start form also stayed enabled after a failed initial history request. A successful server load is now required before commands can be submitted, including direct form submit; failure no longer displays a misleading empty-history claim. Explicit Check saved status can reload after server recovery. Existing unknown-outcome markers/control UUIDs are retained; the fix does not assume the earlier start failed, clear site storage or automatically retry a POST. Scope-aware navigation warnings now name Cash, Stock or Full correctly.

## Confirmed response and remaining setup inspection

The Owner supplied Cash history error.code=DEVICE_SETUP_REQUIRED, alongside request.completed503 logs and no unexpected-exception log. Cash/Stock/Full share the same Device preflight. Starting cash and existing Sales/Customer/Product endpoints returning200 do not demonstrate the new Phase7 Device schema is complete. The subsequent migration-status output confirms all nine Phase7 App migrations are pending; the new schema must be applied through the documented backed-up procedure.

## Confirmed remediation on the Owner’s local database

The supplied migration status marks CreatePhaseSevenRegisteredDevices, AddPhaseSevenAuditDeviceForeignKey, the six WP2 evidence migrations and AddPhaseSevenStockVarianceForeignKey as pending. Existing Settings/Shield and Phase1–6 App migrations are applied.

After an identified local database backup, run from the same configured checkout:

    php spark migrate --namespace App
    php spark migrate:status

Use the existing XAMPP PHP executable if needed. Inspect all nine Phase7 rows as applied, then explicitly reload Cash/Stock/Full pages. If any migration fails, stop and inspect that failure/physical DDL before retry; do not drop tables or forge history. Apply migrations OR the raw SQL route, not both. No destructive repair or setup-guard bypass is needed for this confirmed pending-migration state.

For the uncertain start, retain its original UUID and Check saved status after setup succeeds. If no control exists, Continue original start reference preserves that UUID for the explicit retry; do not clear browser storage or manufacture a replacement identity. A successful page load is separate from a successful control lifecycle.

At the time of this record, instructions were supplied to the Owner; successful local migration and page reload are awaiting their outcome. No database operation was performed from the implementation workspace.

## Reference diagnostic procedure

Use Developer Tools → Network, reload one control page and inspect its failed `/api/v1/shop/*-controls` request. Record HTTP status and JSON error.code/message/meta.request_id, excluding cookies, request headers and secrets.

- DEVICE_SETUP_REQUIRED: inspect the two approved WP1 Device migrations and required Device Audit FK.
- CASH_CONTROL_SETUP_REQUIRED: inspect the six approved WP2 evidence migrations and required scoped FKs. This check applies to all three scopes.
- STOCK_CONTROL_SETUP_REQUIRED: inspect the WP3 deferred Stock Movement variance FK migration.
- READINESS_SETUP_REQUIRED: administrator checks the existing private encryption key and PHP OpenSSL support; never disclose/change the private key merely to diagnose a page.
- REQUEST_FAILED/unexpected500: find `commercial.request_failed` matching meta.request_id in writable/logs. Share only exception class, source file/line and app_trace; the application intentionally avoids SQL/body/credential logging.

Run `php spark migrate:status` to inspect applied/pending App migrations in the deployed checkout. If setup is actually missing, use the existing [backed-up migration procedure](../manual-verification-phase-07-wp3.md); inspect partial DDL before retry and use migration OR SQL per database. Do not forge history, drop evidence or disable setup checks. A missing log entry is expected for a handled ApiException; inspect the JSON rather than treating its absence as proof of success.

## Candidate verification and handover

Updated source/public assets and a separate [fix inventory](Phase_7_WP3_Control_Error_Fix_Artifact_Manifest.json) bind this follow-up. The original WP3 inventory/report remain historical. The current manual guide uses the follow-up inventory. No project dependency or test framework is added.

Scratch actual Vue rendering with mocked API/dialog methods verifies all three scopes: safe503 explanation/support reference, blocked Start/direct submit after failed load, explicit GET refresh restoring the form after recovery, no false empty history, unexpected5xx redaction and preservation of the original uncertain-start UUID. Existing Stock/Full and Cash UI probes remain passing. The deployed503 code and pending-migration status are Owner-supplied evidence; the probes do not confirm corrected native MySQL setup, HTTP/cookies or an actual successful control start.
