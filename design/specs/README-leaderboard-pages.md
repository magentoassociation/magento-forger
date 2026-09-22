# Handoff: Leaderboard pages — Contributor, Maintainer, Monthly

## Overview
Covers the three ranked board pages. Approved directions: **21a — Contributor**,
**21b — Maintainer**, **21c — Monthly**. They are one template with three data sets and three
small differences, listed under *Per-board differences*.

Two problems are fixed.

**The scoring link had no home.** "How are scores tallied?" sat on its own line between the intro
paragraph and the tab row, attached to nothing above or below it. It now ends the intro
sentence, inline, where the reader is already thinking about how points are earned. This applies
to every page that shows a score, including the detail pages.

It is a `<button type=\"button\">` that opens the scoring modal, styled as a text link — never a
navigating link. The standalone How Scores Work page is reached only from the footer.
See `README-scoring-modal.md` for the button styles and modal behaviour.

**The list was a wall.** The board rendered every ranked person in one uninterrupted column —
no count, no search, no way for someone in 142nd place to reach their own row, and no indication
of where the list ends. Each row also carried the person's activity on a second line, doubling
row height for information that reads better as a column. The board now states its population,
offers search and a jump-to-me control, and paginates.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the intended look and behaviour, not production code to copy.
Reproduce the spec below in the Laravel/Blade + Bootstrap codebase using its existing template
conventions. Turn 21 holds all three boards.

## Fidelity
**High-fidelity** for colour, type and spacing. Row values in 21b and 21c are **placeholder** —
the layout is the spec, the numbers are not.

---

## Page frame

Standard site chrome: dark header per `README-header.md`, white title block, dark footer.

- Title block: `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`. H1 Libre Franklin
  700, 40px, `letter-spacing: -.032em`, `line-height: 1.05`, title case — "Contributor
  Leaderboard", "Maintainer Leaderboard", "Monthly Leaderboard".
- Body: `padding: 24px 36px 0`, full container width.

## Intro paragraph and the scoring link

Libre Franklin 400, 14.5px, `line-height: 1.65`, colour `#3c4148`, `max-width: 660px`,
`text-wrap: pretty`.

The scoring link is the **last thing in the paragraph**, inline, after "Note that scores are
subject to change." It is Libre Franklin 500, 14.5px, colour `#ee6524`, underlined — the
site-wide link treatment from `README.md`. It is not a separate block, not a button, and does
not sit on its own line.

Which modal it opens is board-dependent; see *Per-board differences*.

Apply the same inline placement anywhere else the link appears, including the detail pages
specified in `README-detail-page.md`.

## Tab row

`display: flex; gap: 2px; margin-top: 22px; border-bottom: 1px solid #e6e7ea`.

- Active tab: `padding: 9px 14px`, `border-radius: 7px 7px 0 0`, `1px solid #e6e7ea` with
  `border-bottom-color: #ffffff`, `margin-bottom: -1px`, background `#ffffff`, Libre Franklin
  600, 14px, colour `#15171b`. It sits on the rule, not under it.
- Inactive tabs: `padding: 9px 14px`, Libre Franklin 500, 14px, colour `#5d636c`, no underline,
  no border. Hover: `#15171b`.

Tabs are Contributor, Maintainer, Monthly, Highlights.

## Control strip

`display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 16px 0 14px`, directly
under the tabs.

**Population caption** (left) — Martian Mono 400, 9.5px, uppercase, `letter-spacing: .06em`,
colour `#6b7178`. Two facts, separated by ` · `: how many people are ranked, and the window.
Examples: "1,284 contributors · 12 months to Sep 2026", "318 maintainers · 12 months to Sep
2026", "214 contributors · September 2026".

**Search** (`margin-left: auto`) — `padding: 7px 12px`, `1px solid #d5d8dc`,
`border-radius: 7px`, `min-width: 210px`. Placeholder "Search name or handle", Libre Franklin
13.5px colour `#9aa3ae`, with a 12px `⌕` glyph. Filters the full board, not the loaded page —
searching for someone in 600th place must find them without paginating there first.

