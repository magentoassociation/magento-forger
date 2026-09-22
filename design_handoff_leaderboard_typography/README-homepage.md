# Handoff: Homepage — contribute, climb the board

## Overview
Replaces the current homepage. Approved direction: **15a — dark hero carrying a live top-five
leaderboard, with an empty row for the visitor.**

The existing page opens with a centred headline and CTA pair, then three equal-weight benefit
cards ("Make real impact", "Level up", "Get recognized"), then Ready to code, then the area grid,
then a tall "First time contributing?" box, then a repeat of the hero CTAs. The leaderboard —
the actual draw — appears once, as a link inside the third card. 15a puts the board above the
fold as real data with a visible empty slot for the visitor, and removes the three cards and the
closing CTA repeat.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the
intended look and behaviour, not production code to copy. Reproduce the spec below in the
Laravel/Blade + Bootstrap codebase using its existing template conventions.

In the prototype, **`#15a` is the approved design.** `#15b` (white hero, board as a horizontal
scoreboard strip) was explored and rejected.

## Fidelity
**High-fidelity** for colour, type and spacing — the values below are exact. Layout proportions
are exact at the container width; see "Responsive".

Placeholders in the prototype: the logo is a grey rounded square; leaderboard avatars are
initials circles. In production use the real Forger logo and the GitHub avatars already used on
the leaderboard (see `README.md`).

## Depends on
- `README-header.md` — header (11a) and footer (12a). Both appear on this page unchanged. The
  hero band below is a **third** dark band; see "Hero" for how it joins the header.
- `README.md` — font loading, leaderboard row conventions, score formatting.

---

## Page structure

Top to bottom:

1. Header — 11a, unchanged. **The white page-title block is omitted on the homepage** (there is
   no H1 block; the hero carries the H1).
2. Hero — dark, full-bleed band containing the headline, CTAs and the leaderboard card.
3. Start contributing — "Ready to code" row.
4. Pick your area — 2-column list.
5. First time contributing? — three-step rail.
6. Footer — 12a, unchanged.

**Removed from the current page:** the three benefit cards, and the closing "Ready to ship your
first fix?" block with its duplicate CTA pair. The hero CTAs are the only CTAs; "Ready to code"
and the area list carry the rest of the intent.

All sections use the **same centred content container as the rest of the site**
(`max-width` + `margin: 0 auto`). The hero's dark background is full-bleed; its contents sit in
the container. Section padding below the hero is `0 36px` horizontally, which becomes the
container gutter at narrow widths (same rule as the header).

---

## Hero

Background `#15171b` — the same value as the dark bar, with **no divider between them**: the
header bar and the hero read as one dark mass, 64px of bar plus the hero. Do not add a border,
shadow or colour shift at the seam.

`padding: 48px 36px 54px`. `display: flex; gap: 44px; align-items: flex-start`.
Left column `flex: 1.05`, right column `flex: 1`.

### Left column

**Eyebrow** — "Open source · maintained in public".
Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`, colour `#9aa3ae`. This is the header's
utility-link token reused; it is the only mono in the hero's left column.

**H1** — "Ship a fix. Climb the board."
Libre Franklin 700, 46px, `letter-spacing: -.034em`, `line-height: 1.04`, colour `#ffffff`,
`margin: 14px 0 0`, `text-wrap: pretty`.

This is the page's `<h1>` and the only one. It is 6px larger than the standard page title (40px)
because it sits on a dark field with no rule below it.

**Body** — one paragraph, `margin-top: 16px`, `max-width: 420px`,
Libre Franklin 400, 15.5px, `line-height: 1.6`, colour `#c9ced4`, `text-wrap: pretty`:

> Magento powers thousands of stores worldwide, and it's maintained in the open by developers
> like you. Pick an issue, open a PR, and ship a fix that real merchants will use. Every
> contribution scores and moves you up the contributor leaderboard.

The first two sentences are the live page's copy verbatim; the third replaces the old "Get
recognized" card. Keep the paragraph to three sentences — the `max-width: 420px` measure is what
lets the board card sit beside it.

**CTA pair** — `display: flex; gap: 10px; margin-top: 26px`.

