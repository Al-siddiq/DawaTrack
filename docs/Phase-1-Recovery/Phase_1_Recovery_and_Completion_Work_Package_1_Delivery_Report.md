Phase 1 Recovery & Completion — Work Package 1 Delivery Report
A. Work Package Delivered
WP1 — Runtime, Database & Security Foundation

Only WP1 was implemented. No WP2 onboarding transaction repair, Staff lifecycle completion, permission UI, Shop settings hardening, WP3 domain/asset work, WP4 subscription lifecycle, or Phase 2 work was started.

B. Implementation Summary
WP1 now provides:

disposable Composer/npm dependency handling with committed self-hosted browser assets;

utf8mb4_unicode_ci throughout the pre-approval Phase 1 SQL and supporting configuration/documentation;

intentional ascii_bin handling retained for UUIDs, hostnames, and other normalized identifiers;

strict MySQL connection behavior;

explicit DawaTrack platform_admin group and least-privilege permissions;

disabled public registration;

a unified 12-character Shield password minimum;

Shield’s native Email2FA login action;

server-established, user-and-session-bound recent MFA with a 15-minute lifetime;

Admin MFA step-up through complete reauthentication;

CSRF enforcement for session-authenticated requests;

refreshed CSRF token propagation through the native Fetch client;

reusable rate limiting for login, recovery, MFA, and Platform Admin operations;

two-hour idle and eight-hour absolute session boundaries;

safe logout and recent-MFA clearing;

distinct browser redirects versus API JSON authentication responses;

permission-scoped Platform Admin routes;

branded real login and magic-link recovery views;

branded Shield MFA through the shared custom authentication layout;

responsive/accessibility styling for authentication, recovery, denial, step-up, and throttling states;

WP1 security Audit events for MFA establishment and clearing;

updated runtime, collation, authentication, MFA, session, CSRF, rate-limit, and manual-verification documentation.

C. Existing Work Preserved
The implementation retained:

the existing CodeIgniter 4 modular-monolith structure;

Shield as the sole identity/session/recovery authority;

the existing hostname-based brand resolver;

existing Tenant/Shop authority behavior;

Vue 3 with ordinary setup();

native Fetch;

Bootstrap 5/Sass static compilation;

the current migration’s raw-SQL execution design;

the existing Phase 1 Tenant, Shop, branding, domain, subscription, Audit, and Sync Generation schema;

Shield starter groups, without using them as a substitute for DawaTrack Platform Admin authority;

existing WP2–WP4 implementation areas without expanding their scope.

The existing migration continues to execute its registered SQL verbatim; only that pre-approval SQL source’s ordinary-text collation was corrected. 

D. Dependency / Runtime Changes
Repository hygiene
Changed the Composer ignore rule to /vendor/, preventing it from accidentally ignoring nested public asset directories named vendor.

Added node_modules/ to .gitignore.

Confirmed that zero node_modules files are tracked. 

Locked dependency outcomes
npm ci completed successfully from package-lock.json.

The existing package versions were not upgraded.

npm run build completed successfully.

The generated Vue and Dexie browser runtimes are now committed under:

public/assets/vendor/vue/vue.global.prod.js

public/assets/vendor/dexie/dexie.min.js

Compiled Bootstrap/Sass CSS and application ES modules were regenerated.

No runtime HTML/JavaScript reference to a public production CDN was found.

Production browser runtime does not depend on node_modules.

Composer outcome
composer install --no-dev --no-interaction --prefer-dist was attempted from the existing lock file. Dependency resolution recognized the locked packages, including CodeIgniter 4.7.3 and Shield 1.4.1, but the environment proxy returned HTTP 403 for GitHub distribution and source downloads. Consequently, vendor/ could not be restored in this environment.

composer.json itself passed strict validation, and no package versions or Composer metadata were changed.

E. Database / Collation Outcome
Durable database assessment
No durable applied database was discovered:

