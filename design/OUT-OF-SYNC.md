# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-03**,
against the spec refresh of the same day (`fe1948b`). Only current differences are listed;
anything fixed is deleted, not kept as history.

## How to read this

| Heading | Meaning |
|---|---|
| **To do** | Code doesn't match the spec and should, eventually. |
| **Kept on purpose** | Code differs from the spec deliberately. Decided; no action. |
| **Not covered** | Parts of the site no spec describes. Not drift. |

`README.md` still has an "earlier generation" board section with older sizes. The current type
scale in the same file and `README-leaderboard-pages.md` win; differences from the old section
are not listed. Items marked *(unsure)* need a look before acting.

---

## To do

### Site-wide (`README.md`, `README-header.md`)

- **Hover colours.** The table-row hover should be `#faf9f7`. Detail rows, List view rows and
  Highlights rows use `#fafbfb` (`_lb-styles.blade.php` `.lb-d-row`, `.lb-a-row`, `.lb-hl-row`).
- **Text-link hover.** Links should go `#ee6524` → `#c74e16`. Only the homepage steps do.
  `.lb-tallied`, `.lb-empty-2 a`, `.lb-d-back`, `.lb-d-more`, `.lb-hl-more`, `.lb-hl-sort`,
  `.lbr-activity a` and `.lb-d-otherboard a` stay orange or only change their underline.
- **Hover transitions.** Every hover should be 120ms ease-out. These have none: search clear,
  Highlights rows and cards, detail and List rows, `.lb-empty-2 a`, `.lb-tallied`, footer links.
  By-month bars use `ease` instead of `ease-out`.
- **Focus rings.** Detail and Highlights links have no `:focus-visible` rule, so they get the
  browser default: `.lb-hl-row`, `.lb-hl-card`, `.lb-d-row`, `.lb-a-row`, `.lb-d-back`,
  `.lb-d-toggle a`, `.lb-a-sort`, `.lb-d-more`, `.lb-d-otherboard a`.
- **Retired border colours.** `#e3e5e8` and `#e6e8ea` should be `#e6e7ea` (rules) or `#d5d8dc`
  (outlined controls). Still in use: `.hp-ready` and the detail zero panel use `#e3e5e8`. The
  by-month colours are listed under By-month below.
- **Score tooltip arrow.** It should be centred over the score (`right: 22px`). Code has
  `right: 52px` (`_lb-styles.blade.php:373`).
- **Account menu label.** It should read "My contributions"; code says "My Contributions"
  (`app.blade.php:62`).
- **Footer label.** It should read "Pull Requests"; code says "Pull requests" (`footer.blade.php:22`).
- **Current nav item.** The spec wants `aria-current="page"` on it. `MainMenu` only sets the
  `.active` class (`app/Menus/MainMenu.php`).
- **Header gutter on phones.** The logo should line up with the H1. Below 576px the page uses a
  20px gutter but `.site-nav > .container` keeps 36px (`_chrome-styles.blade.php:32`).
- **Fonts.** Only Libre Franklin and Martian Mono are specified. The layout still loads Roboto and
  Roboto Condensed from Google Fonts (`app.blade.php:15`).
- *Minor:* footer heading to first link is 11px, not 9px (`.sf-col-head` margin). The Slack
  button only shows when `homepage.slack_invite_url` is set. Avatar initials are 8.5px, not 9px.
- *(unsure)* The account menu has no `role="menu"`/`menuitem`, and Enter/Space doesn't focus
  the first item. The Issues and PRs dropdowns use Bootstrap's CSS caret, not a `▾` in the label.

### Homepage (`README-homepage.md`)

- **Ranked visitor row.** The caption "Your rank over the last 12 months." should be inside the
  row's `<a>`. The link should be named "Your rank, N — see your contributions". Code puts the
  caption after the link and gives it no `aria-label` (`welcome.blade.php:72-84`).
- **"Ready to code" row border.** Should be `#e6e7ea`; code has `#e3e5e8`.
- *(unsure)* **Area grid.** Areas with zero open issues are dropped (`WelcomeController`
  `buildAreas()`). The spec says "any count is acceptable", which may mean zero should show.
