# Handoff: Contributor Leaderboard — typography refresh

## Overview
A typographic refresh of the Contributor Leaderboard page on Magento Open Source Forger
(`/leaderboard/contributor`). The data, columns and page copy are unchanged. What changes is
the typeface pairing, the type scale, two link affordances, and one removed column.

Approved direction: **6a — Libre Franklin (sans) + Martian Mono (mono)**.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a **design reference
written in HTML** —
a prototype showing the intended look and behaviour. It is not production code to copy.
The Forger is a Laravel/Blade + Bootstrap app; the task is to reproduce the specification below
in that codebase using its existing template and CSS conventions.

The file contains six turns of exploration, newest first. **Only turn 6 (`#6a`) is the approved
design.** Turns 1–5 are earlier options, kept for context; ignore them when implementing.

## Fidelity
**High-fidelity** for typography, colour and spacing — the values below are exact and should be
matched. The surrounding page chrome (top utility bar, orange masthead, footer) was not part of
this exercise and is unchanged.

Two things in the prototype are placeholders:
- **Avatars** are grey rounded squares with initials. In production use the real GitHub avatar
  image (`https://avatars.githubusercontent.com/<handle>?s=68`), same 34×34 box, same 7px radius.
- **Tooltip contents** are real only for rank 1 (taken from the live page); every other row shows
  a stand-in string. In production the tooltip is populated from the existing breakdown data.

---

## Screen: Contributor Leaderboard

### Purpose
Rank contributors by a 12-month activity score; let a visitor jump to a contributor's GitHub
profile or to the detail page listing the issues and PRs behind their score.

### Layout
Unchanged from the current page: full-width container, page title block, intro paragraph,
"How are scores tallied?" button, four tabs (Contributor / Maintainer / Monthly / Highlights),
then the table.

The table is **not** a card: no border, no radius, no background fill. It sits flush with the
page gutter — see "Page gutter and title block", which is the governing rule.
Header row and each data row are a 3-column grid:

```
grid-template-columns: 56px 1fr 136px;   /* rank | contributor | score */
gap: 0 12px;
align-items: center;
```

- Header row: `padding: 11px 0 9px`, no background, `border-bottom: 1px solid #e6e7ea`.
- Data rows: `padding: 10px 0`, `border-bottom: 1px solid #eff0f2`.
- No horizontal padding on rows: the 18px inset is removed so the rank column aligns with the
  H1 above it. Horizontal inset comes from the page container only.

**Structural change: the Details column is removed.** It previously occupied a fourth 112px
column with an outlined orange button per row. Its destination is now reached from the
count link under each contributor's name (see below).

### Components

**Page title** — "Contributor Leaderboard"
Libre Franklin 700, 40px, `letter-spacing: -.032em`, `line-height: 1.05`, colour `#15171b`.
Block padding `26px 36px 24px`, `border-bottom: 1px solid #e6e7ea` — the site-wide title block;
see "Page gutter and title block".

**Intro paragraph** — copy unchanged, verbatim:
"Ranked by the last 12 months of activity — recent work and bigger changes count for more.
Points come from opening issues, opening PRs, getting a PR merged, and closing an issue with a
merged PR. Note that scores are subject to change."
Libre Franklin 400, 15px, `line-height: 1.6`, colour `#3c4148`, `max-width: 760px`,
`text-wrap: pretty`.

**"How are scores tallied?"** — a `<button type="button">` that opens the scoring modal, not a
link. Styled as a text link: Libre Franklin 500, 14px, colour `#ee6524`,
underlined, `text-underline-offset: 2px`, with `background: none; border: 0; padding: 0;
font: inherit; cursor: pointer`. Behaviour and modal contents: `README-scoring-modal.md`.
The only navigating "How scores work" affordance is the footer link.

**Tabs** — Libre Franklin, 14.5px. Active tab 600 / `#15171b` on white with
`1px solid #dfe1e4`, bottom border white, `border-radius: 7px 7px 0 0`, `margin-bottom: -1px`.
Inactive tabs 500 / `#ee6524`, no border. Tab strip padding `11px 20px` per tab,
strip sits on `border-bottom: 1px solid #dfe1e4`.

**Table header labels** — "#", "Contributor", "Score" (Score centred).
Martian Mono 700, 9px, `letter-spacing: .08em`, `text-transform: uppercase`, colour `#4c525a`.

**Rank** — Martian Mono 500, 11px, colour `#4c525a`.

