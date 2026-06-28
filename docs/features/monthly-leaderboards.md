# Monthly Leaderboards — Spec

> Status: proposed. Per-calendar-month contributor (and maintainer) score boards for the **trailing 12 months**, modelled on package-maven's `/leaderboard/monthly/YYYY-MM`. Additive to the rolling-12 boards in [Weighted Scoring](leaderboard-scoring.md) — nothing about the existing boards changes.

## Goal

Give each calendar month its own board with a stable, shareable URL (`scores/monthly/{board}/2026-06`) and month-to-month navigation. A month is a discrete bucket, so — unlike a free date-range picker — there is no recency-decay ambiguity and the result is cheap to precompute and cache.

## Scope

- **In:** contributor and maintainer monthly boards (the `window` machinery is per-board already, so both come almost for free).
- **Out (v1):** monthly **company** rollup (adds point-in-time org aggregation per month; defer until asked), and per-month drill-down ("Details"). Engagement segments (Rising, Comebacks, Recently active, newcomer spotlight) are now-relative and never appear on a historical month.
- **Window:** only the **last 12 months** (current month + the previous 11) are built, served, and linked. Older months 404; future months 404.

## Scoring semantics (the one real decision)

A monthly score is **`Σ weight × impact`** for the events whose event-date falls in that calendar month — **with recency decay turned off**. The month *is* the window, so decaying toward "now" makes no sense and would make closed months drift every run.

- **Impact weighting is kept** (a 400-line PR still outscores a typo; the cap still prevents PR-splitting).
- **Bucketing is by event date in UTC** (`date->format('Y-m')`), matching how timestamps are indexed. Document the UTC choice; a contribution near a month boundary lands deterministically.
- **Merge bonuses bucket by the merge event's date**, so a PR opened in May and merged in June contributes `pr_opened` to May and the `pr_merged` author bonus to June. That is the intended behaviour, and it matches activity-date bucketing.
- **Same weights, same `Action` enum, same self-review exclusion, same eligibility gating and bot filtering** — events are gated/filtered exactly once (as today) before being bucketed, so a monthly board can never include an action the rolling board would have rejected.

Consequence to label clearly in the UI: a person's June monthly number **will not equal** their contribution to the rolling-12 board (no decay, fixed window). The monthly view must read as a *month total*, not "the score."

## Data model

Reuse `leaderboard_entries` as-is — **no migration**. The existing `window` column already distinguishes board variants:

- Rolling board rows: `window = 'rolling12'` (unchanged).
- Monthly rows: `window = 'YYYY-MM'` (e.g. `'2026-06'`).

Unique key stays `(login, board, window)`; `score`, `breakdown`, `rank`, `computed_at` are reused. `github_user_stats` is **not** touched (no engagement signals on monthly boards). Verify an index on `(board, window, rank)` exists for fast reads; add one if not.

`config/leaderboard.php`:

```php
'monthly' => [
    'months_back' => 12, // current month + previous 11 are built/served/linked
],
```

> Coupling to watch: the compute job reads events from `now − recency.window_days` (365). The earliest month start needed (≈11 months ago) is < 365 days, so the already-loaded event set covers all 12 months — **no extra OpenSearch reads**. If `months_back` is ever raised above ~12, raise `recency.window_days` or add a dedicated read, or the oldest month will be under-filled.

## Scorer (`LeaderboardScorer`)

Pure, unit-tested additions; existing `summarize()` untouched:

- `pointsFlat(ScoredEvent): float` — `base × clamped impact`, **no** `recencyFactor`.
- `summarizeByMonth(array $events, array $allowedMonths): array` — returns
  `['YYYY-MM' => ['login' => ['contributor_score','maintainer_score','breakdown']]]`,
  keeping only events whose `Y-m` is in `$allowedMonths`. No engagement, no dates.

## Compute (`leaderboard:compute`)

After the rolling-12 rows are written, reusing the **already-read, already-gated** `$events`:

1. `$allowedMonths` = current `Y-m` plus the previous `months_back − 1`.
2. `$byMonth = $scorer->summarizeByMonth($events, $allowedMonths)`.
3. Build entry rows for each month/board/login (`window = 'YYYY-MM'`, `score`, `breakdown`) and upsert on `(login, board, window)`.
4. **Evict the rolled-off 13th month and emptied months:** delete `leaderboard_entries` where `window != 'rolling12'` and `window NOT IN $allowedMonths`. (Never touches rolling rows.)
5. **Rank per window:** generalize `assignRanks()` to iterate `['rolling12', ...$allowedMonths]` × boards instead of just `rolling12` × boards.

