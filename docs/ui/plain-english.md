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


## Phase 8 Sales report language

A Sales report must be understandable without accounting knowledge. Use short labels and explain each amount beside it. Avoid unexplained words such as revenue, net sales, COGS, margin, turnover or reconciliation variance on Shop pages.

| Report label | Explain it this way |
|---|---|
| Money from sales after returns | The value of goods sold, less goods returned or cancelled. |
| Cash after refunds | Cash received, less Cash paid back to customers. |
| Bank transfers after refunds | Transfers received, less transfers paid back. |
| Money customers can pay later | The part customers were allowed to pay later, less any amount removed after a return. Later repayments are not counted as new sales. |
| Money left after paying for goods | This is Gross Profit: money left after paying for the goods sold. Shop bills and other expenses have not been taken out. |

Show the Shop dates, Shop time zone and last refresh time. Do not call Gross Profit net profit. If a user cannot see profit, say that their access does not include profit figures and direct them to the Shop Owner. Do not show a blank, zero or masked-looking profit amount that could be mistaken for a real result.

The existing Sales workspace remains the entry point. A Staff member with `report.view` can open the report; `sale.view` separately controls Sales and Returns history and the Sell destination; `sale.profit.view` separately controls the money-left-after-goods figure and cost values. Use the simple screen label first and explain the technical name only where it helps. The Primary Owner continues to use the existing role-derived authority. Do not introduce a new role or broaden Staff grants.
