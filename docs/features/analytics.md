# Analytics

## Purpose

Surface aggregate views of GitHub activity that aren't leaderboards — monthly breakdowns of issues/PRs, average age at close, homepage label counts and Momentum charts, and the universe bar embed.

## How it works

### Homepage (`/`)

`WelcomeController` loads three data sets:

1. **Path cards** — "Choose how you want to help" tiles. Each card maps to a GitHub label (from `config/homepage.paths`). Live open-issue counts come from `HomepageCountsService`.
2. **Area tiles** — "Pick your area" tiles. Labels from `config/homepage.areas`; tiles with zero open issues are dropped.
3. **Momentum charts** — PRs and issues opened/closed over the last 12 months, side by side, with the hero CTA repeated beside the heading. `Controller::openedClosedPerMonth()` runs one date-histogram aggregation per index and passes the full monthly history as `prStats` / `issueStats`. `<x-charts.momentum-card>` charts the 12 calendar months ending with the current one (missing months count as zero) and links its footer ("N opened since …", all-time) to that dataset's By Month page. Each is null when its search fails (error reported), which hides that card; both null hides the section.

`HomepageCountsService` wraps `OpenLabelsByIssueQuery` with a 1-hour cache (`homepage_label_counts`). On OpenSearch failure, returns an empty map (tiles render without counts) rather than erroring.

### Issues by Month (`/issues/by-month`)

`IssuesByMonthController` calls `OpenItemsByMonthQuery` against the `github-issues` index. Groups open issues by month of last update — gives contributors a way to tackle the backlog in chunks.

### PRs by Month (`/prs/by-month`)

Same as issues, `PrsByMonthController` calls `OpenItemsByMonthQuery` against the `github-pull-requests` index.

### Opened and closed, all time (both month views)

Both month controllers also call `Controller::openedClosedPerMonth()` on their own index and pass `allTimeStats`. `<x-charts.all-time-card>` renders below "Pick a month" and sums the months into calendar quarters. It is hidden when `allTimeStats` is null (search failed) or empty.

### Average age at close (hidden)

The age-at-close chart is switched off: no view renders `<x-charts.age-over-time>` and no controller queries it. `AgeOverTimeQuery`, `Controller::ageOverTime()` and the component are kept so it can be restored by passing `ageStats` again and adding the component back to the month views.

### Charts

All charts are server-rendered: data is JSON-encoded into each canvas's data attributes (or the page) and drawn with Chart.js 4.5.1 (pinned with an SRI hash in `layouts/app.blade.php`). `components/charts/_bar-chart.blade.php` defines `forgerBarChart()`, `forgerNiceStep()` and `forgerFmt()` once per page with the site palette; chart components call them.

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
- `resources/views/components/charts/momentum-card.blade.php` — Homepage Momentum card (last 12 months)
- `resources/views/components/charts/github-stats.blade.php` — Homepage Momentum chart script
- `resources/views/components/charts/all-time-card.blade.php` — Month views' all-time quarterly chart
- `resources/views/components/charts/age-over-time.blade.php` — Month views' age chart card (currently unused)
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
- The homepage runs two uncached aggregations per load for Momentum; each month view runs one more for its all-time chart.
- Homepage counts cache key is `homepage_label_counts`, TTL 1 hour. Clear with `php artisan cache:clear` if counts seem stale after a major sync.
- Universe bar DDEV bypass: any request whose `Host` contains `ddev.site` is allowed regardless of `Origin`. This is a dev convenience — do not replicate in production.
