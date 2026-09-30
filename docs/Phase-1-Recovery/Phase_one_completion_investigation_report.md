# DawaTrack Phase 1 Completion Investigation Report

## A. Executive Assessment

**Assessment: MATERIALLY INCOMPLETE.**

The repository contains a meaningful Phase 1 foundation:

- The principal Phase 1 tables and constraints are represented in raw MySQL SQL.
- Tenant and Shop context is generally derived from the authenticated user and resolved hostname.
- Owner-oriented services exist for Staff, permissions, Shop settings, branding, assets, custom-domain requests, and subscription display.
- An access-mode evaluator and an append-oriented Audit service exist.
- Bootstrap/Sass, statically copied Vue modules, native Fetch, and the approved no-bundler architecture are present.

However, Phase 1 cannot honestly be approved because several acceptance-critical paths are absent, disconnected, or unsafe:

1. **Platform Admin access is not operable as configured.** The **`platform_admin`** Shield group is absent, and the recent-MFA session timestamp is checked but is never established by any enrollment or challenge flow.
2. **Public self-registration remains enabled**, contrary to the security contract.
3. **Actual Shield login and recovery pages are vendor-generic rather than Shop-branded.**
4. **Onboarding is not atomic:** the Owner Shield identity is created before the business transaction begins and can remain orphaned after onboarding failure.
5. **Staff credential reset and effective permission state have backend routes but no usable UI integration.**
6. **The Shop shell is effectively Owner-only:** its initial parallel load calls Owner-only Staff and subscription endpoints, so ordinary Staff cannot reliably use it.
7. **Custom-domain TLS readiness, activation, and primary switching have no HTTP/Admin UI workflow.**
8. **Login backgrounds and PWA icons are uploadable metadata but are not propagated to their runtime destinations.**
9. **Paid subscription creation/change/downgrade workflows are missing**, including rejection of a downgrade that exceeds the target Staff cap.
10. **Audit coverage does not encompass all required sensitive actions**, and some required actions do not exist at all.
11. **CSRF is not globally enabled for the custom session-authenticated mutation APIs.**
12. **The approved collation is inconsistent:** runtime database configuration already says **`utf8mb4_unicode_ci`**, while the Phase 1 SQL and authoritative documentation still say **`utf8mb4_0900_ai_ci`**.
13. **Runtime verification is blocked by the absence of `vendor/` and usable database/environment configuration.**

The roadmap requires complete mobile- and desktop-usable brand/domain workflows, working Shop/Admin boundaries and MFA, matching migration/SQL documentation, and atomic Audit for every sensitive mutation. Those are explicit Definition of Done conditions, not optional closure tasks. svg

---

## B. Repository / Commit State

### Current branch and history

The checked-out branch is **`work`**, at:

- **`569bd16 Shield Set up and NPM Build`**
- preceded by **`961a68e CI4 Installation`**.

The four supplied historical commit identifiers are **not present in this clone’s object database**:

- **`9bb999e`**
- **`2f49a1f`**
- **`cd744f0`**
- **`e408e60`**

Consequently, their reported Phase 0/Phase 1 changes cannot be independently compared commit-by-commit here. The Phase 1 implementation visible in the repository was introduced as part of the much larger **`569bd16`** commit.

### Working tree

The working tree was already dirty before investigation, exclusively under tracked/untracked **`node_modules`** content: platform-specific binaries, shims, nested dependency folders, and package-lock metadata. I did not modify or normalize those files.

No application, documentation, migration, SQL, or generated public asset was changed during this investigation. In accordance with the investigation-only instruction, **no commit and no pull request were created**.

### Repository hygiene concern

**`node_modules`** is tracked in this repository. That is the source of the platform-specific dirty state and makes dependency state less reproducible. This is not itself the principal Phase 1 functional blocker, but it increases build and review risk.

---

## C. Phase 1 Requirement Matrix

