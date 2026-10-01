# Out of sync — spec vs code

Drift between the `specs/` handoff and the live Laravel/Blade code. First pass **2026-09-25**;
items tagged *(2026-10-01)* were added in a second audit pass on that date. Items resolved since
the first pass have been removed.

Legend: **[open]** still diverges · **[design call]** spec and code disagree and the code's choice
may be the better one · **[out of scope]** not governed by the approved specs.

> Note on the type scale: `README.md`'s *Contributor Leaderboard* type sizes are the earlier
> `#6a` generation and are **superseded** by `README-leaderboard-pages.md`, which the boards
> follow. Those size deltas are not listed here — they are not real drift.

---

## Foundation — `README.md`

- **[open]** *(2026-10-01)* Score help dotted-underline wrong grey — spec `#6b7178` (token table
  L276), code `#9aa0a8` (`_lb-styles.blade.php:328`, `.lb-score-has-tip`). Not restated in the board
  spec, so Foundation's token governs.
- **[design call]** *(2026-10-01)* Title block adds a rule→content gap (`margin-bottom:24px` on
  `.page-title-bar`, `_chrome-styles.blade.php:223`) vs spec's no-gap rhythm. Detail pages zero it, so
  net spacing is consistent.

## Header & footer — `README-header.md`

- **[open]** Logout menu item missing its top-padding offset above the divider
  (`_chrome-styles.blade.php` `.acct-logout`). Spec `padding: 12px 10px 7px`. *Minor.*
- **[open]** Footer body padding relocated (`38px 36px 0` + bottom on `.sf-legal`) vs spec
  `38px 36px 30px`. *Cosmetic.*
- **[open]** *(2026-10-01)* "My Contributions" hidden when the user has no GitHub handle
  (`@if($acctLogin)`, `app.blade.php:61-63`) — spec shows it to everyone. *Edge case.*
- **[design call]** *(2026-10-01)* Login button carries a GitHub glyph the spec's text-only button
  doesn't list (`app.blade.php:75`). Matches the footer Slack button's treatment.

## Leaderboard boards — `README-leaderboard-pages.md`

- **[open]** **Loading states unbuilt** — no first-load skeleton, no append skeleton. Board is
  server-rendered.
- **[open]** **Failure states unbuilt** — no whole-board "didn't load / Try again", no
  failed-append, no 10s timeout.
- **[open]** **No H1 title block** on the contributor and monthly boards (`score.blade.php`,
  `score-monthly.blade.php`). Layout renders `<title>` only. *(Detail pages do have their H1.)*
- **[open]** Narrow (<560px): tab-row horizontal scroll has no white gradient affordance.
- **[open]** Focus-ring gap: `.lb-empty-2 a` has no `:focus-visible` ring.
- **[open]** *(2026-10-01)* No 560–700px intermediate narrow-strip wrap (caption on line 1,
  search+jump full-width on line 2); code jumps straight to the full unstack at `max-width:560px`
  (`_lb-styles.blade.php:1068`). *Low priority.*
- **[design call]** Month chips link via REST routes (`/leaderboard/monthly/{board}/{ym}`); spec
  L260 wants `?month=YYYY-MM` query params. Code's routing is arguably cleaner; changing it
  touches `routes/web.php` + controller + the detail sub-route.
- **[design call]** *(2026-10-01)* Search input padding `7px 30px` vs spec `7px 12px`
  (`_lb-styles.blade.php:144`) — clears the in-field `⌕` glyph and `✕` clear button.

## Monthly board — `README-leaderboard-pages.md`

- **[open]** *(2026-10-01)* Intro never positively states the board is "not decayed" — spec L252
  wants both the recency drop (done) and the affirmative statement (absent). The monthly *detail*
  page does say "no recency decay"; the board page does not (`score-monthly.blade.php:12`). *Minor.*
- **[design call]** Month-chip URL scheme — same item as the boards section above.

## Contributor / maintainer detail — `README-detail-page.md`

- **[open]** Avatar hover transition `100ms` vs the blanket `120ms` rule. *Trivial.*
- **[open]** *(2026-10-01)* Other-board line copy "`{Board}` Board" → "Maintainer Board" vs spec's
  "Maintainer **Leaderboard**" (`score-detail.blade.php:147`). The board no-results block uses the
  correct "Maintainer Leaderboard" (`_board.blade.php:164`), so the detail page is inconsistent with
  both spec and sibling code.
