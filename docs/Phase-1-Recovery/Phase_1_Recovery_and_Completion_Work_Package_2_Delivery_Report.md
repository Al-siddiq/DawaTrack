Phase 1 Recovery & Completion — Work Package 2 Delivery Report
A. Work Package Delivered
WP2 — Tenant Onboarding, Staff, Permissions & Shop Access

Implementation is committed as:

a69bef1 feat: complete phase 1 tenant and staff workflows

Pull-request metadata was created with the title:

Phase 1 WP2: complete tenant onboarding and staff access workflows

B. Implementation Summary
WP2 now provides corresponding code for:

Transactional Tenant onboarding that includes the Shield Owner identity, Tenant, one Shop, Owner membership, default branding, platform hostname, Trial subscription/event, and authoritative Audit evidence.

Checked advisory-lock acquisition and guaranteed release.

Active Sync Generation and Trial-plan prerequisites.

Application-level one-active-ordinary-membership enforcement.

Serialized Staff creation/reactivation and entitlement-cap enforcement.

Staff activation, deactivation, credential reset, remembered-login invalidation, and cross-session revocation.

Server-authoritative permission catalogue, active grants, grant/revoke history, reasons, and atomic Audit.

Role-aware Owner/Staff context and Shop shell loading.

Explicit FULL, READ_ONLY, BLOCKED, and CLOSED presentation.

Hardened Shop profile normalization, validation, allowlisting, and before/after Audit evidence.

Responsive Bootstrap/Vue Staff cards, permission groups, searchable permissions, reason dialogs, credential-reset presentation, settings errors, and loading/empty/error states.

Updated WP2 security, module, Audit, and manual-verification documentation.

No WP3, WP4, or Phase 2 functionality was implemented.

C. WP1 Foundation Preserved
The approved WP1 architecture remains in place:

Shield authentication and platform_admin remain the identity and Platform Admin authority foundation.

CSRF remains globally required.

Browser/API authentication separation remains intact.

Existing API response envelopes and ApiException handling are reused.

Native Fetch, Vue 3 global build, Bootstrap, Sass, and static asset publishing remain unchanged architecturally.

Staff-sensitive mutation routes were attached to the existing shared rate-limit filter rather than creating a second throttling mechanism.

The session-security filter now participates in both request phases so successful new sessions receive an authentication timestamp and revoked/deactivated identities are rejected on subsequent requests.

The WP1 Audit safety requirement was tightened: once audit_events exists, a Platform MFA Audit insertion failure is no longer silently swallowed.

The approved utf8mb4_unicode_ci and intentional ascii_bin schema definitions were not changed.

D. Atomic Onboarding
OnboardingService now owns the complete operation.

The sequence is:

Normalize and validate input.

Acquire and verify the global identity/membership advisory lock.

Start the shared database transaction.

Verify exactly one active Sync Generation.

Verify the active Trial plan.

Verify normalized Owner email and platform hostname availability.

Create the Shield Owner identity inside the transaction.

Enforce the active ordinary-membership invariant.

Create the Tenant.

Assert that no Shop already exists for the new Tenant.

Create the sole Phase 1 Shop.

Create the active Primary Owner membership.

Create default branding.

Create the active, verified, primary platform domain.

Create the 30-day Trial and its CREATED subscription event.

Append owner.created and tenant.onboarded Audit evidence.

Commit.

Release the advisory lock in finally.

The transaction and lock boundary are explicit, with rollback on every exception and guaranteed lock release.

The service returns safe conflict or validation responses for invalid fields, unavailable identity/hostname, missing platform prerequisites, and lock contention rather than exposing database exceptions.

The one-Shop rule remains a service invariant; no UNIQUE(tenant_id) schema redesign was introduced.

E. Primary Owner Creation
The Primary Owner is created through the pinned Shield provider within the owning transaction rather than by the controller before entering the transaction.

The implementation:

Uses Shield’s User entity and password storage.

Creates an active identity.

Requests Shield’s forced-password-reset behavior when supported.

Creates the PRIMARY_OWNER membership only after identity creation.

Records distinct owner.created Audit evidence without recording the supplied password.

Clears the password from the Admin onboarding form after both success and failure.

Owner creation and the Tenant graph therefore share the same success or rollback outcome.