| **RequirementStatusEvidenceRemaining Work** |                            |                                                                                                                                                              |                                                                                                                                                |
| ------------------------------------------- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Shield username/password sessions           | PARTIAL                    | Shield routes are registered, but runtime dependencies are absent; registration is enabled and the actual login uses Shield’s generic vendor view. svgsvgsvg | Install dependencies; disable public registration; brand real login/recovery views; execute login/logout/recovery/session-expiry verification. |
| Password recovery/reset                     | PARTIAL                    | Shield magic-link recovery is enabled; Staff password replacement exists as a custom backend operation. svgsvg                                               | Verify email delivery/recovery behavior; add Owner UI for Staff recovery; define forced-change/session invalidation behavior.                  |
| Shop/Admin route separation                 | PARTIAL                    | **`/platform-admin`** and onboarding API use **`platformAdmin`**; Shop routes use **`authenticated`**. svgsvg                                                | Make platform group and MFA functional; return API-appropriate 401/403 responses; verify distinct redirects/sessions.                          |
| Mandatory Platform Admin MFA                | MISSING                    | The filter only reads a hand-written session timestamp; no code writes it. The configured Shield login action is **`null`**. svgsvg                          | Implement supported enrollment, challenge, recent-MFA establishment, step-up/expiry, and recovery.                                             |
| Tenant/Shop onboarding                      | PARTIAL                    | Business rows are created together, but Owner identity is created first, outside that transaction. svgsvg                                                    | Make identity and all onboarding records atomic; validate uniqueness/useful failures; add complete Audit evidence.                             |
| Exactly one Phase 1 Shop                    | PARTIAL                    | Service creates one Shop and serializes onboarding with a global DB lock; no invariant check protects later data. svgsvg                                     | Use the approved service/config invariant and integrity checks; avoid relying only on one entry point.                                         |
| Membership and server-derived context       | COMPLETE statically        | Context resolves active verified hostname plus active membership; no tenant ID is accepted from the client. svg                                              | Runtime IDOR/Host-header verification remains required.                                                                                        |
| Staff lifecycle                             | PARTIAL                    | List/create/activate/deactivate/reset services and routes exist. UI lacks reset, and Staff listing includes the Owner. svgsvgsvg                             | Add credential-recovery UI, safer invalidation, state-specific validation, and manual verification.                                            |
| Granular permissions                        | PARTIAL                    | Grant/revoke and permission-state backend exist; current grants are not loaded or reflected by the UI. svg                                                   | Wire retrieval and checked states; searchable/grouped mobile UI; loading/error/reason behavior.                                                |
| Deny by default                             | PARTIAL                    | **`PermissionService::allows()`** denies absent/unknown grants, but normal request endpoints do not use permission checks for Staff functionality. svg       | Apply permission policies to every relevant endpoint as operational modules arrive; make the Phase 1 Staff shell usable.                       |
| Staff caps                                  | PARTIAL                    | Seeder snapshots 5/2/5, and create/reactivate enforces the current snapshot. svgsvg                                                                          | Implement subscription changes and reject over-cap downgrades without auto-deactivation.                                                       |
| Shop settings                               | PARTIAL                    | Approved mutable fields are allow-listed and audited. Timezone and phone validation are weak. svg                                                            | Validate IANA timezone/phone/lengths; improve field errors and runtime verification.                                                           |
| Branding profile                            | PARTIAL                    | All requested profile fields, color format, version increment, and Audit exist. svg                                                                          | Enforce lengths/nonempty fields/accessibility constraints and verify runtime propagation.                                                      |
| Branding assets                             | PARTIAL                    | Five types are accepted; decoded MIME, size, exact PWA dimensions, opaque private storage, replacement, and Audit exist. svg                                 | Define dimensions for logo/favicon/background; handle orphan files on DB failure; add safe optimization/scanning policy; complete propagation. |
| Branded login/title/favicon/logo            | PARTIAL                    | Shell propagates title, favicon, logo, and colors. The real Shield login view is not this shell. svgsvg                                                      | Replace/override actual Shield auth views with Shop-resolved presentation.                                                                     |
| Login background                            | MISSING at runtime         | It can be uploaded and resolved as an asset but is never referenced in the auth view/layout/CSS. svgsvg                                                      | Render it safely with readable overlay/fallback across mobile sizes.                                                                           |
| Dynamic PWA presentation                    | PARTIAL                    | Manifest dynamically supplies name, short name, theme, and background colors. It omits icons and version/cache handling. svg                                 | Add active 192/512 icon entries, fallback icons, description/version/cache strategy.                                                           |
| Custom-domain request/token                 | PARTIAL                    | Request normalizes hostname, returns a raw token once, stores SHA-256, and audits. svg                                                                       | Harden hostname/IDN/reserved-domain validation; show exact record name; preserve/reissue instructions appropriately.                           |
| Real DNS verification                       | PARTIAL                    | A Spark command checks TXT records and writes Audit. svg                                                                                                     | Correct operational challenge-record convention, failure/retry state, actor attribution, and runtime verification.                             |
| TLS readiness                               | MISSING                    | A schema state exists, but no service/controller/UI updates it. svg                                                                                          | Add Admin-authorized status workflow and Audit.                                                                                                |
| Domain activation                           | PARTIAL backend only       | **`activateVerified()`** exists and requires verified + TLS-active, but no route or UI calls it. svg                                                         | Expose through protected Admin HTTP/UI; validate scoped ID; audit and verify.                                                                  |
| Primary-domain switching/fallback           | MISSING as usable workflow | Activation implicitly changes primary; there is no explicit switch UI/API or safe deactivation/fallback operation.                                           | Implement Admin workflow and ensure the platform subdomain remains active as fallback.                                                         |
| Subscription plans/immutable periods        | PARTIAL                    | Schema is append-oriented, contains no mutable current-status field, and evaluation uses half-open bounds. svgsvg                                            | Add paid entitlement creation/change services/UI, overlap rules, Trial bounds, downgrade policy, events, and Audit.                            |
| FULL/READ_ONLY/BLOCKED/CLOSED               | PARTIAL                    | The access evaluator returns all four modes and mutations call **`mutableContext()`**. svgsvg                                                                | Use proper HTTP/error codes and distinct UI states; verify all mutations and historical reads.                                                 |
| Minimum authoritative Audit                 | PARTIAL                    | Append service exists and participating services generally call it inside DB transactions. svg                                                               | Complete action coverage, transaction atomicity, redaction enforcement, immutable DB grants, and before/after evidence.                        |
| Sync Generation                             | COMPLETE statically        | Seeder creates one active generation, guarded by a generated unique column in SQL. svgsvg                                                                    | Execute and verify in MySQL.                                                                                                                   |
| Responsive/mobile parity                    | NEEDS VERIFICATION         | Forms/cards use fluid CSS and mobile rules, but required workflows are absent and no browser execution was possible. svg                                     | Complete missing workflows, then verify at 320/360/390/tablet/desktop and 200% zoom.                                                           |
| Migration/raw SQL/docs parity               | PARTIAL                    | The migration executes the registered SQL verbatim, but collation documentation/SQL conflicts with config and the new decision. svgsvg                       | Standardize collation and execute migration/down/up against MySQL 8.4.                                                                         |
| Manual verification readiness               | MISSING                    | The checklist claims workflows that are not implemented and dependencies are unavailable. svg                                                                | Correct checklist/docs and perform evidence-backed manual verification.                                                                        |

---

## D. Authentication Investigation

### Shop authentication

**Login and logout — PARTIAL**

Shield’s route registration exists, and session authentication is the configured default. The login redirect is generic **`/`**, and logout redirects to **`login`**. svgsvg

Problems:

- The actual **`/login`** view is Shield’s vendor view, not the Shop-branded **`/auth`** landing page. svgsvg
- **`/auth`** merely links to **`/login`**; it is not the actual credential form.
- **`allowRegistration`** is **`true`**, directly contradicting the “no public self-registration” requirement. svg
- The configured minimum password length is 8, while custom onboarding/Staff services independently require 12. svg This is an inconsistent credential policy.
- Login redirect always points to **`/`**; no role-aware redirect sends a Platform Admin to the Admin area.
- Runtime login/logout behavior could not be executed without Composer dependencies.

**Recovery — PARTIAL**

Magic-link recovery is enabled for one hour. svg The investigation found no custom branded recovery view, Shop-host restoration logic, or executed mail configuration verification.

**Session expiration — PARTIAL/INCORRECT for APIs**

The session expires after 7,200 seconds and regenerates every 300 seconds. svgsvg

