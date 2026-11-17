# WP4 engineering observations

17 November 2026 (Africa/Lagos). Supporting engineering evidence only; all native case outcomes remain Not run. No framework/suite added to the project. Scratch tools are outside the repository.

## Actual browser, synthetic service

Chrome145.0.7632.77 headless rendered the real ShopApp, Vue3.5.44, CSS, shared dialog and source components against local synthetic HTTP fixtures.41 renders:36 Cash/Stock/Full × Start/Counting/Review/Finished ×390/768/1366 widths; Full Counting320; Cash Start keyboard; three generation-changed Counting pages. No horizontal overflow, every rendered field had a label, old heavy fixed terms were absent, expected values absent from Counting UI, and zero page exceptions were observed. Tab and Shift+Tab stayed in the modal after the shared fix; Escape closed and returned focus. Generation-changed count inputs were disabled, forced count form events made no POST, cancellation remained available. Zero POST requests occurred in these browsing/keyboard checks. Unsaved-page navigation confirmation was explicitly accepted by the scratch driver.

[Machine observations](../ui/verification/phase-07-wp4/browser-observations.json) · [Phone Stock](../ui/verification/phase-07-wp4/stock-counting-390.png) · [Tablet Full review](../ui/verification/phase-07-wp4/full-review-768.png) · [Desktop Full finished](../ui/verification/phase-07-wp4/full-finalized-1366.png). Phone/tablet screenshots visually inspected. Full-page captures place the fixed bottom menu at its viewport position; normal scrolling remains necessary. Practice Pharmacy, IDs and numbers are synthetic. These images are **not deployed/native acceptance screenshots**.

Not exercised: native PHP HTTP pipeline, real sessions/CSRF/MFA/Device cookies, source database transactions/locks, physical touch/virtual keyboard, screen-reader behavior or trader comprehension. The manual guide retains those gates.

## PHP, SQL and component probes

- All303 App PHP files passed PHP8.3 grammar parsing. PHP8.3 WebAssembly ran actual StockCountEvidence against SQLite (137 captured Products), full digest/tamper and bounded-page/token cases.20-row helper page:≤6 queries and≤21 proof rows; last/empty/renamed/literal-search/scoped-one cases passed. FOR UPDATE was recorded then removed for SQLite; not native MySQL plans/locks/DECIMAL behavior.
- Actual ControlQuery PHP class passed five free-text privacy combinations with mocked repository/access/Device/snapshot dependencies: blind reviewer, non-reviewer, counting reviewer, committed reviewer and cancelled reviewer. Internal reasons remain stored. Not native cross-route authorization proof.
- Actual ChangeSequenceService PHP class passed shared Full/existing refresh/disjoint target publication probes (3/3/4 rows) with mocked DB/source association. Not transaction/rollback proof.
- Actual Vue component scratch hosts passed Cash/Stock/Full saved-count/review/explicit-zero/draft retention, adjustment preview49→46, original-ID unknown reply and GET recovery without repost, note identity, offline data clearing and setup503 handling. Dialog methods and API were mocked in these component probes; the separate real-browser check exercises native dialogs.
- Locked frontend build passed with existing Sass warnings. All public JavaScript syntax, source/public graph and inventory checked. No dependency/schema/configuration/secret change.

## Exact content inventory

[WP4 inventory](Phase_7_WP4_Artifact_Manifest.json) includes449 deployable/source/pin files. Content digest: `347a3a4075c6cd623e68f323f3d2de30cced1592f601aad109b190db76b4b26b`. Manifest-file SHA-256: `4f94a55b99a3bf9ebc72e8152aaf581f4899e20e7f3454251448af7c8b835f55`. Match the final reviewed Git commit shown by the PR, then record that commit and these hashes in the release decision. The manifest does not hash itself or documentation; Git binds the whole review pack and these screenshots.

Main fetched during preparation remains `42664001a18c9678022d85fd85e7be33b7640e3f`; accepted predecessor `c9d8b996a830ce1ce3248a46d7516d7524e1ea80` is an ancestor of this candidate. No individual native result, whole-phase acceptance, merge or deployment was manufactured from these observations.
