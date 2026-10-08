# GitHub Sync

## Purpose

Pull GitHub issues and pull requests into OpenSearch via GraphQL so all analytics queries run against a local index instead of hitting the GitHub API at read time.

## How it works

Two Artisan commands — `sync:github:issues` and `sync:github:prs` — share a single `GitHubSyncer` engine that drives paginated GraphQL queries. Each run:

1. Fetches a count of total items (for progress display only).
2. Pages through results via cursor pagination — issues 100 nodes/page, PRs 10 nodes/page (PRs carry heavier timeline sub-queries, so a smaller page keeps node cost down).
3. Passes each page to `OpenSearchService::indexIssues()` / `indexPullRequests()` for bulk upsert.
4. Stops early when the last node's `updatedAt` is older than the `--since` cutoff (incremental mode).

`GitHubSyncer` is callback-based: callers inject `fetchPage`, `index`, `onPage`, `onNode`, and `onError` closures. Errors per page are logged and skipped — the sync continues rather than aborting.

Both commands implement `Isolatable` so Laravel prevents concurrent runs of the same command.

### Schedule

| Job | Trigger | Notes |
|-----|---------|-------|
| Full + incremental sync (issues) | Weekly full (00:00) + every 15 min | Full has no cutoff; incremental `--since "1 hour ago"` |
| Full + incremental sync (PRs) | Weekly full (01:00) + every 15 min | `SyncGitHubPRs` |
| Full + incremental sync (interactions) | Weekly full (02:00) + every 15 min | `SyncGitHubInteractions` |
| Full + incremental sync (events) | Weekly full (03:00) + every 15 min | `SyncGitHubEvents` |
| Teams roster | Daily (10:00) | `SyncGitHubTeams` |
| `leaderboard:compute` | Every 15 min + weekly full | `ComputeLeaderboardScores` (see [Leaderboard Rollout](leaderboard-rollout.md)) |

Each incremental pauses in a ±20-min window around *its own* full-sync time (issues 23:40–00:20, PRs 00:40–01:20, interactions 01:40–02:20, events 02:40–03:20) via `skip($duringWeeklyFullSync(...))`. All run in the background with `withoutOverlapping()`.

## Key files

- `app/Console/Commands/SyncGitHubIssues.php` — Issues sync command
- `app/Console/Commands/SyncGitHubPRs.php` — PRs sync command
- `app/Console/Commands/SyncGitHubInteractions.php` — Comments/reviews sync command
- `app/Console/Commands/SyncGitHubEvents.php` — Issue timeline events sync command
- `app/Console/Commands/SyncGitHubTeams.php` — Maintainer/council roster sync command
- `app/Console/Commands/SyncGitHubProfiles.php` — Display name + avatar sync command
- `app/Services/GitHub/GitHubSyncer.php` — Pagination engine
- `app/Services/GitHub/GitHubIssueService.php` — GraphQL fetcher for issues
- `app/Services/GitHub/GitHubPullRequestService.php` — GraphQL fetcher for PRs
- `app/Services/GitHub/GitHubInteractionService.php` — GraphQL + REST fetcher for comments/reviews/events (`fetchEventsForIssue()` uses REST; the rest use GraphQL)
- `app/Services/GitHub/GitHubConnection.php` — GitHub API client + auth (every command above goes through this)
- `resources/graphql/github/` — Raw GraphQL query files
- `routes/console.php` — Schedule definitions
- `config/github.php` — `repo` (owner/name), API token

## Configuration

Every `sync:github:*` command authenticates through `GitHubConnection`, which reads these two keys — no command-specific credentials exist below this level.

| Key | Description | Required scope |
|-----|-------------|-----------------|
| `github.repo` / `GITHUB_REPO` (env) | Target repository in `owner/name` format | — |
| `GITHUB_TOKEN` (env) | Classic personal access token used by GraphQL + REST clients | `public_repo`, `read:org`. `read:org` also covers `sync:github:teams`' team-roster reads — a token missing it 404s on that command only (see `SyncGitHubTeams::describe()`), the rest are unaffected. |

**Not used by any `sync:github:*` command:** `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI` — those are OAuth login credentials, unrelated to data sync. See `docs/features/authentication.md`.

In production, `GITHUB_TOKEN` is populated from the `GH_SYNC_PAT_CLASSIC` GitHub Actions secret at deploy time (`.github/workflows/deploy.yml`) — the secret name and the env var name differ.

## Gotchas / constraints

- `--since` accepts any string Carbon can parse (e.g. `"2 days ago"`, `"2026-01-01"`). Both commands use the same `parseCutoff()` on the `SyncsWithGitHub` trait, which wraps `Carbon::parse` in try/catch and throws `InvalidSyncCutoffException` on bad input — identical behaviour across commands.
- Cutoff is checked against `updatedAt` of the *last node on each page*, not per-node. If a page ends on a node just inside the cutoff, the next page still runs.
- PR indexing (`indexPullRequests()`) also triggers three side effects: review indexing (`github-pr-reviews`), timeline indexing (`github-pr-timeline`, via `indexPullRequestTimeline()`), and flagging issues closed by merged PRs (`closed_by_merged_pr` field).
- Re-running a full sync is safe — issues are upserted by `number`; PRs are indexed by `number` (full replace).
