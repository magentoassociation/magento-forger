# Handoff: Scoring modal — "How scores are tallied"

## Overview
Restructures the two scoring modals reached from the "How are scores tallied?" link on the
leaderboard and detail pages. Approved directions: **19a — maintainer scores** and
**19b — contributor scores**. They are the same layout with different base-point rows and a
different example; build one component, pass it the two data sets.

The copy is unchanged from the live modals. What changes is structure and chrome:

- The solid orange title bar and the solid orange footer bar both go. Orange returns to the 3px
  edge used across the rest of the site.
- Two close affordances become one ✕ at the top right. The footer bar disappears entirely.
- The formula (base × priority × recency) is stated once at the top, before the tables explain
  each factor.
- Base points and the two multipliers sit side by side rather than stacked, so all three factors
  are visible together.
- The six priority rows become chips; the four recency rows become a decay bar.
- The example lands at the bottom in a dark panel, in view rather than below the fold.

At 1000px the whole modal fits without scrolling.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the intended look and behaviour, not production code to copy.
Reproduce the spec below in the Laravel/Blade + Bootstrap codebase using its existing template
conventions. Turn 19 holds both modals.

## Fidelity
**High-fidelity** for colour, type and spacing — the values below are exact. Type is Libre
Franklin for prose and Martian Mono for all numbers, labels and code-like strings, per
`README.md`.

---

## Surface

- Panel: background `#ffffff`, `border-radius: 12px`, `overflow: hidden`,
  `box-shadow: 0 24px 60px rgba(0,0,0,.35)`.
- Top edge: a 3px `#f26322` strip, full width of the panel, flush to the top. This is the only
  large use of orange in the modal.
- Scrim: `rgba(21,23,27,.55)` over the page behind.
- Width: 1000px max, centred. Below that it scales down; see **Narrow widths**.

## Header

`display: flex; align-items: flex-start; gap: 24px`, `padding: 24px 30px 20px`,
`border-bottom: 1px solid #e6e7ea`.

- Title: `<h2>`, Libre Franklin 700, 25px, `letter-spacing: -.028em`, `line-height: 1.1`,
  colour `#15171b`. Text: "How maintainer scores are tallied" / "How contributor scores are
  tallied".
- Intro paragraph: Libre Franklin 400, 14px, `line-height: 1.6`, colour `#3c4148`,
  `max-width: 620px`, `margin-top: 9px`. "priority label" and "recency factor" are weight 600.
- Close: 32×32 box, `border-radius: 7px`, glyph 17px colour `#5d636c`, no background at rest.
  Hover: background `#f4f5f6`, glyph `#15171b`. `aria-label="Close"`.

## Formula strip

A single row directly under the header. `padding: 14px 30px`, background `#faf9f7`,
`border-bottom: 1px solid #e6e7ea`, `display: flex; align-items: center; gap: 10px; flex-wrap: wrap`.
All type Martian Mono 10.5px, `letter-spacing: .02em`.

| Token | Treatment |
|---|---|
| BASE POINTS | background `#15171b`, text `#ffffff`, weight 700, `padding: 5px 9px`, `radius: 5px` |
| × and = | colour `#9aa3ae`, no box |
| PRIORITY, RECENCY | `1px solid #d5d8dc`, text `#15171b`, same padding and radius |
| SCORE | background `#fdece3`, text `#8f3a10`, weight 700, same padding and radius |

The strip is decorative-but-informative: it names the three factors in the order the columns
below explain them. It carries no interaction.

## Body grid

`display: grid; grid-template-columns: 1fr 1fr; gap: 0 34px; padding: 24px 30px 4px`.

Left column holds section 1. Right column holds sections 2 and 3 stacked, with `margin-top: 26px`
above section 3.

**Section headings** — Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`, colour
`#6b7178`, `padding-bottom: 9px`, `border-bottom: 1px solid #e6e7ea`. Numbered, because the
formula strip above established the order:

1. `1 · Base points`
2. `2 · Priority — higher-priority work counts for more`
3. `3 · Recency — recent work counts for more`

### 1 · Base points

One row per action. `display: flex; justify-content: space-between; gap: 14px; padding: 11px 0`,
`border-bottom: 1px solid #f0f1f3` on all but the last row.

- Action label: Libre Franklin 400, 14px, colour `#15171b`.
- Value: Martian Mono 700, 13px, right-aligned.
- `× PRIORITY` tag, where the action carries one: Martian Mono 8.5px, `letter-spacing: .04em`,
  `padding: 3px 6px`, `border-radius: 4px`, background `#fdece3`, text `#8f3a10`,
  `white-space: nowrap`, `margin-left: 4px`. Sits inline after the label, inside the same span.

**Maintainer rows (19a)**

| Action | Base points | Tag |
|---|---|---|
| Approved a PR | 3 | — |
| Requested changes on a PR | 3 | — |
| Commented on a review | 1 | — |
| Approved a PR that later merged | 6 | × PRIORITY |
| Claimed a stale pending-review PR | 2 | — |
| Applied a triage label | 1 | — |

**Contributor rows (19b)**

| Action | Base points | Tag |
|---|---|---|
| Opened an issue | 1 | × PRIORITY |
| Opened a PR | 2 | × PRIORITY |
| PR was merged | 10 | × PRIORITY |
| Issue resolved by a merged PR | 4 | × PRIORITY |

