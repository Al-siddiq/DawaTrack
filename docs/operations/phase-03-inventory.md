# Phase 3 Product and Inventory operations

Products & stock is the primary workspace for catalog search, low/below-zero review, Product detail, threshold, movement evidence and authorized accountability. Stock coming in owns Opening, Receipt, Draft review/post and linked Intake evidence. READ_ONLY retains these reads; BLOCKED/CLOSED have no normal operational access.

Never patch quantity, average cost, basis, pointer, posted Intake data or movements. Use an authorized Intake, Correction or eligible reversal. Draft Intake and replacement Draft activity is non-authoritative and silent; replacement causality becomes authoritative only on successful post.

Growing Product, Intake and Product-history collections use stable, capped pagination. Run the read-only diagnostics described in `phase-03-inventory-diagnostics.md` after migration, before Phase 4 handoff, and during investigation of unexplained stock. Diagnostics do not repair.
