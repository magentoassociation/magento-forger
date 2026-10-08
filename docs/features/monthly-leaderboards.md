# Monthly Leaderboards — Spec

> Status: implemented. Per-calendar-month contributor (and maintainer) score boards for the **trailing 12 months**, modelled on package-maven's `/leaderboard/monthly/YYYY-MM`. Additive to the rolling-12 boards in [Weighted Scoring](leaderboard-scoring.md) — nothing about the existing boards changes.

## Goal

Give each calendar month its own board with a stable, shareable URL (`leaderboard/monthly/{board}/2026-06`) and month-to-month navigation. A month is a discrete bucket, so — unlike a free date-range picker — there is no recency-decay ambiguity and the result is cheap to precompute and cache.

## Scope

- **In:** contributor and maintainer monthly boards (the `window` machinery is per-board already, so both come almost for free).
- **Out (v1):** monthly **company** rollup (adds point-in-time org aggregation per month; defer until asked). Engagement segments (Rising, Comebacks, Recently active, newcomer spotlight) are now-relative and never appear on a historical month. (Per-month drill-down "Details" was deferred in v1 but has since shipped — see below.)
- **Window:** only the **last 12 months** (current month + the previous 11) are built, served, and linked. Older months 404; future months 404.

## Scoring semantics (the one real decision)

A monthly score is **`Σ weight × impact`** for the events whose event-date falls in that calendar month — **with recency decay turned off**. The month *is* the window, so decaying toward "now" makes no sense and would make closed months drift every run.

- **Impact weighting is kept** (a `Priority: P0` item still outscores an unlabeled one; the cap still bounds it) — sourced from priority labels, same as the rolling board.
- **Bucketing is by event date in UTC** (`date->format('Y-m')`), matching how timestamps are indexed. Document the UTC choice; a contribution near a month boundary lands deterministically.
- **Merge bonuses bucket by the merge event's date**, so a PR opened in May and merged in June contributes `pr_opened` to May and the `pr_merged` author bonus to June. That is the intended behaviour, and it matches activity-date bucketing.
- **Same weights, same `Action` enum, same self-review exclusion, same eligibility gating and bot filtering** — events are gated/filtered exactly once (as today) before being bucketed, so a monthly board can never include an action the rolling board would have rejected.

Consequence to label clearly in the UI: a person's June monthly number **will not equal** their contribution to the rolling-12 board (no decay, fixed window). The monthly view must read as a *month total*, not "the score."

## Data model

Reuse `leaderboard_entries` as-is — **no migration**. The existing `window` column already distinguishes board variants:

- Rolling board rows: `window = 'rolling12'` (unchanged).
- Monthly rows: `window = 'YYYY-MM'` (e.g. `'2026-06'`).

Unique key stays `(login, board, window)`; `score`, `breakdown`, `rank`, `computed_at` are reused. `github_user_stats` is **not** touched (no engagement signals on monthly boards). The read index is `(board, window, score)` (`leaderboard_entries` migration); reads order by `rank` then `score`.

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
Route::get('leaderboard/monthly/{board}', [ScoreLeaderboardController::class, 'monthlyIndex'])->name('leaderboard.monthly.index'); // → redirect to current month
Route::get('leaderboard/monthly/{board}/{ym}', [ScoreLeaderboardController::class, 'monthly'])
    ->where('ym', '[0-9]{4}-[0-9]{2}')
    ->name('leaderboard.monthly');
