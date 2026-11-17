# Phase 7 WP2 — standard Cash/readiness integration contracts

16 November 2026 (Africa/Lagos). WP1 is Owner-accepted; WP2 only implemented for native review. This is the actual Cash/API/schema contract. Stock/Full/Inventory adjustment and late-operation association across modules remain WP3; no offline transport is enabled.

## Existing foundations and authority

WP2 builds on accepted WP1 `676fb09` and accepted plan `bf5a358`, with main observed at `4266400` before delivery. Cumulative branch `codex/phase-7-wp2-cash-reconciliation-20261115` retains both prerequisites. Every read resolves authenticated same-origin Shop membership and requires reconciliation.view through CommercialAccess; READ_ONLY may inspect authorized data, BLOCKED/CLOSED/foreign/inactive deny. Each command owns one CommercialAccess transaction with Tenant→Shop→current membership locks and FULL/current grant rechecks. No Device proof replaces membership authority.

## Schema delivery

Six forward migration/SQL pairs after WP1:

| Suffix | Migration | Purpose |
|---|---|---|
|100200|AddPhaseSevenEvidenceScopeKeys|Supporting unique keys on existing financial counts (Tenant/Shop/control/id) and Devices (Tenant/Shop/id) for same-scope composite FKs|
|100300|CreatePhaseSevenStockCounts|Full approved Stock-count table, prerequisite of full v1.1 variance FK; no Stock workflow exposure|
|100400|CreatePhaseSevenVariances|Exactly one Cash/Stock count source/value, public UUID and immutable finalized evidence|
|100500|CreatePhaseSevenVarianceNotes|Scoped append-only later typed notes, up to2,000 characters|
|100600|CreatePhaseSevenStaffContexts|Scoped historical member/user role and optional known duty window|
|100700|CreatePhaseSevenDeviceAcknowledgements|One actual ACKNOWLEDGED/EXCLUDED decision per control/Device, same-scope FK and explicit reason rules|

Supporting candidate keys add no new business restriction: id was already globally unique. They are necessary for MySQL8.4 unique referenced-key requirements and exact control/source/Device scope. Prior migration definitions remain untouched; no columns, balance/status store or receipt/pending table is invented. Apply native migrations OR twins on each database, not both; DDL may implicitly commit. The down paths refuse populated evidence; remove children before supporting keys on empty disposable copies only. CashControlSchema requires all evidence tables and critical scoped FKs; partial setup fails closed.

## Start transaction and fixed cutoff

Start accepts only public_id and optional bounded on_duty context. It rejects client scope/expected/baseline/generation/time/actor. Under the root barrier, find the latest finalized CASH/FULL predecessor by cutoff/id, require its committed actual, and snapshot Phase6 ExpectedCashQuery over `(prior cutoff,new server cutoff]`. The returned Opening baseline is previous actual, including shortage/surplus. Physical actual is never inferred from expected. Source-owned correction/reversal validation and Transfer/Credit exclusion remain intact.

All source writers serialize through WP1 roots. CommercialAccess establishes its read snapshot only after roots are held; the existing ExpectedCashQuery participates in that owning transaction. Capture the Cash snapshot and all ACTIVE Devices under domain locks; retain one STANDARD/CASH COUNTING row and one financial snapshot. Append typed start Audit with cutoff, selected generation UUID, predecessor public UUID, duty fingerprint and Device count/digest. Append one typed device_required Audit per pinned Device linked causally to start; no amount/expected snapshot in startup metadata. Insert known Staff context before generation acquisition.

The final locking-read ChangeSequenceService watermark binds generation UUID/sequence. If generation changed since capture, rollback the entire start. Update only the already-owned session cutoff sequence, then publish aggregate UPSERT and commit. No joined-generation FOR UPDATE occurs during control lookup, and no new domain locks follow final generation acquisition. Root/session locks prevent overlapping Cash/Full starts. Abort/finalize releases the active-scope restriction through the terminal status, not a separate mutable lock record.

Exact same start UUID/context fingerprint recovers one control; changed duty context conflicts. Earlier applied Opening behavior remains unchanged. Real native two-session snapshot/lock/DDL proof is still required.

## Frozen relevant Devices and online receipt

Every ACTIVE same-Shop Device at start is pinned, including unavailable Devices; never last-seen-filtered. Historical membership is typed immutable per-Device Audit with start cause and count/digest, not a later live query. CashControlRepository verifies all membership events, Device resolution, duplicate identities, digest/count and actual ACK cardinality. Missing/foreign/extra/inconsistent evidence fails closed; empty is a recorded zero set, not fabricated ACK.

