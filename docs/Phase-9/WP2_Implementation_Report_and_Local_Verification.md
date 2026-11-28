# Phase 9 — WP2 Implementation Report and Local Verification

**Work package:** WP2 — Tenant Onboarding and Primary Owner Lifecycle  
**Status:** Implementation source prepared; PHP/runtime/database and Owner manual verification pending.  
**Dependency:** WP1 manually verified and accepted by the Owner on 28 November 2026.  
**Branch:** `phase-9/wp2-tenant-onboarding-primary-owner`  
**Schema migrations:** None added.  
**Production deployment:** Not performed or authorized.

## 1. Delivered source

### Invitation-first Tenant onboarding

Updated:
- `app/Modules/TenantShop/Application/OnboardingService.php`
- `app/Controllers/Api/OnboardingController.php`
- `resources/js/admin/app.js`

The existing onboarding transaction now creates:
- Tenant in `ONBOARDING`.
- Exactly one Shop in `INACTIVE`.
- Primary Owner membership in `INVITED`.
- Existing branding profile and primary platform domain.
- Reasoned `owner.created` and `tenant.onboarded` Audit events.
- Tenant, Shop, membership, branding and domain change publication in the same transaction.

The initial Trial is deliberately not created during registration. It starts only when the Owner accepts the invitation and completes activation. The onboarding form no longer accepts or displays an Admin-entered temporary password.

An existing email/password Shield identity can be reused only when it is active, is not a Platform Admin, has no active Tenant membership and has no other pending Primary Owner invitation. Otherwise onboarding fails safely. A new account receives an internally generated high-entropy password; Shield's force-reset state is verified before an invitation is issued. The generated password is never returned or emailed.

After the onboarding graph commits, the controller asks the Identity module to issue a one-time Shield magic-link identity and send the standard Shield magic-link email. The API returns only delivery status and expiry, never the token. Email failure leaves the graph safely pending and allows invitation resend.

### Owner invitation and activation

New:
- `app/Modules/Identity/Application/OwnerInvitationService.php`
- `app/Controllers/Web/OwnerActivationController.php`
- `app/Views/auth/owner_activate.php`

Updated:
- `app/Config/Auth.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`
- `app/Modules/Identity/Application/MembershipInvariantService.php`

Shield's existing one-time magic-link flow is reused. The invited Owner is routed to an authenticated activation screen. If the email/password identity is force-reset, the Owner sets their own password using the verified magic-link session; no current/temporary password is requested. The setup session is bounded and refreshed only after the Owner reaches the activation page. If it expires, the Owner is signed out and directed to request a fresh link.

Owner activation is atomic:
1. Revalidates exactly one invited Primary Owner membership and exactly one Shop.
2. Rechecks the active Sync generation and Trial plan prerequisites.
3. Rechecks the global active-membership and pending-Owner-invitation invariants.
4. Saves the Owner-selected password and clears Shield force-reset when required.
5. Activates membership, Tenant and Shop.
6. Creates the initial 30-day Trial from the actual activation time.
7. Appends `owner.activated` and, when applicable, `identity.password_changed`.
8. Publishes Tenant, Shop, membership and subscription changes before commit.

A failed operation rolls back the password/force-reset change, Tenant/Shop/membership activation, Trial, Audit and Sync rows together. A repeated activation cannot create a second Trial because only an INVITED membership can be activated.

### Owner recovery and invitation resend

The Identity service issues a fresh Shield magic-link identity, invalidating the prior unused magic link. Initial invitations/resends also invalidate the pending Owner's remembered credentials, non-password API/2FA identities and prior sessions; active Owner recovery does not unnecessarily log out the current Owner. It requires a bounded reason and the current reviewed Owner membership public UUID. It records `owner.invitation_issued` or `owner.recovery_requested` in the same transaction as token identity creation. Email delivery occurs after commit and is reported separately; the token is never returned.

The existing Shield password-change page now permits a one-time magic-link-authenticated user with a force-reset requirement to choose a password without entering an unknown current password. Normal password-based forced-reset behavior is preserved. Platform Admin MFA is still cleared and the Platform Admin is signed out after a password change.

### Primary Owner reassignment

New:
- `app/Modules/Identity/Application/OwnerLifecycleService.php`

Updated:
- `app/Controllers/Api/PlatformOperationsController.php`
- `app/Config/Services.php`
- `app/Config/Routes.php`
- `app/Modules/Platform/Application/PlatformTenantDetailQuery.php`
- `resources/js/admin/app.js`
- `resources/js/api/phase-one.js`

