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

In the prototype, **`#14b` is the approved design**, shown with the Issues dataset. `#14a`
(heatmap grid) was explored and rejected.

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
3. **Timeline** — all years, one bar per month.
4. **Month picker** — the current year as tiles.

### 1. Intro copy

Verbatim from the live page; do not rewrite. Runs the **full width of the content container**,
flush with the timeline below it — not set in a narrow measure.

- `<h2>` "Why Group Open Issues by Month?" / "Why Group Open PRs by Month?" — Libre Franklin 700,
  17px, `letter-spacing: -.016em`, colour `#15171b`, `margin: 0 0 10px`.
- Paragraphs — Libre Franklin 400, 14.5px, `line-height: 1.6`, colour `#3c4148`,
  `text-wrap: pretty`, `margin: 0 0 9px` (last one `margin: 0`).

### 2. Timeline

A flex row of year blocks: `display: flex; align-items: flex-end; gap: 14px; padding-bottom: 9px;
border-bottom: 1px solid #e3e5e8`. Each year block is `flex: 1`, so every year gets equal width
regardless of how many months carry data.

Inside a year block, the bars: `display: flex; align-items: flex-end; gap: 2px; height: 124px`.
Twelve children, one per month, each `flex: 1`.

**Bar** — `border-radius: 2px 2px 0 0`, height from the scale below, fill by volume bucket
(same buckets as the legend colours in the table further down).
- Hover: fill `#15171b`. Whole bar is the link to that month's list.
- Months with a count of zero: a 2px stub in `#e6e8ea` — present but visibly nothing.
- Months that have not happened yet (Oct–Dec of the current year): **no bar at all**, transparent
  slot. A future month and an empty month must not look the same.
- `title` (and `aria-label`) on every bar: `"Sep 2026 — 261 issues"`.

**Year labels** — a second flex row below the rule, same `gap: 14px`, each `flex: 1`:
- Year — Libre Franklin 700, 15px, `letter-spacing: -.02em`, colour `#15171b`.
- Total — Martian Mono 400, 9.5px, colour `#5d636c`, formatted `"567 issues"` with a thousands
  separator. This replaces the yellow `(567 Issues)` parenthetical.

**Caption** — below the timeline, `margin-top: 16px`, `max-width: 620px`, 13px,
`line-height: 1.6`, colour `#5d636c`:
"Each bar is one month; height is the number of open issues, on a square-root scale so small
months stay visible. Hover for the exact count, click to open that month."

#### Scale

Bar height is `sqrt(n / max) * 118`, floored at 3px for any non-zero month, where `max` is the
largest monthly count **across all years on the page**. Linear height would flatten every year
before 2026 into a sliver against Sep 2026's 261; the square root keeps a 4-issue month visible
while still reading 261 as far larger than 66.

Recompute `max` per page — Issues and PRs have different ranges and must not share a scale.

### 3. Month picker

Heading row: "Pick a month" — Libre Franklin 700, 17px — followed by the year in Martian Mono
400, 9px, uppercase, `.06em` tracking, colour `#6b7178`.

Grid: `grid-template-columns: repeat(12, 1fr); gap: 5px`.

**Tile with data** — an `<a>`: `padding: 8px 0 7px`, `border-radius: 6px`,
`border: 1px solid #e3e5e8`, no fill, `display: flex; flex-direction: column; align-items:
center; gap: 1px`. Hover: `border-color: #15171b`.
- Month — Martian Mono 400, 8.5px, uppercase, `letter-spacing: .04em`, colour `#6b7178`.
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
| 0 | `#e6e8ea` (2px stub) |
| no data yet | transparent |

The thresholds are absolute, not relative to the page, so the same count means the same colour on
both pages and across years. If PR volumes drift far from these ranges, re-derive the thresholds
once and apply the same set to both pages.

## Accessibility

- Empty-month text is `#6b7178` — 4.9:1 on `#f7f8f9`, 5.3:1 on white. Do not lighten it; the grey
  fill and the missing link already make these cells recessive.
- Year totals `#5d636c` on white — 5.9:1. Caption the same.
- Bars are links with no text: every one needs an `aria-label` carrying month, year and count.
  The bar row should be a list, so the count is announced per item.
- Colour is never the only carrier — the timeline encodes volume in height as well as fill, and
  the picker prints the number.
- Focus: 2px `#f26322` outline, `outline-offset: 2px`, same as the chrome.

## Responsive

Below the Bootstrap `lg` breakpoint the timeline's seven year blocks no longer fit at a legible
bar width. Two acceptable options, in order of preference:

1. Horizontally scroll the timeline with a fixed minimum year-block width (~120px), keeping the
   year labels pinned under their bars.
2. Drop to the three most recent years and add a "show all years" control.

The month picker reflows to `repeat(6, 1fr)` and then `repeat(4, 1fr)`.

## Out of scope
The per-month issue/PR list pages the tiles link to are unchanged.
