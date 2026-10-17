# Out of sync — spec vs code

Where the live site differs from the design specs in `design/specs/`. Last checked **2026-10-17**,
against `feature/community-picked`. Only current
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

- **Leaderboard is a nav dropdown** (`README-header.md`, *Nav dropdowns*). The spec has only
  Issues ▾ and PRs ▾, and a click on a parent toggles its menu. Leaderboard ▾ holds Contributor,
  Maintainer, Monthly and Highlights (`app/Menus/MainMenu.php`). On hover devices, clicking it
  goes to the main board (`/leaderboard`) instead of toggling, since hover already opened the
  menu. Touch devices still toggle; Contributor is the main board.

- **Claim wording** (`README-scoring-modal.md` 19a, `README-detail-page.md`,
  `README-leaderboard-pages.md`). The specs say "Claimed a stale pending-review PR" / "Stale PR(s)
  claimed" / "stale PRs claimed". Code says "Reviewed a PR" / "PR(s) reviewed" / "PRs reviewed"
  (`Action.php`, `ScoreLeaderboardController.php`). Scoring has no staleness check: any PR a
  maintainer self-assigns and then reviews earns the bonus.

- **Comment-review wording** (`README-scoring-modal.md` 19a, `README-detail-page.md`,
  `README-leaderboard-pages.md`). The specs say "Commented on a review" / "Review comment(s)" /
  "review comments". Code says "Commented on a PR" / "PR comment(s)" / "PR comments"
  (`Action.php`, `ScoreLeaderboardController.php`).

- **Label wording** (`README-scoring-modal.md` 19a, `README-detail-page.md`,
  `README-leaderboard-pages.md`). The specs say "Applied a triage label" / "Triage label(s)
  applied" / "triage labels applied". Code drops "triage": "Applied a label" / "Label(s) applied"
  / "labels applied" (`Action.php`, `ScoreLeaderboardController.php`).

- **Label base points** (`README-scoring-modal.md` 19a). Spec lists "Applied a triage label" at
  1. Config (`config/leaderboard.php`) has 0.5; the modal reads config, so it shows 0.5.

## Not covered

- **Company board** (`score-company.blade.php`) — still old styling. Hidden from the menu; not
  one of the three redesigned boards. Pages: `/leaderboard/company`.
- **Universe bar** (`universe-bar.blade.php`) — its own grey colour scheme. It's an embed for
  other sites (`/api/universe-bar`), outside the redesign. Pages: none on this site.
