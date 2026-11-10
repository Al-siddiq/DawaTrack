# Interaction states and invariants

A provides visual examples, not production business-state rewrites. The approved strategy §§6–8 contains the full contract. These requirements govern B/C and task migration.

- **Loading reads:** show the affected region and clear text; preserve usable existing content/navigation. No entire-app lock for a small search. Stale read results must not overwrite a later search.
- **Saving:** disable the actual submitting operation; show “Saving sale…” or its real task wording plus a spinner; keep the action width stable. Avoid both click and submit dispatching the same operation. Server duplicate safety remains authoritative.
- **Outcome unknown:** a timeout/abort does not mean a write failed. Preserve the current identity and basket, show persistent status recovery, never create a new UUID or automatically repeat a financial write. Use existing domain-specific status mechanisms; do not invent Phase 10 receipts.
- **Validation:** retain values; link error summary to fields; provide a practical correction, not raw exceptions. Use existing exact-money helpers and server previews, never new floating-point financial rules.
- **Success:** announce politely; close control; ordinary success dismisses after 8–10 seconds with pause on hover/focus. Saved evidence stays. Critical payment/recovery instructions do not disappear.
- **Read-only/blocked/closed:** retain the actual mode and server conditions. Do not turn the mode into a generic “all view routes permitted” flag. A Plan link appears only with real authority and permitted route.
- **Offline:** local icon availability is distinct from transaction availability. Do not show queued/saved success for unsupported offline writes.
- **Dialogs/sheets:** clear title and Cancel/Close; focus on opening, contain focus, isolate background, restore trigger, preserve edits. Escape when safe. A closed window does not cancel or roll back a write.
- **Accordions/disclosures:** optional extra information only; keep essential totals/errors/actions outside. Secondary-action disclosure uses normal links/buttons unless implementing a full ARIA menu pattern.
- **Motion:** remove nonessential motion under reduced-motion preference; keep status words and static cues. No animated decoration or fake progress.

UI2-A reference safeguards: every state is clearly identified as an example; saving uses a 2.5-second local timer; dialogs never call an endpoint; native `<dialog>` supplies background isolation; success closes after 9 seconds except while hovered/focused. These timings do not enter production business logic. Full component recovery, dirty-state handling, body scroll, request integration and screen-reader/device validation are B/C obligations.