Once the field has a value, a clear control (`✕`, Libre Franklin 13px, `#9aa3ae` → `#15171b`)
sits at the field's right edge; it empties the field and restores the full board. `Esc` does the
same.

### Search results

Matching is case-insensitive **substring**, not prefix, over both the display name and the
handle, so "ava" finds both "Ava Ricci" and "@joravadi". Filtering is debounced ~150ms and the
rows filter in place — no separate results page, no modal.

- **Ranks do not renumber.** Each row keeps its real board rank, so a filtered list can read
  `4`, `87`, `612` in sequence. The rank is the answer the reader came for.
- **The population caption becomes the result count**, same type and colour:
  "3 of 1,284 contributors". It returns to the full caption when the search is cleared.
- **Pagination applies to the filtered set** and resets to page 1. The count statement under the
  list follows it: "Showing 3 of 3 matches". Under 25 matches there is no Load more control.
- **No match highlighting.** The accent colour is already carrying the rank and the count link;
  bolding substrings inside names adds a third signal to a row that does not need one.
- The Jump to my rank control stays visible and still works — it clears the search first, then
  jumps.

### No results

The column header stays in place; the rows are replaced by a single block, left-aligned at the
page gutter, `padding: 40px 0 44px`, `border-bottom: 1px solid #f0f1f3`.

- Line 1 — Libre Franklin 500, 15px, colour `#15171b`: `No one matching "foo" on this board.`
  The query is quoted verbatim and truncated with an ellipsis past 40 characters.
- Line 2 — Libre Franklin 400, 14px, `line-height: 1.6`, colour `#5d636c`, `margin-top: 6px`:
  the two likely fixes, in this order — the other board, then clearing the search. On the
  contributor board: "They may be on the Maintainer Leaderboard, or try a shorter search." Board name
  is a link in `#ee6524`; on the monthly board the first fix is the all-time board instead
  ("They may not have been active in September 2026 — try the Contributor Leaderboard.").

No illustration, no centred empty-state card, no "0 results" badge. The count statement under
the list reads "No matches" and the pagination control is suppressed.

**Jump to my rank** — signed-in only. `padding: 7px 12px`, `border-radius: 7px`, background
`#15171b`. Contains the user's 20px avatar, the label "Jump to my rank" (Libre Franklin 600,
13px, `#ffffff`), and their rank in Martian Mono 11px colour `#9aa3ae` (e.g. `#142`). Hover:
background `#2a2e34`.

Signed out, the control is absent — nothing takes its place.

### What clicking does

1. **Any active search is cleared first** (the field empties, the full board returns), because a
   jump into a filtered list would land on a row whose neighbours are not its real neighbours.
2. **Pages load up to the user's rank.** The board loads the pages between the current one and
   the page holding their rank — rank 142 means pages 1–6 are present afterwards, so scrolling
   up from their row walks the real board rather than jumping a gap. The control shows a
   disabled state with the label "Loading…" while this happens; it is one request per page and
   should be batched into a single range request if the backend allows it.
3. **The page scrolls their row to roughly a third from the top**, not to the very top — the
   ranks above are the context that makes a rank mean anything. Use a programmatic scroll on the
   window with `behavior: smooth`, honouring `prefers-reduced-motion: reduce` by jumping
   instantly.
4. **The row takes a highlight**: background `#fff6f1`, `box-shadow: inset 3px 0 0 #f26322`,
   held for 2s and then faded out over 400ms. Nothing else about the row changes — no bold, no
   badge, no permanent treatment. The highlight is decorative only.
5. **Focus moves to the row** (`tabindex="-1"` on the row, focused after the scroll) so keyboard
   and screen-reader users arrive where sighted users do. The row carries
   `aria-label="Your rank, 142"`; announce the arrival with a polite live region:
   "Jumped to your rank, 142 of 1,284."
6. **The URL gains `#rank-142`** so the position survives a reload and can be shared. On load
   with that fragment present, the board performs the same page loading and scroll without the
   highlight.

If the user is already on the loaded page, step 2 is skipped and the rest run unchanged —
clicking the control when the row is already visible still scrolls and highlights, which is the
correct answer to "where am I".

### Signed in but not ranked

