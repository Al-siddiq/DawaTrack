# Phase 6 WP4 — rollback and recovery handover

## Scope

WP4 adds read-only expected-Cash query classes and a Financial Movement history type filter. It adds no database migration, raw SQL, package dependency, public route, Audit writer, change publisher, reconciliation snapshot writer or financial posting command.

## If a defect is found before merge/deployment

1. Stop using the WP4 expected-Cash result and preserve the test Shop/source UUIDs.
2. Keep WP4 on its feature branch; do not merge PR #48.
3. Correct the source query/contract and rerun the affected P6-31–P6-40 cases.
4. Do not repair test or production evidence with direct SQL.

For local candidate rollback, return to the WP3 accepted candidate commit `154a2d84b772d594adf8555bee08bc3fe80ca27c`. This is a repository code baseline, not a claim that it has been deployed.

## If a defect is found after an authorized deployment

1. Stop the affected Reconciliation workflow/result and notify the Shop Owner.
2. Preserve the exact deployed commit, artifact inventory, query boundaries, baseline source and request/log correlation.
3. Roll application code back to the last actually deployed and approved commit using the release procedure.
4. Do not assume the WP3 candidate SHA is the production rollback target unless it was actually deployed.
5. Do not delete/rewrite Sale payments, Debt payments, Financial Movements, Audit events or finalized reconciliation evidence.
6. Do not roll back Phase 4 or Phase 6 migrations; WP4 has no migration.
7. Re-enable expected-Cash use only after the defect is corrected and the affected native cases pass.

## Data integrity rules

- The query is read-only; no snapshot or balance needs to be rolled back.
- A wrong expected-Cash result is not repaired by adding an automatic Financial Movement.
- Keep original, inverse and replacement rows immutable.
- If source evidence is missing/inconsistent, fail closed and investigate through the owning module.
- Any future data correction must be a separately reviewed, audited business command, not operator SQL.

## Merge sequence

The cumulative package PRs are intended to merge in order: WP2 #46 → WP3 #47 → WP4 #48. After each merge, re-check the next PR's base/head comparison and resolve conflicts without duplicating already merged package changes.
