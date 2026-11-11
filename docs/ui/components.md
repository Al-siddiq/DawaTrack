# Shared component reference — UI2-B

**UI2-B state:** manually accepted by the Owner on 9 November 2026; see the acceptance record. UI2-A remains the dependency. Browser, keyboard, screen-reader and Owner acceptance are not inferred from compilation. Full shell integration is UI2-C; complete task-screen migration is UI-3.

All exports are in `resources/js/components/ui/index.js`, copied unchanged to `public/assets/js/components/ui/`. Use normal Vue `setup()` with `window.Vue`, render functions and the current production runtime. No runtime templates, UI framework or DOM-replacement icon runtime is introduced.

## 1. Component interfaces

| Export / module | Actual inputs | Events / slots / behavior |
|---|---|---|
| UiIcon / `icon.js` | name; size small/action/navigation; optional label | Existing A interface; selected local symbol, decorative by default |
| UiButton / `primitives.js` | type=button, variant=primary, icon, loading=false, disabled=false, busyLabel=Please wait… | click(event); default label slot. Native type/submit semantics; explicit operation loading disables activation; same-tick guard; spinner with words |
| UiField | id or generated ID, required label, help, errors=[], required=false, describedBy | default({controlAttrs}); caller binds id/aria-describedby/aria-invalid to actual input, retains value and native required/type constraints |
| UiErrorSummary | errors=[{id,message}], title, focusOnChange=true | Focus summary after errors change; links focus/scroll actual fields; no raw HTML |
| UiAlert | message, severity=info, dismissible=true, persistent=true, duration=9000 | dismiss(reason); optional default content slot. Only explicit non-persistent success auto-dismisses; 8–10s clamp, pause on hover/focus, cleanup on unmount |
| UiLoading | label=Loading…, variant=spinner or skeleton | Accessible text/region; static under reduced motion; no invented percentage |
| UiEmptyState | title, message, icon=info | Optional default action slot; caller distinguishes no records vs no matches vs failure |
| UiInformationPanel | title, severity=info, message | Optional default content slot; inline important facts, no auto-dismiss |
| UiBadge | required label, severity=neutral, optional icon | Static status text; color is supplementary, no unnecessary live announcement |
| UiDialog / `overlay.js` | open=false, required title, description, busy=false, closable=true, beforeClose(reason), initialFocus(selector), variant=dialog | update:open(bool), request-close(reason), blocked(message), close-error(error); default and footer slots. Controlled native modal with focus/scroll lifecycle |
| UiSheet | UiDialog inputs plus placement=bottom/start/end | Forwards attrs/listeners/slots to UiDialog with sheet styling. Bottom context sheet on phones; start/end placement can support C drawers |
| UiAccordion / `disclosure.js` | required title, expanded optional, defaultExpanded=false, level=3 | update:expanded(bool); default content. Controlled if expanded supplied; otherwise internal. aria-expanded/controls and hidden panel |
| UiDropdown | label=More actions, items=[{id,label,icon?,href?,disabled?}], open optional | update:open(bool), select(item,event). Normal links/buttons, not a partial ARIA menu. Click/Tab/arrow/Home/End/Escape/outside-pointer behavior |
| UiNavigation / `records.js` | items=[{id,label,icon?,href?,expanded?}], currentId, currentGroup, label, variant=sidebar/bottom | navigate(item,event). Parent supplies only authorized entries and trusted destinations. Actual anchors retain link behavior; buttons support Menu/local action. No internal router/permission engine |
| UiPagination | page=1, totalPages=0, busy=false | page-request(number); Previous/Next boundaries and busy state |
| UiRecordList | records=[], recordKey=public_id or function, busy, error, loadingLabel, emptyTitle, emptyMessage | record({record,index}) slot. Stable keys required; current records may remain during a read; no false empty state on error |
| UiStatusPanel | state=ready/loading/unknown/offline/readonly/denied/error/conflict, message, checking=false, canCheck=true | check-status event; actions slot for other states. Unknown state exposes only status checking, never generic retry/new-record actions |

All components render trusted text through Vue. Do not pass user-provided markup, href destinations, icon names or CSS variants directly. Use the controlled inputs/events as documented, not parallel Bootstrap JS initialization. The `.ui-*` class family is opt-in; button/field primitives can be used inside existing screens without adopting a new whole-page shell.

### Button and field example

```js
import { UiButton, UiField } from '../components/ui/index.js';
const { h, ref } = window.Vue;
const amount = ref('');
// Inside setup/render, with caller-owned pending state and submit handler:
h('form', { onSubmit: save }, [
    h(UiField, { id: 'paid-cash', label: 'Paid in cash (₦)', required: true, errors: fieldErrors.value }, {
        default: ({ controlAttrs }) => h('input', {
            ...controlAttrs, value: amount.value, required: true, inputmode: 'decimal',
            onInput: event => { amount.value = event.target.value; },
        }),
    }),
    h(UiButton, { type: 'submit', loading: pending.value, busyLabel: 'Saving sale…' }, () => 'Save sale'),
]);
```

The submit handler must prevent default, check its own per-operation guard and use existing domain/API logic. Do not attach the same write to both click and form submit. A button's UI guard is not server duplicate safety. Native constraint validation still runs unless the caller deliberately handles it separately.

### Controlled dialog / sheet example

```js
h(UiDialog, {
    open: dialogOpen.value,
    title: 'Review this change',
    busy: pending.value,
    initialFocus: '#action-reason',
    'onUpdate:open': value => { dialogOpen.value = value; },
    beforeClose: () => !hasUnsavedChanges.value,
    onBlocked: () => { dialogOpen.value = false; },
}, {
    default: () => formContents(),
    footer: () => actionButtons(),
});
```

