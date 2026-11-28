# Phase 9 — WP2 Tenant Onboarding and Primary Owner Lifecycle Contract

**Work package:** WP2 — Tenant Onboarding and Primary Owner Lifecycle  
**Status:** Implementation in progress  
**Dependency:** WP1 — Owner accepted 28 November 2026  
**Branch:** `phase-9/wp2-tenant-onboarding-primary-owner`  
**Schema changes:** None planned; use existing Phase 1 Tenant/Shop/Membership, Shield identity and Audit structures.  
**Production deployment:** Not authorized.

## 1. Objective

Make Tenant registration, initial Primary Owner invitation/activation, Owner access recovery and Primary Owner reassignment operable from the Platform Admin console. Reuse existing authoritative services and Shield's configured magic-link identity/recovery flow. Do not create a second identity or credential system.

## 2. Existing baseline and required corrections

The existing `OnboardingService` creates a Shield User using an Admin-entered temporary password, immediately activates the Tenant/Shop/Primary Owner membership, starts the Trial, and emits onboarding Audit evidence. This prematurely presents a new Tenant as active before the Owner has accepted access. It also requires the Platform Admin to handle a password.

WP2 changes the initial state to:
- Tenant: `ONBOARDING`
- Shop: `INACTIVE`
- Primary Owner membership: `INVITED`
- Shield identity: existing identity reused where safe, otherwise created with an internally generated high-entropy password; no raw password is returned or emailed.
- Trial: created only when the invited Owner completes activation, so the Trial does not run while onboarding is pending.

The Phase 1 one-Shop-per-Tenant invariant remains unchanged.

## 3. Guided onboarding

Platform Admin onboarding form:
- Business and Shop details, contact/location, platform subdomain, Owner name/email and reason.
- No Admin-entered or displayed Owner password.
- Existing Shield identity may be reused only if it is active, is not a Platform Admin and has no active Tenant membership. Otherwise fail with a safe conflict.
- A newly created identity is password-reset-required and initially has an INVITED membership.
- Tenant, Shop, membership, branding and primary platform domain are created atomically using the existing onboarding transaction and advisory lock.
- The initial Trial is not created until Owner activation.
- An invitation is issued through Shield's existing one-time magic-link identity. Existing magic-link identities are invalidated before issuing a new token.
- Before sending an invitation, the prior pending Owner's sessions, remember-me tokens and non-password API/2FA identities are invalidated; the email/password identity remains available for Shield's force-reset flow. Invitation email delivery failure does not roll back the already committed onboarding graph; the Tenant remains ONBOARDING and the Admin can safely resend. The API reports delivery state without exposing the token.

## 4. Owner invitation acceptance

- Magic-link verification remains the Shield authentication flow.
- After magic-link login, an invited Primary Owner is directed to a dedicated Owner activation screen.
- If the Shield email/password identity is marked force-reset, the Owner must choose a new password through the activation screen; no current or temporary password is requested.
- The activation operation atomically changes the membership to ACTIVE, Tenant to ACTIVE, Shop to ACTIVE, creates the initial 30-day Trial and emits reasoned Audit/Sync evidence.
- A successful commit is required before the Owner is shown as active.
- A failed activation leaves the graph pending and retryable. It must not create duplicate Trials, memberships or Audit events.
- An existing eligible Shield account is reused through its existing email/password identity. Accounts without a usable email/password identity are not silently converted or given a parallel credential record.

## 5. Owner recovery and invitation resend

- Platform Admin can resend an invitation for an INVITED Primary Owner or request access recovery for an ACTIVE Primary Owner.
- Both use Shield magic-link identities and email delivery; each new request invalidates the prior magic-link token.
- The route requires Platform Admin permission, recent MFA, explicit reason and current Owner membership reference.
- Responses never reveal a magic-link token or password.
- Email delivery failures are reported safely and can be retried.

## 6. Primary Owner reassignment

- Platform Admin reviews the current Owner and supplies the current Owner membership public UUID, target email, reason and explicit confirmation.
- The target must be an existing active Shield identity, not a Platform Admin, and must not hold active membership in another Tenant.
- A target already in this Tenant as Staff may be promoted by updating the existing membership row; no duplicate tenant/user membership is inserted.
- Under the existing identity-membership advisory lock and a database transaction, the former Owner membership is deactivated, the target membership is created/promoted to ACTIVE PRIMARY_OWNER, and the unique active-owner guard remains authoritative.
- The transaction records immutable Audit evidence and publishes the relevant membership change.
- The former Owner's remembered/magic-link identities are invalidated and their sessions are revoked after commit. The target identity's existing sessions are also revoked so its new Owner access is re-established through the fresh link. Existing permission grants on the promoted Staff membership are revoked. The former Owner's global Shield user is not disabled.
- The new Owner receives a Shield magic-link recovery/access email after commit. Delivery failure does not roll back the completed reassignment and is surfaced for retry.
- If the current Owner changed since the Admin reviewed the detail, reject with a conflict and require refresh.

