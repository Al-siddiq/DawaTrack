# Phase 3 WP3 correction and reversal operations

Use request correlation to inspect the new movement, position, Audit and sibling change rows. Never update original evidence or repair quantity directly. `INTAKE_REVERSAL_NOT_CURRENT` is a safety decision: record a reasoned Correction and, when acquisition evidence needs replacement, create/post the linked Receipt Draft. Duplicate-key reversal conflicts are translated to stable domain conflicts. Owner acceptance must exercise two-session races and every rollback stage.