All four contributor actions carry the tag, so it repeats down the column. That is correct — the
priority panel opposite explains it once, and dropping the tag would make the two modals
inconsistent.

### 2 · Priority

Explanatory paragraph: Libre Franklin 400, 13px, `line-height: 1.6`, colour `#3c4148`,
`margin: 10px 0 4px`. Copy unchanged, including the inline `× PRIORITY` tag (same treatment as
above, without the left margin) and the bold `+2`.

Chips: `display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px`. Each chip is
`padding: 6px 10px`, `border-radius: 6px`, background `#f7f5f2`,
`display: flex; align-items: baseline; gap: 7px`.

- Label inside chip: Martian Mono 10px, colour `#3c4148` (`P0`…`P4`); the last chip's label is
  Libre Franklin 11.5px, "No priority label".
- Value: Martian Mono 700, 12.5px, colour `#15171b`.

Order and values: P0 3.5×, P1 3×, P2 2.5×, P3 2×, P4 1.5×, No priority label 1×.

The chips drop the "Priority: " prefix the table rows carried — the section heading supplies it.

### 3 · Recency

Explanatory paragraph: same treatment as section 2. Copy unchanged, bold `182-day`.

Decay bar: `display: flex; align-items: flex-end; gap: 10px; margin-top: 14px`. Four equal
columns (`flex: 1`), each a bar above its value above its label, `gap: 6px`, centred.

| Column | Bar height | Bar colour | Value | Label |
|---|---|---|---|---|
| 1 | 56px | `#f26322` | 1× | Today |
| 2 | 28px | `#f7a97f` | 0.5× | 182 days |
| 3 | 14px | `#fad4bd` | 0.25× | 364 days |
| 4 | 3px | `#dfe1e4` | 0× | 365+ days |

Bars are `width: 100%`, `border-radius: 4px 4px 0 0`. Values are Martian Mono 700, 11px
(`#15171b`; the 0× is `#6b7178`). Labels are Libre Franklin 10.5px, colour `#6b7178`, centred.

Heights are proportional to the multiplier — 56 / 28 / 14 — so the halving is legible at a
glance. The 0× bar keeps a 3px sliver so the column reads as a column rather than a gap.

## Example

Dark panel: `margin: 26px 30px 30px`, `padding: 18px 20px`, background `#15171b`,
`border-radius: 9px`.

- Eyebrow: Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`, colour `#9aa3ae`, reading
  "Example".
- Paragraph: Libre Franklin 400, 14px, `line-height: 1.65`, colour `#e8eaec`, `max-width: 700px`.
  Emphasised figures are weight 600 colour `#ffffff`. `Priority: P1` is Martian Mono 12px
  colour `#ffffff`.
- Equation row: `display: flex; align-items: baseline; gap: 10px; margin-top: 14px`. Operands
  Martian Mono 15px colour `#c9ced4`; operators colour `#6b7178`; result Martian Mono 700 19px
  colour `#f26322`, followed by "points" in Martian Mono 10px uppercase `letter-spacing: .06em`
  colour `#9aa3ae`.

**19a (maintainer):** 6 × 3 × 0.5 = 9 points. Prose is the live copy verbatim.

**19b (contributor):** 10 × 3 × 0.5 = 15 points. Prose is the live copy verbatim.

The equation row restates the paragraph's arithmetic. It is the one piece of the modal that is
not in the current copy; it exists because the paragraph asks the reader to multiply three
numbers spread across two sentences.

## Behaviour

- **It is a `<button>`, not a link.** Every in-page "How are scores tallied?" affordance — on the
  three leaderboard boards and all four detail states — is a
  `<button type="button">` that opens this modal. It does not navigate. The only exception is the
  **footer** "How scores work" item, which is a real `<a href>` to the standalone page
  (`README-how-scores-work.md`) — footers navigate, they do not open dialogs.
- The button is styled exactly like a text link (Libre Franklin 500, `#ee6524`, underlined,
  `text-underline-offset: 2px`) with `background: none; border: 0; padding: 0; font: inherit;
  cursor: pointer`, so it sits inline inside the intro sentence without disturbing the line.
- Which modal opens follows the page: the maintainer board and maintainer detail pages open 19a,
  the contributor board and contributor detail pages open 19b.
- Dismiss: the ✕, the scrim, and `Esc`. There is no footer button.
- Focus moves to the panel on open and returns to the triggering button on close. Focus is trapped
  inside the panel while it is open. `role="dialog"`, `aria-modal="true"`,
  `aria-labelledby` pointing at the `<h2>`.
- The page behind does not scroll while the modal is open.

## Narrow widths

Below roughly 820px the grid collapses to one column, in the order: base points, priority,
recency, example. The formula strip already wraps (`flex-wrap: wrap`). The decay bar keeps its
four columns at any width — the bars shrink, the labels do not wrap. Panel margin drops to 16px
and `border-radius` to 10px.

## Related
The standalone **How Scores Work** page carries the same data. It is specified separately in
`README-how-scores-work.md` and reuses the components defined here — formula strip, action rows,
priority chips, recency decay bar, example panel. Build them once.

## Out of scope
The scoring rules themselves, the wording of every line, and which actions appear in each modal
are unchanged. If a base-point value or multiplier changes, only the numbers change — the layout
holds.
