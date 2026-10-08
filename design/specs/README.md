# Handoff: Forger leaderboard refresh

## Overview
A redesign of the Magento Open Source Forger leaderboard and the pages around it. It began as a
typographic refresh of `/leaderboard/contributor` and grew to cover the homepage, the header
and footer, the detail pages, the monthly views, and the scoring explanations.

Approved type direction: **Libre Franklin (sans) + Martian Mono (mono)**, applied throughout.

## About the design files
`../Leaderboard prototype.html`, one level up from this folder, is a **design reference
written in HTML** — a prototype showing the intended look and behaviour. It is not production
code to copy. The Forger is a Laravel/Blade + Bootstrap app; the task is to reproduce the
specifications in this folder in that codebase, using its existing template and CSS conventions.

The prototype is organised in numbered turns, newest first. Rejected explorations have been
removed, so anything you can see is something to build. Options are referenced by id throughout these specs: `#21a` is the
contributor board, `#19b` the contributor scoring modal, and so on.

## Where to find things

| Spec | Covers | Prototype |
|---|---|---|
| `README.md` (this file) | Type scale, design tokens, capitalisation, gutter and title block — the site-wide foundation | — |
| `README-header.md` | Dark masthead, nav, account chip, footer | `#11a` `#12a` `#13a` `#16a` `#24a` |
| `README-homepage.md` | Hero, live top-five card, area grid, first-timer steps | `#15a` `#24d` |
| `README-leaderboard-pages.md` | All three boards: control strip, search, jump, pagination, hover, narrow widths | `#21a` `#21b` `#21c` `#22a` `#22b` `#22d` `#22c` `#23a` `#23b` `#23c` `#24a` `#25b` |
| `README-detail-page.md` | Contributor and maintainer detail, all four states, Grouped and List views | `#17a` `#17b` `#17c` `#17d` `#18a` `#18b` `#24b` |
| `README-scoring-modal.md` | Both scoring modals, and the scoring button rule | `#19a` `#19b` `#24c` |
| `README-how-scores-work.md` | Standalone scoring page, both boards side by side | `#20a` |
| `README-highlights.md` | New contributor spotlight, Comebacks, Rising, Recently active | `#8b` |
| `README-issues-prs-by-month.md` | Both by-month timelines, colour thresholds, month picker | `#14b` `#24e` |

Three rules are stated once and apply everywhere: **capitalisation** (below), **focus rings**
and **hover states** (both in `README-leaderboard-pages.md`).

## Which spec governs the boards

This document and `README-leaderboard-pages.md` describe the same three boards at two different
stages of the work. **`README-leaderboard-pages.md` supersedes this one for anything about the
board's structure or behaviour.** Where they disagree, it wins.

The section below, *Screen: Contributor Leaderboard*, is the earlier generation (formerly card `#6a`, now removed from the prototype): a
three-column table with no control strip. It is kept because its tooltip, avatar and name
detail are unchanged and still exact. Everything that arrived later — the activity
column, the control strip, search, jump-to-my-rank, pagination,
focus rings and hover states — exists only in `README-leaderboard-pages.md` and the turn 21–25
prototype cards.

**Scope note for whoever picks this up.** The current site implements the `#6a` generation. The
whole turn 21–25 layer is unbuilt, and it is the larger half of this handoff: it is a decision
about how much to take on, not a list of fixes to a shipped page. Build the foundation from this
document, then the boards from `README-leaderboard-pages.md`.

## Fidelity
**High-fidelity** for typography, colour and spacing — the values in these specs are exact and
should be matched.

Two things in the prototype are placeholders:
- **Avatars** are grey rounded squares with initials. In production use the real GitHub avatar
  image (`https://avatars.githubusercontent.com/<handle>?s=56`) in the same box and radius as the
  placeholder — 28×28 at 6px on the boards; each spec gives its own size elsewhere.
- **Tooltip contents** are real only for rank 1 (taken from the live page); every other row shows
  a stand-in string. In production the tooltip is populated from the existing breakdown data.

---

## Screen: Contributor Leaderboard (earlier generation — superseded in part)