The custom **`AuthenticatedFilter`** returns a redirect to **`/login`**, even for API calls. svg Fetch can follow this redirect and receive HTML with HTTP 200, while the JS client’s explicit session-expired branch only handles HTTP 401. svg Therefore API session expiration is not reliably surfaced as the intended **`SESSION_EXPIRED`** state.

**Deactivated accounts — PARTIAL**

Staff deactivation sets both the membership and the global Shield user inactive. svg Context resolution also requires an active membership. This gives layered denial, but:

- The schema allows the same Shield identity to hold memberships in different Tenants, despite documentation saying Phase 1 transactionally prevents more than one active ordinary membership per user. svg
- Globally deactivating the user from one Tenant would disable that identity everywhere.
- Existing sessions and token identities beyond remember tokens are not explicitly invalidated during deactivation.
- These behaviors need a deliberate single-membership invariant and runtime verification.

### Platform Admin and MFA

**Platform group — MISSING**

**`PlatformAdminFilter`** checks **`inGroup('platform_admin')`**, but **`AuthGroups::$groups`** defines only **`superadmin`**, **`admin`**, **`developer`**, **`user`**, and **`beta`**. svgsvg As configured, the documented bootstrap command cannot validly assign the required group.

**MFA enrollment/challenge — MISSING**

The filter checks **`session('platform_mfa_verified_at')`** and accepts it for 15 minutes, but repository-wide inspection found no code that sets this session value. svg

Shield’s available email 2FA action is listed in comments/views, but the login action is configured as **`null`**. svgsvg There is no:

- enrollment flow;
- secret/factor registration;
- challenge route or view;
- successful-challenge callback;
- recent-MFA timestamp creation;
- step-up UI;
- MFA recovery flow.

The current MFA gate therefore makes Admin routes inaccessible rather than securely MFA-protected.

---

## E. Tenant / Shop / Isolation Investigation

### What is correctly designed

**`ShopContext::resolve()`**:

1. normalizes the request hostname;
2. finds only an active and verified domain;
3. derives Tenant and Shop from that domain;
4. requires an active membership for the authenticated user and selected Tenant;
5. returns server-side IDs and role. svg

The Shop APIs do not accept Tenant or Shop IDs. Staff and permission operations additionally constrain membership IDs by the context Tenant and **`STAFF`** role. svgsvg

The Platform Admin route does not create synthetic Tenant membership.

### Remaining risks

- **`ShopContext`** does not independently confirm that the resolved Tenant is active or Shop status is active; reads rely on callers to consult **`AccessGate`**. Most read endpoints resolve context but do not block CLOSED/BLOCKED access.
- Asset delivery by public hostname checks active/verified domain and asset scope, but does not check Tenant/Shop lifecycle state. svg
- Host parsing is simple port removal. It does not establish a trusted-host allow-list, canonical punycode normalization, or proxy-header policy.
- Internal numeric membership IDs are exposed in Staff listing and routes. Queries are tenant-scoped, which mitigates BOLA, but runtime tampering verification is still required.
- Domain activation’s backend method accepts a global numeric domain ID without an explicit Tenant parameter. It is intended for Admin use, but any future route must remain Admin-only and should prefer stable public IDs.
- The ordinary Staff experience is broken by Owner-only shell loads, discussed below.

**Conclusion:** server-derived authority is one of the stronger parts of the implementation, but runtime cross-Tenant/Host-header testing remains required.

---

## F. Onboarding Investigation

The Admin UI contains a Details → Review → Complete flow and posts to the protected onboarding API. svgsvg

The service attempts to create:

- Tenant;
- one Shop;
- Owner membership;
- default branding profile;
- verified/active/primary platform subdomain;
- Trial subscription;
- subscription event;
- onboarding Audit event. svg

### Critical atomicity defect

The controller creates and persists the Shield Owner **before** calling **`OnboardingService::onboard()`**. svg The service begins its transaction only afterward. svg

If hostname uniqueness, plan lookup, a late FK, Audit insertion, or another operation fails:

- the business transaction may roll back;
- the Shield user remains;
- retrying with the same email may then fail;
- the “one audited operation” claim is false.

### Additional onboarding gaps

- There is no distinct **`owner.created`** Audit evidence; only **`tenant.onboarded`**.
- No recovery/initial-password-change path is established for the Owner.
- The UI marks every generated input **`required`**, including contact phone, contact email, and address line 2, although the database/service treats them as nullable.
- Useful duplicate-email/hostname validation is not surfaced; generic database errors become a generic 500.
- The global **`GET_LOCK`** result is not checked.
- An exception before **`transComplete()`** can leave release/transaction behavior dependent on framework teardown; this needs executed rollback verification.
- The active Sync Generation is independently seeded as intended, but onboarding does not verify that bootstrap prerequisite exists.

---

## G. Staff Lifecycle Investigation

### Implemented

The Owner backend can:

- list memberships;
- create Staff;
- activate/deactivate Staff with a reason;
- reset a Staff password with a reason;
- enforce active-Staff capacity on create/reactivate;
- reject Primary Owner targets because target queries require role **`STAFF`**;
- reject a Platform Admin-associated target during password reset. svg

### Partial or incorrect

1. **Credential reset is not wired to the frontend.** Routes and controller methods exist, but **`phase-one.js`** exposes neither the reset endpoint nor permission-state GET. svgsvg
2. **No reset control appears in the Staff card.** svg
3. Reset only deletes **`auth_remember_tokens`**; it does not demonstrate invalidation of the current session, other access tokens, or forced password change.
4. Password reset directly assigns a new password chosen by the Owner. A safer recovery/temporary-credential policy needs explicit approval and implementation.
5. Listing returns every Tenant membership, including the Primary Owner, despite the screen being named Staff. This is not a data leak within the Tenant, but it is inconsistent.
6. The create form is not cleared after success.
7. Duplicate email handling is generic rather than field-specific.
8. The one-active-ordinary-membership-per-user invariant is not implemented.
9. Because user **`active`** is global, deactivation has cross-Tenant consequences if multi-membership data is ever introduced.

---

## H. Permission Investigation

The permission enum and documented catalogue agree on the 13 codes. svgsvg

The backend:

- validates codes;
- scopes target Staff to the Tenant;
- inserts grant history;
- preserves revoked rows;
- requires a revoke reason;
- audits grant/revoke;
- derives Owner full authority and Staff explicit authority. svg

### Incomplete effective-state UI

