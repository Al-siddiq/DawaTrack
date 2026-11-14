# Phase 6 WP3 — Owner manual verification (P6-19–P6-30)

**WP3 is implemented for manual acceptance, not yet accepted, merged or deployed.** Use a backed-up isolated XAMPP development database with synthetic Shops and users. Never inject failures, edit/delete financial rows or change grants in production.

## Setup and candidate integrity

Run these commands from the DawaTrack checkout:

    git fetch origin
    git switch codex/phase-6-wp3-reversal-correction-20261114
    git pull --ff-only
    composer install
    npm ci
    npm run build
    npm run ui:release -- --output=docs/Phase-6/Phase_6_WP3_Artifact_Manifest.json
    npm run ui:release:check -- --output=docs/Phase-6/Phase_6_WP3_Artifact_Manifest.json
    php spark migrate:status

The first ui:release command creates the candidate inventory after the build. The check command must then pass. Commit the generated WP3 manifest to the candidate branch after reviewing it. Do not regenerate it to hide changed files. WP3 adds no migration; migration status should show only the already accepted Phase 6 WP1 schema. Do not run rollback or alter previous migration files.

Merge WP2 PR #46 before WP3 PR #47 for a sequential merge. Both PRs must not be merged as separate duplicate cumulative snapshots.

## Test fixtures

Use a test Owner with FULL access and a Staff user whose grants can be changed. Record the current Tenant/Shop and each test UUID. Start with finalized Opening Cash for Cash tests; create a separate Shop with no Opening Cash for Transfer-only tests. Use a genuine accepted WP2 movement as the source (Shop Expense, Owner Withdrawal or Owner Introduction). Keep amounts small and exact, e.g. ₦1,500.00. Never reuse production references.

## P6-19 — exact standalone Cash reversal

1. Record a Cash Shop Expense of ₦1,500.00 and confirm it appears once in history.
2. Open its detail and choose Undo this money record.
3. Enter a new inverse UUID and a clear required reason. Review the source, same Cash channel and exact +₦1,500.00 inverse.
4. Confirm once. Verify one new REVERSAL row with the same Shop/channel, exact opposite signed amount, correct actor/time/reason and source link.
5. Reopen the original: its description, amount, channel, UUID, timestamps and Audit remain unchanged. The source detail shows its undo link and the linked history shows both rows.
6. Check one financial_movement.reversed Audit event and one change row. GET/preview must add none.

## P6-20 — Transfer-only reversal

In a separate Shop with no finalized Opening Cash, create a Transfer expense and undo it. It must succeed without invoking the Cash/Full guard, use the same Transfer channel, create an exact opposite Transfer row and show ₦0.00 physical Cash effect. Repeat while a Cash/Full control is active in a disposable fixture: Transfer-only reversal must not be blocked merely by the Cash control.

## P6-21 — identity, duplicate and invalid-link protection

- Double-click Confirm and retry the exact same UUID/body after the response. Exactly one inverse, one Audit and one change remain.
- Reuse the inverse UUID with another reason/source/action: conflict; no second row.
- Try source UUID as inverse UUID: validation rejection.
- Try a second inverse UUID against an already reversed source: reject.
- Try to reverse the REVERSAL row itself: reject.
- Try a foreign Shop/Tenant source UUID: non-enumerating not-found/denial; no evidence.
- Confirm an inverse cannot be attached to another source or have a client-supplied actor, scope, internal ID or signed amount.
- Verify a standalone reversal ends the effective chain; it cannot be corrected as if it were an ordinary current record.

## P6-22 — atomic corrected replacement

1. Create a Cash expense of ₦1,000.00 with a deliberately incorrect description or amount.
2. Choose Correct this money record. Use two distinct new UUIDs (inverse and replacement), direction MONEY_OUT, amount "800.00", corrected description, Transfer, and a required reason.
3. Review must show the original, exact +₦1,000.00 Cash inverse and -₦800.00 Transfer replacement. The inverse and replacement must each identify the original; the replacement must not overwrite it.
4. Confirm. Verify two new rows: REVERSAL (+1000.00 CASH) and FINANCIAL_CORRECTION (-800.00 TRANSFER), both same Shop and canonical request correlation, each with correct actor, reason, reference and timestamps.
5. Verify two required Audit events (financial_movement.reversed and financial_movement.corrected) and two change rows commit with them. Original remains unchanged.

## P6-23 — Cash/Transfer cross-channel guard

Test all combinations in a disposable Shop:

