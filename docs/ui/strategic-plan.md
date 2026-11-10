> Implementation note, 8 November 2026: the Owner approved this plan and authorized implementation. UI2-A is delivered for manual review; package acceptance is pending. The historical planning status below records its original issuance.

# DawaTrack UI-2 — Strategic Implementation Plan and Developer Reference

**Version:** 1.0 · **Date:** 8 November 2026 · **Status:** proposed delivery baseline; implementation not started by this document.

**Decision owner:** Project Owner. **Implementation owner:** Lead engineer. **Acceptance owner:** Project Owner through manual use and recorded verification.

## 1. Outcome and scope contract

Deliver the agreed shared UI foundation completely: a professional, compact, responsive interface; clear permission-based navigation; locally hosted icons; accessible reusable controls; and explicit feedback, loading and recovery behavior. An ordinary English-reading shop operator must understand where they are, what to do next, and how to correct invalid input.

The original sequence is retained:

| Stage | Completion means | Does not mean |
|---|---|---|
| UI-1 | Navigation, wording and representative design direction, including the agreed icon/component additions | Production screens implemented |
| **UI-2** | Complete shared design system, icon pipeline, interactive component library, and functioning Shop/Admin/Auth shells; every current destination reachable under its existing authority | All existing task screens already migrated |
| UI-3 | Every existing task screen migrated to the new components, visual rules and plain-English copy | Final Owner acceptance assumed |
| UI-4 | Full manual role, device, business-regression and release acceptance, with completed documentation | A new business phase |

This plan specifies UI-2 in detail and includes the UI-3/UI-4 completion programme so that nothing in the overall agreement disappears between stages. A working shell alone must never be reported as “100% of the redesigned application.” UI-2 acceptance and full UI programme acceptance are separate records.

“100% delivered” means all applicable requirements have implementation locations and passing evidence; all routes/actions and relevant states are accounted for; all 18 visual references and 96 wording entries have dispositions; and the Owner has accepted the relevant stage. It is a coverage and acceptance rule, not a claim made in advance.

### 1.1 Approved design decisions

- One design system and one Shop layout for Owner and Staff, with permitted content/actions. Admin remains a separate workspace and session. Customers are current records, not a newly introduced login category.
- Desktop: persistent sidebar with shop logo/name, permitted destinations and user details; sticky header with current task; no redundant bottom navigation.
- Phone: sticky compact header, labeled bottom navigation **Home / Sell / Stock / Customers / Menu**, and an open/close navigation drawer. Unauthorized destinations are omitted.
- Consistent local SVG icons in bottom navigation, sidebar links, Back, Add, search, editing, status and other meaningful controls. Icons accompany plain-English text.
- Locally hosted **Bootstrap Icons**; no Font Awesome/Lucide mixed into the same interface. CSS-based spinners; no separate animation library.
- Moderate spacing, readable regular type, restrained branding and mobile/desktop task parity. Essential totals and controls must not be obscured by sticky elements.
- Buttons with loading states; contextual sheets; modals; accordions; dropdown menus; information panels; dismissible success alerts; persistent corrective errors.
- Preserve current business rules, API authority, immutable records, privacy, permissions, authentication, domain context and Phase 4 acceptance.

### 1.2 Explicit exclusions

No Phase 5 debt collection/statements, report dashboards, public Customer portal, wallets, interest, unit conversion, daily cash closing, new payment gateway, schema redesign or offline sale posting. No fabricated summary metrics, mock production values or visible future-feature placeholders. Locally available assets do not grant offline business-write capability.

No application JavaScript bundler, Vue Router, Pinia, TypeScript, replacement frontend framework or automatic icon DOM-replacement library is introduced. No new automated test framework or suite is introduced; the roadmap requires manual acceptance. Build, compilation and syntax checks remain engineering checks, not business acceptance.

## 2. Evidence and source baseline

Reviewed main commit: `09789e8aab48b5d62b546ecf538e491867b5fedf`, confirmed unchanged on 8 November 2026. All proposed paths/components below are explicitly proposals unless listed as existing.

| Source | Why it governs this work |
|---|---|
| `docs/DawaTrack_Technical_Blueprint.md` | Modular monolith, Vue `setup()`, separate application boundaries and server authority |
| `docs/DawaTrack_Strategic_Phased_Implementation_Roadmap.md` §§1.1–1.4 | Plain English, responsive parity, no placeholders, manual verification and authoritative business contracts |
| Approved UI-1 package | 18 representative screens, 29 navigation mappings, 96 wording entries and design specification |
| Follow-up icon/component agreement | Local Bootstrap SVGs, visible labels, loading states, reduced motion and appropriate overlays |
| `app/Controllers/Api/ShopController.php` and `app/Enums/PermissionCode.php` | Actual capability flags, Owner-only functions and separate action grants |
| `resources/js/shop/app.js` | Current navigation, hash identities, Vue composition and context/access state |
| `resources/js/admin/app.js` | Current onboarding, domains, platform operations and activity functionality |
| `app/Views/layouts/shell.php` | Server-resolved branding, nonce-authorized styles, CSRF metadata and self-hosted runtime |
| `scripts/build-frontend-assets.mjs`, `scripts/compile-shop-template.mjs` | Windows-compatible copy/build workflow, generated Shop renderer and CSP-sensitive compilation |
| `resources/js/components/commercial-ui.js` | Existing shared presentation helpers to consolidate carefully |

Current build compiles `resources/js/shop/template.html` into `resources/js/shop/render.js`, then copies runtime/source assets to `public/assets`. The generated renderer is not a hand-edit target. Current Admin code uses Vue `h()` rendering. New shared UI components must work with the production runtime-only Vue build and current CSP; a runtime template string is not a safe default.

UI-1 catalogue shorthand `ShopController.php` is clarified here as `app/Controllers/Api/ShopController.php`. Reconcile any abbreviated navigation gate descriptions against the actual server/controller before implementation; catalogue display names never grant authority.

**Source conflict rule:** preserve accepted business/security behavior. Record any conflict between a mockup, old wording or current service behavior, its effect and resolution. Routine layout details can be resolved by the lead engineer within these rules. Changed business semantics, authority or phase scope need an explicit design decision rather than an undocumented shortcut.

## 3. Delivery structure and dependency order

Use three cohesive UI-2 work packages. Then two task-migration packages and one final acceptance package complete the overall programme. Do not combine all work into a single difficult-to-review PR.

| Package | Scope | Depends on | Reviewable result |
|---|---|---|---|
| **UI2-A** | Reference baseline, visual tokens and local icon pipeline | Current baseline and agreement | Pinned assets, icon catalogue, tokens, coverage register, icon-enhanced design examples |
| **UI2-B** | Shared interaction components and state behavior | A | Working component reference with real keyboard/focus/loading behavior |
| **UI2-C** | Shop, Admin and Auth shells; navigation integration | A, B | Real navigation and common controls around all current functions |
| UI3-A | Trading task migration | UI-2 accepted | Customers, stock, sales, payments, returns, corrections and receipts fully restyled |
| UI3-B | Owner/Admin/Auth task migration and copy closure | UI2-C; shared patterns from UI3-A | Remaining existing screens migrated; no forgotten settings or account states |
| UI4-A | Programme-wide manual verification and release handover | UI3-A, UI3-B | Completed traceability/evidence, corrected defects, Owner acceptance and rollback guide |

Prefer one reviewable PR per package; split a package only when a coherent dependency or review size requires it. A split does not remove its remaining obligations. Branch from the accepted integration head, identify dependencies in each PR and avoid merging a dependent package ahead of its prerequisites. Merge the documented branch order, rather than assuming unmerged feature branches are present in main.

### 3.1 UI2-A — Baseline, tokens and icons

**Tasks**

