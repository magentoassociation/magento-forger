# Contributor Leaderboards

> **⚠️ Not implemented as described / superseded.** This doc described a raw-count, one-metric-per-view board (`LeaderboardController`, `/leaderboard/{metric}`, per-metric `*LeaderboardQuery` classes). **None of that exists in the codebase.** The live contributor board is the weighted-score system.

## What actually ships

The contributor board is served by `App\Http\Controllers\ScoreLeaderboardController`:

- Route: `GET leaderboard/{board}` where `board ∈ {contributor, maintainer, company}` (`routes/web.php`).
- `/leaderboard` redirects to the `contributor` board.
- Rows come from the precomputed `leaderboard_entries` table (`->limit(100)` on an Eloquent query), **not** an OpenSearch `terms` aggregation.
- Window is `rolling12` (or a monthly `YYYY-MM` window); there is no `period`/`from`/`to` Calendar-Period query-param system.
- Per-user drill-down: `leaderboard/{board}/user/{login}`.
- View: `resources/views/leaderboard/score.blade.php`.

The scoring math, weights, decay, and compute job are documented in **[Weighted Scoring](leaderboard-scoring.md)**; rollout status in **[Leaderboard Rollout](leaderboard-rollout.md)**.

## Not built

- Raw-count per-metric boards (`prs-merged`, `prs-opened`, `issues-opened`, `issues-closed`) — never implemented.
- `LeaderboardController`, the four `*LeaderboardQuery` classes, `resources/views/leaderboard/contributor.blade.php` — do not exist.
- `app/DataTransferObjects/Dashboard/ContributorCount.php` exists but is unreferenced dead code.
- Per-row GitHub search-URL builder — not implemented.

## Bot exclusion (current)

The live bot list lives in `config/leaderboard.php` (`bots.exact` + `bots.prefixes`), consumed via `App\Support\BotFilter`: exact = `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`, `magento-github-admin-beta`; prefixes = `engcom-`, `magento-automated`, `m2-community`, `github-`, `ct-prd-projects`, `copilot-pull-request-reviewer`.
