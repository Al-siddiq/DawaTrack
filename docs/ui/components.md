# Shared component reference — implementation status

**Delivered in UI2-A:** `UiIcon` and `renderUiIcon`, generated icon catalogue, PHP helper, opt-in style/token primitives. Their actual interfaces are in [icons.md](icons.md).

**Design examples only:** reference loading button, modal, sheet, native details accordion, secondary-action disclosure, success timer and input error. They demonstrate proposed states and intentionally make no API calls. They are not exports ready to replace current business workflows. Do not copy timer-driven fake saves into production.

**UI2-B implementation contract:** the following components are planned, not marked complete.

| Component | Interface / ownership | Required behavior |
|---|---|---|
| UiButton | type, variant, icon, loading, disabled, busyLabel; slot/click | One submit path, duplicate guard, stable width, visible busy words |
| UiField / UiErrorSummary | stable ID, label/help/errors; actual caller input | Associated label/descriptions, field links, retained values |
| UiAlert | severity, persistent/dismissible policy; dismiss | Close; safe 8–10s success dismissal with hover/focus pause; important messages persistent |
| UiLoading / UiEmptyState | label/variant; real dataset state | Stable region, accessible status, no invented percentage |
| UiInformationPanel | heading/severity/content | Consequences, setup and refund/debt split visible |
| UiDialog / UiSheet | open, title, close policy, initial focus; close/confirm | One state owner; focus containment/return, background isolation, keyboard-safe, no nested modals |
| UiAccordion | expanded, heading, controls ID; toggle | Essential totals/errors/next action remain outside collapsed content |
| UiDropdown | open, trigger label/items; select/close | Semantic links/buttons; keyboard/Escape; no hidden primary action |
| UiNavigation | registry, current ID/group; navigate | Shared authorized map, `aria-current`, meaningful actual links |
| UiPagination / UiRecordList | page/records/busy; request/render | Responsive cards, correct page/filter lifecycle, stale-read protection |
| UiStatusPanel | actual operation/access state; allowed recovery | Preserve operation identity; no blind write retry |

Use focused Vue `h()` render functions or current precompilation. Do not introduce runtime string templates into the runtime-only app. Consolidate existing `commercial-ui.js`/reason dialog/feedback callers and document compatibility adapters while migrating them. Vue controls open/busy state; Bootstrap JavaScript cannot compete over the same nodes.

B handover must add real per-component examples, all actual props/events/slots/defaults, keyboard behavior, disposal of listeners/timers and caller migrations. C uses them in the shells; UI-3 completes task callers. Documentation is updated with implementation, not postponed until final acceptance.
