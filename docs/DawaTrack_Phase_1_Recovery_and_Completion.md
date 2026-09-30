DawaTrack Phase 1 Recovery & Completion — Four Work Package Implementation Plan
A. Planning Assessment
Phase 1 recovery should be delivered through four sequential vertical milestones because the outstanding problems form a clear dependency chain:

The runtime, schema baseline, authentication boundary, and Platform Admin authority must be trustworthy first. The current application cannot be fully executed in the supplied environment, public registration is enabled, CSRF is inactive, platform_admin is not configured, and the MFA filter relies on session state that no supported flow establishes. 

Tenant identity workflows must then be made transactionally correct and usable by both Owners and Staff. Onboarding currently creates the Shield identity before the Tenant transaction, while the Shop shell unconditionally loads Owner-only resources. 

White-label assets and domains depend on working authentication, Tenant context, Owner authority, and Platform Admin MFA. The current asset upload client call is broken, and the domain lifecycle stops before TLS readiness and operational activation. 

Subscription and lifecycle administration must be completed last, after Admin security and Staff-cap enforcement exist, because downgrade decisions depend on trustworthy membership counts and Tenant isolation. Package 4 can then close Audit coverage, documentation, and full Phase 1 acceptance.

Each package ends at an independently reviewable operational boundary. No package is merely a backend layer; each includes its routes, services, UI, responsive behavior, Audit, documentation relevant to that milestone, and manual verification.

The implementation plan preserves the approved modular-monolith and frontend architecture: server-derived tenancy, Shield identity, Vue 3 Composition API, native Fetch, Bootstrap/Sass, static self-hosted assets, and no JavaScript application bundler. The Technical Blueprint explicitly requires one shared Shop application governed by permissions, a logically separate Platform Admin interface, and backend-established Tenant authority. 

B. Dependency Overview
WP1 — Runtime, Database & Security Foundation
  ↓
WP2 — Tenant Onboarding, Staff, Permissions & Shop Access
  ↓
WP3 — White-Label Branding, Assets, PWA & Custom Domains
  ↓
WP4 — Subscription Lifecycle, Tenant Operations, Audit Closure & Acceptance
Dependency logic
WP1 → WP2: Onboarding cannot be reviewed honestly until Platform Admin authentication and MFA work. Staff and Owner workflows also need reliable Shield sessions, CSRF protection, rate limiting, JSON error semantics, and executable runtime dependencies.

WP2 → WP3: Branding and domain changes require a functioning Owner shell, reliable Tenant/Shop context, effective access modes, and correct Staff/Owner separation.

WP3 → WP4: Domain activation and white-label presentation must already work before final subscription and Tenant lifecycle actions are tested across platform and custom hostnames.

WP4 closes the phase: Subscription changes, suspension, closure, and final Audit review exercise every earlier authority and presentation boundary. The Roadmap’s Phase 1 Definition of Done requires all membership/status/entitlement gates, Admin MFA, complete brand/domain workflows, safe uploads, schema parity, and atomic Audit before approval. 

C. Work Package 1 — Runtime, Database & Security Foundation
Objective
Establish a reproducible, executable, correctly collated, session-secure foundation with operational Shop/Admin authentication and mandatory recent MFA for Platform Administrators.

No Tenant onboarding or later administrative mutation should be developed on top of the current nonfunctional Admin boundary.

Existing Problems It Resolves
WP1 owns these investigation findings:

missing Composer runtime dependencies;

incomplete/untracked node_modules;

missing self-hosted Vue runtime asset;

lack of executable MySQL verification environment;

inconsistent utf8mb4_0900_ai_ci, utf8mb4_unicode_ci, and sample utf8mb4_general_ci;

missing platform_admin group;

unused starter Shield groups/permissions that do not represent DawaTrack authority;

public registration enabled;

CSRF filter disabled;

generic HTML redirects for API authentication failures;

missing stable JSON 401/403/409/422 response policy;

no real MFA enrollment/challenge/recent-verification flow;

same undifferentiated Shop/Admin redirect behavior;

generic Shield login and recovery presentation;

no demonstrated login/recovery throttling;

missing or incomplete sensitive-endpoint rate limiting foundation;

insufficient session/logout/recovery invalidation behavior;

stale runtime/bootstrap/security/collation documentation.

The current mismatch is explicit: the application config already uses utf8mb4_unicode_ci, while raw SQL and schema notes use utf8mb4_0900_ai_ci. 

Exact Implementation Scope
Recreate PHP dependencies strictly from composer.lock.

Recreate frontend dependencies strictly from package-lock.json.

ensure node_modules/ is ignored and treated as disposable;

rebuild CSS, Vue, Dexie, and application static assets;

verify source/generated asset reproducibility;

establish a documented disposable MySQL 8.4 verification method if the environment permits;

resolve the migration-history question before changing schema source;

standardize Phase 1 text collation;

configure DawaTrack-specific Shield groups and permissions;

disable registration;

enforce session-write CSRF protection;

create browser/API-aware authentication filters and error responses;

implement rate-limit policies for current sensitive routes and reusable policies for later packages;

implement supported Admin MFA;

implement actual branded Shop/Admin login, recovery, MFA, logout, and expired-session paths;

Audit authentication/MFA recovery actions without storing secrets.

Backend Work
Authentication boundary
Create focused components rather than expanding existing filters into large policy classes:

Shop authentication filter

HTML requests: remember a safe same-origin destination and redirect to the branded Shop login.

API requests: return a standard JSON 401 SESSION_EXPIRED/AUTHENTICATION_REQUIRED.

Platform Admin authentication filter

require authenticated Shield identity;

require platform_admin;

reject any Tenant membership as proof of Admin authority;

require completed mandatory second factor;

require recent MFA for sensitive Admin operations;

return browser redirects for views and JSON errors for APIs.

Recent MFA service

establish recent verification only after the supported MFA authenticator succeeds;

bind the timestamp to the authenticated Admin/session;

expire after 15 minutes;

clear on logout, recovery, credential change, MFA reset, identity switch, or session invalidation;

never trust a client-submitted timestamp.

Authentication presentation resolver

resolve branding from the requested active hostname;

fall back to safe platform branding;

never derive Tenant authority from branding resolution.

Response semantics
Introduce reusable exception/response mapping:

Condition	HTTP/API result
No valid session	401 AUTHENTICATION_REQUIRED or SESSION_EXPIRED
Authenticated but wrong group/permission	403 PERMISSION_DENIED
MFA absent/stale	403 MFA_REQUIRED with safe re-verification destination
Uniqueness/state race	409 CONFLICT
Input invalid	422 VALIDATION_FAILED, field details where safe
Resource absent or out of scope	404 RESOURCE_NOT_FOUND
Read-only/blocked/closed	stable access-specific 403/409 contract documented consistently
Controllers remain thin and do not duplicate this mapping.

Rate limiting
Configure named throttling policies for:

login attempts;

magic-link/recovery requests;

MFA challenge/resend/verification;

recovery/reset;

existing uploads and domain requests;

Platform Admin sensitive writes.

Later packages must attach the appropriate existing policy to their new routes instead of inventing new mechanisms.

Frontend Work
Replace the branded /auth launcher-only behavior with actual branded authentication views.

Provide distinct but visually consistent:

Shop sign-in;

Shop recovery request/result;

Admin sign-in;

MFA enrollment;

