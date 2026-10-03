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

Everything left here is marked *(unsure)* or depends on configuration; it needs a decision
before code changes.

### Site-wide (`README.md`, `README-header.md`)

- *Minor:* the Slack button only shows when `homepage.slack_invite_url` is set.
- *(unsure)* The account menu has no `role="menu"`/`menuitem`, and Enter/Space doesn't focus
  the first item. The Issues and PRs dropdowns use Bootstrap's CSS caret, not a `▾` in the label.

### Homepage (`README-homepage.md`)

- *(unsure)* **Area grid.** Areas with zero open issues are dropped (`WelcomeController`
  `buildAreas()`). The spec says "any count is acceptable", which may mean zero should show.
- *(unsure)* **CTA pair on phones.** The spec has each button go full width when they no longer
  fit. Below 576px the code stretches them side by side instead.

### Boards (`README-leaderboard-pages.md`)

- *(unsure)* **Jump depth.** The spec reveals rows to the end of the user's 25-row block (rank
  142 shows rows 1–150). Code reveals exactly to the rank, both on click and on `#rank-N` load.
- *(unsure)* **Jumped row name.** `aria-label="Your rank, N"` sits on a `div` with no role, so
  screen readers may ignore it.

### Detail page (`README-detail-page.md`)

- *(unsure)* **Type sort order.** Code sorts by action key alphabetically, not in group order.

### Scoring modal and How Scores Work (`README-scoring-modal.md`, `README-how-scores-work.md`)

- *(unsure)* **Maintainer example copy.** The modal and the How Scores Work page word the
  approved-then-merged example differently. It's unclear which is the live copy.

## Kept on purpose

| Where | Spec says | Code does | Why |
|---|---|---|---|
| Page title block (`_chrome-styles.blade.php`) | No gap under the title's divider line | 24px gap below it | Detail pages remove it, so spacing ends up the same everywhere |
| Header "My contributions" link (`app.blade.php`) | Shown to everyone | Hidden if the user has no GitHub username | The link goes to the user's own detail page, which needs a username |
| Login button (`app.blade.php`) | Text only | GitHub icon + text | Matches the footer's Slack button |
| Header wordmark below `lg` (`_chrome-styles.blade.php`) | Full "Magento Open Source Forger" | "Forger" only below `lg`; mark alone below 400px. Hidden words stay in the accessible name | Logo, login and hamburger don't fit on one 64px row otherwise; the login button wrapped over the page title |
| Header height below `lg` (`_chrome-styles.blade.php`) | 64px | 64px closed; grows when the hamburger menu is open | A fixed 64px bar let the open menu overlap the page |
| Month links on the monthly board | `?month=2026-09` | `/leaderboard/monthly/{board}/2026-09` | Cleaner addresses |
| Board search box | Padding `7px 12px` | Padding `7px 30px` | Makes room for the search and clear icons inside the box |
| Monthly detail intro (`score-detail.blade.php`) | Plain sentence | Adds "— impact-weighted, no recency decay" | Tells the reader how the monthly score differs |
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
