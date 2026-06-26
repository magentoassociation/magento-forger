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
use App\Services\Leaderboard\CompanyScoreAggregator;
use App\Services\Leaderboard\LeaderboardScorer;
use App\Services\Leaderboard\MembershipResolver;
use Carbon\Carbon;
use Tests\TestCase;

class CompanyScoreAggregatorTest extends TestCase
{
    private function aggregator(): CompanyScoreAggregator
    {
        return new CompanyScoreAggregator(new LeaderboardScorer(
            weights: ['contributor' => ['pr_opened' => 3], 'maintainer' => []],
            impactMin: 1.0,
            impactMax: 5.0,
            windowDays: 365,
            halfLifeDays: 182,
        ));
    }

    public function test_attributes_events_to_org_active_at_event_date(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $resolver = new MembershipResolver([
            'jane' => [['org_id' => 5, 'from' => Carbon::parse('2020-01-01'), 'to' => null]],
        ]);

        $result = $this->aggregator()->aggregate([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
        ], $now, $resolver);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['organization_id']);
        $this->assertSame(3.0, $result[0]['contributor_score']);
        $this->assertSame(1, $result[0]['member_count']);
    }

    public function test_unresolved_contributor_goes_to_null_bucket(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');

        $result = $this->aggregator()->aggregate([
            new ScoredEvent('ghost', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
        ], $now, new MembershipResolver([]));

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['organization_id']);
        $this->assertSame(3.0, $result[0]['contributor_score']);
    }

    public function test_event_before_membership_start_is_unattributed(): void
    {
        $now = Carbon::parse('2026-06-01T00:00:00Z');
        $resolver = new MembershipResolver([
            'jane' => [['org_id' => 5, 'from' => Carbon::parse('2026-03-01'), 'to' => null]],
        ]);

        $result = $this->aggregator()->aggregate([
            new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-01-01T00:00:00Z')),
        ], $now, $resolver);

        $this->assertNull($result[0]['organization_id']);
    }
}