The Admin UI retains non-secret data, returns to the Details step with field errors after recoverable failure, and never repopulates the password.

F. Membership Invariant
A focused MembershipInvariantService now prevents an ordinary Shield identity from acquiring a second simultaneous active Tenant membership.

It supports an excluded membership for safe reactivation checks and returns 409 MEMBERSHIP_CONFLICT when the invariant would be violated.

The invariant is applied during:

Primary Owner onboarding.

Staff creation.

Staff reactivation.

Onboarding and Staff lifecycle operations share the same global identity/membership advisory lock, preventing concurrent application paths from independently passing the invariant check.

Platform Administrators remain outside ordinary Tenant memberships.

G. Staff Lifecycle
The Owner can now perform the complete WP2 Staff lifecycle through the API and UI:

List Owner and Staff membership status.

Create Staff.

View Staff name, email, role, and status.

Activate or deactivate Staff with a reason.

Initiate credential reset with a reason.

View permission state and history.

Grant and revoke permissions.

Staff creation uses Shield for identity persistence, requests forced reset where supported, creates only a STAFF membership, and appends staff.created Audit evidence in the same transaction.

Status transitions:

Reject no-op transitions with 409.

Require a reason.

Restrict the target query to same-Tenant STAFF.

Apply membership and Shield-user active state together.

Recheck the ordinary-membership invariant and Staff cap on reactivation.

Append before/after Audit evidence in the transaction.

Owner, Platform Admin, and foreign-Tenant records cannot be selected through the Staff-target query because it requires the current Tenant and role = STAFF.

H. Staff Cap Concurrency
Staff creation and reactivation now acquire the shared identity/membership advisory lock before:

Resolving the currently effective half-open subscription period.

Reading max_active_staff_snapshot.

Counting active STAFF memberships.

Creating or reactivating the membership.

Writing Audit.

Committing.

The cap excludes the Primary Owner because the authoritative count is restricted to role = STAFF and status = ACTIVE.

The approved limits continue to come from immutable plan snapshots:

Trial: 5

Starter: 2

Growth: 5

An operation at or above the current snapshot cap returns 409 STAFF_CAP_REACHED; no Staff member is automatically deactivated.

The advisory lock is checked and failure produces a retryable conflict instead of continuing without serialization.

I. Credential Recovery and Session Invalidation
The pinned Shield integration available to the application supports password replacement and a forced-reset request. WP2 therefore uses the approved fallback policy:

Generate a high-entropy temporary password.

Store it only through Shield’s password mechanism.

Request forced password reset where supported.

Return the temporary value once to the initiating Owner.

Never write it to Audit or logs.

Delete Shield remember-login tokens.

Delete pending magic-link identity state.

Record a per-user revocation timestamp.

Require a reason and append safe staff.credentials_reset Audit evidence.

The reset is serialized and the Shield save, invalidation actions, and Audit record are handled by the owning service transaction boundary.

UserSessionRevocationService binds an authenticated session to its login time and rejects sessions older than a user’s latest revocation timestamp.

The global session filter also independently rejects an inactive Shield user and returns JSON SESSION_EXPIRED for APIs or a safe auth redirect for browser navigation.

Actual Shield forced-reset behavior and cross-browser session invalidation remain mandatory runtime-verification debt because Composer dependencies could not be restored in this environment.

J. Permission Management
The permission service now provides:

A structured catalogue with code, logical group, and readable label.

Fail-closed validation of permission codes.

Same-Tenant STAFF targeting.

Active-Staff enforcement for new grants.

Required grant/revoke reasons.

Per-membership advisory-lock serialization.

409 PERMISSION_UNCHANGED for repeated grants or revokes.

Append-oriented grant history.

Atomic permission mutation and Audit.

Tenant, Shop, actor user, actor membership, target membership, permission, reason, and bounded before/after context in Audit.

The Staff permission-state endpoint returns:

Staff membership state.

Approved permission catalogue.

Current active codes.

Complete grant/revoke history.

The UI loads that state from the server, renders current grants as checked, groups/searches the catalogue, refreshes state following changes, and displays history. Native prompt() is no longer used.

K. Role-Aware Shop Shell
The Shop context response now supplies safe, server-derived:

Authenticated Tenant role.

Public Tenant and Shop presentation identifiers.

Effective permission codes.

Owner capabilities.

