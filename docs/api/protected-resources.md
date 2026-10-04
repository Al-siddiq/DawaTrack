# Protected resource API convention

Drafts may use explicitly approved edit/discard operations. Mutable master/configuration resources use focused owning-service updates and Audit important changes.

Posted or finalized evidence never receives generic CRUD mutation routes. A future correction is an action-oriented endpoint owned by the relevant domain, for example `POST /.../{public_id}/reverse`. The service must authorize, lock, validate, append domain compensation, attach the approved source/reversal relationship, append causal Audit, and commit atomically. The original is never overwritten or deleted.

Route shape does not supply authority. Tenant scope is server-derived, Platform authority stays separate, and resource UUIDs are not authorization.

Repeated commands return existing domain-specific `409` codes or the established `CONFLICT` envelope. DawaTrack does not promise generic response replay before Phase 10 and does not accept an unapproved generic `idempotency_key`.
