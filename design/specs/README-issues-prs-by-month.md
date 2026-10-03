# Handoff: Issues By Month / PRs By Month

## Overview
Replaces the grid of month tiles on both pages with a single bar timeline of the whole backlog,
followed by a month picker for the current year. Approved direction: **14b — one timeline**.

The two pages share one template. Only the dataset and the noun change ("issues" / "PRs"),
including in the headings, the year totals and the hover text.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the
intended look and behaviour, not production code to copy. Reproduce the spec below in the
Laravel/Blade + Bootstrap codebase using its existing template conventions.

In the prototype, **`#14b` is the approved design**. An Issues / PRs switch beside its label (and beside `#24e`) swaps the dataset; the switch is a prototype control, not page UI.

Header, footer and page-width rules are in `README-header.md`; type and colour foundations in
`README.md`. This page introduces no new colours.

## Fidelity
**High-fidelity** for colour, type and spacing. The one thing to tune against real data is the
bar scale — see "Scale" below.

---

## What it replaces

The current page renders 84 identical tiles, one per month, all the same size and all the same
orange. The count is the only varying value on the page and it has no visual weight: 261 issues
and 1 issue look alike, and "261 Issu…" truncates inside its box. The word "Issues" is repeated
84 times. Year totals sit in yellow at roughly 1.9:1 on white. Months with nothing in them are
rendered as live tiles that lead to empty pages.

14b treats the data as what it is — a time series — and keeps a tile grid only where tiles are
actually useful, for picking a month in the current year.

## Structure

Four blocks, top to bottom, all inside the page content container:

1. **Page title block** — unchanged, per `README-header.md`. H1 "Issues By Month" / "PRs By Month".
2. **Intro copy** — full content width.
3. **Timeline** — as many years as fit the width, one bar per month, scrollable back to the oldest year with anything open.
4. **Month picker** — the current year as tiles.

### 1. Intro copy

Verbatim from the live page; do not rewrite. Runs the **full width of the content container**,
flush with the timeline below it — not set in a narrow measure.

- `<h2>` "Why group open issues by month?" / "Why group open PRs by month?" — sentence case per
  `README.md`; this casing is the one change to the live copy. Libre Franklin 700,
  17px, `letter-spacing: -.016em`, colour `#15171b`, `margin: 0 0 10px`.
- Paragraphs — Libre Franklin 400, 14.5px, `line-height: 1.6`, colour `#3c4148`,
  `text-wrap: pretty`, `margin: 0 0 9px` (last one `margin: 0`).

### 2. Timeline

**Loaded years.** Every year from the current one back to the **oldest year that still has at least
one open item**, oldest left. Years before that are not rendered; there is nothing to act on in them.
Issues and PRs compute this independently, so the two pages can go back different distances.

**How many show.** As many year blocks as fit the timeline's width, each at least **118px** wide
(twelve bars stay at least 8px):

```
visible = clamp(1, loadedYears, floor((W + gap) / (118 + gap)))
blockWidth = (W - gap * (visible - 1)) / visible
```

`W` is the timeline's content width and `gap` is the year-block gap (14px, 10px at narrow widths).
Blocks stretch to fill `W` exactly, so the last visible year is flush with the right edge. At the
928px desktop content width that is seven years; at 380px it is three. Recompute on resize.
If every loaded year fits, they all show and there is no scroll control.

Each year block is `flex: none` at `blockWidth`, so every year gets equal width regardless of how
many months carry data.

**Scrolling.** The row of year blocks sits in a horizontal scroller —
`overflow-x: auto; scroll-snap-type: x mandatory; overscroll-behavior-x: contain`, scrollbar
hidden — with each block `scroll-snap-align: start`. It opens scrolled fully right (the current
year at the right edge) and snaps one year at a time. Year labels live inside their block and scroll
with it. The baseline rule spans the whole scrolling row, gaps included.

**Range row** above the timeline, `margin-bottom: 12px`, flex with `gap: 10px`:
- Left: the visible span, Martian Mono 400, 9px, uppercase, `.06em` tracking, `#6b7178` —
  `"2020 – 2026 · back to 2018"`, or just `"2020 – 2026"` when nothing is hidden.
- Right (only when there is something to scroll): **‹ Earlier** and **Later ›** buttons, `gap: 5px`.
  Libre Franklin 600, 12.5px, `padding: 6px 11px`, `border-radius: 6px`,
  `border: 1px solid #d5d8dc`, background `#fff`, colour `#15171b`; hover `border-color: #15171b` —
  the site's outlined control.
  Each scrolls by exactly one year block (smooth). At either end the button becomes a non-interactive
  span — background `#f7f8f9`, transparent border, colour `#6b7178` — same treatment as an empty
  month tile. The range text updates as the row scrolls.

Inside a year block, the bars: `display: flex; align-items: flex-end; gap: 2px; height: 124px;
padding-bottom: 9px`, with the 1px `#e6e7ea` baseline directly beneath.
Twelve children, one per month, each `flex: 1`.

**Bar** — `border-radius: 2px 2px 0 0`, height from the scale below, fill by volume bucket
(same buckets as the legend colours in the table further down).
- Hover: fill `#15171b`. Whole bar is the link to that month's list.
- Months with a count of zero: a 2px stub in `#e6e7ea` — present but visibly nothing.
- Months that have not happened yet (Oct–Dec of the current year): **no bar at all**, transparent
  slot. A future month and an empty month must not look the same.