A user with no scored activity in the window has no row to jump to. The control is **replaced**,
not hidden — a signed-in person should still get an answer about their own position. Same
position in the strip, same 7px radius, but no background fill: `1px solid #d5d8dc`,
`padding: 7px 12px`, the user's 20px avatar, then "You're not on this board yet" in Libre
Franklin 500, 13px, colour `#3c4148`. It is a link to their own detail page, where the zero
state explains what scores (see `README-detail-page.md`). No rank number, no `#—` placeholder.

Three cases produce it, and they read the same:

| Case | Control |
|---|---|
| Signed in, zero score in the window | "You're not on this board yet" → their detail page |
| Signed in, scored on the other board only | "You're not on this board yet" → their detail page |
| Monthly board, no activity in the selected month | "You're not on this board yet" → their detail page |

On the monthly board the label is per month, so it changes as the month chip changes — a user
can be ranked in August and absent in September. Nothing about the strip's layout shifts when it
does; the control keeps its width.

## Column header

`display: flex; align-items: center; gap: 16px; padding-bottom: 8px`,
`border-bottom: 1px solid #e6e7ea`. Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`,
colour `#6b7178`.

| Column | Width | Align |
|---|---|---|
| `#` | 42px | left |
| Contributor / Maintainer | `flex: 1` | left |
| Activity | 130px | right |
| Score | 70px | right |

The rank column is 42px so a four-digit rank (the board passes 1,000 contributors) fits without
eating the 16px gap before the avatar. Ranks stay left-aligned; the column does not grow.

## Rows

`display: flex; align-items: center; gap: 16px; padding: 10px 0`,
`border-bottom: 1px solid #f0f1f3`. One line per person — the old second line is gone, which is
what halves the row height.

- **Rank** — Martian Mono 13px. Ranks 1–3 are weight 700 colour `#15171b`; ranks 4+ are regular
  weight colour `#3c4148`. This is the only rank emphasis: no medals, no tinted rows, no
  podium.
- **Avatar** — 28×28, `border-radius: 6px`, `flex: none`.
- **Name** — Libre Franklin 600, 14.5px, colour `#15171b`, no underline; links to the person's
  detail page. Hover: `#ee6524`.
- **Handle** — Martian Mono 10px, colour `#6b7178`, baseline-aligned beside the name.
- **Activity** — Martian Mono 10px, colour `#6b7178`, right-aligned in its 130px column.
  Abbreviated and counted: `281 PR · 47 ISS`. Where a person has no counted activity, the cell
  reads "See contributions" (existing behaviour, unchanged).
- **Score** — Martian Mono 700, 14px, right-aligned.

## Pagination

`display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 20px 36px 30px`.

- Button "Show 25 more": `padding: 9px 18px`, `1px solid #d5d8dc`, `border-radius: 7px`, Libre
  Franklin 600, 13.5px, colour `#15171b`. Hover: `border-color: #15171b`.
- Count beside it: Martian Mono 9.5px uppercase `letter-spacing: .06em` colour `#6b7178`,
  reading "Showing 1–25 of 1,284". It updates as pages load.

25 rows per page. The count is what makes the control honest — "Show more" alone does not tell
the reader whether two rows remain or twelve hundred. When the last page is reached the button
disappears and the count reads "Showing all 1,284".

No download or export control.

## Per-board differences

Everything above is shared. Only these three things change:

| | Contributor (21a) | Maintainer (21b) | Monthly (21c) |
|---|---|---|---|
| Activity column | PRs and issues | reviews and merges (`412 REV · 180 MRG`) | PRs and issues |
| Scoring link opens | contributor modal (19b) | maintainer modal (19a) | contributor modal (19b) |
| Window control | caption only | caption only | month chips |

**Month chips (21c only).** A row between the tabs and the control strip:
`display: flex; gap: 7px; flex-wrap: wrap; padding-top: 16px`. Selected month is Martian Mono
10px, `padding: 6px 11px`, `border-radius: 6px`, background `#15171b`, text `#ffffff`, weight
700, and spelled with its year ("SEP 2026"). Other months are the same box on background
`#f7f5f2`, colour `#3c4148`, month only. Hover: `#ece9e4`. A final "All months →" link, Martian
Mono 10px colour `#6b7178`, no fill.

