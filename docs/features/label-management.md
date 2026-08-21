# Label Management

## Purpose

View which GitHub labels are in use on the tracked repository, and surface PRs missing a component label.

## How it works

Two public views under `/labels`. Read-only — the token in use lacks `repo` scope, so bulk label creates/renames are not supported.

### View: All Labels (`/labels/allLabels`)

Queries `OpenLabelsByIssueQuery` to aggregate open issues by label, grouped into sections. Renders as a browsable label directory.

### View: PRs Without Component Label (`/labels/prsMissingComponent`)

Queries `PrsWithoutComponentLabelQuery` to find open PRs that have no label matching the `Component:` prefix. Used to identify PRs that need triage.

## Key files

- `app/Http/Controllers/LabelController.php` — All label routes
- `app/Queries/Dashboard/OpenLabelsByIssueQuery.php` — All-labels aggregation
- `app/Queries/Dashboard/PrsWithoutComponentLabelQuery.php` — Missing-component PR query
- `resources/views/labels/` — Blade views

## Configuration

None — these views query OpenSearch (`github-issues` index), not the GitHub API directly. No `GITHUB_TOKEN` is used at request time; label data is only as fresh as the last `sync:github:issues` run (see `docs/features/github-sync.md`).

## Gotchas / constraints

- All label-view pages render a "data missing" placeholder when OpenSearch throws a missing-index exception.