The permissions endpoint returns catalogue, active codes, and history, but the Vue application never calls it. svgsvg

All checkboxes render unchecked because they have no **`checked`**/**`v-model`** binding. Changing one therefore acts on UI state that may not reflect server state. svg

Further issues:

- permissions are not searchable or grouped as required by the roadmap;
- prompt-based revoke reasons are fragile on mobile and discard context;
- repeated grant is a silent no-op, but repeated revoke still writes a revoke Audit event even if no active grant changed;
- Audit events omit **`shop_id`**, weakening immediate authority context;
- the permission service is not used to authorize any ordinary Staff-facing Phase 1 action.

---

## I. Shop Settings Investigation

The service allows mutation only of:

- name;
- phone;
- email;
- address lines;
- locality/city;
- state/region;
- timezone. svg

Country, currency, status, IDs, and lifecycle fields are not unrestricted form inputs. The update is Owner-only through **`mutableContext()`** and is audited in the same transaction.

Remaining defects:

- timezone is only checked for nonempty text, not validated against IANA timezones;
- no phone normalization or maximum-length validation;
- the email check permits empty string and may persist **`''`** instead of normalized **`NULL`**;
- values are not explicitly trimmed;
- Audit records only field names and includes **`updated_at`** among them, rather than bounded before/after changes;
- all errors map to generic **`VALIDATION_FAILED`**/422, including permission and access-state failures.

---

## J. Branding Investigation

### Profile — PARTIAL

The Owner UI exposes all required textual and color fields. The service allow-lists them, enforces **`#RRGGBB`**, increments **`configuration_version`**, and audits. svgsvg

Gaps:

- no explicit length validation before database errors;
- nullable tagline/description inputs are incorrectly marked required by the template;
- no nonempty validation for brand/PWA names;
- no accessible color-contrast validation despite schema notes requiring it;
- preview shows only name/tagline and two colors, not the actual shell/login/PWA results;
- current page presentation is not refreshed after saving branding.

### Logo — PARTIAL to COMPLETE statically

The resolver returns versioned asset URLs, and the shell uses the active logo. svgsvg

Runtime upload/delivery has not been executed.

### Favicon — PARTIAL to COMPLETE statically

The shell creates a favicon link when an active favicon exists. svg Runtime browser behavior remains unverified.

### Login background — MISSING at runtime

The upload service accepts **`LOGIN_BACKGROUND`**, but neither the auth view, shell, nor Sass uses it. It therefore has no visible effect.

### PWA icons — MISSING at runtime

192×192 and 512×512 files are strictly dimension-validated, but the dynamic manifest returns no **`icons`** array. svgsvg

### Upload/storage concerns

Positive controls include decoded-image inspection, server-detected MIME, a 5 MiB limit, opaque names, non-public storage, active-version metadata, hashed cache version, and scoped delivery. svg

Remaining issues:

- logo/favicon/background have no type-specific dimension/aspect limits;
- there is no image re-encoding/metadata stripping or documented malware scanning boundary;
- file bytes are written before the DB transaction; a DB failure leaves an orphan file;
- superseded physical files are retained without a documented cleanup/retention procedure;
- authenticated API asset delivery uses a one-hour private cache, while hostname delivery uses immutable public caching; both need runtime verification;
- file-error UI is generic.

---

## K. Custom Domain Investigation

### Request — PARTIAL

The Owner can request a custom hostname through UI/API. A 256-bit token is returned once, while only its SHA-256 digest is stored. svg

The validator remains permissive: it relies on **`FILTER_VALIDATE_URL`**, with no punycode/label-length/reserved platform-suffix/IP-address checks.

### DNS verification — PARTIAL

A real **`dns_get_record(..., DNS_TXT)`** check exists and uses constant-time hash comparison. Successful verification is persisted and audited transactionally. svg

But:

- it queries TXT at the customer hostname itself and never communicates a conventional challenge record name;
- no mismatch/failed-attempt state or retry metadata is persisted;
- **`verified_by_admin_user_id`** is not set;
- raw tokens are not recoverable, so the Owner loses the instructions after leaving the page;
- the Owner UI only says that verification “is performed by the platform”; no refresh/poll control exists.

### TLS readiness — MISSING

No service, route, controller, command, or Admin UI changes **`certificate_status`** to **`ACTIVE`** or **`FAILED`**.

### Activation — PARTIAL backend only

**`DomainService::activateVerified()`** enforces both ownership verification and active certificate state, switches primary, and audits. svg Nothing calls this method.

### Primary switching and fallback — MISSING

There is no explicit route/UI for primary switching. Activation demotes all primaries, including the platform hostname, but leaves the platform hostname active. That is a useful fallback property, yet it is not exposed or operationally verified.

The Owner-facing screen is status-only after request. There is no Platform Admin domain-operations screen at all.

---

## L. Subscription / Access Investigation

### Implemented foundation

- Trial, Starter, and Growth plans seed with caps 5, 2, and 5. svg
- Subscription periods contain immutable snapshots and no mutable current-status field. svg
- Current entitlement uses **`starts_at <= now`** and **`ends_at > now`**, correctly implementing half-open time evaluation. svg
- Tenant/Shop state composes into FULL, READ_ONLY, BLOCKED, or CLOSED. svg
- All currently defined Shop mutations enter through **`mutableContext()`**.

### Missing business lifecycle

There are no services, APIs, or screens for:

- Starter/Growth activation;
- renewal;
- upgrade;
- downgrade;
- extension;
- expiry event creation;
- entitlement overlap validation;
- Trial-duration constraint enforcement beyond the fixed onboarding calculation;
- downgrade rejection when active Staff exceeds the target cap;
- Tenant/Shop status transition management and Audit.

The Owner screen displays only the latest subscription row, not necessarily the currently effective row; if a future period exists, it may display that while **`AccessGate`** evaluates another period. svg

### Error semantics

READ_ONLY/BLOCKED/CLOSED mutation denial throws **`DomainException`** and becomes HTTP 422 **`VALIDATION_FAILED`**, rather than a stable access-state error and suitable 403/409 code. svg

---

## M. Audit Investigation

### Strengths

The Audit service:

- creates stable public IDs;
- records actor, Tenant, Shop, membership, action/category/resource, reason, request context, IP, and hashed user agent;
- is called within the caller’s active transaction;
- does not itself accept password, password hash, MFA secret, domain raw token, recovery secret, or TLS key fields. svg

