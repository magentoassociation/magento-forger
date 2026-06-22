# Leaderboard Rollout — Backlog & Caveats

Living checklist for the weighted contributor/maintainer leaderboard. Source-of-truth specs:

- [Weighted Scoring](leaderboard-scoring.md) — scores, engagement, company model, build phases
- [GitHub Timeline-Events Sync](github-timeline-events.md) — PR timeline, latency, label/triage prerequisites
- ADRs: [0001](../adr/0001-contributor-leaderboard.md), [0002](../adr/0002-maintainer-leaderboard.md)

## Outstanding work

- [ ] **`assignRanks` test** — sqlite-backed feature test for `ComputeLeaderboardScores::assignRanks()`: seed `leaderboard_entries` with known per-board scores, run ranking, assert order, `id` tiebreak, and per-board partitioning (contributor vs maintainer independent). The batch-upsert path currently has no automated coverage.
- [ ] **`approved_then_merged` maintainer bonus** — needs a review→PR-merge join (review in window, PR merged any time).
- [ ] **Review latency / responsiveness** — compute `pending_review_at` / `claimed_at` / `first_review_at` from `github-pr-timeline` into `github_user_stats`. Time-to-claim is backlog health (not a maintainer board); Time-to-review is per maintainer.
- [ ] **Label / triage scoring** — `label_name` is captured; scoring logic (scored event #9, "issues triaged") not built.
- [ ] **Company rollup** — create `organizations` / `user_org_memberships` tables, point-in-time resolution pipeline, and the company board.
- [ ] **Score boards UI** — contributor / maintainer / company boards (controllers + Blade), with per-row breakdown expansion.
- [ ] **Segmented boards** — New contributor spotlight, Rising, Recently active; all-time as opt-in only.
- [ ] **Retention loop** — good-first-issue queue from triage labels; lapse nudges driven by `current_gap_days`.
- [ ] **Schedule `leaderboard:compute`** — add to `routes/console.php` (e.g. hourly); relies on `schedule:run`.
- [ ] **All-time `first_contribution_at`** — currently "first within the rolling window"; add an all-time min pass if true first-contribution is needed.
- [ ] **(Optional) Action enum** — action strings (`pr_opened`, `review_approved`, …) are bare strings; a typo silently scores 0. `Board` is already enum-safe; actions could follow.
- [ ] **(Scale) Chunk the rank upsert** — `assignRanks` builds one upsert per board; chunk at tens of thousands of rows.

## Pre-ship verification

- [ ] Run `vendor/bin/pint --dirty --format agent` and the full test suite (nothing was executed in the build environment).
- [ ] Run migrations (`leaderboard_entries`, `github_user_stats`).
- [ ] First real `leaderboard:compute`: validate `ScoredEventReader` field assumptions — `state.keyword`, `author.keyword`, boolean `closed_by_merged_pr`.
- [ ] First PR sync after timeline change: confirm `github-pr-timeline` populates and node `id` exists on all four event types.
- [ ] Add keyword mappings (or query `.keyword`) for `label_name`, `requested_reviewer`, and timeline `type` if exact-match filtering is needed.

## Re-sync & index operations

- [ ] Drop `github-events` and `github-interactions` before re-syncing (content-hash IDs → adding `label_name` duplicates label events).
- [ ] Drop the orphaned `points` index (`ProcessGitHubInteractions` deleted).
- [ ] Re-sync PRs (`sync:github:prs`) to backfill `github-pr-timeline` + `additions`/`deletions`/`changed_files`/`author_company` — clean upsert, no drop.
- [ ] Re-sync issues (`sync:github:issues`) to backfill `author_company` — clean upsert, no drop.

## Tunable decisions (revisit, don't forget)

- **Weights** in `config/leaderboard.php` are arbitrary placeholders — tune against real output; bump `version` on change.
- **Recency**: 365-day window, 182-day half-life.
- **Impact**: `1 + log10(additions + deletions) / 2`, clamped [1, 5].
- **`current_streak_weeks`**: anchored to now with a 1-week grace; remove the grace for strict behavior.
- **Comments** are intentionally **not** scored (gaming risk) — open decision.
- **`author_company`** is free-text GitHub profile data (unreliable) — a seed for org resolution, not a key; manual mapping is the intended source of truth.
- **Bot list** (`engcom-*`, `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`) is duplicated in `ScoredEventReader` and the raw-count queries — keep in sync.