MFA challenge;

stale-MFA step-up;

MFA recovery/reset outcome;

session-expired;

denied-account/deactivated-account presentation.

Preserve normal server forms where Shield requires them; Vue should only progressively enhance appropriate state.

Use the same constrained brand data as the application shell.

Preserve entered identifiers after safe validation failures, but never repopulate passwords, MFA codes, or recovery secrets.

Ensure redirects cannot become open redirects.

Update the Fetch client to distinguish session expiry, permission denial, MFA requirement, validation, conflict, blocked, and network failures.

Mobile requirements
At 320/360/390px:

all authentication and MFA controls remain one-column and touch-sized;

recovery instructions wrap without horizontal scrolling;

MFA code input works without precision interaction;

errors are associated with fields and announced;

brand imagery cannot make text unreadable;

keyboard and 200% zoom retain complete functionality.

Database/Configuration Work
Inspect any available configured database and migration ledger.

Choose the collation path specified in Section H.

Update all Phase 1 raw SQL table defaults to utf8mb4_unicode_ci.

Preserve explicit CHARACTER SET ascii COLLATE ascii_bin identifiers.

Align:

app/Config/Database.php;

environment example;

MySQL setup instructions;

schema blueprint;

Roadmap;

schema notes;

migration register.

Align strict SQL mode and UTC requirements with actual configuration/runtime connection initialization.

Ensure migration ordering remains Shield first, then DawaTrack Phase 1, then seeder. The migration deliberately executes the registered SQL verbatim. 

Validate plan and Sync Generation seed repeat-safety.

Do not alter intentional late audit_events.actor_device_id FK documentation.

Security Work
Add platform_admin to Shield configuration with only Platform Admin permissions.

Remove or neutralize irrelevant starter authorization groups rather than treating superadmin as DawaTrack authority.

Set allowRegistration = false.

Confirm password minimums and compromised/common-password expectations consistent with approved policy.

Enable CSRF for JSON, form, and multipart session-authenticated writes.

Validate CSRF token refresh/rotation behavior in the Fetch client.

Enforce secure/HTTP-only/SameSite production cookie behavior.

Verify session ID rotation and invalidate session state after security recovery.

Use generic login/recovery responses that do not disclose account existence.

Do not log credentials, MFA factors, recovery codes, magic links, cookies, or request bodies.

Retain CSP compatibility with self-hosted scripts/styles.

Audit Work
Audit, with bounded metadata:

Admin MFA enrollment completed;

MFA verification/step-up completed;

MFA reset/recovery;

Admin credential recovery;

forced session invalidation;

other sensitive authentication recovery.

Do not Audit ordinary failed password text or raw codes. Technical login-abuse telemetry remains in redacted security logs, not verbose business Audit rows.

The Audit service should not yet be globally redesigned; centralized metadata redaction closure belongs to WP4.

Documentation Work
Update only documentation directly changed by WP1:

runtime installation and dependency reproduction;

environment setup;

MySQL/collation baseline;

Shield group bootstrap;

authentication route behavior;

MFA operational/recovery procedure;

CSRF/rate-limit/session expectations;

migration/schema notes affected by collation.

Global Phase 1 completion claims remain for WP4.

Likely Files/Modules Affected
.gitignore

composer.json, composer.lock only if installation proves metadata correction necessary

package.json, package-lock.json

scripts/build-frontend-assets.sh

app/Config/Auth.php

app/Config/AuthGroups.php

app/Config/Filters.php

app/Config/Routes.php

app/Config/Database.php

app/Config/Cookie.php

app/Config/Session.php

app/Config/Security.php

app/Filters/AuthenticatedFilter.php

app/Filters/PlatformAdminFilter.php

new focused authentication/MFA controllers, services, filters, and rate-limit policy classes

