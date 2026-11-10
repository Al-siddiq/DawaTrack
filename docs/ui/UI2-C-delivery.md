# UI2-C — Shop, Admin and Auth shells/navigation

**Date:** 9 November 2026. **Branch:** `codex/ui2-c-workspace-shells-20261109`. **Base:** accepted UI2-B `aaa93d9c3bb984ea973fbad91250662865c9e899`, PR #29. **C acceptance:** confirmed by Owner on 10 November 2026; see [acceptance record](acceptance/2026-11-10-ui2-c-owner-acceptance.md). Additional [brand refinement](brand-refinement.md) awaits review.

## Implemented

- Shared Shop workspace: desktop sidebar/current user, sticky task header, labeled compact bottom navigation and start-side Menu sheet. All current destinations retained under the current server capabilities/access mode.
- Separate Admin shell/sections over current onboarding, lifecycle/subscription, domain and audit APIs, filtered by server-evaluated platform grants. No Shop identity or Customer portal is introduced.
- Branded native Auth entry layouts, locally hosted icons, busy submit, show/hide password and BFCache restore; existing email/CSRF/recovery/MFA/forced-password rules retained. The existing Shop context response adds only current_user.display_name for refreshed presentation.
- Real task Home with authorized existing baskets and initial cash prerequisite. No reporting metrics, fake production records or future-phase links.
- Central hash navigation, current-group metadata, preserved sale/return/correction identities, Back/Forward handling and same-page record routing. Existing task bodies remain for UI-3.
- Guarded unfinished edits, active mutations and unresolved financial outcomes; revision-based submitted-form tracking and nonce-authorized measured layout space, safe areas/keyboard and print rules.
- UI2-B Owner acceptance recorded; developer reference, catalogue/coverage and 29-case C manual checklist updated.

## Engineering evidence and limits

Frontend/template/asset build passed during implementation. New/changed JS received syntax inspection. Ten changed PHP/controller/helper/view files parsed successfully with PHP 8.3 grammar using a scratch-only language parser; this is not native PHP/CI4 execution. Owner/restricted Staff/blocked Staff workspace rendering completed without Vue warnings, and navigation was exercised for normal/dirty-confirmed/denied paths with lightweight host stubs. These checks do not establish native browser events, CSP, keyboard/accessibility or actual HTTP/DB correctness.

Native Chromium cannot start here because the environment denies its startup socket. Native Windows/XAMPP, real sign-in/permissions/access modes, browser history/focus/keyboard, responsive layout, print and business regression remain manual verification. No test framework/suite, schema migration, business API endpoint, permission grant or financial calculation is introduced.

## Dependency and next stage

C is stacked on B (which depends on A). Merge/deploy coherent prerequisites; no automatic merge or deployment is performed. After C review/acceptance, UI3-A migrates trading task bodies, followed by UI3-B Owner/Admin/Auth task-body/copy closure and final UI4-A programme verification. A completed shell is not a claim that every legacy task screen is already redesigned.