The reassignment operation:
- Requires an active Tenant, current Owner membership public UUID, valid target email, non-empty reason and strict boolean confirmation.
- Rechecks the current Owner under the shared `dawatrack:identity-membership` advisory lock and a database transaction.
- Rejects inactive/Platform identities, self-transfer, target identities with active membership in another Tenant and target identities with a pending Primary Owner invitation elsewhere.
- Reuses and promotes an existing Staff/deactivated membership row where one exists; otherwise creates one row.
- Deactivates the former Owner before promoting the new Owner, preserving the existing generated unique active-Owner guard.
- Revokes active permission grants on the former and promoted membership and appends reasoned `permission.revoked` evidence for each grant.
- Invalidates remember tokens, magic-link/access-token/HMAC/2FA identities for both affected identities.
- Appends `owner.reassigned` and publishes both membership changes in the same transaction.
- Verifies exactly one active Primary Owner remains before commit.
- Revokes both former and new Owner sessions after commit.
- Issues a fresh Shield access link to the new Owner after commit. Email failure does not roll back the completed transfer; the new Owner can be sent a recovery link again.

The former Owner's global Shield user is not disabled. They lose this Tenant membership and all sessions/remembered credentials; the identity may continue to exist for other legitimate account purposes.

### Tenant detail and Admin UI

Tenant detail now includes safe Primary Owner name/email, membership public UUID/status and invited/activated timestamps. The Admin UI adds reasoned invitation resend, active Owner access recovery and confirmed Owner reassignment. Controls are hidden unless the Admin has the existing `platform.tenants` permission. The Add Tenant form explains that the Owner sets their own password.

No new permission code, table, migration, credential store, impersonation flow, offline cache or Shop business-data access was introduced.

## 2. API surface

