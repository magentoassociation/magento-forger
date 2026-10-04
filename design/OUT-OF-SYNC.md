# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-04**,
against the second spec edit of the same day (header wordmark and menu height, My contributions
gating, path-segment month links, search padding, detail group-header widths, monthly detail
page, row tie-breaks, singular item count, by-month GitHub links, zero-point maintainers,
Inactive tag). Only current differences are listed; anything fixed is deleted, not kept as history.

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

### Who sees zero-point maintainers (`User::canViewFullMaintainerBoard`)

- **Pages:** Maintainer board.
- **Spec says:** only signed-in admins see maintainers with 0.0 points; everyone else sees only
  maintainers who scored (`README-leaderboard-pages.md`, "Zero-point maintainers").
- **Code does:** admins, active maintainers and active community council members all see them.
  Tests pin this (`testMaintainerViewerSeesIdleMaintainers`, `testCouncilViewerSeesIdleMaintainers`).
- **Decide:** narrow the code to admins, or widen the spec to maintainers and council.

## Kept on purpose

None at the moment.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
- **Monthly scoring popup** — the monthly board has no score decay, so the code shows a
  no-decay version of the scoring popup. The spec only describes the 12-month version.
  Pages: Monthly board, monthly detail page.
