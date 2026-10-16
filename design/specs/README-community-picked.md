# Handoff: Community-picked PRs

## Overview
Restyles the Community-picked PRs page (reached from the PRs menu) on the leaderboard template.
Approved direction: **27b** at desktop and **27c** at 420px. The page lists open pull requests
ranked by GitHub 👍 votes, with filters for title, Area, Component and author.

## About the design files
`../Leaderboard prototype.html`, one level up from this folder, is a design reference written in HTML, not
production code. Reproduce it in the Laravel/Blade + Bootstrap codebase. Turn 27 holds both frames.

## Fidelity
**High-fidelity.** Type, colour, gutter, title block, control styling and row dividers follow
`README.md` and `README-leaderboard-pages.md`; values not restated here come from those.

---

## Page structure
Standard site chrome per `README-header.md`, with **PRs ▾** as the active nav item. Then:

1. **Title block**: H1 "Community-picked PRs", per *Page gutter and title block* in `README.md`.
2. **Intro**: one paragraph, `padding: 24px 36px 0`, Libre Franklin 14.5px, `line-height: 1.65`,
   `#3c4148`, `max-width: 660px`. Copy, verbatim:
   > Vote for the open pull requests you want merged into Magento 2: click a PR's number to open
   > it on GitHub, then click 👍 on the PR description. After each voting period, the top-voted PRs
   > are prioritized for merging. Vote counts refresh from GitHub every 15 minutes.

   The live page's "How community picking works" heading is removed; the title block already
   names the page and the paragraph explains it.
3. **Control strip**, **count line**, **column heads**, **rows**, **pagination**, footer.

## Control strip
`margin-top: 22px`, `border-top: 1px solid #e6e7ea`, `padding: 16px 0 14px`,
`display: flex; align-items: center; gap: 10px; flex-wrap: wrap`. In order:

| Control | Type | Rest text | Width |
|---|---|---|---|
| Search | text input, ⌕ icon left | "Title words or PR #" (placeholder) | `min-width: 200px` |
| Area | select | "All areas" | content |
| Component | select | "All components" | content |
| Author | text input | "Author handle" (placeholder) | `min-width: 130px` |
| Sort | select, **dark control** | "Most votes" | content, `margin-left: auto` |

- Outlined controls: `padding: 7px 12px` (search `7px 30px`), `1px solid #d5d8dc`, `radius 7px`,
  Libre Franklin 13.5px. Value text `#15171b`; placeholder `#9aa3ae`.
- Sort: background `#15171b`, text `#ffffff` weight 600, hover `#2a2e34`.
- Selects carry the chevron from *Dropdown caret* in `README.md`, `gap: 8px` after the label;
  `#6b7178` on outlined, `#9aa3ae` on Sort.
- **No Filter button.** Selects apply on change; the two text fields apply on Enter and after
  a 300ms pause in typing. Every filter is written to the query string so a filtered view can be
  shared and survives Back.
- Area and Component stay dependent (Component lists only the chosen Area's components).

**Count line**: directly under the strip, `margin: 4px 0 22px`. Martian Mono 9.5px uppercase,
`letter-spacing: .06em`, `#6b7178`: "1,028 open PRs". It counts the filtered set.

## Column heads
`display: flex; gap: 18px; padding-bottom: 8px; border-bottom: 1px solid #e6e7ea`. Martian Mono
9px uppercase, `letter-spacing: .06em`, `#6b7178`: **Votes** (48px), **Pull request** (`flex: 1`),
**Author · Age** (150px, right-aligned).

## Rows
`display: flex; align-items: flex-start; gap: 18px; padding: 13px 0`,
`border-bottom: 1px solid #f0f1f3`, hover background `#faf9f7`.

- **Votes** (48px): Martian Mono 700, 17px, tabular figures, `#15171b`, `line-height: 1.3`.
- **Pull request** (`flex: 1; min-width: 0`), two lines:
  - Line 1, `display: flex; gap: 8px; align-items: baseline`: PR number then title.
    - Number: `#41427`, Martian Mono 500, 12px, `#ee6524`, underlined (`text-underline-offset: 2px`),
      hover `#c74e16`. Links to the PR on GitHub.
    - Title: Libre Franklin 600, 15px, `line-height: 1.4`, `#15171b`, `text-wrap: pretty`. Not a
      link. Full title; let it wrap.
  - Line 2, `margin-top: 7px; display: flex; flex-wrap: wrap; align-items: center; gap: 5px`:
    Area pills, then Component pills, then the issue group.
    - **Area pill**: outlined. `padding: 3px 7px`, `1px solid #d5d8dc`, `radius 5px`, Libre Franklin
      11.5px, `#3c4148`. Text is the area name alone ("Framework"), no "Area:" prefix.
    - **Component pill**: filled. `padding: 4px 8px`, background `#f7f5f2`, `radius 5px`, Martian Mono
      10px, `#15171b`. Name alone, no "Component:" prefix. Outline versus fill is what tells
      them apart.
    - **Issue group**, `margin-left: 6px`, `gap: 6px`: label "Issue" (Martian Mono 9px uppercase,
      `letter-spacing: .06em`, `#6b7178`), then each linked issue as `#41426` (Martian Mono 11px,
      `#ee6524`, underlined, hover `#c74e16`) linking to the issue on GitHub. Several issues sit
      side by side.
    - A PR with no area, component or linked issue omits that part. No "—" placeholders; a row
      with none of them has no second line.
- **Author · Age** (150px, right-aligned, stacked, `gap: 4px`, `padding-top: 3px`):
  - Author: `@handle`, Martian Mono 11px, `#3c4148`, underline `rgba(60,65,72,.35)`
    (`text-underline-offset: 2px`), hover `#ee6524`. **Links to the person's contributor detail
    page** (`README-detail-page.md`), not GitHub; `title="Contributions — @handle"`.
  - Age: "1 month ago", Martian Mono 10px, `#6b7178`. Age of the PR (opened date).

## Pagination
As on the boards (`README-leaderboard-pages.md`, pagination): a **"Show 50 more"** outlined
button beside "Showing 1–50 of 1,028" (Martian Mono 9.5px uppercase `#6b7178`), block padding
`20px 36px 30px`. Appends; depth in the URL as `?rows=`; at the end of the list the button is
removed. Replaces the live page's numbered pages.

## Narrow widths (27c)
Below roughly 700px:

- Gutter 20px; H1 28px; header and footer per `README-header.md` (as in `#24a`).
- Control strip becomes a two-column grid (`grid-template-columns: minmax(0,1fr) minmax(0,1fr)`,
  `gap: 8px`): Search spans both columns; then Area | Component; then Author | Sort. Controls are
  `padding: 10px 12px` (44px tall) and stretch to their cell, chevron pushed right.
- Column heads: **Votes** (34px) and **Pull request** only; `gap: 12px`.
- Row `gap: 12px`. Votes 15px. Title 14.5px with the PR number inline at its start
  (`margin-right: 7px`) so the title wraps under it. Line 2 unchanged. Author and age move to a
  third line, `margin-top: 8px`, `gap: 8px`, left-aligned.
- "Show 50 more" stretches full width, padding `11px 18px`; the count sits beneath it.

## Out of scope
How votes are counted and refreshed, the voting period, and which PRs are eligible are
unchanged. Area/Component vocabulary comes from GitHub labels as today.
