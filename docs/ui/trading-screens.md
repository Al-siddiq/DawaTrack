# Trading screens developer reference

UI3-A, 10 November 2026. Implemented for review; native manual acceptance pending.

## Boundaries and ownership

The accepted workspace shell still owns authorized destinations, context refresh, global navigation and active-write guards. `shop/app.js` retains the existing stock composables and passes their reactive state to `ProductsPanel` and `IntakesPanel`. These replace the old inventory template bodies, not their APIs/services. New code uses ordinary Vue JavaScript and `h`; no Router/Pinia/compiler dependency is added. Run the normal build after editing `shop/template.html`, source modules or Sass and commit generated files.

| Module | Responsibility |
|---|---|
| `components/trading-ui.js` | Label-only enum mapping; server-value facts; labeled selects; stable linked error-summary arrays; local decisions and guarded internal transitions |
| `components/commercial-ui.js` | Existing API/decimal-string adapters; allowlisted semantic action icons; plain copy for known machine errors; preserved details/support reference; safe-success timer policy |
| `components/products-panel.js` | Product list/form/detail, thresholds, adjustments, inactive state, history/paging and native stock-record sheet |
| `components/intakes-panel.js` | Delivery/starting-stock drafts/items/review/saved changes/cancellation/replacement; independent active-product lookup |
| `components/sale-draft-panel.js` | Existing sale draft lifecycle/IDs; larger-screen product/basket + payment columns; phone single column; review remains visible |
| `components/customer-panel.js` | Existing customer permissions/private fields, validation, inactive action and local edit protection |
| `components/sale-history-panel.js` | Existing kind/date/search/paging and detail adoption; guarded Back/Reload |
| `components/return-editor.js` | Existing original-item quantities, authoritative calculations, refund split/reason/review and same-ID status recovery |
| `components/commercial-actions.js` | Existing return/cancellation/replacement endpoints, review fingerprints and lineage; guarded linked navigation |
| `components/sale-receipt.js` | Saved evidence; internal authorized cost/profit; explicitly filtered customer print copy |
| `shared/workspace-navigation.js` | Exposes read-only `inspectWorkspaceIssues(hash)` over the same guard registry used by the shell; no new navigation owner |
| `css/ui/_trading.scss` | Scoped task layouts/forms/facts/cards/confirmation/receipt print; imports after brand styles |

## Public helper contracts

`selectField(id,label,value,update,choices,options)` takes `[machineValue, visibleText]` pairs. Machine enum/ID values stay unchanged. `facts([[label,value],...])` formats no money and calculates no totals: callers supply server-derived values or existing exact helpers. `stateLabel(value)` is presentation only; no label is sent as an API enum.

`fieldSummary(details,mapping)` maps server field keys to real input IDs. A WeakMap caches an identical errors array until errors/mapping change, so ordinary typing does not repeatedly focus the summary. Inputs keep their value, native validity and associated inline errors. Errors without a known input remain summary text. Do not turn a generic validation example into a different business rule.

`useTradingConfirm()` returns `ask(options)`, `guard(work,hash)` and `render()`. Ask resolves false on cancellation, blocked overlay or unmount; it closes and waits a Vue tick before continuing. Guard consults the existing active-write/unknown/dirty registry; hard blocks show a persistent warning; dirty state asks before continuing. This is a local UI decision helper, never a mutation retry/identity layer. Do not call ask inside an already open modal. The stock sheet uses its own inline unsaved-reason close choice and a reason + explicit final undo button; no nested modal is created.

The shell's legacy-form revision tracking still protects stock form edits. Trading domain guards retain basket/payment/return/correction state. Same-page hash replay after the shell's approved decision bypasses a second basket-Back prompt. Direct internal Back/Reload/edit/linked-record transitions use local guards where they would replace current entries. Explicit Cancel/Discard always names the loss/consequence.

## States, requests and authority

- Retain every current server capability/permission/access-mode gate. Product management, stock adjustment, stock intake and stock reversal remain distinct grants; a Staff user with reversal permission does not need intake permission merely to use existing reversal/replacement operations. Reads and writes still fail closed at the server.
- Lookup search is read-only; delivery product lookup uses `useUiList` with a request generation and AbortController. Product list/detail/similar and intake list/detail reads ignore superseded results. No write shares these controllers.
- Mutation buttons use accepted disabled/busy controls; successful commands keep their original IDs/version/fingerprint payloads. Decimal money/quantity strings are not converted to JavaScript floats. Only existing integer item-order fields use Number. Existing BigInt stock-change preview is unchanged.
- Sale/return/cancellation/replacement unknown outcomes keep the existing status-read identities and guards. The shared status panel's emitted check-status action is wired. Delivery posting adds a presentation guard and GET of the same existing delivery ID after an uncertain result; it clears only after a successful detail read. No fresh operation ID, automatic POST retry or generic financial retry is created.
- Delivery Review waits for saved header details and for the current item to be saved/cancelled. Review adds an explicit checkbox before the existing post call. Server cost/eligibility/version checks remain authoritative.
- Do not interpret an aborted/timed-out response as proof a write failed. Existing Phase 3 adjustment/create contracts do not expose a new generic idempotency/status service here: inspect existing saved records before manually repeating an uncertain stock command. UI3-A does not invent a new recovery endpoint.
- Known backend error codes get plain text, with existing normalized field details and support reference retained. Unexpected server failures receive a safe general message; no raw exception/SQL is intentionally surfaced. Error and unknown notices persist.
- Only exact safe notices (`Customer saved.`, `Product saved.`, `Product created.`, `Low-stock level saved.`) use a nine-second dismissible timer. Pause/dismiss behavior is the existing shared component. Business outcomes/next-step messages remain until dismissed.

## Copy decisions

Use the actual base unit (`tablet`, `bottle`, `sachet`, etc.), rather than assuming every product is a pack. “Quantity” and “Price per unit” therefore replace the specimen's generic “How many packs?”/“Price per pack.” “Unfinished” is used for drafts; “Saved” describes a permanent record. “Amount left to pay” means the original sale amount, not a live customer balance; collections/statements are future features. “Cancel” means linked inverse records; it never means deleting saved history. Original timestamp values stay explicitly UTC or device/shop calendar as the current API contract requires.

Keep original COPY-001…096 IDs in the catalogue; UI3-A dispositions/paths are recorded without marking remaining Owner/Admin/Auth or future-feature terms complete. New trading strings have additional IDs. API code and stored enum names remain untouched.

## Receipt/privacy contract

Authorization still requires both `profit_visible` and `sale.profit.view` for cost/profit nodes. Private phone/notes are not added to receipts. Customer print builds a transient clone and **removes** `.receipt-private`, `.receipt-stock-evidence`, buttons and transient alerts before `window.print`; hiding only through CSS is insufficient. Print retains product quantities/prices/total/payment/amount-owed evidence and original reference. It labels original unpaid amount clearly rather than claiming a current balance. The original live screen is not changed. Cleanup runs in finally. Print/CSP/paper sizes and browser timing remain manual verification.

## Maintenance and deferred work

No migrations or backend commercial code change in UI3-A. Update source/public copies through `npm run build`, developer docs, wording and coverage registers together. Merge the delivery against main with its explicit missing-UI2 prerequisite. UI3-B and UI4 remain distinct packages; no Phase 5 implementation begins here.

## UI4 direct Print privacy

Global receipt print selectors now hide private cost/profit/internal stock sections even when the user chooses browser Print/Ctrl+P rather than the app button. The clone still removes those nodes. Both native paths/paper sizes require M15 evidence; a source fix is not a native print pass.
