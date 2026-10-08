# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-08**,
after the homepage Momentum charts (28a) were built to the spec. Only current
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

None at the moment.

## Unsure

None at the moment.

## Kept on purpose

- **Momentum chart height** (`.chart-card-canvas--momentum` in `_chrome-styles.blade.php`,
  `layout.padding.top` in `charts/github-stats.blade.php`) — the canvas is 186px: the spec's 150px
  plot and 22px year axis, plus 14px of top headroom. Without it Chart.js clips the top y label,
  which the spec draws above its gridline. Pages: `/`.
- **Momentum year labels** (`charts/github-stats.blade.php`) — each label starts at the centre of
  its Q1 bar pair, about 2px right of the 1×5px tick at the pair's left edge, instead of
  left-aligned with the tick. Chart.js anchors category labels at the bar centre. Pages: `/`.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