- *(unsure)* **CTA pair on phones.** The spec has each button go full width when they no longer
  fit. Below 576px the code stretches them side by side instead.

### Boards (`README-leaderboard-pages.md`)

- **No-JS fallback.** Without JavaScript every row should be visible, with no "Show 25 more" and
  no count. CSS hides the rows past the current depth, and the button is a reload link to `?rows=`
  (`_board.blade.php:104,169`).
- **Search by handle.** Search should match the handle as displayed. `data-search` stores it
  without the `@`, so "@jora" finds nothing (`_board.blade.php:106`).
- **"No matches" never shows.** The script hides the whole pager when nothing matches, which
  hides the "No matches" count too (`_board-script.blade.php:71`).
- **"Show 25 more" missing under search.** The button is only rendered when the page loads short
  of full depth. Load at full depth via `?rows=`, then search for something with more than 25
  matches, and there is no button (`_board.blade.php:168`).
- **Live region text.** Should say "25 more shown. …"; code says "25 more loaded. …"
  (`_board-script.blade.php:140`).
- **`?rows` during search.** It should be dropped while a search is active. Code leaves it.
- **Jump highlight.**
  - It should extend 12px past the gutter (`margin: 0 -12px; padding: 10px 12px`); it doesn't.
  - It should be held 2s; code holds it 2.4s.
  - It should fade over 400ms. The fade runs at the row's 120ms and the orange bar snaps off.
- *(unsure)* **Jump depth.** The spec reveals rows to the end of the user's 25-row block (rank
  142 shows rows 1–150). Code reveals exactly to the rank, both on click and on `#rank-N` load.
- *(unsure)* **Jumped row name.** `aria-label="Your rank, N"` sits on a `div` with no role, so
  screen readers may ignore it.
- **Tab gap.** The control strip should sit right under the tabs. `_tabs.blade.php:5` adds
  Bootstrap `mb-4`, a 24px gap.
- **H1 on phones.** It should drop from 40px to 28px below about 560px; there is no rule for it.
- **Monthly intro.** It is missing "Note that scores are subject to change." before the scoring
  link (`score-monthly.blade.php:12-15`).
- **Empty month.** It should render the board with the no-activity state. Code shows an
  `alert-info` box with no control strip (`score-monthly.blade.php:37-40`).
- **"See contributions" in the activity cell.** The spec no longer has it. Code still shows it
  when a person has no counted activity (`_board.blade.php:131`).
- **Maintainer fallback.** The whole board should be in the page. Code caps it at 100 rows when
  the maintainer roster is empty (`ScoreLeaderboardController.php:675`).
- *Minor:* an empty board shows "Run `artisan leaderboard:compute`" to visitors
  (`score.blade.php:14`). Two stale comments still mention a whole-board failure block
  (`_board.blade.php:155`, `_lb-styles.blade.php:410`).

### Detail page (`README-detail-page.md`)

The spec now describes the List view as built (group cards, Type/Date/Points sorting, type
tags). What still differs:

**Both views**
- **Item titles.** They should sit on one line, cut off with "…", with columns aligned on the
  baseline. Code wraps them (`text-wrap: pretty`, `align-items: start`) and nudges the date and
  points down by 3px and 1px.
- **Group names.** They should be sentence case ("PRs opened", "Approved PRs that were merged").
  `GROUP_LABELS` is Title Case (`ScoreLeaderboardController.php:361`).
- **Grouped/List toggle.**
  - The spec has: gap 16px, 9.5px, `.06em`, inactive 400 `#6b7178`, hover `#15171b`,
    `padding-bottom: 5px`, and the active label not a link.
  - Code has: gap 14px, 9px, `.08em`, 700 on both states, inactive `#4c525a`, hover orange, 2px,
    and both labels are links.
- **Month chips.**
  - The spec has: 10.5px uppercase `.06em`, `6px 12px`, radius 6px, border `#d5d8dc`, text
    `#3c4148`, hover border `#15171b`, labels like "SEP 2026".
  - Code has: 11px, `7px 12px`, radius 8px, border `#dfe1e4`, orange text, orange hover, labels
    like "Sep 2026".
- **Which months show.** The spec wants a fixed last 12 months. Code shows only months that have
  data.