there was no project .env;

the environment had no MySQL client or server binary;

no reachable/configured migration ledger was available;

no repository deployment evidence identified a retained shared, staging, pilot, or production database.

Accordingly, the approved pre-production correction strategy was used. No corrective migration was created.

Changes
All 12 Phase 1 table defaults in the registered raw SQL now use:

DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
The SQL still retains explicit ASCII binary definitions for UUIDs, hostnames, correlations, and similar normalized identifiers. For example, shop_domains.hostname remains CHARACTER SET ascii COLLATE ascii_bin, while the table default is now utf8mb4_unicode_ci. 

The subscription and Audit tables likewise use utf8mb4_unicode_ci while retaining ASCII UUID columns. 

Configuration and documentation were aligned, including:

application DB connection;

environment template;

Database Schema Design;

Roadmap;

configuration guide;

schema notes;

migration register.

The migration register explicitly documents why the existing source was corrected and why no historical migration was added. 

Static schema verification
Exactly 12 Phase 1 table defaults were found using utf8mb4_unicode_ci.

No remaining utf8mb4_0900_ai_ci or utf8mb4_general_ci references were found outside dependencies/lock files.

Fifteen intentional ascii_bin clauses remain.

MySQL strictOn is enabled in application configuration.

Runtime schema verification
Migration, seed, rollback, SHOW CREATE TABLE, FK/CHECK/generated-guard, UTC, and repeat-seed execution could not be performed because MySQL and Composer runtime dependencies were unavailable.

F. Shield Configuration
Registration
allowRegistration is now false.

Shield registration routes are excluded when Shield routes are registered.

A safe branded registration-disabled view exists as defense-in-depth.

Account creation remains controlled through later approved Admin/Owner workflows. 

Groups and permissions
Added the explicit DawaTrack group:

platform_admin
with only:

platform.access

platform.onboard

The existing unrelated Shield starter groups were retained but do not inherit these DawaTrack permissions. 

The Admin shell requires platform.access, while the existing onboarding endpoint requires platform.onboard. 

Password policy
Shield’s minimum was raised from 8 to 12 characters, matching the custom Phase 1 identity services. Existing Shield composition, personal-information, and dictionary validators remain active. 

Recovery
Shield magic-link recovery remains the supported recovery mechanism.

The recovery request/result views are branded and use generic, non-enumerating wording.

Recovery links/codes are not logged or stored in Audit by the added WP1 code.

Shield remains responsible for recovery token generation and validation.

Sessions
Shield session authentication remains the default authenticator.

previous session data is destroyed during scheduled session ID regeneration;

configured idle lifetime remains two hours;

WP1 adds an eight-hour absolute application lifetime;

logout destroys the session;

Admin logout, step-up restart, and absolute expiry clear recent MFA. 

G. Platform Admin MFA
Mechanism used
The locked Shield version is 1.4.1. WP1 uses its native supported:

CodeIgniter\Shield\Authentication\Actions\Email2FA
as the login action. No parallel TOTP database, MFA secret store, or alternate authentication framework was introduced. 

Because native email second-factor authentication relies on the verified account email, it has no separate TOTP-style secret enrollment step. Setup consists of:

controlled creation of the Platform Admin identity;

assignment to platform_admin;

verification/control of its email identity;

successful Shield password authentication;

completion of Shield’s emailed second-factor challenge.

Recent verification
After Shield completes its configured login action and calls the successful login redirect:

DawaTrack records the current server time;

the state is bound to the authenticated Admin user ID;

the state is held in that server session;

the validity window is 900 seconds;

no timestamp is accepted from a client. 

Admin boundary
A protected Platform Admin request requires:

authenticated Shield identity;

membership in platform_admin;

the specific configured Platform permission;

recent MFA.

Browser requests redirect to the relevant Admin login or step-up experience. API requests receive JSON 401, 403 PERMISSION_DENIED, or 403 MFA_REQUIRED. 

