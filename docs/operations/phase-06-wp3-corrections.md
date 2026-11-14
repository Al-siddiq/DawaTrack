# Phase 6 WP3 — operating and recovery guidance

## What an undo does

Use Undo this money record only when the posted record should be completely cancelled. DawaTrack adds one new record for the exact opposite amount, using the same payment method. The original remains visible and unchanged. The undo requires a clear reason.

## What a correction does

Use Correct this money record when the original record is wrong but a replacement should remain. DawaTrack adds two linked records together:

1. An exact opposite undo for the original.
2. A corrected replacement with the right money direction, amount, payment method and description.

The correction reason explains why the old entry was wrong; the corrected description explains the replacement event. Both are retained. Never edit or delete the original to hide a mistake.

## Who may do it

A Staff user needs financial.reverse to undo. A correction requires both financial.reverse and financial.correct, plus financial.view. A debt, stock, reconciliation or ordinary expense grant is not a substitute. Owner authority is role-derived. All writes require active membership and FULL access.

## Cash and Transfer

Any undo/correction that affects physical Cash must pass the existing Opening Cash and active Cash/Full control checks. This includes Cash-to-Transfer and Transfer-to-Cash corrections because one side affects Cash. Transfer-to-Transfer changes do not affect physical Cash and must not be blocked solely because Opening Cash is absent or a Cash count is active.

## Interrupted or uncertain saves

If the screen says it cannot confirm whether a change was saved:

- Do not start another correction and do not invent new UUIDs.
- Use Check saved status with the same displayed references.
- If the saved pair is found, open the linked history.
- If a 404 is returned while an earlier request may still finish, continue only with the same references and same details after the screen explicitly permits it.
- If only one half of a correction appears, stop and ask the shop owner/technical support to investigate. The transaction should commit both halves or neither.
- Offline operations are not queued. Reconnect before continuing.

The browser marker contains only the inverse/replacement/source public UUIDs and action; it does not store financial details or credentials.

## History and audit

Use View linked correction history to see the original, each undo and each corrected replacement. Each row retains the actor, explanation, channel, signed amount and recorded time. A corrected replacement can itself be corrected later; the next pair must link to that current replacement. Do not branch from an older record. A standalone undo ends that chain and cannot itself be undone.

Never attempt manual SQL repair, deletion or relinking of financial evidence. Escalate an incomplete or inconsistent history with the public UUIDs and support reference, not raw database IDs or customer/credential data.

## Release and rollback

WP3 adds no migration or dependency. Build and verify the frontend inventory before Owner testing. If a source/runtime defect is found, stop new corrections, preserve all evidence and roll back the code release to the last accepted application commit using the project's release procedure. Do not roll back or edit the Phase 6 table or remove already posted movement/Audit/change rows. Any data repair requires a separately reviewed, audited corrective command; it is not an operator SQL task.