| | Primary | Secondary |
|---|---|---|
| Label | "Find an issue to work on →" | "Login with GitHub" |
| Padding | `12px 20px` | `12px 20px` |
| Radius | `7px` | `7px` |
| Fill | `#f26322` | none |
| Border | none | `1px solid #3a3f46` |
| Ink | `#15171b` | `#ffffff` |
| Type | Libre Franklin 700, 14.5px | Libre Franklin 600, 14.5px |
| Hover | background `#ff7433` | border-color `#c9ced4` |

Both are larger than the header's login button (`8px 15px` / 13.5px) — deliberate, so the hero
CTA outranks the one in the bar. The secondary button carries the GitHub mark at ~15px, as it
does today.

When the visitor is signed in, the secondary button is dropped and the primary becomes the only
CTA; the CTA row keeps its `margin-top`. The header shows the account chip in place of the login
button — see `README-header.md`, "Signed-in state (16a)".

### Right column — the leaderboard card

Background `#1c1f24`, `border: 1px solid #2b2f36`, `border-radius: 10px`,
`padding: 18px 20px 16px`. This is a lighter dark than the hero so the card reads as a panel, not
a hole; no shadow.

**Card head** — `display: flex; align-items: baseline; justify-content: space-between;
margin-bottom: 12px`.
- Label: "Leaderboard · last 12 months" — Martian Mono 400, 9px, uppercase, `.06em`, `#9aa3ae`.
  This matches the main leaderboard, which is a rolling 12-month window, not a calendar period.
- Link: "Full board →" — Libre Franklin 600, 12.5px, `#c9ced4`, no underline. Hover `#ffffff`.
  Links to the leaderboard (Contributor tab, the default view).

**Rows — top five.** Live data: the top five of the Contributor leaderboard — the same rolling
12-month ranking, same scores, same order. This card is a truncation of that table, never a
separately computed ranking. Each
row: `display: flex; align-items: center; gap: 11px; padding: 8px 0;
border-bottom: 1px solid #262a30`. The fifth row keeps its border (it separates the ranked rows
from the visitor's row).

| Element | Spec |
|---|---|
| Rank | Martian Mono 400, 10.5px, `#8a919b`, `width: 18px`, zero-padded (`01`…`05`) |
| Avatar | 26×26, `border-radius: 50%`, GitHub avatar; fallback initials 10px/700 `#c9ced4` on `#33383f` |
| Name | `flex: 1`, Libre Franklin 500, 13.5px, `#ffffff` |
| Score | Martian Mono 700, 12px, `#ffffff`, right-aligned |

The whole row is a link to that contributor's leaderboard detail page. Hover: name and score go
to `#ffffff` (already) and the row background to `#22262c` — the row is the hit area, not the
name. Avatars are **not** separately linked here (unlike the leaderboard table); one hit target
per row.

Scores use the same formatting as the leaderboard (one decimal, no thousands separator under
1000) — see `README.md`.

**The visitor's row.** Immediately below the five, `padding: 11px 0 3px`, same flex layout, no
border.

- Rank slot: an em dash `—` in Martian Mono 10.5px, colour `#f26322`, same `width: 18px`.
- Avatar slot: 26×26 circle, `border: 1px dashed #4a5057`, no fill.
- Label: "Your row is empty" — Libre Franklin 600, 13.5px, colour `#f9a279`.
- Score: "0.0" — Martian Mono 400, 12px, `#9aa3ae`.
- Caption below, `margin: 6px 0 0 55px` (aligned to the name column), Libre Franklin 400, 12px,
  `line-height: 1.5`, `#9aa3ae`, `text-wrap: pretty`: "Your score starts with your first
  contribution." It may wrap to two lines inside the card; that is fine, and the card grows to
  fit rather than the caption shrinking.

This row is the mechanism the whole page turns on, and it has three states:

| State | Rank | Avatar | Label | Score | Caption |
|---|---|---|---|---|---|
| Signed out | `—` | dashed circle | "Your row is empty" | `0.0` | "Your score starts with your first contribution." |
| Signed in, no scoring activity in the window | `—` | the user's GitHub avatar | the user's name | `0.0` | "Your score starts with your first contribution." |
| Signed in, ranked outside the top five | real rank, zero-padded, `#f26322` | avatar | name, `#ffffff` | real score, `#ffffff` 700 | "Your rank over the last 12 months." |

In the third state the row links to the user's own detail page and `#f26322` on the rank is what
distinguishes it from the five above. If the signed-in user is **inside** the top five, drop the
extra row entirely and give their row in the five a `#f26322` rank and a 2px `#f26322` left
border inset into the card padding.

Never render the row empty or hidden when signed out — the empty state is the pitch.

The caption deliberately names no threshold and no specific action. Earlier drafts read "One
merged PR puts you on the board", which is both unsafe (merging is not in the contributor's
hands, and the number of PRs needed to rank shifts as the board moves) and an invitation to work
the minimum. "Your score starts with your first contribution" names the trigger without quoting a price.
If this ever needs to be more specific, link "How scores work" rather than naming a number.

