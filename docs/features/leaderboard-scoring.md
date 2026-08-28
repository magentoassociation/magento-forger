# Weighted Scoring & Point-in-Time Company Leaderboards

> **Status:** Build spec. **Supersedes** the raw-count-only direction of ADR 0001 and ADR 0002 by decision (2026-06-20). The per-metric raw-count boards were never shipped (see [contributor-leaderboards.md](contributor-leaderboards.md) / [maintainer-leaderboards.md](maintainer-leaderboards.md)); the live boards are this weighted **Score** layer plus a **Company** board, all served by `ScoreLeaderboardController`.
>
> **Repo tracked:** `magento/magento2` (not this app). **Adobe** merges and closes; maintainers and contributors cannot. A merge/close is therefore **never a scored action** — it is an *outcome signal* that boosts the people who did the work.

## Goal

Recognize *impact*, not just volume, and give lapsed contributors a reason to return. Two levers:

1. A weighted **Contributor Score** and **Maintainer Score** per individual (kept strictly separate — never a single combined number).
2. A **Company Score** built by attributing each individual's contributions to whatever org they belonged to *at the time of the contribution* (point-in-time).

## Roles & what each can do

| Role | Scored actions | Cannot do |
|---|---|---|
| Contributor | Open PRs, open issues (comments out of scope, see below) | Review, label, merge, close |
| Maintainer | Approve / reject / comment on reviews, apply labels | Merge, close |
| Adobe | — (never scored) | — (they merge & close; this only feeds outcome bonuses) |

A maintainer who also authors PRs accrues a Contributor Score *and* a Maintainer Score independently.

**Eligibility.** Maintainer points are limited to people with maintainer rights: the **maintainer** team plus the **community-council** committee (who hold the same rights but don't actively maintain). Contributor points are open to anyone. Both rosters populate `role_eligibilities` (roles `maintainer` and `community-council`) via `sync:github:teams` (token needs org Members:read) or `leaderboard:import-eligibility <csv>`. `EligibilityGate` lets *either* role earn maintainer points. The **public maintainer board lists only the `maintainer` roster** — council-only members are excluded from it (they'll surface in the planned internal-only stats). If both rosters are empty, gating is disabled and everyone counts.

**Self-reviews don't count.** A review on one's own PR (reviewer == PR author) earns nothing — for *all* review actions, not just the merge bonus. This is why commenting on your own PR no longer awards maintainer points.

## Scored events (grounded in current indexes)

| # | Role | Action | Index | Date field | Status |
|---|---|---|---|---|---|
| 1 | Contributor | Issue opened | `github-issues` | `created_at` | ✅ exists |
| 2 | Contributor | PR opened | `github-pull-requests` | `created_at` | ✅ exists |
| 3 | Contributor | **PR merged** (author bonus) | `github-pull-requests` | `merged_at` (+ `author`) | ✅ exists |
| 4 | Contributor | **Issue resolved by merged PR** (author bonus) | `github-issues` | `closed_by_merged_pr` flag | ✅ exists |
| 5 | Maintainer | Review approved | `github-pr-reviews` | `submitted_at`, `state=APPROVED` | ✅ exists |
| 6 | Maintainer | Review rejected | `github-pr-reviews` | `submitted_at`, `state=CHANGES_REQUESTED` | ✅ exists |
| 7 | Maintainer | Review commented | `github-pr-reviews` | `submitted_at`, `state=COMMENTED` | ✅ exists |
| 8 | Maintainer | **Approved PR later merged** (approver bonus) | join reviews → PR `merged_at` | derivable | ✅ emitted by `ScoredEventReader` (`Action::APPROVED_THEN_MERGED`, impact-weighted, self-review guarded) |
| 9 | Maintainer | **Label applied** (triage) | `github-events` + `github-pr-timeline` | event date | ✅ `ScoredEventReader` label events (deduped per actor/target/label; excludes configured labels) |
| 10 | Contributor | Comment on issue/PR | `github-interactions` (`type=comment`) | `created_at` | ✅ available — optional, see caveat |
| 11 | Maintainer | **Claimed & reviewed a pending-review PR** (flat bonus) | `github-pr-timeline` + `github-pr-reviews` | claim `created_at` | ✅ `ReviewLatencyAnalyzer` (flat weight; only credited if the claim is reviewed — time-to-claim is kept as a stat only) |