- **[open]** *(2026-10-01)* Monthly drill-down zero state still renders a Bootstrap
  `alert alert-info` — the exact pattern spec L300 says to replace with the `.lb-d-empty` panel (also
  repeats the handle, which the spec calls out) (`score-monthly-detail.blade.php:22`).
- **[open]** *(2026-10-01)* Monthly drill-down header omits the zero-score grey treatment (no `zero`
  flag), so a 0.0 monthly score renders in full-strength `#15171b` instead of `#6b7178`
  (`score-monthly-detail.blade.php:14-16`). *Minor.*
- **[design call]** *(2026-10-01)* Monthly drill-down intro adds un-spec'd "— impact-weighted, no
  recency decay" mid-sentence (`score-monthly-detail.blade.php:19`).
- **[design call]** *(2026-10-01)* Group-head grid `1fr 112px 66px` vs spec L116 `1fr auto auto`
  (`_lb-styles.blade.php:857`) — fixed columns align count/subtotal with the item rows.
- **[out of scope]** The `#9a` flat-list view is built and reachable via a live Grouped/List
  toggle. Spec says build `#9b` only, `#9a` if `#9b` is rejected — code ships both. Not a bug,
  just beyond the brief.

## Scoring modal — `README-scoring-modal.md`

- No drift. (The undocumented non-decay "monthly" modal variant is listed under Out of scope.)

## How Scores Work — `README-how-scores-work.md`

- **[open]** **No H1 "How Scores Work" title block** — page opens at `.hsw-intro`
  (`scoring.blade.php`).
- **[open]** *(2026-10-01)* Example-panel eyebrow reads "Worked example" vs the modal's verbatim
  "Example" the spec says to reuse (`scoring.blade.php:63`). *Could be a deliberate design call.*
- **[open]** *(2026-10-01)* No `.hsw` body-frame padding (spec `24px 36px 36px`, full width); the
  page falls back to Bootstrap `.container` gutters (`layouts/app.blade.php:93`). Distinct from the
  no-H1 item above.

## Highlights — `README-highlights.md`

- **[open]** No H1 "Leaderboard Highlights" title block.
- **[open]** Missing "Sort by recent activity instead" control (only a static caption).
- **[open]** Missing "Show all N" for >12 comebacks (hard `take(12)`).
- **[open]** Tab-strip literal values differ from this spec's numbers, but the shared
  `.lb .nav-tabs` component was restyled under turn 21; likely intentional. *Verify, low priority.*

## Homepage — `README-homepage.md`

- **[open]** First-timer step-link hover `#8f3a10` vs spec `#c74e16`. *Minor.*
- **[open]** *(2026-10-01)* Below `lg`, `.hp-board-row:nth-child(n+5){display:none}`
  (`_chrome-styles.blade.php:1101`) hides a signed-in viewer's own highlighted row when they rank 4
  or 5 — violates spec's "visitor's row is never dropped at any width". The media query has no
  `--you` exception.
- **[design call]** *(2026-10-01)* Ranked visitor row is a focusable full-row link
  (`welcome.blade.php:73`, following spec body L170) vs spec's accessibility note L326 "the row holds
  nothing focusable". The spec self-contradicts.

---

## Out of scope (not governed by the approved specs)

- **`score-company.blade.php`** still uses the old Bootstrap `table table-hover` + badges. The
  Company board is nav-hidden and not one of the three approved boards.
- **`universe-bar.blade.php`** uses an off-palette grey/`#3c3c3c` scheme. It is not dead code —
  it is served as an external embed via `/api/universe-bar` (`UniverseBarController`) — and sits
  outside the dark-chrome redesign's surface.
- **Non-decay "monthly" scoring modal** *(2026-10-01)* — code renders a `decay=false` variant
  (reworded intro, formula strip without the Recency token, example with no recency factor) via
  `scoringExplainer(decay:false)`; the spec covers only the 12-month decay modal
  (`_scoring-modal.blade.php:24-36`).
