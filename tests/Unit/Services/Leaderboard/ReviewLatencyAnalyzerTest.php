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
    public function testPrClaimedScoresFlatRegardlessOfTimeToClaim(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');
        $pending = $claimed->copy()->subDays(100);

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(1, 'maint', $claimed, $pending, $claimed->copy()->addHours(5)),
        ]);

        $event = $result['events'][0];
        $this->assertSame(Action::PR_CLAIMED, $event->action);
        $this->assertSame(Board::MAINTAINER, $event->board);
        $this->assertSame(1.0, $event->impact); // flat — no staleness scaling
        // Time-to-claim is still measured for the responsiveness stat.
        $this->assertEqualsWithDelta(100.0, $result['stats']['maint']['median_time_to_claim_days'], 0.1);
    }

    public function testClaimedEventCarriesPrTitleAndLink(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer('magento/magento2'))->analyze([
            new ClaimRecord(42, 'maint', $claimed, $claimed->copy()->subDays(10), $claimed->copy()->addHours(5)),
        ]);

        $event = $result['events'][0];
        $this->assertSame('PR #42', $event->title);
        $this->assertSame('https://github.com/magento/magento2/pull/42', $event->url);
    }

    public function testClaimedEventOmitsLinkWhenRepoUnknown(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(42, 'maint', $claimed, null, $claimed->copy()->addHours(5)),
        ]);

        $this->assertSame('PR #42', $result['events'][0]->title);
        $this->assertNull($result['events'][0]->url);
    }

    public function testMissingPendingReviewGivesBaseImpactAndNullClaimStat(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(2, 'maint', $claimed, null, $claimed->copy()->addHours(2)),
        ]);

        $this->assertSame(1.0, $result['events'][0]->impact);
        $this->assertNull($result['stats']['maint']['median_time_to_claim_days']);
    }

    public function testClaimWithoutAReviewIsNotCredited(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            new ClaimRecord(9, 'ghost', $claimed, $claimed->copy()->subDays(50), null),
        ]);

        $this->assertSame([], $result['events']);
        $this->assertArrayNotHasKey('ghost', $result['stats']);
    }

    public function testReviewBeforeClaimIsNotCredited(): void
    {
        $claimed = Carbon::parse('2026-06-01T00:00:00Z');

        $result = (new ReviewLatencyAnalyzer)->analyze([
            // Review submitted 3h *before* the self-assignment — must be ignored,
            // not scored as 3h of positive latency via abs().
            new ClaimRecord(7, 'maint', $claimed, null, $claimed->copy()->subHours(3)),
        ]);

        $this->assertSame([], $result['events']);
        $this->assertArrayNotHasKey('maint', $result['stats']);
    }

    public function testTimeToReviewMedianAndCount(): void
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
