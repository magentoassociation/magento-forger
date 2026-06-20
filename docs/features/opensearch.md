# OpenSearch

## Purpose

Serve as the primary data store for all GitHub analytics. Issues, pull requests, and PR reviews are indexed here; all leaderboard, chart, and analytics queries hit OpenSearch rather than the GitHub API.

## How it works

`OpenSearchService` wraps the OpenSearch PHP client and owns all index I/O. `QueryBuilder` constructs DSL queries in a fluent style and is consumed by every query class under `app/Queries/`.

### Indices

| Index name | Document ID | Content |
|-----------|------------|---------|
| `github-issues` | Issue number | Issue metadata, labels, state, author, `closed_by_merged_pr` flag |
| `github-pull-requests` | PR number | PR metadata, labels, state, review counts, `is_draft` |
| `github-pr-reviews` | GitHub review node ID | One doc per review: `pr_number`, `author`, `state`, `submitted_at` |

All index names are prefixed with `opensearch.index_prefix` from config (default empty). Use `OpenSearchService::getIndexWithPrefix()` whenever referencing an index name.

### Write path

- Issues: `indexIssues()` — bulk **upsert** (`update` + `doc_as_upsert`). Safe to reindex without data loss.
- PRs: `indexPullRequests()` — bulk **replace** (`index`). Also calls `indexPullRequestReviews()` and `flagIssuesClosedByMergedPRs()` as side effects.
- Reviews: `indexPullRequestReviews()` — bulk **replace** by GitHub review node ID.

### Read path

`QueryBuilder` produces `bool` queries with `must` clauses. Fields listed in `keywordFields` (`state`, `author`, `status`) automatically get `.keyword` appended when used in `TERM` filters.

```php
$builder = (new QueryBuilder)
    ->addFilter(new Filter('state', FilterType::TERM, 'MERGED'))
    ->addAggregation(new Aggregation('by_author', [...]))
    ->setSize(0);

$openSearch->searchPRs($builder);
```

## Key files

- `app/Services/Search/OpenSearchService.php` — All index read/write methods
- `app/Services/Search/QueryBuilder.php` — Fluent DSL builder
- `app/Providers/OpenSearchServiceProvider.php` — Binds `OpenSearch\Client` into the container
- `app/Console/Commands/ClearOpenSearchIndex.php` — Clears an index (dev/reset utility)
- `config/opensearch.php` — Host, port, index prefix
- `app/DataTransferObjects/Search/` — DTOs: `Filter`, `FilterType`, `Aggregation`, `QueryConfig`, `TimeRange`

## Configuration

| Key | Description |
|-----|-------------|
| `opensearch.host` | OpenSearch host |
| `opensearch.port` | OpenSearch port (default 9200) |
| `opensearch.index_prefix` | Prefix applied to all index names (e.g. `dev_`) |

## Gotchas / constraints

- The `closed_by_merged_pr` flag is set on issues at PR-index time, not issue-index time. Syncing only issues won't update this flag.
- `indexBulk()` generates document IDs as `sha1(json_encode($doc))` — collisions will silently overwrite. Only use for append-style data where the full document content is the identity.
- Leaderboard queries query OpenSearch directly via the injected `Client`, bypassing `OpenSearchService` — they don't go through `QueryBuilder`. Changes to index naming must be updated in both places.
- All views render a "data missing" state when OpenSearch throws a missing-index exception; any other exception is a 500.
