# Replace points-based company leaderboard with count-based contributor leaderboards

The points system assigned opaque numerical weights to GitHub timeline events and aggregated them by company. We're replacing it with per-metric Contributor Leaderboards that show raw counts per individual contributor, making the data transparent and directly useful.

## What changes

**Remove** the entire points pipeline: `SyncGitHubInteractions`, `SyncGitHubEvents`, `InteractionPointsProcessor`, `ProcessGitHubInteractions`, the `interactions` and `points` OpenSearch indexes, `LeaderboardByMonthQuery`, `LeaderboardByYearQuery`, and the company leaderboard UI. Clean up any GraphQL queries that only served these sync commands.

**Add** five Contributor Leaderboards, each a separate query class in `app/Queries/Dashboard/` per ADR 0001:

| Leaderboard | Source index | Date field filtered |
|---|---|---|
| Issues opened | `github-issues` | `created_at` |
| Issues closed | `github-issues` | `closed_at` |
| PRs opened | `github-pull-requests` | `created_at` |
| PRs merged | `github-pull-requests` | `merged_at` |

Each leaderboard returns top 100 contributors sorted by count descending. Bots (`engcom-*`, `dependabot`, `github-actions[bot]`) are excluded at query time. Company attribution is shown where `User.github_username` matches the GitHub login; otherwise left blank.

Date filtering uses Calendar Periods (last month, last quarter, last year, custom). Each metric filters on its own event date — "PRs merged last month" means `merged_at` is within that month, not `created_at`.

## Deferred: Maintainer Leaderboards

Two review-based leaderboards (PRs approved, PRs rejected) are deferred. They require expanding `github_pull_requests.graphql` to fetch `reviews` nodes and re-syncing. These will be implemented as a separate Maintainer Leaderboards phase.

## Considered options

**Keep points system, add contributor view on top** — rejected. The points system is a maintenance burden and the weights are arbitrary. Running two pipelines (points + raw counts) in parallel serves nobody.

**One combined leaderboard table with all metrics as columns** — rejected. Different metrics have different natural audiences. A single table with 8 columns forces users to mentally filter; separate leaderboards let each stand on its own.

**Show only registered Users, not all Contributors** — rejected. Excluding non-registered GitHub contributors misrepresents actual project activity. Company attribution can be blank without hiding the contribution.
