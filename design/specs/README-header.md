# Handoff: Site header and footer — dark chrome

## Overview
Replaces the current two-part page chrome (thin grey utility bar + solid orange masthead) with a
single dark navigation bar topped by an orange hairline, and rebuilds the footer to match it.
Approved directions: **11a — dark masthead with the white page title block kept as it is today**,
**12a — dark footer, orange only on the Slack button**, and **16a — the signed-in account as a
single chip at the right edge.**

This applies to every page on Magento Open Source Forger, not just the leaderboard. The page
content below the header is unchanged, except on the homepage, which has its own spec
(`README-homepage.md`) and omits the white page-title block.

## About the design files
`../Leaderboard Type Directions.dc.html`, one level up from this folder, is a design reference
written in HTML — a prototype of the
intended look and behaviour, not production code to copy. Reproduce the spec below in the
Laravel/Blade + Bootstrap codebase using its existing template conventions.

In the prototype, **`#11a`, `#12a` and `#16a` are the approved designs.** `#11b` (title carried on
the dark bar), `#12b` (orange Slack row retained) and `#16b` (Admin kept inline on the bar) were
explored and rejected; turn 10 shows the three masthead options 11a came from, and turn 16 shows
the bar signed in.

## Fidelity
**High-fidelity** for colour, type and spacing — the values below are exact.

Placeholder in the prototype: the logo is a grey rounded square reading "LOGO". In production use
the existing Forger logo mark at the same 30×30 box.

---

## Page width — applies to both header and footer

