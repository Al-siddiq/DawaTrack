# UI3-B — Owner, Admin and Auth screens

**Date:** 11 November 2026 (Africa/Lagos). **Branch:** `codex/ui3-b-owner-admin-auth-20261111`. **Base:** UI3-A commit `4e560dcb295f9e89fea2fc61a5cd10bd3a46df08`, PR #35. **Acceptance:** pending.

The Owner authorized proceeding to UI3-B. They did not supply UI3-A manual results or an explicit UI3-A acceptance statement, so that status is not changed. At implementation, #35 remained open and main remained `5ff9ae8`. B is stacked on A. Merge #35 into main first, then retarget the B PR to main; do not merge B into an obsolete UI2 feature branch. No merge or deployment is performed here.

## Delivered

- Owner Staff: compact add form, search, actual roles/status, plain-language permission catalogue, grant/revoke history, existing reason dialogs and one-time password reset output. Temporary password is cleared when leaving the screen; private outputs are not timed away.
- Shop details, appearance, logo/app images, web addresses and plan: shared fields/errors/loading/accordions; existing PNG/JPEG and exact icon dimensions; explicit DNS copy-once warning; no checkout/plan catalogue invented. Authorized read-only views remain.
- Starting cash: existing start/count/commit/review/finalize/abort state machine, string amount and attempt identity retained. Plain stages, explicit final/abort decisions, support details separated, and same-state refresh after uncertain replies. It remains a one-time starting baseline, never a daily opening routine.
- Activity: existing Owner/platform/support queries, cursor, filters, metadata and related evidence retained; readable summaries and native detail sheet. Shop support requires the separate `platform.audit_support` presentation grant as well as server authorization, with explicit target/reason and no impersonation.
- Admin: separate Shops, Add shop, Plans, Web addresses and Activity sections; existing onboarding keys and masked review, lifecycle/plan/domain operations with consequence dialogs, reason requirements and actual grants; UTC time inputs remain explicitly UTC. No new authority/API/business policy.
- Auth: native sign-in/recovery/password-change/registration-disabled/rate-limit/Admin step-up/denial plus local Shield email-code and activation screens. CSRF, same routes/field names and original handlers remain. Native validation links to controls; busy submit/BFCache restore and password visibility stay local. Error/security messages remain until dismissed or navigation.
- Generic HTTP 400/404/production error pages now use the local stylesheet, clear recovery wording and no inline CSS. They do not depend on Auth/branding/database services during exception handling. CLI/development exception debug views are unchanged.

## Engineering evidence and limits

Build, source syntax, PHP grammar and generated/source parity are checked at delivery. Eighteen Owner/starting-cash/Activity/Admin variants rendered through a Vue host adapter without Vue warnings. The actual starting-cash handlers were exercised with mocked replies through one start, string count, commit, review and final decision on the original attempt ID. Actual Admin onboarding handlers retained original request keys and did not display the temporary password in review/completion. Five generated Shop Owner destinations also rendered without Vue warnings through the reactive ownerTools integration. The actual Activity support handlers retained their explicit target/reason, returned access ID, cursor and detail payload with mocked replies. These are host-adapter observations, not native browser/PHP/HTTP/DB/security acceptance.

Local Shield screen form contracts were compared to the locked Shield v1.4.1 source (`93c5f1f1481ced93ceb4071ffa3a08a9647a7c8b`). Only three view mappings change; email delivery/action classes, verification policy, token lifetime/validation, remembering/recovery settings and rate-limit response logic are untouched. Native MFA/email/password/CSRF behavior remains manual verification.

No test framework/suite, new dependency, migration or financial backend change. No browser screenshot, actual email delivery, MySQL, native accessibility/device or production rollout pass is claimed. See [manual checklist](manual-verification-ui3-b.md) and [developer reference](owner-admin-auth.md). UI4-A still provides full programme/manual/release closure; it has not been completed by this package.
