# UI2-A manual verification and acceptance

**Owner acceptance: pending.** The engineer has not marked the native Windows/browser/PHP checks passed on the Owner's behalf.

## Setup on the development checkout

Use a clean checkout; preserve any local changes before switching branches. The branch is `codex/ui2-a-tokens-icons-20261108`.

```bash
git fetch origin
git switch codex/ui2-a-tokens-icons-20261108
npm ci
npm run build
npm run ui:reference
npm run ui:preview
```

Open `http://127.0.0.1:4173` on the same machine. The preview server stays running until Ctrl+C; use another terminal if needed. It is independent of MySQL/application sign-in and includes no real customer data. Application pages keep their existing design until C/UI-3.

## Checklist

| Case | Action | Expected result | Owner result |
|---|---|---|---|
| A01 | Run the commands in Windows/XAMPP CMD or PowerShell | Build finishes with `Frontend assets built.`; reference reports 18 screens; no `sh` error | Pending |
| A02 | Open reference and choose all 18 screens | Correct heading/layout; meaningful bottom/sidebar/action icons; labels legible; no clipping | Pending |
| A03 | Inspect Home/Sell/Stock/Customers/Menu on phone examples | Five labeled targets; current state clear; return/history points to Menu | Pending |
| A04 | Open Icon catalogue | 34 symbols in both catalogues, including the actual Vue/local-sprite renderer; consistent family; text beside each; selected sprite stays inside documented budget | Pending |
| A05 | Open Interaction examples and press Save sale twice | “Saving sale…” plus spinner; second activation blocked; sample returns after 2.5s with no request/record | Pending |
| A06 | Enable system/browser reduced motion | Spinner stops rotating; status remains readable | Pending |
| A07 | Open/close confirmation and customer sheet with keyboard; press Escape | Clear heading; background isolated; focus returns; no record change | Pending |
| A08 | Expand details, open/close More actions | Optional details only; main action remains visible; Escape closes open secondary list | Pending |
| A09 | Show success; hover/focus it, then leave it | Close control works; 9s dismissal pauses on hover/focus; saved evidence text stays visible | Pending |
| A10 | Inspect invalid payment and unknown-status examples | Clear persistent correction/recovery; no disappearing warning or automatic retry | Pending |
| A11 | Inspect local requests and block internet access | No CDN/icon-font/remote-kit requests; necessary local assets remain usable after available; no offline sale claim | Pending |
| A12 | Narrow view to 320/360/390px, intermediate and desktop; use 200% text zoom | Reference controls readable and reachable; design specimens scale; real responsive shell behavior remains C | Pending |
| A13 | Inspect actual UiIcon integration with runtime Vue and PHP helper during B/C adoption | Same local sprite, accessible names/default decoration, CSP compatible, unknown names rejected | Pending integration |
| A14 | Sign in to the existing app and open usual allowed pages | Existing functions unchanged; no new UI2 shell or permission/record change claimed | Pending |
| A15 | Review coverage and developer docs | 46 requirements, 29 nav entries, 96 copy IDs, 18 specimens; future work not prematurely accepted | Pending |

When accepted, record actual commit, machine/browser versions, date, case results and Owner confirmation in `docs/ui/acceptance/`. Do not infer acceptance from a build or reference-page screenshot.

UI2-B/C and UI-3/UI-4 expand the strategy's M01–M18 into full application steps: real keyboard/screen reader, Android/Windows, actual permissions/access states, stale previews, unknown writes, receipts/print and all current business flows. UI2-A alone cannot establish those results.