The dark bands, the orange hairline and the divider rule above the legal text are **full-bleed**:
they span the viewport at every width. Everything *inside* them is constrained to the **same
centred content container the page body already uses** (`max-width` + `margin: 0 auto` — reuse
the existing Bootstrap container, don't introduce a second width).

So the logo begins where the page content begins, and the login button ends where it ends, on
every screen. Without this, at 2560px the logo and the login button sit roughly a screen's width
away from anything they relate to, and the footer's link columns pin to the far right with an
empty gap beside them.

The horizontal padding quoted in the sections below (`0 36px` on the bar and in the footer)
becomes the container's own gutter at narrow widths; inside the container the contents need no
additional horizontal padding. `margin-left: auto` on the header's right-hand group and the
footer's link columns then resolves against the container, not the viewport.

On a wide screen the middle of the header reads emptier than it does today. That is the page's
actual measure and is intended; do not spread the nav to fill it.

See `#13a` in the prototype — header, content and footer at 1600px with a 1100px container.

## Structure

Three stacked pieces, full page width:

1. **Orange hairline** — `height: 3px`, background `#f26322`. No content. This is the only place
   the brand orange appears at full strength in the chrome.
2. **Dark bar** — background `#15171b`, `height: 64px`, `padding: 0 36px` — the same gutter as
   the title block below it, so the logo aligns with the H1,
   `display: flex; align-items: center; gap: 30px`.
3. **Page title block** — white background, `padding: 26px 36px 24px`,
   `border-bottom: 1px solid #e6e7ea`. The border is full-bleed; the H1 sits in the container.
   Same values on every page, including the user detail pages — see "Page gutter and title block"
   in `README.md`. (The homepage has no title block; its hero carries the H1.)

The previous grey utility bar is removed; its links move into the dark bar (see "Utility links").

## Dark bar contents

Left to right: logo lockup, primary nav, then a right-aligned group
(`margin-left: auto`) holding the login button (signed out) or the account chip (signed in).

**Logo lockup** — flex row, `gap: 11px`, links to `/`.
- Mark: 30×30, `border-radius: 7px`.
- Wordmark: Libre Franklin, 16px, `letter-spacing: -.022em`, two weights in one line —
  "Magento Open Source " in 500 / `#c9ced4`, "Forger" in 700 / `#ffffff`.

**Primary nav** — flex row, `gap: 2px`. Items: Home, Leaderboard, Issues ▾, PRs ▾.
- Each item: `padding: 8px 13px`, `border-radius: 6px`, Libre Franklin 14px.
- Default: weight 500, colour `#c9ced4`, no underline.
- Hover: colour `#ffffff`, background `#23262b`.
- Current section: weight 600, colour `#ffffff`, background `#2a2e34` (persistent, not a hover
  state). Exactly one item carries this at a time.
- Dropdown parents keep the ▾ glyph in the label; menu behaviour is unchanged from today.

**Utility links.** In this design the bar carries none, in either state — the two things the
old grey bar held both move: **Admin** into the account menu (see below), and the
"Ecosystem ▾" destinations into the footer's Community column. The right-hand group holds one
control and nothing else. `#11a` and `#16a` show the bar with no utility links in it.

If a site-wide utility link has to survive in the bar, it takes this treatment and sits before
the button or chip:
Martian Mono 400, 9px, `letter-spacing: .06em`, `text-transform: uppercase`, colour `#9aa3ae`,
no underline. Hover: `#ffffff`. This is the only mono in the header and is what keeps the
secondary links quiet next to the 14px nav.

**Login button** — "Login with GitHub". Signed out only.
`padding: 8px 15px`, `border-radius: 7px`, background `#f26322`, text `#15171b`,
Libre Franklin 700, 13.5px, no underline. Hover: background `#ff7433`.
When signed in it is replaced by the account chip below, in the same position.

Right-hand group: `display: flex; align-items: center; gap: 10px` signed out, `gap: 14px` signed
in (the chip needs more air than the button).

## Signed-in state (16a)

Today the signed-in bar puts the user's name as plain text in the empty middle of the bar,
unattached to any control, and gives the right edge an outlined **Admin** button and an orange
**Logout** button. Two problems: the name is not a control but is styled like a label floating in
dead space, and Logout takes the bar's only orange fill — the strongest emphasis on the page
spent on the action a visitor is least likely to want. Both go.

Signed in, the right-hand group is **one account chip, alone.** Nothing else is added to the
bar; the middle stays empty, as it does signed out.

`#16b` in the prototype (Admin kept inline as a mono utility link, avatar bare with no chip
fill) was explored and rejected.

### Account chip

A single link/button opening the account menu.
`display: flex; align-items: center; gap: 9px`, `padding: 5px 11px 5px 6px` (tighter on the left
so the avatar sits optically centred in the pill), `border-radius: 999px`,
background `#23262b` — the nav hover fill, reused. Hover: `#2f333a`.

- Avatar: 26×26, `border-radius: 50%`, the user's GitHub avatar. Fallback initials:
  Libre Franklin 700, 10px, ink `#e3ddf5` on `#4b3f6b` — a tinted fill rather than the logo
  grey, so an initials avatar still reads as a person at 26px.
- Name: the user's display name, Libre Franklin 500, 13.5px, colour `#ffffff` — the same size as
  the login button's label, so the chip holds the same weight in the bar as the button it
  replaces. Do not show the `@handle` here; it appears in the menu.
- Caret: `▾`, 10px, `#9aa3ae`.

The chip has a resting fill where nav items do not, which is what marks it as the one control in
the bar that belongs to you rather than to the site. Name truncates with
`max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap` — long names
must not push the chip into the nav.

### Account menu

Anchored to the chip's right edge, `margin-top: 8px`. `width: 196px`, background `#1c1f24`,
`border: 1px solid #2b2f36`, `border-radius: 9px`, `padding: 6px`,
`box-shadow: 0 12px 28px rgba(0,0,0,.28)`. Same panel values as the homepage hero card.

**Header block** — `padding: 8px 10px 9px`, `border-bottom: 1px solid #262a30`,
`margin-bottom: 5px`:
- Display name — Libre Franklin 600, 13px, `#ffffff`.
- Handle — `@laurafolco`, Martian Mono 400, 9.5px, `#9aa3ae`, `margin-top: 2px`.

**Items** — each `display: block`, `padding: 7px 10px`, `border-radius: 6px`,
Libre Franklin 500, 13.5px, `#c9ced4`, no underline. Hover: background `#262a30`, colour
`#ffffff`.

| Item | Destination | Shown to |
|---|---|---|
| My Contributions | the user's own **contributor** detail page | everyone |
| Admin | existing admin entry point | users with the admin role only |
| Logout | existing logout route | everyone |

Order is fixed. **Admin is omitted entirely for non-admins** — not disabled, not greyed — so the
menu is two items for most people and three for admins. The width is fixed at 196px in both
cases; do not size the panel to its contents.

**Maintainers get no extra item.** A maintainer is usually on both boards, but the account menu
is not where that distinction belongs — it would make the menu's length depend on two independent
flags and would put a role label in front of everyone who has the role, on every page. "My
Contributions" always goes to the **contributor** detail page; the maintainer page is reached
from a line below the groups on it. See `README-detail-page.md`, "Link to the person's other
board" — that also covers people who arrive from the board rather than the menu, which a second
menu item would not.

Logout is last and separated from the items above it by a rule:
`margin-top: 5px; padding: 12px 10px 7px; border-top: 1px solid #262a30` on the Logout item (the
same rule value as the header block's; the extra top padding is what sets it off the rule). It keeps the item's own type and hover treatment — the rule
marks it as leaving rather than navigating; it is not styled as destructive.

No orange appears anywhere in the signed-in bar except the hairline.

### Behaviour

- Opens on click, not hover (it is the only menu in the bar that is not a nav dropdown; hover
  menus on an avatar are easy to trigger by accident on the way to the nav).
- Closes on outside click, `Esc`, and on selecting an item. `Esc` returns focus to the chip.
- `aria-haspopup="menu"`, `aria-expanded`, and the accessible name "Account menu, <name>".
- Keyboard: `Enter`/`Space` opens and focuses the first item; up/down move; `Tab` closes and
  moves on.
- Focus ring on chip and items: 2px `#f26322` outline, `outline-offset: 2px`.
- Responsive: below `lg` the chip collapses to the avatar alone (name and caret hidden) and
  stays in the bar — it does not move into the hamburger menu. The menu then anchors to the
  avatar and keeps its 196px width.

### Signed-in colours

| Token | Value | Used for |
|---|---|---|
| Chip fill | `#23262b` | account chip background (= nav hover fill) |
| Chip hover | `#2f333a` | account chip hover |
| Menu fill | `#1c1f24` | menu panel |
| Menu border | `#2b2f36` | menu panel border |
| Menu rule / item hover | `#262a30` | header rule, item hover fill |

Contrast: chip name `#ffffff` on `#23262b` — 15.1:1. Menu items `#c9ced4` on `#1c1f24` — 8.7:1.
Handle `#9aa3ae` on `#1c1f24` — 5.4:1.

## Page title block

Kept white, as today. The dark bar ends at the top of it; the H1 sits on white.
`<h1>` — Libre Franklin 700, 40px, `letter-spacing: -.032em`, `line-height: 1.05`, colour
`#15171b`, `margin: 0`.

This is deliberate: it keeps a single dark band at the top of every page and lets tabs, chips and
filters below the title stay on white without a second colour transition. (`11b` carried the
title and tabs into the dark and was rejected for that reason.)

## Colours

| Token | Value | Used for |
|---|---|---|
| Brand orange | `#f26322` | hairline, login button |
| Orange hover | `#ff7433` | login button hover |
| Bar background | `#15171b` | dark bar, login button text |
| Nav text | `#c9ced4` | nav default, wordmark first half |
| Nav text hover | `#ffffff` | nav hover, current item, utility hover |
| Nav hover fill | `#23262b` | nav item hover background |
| Current item fill | `#2a2e34` | active nav item background |
| Utility text | `#9aa3ae` | mono utility links |
| Logo mark fill | `#33383f` | logo square (placeholder only) |
| Avatar fallback fill | `#4b3f6b` | initials avatar background |
| Avatar fallback ink | `#e3ddf5` | initials avatar letters |
| Chip fill | `#23262b` | account chip background, signed in |
| Chip hover | `#2f333a` | account chip hover |
| Menu fill | `#1c1f24` | account menu panel |
| Menu border | `#2b2f36` | account menu border |
| Menu rule | `#262a30` | menu header rule, item hover fill, rule above Logout |

## Type

- Libre Franklin — wordmark 16px/500+700, nav 14px/500 (600 current), login 13.5px/700,
  account chip name 13.5px/500, menu items 13.5px/500, menu display name 13px/600.
- Martian Mono — utility links 9px/400 uppercase `.06em`, and the account menu's `@handle`
  9.5px/400 (not uppercase).

Both families and their loading are specified in `README.md`.

## Behaviour and states

- The bar is not sticky (unchanged from today). If sticky is added later, keep the hairline
  attached to it.
- Focus: visible ring on all bar links — 2px `#f26322` outline, `outline-offset: 2px`. The
  default browser ring is not visible enough on `#15171b`.
- Responsive: below the Bootstrap `lg` breakpoint the primary nav and utility links collapse into
  the existing hamburger menu; logo, hairline, and the login button or account chip stay. Bar
  height stays 64px.

## Accessibility

- Nav text `#c9ced4` on `#15171b` is 9.6:1; utility `#9aa3ae` on `#15171b` is 5.9:1. Do not
  lighten the background or darken these text colours.
- Login button `#15171b` on `#f26322` is 6.9:1. Dark text on orange is required — white on
  `#f26322` fails.
- Mark the current nav item with `aria-current="page"` in addition to the background fill; the
  fill alone is a weak signal.

---

# Footer (12a)

## What it replaces
Today the footer is a full-bleed orange band carrying one sentence and one button, followed by a
dark strip of trademark text — around 280px of page for a single link. With the masthead reduced
to a hairline of orange, a solid orange footer becomes the loudest element on the page and works
against the header. 12a reuses the header's exact palette so the two bookend the page.

## Structure

1. **Orange hairline** — `height: 3px`, background `#f26322`. Identical to the header's; it is
   what closes the page.
2. **Footer body** — background `#15171b`, `padding: 38px 36px 30px`.
3. **Legal strip** — inside the same dark block, separated by a rule rather than a colour change
   (see below). The separate darker trademark bar is removed.

### Footer body

A flex row, `align-items: flex-start; gap: 40px`.

**Left — Slack call to action** (`max-width: 420px`):
- Heading: "Join the conversation on the Magento Association Slack" — Libre Franklin 700, 23px,
  `letter-spacing: -.024em`, `line-height: 1.25`, colour `#ffffff`, `text-wrap: pretty`.
  Copy is unchanged from the live page.
- Button: "Join our Slack" with the Slack mark. `margin-top: 18px`, `padding: 11px 19px`,
  `border-radius: 7px`, background `#f26322`, text `#15171b`, Libre Franklin 700, 14px,
  `display: inline-flex; align-items: center; gap: 9px`. Hover: background `#ff7433`.
  This is the same button as the header's "Login with GitHub" — same fill, radius, ink and hover.
  It is the only orange in the footer besides the hairline.
  The prototype substitutes a mono `#` for the Slack mark; use the real logo at ~15px.

**Right — link columns** (`margin-left: auto`, `display: flex; gap: 56px`):

Two columns, each `display: flex; flex-direction: column; gap: 9px`.

| Column | Links |
|---|---|
| Forger | Leaderboard, Issues, Pull requests, How scores work |
| Community | Magento Association, Magento Open Source, Mage-OS, Meet Magento, Magento on GitHub |

- Column heading: Martian Mono 400, 9px, uppercase, `letter-spacing: .06em`, colour `#9aa3ae` —
  the header's utility-link token, reused.
- Links: Libre Franklin 500, 13.5px, colour `#c9ced4`, no underline. Hover: `#ffffff`.

These columns are new — the current footer has no navigation. The Community column is the only
place these outbound destinations appear; the header carries no equivalent menu.

### Legal strip

`margin-top: 34px`, `padding-top: 18px`, `border-top: 1px solid #292d33`.

Trademark paragraph, verbatim and unchanged: Martian Mono 400, 9.5px, `line-height: 1.85`,
colour `#8a919b`, `max-width: 720px`, `text-wrap: pretty`, left-aligned (it is centred today).

Mono at this size is deliberate — it marks the text as boilerplate without shrinking it below a
legible size, and matches the register of the header's utility links.

## Footer colours

All tokens are the header's. No new values.

| Token | Value | Used for |
|---|---|---|
| Brand orange | `#f26322` | hairline, Slack button |
| Orange hover | `#ff7433` | Slack button hover |
| Bar background | `#15171b` | footer body, Slack button text |
| Nav text | `#c9ced4` | footer links |
| Nav text hover | `#ffffff` | link hover, heading |
| Utility text | `#9aa3ae` | column headings |
| Legal text | `#8a919b` | trademark paragraph |
| Divider | `#292d33` | rule above the legal strip |

## Footer accessibility

- Links `#c9ced4` on `#15171b` — 9.6:1. Column headings `#9aa3ae` — 5.9:1. Trademark `#8a919b` —
  5.5:1. All pass at their sizes; do not darken any of them.
- Slack button `#15171b` on `#f26322` — 6.9:1. Dark ink on orange is required.
- Same focus treatment as the header: 2px `#f26322` outline, `outline-offset: 2px`.
- Mark the footer `<footer role="contentinfo">`; give each link column a heading element rather
  than a styled `<span>`, so the columns are navigable.

## Footer responsive

Drawn at 420px at the foot of `#24a`.

Below the Bootstrap `lg` breakpoint the row stacks: CTA block first, then the two link columns
side by side, then the legal strip. Padding drops to `28px 20px 24px`. The heading may drop to
20px; nothing else changes.

The two columns stay side by side at every width — they carry four and five short links, so
collapsing them to one column doubles the footer's height and gains nothing. `gap` drops from
56px to 24px and each column becomes `flex: 1`.

## Out of scope
Primary-nav dropdown menu interiors (Issues ▾, PRs ▾) are unchanged.