Because the window rolls, a contributor who stops contributing falls off it. Do not add copy
warning about decay on the homepage; the leaderboard's own intro paragraph ("Ranked by the last
12 months of activity") is where that is explained.

---

## Start contributing

`padding: 32px 36px 0`.

- `<h2>` "Start contributing" — Libre Franklin 700, 21px, `letter-spacing: -.024em`,
  `margin: 0 0 4px`, colour `#15171b`.
- Sub: "Pick up a confirmed, prioritized issue and open your first PR." — 14.5px, `#3c4148`,
  `margin: 0 0 16px`. Copy unchanged.

**Ready to code row** — one link, `display: flex; align-items: center; gap: 16px;
padding: 18px 20px; border: 1px solid #e3e5e8; border-radius: 10px`.
Hover: `border-color: #15171b`.

- Title "Ready to code" — Libre Franklin 700, 16.5px, `letter-spacing: -.018em`. **The 🛠 emoji
  is dropped.**
- Description "Confirmed, prioritized issues waiting for a developer." — 13.5px, `#5d636c`,
  `margin-top: 3px`. Copy unchanged.
- Count chip — Martian Mono 700, 11px, `padding: 5px 9px`, `border-radius: 5px`,
  background `#fdf1ea`, colour `#a8420f`. Text "N open".
- Trailing `→` — 15px/700, `#ee6524`.

The current page has the title, the description and a separate "Browse Ready for Work →" link
inside one card; the whole row is now the link and the separate link text is removed.

If more "ready" buckets are added later, they stack as identical rows with `gap: 8px`; the count
chip keeps its colour regardless of value.

---

## Pick your area

`padding: 26px 36px 34px`.

- `<h2>` "Pick your area" — same as above.
- Sub: "Jump straight to open issues in the part of Magento you know best." — unchanged, 14.5px,
  `#3c4148`, `margin: 0 0 14px`.

**The grid.** A hairline-ruled two-column list, not cards:
`display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: #e6e7ea;
border: 1px solid #e6e7ea; border-radius: 8px; overflow: hidden`. The 1px gap over the grey
background is what draws the rules.

Each cell is a link: `display: flex; align-items: center; justify-content: space-between;
gap: 10px; padding: 13px 16px; background: #fff`. Hover: `background: #faf9f7`.
- Name — Libre Franklin 600, 14.5px, `letter-spacing: -.012em`, `#15171b`.
- Count — Martian Mono 400, 10.5px, `#5d636c`, text "N open".

Order is by open count, descending — highest first, so the busiest areas lead. Ten areas as
today; the list is data-driven and any count is acceptable. An odd count leaves one cell empty:
render an empty `#fff` cell so the rules stay square.

This replaces four columns of drop-shadowed cards. At two columns the names are long enough to
scan and the counts line up in two vertical runs.

---

## First time contributing?

`margin-top: 30px; padding-top: 24px; border-top: 1px solid #e6e7ea`, inside the same section as
the area grid. `display: flex; gap: 26px`.

**Left** — fixed `width: 200px`, `flex: none`:
- `<h3>` "First time contributing?" — Libre Franklin 700, 17px, `letter-spacing: -.018em`.
- Sub: "Three steps to your first merged PR." — 13.5px, `line-height: 1.55`, `#5d636c`,
  `margin-top: 6px`.

**Right** — `flex: 1; display: flex`. Three equal steps, each `flex: 1`; steps 2 and 3 carry
`border-left: 1px solid #e6e7ea`. Padding: step 1 `padding-right: 18px`, step 2 `0 18px`,
step 3 `padding-left: 18px`.

Each step: a Martian Mono 400, 10px number (`01`, `02`, `03`) in `#a8420f`, then the step text at
Libre Franklin 400, 14px, `line-height: 1.5`, `margin-top: 5px`. Copy verbatim from the live
page:

1. Read the **Contribution Guidelines**
2. Set up your **development environment**
3. **Claim an issue** and open your first PR

Links: `#ee6524`, underlined, hover `#8f3a10` (site link tokens). Keep the same destinations.

The `<ol>` semantics stay — it is a numbered list rendered as a row, not three divs. This is the
same content as today's box; the box was ~230px tall with more than half of it empty.

---

## Colours

All values are existing tokens from `README-header.md` and `README.md`. New to this page:

| Token | Value | Used for |
|---|---|---|
| Hero card fill | `#1c1f24` | leaderboard card background |
| Hero card border | `#2b2f36` | leaderboard card border |
| Hero row rule | `#262a30` | rules between leaderboard rows |
| Hero row hover | `#22262c` | leaderboard row hover fill |
| Secondary border | `#3a3f46` | hero secondary button border |
| Empty-row ink | `#f9a279` | "Your row is empty" |
| Dashed avatar | `#4a5057` | empty avatar outline |

Reused: `#15171b`, `#f26322`, `#ff7433`, `#c9ced4`, `#9aa3ae`, `#8a919b`, `#ffffff`, `#e6e7ea`,
`#e3e5e8`, `#faf9f7`, `#3c4148`, `#5d636c`, `#fdf1ea`, `#a8420f`, `#ee6524`.

## Type

| Element | Family / size |
|---|---|
| H1 | Libre Franklin 700, 46px, `-.034em` |
| H2 | Libre Franklin 700, 21px, `-.024em` |
| H3 | Libre Franklin 700, 17px, `-.018em` |
| Hero body | Libre Franklin 400, 15.5px/1.6 |
| Section sub | Libre Franklin 400, 14.5px |
| Primary CTA | Libre Franklin 700, 14.5px |
| Board name | Libre Franklin 500, 13.5px |
| Board score / rank | Martian Mono 700 12px / 400 10.5px |
| Eyebrow, card label | Martian Mono 400, 9px, uppercase, `.06em` |
| Counts, step numbers | Martian Mono, 10–11px |

Mono is confined to ranks, scores, counts and the uppercase labels — everything numeric or
secondary. No mono in any headline or body paragraph.

---

## Accessibility

- Hero body `#c9ced4` on `#15171b` — 9.6:1. Eyebrow and card label `#9aa3ae` — 5.9:1. Board rank
  `#8a919b` — 5.5:1. Do not darken any of them.
- "Your row is empty" `#f9a279` on `#1c1f24` — 6.7:1. This is the light orange, not `#f26322`
  (2.9:1 on the card and not usable for 13.5px text). Do not substitute the brand orange here.
- Primary CTA `#15171b` on `#f26322` — 6.9:1. Dark ink on orange is required.
- Count chip `#a8420f` on `#fdf1ea` — 5.4:1.
- The hero H1 is the page's only `<h1>`; section headings are `<h2>`, the first-timer heading is
  `<h3>`.
- The leaderboard card is a `<ul>` of rows, not a table — it is a summary, and the real table is
  one link away. Give it an `aria-label` of "Top contributors this month".
- The visitor's row when signed out is not a link; it is a `<li>` whose caption sentence contains
  no link. The action lives in the CTAs above it, so the row does not need to be focusable.
- Focus: 2px `#f26322` outline, `outline-offset: 2px`, on every link and button on the page —
  including the dark hero, where the default ring is invisible.
- The area grid cells are links with the count in the accessible name ("Framework, 223 open"), so
  the count is not read as a bare number.

## Responsive

Below the Bootstrap `lg` breakpoint:
- The hero stacks: text column, then the leaderboard card, `gap: 30px`. Hero padding drops to
  `34px 20px 38px`; H1 to 36px; the body's `max-width` is released.
- The CTA pair stays a row until it can't, then each button goes full width with `gap: 8px`.
- The area grid collapses to one column; the 1px-gap rule technique still applies.
- The first-timer block stacks: heading block, then the three steps in a column with
  `border-left` swapped for `border-top: 1px solid #e6e7ea` and `padding: 14px 0 0`.
- The leaderboard card keeps all five rows plus the visitor's row at every width. Do not truncate
  it to three.

## Out of scope
The leaderboard, detail and by-month pages (their own specs). Signed-in account menu. The
"this month" scoping rule for scores is the existing leaderboard behaviour, unchanged.
