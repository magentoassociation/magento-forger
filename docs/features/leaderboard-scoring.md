# Weighted Scoring & Point-in-Time Company Leaderboards

> **Status:** Build spec. **Supersedes** the raw-count-only direction of ADR 0001 and ADR 0002 by decision (2026-06-20). The existing per-metric raw-count boards are *kept* for transparency; this adds a weighted **Score** layer and a **Company** board on top of them.
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
| 8 | Maintainer | **Approved PR later merged** (approver bonus) | join reviews → PR `merged_at` | derivable | ⚙️ compute-side join |
| 9 | Maintainer | Label applied | — timeline events | — | ⛔ not event-sourced (deferred) |
| 10 | Contributor | Comment on issue/PR | `github-interactions` (`type=comment`) | `created_at` | ✅ available — optional, see caveat |

> **Comment scoring caveat:** comments are now available (the interactions index is live), but they are the easiest signal to farm with low-value chatter. If scored at all, give them a low base weight and cap points per thread/day. Recommended: leave comments *out* of the Score initially and revisit, rather than incentivize noise on `magento/magento2`.

### Required sync additions before build

- **PR size** for impact weighting: add `additions`, `deletions`, `changedFiles` to `github_pull_requests` GraphQL and `OpenSearchService::toPullRequestDocument()`. Currently absent — impact weight is the whole point of the merge bonus, so this is a hard prerequisite. Backfill historical PRs.
- **(Optional)** PR/issue author `profile company` to seed org resolution.
- **(Deferred)** A timeline-events index to event-source label application (#9). Until then, maintainer scoring uses reviews only.

## Scoring formula

```
points(event) = base_weight[action] × impact_weight(event) × recency_factor(event_date)
```

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

Each scored event is attributed to the org the actor belonged to **on the contribution date**:

| Event | Attribution date |
|---|---|
| Issue/PR opened (1, 2) | `created_at` |
| PR merged — author bonus (3) | **PR `created_at`** (when the work was done, *not* `merged_at`) |
| Issue resolved by merged PR (4) | issue `created_at` |
| Reviews (5–7) | `submitted_at` |
| Approved-then-merged — approver bonus (8) | review `submitted_at` |

`Company Score` = Σ member event points, attributed point-in-time, with the same decay. A contributor who changes employer keeps their personal score; their *past* org keeps the credit for *past* work.

### Org resolution pipeline

Candidate org from, in order: (1) email domain → `organizations.domains`, (2) normalized GitHub profile company, (3) registered `User.company`. **Manual override in Filament is the source of truth.** Each membership carries a `source` and `confidence`; low-confidence rows land in a needs-review queue. Unresolved actors roll up to **"Independent / Unknown"** rather than being hidden.

## New data model

```
organizations
  id, name, slug, type [agency|merchant|adobe|independent|unknown],
  domains json, created_at, updated_at

github_users                          # contributor/maintainer dimension (distinct from auth Users)
  id, login (unique), github_id, display_name, avatar_url,
  is_bot bool, profile_company_raw nullable,
  excluded bool, excluded_reason nullable, created_at, updated_at

user_org_memberships                  # point-in-time
  id, github_user_id, organization_id,
  from_date, to_date nullable,
  source [manual|domain|profile|user], confidence tinyint,
  created_at, updated_at

leaderboard_entries                   # precomputed individual scores
  id, github_user_id, board [contributor|maintainer],
  window [rolling12|period], period_key nullable,
  score decimal, breakdown json, computed_at

org_leaderboard_entries               # precomputed company scores
  id, organization_id, board, window, period_key nullable,
  score decimal, member_count int, breakdown json, computed_at
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
- `github_users.excluded` flag (set in Filament, Drupal-style) removes bad actors from all score boards.
- Guard `author == reviewer` so self-review can't earn the approver bonus.

## UI

- **Public:** new "Contributor Score", "Maintainer Score", and "Company" boards, defaulting to the rolling-12 window and reusing the Calendar Period selector. Each row is **expandable to show its breakdown** (action counts → weighted points) so the score stays transparent — this directly answers ADR 0001's "opaque weights" objection.
- **Filament admin:** `Organizations` resource; `Memberships` resource with a point-in-time editor and a needs-review filter; a read-only scoring-weights view (with version); an exclusions/abuse tool.

## Engagement signals (re-engagement layer)

Scores rank impact; these signals power *getting lapsed contributors back* — the original goal. Computed per `github_user` in the same job, stored in a new `github_user_stats` table:

```
github_user_stats
  id, github_user_id,
  first_contribution_at, last_contribution_at,
  current_gap_days,            # now - last_contribution_at
  current_streak_weeks,        # consecutive weeks with ≥1 contribution
  longest_streak_weeks,
  contributor_score_prev,      # prior window, for "Rising" delta
  maintainer_score_prev,
  median_review_latency_hours, # maintainers only
  reviews_in_window,
  computed_at
```

| Signal | Definition | Source |
|---|---|---|
| First / last contribution | min/max event date across all scored actions | existing indexes |
| Current gap | days since `last_contribution_at` — drives lapse detection | derived |
| Streak | consecutive weeks with ≥1 contribution | derived |
| Time-to-review (maintainer-controlled) | median hours from reviewer **self-assignment** to that maintainer's first `submitted_at` | ⛔ needs assignment timeline event — **deferred** |
| Responsiveness | share of self-assigned PRs reviewed within N days | ⛔ same dependency — **deferred** |

### Review workflow (Magento-specific) and why latency is deferred

Contributors do **not** request reviews. The flow is:

1. **Adobe** applies the `Progress: pending review` label → the PR enters the pool available for review.
2. A **maintainer self-assigns** as reviewer — this can be months later.
3. The maintainer submits their review.

This produces two different clocks:

- **Time-to-claim** (`Progress: pending review` applied → self-assignment): a *backlog / project-health* metric, **not** attributable to any individual maintainer. Do not put this on a maintainer board.
- **Time-to-review** (self-assignment → first review submitted): the only span a maintainer controls, so the only fair per-maintainer responsiveness signal.

Both require event-sourced timing that the current sync lacks — `labels[]` is only a snapshot and reviewer assignment isn't captured at all. **Review latency, responsiveness, "issues triaged", and label-applied scoring (#9) are all deferred until a timeline-events index lands** — scoped in [GitHub Timeline-Events Sync](github-timeline-events.md). Until then, maintainer signals use review submissions (approve/reject/comment) only. `created_at` is *not* an acceptable proxy — a PR can sit unclaimed for months before any maintainer is responsible for it.

Lapse detection is just a query: `last_contribution_at` older than a threshold (e.g. 90 days) among users with a meaningful prior score. This is the hook for the retention loop in phase 6 (nudges, "we miss you" surfacing).

## Segmented boards (instead of one global ranking)

A single all-time board entrenches the top 3 and tells everyone else they don't matter — the exact reason lapsed contributors don't come back. Ship these *segments* instead, each a thin query/filter over the precomputed tables:

| Board | Definition | Why it exists |
|---|---|---|
| **New contributor spotlight** | `first_contribution_at` within the current period | Celebrates entry, where motivation is most fragile |
| **Rising** | largest positive delta `score − score_prev` | Lets newcomers rank without beating veterans |
| **Recently active** | ranked within the rolling-12 window (decayed) | Refreshes constantly; not permanently owned by incumbents |
| **This month / quarter / year** | score over the Calendar Period | Time-boxed competition with a clear reset |
| **All-time** | *intentionally omitted as a default* | Entrenches incumbents; available only as an opt-in deep view |

All segments reuse the existing Calendar Period selector and the same decayed Score, so there's one scoring engine behind every view.

## Testing (per CLAUDE.md)

PHPUnit feature tests, using factories, covering: scoring math against fixtures, decay boundaries (364 vs 366 days), point-in-time attribution across a mid-history job change, bot + `excluded` filtering, and org rollup including "Independent / Unknown". Run with `php artisan test --compact --filter=...`.

## Build phases

1. **Sync** — add `additions`/`deletions`/`changedFiles` to PR GraphQL + document; backfill.
2. **Org model** — tables, resolution pipeline, Filament admin.
3. **Scoring engine** — `config/leaderboard.php`, compute job → individual score boards.
4. **Company rollup** — point-in-time org aggregation → company board.
5. **Engagement signals** — `github_user_stats` (first/last contribution, streak, gap, review latency) computed in the same job.
6. **Segmented boards** — New contributor spotlight, Rising, Recently active; all-time demoted to opt-in.
7. **Transparency UI** — breakdown expansion + abuse tooling.
8. **(Separate spec, recommended next)** retention loop — good-first-issue queue from triage labels, lapse nudges driven by `current_gap_days`.
