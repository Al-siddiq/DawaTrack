# Cash check Start — field-name lookup fix

16 November 2026 (Nigeria time).

## Cause

After the private app setup was corrected, the owner reported a new500 response when pressing Start. The application log points to SaleCashComponentQuery.php line87, called by ExpectedCashQuery.

That line read `table_name` and `column_name` from an information_schema result. A database can return the system table’s own uppercase field labels, TABLE_NAME and COLUMN_NAME, even when the SELECT names were written in lowercase. PHP array keys are case-sensitive. The unchecked lookup then raises an ErrorException before a new check is written.

DebtCashComponentQuery used the same lookup immediately afterward in the Cash calculation, so fixing Sales alone would leave another crash.

## Change

Both queries now give the result fields explicit names, `source_table` and `source_column`, and read those exact keys. No migration, database setting, balance calculation or saved payment changes. The existing required-column checks and payment-source checks remain in place. A missing required column still returns the handled setup503; broken payment evidence still returns409. Those errors also have plain shop-page messages.

Cash and combined Cash/Stock checks use these helpers. The source payment totals, Cash-versus-Transfer choice, reversal links, original occurrence/posting times, Shop limits, Audit checks and period boundaries are unchanged. This fixes how system field names are read; it does not repair or hide bad payment records.

## Checks done here

The actual before/after metadata SELECTs were run against a scratch SQLite system-table fixture with uppercase field names. The old SQL returns uppercase labels; the fixed SQL returns the explicit names. Rows for a missing required column were retained as an error fixture.

The actual Sales and customer-payment component classes were then run under PHP8.3 WebAssembly using those SQL-derived rows and mocked payment aggregates. The old Sales class reproduces ErrorException at line87. Both old lookups fail on the uppercase keys. Both fixed classes return exact sample receipt/refund/reversal amounts. Both still refuse a missing required column with503 and invalid payment evidence with409. This is PHP execution with a database fixture, not native MySQL or HTTP acceptance.

All303 App PHP files pass PHP8.3 grammar parsing. The frontend build, changed JavaScript syntax, source/public match and [exact file check](Phase_7_WP3_Cash_Start_Artifact_Manifest.json) pass. Original inventories remain historical. No project dependency or automated suite was added.

## Try it in the owner’s app

Update the existing WP3 branch and reload the page without using the old cached files. Keep the private key already configured. Use Check what was saved first. If the previous check cannot be found, use Try starting with the same check number. On an older page these buttons were called Check saved status and Continue original start reference. Leave the finish-work time empty while that person is still working.

The owner still needs to confirm that the updated Start works in their local app. A successful Start is a partial observation, not a Pass for all manual cases. The48 integrated and12 WP3 case statuses remain unchanged. No main merge, deployment or business/database change was performed here; WP4 has not started.