```

`board ∈ {contributor, maintainer}`. `monthlyIndex` redirects to `leaderboard.monthly` for the current `Y-m`.

## Controller

`monthly(string $board, string $ym)`:

- 404 unless `board` is contributor/maintainer, `ym` is a real month, `ym` is within `$allowedMonths` (enforces "only 12 months back"), and `ym` is not in the future.
- Query `LeaderboardEntry` where `board`, `window = $ym`, `score > 0`, ordered by `rank`.
- Build `profiles` via the existing `profilesFor()`.
- Pass month-nav data: the 12 allowed months as `{ym, label}` (e.g. `2026-06 → "Jun 2026"`), current marked active, plus bounded prev/next.

## Views

New `resources/views/leaderboard/score-monthly.blade.php` (reuses the rolling board's row markup — avatar, name + handle, score badge, per-row month-scoped breakdown expansion):

- **Subtitle:** "June 2026 — points earned that month (impact-weighted, no recency decay)."
- **Month nav:** a horizontal, wrapping list of the 12 months (newest → oldest), current highlighted, each linking to `leaderboard.monthly`; matches the package-maven affordance and is bounded to the 12-month window.
- **Breakdown** stays (the row's `breakdown` JSON is already month-scoped and accurate). **"Details" drill-down now shipped** (`leaderboard.monthly.detail`, `monthlyDetail()`): it reads the persisted line items, filters by `month`, and sums flat `points_flat`, so it reconciles with the monthly board. This sidesteps the reason it was deferred — the rolling `detail()` couldn't be reused because it applies recency decay anchored to `computed_at`, wrong on both scoring mode and timestamp for a month.
- **Header h1:** extend `components/header.blade.php` so `leaderboard.monthly` renders "{Board} Leaderboard — {Month Year}".
- **Discoverability:** add a "Monthly" entry to `_tabs` (links to `leaderboard.monthly.index` for the current board) so the rolling and monthly views cross-link.

The "How are scores tallied?" modal is reused, but for monthly it must **drop the recency bullet** and state impact still applies — pass a `decay = false` flag into `scoringExplainer()`/the modal partial. The per-action point list (`scoredList`) is unchanged.

## Tasks (phased) — all done

1. **Scorer** — `pointsFlat()` + `summarizeByMonth()` in `LeaderboardScorer`, plus the `App\Support\MonthlyWindow` helper (shared allowed-month set + `M Y` labels); unit tests cover UTC bucketing, no decay, and out-of-range exclusion.
2. **Compute** — month bucketing via `summarizeByMonth()`, `writeMonthlyEntries()` upsert, window-aware `assignRanks(['rolling12', ...months])`, stale-month eviction; feature tests cover months written, out-of-window exclusion, per-month ranks, and eviction. **Note:** eviction is implemented as "delete every `window != 'rolling12'` row, then re-insert the allowed months" — a superset of the spec's `NOT IN $allowedMonths` delete that also drops contributors who fell out of an otherwise-live month. Matches the "recompute all 12 months every run" contract.
3. **Routes + controller** — `monthly()`/`monthlyIndex()` with in-range/real/not-future guards (out-of-range and future both fall outside `MonthlyWindow::allowed()` → 404; the route regex rejects malformed `ym`); feature tests cover render, redirect, and the 404 cases.
4. **View** — `score-monthly.blade.php`, month nav, header label (`components/header.blade.php`), `_tabs` "Monthly" link, and the shared `_scoring-modal.blade.php` partial driven by a `decay` flag; controller feature tests assert the month heading, nav links, and absence of recency copy.
5. **Docs** — this file; [rollout](leaderboard-rollout.md) updated.

## Caveats

- **UTC bucketing** is a deliberate, documented choice; boundary contributions land deterministically.
- **No decay** means monthly numbers differ from the rolling board by design — label them as month totals.
- **`months_back` is coupled to `recency.window_days`** (see Data model note).
- **Late merges/edits** within the 365-day read window are corrected because all 12 months are recomputed each run.
- **Company monthly rollup is explicitly deferred.** (Per-month drill-down is built — see next bullet.)
- **Monthly drill-down is built.** `leaderboard:compute` persists per-contribution line items (`leaderboard_line_items`) carrying both rolling `points` and flat `points_flat` plus the UTC `month`. The rolling `detail()` sums `points` and the monthly `monthlyDetail()` (`leaderboard.monthly.detail`) filters by `month` and sums `points_flat`, so each drill-down reconciles with its board. See [rollout](leaderboard-rollout.md).