Expiry and step-up
When the recent-MFA period expires:

the Admin is directed to the step-up page;

the intended destination is retained only if it is a safe /platform-admin path;

the current Admin is logged out;

the session ID is regenerated with old data destroyed;

the Admin must again complete password authentication and Shield Email2FA;

successful authentication establishes a fresh 15-minute window.

Sensitive failed mutations are not automatically replayed.

Recovery/reset
Email2FA has no separate stored authenticator-app secret to reset. Recovery uses the controlled Shield email identity recovery path followed by a completely new password + email-factor sign-in. Logout, step-up restart, and absolute session expiry clear DawaTrack recent-MFA state.

Audit
Successful MFA establishment and clearing produce bounded SECURITY Audit events with:

actor user ID;

action code;

safe resource type;

reason.

The Audit payload does not contain codes, email messages, magic links, cookies, sessions, or tokens. 

If the Audit table is unavailable during initial database bootstrap, the security action is not blocked; only a safe exception class/action code warning is logged.

H. CSRF / Rate Limiting / Session Security
CSRF
The session CSRF filter is now a required global before-filter, covering normal forms, JSON, and multipart requests. 

Server-rendered auth and step-up forms use csrf_field().

The security-header filter returns the current CSRF token in the configured response header. The Fetch client:

reads token/header names from page metadata;

sends the token on non-GET/HEAD writes;

supports JSON and FormData;

adopts the refreshed response token. 

CSRF failure redirection is disabled so API requests are not converted into unrelated HTML redirects.

Rate limiting
A reusable SensitiveRateLimitFilter now supports:

recovery: 3 attempts per 15 minutes;

MFA action/step-up: 6 attempts per 15 minutes;

Platform Admin writes: 20 attempts per minute;

default login-sensitive action: 5 attempts per 5 minutes.

Keys are hashed from the authenticated user ID or client IP plus route path. APIs receive JSON 429 RATE_LIMITED and Retry-After; browser flows receive a branded 429 page. 

The policy is attached to:

/login;