- `title` (and `aria-label`) on every bar: `"Sep 2026 — 261 issues"`.

**Year labels** — inside each year block, below the rule, `margin-top: 9px`, stacked with `gap: 2px`:
- Year — Libre Franklin 700, 15px, `letter-spacing: -.02em`, colour `#15171b`.
- Total — Martian Mono 400, 9.5px, colour `#5d636c`, formatted `"567 issues"` with a thousands
  separator. This replaces the yellow `(567 Issues)` parenthetical.

**Caption** — below the timeline, `margin-top: 16px`, `max-width: 620px`, 13px,
`line-height: 1.6`, colour `#5d636c`:
"Each bar is one month; height is the number of open issues, on a square-root scale so small
months stay visible. Hover for the exact count, click to open that month. Earlier years scroll in
from the left, back to the oldest year with anything still open."
(PRs page: "open PRs".)

#### Scale

Bar height is `sqrt(n / max) * 115`, floored at 3px for any non-zero month, where `max` is the largest monthly count **across every loaded year**, not just the visible ones — scrolling must never rescale a bar. 115px is the bar row's height less its 9px bottom padding,
so the largest month fills the row exactly. Linear height would flatten every year
before 2026 into a sliver against Sep 2026's 261; the square root keeps a 4-issue month visible
while still reading 261 as far larger than 66.

Recompute `max` per page — Issues and PRs have different ranges and must not share a scale.

### 3. Month picker

Heading row: "Pick a month" — Libre Franklin 700, 17px — followed by the year in Martian Mono
400, 9px, uppercase, `.06em` tracking, colour `#6b7178`.

Grid: `grid-template-columns: repeat(12, 1fr); gap: 5px`.

**Tile with data** — an `<a>`: `padding: 8px 0 7px`, `border-radius: 6px`,
`border: 1px solid #d5d8dc`, no fill, `display: flex; flex-direction: column; align-items:
center; gap: 1px`. Hover: `border-color: #15171b`.
- Month — Martian Mono 400, 8.5px, uppercase, `letter-spacing: .06em`, colour `#6b7178`.
- Count — Martian Mono 700, 13px, colour `#15171b`. The number only; the noun is not repeated.

**Empty or future tile** — a `<span>`, not a link: background `#f7f8f9`, no border, month and
figure both Martian Mono, colour `#6b7178`, figure at weight 400. Future months show an em dash.

Nothing that leads to an empty list is clickable.

## Colour buckets

Used for the bars. Dark ink `#15171b` throughout; these are fills only.

| Monthly count | Fill |
|---|---|
| 1–9 | `#fdf1ea` |
| 10–19 | `#fbddcb` |
| 20–39 | `#f8bd96` |
| 40–79 | `#f59058` |
| 80+ | `#f26322` |
| 0 | `#e6e7ea` (2px stub) |
| no data yet | transparent |

The thresholds are absolute, not relative to the page, so the same count means the same colour on
both pages and across years. If PR volumes drift far from these ranges, re-derive the thresholds
once and apply the same set to both pages.

**Verified against real PR data (Sep 2026).** Across PRs By Month for 2024, 2025 and 2026 to date — 33 months,
range 6 to 107 — the buckets fill 8 / 14 / 7 / 2 / 2 from palest to hottest. Every step is used
and none holds half the data, so the scale stands as written. Expect 2025 to render nearly
uniformly pale (6–21, mostly low teens), 2024 a step warmer, and 2026 to climb into the top two
buckets from June onward (42, 73, 98, 107) — the growth is real and the colour should show it.
Re-check if a year ever exceeds ~150 in a month, which would need a sixth step. Years before 2024
fall almost entirely in the palest bucket and in 2px zero stubs, which is correct: they are the
long tail the scroll exists to reach.

## Narrow widths

Drawn at 420px in `#24e`.

The timeline follows the same fit rule: at 380px content width three years show and the rest are a
swipe to the left. The bar row height drops from 124px to 88px (`padding-bottom` stays 9px, bar scale factor 78
instead of 115); `gap` stays 2px and the scale uses the same all-years `max`. Year block
`gap` drops from 14px to 10px. Year label 14px, total 9px. The Earlier / Later buttons grow to a
44px minimum height and width, 13px type; the range text is unchanged. Caption on touch reads "Tap
for the exact count and that month's list. Swipe right for earlier years."

The month picker is the one thing that reflows: `repeat(12, 1fr)` becomes `repeat(6, 1fr)` and
then, at phone widths, `repeat(4, 1fr)` — a 4×3 grid that keeps each tile above the 44px touch target and keeps a full year on one screen.
Tile padding and type are unchanged. Future months keep their greyed tile — the distinction
between "nothing happened" and "hasn't happened" matters more at small sizes, not less.

The intro block and the caption release their `max-width` and run the container gutter (20px).

## Accessibility

- Empty-month text is `#6b7178` — 4.9:1 on `#f7f8f9`, 5.3:1 on white. Do not lighten it; the grey
  fill and the missing link already make these cells recessive.
- Year totals `#5d636c` on white — 5.9:1. Caption the same.
- Bars are links with no text: every one needs an `aria-label` carrying month, year and count.
  The bar row should be a list, so the count is announced per item.
- Colour is never the only carrier — the timeline encodes volume in height as well as fill, and
  the picker prints the number.
- Focus: 2px `#f26322` outline, `outline-offset: 2px`, same as the chrome.

## Out of scope
The per-month issue/PR list pages the tiles link to are unchanged.