Effective access mode, message, plan code, and entitlement end time.

The frontend first loads common context, then conditionally loads Owner-only resources according to returned capabilities. Ordinary Staff no longer trigger an unconditional Promise.all() containing Owner-only requests.

The Staff home surface shows role and effective permissions but does not expose Product, Inventory, Sales, or other unimplemented navigation.

Direct calls remain server-protected:

Owner services call requireOwner().

Cross-Tenant or inactive membership context returns a non-disclosing 404.

Unknown permissions fail closed.

Mutation endpoints invoke the effective AccessGate before the domain service.

L. Access Modes
The Shop shell handles the four approved access modes:

FULL: permitted mutations remain enabled subject to role and authorization.

READ_ONLY: read surfaces remain available, but mutation controls are disabled and backend mutation requests remain rejected.

BLOCKED: only the common home/access explanation remains available.

CLOSED: only the terminal common home/access explanation remains available.

Navigation filtering and mutation disabling are driven by the server’s effective access response.

The shell renders distinct success, warning, and danger presentations rather than collapsing access failures into validation errors.

WP4 remains responsible for implementing the administrative Tenant/Shop lifecycle operations that produce these states.

M. Shop Settings
Shop settings now use an explicit mutable-field allowlist:

Name.

Contact phone.

Contact email.

Address lines.

City/town.

State/region.

IANA timezone.

IDs, Tenant ownership, status, country, currency, and lifecycle timestamps are not accepted as mutable fields.

Validation now includes:

Trimming.

Consistent blank-to-null normalization for optional values.

Maximum lengths.

Lowercased and validated email.

Phone-character validation followed by normalized digits.

Seven-to-fifteen-digit normalized phone validation.

Real IANA timezone membership.

Field-specific 422 VALIDATION_FAILED details.

Updates calculate actual changed fields, reject no-op writes with 409, and atomically persist bounded before/after Audit evidence with the Shop update.

N. Tenant Isolation / Security
WP2 preserves server-derived authority:

Hostname selects a candidate active, verified Shop domain.

The authenticated user must hold an active membership in that domain’s Tenant.

Client-supplied Tenant IDs are not used.

Foreign and unavailable Shop contexts receive the same non-disclosing 404.

Owner authorization is enforced by server-side role checks.

Staff target lookups always include:

Current server-derived Tenant.

Requested membership ID.

role = STAFF.

Permission targets are similarly Tenant- and role-scoped.

Staff-sensitive writes use the shared WP1 throttling mechanism, while normal GET requests remain exempt from throttling.

O. Audit Coverage
WP2 adds atomic Audit evidence for:

Primary Owner creation.

Tenant onboarding.

Staff creation.

Staff activation.

Staff deactivation.

Staff credential reset.

Permission grant.

Permission revoke.

Shop profile updates.

Audit records include server-derived authority and target context where applicable. Passwords, generated temporary passwords, password hashes, recovery secrets, remember tokens, and session identifiers are not included.

Permission Audit now includes the previously missing Shop context, actor membership, reason, target public membership, and safe before/after state.

The Audit contract now explicitly states that Audit failure is transaction failure for WP2 identity mutations and that repeated no-op requests do not create false state-change evidence.

P. Responsive / Accessibility
WP2 UI work uses:

Responsive cards instead of Staff or permission tables.

Wrapping action groups.

Searchable permission groups.

A mobile-compatible reason dialog.

Visible labels and inline field errors.

Touch-sized Bootstrap controls.

Focus movement into the dialog and restoration to the triggering control.

Keyboard Escape dismissal.

One-time temporary credential presentation.

Access-mode alerts and loading/empty/error states.

The Staff cards, permission controls, settings form, and reason dialog are represented without a mandatory horizontal table layout.

The Sass source includes responsive dialog, card-action, permission-grid, and credential-display rules, with compiled CSS regenerated into public/assets/css/app.css.

Browser-level checks at 320px, 360px, 390px, tablet, desktop, keyboard-only, and 200% zoom could not be executed because the PHP runtime could not be restored. They remain mandatory final acceptance evidence.

No screenshot was produced because the application could not be run without the missing Composer installation.

Q. Files Created
app/Modules/Identity/Application/MembershipInvariantService.php

app/Security/UserSessionRevocationService.php

app/Support/UtcTimestamp.php

resources/js/shop/permission-groups.js