No reviewed Audit call stores the raw DNS token or password.

### Required coverage that exists

- Tenant onboarding — one aggregate event.
- Staff creation.
- Staff activation/deactivation.
- Staff credential reset.
- Permission grant/revoke.
- Shop profile update.
- Branding profile update.
- Asset replacement.
- Custom-domain request.
- DNS verification.
- Domain activation backend.
- Trial subscription creation is referenced in onboarding metadata and has a subscription event.

### Missing or incomplete coverage

- distinct Primary Owner identity creation;
- Owner recovery;
- Platform Admin MFA enrollment/challenge/recovery;
- TLS readiness;
- usable activation/primary-switch actions;
- subscription renewal/upgrade/downgrade/extension/expiry;
- Tenant status transitions;
- Shop status transitions;
- Primary Owner reassignment;
- Platform Admin creation/recovery and other sensitive Admin actions.

Further weaknesses:

- onboarding identity creation is outside the audited transaction;
- asset bytes can survive after DB/Audit rollback;
- permission Audit omits Shop context;
- **`AuditService`** does not enforce forbidden metadata keys or sensitive-action reason policy centrally;
- application-level immutability is documented but no executed least-privilege DB-grant verification was possible;
- no database trigger prevents Audit UPDATE/DELETE, which is acceptable only if operational grants are actually applied.

---

## N. API / Controller / Route Investigation

### Existing disconnected endpoints

- **`GET /api/v1/shop/staff/{id}/permissions`** exists but has no API client method/UI consumer.
- **`POST /api/v1/shop/staff/{id}/reset-password`** exists but has no API client method/UI action.
- **`DomainService::activateVerified()`** exists but has no route/controller/UI caller.

### Missing endpoints

- MFA enrollment/challenge/recovery/reverification.
- Platform Admin domain listing/operations.
- TLS readiness update.
- Domain activation.
- Explicit primary switch.
- Subscription create/renew/upgrade/downgrade/extend.
- Tenant status transition.
- Shop status transition.
- Owner recovery/reassignment.
- Appropriate Shop authentication-context redirect endpoint, if not implemented through custom Shield views.

### Controller concerns

- Authentication failures return a redirect rather than an API JSON 401. svg
- Platform Admin failures always return JSON, including the HTML Admin shell request. svg
- All **`DomainException`**s become **`VALIDATION_FAILED`** 422, collapsing permission denied, read-only, blocked, closed, not found, and invalid input into one category. svg
- CSRF is commented out globally even though the custom Fetch client sends a token. svg Sending a token does not protect anything unless the server filter validates it.
- No rate-limit/throttle filter is applied to custom onboarding or mutation routes.

---

## O. Frontend / Vue / Bootstrap Investigation

### Architecture compliance

The frontend matches the approved decision:

- Bootstrap 5.3.8 compiled from Sass;
- Vue 3 global build;
- normal **`setup()`**;
- native Fetch;
- no Router, Pinia, Axios, TypeScript, or JS bundler;
- assets copied locally. svgsvg

### Specific incomplete or disconnected UI behavior

1. Actual Shield login/recovery pages are not branded.
2. No MFA enrollment, challenge, step-up, expiry, or recovery screen.
3. No Staff password reset/recovery control.
4. Permission GET/state/history are never loaded.
5. Permission checkboxes always begin unchecked.
6. Permission controls use blocking **`prompt()`** dialogs rather than accessible inline forms.
7. Staff and subscription calls are Owner-only, but **`load()`** always requests both. An ordinary Staff member causes the entire **`Promise.all()`** to reject, leaving the shell unusable. svg
8. Navigation is not role/permission-aware.
9. Read-only disables some Save/upload/request buttons, but the Staff-create and activate/deactivate/permission controls are not disabled by **`readOnly`**.
10. BLOCKED and CLOSED states are rendered with the same generic warning style; there is no dedicated suspended/closed explanation.
11. Permission denied and validation errors have no field-level presentation.
12. Onboarding input is not retained against page navigation/session expiry beyond in-memory Vue state.
13. Asset upload has no preview/crop UI or asset-specific dimension instructions.
14. Domain screen lacks refresh, DNS history, TLS action, activation, or primary selection.
15. There is no Admin operational screen beyond onboarding.
16. Success notices are fixed-position but not reset; no focus management moves to errors/success.
17. No explicit handling exists for an empty domains list or missing subscription beyond placeholder values.

---

## P. Mobile / Responsive Investigation

### Static positives

The Sass uses:

- fluid shell width;
- **`clamp()`** spacing and headings;
- auto-fit form grids bounded by **`min(100%, 16rem)`**;
- cards rather than tables;
- wrapped navigation/button rows;
- 44px form buttons;
- phone-specific header/nav/card action rules. svgsvg

This is a reasonable responsive foundation and does not introduce obvious mandatory horizontal tables.

### Remaining mobile concerns

- Permission lists are not searchable or grouped and contain 13 checkboxes in one details block.
- Browser **`prompt()`** is used for status and revoke reasons.
- The Admin onboarding form contains many fields in a single step without subsectioning.
- No image preview/crop or background readability preview exists.
- Domain tokens can be long; **`<code>`** does not have an explicit word-break rule.
- Login background/mobile readability cannot be assessed because it is not rendered.
- Missing MFA, reset, TLS, activation, and primary-switch screens necessarily lack mobile parity.
- No browser was launched, so 320/360/390/tablet/desktop usability, keyboard-only access, focus behavior, and 200% zoom remain **NEEDS VERIFICATION**, not passed.

---

## Q. PWA Presentation Investigation

The dynamic manifest route provides:

- ID;
- name;
- short name;
- start URL;
- scope;
- standalone display;
- background color;
- theme color. svg

It does not provide:

- 192 icon;
- 512 icon;
- fallback icons;
- description;
- asset/configuration version;
- cache-control/ETag strategy.

A static **`public/manifest.webmanifest`** also exists with an empty **`icons`** array, although the CI route is intended to supersede it. svg Deployment/server rewrite behavior should be verified to ensure the dynamic route is what browsers actually receive.

The service worker intentionally has no fetch handling and remains Phase 0-only, which is appropriate because Phase 10 offline synchronization is out of scope. svg

---

