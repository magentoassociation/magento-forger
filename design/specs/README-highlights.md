# Handoff: Leaderboard Highlights — UX + typography

Companion to `README.md` (Contributor Leaderboard). Everything in the **Design tokens** and
**Type scale** sections of that document applies here unchanged; this file covers only what is
specific to the Highlights page. Implement option **`#8b`** in
`../Leaderboard Type Directions.dc.html`.

## What changes and why

The current page has four problems this design fixes.

1. **Repeated label text.** "first contribution →" appears on all ten newcomer rows and
   "back after …" on all twelve comeback rows. The only part that varies — the number — sits at
   the end of a repeated sentence, so the eye has to read past identical words to reach it.
   **Fix:** the label moves once into the section head; the row carries only the value.
2. **Misaligned panels.** The two panel headers are different depths, so the first rows of the
   left and right lists don't sit on the same line.
   **Fix:** section heads are a fixed pattern — title and unit label on one baseline row above a
   hairline, description below it.
3. **No sense of scale or position.** Nothing is ranked, and 8.5 next to 0.9 reads the same as
   8.5 next to 8.4.
   **Fix:** rank numbers on every list; proportional bars on New contributor spotlight.
4. **Two of the four promised sections are missing.** The intro names newcomers, fast risers,
   returning contributors and who's active now; the page shows two.
   **Fix:** all four panels are present — Spotlight, Comebacks, Rising, Recently active. The
   data for Rising and Recently active exists; both panels ship.

**Comebacks sorts by recent activity**, most recently back first. The live page sorts by length of
absence, which puts the least-engaged person at the top; the point of the panel is who to welcome
back, so the newest return leads. Below the grid: "Most recently back first." followed by a
"Sort by time away instead." link (the period sits outside the link) (Libre Franklin 13px, `#3c4148`; link `#ee6524`, underlined), which
re-sorts by length of absence, longest first. In the prototype the return dates are sample values,
used only to order the cards.

## Page structure

```
h1  Leaderboard Highlights
p   intro copy (unchanged)
tab strip — Contributor / Maintainer / Monthly / Highlights (Highlights active)

section  New contributor spotlight   full width, ranked list with bars
section  Comebacks                   full width, 3-up card grid
section  Rising | Recently active    two equal columns, ranked lists
```

Page header block `26px 36px 24px`, `border-bottom: 1px solid #e6e7ea` (site-wide title block),
holding the H1 only. The intro and the tab strip sit **below** the rule, in a body block
`padding: 24px 36px 0`, exactly as on the boards: intro Libre Franklin 400, 14.5px/1.65,
`#3c4148`, `max-width: 660px`, then the tab row.
Section blocks `28px 36px 0` (first), `30px 36px 34px` (Comebacks),
`0 36px 34px` (the two-column block). Two-column gap `28px`.

## Section head pattern

Used by all four sections. A flex row, `align-items: baseline`,
`justify-content: space-between`, `padding-bottom: 10px`, `border-bottom: 1px solid #e6e7ea`:

- **title** — Libre Franklin 700, 19px, `letter-spacing: -.02em`, sentence case (it is a section
  heading, not a page name): "New contributor spotlight", "Comebacks", "Rising", "Recently active"
- **unit label** — Martian Mono 700, 9px, `letter-spacing: .06em`, uppercase, `#4c525a`.
  This is where the repeated row text goes. Values in use:
  Spotlight "First contribution · 30 days · Score" (who qualifies, then what the row figure is) · Comebacks "Time away" ·
  Rising "Gain · 30 days" · Recently active "Score".
- **description** (Rising / Recently active only, below the rule) — Libre Franklin 400,
  13.5px/1.5, `#3c4148`, `margin: 10px 0 4px`.

## Row patterns

### Ranked list row — Spotlight, Rising, Recently active
Whole row is an `<a>` to the contributor's detail page (not just the name).
`padding: 9px 0`, `border-bottom: 1px solid #f0f1f3`, hover `background: #faf9f7`.

Grid:
- Spotlight: `28px 250px 1fr 52px` — rank | identity | bar | score
- Rising / Recently active: `24px 1fr 76px` — rank | identity | value
- gap `14px` (Spotlight) / `12px`