Readiness list is bounded in the response (UI20/server≤50); complete immutable-set validation is a domain consistency read and not a load-all user endpoint. Names are frozen at start; current ACTIVE/REVOKED status is displayed separately. Later enrollment does not join the old set. Pending/revoked Devices cannot issue an active proof. An unresolved revoked Device retains its obligation; Owner can exclude with reason. A valid prior ACK remains historical after later revocation.

Challenge GET requires current active installation cookie, same-Shop membership/view authority, active control stage, inclusion in pinned set and matching active generation. It issues a five-minute opaque AES-256-GCM receipt with purpose-separated key derived from existing private encryption.key (minimum32 bytes/OpenSSL; no default/new secret). Payload binds user/membership/control/Device, generation UUID, cutoff and exact sequence as a decimal string. It is not an offline lease, sync cursor or outbox-drained claim. No receipt in URL/storage/Audit/log. Client cannot assert future watermark.

ACK POST requires that same current proof and receipt. Insert one immutable ACK with exact target sequence and actor/time; update actual Device last_seen/last_validated and append validated Device-context Audit; publication comes last. Exclusion is current PRIMARY_OWNER plus independent exclude grant and mandatory reason; insert one immutable EXCLUDED row with null sequence. Existing different decisions/reasons conflict; no UPDATE route. Equivalent repeats cannot add another decision. All pinned Devices must resolve before commit/finalize. Reconsidering a decision requires explicit abort/new control. Generation change blocks progress; reasoned abort remains possible without decrypting a readiness receipt. Readiness encryption setup is checked before new start and proof use; abort/note/recovery do not require receipt decryption.

## Exact count, revision, commitment, review and finalization

Count requires view+count, exact nonnegative decimal string (including0.00), current COUNTING state and expected_updated_at for a changed observation. Same saved value is a no-op; stale different input conflicts. Actual/variance are stored exactly even in draft to satisfy existing financial-count CHECK; variance/expected are not sent in blind DTOs. saved count actor/time is preserved. Amounts never pass through binary float. Unrepresentable signed variance fails closed rather than overflowing DECIMAL(19,2).

Commit requires view+count+commit, saved amount/current revision and complete real readiness. It atomically marks the count committed and control COUNTS_COMMITTED. Historical COUNTER role belongs to the user who saved the physical observation, not automatically a different committer; counts_committed_by separately records confirmation authority. No count edits after commitment. Equivalent later committed-state retry returns saved state without a duplicate event.

Review requires view+review and committed state; explanation uses the committed count's current revision and≤500 characters. An explanation never edits actual/expected/variance. Finalize requires view+review+finalize, REVIEW, complete readiness, consistent committed observations/variance and a saved nonblank explanation for nonzero variance. Insert one normalized Cash variance only for nonzero difference, capture FINALIZER context, set FINALIZED and append required Audit/publication atomically. No Financial Movement, Stock adjustment or mutable balance is written. The next control selects this finalized actual. Failed Audit/publication/commit rolls back all new evidence; repeated finalization adds none.

Abort requires view+abort and mandatory reason from COUNTING/committed/review; preserve drafts/readiness/context, do not insert final variance or advance a predecessor. Generation mismatch does not trap safe abort. Terminal evidence cannot transition back or have reason overwritten.

## Blind/read projections and source outcome

History returns only control public identity/stage/times, no money. Detail allowlists period/generation target/readiness summary plus saved actual/revision only for count/review consumers. Frozen expected/components/variance/explanation require committed counts AND current review authority AND no other active Cash blind count for that counting actor. Historical predecessor detail and variance notes cannot bypass blindness. Standard startup is always blind; uncommitted aborted expected values stay unexposed. A count-only/view-only client cannot acquire values by editing role/UI. Server/API filtering, private no-store responses, safe Print and cleared failed/offline UI enforce the boundary.

WP1 source policy remains across source read/preview routes for active counting actors. Non-counting appropriately authorized Staff can perform unrelated Transfer-only business events while Cash effects remain blocked by shared control guards. This is separate from a counting actor's intentionally restricted source screens.

Blind-safe source-status read independently requires owning Sale or Debt or Financial view grants. It returns only requested UUID, found/state and explicit inconclusive-read guidance; no money/command fingerprint or certification of changed-body acceptance. Original source recovery markers are not silently cleared. Full source/body conflicts must be inspected later when authorized detail becomes available.

Known ON_DUTY windows/notes are server-validated same-Tenant membership context; unknown stays null. Historical COUNTER/REVIEWER/FINALIZER attribution is captured by actions. Staff list uses public membership identity and name/role/windows; duty notes are sent only after commitment to review consumers. No guilt inference. All user text remains plain rendered content.

## Notes