- **Score under a month filter.** The spec keeps "Points · 12 months". Code switches to the
  month's sum and label.
- **Default params.** Grouped and Points should carry no URL param. Code writes `view=grouped`
  and `sort=points`.
- **Smaller values.** Intro line-height should be 1.65 (code 1.6). Group count letter-spacing
  should be `.06em` (code `.08em`).

**List view**
- **No pagination.** It should use "Show 25 more" with a "Showing 1–25 of N" count. Code shows
  the whole list.
- **Intro.** It should read "Every scored contribution in the last 12 months in one list. The
  points column sums to the grand total." Code reuses the Grouped intro.
- **Type tags.** They should be the singular group name in sentence case ("PR opened",
  "Issue resolved by a merged PR"). `CHIP_LABELS` uses short labels instead ("Opened PR",
  "Approved → Merged"). Tag style should be 9.5px `#3c4148` on `#f0f1f3`; code has 9px
  `#4c525a` on `#f2f3f5`. On phones the tag is hidden, which the spec doesn't say.
- **Sort headers.** They should be `<button>`s, with the inactive state 400 `#6b7178` `.06em`
  and hover `#15171b`. Columns should be 212/112/66 with a 16px gap. Code uses links, 700
  `#4c525a` `.08em`, orange hover, and columns 104/92/54 with a 28px gap.
- **Type sort order.** *(unsure)* Code sorts by action key alphabetically, not in group order.
- **Group cards.**
  - The maintainer page should use 3 columns; code always uses 4.
  - Spec sizes: gap 12px, `margin-top: 22px`, radius 9px, padding `16px 16px 18px`, name 13px
    with `min-height: 2.7em`, total 20px, count 10px, bar 4px at radius 2px.
  - Code sizes: gap 14px, `margin-bottom: 24px`, radius 8px, padding `13px 14px 12px`, name
    12.5px with `min-height: 34px`, total 17px, count 9.5px, bar 5px at radius 3px.
- **Heading and rows.** The list heading should be 18px; code reuses the 19px group style. The
  table head should have `margin-top: 28px`. Row padding should be 9px; code has 10px.
- *Minor:* there is no 30px gap below the other-board line in the List view.

**Zero-score panel**
- Border should be `#e6e7ea`; code has `#e3e5e8`. Points width should be 66px; code has 62px.
- **Footer order.** The hint line should come first, with the button after it pushed right
  (`margin-left: auto`). Code puts the button first (`score-detail.blade.php:61-64`).

**Phones**
- The gutter should be 20px below `lg` (992px); code switches only below 576px.
- The H1 should drop to 28px; code keeps 40px.
- The score block should stay on the right; at 640px and below code moves it to its own line.
- Item rows should keep their grid; code narrows the columns to `1fr 92px 54px`.
- The group cards' breakpoint is 640px, not `lg`.

**Monthly detail page** (`score-monthly-detail.blade.php`)
- It has no Grouped/List toggle, month chips, "Show all" filter or other-board line. It shows
  every row in each group. It should match the spec like the other detail pages: one template
  for all of them.
- On a month with nothing scored, the intro still promises groups that sum to the total. The
  hint doesn't use the spec's wording ("Each fills in as you go.").

### Highlights (`README-highlights.md`)

