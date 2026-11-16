# Phase 7 WP3 — operator and recovery handover

WP2 is accepted. WP3 is prepared for native review, not merged, migrated or deployed by this delivery. WP4 is not authorized. Read the [contract](../Phase-7/Phase_7_WP3_Integration_Contracts.md) and [manual guide](../manual-verification-phase-07-wp3.md).

Use normal Shop Menu → Stock control or Full control. Start with optional on-duty context; resolve all pinned Devices through actual acknowledgement or explicit Owner reasoned exclusion. Save every physical quantity (zero explicitly), plus Cash for Full. Check saved progress before whole-set commitment. Expected figures remain hidden until commitment and authorized review. Explain each nonzero variance and finalize once. Abort with a reason if the attempt cannot finish; saved evidence remains and neither baseline advances. Generation changes require safe abort/new control.

Finalization records differences without changing operational Stock. For a latest finalized Stock variance, choose Review Stock adjustment, enter the reason, inspect frozen delta/current Stock/projected result/cost, then confirm once. Later Sales remain included. Copy the movement/variance references. A newer finalized Stock/Full control makes old unadjusted variances ineligible; retain their notes rather than trying a manual reversal or new identity. Cash variance never generates a balancing movement.

Later notes select a specific final variance. A finding adds context without altering the physical evidence or assigning guilt. Late-operation lists identify original source UUIDs and times; inspect their owning module with independent permissions after count commitment. Backdating never rewrites an earlier snapshot.

If a reply is lost, keep original references and use Check saved status online. No automatic retry occurs. For adjustments, a found source-linked movement resolves the outcome; absence is inconclusive. Keep the same UUIDs, re-enter original reason after reload and explicitly preview/retry that same command. Do not clear storage, make another identity, or infer failure from a404. A changed-body identity conflict needs inspection of saved details. Unknown outcomes block leaving; unsaved input is memory-only and not queued offline. Permission loss, offline and unavailable schema show their actual restrictions.

Before any later release: identified backup and restore rehearsal; native exact inventory check; App migration status; inspect the single WP3 migration and matching SQL; apply one route per database following WP1/WP2 dependencies. Native MySQL8.4 is required. The new candidate key and FK are implicitly committed DDL; if partial, inspect information_schema/history with the operator before retry. Never delete evidence/forge history or apply SQL and migration twice. Down is guarded after any linked adjustment. A code rollback must remain compatible with the installed schema and movement source; preserve database, cookies, private encryption key, uploaded assets and original reviewed code/asset set. No deployment/migration was performed here.

## Start says the app settings are not ready

If the Start response says READINESS_SETUP_REQUIRED, the code requires an existing private app key of at least32 bytes and PHP OpenSSL encrypt/decrypt functions. The owner supplied this exact response after the three page lists began returning200. It is a settings refusal before the new check is written, not a request for another migration.

Privately inspect the project’s `.env` file (with the leading dot, not the example file named `env`). Keep an existing key unchanged. If the line is missing, empty or commented out, generate a random key on that local computer:

    php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

Copy the generated value privately into `encryption.key = 'YOUR_LOCALLY_GENERATED_VALUE'` in `.env`; remove a leading comment marker on that line. The placeholder is not a key. Never send the real value in chat, screenshots, logs or Git. If a key already exists, do not replace it to try to fix this error; first check its length locally and the PHP settings. Preserve it with the deployment’s private backup.

A non-secret PHP check is:

    php -r "echo (function_exists('openssl_encrypt') && function_exists('openssl_decrypt')) ? 'OpenSSL is ready' : 'OpenSSL is missing';"

The web app must use PHP with these functions too; a CLI result alone does not prove Apache uses the same PHP settings. If OpenSSL is missing, check the active XAMPP PHP configuration and enable its OpenSSL extension, then restart Apache. After private settings are ready, reload the page, check the earlier saved result and use the same original check number for an explicit retry. If a future staff finish time is entered, clear it while that person is still working.

A fresh Start that gets this exact known refusal no longer gets a misleading unknown-save warning. Any uncertainty kept from an earlier request remains protected; other503/500 replies, timeouts, changed identities and lost replies retain their normal safe recovery. There is no default key, bypass or automatic retry. Local success still needs the owner to confirm it.
