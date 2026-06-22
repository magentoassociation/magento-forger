<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Services\Leaderboard\LeaderboardScorer;
use Carbon\Carbon;
use Tests\TestCase;

class LeaderboardScorerTest extends TestCase
{
    private function scorer(): LeaderboardScorer
    {
        return new LeaderboardScorer(
            weights: [
                'contributor' => ['pr_opened' => 3, 'pr_merged' => 10],
                'maintainer' => ['review_approved' => 3],
            ],
            impactMin: 1.0,
            impactMax: 5.0,
            windowDays: 365,
            halfLifeDays: 182,
        );
    }

    public function test_points_apply_weight_with_full_recency_for_today(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $event = new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now);

        $this->assertSame(3.0, $this->scorer()->points($event, $now));
    }

    public function test_unknown_action_scores_zero(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $event = new ScoredEvent('jane', Board::CONTRIBUTOR, 'mystery', $now);

        $this->assertSame(0.0, $this->scorer()->points($event, $now));
    }

    public function test_recency_halves_at_half_life(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $factor = $this->scorer()->recencyFactor($now->copy()->subDays(182), $now);

        $this->assertEqualsWithDelta(0.5, $factor, 0.01);
    }

    public function test_recency_is_zero_outside_window(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');

        $this->assertSame(0.0, $this->scorer()->recencyFactor($now->copy()->subDays(400), $now));
    }

    public function test_constructor_throws_on_zero_half_life(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LeaderboardScorer(
            weights: [],
            impactMin: 1.0,
            impactMax: 5.0,
            windowDays: 365,
            halfLifeDays: 0,
        );
    }

    public function test_impact_from_size_floors_and_caps(): void
    {
        $this->assertSame(1.0, LeaderboardScorer::impactFromSize(0, 0));
        $this->assertSame(5.0, LeaderboardScorer::impactFromSize(100_000_000, 100_000_000));
        $this->assertGreaterThan(1.0, LeaderboardScorer::impactFromSize(200, 200));
    }

    public function test_summarize_keeps_roles_separate_with_breakdown(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now),
            new ScoredEvent('jane', Board::MAINTAINER, 'review_approved', $now),
        ], $now);

        $this->assertSame(3.0, $summary['jane']['contributor_score']);
        $this->assertSame(3.0, $summary['jane']['maintainer_score']);
        $this->assertSame(1, $summary['jane']['breakdown']['contributor']['pr_opened']['count']);
        $this->assertSame(1, $summary['jane']['breakdown']['maintainer']['review_approved']['count']);
    }

    public function test_summarize_computes_streak_and_gap(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now),
            new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now->copy()->subWeeks(1)),
            new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now->copy()->subWeeks(2)),
        ], $now);

        $this->assertSame(3, $summary['jane']['current_streak_weeks']);
        $this->assertSame(3, $summary['jane']['longest_streak_weeks']);
        $this->assertSame(0, $summary['jane']['current_gap_days']);
        $this->assertTrue($summary['jane']['first_contribution_at']->equalTo($now->copy()->subWeeks(2)));
    }

    public function test_current_streak_is_zero_for_inactive_contributor(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('rip', Board::CONTRIBUTOR, 'pr_opened', $now->copy()->subWeeks(26)),
            new ScoredEvent('rip', Board::CONTRIBUTOR, 'pr_opened', $now->copy()->subWeeks(27)),
        ], $now);

        $this->assertSame(0, $summary['rip']['current_streak_weeks']);
        $this->assertSame(2, $summary['rip']['longest_streak_weeks']);
    }

    public function test_current_streak_allows_one_week_grace(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('mid', Board::CONTRIBUTOR, 'pr_opened', $now->copy()->subWeeks(1)),
        ], $now);

        $this->assertSame(1, $summary['mid']['current_streak_weeks']);
    }
}
