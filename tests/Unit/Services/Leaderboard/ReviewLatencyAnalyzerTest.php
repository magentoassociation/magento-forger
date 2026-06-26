<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ClaimRecord;
use App\Services\Leaderboard\ReviewLatencyAnalyzer;
use Carbon\Carbon;
use Tests\TestCase;

class ReviewLatencyAnalyzerTest extends TestCase
{
    public function test_pr_claimed_impact_scales_with_time_to_claim(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');
        $pending = $claimed->copy()->subDays(100);

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(1, 'maint', $claimed, $pending, $claimed->copy()->addHours(5)),
        ]);

        $event = $result['events'][0];
        $this->assertSame(Action::PR_CLAIMED, $event->action);
        $this->assertSame(Board::MAINTAINER, $event->board);
        $this->assertEqualsWithDelta(3.0, $event->impact, 0.05); // 1 + log10(100)
        $this->assertEqualsWithDelta(100.0, $result['stats']['maint']['median_time_to_claim_days'], 0.1);
    }

    public function test_missing_pending_review_gives_base_impact_and_null_claim_stat(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(2, 'maint', $claimed, null, $claimed->copy()->addHours(2)),
        ]);

        $this->assertSame(1.0, $result['events'][0]->impact);
        $this->assertNull($result['stats']['maint']['median_time_to_claim_days']);
    }

    public function test_claim_without_a_review_is_not_credited(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(9, 'ghost', $claimed, $claimed->copy()->subDays(50), null),
        ]);

        $this->assertSame([], $result['events']);
        $this->assertArrayNotHasKey('ghost', $result['stats']);
    }

    public function test_time_to_review_median_and_count(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(1, 'maint', $claimed, null, $claimed->copy()->addHours(10)),
            new ClaimRecord(2, 'maint', $claimed, null, $claimed->copy()->addHours(20)),
            new ClaimRecord(3, 'maint', $claimed, null, null), // claimed but not reviewed → excluded
        ]);

        $this->assertSame(15.0, $result['stats']['maint']['median_time_to_review_hours']);
        $this->assertSame(2, $result['stats']['maint']['reviews_in_window']);
        $this->assertCount(2, $result['events']);
    }
}
