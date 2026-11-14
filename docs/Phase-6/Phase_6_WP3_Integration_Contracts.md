# Phase 6 WP3 — protected reversal and correction

**14 November 2026 (Africa/Lagos).** WP1 and WP2 are Owner-accepted. WP3 is implemented on the cumulative WP2 candidate for native Owner manual acceptance. No migration, SQL artifact, package dependency, broad permission or mutable balance is added.

## 1. Scope and non-negotiable rules

WP3 makes mistakes correctable without editing or deleting a posted money record.

- A standalone undo creates one immutable REVERSAL row with the exact opposite signed amount and same CASH/TRANSFER channel and Tenant/Shop.
- A correction creates two immutable rows in one transaction: the exact inverse REVERSAL and a FINANCIAL_CORRECTION replacement linked to the original record.
- The original and all earlier rows remain unchanged.
- A reversal cannot itself be reversed.
- Each original can have one direct reversal. A replacement chain is linear: a later correction must target the current effective replacement, not branch from an older row.
- A transfer-only operation does not require Opening Cash or the Cash-control guard. Any operation with a Cash effect must pass the existing CashControlGuard.
- No correction creates an automatic expected-Cash adjustment or a new Sale, Debt, Stock or Opening Cash event.

The v1.1 schema's unique reversal self-link is reused. The schema intentionally has no unique replacement self-link. The serialized Tenant → Shop → active membership transaction boundary enforces one replacement child per source; no schema constraint is invented.

## 2. Explicit API

All routes are authenticated same-origin Shop routes under /api/v1/shop/financial-movements. Existing CSRF, no-store, canonical request correlation and standard response envelopes apply.

| Method and route | Purpose |
|---|---|
| GET /{source_uuid}/correction-state | Read the scoped original → undo → replacement graph |
| POST /{source_uuid}/reversal-preview | Review a standalone exact inverse |
| POST /{source_uuid}/reverse | Save one exact inverse |
| POST /{source_uuid}/correction-preview | Review inverse + corrected replacement |
| POST /{source_uuid}/correct | Save the inverse and replacement atomically |

There is no generic movement writer and no PUT/PATCH/DELETE for posted evidence. The path UUID identifies the original/source record.

### 2.1 Reversal DTO

Preview accepts exactly: public_id (new inverse UUIDv4) and reason (required). Post accepts the same fields plus review_fingerprint from the preview. The server derives the REVERSAL type, exact opposite sign, original channel, Tenant, Shop, actor, occurrence time and posted time. The new UUID must differ from the source UUID.

### 2.2 Correction DTO

Preview accepts exactly: public_id (new inverse UUIDv4), replacement_public_id (new replacement UUIDv4), direction (MONEY_IN or MONEY_OUT), amount (positive canonical decimal string), channel (CASH or TRANSFER), description (required corrected description), optional external_reference, optional bounded UTC occurred_at, and reason (required explanation). Post accepts the same fields plus review_fingerprint. The path UUID identifies the original source.

The inverse UUID, replacement UUID and source UUID must all be distinct. Direction is explicit; amount is a positive decimal string, never a JSON number or float. The server derives the signed delta. Description explains the corrected event; reason explains why the earlier record is being corrected. External reference is optional and does not determine the channel. A null occurrence uses server time; an explicit value must satisfy the existing bounded UTC Audit occurrence policy. posted_at is always server-generated.

Unknown keys, client-supplied type/sign/actor/scope/internal IDs/links/posted time, negative/zero/excess-precision/out-of-range amounts, JSON floats and malformed UUIDs are rejected.

## 3. Authority

- Reads and correction-state history require financial.view.
- Standalone reversal requires financial.view + financial.reverse.
- Correction requires financial.view + financial.reverse + financial.correct.
- financial.correct and financial.reverse remain independent. Neither implies financial.record, the other correction permission, or any debt/inventory/reconciliation permission.
- Owner authority is role-derived. Staff require explicit active grants. A Staff member with debt.manage, financial.record, or only one correction grant is not authorized to perform a correction.
- The owning CommercialAccess boundary rechecks active membership, current grants, Tenant/Shop state and FULL access at preview and confirmation. READ_ONLY allows authorized reads only. BLOCKED/CLOSED, inactive membership, foreign scope and Platform Admin without current Shop membership are denied.
- Creator identity is not an authorization shortcut.

## 4. Source eligibility and causal chain

Eligible source types are SHOP_EXPENSE, OWNER_WITHDRAWAL, OWNER_INTRODUCTION and the current FINANCIAL_CORRECTION replacement. REVERSAL is never a source. A source with an existing inverse is already undone/superseded. A replacement without its required inverse, multiple replacement children, missing parent, cycle, foreign link, invalid Audit evidence or non-exact inverse fails closed.

A correction stores:
- REVERSAL.reversal_of_movement_id → original source row;
- FINANCIAL_CORRECTION.replacement_for_movement_id → that same source row.