1. Capture implementation base SHA, current route/action inventory, access/capability rules and the original design IDs in the coverage register.
2. Bring the approved navigation/copy catalogues and updated reference into version control under `docs/ui/`; preserve their original IDs.
3. Define shared spacing, typography, colors, focus, motion, elevation and responsive tokens. Audit current magic colors and overriding styles before replacing them.
4. Pin Bootstrap Icons `1.13.2`, the official release observed at planning time. Verify its package metadata/licence at implementation; document any justified replacement pin before using it. Add the exact lockfile entry and retain notices.
5. Introduce a deterministic allowlist-driven SVG asset generation step through the existing Node asset script. Produce only selected local symbols/assets. No runtime CDN, remote kit, webfont requirement or whole-library JavaScript payload.
6. Define the semantic icon map and a single `UiIcon` interface. Supply an equivalent server-rendered helper/partial for non-Vue Auth pages.
7. Update the UI-1 examples to show navigation icons, icon-and-text buttons, selected navigation, saving, loading, modal, sheet, accordion, dropdown, success and persistent error examples. These resolve the prior static design omissions before claiming implementation fidelity.
8. Record asset bytes and local request behavior on a production build. Set the UI asset byte budget from the measured allowlist and record the approved limit; do not promise a library-size number without measurement.

**Outputs:** tokens; exact dependency pin; allowlist and icon catalogue; local asset generator; licensed SVG output; enhanced design examples; source-to-requirement register; first developer-reference sections.

**Manual gate:** inspect every selected icon; confirm visible labels and meanings; build on Windows/XAMPP and Linux; inspect network requests for external asset dependencies; check branded and fallback colors; open the reference with network unavailable after the necessary local assets are present. This does not test offline business writes.

**Exit:** all UI2-A requirements have passing evidence or an explicit documented dependency in B/C. No missing icon or placeholder is counted as delivered.

### 3.2 UI2-B — Shared components and interaction contract

**Tasks**

1. Implement focused shared components listed in §6, using semantic HTML, Vue `h()` or existing precompilation, and controlled state.
2. Consolidate compatible existing commercial helpers/reason dialogs instead of leaving two competing button/feedback/focus systems. Migrate callers safely, with compatibility adapters where necessary.
3. Implement button busy states, field help/errors, linked error summaries, list loading, empty/error panels, alerts and persistent outcome-unknown panels.
4. Implement drawer/sheet/modal focus, keyboard behavior, scroll management and responsive sizes. Use only one state owner per control. Avoid simultaneous Bootstrap JavaScript and Vue handlers mutating the same open state.
5. Implement accordion, dropdown, information panel, badge, pagination and accessible icon controls.
6. Create a developer component reference with interactive examples for normal, loading, error, disabled, empty, read-only and restricted states. Keep it a development/documentation surface, not an unauthenticated production route leaking context.
7. Verify reduced motion, long text, high zoom and narrow widths before applying the components everywhere.

**Outputs:** reusable components/composables, documented inputs/events/slots and examples, a11y behavior, style variants, state catalogue and manual evidence.

**Manual gate:** use all controls with keyboard alone; test focus return and background isolation; submit a form with multiple invalid fields; dismiss success; check critical warnings persist; toggle reduced motion; inspect with a screen reader; prevent double-taps; resolve a simulated unknown outcome through a controlled safe fixture without creating real duplicate transactions.

**Exit:** each component contract works in the reference and in at least one real authorized application use where applicable. A reference-only spinner does not complete its real integration obligation in C/UI-3.

### 3.3 UI2-C — Shells and navigation integration

**Tasks**

1. Replace the repeated top link collection with the agreed responsive shell, reserving real space for header/bottom navigation and the on-screen keyboard.
2. Implement one Shop navigation registry, filtered by current server capabilities/access mode. Use it for sidebar, bottom navigation, Menu and page metadata.
3. Map every current destination/action to its new parent. Preserve existing sale/basket IDs and browser hash links, refresh, Back/Forward and receipt links. Do not add Vue Router.
4. Implement the shared Owner/Staff layout and task-oriented Home. Show only real shortcuts/current unfinished baskets, with no reporting totals or invented metrics.
5. Implement Admin navigation over existing operations and a separate Admin shell. Plans means existing subscription operations, not a new plan catalogue. No standalone Owners root or shop-operating impersonation feature.
6. Apply shared header/branding/entry layout to Auth while preserving existing email login, configured recovery, password-change and Admin security-check flows.
7. Add meaningful icons to shell links and visible common actions; use shared feedback components in shell-owned forms/status blocks. Keep internal legacy task bodies functional pending UI-3.
8. Integrate loading/context errors and FULL/read-only/BLOCKED/CLOSED or other actual server access modes. Preserve existing mode-specific navigation restrictions, not just a generic boolean.
9. Update runbook, developer reference, navigation/permission mappings and UI2 completion ledger.

**Outputs:** integrated shells, working responsive navigation, current-page context, real permitted Home shortcuts, local icons, entry shells and manual checklist/evidence.

**Manual gate:** Owner, restricted Staff and Admin; every current destination; phone/desktop; direct links and browser history; stale permission changes; multiple saved baskets; startup failure; long shop/user names; no visible unauthorized writes; no covered fields or action bars. Sign-out must use the existing secure mechanism.

**Exit:** the UI-2 scope is complete and accepted explicitly. Record remaining task-body migrations as UI-3 work, not UI2 defects silently deferred. Any unfinished UI2 component/navigation item remains an open UI2 requirement.

## 4. Navigation and authorization reference

### 4.1 Registry contract

Proposed entry shape:

```js
{
    id: 'products',
    labelKey: 'nav.stock',
    icon: 'stock',
    parentId: null,
    bottomGroup: 'stock',
    capability: 'view_inventory',
    accessPolicy: 'existing-stock-visibility',
    target: 'existing-products-view'
}
```

Names above are illustrative interfaces, not existing exports. Store translation/copy keys, semantic icons, capability and grouping once. Do not create a parallel list in each renderer. Render actual links where navigation occurs and buttons where a local action occurs. Selected page uses `aria-current="page"`; link labels remain available to assistive technology.

Display access comes from current context. Write actions require both their specific grants and the actual server access decision. A visible destination is never proof that the user may mutate it. Re-evaluate after context refresh and safely handle a server denial. Do not fetch sensitive datasets and merely hide them in CSS.

### 4.2 Required destination mapping

| Label/group | Existing view or flow | Authority and behavior |
|---|---|---|
| Home | `home` | Authenticated member; shortcuts inherit target gates |
| Sell | `sale-drafts` | `view_sale_drafts`; saving additionally needs `sale.record` |
| Stock | `products` | `view_inventory`; separate grants for edits/actions |
| Customers | `customers` | `view_customers`; `customer.manage` for edits, `customer.private` for private fields |
| Sales, inside phone Menu | `sale-history` | Existing sale-view authority; returns/corrections separately gated |
| Stock deliveries, inside Stock | `intakes` | Preserve current read visibility; writes need `manage_intakes` / `inventory.intake` |
| Starting stock / stock corrections | Existing inventory actions | Existing action-specific authority; do not infer from view permission |
| Starting cash | `opening-cash` | `manage_opening_cash`, currently Owner-only; existing initial/generation baseline semantics |
| Staff | `staff` | `manage_staff`, currently Owner-only |
| Shop details | `settings` | `manage_shop_settings`, currently Owner-only |
| Shop appearance | `branding` | `manage_branding`, currently Owner-only |
| Web address | `domains` | `manage_domains`, currently Owner-only |
| Plan | `subscription` | `view_subscription`, currently Owner-only |
| Activity | `audit` | `view_audit`, currently Owner-only |
| My account / password / sign out | Existing Auth flows | Existing session rules; no new account-management service |
| Admin Shops / Add shop | Existing platform operations / onboarding | Existing platform access/onboarding grants |
| Admin Plans | Existing subscription operations | Existing platform subscription grant |
| Admin Web addresses / Activity | Existing domain/audit operations | Existing platform domain/audit grants |

