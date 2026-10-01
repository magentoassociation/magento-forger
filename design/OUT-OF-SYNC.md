# Out of sync — spec vs code

Drift between the `specs/` handoff and the live Laravel/Blade code. First pass **2026-09-25**;
second audit **2026-10-01**; drift fixes applied **2026-10-01**. Items resolved are removed.

Legend: **[open]** still diverges · **[design call]** spec and code disagree and the code's choice
may be the better one (left as-is by decision) · **[out of scope]** not governed by the approved specs.

> Note on the type scale: `README.md`'s *Contributor Leaderboard* type sizes are the earlier
> `#6a` generation and are **superseded** by `README-leaderboard-pages.md`, which the boards
> follow. Those size deltas are not listed here — they are not real drift.
>
> Note on H1 title blocks: the boards, monthly board, Highlights and How-Scores-Work pages all
> render an H1 via the shared `components/header.blade.php` include (only home + the two detail
> routes are exempt in `layouts/app.blade.php`). Earlier passes flagged these as "no H1" by reading
> only the page blade and missing the layout include — those were false positives, now removed.

---

## Foundation — `README.md`

- **[design call]** Title block adds a rule→content gap (`margin-bottom:24px` on `.page-title-bar`,
  `_chrome-styles.blade.php`) vs spec's no-gap rhythm. Detail pages zero it, so net spacing is
  consistent.

## Header & footer — `README-header.md`

- **[design call]** "My Contributions" is hidden when the user has no GitHub handle
  (`@if($acctLogin)`, `app.blade.php`). Spec shows it to everyone, but the link targets a per-login
  detail route — a user with no handle has no valid target, so the guard is required.
- **[design call]** Login button carries a GitHub glyph the spec's text-only button doesn't list
  (`app.blade.php`). Matches the footer Slack button's treatment.
- *Note:* the footer "relocated padding" item (sf-inner `38px 36px 0` + bottom on `.sf-legal`) is
  **not drift** — it is how the full-bleed divider the spec calls for (L35) is achieved while keeping
  the same 34px gap and 30px footer bottom. Removed.

## Leaderboard boards — `README-leaderboard-pages.md`

- **[open]** **Loading & failure states unbuilt** — no first-load skeleton, no append skeleton, no
  whole-board "didn't load / Try again", no failed-append, no 10s timeout. The board is fully
  server-rendered (`_board.blade.php` renders the whole population; rows reveal in-place via CSS/JS,
  no XHR). These states presuppose async loading, so hosting them means converting the board from
  sync to async — which would also require re-implementing the search + jump-to-rank features that
  currently work against the full DOM. **Deferred** — revisit if/when the boards move to async
  loading; until then there is no async window for these states to occupy.
- **[design call]** Month chips link via REST routes (`/leaderboard/monthly/{board}/{ym}`); spec
  L260 wants `?month=YYYY-MM` query params. Code's routing is arguably cleaner.
- **[design call]** Search input padding `7px 30px` vs spec `7px 12px` — clears the in-field `⌕`
  glyph and `✕` clear button.

## Monthly board — `README-leaderboard-pages.md`

- **[design call]** Month-chip URL scheme — same item as the boards section above.

## Contributor / maintainer detail — `README-detail-page.md`

- **[design call]** Monthly drill-down intro adds un-spec'd "— impact-weighted, no recency decay"
  mid-sentence (`score-monthly-detail.blade.php`).
- **[design call]** Group-head grid `1fr 112px 66px` vs spec L116 `1fr auto auto`
  (`_lb-styles.blade.php`) — fixed columns align count/subtotal with the item rows.
- **[out of scope]** The `#9a` flat-list view is built and reachable via a live Grouped/List
  toggle. Spec says build `#9b` only, `#9a` if `#9b` is rejected — code ships both.

## Scoring modal — `README-scoring-modal.md`

- No drift. (The undocumented non-decay "monthly" modal variant is listed under Out of scope.)

## How Scores Work — `README-how-scores-work.md`

- No open drift. (H1 renders via the shared header; eyebrow copy, body-frame padding fixed.)

## Highlights — `README-highlights.md`

- **[open]** Tab-strip literal values differ from this spec's numbers, but the shared
  `.lb .nav-tabs` component was restyled under turn 21; likely intentional. *Verify, low priority.*

## Homepage — `README-homepage.md`

- **[design call]** Ranked visitor row is a focusable full-row link (`welcome.blade.php`, following
  spec body L170) vs spec's accessibility note L326 "the row holds nothing focusable". The spec
  self-contradicts.

---

## Fixed in the 2026-10-01 pass

- Foundation: score help dotted-underline `#9aa0a8` → `#6b7178`.
- Header: logout menu item padding → `12px 10px 7px`.
- Boards: `.lb-empty-2 a` focus-visible ring added; 561–700px intermediate strip wrap (caption line
  1, search+jump line 2); narrow tab-row right-edge white gradient affordance.
- Monthly board: intro now states the board is not decayed.
- Detail: avatar hover transition `100ms` → `120ms`; other-board copy "… Board" → "… Leaderboard";
  monthly drill-down zero state replaced the Bootstrap alert with the `.lb-d-empty` panel (handle no
  longer repeated); monthly drill-down header now gets the zero-score grey treatment.
- How Scores Work: example eyebrow "Worked example" → "Example"; `.hsw` body frame padding added
  (`24px` top / `36px` bottom; horizontal gutter from the container so it aligns with the H1).
- Highlights: "Sort by recent activity" control + "Show all N" comebacks (12 by default), mirroring
  the detail page's Show-all-as-URL pattern.
- Homepage: first-timer step-link hover `#8f3a10` → `#c74e16`; below `lg` the viewer's own top-5 row
  (`.hp-board-row--you`) is no longer dropped by the `nth-child(n+5)` rule.
- Issues/PRs by month: timeline rewrite (prior pass).

---

## Out of scope (not governed by the approved specs)

- **`score-company.blade.php`** still uses the old Bootstrap `table table-hover` + badges. The
  Company board is nav-hidden and not one of the three approved boards.
- **`universe-bar.blade.php`** uses an off-palette grey/`#3c3c3c` scheme. It is not dead code —
  it is served as an external embed via `/api/universe-bar` (`UniverseBarController`) — and sits
  outside the dark-chrome redesign's surface.
- **Non-decay "monthly" scoring modal** — code renders a `decay=false` variant via
  `scoringExplainer(decay:false)`; the spec covers only the 12-month decay modal.
