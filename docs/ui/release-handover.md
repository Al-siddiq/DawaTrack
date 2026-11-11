# UI4 release and rollback handover

This is an operational guide for the reviewed candidate. No hosting environment or deployment is connected here, so no deployment/rollback pass is claimed. Complete `releases/ui4-a-release-decision.csv` for the actual environment.

## 1. Verify the integration target

The candidate PR targets main directly and has an explicit accepted-UI3-B integration commit followed by UI4 work. A merged/closed stacked PR is not evidence that its content reached main. Inspect the resulting main commit/tree after merge and record it. Do not release from the obsolete UI2/UI3 feature branch merely because GitHub says merged.

The accepted B snapshot is `3e13f2f1db7d86c1b2be00cbda2915113d5c521f`, tree `8289a3729862ecf33178572a841172148291d902`. UI4 adds the documented print/disclosure/release-content corrections. Its new candidate needs final review, not a retroactive pass from B acceptance.

## 2. Prepare a coherent, clean release directory

Build/review the exact candidate in a new directory, preserving the current live deployment. Keep environment/secrets and writable/upload/database storage outside the code archive. Never overwrite a live `.env` with an example file. Record the existing live code release/commit and a restorable archive before changing it.

A Git archive contains canonical repository text bytes on Windows or Linux. `.gitattributes` now defines LF for release text and leaves binary detection intact. Do not reset a checkout with uncommitted work to normalize line endings. For a clean archive of the reviewed commit, use `git archive --format=zip --output=dawatrack-reviewed-source.zip <reviewed-commit>` and extract into a new release directory. The literal placeholder must be replaced with the reviewed SHA. This is source preparation, not a complete runnable deployment: PHP dependencies and environment/storage still need provisioning.

For developer/XAMPP verification run:

```bash
composer install
npm ci
npm run build
npm run ui:release:check
```

A production release build normally installs locked PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` in the new release directory. Run it only in the intended build/runtime environment; PHP is unavailable in the engineering environment used for this handover. Verify the repository's PHP/extensions/platform requirements there. No database migration is introduced by the UI programme; do not run rollback/migrate commands as part of a visual release.

Use the existing supported host/domain/base-URL, mail, TLS and writable-directory configuration. Do not expose repository/config/private storage as a public document root; retain the existing public-root deployment convention. No new server or hosting architecture is mandated by this UI packet.

## 3. Understand the release inventory

`npm run ui:release` regenerates `docs/ui/releases/ui4-a-artifact-manifest.json` after the normal frontend build and staged release-file changes. It records hashes/byte counts for tracked app/public, UI source, dependency locks and line-ending metadata; new required licence/view assets must be staged first. `npm run ui:release:check` compares current content to that reviewed inventory without overwriting it. Do not regenerate the expected file merely to hide a mismatch.

The manifest includes the active Shop/Admin/native-Auth entry points, exact dependency pins, icon identity/budget and source/public module parity. It excludes PHP vendor contents, environment, writable data, uploads, database and documentation. It therefore does not prove runtime provisioning, security behavior, device acceptance or deployment. `--check` also works in an extracted archive without Git by verifying its recorded file list. It is not an audit of unrelated extra/untracked host files; deploy into a clean directory. Bind its `contentSha256` and the exact reviewed commit separately in the release decision. The manifest deliberately avoids a circular self-referential Git commit hash.

If a Windows working tree contains older CRLF checkouts, prepare a clean archive/fresh checkout respecting `.gitattributes` rather than destroying local work. Rebuild there and compare the manifest. Raw-byte differences are not silently accepted as the same artifact. Build output must be reviewed as a whole if a dependency/source change legitimately changes the inventory.

## 4. Stage, inspect and decide

Deploy the complete compatible release directory to staging/test using the existing host mechanism. Include PHP layouts/helper/Auth.php view mappings and the three new local email-code views, generated Shop render, Shop/Admin/Auth/components/API/shared modules, CSS, runtime vendor files/licences and fingerprinted icons. Partial file uploads are not a coherent release.

Finish or reference valid M01–M17 observations and all affected fixes. Confirm no data-integrity/privacy/authority/usability blocker remains. Record actual environment/browser/device and errors. Keep the previous complete code/assets available for rollback. The release decision is Pending until those actual facts and Owner approval are recorded.

## 5. Cache and long-lived tabs

The app already uses private/no-store headers for authenticated shell/security/financial responses; verify actual response headers because a reverse proxy/CDN can override origin policy. The existing Service Worker has no fetch handler and must not gain business-data caching for this release.

Stable `.js`/`.css` paths must revalidate. `public/.htaccess` now sets `Cache-Control: no-cache` for those extensions when Apache mod_headers is loaded. Check an actual response in browser Network/curl: `no-cache` permits storage but requires validation; it is not a promise that an offline asset is current. If mod_headers is absent, configure the equivalent in the existing host. For Nginx/other hosts, apply that policy in the host's current static-asset configuration; retain its existing routing/security/TLS rules. Do not add a new catch-all location that bypasses the application's front controller.

Fingerprint-named icon sprites can remain immutable and must be retained for the release/rollback window. Do not apply immutable caching to stable-path JS/CSS. Preserve any required old assets in the previous release/archive. No CDN purge or HTTP-header verification was performed here.

Before the atomic switch, ask test operators to finish/check any in-flight writes; do not abort a write and call it failed. Keep old code/assets together for existing tabs. After the switch, inspect a normal reload and an already-open tab: correct modules/styles/icons, no CSP errors, preserved saved basket/receipt references and safe same-ID status recovery. A hard reload alone is not a cache-invalidation strategy. Record the actual application/asset headers, artifact identity and old-tab behavior under M18.

## 6. Production switch and focused smoke

After the concrete release decision is approved, use the existing atomic release switch (for example the host's versioned directory switch). If the host cannot switch atomically, use its existing maintenance/quiescence procedure and a complete package upload; do not expose mixed markup/modules during active selling. Keep the actual previous release identifier available.

Immediately check sign-in/security, current context/role, permitted navigation, one saved unfinished sale, one saved receipt, direct Print privacy and local icons. Use controlled test records for any write smoke. Check application/server/CSP errors. Record actual release/environment/time/commit/hash/operator; do not label a staged deployment production.

## 7. Rollback rehearsal and incident rollback

In staging, switch back to the complete previous compatible code/assets and verify sign-in/context/navigation/saved basket identity/receipt. Then restore the candidate and verify again. Record both artifact identities and outcomes. This is the M18 rehearsal, not a hypothetical checklist tick.

For an incident, stop further affected operations, preserve logs/references and switch the complete code/assets release back using the existing mechanism. Preserve environment, uploads, all business data and operation identities. Never reverse customer sales, restore an older database, delete stock evidence or roll back migrations as a UI rollback. Check uncertain operations by their existing IDs before retrying. Verify cache/reload behavior and the same focused smoke after rollback.

## 8. Final handover

Store the release decision, manual result references, closed/accepted defects and actual deploy/rollback evidence together with the exact commit/content hash. Only then record full UI4/programme acceptance and Phase 5 readiness. This engineering delivery does not authorize or claim an unseen production switch.