Phone main destinations: Home, Sell, Stock, Customers, Menu. Sales/returns/settings use Menu as the selected bottom group. Admin uses its own permitted navigation; do not force shop selling links into an Admin bottom bar. A Customer portal is not part of any shell.

Desktop work section: Home, Sell, Stock, Customers, Sales. Owner section: Staff, Shop settings, Activity. Shop settings groups details/starting cash/appearance/web address/plan, each retaining its current gate. The drawer mirrors the same registry. Unauthorized main destinations are omitted, remaining touch targets distribute evenly, and at least Home/Menu or the actual limited access routes remain coherent.

Use at most one side-navigation scroller. Keep shop identity and current-user information discoverable without a sidebar so tall that it traps navigation on small laptops.

## 5. Visual, icon and asset reference

### 5.1 Visual tokens and responsive rules

| Token family | Baseline |
|---|---|
| Colors | Primary `#155D46`, ink `#20342A`, muted `#5D7066`, page `#F3F6F4`, surface white, border `#DCE5DF`, selected surface `#E6F1EB`; validate actual combinations |
| Typography | Body 16px; helpers 14px; headings 22–28px; bottom labels 11–12px only if clearly legible; normal-weight body |
| Spacing | 4, 8, 12, 16, 24px; compact task-focused sections, not large empty panels |
| Icons | Action 18–20px, navigation 22–24px; consistent alignment and optical weight |
| Touch controls | At least 44×44px target; an icon's visual size is not its touch size |
| Breakpoints | Proposed compact below 768px; intermediate 768–1199px; persistent sidebar from 1200px. Validate at the content-fit boundary and update once centrally if needed |
| Layout | Phone single task column; wide product search plus basket; tables become labeled cards/drill-downs on narrow screens |
| Motion | Short, restrained transitions (normally 120–200ms); reduced-motion override removes nonessential movement and offers readable loading text |
| Layers | Content < sticky navigation < scrim < active dialog/sheet < permitted alert region; one documented scale, not arbitrary competing z-index values |

Header/sidebar/bottom heights must be derived from their actual rendered content and safe areas, not hardcoded assumptions that fail when names wrap. Use `env(safe-area-inset-*)` where needed and dynamic viewport-aware sizing with a fallback. Focused fields and buttons must remain reachable when the mobile keyboard opens. No routine horizontal scrolling at 320 CSS px or at 200% text zoom; inspect reflow also at 400% browser zoom where practical.

Brand values remain server-resolved and nonce-authorized as current CSP requires. Validate/fallback when branding undermines contrast. Do not make every heading a shop accent color. Maintain text contrast targets of 4.5:1 for normal text, 3:1 for large text and appropriate non-text control contrast. Accessibility conformance must be verified, not inferred from a palette table.

### 5.2 Semantic icon catalogue

Use semantic names so a future asset update does not force every component to change. Keep one icon family. Proposed mappings must be verified against the pinned package in UI2-A.

| Semantic name | Bootstrap symbol candidate | Use |
|---|---|---|
| home | `house` | Home |
| sell | `basket` | Sell |
| stock | `box-seam` | Stock |
| customers | `people` | Customers |
| menu | `list` | Menu/drawer |
| sales | `receipt` | Saved sales/history |
| back | `arrow-left` | Back with destination text where useful |
| next | `arrow-right` | Next/continue |
| add | `plus-lg` | Add stock/customer/product |
| search | `search` | Search |
| edit | `pencil` | Edit |
| remove | `trash` | Remove an unfinished basket line; label distinguishes permanent cancellation |
| return | `arrow-return-left` | Return products |
| cancel-record | `x-circle` | Cancel saved sale, with explicit consequences/reason |
| print | `printer` | Print receipt |
| close | `x-lg` | Close overlay |
| settings | `gear` | Shop settings |
| cash | `cash-stack` | Starting cash/cash information |
| staff | `person-badge` | Staff |
| appearance | `palette` | Shop appearance |
| web-address | `globe` | Web address |
| plan | `card-checklist` | Plan information |
| activity | `clock-history` | Activity |
| account | `person-circle` | Current account |
| sign-out | `box-arrow-right` | Secure sign out |
| expand | `chevron-down` | Accordion/dropdown; rotate only while appropriate |
| success | `check-circle` | Confirmed success |
| warning | `exclamation-triangle` | Persistent warning |
| error | `exclamation-circle` | Error |
| info | `info-circle` | Information/help |
| refresh | `arrow-clockwise` | Refresh/read status, not blind financial retry |
| visibility | `eye` / `eye-slash` | Show/hide password with matching accessible label |
| shop | `shop` | Admin Shop list |

Do not use a garbage-bin icon alone for cancellation of an immutable sale. Keep its label distinct from removing an unfinished basket line. Do not attach icons to every sentence or heading; use them where they improve recognition or status.

### 5.3 Asset contract

- Add exact `bootstrap-icons` version to the existing package/lockfile and maintain an allowlist of selected symbols.
- Generate a deterministic same-origin SVG sprite or equivalent selected vector map during `build:assets`; do not ship `all.js`, an entire icon font or a remote kit.
- Use trusted upstream paths only. Do not inject raw user-supplied SVG with `v-html`.
- Vue `UiIcon` and server-rendered equivalents use the same symbol names/assets. Decorative icons have `aria-hidden="true"` and are not focusable. Buttons/links get their name from visible text; compact icon-only controls receive an accessible label.
- Prefer a local sprite URL through the project's URL conventions. Validate external `<use>` in the target browsers and CSP. If it fails, use a selected inline symbol/vector map from the same trusted source, not an external service.
- Package icons and runtime assets in one coherent release; record paths, hash/version strategy and measured bytes. Preserve existing Service Worker safety boundaries; do not add caching of sensitive data or writes merely to cache icons.
- Build with `npm ci` and `npm run build`. The Node asset script is the canonical cross-platform path. Do not reintroduce a shell-only `sh` dependency for Windows users.

## 6. Shared component contracts

Proposed module directory: `resources/js/components/ui/`; shared styling under `resources/css/` using the existing Sass entry point. Final filenames may follow current repository conventions, but the documented interfaces and responsibilities must remain explicit.

All components: semantic HTML, predictable focus, no embedded business calculations, no implicit permission grants, no unescaped HTML, and no side-effecting API call merely from rendering.

| Component | Inputs / events / slots | Required behavior |
|---|---|---|
| `UiIcon` | `name`, `size`, optional title | Trusted allowlist, `currentColor`, decorative by default; missing names caught during development |
| `UiButton` | `type`, `variant`, `icon`, `loading`, `disabled`, `busyLabel`; click/default content | Fixed-enough width to avoid jumping; loading spinner + words; submit/click cannot double-dispatch |
| `UiField` | `id`, `label`, `help`, `errors`, `required`; input slot | Real label association; `aria-describedby`, `aria-invalid`; input value remains owned by caller |
| `UiErrorSummary` | List of field target/message pairs | Focusable summary on failed submit; links focus actual fields |
| `UiAlert` | severity, message, dismissible, persistence policy; dismiss | Close button; safe auto-dismiss for ordinary success only; no raw backend detail leakage |
| `UiLoading` | label, visual variant | Text plus spinner/skeleton; region `aria-busy`; no invented percentage |
| `UiEmptyState` | heading, explanation, optional authorized action | Distinguish no records from no matching results and load failure |
| `UiInformationPanel` | heading, severity, content | Important consequences visible without hover; sensible heading level |
| `UiDialog` | open, heading, close policy, initial focus; request-close/confirm | Focus containment/return, background isolation, scroll lock, one active modal, labelled description |
| `UiSheet` | Dialog contract plus mobile placement | Same a11y; usable width/height; keyboard-safe; parent state preserved |
| `UiAccordion` | expanded, heading, controls ID; toggle | Button trigger with `aria-expanded`; essential data not collapsed by default |
| `UiDropdown` | open, labeled trigger, items; select/close | Predictable keyboard/Escape/outside close; no hidden primary action. Use semantic links/buttons unless full ARIA menu behavior is implemented |
| `UiNavigation` | authorized registry, current ID/group; navigate | Real target/current-state semantics; shared desktop/mobile registry |
| `UiPagination` | current page/total/busy; page-request | Previous/Next states correct; filter changes reset page; no stale response overwriting newer results |
| `UiStatusPanel` | task state, explanation, allowed recovery actions | Persistent unknown/error/access state; no auto financial retries |
| `UiRecordList` | records/empty/loading/renderer | Responsive labeled rows/cards, stable record identity and drill-down |