public/assets/js/shop/permission-groups.js

UtcTimestamp provides actual UTC microsecond timestamps for permission-history uniqueness and other WP2 DATETIME(6) writes rather than relying on gmdate()’s zero microseconds.

R. Files Modified
Backend and configuration
app/Config/Filters.php

app/Config/Routes.php

app/Config/Services.php

app/Controllers/Api/OnboardingController.php

app/Controllers/Api/ShopController.php

app/Filters/SensitiveRateLimitFilter.php

app/Filters/SessionSecurityFilter.php

app/Modules/Identity/Application/PermissionService.php

app/Modules/Identity/Application/StaffService.php

app/Modules/TenantShop/Application/OnboardingService.php

app/Modules/TenantShop/Application/ShopSettingsService.php

app/Modules/TenantShop/Infrastructure/ShopContext.php

app/Security/PlatformMfaService.php

Views and frontend sources
app/Views/admin/index.php

app/Views/shop/index.php

resources/css/app.scss

resources/js/admin/app.js

resources/js/api/phase-one.js

resources/js/shop/app.js

Generated public assets
public/assets/css/app.css

public/assets/js/admin/app.js

public/assets/js/api/phase-one.js

public/assets/js/shop/app.js

Documentation
docs/audit-contract.md

docs/manual-verification-phase-01.md

docs/modules/phase-01-completion.md

docs/modules/phase-01.md

docs/security/permission-catalogue.md

S. Database / Migration Changes
No WP2 schema change was required; the approved Phase 1 schema remained sufficient.

No migration or raw SQL file was added or modified.

The existing:

Future-compatible one-to-many Shop schema.

Tenant membership constraints.

Permission history table.

Immutable subscription period/event tables.

Audit table.

utf8mb4_unicode_ci ordinary-text baseline.

Intentional ascii_bin identifiers.

remain unchanged.

T. Documentation Updated
Documentation now records:

Shared-transaction Owner/Tenant onboarding.

Advisory-lock and prerequisite requirements.

Active membership and Staff-cap invariants.

Role-aware Shop context.

Server-authoritative permission state.

Staff session revocation behavior.

Shop-settings validation and bounded Audit deltas.

Forced rollback, concurrency, IDOR/BOLA, credential reset, access-mode, and validation scenarios.

The documentation explicitly states that Phase 1 remains unapproved until WP3, WP4, and outstanding runtime verification are complete.

The manual checklist includes the required forced onboarding failures, cap races, membership conflict, session invalidation, permission refresh/history, settings tampering, and multi-Tenant isolation scenarios.

U. Verification Actually Executed
Static verification
All 110 PHP files under app/ passed syntax validation.

Changed source and compiled JavaScript application modules passed node --check.

Composer metadata passed strict validation.

Source and compiled JavaScript copies matched.

No native prompt() call remains in application, resource, or compiled JavaScript.

No utf8mb4_0900_ai_ci reference remains outside ignored dependency output.

git diff --check passed.

The committed working tree is clean.

Dependency/build verification
npm ci successfully restored exactly 48 frontend packages from the lock file.

npm run build successfully compiled Sass and published the static frontend assets.

The Sass build emitted upstream Bootstrap/Dart Sass deprecation warnings, but completed successfully.

Composer installation was attempted but could not complete because the environment’s GitHub CONNECT tunnel returned HTTP 403.

vendor/autoload.php therefore remains unavailable.

Runtime verification
Not executable:

CodeIgniter boot.

Shield routes.

HTTP controller execution.

Mail/recovery delivery.

Session lifecycle.

CSRF acceptance/rejection.

Throttling behavior.

Staff concurrent-write scenarios.

Audit persistence.

Database verification
Not executable because no MySQL client/server was installed.

Browser/manual verification
Not executable because the application could not boot without Composer dependencies and no browser runtime was available.

WP1 debt status
None of the pending WP1 runtime checks became executable during WP2. All previously recorded WP1 verification debt remains mandatory before Phase 1 approval.

