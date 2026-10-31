# Phase 3 inventory diagnostics runbook

`php spark inventory:diagnose <tenant-public-id> <shop-public-id>` is an operator-only, read-only consistency inspection. Run it against a backup/restored environment first when investigating an incident. It resolves one explicit Tenant/Shop scope and reports at most 100 sample rows per category. It performs only `SELECT` statements and never repairs data.

The command checks position quantity against the signed movement sum; the last-movement pointer; quantity and average-cost snapshot continuity; Phase 3 source/type/sign combinations; exactly one movement for each posted Intake Item; reversal quantity/scope/target integrity; same-kind, posted, zero-item Intake reversal headers; replacement-to-original/reversal causality; movement/Product/position scope; and suspicious position versions.

A zero result in every category is expected. Any result is evidence to investigate together with application logs, Audit correlation and sibling `sync_changes`. Never patch `stock_positions`, delete evidence, or rerun a business command blindly. Resolve a genuine business discrepancy through an authorized Correction/reversal workflow. Escalate structural drift before taking writes. Retain sanitized command output, relevant public IDs, migration status, `SHOW CREATE TABLE`, and the investigation correlation in the incident record.

The diagnostic cannot prove economic correctness or replace the closure checklist. Its sample cap prevents unbounded terminal output; if a category reaches 100, investigate with a scoped read-only SQL session before concluding its total size.
