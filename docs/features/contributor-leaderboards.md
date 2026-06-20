# Contributor Leaderboards

## Purpose

Rank the top 100 Contributors by a single contribution metric within a Calendar Period. Each metric is a separate view; there is no combined score.

## How it works

`LeaderboardController` maps URL metric slugs to query classes. On `GET /leaderboard/{metric}`, it resolves the Calendar Period from query params, instantiates the appropriate query class, and passes results to `leaderboard.contributor` view.

### Metrics

| Slug | Label | Index | Date field |
|------|-------|-------|-----------|
| `prs-merged` | PRs Merged | `github-pull-requests` | `merged_at` |
| `prs-opened` | PRs Opened | `github-pull-requests` | `created_at` |
| `issues-opened` | Issues Opened | `github-issues` | `created_at` |
| `issues-closed` | Issues Closed | `github-issues` | `closed_at` |

Default metric (redirect from `/leaderboard`): `prs-merged`.

### Calendar Period resolution

| `period` param | Range |
|---------------|-------|
| `last-month` (default) | First–last day of previous month |
| `last-quarter` | Q1–Q4 of current year (previous quarter) |
| `last-year` | Jan 1–Dec 31 of previous year |
| `custom` | `from` + `to` query params (ISO dates) |

Invalid custom dates fall back to `last-month` silently.

### Bot exclusion

All leaderboard queries exclude the following authors at query time via `must_not`:

- `engcom-*` (wildcard)
- `dependabot[bot]`
- `github-actions[bot]`
- `m2-assistant`

### GitHub search links

Each row in the leaderboard links to a GitHub search URL scoped to that contributor and the selected date range. The URL pattern varies by metric (e.g. `is:pr+is:merged+author:{login}+merged:{range}`).

## Key files

- `app/Http/Controllers/LeaderboardController.php` — Route handler, period resolution, GitHub URL builder
- `app/Queries/Dashboard/PRsMergedLeaderboardQuery.php`
- `app/Queries/Dashboard/PRsOpenedLeaderboardQuery.php`
- `app/Queries/Dashboard/IssuesOpenedLeaderboardQuery.php`
- `app/Queries/Dashboard/IssuesClosedLeaderboardQuery.php`
- `app/DataTransferObjects/Dashboard/ContributorCount.php` — `{login, count}` DTO
- `resources/views/leaderboard/contributor.blade.php`

## Configuration

- `github.repo` — Used to build GitHub search URLs per row.

## Gotchas / constraints

- Leaderboard queries use the injected `OpenSearch\Client` directly (not `OpenSearchService`), so index prefix must be applied manually via `OpenSearchService::getIndexWithPrefix()`.
- Top 100 is hard-coded via `'size' => 100` in the `terms` aggregation. Expanding this requires changing each query class.
- `issues-closed` filters on `closed_at` with no state filter — it counts all closed issues, not just completed ones. The GitHub URL uses `reason:completed` which is narrower.
- Do not add a combined "total score" metric. See `CONTEXT.md` — that was the old points system, removed by design.
