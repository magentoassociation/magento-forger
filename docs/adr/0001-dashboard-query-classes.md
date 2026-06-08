# Dashboard queries are self-contained injectable classes, not raw DSL in controllers

Dashboard OpenSearch queries live in `app/Queries/Dashboard/` as individual classes, each owning its query DSL and response parsing behind a single typed `execute()` method. Controllers inject the specific query class they need via Laravel's method injection.

We chose one class per query pattern over a shared `DashboardQueryService` (which would couple all dashboards into one module) and over a generic executor interface (which loses return-type safety without PHPStan, which this project doesn't run). The key trade-off: no shared interface means no single place to swap the OpenSearch client — but since there is currently only one adapter, the seam isn't real yet.

## Considered Options

- **Named methods on a single `DashboardQueryService`** — rejected because it couples all dashboard queries into one module; adding a new dashboard type means editing a growing service.
- **Shared `DashboardQuery` interface + executor** — rejected because PHP has no native generics and the project doesn't run PHPStan, so `parse(): mixed` on the interface buys nothing at runtime.
- **Extend `QueryBuilder` for date histograms and Painless scripts** — rejected because `QueryBuilder` handles simple filters/sorts only; extending it to cover nested aggregations and multi-line Painless scripts would make it a large general-purpose DSL builder rather than a focused module.