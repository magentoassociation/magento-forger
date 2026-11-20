# Handoff: Issues and PRs — Stats pages

## Overview
Two new pages, one for issues and one for PRs, each reached from its own menu item, **Stats**, under
Issues and PRs in the primary nav (`README-header.md`). Each page shows how the repository's work moves:
how many items are opened and closed over a chosen period, and how long they stay open before they close.

Approved type direction, as elsewhere: **Libre Franklin (sans) + Martian Mono (mono)**. Numbers, axis labels
and captions are Martian Mono. Headings and prose are Libre Franklin.

## Data
Every number on these pages comes from the existing `github-issues` and `github-prs` indexes (`docs/features/opensearch.md`).
Fields used: `created_at`, `closed_at`, `merged_at`, `is_open`, `state`. No new sync fields are needed for these pages.

Definitions, used everywhere on both pages:

- **Opened in period**: `created_at` falls inside the selected range.
- **Closed in period**: `closed_at` falls inside the selected range. For PRs, *merged* is `merged_at` in range and
  *closed without merging* is `closed_at` in range with no `merged_at`.
- **Time to close**: `closed_at − created_at`, for items closed inside the selected range. Items still open are not
  in the distribution; they are reported as a count ("412 still open") beside it so the reader sees what the chart leaves out.
- **Closed via a merged PR** (issues only): the issue appears in `closingIssuesReferences` of a PR with a `merged_at` value, the same
  field the Issue picker uses for *Has PR*. **No PR** is every other closed issue. Both are counted by `closed_at`.
  **Scale (all time, from GitHub search):** 24,571 closed issues; 2,819 with a linked PR (`linked:pr`, about 11%) and 21,752
  with no linked PR (about 89%). No PR is the large majority. Design for that: Merged PR is the small slice, not an even split.
  `linked:pr` matches a closing reference from a PR in any state, so the merged-PR count is at most 2,819; use the
  index's `closingIssuesReferences` plus `merged_at` for the real figure.
- **Time to merge** (PRs only): `merged_at − created_at`, for PRs merged inside the range.
- Bots (`engcom-*`, `dependabot`, `github-actions[bot]`) are excluded, as on the leaderboards (`CONTEXT.md`, *Bot*).

## About the design files
`../MageForger.html` is a design reference in HTML, not production code. These pages are new, so they have no
prototype turn yet. **Prototype ids in this document are reserved names, not existing cards**: `#30a` (issues
stats) and `#30b` (PRs stats), to be drawn in the same turn-based layout before build. Reproduce the spec in the
Laravel/Blade + Bootstrap codebase using its existing conventions.

## Fidelity
**High-fidelity** for type, colour, spacing, and interaction. Chart geometry is specified as rules (see *Charts*),
not as pixel values, because the data sets vary in size.

---

## Page frame

Standard site chrome: dark header (`README-header.md`), white title block, dark footer.

- Title block: `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`. H1 Libre Franklin 700, 40px,
  `letter-spacing: -.032em`, `line-height: 1.05`. Title case, per *Capitalisation* in `README.md`: "Issue Stats", "PR Stats".
- Body: `padding: 24px 36px 0`, full container width, same container class as every other page.

Nav: the Stats item sits as the last child of the Issues and PRs dropdowns. Its label is "Stats" (sentence case,
since it is a short form of the page name; `README.md` allows short forms in nav). Active when on either stats route.

## Intro paragraph

Libre Franklin 400, 14.5px, `line-height: 1.65`, colour `#3c4148`, `max-width: 660px`, `text-wrap: pretty`.

Issues: "How many issues were opened and closed in the period you pick, and how long issues stay open before they close.
Counts use the date each issue was opened or closed on GitHub."

PRs: "How many pull requests were opened, merged, and closed without merging in the period you pick, and how long they
take to merge. Counts use the date each PR was opened, merged, or closed on GitHub."

## Controls strip

`display: flex; align-items: center; gap: 24px 12px; flex-wrap: wrap; padding: 16px 0 28px`, directly under the intro. The 24px row gap
separates the date row from the *How it closed* row, and the 28px bottom padding separates the strip from the range caption.

