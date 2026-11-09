# UI2-C workspace shells and navigation reference

**Date:** 9 November 2026. **State:** implemented for review; Owner acceptance pending. Dependency: accepted UI2-B commit `aaa93d9c3bb984ea973fbad91250662865c9e899`, PR #29.

## Architecture and entry points

| File | Responsibility |
|---|---|
| `shared/workspace-registry.js` | Single Shop/Admin destination catalogue, label, semantic icon, current group, existing capability/grant and parent |
| `shared/workspace-navigation.js` | Current page/hash, guarded requests, browser hash/history handling, domain replacement notices and unload guard |
| `components/ui/workspace-shell.js` | Persistent desktop sidebar, compact drawer, task header, bottom bar, current user and sign-out |
| `components/workspace-home.js` | Actual authorized saved baskets, task shortcuts and initial cash prerequisite, using current read endpoints |
| `shop/app.js`, `shop/template.html` | Adopt shared shell while retaining existing task bodies, service payloads and field permissions |
| `admin/app.js` | Separate shell/sections over current onboarding, Shop operations, plans, domains and audit |
| `app/Views/shop/index.php`, `admin/index.php` | Escaped current user/branding and server-evaluated Admin grant metadata; no secrets or new auth authority |
| `app/Views/layouts/shell.php` | Workspace mount, CSP-nonced layout measurement style and self-hosted runtime; removes duplicate legacy header/footer only for workspace mounts |
| `app/Views/auth/*`, `auth/entry.js` | Branded native entry forms, visible area, busy submit and password visibility; existing Shield/CSRF/recovery/MFA unchanged |
| `resources/css/ui/_workspace.scss` | Shell, compact sizing, safe areas, responsive breakpoints, keyboard and print handling |

Paths under the first seven client rows are relative to `resources/js/`. The application still uses runtime-only Vue and its existing template compiler, no Router/Pinia/app bundler. `npm run build` regenerates renderer, CSS and public copies; do not edit generated output directly.

## Shop map and authority

The registry includes Home; Sell; Stock; Customers; Sales; Stock deliveries; Staff; Shop details; Starting cash; Shop appearance; Web address; Plan; Activity. All current targets remain reachable.

- First filter actual server `capabilities`; then enforce access-mode restrictions. BLOCKED/CLOSED permit only the existing Home/authorized Activity routes. This also prevents an unauthorized Staff Activity link from appearing just because a mode is blocked.
- FULL/READ_ONLY preserve actual service restrictions: write actions retain existing grants and `readOnly` conditions. No new permission grants are inferred from names or icons.
- Phone/intermediate: Home / Sell / Stock / Customers / Menu, omitting unavailable destinations. Menu opens the start-side sheet using B's shared native dialog. Sales/returns/settings select Menu as their current group; deliveries select Stock.
- Desktop: persistent brand/sidebar/account; work links followed by existing Owner sections. Stock deliveries is reached inside Stock, not a second unrelated global work module. Settings links are grouped under Shop settings. No desktop duplicate bottom bar.
- There is no new Customer portal, Owner impersonation or general account-management service. Password-change remains the existing required security flow; no misleading general “My account” destination is invented.

## Hash contract

| Hash | Existing target |
|---|---|
| `#home` | Task Home |
| `#sale-drafts` | Unfinished-sale list |
| `#sale-drafts/new` | Explicit New sale action, only when current sale-record/full/online conditions allow it; otherwise return to the permitted list |
| `#sale-draft/<existing UUID>` | Existing saved basket identity |
| `#sale-history`, `#sale/<existing UUID>` | Existing history / saved sale, return or correction detail |
| `#products`, `#intakes`, `#customers` | Existing stock/delivery/customer panels |
| `#staff`, `#settings`, `#opening-cash`, `#branding`, `#domains`, `#subscription`, `#audit` | Existing authorized Owner panels |

Existing domain `replaceState` calls now use `announceWorkspaceHash(hash)` with the same ID/format so the shell follows authoritative saved-record changes. The helper does not generate a new record, post a transaction or bypass service permission.

`useWorkspaceNavigation({items,fallback})` returns currentId/current/hash refs, pending confirmation/message refs, request(hash,{replace,skipDirty}), confirmLeave(), initialize(). `request` validates a current allowed destination before changing it. Capture-phase hash handling checks navigation before old panel hash listeners run; same-page approved changes replay once for existing record handlers. New page components read the existing hash on mount. Same-page record changes wait for an already active record read; unmounted record panels cannot announce a late response back into the new task. Invalid/unavailable links show a clear warning and a safe allowed page. Modified link clicks retain native browser behavior.

Programmatic `announceWorkspaceHash` is reserved for existing domain workflows after they select/save/adopt a record; ordinary workspace links use guarded request. Internal bespoke task buttons retain their current behavior pending UI-3; this package does not claim every possible internal edit transition has been migrated.

## Unfinished edits, writes and context changes