With chips present, the population caption names the month ("214 contributors · September 2026")
rather than a rolling window.

**Monthly intro copy.** The monthly board's intro says the board is not decayed and does not
mention recency, because monthly boards are not decayed. The contributor and maintainer intros
keep their existing 12-month wording.

### Month-chip behaviour

- **Single select.** Exactly one month is active at any time; there is no multi-select and no
  range. Clicking a chip selects it and deselects the previous one. A chip cannot be
  deselected — the board always shows a month.
- **It navigates, it does not filter in place.** This is how the chips already work: each is an
  `<a href>` carrying a `?month=YYYY-MM` query param, the convention the detail page uses
  (`leaderboard/contributor/user/lbajsarowicz?month=2026-04`). Keep the param name and format
  on the monthly board — `?month=2026-09` — so one rule covers both. Months stay linkable,
  bookmarkable and indexable, and the back button walks the months a reader looked at. A
  client-side fetch is fine as long as it `pushState`s the same URL.
- **Which months appear.** The current calendar year to date, most recent first, one chip per
  month — twelve at most, so the row wraps at most once. Months with no scored activity are
  still shown and lead to a board with the no-activity state rather than being omitted; a
  missing month reads as a bug.
- **Three visual states, not four.** Selected (dark fill), available (`#f7f5f2`), hover
  (`#ece9e4`). There is no separate "current month" treatment: on first load the current month
  *is* the selected one, so a second signal would be redundant. Once the reader picks another
  month, the current month is just another available chip.
- **"All months →"** goes to the all-time contributor board — the Contributor tab. It is the
  escape from the monthly view, not a thirteenth month.
- **Changing month resets the view**: search clears, pagination returns to page 1, and scroll
  returns to the top of the table rather than the top of the page, so the chips stay in view and
  a second month is one click away. The jump-to-my-rank control recalculates for the new month
  and may switch to its not-ranked form.
- **`?month` and `?rows` are independent, and changing month drops `?rows`.** A chip link
  carries `?month=2026-08` alone, never the current depth — 50 rows into September says nothing
  about August, and a board with 31 contributors should not open claiming to show 50. The new
  month starts at 25 and re-accumulates `?rows` from there.
- **Keyboard**: the chips are an ordinary list of links in DOM order — `Tab` through them, no
  arrow-key roving. They are not tabs and carry no `role="tab"`; they change the page's data,
  not a panel within it.

## Pagination state

- **"Show 25 more" appends.** Rows already loaded stay; the next 25 are added below and the count
  statement updates ("Showing 1–50 of 1,284"). Nothing is replaced, so a reader who scrolled past
  rank 30 does not lose their place. This is also what makes *Jump to my rank* possible without a
  page-by-page walk.
- **Focus after loading** moves to the first newly added row, so keyboard users continue where
  the list grew rather than from the button. Announce with a polite live region: "25 more loaded.
  Showing 1–50 of 1,284."
- **The control disappears at the end of the list**, leaving the count statement alone: "Showing
  all 1,284". It is never shown disabled.
- **URL** — the loaded depth is written as `?rows=50`, with `history.replaceState` rather than
  `pushState`, so the back button leaves the board instead of unwinding one click at a time. On
  load with `?rows=50` the board renders 50 rows directly. A `#rank-142` fragment from a jump
  sets the depth implicitly and takes precedence.
- **Under an active search** the filtered set paginates the same way and resets to 25 on each new
  query (see *Search results*). The `rows` parameter is dropped while a search is active, and the
  query itself is not put in the URL.
- **Without JavaScript** the control is a plain link to `?rows=50`, which renders the longer list
  and moves the link to `?rows=75`. The page still works; it just reloads.

## Focus rings — site-wide

One rule for every interactive element on these pages, matching the header
(`README-header.md`): **2px `#f26322` outline, `outline-offset: 2px`**, on `:focus-visible`
only, never suppressed. The default browser ring is not visible enough on `#15171b` and is
inconsistent across the light and dark surfaces this design uses.

