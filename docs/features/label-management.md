# Label Management

## Purpose

Manage GitHub labels on the tracked repository — view which labels are in use, surface PRs missing a component label, and bulk-apply label creates and renames from a spreadsheet.

## How it works

Three public views and one admin-only upload form, all under `/labels`.

### View: All Labels (`/labels/allLabels`)

Queries `OpenLabelsByIssueQuery` to aggregate open issues by label, grouped into sections. Renders as a browsable label directory.

### View: PRs Without Component Label (`/labels/prsMissingComponent`)

Queries `PrsWithoutComponentLabelQuery` to find open PRs that have no label matching the `Component:` prefix. Used to identify PRs that need triage.

### Admin: Process Labels (`/labels/process-labels`) — admin only

Upload a spreadsheet (`.xlsx`, `.xls`, `.ods`, `.csv`) to bulk-create or rename GitHub labels. The form POSTs to `uploadLabels`.

`LabelOrchestrator::process()` parses the spreadsheet:

- Reads `area` and `component` tabs.
- Column A: label name. Column D: `keep` flag (`no` = remap). Column E: new name (rename). Column F: replace-with (remap).
- **New labels** (no keep/rename/remap): created via `GitHubLabelService::createLabel()`.
- **Renames** (column E set): renamed via `GitHubLabelService::renameLabel()`.
- **Remaps** (column D = `no`, column F set): skipped with a warning — remap is not implemented.

Results flash back: `created`, `renamed`, `skipped`, `errors` counts with detail messages.

## Key files

- `app/Http/Controllers/LabelController.php` — All label routes
- `app/Services/Label/LabelOrchestrator.php` — Spreadsheet parsing + GitHub orchestration
- `app/Services/GitHub/GitHubLabelService.php` — GitHub REST calls for create/rename
- `app/Queries/Dashboard/OpenLabelsByIssueQuery.php` — All-labels aggregation
- `app/Queries/Dashboard/PrsWithoutComponentLabelQuery.php` — Missing-component PR query
- `app/Http/Middleware/AdminMiddleware.php` — Guards `/labels/process-labels`
- `resources/views/labels/` — Three blade views

## Configuration

- `github.repo` — Repository to apply label changes to.
- `GITHUB_TOKEN` — Must have `repo` scope (label write permission).

## Gotchas / constraints

- Remap (`keep = no` + column F) is **not implemented** — entries are logged and skipped. Do not add remap logic without updating `LabelOrchestrator`.
- Spreadsheet detection stops at the first run of 3+ consecutive empty rows in column A. Sparse sheets may truncate early.
- Label creation returning `0` from `GitHubLabelService` is treated as either a skip (label already exists) or an error, depending on the `status` field of `getLastOperationError()`.
- All label-view pages render a "data missing" placeholder when OpenSearch throws a missing-index exception.
