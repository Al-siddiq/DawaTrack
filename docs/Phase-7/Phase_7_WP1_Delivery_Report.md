# Phase 7 WP1 — delivery report

**15 November 2026 (Africa/Lagos). Implemented for Owner native manual review; not Owner-accepted, merged or deployed.** The plan is Owner-accepted and only WP1 is authorized. Base main `4266400`; accepted planning prerequisite `bf5a358` included because the plan is not yet merged. Branch `codex/phase-7-wp1-devices-control-20261115` targets main directly and contains the documentation plan prerequisite plus WP1.

## Delivered

- Online Owner Device enrollment, bounded list/status, rename, reasoned revoke and explicit same-installation proof recovery; Staff only own-cookie status/online validation.
- New full v1.1 registered_devices migration/raw SQL and parent-first deferred Audit Device FK with orphan/cross-scope preflight, readiness failure and protected down guards.
- Random256-bit installation proof in secured host-only HttpOnly cookie, SHA-256 verifier server-side; no proof in JSON/JS storage/Audit; public IDs never authorize a Device. Actual last_validated_at requires proof roundtrip, offline/sync timestamps remain null.
- Current membership/Owner/FULL checks under existing owning Tenant→Shop→membership transaction; scoped Device locks, required Audit, final aggregate change publication and safe repeat/status recovery.
- Device actor context validated against ACTIVE same-Shop Device; existing business actors remain Device-null, no arbitrary client Device attribution.
- Shared ControlScopeGuard/StockControlGuard and retained Cash Opening gate; all existing Stock posting/reversal/commercial paths guarded before source/Product locks, Product lifecycle serializes roots, current grants rechecked.
- BlindReadPolicy foundation denies revealing source APIs for counting actors across sessions during future STANDARD COUNTING, including index-page-normalized paths; Opening confirmation unchanged.
- Independent reconciliation.start/commit/abort/exclude_device/note registered alongside existing grants without changing Staff grants; no later command routes.
- Integrated Shop devices workspace uses shared fields/loading/dialogs/status/navigation guards; Owner list pages capped50 (UI20), Staff own-only presentation, unknown replies require explicit status inspection, offline/failed refresh clears stale records. Safe local marker contains IDs only.
- Full credential/API/lock/cutoff/readiness handoff, source-effect matrix, native P7-01–P7-12 guide/results, migrations/security/sync/UI/configuration/operations updates and Owner plan-approval record.

## Inherited baseline blocker repaired

The accepted Phase6 source FinancialMovementCashComponentQuery.php already contained a truncated Audit-fingerprint SQL predicate and duplicated tail outside the completed class. Grammar inspection found the parse failure. WP1 completes the correlated COUNT/REGEXP predicate and removes165 duplicate/stray lines, retaining the first class's cross-channel validation. This is a narrowly documented source repair needed for the reconciliation foundation. No new expected-Cash endpoint/feature is added. Native MySQL/F1/F3 proof remains required; historical Phase6 manifest is preserved, not regenerated.

## Engineering observations

| Check | Actual result / limit |
|---|---|
| Frontend build | Passed using existing locked node_modules; npm run build, no dependency changes; existing Sass/Bootstrap deprecation warnings. Node v24.22.1/npm11.9.0 |
| PHP grammar | All280 App PHP files parsed with PHP8.3 grammar after source repair; no native PHP execution |
| Published JS syntax | All50 published modules parsed; generated frontend source/public consistency checked by release inventory |
| Device migration twins | Device CREATE DDL exact-byte equal to SQL twin; Audit FK DDL matches; native apply/CHECK/FK/rollback not executed |
| Device UI scratch host | Actual Vue component with mocked API: enrollment, committed lost reply, no auto-retry, same-ID marker, explicit status recovery, Staff own-only presentation and offline clearing passed; dialog host methods stubbed; not a real browser/HTTP/cookie/layout test |
| Expected-Cash SQL shape | Four SQLite text-money fixture observations passed: valid Audit evidence, broken fingerprint rejection, Tenant scope, exclusive start. Not native MySQL DECIMAL or service posting |
| Artifact inventory | Generated/verified 424 files; content SHA-256 `f58f719b5db3512a1725555a61f397cc63a84d63f2a6d4630cf8a34a0c75a699`; manifest-file SHA-256 `c115ce63f74600a15193d9ef49ae0bd0b0286dc2165b33a71e6568b07bb9fed3` |
| Documentation/register checks | Relative targets/CSV shapes/32 requirement identities/48 integrated identities/12 WP1 case identities and no inferred Pass checked; git diff whitespace check |
| Native runtime / browser | PHP/Composer/MySQL unavailable; attempted local system package installation denied for lack of package-manager permissions. Native migration/constraints/DECIMAL/HTTP/CSRF/cookies/concurrency/rollback/real-device/layout/accessibility/restore not executed |

Scratch tools/probes stayed under work outside the repository; no test framework/suite/dependency was introduced. Existing manifest scope excludes PHP vendor, environment/secrets, uploads/writable data and database. Native result rows remain Not run; contract-only portions of P7-06/P7-10 explicitly await WP2/WP3 runtime. No ready/not-ready standard session is fabricated to satisfy a WP1 checklist.

## Review / setup / release

[Native guide](../manual-verification-phase-07-wp1.md) · [WP1 results](Phase_7_WP1_Manual_Results.csv) · [API/lock/Device contract](Phase_7_WP1_Integration_Contracts.md) · [source effects](Phase_7_WP1_Source_Effect_Matrix.csv) · [operations](../operations/phase-07-wp1-devices.md) · [defect/verification disposition](Phase_7_WP1_Defect_Disposition.csv) · [inventory](Phase_7_WP1_Artifact_Manifest.json).

Use a backed-up isolated native checkout, install locked dependencies, build and verify exact inventory, apply only the two approved new migrations in order, verify actual cookies/authority/scope and complete native cases. Do not apply SQL in addition to migrations or run destructive down on evidence. The generated manifest identifies reviewed content; it is not deployment/runtime approval.

WP2 standard Cash/readiness/ACK/evidence, WP3 Stock/Full/adjustments and WP4 integration/closure are not implemented or authorized. Owner WP1 acceptance is the next package gate; no production migration, secret/environment change, financial mutation, main merge or deployment was performed.
