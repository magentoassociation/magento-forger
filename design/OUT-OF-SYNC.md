# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-04**,
against the spec edits of the same day (scoring label "How scoring works", single homepage CTA,
login button GitHub mark, Slack button rule, empty areas omitted, canonical example copy,
jumped-row naming, List view type sort). Only current differences are listed; anything fixed is deleted, not kept as history.

## How to read this

| Heading | Meaning |
|---|---|
| **To do** | Code doesn't match the spec and should, eventually. |
| **Unsure** | Code may not match the spec; needs a decision before code changes. |
| **Kept on purpose** | Code differs from the spec deliberately. Decided; no action. |
| **Not covered** | Parts of the site no spec describes. Not drift. |

`README.md` still has an "earlier generation" board section with older sizes. The current type
scale in the same file and `README-leaderboard-pages.md` win; differences from the old section
are not listed.

---

## To do

### Boards (`README-leaderboard-pages.md`)

- **Jumped row name.** The spec says no `aria-label` on the row unless it has a row role; the
  row's contents are read on arrival. The viewer's row still carries `aria-label="Your rank, N"`
  on a plain `div` (`_board.blade.php:109`). Pages: Contributor, Maintainer and Monthly boards.
- *Minor:* **Jump announcement number format.** The spec's announcement is "Jumped to your rank,
  142 of 1,284." The code formats the total but not the rank (`_board-script.blade.php:174`), so
  a rank above 999 reads "1284", not "1,284". Pages: Contributor, Maintainer and Monthly boards.

### Detail page (`README-detail-page.md`)

- **List view type sort.** The spec sorts Type in the same order as the group tiles (subtotal
  descending), points descending within each type. The code sorts by action key alphabetically
  (`ScoreLeaderboardController.php:419`). Pages: detail page List view, 12-month and monthly.

### Scoring modal and How scoring works (`README-scoring-modal.md`, `README-how-scores-work.md`)

- **Maintainer example copy.** The spec's canonical prose opens "When a `Priority: P1` PR you
  approved later merges, the merge bonus alone earns…". The 12-month modal still reads "A
  `Priority: P1` PR you approved later merges. The merge bonus alone earns…"
  (`_scoring-modal.blade.php:116`). The How scoring works page and both contributor examples
  already match. Pages: scoring popup on the Maintainer board and maintainer detail pages.

## Unsure

None at the moment.

## Kept on purpose

### Header "My contributions" link (`app.blade.php`)

- **Pages:** every page, signed in.
- **Spec says:** shown to everyone.
- **Code does:** hidden if the user has no GitHub username.
- **Why:** the link goes to the user's own detail page, which needs a username.

### Header wordmark below `lg` (`_chrome-styles.blade.php`)

- **Pages:** every page, below 992px.
- **Spec says:** full "Magento Open Source Forger".
- **Code does:** "Forger" only below `lg`; mark alone below 400px. Hidden words stay in the
  accessible name.
- **Why:** logo, login and hamburger don't fit on one 64px row otherwise; the login button
  wrapped over the page title.

### Header height below `lg` (`_chrome-styles.blade.php`)

- **Pages:** every page, below 992px.
- **Spec says:** 64px.
- **Code does:** 64px closed; grows when the hamburger menu is open.
- **Why:** a fixed 64px bar let the open menu overlap the page.

### Month links on the monthly board

- **Pages:** Monthly board.
- **Spec says:** `?month=2026-09`.
- **Code does:** `/leaderboard/monthly/{board}/2026-09`.
- **Why:** cleaner addresses.

### Board search box

- **Pages:** Contributor, Maintainer and Monthly boards.
- **Spec says:** padding `7px 12px`.
- **Code does:** padding `7px 30px`.
- **Why:** makes room for the search and clear icons inside the box.

### Monthly detail intro (`score-detail.blade.php`)

- **Pages:** monthly detail page.
- **Spec says:** plain sentence.
- **Code does:** adds "— impact-weighted, no recency decay".
- **Why:** tells the reader how the monthly score differs.

### Detail page group headers (`_lb-styles.blade.php`)

- **Pages:** detail page, 12-month and monthly.
- **Spec says:** columns sized to content (`1fr auto auto`).
- **Code does:** fixed widths (`1fr 112px 66px`).
- **Why:** keeps header numbers lined up with the rows below.

### By-month bars (`by-month.blade.php`)

- **Pages:** Issues By Month, PRs By Month.
- **Spec says:** link to that month's list.
- **Code does:** link to a filtered GitHub search.
- **Why:** decided earlier; noted in the code.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
- **Monthly scoring popup** — the monthly board has no score decay, so the code shows a
  no-decay version of the scoring popup. The spec only describes the 12-month version.
  Pages: Monthly board.
- **"Inactive" badge on board rows** (`_board.blade.php:124`) — no spec mentions it.
  Pages: Maintainer board (only its rows carry an active flag).
