# Cash check would not start — cause and fix

16 November 2026 (Nigeria time).

The owner supplied the failed Start response: READINESS_SETUP_REQUIRED. They then confirmed that `encryption.key` in the private `.env` file was empty or commented out. Their XAMPP PHP check returned “OpenSSL is ready”. The missing private app key explains this refusal. The three list pages already return200; this is separate from the earlier missing database setup.

## Finish the private app setup

The owner was given these steps to carry out on their own computer:

1. Generate a random value locally with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
2. Put that value in the private `.env` line `encryption.key = 'YOUR_LOCALLY_GENERATED_VALUE'`, without a leading `#`. The placeholder is not a key. Do not send the real value to chat or commit it.
3. Save the file and restart Apache in XAMPP.
4. Reload Cash check. Check what was saved first. If the previous check cannot be found, use the same original check number to try starting again. Leave a staff finish time empty while that person is still working.

There was no existing key to replace in the reported setup. An existing configured key on another installation must be kept; do not rotate it for this fix. The web app must use PHP with OpenSSL as well as the CLI. See [the setup guide](../operations/phase-07-wp3-stock-full.md#start-says-the-app-settings-are-not-ready).

Local key entry and a successful Start still await the owner’s result. No secret value was requested, read, generated for that computer, stored in the repository or included in this report. No extra migration, default key or weakened check was added.

## What changed in the page

The [plain-English update](Phase_7_WP3_Plain_English.md) remains in place. The help for each step now also matches what the person is allowed to do. Someone who can only look is not told to enter a count. An empty phone/computer list is explained.

A fresh Start with this exact known settings refusal no longer shows a misleading message that the save result is unknown. The setting is checked before the new check is written. Only this attempt’s own pending marker is cleared, and its check number is kept for a later explicit retry. If an earlier unknown request already left a marker, that marker is preserved. Unexpected500 responses, other503 responses, lost replies and changed record numbers still keep the safe checks. No automatic Start request is sent.

## Checks done here

The actual Vue component was run in a scratch host with mocked API/dialog methods. It passed these cases: fresh known503 clears only its own marker, explicit retry keeps the same number, an earlier uncertain marker remains protected, and an unexpected500 still requires a saved-result check. Existing plain-reading and Cash/Stock/combined-page checks remain passing.

The actual ReadinessProof class was also run in PHP8.3 WebAssembly with synthetic data. Empty and short keys returned the same503 code the owner supplied. A synthetic key passed an OpenSSL encode/decode round trip; a changed receipt was rejected. No key or receipt was printed. This is separate from native XAMPP/HTTP/Device verification.

The frontend build, JavaScript syntax, source/public match and [new exact file check](Phase_7_WP3_Start_Setup_Artifact_Manifest.json) pass. Earlier inventories remain historical. The [manual guide](../manual-verification-phase-07-wp3.md) now uses this candidate’s inventory. The original 48 integrated cases and12 WP3 cases retain their recorded statuses; a successful page request is not a full case Pass. No merge, deployment, cash/stock entry or database change was performed here. WP4 has not started.