/login/*;

/auth/a/*;

/platform-admin/mfa/*;

/api/v1/platform/*. 

The filter is reusable by WP2/WP3 routes without introducing another throttling system.

Session security
Secure cookies remain environment-aware for production.

Cookies remain HTTP-only and SameSite Strict.

Scheduled regeneration now destroys the previous session record.

Absolute lifetime is eight hours.

Admin recent MFA is 15 minutes.

logout destroys the session;

auth/Admin responses receive Cache-Control: no-store, private.

I. Authentication and Branded UI
Created real branded Shield authentication views for:

credential login;

magic-link recovery request;

generic recovery result;

registration-disabled state;

rate-limit response;

Admin denied state;

Admin MFA step-up.

Shield’s native Email2FA views use the configured custom authentication layout, so the native challenge remains Shield-owned while inheriting DawaTrack/Shop presentation.

The authentication layout:

safely resolves presentation by hostname;

uses resolved title, theme color, logo, favicon, and colors;

falls back to DawaTrack presentation;

escapes all branding values;

never treats hostname resolution as Tenant authority;

renders accessible status/error regions. 

The login form provides:

associated email/password labels;

correct autocomplete values;

safe recovery navigation;

CSRF token;

touch-sized submission. 

WP3’s richer login-background and PWA-asset propagation was intentionally not started.

J. API / Browser Error Semantics
Browser behavior
Unauthenticated page requests:

retain only a relative same-origin path;

reject protocol-relative destinations;

redirect to branded /auth.

Platform Admin pages distinguish:

unauthenticated identity;

authenticated non-Admin;

missing Platform permission;

stale MFA.

API behavior
Unauthenticated API requests now return JSON:

401 AUTHENTICATION_REQUIRED
instead of redirecting Fetch to an HTML login page. 

Platform Admin API outcomes include:

401 AUTHENTICATION_REQUIRED;

403 PERMISSION_DENIED;

403 MFA_REQUIRED.

A typed ApiException foundation was added for stable code/status/details mapping. Current read-only, blocked, and closed mutation decisions now retain their access-mode codes rather than being collapsed into 422 VALIDATION_FAILED.

The Fetch client deliberately handles:

401 by returning to branded expired-session authentication;

MFA_REQUIRED by opening Admin step-up;

structured validation/conflict/permission responses as ApiError;

timeout and network failures separately. 

WP2/WP4 remain responsible for applying the conflict/access semantics to their new lifecycle services.

K. Audit / Logging Security
WP1 Audit covers:

successful recent-MFA establishment;

MFA clearing on step-up restart;

Admin logout;

absolute session expiry.

No newly added logging context contains:

passwords;

hashes;

MFA codes;

magic links;

recovery material;

cookies;

session identifiers;

raw authentication tokens.

Ordinary failed-login volume remains Shield/operational security logging rather than business Audit noise.

WP4’s centralized Audit metadata rejection policy remains intentionally out of scope.

L. Responsive / Accessibility Implementation
Authentication styling now provides:

a viewport-bounded authentication shell;

stacked forms;

touch-sized controls;

responsive padding;

visible focus inherited from the existing application foundation;

associated labels;

one-time-code-friendly styling;

scalable logo constraints;

alert/status semantics;

no wide-table or desktop-only dependency. 

The CSS was compiled successfully into the tracked static output.

Actual 320/360/390/tablet/desktop, keyboard-only, and 200% zoom browser execution remains unverified because the application could not bootstrap without Composer dependencies. The detailed owner-environment procedure is recorded in the manual checklist. 

No screenshot was produced because the application was not runnable in this environment.

M. Files Created
Backend/security
app/Controllers/Web/AdminAuthController.php

app/Controllers/Web/SecuritySessionController.php

app/Exceptions/ApiException.php

app/Filters/PlatformAdminIdentityFilter.php

app/Filters/SensitiveRateLimitFilter.php

app/Filters/SessionSecurityFilter.php

app/Security/PlatformMfaService.php

Authentication views
app/Views/auth/admin_denied.php

app/Views/auth/admin_step_up.php

app/Views/auth/layout.php

app/Views/auth/login.php

app/Views/auth/magic_link_form.php

app/Views/auth/magic_link_message.php

app/Views/auth/rate_limited.php

app/Views/auth/registration_disabled.php

Self-hosted runtime assets
public/assets/vendor/dexie/dexie.min.js

public/assets/vendor/vue/vue.global.prod.js

N. Files Modified
Application/configuration
.gitignore

README.md

app/Common.php

app/Config/Auth.php

app/Config/AuthGroups.php

app/Config/Database.php

app/Config/Filters.php

app/Config/Routes.php

app/Config/Security.php

app/Config/Services.php

app/Config/Session.php

app/Controllers/Api/ShopController.php

app/Filters/AuthenticatedFilter.php

app/Filters/PlatformAdminFilter.php

app/Filters/SecurityHeadersFilter.php

env

Frontend/generated assets
resources/css/app.scss

resources/js/api/http-client.js

public/assets/css/app.css

public/assets/js/api/http-client.js

Documentation/schema source
docs/DawaTrack_Database_Schema_Design.md

docs/DawaTrack_Strategic_Phased_Implementation_Roadmap.md

docs/configuration.md

docs/database/migration-register.md

docs/database/schema-notes.md

docs/database/sql/phase-01/001_phase_01_foundation.sql

docs/manual-verification-phase-01.md

docs/operations/phase-01-bootstrap.md

docs/security/foundation.md

O. Database / Migration / Raw SQL Changes
No new migration was created.

The existing PHP migration structure was not changed.

The registered Phase 1 raw SQL was corrected in place from utf8mb4_0900_ai_ci to utf8mb4_unicode_ci.

Explicit ASCII binary columns remain intact.

DB connection strict mode was enabled.

The sample test collation was changed from utf8mb4_general_ci to utf8mb4_unicode_ci.

No table, column, FK, check, generated guard, index, or seed definition was redesigned.

P. Documentation Updated
Documentation now records:

current Phase 1 recovery status rather than “Phase 0 only”;

correct env template setup;

Composer/npm dependency hygiene;

self-hosted browser assets;

utf8mb4_unicode_ci;

no corrective migration because no durable database was found;

platform_admin bootstrap;

Shield Email2FA behavior;

15-minute recent MFA;

email-factor recovery implications;

session idle/absolute lifetimes;

CSRF behavior;

throttling behavior;

API/browser authentication semantics;

Audit/log secret exclusions;

exact WP1 owner-environment verification steps. 

No documentation claims that all of Phase 1 is complete.

Q. Verification Actually Executed
Static verification
✅ find app -type f -name '*.php' -print0 | xargs -0 -P 8 -n 1 php -l

All 107 application PHP files passed syntax validation.

✅ find resources/js public/assets/js -type f -name '*.js' -print0 | xargs -0 -P 4 -n 1 node --check

✅ composer validate --strict --no-check-publish

✅ git diff --check

✅ rg -n 'utf8mb4_0900_ai_ci|utf8mb4_general_ci' . --glob '!node_modules/**' --glob '!vendor/**' --glob '!*.lock'

No conflicting collation references remained.

✅ rg -o 'utf8mb4_unicode_ci' docs/database/sql/phase-01/001_phase_01_foundation.sql | wc -l

Confirmed all 12 Phase 1 table defaults.

✅ rg -o 'COLLATE ascii_bin' docs/database/sql/phase-01/001_phase_01_foundation.sql | wc -l

Confirmed 15 intentional ASCII binary clauses.

✅ git ls-files node_modules | wc -l

Confirmed zero tracked dependency-tree files.

✅ rg -l '<script[^>]+https?://|<link[^>]+https?://' app/Views resources public --glob '!welcome_message.php' --glob '!error_exception.php'

Confirmed no production runtime CDN references in current application surfaces.

Dependency/build verification
✅ rm -rf node_modules && npm ci

✅ npm run build

Completed with upstream Bootstrap/Sass deprecation warnings only.

✅ test -s public/assets/vendor/vue/vue.global.prod.js

✅ test -s public/assets/vendor/dexie/dexie.min.js

⚠️ COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist

Environment limitation: GitHub distribution/source downloads were rejected by the configured network proxy with HTTP 403.

Runtime verification
⚠️ php spark routes

Not executable because Composer dependencies could not be downloaded.

⚠️ php spark serve

Not executable for the same reason.

⚠️ Authentication, recovery, MFA, CSRF, throttle, logout, redirect, and API HTTP scenarios could not be executed without the framework runtime.

Database verification
⚠️ mysql --version

MySQL client/server is unavailable.

⚠️ Shield migration, Phase 1 migration, seeder, rollback/reapply, repeat-seed, and SHOW CREATE TABLE verification could not be executed.

Browser/manual verification
⚠️ 320/360/390/tablet/desktop, keyboard-only, 200% zoom, and screenshot verification could not be performed because the PHP application was not runnable.

R. Manual Verification Checklist and Results
Scenario	Expected Result	Actual Result
Clean npm installation	Locked frontend packages install	Passed
Static frontend build	CSS, app modules, Vue, Dexie generated	Passed
Production dependency hygiene	No tracked node_modules; no CDN dependency	Passed statically
Clean Composer install	Locked CI4/Shield dependencies install	Environment blocked: GitHub HTTP 403
Fresh MySQL 8.4 migration	Shield + Phase 1 + seeder succeed	Not executable: MySQL unavailable
Schema collation	12 ordinary table defaults use utf8mb4_unicode_ci	Passed statically
Identifier collation	Explicit ascii_bin retained	Passed statically
Registration unavailable	/register GET/POST unavailable	Implemented; runtime pending
Password policy	Shield minimum 12 plus validators	Passed statically
Form/JSON/multipart CSRF	Invalid rejected, valid accepted	Implemented; runtime pending
API authentication	JSON 401, no HTML login redirect	Passed statically; runtime pending
Browser authentication	Safe branded redirect	Passed statically; runtime pending
Open redirect	External/protocol-relative destination refused	Passed static code review; runtime pending
Shop login/logout/recovery	Shield flows use branded layout	Implemented; runtime/email pending
Platform Admin authority	Group + permission required	Passed statically; runtime pending
Native MFA	Shield Email2FA required at login	Passed configuration inspection; runtime/email pending
Recent MFA	Server-bound 15-minute state	Passed static code review; runtime pending
MFA expiry/reverification	Full password + Email2FA restart	Passed static code review; runtime pending
Owner/Staff denied from Admin	Browser denial / API 403	Passed statically; runtime pending
Login/recovery/MFA throttle	429 and Retry-After	Passed static code review; runtime pending
Security Audit	Safe establish/clear events	Passed static code review; DB runtime pending
Secret leakage	No secret payload introduced	Passed static source inspection
Responsive widths	No lost mobile authentication action	Implemented; browser pending
Keyboard/zoom	Complete keyboard and 200% operation	Implemented structurally; browser pending
The owner-environment checklist is preserved in docs/manual-verification-phase-01.md. 

S. Deviations / Blockers
Environment-only blockers
Composer network access: the environment proxy rejected GitHub downloads, preventing restoration of vendor/.

MySQL unavailable: no MySQL 8.4 client/server was available.

Browser runtime unavailable: without vendor/, the application could not be started for screenshot, viewport, keyboard, zoom, email, or HTTP verification.

Shield runtime compatibility verification remains required: the implementation uses the locked/configured native Shield 1.4.1 Email2FA API, but actual route/controller execution could not be exercised without the package installation.

No durable database was found, and no retained data was modified.

No unresolved WP1 code-scope design blocker was identified, but the listed owner-environment runtime checks remain mandatory before WP1 operational approval.

T. WP1 Acceptance Assessment
Implemented
All WP1 acceptance requirements have corresponding implementation:

clean frontend dependency reproduction;

committed self-hosted runtime assets;

pre-approval utf8mb4_unicode_ci schema source;

retained ascii_bin;

disabled registration;

coherent Shield password policy;

enforced global CSRF;

browser/API authentication separation;

stable authorization/MFA/rate-limit response codes;

reusable throttling;

explicit platform_admin;

native Shield Email2FA;

15-minute recent-MFA gate;

MFA clearing and reauthentication step-up;

separate Shop/Admin authority;

branded login/recovery/MFA architecture;

safe WP1 security Audit;

responsive/accessibility implementation;

accurate WP1 documentation.

Still requiring owner-environment verification
WP1 should be treated as implementation complete but runtime acceptance pending for:

Composer install;

Shield route loading;

actual Email2FA email delivery/challenge;

recent-MFA redirect lifecycle;

CSRF rejection/refresh for form, JSON, and multipart;

rate-limit behavior;

session expiry and inactive-user behavior;

MySQL migration/seed/rollback/schema inspection;

Audit persistence;

all browser/mobile/accessibility checks.

WP2 must not begin until those WP1 runtime checks are reviewed or explicitly accepted as owner-environment verification.

U. Git Commit / Change Summary
Commit: 37eb89e feat: deliver Phase 1 security foundation

Working tree: clean

Pull request metadata created:

Title: Complete Phase 1 runtime and security foundation

Scope: 46 files changed, 749 insertions, 54 deletions.

No WP2, WP3, WP4, or Phase 2 implementation was included.