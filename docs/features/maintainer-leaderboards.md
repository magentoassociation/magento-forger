# Maintainer Leaderboards

> **⚠️ Board UI not implemented as described / superseded.** The raw-count review board (`MaintainerLeaderboardController`, `/maintainer/leaderboard`, `reviews-approved`/`reviews-rejected` metric slugs, `ReviewsApprovedLeaderboardQuery`/`ReviewsRejectedLeaderboardQuery`/`BaseReviewLeaderboardQuery`, `maintainer.blade.php`) **does not exist.** The live maintainer board is the weighted-score system in `ScoreLeaderboardController` — see [Weighted Scoring](leaderboard-scoring.md). The **Data source** section below is accurate and current.

## What actually ships

The maintainer board is served by `ScoreLeaderboardController::show()` → `maintainerRows()` at `GET leaderboard/maintainer`, reading precomputed `leaderboard_entries` and rendering `resources/views/leaderboard/score.blade.php`. Weighted review/claim/triage scoring and latency stats are documented in [Weighted Scoring](leaderboard-scoring.md).

## Data source (`github-pr-reviews` index)

The `github-pr-reviews` index is populated as a side effect of PR syncing — `OpenSearchService::indexPullRequestReviews()` (a `protected` method) is called inside `indexPullRequests()`. Each review is one document, keyed by the GitHub review node ID (`id` field from GraphQL). Upsert semantics: re-syncing the same PRs overwrites existing review docs safely.

Review document schema:

```json
{
  "pr_number": 12345,
  "author": "github-login",
  "state": "APPROVED",
  "submitted_at": "2026-01-15T10:00:00Z"
}
```

## Key files

- `app/Http/Controllers/ScoreLeaderboardController.php` — serves the live weighted board
- `app/Services/Search/OpenSearchService.php` — `indexPullRequestReviews()` (write path)
- See [Weighted Scoring](leaderboard-scoring.md) for the scoring services (`ScoredEventReader`, `LeaderboardScorer`, `ReviewLatencyAnalyzer`, …)

## Gotchas / constraints

- Review data only exists for PRs that have been synced. A PR with no review activity returns zero rows — not an error.
- Reviews with an empty `id` field are skipped during indexing (guard in `indexPullRequestReviews()`).
