# WP2 Money movements history error — metadata label follow-up

13 November 2026 (Africa/Lagos). The Owner reported a generic error loading Money movements. Their native migration screenshot shows CreatePhaseSixFinancialMovements applied in batch 9. A later log captures GET /api/v1/shop/financial-movements returning 500 with ErrorException. The previous commercial catch handler retained only exception class/request ID, so the screenshot does not establish the precise throw location or prove a successful post.

## Defect corrected

FinancialMovementSchema selected three metadata columns without explicit result aliases: EXTRA from information_schema.columns and COLUMN_NAME/NON_UNIQUE from information_schema.statistics. The consumer accesses lowercase extra/column_name/non_unique keys. Metadata column labels can retain uppercase names; undefined lowercase access then triggers a PHP warning/ErrorException before normal history processing. The remaining metadata fields already have explicit aliases.

All three columns now use explicit lowercase aliases. This fixes a concrete metadata-key defect consistent with the reported ErrorException. Native user retest is still required to confirm this was the cause of their request and that no second runtime issue remains. Schema checks, permission requirements, read-only behavior and protected evidence are retained; no database repair/migration/data change is needed for this code fix.

CommercialController now records exception source file/line and up to three application source locations with the existing support reference. It does not log raw exception messages, SQL, trace arguments, request bodies, credentials or customer/financial values. Public errors remain sanitized. Future failures can be located without exposing business payloads.

## Verification and inventory

The actual old metadata queries were executed against uppercase-column SQLite fixtures, reproducing all three missing lowercase keys. The corrected actual queries return the six expected column keys and three expected index keys. This is a SQL-label regression probe, not native PHP/MySQL acceptance. PHP 8.3 grammar parsing of 264 files, scoped SQL/source checks and all 38 mocked-API/custom Vue host workflows pass. No automated suite/dependency was added. Native PHP/MySQL/HTTP/browser verification remains pending.

The WP2 inventory intentionally advances with this code revision: 404 files, hash `75b2902fbd74df5696caf29a8a7ebab007c3c7f63c1838aa24c9b82a73fd4524`. The initial delivery report's earlier hash is historical. Prior WP1 and other release manifests remain unchanged.

## Owner retest

On codex/phase-6-wp2-owner-movements-20261113, pull the updated branch with git pull --ff-only and check the WP2 inventory. No npm/composer change or new migration is introduced. Refresh Money movements: it should show history or the honest empty state instead of this metadata failure. Test both Owner Cash/Transfer actions and expense regression using the existing manual guide. WP2 is not accepted by this repair.

If the page still fails, reproduce once and collect the newest commercial.request_failed line; it now includes source and app_trace. Do not rerun destructive migrations, delete movements or create another UUID to bypass an uncertain save.
