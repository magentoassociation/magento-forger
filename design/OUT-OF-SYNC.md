# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-16**,
against `main` and `feature/community-picked`. Only current
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

- **Merge `feature/community-picked`.** It carries the Community-Picked PRs page
  (`README-community-picked.md`, `#27b` `#27c` `#27d`) and the SVG dropdown chevron
  (*Dropdown caret*, `README.md`). Both match the spec on the branch except the pills and search field: `.cpr-comp` should be Libre
  Franklin 11.5px (not Martian Mono 10px) on `#fbddcb` (not `#f7f5f2`); `.cpr-area` should be filled
  `#e6e7ea` with no border, `padding: 4px 8px`, `#15171b` text (not outlined `#d5d8dc`, `#3c4148`). The search field drops its ⌕ icon
  (`.lb-search-ico`) and its left padding goes to 12px.
  Placeholder becomes "PR title or #" (was "Title words or PR #"). `main` still has neither.

- **Nav dropdowns open on hover** (`README-header.md`, *Nav dropdowns*). Issues ▾ and PRs ▾
  open on click today. They render from `app/Menus` via `$mainMenu` in `layouts/app.blade.php`.
  Menu interiors (`#29a`): the current item's solid orange `.active` fill stays put instead of
  the highlight following the pointer, and a click leaves the orange focus ring on the parent.
  Restyle per *Menu interiors*. Nav items sit `gap: 8px` apart (was 2px); filled neighbours
  touched.

- **"Community-Picked" capitalisation** (`README.md`, *Capitalisation*). Page H1 becomes
  "Community-Picked PRs" (`header.blade.php` `$formattedLabel`);
  the PRs menu item becomes "Community-Picked" (was "Community Picked", `app/Menus`).

- **Remove the footer link to Community-Picked PRs** (`footer.blade.php`). The Forger column
  lists one link per section (`README-header.md`, footer); the page sits inside Pull Requests.

- **Homepage: Community-Picked PRs section** (`README-homepage.md`, `#15a`, `#24d`). New, between
  the first-timer block and Momentum: top three PRs by votes, "See all and vote" button. Not built.

## Unsure

None at the moment.

## Kept on purpose

None at the moment.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
