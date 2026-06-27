# Leaderboard Rollout — Backlog & Caveats

Living checklist for the weighted contributor/maintainer leaderboard. Source-of-truth specs:

- [Weighted Scoring](leaderboard-scoring.md) — scores, engagement, company model, build phases
- [GitHub Timeline-Events Sync](github-timeline-events.md) — PR timeline, latency, label/triage prerequisites
- ADRs: [0001](../adr/0001-contributor-leaderboard.md), [0002](../adr/0002-maintainer-leaderboard.md)

## Outstanding work

### UI & surfacing

- [ ] **Link score boards in nav** — `scores/*` routes exist but aren't in the dynamic `MainMenu`.
- [ ] **Internal-only maintainer stats** — expose review-latency / responsiveness signals (`median_time_to_review_hours`, `median_time_to_claim_days`, `reviews_in_window`), and council-only maintainers, to maintainers / community-council / admins only — not on the public board.

### Retention

- [ ] **Retention loop** — good-first-issue queue from triage labels; lapse nudges driven by `current_gap_days`.

### Hardening & operations

- [ ] **`assignRanks` test** — sqlite-backed feature test for `ComputeLeaderboardScores::assignRanks()`: seed `leaderboard_entries` with known per-board scores, run ranking, assert order, `id` tiebreak, and per-board partitioning (contributor vs maintainer independent). The batch-upsert path currently has no automated coverage.
- [ ] **Schedule `leaderboard:compute`** — add to `routes/console.php` (e.g. hourly); relies on `schedule:run`.
- [ ] **(Scale) Chunk the rank upsert** — `assignRanks` builds one upsert per board; chunk at tens of thousands of rows.

## Completed

- **`approved_then_merged` maintainer bonus** — impact-weighted approver bonus when an approved PR merges; self-reviews skipped.
- **Review latency / responsiveness** — `median_time_to_review_hours` / `median_time_to_claim_days` / `reviews_in_window`, plus the `pr_claimed` staleness bonus (credited only when the claim is reviewed).
- **Label / triage scoring** — `label_applied`, deduped per (actor, target, label); excluded labels via config.
- **All-time `first_contribution_at`** — earliest issue/PR/review across all time (composite agg).
- **Action enum** — scored actions are a backed `Action` enum; an invalid action can't be constructed (no silent zero-scores from typos).
- **Comeback metric** — `returned_after_days` records the silence a returning contributor bridged (stat, not a score); `comeback_url`/`comeback_title` capture the PR/issue that ended the silence, linked on the Highlights card.
- **Company rollup (engine)** — `organizations` / `user_org_memberships` / `org_leaderboard_entries` tables; `MembershipResolver` (point-in-time) + `CompanyScoreAggregator` write per-org scores, unresolved → Unknown.
- **Membership suggestions** — `leaderboard:suggest-memberships` seeds low-confidence `profile` memberships from `author_company` (skips bots, never overwrites manual). Manual entry / a Filament review UI is still the gap for high-confidence org mapping.
- **Score boards UI** — `scores/{board}`: contributor & maintainer boards (with per-row breakdown expansion) and a merged company board, reading the precomputed SQL tables. `ScoreLeaderboardController` + Blade. Not yet linked in the dynamic nav.
- **Segmented boards (Highlights)** — `scores/highlights`: New contributor spotlight, Rising, Comebacks, Recently active — all from `github_user_stats`. Shared tabs partial; all-time intentionally omitted. Each card carries plain-language explanatory text. New contributor spotlight links to the newcomer's first PR/issue (`first_contribution_url`/`first_contribution_title`, captured during compute within `spotlight.window_days`).
- **Rising over a fixed window** — Rising is `contributor_score − rising_baseline_score` (the score as of `rising.window_days` ago), not a per-run delta. `github_score_snapshots` + `ScoreSnapshotRepository` (record / `baselineAsOf` / prune) back it; `leaderboard:compute` reads the baseline, snapshots today, and prunes past `rising.retention_days`. The panel label states the real timeframe.
- **Recently active = contributor activity only** — `last_contributor_at` tracks the most recent *contributor* event; maintainer reviews don't qualify a maintainer for the Recently-active card. Computed in `LeaderboardScorer::summarize()`.
- **"How are scores tallied?" modal** — each contributor/maintainer board has a Bootstrap modal listing configured base points per action (live from config) + impact/recency/staleness multipliers. Data via `ScoreLeaderboardController::scoringExplainer()` → `$scoring` (kept out of Blade `@php` blocks, which collide with inline `@php(...)`).
- **Per-user score drill-down** — `scores/{board}/user/{login}` lists the actual PRs/issues/reviews behind a score, re-derived on demand (`ContributionDetailReader`), linked as "Details" on each board row. Authored items + reviews are itemized; derived bonuses (claim/label/merge-approval) count toward the score but aren't line-itemized.
- **Self-review exclusion + team eligibility** — reviews on one's own PR no longer score (all review actions). Maintainer points require maintainer-rights membership — the `maintainer` team **or** the `community-council` committee — via `role_eligibilities` → `EligibilityGate` (empty roster = allow all, opt-in until populated); contributor points are open to everyone. The public maintainer board lists only the `maintainer` roster (council-only members are gated in for scoring but hidden from it). Rosters come from `sync:github:teams` (both teams; needs org Members:read) or `leaderboard:import-eligibility <csv>`.
- **Profile names + avatars** — `sync:github:profiles` fetches GitHub display name + avatar into `github_profiles` for board/roster logins; the contributor/maintainer boards, Highlights, and drill-down show real name + handle, with an avatar for every user (derived from the login when not yet fetched). Public profile data — no special token scope.

