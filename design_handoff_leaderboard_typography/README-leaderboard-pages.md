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

**Jump to my rank** — signed-in only. `padding: 7px 12px`, `border-radius: 7px`, background
`#15171b`. Contains the user's 20px avatar, the label "Jump to my rank" (Libre Franklin 600,
13px, `#ffffff`), and their rank in Martian Mono 11px colour `#9aa3ae` (e.g. `#142`). Hover:
background `#2a2e34`.

Clicking loads whatever pages are needed and scrolls their row into view; the row takes a brief
highlight so it can be found after the jump. Signed out, the control is absent — nothing takes
its place.

## Column header

`display: flex; align-items: center; gap: 16px; padding-bottom: 8px`,
`border-bottom: 1px solid #e6e7ea`. Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`,
colour `#6b7178`.

| Column | Width | Align |
|---|---|---|
| `#` | 26px | left |
| Contributor / Maintainer | `flex: 1` | left |
| Activity | 130px | right |
| Score | 70px | right |

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

## Narrow widths

The control strip wraps: caption on the first line, search and jump control on the second, both
full width. In rows, the activity column is the first thing to go — below roughly 700px it drops
and the handle moves under the name, returning the row to two lines. Rank, avatar, name and
score always remain.

## Out of scope
Ranking logic, score values, the Highlights tab (`README-highlights.md`), and the detail pages
(`README-detail-page.md`) — except for the inline scoring-link placement, which those pages
adopt too.