## R. Database / Migration / SQL Investigation

### Migration/raw SQL parity

The CodeIgniter migration does not independently define the schema; it reads and executes the registered raw SQL. Therefore migration/SQL column-level parity is exact by construction. svg

The SQL includes all 12 expected Phase 1 tables:

1. **`sync_generations`**
2. **`subscription_plans`**
3. **`tenants`**
4. **`shops`**
5. **`tenant_memberships`**
6. **`membership_permission_grants`**
7. **`shop_branding_profiles`**
8. **`shop_brand_assets`**
9. **`shop_domains`**
10. **`subscriptions`**
11. **`subscription_events`**
12. **`audit_events`**

It contains the principal v1.1 columns, composite scope uniques/FKs, checks, generated active/primary guards, indexes, UTC **`DATETIME(6)`** fields, and the documented deferred **`audit_events.actor_device_id`** FK. svg

### Schema design differences or caveats

- The SQL follows the schema design’s deliberate decision not to use **`UNIQUE(tenant_id)`** on Shops. svg
- Subscription periods are structurally immutable but there is no DB privilege/trigger preventing application UPDATE/DELETE.
- There is no DB constraint preventing overlapping entitlement periods; this is explicitly a service responsibility, but that service is missing.
- Trial duration 14–30 days is a service rule; only a fixed 30-day onboarding period exists.
- Plan caps are seed data, not CHECK constraints, which is reasonable but requires controlled plan mutation.
- Phase 1’s “one active ordinary membership per user” is neither a generated DB guard nor service-enforced.
- The raw-SQL statement splitter is simplistic. It appears adequate for the current file, but only execution on MySQL can prove it.
- **`down()`** does not remove Shield tables, which is correct because they are vendor-owned.
- The SQL requires Shield’s **`users`** table to exist first; operational ordering is documented but unexecuted.

---

## S. `utf8mb4_unicode_ci` Change Impact

### Current `utf8mb4_0900_ai_ci` references

**Raw SQL — 12 table definitions**

**`docs/database/sql/phase-01/001_phase_01_foundation.sql`**:

- line 9 — **`sync_generations`**
- line 20 — **`subscription_plans`**
- line 30 — **`tenants`**
- line 41 — **`shops`**
- line 54 — **`tenant_memberships`**
- line 64 — **`membership_permission_grants`**
- line 77 — **`shop_branding_profiles`**
- line 90 — **`shop_brand_assets`**
- line 109 — **`shop_domains`**
- line 122 — **`subscriptions`**
- line 133 — **`subscription_events`**
- line 150 — **`audit_events`**
  svgsvg

**Authoritative/project documentation**

- Phase 0 roadmap database configuration. svg
- Database / Schema Design v1.1 global rule. svg
- Configuration/setup baseline. svg
- Schema implementation notes. svg

**Database configuration**

There is no conflicting **`0900`** value here: the runtime CodeIgniter configuration already uses **`utf8mb4_unicode_ci`**. svg

**Environment/setup files**

No additional **`utf8mb4_0900_ai_ci`** reference was found in **`env`**, README, other setup instructions, or application PHP outside the files listed above.

**Column-level definitions**

Explicit special-purpose columns use ASCII/**`ascii_bin`**, notably UUIDs and hostnames. Those should remain ASCII and are not conflicts with the new business-text collation decision. svg

### Minimal correction approach

Because Phase 1 has not been approved, runtime dependencies are absent, and no production-use evidence exists, the minimal clean approach is:

1. Change all 12 table-default collations in the existing registered Phase 1 SQL to **`utf8mb4_unicode_ci`**.
2. Update the four conflicting authoritative/setup documentation statements.
3. Retain **`CHARACTER SET ascii COLLATE ascii_bin`** for UUIDs and normalized hostnames.
4. Recreate or migrate a disposable database from scratch and verify:
   - Shield migrations first;
   - Phase 1 migration;
   - seed;
   - **`SHOW CREATE TABLE`** for all 12 tables;
   - migration rollback/reapply.
5. Record the correction in the migration register/schema notes.

### Corrective migration decision

- **If no shared/staging/production database has applied this migration:** correct the existing Phase 1 SQL/migration before approval and rebuild environments. A second migration would add unnecessary pre-approval history.
- **If any durable environment has already applied it and must preserve data:** do not edit history alone. Add an approved corrective migration using **`ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`** for all 12 business tables, with collision/preflight checks and matching raw SQL documentation.

The repository alone cannot prove whether an external database has already applied it, so that operational fact must be confirmed before implementation.

### Compatibility and index implications

- **`utf8mb4_unicode_ci`** is supported by a broader range of MySQL/MariaDB deployments than MySQL 8’s **`0900`** collation.
- Collation conversion can alter comparison and sort behavior. Existing unique textual keys should be checked for values that become equivalent under the target collation.
- The most security-sensitive unique hostname and UUID columns remain **`ascii_bin`**, so hostname/UUID uniqueness is unaffected.
- Existing indexed varchar lengths in these Phase 1 tables are within ordinary InnoDB/MySQL 8 limits; no obvious index-width redesign is required.
- Application-level normalization should still be used for email, plan codes, permission codes, and hostnames rather than relying on a case/accent-insensitive collation.

---

## T. Documentation Accuracy

### Accurate or mostly accurate

- The migration register correctly explains that the migration executes the raw SQL. svg
- The permission catalogue agrees with the enum.
- Asset storage and late Device FK notes generally match code.
- The dependency register’s intended versions match lock/package data conceptually.

### Stale or overstated claims

1. “Shield owns … MFA” is stated as if MFA were integrated, but no working flow exists. svg
2. “Onboarding is … atomic” is false because identity creation occurs before the transaction. svg
3. “Every sensitive service writes immutable Audit evidence” is overstated; multiple required services/actions are missing. svg
4. The manual checklist instructs testers to enroll/complete Shield MFA, but no such app path exists. svg
5. The checklist requires Staff credential recovery, complete asset propagation, and domain activation/switching, none of which is usable end-to-end. svg
6. Bootstrap instructions require assigning **`platform_admin`**, but that group is absent. svg
7. Configuration says production Admins must enroll Shield MFA, but there is no enrollment integration. svg
8. Collation statements are stale under the newly approved decision.
9. Dependency documentation says Shield 1.4.1 is locked, but **`composer.json`** permits **`^1.1`**; exact lock verification is possible statically, while runtime package availability is not because **`vendor/`** is absent. svgsvg

---

## U. Runtime Verification Status

### Inspected

- Complete custom application file inventory.
- Phase 0/Phase 1 documentation and authoritative Markdown sources.
- Routes, filters, services, controllers, views, Vue modules, Sass, generated assets, migration, SQL, seeder, configuration, environment template, package metadata, and Git history.
- All repository references to the old/new collations.

### Statically verified

- All application PHP files passed **`php -l`**.
- Source and generated public JavaScript files matched byte-for-byte.
- The Tenant-context and access-gate call paths were traced.
- Migration and raw SQL are the same source by construction.
- Generated CSS and JS assets exist.
- PHP includes **`mysqli`**, **`pdo_mysql`**, **`fileinfo`**, **`gd`**, **`intl`**, and other relevant extensions.
- Node/npm and tracked **`node_modules`** exist.

### Actually executed

Only non-mutating/static investigation commands were executed. No application request, migration, DNS verification, login, upload, or database transaction was successfully executed.

### Unable to execute

- **`vendor/`** is absent, so **`spark`**, route inspection, Shield behavior, and application boot cannot run.
- No configured **`.env`**/MySQL service and Phase 1 schema were available for safe verification.
- Therefore login/recovery/MFA, migration execution, onboarding rollback, IDOR testing, file upload, manifest delivery, DNS verification, and responsive browser behavior remain unverified.