app/Views/auth/*

app/Views/layouts/shell.php

resources/js/auth/app.js

resources/js/api/http-client.js

resources/css/app.scss

docs/database/sql/phase-01/001_phase_01_foundation.sql

possibly one corrective migration only under Section H scenario 2

env

affected configuration, schema, security, migration, and bootstrap documents

Manual Verification
Clean-install Composer and npm dependencies from locks.

Run the frontend build twice and confirm reproducible required outputs.

Confirm no public CDN requests and no runtime dependency on node_modules.

Start with disposable MySQL 8.4.

Run Shield migrations, Phase 1 migration, and seeder.

Inspect table/database collations and intentional ASCII columns.

Verify registration routes are unavailable.

Verify valid/invalid Shop login, logout, expiry, deactivation, and recovery.

Verify generic account-enumeration-resistant recovery responses.

Verify API calls receive JSON 401 while browser pages redirect appropriately.

Submit JSON/form/multipart writes with missing, invalid, and valid CSRF tokens.

Exercise login/recovery/MFA throttles.

Bootstrap a real platform_admin.

Complete MFA enrollment/challenge.

expire the 15-minute recent-MFA window and verify step-up.

clear/recover MFA and verify old recent-MFA/session state cannot be reused.

confirm ordinary Shop identities cannot enter Admin routes.

inspect Audit rows and logs for secret leakage.

repeat login/recovery/MFA at phone, tablet, desktop, keyboard-only, and 200% zoom.

Acceptance Criteria
WP1 is accepted only when:

the application boots from clean locked dependencies;

self-hosted Vue/static assets are present;

a disposable MySQL migration/seed path succeeds where environment support exists;

all non-ASCII Phase 1 table defaults use utf8mb4_unicode_ci;

public registration is disabled;

session writes reject missing/invalid CSRF;

Shop and Admin authentication paths behave distinctly;

platform_admin is real and least-privileged;

supported mandatory MFA and 15-minute step-up operate end-to-end;

API and browser auth failures use correct semantics;

branded login/recovery/MFA pages work responsively;

no authentication/MFA/recovery secret enters Audit or logs.

Explicitly Out of Scope
Tenant onboarding transaction repair;

Staff lifecycle and permission UI;

asset editor completion;

custom-domain lifecycle;

subscription changes;

Tenant/Shop lifecycle administration;

Phase 2 Audit query UI or sync_changes;

offline authentication or synchronization.

Stop Condition
Stop after WP1 acceptance evidence is produced. Do not begin onboarding/Staff recovery work until the Platform Admin can authenticate, complete MFA, and reach the Admin shell through the final security boundary.

D. Work Package 2 — Tenant Onboarding, Staff, Permissions & Shop Access
Objective
Deliver a transactionally sound Tenant onboarding workflow and a genuinely usable Owner/Staff identity, permission, access-mode, and Shop-settings vertical slice.

Existing Problems It Resolves
WP2 owns:

Owner identity created outside onboarding transaction;

possible orphan Shield identity;

unchecked named-lock acquisition;

weak duplicate email/hostname error handling;

missing Sync Generation prerequisite assertion;

incomplete Owner creation Audit;

concurrency-unsafe Staff cap checks;

insufficient session/token invalidation;

missing ordinary active-membership invariant;

incomplete Staff credential reset/recovery UI;

Owner/Admin exclusion and same-Tenant targeting closure;

effective permission endpoint not consumed;

permission checkboxes showing false state;

use of prompt() for reasons;

Staff unable to load the Shop shell;

unconditional Owner-only API calls;

incomplete permission/read-only/blocked/closed/session-expired presentation;

weak Shop field validation and Audit deltas;

onboarding and Shop UI mobile parity.

Exact Implementation Scope
Atomic onboarding transaction
One application service transaction must own:

validate input;

assert active Sync Generation exists;

acquire and verify the onboarding lock;

check normalized email and hostname availability;

create Shield Owner identity;

create Tenant;

create exactly one Shop;

create Primary Owner membership;

create default branding profile;

create active platform subdomain;

create Trial entitlement;

create subscription event;

append Owner-created and Tenant-onboarded Audit evidence;

commit;

release lock in guaranteed cleanup.

Any failure rolls back every database row, including the Shield identity.

Staff lifecycle
Complete:

list Owner and Staff status safely;

create Staff;

deactivate/reactivate Staff;

initiate supported credential recovery/reset;

invalidate active sessions, remember tokens, and applicable recovery/security state;

require appropriate reason;

enforce no targeting of Primary Owner or Platform Admin through Staff routes;

prevent cross-Tenant targeting;

prevent multiple active ordinary Tenant memberships for the same identity;

serialize Staff-cap checks within the transaction;

leave existing Staff active when plans later change.

The preferred credential pattern is a Shield recovery/forced-reset path rather than displaying reusable passwords to the Owner. If a temporary credential remains necessary, it must be one-time, force a reset, never be logged/Audited, and never be returned again.

Effective permissions
return catalogue metadata grouped for human presentation;

return current grants and retained grant/revoke history;

load permission state when a Staff record is expanded;

render actual checked state;

grant/revoke with server-authoritative refresh;

require a reason for revoke;

use an accessible reason dialog;

reject unknown/stale/out-of-scope targets;

preserve deny-by-default authorization.

Role-aware shell
Load only resources the current role may access:

common: context and effective access;

Owner: Staff, settings, branding, domains, subscription management views permitted in Phase 1;

Staff: only permitted/current Phase 1 shell areas;

blocked/closed: safe explanatory surface and allowed logout/recovery path;

read-only: visible configuration/status with all mutations consistently disabled and server-rejected;

expired session: redirect through WP1 behavior.

Backend Work
Create or refine focused services:

OnboardingService

MembershipInvariantService

StaffLifecycleService

CredentialRecoveryService

PermissionService

ShopSettingsService

a role/effective-capability presenter for the Shop context

Use explicit transaction boundaries and row/advisory locks around:

Tenant onboarding;

Staff creation;

Staff reactivation;

membership state changes.

Do not rely only on a pre-transaction count.

Return public UUIDs where practical. Numeric membership IDs may remain internal to current UI only if every lookup is Tenant-scoped and no cross-Tenant existence is disclosed.

Frontend Work
Finish Admin onboarding review, validation, duplicate/conflict, busy, rollback-safe error, and completion states.

Split Shop shell initial loading by role/capability rather than one all-or-nothing Promise.all.

Add:

Staff status card;

credential recovery/reset dialog;

permission-state loading;

searchable/grouped permissions;

grant/revoke state and history;

reason dialog;

clear Staff-cap display/error;

Owner-only Shop settings navigation;

explicit read-only/blocked/closed/denied states.

Retain form values after correctable errors.

Never expose password or recovery secret after submission.

Mobile requirements
onboarding review fits 320px without horizontal scrolling;

Staff records remain cards;

permission search/groups are touch-friendly;

dialogs work with software keyboard and zoom;

credential reset and status confirmation require no native prompt();

every Owner action remains available at phone widths;

Staff sees a useful shell rather than a partial/failed Owner page.

Database/Configuration Work
No new schema is expected.

Use existing:

generated Primary Owner guard;

membership composite uniqueness;

plan cap snapshots;

subscription period;

Audit tables.

A schema change should be proposed only if implementation proves the approved invariant cannot be enforced transactionally with existing structures. No such change should be presumed.

Security Work
Owner authority derives from active server-resolved membership.

Staff may not manage Owner/Admin identities.

Staff creation/recovery is rate-limited using WP1 policies.

Passwords/recovery artifacts never enter logs or Audit.

all status/reset/grant/revoke writes require CSRF;

deactivation and reset terminate usable sessions/tokens;

duplicate/conflict responses do not disclose cross-Tenant identities;

hostname remains context selection, not authorization.

Audit Work
Atomically record:

Owner identity created;

Tenant/Shop onboarding;

Staff identity/membership created;

Staff activated/deactivated;

Staff credential recovery/reset;

permission granted/revoked;

Shop profile changed.

Audit includes actor, membership authority, Tenant, Shop, target public ID, reason where required, correlation ID, and bounded changed-field metadata.

Documentation Work
Update the affected onboarding, identity, permission, Shop-context, and Shop-settings module contracts and package-specific manual verification instructions. Do not yet declare Phase 1 complete.

Likely Files/Modules Affected
app/Controllers/Api/OnboardingController.php

app/Controllers/Api/ShopController.php

app/Modules/TenantShop/Application/OnboardingService.php

app/Modules/TenantShop/Application/ShopSettingsService.php

app/Modules/TenantShop/Infrastructure/ShopContext.php

app/Modules/Identity/Application/StaffService.php

app/Modules/Identity/Application/PermissionService.php

new focused membership/session/recovery services

app/Modules/Access/Application/AccessGate.php only for capability presentation needed by the shell

app/Config/Routes.php

app/Views/admin/index.php

app/Views/shop/index.php

resources/js/admin/app.js

resources/js/shop/app.js

resources/js/api/phase-one.js

resources/css/app.scss

affected module, permission, and manual-verification docs

Manual Verification
Onboard a Tenant successfully and inspect every required row.

Force failure:

after Owner identity creation;

after Shop creation;

after subscription creation;

during Audit insert.

Confirm no orphan identity/Tenant/Shop rows remain.

Attempt duplicate normalized email and hostname.

Simulate failed lock acquisition.

remove/inactivate Sync Generation and verify onboarding is refused.

Sign in as Owner and Staff.

Verify Staff shell loads without Owner-only requests.

Create Staff to Trial/Growth cap 5 and Starter cap 2.

issue concurrent create/reactivate attempts and confirm the cap holds.

Deactivate Staff and verify current session and remember access fail.

Reset/recover credentials and verify old credentials/session/tokens fail.

Attempt to target Owner, Admin, and another Tenant’s Staff.

Load effective permissions, grant, refresh, revoke with reason, and inspect history.

call a hidden/denied operation directly and confirm backend denial.

update every Shop field with valid and invalid timezone/email/phone/length/null input.

inspect Audit and force rollback to verify mutation and Audit disappear together.

repeat all UI journeys at required phone/tablet/desktop widths, keyboard-only, and 200% zoom.

Acceptance Criteria
onboarding is all-or-nothing, including Shield identity;

exactly one Shop is created;

Sync Generation prerequisite is enforced;

duplicate/lock/input failures are useful and safe;

Owner and Staff can both sign in and load the appropriate Shop shell;

Staff status/reset/session invalidation works;

cap enforcement survives concurrency;

active ordinary membership invariant holds;

permission UI always reflects server state;

no prompt() remains;

Shop settings are normalized, validated, and atomically audited;

all new screens satisfy mobile parity.

Explicitly Out of Scope
branding asset hardening and final runtime propagation;

custom-domain TLS/activation;

subscription lifecycle changes;

Tenant/Shop suspension/closure;

Phase 2 operational modules.

Stop Condition
Stop once a Platform Admin can securely onboard a Tenant, the Owner can manage Staff/settings, and ordinary Staff can enter a correct role-aware shell with verified isolation and session invalidation.

E. Work Package 3 — White-Label Branding, Assets, PWA & Custom Domains
Objective
Complete the full Phase 1 white-label presentation and custom-domain lifecycle with safe asset storage, versioned runtime propagation, and no direct database operations.

Existing Problems It Resolves
WP3 owns:

broken phaseOneApi.upload versus uploadAsset call;

incomplete asset type/dimension constraints;

file write outside DB failure cleanup;

no defined safe optimization/re-encoding policy;

no login-background propagation;

no PWA manifest icons;

no fallback PWA icon strategy;

incomplete dynamic manifest fields and cache/version behavior;

stale static manifest ambiguity;

incomplete asset preview/error UX;

hostname validation gaps;

no IDN/punycode or reserved-host policy;

ambiguous DNS TXT instructions;

one-time challenge token loss with no rotation;

no domain status refresh workflow;

no TLS readiness route/UI;

disconnected domain activation;

no explicit primary switching/deactivation/fallback UI;

incorrect Admin provenance semantics for Owner domain requests;

missing Audit for TLS and primary transitions;

responsive white-label/domain workflow gaps.

Exact Implementation Scope
Branding profile
Validate and normalize:

display name;

tagline;

public description;

five colors;

application title;

PWA full/short names.

Requirements:

application-level maximum lengths match schema;

required names/titles are nonempty;

optional text normalizes blanks to null;

colors normalize consistently to uppercase #RRGGBB;

accessible contrast is validated or the UI warns/blocks according to the approved branding contract;

configuration version increments once per successful change;

Audit captures changed field names and safe bounded before/after values;

successful changes refresh the visible presentation.

Assets
For each type:

LOGO

FAVICON

LOGIN_BACKGROUND

PWA_ICON_192

PWA_ICON_512

Define explicit:

MIME allow-list;

maximum bytes;

dimension/aspect rules;

exact PWA icon dimensions;

decoded pixel/dimension safety bounds;

accepted animated-image policy;

image re-encoding/optimization policy where supported.

File/DB flow:

validate upload metadata and decoded content;

write to a private temporary location using an opaque name;

safely process/re-encode as required;

calculate final hash/metadata;

begin DB transaction;

deactivate old metadata, insert new metadata, increment relevant presentation version if required, append Audit;

commit;

atomically promote/retain final file;

on failure, remove temporary/orphan output without damaging the old active asset.

Domain lifecycle
Create explicit services/commands for:

request;

challenge rotation;

DNS verification;

verification failure/retry;

TLS readiness update;

activation;

primary switch;

deactivation;

platform fallback.

Owner requests the domain; Platform Admin controls TLS readiness and activation. Primary switching policy must ensure one valid active primary while preserving the platform subdomain as a safe fallback.

Backend Work
Use focused services:

BrandingProfileService

BrandAssetService

BrandPresentationResolver

HostnamePolicy

DomainChallengeService

DomainVerificationService

DomainLifecycleService

Platform Admin domain controller

The scheduled/Spark DNS command calls the same verification service as any explicit Admin status action; it must not contain duplicate business logic.

Domain transitions should be state-machine-like and transactionally conditional:

PENDING
  → VERIFIED
  → TLS ACTIVE
  → ACTIVE
  → PRIMARY
Invalid transitions produce conflict responses, not generic validation failures.

Frontend Work
Owner UI
full branding editor with constrained preview;

asset upload/replace controls with per-type guidance;

current asset previews;

loading, validation, replacement success/failure states;

domain request with explicit TXT record name/value;

challenge rotation confirmation;

status refresh;

verified/TLS/active/primary status timeline;

platform fallback visibility.

Platform Admin UI
pending/verified domain operational list;

TLS-ready/failed update with reason;

activate/deactivate;

make primary;

revert primary to platform domain;

clear confirmation and result states.

Runtime presentation
Apply to:

Shop header/logo;

actual Shop login;

recovery;

Admin MFA surfaces where hostname branding is applicable;

favicon;

login background;

CSS variables;

application title;

manifest name/short name/description/colors/icons.

Mobile requirements
previews scale without overflow;

file input/help/errors work at 320px;

no precision crop interaction is required;

login background retains readable contrast;

DNS record names and values wrap/copy safely;

domain status/actions remain card-based;

every activation/primary/fallback action is usable without desktop-only controls.

Database/Configuration Work
The existing branding/domain schema should support this package. No migration is planned unless implementation discovers a genuine approved-schema mismatch.

Preserve:

private byte storage outside public;

active-asset generated uniqueness;

global hostname uniqueness;

primary-domain generated guard;

hashed challenge storage;

no TLS private keys/certificates in MySQL. 

Resolve dynamic versus static manifest delivery:

one canonical dynamic route;

static fallback only if deliberately named/used;

versioned icon URLs based on content hashes;

cache headers that permit asset immutability while ensuring manifest/config updates become visible.

Security Work
normalize and validate ASCII hostnames;

define IDN policy: accept Unicode only through explicit normalized punycode conversion, or reject it clearly;

reject IP literals, ports, schemes, paths, wildcards, platform apex, reserved subdomains, localhost/internal names, and malformed labels;

compare global uniqueness only after normalization;

store only challenge hash;

never Audit raw token;

allow challenge rotation without accepting an old token;

require recent Admin MFA for TLS readiness, activation, deactivation, and primary switch;

require verified ownership and active TLS before custom-domain activation/primary selection;

public asset delivery must resolve active, verified hostname context and active scoped assets only;

responses must use nosniff and safe content types.

Audit Work
Atomically cover:

branding profile updated;

asset replaced;

domain requested;

challenge rotated;

DNS verified/failed where authoritative;

TLS marked ready/failed;

domain activated/deactivated;

primary switched;

platform fallback restored.

Audit includes Owner/Admin authority context and reason for operational transitions, but excludes raw challenge and TLS secret material.

Documentation Work
Update package-specific branding asset rules, hostname normalization, DNS challenge instructions, TLS operational procedure, domain transition rules, manifest/cache behavior, and manual scenarios.

Likely Files/Modules Affected
app/Modules/BrandingDomain/Application/BrandingService.php

app/Modules/BrandingDomain/Application/BrandResolver.php

app/Modules/BrandingDomain/Application/DomainService.php

app/Commands/VerifyShopDomains.php

app/Controllers/Api/ShopController.php

app/Controllers/Api/AssetController.php

app/Controllers/Web/BrandAssetController.php

app/Controllers/Web/ShellController.php

new focused asset/domain lifecycle classes and Admin controller

app/Config/Routes.php

app/Views/layouts/shell.php

app/Views/auth/*

app/Views/shop/index.php

app/Views/admin/index.php

resources/js/shop/app.js

resources/js/admin/app.js

resources/js/api/phase-one.js

resources/css/app.scss

public/manifest.webmanifest

fallback icon assets

public/service-worker.js only for presentation version metadata, not offline business caching

affected branding/domain/PWA/operations docs

Manual Verification
Edit every branding field with valid and boundary values.

Submit blank required fields, oversized strings, invalid colors, and poor-contrast combinations.

Confirm configuration version changes once per successful update.

Upload/replace every asset type.

Attempt fake extensions, MIME mismatch, malformed image, truncated image, polyglot/script content, oversized bytes, excessive decoded dimensions, and wrong PWA dimensions.

force DB/Audit/filesystem failures and confirm no orphan/incorrect active file.

verify old immutable URLs and new content-hash URLs behave according to retention policy.

inspect logo, favicon, login background, title, colors, and manifest across platform/custom hostnames.

verify dynamic manifest name, short name, description, theme/background colors, and 192/512 icons.

request valid and invalid hostnames, including Unicode/IDN, uppercase, trailing dot, IP, port, path, platform suffix, reserved labels, and duplicates.

rotate DNS challenge and confirm the prior token fails.

verify missing/mismatched/matching DNS TXT.

prove activation fails before verification and before TLS readiness.

mark TLS ready through Admin UI, activate, switch primary, deactivate, and restore platform primary.

confirm Shop authority does not change when hostname changes.

inspect Audit after every transition.

repeat all UI workflows at required viewport widths, keyboard-only, and 200% zoom.

Acceptance Criteria
all branding fields are validated, versioned, audited, and propagated;

every approved asset uploads and renders safely;

file and DB failure handling leaves a consistent active asset;

actual authentication pages use logo/colors/login background;

the dynamic manifest includes working versioned 192/512 icons and fallbacks;

custom domains proceed end-to-end without DB edits;

verification and TLS are prerequisites to activation;

primary switching and platform fallback are explicit and audited;

hostname never grants authority;

all workflows have mobile parity.

Explicitly Out of Scope
offline caching of business data;

IndexedDB;

service-worker business fetch interception;

certificate private-key storage or certificate automation infrastructure beyond Phase 1 readiness state;

subscription and Tenant lifecycle management;

Phase 2 change sequencing.

Stop Condition
Stop after a Shop can be completely branded and reached through both platform and verified custom domains, including branded auth/PWA presentation, with the entire domain lifecycle operable through Owner/Admin HTTP UI.

F. Work Package 4 — Subscription Lifecycle, Tenant Operations, Audit Closure & Final Phase 1 Acceptance
Objective
Implement the remaining subscription and account lifecycle controls, close effective access and Audit policy, align all documentation with reality, and produce the final Phase 1 acceptance record.

Existing Problems It Resolves
WP4 owns:

no renewal/upgrade/downgrade/extension/expiry workflow;

no entitlement-overlap serialization;

no Trial-bound enforcement service;

no target-plan Staff-cap downgrade refusal;

Owner UI displaying the latest rather than effective entitlement;

no Tenant suspend/reactivate/close operations;

no Shop activate/deactivate operations;

incomplete AccessGate composition and inconsistent response codes;

absent Audit events for subscription/status lifecycle;

no centralized Audit sensitive-metadata safeguard;

no operational append-only privilege verification;

stale README and overclaimed completion/module/manual documents;

incomplete final MySQL/runtime/mobile/security acceptance evidence.

Exact Implementation Scope
Subscription Service
Implement immutable commands:

activate paid Starter/Growth period;

renew;

upgrade;

downgrade;

extend Trial within approved bounds;

record expiry event where an explicit operational event is required without mutating the period;

list current, historical, and future periods/events.

Every command:

requires Platform Admin and recent MFA;

validates plan and reason/reference;

acquires a Tenant-scoped serialization lock;

selects current/future periods under lock;

validates half-open boundaries and no overlap;

validates Trial bounds and plan snapshot values;

checks target Staff cap for immediate downgrade;

inserts a new immutable subscription row where required;

inserts the corresponding immutable event;

appends Audit;

commits atomically.

Never mutate historical period terms merely because time passes. Never store a mutable “current subscription status.”

Tenant lifecycle
Implement:

suspend;

reactivate;

close.

Rules:

Platform Admin + recent MFA;

mandatory reason;

timestamp/status_reason consistency;

close is terminal unless an approved document explicitly allows reversal;

no hard deletion;

session/access consequences occur immediately;

Owner/Staff see plain blocked/closed presentation;

no silent Tenant access by Admin.

Shop lifecycle
Implement:

deactivate;

reactivate/activate.

Rules:

reason and lifecycle timestamps;

Tenant-scoped Shop target;

effective access immediately becomes BLOCKED when inactive;

platform/custom presentation behavior remains safe;

no direct owner mutation unless approved authority permits it.

Access closure
Produce one coherent access policy combining:

authenticated identity;

active membership;

Tenant status;

Shop status;

effective entitlement;

required permission;

recent Admin MFA for sensitive Platform operations.

Backend Work
Create focused:

SubscriptionService

EntitlementPeriodEvaluator

SubscriptionHistoryQuery

TenantLifecycleService

ShopLifecycleService

EffectiveAccessPolicy

Admin subscription/lifecycle controllers

Keep AccessGate as a clear evaluator rather than mixing mutation orchestration into it.

Fix Owner subscription response to return:

current_entitlement;

historical_periods;

future_periods;

current active Staff/cap;

effective access mode.

Frontend Work
Platform Admin
Provide:

Tenant lookup/selection sufficient for Phase 1 operations;

current entitlement card;

chronological period/event history;

scheduled future period display;

paid activation;

renewal;

upgrade/downgrade;

Trial extension;

clear staff-cap conflict;

Tenant suspend/reactivate/close;

Shop activate/deactivate;

reason/confirmation dialogs;

recent-MFA step-up integration.

This is not the later broad Phase 9 Admin dashboard; it is the minimum operational UI required to complete Phase 1 without DB edits.

Owner Shop UI
Display separately:

current effective entitlement;

access mode;

start/end;

Staff cap and active count;

future scheduled entitlement;

relevant history;

read-only/blocked/closed explanation.

No billing/card-management UI is introduced.

Mobile requirements
subscription timelines become cards on phones;

plan/status actions remain fully available;

confirmations fit 320px and retain reason input;

no wide-table dependency;

blocked/closed messages remain readable and provide logout/recovery/help paths;

200% zoom and keyboard operation preserve every action.

Database/Configuration Work
The current schema is intended to support immutable periods/events. No new subscription-status field is permitted. 

Database work is limited to:

using existing indexes and constraints correctly;

considering an approved corrective index only if manual EXPLAIN/locking review proves it necessary;

verifying no accidental period updates are used;

validating collation and full schema result from WP1;

operationally applying append-only privileges to audit_events where environment separation permits.

Security Work
recent MFA for every sensitive lifecycle mutation;

no hidden Admin Tenant membership;

same-Tenant target validation;

mandatory reasons;

conflict-safe concurrent subscription operations;

closed Tenant cannot regain ordinary access through hostname or stale session;

suspended/inactive states do not expose mutation endpoints;

read-only permits only approved reads;

stable response codes for all access states;

direct endpoint calls enforce the same rules as visible controls.

Audit Work
Complete an action-by-action coverage inventory for all Phase 1 workflows.

Add a central Audit metadata policy that:

allow-lists or recursively rejects sensitive key names;

bounds string/array size;

rejects raw passwords/hashes;

rejects MFA/recovery secrets;

rejects raw verification tokens;

rejects TLS keys;

rejects cookie/session/auth tokens;

preserves safe before/after field changes;

retains actor, authority, Tenant, Shop, subject, reason, correlation, and timestamp.

Verify atomic Audit for:

onboarding and Owner creation;

Staff lifecycle/reset;

permission changes;

Shop profile;

branding/assets;

domain verification/TLS/activation/primary;

subscription actions;

Tenant/Shop lifecycle;

Admin MFA/recovery and other sensitive Admin actions.

Audit query/history UI beyond what is necessary to verify Phase 1 evidence remains Phase 2 scope.

Documentation Work
After implementation matches behavior, close all documentation:

correct README’s obsolete “Phase 0 only” statement. 

correct env versus .env.example setup naming;

update configuration;

migration register;

schema notes;

module map and Phase 1 module/completion docs;

permission catalogue;

security documentation;

MFA/Admin bootstrap;

branding/domain operations;

subscription/Tenant lifecycle operations;

dependency register;

manual verification checklist;

record remaining environment-only verification separately from implementation gaps.

Likely Files/Modules Affected
new Subscription application services/query objects

new Tenant/Shop lifecycle services

app/Modules/Access/Application/AccessGate.php

app/Modules/Access/Application/AccessDecision.php

app/Modules/Audit/Application/AuditService.php

app/Controllers/Api/ShopController.php

new Platform Admin controllers

app/Config/Routes.php

app/Views/admin/index.php

app/Views/shop/index.php

resources/js/admin/app.js

resources/js/shop/app.js

resources/js/api/phase-one.js

resources/css/app.scss

README.md

all affected Phase 1 configuration/schema/module/security/operation/manual-verification docs

Manual Verification
Database
Fresh MySQL 8.4 database.

Shield migrations.

Phase 1 migration.

Phase 1 seeder.

rollback and reapply.

inspect every Phase 1 SHOW CREATE TABLE.

verify all intended defaults are utf8mb4_unicode_ci.

verify UUID/hostname identifiers remain ascii_bin.

verify checks, FKs, generated guards, unique keys, and late-FK documentation.

verify one active Sync Generation and plan seeds.

End-to-end
Admin login and MFA enrollment/challenge/step-up/recovery.

Tenant onboarding and forced failures at multiple transaction points.

two-Tenant hostname and ID tampering.

Owner login/recovery/logout/session expiry.

Staff create/deactivate/reactivate/reset and session invalidation.

Staff cap boundaries and concurrent attempts.

effective permission grant/revoke/direct denied calls.

Shop settings.

all branding fields and assets.

malicious upload matrix.

domain request/challenge/rotation/DNS/TLS/activation/primary/fallback.

Trial, Starter, Growth, paid activation, renewal, upgrade, downgrade, extension, expiry.

overlapping/scheduled period rejection.

downgrade refusal above cap with no Staff deactivation.

Tenant suspend/reactivate/close.

Shop deactivate/reactivate.

FULL/READ_ONLY/BLOCKED/CLOSED.

correct current versus future subscription display.

immediate and rollback-safe Audit evidence for every sensitive action.

inspect Audit/logs for forbidden secret values.

verify runtime DB Audit privileges where possible.

all workflows at 320, 360, 390, tablet, and desktop;

keyboard-only and 200% zoom;

no dead controls, placeholders, fake values, or disconnected endpoints.

Acceptance Criteria
immutable subscription lifecycle is complete;

period overlaps are prevented under concurrency;

downgrade above target cap is refused without Staff changes;

current/history/future entitlements are presented correctly;

Tenant/Shop lifecycle actions are authorized, reasoned, effective, and audited;

AccessGate and API outcomes are coherent;

all Phase 1 sensitive mutations are atomically audited;

Audit rejects sensitive metadata;

documentation describes actual code;

all executable manual checks pass;

environment-only checks are clearly identified with no hidden implementation debt;

the full Roadmap Phase 1 Definition of Done can truthfully be assessed.

Explicitly Out of Scope
automated card billing;

Product, Inventory, Sales, Debt, Financial Movement, Reconciliation, and reporting;

Phase 2 Audit query/causal correction;

sync_changes;

devices;

IndexedDB/offline outbox/sync transport;

multi-branch workflows.

Stop Condition
Stop after the consolidated Phase 1 acceptance record is produced. Phase 2 must not begin unless the user explicitly reviews and approves Phase 1.

G. Investigation-to-Package Coverage Matrix
A checkmark identifies the one primary implementation package. Later packages may verify earlier work but do not re-own it.

Investigation Finding	WP1	WP2	WP3	WP4	Notes
Restore Composer dependencies	✓				Locked clean install
Reproducible npm dependency handling	✓				Disposable node_modules
Missing self-hosted Vue/static assets	✓				Rebuild from locks
Disposable MySQL 8.4 verification environment	✓				Final clean rehearsal repeated in WP4
utf8mb4_unicode_ci correction	✓				Migration strategy in Section H
Preserve explicit ascii_bin identifiers	✓				UUID/hostname semantics
DB strict mode/UTC configuration drift	✓				Runtime connection verification
Missing platform_admin group	✓				DawaTrack-specific authority
Least-privilege Shield groups	✓				No substitute superadmin
Public registration enabled	✓				Must be disabled
Password/session/cookie alignment	✓				Includes logout/recovery invalidation
CSRF inactive	✓				JSON/form/multipart
API unauthenticated redirect problem	✓				JSON 401 vs browser redirect
API 401/403/409/422 semantics foundation	✓				WP4 closes access-specific use
Login/recovery/MFA throttling	✓				Reusable rate policies
Sensitive-endpoint rate-limit foundation	✓				Later routes attach existing policies
Admin MFA enrollment/challenge	✓				Pinned Shield-supported mechanism
Recent MFA 15-minute step-up	✓				Trusted server session state
MFA expiry/recovery/session clearing	✓				No secret leakage
Branded real Shop login/recovery	✓				Not launcher-only
Branded Admin login/MFA	✓				Asset enrichment completed in WP3
Shop/Admin redirect/session authority	✓				Distinct route behavior
Onboarding identity outside transaction		✓			Single atomic operation
Orphan Owner prevention		✓			Forced failure verification
Duplicate email/hostname validation		✓			Safe 409/422
Onboarding lock acquisition check		✓			Explicit failure
Sync Generation prerequisite		✓			Existing seeded global generation
Owner creation Audit		✓			Separate bounded evidence
Default branding/platform domain/Trial transaction		✓			One onboarding unit
Staff create/list/status completion		✓			Owner UI/API
Staff reset/recovery UI		✓			Shield-based safe flow
Staff session/token invalidation		✓			Deactivate/reset
Staff cap concurrency		✓			Transactional lock/check
Active ordinary membership invariant		✓			Service-enforced
Owner/Admin exclusion from Staff routes		✓			Same-Tenant targeting
Effective permission API consumption		✓			Current checked state
Permission history/search/grouping		✓			Responsive cards/groups
Remove prompt() reason capture		✓			Accessible dialogs
Staff Shop-shell usability		✓			No Owner-only initial calls
Role/permission-aware navigation		✓			Server-authoritative
Package-level read-only/blocked/closed UI		✓			WP4 finalizes lifecycle sources
Shop settings validation		✓			IANA timezone, phone, email, lengths
Shop settings Audit deltas		✓			Safe bounded changes
Broken asset-upload API name			✓		uploadAsset contract
Type-specific asset validation			✓		Logo/favicon/background/icons
Filesystem/DB failure cleanup			✓		No orphan active state
Asset optimization/re-encoding policy			✓		Decoded safe output
Login background runtime use			✓		Actual auth surfaces
PWA 192/512 manifest icons			✓		Exact dimensions/fallback
Manifest description/version/cache			✓		Dynamic canonical route
Static/dynamic manifest ambiguity			✓		One documented behavior
Branding validation/contrast			✓		Lengths, required fields
Immediate brand presentation refresh			✓		Versioned resolution
Hardened hostname/IDN policy			✓		Reserved/internal rejection
Explicit DNS TXT name/value UX			✓		Copyable, durable instructions
Challenge retry/rotation			✓		Old token invalidated
DNS verification/service reuse			✓		Spark calls owning service
TLS readiness API/UI			✓		Recent Admin MFA
Domain activation endpoint/UI			✓		Verified + TLS prerequisite
Explicit primary switching			✓		Atomic guard
Domain deactivation/platform fallback			✓		No DB edit
Domain Audit completion			✓		Request through fallback
Subscription immutable lifecycle				✓	Trial/Starter/Growth
Paid activation/renew/upgrade/downgrade/extend				✓	New periods/events
No entitlement overlap				✓	Serialized Tenant command
Trial bounds and plan snapshots				✓	Server validation
Downgrade above Staff cap				✓	Reject; no auto-deactivation
Current vs history/future UI				✓	Fix latest-row defect
Tenant suspend/reactivate/close				✓	Reason/timestamps/access
Shop activate/deactivate				✓	Reason/timestamps/access
AccessGate full composition				✓	Identity through permission
Stable read-only/blocked/closed/conflict outcomes				✓	Final policy
Complete Phase 1 Audit coverage				✓	Coverage inventory
Central Audit sensitive-data guard				✓	Reject secret keys/content
Append-only Audit DB privilege verification				✓	Where operationally possible
README and stale completion claims				✓	Global documentation closure
Full runtime/manual acceptance				✓	Cross-package final gate
320/360/390/tablet/desktop parity				✓	Each WP also verifies its own UI
Keyboard-only and 200% zoom				✓	Each WP also verifies its own UI
Requested commits absent from clone				✓	Document actual release/commit lineage at closure
Untracked node_modules/ hygiene	✓				No tracked platform-specific dependencies
H. Database/Migration Strategy
Preliminary decision gate in WP1
Before changing SQL, inspect:

configured .env/database target without exposing credentials;

migrations ledger if a database is reachable;

deployment/runbook evidence;

whether any retained shared/staging/pilot data exists;

whether the current Phase 1 migration has been applied outside a disposable developer database.

The repository itself does not prove a durable database exists. Therefore the preferred pre-approval path is to correct the existing source, but the environment check is mandatory.

Scenario 1 — No durable database has applied Phase 1
Use the preferred pre-approval correction:

edit each Phase 1 table default in the existing raw SQL from utf8mb4_0900_ai_ci to utf8mb4_unicode_ci;

do not add a corrective migration;

retain every explicit ASCII column;

because the CodeIgniter migration executes this SQL verbatim, fresh migration behavior changes with the SQL source;

align application config, env, schema blueprint, Roadmap, configuration guide, schema notes, and migration register;

recreate the disposable database;

run Shield migrations, Phase 1 migration, and seeder;

verify with SHOW CREATE TABLE, information_schema.TABLES, and information_schema.COLUMNS;

perform rollback/reapply before Package 1 acceptance and again in the final WP4 rehearsal.

This avoids unnecessary historical migration debt before production approval.

Scenario 2 — A durable database has applied Phase 1
Preserve history:

do not rewrite the meaning of the already-applied migration as the only remedy;

add one explicitly approved corrective Phase 1 migration;

set the database default to utf8mb4_unicode_ci where deployment ownership permits;

convert each Phase 1 non-ASCII table/default and affected textual columns;

preserve explicit ASCII columns;

preflight unique indexes for collation-equivalent collisions;

document backup, maintenance/locking expectations, rollback decision, and post-conversion verification;

update raw bootstrap SQL so new installations begin correctly;

record both original and corrective migrations in the migration register.

Because both collations use utf8mb4, storage width does not inherently increase, but Unicode equality/sort weights differ. Unique-index collision preflight remains mandatory even though Phase 1’s meaningful identifier uniques are predominantly controlled codes or ASCII.

No unrelated schema redesign belongs in this correction.

I. Cross-Package Invariants
All four packages must preserve:

Server-derived authority: a client-supplied Tenant/Shop ID never establishes authorization.

Hostname is context, not authority: active verified hostname selection must still be followed by identity, membership, status, entitlement, and permission checks. 

Tenant isolation: every target relationship is checked within the resolved Tenant/Shop scope.

Shield owns identity: passwords, authenticators, sessions, recovery identities, and platform groups remain Shield-managed.

Platform Admin is not a universal Tenant member.

No public registration.

Mandatory Admin MFA and recent step-up for sensitive Platform actions.

One Shop per Tenant in Phase 1, service-enforced without permanently redesigning the one-to-many schema.

Owner and Staff are distinct: Staff authority is deny-by-default and explicit.

Immutable entitlement evidence: changes append new periods/events; no mutable current-status field.

Half-open entitlement evaluation: [starts_at, ends_at).

No automatic Staff deactivation on downgrade.

Atomic sensitive mutations: business change and Audit commit or roll back together.

Append-oriented Audit: no normal update/delete of committed Audit events.

Secret minimization: no password, hash, MFA secret/code, recovery secret, raw domain token, TLS key, session token, or cookie in Audit/logs.

Safe asset storage: decoded allow-listed images, opaque private paths, non-executable delivery.

utf8mb4_unicode_ci for ordinary text; ascii_bin for approved normalized identifiers.

Bootstrap/Sass/static CSS/self-hosted Vue/native Fetch.

No Tailwind, router, Pinia, Axios, TypeScript, Vite, Webpack, or other JS bundler.

No automated application tests.

No Phase 2 scope: no sync_changes, causal correction layer, business operations, devices, IndexedDB business state, or synchronization.

Mobile parity is delivered within each package, not deferred as final styling.

Thin controllers and focused typed services: explicit readable transactions, one statement per line, meaningful exceptions, no compressed business logic.

No direct database operations for normal lifecycle management.

No false completion claim when runtime execution is unavailable.

J. Final Phase 1 Definition of Done
Phase 1 may be approved only when all items are true:

Runtime and database
 Clean locked Composer/npm installation succeeds.

 Required self-hosted browser assets exist.

 Application boots with documented configuration.

 Shield migrations precede DawaTrack migrations.

 Phase 1 migration and seeder succeed on fresh MySQL 8.4.

 rollback/reapply succeeds.

 raw SQL, migration behavior, schema documentation, and register agree.

 all ordinary Phase 1 text uses utf8mb4_unicode_ci.

 intentional identifier columns remain ascii_bin.

 initial active Sync Generation exists.

Authentication and security
 Public registration is unavailable.

 Owner and Staff can login, recover, expire, and logout correctly.

 deactivated/reset users cannot retain usable sessions.

 Platform Admin group and least-privilege permissions work.

 mandatory MFA enrollment/challenge/recovery works.

 15-minute step-up works.

 Shop/Admin route boundaries hold.

 CSRF protects every session write.

 rate limits operate on sensitive endpoints.

 HTML and API failures use appropriate redirects/status envelopes.

 no authentication secret appears in Audit/logs.

Tenancy and onboarding
 context is server-derived.

 hostname never grants authority.

 active membership is required.

 two-Tenant ID/Host tampering reveals no foreign data.

 onboarding atomically creates the complete required graph.

 forced failures leave no orphan identity or partial Tenant.

 exactly one Phase 1 Shop is created.

 Owner creation and onboarding are audited.

Staff, permissions, and Shop
 Owner can list/create/activate/deactivate/recover/reset Staff.

 Staff caps are concurrency-safe.

 Trial/Growth cap is 5; Starter cap is 2, excluding Owner.

 Staff sessions/tokens are invalidated when required.

 Owner/Admin cannot be targeted through Staff workflow.

 current effective grants render accurately.

 grant/revoke/history/reasons work.

 Staff remains fail-closed.

 ordinary Staff can load the Shop shell.

 FULL/READ_ONLY/BLOCKED/CLOSED states are clear and enforced.

 Shop settings are normalized, validated, and audited.

Branding, PWA, and domains
 every branding field validates, versions, audits, and propagates.

 logo, favicon, login background, 192 icon, and 512 icon work.

 malicious/invalid uploads are rejected safely.

 asset replacement handles DB/filesystem failures consistently.

 real login/recovery/MFA screens are branded.

 dynamic manifest fields/icons/fallbacks/cache versioning work.

 custom-domain request, challenge, verification, TLS, activation, primary switch, deactivation, and fallback require no DB edits.

 custom hostname changes never alter authority.

 every sensitive domain transition is audited.

Subscription and lifecycle
 Trial/Starter/Growth lifecycle is operational.

 paid activation, renewal, upgrade, downgrade, extension, and expiry evidence work.

 periods/events are immutable.

 overlaps are prevented under concurrency.

 half-open evaluation is correct.

 current/history/future periods are distinguished.

 downgrade above cap is refused without Staff deactivation.

 Tenant suspend/reactivate/close works.

 Shop deactivate/reactivate works.

 access consequences are immediate and consistent.

Audit, documentation, and UX
 every Phase 1 sensitive mutation writes immediate atomic Audit.

 Audit contains correct actor/authority/Tenant/Shop/target/reason/correlation.

 centralized safeguards reject secret metadata.

 append-only operational privileges are verified where possible.

 documentation matches actual behavior.

 no dead button, placeholder, fake value, or disconnected route remains.

 all workflows pass at 320/360/390px, tablet, and desktop.

 keyboard-only and 200% zoom retain all functionality.

 only clearly documented environment-only verification remains.

K. Recommended Implementation Sequence Inside Each Package
WP1 internal order
Record clean Git/runtime state and determine durable-database status.

Correct dependency hygiene and install from lock files.

regenerate self-hosted frontend assets.

establish disposable MySQL verification.

apply the selected collation strategy.

verify migration/seed/schema baseline.

configure DawaTrack Shield groups and disable registration.

establish API/browser authentication response contracts.

enable CSRF and session/cookie hardening.

implement reusable rate-limit policies.

implement supported Admin MFA and recent-step-up service.

implement branded Shop/Admin auth/recovery/MFA views.

add required authentication/MFA Audit.

complete responsive behavior.

run WP1 manual verification.

update WP1-specific docs and stop.

WP2 internal order
Define normalized onboarding and membership invariants.

refactor Owner identity creation into the onboarding service transaction.

verify locks, Sync Generation, duplicate handling, and Audit.

complete Staff lifecycle transaction/locking.

implement total session/token invalidation.

implement credential recovery/reset policy.

complete permission query/grant/revoke/history APIs.

make Shop bootstrap role/capability aware.

build Staff/reset/permission dialogs and remove prompts.

harden Shop settings and Audit deltas.

complete mobile behavior.

run rollback, isolation, concurrency, and responsive manual checks.

update WP2-specific docs and stop.

WP3 internal order
finalize branding validation and presentation-version contract.

define per-asset validation and processing rules.

refactor asset storage into a failure-safe lifecycle.

fix API/client upload integration.

implement asset previews and responsive controls.

propagate logo/favicon/background/title/colors.

complete dynamic manifest icons/fallback/version/cache behavior.

define hostname normalization and challenge protocol.

refactor DNS command onto the owning verification service.

implement TLS readiness and domain transition services.

add Owner/Admin domain routes and UI.

complete Audit for every transition.

run malicious-upload, DNS, lifecycle, and mobile checks.

update WP3-specific docs and stop.

WP4 internal order
define immutable subscription command/state-transition contract.

implement period evaluator and serialized Subscription Service.

implement downgrade cap refusal.

implement current/history/future query model.

add Admin subscription APIs/UI and Owner status UI.

implement Tenant lifecycle.

implement Shop lifecycle.

close effective access composition and response semantics.

inventory every Phase 1 sensitive action.

implement centralized Audit metadata safeguards and missing Audit calls.

apply/verify append-only operational privileges.

run the entire fresh-database and end-to-end acceptance matrix.

correct all documentation against observed behavior.

rerun relevant checks after documentation/build outputs.

prepare final Phase 1 approval evidence and stop.

L. Risks / Decisions Requiring My Approval
Only the following require explicit project-owner approval before or during implementation:

1. Durable migration history
Decision: Does any retained shared/staging/pilot database already have the current Phase 1 migration recorded?

Recommended default: treat Phase 1 as pre-production and edit the existing SQL source if inspection confirms no durable application.

Alternative: approve one corrective migration if durable history exists.

This cannot be inferred conclusively from Git alone.

2. Mandatory MFA factor supported by the pinned Shield version
The lock pins Shield v1.4.1, while the current config exposes Shield’s email 2FA views but enables no login action. 

Recommended decision: use Shield’s native supported email second-factor action for Phase 1 if that is the strongest enrollment/challenge flow available in the pinned package, with verified Admin email, recent-MFA step-up, and secured recovery.

If authenticator-app TOTP is mandatory, that is a distinct dependency/design decision and must be approved rather than simulated through an arbitrary session flag.

3. Staff credential recovery policy
Recommended decision: Owners initiate a Shield recovery/forced-reset process; they do not choose or retain a Staff member’s long-lived password.

If temporary passwords are commercially required, approve a one-time forced-reset design explicitly.

4. IDN custom-domain policy
Recommended decision: accept internationalized hostnames only after deterministic server-side conversion to canonical lowercase ASCII punycode; store and compare the ASCII form.

The lower-risk alternative is to reject non-ASCII hostnames in Phase 1 with a clear validation message.

5. Asset image-processing capability
The approved roadmap calls for safe optimization, but available PHP extensions must be checked.

Recommended decision: require a supported decoded re-encoding path using the available GD/Imagick handler. If neither exists in the target environment, reject deployment readiness rather than pretending byte validation is optimization.

6. Tenant closure reversibility
The schema describes CLOSED as retained/no normal access, while no explicit reopening rule is stated.

Recommended decision: make close terminal in Phase 1; reactivation applies only to SUSPENDED. Any reopen workflow would require separate explicit approval and stronger Audit/recovery rules.

7. Domain activation versus primary selection
The current backend combines activation and primary switching.

Recommended decision: treat them as distinct operations:

activation makes an eligible custom domain routable;

primary selection changes the canonical domain;

the platform subdomain remains active as fallback.

This provides safer rollback and clearer Audit evidence.