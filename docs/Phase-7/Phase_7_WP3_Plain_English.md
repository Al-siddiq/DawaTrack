# Cash and stock checks — plain words and the remaining error

16 November 2026 (Nigeria time). The owner asked for words that ordinary traders, including people with primary school education, can understand. This is also the rule for future work.

## What changed on the pages

- Menu and headings: Cash check, Stock check, Cash and stock check.
- Each page explains what to count, how to save it, how to confirm it and what comes next.
- Buttons say Save cash count, Save item count, Confirm this count, Compare the numbers, Finish check and Cancel check.
- Starting a check explains which shop records will be paused and how to allow them again.
- The page says the count cannot be changed after it is confirmed. Finishing the check does not add or remove money. Changing stock shows the current and new numbers and keeps later sales.
- Cash differences say how much less or more was counted. An unknown buying price says it is not yet known, without presenting zero as a known price. Buying-price states, staff roles, note types and phone/computer choices use familiar words rather than internal codes.
- Times on saved checks are shown in Nigeria time. Work-time help says to leave the finish time empty if the person is still working.
- Failed saves explain what to check and keep the same check number. Help numbers stay visible. Private server details remain hidden.
- Error messages in the owning cash/stock code also use plain words. Request fields, routes, status values, permissions, calculations, lock order and saved evidence keep their existing meaning.

[Writing guide](../ui/plain-english.md) and root [project instructions](../../AGENTS.md) record the rule for future work. Shop-written names and notes remain the shop users’ own words.

## The remaining Start error

The earlier missing-schema error was traced to all nine Phase7 migrations being pending. The owner now reports that most errors are gone. They supplied a successful Cash history response with an empty list. Their later log also shows Cash, Stock and combined page requests returning200, while Start returns503. That confirms those page-load requests work and no standard Cash check is listed; it does not prove the earlier Start request succeeded or establish why it failed.

The remaining screenshot shows a failed action and the retained original check number. The JSON body of the failed Start503 response is still needed to identify the exact reason. The supplied log has no unexpected-error line, which is consistent with a handled503 refusal. Missing private-key/OpenSSL setup or an unavailable source prerequisite must not be guessed from a screenshot alone. No new check identity, automatic retry, cleared storage or removal of a guard has been used to hide it. The screenshot also shows a future finish-work time; that must be left empty if the person is still working. Existing validation refuses future work times and the new message explains what to enter. This is not asserted to be the cause of the unexpected error.

## Checks done here

The frontend build and source/public checks pass. All303 App PHP files parse under PHP8.3 grammar; that is not native PHP execution. The edited public JavaScript files pass syntax checks.

A scratch Vue host with mocked API/dialog methods checks all three start pages and dialogs plus15 saved-stage screens. It checks that the old fixed terms do not appear, that the next step is explained, that staff finish-time help is clear, and that saved times say Nigeria time. It checks names for phones, staff roles, notes and buying-price states and plain less/more cash and item counts. No Start POST is sent before confirmation.

The earlier Cash and Stock/Full behavior checks were rerun with their expected labels updated: explicit zero, blind reads, page progress, saved input, note source, stock change49→46, lost-reply original-number recovery, no automatic retry and offline clearing still pass. Error-page checks for all three scopes, safe setup messages and failed-load Start blocking pass. These are mocked checks, not the owner using the real app or a reading trial with traders. Native acceptance rows remain unchanged.

Use [the new exact file check](Phase_7_WP3_Plain_English_Artifact_Manifest.json) for this candidate. Prior inventories remain historical. See [the current manual guide](../manual-verification-phase-07-wp3.md) for setup and real-app checks. The remaining Start error is still open until its response/log is supplied and the fix is checked in the owner’s app. No merge, migration, cash/stock entry or deployment was performed here.
