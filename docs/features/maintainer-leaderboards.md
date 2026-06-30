# Maintainer Leaderboards

## Purpose

Rank the top 100 Contributors by code-review activity (approvals or change requests) within a Calendar Period. Separate from Contributor Leaderboards — these surface review work, not authorship.

## How it works

`MaintainerLeaderboardController` mirrors `LeaderboardController` in structure. Metrics map to query classes that hit the `github-pr-reviews` index.

### Metrics

| Slug | Label | Index | Filter |
|------|-------|-------|--------|
| `reviews-approved` | Reviews Approved | `github-pr-reviews` | `state = APPROVED` |
| `reviews-rejected` | Reviews Rejected | `github-pr-reviews` | `state = CHANGES_REQUESTED` |

Default metric (redirect from `/maintainer/leaderboard`): `reviews-approved`.

### Data source

The `github-pr-reviews` index is populated as a side effect of PR syncing — `OpenSearchService::indexPullRequestReviews()` is called inside `indexPullRequests()`. Each review is one document, keyed by the GitHub review node ID (`id` field from GraphQL). Upsert semantics: re-syncing the same PRs overwrites existing review docs safely.

Review document schema:

```json
{
  "pr_number": 12345,
  "author": "github-login",
  "state": "APPROVED",
  "submitted_at": "2026-01-15T10:00:00Z"
}
```

### Bot exclusion

Same bot list as Contributor Leaderboards: `engcom-*`, `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`.

### Calendar Period

Same resolution logic as Contributor Leaderboards — `period`, `from`, `to` query params.

## Key files

- `app/Http/Controllers/MaintainerLeaderboardController.php` — Route handler
- `app/Queries/Dashboard/ReviewsApprovedLeaderboardQuery.php`
- `app/Queries/Dashboard/ReviewsRejectedLeaderboardQuery.php`
- `app/Queries/Dashboard/BaseReviewLeaderboardQuery.php` — Shared bot filters and response parsing
- `app/Services/Search/OpenSearchService.php` — `indexPullRequestReviews()` (write path)
- `resources/views/leaderboard/maintainer.blade.php`

## Configuration

None beyond the shared OpenSearch config.

## Gotchas / constraints

- Review data only exists for PRs that have been synced. A PR with no review activity in the synced period returns zero rows — not an error.
- Reviews with an empty `id` field are skipped during indexing (guard in `indexPullRequestReviews()`).
- This doc covers the **raw-count** review boards only. The weighted **Maintainer Score** (review/claim/triage scoring, latency stats) is a separate system — see [Weighted Scoring](leaderboard-scoring.md).