Recompute all 12 months every run (free — events are in memory). This self-heals closed months when a PR's merge data changes within the 365-day read window. A later optimization (skip immutable closed months, recompute only current + previous) is possible but unnecessary for v1; note it and move on.

## Routes

```php
Route::get('scores/monthly/{board}', [ScoreLeaderboardController::class, 'monthlyIndex'])->name('scores.monthly.index'); // → redirect to current month
Route::get('scores/monthly/{board}/{ym}', [ScoreLeaderboardController::class, 'monthly'])
    ->where('ym', '[0-9]{4}-[0-9]{2}')
    ->name('scores.monthly');
```

`board ∈ {contributor, maintainer}`. `monthlyIndex` redirects to `scores.monthly` for the current `Y-m`.

## Controller

`monthly(string $board, string $ym)`:

- 404 unless `board` is contributor/maintainer, `ym` is a real month, `ym` is within `$allowedMonths` (enforces "only 12 months back"), and `ym` is not in the future.
- Query `LeaderboardEntry` where `board`, `window = $ym`, `score > 0`, ordered by `rank`.
- Build `profiles` via the existing `profilesFor()`.
- Pass month-nav data: the 12 allowed months as `{ym, label}` (e.g. `2026-06 → "Jun 2026"`), current marked active, plus bounded prev/next.

## Views

New `resources/views/leaderboard/score-monthly.blade.php` (reuses the rolling board's row markup — avatar, name + handle, score badge, per-row month-scoped breakdown expansion):

- **Subtitle:** "June 2026 — points earned that month (impact-weighted, no recency decay)."
- **Month nav:** a horizontal, wrapping list of the 12 months (newest → oldest), current highlighted, each linking to `scores.monthly`; matches the package-maven affordance and is bounded to the 12-month window.
- **Breakdown** stays (the row's `breakdown` JSON is already month-scoped and accurate). **Omit the "Details" drill-down in v1.** The rolling board's `detail()` cannot be reused for a month: it runs `LeaderboardScorer::points()`, which applies **recency decay** anchored to the entry's `computed_at`, whereas monthly scores are **flat** (no decay). Reusing it would be wrong on both axes — wrong scoring mode *and* wrong timestamp. A correct per-month detail would need the flat scorer (`pointsFlat` / `summarizeByMonth`) and month bucketing, and is best built on persisted line items (see Caveats). Future item.
- **Header h1:** extend `components/header.blade.php` so `scores.monthly` renders "{Board} Leaderboard — {Month Year}".
- **Discoverability:** add a "Monthly" entry to `_tabs` (links to `scores.monthly.index` for the current board) so the rolling and monthly views cross-link.

The "How are scores tallied?" modal is reused, but for monthly it must **drop the recency bullet** and state impact still applies — pass a `decay = false` flag into `scoringExplainer()`/the modal partial. The per-action point list (`scoredList`) is unchanged.

## Tasks (phased)

1. **Scorer** — `pointsFlat()` + `summarizeByMonth()` + unit tests (bucketing by UTC `Y-m`, no decay, ignores out-of-range months).
2. **Compute** — month bucketing, per-month upsert, window-aware `assignRanks()`, stale-month eviction; feature tests (12 months written, 13th evicted, ranks per window).
3. **Routes + controller** — `monthly()`/`monthlyIndex()` with in-range/real/not-future guards; feature tests (valid month renders; out-of-range and future → 404; current-month redirect).
4. **View** — `score-monthly.blade.php`, month nav, header label, `_tabs` link, decay-off modal; view tests.
5. **Docs** — finalize this file; update [rollout](leaderboard-rollout.md) (Completed + `monthly.months_back` tunable) and the segments note in [leaderboard-scoring.md](leaderboard-scoring.md).

## Caveats

- **UTC bucketing** is a deliberate, documented choice; boundary contributions land deterministically.
- **No decay** means monthly numbers differ from the rolling board by design — label them as month totals.
- **`months_back` is coupled to `recency.window_days`** (see Data model note).
- **Late merges/edits** within the 365-day read window are corrected because all 12 months are recomputed each run.
- **Company monthly and per-month drill-down are explicitly deferred.**
- **Detail re-derivation is the real blocker for monthly drill-down.** The rolling `detail()` page re-derives points from `ContributionDetailReader` rather than reading what compute stored; its total is anchored to `computed_at` to avoid decay drift, but it still surfaces a *different event set* than the board sum, so the two never reconcile exactly. The durable fix — persisting per-contribution line items during `leaderboard:compute` and having detail read them — would both reconcile the rolling detail total and make monthly Details nearly free (filter by `Y-m`). Tracked in [rollout](leaderboard-rollout.md); a prerequisite for monthly Details, not part of monthly v1.