Vue owns open/busy state. Existing Bootstrap CSS can style the components without initializing Bootstrap JS plugins on the same DOM. Auth pages can use native/server-rendered equivalents where Vue is not mounted. Avoid two sources of truth, repeated global listeners or per-screen ad hoc dialogs. Remove listeners and timers on unmount; reference-count scroll locking if the implementation needs it.

### 6.1 Overlay behavior

A focused, short edit belongs in a sheet; a long form or multi-step sale/return belongs on its page. A modal is for a short confirmation with clear consequences. Do not nest modals. A reason field in a confirmation remains governed by the server's existing constraints.

On open, record the trigger and focus the heading or first safe field. Confirmation defaults must not put focus on a destructive action without thought. Contain focus, isolate background interaction, and make Close/Cancel clear. Escape closes when safe. If closing would discard entered data, request confirmation or retain the edit state; do not silently lose it. When closed, restore focus to the trigger or a sensible surviving location.

Do not imply a server write was cancelled simply because an overlay closed. A write in flight keeps its operation identity and explicit status. Close/read navigation is not an HTTP rollback mechanism.

### 6.2 Feedback and motion rules

Ordinary success alerts can dismiss after roughly 8–10 seconds, have a close button and pause dismissal while hovered/focused. The saved result remains visible after the alert disappears. Avoid transient toasts for money, required next steps, unrecorded changes, errors or uncertain outcomes.

Use polite live announcements for routine status and alert semantics for actionable errors, without repeatedly announcing the whole page. Avoid progress percentages without actual measured progress. Do not use spinners or skeletons forever when a request has entered an error/unknown state. Reduced motion replaces decorative movement with a stable cue and readable status; do not rely on a slower rotation as the only accommodation.

## 7. State and request lifecycle

| State | Visible result | Permitted next step |
|---|---|---|
| Initial context loading | Stable shell outline and “Loading your shop…” | Wait; no unauthorized shortcut flash |
| Read loading | Region spinner/skeleton plus task label | Existing navigation stays usable where safe |
| Ready | Real content and one clear main action | Existing authorized actions |
| Empty | Specific no-record or no-match text | Adjust search or authorized create action |
| Validation error | Inline help + summary; values retained | Correct and resubmit |
| Mutation saving | “Saving sale…” / task-specific busy label | Prevent duplicate submission; preserve identity |
| Confirmed saved | Actual saved record/reference and success | View/print/continue as authorized |
| Confirmed failed | Useful server-normalized reason; edits retained | Correct or retry only where operation contract permits |
| Outcome unknown | Persistent “Check saved status”; no second new transaction | Query existing identity through current supported flow |
| Permission revoked | Clear access message; sensitive data hidden/disposed appropriately | Reload context or permitted destination |
| Read-only/expired | Explain actual access condition and permitted next step | Allowed read routes; plan link only if actually permitted |
| Blocked/closed | Actual server explanation; current limited routes only | No generic “View sales” shortcut overriding policy |
| Offline before action | “Connect to continue” where online is required | Restore connection; no queued/saved-success claim |
| Stale preview/conflict | Explain what changed; retain editable context | Refresh preview and review before permanent save |

Keep read-request cancellation separate from mutation outcome. Abort/timeout on the client does not establish that a write failed. Never automatically create a fresh UUID because a response was lost. Keep current duplicate-safety identity, endpoint status recovery and domain rules. A UI refactor must not invent Phase 10 Sync Receipts or introduce a generic retry wrapper around financial writes.

Use the existing centralized API layer for credentials, CSRF and normalized errors. Avoid double submission through both `onClick` and `submit`. Maintain a busy lock per operation, not one global spinner disabling unrelated tasks. For fast searches, disregard stale results arriving after a later search; do not cancel an in-flight write by reusing a read-search abort controller.

Do not duplicate decimal money calculations as floating-point business rules in presentation components. Use existing exact-money/domain helpers and server-authoritative previews. Values stay decimal strings through the API. Do not aggregate quantities across different units into a misleading total.

## 8. Task migration inventory and safeguards

UI-3 is not limited to the 18 sample screens. It covers every implemented task and its relevant variants.

| Package | Task family | Must preserve / improve |
|---|---|---|
| UI3-A | Customers | Search/list/detail, create/edit/status, private-data gate, validation and customer selection |
| UI3-A | Products/stock | Search/detail/manage product, base units, quantity/cost visibility and inactive product handling |
| UI3-A | Stock deliveries/adjustments | Existing draft/preview/post/reversal/starting-stock paths, reasons, permission and stock evidence |
| UI3-A | Sale preparation | New/resume/discard baskets, customer quick-create where supported, line edit/remove/reorder where currently supported, business time, saved state |
| UI3-A | Payment/review/save | Cash/transfer composition, unpaid remainder and customer requirement, optional due date, starting cash prerequisite, duplicate and unknown outcomes |
| UI3-A | History/details/receipts | Existing filtering/paging, saved immutable evidence, print/reprint, provisional costs and profit/privacy grants |
| UI3-A | Returns | Return draft/quantity limits, preview, refund/debt/stock consequences, reasons, posting and saved evidence |
| UI3-A | Sale cancellation/correction | Current inverse effects, reason, linked original and replacement lineage, no history deletion |
| UI3-B | Starting cash | Current initial/generation baseline, preview/finalize/evidence/recovery; no daily opening routine |
| UI3-B | Staff | Create/status/permission grant/revoke/reset credentials, owner authority, reasons, sensitive one-time outputs |
| UI3-B | Shop settings | Existing operational settings; valid inputs and recoverable errors |
| UI3-B | Branding/domains | Logo/assets/colors/fallbacks, verification/challenge/activation states and real operator responsibilities |
| UI3-B | Plan/access | Existing plan/status/capacity details; no invented payment/checkout service |
| UI3-B | Activity | Current search/detail/support views; authorized scope and privacy |
| UI3-B | Admin | Onboarding details/review/result, operations/lifecycle/subscriptions, domain operations, audit/support and denial/security states |
| UI3-B | Auth | Email login, configured recovery, mandatory password change, Admin MFA/denial, rate limiting, registration-disabled/error pages and secure sign-out |

Customer-facing receipts must not leak costs/profit, technical stack details or unauthorized private customer data. Printed output excludes navigation/buttons, uses readable page breaks and retains the original transaction/return references. Negative financial actions need explicit text; an icon is not sufficient confirmation.

Technical domain/DNS/security instructions may need exact terms in dedicated setup/help areas. Pair necessary terms with a practical explanation. Do not remove a precise value a person must copy merely to simplify wording. Routine selling and stock tasks use ordinary English.

## 9. Wording and documentation governance

Preserve UI-1 `COPY-001`…`COPY-096` IDs. For every entry, record the implementation location, applicable screen/state and manual review result. If a term applies only to future functionality or developer references, record that precise reason and keep it out of production navigation. “Not implemented in this phase” is not permission to omit existing functionality.

New messages receive new IDs. Do not rewrite API machine codes or persistence enums to match display labels. Normalize known errors into useful copy, preserve correlation/reference information in a support detail, and avoid exposing raw exceptions, SQL or secrets. Existing server facts must not be hidden by a blanket success message.

Proposed version-controlled developer-reference structure:

| Document | Mandatory contents |
|---|---|
| `docs/ui/README.md` | How to navigate the reference, scope/version/source SHA, package status |
| `docs/ui/architecture.md` | App boundaries, source/generated paths, CSP, rendering and ownership rules |
| `docs/ui/design-tokens.md` | Exact tokens, responsive/layer rules, branding fallback, motion |
| `docs/ui/navigation-and-permissions.md` | Complete route/group/role/action map and access modes |
| `docs/ui/icons.md` | Pinned library, allowlist, semantic mapping, licence, local generation, examples |
| `docs/ui/components.md` | Every component interface, states, examples, keyboard/focus behavior and limitations |
| `docs/ui/interaction-states.md` | Reads/writes, loading/errors/unknown outcomes, overlays, duplicate prevention |
| `docs/ui/wording-catalogue.csv` | All original/new copy IDs, usage, implementation references and review disposition |
| `docs/ui/screen-inventory.csv` | Every implemented screen/subflow, role/action/state coverage, package and evidence |
| `docs/ui/requirements-register.csv` | Requirement → component/screen → changed path → manual case → result → acceptance |
| `docs/ui/manual-verification.md` | Safe setup, roles, fixtures, steps, expected results, device/browser evidence |
| `docs/ui/release-and-rollback.md` | Build/deploy/cache behavior, coherent rollback, smoke steps and known limitations |
| `docs/ui/decisions.md` | Accepted decisions, differences from mockups, reasons and owner/date |
| `docs/ui/acceptance/` | Separate UI2 and programme acceptance, actual commit/date and outstanding items |

This document is the initial strategic/developer reference, not evidence that those proposed repository files already exist. Every implementation package must update its affected reference sections in the same PR. Final documentation cannot be postponed to a cleanup package with no clear owner.

### 9.1 Developer change checklist

Before adding a screen/action: identify existing endpoint and permission; select registry entry/parent; use a shared component; select approved copy and icon; specify loading/empty/error/read-only/unknown cases; preserve identity and server rules; define manual cases; update the inventory/reference. Do not add a visible placeholder while waiting for a future endpoint.

Before changing a component: list all current callers; preserve interface or supply a migration adapter; inspect keyboard/focus/reduced motion and narrow layouts; remove duplicate styling/handlers; rebuild generated assets; update examples and documentation. Prefer focused modules over expanding the existing large Admin or Shop renderer with another monolithic block.

## 10. Requirements and completion register

The following seed register is mandatory. At implementation, split rows further whenever they cannot be verified independently. “UI2 + UI3” means component delivery in UI2 and complete caller migration in UI3; both must be evidenced before overall closure.

| ID | Requirement | Delivery | Acceptance evidence |
|---|---|---|---|
| UI-R01 | Shared Owner/Staff design, permission-based content | UI2-C | Compare Owner/restricted Staff and direct-link denial |
| UI-R02 | Separate Admin workspace/session | UI2-C | Admin entry/session and no Shop-operation controls |
| UI-R03 | No unsupported Customer portal/future navigation | UI2-C | Full navigation inventory inspection |
| UI-R04 | Sticky task header with shop identity | UI2-C | Phone/desktop/long-name checks |
| UI-R05 | Persistent desktop sidebar, user details | UI2-C | Desktop/laptop navigation and scroll checks |
| UI-R06 | Phone Home/Sell/Stock/Customers/Menu | UI2-C | Current-group and limited-grant cases |
| UI-R07 | Drawer keyboard, close and focus return | UI2-B/C | Keyboard/screen-reader and resize checks |
| UI-R08 | All current destinations reachable | UI2-C | 29-entry map plus source-inventory reconciliation |
| UI-R09 | Existing browser links/history preserved | UI2-C | Direct load, reload, Back/Forward and saved-reference cases |
| UI-R10 | Real task Home, no invented statistics | UI2-C | Data source/empty/multiple-basket review |
| UI-R11 | Consistent tokens and compact spacing | UI2-A; UI3 | Visual comparison and long-content checks |
| UI-R12 | Readable type and contrast | UI2-A; UI4 | Contrast measurements and zoom/reflow evidence |
| UI-R13 | Branding and CSP-safe fallback | UI2-A/C | Extreme branding, missing assets and CSP inspection |
| UI-R14 | Local pinned icons and notices | UI2-A | Exact version/licence/asset manifest |
| UI-R15 | Meaningful icons on nav/actions/status | UI2-C; UI3 | Icon mapping and every-screen inspection |
| UI-R16 | Visible text and accessible icon-only names | UI2-B/C; UI3 | Accessible-name and non-hover inspection |
| UI-R17 | Button saving/busy state, no duplicate taps | UI2-B; UI3 | Actual submit and slow-response cases |
| UI-R18 | List/page loading and skeletons | UI2-B/C; UI3 | Controlled loading/error and screen-reader checks |
| UI-R19 | Reduced motion and restrained animation | UI2-B; UI4 | OS/browser reduced-motion evidence |
| UI-R20 | Contextual sheets preserve parent edits | UI2-B; UI3 | Open/edit/cancel/close and keyboard cases |
| UI-R21 | Modals state exact consequences | UI2-B; UI3 | Destructive reason, focus and cancel cases |
| UI-R22 | Accordions do not conceal essential facts | UI2-B; UI3 | Totals/errors/next-step inspection |
| UI-R23 | Dropdowns hold secondary actions | UI2-B; UI3 | Keyboard/overflow and primary-action visibility |
| UI-R24 | Smart information panels | UI2-B; UI3 | Payment/return/setup facts match server |
| UI-R25 | Closable safe auto-dismiss success | UI2-B; UI3 | Timer pause/dismiss and saved evidence remains |
| UI-R26 | Persistent errors/warnings/unknown outcomes | UI2-B; UI3 | Delayed response/validation/status recovery |
| UI-R27 | Plain English and all 96 copy dispositions | UI3-A/B | Catalogue location and Owner reading review |
| UI-R28 | Correct invalid input without lost values | UI2-B; UI3 | Multi-field/recoverable failure checks |
| UI-R29 | Role/action/private-data authority retained | Every package | View/write/direct-request and hidden-data review |
| UI-R30 | Correct access-mode restrictions | UI2-C; UI3 | Every actual mode; stale mode response |
| UI-R31 | Exact-money/stock/business rules preserved | UI3-A/B | Existing manual commercial regression |
| UI-R32 | Preview/refund/debt/stock consequences clear | UI3-A | Return/correction examples and staleness cases |
| UI-R33 | Same identity through uncertain writes | UI3-A | Lost response, check status, no fresh UUID |
| UI-R34 | No offline success/queue claim | UI2-C; UI3 | Asset-local vs business-offline checks |
| UI-R35 | All trading screens migrated | UI3-A | Full task inventory, not sample screens alone |
| UI-R36 | All Owner/Admin/Auth screens migrated | UI3-B | Remaining inventory and denial/error pages |
| UI-R37 | Print/reprint privacy and readable layout | UI3-A/B | Preview/print both paper sizes used in pilot |
| UI-R38 | Touch, keyboard, focus and semantics | UI2-B/C; UI4 | 44px targets, keyboard walkthrough, screen reader |
| UI-R39 | No covered controls or horizontal task scroll | UI2-C; UI3; UI4 | 320/360/390px, keyboard, zoom, long records |
| UI-R40 | Shared runtime/precompilation maintained | UI2-A/B/C | Production assets and no CSP/runtime compile errors |
| UI-R41 | Windows-compatible reproducible build | UI2-A; UI4 | Windows and Linux build records |
| UI-R42 | No competing Vue/Bootstrap DOM ownership | UI2-B/C | Component ownership/listener review |
| UI-R43 | Complete reference updated with each package | Every package | Reference and changed-source cross-check |
| UI-R44 | All 18 design specimens reconciled | UI3; UI4 | Before/after screenshot references and justified differences |
| UI-R45 | Full manual evidence and explicit acceptance | UI2-C; UI4 | Completed checklists, commit and Owner decision |
| UI-R46 | Coherent release/rollback and cache behavior | UI4 | Staging deployment, refresh and rollback rehearsal |

