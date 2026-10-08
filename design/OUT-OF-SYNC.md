# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-08**,
after Momentum was reworked: last 12 months on the homepage, full history on the By Month pages. Only current
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

- **Homepage Momentum** (`welcome.blade.php`, `charts/momentum-card.blade.php`,
  `charts/github-stats.blade.php`) — live charts every quarter since Dec 2014 with all-time
  totals. Spec (`README-homepage.md`, "Momentum"): last 12 months, monthly bars with month
  labels, the hero CTA beside the heading, and a footer link to the By Month page. Pages: `/`.
- **By Month all-time chart** (`IssuesByMonthController`, `PrsByMonthController`, their views) —
  not built. Spec (`README-issues-prs-by-month.md`, "4. Opened and closed, all time"): the
  quarterly chart that was on the homepage, plus the opened/closed aggregation it needs.
  Pages: `/issuesByMonth`, `/prsByMonth`.

## Unsure

None at the moment.

## Kept on purpose

- **All-time chart height** (`.chart-card-canvas--momentum` in `_chrome-styles.blade.php`,
  `layout.padding.top`) — the canvas is 186px: the spec's 150px plot and 22px year axis, plus
  14px of top headroom so Chart.js doesn't clip the top y label. Carries over when the chart moves
  to the By Month pages.
- **All-time year labels** — each label starts at the centre of its Q1 bar pair, about 2px right
  of the tick, instead of left-aligned with it. Chart.js anchors category labels at the bar
  centre. Carries over with the chart.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