- Cash source → Transfer replacement: CashControlGuard is still required because the inverse changes physical Cash.
- Transfer source → Cash replacement: CashControlGuard is required because the replacement changes physical Cash.
- Transfer source → Transfer replacement: no Opening Cash requirement and no Cash-control block.
- Cash source → Cash replacement: guard required.

Test missing Opening Cash and active Cash/Full control separately. A rejected preview/confirmation creates no movement, Audit success or change. External bank references must never infer a Transfer channel.

## P6-24 — independent permission checks

Test Staff with each combination:

- financial.view only: can read history/graph, cannot preview or post.
- financial.reverse + view only: can reverse an eligible source, cannot correct.
- financial.correct + view only: cannot correct without financial.reverse.
- financial.record + view only: cannot reverse or correct.
- debt.manage or reconciliation grants only: cannot reverse/correct.
- financial.reverse + financial.correct + view: can correct, subject to FULL access.
- Owner: role-derived authority, still subject to FULL access and current active membership.

Try direct API calls and direct hash routes; hiding a button is not backend authorization.

## P6-25 — transaction rollback between correction halves

Only in a disposable local environment, inject a failure after inverse insert, after replacement insert, during each Audit append, and during change publication. Every failure must roll back both movement rows, both Audit events and all change rows/generation effects. There must never be a committed inverse without its replacement for a correction. Restore all injected code before continuing. Do not create manual SQL cleanup in a real Shop.

## P6-26 — linear correction chain and branch prevention

Create original O, correct it to C1 (with inverse R1), then correct C1 to C2 (with inverse R2). The graph must be O → R1 + C1 → R2 + C2, with each inverse/replacement linked to its immediate source. The current effective public UUID is C2.

Attempt to correct O again, create a second replacement child for O, correct R1, or introduce a cycle/foreign link. Every attempt must fail without evidence. Verify the original and C1 are unchanged. No new unique replacement database index should be present; linearity is enforced by the Shop transaction and source checks.

## P6-27 — authority changes after preview

Prepare a reversal/correction review, then in another authorized session revoke the needed grant, change the Staff role, deactivate membership, switch the Shop to READ_ONLY/BLOCKED/CLOSED, or change subscription/access. Confirm with the old preview. It must reject and create no rows/Audit/change. Recheck active membership and FULL access at confirmation. A previously opened modal must not bypass changed authority.

## P6-28 — Audit, correlation and publication ordering

For standalone reversal and correction, verify actor, required reason, movement type, channel, exact signed delta, source/counterpart public IDs and canonical request correlation. Correction's two Audit events and two changes share one correlation and commit atomically. Activity/history must show plain-English reasons, not raw metadata or internal IDs. Reads, graph refresh and previews add no Audit/change. Change publication must be last in the owning transaction.

## P6-29 — lost response and same-reference recovery

- Simulate a lost response after a successful standalone reversal. Reload: the original action route and inverse UUID are restored; status GET finds the same reversal; no second POST is automatic.
- Repeat for correction: the marker must retain inverse UUID, replacement UUID, source UUID and action only. No amount, channel, description, reason, reference, request body, credential or token is stored.
- A 404 before the original request resolves is inconclusive. Continue only with the same references and re-enter/review the same details. Never create new IDs to bypass uncertainty.
- A correction pair with only one visible half is an evidence error, not permission to silently complete the other half.
- Test offline/online, unknown 5xx, READ_ONLY and lost view permission. No background queue or offline write is permitted.
- Existing expense and Owner UUID-only markers must continue to recover through their original action.

## P6-30 — causal graph, UI and regression

Verify the linked graph from the original, inverse and replacement detail routes. It must show each public UUID, relationship, movement type, signed amount, channel, description, why it changed, actor and recorded time. It must not show internal numeric IDs or raw Audit metadata.

Review narrow phone and desktop widths, keyboard/focus, modal close/confirm, disabled/loading state, validation correction, stale review, error/empty/loading state, browser back/forward and navigation protection. Verify permissions hide actions but backend remains authoritative. Confirm no new bottom tab or parallel shell.

Regression-check WP1 expenses, WP2 Owner withdrawal/introduction, Opening Cash, Sales/Returns, Debt payments/corrections/statements, Stock, Staff permissions and Money movements filters/history. Expected Cash composition is WP4 and must not be inferred from this package.

## Results and acceptance

Record each case in the WP3 manual result register with the exact commit, runtime versions, Shop, actor/grants, UUIDs, expected/observed values and evidence. Mark Pass only when observed. A failed or blocked critical case prevents acceptance. Send the completed results and explicitly state whether WP3 is accepted. WP4 starts only after that decision.
