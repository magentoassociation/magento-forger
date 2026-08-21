# Label Management

## Purpose

View which GitHub labels are in use on the tracked repository.

## How it works

One public view under `/labels`. Read-only — the token in use lacks `repo` scope, so bulk label creates/renames are not supported.

### View: All Labels (`/labels/allLabels`)

Queries `OpenLabelsByIssueQuery` to aggregate open issues by label, grouped into sections. Renders as a browsable label directory.

## Key files

- `app/Http/Controllers/LabelController.php` — All label routes
- `app/Queries/Dashboard/OpenLabelsByIssueQuery.php` — All-labels aggregation
- `resources/views/labels/` — Blade views

## Configuration

None — these views query OpenSearch (`github-issues` index), not the GitHub API directly. No `GITHUB_TOKEN` is used at request time; label data is only as fresh as the last `sync:github:issues` run (see `docs/features/github-sync.md`).

## Gotchas / constraints

- All label-view pages render a "data missing" placeholder when OpenSearch throws a missing-index exception.