Register fields: ID, wording/design source, package, applicable roles/access modes, component/screen, source path, manual case IDs, expected result, actual result, device/browser, evidence reference, status, defect/decision link, implementer, reviewer, acceptance date. Avoid collecting unnecessary customer data in evidence.

Allowed status: planned, in progress, implemented awaiting verification, failed, verified, accepted, or not applicable with a precise approved reason. “Deferred” cannot count as delivered. No row starts as passed. Whole-programme closure requires all applicable rows accepted with no unresolved functional/accessibility/security/data-loss defect and no unapproved scope omission.

## 11. Manual verification strategy

The Owner retains manual acceptance. The engineer first verifies the build, documents environment/setup and fixes reproducible problems. The Owner then receives a runnable branch/commit, exact commands, safe sample setup, checklist and expected outcomes. Do not ask the Owner to discover undocumented routes or guess how to stage an uncertain-response case.

### 11.1 Environment and fixtures

Use a non-production Shop with authorized test users and no real customer payments. Prepare: Owner; Staff with view-only sales/stock/customer grants; Staff allowed to record sales but no return/profit permission; Staff with return/correction authority as supported; Admin with actual platform grants; different-tenant record; actual read-only/blocked/closed fixtures; active/inactive products/customers; empty/populated lists; multiple baskets; long names; provisional costs; cash ready/not ready; partial-cash/transfer/debt sale and return examples.

Do not alter production subscriptions or credentials just to exercise a screen. Document fixture setup/reversal using existing supported operations. Never mark a state “not applicable” just because it was inconvenient to reproduce.

Devices/browsers: actual affordable Android/Chrome; Windows Chrome or Edge/XAMPP as the Owner's workflow; a tablet/intermediate width; a second supported browser including Safari/iOS if those are supported deployment targets. Record actual versions and OS. Check portrait/landscape, safe areas, keyboard open, 320/360/390px narrow widths, 768/1024px intermediate and 1280px+ desktop. A resized desktop alone is not phone acceptance.

### 11.2 Minimum manual scenarios

| Case | Steps and expected outcome |
|---|---|
| M01 Navigation coverage | Open every current allowed destination; correct header, selected group and return path; no dead links |
| M02 Restricted Staff | Compare allowed/rejected navigation and actions; Owner controls absent; direct navigation cannot reveal data |
| M03 Admin boundary | Sign in through Admin flow, complete security check and traverse existing platform tasks; no Shop sell/stock mutation |
| M04 Responsive shell | Change widths/orientation/zoom, open keyboard and use long names; no covered field/button or routine horizontal task scrolling |
| M05 Icon delivery | Production build with network inspection; icons use local assets and retain labels; no font/CDN dependency |
| M06 Loading | Slow a safe read/write in test setup; task-specific cue, stable button width and no duplicate submission |
| M07 Input correction | Submit missing/invalid values; summary links reach fields; valid edits remain; error tells how to correct |
| M08 Overlay lifecycle | Keyboard open/traverse/Escape/Cancel/close/return focus; background unavailable; no nested modal or silent loss |
| M09 Motion/feedback | Reduced motion, screen reader, success dismissal pause; important warnings remain visible |
| M10 Browser lifecycle | Open sale/return link, reload, Back/Forward, navigate away and resume; identity and saved state are correct |
| M11 Commercial regression | Complete existing Phase 4 cash/transfer/debt combinations and existing stock constraints; values/evidence match accepted behavior |
| M12 Return/correction | Preview and save permitted return/cancellation/replacement; exact stock, refund, debt and lineage remain clear |
| M13 Uncertain outcome | In a safe environment lose a write response; no automatic second operation; same-identity status recovery shows authoritative evidence |
| M14 Access transition | Change actual role/grant/access mode through supported operations; stale screen handles denial and refresh safely |
| M15 Privacy/print | Restricted user and printed receipt show only permitted data; no cost/private field leaks or missing references |
| M16 Auth | Email/password, configured recovery, mandatory password change, MFA/rate-limit/denial and sign-out preserve current security |
| M17 Owner/Admin functions | Exercise existing staff/settings/branding/domains/plan/activity/onboarding/operations as allowed; no omitted action |
| M18 Release/cache/rollback | Build/deploy coherent assets, refresh old open tab, check CSP/runtime logs; rehearse rollback with no mixed release artifacts |

Each package expands the relevant cases into actual step-by-step checks against its real implementation. Existing manual business checklists remain authoritative; reference them rather than replacing them with superficial visual checks. Existing diagnostic scripts, if used, are supplementary evidence and never a substitute for the mandated manual gate.

### 11.3 Severity and acceptance

Stop release for data integrity/duplicate writes, authority/private-data leaks, unusable essential actions, inaccessible essential keyboard flow, unrecoverable lost edits or broken mobile navigation. Fix and reverify the affected cases and dependent flows. Minor visual differences must still be recorded and resolved or explicitly accepted; an outstanding agreed requirement cannot disappear under “cosmetic.”

## 12. Release, rollback and handover

Before each release, record base/head commits, package dependencies, build environment, exact dependency versions, generated asset locations and required deployment steps. No database migration is expected for this UI programme; a proposed migration is a scope change requiring explanation.

Deploy compatible server markup, compiled renderers, JS, CSS and icon assets as a coherent unit. Avoid a rollout in which old JS references new/removed sprite symbols or new markup points to missing assets. Cache invalidation must follow the current hosting/Service Worker contract; do not broaden data caching to solve stale styles. Retain required old assets during transition or use a documented versioned release strategy.

Rollback means restoring the complete prior compatible asset/markup release, not deleting individual new files. Preserve business records written during the UI release; never roll back data as a visual rollback. Reopen/refresh and manually check sign-in, context, allowed navigation, basket identity and saved receipts after rollback. Long-lived tabs require a safe refresh/recovery experience.

Every package handover includes: actual branch/commit/PR; what is delivered; changed source/generated files; reference updates; build commands/results; manual setup/checklist/evidence; remaining scope and defects; rollback notes; explicit Owner acceptance request for that concrete package. Do not record acceptance until the Owner gives it.

### 12.1 Definition of done — UI-2

- UI2-A/B/C scope complete; no unimplemented UI2 obligation reclassified as UI-3.
- All agreed shared primitives have working states and real shell integration where relevant.
- Current Shop/Admin/Auth entry and navigation work under actual permissions/access restrictions.
- Icons are pinned, licensed and entirely locally served; the asset build works in the Owner's Windows workflow.
- Keyboard/focus/reduced motion/critical responsive cases for the foundation are manually verified.
- All UI2 requirements map to changed code and evidence; developer reference matches delivered interfaces.
- Owner accepts the concrete UI2 build; task-body migrations remaining in UI-3 are clearly listed.

### 12.2 Definition of done — full agreed UI

- UI-2 plus UI3-A/B and UI4-A complete and accepted.
- Every implemented screen/action/state is reconciled with source inventory, all 29 navigation entries, all 96 wording entries and all 18 design references.
- Commercial/security/privacy/online recovery invariants remain intact, with manual evidence.
- No unapproved omitted feature, placeholder or unresolved material defect.
- Full source-maintenance reference, component examples, release/rollback runbook and actual acceptance record delivered.

## 13. Recommended next implementation objective

Start with **UI2-A — Baseline, tokens and local icon pipeline**. Its handover must include the enhanced representative designs and coverage register as well as the pinned local icon assets. Then deliver UI2-B and UI2-C in order. Implement task migrations only against that accepted foundation, retaining the complete scope register until final Owner acceptance.

This document authorizes no implementation by itself; it is the requested strategic plan and initial developer reference for review. The present deliverable changes no application code or business records.

## Appendix A — Original navigation catalogue

