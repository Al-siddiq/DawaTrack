# Writing for shop users

The owner restated this rule on16 November 2026: shop users must understand each page without higher education or accounting knowledge. Keep using this rule in future work.

Explain three things: where the person is, what they can do here and what to do next. Use short sentences. Write instructions in the order the person will follow them. Say what happens when they press a button.

| Avoid on shop pages | Use instead |
|---|---|
| Cash control | Cash check |
| Stock control | Stock check |
| Full control | Cash and stock check |
| Commit observations | Confirm this count |
| Review the frozen result | Compare your count with the shop records |
| Variance / shortage / surplus | Say how much less or more was counted |
| Finalize | Finish check |
| Abort | Cancel check |
| On-duty responsibility context | Who worked in the shop? |
| Device acknowledgement | This phone or computer is ready |
| Exclude Device | Skip this phone or computer |
| Reconciliation adjustment | Change the stock record |
| Unconfirmed outcome | We do not yet know if this was saved |

Do not just swap words. Explain the effect. For example: “While you count, no one can record sales, returns or other stock changes. Finish or cancel this check to allow those records again.” Cash checks explain the pause for cash records; a combined check explains both.

When a count is confirmed, say it cannot be changed. When a check is finished, say the count and reasons cannot be changed. When stock is changed, show the old and new stock figures and say that later sales are kept. Do not imply that finishing a check adds or removes money.

For a failed save, say what is known and what to do next. Keep the same check number. Do not say it failed if the saved result is unknown. Keep help numbers visible. Explain who can help without exposing passwords, keys, database errors or other private details.

Use familiar names for people, notes and saved states. Show shop times in Nigeria time and label them. Work-time fields explain that a person still working has no finish time yet. A display name or note written by a shop user remains their own words.

Technical identifiers in code, APIs, migration files and developer records can remain unchanged. They are not shop-page labels. A code used only to get help may remain where needed.

Before handing over a screen, read its menu label, heading, buttons, prompts, status, errors and empty state aloud. Check that a trader can follow the next step. Render every stage as well as the start page. A developer check does not replace the owner trying the screen with real shop users.


## Phase 8 Sales reports

Sales reports must be understandable without accounting knowledge. Use the words shown on the screen and explain each amount in one short sentence.

| Avoid | Use on the Shop screen |
|---|---|
| Revenue / turnover | Sales |
| Net Sales | Sales after returns |
| Payment composition | How customers paid |
| Receivable / debtor | Customer bill / amount left to pay |
| Credit Sales | Sales left on a customer bill |
| COGS | Cost of the products sold |
| Gross margin / margin | Money left after product costs (gross profit before shop expenses), with a plain explanation |
| Net Profit | Do not show this in Phase 8; it is outside the approved scope |
| Reporting period | Dates shown |
| Refreshed at | Last updated |

Explain that Cash and Transfer are money received or refunded. Explain that Credit is not money received; it is the part added to a Customer bill. Explain that collections of an old bill do not count as a new Sale.

When showing Gross Profit, say: “This is sales after returns minus the saved cost of the products sold. Shop expenses have not been taken away.” Also explain that the cost saved when the Sale was recorded is used; a missing cost can make the figure incomplete.

Use short headings, familiar words, readable amounts, and a clear next action. Do not rely on color alone. Show the selected dates and Shop time zone. Never display cost or profit details when the actor lacks `sale.profit.view`. Before acceptance, read every report label, explanation, warning and empty/error message aloud with a Shop user.
