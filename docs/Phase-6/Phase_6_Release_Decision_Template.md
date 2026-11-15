# Phase 6 — final release decision template

**Status:** WP4 Owner-accepted on 14 November 2026; final whole-Phase Owner decision and release evidence pending. This is a template, not a release approval.

## Candidate identity

- WP1 acceptance: 13 November 2026
- WP2 acceptance: 14 November 2026
- WP3 acceptance: 14 November 2026
- WP4 branch: codex/phase-6-wp4-expected-cash-20261114
- WP4 PR: #48 (cumulative successor)
- Reviewed base commit: `154a2d84b772d594adf8555bee08bc3fe80ca27c`
- WP4 implementation commit: `6114f910e4f85ea0c25a19e92bfb55891ea9fae1`
- Owner-accepted WP4 delivered HEAD: `9483c57d2fae3fd0ed59f0403470e678de4429d2`
- Final reviewed closure/integration HEAD: [record actual SHA from reviewed PR]
- Built artifact manifest: `Phase_6_WP4_Artifact_Manifest.json`, 412 files
- Content SHA-256: `0f2bb165391d7dc006fe91ac0fad99ca5a396618928ad0898e494d0b9ab4d728`
- Manifest-file SHA-256: `447b2fe6f3c53ab95891003d32341b7f0beeed04d996926dec96887bb7ff1da5`
- PHP version: [record]
- MySQL version: [record]
- Closure build Node/npm: v24.22.1 / 11.9.0; target runtime: [record]
- Migration status: [record actual output]

## Manual acceptance evidence

| Package | Cases | Owner decision | Result register |
|---|---|---|---|
| WP1 | P6-01–P6-12 | Owner-accepted | Existing WP1 register |
| WP2 | P6-13–P6-18 | Owner-accepted | Existing WP2 register; individual outcomes not itemized |
| WP3 | P6-19–P6-30 | Owner-accepted | Existing WP3 register; individual outcomes not itemized |
| WP4 | P6-31–P6-40 | Owner-accepted; individual outcomes not supplied | Phase_6_WP4_Manual_Results.csv |

Native test outcomes must be observed, not inferred from package acceptance or static source review. Attach the final requirements/source/manual matrix and defect disposition.

## Release gates

- [ ] P6-31–P6-40 critical cases observed and accepted.
- [ ] F1 ₦35,000.00 and F3 -₦800.00 hand calculations agree exactly.
- [ ] Transfer/Credit exclusion and posted_at cutoff behavior verified.
- [ ] No double counting or invalid causal evidence.
- [x] Locked frontend build and inventory pass in closure workspace.
- [ ] Native PHP/Composer pass in release environment.
- [ ] Native MySQL migration status and query execution pass; no WP4 migration exists.
- [ ] Owner reviews generated inventory committed with closure.
- [ ] Defect disposition has no unresolved release blocker.
- [ ] Review direct cumulative closure merge or WP1 #45 → WP2 #46 → WP3 #47 → WP4 #48 → closure sequence.
- [ ] Rollback handover and backup verified.

## Owner decision

- [x] Accept WP4 — recorded in dated acceptance; individual native results not inferred.
- [ ] Accept complete Phase 6.
- [ ] Reject / return for correction.

Owner name/signature: [record]  
Decision date/time and timezone: [record]  
Accepted commit SHA: [record]  
Accepted artifact inventory SHA-256: [record]  
Outstanding conditions: [record]

**Deployment is a separate authorization.** A Phase 6 acceptance does not deploy, migrate or publish to production automatically.

See [current closure report](Phase_6_Closure_Report.md), [decision register](Phase_6_Release_Decision.csv) and [complete operations handover](../operations/phase-06-final-release.md).