It applies to — and is currently missing from — the search field (the ring replaces the
`#15171b` border change), its clear control, the jump-to-my-rank chip in both forms, month
chips, "Show 25 more", tab links, row name links, count links, the "How are scores tallied?"
button, and the score tooltip trigger.

Two notes:

- On the dark jump chip the ring sits outside the fill, so the 2px offset keeps it legible. Do
  not inset it.
- Hover and focus are different states and must not share a treatment. A row that only changes
  background on hover still needs the ring, or keyboard users get no feedback at all.

## Loading and failure

Drawn in `#25a`. The rule across all four states: never blank the page, and never move a row
that is already on screen.

**First load.** Skeleton rows at the real row height and column widths — `#eceef0` bars,
`border-radius: 4px` — so nothing shifts when the data arrives. Five rows, not twenty-five; the
fold is all anyone sees. Vary the name-bar width per row (135–215px); identical bars read as a
graphic rather than as pending content. No spinner. The caption below reads "Loading
contributors…" in the population caption's own type and colour, and is an `aria-live="polite"`
region that announces the count when the rows land.

**Appending.** Loaded rows stay exactly where they are; two skeleton rows appear below them and
the control reads "Loading…", disabled, with `#e6e7ea` border and `#9aa3ae` text. The count
statement does not change until the rows arrive.

**Whole-board failure.** Column header stays; the rows are replaced by the same block the
no-results state uses (see *No results*) — "The leaderboard didn't load." and, below it, "This
is usually temporary. Try again, or come back in a few minutes.", with *Try again* as an
`#ee6524` link that re-fetches in place. No status code, no illustration. The control strip is
hidden: there is nothing to search.

**Failed append.** Everything already loaded stays on screen. The "Show 25 more" control is
replaced by a "Try again" control in the same position with "Couldn't load more rows." beside
it in `#5d636c`. Never replace a populated list with an error.

**Timeout.** Treat as failure after 10s. A slow board that eventually loads is better than an
error the reader has to dismiss, so only escalate once the request has actually failed.

## Hover states

Tabulated in `#25b`; this is the whole set for these pages. All of them are a 120ms ease-out
transition on the property that changes, and nothing changes size, position or elevation — a
lift on each of 25 rows turns scanning into a flicker.

| Element | Rest | Hover |
|---|---|---|
| Table row | no background | `#faf9f7` |
| Name link | `#15171b` | `#ee6524` |
| Tab, inactive | `#5d636c` | `#15171b` |
| Month chip, available | `#f7f5f2` | `#ece9e4` |
| Outlined control | border `#d5d8dc` | border `#15171b` |
| Dark control | `#15171b` | `#2a2e34` |
| Orange button | `#f26322` | `#ff7433` |
| Text link | `#ee6524` | `#c74e16` |
| Timeline bar | as scaled | 1px `#15171b` outline at 1px offset, plus tooltip |
| Footer link | `#c9ced4` | `#fff` |

Three families: a surface warms one step in the direction it already leans, ink darkens, or
orange appears where there was none. Hover is never the only signal — every element here also
carries the focus ring above.

## Narrow widths

Drawn at 420px in `#24a`; the detail page is `#24b` and the scoring modal `#24c`.

The control strip wraps: caption on the first line, search and jump control on the second, both
full width. In rows, the activity column is the first thing to go — below roughly 700px it drops
and the handle moves under the name, returning the row to two lines. Rank, avatar, name and
score always remain.

Below roughly 560px the strip unstacks completely — caption, search, jump control, each on its
own full-width line, in that order, which is also their reading order: how many, find someone,
find me. The tab row scrolls horizontally rather than wrapping, with a white gradient at the
right edge as the affordance; it never becomes a select. The rank column narrows from 42px to
38px, which still clears a four-digit rank, the gap from 16px to 12px, and the H1 from 40px to 28px. The "Show 25 more" control goes
full width with its count statement below it rather than beside it.

## Out of scope
Ranking logic, score values, the Highlights tab (`README-highlights.md`), and the detail pages
(`README-detail-page.md`) — except for the inline scoring-link placement, which those pages
adopt too.
