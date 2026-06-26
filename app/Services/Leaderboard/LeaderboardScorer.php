<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\ScoredEvent;
use Carbon\CarbonInterface;

/**
 * Pure scoring logic: applies weight x impact x recency decay to scored events
 * and derives per-contributor engagement signals. No I/O — fully unit-testable.
 */
class LeaderboardScorer
{
    /**
     * @param  array<string, array<string, int|float>>  $weights  board => action => base weight
     */
    public function __construct(
        private readonly array $weights,
        private readonly float $impactMin,
        private readonly float $impactMax,
        private readonly int $windowDays,
        private readonly int $halfLifeDays,
    ) {
        if ($halfLifeDays <= 0) {
            throw new \InvalidArgumentException("halfLifeDays must be positive, got {$halfLifeDays}.");
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            weights: (array) config('leaderboard.weights', []),
            impactMin: (float) config('leaderboard.impact.min', 1.0),
            impactMax: (float) config('leaderboard.impact.max', 5.0),
            windowDays: (int) config('leaderboard.recency.window_days', 365),
            halfLifeDays: (int) config('leaderboard.recency.half_life_days', 182),
        );
    }

    /**
     * Impact weight from PR size, clamped to [min, max]. A typo and a 400-line
     * change should not be worth the same, but the cap prevents PR-splitting farming.
     */
    public static function impactFromSize(int $additions, int $deletions, float $min = 1.0, float $max = 5.0): float
    {
        $size = max($additions + $deletions, 1);
        $impact = 1.0 + (log10($size) / 2);

        return max($min, min($max, $impact));
    }

    public function recencyFactor(CarbonInterface $date, CarbonInterface $now): float
    {
        $ageDays = abs($date->diffInDays($now));

        if ($ageDays > $this->windowDays) {
            return 0.0;
        }

        return 2 ** (-$ageDays / $this->halfLifeDays);
    }

    public function points(ScoredEvent $event, CarbonInterface $now): float
    {
        $base = (float) ($this->weights[$event->board->value][$event->action->value] ?? 0);

        if ($base === 0.0) {
            return 0.0;
        }

        $impact = max($this->impactMin, min($this->impactMax, $event->impact));

        return $base * $impact * $this->recencyFactor($event->date, $now);
    }

    /**
     * Aggregate events per contributor into scores, a per-action breakdown, and
     * engagement signals.
     *
     * @param  list<ScoredEvent>  $events
     * @return array<string, array<string, mixed>> keyed by login
     */
    public function summarize(array $events, CarbonInterface $now): array
    {
        $users = [];

        foreach ($events as $event) {
            $login = $event->login;

            if (! isset($users[$login])) {
                $users[$login] = [
                    'contributor_score' => 0.0,
                    'maintainer_score' => 0.0,
                    'breakdown' => [],
                    'dates' => [],
                ];
            }

            $board = $event->board->value;
            $action = $event->action->value;
            $points = $this->points($event, $now);
            $users[$login][$board.'_score'] += $points;

            $bucket = $users[$login]['breakdown'][$board][$action] ?? ['count' => 0, 'points' => 0.0];
            $bucket['count']++;
            $bucket['points'] = round($bucket['points'] + $points, 4);
            $users[$login]['breakdown'][$board][$action] = $bucket;

            $users[$login]['dates'][] = $event->date;
        }

        foreach ($users as &$data) {
            $engagement = $this->engagement($data['dates'], $now);
            unset($data['dates']);
            $data['contributor_score'] = round($data['contributor_score'], 4);
            $data['maintainer_score'] = round($data['maintainer_score'], 4);
            $data = array_merge($data, $engagement);
        }
        unset($data);

        return $users;
    }

    /**
     * @param  list<CarbonInterface>  $dates
     * @return array{first_contribution_at: CarbonInterface, last_contribution_at: CarbonInterface, current_gap_days: int, current_streak_weeks: int, longest_streak_weeks: int}
     */
    private function engagement(array $dates, CarbonInterface $now): array
    {
        usort($dates, fn (CarbonInterface $a, CarbonInterface $b) => $a->getTimestamp() <=> $b->getTimestamp());
        $first = $dates[0];
        $last = $dates[array_key_last($dates)];

        $weeks = [];
        foreach ($dates as $date) {
            $weeks[intdiv($date->getTimestamp(), 604800)] = true;
        }
        $weekIndexes = array_keys($weeks);
        sort($weekIndexes);

        $longest = 1;
        $run = 1;
        for ($i = 1, $count = count($weekIndexes); $i < $count; $i++) {
            $run = $weekIndexes[$i] === $weekIndexes[$i - 1] + 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        // "Current" must be anchored to now, not to the last active week — an
        // inactive contributor has a current streak of 0. A one-week grace keeps
        // an active contributor from dropping to 0 before they commit this week.
        $activeWeeks = array_flip($weekIndexes);
        $nowWeek = intdiv($now->getTimestamp(), 604800);
        $anchor = isset($activeWeeks[$nowWeek]) ? $nowWeek : $nowWeek - 1;

        $current = 0;
        for ($week = $anchor; isset($activeWeeks[$week]); $week--) {
            $current++;
        }

        return [
            'first_contribution_at' => $first,
            'last_contribution_at' => $last,
            'current_gap_days' => (int) abs($last->diffInDays($now)),
            'current_streak_weeks' => $current,
            'longest_streak_weeks' => $longest,
        ];
    }
}
