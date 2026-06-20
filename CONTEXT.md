# Forger

A dashboard and tooling application for the Magento Association to track and surface open-source contributions to the Magento 2 project on GitHub.

## Features

- [GitHub Sync](docs/features/github-sync.md) — GraphQL-based sync of issues and PRs into OpenSearch
- [OpenSearch](docs/features/opensearch.md) — Index structure, read/write paths, QueryBuilder
- [Contributor Leaderboards](docs/features/contributor-leaderboards.md) — Top 100 contributors by metric and Calendar Period
- [Maintainer Leaderboards](docs/features/maintainer-leaderboards.md) — Top 100 reviewers by approval/rejection metric
- [Label Management](docs/features/label-management.md) — Label views, missing-component detection, bulk spreadsheet upload
- [Authentication](docs/features/authentication.md) — GitHub OAuth login, admin role, rate limiting
- [Analytics](docs/features/analytics.md) — Homepage counts, issues/PRs by month, chart API, universe bar
- [Admin Panel](docs/features/admin-panel.md) — Filament user management, GitHubStats widget

## Language

### GitHub Data

**Interaction**:
A deliberate human contribution act on a GitHub issue: opening it, commenting on it, or having it assigned/labeled/closed. Previously sourced from the removed `SyncGitHubInteractions` command via issue comment nodes and timeline items.
_Avoid_: event (overlaps with GitHub's own "timeline event" concept)

**Event**:
A GitHub timeline item on an issue — a system or workflow action such as `labeled`, `closed`, `assigned`. Previously sourced from the removed `SyncGitHubEvents` command via `timelineItems`. Distinct from an Interaction in that events are triggered by any actor (including bots), not necessarily the issue author.
_Avoid_: interaction (reserved for deliberate contribution acts)

**Bot**:
A GitHub account excluded from all contributor leaderboards. Currently: any login matching `engcom-*`, `dependabot`, or `github-actions[bot]`. Excluded at OpenSearch query time.
_Avoid_: automation, service account

**Contributor**:
Any non-bot GitHub account that has authored at least one issue or pull request in the tracked repository. Contributor identity is the GitHub login. Company attribution is applied where a matching `User.github_username` exists; otherwise attribution is blank.
_Avoid_: member, user (reserved for the application's registered users)

**User**:
A registered account in the Forger application, identified by email and optionally linked to a GitHub login via `github_username`. A User may or may not be a Contributor.
_Avoid_: contributor, member

### Leaderboards

**Contributor Leaderboard**:
A ranked list of the top 100 Contributors by a single contribution metric within a Calendar Period. Each metric has its own leaderboard (e.g., PRs merged, issues opened). Multiple leaderboards are shown as separate views, not combined into one table.
_Avoid_: points leaderboard, company leaderboard

**Company Leaderboard**:
The former points-based ranking of companies. Removed in favour of Contributor Leaderboards. Do not reintroduce.
_Avoid_: (deprecated — do not use)

**Metric**:
A single countable contribution signal used as the basis for one Contributor Leaderboard. Each metric filters on its own event date field (e.g., PRs merged filters on `merged_at`, not `created_at`).
_Avoid_: score, points, stat

**Calendar Period**:
A date range defined by calendar boundaries, not rolling windows. Presets: last month (first–last day of previous month), last quarter (Q1–Q4 of current year), last year (Jan 1–Dec 31 of previous year), or a custom from/to range.
_Avoid_: rolling window, time window

**Maintainer Leaderboard**:
A set of Contributor Leaderboards for review-based metrics: PRs approved (review state `APPROVED`) and PRs rejected (review state `CHANGES_REQUESTED`). Sourced from the `github-pr-reviews` index, one document per review, upserted by GitHub review node ID. Populated as a side effect of the PR sync. See [Maintainer Leaderboards](docs/features/maintainer-leaderboards.md).

---

## Example dialogue

> **Dev**: Should I show the "unclaimed" contributors on the leaderboard like the old points view did?
>
> **Domain expert**: No — Contributor Leaderboards show all Contributors, including those without a linked User account. Just leave the company column blank if there's no match. "Unclaimed" was a points-system concept.
>
> **Dev**: What if someone wants to see totals across all metrics for a Contributor?
>
> **Domain expert**: That's not what Contributor Leaderboards are. Each leaderboard answers one question: "who merged the most PRs?" Combining metrics into a total score is what the old points system did — we moved away from that.
>
> **Dev**: The `engcom-` accounts are creating hundreds of issues. Should they count?
>
> **Domain expert**: No, those are Bots — exclude them at query time. Same for `dependabot` and `github-actions[bot]`.