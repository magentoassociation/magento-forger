# Analytics

## Purpose

Surface aggregate views of GitHub activity that aren't leaderboards — monthly breakdowns of issues/PRs, average age at close, homepage label counts and Momentum charts, and the universe bar embed.

## How it works

### Homepage (`/`)

`WelcomeController` loads three data sets:

1. **Path cards** — "Choose how you want to help" tiles. Each card maps to a GitHub label (from `config/homepage.paths`). Live open-issue counts come from `HomepageCountsService`.
2. **Area tiles** — "Pick your area" tiles. Labels from `config/homepage.areas`; tiles with zero open issues are dropped.
3. **Momentum charts** — PRs and issues opened/closed per month, side by side. `openedClosedPerMonth()` runs one date-histogram aggregation per index and passes `prStats` / `issueStats` to the view. Each is null when its search fails (error reported), which hides that chart; both null hides the section.

`HomepageCountsService` wraps `OpenLabelsByIssueQuery` with a 1-hour cache (`homepage_label_counts`). On OpenSearch failure, returns an empty map (tiles render without counts) rather than erroring.

### Issues by Month (`/issuesByMonth`)

`IssuesByMonthController` calls `OpenItemsByMonthQuery` against the `github-issues` index. Groups open issues by month of last update — gives contributors a way to tackle the backlog in chunks.

### PRs by Month (`/prsByMonth`)

Same as issues, `PrsByMonthController` calls `OpenItemsByMonthQuery` against the `github-pull-requests` index.

### Average age at close (both month views)

Both month controllers also run `AgeOverTimeQuery` against their own index via `Controller::ageOverTime()` and pass `ageStats` (month => average days open, for items closed that month). The `<x-charts.age-over-time>` card renders below "Pick a month"; it is hidden when `ageStats` is null (search failed) or empty.

### Charts

All charts are server-rendered: data is JSON-encoded into the page and drawn with Chart.js (loaded in `layouts/app.blade.php`). `components/charts/_bar-chart.blade.php` defines `forgerBarChart()` once per page with the site palette; chart components call it.

### Universe Bar (`/api/universe-bar`)

`UniverseBarController` renders the `components.universe-bar` blade component and returns it as HTML. Used for cross-site embedding (the bar can be iframed into other Magento Association properties). CORS is enforced against an allowlist:

- `magento-opensource.com`
- `docs.magento-opensource.com`
- `magentoassociation.org`, `*.magentoassociation.org`
- `meet-magento.com`
- `forger.magento-opensource.com`
- `*.ddev.site` (dev)

## Key files

- `app/Http/Controllers/WelcomeController.php` — Homepage
- `app/Services/HomepageCountsService.php` — Cached label counts
- `app/Http/Controllers/IssuesByMonthController.php`
- `app/Http/Controllers/PrsByMonthController.php`
- `app/Queries/Dashboard/OpenItemsByMonthQuery.php` — Shared by both month views
- `app/Queries/Dashboard/AgeOverTimeQuery.php` — Average age at close per month
- `app/Http/Controllers/UniverseBarController.php` — Universe bar embed
- `resources/views/components/charts/_bar-chart.blade.php` — Shared Chart.js bar chart helper
- `resources/views/components/charts/github-stats.blade.php` — Homepage Momentum charts
- `resources/views/components/charts/age-over-time.blade.php` — Month views' age chart card
- `resources/views/welcome.blade.php`
- `resources/views/issuesByMonth/index.blade.php`
- `resources/views/prsByMonth/index.blade.php`
- `resources/views/components/universe-bar.blade.php`
- `config/homepage.php` — `paths` and `areas` label lists, external `links`

## Configuration

| Key | Description |
|-----|-------------|
| `homepage.paths` | Array of path card definitions (icon, title, blurb, cta, label) |
| `homepage.areas` | Array of GitHub label names for the area tiles |
| `homepage.links` | External links shown on the homepage |

## Gotchas / constraints

- Chart searches never fail a page: a search error hides the chart instead. Only the month views' main timeline still aborts with 500 on a non-missing-index error.
- The homepage runs two uncached aggregations per load for Momentum.
- Homepage counts cache key is `homepage_label_counts`, TTL 1 hour. Clear with `php artisan cache:clear` if counts seem stale after a major sync.
- Universe bar DDEV bypass: any request whose `Host` contains `ddev.site` is allowed regardless of `Origin`. This is a dev convenience — do not replicate in production.