> Superseded by `README-leaderboard-pages.md` for layout and behaviour. What remains
> authoritative here: the page title block, the name and handle treatment, the
> score tooltip (content, position and structure), the avatar-as-GitHub-link rule, and the
> colour tokens. Type sizes, the tab treatment, the column grid, the absence of a control strip
> and the interaction list below are the earlier generation's — `README-leaderboard-pages.md`
> restates all of them at their current values and wins.

### Purpose
Rank contributors by a 12-month activity score; let a visitor jump to a contributor's GitHub
profile or to the detail page listing the issues and PRs behind their score.

### Layout
Unchanged from the current page: full-width container, page title block, intro paragraph,
"How scoring works" button, four tabs (Contributor / Maintainer / Monthly / Highlights),
then the table.

The table is **not** a card: no border, no radius, no background fill. It sits flush with the
page gutter — see "Page gutter and title block", which is the governing rule.

Header row and each data row are a flex row — the activity summary moved out of the name cell in
turn 21, which halved the row height:

```
display: flex; align-items: center; gap: 16px;
/* rank 42px | avatar 28px | contributor flex: 1 | activity 130px | score 70px */
```

`README-leaderboard-pages.md` carries the column table and is authoritative. The earlier
three-column form (`56px 1fr 136px`, activity inside the name cell) is what the site has
today.

- Header row: `padding: 11px 0 9px`, no background, `border-bottom: 1px solid #e6e7ea`.
- Data rows: `padding: 10px 0`, `border-bottom: 1px solid #f0f1f3`.
- No horizontal padding on rows: the 18px inset is removed so the rank column aligns with the
  H1 above it. Horizontal inset comes from the page container only.

**Structural change: the Details column is removed.** It previously occupied a fourth 112px
column with an outlined orange button per row. Its destination is now reached from
each contributor's name (see below).

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

**"How scoring works"** — a `<button type="button">` that opens the scoring modal, not a
link. Styled as a text link: Libre Franklin 500, 14px, colour `#ee6524`,
underlined, `text-underline-offset: 2px`, with `background: none; border: 0; padding: 0;
font: inherit; cursor: pointer`. Behaviour and modal contents: `README-scoring-modal.md`.
In the intro it reads as a sentence, "See how scoring works.", with "See how scoring works" as
the button (see `README-leaderboard-pages.md`). The only navigating route to the How scoring
works page is the footer link, labelled "How scoring works".

**Tabs** — Libre Franklin, 14.5px. Active tab 600 / `#15171b` on white with
`1px solid #dfe1e4`, bottom border white, `border-radius: 7px 7px 0 0`, `margin-bottom: -1px`.
Inactive tabs 500 / `#ee6524`, no border. Tab strip padding `11px 20px` per tab,
strip sits on `border-bottom: 1px solid #dfe1e4`.

**Table header labels** — "#", "Contributor", "Score" (Score centred).
Martian Mono 700, 9px, `letter-spacing: .08em`, `text-transform: uppercase`, colour `#4c525a`.

**Rank** — Martian Mono 500, 11px, colour `#4c525a`.

**Avatar — now the GitHub link.**
28×28, `border-radius: 6px` (square-ish, not a circle: GitHub serves square images, the circle
is CSS-only). Placeholder background `#e9eaed`, initials Martian Mono 9px `#4c525a`.
`href="https://github.com/<handle>"`. Rest state has no decoration;
hover adds `box-shadow: 0 0 0 2px #ee6524`. Give it an accessible name
(`title`/`aria-label`: "GitHub profile — <name>").
For contributors with no display name distinct from their handle (rogerdz, thai2301,
DmitryFurs, KrasnoshchokBohdan), the handle is derived from the display name.

**Display name** — Libre Franklin 600, 16px, `letter-spacing: -.012em`, `line-height: 1.3`,
colour `#15171b`, no underline, truncated with ellipsis on overflow. **It links to the person's
detail page**; hover `#ee6524`. The avatar goes to GitHub and the name to the detail page — one
destination each, so neither needs a second label.

**Handle** — Martian Mono 9.5px, colour `#5d636c`, baseline-aligned 8px after the name.
Martian Mono is a wide face; it must sit a size step below the sans or it crowds the name.

**Score** — Martian Mono 700, 13px, `font-variant-numeric: tabular-nums`, colour `#15171b`,
centred in its column. **The green pill is removed.** Its hover affordance is now
`border-bottom: 1px dotted #6b7178` + `cursor: help` — the standard "this has an explanation"
convention, which costs no colour or shape. `tabindex="0"` so it is keyboard-reachable.

