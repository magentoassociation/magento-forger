# Handoff: How Scores Work — standalone page

## Overview
Rebuilds the standalone **How Scores Work** page. Approved direction: **20a**.

The page carries the same data as the two scoring modals. Today it stacks the contributor
section above the maintainer section and prints the complete multiplier stack inside each — the
same six priority rows and four recency rows, twice. The multipliers are identical on both
boards, so the duplication implies a difference that does not exist, and the second board's
actions sit roughly a thousand pixels below the first. Content also runs at about half the
container width, leaving the right half of the page empty top to bottom.

20a keeps every word and restructures:

- The formula (base × priority × recency) is stated once, directly under the intro.
- The two boards sit **side by side**, each showing only what differs between them: its action
  rows and its example.
- The multiplier stack appears **once**, full width, below both boards, labelled "Identical on
  both boards".
- Content runs the full container width.

Page height drops from roughly 2,400px to a little over one screen.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the intended look and behaviour, not production code to copy.
Reproduce the spec below in the Laravel/Blade + Bootstrap codebase using its existing template
conventions. Turn 20 holds this page.

## Fidelity
**High-fidelity** for colour, type and spacing. Every component on this page is already
specified in `README-scoring-modal.md` — build those once and reuse them here. This document
covers only the page-level composition and the two places where values differ.

---

## Page frame

Standard site chrome throughout: the dark header per `README-header.md`, the white title block,
the dark footer with the Slack panel and the Forger / Community columns.

- Title block: `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`. H1 "How Scores
  Work", Libre Franklin 700, 40px, `letter-spacing: -.032em`, `line-height: 1.05` — the
  site-standard title block, unchanged.
- Body: `padding: 24px 36px 36px`, full container width.
- Intro paragraph: Libre Franklin 400, 14.5px, `line-height: 1.65`, colour `#3c4148`,
  `max-width: 680px`. Copy unchanged.

## Formula strip

Same component as the modal's, with two differences: it is a bordered card rather than a
full-bleed band (`padding: 14px 18px`, background `#faf9f7`, `1px solid #e6e7ea`,
`border-radius: 9px`, `margin-top: 22px`), and it carries a right-aligned caption.

Caption: `margin-left: auto`, Martian Mono 9px, uppercase, `letter-spacing: .06em`, colour
`#6b7178`, reading "Same formula on both boards". It is the page's first statement that the two
boards share their machinery, and it sets up the single multiplier section below.

## Board columns

`display: grid; grid-template-columns: 1fr 1fr; gap: 34px; margin-top: 34px`.
Left column: Contributor board. Right column: Maintainer board.

Each column contains, in order:

1. **H2** — Libre Franklin 700, 21px, `letter-spacing: -.024em`, sentence case: "Contributor
   board", "Maintainer board". These name a section, not a page — the pages they refer to are
   the Contributor and Maintainer Leaderboards, so title case here would set up a name that
   matches no H1.
2. **Table header row** — `padding: 12px 0 9px`, `margin-top: 12px`,
   `border-bottom: 1px solid #e6e7ea`, Martian Mono 9px uppercase `letter-spacing: .06em`
   colour `#6b7178`: "Action" left, "Base points" right.
3. **Action rows** — exactly as specified in `README-scoring-modal.md` § *1 · Base points*,
   including the `× PRIORITY` tag treatment. Contributor rows and maintainer rows are unchanged
   from the modals.
4. **Example panel** — the modal's dark panel, scaled down for the narrower column:
   `margin-top: 18px`, `padding: 16px 18px`, `border-radius: 9px`, background `#15171b`.
   Paragraph 13.5px; `Priority: P1` 11.5px; equation operands 14px, result 18px, "points" 9.5px.
   Copy is the live copy verbatim.

   | Board | Equation | Result |
   |---|---|---|
   | Contributor | 10 × 3 × 0.5 | 15 points |
   | Maintainer | 6 × 3 × 0.5 | 9 points |

The columns are deliberately unequal in length — the maintainer board has six actions to the
contributor board's four. Do not pad the shorter column; the examples anchor both bottoms closely
enough.

## Multipliers section

Full width, below both columns. `margin-top: 38px`, `padding-top: 22px`,
`border-top: 1px solid #e6e7ea`.

Heading row: `display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap`.

- H2 "Multipliers" — same treatment as the board H2s.
- Caption beside it — Martian Mono 9px uppercase `letter-spacing: .06em` colour `#6b7178`,
  reading "Identical on both boards". This label is load-bearing: without it, a reader who
  remembers the old page may assume a per-board table was dropped.

Lead paragraph: Libre Franklin 400, 14px, `line-height: 1.6`, colour `#3c4148`,
`max-width: 680px`, `margin-top: 10px`. Copy unchanged ("Base points are multiplied together…").

Then `display: grid; grid-template-columns: 1fr 1fr; gap: 34px; margin-top: 24px`:

- **Left — Priority.** Heading "Priority — higher-priority work counts for more", explanatory
  paragraph, and the six chips. Exactly as `README-scoring-modal.md` § *2 · Priority*, minus the
  section number.
- **Right — Recency.** Heading "Recency — recent work counts for more", explanatory paragraph,
  and the four-column decay bar. Exactly as `README-scoring-modal.md` § *3 · Recency*, minus the
  section number.

## Copy changes

One sentence is added, at the end of the recency paragraph: **"Monthly boards are not decayed."**
The page intro already says the monthly boards do not decay; repeating it where the decay is
shown stops the bar being read as applying to every board. Nothing else changes — all other
wording is the live page's, verbatim.

## Narrow widths

Below roughly 900px both grids collapse to one column. The order becomes: contributor board with
its example, maintainer board with its example, multipliers (priority, then recency). The
formula strip wraps and its caption drops below the tokens rather than sitting right-aligned.

## Out of scope
The scoring rules, the action lists and all existing wording. If a value changes, only the
numbers change — and it changes in one place, since both boards read the same multiplier
section.