**Period presets** — a segmented row of four buttons: "30 days", "90 days", "12 months", "All time".
- Each button: `padding: 7px 12px`, `1px solid #d5d8dc`, `border-radius: 7px`, Libre Franklin 600, 13.5px, `#15171b`.
  Hover `border-color: #15171b`. Joined: adjacent buttons share a border (`margin-left: -1px`).
- Selected: background `#15171b`, text `#ffffff`, border `#15171b`.
- Default selection: **12 months**.
- "All time" starts at the history floor (`github.history_start`, default 2014-12-01).

**Custom range** — two date fields, "From" and "To", after the presets, separated by "–".
- Field: `padding: 7px 10px`, `1px solid #d5d8dc`, `border-radius: 7px`, Martian Mono 12px, `#15171b`.
  Label above the field in Martian Mono 9px, uppercase, `letter-spacing: .06em`, `#6b7178`.
- Typing or the browser date picker both work. Dates are shown as `YYYY-MM-DD`.
- Choosing a custom range clears the preset selection. Choosing a preset clears the custom fields.
- Invalid ranges (From after To, or a range beyond the history floor or today) disable Apply and show a
  one-line error in Libre Franklin 13px, `#c74e16`: "From must be before To." / "No data before 2014-12-01."
- An **Apply** button (`#15171b` dark control) sits at the end of the custom range. Presets apply immediately.

**Granularity** — a small toggle, "Weekly" / "Monthly", in the heading row of Chart 1, right-aligned on the same line as the chart's title and caption (`display: flex; justify-content: space-between; align-items: flex-start; gap: 16px`). It is not in the controls strip, because it changes the charts only, not the summary tiles. The Chart 1 setting also applies to Chart 3.
- Default: **Weekly** when the range is 90 days or less, **Monthly** otherwise.
- The toggle reads Martian Mono 10px, uppercase, `letter-spacing: .06em`. Selected uses the same dark fill as the presets.

**How it closed** (Issue Stats only) — a second row under the controls strip, left-aligned. A label "How it closed" above it in
Martian Mono 9px, uppercase, `letter-spacing: .06em`, `#6b7178`, then a three-button segmented control in the same style as the period presets: **All**
(default), **Merged PR**, **No PR**. It applies immediately, like the presets.
- It filters how closed issues are counted: the Closed tile, the closed bars in Chart 1, and everything in Charts 2 and 3
  (histogram, stat column, line). Opened, Net, Open now, and Still open ignore it, because they are not about how an issue closed.
  Net always uses all closed issues, and its sub-line stays "opened minus closed".
- When it is not on All, the Closed tile's sub-line reads "with a merged PR" or "with no PR", and the Chart 1 and
  Chart 2 captions add the same words. A period with no matching issues follows the empty state.
- **Scale rule.** Merged PR is about 11% of closed issues, so its bars and counts are roughly one-ninth of All. Each chart sets its
  y-axis maximum from the data it is showing, not from the All view, so the Merged PR bars stay readable. The Closed
  tile's sub-line still shows the count, so the reader can see the proportion.
- Expect the *partial data* rule (fewer than 3 items) to trigger more often under Merged PR, especially on weekly buckets
  and short ranges.
- The PR page does not have this filter. It already separates merged from closed without merging.

**Range caption** — Martian Mono 9.5px, uppercase, `letter-spacing: .06em`, `#6b7178`, under the strip:
"Opened or closed 1 Dec 2025 – 17 Nov 2026 · 2,184 issues". Updates with the range.

## Charts

All charts share one rule set. Charts are drawn with the same charting approach as the existing by-month and dashboard
charts, using the site's colour tokens. No new charting library is introduced without approval.

Colour tokens, used consistently:

| Series | Colour | Notes |
|---|---|---|
| Opened | `#15171b` | ink |
| Closed | `#ee6524` | link orange; the Closed series is never shown without its legend and tooltip |
| Merged (PRs) | `#15171b` | ink |
| Closed without merging (PRs) | `#9aa3ae` | muted |

Every series is also distinguished by shape or position, not colour alone: opened and closed bars sit side by side in a
fixed order, and the legend uses a swatch plus its label.

Common chart rules:

- Axis labels: Martian Mono 9.5px, `#6b7178`. Axis lines `1px solid #e6e7ea`. Gridlines `1px solid #f0f1f3`, horizontal only.
- Chart titles: Libre Franklin 700, 17px, `letter-spacing: -.016em`, `#15171b`, `margin: 0 0 10px`, sentence case.
- Chart caption under the title: Libre Franklin 400, 13px, `line-height: 1.5`, `#5d636c`.
- Tooltips on hover and on keyboard focus: background `#15171b`, `#ffffff` text, `border-radius: 9px`,
  `padding: 10px 14px`, `box-shadow: 0 8px 24px rgba(0,0,0,.22)`. Martian Mono 10px for values, Libre Franklin 13px for labels.
  Same panel as the score tooltip (`README.md`, *Score tooltip*).
- Every chart has a text alternative: a visually hidden data table with the same numbers, reachable by screen readers, and a
  caption that states the range.

### Chart 1 — Opened vs closed

**Type**: grouped bars, two per period. Opened first (ink), closed second (orange).

- X axis: one slot per period (week or month, per the granularity toggle). Labels in Martian Mono 9.5px, uppercase.
  Label every period when there are 16 or fewer; otherwise label every other period, starting with the first.
- Y axis: count, starting at 0, with a max rounded up to a round number.
- Bars: 60% of slot width, radius `2px 2px 0 0`. Bar hover: `opacity: .85`, tooltip shows the period, opened count and closed count.
- Net change line: optional toggle "Show net" adds a thin `1.5px` line in `#6b7178` showing opened minus closed per period
  (a zero line is drawn). Off by default.

Summary tiles above the chart, four in a row (`display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px`):

- Each tile: `1px solid #e6e7ea`, `border-radius: 9px`, `padding: 16px`.
- Label: Libre Franklin 13px, `#3c4148`.
- Value: Martian Mono 700, 20px, `#15171b`, tabular.
- Sub-line: Martian Mono 9.5px, `#6b7178`, e.g. "vs 1,904 prior period".

Tiles: **Opened**, **Closed**, **Net** (opened minus closed, signed), **Open now** (`is_open` total at the range end).
The "vs prior period" comparison uses the same-length range immediately before.

### Chart 2 — Time to close (issues) / Time to merge (PRs)

**Type**: histogram with fixed buckets, so two date ranges are comparable.

Buckets, in order: **same day**, **2–7 days**, **1–4 weeks**, **1–3 months**, **3–12 months**, **over a year**.

- Bars in `#15171b`. The bucket holding the median is drawn in `#ee6524` and labelled "50% mark" in Martian Mono 9px, uppercase (the bucket where half of the items have closed).
- Bar value labels above each bar: Martian Mono 10px, `#15171b`.
- X labels: Libre Franklin 12px, `#3c4148`, `maxRotation: 0`, wrapped to two lines where needed.

Beside the histogram, a stat column (`width: 220px`):

- **Half close within** (PRs: **Half merge within**) — the median, in plain words. Martian Mono 700, 34px (the score-size value from the detail page).
- **9 in 10 close within** (PRs: **9 in 10 merge within**) — the 90th percentile, in plain words. Martian Mono 700, 20px.
- **Still open** — the count of items opened in range and not yet closed, Martian Mono 700, 20px, with the sub-line
  "excluded from the chart".

Durations in the stat column use the largest two units: "4 days 6 hours", "2 weeks 3 days", "11 months". Under a day: "under a day".

For PRs the same chart shows time to **merge**, for merged PRs only. Closed-without-merge PRs are not in this chart; their
count appears in the summary tile for Closed without merging (Chart 1, PRs).

### Chart 3 — How quickly issues close, by period (optional, on by default)

**Type**: line, one point per period, same period axis as Chart 1.

- Line `2px` in `#15171b`. Points `4px` circles, `#15171b`, filled white at rest, filled ink on hover.
- Heading: "How quickly issues close, by month" (PRs: "How quickly PRs merge, by month"; "by week" when the page is on weekly buckets). Caption: "Half of the issues closed each month had been open this many days or fewer." (PRs: "Half of the PRs merged each month took this many days or fewer to merge.") The word "median" is not used on the page, because readers confuse it with the average. Each point is still the median of items closed in that period. Periods with fewer than 3 closed items are drawn as hollow
  points, joined to their neighbours by a dashed `2px` segment (`4px 4px` dash) instead of a solid one, and the tooltip
  says "too few items to compare".