Cells:
| cell | spec |
|---|---|
| rank | Martian Mono 10px, `#4c525a` |
| avatar | 28×28, `border-radius: 6px`, placeholder `#e9eaed`, initials Martian Mono 8.5px `#4c525a` |
| name | Libre Franklin 600, 14.5px/1.3, `-.012em`, ellipsis on overflow |
| handle | Martian Mono 9.5px/1.5, `#6b7178`, ellipsis |
| value | Martian Mono 700, 12px, tabular-nums, right-aligned |

Avatar → text gap `10px`. Name and handle stack (name above handle).

### Score bar — Spotlight only
`height: 7px`, `border-radius: 4px`, track `#f0f1f3`, fill `#ee6524`,
`width: value / max * 100%` (max = the top score in the list).

**Bars are deliberately absent from Rising and Recently active.** Rising spans +338.0 to +15.5
and Recently active 839.3 to 15.6; a linear bar renders everything below rank three as a stub and
implies a comparison the reader can't make. Rank plus tabular figures carries it. If bars are
wanted there later, use a log scale or bar against the rank-2 value, not rank 1.

### Comeback card — 3-up grid
`grid-template-columns: repeat(3, 1fr)`, gap `10px 16px`.
Each card is an `<a>`: `padding: 8px 10px`, `border: 1px solid #f0f1f3`,
`border-radius: 8px`; hover `border-color: #d3a98f`, `background: #fdfaf8`.
Contents are a flex row: avatar (as above) · name + handle stack (name 14px) ·
duration, Martian Mono 700, 10px, tabular, `#15171b`.

Twelve cards fill four rows instead of a full screen of list. If the real list is longer,
show the first twelve and add a "Show all N" control rather than growing the grid. It is the
site's outlined control, the same as the boards' "Show 25 more": `padding: 9px 18px`,
`1px solid #d5d8dc`, `border-radius: 7px`, Libre Franklin 600, 13.5px, `#15171b`; hover
`border-color: #15171b`. It sits below the grid, `margin-top: 16px`, before the sort line.

**Duration format.** Values carry week-level precision in the source
("back after 10 years 3 weeks"). Abbreviate as `10y 3w` / `9y 6m` — the one deliberate exception
to the no-abbreviations rule, because twelve cards three across have no room for the long form and
the "Time away" unit label already names what the figure is. Keep the unit the source
gives; do not normalise a week value to `0m`, which reads as missing data. `white-space: nowrap`.

## Tab strip
The shared tab row from `README-leaderboard-pages.md` (*Tab row*), unchanged: flex, gap `2px`,
`margin-top: 22px`, `border-bottom: 1px solid #e6e7ea`. Each tab `padding: 9px 14px`, 14px.
Inactive 500 / `#5d636c`, hover `#15171b`; active 600 / `#15171b` on white with
`1px solid #e6e7ea`, white bottom border, `border-radius: 7px 7px 0 0`, `margin-bottom: -1px`.
**Highlights is the active tab here.** (The strip was present on the live page and must stay —
it is the only route back to the other three leaderboards.)

## Content changes
**Spotlight and Comebacks lose their descriptions.** Both were restatements of their own titles
("People whose first-ever contribution to the project landed in the last 30 days…",
"Contributors who have started up again after an absence."), and the unit label now carries the
definition — "First contribution · 30 days · Score" and "Time away". Deleting them also lets both
full-width sections start their lists directly under the hairline.

**Rising and Recently active keep theirs**, because their titles are the two that don't explain
themselves — what counts as rising, and what counts as active. Copy as built:
- Rising — "Biggest increase in contributor score over the past 30 days."
- Recently active — "Opened a PR, had one merged, or opened an issue in the last 30 days."

Both are one line at the same depth, so the two columns start level.
All other copy, including the page intro, is unchanged.

## Accessibility notes
- Row links need an accessible name that includes the contributor — the avatar alone carries no
  text. Either keep the name inside the same anchor (as here) or add `aria-label`.
- Hover backgrounds are not the only affordance — the whole row is an anchor, so it takes the
  site-wide focus ring (2px `#f26322`, `outline-offset: 2px`; see *Focus rings* in
  `README-leaderboard-pages.md`). Never suppress it.
- The Comebacks card hover border `#d3a98f` is ~3:1 on white, the minimum for a non-text
  indicator. Do not lighten it.

## Files
- `../Leaderboard Type Directions.dc.html` — implement turn 8, option `#8b`.
- `README.md` — the Contributor Leaderboard spec (tokens and type scale live there).
- `reference-current-page.png` — the all-time Contributor page before the refresh.