The original 29-entry catalogue follows for continuity. Read gates alongside §4 and the current server controller; this is design traceability, not a replacement authorization policy.

| area | group | label | current target | gate | placement | behavior |
| --- | --- | --- | --- | --- | --- | --- |
| Shop | Work | Home | home | Authenticated current Shop member | Sidebar + bottom | Task shortcuts; no invented reporting totals |
| Shop | Work | Sell | sale-drafts | sale.view; sale.record for writing | Sidebar + bottom | Open new/own unambiguous unfinished basket; saved-sale chooser otherwise |
| Shop | Work | Stock | products | inventory.view | Sidebar + bottom | Search recorded stock; Product and delivery actions retain separate grants |
| Shop | Work | Customers | customers | customer.view | Sidebar + bottom | Search/view records; customer.manage and customer.private remain separate |
| Shop | Work | Sales | sale-history | sale.view | Sidebar + Menu | Search completed sales, saved returns, returns and corrections |
| Shop | Stock tasks | Add stock | intakes | inventory.view; inventory.intake for writing | Inside Stock | Receive stock using existing intake draft/review/confirmation |
| Shop | Stock tasks | Starting stock | intakes:OPENING | Existing opening-stock eligibility and inventory.intake | Inside Stock setup | One allowed opening basis; not a daily reset |
| Shop | Records | Starting cash | opening-cash | Existing PRIMARY_OWNER capability | Sidebar + Menu; setup shortcut when needed | One existing starting-cash baseline; do not create a new daily cash session |
| Shop | Records | Activity | audit | Existing PRIMARY_OWNER view_audit capability | Sidebar + Menu | Existing scoped read-only activity; historical evidence is protected |
| Shop | Manage shop | Staff | staff | Existing PRIMARY_OWNER manage_staff | Sidebar + Menu | Retain present backend authority; no new Staff grants |
| Shop | Manage shop | Shop details | settings | Existing PRIMARY_OWNER manage_shop_settings | Sidebar + Menu | Retain present backend authority; no new Staff grants |
| Shop | Manage shop | Shop appearance | branding | Existing PRIMARY_OWNER manage_branding | Sidebar + Menu | Retain present backend authority; no new Staff grants |
| Shop | Manage shop | Web address | domains | Existing PRIMARY_OWNER manage_domains | Sidebar + Menu | Retain present backend authority; no new Staff grants |
| Shop | Manage shop | Plan | subscription | Existing PRIMARY_OWNER view_subscription | Sidebar + Menu | Retain present backend authority; no new Staff grants |
| Shop | Account | Change password | /change-password | Existing authenticated security flow | User menu | Retain current password/security requirements |
| Shop | Account | Sign out | /logout | Existing authenticated flow | User menu | Warn about unsaved work where appropriate; do not claim a pending sale failed |
| Shop | Navigation | Menu | UI drawer; no business endpoint | Filtered authorized destinations | Bottom fifth control | Open navigation drawer; does not change role or authority |
| Shop | Sale tasks | Return products | sales/{id}/returns | sale.view + sale.return | Completed original Sale | Original-line quantities; debt-first settlement; explicit save |
| Shop | Sale tasks | Correct this record | sales/{id}/reversal-preview and reverse | sale.view + sale.reverse | Completed ordinary Sale/Return action menu | Show exact money/stock/debt effects and reason before confirmation |
| Shop | Sale tasks | Make a corrected sale / return | sales/{id}/replacement | sale.view + sale.reverse to prepare; ordinary grant to post | Reversed source details | Separate linked unfinished record; never silently repost |
| Admin | Platform | Shops | platform operations: tenants | platform.access | Separate Admin sidebar/drawer | Current implemented services only; no Shop selling or stock mutation |
| Admin | Platform | Add shop | platform/tenants | platform.onboard | Separate Admin sidebar/drawer | Current implemented services only; no Shop selling or stock mutation |
| Admin | Platform | Plans | platform operations: subscriptions | platform.subscriptions | Separate Admin sidebar/drawer | Current implemented services only; no Shop selling or stock mutation |
| Admin | Platform | Web addresses | platform/domains | platform.domains | Separate Admin sidebar/drawer | Current implemented services only; no Shop selling or stock mutation |
| Admin | Platform | Activity | platform/audit-events | platform.audit | Separate Admin sidebar/drawer | Current implemented services only; no Shop selling or stock mutation |
| Admin | Owner details | Shop owner | Owner details within existing onboarding/Shop account context | Existing approved Admin authority only | Within Shop details | No standalone Owners destination until supported workflow is confirmed |
| Authentication | Entry | Sign in | /auth and configured Shield email/password login | Resolved current branded Shop | Separate branded entry | Keep email sign-in shown in current login view; no public registration |
| Authentication | Recovery | Get help signing in | Configured existing recovery + controlled Owner/Admin help | Existing security policy | Sign-in help | No password revelation or public role switch |
| Authentication | Admin | Admin sign-in and security check | /platform-admin/login and /platform-admin/mfa | Existing Admin session + MFA | Separate entry | Never remove MFA or borrow a Shop identity |

## Appendix B — Original wording catalogue

All wording remains proposed for per-screen review in implementation. Source IDs and usage constraints are retained. Implementation-location/evidence columns will be added to the version-controlled register.

