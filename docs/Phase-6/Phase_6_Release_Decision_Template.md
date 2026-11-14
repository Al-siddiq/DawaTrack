# Phase 6 — final release decision template

**Status:** Pending WP4 manual verification and whole-Phase Owner decision. This is a template, not a release approval.

## Candidate identity

- WP1 acceptance: 13 November 2026
- WP2 acceptance: 14 November 2026
- WP3 acceptance: 14 November 2026
- WP4 branch: codex/phase-6-wp4-expected-cash-20261114
- WP4 PR: #48 (cumulative successor)
- Reviewed base commit: `154a2d84b772d594adf8555bee08bc3fe80ca27c`
- WP4 implementation commit: `6114f910e4f85ea0c25a19e92bfb55891ea9fae1`
- Final branch HEAD after generated manifest commit: [record actual HEAD SHA]
- Built artifact manifest path/hash: [record actual generated file and SHA-256]
- PHP version: [record]
- MySQL version: [record]
- Node/npm versions: [record]
- Migration status: [record actual output]

## Manual acceptance evidence

| Package | Cases | Owner decision | Result register |
|---|---|---|---|
| WP1 | P6-01–P6-12 | Owner-accepted | Existing WP1 register |
| WP2 | P6-13–P6-18 | Owner-accepted | Existing WP2 register; individual outcomes not itemized |
| WP3 | P6-19–P6-30 | Owner-accepted | Existing WP3 register; individual outcomes not itemized |
| WP4 | P6-31–P6-40 | Pending | Phase_6_WP4_Manual_Results.csv |

Native test outcomes must be observed, not inferred from package acceptance or static source review. Attach the final requirements/source/manual matrix and defect disposition.

## Release gates

- [ ] P6-31–P6-40 critical cases observed and accepted.
- [ ] F1 ₦35,000.00 and F3 -₦800.00 hand calculations agree exactly.
- [ ] Transfer/Credit exclusion and posted_at cutoff behavior verified.
- [ ] No double counting or invalid causal evidence.
- [ ] PHP/Composer and frontend build pass.
- [ ] Native MySQL migration status and query execution pass; no WP4 migration exists.
- [ ] Generated artifact inventory reviewed and committed.
- [ ] Defect disposition has no unresolved release blocker.
- [ ] WP2 → WP3 → WP4 PR merge sequence reviewed.
- [ ] Rollback handover and backup verified.

## Owner decision

- [ ] Accept WP4.
- [ ] Accept complete Phase 6.
- [ ] Reject / return for correction.

Owner name/signature: [record]  
Decision date/time and timezone: [record]  
Accepted commit SHA: [record]  
Accepted artifact inventory SHA-256: [record]  
Outstanding conditions: [record]

**Deployment is a separate authorization.** A Phase 6 acceptance does not deploy, migrate or publish to production automatically.