V. Manual Verification Results
Scenario	Expected Result	Actual Result
Successful atomic onboarding	Exactly one complete Owner/Tenant/Shop/Trial/Audit graph	Environment-blocked; implementation statically inspected
Forced failure after each onboarding stage	No partial graph or orphan Owner	Environment-blocked
Duplicate email/hostname	Safe 409, no disclosure or partial write	Environment-blocked
Lock contention / missing Sync Generation	Safe conflict and no write	Environment-blocked
Concurrent Staff create/reactivate at cap	One commit; competing request rejected	Environment-blocked
Second active ordinary membership	409 MEMBERSHIP_CONFLICT	Environment-blocked
Staff deactivation/reset	Existing and remembered access revoked	Environment-blocked
Owner/Admin/cross-Tenant target manipulation	Non-disclosing rejection	Environment-blocked
Permission load/grant/revoke/history	Server-authoritative checked state and history	Environment-blocked
Repeated permission mutation	409, no false Audit evidence	Environment-blocked
Ordinary Staff shell login	Stable shell without Owner-only bootstrap requests	Environment-blocked
FULL/READ_ONLY/BLOCKED/CLOSED	Correct presentation and mutation enforcement	Environment-blocked
Shop validation and immutable-field tampering	Field errors; immutable values unchanged	Environment-blocked
WP2 transaction/Audit failure	Business effect and Audit roll back together	Environment-blocked
320/360/390/tablet/desktop	Functional parity without horizontal overflow	Environment-blocked
Keyboard-only and 200% zoom	Operable controls, visible focus, readable content	Environment-blocked
The required procedures and expected outcomes are recorded in the repository manual-verification document.

W. Deviations / Blockers
Implementation deviations
Staff credential reset uses a generated temporary password plus Shield’s supported forced-reset flag because no separate native Owner-initiated recovery-link runtime could be verified without the Shield dependency tree. The password is shown once, never audited, and active/remembered access is revoked.

No automated application tests were introduced, as required.

Environment blockers
Composer downloads were rejected by the environment’s GitHub CONNECT tunnel.

MySQL 8.4 was unavailable.

Browser/manual execution was unavailable.

No unresolved WP2 code-scope blocker was identified. Runtime verification remains mandatory acceptance debt.

X. WP2 Acceptance Assessment
Every WP2 acceptance criterion has corresponding implementation:

Atomic onboarding including Shield identity: implemented.

Rollback boundary and guaranteed lock release: implemented.

Checked lock acquisition: implemented.

Sync Generation and Trial prerequisites: implemented.

Safe conflict/validation handling: implemented.

One-Shop service invariant: implemented without schema redesign.

One-active-ordinary-membership invariant: implemented.

Complete Staff UI/API lifecycle: implemented.

Concurrency-safe Staff caps: implemented.

Credential reset and session invalidation: implemented.

Owner/Admin/foreign target rejection: implemented.

Effective permission state and history: implemented.

Grant/revoke with reasons and atomic Audit: implemented.

Native prompt() removal: verified statically.

Ordinary Staff Shop shell: implemented.

Role/capability-aware navigation and loading: implemented.

Access-mode presentation and backend mutation rejection: implemented.

Shop normalization and validation: implemented.

Atomic WP2 Audit: implemented.

Responsive/mobile structure: implemented; browser verification pending.

Documentation: updated.

WP3/WP4/Phase 2 exclusion: preserved.

WP2 is complete at the implementation level. Runtime, database, concurrency, email/session, browser, responsive, and accessibility evidence must still be executed in a capable environment before final Phase 1 approval.

Y. Remaining Work for WP3/WP4
WP3 only
Final branding validation and propagation.

Asset-upload hardening and filesystem/database atomicity.

Login background.

PWA 192/512 icons.

Dynamic manifest completion and cache versioning.

Custom-domain DNS/TLS operational workflow.

Domain activation, primary switching, deactivation, and fallback.

WP4 only
Full immutable subscription lifecycle and operational UI.

Upgrade, renewal, extension, downgrade, and cap refusal.

Tenant suspend/reactivate/close.

Shop activate/deactivate.

Final AccessGate and API-state closure.

Central Audit-policy closure.

Final documentation reconciliation.

Complete MySQL, runtime, adversarial, responsive, accessibility, and Phase 1 acceptance execution.

No WP3 or WP4 implementation was started in this delivery.

Z. Git Commit / Change Summary
Commit: a69bef1 feat: complete phase 1 tenant and staff workflows

Working tree: clean after commit.

Pull-request metadata: created successfully.

Schema migrations: none.

Automated test frameworks: none added.