- **Section titles.** They should be sentence case ("New contributor spotlight", "Recently
  active"). Code uses Title Case.
- **Unit labels.**
  - Spotlight should be "First contribution · 30 days · Score"; code says "{N} in the last 30
    days".
  - Comebacks should be "Time away"; code says "Away for · then back".
  - Rising should be "Gain · 30 days"; code says "Gain · 30d".
  - Letter-spacing should be `.06em`; code has `.08em`.
- **Comebacks sort.** The default should be most recently back first, with "Most recently back
  first." and an underlined "Sort by time away instead." link (period outside the link). Code
  defaults to longest away, and its sort link has no underline
  (`ScoreLeaderboardController.php:615-626`).
- **"Show all N".** It should be the outlined button (like "Show 25 more"), `margin-top: 16px`,
  labelled "Show all N". Code uses a small orange mono link labelled "Show all N →".
- **Avatars and handles.** Avatars should be 28px (code 30px). Handles should be `#6b7178`
  (code `#5d636c`).
- **Time-away values** should have `white-space: nowrap`; code doesn't set it.
- *Minor:* the Comebacks grid has an inline `margin-top: 14px`. The spec says lists start
  directly under the rule.

### Scoring modal and How Scores Work (`README-scoring-modal.md`, `README-how-scores-work.md`)

- **Modal title.** It should be sentence case, "How contributor scores are tallied". Code says
  "How Contributor Scores Are Tallied" (`_scoring-modal.blade.php:23`).
- **`× PRIORITY` tag.** Letter-spacing should be `.06em`; code has `.04em`. This affects both
  the modal and the page.
- **Top edge.** The modal should have a flush, full-width 3px strip. Code uses `border-top` on a
  12px-radius panel, so the orange curves at the corners.
- **How Scores Work gap.** The body should start 24px below the title rule. `.page-title-bar`
  adds 24px and `.hsw` adds another 24px, about 48px in total.
- *(unsure)* **Maintainer example copy.** The modal and the How Scores Work page word the
  approved-then-merged example differently. It's unclear which is the live copy.
- *Minor:*
  - On phones the modal's own margin should drop to 16px; code only changes the example's margin.
  - The close button uses `&times;` instead of `✕`.
  - `.lb-tallied` hard-codes 14.5px instead of `font: inherit`.

### By-month (`README-issues-prs-by-month.md`)

- **Bar scale.** Bars should be scaled by 115 (78 on phones). Code uses 118 and 82
  (`_chrome-styles.blade.php:341,440`). On phones, `padding-bottom` should stay 9px; code has 10px.
- **Colours.**
  - The empty-month stub should be `#e6e7ea`; code has `#e6e8ea`.
  - The baseline should be `#e6e7ea`; code has `#e3e5e8`.
  - The Earlier/Later buttons and month tiles should have a `#d5d8dc` border; code has
    `#e3e5e8` (`_chrome-styles.blade.php:281,322,343,407`).
- **Tile month letter-spacing.** Should be `.06em`; code has `.04em`.
- **Intro H2.** It should be sentence case, "Why group open issues by month?". Code says "Why
  Group Open Issues by Month?" (`IssuesByMonthController`, `PrsByMonthController`).
- **Missing years.** Every year back to the oldest open item should get a block. The year
  histogram uses `min_doc_count: 1`, so a year with nothing open has no block. The current year
  can disappear too (`OpenItemsByMonthQuery.php`).
- *Minor:* at either end the Earlier/Later button should become a `<span>`. Code keeps a disabled
  `<button>` that looks the same.

## Kept on purpose

| Where | Spec says | Code does | Why |
|---|---|---|---|
| Page title block (`_chrome-styles.blade.php`) | No gap under the title's divider line | 24px gap below it | Detail pages remove it, so spacing ends up the same everywhere (except How Scores Work; see To do) |
| Header "My Contributions" link (`app.blade.php`) | Shown to everyone | Hidden if the user has no GitHub username | The link goes to the user's own detail page, which needs a username |
| Login button (`app.blade.php`) | Text only | GitHub icon + text | Matches the footer's Slack button |
| Month links on the monthly board | `?month=2026-09` | `/leaderboard/monthly/{board}/2026-09` | Cleaner addresses |
| Board search box | Padding `7px 12px` | Padding `7px 30px` | Makes room for the search and clear icons inside the box |
| Monthly detail intro (`score-monthly-detail.blade.php`) | Plain sentence | Adds "— impact-weighted, no recency decay" | Tells the reader how the monthly score differs |
| Detail page group headers (`_lb-styles.blade.php`) | Columns sized to content (`1fr auto auto`) | Fixed widths (`1fr 112px 66px`) | Keeps header numbers lined up with the rows below |
| By-month bars (`by-month.blade.php`) | Link to that month's list | Link to a filtered GitHub search | Decided earlier; noted in the code |

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign.
- **Monthly scoring popup** — the monthly board has no score decay, so the code shows a
  no-decay version of the scoring popup. The spec only describes the 12-month version.
- **"Inactive" badge on board rows** (`_board.blade.php:123`) — no spec mentions it.