**Avatar — now the GitHub link.**
34×34, `border-radius: 7px` (square-ish, not a circle: GitHub serves square images, the circle
is CSS-only). Placeholder background `#e9eaed`, initials Martian Mono 9px `#4c525a`.
`href="https://github.com/<handle>"`. Rest state has no decoration;
hover adds `box-shadow: 0 0 0 2px #ee6524`. Give it an accessible name
(`title`/`aria-label`: "GitHub profile — <name>").
For contributors with no display name distinct from their handle (rogerdz, thai2301,
DmitryFurs, KrasnoshchokBohdan), the handle is derived from the display name.

**Display name** — Libre Franklin 600, 16px, `letter-spacing: -.012em`, `line-height: 1.3`,
colour `#15171b`. **Not a link** — plain ink, truncated with ellipsis on overflow.
(It was orange and link-coloured on the live page; making the avatar the only profile link
leaves exactly one orange link per row, so the row reads unambiguously.)

**Handle** — Martian Mono 9.5px, colour `#5d636c`, baseline-aligned 8px after the name.
Martian Mono is a wide face; it must sit a size step below the sans or it crowds the name.

**Count link (replaces the Details button)** — second line under the name.
Martian Mono 9.5px, `line-height: 1.7`, colour `#ee6524`,
`border-bottom: 1px solid rgba(238,101,36,.4)` → `#ee6524` on hover, no underline.
Links to the same destination the Details button used (the contributor's issue/PR breakdown page).
Label: the contributor's own counts, e.g. "201 PRs · 47 issues". If counts aren't available for a
row, fall back to "See contributions".

**Score** — Martian Mono 700, 13px, `font-variant-numeric: tabular-nums`, colour `#15171b`,
centred in its column. **The green pill is removed.** Its hover affordance is now
`border-bottom: 1px dotted #9aa0a8` + `cursor: help` — the standard "this has an explanation"
convention, which costs no colour or shape. `tabindex="0"` so it is keyboard-reachable.

**Score tooltip** — unchanged in content, restyled.
Opens on hover/focus of the score. Panel: background `#15171b`, `color: #fff`,
`border-radius: 9px`, `padding: 12px 16px`, `box-shadow: 0 8px 24px rgba(0,0,0,.22)`,
`width: max-content`, `max-width: 380px`, `pointer-events: none`, `z-index: 20`.
Positioned `top: calc(100% + 9px); right: 0` relative to the score cell — **below and
right-aligned**, so it never clips out of the card on the first row or at the right edge.
7px CSS triangle on the top edge, `right: 52px`.
Each line is a 3-part flex row rather than a sentence:
- label — Libre Franklin 13px, `#e3e5e8`, `flex: 1`
- count ("201×") — Martian Mono 9.5px, `#9aa3ae`
- points ("585.6 pts") — Martian Mono 700, 10px, `#fff`, tabular

so the point values align in a column down the right edge.
Note the parent table card must **not** have `overflow: hidden`, or the tooltip is clipped.

### Interactions & behaviour
- Avatar → `https://github.com/<handle>` (external; consider `target="_blank" rel="noopener"`).
- Count link → existing contributor detail page (the old Details destination).
- Score hover/focus → tooltip in, tooltip out on leave/blur. No transition in the prototype;
  a 100ms fade is fine.
- Row hover: none in 6a. (Turn 4 explored a whole-row-clickable variant, `#4a`, and an
  expand-in-place variant, `#4c`, if you later want to revisit.)
- Responsive: not specified in this exercise. The prototype is fixed at 980px.
  At narrow widths the sensible collapse is to drop the handle onto its own line and
  right-align the score under the name block.

### State management
One piece of UI state only: which row's score tooltip is open (`hoveredRank | null`).
No data fetching changes — same payload as today.

---

## Design tokens

### Fonts
```
@import url('https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700&family=Martian+Mono:wght@400;500;700&display=swap');

--font-sans: 'Libre Franklin', system-ui, sans-serif;
--font-mono: 'Martian Mono', ui-monospace, monospace;
```
Both are SIL Open Font License, so self-hosting is fine and preferable — subset to latin and
preload the two weights used most (Libre Franklin 600, Martian Mono 500).

### Type scale
| role | family | size | weight | tracking |
|---|---|---|---|---|
| page title | sans | 40px | 700 | -.032em |
| intro copy | sans | 15px / 1.6 | 400 | — |
| tab label | sans | 14.5px | 500 / 600 | — |
| table header | mono | 9px | 700 | .08em, uppercase |
| rank | mono | 11px | 500 | — |
| display name | sans | 16px / 1.3 | 600 | -.012em |
| handle | mono | 9.5px | 400 | — |
| count link | mono | 9.5px / 1.7 | 400 | — |
| score | mono | 13px | 700 | tabular-nums |
| tooltip label | sans | 13px / 1.35 | 400 | — |
| tooltip count | mono | 9.5px | 400 | — |
| tooltip points | mono | 10px | 700 | tabular-nums |

### Colours
| token | value | use |
|---|---|---|
| ink | `#15171b` | names, scores, title, tooltip background |
| ink secondary | `#3c4148` | intro copy |
| muted | `#5d636c` | handles |
| muted strong | `#4c525a` | ranks, table header, avatar initials |
| link | `#ee6524` | all links, hover states |
| link underline rest | `rgba(238,101,36,.4)` | count link |
| hairline | `#eff0f2` | row dividers |
| border | `#e6e7ea` | table header divider, title-block divider |
| border light | `#dfe1e4` | tab strip |
| avatar placeholder | `#e9eaed` | remove once real avatars are in |
| dotted affordance | `#9aa0a8` | score underline |

## Page gutter and title block — site-wide

Every page uses **one** gutter and **one** title-block height. The detail page's are the
standard; the leaderboard, monthly, highlights and by-month pages are brought to them.

- **Gutter** — the page container's horizontal inset. The narrower detail-page gutter wins: it is
  the one the site already uses on its densest page and it gives the tables more measure. In the
  Blade/Bootstrap templates this means every page uses the **same container class as the detail
  page** — do not mix `container` and `container-fluid` between pages, and do not set a per-page
  `max-width`.
- **No second gutter inside the first.** Table rows, cards and panels sit flush with the page
  gutter; they must not add their own horizontal padding on top of it. The leaderboard's table
  rows previously carried an extra 18px inset, so its content started ~18px further in than the
  detail page's — that inset is removed and the rank column now aligns with the H1 above it.
  Vertical padding inside rows is unchanged.
- **Title block** — `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`, on every page.
  The 36px is the gutter and comes from the container at narrow widths. Earlier drafts used
  `30px … 26px` on the board pages and `26px … 24px` on the detail page; the smaller pair is now
  used everywhere, so the H1 sits at the same height on every page and does not shift when
  clicking through.

The rule to hold onto: **dark bar → 26px → H1 → 24px → rule → content**, identically on every
page. The only thing that changes between pages is what is inside the block (a page name, or a
person's avatar, name, handle and score).

**On the orange.** Link text is `#ee6524` throughout, chosen by the team to sit closer to the
Magento brand orange (`#f26322`) than the darker `#c2521a` earlier drafts used.

**This is a known AA exception.** `#ee6524` on white measures about **3.2:1** — it passes the
3:1 minimum for large text (24px+, or 19px+ bold) but not the 4.5:1 minimum for body-size text,
which is most of the links on these pages. It was accepted as a brand decision; it is recorded
here so it is a decision and not an oversight.

Two things follow from it:
- Never rely on colour alone to mark a link. Every body-size link in this design is underlined
  (or carries an underline on hover plus another affordance — a chevron, an arrow, a row hover
  fill). That underline is doing the work the contrast ratio is not.
- Do not push it further toward `#f26322`, and do not use it for small non-link text (captions,
  counts, metadata), where there is no underline to compensate. Those stay `#5d636c` / `#6b7178`.

If AA compliance for body links is required later, `#c2521a` (4.9:1, same hue) is the drop-in
replacement — swap the token, nothing else changes.

### Spacing / radius
Row padding `10px 0` · header padding `11px 0 9px` · page header `26px 36px 24px` ·
body block `26px 36px` · table block `20px 36px 30px` · grid gap `12px` ·
avatar → text gap `12px` · name → handle gap `8px`.
Radius: avatar `7px` · tooltip `9px` · tab top `7px`. The table itself has no radius.
Shadow: tooltip only — `0 8px 24px rgba(0,0,0,.22)`.

---

## Assets
None to import. Avatars come from GitHub at runtime; no icons are used (the Details button,
the only icon-adjacent element, is gone). Fonts are Google Fonts / OFL.

## Files
- `../Leaderboard Type Directions.dc.html` — the design reference (lives beside this folder, not in it). Implement **turn 6 / `#6a`** only.
- `reference-current-page.png` — screenshot of the page as it is today, for before/after.

## Summary of changes for a reviewer
1. Typeface pairing → Libre Franklin + Martian Mono (was the default UI sans).
2. Green score pills removed; scores are plain tabular mono.
3. Details column removed; its link moves under the contributor name as a contribution count.
4. Display name is no longer a link; the avatar is, and it points at GitHub.
5. Avatars square-ish (7px radius) rather than circular.
6. Score gains a dotted-underline + help-cursor affordance for the existing points tooltip;
   tooltip repositioned below-right so it can't clip, and its lines set as aligned columns.
7. Link orange set to `#ee6524` — brand-matched; see the contrast exception under "On the orange".
