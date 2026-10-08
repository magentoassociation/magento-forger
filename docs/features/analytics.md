# Analytics

## Purpose

Surface aggregate views of GitHub activity that aren't leaderboards — monthly breakdowns of issues/PRs, homepage label counts, the universe bar embed, and the chart API.

## How it works

### Homepage (`/`)

`WelcomeController` loads two data sets:

1. **Path cards** — "Choose how you want to help" tiles. Each card maps to a GitHub label (from `config/homepage.paths`). Live open-issue counts come from `HomepageCountsService`.
2. **Area tiles** — "Pick your area" tiles. Labels from `config/homepage.areas`; tiles with zero open issues are dropped.

`HomepageCountsService` wraps `OpenLabelsByIssueQuery` with a 1-hour cache (`homepage_label_counts`). On OpenSearch failure, returns an empty map (tiles render without counts) rather than erroring.

### Issues by Month (`/issuesByMonth`)

`IssuesByMonthController` calls `OpenItemsByMonthQuery` against the `github-issues` index. Groups open issues by month of last update — gives contributors a way to tackle the backlog in chunks.

### PRs by Month (`/prsByMonth`)

Same as issues, `PrsByMonthController` calls `OpenItemsByMonthQuery` against the `github-pull-requests` index.

### Chart API (`/api/charts/{method}`)

`ChartController::dispatch` resolves `{method}` to one of an allowlist of handlers (currently `prAgeOverTime` / `issueAgeOverTime`) that return JSON. **Not yet wired into any view** — the endpoint exists but nothing in `resources/` consumes it today.

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
- `app/Http/Controllers/ChartController.php` — Chart API dispatcher
- `app/Http/Controllers/UniverseBarController.php` — Universe bar embed
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

- The "Momentum" chart (monthly PR opened/closed) is disabled. `WelcomeController::prsOverTime()` is live code, but its *call* (and the `monthlyStats`/`dataMissing` view keys) is commented out in `index()` — do not remove the method, it's intentionally preserved for restoration.
- Homepage counts cache key is `homepage_label_counts`, TTL 1 hour. Clear with `php artisan cache:clear` if counts seem stale after a major sync.
- Universe bar DDEV bypass: any request whose `Host` contains `ddev.site` is allowed regardless of `Origin`. This is a dev convenience — do not replicate in production.