| id | context | current or internal | usage rule | proposed copy |
| --- | --- | --- | --- | --- |
| COPY-001 | Navigation and access | Dashboard | Task shortcuts, not unimplemented report metrics | Home |
| COPY-002 | Navigation and access | Tenant | Admin context only; Shop remains the operating outlet | Business account |
| COPY-003 | Navigation and access | Primary Owner | Role label only; keep backend identity unchanged | Shop owner |
| COPY-004 | Navigation and access | Record Sale | Destination | Sell |
| COPY-005 | Navigation and access | Sale drafts | An unfinished sale is not a completed sale | Unfinished sales |
| COPY-006 | Navigation and access | Create Sale draft | Create necessary server draft behind this action; do not auto-post | New sale |
| COPY-007 | Navigation and access | Products & stock | Includes existing stock-delivery entry | Stock |
| COPY-008 | Navigation and access | Stock coming in | Action within Stock | Add stock |
| COPY-009 | Navigation and access | Stock Intake | History/detail noun | Stock delivery |
| COPY-010 | Navigation and access | Opening stock | Setup only; preserve eligibility | Starting stock |
| COPY-011 | Navigation and access | Opening Cash | Existing setup baseline, not daily cash reset | Starting cash |
| COPY-012 | Navigation and access | Branding | Owner-only current capability | Shop appearance |
| COPY-013 | Navigation and access | Domains | Owner setup; hide technical status in Details where possible | Web address |
| COPY-014 | Navigation and access | Subscription | Do not change billing rules | Plan |
| COPY-015 | Navigation and access | Audit | Read-only who/what/when; no delete control | Activity |
| COPY-016 | Navigation and access | Shop settings | Business contact/profile; distinguish appearance | Shop details |
| COPY-017 | Navigation and access | Effective permissions | Staff settings only, not ordinary Home | What this person can do |
| COPY-018 | Navigation and access | FULL | Normally quiet; no permanent full-width success banner | You can make changes |
| COPY-019 | Navigation and access | READ_ONLY | Keep reason and allowed next action visible | View only |
| COPY-020 | Navigation and access | BLOCKED | Use safe reason and permitted next step | You cannot open this shop right now |
| COPY-021 | Navigation and access | CLOSED | Do not imply records were deleted | This shop account is closed |
| COPY-022 | Sales | Draft | Prefer Unfinished sale for record status | Not saved as a completed sale |
| COPY-023 | Sales | Posted | Separate record status from payment status | Completed |
| COPY-024 | Sales | Post sale | Final explicit financial action after review | Save sale |
| COPY-025 | Sales | Review preparation | No effects yet | Review sale |
| COPY-026 | Sales | Add line | Product + quantity | Add to basket |
| COPY-027 | Sales | Line order | Advanced optional field only | Basket order |
| COPY-028 | Sales | Unit selling price | Use the actual Product unit; no automatic unit conversion | Price per pack |
| COPY-029 | Sales | Quantity up to 4 decimal places | Accept allowed fractions; give an example only when relevant | How many packs? |
| COPY-030 | Sales | Prepared cash | Only money applied to this sale; change is not an extra payment | Cash received |
| COPY-031 | Sales | Prepared transfer | Cash and Transfer remain distinct | Transfer received |
| COPY-032 | Sales | Credit amount | Not a payment method | Customer still owes |
| COPY-033 | Sales | Credit due date | Use Shop calendar rules | When should they pay? (optional) |
| COPY-034 | Sales | Customer required | Example amount must come from server review | Choose a customer because ₦3,000 is still owed. |
| COPY-035 | Sales | Discard draft | Reason where required; separate from completed-sale correction | Discard unfinished sale |
| COPY-036 | Sales | Walk-in / no Customer | Allow for fully paid sales | No customer selected |
| COPY-037 | Sales | Business time | Device-local entry; display Shop time clearly when different | Sale date and time |
| COPY-038 | Sales | Save draft details | Not final sale posting | Save details |
| COPY-039 | Sales | Reload saved draft | Warn before replacing unsaved edits | Reload saved sale |
| COPY-040 | Sales | Posted successfully | Permanent receipt remains available | Sale saved |
| COPY-041 | Sales | Immutable receipt | Protected history explained in correction flow | Saved receipt |
| COPY-042 | Sales | Public UUID | Short display only; full reference available in More details | Reference |
| COPY-043 | Sales | Correlation ID | Help details, not primary task content | Support reference |
| COPY-044 | Money and stock | Debtors | Later collection navigation is Phase 5, not displayed now | Customers who owe |
| COPY-045 | Money and stock | Receivable | Do not invent a second debt record | Amount owed for this sale |
| COPY-046 | Money and stock | Outstanding balance | Always show who owes whom | Still to pay |
| COPY-047 | Money and stock | Settled | Payment status, not sale completion status | Paid in full |
| COPY-048 | Money and stock | Partially paid | Show amount still owed | Partly paid |
| COPY-049 | Money and stock | Debt allocation | Phase 5 wording reserve only | Which sales this payment covers |
| COPY-050 | Money and stock | Gross profit | Only sale.profit.view; never claim net profit | Profit before shop costs |
| COPY-051 | Money and stock | COGS | Authorized details only | Cost of the products sold |
| COPY-052 | Money and stock | Cost basis unestablished | Never call this verified profit | The cost is not confirmed. This profit estimate may be incomplete. |
| COPY-053 | Money and stock | Negative stock | Do not block otherwise valid sales merely due to negative stock | The recorded stock is below zero. Check the stock records. |
| COPY-054 | Money and stock | Stock Position | Distinguish system records from actual physical count | Recorded stock |
| COPY-055 | Money and stock | Movement ledger | Preserve immutable evidence | Stock history |
| COPY-056 | Money and stock | Reconciliation variance | Later full checking workflow; no blame implied | Difference between the count and the records |
| COPY-057 | Returns and feedback | Return preparation | Original Sale lines only | Review returned products |
| COPY-058 | Returns and feedback | Return quantity | Never exceed current effective eligibility | How many are being returned? |
| COPY-059 | Returns and feedback | RETURN_REDUCTION | Not money received | Amount owed reduced |
| COPY-060 | Returns and feedback | Refund amount | Actual Cash/Transfer split still required | Money to return to the customer |
| COPY-061 | Returns and feedback | Cash refund | Actual money effect | Cash returned |
| COPY-062 | Returns and feedback | Transfer refund | Actual money effect | Transfer returned |
| COPY-063 | Returns and feedback | Compensation | Keep original link and explicit effects | Correction record |
| COPY-064 | Returns and feedback | Reverse sale | Only when all approved inverse effects are confirmed | Cancel this completed sale |
| COPY-065 | Returns and feedback | Reverse Return | Show cash received back and debt restored where applicable | Cancel this return |
| COPY-066 | Returns and feedback | Existing debt restored | Same original Receivable | The customer owes this amount again |
| COPY-067 | Returns and feedback | CORRECTION_DECREASE | Original credit cancellation, not a payment | Original amount owed cancelled |
| COPY-068 | Returns and feedback | Replacement | Separate new unfinished record and confirmation | Corrected sale / corrected return |
| COPY-069 | Returns and feedback | Reversal of reversal denied | No generic delete or automatic Undo | This correction cannot be cancelled here. Open the original record for the available options. |
| COPY-070 | Returns and feedback | Stock dependency | Show permitted linked records; do not suggest bypassing guards | Later stock records affect this sale. Check those records before correcting it. |
| COPY-071 | Returns and feedback | Historical snapshots | Current Product price must not replace history | Prices recorded when the sale was made |
| COPY-072 | Returns and feedback | Reason required | Keep required reason and Audit | Why are you making this change? |
| COPY-073 | Returns and feedback | Finalized Opening Cash required | Owner action; Staff gets Ask your shop owner | Record the shop’s starting cash before saving a cash sale. |
| COPY-074 | Returns and feedback | No products in draft | Clear action | Your basket is empty. Add a product to start. |
| COPY-075 | Returns and feedback | No matching records | Preserve search | No matches found. Try another name or clear the filters. |
| COPY-076 | Returns and feedback | Request pending | Disable repeated financial confirmation | Saving… Please wait. |
| COPY-077 | Errors and support | Invalid amount | Use zero-allowed wording for fields that permit zero | Enter an amount greater than zero, for example ₦500. |
| COPY-078 | Errors and support | Invalid cash baseline | Starting cash permits zero | Enter zero or the amount of cash counted. |
| COPY-079 | Errors and support | Refund mismatch | Amounts from current server review | Return ₦2,000 in total. Cash and transfer must add up to ₦2,000. |
| COPY-080 | Errors and support | Excess payment | No wallet/advance or new change-calculation scope | Record only the amount paid for this sale. Do not include change given back. |
| COPY-081 | Errors and support | Version conflict | Retain entries; do not silently overwrite | This record changed. Reload it and check your entries before saving. |
| COPY-082 | Errors and support | Network error after post | Persistent; no blind new UUID/repost | We could not confirm that this sale was saved. Check its status before trying again. |
| COPY-083 | Errors and support | Success toast | 8-10 seconds, close button, pause while interacting; receipt persists | Changes saved. |
| COPY-084 | Errors and support | Permission denied | Never grant access through the UI | You are not allowed to do this. Ask your shop owner for help. |
| COPY-085 | Errors and support | Session expired | Existing session/security flow | Please sign in again to continue. |
| COPY-086 | Errors and support | Offline | Current phase; do not promise offline queuing | You are offline. New sales cannot be saved right now. |
| COPY-087 | Errors and support | Empty customers | Create action only with customer.manage | No customers yet. Add a customer when you need to record their details. |
| COPY-088 | Errors and support | Deactivate customer | Explain history remains; no delete | Stop using this customer record |
| COPY-089 | Errors and support | Lifecycle | Admin only | Account status |
| COPY-090 | Errors and support | Onboard Tenant | Retain separate business-account and shop fields | Add shop |
| COPY-091 | Errors and support | Activate Tenant | Actual approved lifecycle restrictions still apply | Reopen account access |
| COPY-092 | Errors and support | TLS ready | Details preserve precise platform status | Secure web address ready |
| COPY-093 | Errors and support | MFA | Do not remove second-factor step | Security check |
| COPY-094 | Errors and support | Source evidence | Keep protected links | Original record |
| COPY-095 | Errors and support | Sync generation | Not user task vocabulary | Support details |
| COPY-096 | Errors and support | Server unavailable | Do not assert save failure if outcome is unknown | We cannot reach DawaTrack. Check the connection and try the status check. |