## 7. Invariants and security

- One Shop per Tenant.
- At most one ACTIVE PRIMARY_OWNER per Tenant, enforced by the existing generated unique guard.
- One active Tenant membership per Shield identity, enforced through `MembershipInvariantService` under the shared identity-membership advisory lock.
- At most one pending Primary Owner invitation per Shield identity, preventing multiple Tenants from competing for the single Shield magic-link identity.
- No raw passwords, reset tokens, magic-link tokens or credential verifiers in API responses, logs, Audit metadata or UI.
- No Platform Admin impersonation and no Tenant business-data access is opened by onboarding/reassignment.
- All sensitive Owner operations require a non-empty reason (maximum 500 chars) and current-state confirmation where applicable.
- Audit is appended inside the same DB transaction as each committed membership/ownership change. Email delivery is outside the transaction and reported separately.
- Session/remember-token invalidation occurs only after the owning transaction commits.
- Existing Tenant/Shop, Identity, BrandingDomain, Subscription, Audit and Sync service ownership remains respected.
- No new schema, permissions, cached state, or alternate credential store without separate Owner approval.

## 8. API surface

- Existing `POST /api/v1/platform/tenants`: guided onboarding; returns public Tenant/Shop IDs, hostname, Owner email and invitation delivery state only.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/invitation`: resend pending Owner invitation.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/recovery`: issue active Owner access-recovery magic link.
- `POST /api/v1/platform/operations/tenants/{tenantPublicId}/owner/reassign`: confirmed Primary Owner reassignment.
- `GET /owner/activate` and `POST /owner/activate`: authenticated Owner invitation acceptance/password setup.
- Existing Tenant detail read model adds safe Owner name/email, membership public UUID/status and onboarding progress; no internal IDs.

Each Platform endpoint is protected by the existing Platform Admin filter and its existing permission boundary. Do not add a new permission in WP2.

## 9. Required UI

- Remove the temporary password field from the Add shop flow.
- Clearly explain that an invitation is emailed and the Owner sets their own password.
- Display invitation delivery status and pending activation.
- Tenant detail shows current Owner identity/status and offers reasoned resend/recovery/reassignment actions only when valid for the current state.
- Reassignment UI displays current Owner and target identity, requires reason and explicit confirmation, and handles stale-state conflicts.
- Owner activation page is mobile-responsive and does not expose Tenant access until activation commits.

## 10. Manual acceptance scenarios

The Owner will verify locally:
1. New Tenant onboarding creates exactly one Tenant, one Shop, one invited Owner membership, one branding profile and one primary platform domain.
2. New onboarding leaves Tenant ONBOARDING, Shop INACTIVE and does not create a Trial.
3. Admin never enters, sees or sends an Owner password.
4. Existing eligible Shield identity can be reused safely.
5. Existing Platform Admin, inactive identity or identity with another active Tenant membership is rejected.
6. Duplicate email, hostname and one-Shop conflicts are safe and do not leave partial state.
7. Invitation email delivery and Shield magic-link login work.
8. New Owner can set their own password without knowing a temporary password.
9. Owner activation commits Tenant/Shop/membership and exactly one 30-day Trial.
10. Failed activation leaves all records pending and retryable.
11. Invitation resend invalidates the prior token and sends a fresh link.
12. Active Owner recovery issues a one-time magic link without exposing token material.
13. Reassignment to an eligible unassigned identity succeeds.
14. Reassignment to an existing Staff member in the same Tenant promotes the existing row.
15. Platform Admin, inactive identity, cross-Tenant active membership, invalid email and self-reassignment are rejected.
16. Stale current Owner confirmation is rejected.
17. Concurrent reassignment attempts serialize; exactly one active Primary Owner remains.
18. Former Owner session and remember/magic-link credentials are invalidated after commit; their global Shield account is not disabled.
19. New Owner receives access recovery link; mail failure is safely retryable.
20. Every committed sensitive change has the expected reasoned Audit evidence; failed transactions leave no partial Audit or Sync evidence.
21. Interrupted onboarding can be resumed by resending the invitation; duplicate submissions do not create duplicate graph records.
22. Owner activation and Admin operations work on phone, tablet and desktop.
23. Anonymous, non-Platform, missing permission and expired-MFA Admin API requests are denied.
24. No credential/token/secret or internal numeric identifier appears in responses, UI, logs or Audit metadata.

## 11. Acceptance gate

WP2 is complete only when the local build/runtime checks pass, the Owner executes and records all applicable manual scenarios, and the Owner explicitly accepts WP2. WP3 must not start before that acceptance.