- The same line in PRs shows the time within which half of the merged PRs were merged.

## Page layout

1. Title block.
2. Intro paragraph.
3. Controls strip (presets, custom range, Apply), then on Issue Stats the *How it closed* row.
4. Range caption.
5. Summary tiles (Chart 1's four tiles).
6. Chart 1, full width.
7. Two columns, `grid-template-columns: 1fr 1fr; gap: 34px`: Chart 2 (left, with its stat column), Chart 3 (right).
   Below 900px these stack.
8. A table-caption line in Libre Franklin 13.5px, `#5d636c`: "Data from the github-issues index, synced <time>." Shows the last successful sync time.

Section gaps: `margin-top: 28px` between items 5, 6 and 7. The page ends after item 8 with `padding-bottom: 40px`.

## States

- **Loading**: not a loading state. The page is rendered server-side, as on the boards. A request that fails shows the
  site's standard error page.
- **Empty range** (no items opened or closed in the range): the chart area is replaced by one line, Libre Franklin 500,
  15px, `#15171b`: "No issues were opened or closed between 1 Jan 2021 and 31 Jan 2021." Below it, Libre Franklin 14px,
  `#5d636c`: "Try a wider range." The summary tiles show zeros; the stat column shows "–".
- **Partial data** (fewer than 3 items for a bucket or period): the chart draws what exists and the caption states it.
- **Sync stale** (last sync more than 24 hours old): the table-caption line turns `#c74e16` and reads "Data last synced
  <time>; counts may lag." This is informational and does not block the page.

## Interactions

- Presets, Apply, and the granularity toggle reload the page with query parameters. The URL carries the full state:
  `?from=2025-11-17&to=2026-11-17&granularity=weekly&net=1&closedBy=merged-pr`; `closedBy` is `merged-pr` or `no-pr`, and is
  absent for All. Presets use `?preset=12m`, so the URL stays readable.
- Hover and focus on chart elements show the tooltip. Keyboard users move between bars with arrow keys within a chart;
  Tab moves between charts and controls.
- "Show net" and the Chart 3 toggle update in place without a reload.
- Nothing on these pages changes GitHub. The pages are read-only.

## Hover states

Per `README-leaderboard-pages.md`, *Hover states*: 120ms ease-out on the property that changes, no size or position changes.

| Element | Rest | Hover |
|---|---|---|
| Preset button, unselected | border `#d5d8dc` | border `#15171b` |
| Bar | fill as specified | `opacity: .85` |
| Line point | hollow or filled as specified | filled ink |
| Granularity button, unselected | `#f7f5f2` | `#ece9e4` |
| Text link (e.g., "See the issues in this period") | `#ee6524` | `#c74e16` |

## Focus rings — site-wide

Per `README-leaderboard-pages.md`: **2px `#f26322` outline, `outline-offset: 2px`**, on `:focus-visible` only.
Applies to presets, date fields, Apply, the granularity toggle, the "Show net" toggle, and every chart element that takes focus.

## Narrow widths

Drawn at 420px in the same way as the boards (`README-leaderboard-pages.md`, *Narrow widths*).

- Presets wrap to two rows of two. Custom range fields stack.
- Summary tiles become a 2×2 grid.
- The stat column of Chart 2 moves below the histogram, full width.
- The H1 drops to 28px.

## Accessibility

- Charts are never the only way to read a number: each has its data table (visually hidden) and tile values.
- Colour is never the only signal (see *Charts*).
- Each chart has an `aria-labelledby` pointing at its title and an `aria-describedby` pointing at its caption.
- Date fields have visible labels, not placeholders.
- The orange `#ee6524` link exception from `README.md` applies to text links here. Link text is underlined.

## Out of scope

- Per-author or per-label breakdowns (the picker and label views cover label detail).
- Exports, CSV download, and sharing a chart image.
- Forecasting or trend lines beyond Chart 3.
- Changes to the sync itself. These pages read data that already exists.