For a later correction, the new inverse and replacement point to the current replacement row, not the root original. The original source and each prior replacement remain immutable. A standalone reversal ends the effective chain; a correction's replacement becomes the current effective record.

The GET correction-state query starts from any scoped row in the chain, resolves its root and returns a bounded linear graph (maximum 100 causal levels). Each safe DTO includes public UUID, relationship label, movement type, signed amount, channel, description, required explanation, actor and recorded time. It excludes internal numeric IDs, raw Audit metadata, credentials and private support data.

## 5. Review, Cash and transaction ordering

Preview and post use the same canonical command normalization. The review fingerprint binds the command to the immutable source state and its recorded evidence fingerprint. Confirmation recomputes it under the owning transaction; changed source/command or stale review is rejected.

The owning transaction follows this order:

1. Normalize closed DTO and canonical UUIDs.
2. Enter CommercialAccess and lock Tenant → Shop → active membership; recheck current grants and FULL access.
3. For a new Cash-affecting operation, read the source channel and invoke CashControlGuard before locking Financial Movement domain rows. A correction invokes it if either the source or replacement channel is CASH. Transfer-only inverse/replacement skips this Cash-only guard.
4. Lock the source and required causal ancestors/children; verify source scope, current status, recorded Audit evidence, exact inverse eligibility and linear chain.
5. Insert the inverse. For correction, insert the FINANCIAL_CORRECTION replacement linked to the source.
6. Append required FINANCIAL Audit evidence for each new row with the same canonical request correlation and required explanation.
7. Publish the one or two financial_movement change rows together, after every row and Audit append.
8. Commit.

All writes use one connection and the existing CommercialAccess transaction; participants never open or commit an independent transaction. If either insert, either required Audit append, or change publication fails, the entire transaction rolls back. No domain lock/read is acquired after change publication begins. A preview, GET or failed command publishes no success event.

## 6. Audit and change evidence

Closed actions:

- financial_movement.reversed — FINANCIAL / financial_movement; required reason; metadata includes REVERSAL type, channel, exact signed delta, source/counterpart public UUIDs, command fingerprint and review fingerprint.
- financial_movement.corrected — FINANCIAL / financial_movement; required reason; metadata includes FINANCIAL_CORRECTION type, channel, exact signed delta, source/counterpart public UUIDs, command fingerprint and review fingerprint.

Standalone reversal writes one movement, one required Audit event and one change. Correction writes two movements, two required Audit events and two change rows. All share the request correlation and transaction. The history reader verifies the typed Audit evidence and matching counterpart links. No raw Audit metadata is exposed by the API.

## 7. Replay and interrupted-save recovery

The inverse and replacement each have a permanent UUID. An equivalent retry with the same action, source, IDs and command returns the existing result only after verifying the saved pair and Audit evidence. Reusing either UUID with different content/action/source conflicts. A correction with only one saved half is treated as evidence corruption; it is never silently completed by a second unrelated command.

The browser's existing per-host/user/Shop session marker is extended only with safe identities: inverse UUID, action, source UUID and (for correction) replacement UUID. It stores no amount, channel, explanation, reference, request body, token or credentials. Old expense/Owner markers remain compatible. On reload, the correct action route is restored. A lost response uses scoped GET status for the same references; a 404 is inconclusive while an earlier request may still finish. Explicit continuation retains the same UUIDs and requires re-entry/review of the same details. No automatic repost, background queue or offline operation is introduced.

## 8. UI contract

The accepted Money movements workspace adds permission-gated Undo this money record, Correct this money record and View linked correction history actions. The source detail and review show the original amount/channel and the exact inverse/replacement effects. A confirmation modal states that the original will remain unchanged. Loading/disabled, validation, offline, unknown-save and status-check states reuse the accepted Shop shell. The linked history displays each immutable row, actor, explanation, channel, signed amount and recorded time.

No new navigation destination or bottom tab is added. The workspace remains online-only. No undo-of-undo, delete, edit-posted, bulk correction, expected-Cash component or variance-balancing action is exposed.

## 9. Source map

| Concern | Implementation |
|---|---|
| Closed DTO/fingerprint | FinancialMovementCorrectionInput |
| Source validation, locking, replay, exact inverse, atomic correction | FinancialMovementCorrectionService |
| Scoped movement, direct reversal/replacement and Audit evidence | FinancialMovementRepository |
| Safe detail, history and causal graph | FinancialMovementQuery |
| HTTP mapping | FinancialMovementController |
| Audit vocabulary | AuditAction |
| Routes | app/Config/Routes.php |
| Existing workspace detail/actions | financial-movements-panel.js |
| Undo/correction/recovery/history UI | financial-movement-correction-panel.js |

No database migration, SQL file, dependency, permission alias or new icon package is introduced. The existing local icon vocabulary is reused.
