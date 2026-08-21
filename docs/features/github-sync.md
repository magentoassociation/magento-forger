# GitHub Sync

## Purpose

Pull GitHub issues and pull requests into OpenSearch via GraphQL so all analytics queries run against a local index instead of hitting the GitHub API at read time.

## How it works

Two Artisan commands — `sync:github:issues` and `sync:github:prs` — share a single `GitHubSyncer` engine that drives paginated GraphQL queries. Each run:

1. Fetches a count of total items (for progress display only).
2. Pages through results 100 nodes at a time via cursor pagination.
3. Passes each page to `OpenSearchService::indexIssues()` / `indexPullRequests()` for bulk upsert.
4. Stops early when the last node's `updatedAt` is older than the `--since` cutoff (incremental mode).

`GitHubSyncer` is callback-based: callers inject `fetchPage`, `index`, `onPage`, `onNode`, and `onError` closures. Errors per page are logged and skipped — the sync continues rather than aborting.

Both commands implement `Isolatable` so Laravel prevents concurrent runs of the same command.

### Schedule

| Job | Trigger | Notes |
|-----|---------|-------|
| Full sync (issues) | Weekly | No cutoff — syncs entire history |
| Incremental sync (issues) | Every 15 min | `--since "1 hour ago"` |
| Full sync (PRs) | Weekly | No cutoff |
| Incremental sync (PRs) | Every 15 min | `--since "1 hour ago"` |

Incremental syncs pause between 23:40–00:20 to avoid overlapping with the weekly full sync. Both run in the background with `withoutOverlapping()`.

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

In production, `GITHUB_TOKEN` is populated from the `GH_SYNC_FGPAT` GitHub Actions secret at deploy time (`.github/workflows/deploy.yml`) — the secret name and the env var name differ.

## Gotchas / constraints

- `--since` accepts any string Carbon can parse (e.g. `"2 days ago"`, `"2026-01-01"`). Issues command uses `Carbon::parse`; PRs command uses `Carbon::parse` — behaviour on invalid input differs slightly.
- Cutoff is checked against `updatedAt` of the *last node on each page*, not per-node. If a page ends on a node just inside the cutoff, the next page still runs.
- PR indexing also triggers two side effects: review indexing (`github-pr-reviews` index) and flagging issues closed by merged PRs (`closed_by_merged_pr` field).
- Re-running a full sync is safe — issues are upserted by `number`; PRs are indexed by `number` (full replace).
