# Interaction states and invariants

B delivers shared components and selected real integrations, without rewriting domain state transitions. The approved strategy §§6–8 contains the full contract. These requirements govern B/C and task migration.

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

UI2-B reference safeguards: examples mount the real components but use sample records and local delays. They never call business endpoints. UiDialog owns native focus/background/scroll behavior, beforeClose allows dirty-state refusal, UiAlert owns its opt-in success timer, and useUiList ignores stale read results. These local fixture delays never enter production saves. Actual domain recovery remains the caller's current authoritative status flow. Screen-reader/device and all application lifecycle acceptance require manual verification.
