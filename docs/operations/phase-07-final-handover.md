# Phase 7 — final operation and support handover

**Updated 24 November 2026 (Africa/Lagos). Phase 7 is Owner-accepted and CLOSED.** The Owner confirms the phase has been reviewed, tested and verified. This document remains the operational reference for any separately authorized deployment, backup/restore, migration inspection, monitoring or compatible recovery; phase closure is not a claim that a production deployment occurred.

## Closure and source reference

The dated [Owner acceptance](../acceptance/2026-11-24-phase-07-owner-closure.md) records whole-phase acceptance. [Closure report](../Phase-7/Phase_7_Closure_Report.md) and [decision record](../Phase-7/Phase_7_Release_Decision.md) are authoritative for the Phase 7 closure state. WP4 PR #53 is merged to main at `98cb8d4ee99c8668a18aa92993b7828dc982902f`.

Historical WP4 engineering observations, artifact manifest, original requirement matrix, manual case registers and defect disposition remain unchanged. Their historical caveats are not rewritten as new test results. Do not use the old candidate-only wording in the original delivery report as the current Owner decision.

## Existing operational guidance

The following existing controls remain binding for any later operational activity:

- Use an isolated copy and synthetic Shops for rehearsal.
- Record actual PHP, Composer, MySQL, OS/server/browser, SQL mode, storage engine and UTC/Africa-Lagos behavior.
- Back up database, matching code/assets and private configuration together; protect existing encryption keys and Device/session state.
- Prove an isolated restore and compatible rollback before relying on them. Never restore an old database over later posted Shop activity as a routine code rollback.
- Inspect the ordered Phase 7 migrations and actual MySQL objects/status. MySQL DDL may partly commit; never blindly rerun a partially applied migration or manually mark it applied.
- Preserve the existing encryption key and HTTPS/hostname/secure cookie/CSRF behavior.
- Keep integration, deployment, production migration and operational rollback as separately authorized and recorded decisions.
- Never use real Shop balances as smoke-test data.

See the [WP4 delivery report](../Phase-7/Phase_7_WP4_Delivery_Report.md), [integration contracts](../Phase-7/Phase_7_WP4_Integration_Contracts.md), [manual guide](../manual-verification-phase-07-wp4.md), [trader guide](phase-07-shop-checks.md), [closure checklist](../Phase-7/Phase_7_Closure_Checklist.md) and [release decision](../Phase-7/Phase_7_Release_Decision.md).

## Next phase

Phase 8 is **Authoritative Reporting and Operational Dashboard**. Its three-package [implementation plan](../Phase-8/Phase_8_Implementation_Plan.md) follows the Strategic Roadmap, Technical Blueprint and Schema v1.1. Phase 8 has not started. Do not begin WP1 until the Owner accepts the plan and explicitly authorizes commencement.