**Score tooltip** — unchanged in content, restyled.
Opens on hover/focus of the score. Panel: background `#15171b`, `color: #fff`,
`border-radius: 9px`, `padding: 12px 16px`, `box-shadow: 0 8px 24px rgba(0,0,0,.22)`,
`width: max-content`, `max-width: 380px`, `pointer-events: none`, `z-index: 20`.
Positioned `top: calc(100% + 9px); right: 0` relative to the score cell — **below and
right-aligned**, so it never clips out of the card on the first row or at the right edge.
7px CSS triangle on the top edge, centred over the score (`right: 22px` in the 70px score column).
Shown on `#21a`, rank 1.
Each line is a 3-part flex row rather than a sentence:
- label — Libre Franklin 13px, `#e3e5e8`, `flex: 1`
- count ("201×") — Martian Mono 9.5px, `#9aa3ae`
- points ("585.6 pts") — Martian Mono 700, 10px, `#fff`, tabular

so the point values align in a column down the right edge.
Note the parent table card must **not** have `overflow: hidden`, or the tooltip is clipped.

### Interactions & behaviour
- Avatar → `https://github.com/<handle>` (external; consider `target="_blank" rel="noopener"`).
- Name → existing contributor detail page (the old Details destination).
- Score hover/focus → tooltip in, tooltip out on leave/blur. No transition in the prototype;
  a 100ms fade is fine.
- Row hover: background `#faf9f7`, with the name link turning `#ee6524`. Every hover state on
  these pages is tabulated in one place — see `#25b` and *Hover states* in
  `README-leaderboard-pages.md`.
- Responsive: drawn at 420px in `#24a`, specified under *Narrow widths* in
  `README-leaderboard-pages.md`. The activity column drops below roughly 700px and the handle
  moves under the name; rank and score keep their positions.

### State management
In this generation, one piece of UI state only: which row's score tooltip is open
(`hoveredRank | null`), and no data-fetching changes — the same payload as today.

The current generation adds three: the search query, the shown row depth, and the selected month
on the monthly board. The board is still one server-rendered payload; nothing is fetched after
load. The depth and the month are reflected in the URL (`?rows=`, and the month as a path segment,
`/leaderboard/monthly/{board}/YYYY-MM`), alongside
`#rank-N` from a jump. See `README-leaderboard-pages.md`.

---

## Capitalisation

**Title case for page names. Sentence case for everything a person reads as a sentence.**

Title case is for strings that name a destination — the page H1s and the tab and nav labels
that point at them: "Contributor Leaderboard", "Maintainer Leaderboard", "Monthly Leaderboard",
"Issues By Month", "PRs By Month", "Leaderboard Highlights". If it is a page and something links to it by name, it is title case. A link that spells
out the full name must match the H1 exactly. Tab, nav and footer labels may use a short form of
the name — "Contributor", "Highlights", "Leaderboard", "Pull Requests" — and stay title case.
The one exception is the standalone scoring page: its H1 is sentence case, "How scoring works",
and the footer link to it uses the same words, "How scoring works". The in-page button that
opens the scoring modal is a sentence, "See how scoring works." — the button label is "See how
scoring works" and the full stop sits outside it.

