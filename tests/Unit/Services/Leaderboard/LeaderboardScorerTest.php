<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
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
                'maintainer' => ['review_approved' => 3, 'approved_then_merged' => 6],
            ],
            impactMin: 1.0,
            impactMax: 5.0,
            windowDays: 365,
            halfLifeDays: 182,
        );
    }

    public function testPointsApplyWeightWithFullRecencyForToday(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $event = new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now);

        $this->assertSame(3.0, $this->scorer()->points($event, $now));
    }

    public function testLastContributorAtIgnoresMaintainerActivity(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');

        $summary = $this->scorer()->summarize([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subDays(5)),
            new ScoredEvent('jane', Board::MAINTAINER, Action::REVIEW_APPROVED, $now), // newer, but maintainer
        ], $now);

        $this->assertTrue($summary['jane']['last_contributor_at']->equalTo($now->copy()->subDays(5)));
    }

    public function testLastContributorAtIsNullWithoutContributorActivity(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');

        $summary = $this->scorer()->summarize([
            new ScoredEvent('mod', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
        ], $now);

        $this->assertNull($summary['mod']['last_contributor_at']);
    }

    public function testApprovedThenMergedBonusAppliesImpact(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $event = new ScoredEvent('maintainer1', Board::MAINTAINER, Action::APPROVED_THEN_MERGED, $now, 2.0);

        // base 6 × impact 2.0 × full recency 1.0
        $this->assertSame(12.0, $this->scorer()->points($event, $now));
    }

    public function testUnknownActionScoresZero(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $event = new ScoredEvent('jane', Board::CONTRIBUTOR, Action::ISSUE_OPENED, $now);

        $this->assertSame(0.0, $this->scorer()->points($event, $now));
    }

    public function testRecencyHalvesAtHalfLife(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $factor = $this->scorer()->recencyFactor($now->copy()->subDays(182), $now);

        $this->assertEqualsWithDelta(0.5, $factor, 0.01);
    }

    public function testRecencyIsZeroOutsideWindow(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');

        $this->assertSame(0.0, $this->scorer()->recencyFactor($now->copy()->subDays(400), $now));
    }

    public function testConstructorThrowsOnZeroHalfLife(): void
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

    public function testImpactFromSizeFloorsAndCaps(): void
    {
        $this->assertSame(1.0, LeaderboardScorer::impactFromSize(0, 0));
        $this->assertSame(5.0, LeaderboardScorer::impactFromSize(100_000_000, 100_000_000));
        $this->assertGreaterThan(1.0, LeaderboardScorer::impactFromSize(200, 200));
    }

    public function testSummarizeKeepsRolesSeparateWithBreakdown(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            new ScoredEvent('jane', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
        ], $now);

        $this->assertSame(3.0, $summary['jane']['contributor_score']);
        $this->assertSame(3.0, $summary['jane']['maintainer_score']);
        $this->assertSame(1, $summary['jane']['breakdown']['contributor']['pr_opened']['count']);
        $this->assertSame(1, $summary['jane']['breakdown']['maintainer']['review_approved']['count']);
    }

    public function testSummarizeComputesStreakAndGap(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subWeeks(1)),
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subWeeks(2)),
        ], $now);

        $this->assertSame(3, $summary['jane']['current_streak_weeks']);
        $this->assertSame(3, $summary['jane']['longest_streak_weeks']);
        $this->assertSame(0, $summary['jane']['current_gap_days']);
        $this->assertTrue($summary['jane']['first_contribution_at']->equalTo($now->copy()->subWeeks(2)));
    }

    public function testCurrentStreakIsZeroForInactiveContributor(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('rip', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subWeeks(26)),
            new ScoredEvent('rip', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subWeeks(27)),
        ], $now);

        $this->assertSame(0, $summary['rip']['current_streak_weeks']);
        $this->assertSame(2, $summary['rip']['longest_streak_weeks']);
    }

    public function testCurrentStreakAllowsOneWeekGrace(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $summary = $this->scorer()->summarize([
            new ScoredEvent('mid', Board::CONTRIBUTOR, Action::PR_OPENED, $now->copy()->subWeeks(1)),
        ], $now);

        $this->assertSame(1, $summary['mid']['current_streak_weeks']);
    }

    public function testPointsFlatAppliesImpactWithoutRecencyDecay(): void
    {
        // 100+ days old: points() would decay it, pointsFlat() must not.
        $old = new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_MERGED, Carbon::parse('2026-01-01T00:00:00Z'), 2.0);

        // base 10 × impact 2.0, no recency factor
        $this->assertSame(20.0, $this->scorer()->pointsFlat($old));
    }

    public function testPointsFlatIsZeroForUnweightedAction(): void
    {
        $event = new ScoredEvent('jane', Board::CONTRIBUTOR, Action::ISSUE_OPENED, Carbon::parse('2026-06-01T00:00:00Z'));

        $this->assertSame(0.0, $this->scorer()->pointsFlat($event));
    }

    public function testSummarizeByMonthBucketsByUtcMonthWithoutDecay(): void
    {
        $months = $this->scorer()->summarizeByMonth([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-06-15T12:00:00Z')),
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-05-20T12:00:00Z')),
            new ScoredEvent('jane', Board::MAINTAINER, Action::REVIEW_APPROVED, Carbon::parse('2026-06-01T00:00:00Z')),
        ], ['2026-06', '2026-05']);

        // No decay: each pr_opened is worth its full base weight of 3.
        $this->assertSame(3.0, $months['2026-06']['jane']['contributor_score']);
        $this->assertSame(3.0, $months['2026-06']['jane']['maintainer_score']);
        $this->assertSame(3.0, $months['2026-05']['jane']['contributor_score']);
        $this->assertSame(1, $months['2026-06']['jane']['breakdown']['contributor']['pr_opened']['count']);
    }

    public function testSummarizeByMonthBucketsUsingUtcNotLocalOffset(): void
    {
        // 2026-06-01T01:30+02:00 is 2026-05-31T23:30Z — must land in May.
        $months = $this->scorer()->summarizeByMonth([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-06-01T01:30:00+02:00')),
        ], ['2026-06', '2026-05']);

        $this->assertArrayNotHasKey('2026-06', $months);
        $this->assertSame(3.0, $months['2026-05']['jane']['contributor_score']);
    }

    public function testSummarizeByMonthIgnoresMonthsOutsideAllowedList(): void
    {
        $months = $this->scorer()->summarizeByMonth([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2024-01-01T00:00:00Z')),
        ], ['2026-06', '2026-05']);

        $this->assertSame([], $months);
    }
}
