# Handoff: Contribution detail page — UX + typography

Companion to `README.md` (foundations) and `README-leaderboard-pages.md` (the boards this page
is reached from).
The **Design tokens** and **Type scale** sections of `README.md` apply unchanged.
Implement option **`#18a`** in `../Leaderboard prototype.html`. Grouped is the default view;
the Grouped / List toggle switches to a flat list (`#18b`, see *List view*).

## What this page is for

It is reached from a contributor's name on the leaderboard, and its single
job is to answer "why is this person's score 839.3?". The current page does not answer that:
it lists items sorted by points with no subtotals, and the four-category breakdown that the
leaderboard tooltip already shows is absent here.

## Problems in the current page

1. **A column of repeated text.** "PR was merged" appears on fourteen consecutive rows.
   An Action column whose value is identical down the page is not carrying information.
2. **Every date wraps to two lines** ("Aug 31," / "2026"), doubling row height for no gain.
3. **The page title names a page type, not a person** — "Contributor Contributions" as H1 with
   the contributor's name demoted to a line below it.
4. **The total is buried mid-sentence** ("… behind this score — 839.4 pts total, reconciling
   with the board").
5. **Sorted by points with no control** — dates jump around and the user cannot re-sort.
6. **Only a fraction of the list is shown**, with no indication of what is missing.
   201 opened PRs are worth 585.6 points, more than everything else combined; three appear.
7. **The total doesn't reconcile.** The page says 839.4; the category figures sum to 839.3,
   which is also what the board shows. Use **839.3**.

## Structure of `#18a`

```
avatar · name · handle          |  839.3 / POINTS · 12 MONTHS     title block
intro paragraph
group  PRs opened                      201 items      585.6
group  PRs merged                       14 items      195.5
group  Issues opened                    47 items       41.0
group  Issues resolved by a merged PR    3 items       17.2
other-board line (only if on both boards)
```

Group order is by subtotal descending. **The four subtotals sum to the headline score** — this is
the point of the page, so the copy states it and the numbers must actually add up.

The other-board line is the page's last element, below the final group — see "Link to the
person's other board".

### Header — the page title block (`#18a`)

Every other page on the site is one dark bar, one white title block, one rule, then content. The
detail page had no title block: the bar butted straight into the back link and the identity row
floated on white with nothing between it and the first group, which is why it read as a different
site from the leaderboard it was reached from.

**It gets the same title block as every other page — the person's name is the H1 in it.** Do not
add a generic heading ("Contributor Detail", the board name) above the name; there is one title
on this page and it is the person.

Block: `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`. The border is full-bleed,
the contents sit in the page container — identical to the title block in `README-header.md`.

- **Identity row** — `display: flex; align-items: center; gap: 16px`. It is the first thing in the
  block; nothing sits above it.
- **No back link.** The current yellow outlined "Contributor Board" button is removed, not
  restyled. Reasons, in order: it sat above the H1, so the title block was ~30px taller on detail
  pages than on every other page and the title visibly jumped when clicking through from a board;
  the nav bar already marks Leaderboard as the current section; and these pages are only reachable
  from a board, so browser back is the route people actually use. Two intermediate drafts — the
  link inside the title block, then below the rule as the first line of content — are both
  superseded by removing it.

  If a route back is needed later, put it in the content area below the rule (not in the title
  block, and not top-right, where it would compete with nothing on a page that has no other
  corner element).
- **Avatar** — 56×56, `border-radius: 9px`, links to GitHub, hover
  `box-shadow: 0 0 0 2px #ee6524`. Larger than the 52px of earlier drafts, to hold the H1 at its
  new size.
- **Name** — Libre Franklin 700, **40px**, `letter-spacing: -.032em`, `line-height: 1.05`. This is
  the `<h1>`, at the same size as "Contributor Leaderboard" on the board it came from — that
  equivalence is the point.
- **Handle** — Martian Mono 400, 11px, `#5d636c`, links to the GitHub profile.
- **Score block** — right-aligned. Value Martian Mono 700, **34px**, `letter-spacing: -.02em`,
  `line-height: 1`, tabular. Label below: Martian Mono 400, 9px, `.06em`, uppercase, `#6b7178`,
  `margin-top: 5px` — "Points · 12 months". The score is the second-largest thing on the page and
  must stay on the H1's baseline block, not drift below it.

**Below the rule**, in the leaderboard's own order — nothing here sits in the title block:
1. **Intro** — Libre Franklin 400, 14.5px/1.65, `#3c4148`, `max-width: 660px`,
   `padding-top: 24px`: "Every scored contribution in the last 12 months, grouped by what earned
   the points. Each group's points sum to the grand total."
   The monthly detail page words this differently; see *The monthly detail page*.
2. **"How scoring works"** — a `<button type="button">` opening the scoring modal, inline at
   the **end of the intro paragraph above**, not on a line of its own. Libre Franklin 500, 14.5px,
   `#ee6524`, underlined, link-reset button styles (`README-scoring-modal.md`). This matches the
   leaderboard pages (`README-leaderboard-pages.md`), where it was moved into the intro
   sentence because standing alone it read as a stray. Which modal it opens follows the page:
   contributor detail opens 19b, maintainer detail opens 19a.
3. **Grouped / List toggle**, then the **month chips** — `margin-top: 20px` and `16px`.
4. The groups — or, on a zero-score page, the "What scores on this board" panel.

The intro block is `padding: 24px 36px 0`; the block that follows it opens with **24px of its own
top padding** (`padding: 24px 36px 34px`, or `4px 36px 34px` where the first group head already
carries `padding: 20px 0 9px`). Either way there is ~24px between the last line of the intro
group and the first group head or panel edge — the intro must not sit directly on the content
below it.

This ordering matters as much as the block itself: on the leaderboard the sequence is title →
intro → scoring button → tabs → table, and the detail page now reads the same way.

Applies to **all four detail states** — contributor and maintainer, scored and zero. See `#18a`
in the prototype for the full scored page with the site header above it, including the working
Show-all filter; `#17a`, `#17b` and `#17c` show the maintainer and zero-score states.

### Grouped / List toggle
`display: flex; gap: 16px`, `margin-top: 20px` below the intro. Two labels, Martian Mono 9.5px,
uppercase, `letter-spacing: .06em`, `padding-bottom: 5px`.
- Active — weight 700, `#15171b`, `border-bottom: 2px solid #ee6524`. Not a link.
- Inactive — weight 400, `#6b7178`, no underline, a link; hover `#15171b`.

It is the same treatment as the List view's sort headers, so the page has one kind of switch.

### Month chips
`display: flex; gap: 7px; flex-wrap: wrap`, `margin-top: 16px` below the toggle. Martian Mono
10.5px, uppercase, `letter-spacing: .06em`, `padding: 6px 12px`, `border-radius: 6px`.
- Selected — background `#15171b`, text `#ffffff`.
- Others — `1px solid #d5d8dc`, colour `#3c4148`, no fill; hover `border-color: #15171b` — the
  site's outlined control.
- Order — "All" first, then the last 12 months, most recent first, each with its year ("SEP 2026").
- Links — each chip is an `<a href>` with `?month=YYYY-MM`; "All" carries no param and is the default.

How they differ from the monthly board's chips (`README-leaderboard-pages.md`): the detail page
has an **All** chip, because it lists items and can show the whole 12-month window at once; a
ranking needs exactly one window, so the board has none. The detail chips are outlined rather
than filled because they sit directly under the Grouped / List toggle, and a second row of filled
shapes would compete with it. They are also a size step larger than the board chips (10.5px and
`6px 12px`, against 10px and `6px 11px`): every detail chip carries its year and a border, and
needs the extra room. Selecting a month filters the groups (or the tiles and list) and
keeps the current view.

### Group head
`display: grid; grid-template-columns: 1fr 112px 66px; gap: 0 16px; align-items: baseline` — the
item row's columns, so the count sits over the dates and the subtotal over the points,
`padding-bottom: 9px`, **`border-bottom: 1px solid #15171b`** — full-strength ink, not a grey
hairline. Group block `margin-bottom: 22px`.
- name — Libre Franklin 700, 17px, `-.02em`
- count — Martian Mono 700, 9px, `.06em`, uppercase, `#4c525a` (e.g. "201 items"; singular "1 item")
- subtotal — Martian Mono 700, 15px, tabular, `min-width: 66px`, right-aligned

**Two rule weights do the grouping**, and the difference between them is the structure of the
page:

| Rule | Value | Where |
|---|---|---|
| Group rule | `1px #15171b` | under each group head |
| Row rule | `1px #f0f1f3` | between item rows, and under the last row of a group |
| Block rule | `1px #e6e7ea` | under the page title block only |

A grey hairline under the group head would read as one more row separator and the groups would
stop being legible as groups. The black rule is what says "everything below this line belongs to
this heading" — it is the only place on the page where ink is used as a rule, and it is why the page can carry four groups (six on the maintainer page) without boxes, cards or
fills around them.

### Item row
Whole row is an `<a>` to the GitHub issue or PR. Within a group, rows sort by points descending,
ties newest first — the same order as the List view's default.
`grid-template-columns: 1fr 112px 66px`, `gap: 0 16px`, `align-items: baseline`,
`padding: 9px 0`, `border-bottom: 1px solid #f0f1f3`, hover `background: #faf9f7`.
- **title** — Libre Franklin 14.5px/1.45, one line, truncated with an ellipsis
  (`overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0`). The List view
  truncates the same way, so both views read alike; the full title is one click away on GitHub
  and in the row's `title` attribute.
- **date** — Martian Mono 9.5px, `#5d636c`, `white-space: nowrap`, format `31 Aug 2026`
  (one line, day-first, no comma).
- **points** — Martian Mono 700, 12px, tabular, right-aligned. **No green pill.**

## Link to the person's other board

A contributor detail page and a maintainer detail page are separate pages with separate scores,
and many maintainers appear on both boards. Nothing on either page currently says the other
exists, so a maintainer lands on one score with no way to know the other is there.

**When the same person appears on both boards**, the page carries one line **below the groups**
(below the empty-state panel on a zero-score page), `margin-top: 22px`, Libre Franklin 400,
12.5px/1.55, `#6b7178`, `max-width: 560px`:

- On the contributor page: "Maintainer score is tracked separately on the Maintainer
  Leaderboard."
- On the maintainer page: "Contributor score is tracked separately on the Contributor
  Leaderboard."

The board name is the link — site link colour `#ee6524`, underlined. No arrow, no button.

It is a pair: whichever page you are on points at the other, so the two are reachable in both
directions.

**It goes below the content — never in the title block or the top-right corner.** The
relationship between a person's two scores is a footnote to the page, so it sits where footnotes
sit. (Historical note: an earlier draft put it top-right as an arrow-suffixed "Also on the
Maintainer Board →", opposite the then-existing back link. At the same size and colour as that
link it read as primary navigation. Both are gone.)

**When it is not shown:** the person appears on only one board. Absence of the line is the signal
that there is no second page — do not render it without a link or as disabled text.

The line is driven by **presence on the other board**, not by a GitHub permission, so the
destination always exists. A maintainer with no rows in the 12-month window still has a page; it
shows its own empty state.

Shown in the prototype on `#18a` (contributor, scored), `#17a` (maintainer, scored) and `#17b`
(maintainer, zero). `#17c` is someone who is not a maintainer, and correctly has no line.

## "Show all" behaviour

Each group whose list is longer than what fits shows a control below its rows:
Martian Mono 9.5px, `#ee6524`, `border-bottom: 1px solid rgba(238,101,36,.4)` → `#ee6524` on
hover. Label "Show all 201 →".

**Clicking it filters the page to that group** — it does not expand in place and does not
paginate. The single-group view replaces the groups with:
- "← All contributions" (Libre Franklin 500, 13.5px, `#ee6524`, `margin-bottom: 16px`)
- the same group head, title bumped to 19px, count and subtotal unchanged
- the group's full list, same row spec

Rationale: expanding 201 rows in place pushes the other three groups off the page and destroys
the at-a-glance reconciliation; pagination hides the shape of the list. Filtering keeps one list
on screen, scales to any length, and is shareable.

**In production this must be a URL, not component state** — `?group=prs-opened` or
`/contributor/lbajsarowicz/prs-opened` — so the view can be linked and the browser back button
returns to the full page. The prototype uses local state only.

**Empty groups.** A group with no rows must not render a Show-all control that leads to an empty
panel. Suppress the control and show a plain line in its place
(Libre Franklin 13.5px/1.5, `#5d636c`). In the prototype that line explains the missing sample
data; in production the equivalent case is a genuinely empty group.

## List view

The toggle's second view, shown in `#18b` (contributor) and `#17d` (maintainer). Same title block, toggle and month chips as the
grouped view; the intro reads "Every scored contribution in the last 12 months in one list. The
points column sums to the grand total." followed by the inline "How scoring works" link.

**Group tiles** — one tile per group subtotal, above the list, so the page still reconciles
without group heads: four on the contributor page, six on the maintainer page.
`display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px` on the contributor
page, `repeat(3, minmax(0,1fr))` on the maintainer page (two rows of three),
`margin-top: 22px`. Each tile: `1px solid #e6e7ea`, `border-radius: 9px`, `padding: 16px 16px 18px`.
- Group name — Libre Franklin 13px/1.35, `#3c4148`, `min-height: 2.7em` (two lines reserved), so a
  long name such as "Issues resolved by a merged PR" can wrap without pushing its figure below the
  others. The subtotals in a row always sit on one line. Never truncate the name.
- Subtotal — Martian Mono 700, 20px, then the count "×201" in Martian Mono 10px `#5d636c`,
  `gap: 6px`, `margin-top: 14px`.
- Bar — 4px, radius 2px, track `#f0f1f3` (same as the Highlights score bar), fill `#ee6524`, `margin-top: 12px`. Width is the
  group's share of the **largest** group, so the biggest bar is always full.
- Same groups, same order as the grouped view. Narrow widths: two columns.

**Table head** — `margin-top: 28px`, `padding-bottom: 10px`, `border-bottom: 1px solid #e6e7ea`
(the site's table-header divider).
- "Scored contributions" — Libre Franklin 700, 18px, `letter-spacing: -.018em`, an H2.
- Sort headers Type · Date · Points — `<button>`s, Martian Mono 9px uppercase `.06em`. Inactive
  `#6b7178` 400, hover `#15171b`; active `#15171b` 700 with a 2px `#ee6524` underline.
  Default sort is Points, descending. Date sorts newest first; Type sorts in the same order as
  the group tiles above (subtotal descending), points descending within each type. Equal points
  break newest first, in every sort. Column widths: Type 212px — wide enough for the longest tag on either page, "Issue resolved
  by a merged PR"; Date 112px, Points 66px right-aligned — the same date and points widths as the grouped view's item row.

**Rows** — `padding: 9px 0`, `border-bottom: 1px solid #f0f1f3`, hover `#faf9f7`, whole row a link.
- Title — Libre Franklin 14.5px, one line, ellipsis — same as the grouped view.
- Type tag — Martian Mono 9.5px, `#3c4148` on `#f0f1f3`, `padding: 3px 7px`, `border-radius: 4px`,
  `white-space: nowrap` — a tag never wraps inside its pill.
  The tag is the **singular** of the row's group name, in sentence case: "PR opened", "PR merged",
  "Issue opened", "Issue resolved by a merged PR"; on the maintainer page "PR approved", "Change requested", "Review comment", "Approved PR that was
merged", "Stale PR claimed", "Triage label applied". A row is one item, so it takes the singular;
  the group heads and tiles name the set and stay plural.
- Date — Martian Mono 9.5px, `#5d636c`. Points — Martian Mono 700, 12px. Row padding, date and
  points match the grouped view's item row.

**Pagination** — the boards' "Show 25 more" control and "Showing 1–25 of N" count
(`README-leaderboard-pages.md`), `padding: 20px 36px 0`.

**Other-board line** — when the person is on both boards, the same line as the grouped view
(see "Link to the person's other board") follows the pagination block, `margin-top: 22px`, with
`30px` below it. It is the page's last element in both views.

**URL** — `?view=list` and `?sort=date|type` alongside `?month=`. Grouped and Points are the
defaults and carry no param. Month chips filter both the tiles and the list.

**Zero-score pages** suppress the toggle (see below), so the List view never renders empty.

## The monthly detail page

Reached from a name on the Monthly board. Shown in `#18c` (Grouped) and `#18d` (List). It is
the same page and template as the 12-month detail page, scoped to one month. Groups, Show all,
tiles, list sorts and pagination are unchanged. Only these differ:

- **Score block** — the month's score, the same figure as the Monthly board. Caption
  "Points · Aug 2026" (the selected month) instead of "Points · 12 months".
- **Intro** — names the month, and the first sentence ends with "— impact-weighted, no recency
  decay" so the reader knows how the monthly score differs from the 12-month one:
  - Grouped: "Every scored contribution in August 2026, grouped by what earned the points —
    impact-weighted, no recency decay. Each group's points sum to the grand total."
  - List: "Every scored contribution in August 2026 in one list — impact-weighted, no recency
    decay. The points column sums to the grand total."
  - The inline "How scoring works" follows, as on every detail state.
- **Month chips** — no "All" chip. The page always shows exactly one month, as the Monthly board
  does. Twelve chips, most recent first, the selected month filled (`#15171b`, white text);
  the others keep the outlined treatment above. A chip goes to that month's detail page for the
  same person.

## The maintainer detail page

The maintainer board's detail page is **the same page as this one** — same header row, same
grouped body, same month chips, same Show-all behaviour — with the maintainer dataset and the
maintainer board's group names ("PRs approved", "Changes requested", "Review comments",
"Approved PRs that were merged", "Stale PRs claimed", "Triage labels applied") — one group per
scored maintainer action, the same six the board's activity column counts. Build one template and pass it the board.

Only two things differ:
- The other-board line, when shown, points at the Contributor Leaderboard.
- The group names come from the maintainer scoring rules.

See `#17a` (Grouped) and `#17d` (List) in the prototype. Nothing else about it is new, and it
needs no separate spec.

## Zero-score state (`#17b`, `#17c`)

A person with a real page but no scored activity in the 12-month window — new contributors,
maintainers who have not reviewed recently, and anyone whose older work has rolled out of the
window. Today this renders a pale cyan Bootstrap alert, "No itemized contributions in this window
for lfolco": it reads as an error, repeats the handle that is already in the header, and says
nothing about what would earn a point. Replace it.

**One empty state serves both boards.** It differs only in its group names and its CTA.

### Header

Unchanged, with two adjustments:
- The score reads `0.0` in `#6b7178` rather than `#15171b` — the figure is real, not missing, but
  it should not carry the weight of a score. The "Points · 12 months" caption is unchanged.
- The intro paragraph is replaced (it otherwise promises groups that sum to a total):
  - Contributor: "No scored contributions in the last 12 months. PRs and issues you open from
    here will show up on this page, grouped by what earned the points."
  - Maintainer: "No maintainer activity scored in the last 12 months. Reviews and merges you
    complete from here will show up on this page, grouped by what earned the points."

"How scoring works" stays, inline at the end of the replacement intro paragraph. The Grouped / List toggle and the month chips are **suppressed**
— there is nothing to group, list or filter.

### The panel

In place of the groups, one bordered panel: `1px solid #e6e7ea`, `border-radius: 10px`,
`overflow: hidden`.

**Head** — `padding: 16px 20px 13px`, `background: #faf9f7`,
`border-bottom: 1px solid #f0f1f3`. Label "What scores on this board", Martian Mono 400, 9px,
uppercase, `.06em`, `#6b7178`.

**Rows** — one per scoring group, in the board's own order, each
`display: flex; align-items: baseline; gap: 12px; padding: 13px 20px;
border-bottom: 1px solid #f0f1f3`:
- Name — Libre Franklin 600, 14.5px, `letter-spacing: -.012em`, `#3c4148`.
- Count — "0 items", Martian Mono 400, 9px, uppercase, `.06em`, `#6b7178`.
- Points — "0.0", Martian Mono 500, 12px, `#6b7178`, `width: 66px`, right-aligned.

The rows are **not links** — there is nothing to open. They are quieter than a scored row by
weight (500 against 700) and by the absence of a hover state, **not** by lightness: the zero
values use `#6b7178` (4.93:1 on white), the same grey as the headline 0.0. An earlier draft used
`#9aa0a8` at 2.6:1 — do not lighten these back. The whole point of the panel is that it is read.

Groups shown:

| Board | Groups |
|---|---|
| Contributor | PRs opened · PRs merged · Issues opened · Issues resolved by a merged PR |
| Maintainer | PRs approved · Changes requested · Review comments · Approved PRs that were merged · Stale PRs claimed · Triage labels applied |

These must be the **same list, in the same order, that a scored page would show** — the panel is
the scoring rules with zeros in them, so it stays correct automatically as rules change. Do not
hand-maintain a second list.

**Foot** — `padding: 16px 20px 18px`, `display: flex; align-items: center; gap: 14px;
flex-wrap: wrap`:
- First, the line "The groups above are the ones that earn maintainer points. Each fills in as you
  go." — Libre Franklin 400, 13px/1.5, `#5d636c` ("contributor points" on the contributor page).
- Then the CTA, after the line and right-aligned (`margin-left: auto`): the site's orange button —
  `padding: 10px 17px`, `border-radius: 7px`,
  background `#f26322`, ink `#15171b`, Libre Franklin 700, 14px, hover `#ff7433`.
  Contributor: "Find an issue to work on →" (same destination as the homepage CTA).
  Maintainer: "Find a PR to review →".

### Below the panel

The other-board line, exactly as on a scored page — see "Link to the person's other board"
above. `margin-top: 16px` here rather than 22px, since the panel already has interior padding.
Omitted entirely when the person is on only one board.

### Copy rules

The empty state names no threshold and quotes no number of PRs — same rule as the homepage's
empty row (see `README-homepage.md`). It says what scores, not what it costs to rank.

Do not use a Bootstrap `alert` class, or any tinted status panel, for this state. It is not a
warning, an error or a success; it is the page, with zeros.

## Narrow widths

Drawn at 420px in `#24b`. Below the Bootstrap `lg` breakpoint:
- The gutter becomes 20px, as on every page; the title block is `padding: 26px 20px 24px`.
- The H1 drops from 40px to 28px, the boards' narrow size. The identity row keeps its structure
  — avatar, name and handle, score block on the right — and a long name wraps rather than
  truncating.
- The intro, inline "How scoring works", Grouped / List toggle and month chips keep their
  order. The chips wrap.
- Group heads and item rows keep their grid. Titles truncate with an ellipsis, as on desktop.
- The List view's group tiles drop to two columns (see *List view*).

## Data notes
- The four category figures come from the same source as the leaderboard tooltip
  (opened a PR 201× 585.6 · PR merged 14× 195.5 · opened an issue 47× 41.0 ·
  issue resolved by a merged PR 3× 17.2).
- Display the total as the sum of those four. If the stored total and the sum disagree, the sum
  is what the page shows, or the copy must stop claiming they reconcile.

## Files
- `../Leaderboard prototype.html` — implement `#18a` (Grouped) and `#18b` (List).
- `reference-detail-current.png` — the detail page before the redesign.
- Turn 17 — the maintainer detail page (`#17a` Grouped, `#17d` List) and the zero-score state on both boards
  (`#17b` maintainer, `#17c` contributor).
- Turn 18 — `#18a`, the grouped page with the title block and Show-all filter; `#18b`, the List
  view. (The earlier `#9b` card has been folded into `#18a` and removed.)
