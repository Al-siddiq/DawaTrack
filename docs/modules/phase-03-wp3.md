# Phase 3 WP3 — Accountable Non-Sale Movements and Append-and-Correct

Manual outgoing types and signed Correction append one `MANUAL` movement and update the locked projection. They never recalculate WAC; VALID becomes RETAINED only when crossing non-positive, RETAINED stays retained, and UNESTABLISHED remains unestablished. Inactive Products remain accountable.

Movement reversal negates one locked original into one uniquely linked `REVERSAL` and follows non-acquisition basis rules. Reversal of reversal is rejected.

Whole Intake reversal requires the original movement set to be each affected position's latest contiguous suffix. It creates a posted same-kind, zero-item B header linked to A, then reverses movements in per-Product reverse application order and exactly restores verified before snapshots/states. Any later activity rejects with `INTAKE_REVERSAL_NOT_CURRENT`. A sensitive replacement link creates a normal Receipt Draft C only after reversal; WP2 posting rules remain authoritative.
