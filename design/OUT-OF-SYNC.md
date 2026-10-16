# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-16**,
against `main`, after the monthly scoring-modal routing was added to the spec. Only current
differences are listed; anything fixed is deleted, not kept as history.

## How to read this

| Heading | Meaning |
|---|---|
| **To do** | Code doesn't match the spec and should, eventually. |
| **Unsure** | Code may not match the spec; needs a decision before code changes. |
| **Kept on purpose** | Code differs from the spec deliberately. Decided; no action. |
| **Not covered** | Parts of the site no spec describes. Not drift. |

`README.md` still has an "earlier generation" board section with older sizes. The current type
scale in the same file and `README-leaderboard-pages.md` win; differences from the old section
are not listed.

---

## To do

- **Community-picked PRs** (`feature/community-picked`, not yet on `main`): built before the
  spec. Restyle to `README-community-picked.md` (`#27b`, `#27c`): title block, no second heading,
  filters apply on change with no Filter button, outlined Area / filled Component pills without
  prefixes, two-line rows, author links to the contributor detail page, "Show 50 more" in place
  of numbered pages.
- **Dropdown carets**: every `▾` glyph (nav items, account chip, selects) becomes the 7×5px SVG
  chevron in *Dropdown caret*, `README.md`.

## Unsure

None at the moment.

## Kept on purpose

None at the moment.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
