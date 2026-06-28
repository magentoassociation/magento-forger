<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ClaimRecord;
use App\DataTransferObjects\Leaderboard\ScoredEvent;

/**
 * Pure analysis of review-claim timing. Produces:
 *  - `pr_claimed` scored events whose impact grows with how long the PR sat in
 *    the review pool before the maintainer claimed it (rewards clearing backlog).
 *    Only emitted when the maintainer actually reviewed the claimed PR — a claim
 *    without a follow-up review earns nothing.
 *  - per-maintainer responsiveness stats (median time-to-review, median
 *    time-to-claim, count of claimed PRs that were reviewed in the window).
 *
 * No I/O — fully unit-testable. The OpenSearch reads that build ClaimRecords
 * live in ClaimRecordReader.
 */
class ReviewLatencyAnalyzer
{
    /**
     * @param  list<ClaimRecord>  $claims
     * @return array{
     *     events: list<ScoredEvent>,
     *     stats: array<string, array{median_time_to_review_hours: float|null, median_time_to_claim_days: float|null, reviews_in_window: int}>
     * }
     */
    public function analyze(array $claims): array
    {
        $events = [];
        $perMaintainer = [];

        foreach ($claims as $claim) {
            // A claim is only credited once the maintainer reviews it *after*
            // claiming it. No review — or one submitted before the claim — earns
            // nothing, so self-assigning stale PRs (or back-dated reviews) can't
            // score. Signed diffs keep pre-claim reviews out rather than abs()
            // turning their negative latency into a positive score.
            if ($claim->firstReviewAt === null || $claim->firstReviewAt->lessThan($claim->claimedAt)) {
                continue;
            }

            $bucket = $perMaintainer[$claim->maintainer] ?? ['ttr' => [], 'ttc' => [], 'reviews' => 0];

            $staleness = 1.0;
            if ($claim->pendingReviewAt !== null) {
                $days = max(0.0, $claim->pendingReviewAt->diffInHours($claim->claimedAt, false) / 24);
                $bucket['ttc'][] = $days;
                $staleness = $this->stalenessFromDays($days);
            }

            $events[] = new ScoredEvent($claim->maintainer, Board::MAINTAINER, Action::PR_CLAIMED, $claim->claimedAt, $staleness);

            $bucket['ttr'][] = $claim->claimedAt->diffInHours($claim->firstReviewAt, false);
            $bucket['reviews']++;

            $perMaintainer[$claim->maintainer] = $bucket;
        }

        $stats = [];
        foreach ($perMaintainer as $login => $bucket) {
            $stats[$login] = [
                'median_time_to_review_hours' => $this->median($bucket['ttr']),
                'median_time_to_claim_days' => $this->median($bucket['ttc']),
                'reviews_in_window' => $bucket['reviews'],
            ];
        }

        return ['events' => $events, 'stats' => $stats];
    }

    /**
     * Reward grows ~+1 per 10× age (1 day → 1.0, 10 → 2.0, 100 → 3.0, 1000 → 4.0).
     * The scorer clamps to the configured impact bounds, so very old PRs cap out.
     */
    private function stalenessFromDays(float $days): float
    {
        return 1.0 + log10(max($days, 1.0));
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        $median = $count % 2 === 1
            ? $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;

        return round($median, 2);
    }
}