`useNavigationGuard(check)` registers a current component-owned dirty/unknown check and unregisters on unmount. A hard condition gives a persistent next-step message; ordinary unsaved state asks Keep editing / Leave without saving through the shared dialog. Confirmation waits for its own dialog to close before navigating. Existing open native/legacy dialogs must be closed before changing tasks; no nested navigation modal is opened over them.

The API wrapper exposes the active mutation count for navigation guards. It still uses the same URL/method/body/credentials/CSRF/normalization/timeout behavior. Counting does not retry, cancel, log or change a write. A JSON-success response may notify the originating form to update presentation-only dirty tracking.

Legacy editable task sections use input revisions rather than copying credentials. Successful form notification clears only unchanged controls from that submitted form snapshot; edits made afterward remain marked. Search/filter inputs are excluded. Password tracking stores change/empty state, not copied password text. There is no localStorage, IndexedDB or analytics log for this feature.

Commercial panels supply domain checks for unsaved basket/payment/customer/return/correction state and unresolved operation outcomes. A write in flight or uncertain financial outcome blocks ordinary workspace navigation until its existing status flow resolves it. Forced browser closure is ultimately controlled by the browser/user; the unload warning does not promise durable offline storage. Existing UUIDs/status endpoints remain authoritative.

On focus/visibility return, Shop refreshes only the current context/permissions/access mode, retaining current task state for unchanged authority. If the current page is no longer permitted, it moves to a safe permitted page and removes the inaccessible task. Services still revalidate every request. Existing session-expiry/MFA redirects notify the guard so a new unload warning cannot block the required security redirect; redirect URLs/protocol remain unchanged. Admin grants are server-rendered bootstrap hints; stale server denials remain authoritative, and a page reload refreshes its bootstrap. Client metadata is never authorization.

## Home data and operation boundaries

Home calls current unfinished-sale and starting-cash read endpoints only when their targets are authorized. It displays actual basket customer, amount and saved timestamp, plus explicit Continue links. It never silently selects among multiple baskets. New sale is shown only with sale-record/full access. Cash setup is Owner-gated and keeps the one initial/current-generation baseline semantics. No daily cash reset, financial dashboard totals, low-stock automation, report charts or new phase feature appears.

Read failures show a retryable read message; they are not misrepresented as an empty collection or saved success. Other destinations load their existing data when selected instead of fetching every Owner dataset on startup. Cold data, read-only/blocked/closed and unauthorized direct links all remain part of manual acceptance.

## Admin separation

Existing server `user->can()` results supply only the current platform grant names in escaped metadata. Navigation uses those grants; it never borrows Shop membership. Shops shows the current operations listing/lifecycle controls; Plans uses existing subscription operations, not an invented plan catalogue. Add shop retains its details/review/result sequence. Domains and Activity use existing APIs and scoped support flows. Write-specific controls retain corresponding platform grants and backend checks/MFA.

Leaving the onboarding section clears the temporary Owner password after the navigation decision; no credential is saved as a navigation preference. No new account lifecycle API, role switcher or Customer login is added.

## Layout, branding and entry forms

The header/side/bottom layout uses moderate type and spacing. At 1200px+ the sidebar is persistent; smaller screens use Menu and, for Shop, fixed bottom navigation. Space is reserved for that bar. ResizeObserver measures actual header/bottom height and writes only numeric CSS variables to a server-nonced style element; fallback sizes remain. No CSP-sensitive inline style binding or external icon service is introduced.

Keyboard behavior uses `interactive-widget=resizes-content` where supported and visualViewport sizing where an overlay keyboard reduces the view. On those overlay-keyboard layouts the bottom bar hides while typing, with header Menu still reachable; content remains scrollable. Actual Android/iOS/Windows/zoom behavior requires native verification.

The existing Shop context response now includes the optional presentation field `current_user.display_name`, so refreshed current-user details do not depend on stale initial markup. No password/email/token or membership authority is added. Server-resolved branding/logo remains. A validated brand primary is used only at a conservative white-text contrast threshold that also protects the selected-nav surface; otherwise the accessible default is used. Logo/name remain visible independently. Print excludes new navigation and preserves the accepted receipt-specific print flow.

Auth remains server-rendered, with visible Shop/Admin area and current branding. Entry enhancement uses no Vue compiler and reads no password value: it adds show/hide controls, protects repeated native form submission and restores buttons/masks on BFCache return. CSRF fields, required password policy, configured recovery availability, email sign-in, forced password change, route/session boundaries and Admin MFA are unchanged. Recovery links are displayed only when their existing configuration enables the flow.

## Integration limits

UI2-C completes the shared shell/navigation foundation. Existing task bodies still contain legacy wording/layout to migrate in UI-3; those are not hidden or falsely counted as redesigned. All new paths and base styling are real production integration, not the A/B specimen gallery. Native HTTP, CSP, device, keyboard/screen-reader, print and full business regression require the accompanying manual checklist before C acceptance.