Use a syntactically valid selector limited to the dialog. If close must be denied, keep the same dialog open and display inline Keep editing/Discard choices; do not nest a second modal. Parent-initiated `open=false` is an explicit programmatic close and bypasses beforeClose. Busy prevents interactive Escape/close controls. Callers must not close a submitted write as if it had been cancelled.

Native `<dialog>.showModal()` provides top-layer background isolation and focus containment in supported browsers. The component captures the trigger before awaiting render, focuses the configured field or heading, restores a connected trigger and uses a body class for scroll locking. A module-level owner rejects a second modal and emits blocked; handle it by resetting the attempted open state. Native close events are reconciled with the controlled state. Listeners/timers are disposed on unmount. Do not retain sensitive one-time output in a closed slot; content is removed when open=false.

## 2. Request-state helpers

### useUiTask({kind='read'})

Returns `pending`, `status`, `error`, `run(work)`, `resolveOutcome(outcome)`.

- `run` owns one operation. Concurrent calls return the same in-flight promise; the callback runs once.
- Successful result is returned unchanged; caller receives rejected errors and must handle them. This helper does not call an endpoint, create UUIDs or save records itself.
- Write errors with status 0, 5xx, 403, 404 or AbortError/TimeoutError are conservatively unknown, consistent with current commercial recovery practices. Caller keeps the domain identity and existing authoritative status flow.
- Unknown writes reject another run until an authoritative status result is supplied to `resolveOutcome('saved'|'not-saved')`. No timeout-based automatic reset/retry exists.
- The helper does not cancel a server write on unmount and does not add persistence, Phase 10 receipts or financial calculations. Durable refresh/recovery remains the existing domain workflow's responsibility.

### useUiList(fetchPage,{perPage=20})

Returns `rows`, `pending`, `error`, reactive `pagination`, `load(page=1,query=currentQuery)`, `setQuery(query)`.

`fetchPage(query,signal)` must return `{rows,pagination}` and use the existing read API. Superseded reads are aborted and generation-checked: even a transport ignoring cancellation cannot overwrite the latest result. `setQuery` resets to page 1; paging keeps the current query. Unmount aborts/invalidate reads and prevents late list-state writes. Never use this helper for mutations. Caller clears protected rows when authority changes; the list is not an authorization filter.

## 3. Real application integration

| Caller | Delivered use | Preserved behavior |
|---|---|---|
| `commercial-ui.js` adapters | UiButton, UiField, UiAlert, UiPagination | Existing field/action/submit/feedback/pager signatures, values, handlers and API ownership |
| `customer-panel.js` | UiLoading, UiRecordList, UiEmptyState via list, UiInformationPanel, useUiList, explicit busy submit | Customer permissions/private fields/IDs/create/update/deactivate payloads and reasons |
| `sale-draft-panel.js` | UiStatusPanel for the existing uncertain-sale path | Same draft identity, existing open/status request, offline/pending guard and no automatic repeat |
| Shop Staff confirmation | UiDialog, UiButton, UiAlert | Same status/grant/revoke/reset actions; 500-character reason rule; one-time credentials and Owner gates |
| Component reference | Actual exports with local fixtures | Explicit example-only delays/data; no business requests |

Legacy feedback adapters intentionally keep success notices persistent because some contain required financial next steps. Only explicitly reviewed ordinary success may set `persistent=false`. Later task migration reviews each caller. This is a documented compatibility policy, not a timer applied indiscriminately to financial messages.

Remaining legacy inventory evidence overlays and other bespoke task markup are not silently counted migrated. They remain tracked for UI-3. C integrates navigation/shells; UI-3 completes task layouts/copy and all remaining shared-component callers.

## 4. Verification and maintenance

Use [UI2-B manual verification](manual-verification-ui2-b.md) and [delivery report](UI2-B-delivery.md). Build compilation and server-side render inspection do not establish native dialog behavior, real HTTP flows or accessibility. Native browser, supported Android/Windows, keyboard/screen reader and actual permission/financial checks require manual verification.

Keep source/generated public JS and compiled renderer/CSS coherent. New icons use A's allowlist/generator; no new icon runtime. Update this interface reference, manual cases and coverage register in the same PR as component changes. Do not reintroduce ad hoc focus traps and conflicting global handlers around shared dialogs.

New production/shared default wording is tracked as COPY-097 through COPY-122 in the wording register; all original 96 entries are retained. Reference-only fixture instructions are not production messages. Dynamic “Close {title}” and pagination labels follow these component patterns.

## UI2-C shell component

`WorkspaceShell` is imported directly from `components/ui/workspace-shell.js` to keep the UI barrel free of a self-import cycle. Its controlled inputs are title, parent, backHash, brandName, logo, userName, roleLabel, area, authorized items, bottomItems, currentId/currentGroup, navigation controller and trackEdits. Its default slot is the current task. It delegates all navigation decisions to the controller and B dialog/sheet primitives; it never authorizes a business write. `WorkspaceHome` emits navigate(hash) and reads only existing authorized Home datasets. See workspace-shells.md for the complete C contract and verification limits.

## UI3-A task integrations

Trading panels use the existing shared components with `components/trading-ui.js` helpers for labeled selects, server-valued facts, stable error summaries and guarded local confirmation. `ProductsPanel`/`IntakesPanel` replace inventory task markup; saved receipt printing explicitly strips internal nodes. See [trading reference](trading-screens.md). `UiStatusPanel.setup` now captures `emit` so its existing `check-status` event is callable. This fixes the recovery action exposed by the sale-handler review; its API is unchanged.
