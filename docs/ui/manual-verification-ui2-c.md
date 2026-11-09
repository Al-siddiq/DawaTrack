# UI2-C manual verification

**Branch:** `codex/ui2-c-workspace-shells-20261109`. **Dependency:** accepted UI2-B PR #29 (itself stacked on #28). **Status:** pending Owner verification. No database migration is introduced.

## Prepare the development checkout

```bash
git fetch origin
git switch codex/ui2-c-workspace-shells-20261109
composer install
npm ci
npm run build
```

Open the **normal XAMPP development Shop address**, sign in, and reload. Unlike the earlier component gallery, this package changes the actual application shell. Use `/platform-admin` through the existing Admin sign-in/MFA path for Admin checks. The local `npm run ui:preview` remains a component/design reference, not the live Shop or proof of HTTP acceptance.

Use a test Shop with Owner, restricted Staff, test customers/products, unfinished and saved sales, and a dedicated test Admin. Preserve real records; do not change a live plan or credentials just to inspect a screen. Record actual browser/OS/device versions, commit, expected/actual results and defects.

| Case | Steps | Expected result | Owner result |
|---|---|---|---|
| C01 | Build on native Windows/XAMPP and sign in normally | Build finishes; no missing module/icon/PHP/CSP errors; actual new shell appears | Pending |
| C02 | Inspect Owner desktop at 1280px+, scroll a long task | Persistent brand/sidebar/account, sticky task header, no duplicate bottom bar | Pending |
| C03 | Inspect affordable Android/phone portrait and landscape | Home/Sell/Stock/Customers/Menu icons plus labels; Menu opens/closes; targets readable/reachable | Pending |
| C04 | Use intermediate/tablet widths and resize through 1200px | Drawer replaces squeezing sidebar; no hidden essential destination | Pending |
| C05 | Traverse every existing permitted destination | Correct page/title/selected group; Stock links reach deliveries/starting stock; settings links retain their gates | Pending |
| C06 | Use restricted Staff with different view/write/private grants | Same Shop layout, only allowed destinations/actions/data; no Owner Activity/settings leak | Pending |
| C07 | Use actual READ_ONLY/BLOCKED/CLOSED fixtures | Correct existing restrictions; blocked Staff does not get Owner Activity; no prohibited reads/writes | Pending |
| C08 | Change allowed grants/access through supported test operations, return focus | Context refresh updates permissions/access; unauthorized current task removed; unchanged authority keeps task edits | Pending |
| C09 | Open direct `#products`, `#customers`, settings and unavailable hashes | Valid links select their task after reload; invalid/unavailable link gives safe page/message | Pending |
| C10 | Open `#sale-draft/<existing UUID>` and `#sale/<existing UUID>`; reload | Original record identity/detail retained; no new record created by ordinary detail load | Pending |
| C11 | Use Back/Forward between list, saved basket/detail and other tasks | Correct record/page/current group; no repeated hash loop or double record request caused by replay; slow reads cannot pull the user back from another task | Pending |
| C12 | Edit unsaved basket/payment/customer/return, try workspace navigation | Keep editing retains input; Leave without saving waits for dialog closure and then changes task; no silent loss | Pending |
| C13 | Submit a real safe test change with delayed response, attempt navigation | In-flight mutation guarded; no UI cancellation/retry/second UUID; outcome shown through existing workflow | Pending |
| C14 | Exercise existing uncertain commercial outcome in a controlled test setup | Check status remains required; no navigation-triggered duplicate write | Pending |
| C15 | Edit legacy settings/stock form, save, then navigate; also edit after submit | Confirmed unchanged submitted controls stop being dirty; later edits remain protected; unrelated forms not cleared | Pending |
| C16 | Close/open Staff/native and legacy evidence dialogs, then navigate | One navigation/dialog decision at a time; focus returns sensibly; no stuck scroll lock | Pending |
| C17 | Inspect Home with zero, one and several saved baskets | Real data only, explicit Continue for each, View all list, no automatic ambiguous selection or report metrics | Pending |
| C18 | Click New sale from Home and inspect starting-cash prerequisite | Explicit authorized creation uses existing flow; view-only/offline direct New hash returns to permitted list; cash setup stays Owner-only and is not daily reset | Pending |
| C19 | Force Home/context read failure safely | Error/reload action visible; not presented as empty list or active unauthorized navigation | Pending |
| C20 | Sign in to Admin through current MFA; traverse sections | Separate Admin workspace; no Shop Sell/stock mutation/impersonation; current onboarding/operations/domain/activity services intact | Pending |
| C21 | Inspect Admin grants, Plans vs Shops, onboarding review/errors and leave/re-enter | Correct controls/sections; no fake plan catalogue; temporary password cleared on leave; no misleading global success/rollback claim | Pending |
| C22 | Shop login/recovery/mandatory password change; Admin expiry/security check/denial | Correct branding/area; email/password/CSRF/remembering/recovery setting/policy/MFA preserved; required security redirects not blocked by a new unload warning; no missing old Vue mount | Pending |
| C23 | Toggle password visibility, submit once repeatedly, return with browser Back/BFCache | Accessible controls; no copied/logged credentials; busy button resets; password remasked | Pending |
| C24 | Open mobile keyboard, zoom 200% and 400%, use long names and 320/360/390px | Fields/actions remain reachable; measured header/bottom space; overlay keyboard may hide bottom bar while header Menu remains; no routine horizontal scrolling | Pending |
| C25 | Keyboard/skip link/screen reader through sidebar/drawer/header/confirmation | Meaningful current destination; logical focus; Escape/cancel safe; heading/trigger focus restored | Pending |
| C26 | Use changed/invalid logo and bright/dark branding | Name remains clear; default accessible action color where needed; no unsafe CSS/remote icon request | Pending |
| C27 | Print/reprint existing saved sale/return receipts | New sidebar/header/bottom excluded; references/private cost gates remain as accepted | Pending |
| C28 | Recheck accepted Phase 4 cash/transfer/debt/returns/correction paths | Same financial/stock/audit/identity rules; task migration remains UI-3, not new business behavior | Pending |
| C29 | Reload/long-lived tab/staged deployment and rollback | Coherent HTML/JS/CSS/sprite release, no mixed renderer or missing asset; no data rollback | Pending |

Owner acceptance is recorded only after confirmation. UI-3 still covers remaining task-body presentation/copy migration; C acceptance does not declare the whole UI programme complete.
