# Phase 3 WP2 — Opening Stock, Receipt Intake and WAC Posting

Draft Intake CRUD is online working state and emits no Audit/change. Posting locks the Draft, locks active Products and positions in ascending internal Product ID, applies repeated Product lines in ascending line number, inserts one movement/item, updates position/version/pointer, marks POSTED, appends one Audit and publishes Intake/movements/final positions atomically.

MySQL exact DECIMAL expressions calculate line amounts and WAC. Positive existing quantity blends; non-positive stock excludes negative quantity; the first line restoring positive resets basis to its cost. Opening requires a null pointer, UNESTABLISHED basis, zero quantity and no movement. Posted rows are query-only.

WP3/WP4 closure refines Opening without changing its single-establishment meaning: a fully reversed initial Opening chain may restore zero `UNESTABLISHED` eligibility. Pristine and corrected-Opening posting call the same locked `OpeningEligibility` policy. Intakes and Product movement history are capped and stably paged.
