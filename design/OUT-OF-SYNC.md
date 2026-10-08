# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-08**,
after the homepage Momentum charts (28a) were added to the spec and prototype. Only current
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

- **Homepage Momentum charts** (`welcome.blade.php`, `charts/github-stats.blade.php`,
  `charts/_bar-chart.blade.php`, `.chart-card` in `_chrome-styles.blade.php`) — live draws monthly
  bars in a 320px plot with rotated `yyyy-MM` ticks and a bottom legend. Spec (`README-homepage.md`,
  "Momentum"): quarterly bars, 150px plot, totals row as the legend, even-year axis.
  Pages: `/`.

## Unsure

None at the moment.

## Kept on purpose

None at the moment.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