- Existing `POST /api/v1/platform/tenants` — invitation-first Tenant registration.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/invitation` — resend pending invitation; requires current Owner membership public UUID and reason.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/recovery` — recover active Owner access; requires current Owner membership public UUID and reason.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/reassign` — confirmed reassignment; requires current Owner membership public UUID, target email and reason.
- `GET /owner/activate` and `POST /owner/activate` — authenticated invitation acceptance/password setup.

The three Platform Owner operations reuse the existing `platformAdmin:platform.tenants` permission and recent-MFA filter. No new permission is introduced.

## 3. Audit and transaction boundaries

New approved Audit actions:
- `owner.activated`
- `owner.invitation_issued`
- `owner.recovery_requested`
- `owner.reassigned`

Existing actions reused:
- `owner.created`
- `tenant.onboarded`
- `identity.password_changed`
- `permission.revoked`

Invitation identity creation and its Audit append share a transaction. Onboarding graph, Audit and change publication share one transaction. Owner activation, Trial, Audit and change publication share one transaction. Reassignment, grant revocation, Audit and change publication share one transaction. Email sending and session-cache revocation happen after the business transaction and are explicitly reported/retried rather than falsely described as atomic with SMTP.

## 4. Source checks completed

- Inspected Phase 1 Tenant/Shop/Membership schema and confirmed `ONBOARDING`, `INACTIVE`, `INVITED` and `DEACTIVATED` are allowed values.
- Inspected Shield 1.4.1 magic-link controller, Session authenticator and routes; implementation uses Shield's existing `magic-link` identity type, configured one-hour token lifetime, standard email template and `magicLogin` session marker.
- Inspected existing UserSessionRevocationService and reused its revocation marker.
- Inspected Audit vocabulary/reason policy and registered the new closed action codes.
- Confirmed no new schema migration or permission code was added.
- JavaScript parser-only checks passed for `resources/js/admin/app.js` and `resources/js/api/phase-one.js` after stripping module import/export declarations. This is not a browser or npm build check.
- Confirmed generated public JavaScript copies are byte-for-byte identical to their resource sources:
  - `public/assets/js/admin/app.js`
  - `public/assets/js/api/phase-one.js`

## 5. Not run in this environment

The GitHub source connector does not provide the local XAMPP/MySQL application runtime. A network attempt to clone the repository from this environment was unavailable. Therefore these are **NOT RUN**, not PASS:
- PHP `php -l` checks for changed PHP files.
- `npm ci` and `npm run build`.
- XAMPP/MySQL migrations and actual database transaction tests.
- SMTP invitation delivery and actual Shield magic-link verification.
- API route/permission/recent-MFA tests.
- Concurrent onboarding, invitation resend, activation and reassignment tests.
- Session/remember-token invalidation in a browser.
- The 24 Owner manual acceptance scenarios.

No production database was accessed and no production deployment was performed.

## 6. Local preparation

Use a disposable/local DawaTrack database only. Do not create fixtures or run negative tests against production.

From the repository root:

```bash
npm ci
npm run build
php -l app/Modules/TenantShop/Application/OnboardingService.php
php -l app/Modules/Identity/Application/MembershipInvariantService.php
php -l app/Modules/Identity/Application/OwnerInvitationService.php
php -l app/Modules/Identity/Application/OwnerLifecycleService.php
php -l app/Controllers/Web/OwnerActivationController.php
php -l app/Controllers/Web/PasswordChangeController.php
php -l app/Controllers/Api/OnboardingController.php
php -l app/Controllers/Api/PlatformOperationsController.php
php -l app/Modules/Platform/Application/PlatformTenantDetailQuery.php
php -l app/Config/Auth.php
php -l app/Config/Services.php
php -l app/Config/Routes.php
php -l app/Config/Filters.php
```

Start Apache/MySQL in XAMPP. Confirm `.env` targets the disposable/local database. Confirm migrations are current with `php spark migrate:status`; WP2 adds no migration. Verify SMTP sender, transport and delivery to a test mailbox without exposing credentials.

## 7. Manual verification register

Execute the WP2 contract scenarios using separate synthetic identities and a disposable database. Record environment, timestamp, actor, fixture IDs, steps, expected/observed result, evidence reference and reviewer.

1. New Tenant creates exactly one Tenant, one Shop, one invited Owner, branding and primary domain.
2. Initial status is Tenant ONBOARDING, Shop INACTIVE, membership INVITED, and no Trial exists.
3. Admin UI/API contains no Owner password field or password in response.
4. Eligible existing email/password Shield identity is reused.
5. Platform Admin, inactive identity, active Tenant membership and other pending Owner invitation are rejected.
6. Duplicate email/hostname/Shop conflict leaves no partial graph.
7. Invitation token is issued through Shield magic-link identity and email arrives.
8. Link opens Shield verification and routes the Owner to the activation screen.
9. Owner sets their own password without entering a temporary/current password.
10. Activation changes membership/Tenant/Shop to ACTIVE and creates exactly one 30-day Trial from activation time.
11. Trial starts at activation, not onboarding creation.
12. Invalid password, missing prerequisites or injected Audit/Sync failure leaves the Tenant pending and no partial Trial/Audit/change.
13. Repeated activation cannot create a second Trial.
14. Invitation resend invalidates the prior unused link and sends a fresh link.
15. Stale Owner membership reference is rejected for resend/recovery.
16. Active Owner recovery sends a one-time magic link; force-reset accounts can set a new password without current password.
17. Reassignment to eligible unassigned identity succeeds.
18. Reassignment to existing active Staff in the same Tenant promotes the existing membership row, creates no duplicate, and revokes its old Staff grants.
19. Reassignment rejects Platform Admin, inactive, self, cross-Tenant active membership, pending Owner invitation elsewhere and invalid email.
20. Stale current Owner UUID and false/non-boolean confirmation are rejected.
21. Concurrent reassignment attempts serialize and exactly one active Primary Owner remains.
22. Former and new Owner sessions, remember tokens and prior magic/access tokens are invalidated; former global Shield user remains active.
23. New Owner receives a fresh access link; SMTP failure is reported and retryable without reversing reassignment.
24. Every committed sensitive mutation has reasoned Audit and correct change publication; failed transactions leave no partial business/Audit/change rows. Verify no token, password, credential verifier or internal numeric ID appears in API/UI/Audit/log output.

Also verify Admin Owner controls are unavailable without `platform.tenants`, protected requests fail for anonymous/non-Platform/missing-permission/expired-MFA actors, and all onboarding/activation/reassignment screens are usable on phone/tablet/desktop.

## 8. Acceptance gate

**WP2 remains IN PROGRESS.** Do not mark the manual scenarios PASS from source inspection or JavaScript parsing. Resolve any local failure and repeat the affected tests. WP3 must not commence until the Owner completes local verification and explicitly accepts WP2.