## Pre-ship verification

- [ ] Run `vendor/bin/pint --dirty --format agent` and the full test suite (nothing was executed in the build environment).
- [ ] Run migrations (`leaderboard_entries`, `github_user_stats` incl. `last_contributor_at`, `rising_baseline_score`, and `first_contribution_url`/`first_contribution_title`, `github_score_snapshots`, org tables, `role_eligibilities`, `github_profiles`).
- [ ] Run `sync:github:profiles` after `leaderboard:compute` to populate display names/avatars (public profile data; re-run periodically). Avatars work without it (derived from login); names need it.
- [ ] Populate eligibility, then re-run `leaderboard:compute`: `sync:github:teams` (token needs org **Members: read**) **or** `leaderboard:import-eligibility <csv>` (`login,role`) when the token can't read org membership. Until populated, everyone counts.
- [ ] First real `leaderboard:compute`: validate `ScoredEventReader` field assumptions — `state.keyword`, `author.keyword`, boolean `closed_by_merged_pr`.
- [ ] First PR sync after timeline change: confirm `github-pr-timeline` populates and node `id` exists on all four event types.
- [ ] Add keyword mappings (or query `.keyword`) for `label_name`, `requested_reviewer`, and timeline `type` if exact-match filtering is needed.

## Re-sync & index operations

> Pre-deployment: there's no production data, so this is just the initial sync, and there's nothing to "backfill." The drop steps below only matter if your dev OpenSearch already holds documents written before these schema changes; on a clean cluster, skip the drops and run the syncs.

- [ ] Drop `github-events` and `github-interactions` before re-syncing (content-hash IDs → adding `label_name` duplicates label events).
- [ ] Drop the orphaned `points` index (`ProcessGitHubInteractions` deleted).
- [ ] Re-sync PRs (`sync:github:prs`) to backfill `github-pr-timeline` + `additions`/`deletions`/`changed_files`/`author_company` — clean upsert, no drop.
- [ ] Re-sync issues (`sync:github:issues`) to backfill `author_company` — clean upsert, no drop.

## Tunable decisions (revisit, don't forget)

- **Weights** in `config/leaderboard.php` are arbitrary placeholders — tune against real output; bump `version` on change.
- **Recency**: 365-day window, 182-day half-life.
- **Rising window**: `rising.window_days` (default 7) — the timeframe the "Rising" delta is measured over, against `github_score_snapshots`. `rising.retention_days` (default 60) bounds the snapshot table. Each `leaderboard:compute` records one snapshot per contributor, so the window only means a true N days once `compute` runs at least daily. Until N days of history exist, the baseline is 0 and everyone shows their full score as their gain.
- **Impact**: `1 + log10(additions + deletions) / 2`, clamped [1, 5].
- **`current_streak_weeks`**: anchored to now with a 1-week grace; remove the grace for strict behavior.
- **Comments** are intentionally **not** scored (gaming risk) — open decision.
- **`author_company`** is free-text GitHub profile data (unreliable) — a seed for org resolution, not a key; manual mapping is the intended source of truth.
- **Bot list** lives in one place — `config('leaderboard.bots')` (`exact` + `prefixes`), consumed everywhere via `App\Support\BotFilter` (`mustNot($field)` for OpenSearch, `isBot($login)` for PHP). Add a bot there and it applies to every board and scorer.
