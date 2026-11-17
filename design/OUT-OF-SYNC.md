# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-11-17**
against `main`, by reading the code: CSS values, markup, routes, menus and tests. Compared to the live
site numerically (computed sizes, weights, padding) on 2026-11-17 for the contributor, maintainer and monthly boards, Highlights, by-month, how-scores, the scoring modal, the homepage, the header and Community-Picked PRs. All values checked match the spec. The issue picker (`/issues/pick`, built 2026-11-17) was checked
in code and tests only, not on the live site. Only current differences are listed; anything fixed is deleted, not
kept as history.

## How to read this

| Heading | Meaning |
|---|---|
| **To do** | Code doesn't match the spec and should, eventually. |
| **Unsure** | Code may not match the spec; needs a decision before code changes, or a visual check. |
| **Kept on purpose** | Code differs from the spec deliberately. Decided; no action. |
| **Not covered** | Parts of the site no spec describes. Not drift. |

`README.md` still has an "earlier generation" board section with older sizes. The current type
scale in the same file and `README-leaderboard-pages.md` win; differences from the old section
are not listed.

---

## To do

None found by code inspection on 2026-11-17. Items from the 2026-10-16 list that are now in code:
the Community-Picked feature (page, footer, menu, homepage band), the nav dropdown hover and
spacing, the dropdown caret, the Community-Picked pill and search styling, the "Community-Picked"
capitalisation, the homepage section layout (220px heading column, 19px headings), the board
control strip, search, jump to my rank and pagination, and self-hosted fonts.

## Unsure

- **Header dropdown hover and the focus ring** were checked in code, not by eye.
- **`feature/community-picked`.** `main` already contains its work (controller, view, footer
  and menu changes). Confirm the branch has nothing left to merge, then delete it.
- **Issue picker, by eye** (`README-issue-picker.md`). The spec was updated on 2026-11-17 to match
  the code, but nothing has been compared on the live site yet: the 420px layout (`#31c`), the
  stretched row link and its focus ring, the column widths, and the 28px H1 (inherited from the
  board styles at 560px and below).
- **`README.md` index still marks the issue picker "(proposed)".** It is built and in the Issues menu.

---

## Kept on purpose

- **Community-Picked full view body padding below 700px** (`README-community-picked.md`, *Full
  view*). Spec keeps the body at `24px 32px 32px`; code uses 20px so the screenshot lines up with
  the stacked header's 20px padding.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Issues menu contents.** `README-header.md`, *Out of scope*, names the PRs menu items but not
  the Issues menu's. It now holds "By Month" and "Pick an Issue", in route order.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
