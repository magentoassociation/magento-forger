# Add Maintainer Leaderboards for review-based metrics

Contributor Leaderboards cover authorship signals (PRs merged, issues opened, etc.). Review-based signals — approving and rejecting PRs — require a separate data source and a separate UI section targeting a different audience: maintainers, not contributors.

## What changes

**Expand the PR sync** to fetch `reviews` nodes from GitHub GraphQL alongside existing PR fields. Each PR query page now includes:

```graphql
reviews(first: 50) {
  nodes {
    id
    author { login }
    state
    submittedAt
  }
}
```

**Add `github-pr-reviews` index** — one document per review submission, upserted by GitHub review node ID:

| Field | Value |
|---|---|
| `_id` | GitHub review node ID |
| `pr_number` | PR number |
| `author` | reviewer login |
| `state` | `APPROVED`, `CHANGES_REQUESTED`, `COMMENTED`, or `DISMISSED` |
| `submitted_at` | ISO 8601 timestamp |

**Add two Maintainer Leaderboard query classes** in `app/Queries/Dashboard/`:

| Leaderboard | State filter | Date field |
|---|---|---|
| Reviews approved | `state: APPROVED` | `submitted_at` |
| Reviews rejected | `state: CHANGES_REQUESTED` | `submitted_at` |

Bot exclusion (`engcom-*`, `dependabot[bot]`, `github-actions[bot]`) applied at query time, same as Contributor Leaderboards.

**Add `MaintainerLeaderboardController`** with routes `/maintainer/leaderboard` (redirects to `reviews-approved`) and `/maintainer/leaderboard/{metric}`.

**Add two nav links** to `layouts.app`: "Contributor Leaderboard" and "Maintainer Leaderboard" as peer entries.

## Considered options

**Embed reviews in `github-pull-requests`** — rejected. Nested aggregations in OpenSearch are significantly more complex than flat terms-agg. A flat index per review keeps query classes consistent with the existing leaderboard pattern.

**Filter to only `APPROVED` and `CHANGES_REQUESTED` at sync time** — rejected. Storing all states costs little and avoids a re-sync if new review-based leaderboards are added later. Leaderboard queries filter by state at query time.

**Merge into existing Contributor Leaderboard section** — rejected. Review metrics target a different audience (maintainers) and sourced from a different index. Separate section makes the distinction visible in the UI and keeps the two controllers independent.

**Per-PR review sync command** — rejected. Fetching reviews per PR individually would require thousands of API calls for large repos. Embedding reviews in the existing paginated PR query is fast and reuses the incremental `updatedAt` sync that already captures PRs with recent activity.
