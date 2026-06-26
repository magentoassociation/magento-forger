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
- [Admin Panel](docs/features/admin-panel.md) — Filament user management

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
Ranks Contributors two ways: the raw-count per-metric boards (top 100 by a single metric within a Calendar Period — e.g. PRs merged, issues opened — each its own view, kept for transparency) and a weighted **Contributor Score** that reflects impact rather than raw volume. See [ADR 0001](docs/adr/0001-contributor-leaderboard.md) and [Weighted Scoring](docs/features/leaderboard-scoring.md).

**Score**:
A weighted sum of a Contributor's (or Maintainer's) scored events, with impact weighting and a recency half-life. Contributor and Maintainer Scores are always kept separate, never merged into one number. Weights are versioned in `config/leaderboard.php`; every board row exposes its breakdown so the weighting stays transparent.

**Company Leaderboard**:
Ranks organizations by the **point-in-time** sum of their members' scores — each contribution credited to the org the contributor belonged to *when the work was done* (for a merge bonus, the PR's `created_at`). Unresolved contributors roll up to "Unknown". See [ADR 0001](docs/adr/0001-contributor-leaderboard.md).

**Metric**:
A single countable contribution signal used as the basis for one raw-count Contributor Leaderboard. Each metric filters on its own event date field (e.g., PRs merged filters on `merged_at`, not `created_at`). Distinct from a Score, which combines weighted events.

**Calendar Period**:
A date range defined by calendar boundaries. Presets: last month (first–last day of previous month), last quarter (Q1–Q4 of current year), last year (Jan 1–Dec 31 of previous year), or a custom from/to range. Used by the raw-count boards and the period-scoped Score views. The Score also has a default **rolling-12-month** window with decay (see Score), used by the Recently active and Rising segments.

**Maintainer Leaderboard**:
Ranks Maintainers by review activity — both raw-count boards (PRs approved = review state `APPROVED`, PRs rejected = `CHANGES_REQUESTED`, sourced from the `github-pr-reviews` index) and a weighted **Maintainer Score** that adds an impact-weighted bonus when an approved PR is later merged. Review latency and label/triage scoring are deferred pending a timeline-events index. See [ADR 0002](docs/adr/0002-maintainer-leaderboard.md) and [Maintainer Leaderboards](docs/features/maintainer-leaderboards.md).

---

## Example dialogue

> **Dev**: Should I show contributors without a linked User account on the leaderboard?
>
> **Domain expert**: Yes — the raw-count boards show all Contributors. Leave the company column blank if there's no match. On the Company Leaderboard, contributors whose org can't be resolved roll up to "Unknown" rather than being hidden.
>
> **Dev**: What if someone wants a single total across all metrics for a Contributor?
>
> **Domain expert**: That's the Score — a weighted, impact-aware sum, separate from the raw-count boards. Keep the per-metric boards too; they answer "who merged the most PRs?" transparently, and the Score row links back to that breakdown. The Contributor Score and Maintainer Score stay separate from each other.
>
> **Dev**: The `engcom-` accounts are creating hundreds of issues. Should they count?
>
> **Domain expert**: No, those are Bots — exclude them at query time. Same for `dependabot` and `github-actions[bot]`.