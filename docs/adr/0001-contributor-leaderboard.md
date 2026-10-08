# Weighted contributor scoring with point-in-time company attribution

> Supersedes the earlier raw-count-only revision of this ADR (2026-06-20). Full build detail lives in `docs/features/leaderboard-scoring.md`.

We rank contributors by a **weighted Score** that reflects impact, not just volume, and attribute each contribution to the contributor's organization **at the time the work was done**. The per-metric raw-count boards are kept alongside the Score for transparency.

## Context

The tracked repo is `magento/magento2`. Contributors open PRs and issues and comment; they cannot review, label, merge, or close. **Adobe** merges and closes. A merge/close is therefore never a scored action — it is an *outcome signal* that boosts the contributor whose work shipped. The goal is to re-engage lapsed contributors, which raw counts alone do not serve.

## Decision

- **Contributor Score** = weighted sum of scored events with a recency half-life (see the feature spec for weights and the formula). Scored contributor events: issue opened, PR opened, PR merged (impact-weighted author bonus), issue resolved by a merged PR (author bonus). A merged PR's impact weight scales by `additions + deletions`, capped, so PR-splitting does not pay.
- **Point-in-time company attribution**: every event is credited to the org the contributor belonged to on the contribution date (for the merge bonus, the PR's `created_at`, not `merged_at`). Org membership is a date-ranged table; manual override is the source of truth.
- **Segmented boards** instead of one global ranking: New contributor spotlight, Rising (score gain over a fixed window — see the feature spec), Recently active (recent *contributor* activity), Comebacks, and Calendar-Period boards. All-time is demoted to an opt-in deep view because it entrenches incumbents.
- **Engagement signals** (first/last contribution, streak, gap) are computed per user to drive lapse detection and the retention loop.
- The existing raw-count per-metric boards remain, reading OpenSearch directly, so every weighted row can be traced back to transparent counts.

## Consequences

- A precompute job is required: point-in-time membership joins and per-event decay cannot be expressed in a single OpenSearch aggregation, so scores are computed in PHP and written to SQL (`leaderboard_entries`, `org_leaderboard_entries`, `github_user_stats`).
- PR size fields (`additions`, `deletions`, `changedFiles`) must be added to the PR sync and backfilled before impact weighting works.
- Weights are configurable and versioned in `config/leaderboard.php`; a version bump triggers recompute. Each board row exposes its breakdown so weighting stays transparent.

## Considered options

**Raw counts only (the previous revision)** — rejected. Transparent and low-maintenance, but cannot express that a 400-line fix outweighs a typo, cannot rank companies, and does nothing for re-engagement. We keep raw-count boards as a transparency layer rather than the whole system.

**Single combined contributor+maintainer score** — rejected. The two roles have different action vocabularies and audiences; scores stay separate (see ADR 0002).

**Current-employer attribution** — rejected in favor of point-in-time. Current-employer is simpler but retroactively hands a contributor's whole history to whoever they work for now, which is wrong for company recognition.