Sentence case is for everything else: section H2s ("Where the work is"), modal titles ("How
contributor scores are tallied"), every button ("See the leaderboard", "Show 25 more", "Jump to
my rank", "Join our Slack"), every link ("How scoring works"), captions,
empty states, and all body copy. Buttons are sentence case without exception — a button is an
instruction, not a name.

Mono eyebrows, column headers and month chips are a third case: always uppercase with
`letter-spacing: .06em`. That is a typographic treatment, not a capitalisation decision, so the
rule above does not apply to them — the underlying string is still written sentence case.

Proper nouns keep their own casing anywhere they appear: Magento Open Source, Mage-OS, Slack,
GitHub, Magento Association.

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
Current board values, matching `README-leaderboard-pages.md`. The earlier-generation section above quotes the
earlier sizes; these win.

| role | family | size | weight | tracking |
|---|---|---|---|---|
| page title | sans | 40px | 700 | -.032em |
| intro copy | sans | 14.5px / 1.65 | 400 | — |
| tab label | sans | 14px | 500 / 600 | — |
| table header | mono | 9px | 400 | .06em, uppercase |
| rank | mono | 13px | 400 (700 for ranks 1–3) | — |
| display name | sans | 14.5px | 600 | — |
| handle | mono | 10px | 400 | — |
| score | mono | 14px | 700 | tabular-nums |
| tooltip label | sans | 13px / 1.35 | 400 | — |
| tooltip count | mono | 9.5px | 400 | — |
| tooltip points | mono | 10px | 700 | tabular-nums |

### Colours
| token | value | use |
|---|---|---|
| ink | `#15171b` | names, scores, title, tooltip background |
| ink secondary | `#3c4148` | intro copy, board ranks 4+ |
| muted | `#5d636c` | detail-page handle, dates, secondary copy |
| muted light | `#6b7178` | board and Highlights handles, captions, column headers, dotted score underline |
| muted strong | `#4c525a` | Highlights ranks and unit labels, detail group counts, avatar initials |
| link | `#ee6524` | all links, hover states |
| link underline rest | `rgba(238,101,36,.4)` | detail page "Show all" control |
| hairline | `#f0f1f3` | row dividers, everywhere on every page |
| border | `#e6e7ea` | table header divider, title-block divider, tab strip, card and tile borders, by-month baseline and zero stub |
| outline | `#d5d8dc` | outlined controls: search, buttons, detail month chips, by-month tiles |
| border light | `#dfe1e4` | scoring modal 0× recency bar |
| avatar placeholder | `#e9eaed` | remove once real avatars are in |
| link hover | `#c74e16` | text-link hover |
| chip fill | `#f7f5f2` | monthly-board month chips, priority chips |
| close hover | `#f4f5f6` | scoring modal ✕ hover |
| tooltip label | `#e3e5e8` | score tooltip label text, on `#15171b` only |
| dotted affordance | `#6b7178` | score underline |

One hairline, one value. Earlier prototype cards render row rules as `#eff0f2` or `#eeeff1`;
those are the same rule at different drafts. Use `#f0f1f3` everywhere and do not reintroduce
the near-identical variants. The same goes for borders: `#e3e5e8` and `#e6e8ea` as border or
rule colours are `#e6e7ea`.

### Score formatting
Scores show one decimal, always (`839.3`, `15.0`, `0.0`), in tabular figures. No thousands
separator below 1,000; a comma from 1,000 up (`1,204.5`). The same format applies on the boards,
the detail pages, the homepage card and Highlights.

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
Row padding `10px 0` · column header `padding-bottom: 8px` · page header `26px 36px 24px` ·
body block `24px 36px 0` · pagination block `20px 36px 30px` · row gap `16px` (rank → avatar →
name → activity → score, avatar → name included) · name → handle gap `8px`. Current values, per
`README-leaderboard-pages.md`.
Radius: avatar `6px` · tooltip `9px` · tab top `7px`. The table itself has no radius.
Shadow: tooltip only — `0 8px 24px rgba(0,0,0,.22)`.

---

## Assets
None to import. Avatars come from GitHub at runtime; no icons are used (the Details button,
the only icon-adjacent element, is gone). Fonts are Google Fonts / OFL.

## Files
- `../Leaderboard prototype.html` — the design reference (lives beside this folder, not in it). Every option in it is approved; `#21a` is the current contributor board; hover or focus rank 1's score there to see the score tooltip.
- `reference-current-page.png` — screenshot of the page as it is today, for before/after.

## Summary of changes for a reviewer
1. Typeface pairing → Libre Franklin + Martian Mono (was the default UI sans).
2. Green score pills removed; scores are plain tabular mono.
3. Details column removed; the contributor name now links to the detail page.
4. The avatar links to GitHub; the name links to the detail page.
5. Board and detail avatars square-ish (6px radius on the boards) rather than circular. The
   homepage card, header chip and account menu keep circular avatars.
6. Score gains a dotted-underline + help-cursor affordance for the existing points tooltip;
   tooltip repositioned below-right so it can't clip, and its lines set as aligned columns.
7. Link orange set to `#ee6524` — brand-matched; see the contrast exception under "On the orange".