> **Comment scoring caveat:** comments are now available (the interactions index is live), but they are the easiest signal to farm with low-value chatter. If scored at all, give them a low base weight and cap points per thread/day. Recommended: leave comments *out* of the Score initially and revisit, rather than incentivize noise on `magento/magento2`.

### Required sync additions before build

- **PR size** for impact weighting — ✅ done: `additions`, `deletions`, `changedFiles` added to `github_pull_requests` GraphQL and persisted as `additions`/`deletions`/`changed_files` in `toPullRequestDocument()`. Re-sync PRs to backfill.
- **Author profile company** to seed org resolution — ✅ done: captured via `author { ... on User { company } }` on the PR and issue queries, persisted as `author_company` on both documents.
- **(Deferred)** A timeline-events index to event-source label application (#9). Until then, maintainer scoring uses reviews only.

## Scoring formula

```
points(event) = base_weight[action] × impact_weight(event) × recency_factor(event_date)
```

> **Why multiply instead of add flat values?** The factors are *ratios* — `impact ∈ [1, 5]`, `recency ∈ [0, 1]` — that scale each action relative to its own base weight, and that buys four things a flat `+bonus` can't:
> - **Action ranking is preserved.** `base × impact` keeps a merged PR (base 10) above an opened issue (base 1) at every impact level. A flat `+impact` bump is the same absolute size for both, so a large issue could out-earn a real merge. `base` encodes how much a *kind* of work matters; `impact` encodes how much of it there is — multiplying keeps neither from overriding the other.
> - **Bounded, predictable range.** Each action's points stay in a known band (`base` to `5 × base`, decaying toward 0). Flat additions are unbounded and unit-mismatched — `+log10(size)` is not in the same currency as base points, so caps become impossible to reason about.
> - **Clean composition.** Three independent ratios multiply order-independently, each tunable alone. `recency = 0` correctly zeroes a dead contribution; a flat `+recency` can never zero anything, so a stale typo would still bank points.
> - **Anti-gaming holds.** Because `impact` is capped at 5, splitting one 400-line PR into ten pays *less* than the single PR. With flat adds each split keeps its full base weight, so splitting still pays.

Base weights live in **`config/leaderboard.php`** with a `version` integer (bump → recompute). Starting defaults:

| Action | Base weight |
|---|---|
| Issue opened (1) | 1 |
| PR opened (2) | 3 |
| PR merged — author bonus (3) | 10 |
| Issue resolved by merged PR — author bonus (4) | 4 |
| Review approved (5) | 3 |
| Review rejected (6) | 3 |
| Review commented (7) | 1 |
| Approved-then-merged — approver bonus (8) | 6 |

**Impact weight** — `1.0` for all non-PR events. For PR merge bonuses (#3, #8), scale by size so a 400-line fix outweighs a typo and PR-splitting doesn't pay:

```
size   = additions + deletions
impact = clamp(1 + log10(max(size, 1)) / 2, 1.0, 5.0)
```

Optionally add a small additive bump when `labels` include hot components (config-driven allowlist), capped so impact never exceeds 5.0.

**Recency factor** — rolling 12-month window with a 6-month half-life, so dormant leaders fade and returning contributors climb fast:

```
age_days     = now - event_date
recency      = age_days > 365 ? 0 : 0.5 ** (age_days / 182)
```

`Contributor Score = Σ points(events 1–4)`. `Maintainer Score = Σ points(events 5–8)`. The period-scoped boards (last month/quarter/year, reusing the existing Calendar Period resolver) compute the same sum without decay, filtered to the period.

## Point-in-time company attribution

> **Implemented.** `MembershipResolver` (point-in-time, reads `user_org_memberships`) + `CompanyScoreAggregator` (reuses `LeaderboardScorer` for points) roll events up per org in `ComputeLeaderboardScores`, writing `org_leaderboard_entries`. Unresolved contributors bucket into an auto-created **Unknown** organization. The `user_org_memberships` table is keyed by login (no separate `github_users` table); membership population (the resolution pipeline below) is still manual until a Filament admin exists.

Each scored event is attributed to the org the actor belonged to **on the contribution date**:

| Event | Attribution date |
|---|---|
| Issue/PR opened (1, 2) | `created_at` |
| PR merged — author bonus (3) | **PR `created_at`** (when the work was done, *not* `merged_at`) |
| Issue resolved by merged PR (4) | issue `created_at` |
| Reviews (5–7) | `submitted_at` |
| Approved-then-merged — approver bonus (8) | review `submitted_at` |

`Company Score` = Σ member event points, attributed point-in-time, with the same decay. A contributor who changes employer keeps their personal score; their *past* org keeps the credit for *past* work.

The **scoring date and the attribution date are separate**: an event's points still decay from its scoring date (e.g. `merged_at`), but org credit is resolved against `ScoredEvent::attributionDate()` — the work date. `ScoredEventReader` sets that to `created_at` for PR-merged and issue-resolved-by-merge (both of which can land months later, under a different employer); for all other events it defaults to the scoring date. `CompanyScoreAggregator` resolves the org with `attributionDate()`.

### Org resolution pipeline

Candidate org from, in order: (1) email domain → `organizations.domains`, (2) normalized GitHub profile company, (3) registered `User.company`. **Manual override is the source of truth.** Each membership carries a `source` and `confidence`; low-confidence rows land in a needs-review queue. Unresolved actors roll up to **"Unknown"** rather than being hidden.

When a login has overlapping memberships, `MembershipResolver` returns the first range covering the attribution date after ordering them most-recent-start-first (an open `from` sorts last, tie-broken by most-recent end). This makes a dated manual range win over an open-ended `source=profile` suggestion without inspecting `source`.

`leaderboard:suggest-memberships` (`SuggestOrgMemberships` + `AuthorCompanyReader`) implements (2): it harvests each non-bot author's `author_company` and creates low-confidence `source=profile` memberships, never overwriting `source=manual` rows. `AuthorCompanyReader` picks each author's company from the **most recently `updated_at`** PR/issue document (so a stale value can't shadow a newer one). Each run **rebuilds the whole `source=profile` set inside a transaction** — every `source=profile` row is deleted, then survivors recreated — so logins who clear their GitHub company field drop out cleanly; consumers never see the momentary empty state. Domain/User.company seeding (1, 3) and a Filament review UI are not built yet.

## New data model

> **Keyed by `login`, not a surrogate `github_user_id`.** There is no `github_users` table. The individual dimension is the GitHub `login` string itself, carried directly on every row; `github_profiles` is only thin display metadata fetched per login.

```
organizations
  id, name, slug (unique), type [agency|merchant|adobe|independent|unknown],
  domains json, created_at, updated_at

github_profiles                       # thin display metadata per login (not a scoring dimension)
  id, login (unique), name nullable, avatar_url nullable,
  fetched_at nullable, created_at, updated_at

user_org_memberships                  # point-in-time, keyed by login
  id, login, organization_id,
  from_date nullable, to_date nullable,
  source [manual|domain|profile], confidence tinyint (default 100),
  created_at, updated_at

leaderboard_entries                   # precomputed individual scores
  id, login, board [contributor|maintainer],
  window (default rolling12), score decimal, breakdown json,
  rank nullable, computed_at

org_leaderboard_entries               # precomputed company scores
  id, organization_id, board, window,
  score decimal, member_count int, rank nullable, computed_at
```

## Compute architecture

- **OpenSearch stays the system of record** for raw events (existing indexes, unchanged).
- A scheduled **`ComputeLeaderboardScores`** job (Laravel scheduler) pulls scored events, applies weight × impact × decay in PHP, joins `user_org_memberships` for point-in-time org credit, and writes `leaderboard_entries` + `org_leaderboard_entries`.
- **Why precompute in SQL:** point-in-time membership joins and per-event decay can't be expressed in a single OpenSearch `terms` aggregation, and Filament/Blade read SQL far faster. Recompute on weight-version bump or schedule.
- Existing raw-count Blade boards keep reading OpenSearch directly — untouched.

## Anti-gaming

- Big points require a **merge**, not just opening a PR (#3, #8).
- Impact weight is **capped at 5.0** → splitting one PR into ten pays less than one solid PR.
- Bot exclusion reuses the existing list (`engcom-*`, `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`), applied in the compute job.
- Decay stops anyone (or any org) camping the top after going quiet.
- **(Planned)** a per-login `excluded` flag (set in Filament, Drupal-style) to remove bad actors from all score boards — not built yet; only the bot list above is enforced today.
- Guard `author == reviewer` so self-reviews earn nothing (all review actions, not just the approver bonus).
- Team eligibility (`EligibilityGate`): maintainer points require `maintainer` or `community-council` membership; contributor points are open.

## UI

- **Public:** new "Contributor Score", "Maintainer Score", and "Company" boards, defaulting to the rolling-12 window and reusing the Calendar Period selector. Each row exposes its **breakdown** (action counts → weighted points) via a hover tooltip on the score badge, so the score stays transparent — this directly answers ADR 0001's "opaque weights" objection. The board subtitle lists, in plain language, which actions earn points (derived from the same configured weights, so it can't drift). A **"How are scores tallied?" modal** on each board lists the configured base points per action (read live from `config('leaderboard.weights.{board}')`) plus the impact and recency multipliers, so the point values shown always match config. Modal data is assembled in `ScoreLeaderboardController::scoringExplainer()` and passed to the view as `$scoring` — not built inside a Blade `@php` block (a Blade `@php`/`@endphp` block pairs with any earlier inline `@php(...)` and breaks the template). Human-readable action names (board breakdown rows, drill-down items, modal, subtitle) all come from one place — `Action::label()` / `Action::labelFor()` on the `Action` enum — so raw keys like `pr_opened` never reach the UI.
- **Filament admin:** `Organizations` resource; `Memberships` resource with a point-in-time editor and a needs-review filter; a read-only scoring-weights view (with version); an exclusions/abuse tool.

## Engagement signals (re-engagement layer)

Scores rank impact; these signals power *getting lapsed contributors back* — the original goal. Computed per `github_user` in the same job, stored in a new `github_user_stats` table:

```
github_user_stats
  id, github_user_id,
  first_contribution_at, last_contribution_at,
  first_contribution_url, first_contribution_title, # newcomer's first PR/issue (set only within the spotlight window)
  current_gap_days,            # now - last_contribution_at
  current_streak_weeks,        # consecutive active weeks ending at now (0 if inactive; 1-week grace)
  longest_streak_weeks,
  contributor_score_prev,      # score at the previous compute run (per-run delta, reference only)
  maintainer_score_prev,
  rising_baseline_score,       # contributor score as of rising.window_days ago, for the "Rising" delta
  median_time_to_review_hours, # maintainers: responsiveness after claiming
  median_time_to_claim_days,   # maintainers: how stale the PRs they pick up are
  reviews_in_window,
  returned_after_days,         # comeback: days of silence bridged by a return (null if not a comeback)
  comeback_url, comeback_title, # the PR/issue that ended the silence (the return contribution)
  last_contributor_at,         # most recent *contributor* event (drives Recently active; excludes maintainer reviews)
  computed_at
```

A companion `github_score_snapshots` table (`login`, `contributor_score`, `captured_at`) records one row per contributor on each `leaderboard:compute`. It backs the **Rising** window: the compute reads each contributor's score as of `rising.window_days` ago (the latest snapshot at or before the cutoff, via `ScoreSnapshotRepository::baselineAsOf()`) into `rising_baseline_score`, then writes today's snapshot and prunes rows older than `rising.retention_days`.

| Signal | Definition | Source |
|---|---|---|
| First contribution | all-time earliest issue/PR opened or review submitted (`FirstContributionReader`, composite agg, not window-bound) | issues/PRs/reviews indexes |
| Last contribution | most recent scored event in the window (any board) | derived |
| Last contributor activity (`last_contributor_at`) | most recent *contributor* event only — drives Recently active, excludes maintainer reviews | derived |
| Comeback (`returned_after_days`) | days between the last contribution before the window and the return inside it, when ≥ `comeback.min_gap_days` (else null) | `FirstContributionReader` |
| Current gap | days since `last_contribution_at` — drives lapse detection | derived |
| Streak | consecutive weeks with ≥1 contribution | derived |
| Time-to-review (maintainer-controlled) | median hours from reviewer **self-assignment** to that maintainer's first `submitted_at` | ✅ `ReviewLatencyAnalyzer` |
| Time-to-claim | median days a claimed PR sat in the review pool before pickup | ✅ `ReviewLatencyAnalyzer` (also drives the `pr_claimed` bonus) |

### Review workflow (Magento-specific) and the claim incentive

Contributors do **not** request reviews. The flow is:

1. **Adobe** applies the `Progress: pending review` label → the PR enters the pool available for review.
2. A **maintainer self-assigns** as reviewer — this can be months later.
3. The maintainer submits their review.

This produces two different clocks:

- **Time-to-claim** (`Progress: pending review` applied → self-assignment): how long the PR sat before this maintainer picked it up. This is recorded as the `median_time_to_claim_days` responsiveness stat but **does not scale the score** — `pr_claimed` earns a flat bonus. Anti-farming guards: the bonus is only credited once the maintainer reviews the PR, and only when that review is submitted **after** the claim (a pre-claim or back-dated review scores nothing — latencies are computed as signed diffs so a negative span can't be `abs()`-ed into points); and repeated self-assignments on the same PR (`ClaimRecordReader`) collapse to one claim per (PR, maintainer), keeping the earliest, so re-requesting review can't manufacture extra claim credit.
- **Time-to-review** (self-assignment → first review submitted): the span a maintainer controls after claiming — stored as the `median_time_to_review_hours` responsiveness stat.

These are computed from the `github-pr-timeline` index by `ClaimRecordReader` (assembling claim, pending-review-label, and first-review timings) and `ReviewLatencyAnalyzer` (the pure scoring + median math). Label-applied / "issues triaged" scoring (#9) remains deferred — it needs per-label event scoring, see [GitHub Timeline-Events Sync](github-timeline-events.md).

Lapse detection is just a query: `last_contribution_at` older than a threshold (e.g. 90 days) among users with a meaningful prior score. This is the hook for the retention loop in phase 6 (nudges, "we miss you" surfacing).

## Segmented boards (instead of one global ranking)

A single all-time board entrenches the top 3 and tells everyone else they don't matter — the exact reason lapsed contributors don't come back. Ship these *segments* instead, each a thin query/filter over the precomputed tables:

| Board | Definition | Why it exists |
|---|---|---|
| **New contributor spotlight** | `first_contribution_at` within `spotlight.window_days` (default 30); links to their first PR/issue (`first_contribution_url`) | Celebrates entry, where motivation is most fragile |
| **Rising** | largest positive delta `score − rising_baseline_score`, where the baseline is the contributor's score as of `rising.window_days` ago (default 7), read from `github_score_snapshots` | Lets newcomers rank without beating veterans; the window is a fixed timeframe, not "since the last compute run" |
| **Recently active** | `last_contributor_at` within 14 days (contributor activity only — maintainer reviews don't count), by contributor score | Refreshes constantly; not permanently owned by incumbents |
| **Comebacks** | `returned_after_days` is set, ordered by gap length; links to the return PR/issue (`comeback_url`) | Welcomes back long-dormant contributors — directly serves re-engagement |
| **This month / quarter / year** | score over the Calendar Period | Time-boxed competition with a clear reset |
| **All-time** | *intentionally omitted as a default* | Entrenches incumbents; available only as an opt-in deep view |

All segments reuse the existing Calendar Period selector and the same decayed Score, so there's one scoring engine behind every view.

## Testing (per CLAUDE.md)

PHPUnit feature tests, using factories, covering: scoring math against fixtures, decay boundaries (364 vs 366 days), point-in-time attribution across a mid-history job change, bot + `excluded` filtering, and org rollup including "Unknown". Run with `php artisan test --compact --filter=...`.

## Status & remaining work

This spec is the design reference (how and why). For current status and what's left to build — in priority order — see [Leaderboard Rollout — Backlog & Caveats](leaderboard-rollout.md).