---

## V. Exact Remaining Phase 1 Work

Ordered by dependency:

1. **Restore reproducible runtime dependencies and environment**
   - Install Composer dependencies from lock.
   - provide disposable MySQL 8.4 configuration;
   - establish platform domain/email/session settings;
   - stop relying on tracked platform-specific **`node_modules`** state.
2. **Correct the database collation contract**
   - decide whether any durable database already applied Phase 1;
   - update existing SQL/docs for fresh pre-approval use, or add a corrective migration if preservation is required;
   - rebuild and inspect all tables.
3. **Make Shield security configuration compliant**
   - add **`platform_admin`**;
   - disable public registration;
   - align password policy;
   - configure rate limits and generic failures;
   - enable and verify CSRF for custom session mutations.
4. **Implement actual Platform Admin MFA**
   - supported factor enrollment;
   - challenge on login/admin entry;
   - recent-MFA timestamp establishment;
   - 15-minute step-up expiry;
   - recovery/reset;
   - logout/session clearing;
   - Audit without secret leakage.
5. **Integrate branded authentication**
   - custom Shield login/recovery/challenge views;
   - hostname-resolved brand name/tagline/colors/logo/favicon/background;
   - role/context-aware safe redirects;
   - correct API 401/session-expired behavior;
   - deactivated-account verification.
6. **Repair onboarding atomicity**
   - create Shield identity inside the same owning transaction/connection strategy;
   - roll back identity and all business rows on any failure;
   - produce Owner and onboarding Audit evidence;
   - handle duplicate email/hostname cleanly;
   - confirm Sync Generation prerequisite.
7. **Complete Staff identity lifecycle**
   - define safe Owner-managed temporary password/recovery behavior;
   - invalidate all relevant sessions/tokens;
   - add Staff reset UI and API client;
   - enforce the approved active-membership invariant;
   - clarify global user deactivation semantics.
8. **Complete effective permission management**
   - call the permissions-state endpoint;
   - show checked active grants and history;
   - group/search permissions;
   - replace prompts with accessible reason forms;
   - correctly disable mutations outside FULL mode.
9. **Make the Shop shell usable by Staff**
   - stop unconditional calls to Owner-only endpoints;
   - return role/effective permissions in context;
   - render authorized navigation/actions;
   - distinguish 401, 403, read-only, blocked, closed, and validation states.
10. **Harden Shop settings**
    - normalize/validate phone, email, lengths, and IANA timezone;
    - add field-level errors and appropriate Audit deltas.
11. **Finish branding profile and asset behavior**
    - enforce length/nonempty/accessibility constraints;
    - add type-specific asset dimensions/aspect rules;
    - handle filesystem rollback/orphan cleanup;
    - render login background;
    - refresh shell presentation after update.
12. **Complete PWA presentation**
    - emit active 192/512 icons;
    - provide fallbacks;
    - add version/cache invalidation;
    - verify the dynamic route wins over the static manifest.
13. **Complete the domain lifecycle**
    - harden hostname normalization;
    - formalize TXT record name/value instructions;
    - implement Admin domain operations;
    - persist/audit TLS readiness;
    - route activation;
    - route explicit primary switching;
    - retain platform fallback;
    - add Owner status refresh.
14. **Complete subscription lifecycle**
    - create immutable paid periods/events;
    - implement renew/upgrade/downgrade/extend;
    - enforce non-overlap and Trial constraints;
    - reject downgrade above target Staff cap;
    - never auto-deactivate Staff;
    - audit all changes;
    - show the effective rather than merely latest entitlement.
15. **Complete lifecycle administration**
    - audited Tenant suspend/reactivate/close;
    - audited Shop activate/deactivate;
    - appropriate access behavior for each state.
16. **Close Audit gaps**
    - add required events for every action above;
    - require sensitive-action reasons centrally;
    - prevent forbidden secret keys in metadata;
    - ensure same-transaction ownership;
    - apply/verify append-only DB privileges.
17. **Correct error/authorization semantics**
    - API JSON 401/403/409/422 envelopes as appropriate;
    - HTML redirects for browser shells;
    - stable codes for MFA, permission, read-only, blocked, closed, not found, and validation errors.
18. **Update documentation**
    - remove claims unsupported by code;
    - document real MFA/domain/subscription operations;
    - update collation;
    - update migration register and manual checklist.
19. **Perform evidence-backed manual verification**
    - fresh migrate/seed;
    - all auth and MFA flows;
    - forced onboarding rollback;
    - two-Tenant BOLA/Host-header checks;
    - every sensitive Audit event;
    - malformed uploads;
    - DNS/TLS/activation/switching;
    - entitlement/access modes and downgrade rejection;
    - 320/360/390/tablet/desktop, keyboard, and 200% zoom.

---

## W. Files Likely Requiring Modification

Expected modules/files, without modifying them:

- **`app/Config/Auth.php`**
- **`app/Config/AuthGroups.php`**
- **`app/Config/Filters.php`**
- **`app/Config/Routes.php`**
- **`app/Config/Services.php`**
- **`app/Filters/AuthenticatedFilter.php`**
- **`app/Filters/PlatformAdminFilter.php`**
- new MFA/auth filters, controllers, services, and views
- **`app/Controllers/Api/OnboardingController.php`**
- **`app/Controllers/Api/ShopController.php`**
- new Platform Admin domain/subscription/status controllers
- **`app/Controllers/Web/ShellController.php`**
- **`app/Modules/TenantShop/Application/OnboardingService.php`**
- **`app/Modules/TenantShop/Application/ShopSettingsService.php`**
- **`app/Modules/TenantShop/Infrastructure/ShopContext.php`**
- **`app/Modules/Identity/Application/StaffService.php`**
- **`app/Modules/Identity/Application/PermissionService.php`**
- **`app/Modules/BrandingDomain/Application/BrandingService.php`**
- **`app/Modules/BrandingDomain/Application/BrandResolver.php`**
- **`app/Modules/BrandingDomain/Application/DomainService.php`**
- **`app/Modules/Access/Application/AccessGate.php`**
- new Subscription application services
- **`app/Modules/Audit/Application/AuditService.php`**
- **`app/Commands/VerifyShopDomains.php`**
- **`app/Views/layouts/shell.php`**
- **`app/Views/auth/index.php`**
- new Shield override/MFA views
- **`app/Views/admin/index.php`**
- **`app/Views/shop/index.php`**
- **`resources/js/api/http-client.js`**
- **`resources/js/api/phase-one.js`**
- **`resources/js/auth/app.js`**
- **`resources/js/admin/app.js`**
- **`resources/js/shop/app.js`**
- **`resources/css/app.scss`**
- **`app/Database/Migrations/2026-09-25-190000_CreatePhaseOneFoundation.php`** only if the approved collation strategy requires migration-history handling
- **`docs/database/sql/phase-01/001_phase_01_foundation.sql`**
- possibly a new corrective migration/raw SQL pair if an applied durable database must be preserved
- **`docs/DawaTrack_Database_Schema_Design.md`**
- **`docs/DawaTrack_Strategic_Phased_Implementation_Roadmap.md`**
- **`docs/configuration.md`**
- **`docs/database/migration-register.md`**
- **`docs/database/schema-notes.md`**
- **`docs/modules/phase-01.md`**
- **`docs/modules/phase-01-completion.md`**
- **`docs/operations/phase-01-bootstrap.md`**
- **`docs/security/foundation.md`**
- **`docs/manual-verification-phase-01.md`**
- rebuilt **`public/assets/**`** outputs where source frontend files change.

---

## X. Database Change Assessment

**Current assessment: at minimum, a collation correction is required.**

No other new Phase 1 business table is clearly required. Most remaining gaps are services, security integration, routes, UI, validation, Audit coverage, and operations.

Whether the collation correction should be:

- an edit to the existing pre-approval Phase 1 SQL, or
- an additional corrective migration

depends solely on whether a durable database has already applied the migration and must retain data.

There is also a **genuine integrity-policy issue** around the approved “one active ordinary membership per user” rule. It can potentially be completed in serialized application logic, as the schema blueprint permits, but it must be implemented and verified. A schema guard would constitute an additional approved schema decision rather than an automatic correction.

No schema changes are required for TLS state, PWA icons, login background, subscription periods, or Audit records; their necessary columns already exist.

---

## Y. Risks Before Phase 1 Approval

1. **Administrative lockout:** absent **`platform_admin`** group plus an unsettable MFA timestamp prevents legitimate Admin access.
2. **Unauthorized registration:** public Shield registration is enabled.
3. **CSRF exposure:** custom session-authenticated mutation APIs do not have the CSRF filter enabled.
4. **Orphan identities:** failed onboarding can leave a live Shield account without Tenant records or Audit.
5. **Broken Staff UX:** Staff cannot reliably load the common Shop shell because it calls Owner-only endpoints.
6. **False permission presentation:** unchecked permission boxes can cause accidental duplicate grant/revoke actions.
7. **Incomplete domain control:** operational staff would need direct DB manipulation for TLS/activation/primary changes.
8. **Brand inconsistency:** actual credential screens, login backgrounds, and installed PWA icons are not white-labeled.
9. **Subscription-rule bypass:** there is no controlled paid-plan/downgrade service.
10. **Audit incompleteness:** several sensitive actions have no event because the action itself is missing.
11. **Session invalidation uncertainty:** password reset/deactivation may not invalidate every active authentication mechanism.
12. **Collation drift:** config, SQL, and authoritative documents currently prescribe different defaults.
13. **Unproven SQL/runtime behavior:** no migration or application request was executed.
14. **Misleading documentation:** manual verification instructs operators to exercise paths that do not exist.
15. **Filesystem/DB inconsistency:** failed asset metadata transactions can leave private orphan files.
16. **Dependency reproducibility:** missing Composer vendor dependencies and tracked cross-platform **`node_modules`** obstruct reliable execution.

---

## Z. Final Recommendation

**Do not approve Phase 1 and do not begin Phase 2 yet.**

Phase 1 approval should require, at minimum:

1. a working Shield configuration with no public registration;
2. a real **`platform_admin`** group and end-to-end MFA enrollment/challenge/recent-verification/recovery;
3. branded real login and recovery pages with session-expiry and deactivation behavior verified;
4. fully atomic Owner/Tenant/Shop onboarding;
5. usable Staff reset and effective-permission UI;
6. an ordinary Staff-capable Shop shell with deny-by-default backend policies;
7. complete TLS readiness, activation, explicit primary switching, and platform fallback workflows through protected HTTP/UI paths;
8. runtime login-background and PWA-icon propagation;
9. complete immutable subscription change/downgrade policy;
10. complete atomic Audit coverage;
11. CSRF and correct API authorization/error semantics;
12. standardized **`utf8mb4_unicode_ci`** SQL/configuration/documentation;
13. corrected implementation documentation;
14. successful MySQL migration/seed and end-to-end manual verification on desktop and required mobile widths.

The present implementation is a useful Phase 1 scaffold, but it is not a complete vertical slice and does not meet the approved Definition of Done.