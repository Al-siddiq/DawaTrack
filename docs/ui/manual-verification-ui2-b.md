# UI2-B manual verification

**Branch:** `codex/ui2-b-shared-components-20261109`. **Dependency:** UI2-A PR #28. **Owner results:** pending. Authorization to advance to B was received on 9 November 2026; it is not an invented record of each native A verification result.

Use a development/test Shop and dedicated test Staff account for sensitive Staff operations. Do not reset a real colleague's credentials or alter real money merely to inspect a component. No new migrations are needed.

## Build and reference

```bash
git fetch origin
git switch codex/ui2-b-shared-components-20261109
npm ci
npm run build
npm run ui:reference
npm run ui:preview
```

Open http://127.0.0.1:4173 on the same computer, then **Interaction examples**. These are the actual shared components with local sample data. No sale/customer/business API is called by the reference. Ctrl+C stops its server. Check real application flows separately through your usual XAMPP address/sign-in.

| Case | Steps | Expected result | Actual result |
|---|---|---|---|
| B01 | Build on Windows, open all three reference sections | No shell-only build dependency; 18 designs remain available; icons load locally | Pending |
| B02 | Press Save sale repeatedly in the example | Busy words/spinner; same operation once; disabled while pending; stable action width | Pending |
| B03 | Enable reduced motion, repeat B02 and inspect skeleton | Static cues and readable text; no decorative rotation/pulsing | Pending |
| B04 | Submit invalid payment, follow summary link, correct to 7,500.00 | Summary/field associations correct; focus reaches input; entered value retained; specific correction | Pending |
| B05 | Show success, hover/focus >9s, then leave it | Timer pauses, resumes, close works; saved evidence remains | Pending |
| B06 | Inspect unknown/offline/readonly/error message patterns | Important status persistent; no automatic dismiss/retry or false offline queue | Pending |
| B07 | Open confirmation with keyboard; Tab/Shift+Tab | Title announced, focus inside, background unavailable, visible Close/Keep controls | Pending |
| B08 | Press Escape/close and repeat open/close | Safe close returns to the original trigger; body scrolling restored; no accumulated handlers | Pending |
| B09 | Show busy in confirmation and attempt Escape/Close | Does not dismiss a write in progress; caller loading state remains visible | Pending |
| B10 | Open customer sheet, enter note, attempt close | Guard keeps it open and explains unsaved note; Discard closes explicitly | Pending |
| B11 | Inspect sheet on narrow phone and keyboard-open layout | Reachable fields/actions; scrollable; start/end drawer styling available for C | Pending |
| B12 | Expand accordion and use More actions via keyboard | Essential facts outside; Tab/arrows/Home/End/Escape work; disabled item not activated; outside closes secondary list | Pending |
| B13 | Search sample customers rapidly, then page | Latest query wins even if an older delayed read completes; page resets on search; stable rows; no false empty state while loading | Pending |
| B14 | Show lost response, try it again, check saved status | Another write blocked; only explicit status check resolves sample; no new identity or automatic retry | Pending |
| B15 | Open actual Customers as allowed Staff/Owner; search/page/create/edit in test data | Same visibility/grants/private fields; shared controls/loaders work; validation retains data; write submission guarded | Pending |
| B16 | Open Customers with view-only/no private-data grant; inspect errors/offline | No write/private-data leakage; error differs from empty list; offline blocks existing write paths | Pending |
| B17 | Open test Staff status/grant/revoke dialog as Owner | Shared native dialog; exact current reason validation and permissions; no change until confirmed; cancel preserves state | Pending |
| B18 | Reset only dedicated test Staff credentials; copy once and close | Loading protected; one-time credential shown, cleared on close; current session revocation behavior preserved | Pending |
| B19 | Inspect existing uncertain-sale state in safe test setup | Existing identity/status route reused; check disabled offline/pending; no duplicate/new sale | Pending |
| B20 | Keyboard/screen reader, 320/360/390px, tablet/desktop, zoom and long text | Component labels/focus/reflow usable; no clipped essential controls; no CSP/console runtime errors | Pending |
| B21 | Navigate away/unmount from component callers and return | No leaked alert timers, list requests, dropdown listeners or stuck body scroll lock | Pending |

Record date, commit, OS/browser, expected/actual results and defects. Existing Phase 4 manual business checks remain authoritative; shared control adoption must not change totals, stock, debt/refund effects, immutable evidence or duplicate-safety behavior. UI2-C/UI-3 still own full navigation/task migration; do not mark that work done based on this reference.