A finalized nonzero Cash variance accepts explicit notes with view+review+note, permanent UUID, approved EXPLANATION/INVESTIGATION_FINDING/REVIEW_NOTE type and1–2,000-character text. Same UUID/type/text/source recovers one note; changed details conflict. No update/delete route or silent overwrite. Audit uses only safe note public identity/type and a short action reason, never truncates the note into a500-character reason. Balanced controls have no normalized nonzero Cash variance to annotate. Historical notes are hidden during an active blind Cash count for that counting actor. Later notes can be appended after epoch recovery without changing frozen control generation/values.

## API/action matrix

All paths under authenticated /api/v1/shop/cash-controls; no generic CRUD, public expected-Cash endpoint or Stock/Full command. Standard CSRF/correlation/private no-store/error handling. Mutations share a30/minute authenticated identity bucket. Collection per_page1–50, UI20; stable ID tie-breakers. Query references are public UUIDs; internal IDs/credential hashes/raw Audit/proof payload never returned.

| Method/suffix | Grant beyond reconciliation.view | Input/result |
|---|---|---|
|GET collection, /{uuid}|view only|Safe bounded history; stage-aware blind/review detail|
|GET /members|start|Bounded active-member search for known duty context|
|POST collection|start|public_id, optional on_duty member UUID/windows/notes; server-selected CASH start|
|GET /{uuid}/readiness|view|Pinned safe Device decisions, count/digest validated internally|
|GET /{uuid}/challenge|view + active own Device proof|Opaque target-bound five-minute receipt; no write|
|POST /{uuid}/acknowledge|view + active own proof|proof; actual immutable ACK|
|POST /{uuid}/exclude|exclude_device + current Owner|device_public_id,reason|
|POST /{uuid}/count|count|amount,expected_updated_at|
|POST /{uuid}/commit|count+commit|expected_updated_at|
|POST /{uuid}/review|review|Empty object|
|POST /{uuid}/explain|review|explanation,expected_updated_at|
|POST /{uuid}/finalize|review+finalize|Empty object|
|POST /{uuid}/abort|abort|reason|
|GET /{uuid}/staff|review|Bounded safe historical responsibility context|
|GET /{uuid}/notes|review|Bounded finalized notes, blind-policy checked|
|POST /{uuid}/note|review+note|public_id,note_type,note_text|
|GET /{uuid}/source-status|Owning source-view grants|kind/public_id; existence/state only|

Stable errors include CASH_CONTROL_SETUP_REQUIRED/NOT_FOUND/IDENTITY_CONFLICT/STATE_CONFLICT/STALE/EVIDENCE_INVALID/GENERATION_CHANGED, CASH_COUNT_REQUIRED, DEVICE_READINESS_REQUIRED, READINESS_SETUP_REQUIRED/REFRESH_REQUIRED/DECISION_CONFLICT, DEVICE_PROOF_REQUIRED/NOT_RELEVANT, VARIANCE_EXPLANATION_REQUIRED/NOT_FOUND and BLIND_COUNT_ACTIVE. Validation never establishes scope or authority.

## Browser recovery / UI

CashControlsPanel joins the existing Shop Menu with capability view_cash_controls. It supplies count/save/commit, real Device ACK/Owner exclusion, review/explanation/finalize/abort, history/Staff context/notes and blind-safe source check. Grant-absent actions are absent; backend always rechecks. Snapshot comes from server only, no client Cash arithmetic. Saved progress is server-side; unsaved input stays in memory on recoverable failure and is compared with fresh saved observation. Navigation is parent-guard-approved, never bypassed by locally switching records first.

Unknown replies block new actions and retain only control/action plus optional note UUID in a host/user/Shop session marker. No amount/reason/duty body/receipt/credential/outbox. Check saved status performs reads only; a404 is inconclusive. Start continuation keeps the same UUID and requires original duty context; note continuation keeps the same UUID/type/text. Found notes resolve that specific pending identity; otherwise retry is explicit and same-reference. Storage failure explains keeping/copying original references, not clearing site storage. Offline/failed refresh clears financial/status records; reconnect never POSTs. Count values are not claimed as durable offline drafts (Phase10).

Shared notice adoption uses UiInformationPanel's actual title/message interface; this also corrects WP1 Device notices that had used UiStatusPanel's incompatible state interface. Unknown/offline notices now appear in the actual component host probe. No new framework/dependency introduced.

## Verification and package boundary

See [phase-style native guide](../manual-verification-phase-07-wp2.md), [result register](Phase_7_WP2_Manual_Results.csv) and [delivery report](Phase_7_WP2_Delivery_Report.md). Scratch grammar/build/Vue-host/SQLite query-shape/DDL pairing checks are engineering observations, not PHP/MySQL/HTTP/concurrency/device acceptance. No native Pass is invented. WP3 Stock/Full/Inventory adjustment and full late-operation association; WP4 integrated phase closure remain separate and unauthorized. No production migration, main merge or deployment is performed.
