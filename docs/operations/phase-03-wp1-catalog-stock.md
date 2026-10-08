# Phase 3 WP1 operations

Apply foundation and Phase 2 migrations first, then the two registered WP1 migrations. Confirm strict SQL mode, UTC and exactly one active generation. Inspect both new tables with `SHOW CREATE TABLE`.

For integrity review, compare every position to its Product/Tenant and Shop/Tenant parents. A position with nonzero quantity/cost in WP1 requires incident investigation; never repair it through an arbitrary API. The `last_movement_id` FK is intentionally pending WP2.
